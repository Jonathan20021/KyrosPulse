-- ============================================================================
-- Kyros Pulse - API publica + Agent-as-a-Service
-- ============================================================================
-- Crea las tablas necesarias para:
--   * api_keys           : credenciales por tenant (Bearer kp_live_xxxx)
--   * api_request_logs   : audit log de cada request al API
--   * api_rate_limits    : sliding-window rate limiting por key + endpoint
--   * agent_runs         : ejecuciones de un agente (input/output/tokens/costo)
--   * agent_skills       : skills componibles por agente (sales, support, etc)
--   * agent_skill_links  : pivote agente <-> skill habilitada
--
-- Idempotente: usa CREATE TABLE IF NOT EXISTS y procedures para columnas/indices.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) api_keys
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `created_by`    INT UNSIGNED NULL,
    `name`          VARCHAR(120) NOT NULL,
    `prefix`        VARCHAR(16) NOT NULL,                          -- ej: kp_live_a1b2c3
    `key_hash`      CHAR(64) NOT NULL,                              -- sha256(secret)
    `last4`         CHAR(4) NOT NULL,                               -- ultimos 4 chars del secret (display)
    `scopes`        JSON NULL,                                      -- ["agents.read","agents.run","contacts.write",...]
    `allowed_ips`   JSON NULL,                                      -- whitelist opcional
    `last_used_at`  DATETIME NULL,
    `last_used_ip`  VARCHAR(64) NULL,
    `expires_at`    DATETIME NULL,                                  -- NULL = sin caducidad
    `revoked_at`    DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_key_hash` (`key_hash`),
    KEY `idx_tenant` (`tenant_id`),
    KEY `idx_prefix` (`prefix`),
    KEY `idx_active` (`tenant_id`, `revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) api_request_logs (audit)
-- ---------------------------------------------------------------------------
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
    `request_id`    CHAR(36) NULL,                                  -- UUID por request, devuelto en X-Request-Id
    `bytes_in`      INT UNSIGNED NOT NULL DEFAULT 0,
    `bytes_out`     INT UNSIGNED NOT NULL DEFAULT 0,
    `error`         VARCHAR(255) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_key_time` (`api_key_id`, `created_at`),
    KEY `idx_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) api_rate_limits (counters por key + bucket)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_hash`      CHAR(64) NOT NULL,
    `bucket`        VARCHAR(64) NOT NULL,                           -- ej: "global", "agent.run", "messages.send"
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`    DATETIME NOT NULL,
    UNIQUE KEY `uniq_bucket` (`key_hash`, `bucket`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4) agent_runs (ejecuciones de agente, ya sean via API o internas)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agent_runs` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `agent_id`        BIGINT UNSIGNED NOT NULL,
    `api_key_id`      BIGINT UNSIGNED NULL,
    `contact_id`      BIGINT UNSIGNED NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `channel`         VARCHAR(32) NOT NULL DEFAULT 'api',           -- api, whatsapp, internal
    `status`          ENUM('queued','running','succeeded','failed','timeout') NOT NULL DEFAULT 'queued',
    `input`           MEDIUMTEXT NULL,
    `output`          MEDIUMTEXT NULL,
    `actions`         JSON NULL,                                    -- [{type,payload}, ...]
    `metadata`        JSON NULL,                                    -- request_id, headers, custom
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5) agent_skills (catalogo de skills disponibles, global + por tenant)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agent_skills` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NULL,                              -- NULL = skill global del sistema
    `slug`          VARCHAR(64) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `description`   TEXT NULL,
    `system_prompt` MEDIUMTEXT NULL,
    `tools`         JSON NULL,                                      -- ["create_order","schedule","escalate","query_kb",...]
    `config`        JSON NULL,                                      -- params custom (canales, segmentos, etc)
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6) agent_skill_links (pivote: que skills tiene activas cada agente)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agent_skill_links` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `agent_id`      BIGINT UNSIGNED NOT NULL,
    `skill_id`      BIGINT UNSIGNED NOT NULL,
    `priority`      SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `config`        JSON NULL,                                      -- override por agente
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_agent_skill` (`agent_id`, `skill_id`),
    KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed: skills globales basicas (idempotente).
-- NOTA: UNIQUE KEY (tenant_id, slug) NO previene duplicacion cuando tenant_id
-- es NULL porque MySQL trata NULL != NULL en comparaciones de constraint.
-- Por eso usamos INSERT ... SELECT WHERE NOT EXISTS, que SI es safe.
-- ---------------------------------------------------------------------------
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
);
