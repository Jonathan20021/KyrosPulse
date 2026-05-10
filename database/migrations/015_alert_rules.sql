-- ============================================================================
-- Kyros Pulse - Sistema de alertas inteligentes
-- ============================================================================
-- Convierte el SaaS pasivo (mira dashboard si quieres) en proactivo (te avisamos
-- cuando algo importante pasa). Reusa NotificationDispatcher + notification_destinations.
--
-- Rule types builtin (tenant scope):
--   api.quota.80         -- cuota API mensual >= 80%
--   api.quota.100        -- cuota API mensual agotada
--   webhook.dead         -- >=5 deliveries dead en 24h en un endpoint
--   agent.error_rate     -- >=20% fallos en agent_runs ultimas 24h
--   security.critical    -- security_event severity=critical reciente
--   workflow.failed      -- workflow_run status=failed (cualquiera)
--
-- Cada regla tiene cooldown_minutes para evitar spam (default 60 min).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `alert_rules` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`        INT UNSIGNED NULL,                          -- NULL = builtin/global
    `slug`             VARCHAR(64) NOT NULL,                       -- ej: api.quota.80
    `name`             VARCHAR(120) NOT NULL,
    `description`      VARCHAR(500) NULL,
    `rule_type`        VARCHAR(40) NOT NULL,                       -- discriminador para AlertEvaluator
    `config`           JSON NULL,                                   -- params especificos (threshold, etc)
    `severity`         ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    `cooldown_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `last_triggered_at` DATETIME NULL,
    `trigger_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active_type` (`is_active`, `rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
