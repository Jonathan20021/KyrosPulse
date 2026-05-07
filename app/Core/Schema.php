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
    private const CACHE_FILE = '/cache/.schema_v11_ok';
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
                    && self::columnExists($pdo, 'contacts', 'lifetime_orders');

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
