-- ============================================================================
-- Kyros Pulse - Proveedores IA globales del SaaS + cuotas de tokens por tenant
-- ============================================================================

CREATE TABLE IF NOT EXISTS `global_ai_providers` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120) NOT NULL,
    `provider`     ENUM('claude','openai') NOT NULL DEFAULT 'claude',
    `api_key`      VARCHAR(500) NOT NULL,
    `model`        VARCHAR(120) NOT NULL,
    `description`  VARCHAR(500) NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `is_default`   TINYINT(1) NOT NULL DEFAULT 0,
    `priority`     INT NOT NULL DEFAULT 100,
    `monthly_token_limit` BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_global_ai_active` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
