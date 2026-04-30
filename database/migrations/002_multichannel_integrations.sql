-- ============================================================================
-- Kyros Pulse - Migration 002
-- Multi-canal de WhatsApp + catalogo de integraciones premium.
-- Idempotente: usa PREPARE/EXECUTE para anadir columnas/indices solo si faltan.
-- Ejecutar despues de 001_initial_schema.sql.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- conversations.channel_id
-- ----------------------------------------------------------------------------
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'conversations' AND column_name = 'channel_id') > 0,
    'SELECT 1',
    'ALTER TABLE `conversations` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `channel`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'conversations' AND column_name = 'from_phone') > 0,
    'SELECT 1',
    'ALTER TABLE `conversations` ADD COLUMN `from_phone` VARCHAR(40) NULL AFTER `channel_id`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
       WHERE table_schema = DATABASE() AND table_name = 'conversations' AND index_name = 'idx_conv_channel_id') > 0,
    'SELECT 1',
    'ALTER TABLE `conversations` ADD KEY `idx_conv_channel_id` (`channel_id`)'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- messages.channel_id
-- ----------------------------------------------------------------------------
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'messages' AND column_name = 'channel_id') > 0,
    'SELECT 1',
    'ALTER TABLE `messages` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `user_id`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'messages' AND column_name = 'from_phone') > 0,
    'SELECT 1',
    'ALTER TABLE `messages` ADD COLUMN `from_phone` VARCHAR(40) NULL AFTER `channel_id`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
       WHERE table_schema = DATABASE() AND table_name = 'messages' AND index_name = 'idx_msg_channel_id') > 0,
    'SELECT 1',
    'ALTER TABLE `messages` ADD KEY `idx_msg_channel_id` (`channel_id`)'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- whatsapp_logs.channel_id, provider
-- ----------------------------------------------------------------------------
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'whatsapp_logs' AND column_name = 'channel_id') > 0,
    'SELECT 1',
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `channel_id` BIGINT UNSIGNED NULL AFTER `tenant_id`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'whatsapp_logs' AND column_name = 'provider') > 0,
    'SELECT 1',
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `provider` VARCHAR(40) NULL AFTER `channel_id`'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- WhatsApp Channels (multiples numeros / proveedores por tenant)
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Catalogo de integraciones por tenant
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Backfill: convertir wasapi_api_key/wasapi_phone existentes en un canal por tenant.
-- ----------------------------------------------------------------------------
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
  AND NOT EXISTS (SELECT 1 FROM `whatsapp_channels` wc WHERE wc.tenant_id = t.id AND wc.provider = 'wasapi');

SET FOREIGN_KEY_CHECKS = 1;
