-- ============================================================================
-- Kyros Pulse - Agentes IA configurables
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_agents` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`           BIGINT UNSIGNED NOT NULL,
    `uuid`                CHAR(36) NOT NULL,
    `name`                VARCHAR(120) NOT NULL,
    `role`                VARCHAR(160) NULL,
    `objective`           TEXT NULL,
    `instructions`        MEDIUMTEXT NULL,
    `tone`                VARCHAR(120) NULL,
    `model`               VARCHAR(120) NULL,
    `auto_reply_enabled`  TINYINT(1) NOT NULL DEFAULT 0,
    `is_default`          TINYINT(1) NOT NULL DEFAULT 0,
    `max_context_messages` INT UNSIGNED NOT NULL DEFAULT 12,
    `handoff_keywords`    JSON NULL,
    `allowed_actions`     JSON NULL,
    `status`              ENUM('active','paused') NOT NULL DEFAULT 'active',
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_agents_uuid` (`uuid`),
    KEY `idx_ai_agents_tenant` (`tenant_id`),
    KEY `idx_ai_agents_default` (`tenant_id`,`is_default`),
    CONSTRAINT `fk_ai_agents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_agent_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`       BIGINT UNSIGNED NOT NULL,
    `agent_id`        BIGINT UNSIGNED NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `message_id`      BIGINT UNSIGNED NULL,
    `action`          VARCHAR(80) NOT NULL,
    `prompt`          MEDIUMTEXT NULL,
    `response`        MEDIUMTEXT NULL,
    `success`         TINYINT(1) NOT NULL DEFAULT 0,
    `error_message`   TEXT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_agent_logs_tenant` (`tenant_id`),
    KEY `idx_ai_agent_logs_agent` (`agent_id`),
    KEY `idx_ai_agent_logs_conv` (`conversation_id`),
    CONSTRAINT `fk_ai_agent_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ai_agent_logs_agent` FOREIGN KEY (`agent_id`) REFERENCES `ai_agents` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_agent_logs_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_agent_logs_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
