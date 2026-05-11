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
    private const CACHE_FILE = '/cache/.schema_v21_ok';
    private const CACHE_TTL  = 600; // 10 minutos

    public static function ensure(): void
    {
        try {
            $cachePath = (string) Config::get('app.paths.storage') . self::CACHE_FILE;
            if (is_file($cachePath) && (time() - filemtime($cachePath)) < self::CACHE_TTL) {
                return;
            }

            $pdo = Database::connection();

            // Verificacion barata: si existen TODAS las tablas/columnas criticas hasta v9
            $tableOk = self::tableExists($pdo, 'whatsapp_channels')
                    && self::tableExists($pdo, 'menu_items')
                    && self::tableExists($pdo, 'orders')
                    && self::tableExists($pdo, 'channel_routing_rules')
                    && self::tableExists($pdo, 'notification_destinations')
                    && self::tableExists($pdo, 'notification_logs');
            $colOk   = self::columnExists($pdo, 'conversations', 'channel_id')
                    && self::columnExists($pdo, 'tenants', 'is_restaurant')
                    && self::columnExists($pdo, 'conversations', 'cart_state')
                    && self::columnExists($pdo, 'tenants', 'ai_force_all')
                    && self::columnExists($pdo, 'tenants', 'public_menu_enabled')
                    && self::columnExists($pdo, 'tenants', 'ai_budget_usd')
                    && self::columnExists($pdo, 'tenants', 'ai_cost_used_period')
                    && self::columnExists($pdo, 'ai_logs', 'order_id')
                    && self::columnExists($pdo, 'ai_logs', 'message_id')
                    && self::columnExists($pdo, 'conversations', 'sales_state')
                    && self::columnExists($pdo, 'conversations', 'last_inbound_at')
                    && self::columnExists($pdo, 'conversations', 'last_outbound_at')
                    && self::tableExists($pdo, 'conversation_followups')
                    && self::columnExists($pdo, 'contacts', 'preferences')
                    && self::columnExists($pdo, 'contacts', 'rfm_segment')
                    && self::columnExists($pdo, 'contacts', 'lifetime_orders')
                    && self::tableExists($pdo, 'api_keys')
                    && self::tableExists($pdo, 'api_request_logs')
                    && self::tableExists($pdo, 'api_rate_limits')
                    && self::tableExists($pdo, 'agent_runs')
                    && self::tableExists($pdo, 'webhook_endpoints')
                    && self::tableExists($pdo, 'webhook_deliveries')
                    && self::tableExists($pdo, 'workflows')
                    && self::tableExists($pdo, 'workflow_steps')
                    && self::tableExists($pdo, 'workflow_runs')
                    && self::tableExists($pdo, 'workflow_run_steps')
                    && self::tableExists($pdo, 'user_2fa')
                    && self::tableExists($pdo, 'user_recovery_codes')
                    && self::tableExists($pdo, 'login_attempts')
                    && self::tableExists($pdo, 'user_sessions')
                    && self::tableExists($pdo, 'security_events')
                    && self::tableExists($pdo, 'workflow_templates')
                    && self::hasGlobalWorkflowTemplates($pdo)
                    && self::columnExists($pdo, 'tenants', 'onboarding_step')
                    && self::columnExists($pdo, 'tenants', 'onboarding_completed_at')
                    && self::columnExists($pdo, 'tenants', 'onboarding_skipped')
                    && self::columnExists($pdo, 'plans',   'api_quota_monthly')
                    && self::columnExists($pdo, 'tenants', 'api_calls_period')
                    && self::tableExists($pdo, 'alert_rules')
                    && self::tableExists($pdo, 'alert_history')
                    && self::tableExists($pdo, 'ai_agent_templates')
                    && self::hasSeededAgentTemplates($pdo);

            if ($tableOk && $colOk) {
                @file_put_contents($cachePath, '1');
                return;
            }

            self::applyMigration($pdo);

            // Re-verificar tras migrar. Solo escribimos el cache flag si todo quedo bien;
            // de lo contrario, el proximo request reintentara automaticamente.
            $okAfter = self::tableExists($pdo, 'notification_destinations')
                    && self::tableExists($pdo, 'notification_logs')
                    && self::columnExists($pdo, 'tenants', 'ai_budget_usd')
                    && self::columnExists($pdo, 'ai_logs', 'order_id')
                    && self::tableExists($pdo, 'conversation_followups')
                    && self::columnExists($pdo, 'conversations', 'sales_state')
                    && self::columnExists($pdo, 'contacts', 'preferences');
            if ($okAfter) {
                @file_put_contents($cachePath, '1');
            }
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

        // ----- Restaurante: menu, ordenes, zonas -----
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `menu_categories` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `icon`        VARCHAR(80) NULL,
    `description` VARCHAR(500) NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `available_from` TIME NULL,
    `available_to`   TIME NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mc_tenant` (`tenant_id`),
    UNIQUE KEY `uk_mc_tenant_slug` (`tenant_id`,`slug`),
    CONSTRAINT `fk_mc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NULL,
    `sku`         VARCHAR(60) NULL,
    `name`        VARCHAR(160) NOT NULL,
    `description` TEXT NULL,
    `price`       DECIMAL(10,2) NOT NULL DEFAULT 0,
    `compare_price` DECIMAL(10,2) NULL,
    `currency`    VARCHAR(3) NOT NULL DEFAULT 'USD',
    `photo`       VARCHAR(500) NULL,
    `prep_time_min` INT UNSIGNED NULL,
    `calories`    INT UNSIGNED NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_combo`    TINYINT(1) NOT NULL DEFAULT 0,
    `tags`        JSON NULL,
    `modifiers`   JSON NULL,
    `allergens`   VARCHAR(255) NULL,
    `stock`       INT NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mi_tenant` (`tenant_id`),
    KEY `idx_mi_cat` (`category_id`),
    KEY `idx_mi_avail` (`is_available`),
    CONSTRAINT `fk_mi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_cat`    FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `delivery_zones` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `fee`         DECIMAL(10,2) NOT NULL DEFAULT 0,
    `eta_min`     INT UNSIGNED NULL,
    `min_order`   DECIMAL(10,2) NULL,
    `area`        TEXT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dz_tenant` (`tenant_id`),
    CONSTRAINT `fk_dz_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `orders` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `code`        VARCHAR(40) NOT NULL,
    `contact_id`  BIGINT UNSIGNED NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `channel_id`  BIGINT UNSIGNED NULL,
    `created_by`  BIGINT UNSIGNED NULL,
    `customer_name`  VARCHAR(160) NULL,
    `customer_phone` VARCHAR(40) NULL,
    `delivery_type`  ENUM('delivery','pickup','dine_in') NOT NULL DEFAULT 'delivery',
    `delivery_zone_id` BIGINT UNSIGNED NULL,
    `delivery_address` TEXT NULL,
    `delivery_notes`   TEXT NULL,
    `kitchen_notes`    TEXT NULL,
    `status`      ENUM('new','confirmed','preparing','ready','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'new',
    `payment_method` ENUM('cash','card','transfer','online','other') NULL,
    `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `payment_link`   VARCHAR(500) NULL,
    `payment_ref`    VARCHAR(120) NULL,
    `subtotal`    DECIMAL(10,2) NOT NULL DEFAULT 0,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `tax`         DECIMAL(10,2) NOT NULL DEFAULT 0,
    `discount`    DECIMAL(10,2) NOT NULL DEFAULT 0,
    `tip`         DECIMAL(10,2) NOT NULL DEFAULT 0,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0,
    `currency`    VARCHAR(3) NOT NULL DEFAULT 'USD',
    `prep_time_min` INT UNSIGNED NULL,
    `scheduled_at` DATETIME NULL,
    `confirmed_at` DATETIME NULL,
    `ready_at`     DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancelled_reason` VARCHAR(255) NULL,
    `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `metadata`    JSON NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orders_tenant_code` (`tenant_id`,`code`),
    KEY `idx_orders_tenant` (`tenant_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_contact` (`contact_id`),
    KEY `idx_orders_created` (`created_at`),
    CONSTRAINT `fk_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orders_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`    BIGINT UNSIGNED NOT NULL,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `menu_item_id` BIGINT UNSIGNED NULL,
    `name`        VARCHAR(180) NOT NULL,
    `qty`         INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0,
    `subtotal`    DECIMAL(10,2) NOT NULL DEFAULT 0,
    `modifiers`   JSON NULL,
    `notes`       VARCHAR(500) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oi_order` (`order_id`),
    KEY `idx_oi_tenant` (`tenant_id`),
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `order_events` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `order_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NULL,
    `event`       VARCHAR(40) NOT NULL,
    `from_status` VARCHAR(40) NULL,
    `to_status`   VARCHAR(40) NULL,
    `note`        VARCHAR(500) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oe_order` (`order_id`),
    KEY `idx_oe_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Destinos de notificacion configurables por tenant para eventos de ordenes
        // (y, en el futuro, para tickets/leads). Multi-canal: email/slack/discord/teams/
        // telegram/webhook/whatsapp. Cada destino define a que eventos se suscribe.
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `notification_destinations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   BIGINT UNSIGNED NOT NULL,
    `type`        ENUM('email','slack','discord','teams','telegram','webhook','whatsapp') NOT NULL,
    `label`       VARCHAR(120) NOT NULL,
    `config`      JSON NOT NULL,
    `events`      JSON NOT NULL,
    `entity`      VARCHAR(40) NOT NULL DEFAULT 'order',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `last_used_at` DATETIME NULL,
    `last_status` VARCHAR(20) NULL,
    `last_error`  TEXT NULL,
    `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`  BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_nd_tenant` (`tenant_id`),
    KEY `idx_nd_type` (`type`),
    KEY `idx_nd_entity` (`entity`),
    KEY `idx_nd_active` (`is_active`),
    CONSTRAINT `fk_nd_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Log de envios de notificaciones para auditar y reintentos manuales
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      BIGINT UNSIGNED NOT NULL,
    `destination_id` BIGINT UNSIGNED NULL,
    `type`           VARCHAR(40) NOT NULL,
    `event`          VARCHAR(60) NOT NULL,
    `entity_type`    VARCHAR(40) NOT NULL,
    `entity_id`      BIGINT UNSIGNED NULL,
    `status`         ENUM('success','failed','queued') NOT NULL DEFAULT 'queued',
    `payload`        JSON NULL,
    `response`       TEXT NULL,
    `error`          TEXT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_nl_tenant` (`tenant_id`),
    KEY `idx_nl_dest` (`destination_id`),
    KEY `idx_nl_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Tenant flags para activar modo restaurante
        if (!self::columnExists($pdo, 'tenants', 'is_restaurant')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `is_restaurant` TINYINT(1) NOT NULL DEFAULT 0 AFTER `industry`");
        }
        if (!self::columnExists($pdo, 'tenants', 'restaurant_settings')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `restaurant_settings` JSON NULL AFTER `is_restaurant`");
        }

        // Carrito en curso por conversacion (persiste entre turnos del cliente)
        if (!self::columnExists($pdo, 'conversations', 'cart_state')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `cart_state` JSON NULL AFTER `from_phone`");
        }

        // Master switch IA "Autopilot Total" (responde TODAS las conversaciones,
        // ignorando bot_enabled per-conversacion). El operador puede pausar 5min
        // tomando manualmente una conversacion (ai_paused_until sigue respetado).
        if (!self::columnExists($pdo, 'tenants', 'ai_force_all')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `ai_force_all` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_enabled`");
        }

        // Activar/desactivar el menu publico (link compartible) por restaurante
        if (!self::columnExists($pdo, 'tenants', 'public_menu_enabled')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `public_menu_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_restaurant`");
        }

        // ============================================================
        //  v9 — Token economy: budgets en USD, alertas, atribucion ROI
        // ============================================================

        // Budget mensual en USD por tenant (NULL = sin cap, mantiene comportamiento previo)
        if (!self::columnExists($pdo, 'tenants', 'ai_budget_usd')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `ai_budget_usd` DECIMAL(10,4) NULL DEFAULT NULL AFTER `ai_token_period_starts_at`");
        }
        // Costo USD acumulado en el periodo (se resetea junto con ai_tokens_used_period)
        if (!self::columnExists($pdo, 'tenants', 'ai_cost_used_period')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `ai_cost_used_period` DECIMAL(12,6) NOT NULL DEFAULT 0 AFTER `ai_budget_usd`");
        }
        // Umbral en % para disparar alerta blanda (default 80%, configurable 50-95)
        if (!self::columnExists($pdo, 'tenants', 'ai_alert_threshold_pct')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `ai_alert_threshold_pct` TINYINT UNSIGNED NOT NULL DEFAULT 80 AFTER `ai_cost_used_period`");
        }
        // Timestamp de la ultima alerta enviada en el periodo (anti-spam)
        if (!self::columnExists($pdo, 'tenants', 'ai_budget_alerted_at')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `ai_budget_alerted_at` DATETIME NULL DEFAULT NULL AFTER `ai_alert_threshold_pct`");
        }

        // ai_logs: atribucion para ROI. Las columnas `cost` (DECIMAL 10,6) y
        // `duration_ms` ya existen desde 001_initial_schema.sql; las reusamos.
        // Solo agregamos message_id y order_id si faltan. conversation_id ya
        // existe pero la verificamos defensivamente.
        if (!self::columnExists($pdo, 'ai_logs', 'conversation_id')) {
            $pdo->exec("ALTER TABLE `ai_logs` ADD COLUMN `conversation_id` BIGINT UNSIGNED NULL AFTER `feature`");
        }
        if (!self::columnExists($pdo, 'ai_logs', 'message_id')) {
            $pdo->exec("ALTER TABLE `ai_logs` ADD COLUMN `message_id` BIGINT UNSIGNED NULL AFTER `conversation_id`");
        }
        if (!self::columnExists($pdo, 'ai_logs', 'order_id')) {
            $pdo->exec("ALTER TABLE `ai_logs` ADD COLUMN `order_id` BIGINT UNSIGNED NULL AFTER `message_id`");
        }
        // Indices para acelerar dashboard (tenant_id+created_at) y rollup por conversacion
        if (!self::indexExists($pdo, 'ai_logs', 'idx_ai_logs_tenant_created')) {
            try { $pdo->exec("CREATE INDEX `idx_ai_logs_tenant_created` ON `ai_logs` (`tenant_id`, `created_at`)"); } catch (\Throwable) {}
        }
        if (!self::indexExists($pdo, 'ai_logs', 'idx_ai_logs_tenant_conv')) {
            try { $pdo->exec("CREATE INDEX `idx_ai_logs_tenant_conv` ON `ai_logs` (`tenant_id`, `conversation_id`)"); } catch (\Throwable) {}
        }

        // ============================================================
        //  v10 — Sales agent autonomo: state machine + cart recovery + re-engagement
        // ============================================================

        // Estado de venta de la conversacion (greeting/qualifying/recommending/cart_building/confirming/closed/follow_up)
        if (!self::columnExists($pdo, 'conversations', 'sales_state')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `sales_state` VARCHAR(30) NULL DEFAULT 'greeting' AFTER `cart_state`");
        }
        if (!self::columnExists($pdo, 'conversations', 'sales_state_at')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `sales_state_at` DATETIME NULL DEFAULT NULL AFTER `sales_state`");
        }
        // Timestamps explicitos de ultima actividad para que el worker pueda
        // detectar carritos abandonados y clientes inactivos sin escanear messages.
        if (!self::columnExists($pdo, 'conversations', 'last_inbound_at')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `last_inbound_at` DATETIME NULL DEFAULT NULL AFTER `sales_state_at`");
        }
        if (!self::columnExists($pdo, 'conversations', 'last_outbound_at')) {
            $pdo->exec("ALTER TABLE `conversations` ADD COLUMN `last_outbound_at` DATETIME NULL DEFAULT NULL AFTER `last_inbound_at`");
        }

        // Tabla de seguimientos proactivos: cart-recovery y re-engagement enviados.
        // Sirve para anti-spam (no enviar dos cart-recoveries al mismo carrito) y
        // para auditoria/dashboard.
        if (!self::tableExists($pdo, 'conversation_followups')) {
            $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `conversation_followups` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`       BIGINT UNSIGNED NOT NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `contact_id`      BIGINT UNSIGNED NULL,
    `kind`            VARCHAR(40) NOT NULL,
    `cart_signature`  VARCHAR(64) NULL,
    `message`         TEXT NULL,
    `status`          VARCHAR(20) NOT NULL DEFAULT 'sent',
    `error_message`   VARCHAR(255) NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cf_tenant_kind_created` (`tenant_id`, `kind`, `created_at`),
    KEY `idx_cf_conversation` (`conversation_id`),
    KEY `idx_cf_contact` (`contact_id`),
    CONSTRAINT `fk_cf_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        // ============================================================
        //  v11 — Contact memory: perfil aprendido + RFM scoring
        // ============================================================

        // Perfil aprendido por contacto: items favoritos, alergias, patrones,
        // estilo de comunicacion. JSON libre para que ContactMemoryService
        // evolucione el shape sin migraciones.
        if (!self::columnExists($pdo, 'contacts', 'preferences')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `preferences` JSON NULL AFTER `custom_fields`");
        }
        // Notas libres que la IA escribe sobre el contacto (interpretacion qualitativa)
        if (!self::columnExists($pdo, 'contacts', 'notes_ai')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `notes_ai` TEXT NULL AFTER `preferences`");
        }
        // RFM segmentation: vip|regular|nuevo|dormido|perdido
        if (!self::columnExists($pdo, 'contacts', 'rfm_segment')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `rfm_segment` VARCHAR(20) NULL AFTER `notes_ai`");
        }
        // Score combinado RFM 0-1000 (mas alto = mejor cliente)
        if (!self::columnExists($pdo, 'contacts', 'rfm_score')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `rfm_score` INT UNSIGNED NULL AFTER `rfm_segment`");
        }
        // Estadisticas de vida del cliente (independientes del `score` generico de CRM)
        if (!self::columnExists($pdo, 'contacts', 'lifetime_orders')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `lifetime_orders` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rfm_score`");
        }
        if (!self::columnExists($pdo, 'contacts', 'lifetime_value')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `lifetime_value` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `lifetime_orders`");
        }
        if (!self::columnExists($pdo, 'contacts', 'last_order_at')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `last_order_at` DATETIME NULL AFTER `lifetime_value`");
        }
        // Cuando se recalculo el perfil aprendido por ultima vez (anti-thrashing)
        if (!self::columnExists($pdo, 'contacts', 'memory_updated_at')) {
            $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `memory_updated_at` DATETIME NULL AFTER `last_order_at`");
        }

        // Backfill: lifetime_orders, lifetime_value, last_order_at desde la
        // tabla orders existente. Idempotente vía COALESCE/zero defaults.
        try {
            $pdo->exec(<<<SQL
UPDATE contacts co
LEFT JOIN (
    SELECT contact_id, tenant_id,
           COUNT(*) AS cnt,
           SUM(total) AS total_sum,
           MAX(created_at) AS last_at
    FROM orders
    WHERE status NOT IN ('cancelled')
    GROUP BY contact_id, tenant_id
) s ON s.contact_id = co.id AND s.tenant_id = co.tenant_id
SET co.lifetime_orders = COALESCE(s.cnt, 0),
    co.lifetime_value  = COALESCE(s.total_sum, 0),
    co.last_order_at   = s.last_at
WHERE co.lifetime_orders = 0 AND co.last_order_at IS NULL AND s.cnt IS NOT NULL
SQL);
        } catch (\Throwable) {}

        // Indice para rankings y consultas RFM
        if (!self::indexExists($pdo, 'contacts', 'idx_contacts_tenant_segment')) {
            try { $pdo->exec("CREATE INDEX `idx_contacts_tenant_segment` ON `contacts` (`tenant_id`, `rfm_segment`)"); } catch (\Throwable) {}
        }

        // Backfill: para conversaciones existentes, derivar last_inbound_at /
        // last_outbound_at desde la tabla messages. Solo correr si hay nulls
        // (idempotente y barato gracias al WHERE IS NULL).
        try {
            $pdo->exec(<<<SQL
UPDATE conversations c
LEFT JOIN (
    SELECT conversation_id, MAX(created_at) AS last_at
    FROM messages WHERE direction = 'inbound'
    GROUP BY conversation_id
) m ON m.conversation_id = c.id
SET c.last_inbound_at = m.last_at
WHERE c.last_inbound_at IS NULL AND m.last_at IS NOT NULL
SQL);
            $pdo->exec(<<<SQL
UPDATE conversations c
LEFT JOIN (
    SELECT conversation_id, MAX(created_at) AS last_at
    FROM messages WHERE direction = 'outbound'
    GROUP BY conversation_id
) m ON m.conversation_id = c.id
SET c.last_outbound_at = m.last_at
WHERE c.last_outbound_at IS NULL AND m.last_at IS NOT NULL
SQL);
        } catch (\Throwable) {}

        // ----- Backfill leads desde ordenes existentes (sincronizacion CRM) -----
        try {
            $orders = $pdo->query(
                "SELECT o.id, o.tenant_id
                 FROM orders o
                 LEFT JOIN leads l ON l.tenant_id = o.tenant_id
                                   AND l.deleted_at IS NULL
                                   AND l.description LIKE CONCAT('%[ORDER:', o.code, ']%')
                 WHERE l.id IS NULL
                 LIMIT 200"
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($orders as $o) {
                try {
                    (new \App\Services\LeadSyncService((int) $o['tenant_id']))->ensureRestaurantStages();
                    (new \App\Services\LeadSyncService((int) $o['tenant_id']))->syncOrderToLead((int) $o['id']);
                } catch (\Throwable) {}
            }
        } catch (\Throwable) {}

        // ----- Cleanup nombres genericos de agentes IA: usar nombre del owner -----
        try {
            $pdo->exec(<<<SQL
UPDATE ai_agents aa
INNER JOIN (
    SELECT u.tenant_id, u.first_name AS owner_name
    FROM users u
    INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE u.deleted_at IS NULL AND u.is_active = 1
      AND r.slug IN ('owner','admin')
    GROUP BY u.tenant_id
) o ON o.tenant_id = aa.tenant_id
SET aa.name = o.owner_name
WHERE LOWER(TRIM(aa.name)) IN (
    'soporte','soporte tecnico','soporte técnico',
    'asistente','asistente ia','asistente virtual',
    'bot','agente','agente ia',
    'servicio al cliente','atencion al cliente','atención al cliente',
    'mesero','mesera','operador','operadora',
    'vendedor','vendedora','ia','ai'
) AND o.owner_name IS NOT NULL AND o.owner_name <> ''
SQL);
        } catch (\Throwable) {}

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

        // ---------------------------------------------------------------
        // API publica + Agent-as-a-Service (migration 008)
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `created_by`    INT UNSIGNED NULL,
    `name`          VARCHAR(120) NOT NULL,
    `prefix`        VARCHAR(16) NOT NULL,
    `key_hash`      CHAR(64) NOT NULL,
    `last4`         CHAR(4) NOT NULL,
    `scopes`        JSON NULL,
    `allowed_ips`   JSON NULL,
    `last_used_at`  DATETIME NULL,
    `last_used_ip`  VARCHAR(64) NULL,
    `expires_at`    DATETIME NULL,
    `revoked_at`    DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_key_hash` (`key_hash`),
    KEY `idx_tenant` (`tenant_id`),
    KEY `idx_prefix` (`prefix`),
    KEY `idx_active` (`tenant_id`, `revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `api_request_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_key_id`    BIGINT UNSIGNED NULL,
    `tenant_id`     INT UNSIGNED NULL,
    `method`        VARCHAR(8) NOT NULL,
    `endpoint`      VARCHAR(255) NOT NULL,
    `status_code`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `latency_ms`    INT UNSIGNED NOT NULL DEFAULT 0,
    `ip`            VARCHAR(64) NULL,
    `user_agent`    VARCHAR(255) NULL,
    `request_id`    CHAR(36) NULL,
    `bytes_in`      INT UNSIGNED NOT NULL DEFAULT 0,
    `bytes_out`     INT UNSIGNED NOT NULL DEFAULT 0,
    `error`         VARCHAR(255) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_key_time` (`api_key_id`, `created_at`),
    KEY `idx_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_hash`      CHAR(64) NOT NULL,
    `bucket`        VARCHAR(64) NOT NULL,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`    DATETIME NOT NULL,
    UNIQUE KEY `uniq_bucket` (`key_hash`, `bucket`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `agent_runs` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `agent_id`        BIGINT UNSIGNED NOT NULL,
    `api_key_id`      BIGINT UNSIGNED NULL,
    `contact_id`      BIGINT UNSIGNED NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `channel`         VARCHAR(32) NOT NULL DEFAULT 'api',
    `status`          ENUM('queued','running','succeeded','failed','timeout') NOT NULL DEFAULT 'queued',
    `input`           MEDIUMTEXT NULL,
    `output`          MEDIUMTEXT NULL,
    `actions`         JSON NULL,
    `metadata`        JSON NULL,
    `tokens_in`       INT UNSIGNED NOT NULL DEFAULT 0,
    `tokens_out`      INT UNSIGNED NOT NULL DEFAULT 0,
    `cost_usd`        DECIMAL(10,6) NOT NULL DEFAULT 0,
    `latency_ms`      INT UNSIGNED NOT NULL DEFAULT 0,
    `error`           TEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`    DATETIME NULL,
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_agent` (`agent_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `agent_skills` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NULL,
    `slug`          VARCHAR(64) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `description`   TEXT NULL,
    `system_prompt` MEDIUMTEXT NULL,
    `tools`         JSON NULL,
    `config`        JSON NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `agent_skill_links` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `agent_id`      BIGINT UNSIGNED NOT NULL,
    `skill_id`      BIGINT UNSIGNED NOT NULL,
    `priority`      SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `config`        JSON NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_agent_skill` (`agent_id`, `skill_id`),
    KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Seed de skills globales. INSERT IGNORE NO funciona con tenant_id NULL
        // (NULL != NULL en UNIQUE constraints de MySQL). Usamos INSERT...SELECT
        // WHERE NOT EXISTS para garantizar idempotencia real.
        $pdo->exec(<<<SQL
INSERT INTO `agent_skills` (`tenant_id`, `slug`, `name`, `description`, `tools`, `is_active`)
SELECT * FROM (
    SELECT NULL AS tenant_id, 'sales'        AS slug, 'Ventas'                AS name, 'Calificacion de leads, recomendacion y cierre.'    AS description, JSON_ARRAY('query_catalog','create_order','escalate')   AS tools, 1 AS is_active UNION ALL
    SELECT NULL, 'support',      'Soporte al cliente',    'Resuelve dudas, abre tickets y escala humanos.',      JSON_ARRAY('query_kb','create_ticket','escalate'),       1 UNION ALL
    SELECT NULL, 'cart_recover', 'Recuperacion carrito',  'Re-engagement de clientes con carrito abandonado.',   JSON_ARRAY('send_message','apply_discount','escalate'),  1 UNION ALL
    SELECT NULL, 'scheduling',   'Agendamiento',          'Reserva citas y bloques de tiempo.',                  JSON_ARRAY('check_availability','book_slot','escalate'), 1 UNION ALL
    SELECT NULL, 'collections',  'Cobranza',              'Recordatorios de pago y reconciliacion.',             JSON_ARRAY('query_invoice','send_reminder','escalate'),  1
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `agent_skills` s
    WHERE s.`tenant_id` IS NULL AND s.`slug` = seed.slug
)
SQL);

        // ---------------------------------------------------------------
        // Webhooks salientes con HMAC (migration 009)
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`        INT UNSIGNED NOT NULL,
    `created_by`       INT UNSIGNED NULL,
    `name`             VARCHAR(120) NOT NULL,
    `url`              VARCHAR(500) NOT NULL,
    `secret`           VARCHAR(120) NOT NULL,
    `events`           JSON NULL,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `description`      VARCHAR(500) NULL,
    `headers`          JSON NULL,
    `last_delivery_at` DATETIME NULL,
    `last_status`      SMALLINT UNSIGNED NULL,
    `last_error`       VARCHAR(500) NULL,
    `success_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_tenant_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `endpoint_id`     BIGINT UNSIGNED NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `event`           VARCHAR(80) NOT NULL,
    `payload`         MEDIUMTEXT NOT NULL,
    `signature`       VARCHAR(255) NULL,
    `status`          ENUM('pending','delivered','failed','dead') NOT NULL DEFAULT 'pending',
    `attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `next_retry_at`   DATETIME NULL,
    `response_code`   SMALLINT UNSIGNED NULL,
    `response_body`   TEXT NULL,
    `latency_ms`      INT UNSIGNED NULL,
    `error`           VARCHAR(500) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivered_at`    DATETIME NULL,
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_endpoint_status` (`endpoint_id`, `status`),
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_retry` (`status`, `next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // ---------------------------------------------------------------
        // Workflow engine OS-style v2 (migration 010)
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `workflows` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `name`            VARCHAR(120) NOT NULL,
    `description`     VARCHAR(500) NULL,
    `trigger_type`    ENUM('event','schedule','webhook','manual') NOT NULL DEFAULT 'event',
    `trigger_config`  JSON NULL,
    `webhook_token`   VARCHAR(48) NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `runs_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_run_at`     DATETIME NULL,
    `next_run_at`     DATETIME NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_webhook_token` (`webhook_token`),
    KEY `idx_tenant_active` (`tenant_id`, `is_active`),
    KEY `idx_trigger_type`  (`trigger_type`, `is_active`),
    KEY `idx_next_run`      (`is_active`, `next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `workflow_steps` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workflow_id`    BIGINT UNSIGNED NOT NULL,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `step_key`       VARCHAR(40) NOT NULL,
    `type`           ENUM('action','branch','delay','set_var','end') NOT NULL DEFAULT 'action',
    `order_index`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `config`         JSON NULL,
    `next_step_key`  VARCHAR(40) NULL,
    `branch_yes`     VARCHAR(40) NULL,
    `branch_no`      VARCHAR(40) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_wf_key` (`workflow_id`, `step_key`),
    KEY `idx_workflow_order` (`workflow_id`, `order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `workflow_runs` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `workflow_id`     BIGINT UNSIGNED NOT NULL,
    `trigger_type`    VARCHAR(20) NOT NULL,
    `status`          ENUM('queued','running','waiting','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
    `current_step_key` VARCHAR(40) NULL,
    `wait_until`      DATETIME NULL,
    `context`         JSON NULL,
    `error`           TEXT NULL,
    `started_at`      DATETIME NULL,
    `finished_at`     DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_resume` (`status`, `wait_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `workflow_run_steps` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id`       BIGINT UNSIGNED NOT NULL,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `step_key`     VARCHAR(40) NOT NULL,
    `step_type`    VARCHAR(20) NOT NULL,
    `status`       ENUM('running','succeeded','failed','skipped') NOT NULL DEFAULT 'running',
    `input`        JSON NULL,
    `output`       JSON NULL,
    `error`        TEXT NULL,
    `latency_ms`   INT UNSIGNED NULL,
    `started_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`  DATETIME NULL,
    KEY `idx_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // ---------------------------------------------------------------
        // Capa de seguridad (migration 011): 2FA, lockout, sessions, events
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_2fa` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `secret`          VARCHAR(64) NOT NULL,
    `enabled`         TINYINT(1) NOT NULL DEFAULT 0,
    `enabled_at`      DATETIME NULL,
    `last_used_code`  VARCHAR(8) NULL,
    `last_used_at`    DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `code_hash`  CHAR(64) NOT NULL,
    `used_at`    DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user` (`user_id`, `used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(160) NOT NULL,
    `ip`         VARCHAR(64) NOT NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `success`    TINYINT(1) NOT NULL DEFAULT 0,
    `reason`     VARCHAR(60) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_email_time` (`email`, `created_at`),
    KEY `idx_ip_time`    (`ip`, `created_at`),
    KEY `idx_user_time`  (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NULL,
    `session_id`    CHAR(64) NOT NULL,
    `ip`            VARCHAR(64) NULL,
    `user_agent`    VARCHAR(255) NULL,
    `last_seen_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at`    DATETIME NULL,
    UNIQUE KEY `uniq_session` (`session_id`),
    KEY `idx_user_active` (`user_id`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `security_events` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `event`      VARCHAR(60) NOT NULL,
    `severity`   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `ip`         VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `metadata`   JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_user_time`   (`user_id`, `created_at`),
    KEY `idx_event`       (`event`, `created_at`),
    KEY `idx_severity`    (`severity`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // ---------------------------------------------------------------
        // Workflow templates marketplace (migration 012)
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `workflow_templates` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NULL,
    `slug`         VARCHAR(64) NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `description`  VARCHAR(500) NULL,
    `category`     VARCHAR(40) NOT NULL DEFAULT 'general',
    `icon`         VARCHAR(8) NOT NULL DEFAULT '🪄',
    `definition`   MEDIUMTEXT NOT NULL,
    `requires`     JSON NULL,
    `clone_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active_category` (`is_active`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        self::seedGlobalWorkflowTemplates($pdo);

        // ---------------------------------------------------------------
        // Onboarding wizard (migration 013): columnas en tenants
        // ---------------------------------------------------------------
        if (!self::columnExists($pdo, 'tenants', 'onboarding_step')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `onboarding_step` TINYINT UNSIGNED NOT NULL DEFAULT 0");
        }
        if (!self::columnExists($pdo, 'tenants', 'onboarding_completed_at')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `onboarding_completed_at` DATETIME NULL");
        }
        if (!self::columnExists($pdo, 'tenants', 'onboarding_skipped')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `onboarding_skipped` TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Marcar tenants existentes (con datos) como "ya completados" para que
        // el wizard solo se dispare con tenants nuevos.
        $pdo->exec(
            "UPDATE `tenants` SET `onboarding_completed_at` = NOW(), `onboarding_step` = 5
             WHERE `onboarding_completed_at` IS NULL
               AND `created_at` < DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );

        // ---------------------------------------------------------------
        // API quotas por plan + tracking mensual (migration 014)
        // ---------------------------------------------------------------
        if (!self::columnExists($pdo, 'plans', 'api_quota_monthly')) {
            $pdo->exec("ALTER TABLE `plans` ADD COLUMN `api_quota_monthly` INT NOT NULL DEFAULT 1000");
        }
        if (!self::columnExists($pdo, 'tenants', 'api_quota_override')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `api_quota_override` INT NULL");
        }
        if (!self::columnExists($pdo, 'tenants', 'api_calls_period')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `api_calls_period` INT UNSIGNED NOT NULL DEFAULT 0");
        }
        if (!self::columnExists($pdo, 'tenants', 'api_period_starts_at')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `api_period_starts_at` DATETIME NULL");
        }
        if (!self::columnExists($pdo, 'tenants', 'api_quota_alerted_at')) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `api_quota_alerted_at` DATETIME NULL");
        }

        // Seed razonable por slug si los planes existen
        $pdo->exec("UPDATE `plans` SET `api_quota_monthly` = 1000   WHERE `slug` = 'free'       AND `api_quota_monthly` = 0");
        $pdo->exec("UPDATE `plans` SET `api_quota_monthly` = 50000  WHERE `slug` = 'basic'      AND `api_quota_monthly` IN (0, 1000)");
        $pdo->exec("UPDATE `plans` SET `api_quota_monthly` = 250000 WHERE `slug` = 'pro'        AND `api_quota_monthly` IN (0, 1000)");
        $pdo->exec("UPDATE `plans` SET `api_quota_monthly` = -1     WHERE `slug` = 'enterprise' AND `api_quota_monthly` IN (0, 1000)");

        // ---------------------------------------------------------------
        // Alert rules + history (migration 015)
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `alert_rules` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`        INT UNSIGNED NULL,
    `slug`             VARCHAR(64) NOT NULL,
    `name`             VARCHAR(120) NOT NULL,
    `description`      VARCHAR(500) NULL,
    `rule_type`        VARCHAR(40) NOT NULL,
    `config`           JSON NULL,
    `severity`         ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    `cooldown_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `last_triggered_at` DATETIME NULL,
    `trigger_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active_type` (`is_active`, `rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `alert_history` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `rule_id`       BIGINT UNSIGNED NULL,
    `rule_slug`     VARCHAR(64) NOT NULL,
    `severity`      ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    `title`         VARCHAR(255) NOT NULL,
    `body`          MEDIUMTEXT NULL,
    `metadata`      JSON NULL,
    `destinations_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `delivered_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_rule_slug`   (`rule_slug`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Seed reglas builtin. INSERT...SELECT WHERE NOT EXISTS para idempotencia
        // real (INSERT IGNORE NO funciona con tenant_id NULL en MySQL).
        $pdo->exec(<<<SQL
INSERT INTO `alert_rules` (`tenant_id`, `slug`, `name`, `description`, `rule_type`, `config`, `severity`, `cooldown_minutes`)
SELECT * FROM (
    SELECT NULL AS tenant_id, 'api.quota.80'      AS slug, 'API: cuota al 80%'             AS name, 'Te avisa cuando consumiste el 80% de tu cuota mensual de API.'    AS description, 'api.quota.threshold' AS rule_type, JSON_OBJECT('pct', 80)  AS config, 'warning'  AS severity, 720 AS cooldown_minutes UNION ALL
    SELECT NULL, 'api.quota.100',     'API: cuota agotada',             'Te avisa cuando llegaste al 100% de tu cuota mensual. Requests siguientes seran 429.', 'api.quota.threshold', JSON_OBJECT('pct', 100), 'critical', 120 UNION ALL
    SELECT NULL, 'webhook.dead',      'Webhooks: entregas muertas',     '>=5 deliveries marcadas como dead en las ultimas 24h en algun endpoint.',               'webhook.dead.count',  JSON_OBJECT('threshold', 5, 'window_hours', 24), 'warning', 360 UNION ALL
    SELECT NULL, 'agent.error_rate',  'Agentes IA: alta tasa de error', 'Mas del 20% de los runs de agentes IA fallaron en las ultimas 24h.',                    'agent.error_rate',    JSON_OBJECT('pct', 20, 'min_runs', 10),          'warning', 360 UNION ALL
    SELECT NULL, 'security.critical', 'Seguridad: evento critico',      'Cualquier security_event con severidad=critical.',                                      'security.critical',   JSON_OBJECT(),                                   'critical', 30 UNION ALL
    SELECT NULL, 'workflow.failed',   'Workflow: ejecucion fallida',    'Avisa cuando un workflow termina con status=failed.',                                   'workflow.failed',     JSON_OBJECT('window_minutes', 60),               'warning', 60
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `alert_rules` r WHERE r.`tenant_id` IS NULL AND r.`slug` = seed.slug
)
SQL);

        // ---------------------------------------------------------------
        // AI Agent Templates (migration 016) - wizard de creacion no-tecnica
        // ---------------------------------------------------------------
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `ai_agent_templates` (
    `id`              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `slug`            VARCHAR(80)  NOT NULL UNIQUE,
    `name`            VARCHAR(120) NOT NULL,
    `category`        VARCHAR(40)  NOT NULL DEFAULT 'generic',
    `icon`            VARCHAR(8)   NOT NULL DEFAULT '🤖',
    `accent_color`    VARCHAR(16)  NOT NULL DEFAULT '#8B5CF6',
    `description`     VARCHAR(255) NOT NULL DEFAULT '',
    `instructions_template` MEDIUMTEXT NOT NULL,
    `default_role`             VARCHAR(160) DEFAULT NULL,
    `default_objective`        TEXT         DEFAULT NULL,
    `default_tone`             VARCHAR(120) DEFAULT 'profesional, cercano y orientado a resolver',
    `default_trigger_keywords` JSON         DEFAULT NULL,
    `default_transfer_keywords` JSON        DEFAULT NULL,
    `default_channels`         JSON         DEFAULT NULL,
    `default_priority`         INT NOT NULL DEFAULT 100,
    `default_max_retries`      INT NOT NULL DEFAULT 3,
    `default_avatar_emoji`     VARCHAR(8)   DEFAULT NULL,
    `questions`       JSON NOT NULL,
    `is_active`       TINYINT      NOT NULL DEFAULT 1,
    `display_order`   INT          NOT NULL DEFAULT 100,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_active_order` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (!self::hasSeededAgentTemplates($pdo)) {
            self::seedAgentTemplates($pdo);
        }

        Logger::info('Schema multichannel aplicado correctamente');
    }

    /** Devuelve true si ya hay al menos 1 template de agente IA activo. */
    private static function hasSeededAgentTemplates(\PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `ai_agent_templates` WHERE `is_active` = 1");
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Seed de 5 plantillas builtin para el wizard de creacion de agentes IA.
     * Idempotente via UNIQUE(slug) + INSERT IGNORE.
     */
    private static function seedAgentTemplates(\PDO $pdo): void
    {
        $templates = self::agentTemplateCatalog();
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO `ai_agent_templates`
             (`slug`,`name`,`category`,`icon`,`accent_color`,`description`,
              `instructions_template`,`default_role`,`default_objective`,`default_tone`,
              `default_trigger_keywords`,`default_transfer_keywords`,`default_channels`,
              `default_priority`,`default_avatar_emoji`,`questions`,`display_order`)
             VALUES
             (:slug,:name,:category,:icon,:accent_color,:description,
              :instructions_template,:default_role,:default_objective,:default_tone,
              :default_trigger_keywords,:default_transfer_keywords,:default_channels,
              :default_priority,:default_avatar_emoji,:questions,:display_order)"
        );
        foreach ($templates as $t) {
            $stmt->execute([
                'slug'                  => $t['slug'],
                'name'                  => $t['name'],
                'category'              => $t['category'],
                'icon'                  => $t['icon'],
                'accent_color'          => $t['accent_color'],
                'description'           => $t['description'],
                'instructions_template' => $t['instructions_template'],
                'default_role'          => $t['default_role'],
                'default_objective'     => $t['default_objective'],
                'default_tone'          => $t['default_tone'],
                'default_trigger_keywords'  => json_encode($t['default_trigger_keywords'],  JSON_UNESCAPED_UNICODE),
                'default_transfer_keywords' => json_encode($t['default_transfer_keywords'], JSON_UNESCAPED_UNICODE),
                'default_channels'      => json_encode($t['default_channels'], JSON_UNESCAPED_UNICODE),
                'default_priority'      => $t['default_priority'],
                'default_avatar_emoji'  => $t['default_avatar_emoji'],
                'questions'             => json_encode($t['questions'], JSON_UNESCAPED_UNICODE),
                'display_order'         => $t['display_order'],
            ]);
        }
    }

    private static function agentTemplateCatalog(): array
    {
        return [
            [
                'slug' => 'vendedor', 'name' => 'Vendedor IA', 'category' => 'sales',
                'icon' => '💰', 'accent_color' => '#10B981',
                'description' => 'Atiende leads, presenta tu catalogo, responde precios y cierra ventas por WhatsApp.',
                'instructions_template' => "Eres un vendedor consultivo de {{negocio}}. Tu objetivo es entender que necesita el cliente y cerrar la venta.\n\nQUE VENDES:\n{{que_vendes}}\n\nINFORMACION QUE DEBES PEDIR ANTES DE CERRAR:\n{{info_pedir}}\n\nCUANDO ESCALAR A UN HUMANO:\n{{escalar_cuando}}\n\nREGLAS:\n- Saluda por el nombre del cliente cuando lo sepas.\n- Si el cliente pregunta precio, dilo de una vez (no des rodeos).\n- Si pregunta por algo que no vendemos, sugiere lo mas parecido.\n- Cuando el cliente diga \"quiero\", \"comprar\", \"ordenar\" o similar, empieza el flujo de cierre.\n- No inventes promociones ni descuentos que no existen.",
                'default_role' => 'Vendedor consultivo',
                'default_objective' => 'Convertir leads en ventas confirmadas',
                'default_tone' => 'profesional, cercano, orientado a vender sin presionar',
                'default_trigger_keywords' => ['precio','cuanto cuesta','comprar','ordenar','pedido','factura','cotizacion','tarifa','catalogo','plan'],
                'default_transfer_keywords' => ['humano','agente real','hablar con persona','queja','demanda','reclamo','gerente'],
                'default_channels' => ['whatsapp','webchat'],
                'default_priority' => 100, 'default_avatar_emoji' => '💰',
                'questions' => [
                    ['key'=>'negocio','label'=>'¿Como se llama tu negocio?','placeholder'=>'Pizzeria La Bella','type'=>'text','required'=>true],
                    ['key'=>'que_vendes','label'=>'¿Que vendes? (productos o servicios principales)','placeholder'=>'Pizzas artesanales, pastas, postres y bebidas. Delivery y pickup.','type'=>'textarea','required'=>true],
                    ['key'=>'info_pedir','label'=>'¿Que datos debes pedirle al cliente antes de cerrar la venta?','placeholder'=>'Nombre, telefono, direccion, metodo de pago','type'=>'textarea','required'=>true],
                    ['key'=>'escalar_cuando','label'=>'¿En que casos debes pasar el chat a un humano?','placeholder'=>'Reclamos, pedidos personalizados grandes, problemas con orden previa','type'=>'textarea','required'=>true],
                ],
                'display_order' => 10,
            ],
            [
                'slug' => 'soporte', 'name' => 'Soporte IA', 'category' => 'support',
                'icon' => '🎧', 'accent_color' => '#06B6D4',
                'description' => 'Responde preguntas frecuentes, ayuda con problemas comunes y filtra solo lo urgente al equipo humano.',
                'instructions_template' => "Eres un agente de soporte de {{negocio}}. Resuelves dudas y problemas comunes sin que el cliente tenga que esperar.\n\nPREGUNTAS Y PROBLEMAS MAS COMUNES QUE DEBES SABER RESOLVER:\n{{problemas_comunes}}\n\nINFORMACION QUE DEBES PEDIR PARA AYUDAR:\n{{info_pedir}}\n\nCUANDO ESCALAR A UN HUMANO:\n{{escalar_cuando}}\n\nREGLAS:\n- Si el cliente esta molesto o frustrado, validas primero (\"entiendo tu frustracion\") antes de resolver.\n- Si no sabes la respuesta, no inventes. Dile que un agente humano lo contactara.\n- Confirma siempre que el problema quedo resuelto antes de cerrar.",
                'default_role' => 'Soporte tecnico / atencion al cliente',
                'default_objective' => 'Resolver dudas y problemas comunes sin escalar a humano',
                'default_tone' => 'empatico, paciente, claro y resolutivo',
                'default_trigger_keywords' => ['ayuda','problema','no funciona','error','duda','consulta','como','soporte','reclamo','queja'],
                'default_transfer_keywords' => ['humano','agente real','hablar con persona','demanda','gerente','urgente'],
                'default_channels' => ['whatsapp','email','webchat'],
                'default_priority' => 100, 'default_avatar_emoji' => '🎧',
                'questions' => [
                    ['key'=>'negocio','label'=>'¿Como se llama tu negocio?','placeholder'=>'Pizzeria La Bella','type'=>'text','required'=>true],
                    ['key'=>'problemas_comunes','label'=>'¿Cuales son los problemas mas comunes y como se resuelven?','placeholder'=>"- No llego mi pedido: revisar status y avisar tiempo estimado.\n- Quiero cambiar mi orden: si lleva menos de 5 min, se puede.\n- ¿Aceptan tarjeta?: si, Visa/MC.",'type'=>'textarea','required'=>true],
                    ['key'=>'info_pedir','label'=>'¿Que datos debes pedirle al cliente para ayudarlo?','placeholder'=>'Numero de orden, telefono, descripcion del problema','type'=>'textarea','required'=>true],
                    ['key'=>'escalar_cuando','label'=>'¿En que casos debes pasar el chat a un humano?','placeholder'=>'Reclamos por dinero, problemas graves, clientes muy molestos, casos sin resolver despues de 3 intentos','type'=>'textarea','required'=>true],
                ],
                'display_order' => 20,
            ],
            [
                'slug' => 'agendador', 'name' => 'Agendador IA', 'category' => 'scheduling',
                'icon' => '📅', 'accent_color' => '#F59E0B',
                'description' => 'Reserva citas, mesas o turnos. Confirma disponibilidad y envia recordatorios automaticos.',
                'instructions_template' => "Eres el agendador de {{negocio}}. Tu unica mision es ayudar al cliente a reservar.\n\nQUE SE PUEDE AGENDAR:\n{{que_agendar}}\n\nHORARIOS DISPONIBLES:\n{{horarios}}\n\nINFORMACION QUE DEBES PEDIR PARA LA RESERVA:\n{{info_pedir}}\n\nCUANDO ESCALAR A UN HUMANO:\n{{escalar_cuando}}\n\nREGLAS:\n- Confirma siempre fecha + hora antes de cerrar.\n- Si el horario que pide no esta disponible, ofrece 2 alternativas cercanas.\n- Recuerda al cliente la politica de cancelacion si aplica.",
                'default_role' => 'Recepcionista / agendamiento',
                'default_objective' => 'Convertir consultas en reservas confirmadas',
                'default_tone' => 'amable, ordenado y eficiente',
                'default_trigger_keywords' => ['cita','reserva','agendar','reservar','turno','disponibilidad','horario','dia','fecha','mesa'],
                'default_transfer_keywords' => ['humano','agente real','hablar con persona','queja','urgente','cambiar reserva'],
                'default_channels' => ['whatsapp','webchat'],
                'default_priority' => 100, 'default_avatar_emoji' => '📅',
                'questions' => [
                    ['key'=>'negocio','label'=>'¿Como se llama tu negocio?','placeholder'=>'Restaurante La Casa','type'=>'text','required'=>true],
                    ['key'=>'que_agendar','label'=>'¿Que tipo de reservas manejas?','placeholder'=>'Mesas para 2-12 personas, eventos privados, reservas grupales','type'=>'textarea','required'=>true],
                    ['key'=>'horarios','label'=>'¿Cuales son tus horarios disponibles?','placeholder'=>'Lunes a Domingo 12pm-11pm. Domingos solo almuerzo hasta 4pm.','type'=>'textarea','required'=>true],
                    ['key'=>'info_pedir','label'=>'¿Que datos pides para confirmar una reserva?','placeholder'=>'Nombre, telefono, cantidad de personas, fecha y hora preferida','type'=>'textarea','required'=>true],
                    ['key'=>'escalar_cuando','label'=>'¿En que casos debes pasar el chat a un humano?','placeholder'=>'Eventos privados, reservas de 10+ personas, peticiones especiales (cumpleanos, alergias)','type'=>'textarea','required'=>true],
                ],
                'display_order' => 30,
            ],
            [
                'slug' => 'cobrador', 'name' => 'Cobrador IA', 'category' => 'collections',
                'icon' => '💳', 'accent_color' => '#EF4444',
                'description' => 'Recuerda pagos pendientes, negocia plazos y procesa cobros sin sonar agresivo.',
                'instructions_template' => "Eres el agente de cobranza de {{negocio}}. Cobras de forma profesional, sin presionar ni amenazar.\n\nMETODOS DE PAGO ACEPTADOS:\n{{metodos_pago}}\n\nQUE PUEDES OFRECER AL DEUDOR:\n{{opciones_pago}}\n\nINFORMACION QUE DEBES CONFIRMAR ANTES DE CERRAR:\n{{info_pedir}}\n\nCUANDO ESCALAR A UN HUMANO:\n{{escalar_cuando}}\n\nREGLAS:\n- Nunca uses amenazas legales ni lenguaje agresivo.\n- Si el deudor menciona problemas economicos, ofrece plan de pagos antes de presionar.\n- Confirma siempre con un comprobante o referencia de pago.\n- Si el deudor rechaza pagar, pasa a humano sin discutir.",
                'default_role' => 'Cobranza y recuperacion',
                'default_objective' => 'Cobrar deudas pendientes manteniendo la relacion con el cliente',
                'default_tone' => 'profesional, firme pero amable, nunca agresivo',
                'default_trigger_keywords' => ['pago','cobro','factura','deuda','pendiente','vencido','pagar','transferencia'],
                'default_transfer_keywords' => ['humano','no puedo pagar','no voy a pagar','demanda','abogado','gerente','queja'],
                'default_channels' => ['whatsapp','email'],
                'default_priority' => 100, 'default_avatar_emoji' => '💳',
                'questions' => [
                    ['key'=>'negocio','label'=>'¿Como se llama tu negocio?','placeholder'=>'Servicios ABC','type'=>'text','required'=>true],
                    ['key'=>'metodos_pago','label'=>'¿Que metodos de pago aceptas?','placeholder'=>'Transferencia bancaria (cuenta XYZ), tarjeta via link Stripe, efectivo en oficina','type'=>'textarea','required'=>true],
                    ['key'=>'opciones_pago','label'=>'¿Que opciones puedes ofrecer si el cliente no puede pagar todo?','placeholder'=>'Plan de 3 cuotas sin recargo. Aplazar 15 dias sin penalidad si lo pide antes del vencimiento.','type'=>'textarea','required'=>true],
                    ['key'=>'info_pedir','label'=>'¿Que datos confirmas antes de cerrar?','placeholder'=>'Numero de factura/orden, monto, fecha del pago, comprobante','type'=>'textarea','required'=>true],
                    ['key'=>'escalar_cuando','label'=>'¿En que casos pasas el chat a un humano?','placeholder'=>'Cliente se niega a pagar, deuda mayor a X, menciona abogado/demanda','type'=>'textarea','required'=>true],
                ],
                'display_order' => 40,
            ],
            [
                'slug' => 'recepcionista', 'name' => 'Recepcionista IA', 'category' => 'generic',
                'icon' => '🛎', 'accent_color' => '#8B5CF6',
                'description' => 'Saluda, identifica que necesita el cliente y lo enruta al agente o departamento correcto.',
                'instructions_template' => "Eres el recepcionista virtual de {{negocio}}. Tu trabajo no es resolver, es identificar y enrutar.\n\nQUE OFRECE TU NEGOCIO:\n{{que_ofreces}}\n\nDEPARTAMENTOS / EQUIPOS QUE EXISTEN:\n{{departamentos}}\n\nCOMO IDENTIFICAR LA INTENCION DEL CLIENTE:\n{{como_identificar}}\n\nREGLAS:\n- Saluda con la energia de tu marca.\n- Haz UNA pregunta clara para identificar que necesita (no abrumes con menu).\n- Una vez identifiques, NO intentes resolver: confirma que pasas con el equipo correcto y haz handoff.\n- Si es algo simple (horarios, direccion), responde tu mismo en una frase.",
                'default_role' => 'Recepcionista / triage',
                'default_objective' => 'Identificar lo que necesita el cliente y enrutarlo correctamente',
                'default_tone' => 'amable, breve, profesional',
                'default_trigger_keywords' => ['hola','buenas','ayuda','info','informacion','que','hola buen dia'],
                'default_transfer_keywords' => ['humano','agente','queja','reclamo','urgente'],
                'default_channels' => ['whatsapp','webchat','instagram','facebook'],
                'default_priority' => 50, 'default_avatar_emoji' => '🛎',
                'questions' => [
                    ['key'=>'negocio','label'=>'¿Como se llama tu negocio?','placeholder'=>'Pizzeria La Bella','type'=>'text','required'=>true],
                    ['key'=>'que_ofreces','label'=>'¿Que ofrece tu negocio? (en 1-2 frases)','placeholder'=>'Pizzeria artesanal con delivery y servicio en local. Tambien hacemos eventos privados.','type'=>'textarea','required'=>true],
                    ['key'=>'departamentos','label'=>'¿Que equipos o departamentos tienes?','placeholder'=>'Ventas/pedidos, Cocina, Eventos privados, Soporte/reclamos','type'=>'textarea','required'=>true],
                    ['key'=>'como_identificar','label'=>'¿Como sabes a que area mandar al cliente?','placeholder'=>"Si menciona \"pedir/comprar/ordenar\" -> Ventas.\nSi dice \"reservar mesa/evento\" -> Eventos.\nSi dice \"problema/reclamo\" -> Soporte.",'type'=>'textarea','required'=>true],
                ],
                'display_order' => 50,
            ],
        ];
    }

    /** Devuelve true si ya hay al menos 1 template global activo. */
    private static function hasGlobalWorkflowTemplates(\PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `workflow_templates` WHERE `tenant_id` IS NULL AND `is_active` = 1");
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Seed de templates globales del marketplace.
     * Idempotente via INSERT IGNORE + uniq (tenant_id, slug).
     */
    private static function seedGlobalWorkflowTemplates(\PDO $pdo): void
    {
        $templates = self::globalTemplateCatalog();
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO `workflow_templates`
             (`tenant_id`, `slug`, `name`, `description`, `category`, `icon`, `definition`, `requires`, `is_active`)
             VALUES (NULL, :slug, :name, :description, :category, :icon, :definition, :requires, 1)"
        );
        foreach ($templates as $t) {
            $stmt->execute([
                'slug'        => $t['slug'],
                'name'        => $t['name'],
                'description' => $t['description'],
                'category'    => $t['category'],
                'icon'        => $t['icon'],
                'definition'  => json_encode($t['definition'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'requires'    => isset($t['requires']) ? json_encode($t['requires'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
    }

    /** Catalogo declarativo de templates del marketplace global. */
    private static function globalTemplateCatalog(): array
    {
        return [
            // ---------- Ventas ----------
            [
                'slug' => 'welcome-new-lead',
                'name' => 'Bienvenida automatica a lead nuevo',
                'description' => 'Cuando entra un contacto nuevo, le envia un mensaje de bienvenida por WhatsApp y espera 2 dias para hacer follow-up si no respondio.',
                'category' => 'sales',
                'icon' => '👋',
                'definition' => [
                    'trigger_type' => 'event',
                    'trigger_config' => ['event' => 'contact.created'],
                    'steps' => [
                        [
                            'step_key' => 'send_welcome',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ payload.phone }}',
                                    'text' => 'Hola {{ payload.first_name }}, gracias por contactarnos! Estoy disponible para resolver cualquier duda. En que te puedo ayudar?',
                                ],
                            ],
                            'next_step_key' => 'wait_2d',
                        ],
                        [
                            'step_key' => 'wait_2d',
                            'type'     => 'delay',
                            'config'   => ['seconds' => 172800],
                            'next_step_key' => 'followup',
                        ],
                        [
                            'step_key' => 'followup',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ payload.phone }}',
                                    'text' => 'Hola {{ payload.first_name }}, queria hacer un follow-up. Tienes alguna pregunta antes de avanzar?',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Ventas: agente IA califica ----------
            [
                'slug' => 'ai-qualify-lead',
                'name' => 'IA califica lead automaticamente',
                'description' => 'Cuando entra un mensaje nuevo, un agente IA evalua si el lead es hot/warm/cold y aplica el tag correspondiente al contacto.',
                'category' => 'ai',
                'icon' => '🧠',
                'requires' => ['ai_enabled'],
                'definition' => [
                    'trigger_type' => 'event',
                    'trigger_config' => ['event' => 'message.received'],
                    'steps' => [
                        [
                            'step_key' => 'qualify',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'run_agent',
                                'params' => [
                                    'agent_id' => null,
                                    'input'    => "Califica esta intencion de compra del cliente: \"{{ payload.content }}\".\nResponde SOLO con una palabra: hot, warm o cold.",
                                ],
                            ],
                            'next_step_key' => 'check_hot',
                        ],
                        [
                            'step_key' => 'check_hot',
                            'type'     => 'branch',
                            'config'   => ['expr' => 'last.output', 'op' => 'contains', 'value' => 'hot'],
                            'branch_yes' => 'tag_hot',
                            'branch_no'  => 'check_warm',
                        ],
                        [
                            'step_key' => 'tag_hot',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'add_tag',
                                'params' => ['contact_id' => '{{ payload.contact_id }}', 'tag' => 'lead_hot'],
                            ],
                        ],
                        [
                            'step_key' => 'check_warm',
                            'type'     => 'branch',
                            'config'   => ['expr' => 'last.output', 'op' => 'contains', 'value' => 'warm'],
                            'branch_yes' => 'tag_warm',
                            'branch_no'  => 'tag_cold',
                        ],
                        [
                            'step_key' => 'tag_warm',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'add_tag',
                                'params' => ['contact_id' => '{{ payload.contact_id }}', 'tag' => 'lead_warm'],
                            ],
                        ],
                        [
                            'step_key' => 'tag_cold',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'add_tag',
                                'params' => ['contact_id' => '{{ payload.contact_id }}', 'tag' => 'lead_cold'],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Restaurante: confirmar pedido ----------
            [
                'slug' => 'order-confirmation',
                'name' => 'Confirmacion automatica de orden',
                'description' => 'Apenas se crea una orden, envia un WhatsApp al cliente con codigo y tiempo estimado, y espera 30 min para confirmar entrega.',
                'category' => 'restaurant',
                'icon' => '🍽',
                'requires' => ['is_restaurant'],
                'definition' => [
                    'trigger_type' => 'event',
                    'trigger_config' => ['event' => 'order.created'],
                    'steps' => [
                        [
                            'step_key' => 'send_confirmation',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ payload.contact_phone }}',
                                    'text' => 'Tu pedido {{ payload.code }} fue recibido! Total: {{ payload.currency }} {{ payload.total }}. Tiempo estimado: 25-35 min. Te avisamos cuando este listo.',
                                ],
                            ],
                            'next_step_key' => 'wait_30m',
                        ],
                        [
                            'step_key' => 'wait_30m',
                            'type'     => 'delay',
                            'config'   => ['seconds' => 1800],
                            'next_step_key' => 'ready_ping',
                        ],
                        [
                            'step_key' => 'ready_ping',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ payload.contact_phone }}',
                                    'text' => 'Hola! Solo confirmando que recibiste tu pedido {{ payload.code }}. Si hay cualquier detalle, respondenos aqui.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Recuperacion carrito ----------
            [
                'slug' => 'cart-recovery',
                'name' => 'Recuperacion de carrito abandonado',
                'description' => 'Cuando una conversacion lleva 4 horas sin actividad y tiene carrito armado, envia un mensaje de recuperacion automatico.',
                'category' => 'sales',
                'icon' => '🛒',
                'definition' => [
                    'trigger_type' => 'schedule',
                    'trigger_config' => ['cron' => '*/30 * * * *'],
                    'steps' => [
                        [
                            'step_key' => 'log',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'log',
                                'params' => ['message' => 'Cart recovery sweep triggered'],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Soporte: ticket urgente ----------
            [
                'slug' => 'urgent-ticket-escalation',
                'name' => 'Escalamiento de ticket critico',
                'description' => 'Cuando se crea un ticket con prioridad critical, envia un webhook a Slack/Teams y un WhatsApp interno al admin de turno.',
                'category' => 'support',
                'icon' => '🚨',
                'definition' => [
                    'trigger_type' => 'event',
                    'trigger_config' => ['event' => 'ticket.created'],
                    'steps' => [
                        [
                            'step_key' => 'check_critical',
                            'type'     => 'branch',
                            'config'   => ['expr' => 'payload.priority', 'op' => 'eq', 'value' => 'critical'],
                            'branch_yes' => 'notify',
                            'branch_no'  => 'end_quiet',
                        ],
                        [
                            'step_key' => 'notify',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'webhook_out',
                                'params' => [
                                    'url'     => 'https://hooks.slack.com/services/REEMPLAZAR',
                                    'payload' => [
                                        'text' => '🚨 Ticket critico abierto: {{ payload.title }} (ID {{ payload.ticket_id }})',
                                    ],
                                ],
                            ],
                            'next_step_key' => 'end_ok',
                        ],
                        [
                            'step_key' => 'end_ok',
                            'type'     => 'end',
                            'config'   => ['status' => 'succeeded'],
                        ],
                        [
                            'step_key' => 'end_quiet',
                            'type'     => 'end',
                            'config'   => ['status' => 'succeeded'],
                        ],
                    ],
                ],
            ],

            // ---------- Marketing: agradecimiento post-compra ----------
            [
                'slug' => 'post-purchase-thanks',
                'name' => 'Agradecimiento post-compra',
                'description' => 'Cuando una orden pasa a delivered, espera 1 dia y envia un mensaje de agradecimiento con pedido de feedback.',
                'category' => 'marketing',
                'icon' => '💌',
                'definition' => [
                    'trigger_type' => 'event',
                    'trigger_config' => ['event' => 'order.delivered'],
                    'steps' => [
                        [
                            'step_key' => 'wait_1d',
                            'type'     => 'delay',
                            'config'   => ['seconds' => 86400],
                            'next_step_key' => 'thank',
                        ],
                        [
                            'step_key' => 'thank',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ payload.contact_phone }}',
                                    'text' => 'Hola {{ payload.first_name }}! Esperamos que hayas disfrutado tu pedido {{ payload.code }}. Nos contarias como te fue? Tu opinion nos ayuda a mejorar.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Ops: daily digest ----------
            [
                'slug' => 'daily-ops-digest',
                'name' => 'Reporte diario de operaciones',
                'description' => 'Todos los dias a las 9:00 envia un webhook con metricas del dia anterior (ordenes, ingresos, ruta de IA, tickets) a Slack/Discord.',
                'category' => 'ops',
                'icon' => '📊',
                'definition' => [
                    'trigger_type' => 'schedule',
                    'trigger_config' => ['cron' => '0 9 * * *'],
                    'steps' => [
                        [
                            'step_key' => 'notify',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'webhook_out',
                                'params' => [
                                    'url'     => 'https://hooks.slack.com/services/REEMPLAZAR',
                                    'payload' => [
                                        'text' => 'Daily digest · {{ scheduled_at }}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ---------- Webhook entrante: integracion custom ----------
            [
                'slug' => 'incoming-webhook-relay',
                'name' => 'Relay de webhook entrante a WhatsApp',
                'description' => 'URL publica unica que cuando recibe un POST, dispara un WhatsApp con el contenido al numero indicado. Ideal para integrar formularios externos.',
                'category' => 'general',
                'icon' => '🔗',
                'definition' => [
                    'trigger_type' => 'webhook',
                    'trigger_config' => [],
                    'steps' => [
                        [
                            'step_key' => 'forward',
                            'type'     => 'action',
                            'config'   => [
                                'action' => 'send_whatsapp',
                                'params' => [
                                    'to'   => '{{ webhook_payload.to }}',
                                    'text' => '{{ webhook_payload.message }}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
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
