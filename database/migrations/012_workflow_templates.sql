-- ============================================================================
-- Kyros Pulse - Workflow templates marketplace
-- ============================================================================
-- Templates de workflows pre-armados (globales del sistema + custom del tenant)
-- que el usuario clona con 1 click para no empezar de cero. El clone genera
-- un workflow nuevo + sus steps en transaccion.
--
-- definition JSON shape:
-- {
--   "trigger_type": "event|schedule|webhook|manual",
--   "trigger_config": {...},
--   "steps": [
--     {
--       "step_key": "welcome",
--       "type": "action|branch|delay|set_var|end",
--       "config": {...},
--       "next_step_key": "next" | null,
--       "branch_yes": null,
--       "branch_no": null
--     },
--     ...
--   ]
-- }
-- ============================================================================

CREATE TABLE IF NOT EXISTS `workflow_templates` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NULL,                              -- NULL = global del sistema
    `slug`         VARCHAR(64) NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `description`  VARCHAR(500) NULL,
    `category`     VARCHAR(40) NOT NULL DEFAULT 'general',         -- sales, support, marketing, ops, restaurant, ai
    `icon`         VARCHAR(8) NOT NULL DEFAULT '🪄',
    `definition`   MEDIUMTEXT NOT NULL,                            -- JSON: trigger + steps
    `requires`     JSON NULL,                                       -- tags/features que el tenant debe tener (ej. ["is_restaurant","ai_enabled"])
    `clone_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active_category` (`is_active`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
