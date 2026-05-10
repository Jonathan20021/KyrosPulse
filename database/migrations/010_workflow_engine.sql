-- ============================================================================
-- Kyros Pulse - Workflow engine OS-style (v2)
-- ============================================================================
-- Extiende el AutomationEngine existente (lineal: trigger->conditions->actions)
-- con un modelo orquestable: triggers + steps tipados + runs persistidas.
--
-- Triggers: event | schedule (cron) | webhook (URL publica unica) | manual
-- Step types:
--   * action       -> ejecuta una accion (send_message, run_agent, http, etc)
--   * branch       -> if expr ? goto step_if : goto step_else
--   * delay        -> espera N segundos antes del proximo step (cron lo retoma)
--   * set_var      -> guarda un valor en el context
--   * end          -> termina el run con status (success|failed)
--
-- workflow_runs guarda el contexto del run (vars, last output, etc) entre
-- steps, asi un delay puede "pausar" y un cron worker reanuda.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `workflows` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `name`            VARCHAR(120) NOT NULL,
    `description`     VARCHAR(500) NULL,
    `trigger_type`    ENUM('event','schedule','webhook','manual') NOT NULL DEFAULT 'event',
    `trigger_config`  JSON NULL,                                 -- {event:'order.created'} o {cron:'0 9 * * *'} o {webhook_token:'xxx'}
    `webhook_token`   VARCHAR(48) NULL,                          -- para trigger=webhook (URL: /workflows/run/{token})
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `runs_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_run_at`     DATETIME NULL,
    `next_run_at`     DATETIME NULL,                              -- proxima ejecucion para schedule
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_webhook_token` (`webhook_token`),
    KEY `idx_tenant_active` (`tenant_id`, `is_active`),
    KEY `idx_trigger_type`  (`trigger_type`, `is_active`),
    KEY `idx_next_run`      (`is_active`, `next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_steps` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workflow_id`    BIGINT UNSIGNED NOT NULL,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `step_key`       VARCHAR(40) NOT NULL,                        -- alias estable (ej. "send_welcome")
    `type`           ENUM('action','branch','delay','set_var','end') NOT NULL DEFAULT 'action',
    `order_index`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `config`         JSON NULL,                                   -- params del step segun type
    `next_step_key`  VARCHAR(40) NULL,                            -- step lineal por defecto
    `branch_yes`     VARCHAR(40) NULL,                            -- si type=branch, step si la condicion es true
    `branch_no`      VARCHAR(40) NULL,                            -- si type=branch, step si la condicion es false
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_wf_key` (`workflow_id`, `step_key`),
    KEY `idx_workflow_order` (`workflow_id`, `order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_runs` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `workflow_id`     BIGINT UNSIGNED NOT NULL,
    `trigger_type`    VARCHAR(20) NOT NULL,
    `status`          ENUM('queued','running','waiting','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
    `current_step_key` VARCHAR(40) NULL,
    `wait_until`      DATETIME NULL,                              -- para steps de delay
    `context`         JSON NULL,                                  -- vars del run (event payload + set_var output)
    `error`           TEXT NULL,
    `started_at`      DATETIME NULL,
    `finished_at`     DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_resume` (`status`, `wait_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
