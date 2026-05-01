<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Schema-healer auto-aplicado en boot. Verifica que las tablas/columnas
 * criticas para multi-canal e integraciones existan; si faltan, las anade.
 *
 * Idempotente: se ejecuta cada request pero las verificaciones son baratas
 * (SHOW TABLES / SELECT FROM information_schema). Resultado cacheado por
 * minuto en storage/cache para reducir el costo.
 */
final class Schema
{
    private const CACHE_FILE = '/cache/.schema_v2_ok';
    private const CACHE_TTL  = 600; // 10 minutos

    public static function ensure(): void
    {
        try {
            $cachePath = (string) Config::get('app.paths.storage') . self::CACHE_FILE;
            if (is_file($cachePath) && (time() - filemtime($cachePath)) < self::CACHE_TTL) {
                return;
            }

            $pdo = Database::connection();

            // Verificacion barata: si existe whatsapp_channels y la columna conversations.channel_id
            $tableOk = self::tableExists($pdo, 'whatsapp_channels');
            $colOk   = self::columnExists($pdo, 'conversations', 'channel_id');

            if ($tableOk && $colOk) {
                @file_put_contents($cachePath, '1');
                return;
            }

            self::applyMigration($pdo);
            @file_put_contents($cachePath, '1');
        } catch (\Throwable $e) {
            Logger::error('Schema::ensure fallo', ['msg' => $e->getMessage()]);
            // No lanzamos: la app debe seguir aunque la auto-migracion falle.
        }
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t"
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function columnExists(\PDO $pdo, string $table, string $col): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
        );
        $stmt->execute(['t' => $table, 'c' => $col]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function applyMigration(\PDO $pdo): void
    {
        Logger::info('Aplicando schema multichannel automaticamente');

        // ----- ALTERS idempotentes -----
        if (!self::columnExists($pdo, 'conversations', 'channel_id')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `channel`");
        }
        if (!self::columnExists($pdo, 'conversations', 'from_phone')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `from_phone` VARCHAR(40) NULL AFTER `channel_id`");
        }
        if (!self::indexExists($pdo, 'conversations', 'idx_conv_channel_id')) {
            $pdo->exec("ALTER TABLE `conversations` ADD KEY `idx_conv_channel_id` (`channel_id`)");
        }

        if (!self::columnExists($pdo, 'messages', 'channel_id')) {
            $pdo->exec("ALTER TABLE `messages` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `user_id`");
        }
        if (!self::columnExists($pdo, 'messages', 'from_phone')) {
            $pdo->exec("ALTER TABLE `messages` ADD COLUMN `from_phone` VARCHAR(40) NULL AFTER `channel_id`");
        }
        if (!self::indexExists($pdo, 'messages', 'idx_msg_channel_id')) {
            $pdo->exec("ALTER TABLE `messages` ADD KEY `idx_msg_channel_id` (`channel_id`)");
        }

        if (self::tableExists($pdo, 'whatsapp_logs')) {
            if (!self::columnExists($pdo, 'whatsapp_logs', 'channel_id')) {
                $pdo->exec("ALTER TABLE `whatsapp_logs` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `tenant_id`");
            }
            if (!self::columnExists($pdo, 'whatsapp_logs', 'provider')) {
                $pdo->exec("ALTER TABLE `whatsapp_logs` ADD COLUMN `provider` VARCHAR(40) NULL AFTER `channel_id`");
            }
        }

        // ----- Tablas nuevas -----
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `whatsapp_channels` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`           BIGINT UNSIGNED NOT NULL,
    `uuid`                CHAR(36) NOT NULL,
    `provider`            ENUM('wasapi','cloud','twilio','dialog360','custom') NOT NULL DEFAULT 'wasapi',
    `label`               VARCHAR(120) NOT NULL,
    `phone`               VARCHAR(40) NOT NULL,
    `display_name`        VARCHAR(120) NULL,
    `business_account_id` VARCHAR(120) NULL,
    `phone_number_id`     VARCHAR(120) NULL,
    `from_id`             VARCHAR(120) NULL,
    `api_key`             VARCHAR(500) NULL,
    `api_secret`          VARCHAR(500) NULL,
    `access_token`        TEXT NULL,
    `webhook_secret`      VARCHAR(255) NULL,
    `webhook_verify`      VARCHAR(255) NULL,
    `default_template`    VARCHAR(120) NULL,
    `daily_limit`         INT UNSIGNED NOT NULL DEFAULT 0,
    `messages_today`      INT UNSIGNED NOT NULL DEFAULT 0,
    `quality_rating`      ENUM('green','yellow','red','unknown') NOT NULL DEFAULT 'unknown',
    `messaging_limit_tier` VARCHAR(20) NULL,
    `status`              ENUM('active','disabled','pending','error') NOT NULL DEFAULT 'active',
    `is_default`          TINYINT(1) NOT NULL DEFAULT 0,
    `color`               VARCHAR(20) NOT NULL DEFAULT '#7C3AED',
    `icon`                VARCHAR(80) NULL,
    `last_health_check`   DATETIME NULL,
    `last_message_at`     DATETIME NULL,
    `error_message`       TEXT NULL,
    `settings`            JSON NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wac_uuid` (`uuid`),
    UNIQUE KEY `uk_wac_tenant_phone` (`tenant_id`,`phone`),
    KEY `idx_wac_tenant` (`tenant_id`),
    KEY `idx_wac_status` (`status`),
    KEY `idx_wac_provider` (`provider`),
    CONSTRAINT `fk_wac_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `integrations` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      BIGINT UNSIGNED NOT NULL,
    `slug`           VARCHAR(60) NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `category`       VARCHAR(60) NOT NULL DEFAULT 'general',
    `description`    VARCHAR(500) NULL,
    `icon`           VARCHAR(80) NULL,
    `is_enabled`     TINYINT(1) NOT NULL DEFAULT 0,
    `is_premium`     TINYINT(1) NOT NULL DEFAULT 0,
    `min_plan`       VARCHAR(40) NULL,
    `status`         ENUM('disconnected','connected','error','pending') NOT NULL DEFAULT 'disconnected',
    `config`         JSON NULL,
    `credentials`    JSON NULL,
    `last_sync_at`   DATETIME NULL,
    `last_error`     TEXT NULL,
    `webhook_url`    VARCHAR(500) NULL,
    `connected_at`   DATETIME NULL,
    `connected_by`   BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_int_tenant_slug` (`tenant_id`,`slug`),
    KEY `idx_int_tenant` (`tenant_id`),
    KEY `idx_int_status` (`status`),
    KEY `idx_int_category` (`category`),
    CONSTRAINT `fk_int_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `integration_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      BIGINT UNSIGNED NOT NULL,
    `integration_id` BIGINT UNSIGNED NULL,
    `slug`           VARCHAR(60) NULL,
    `event`          VARCHAR(80) NOT NULL,
    `direction`      ENUM('outbound','inbound','webhook','sync') NOT NULL DEFAULT 'sync',
    `request_body`   TEXT NULL,
    `response_body`  TEXT NULL,
    `status_code`    INT NULL,
    `success`        TINYINT(1) NOT NULL DEFAULT 0,
    `error_message`  TEXT NULL,
    `duration_ms`    INT UNSIGNED NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_il_tenant` (`tenant_id`),
    KEY `idx_il_integration` (`integration_id`),
    KEY `idx_il_event` (`event`),
    KEY `idx_il_created` (`created_at`),
    CONSTRAINT `fk_il_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_il_integration` FOREIGN KEY (`integration_id`) REFERENCES `integrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // ----- Channel Routing -----
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `channel_routing_rules` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      BIGINT UNSIGNED NOT NULL,
    `channel_id`     BIGINT UNSIGNED NULL,
    `name`           VARCHAR(120) NOT NULL,
    `priority`       INT NOT NULL DEFAULT 100,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `match_type`     ENUM('any','keyword','time','language','contact_tag','contact_score','channel') NOT NULL DEFAULT 'any',
    `match_value`    VARCHAR(500) NULL,
    `assign_strategy` ENUM('round_robin','least_busy','specific_user','team','ai_agent','keep') NOT NULL DEFAULT 'round_robin',
    `assign_user_id` BIGINT UNSIGNED NULL,
    `assign_role`    VARCHAR(60) NULL,
    `assign_ai_agent_id` BIGINT UNSIGNED NULL,
    `auto_reply_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `auto_tag`       VARCHAR(60) NULL,
    `auto_priority`  ENUM('low','normal','high','urgent') NULL,
    `business_hours` JSON NULL,
    `last_assigned_user_id` BIGINT UNSIGNED NULL,
    `executions_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_executed_at` DATETIME NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_crr_tenant` (`tenant_id`),
    KEY `idx_crr_channel` (`channel_id`),
    KEY `idx_crr_active` (`is_active`),
    CONSTRAINT `fk_crr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // ----- Backfill Wasapi -> canal default -----
        try {
            $pdo->exec(<<<SQL
INSERT INTO `whatsapp_channels` (
    `tenant_id`, `uuid`, `provider`, `label`, `phone`,
    `api_key`, `is_default`, `status`, `color`
)
SELECT t.id, UUID(), 'wasapi',
       CONCAT('Wasapi · ', COALESCE(NULLIF(t.wasapi_phone, ''), 'Numero principal')),
       COALESCE(NULLIF(t.wasapi_phone, ''), CONCAT('tenant-', t.id)),
       t.wasapi_api_key, 1, 'active', '#10B981'
FROM `tenants` t
WHERE t.wasapi_api_key IS NOT NULL
  AND t.wasapi_api_key <> ''
  AND NOT EXISTS (SELECT 1 FROM `whatsapp_channels` wc WHERE wc.tenant_id = t.id AND wc.provider = 'wasapi')
SQL);
        } catch (\Throwable) {}

        Logger::info('Schema multichannel aplicado correctamente');
    }

    private static function indexExists(\PDO $pdo, string $table, string $idx): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i"
        );
        $stmt->execute(['t' => $table, 'i' => $idx]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Borra el cache para forzar nueva verificacion (util tras deploy). */
    public static function invalidate(): void
    {
        $cachePath = (string) Config::get('app.paths.storage') . self::CACHE_FILE;
        @unlink($cachePath);
    }
}
