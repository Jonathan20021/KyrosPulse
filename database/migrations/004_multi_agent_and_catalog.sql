-- ============================================================================
-- Kyros Pulse - Multi-agente con enrutamiento + catalogo de productos
-- ============================================================================
-- Idempotente. Anade campos a ai_agents y conversations, crea products.
-- ============================================================================

DROP PROCEDURE IF EXISTS kp004_add_col;
DROP PROCEDURE IF EXISTS kp004_add_idx;

DELIMITER //

CREATE PROCEDURE kp004_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END//

CREATE PROCEDURE kp004_add_idx(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD KEY `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- ai_agents: especializacion + reglas de enrutamiento
CALL kp004_add_col('ai_agents', 'category',          "VARCHAR(40) NOT NULL DEFAULT 'generic' AFTER `tone`");
CALL kp004_add_col('ai_agents', 'priority',          "INT NOT NULL DEFAULT 100 AFTER `category`");
CALL kp004_add_col('ai_agents', 'trigger_keywords',  "JSON NULL AFTER `priority`");
CALL kp004_add_col('ai_agents', 'transfer_keywords', "JSON NULL AFTER `handoff_keywords`");
CALL kp004_add_col('ai_agents', 'working_hours',     "JSON NULL AFTER `transfer_keywords`");
CALL kp004_add_col('ai_agents', 'max_retries',       "INT NOT NULL DEFAULT 3 AFTER `working_hours`");
CALL kp004_add_col('ai_agents', 'fallback_agent_id', "BIGINT UNSIGNED NULL AFTER `max_retries`");
CALL kp004_add_col('ai_agents', 'channels',          "JSON NULL AFTER `fallback_agent_id`");
CALL kp004_add_col('ai_agents', 'avatar_emoji',      "VARCHAR(10) NULL AFTER `channels`");
CALL kp004_add_idx('ai_agents', 'idx_ai_agents_priority', '`tenant_id`,`status`,`priority`');

-- conversations: contadores de retry para escalar a humano
CALL kp004_add_col('conversations', 'ai_failed_attempts', "INT NOT NULL DEFAULT 0 AFTER `ai_paused_until`");
CALL kp004_add_col('conversations', 'ai_handled_count',   "INT NOT NULL DEFAULT 0 AFTER `ai_failed_attempts`");
CALL kp004_add_col('conversations', 'ai_last_run_at',     "DATETIME NULL AFTER `ai_handled_count`");

-- Tabla de productos / servicios
CREATE TABLE IF NOT EXISTS `products` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    BIGINT UNSIGNED NOT NULL,
    `uuid`         CHAR(36) NOT NULL,
    `name`         VARCHAR(180) NOT NULL,
    `sku`          VARCHAR(60) NULL,
    `category`     VARCHAR(80) NULL,
    `description`  TEXT NULL,
    `price`        DECIMAL(12,2) NOT NULL DEFAULT 0,
    `currency`     VARCHAR(3) NOT NULL DEFAULT 'USD',
    `cost`         DECIMAL(12,2) NULL,
    `stock`        INT NULL,
    `image_url`    VARCHAR(500) NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `priority`     INT NOT NULL DEFAULT 0,
    `metadata`     JSON NULL,
    `created_by`   BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_uuid` (`uuid`),
    UNIQUE KEY `uk_products_tenant_sku` (`tenant_id`,`sku`),
    KEY `idx_products_tenant` (`tenant_id`),
    KEY `idx_products_active` (`tenant_id`,`is_active`,`priority`),
    CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS kp004_add_col;
DROP PROCEDURE IF EXISTS kp004_add_idx;
