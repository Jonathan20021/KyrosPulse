-- ============================================================================
-- Kyros Pulse - Webhooks salientes con HMAC
-- ============================================================================
-- Permite que cada tenant subscriba URLs externas a eventos del sistema:
--   * order.created, order.status_changed, order.cancelled
--   * agent.run.completed, agent.run.failed
--   * contact.created, contact.updated
--   * message.received, message.sent
--   * conversation.opened, conversation.closed
--
-- Cada delivery se firma con HMAC SHA-256:
--   X-Kyros-Signature: t=<unix>, v1=<hex sha256>
--   X-Kyros-Event: order.created
--   X-Kyros-Delivery-Id: <uuid>
--
-- Retries con backoff exponencial (1m, 5m, 30m, 2h, 12h) y dead-letter
-- despues de 5 intentos. Los deliveries se conservan 30 dias.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `created_by`      INT UNSIGNED NULL,
    `name`            VARCHAR(120) NOT NULL,
    `url`             VARCHAR(500) NOT NULL,
    `secret`          VARCHAR(120) NOT NULL,                       -- shared secret para HMAC
    `events`          JSON NULL,                                   -- ["order.created","agent.run.*"] o ["*"]
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `description`     VARCHAR(500) NULL,
    `headers`         JSON NULL,                                   -- headers custom adicionales
    `last_delivery_at` DATETIME NULL,
    `last_status`     SMALLINT UNSIGNED NULL,
    `last_error`      VARCHAR(500) NULL,
    `success_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_tenant_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`            CHAR(36) NOT NULL,
    `endpoint_id`     BIGINT UNSIGNED NOT NULL,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `event`           VARCHAR(80) NOT NULL,
    `payload`         MEDIUMTEXT NOT NULL,                          -- JSON enviado
    `signature`       VARCHAR(255) NULL,                            -- header firma generado
    `status`          ENUM('pending','delivered','failed','dead') NOT NULL DEFAULT 'pending',
    `attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `next_retry_at`   DATETIME NULL,
    `response_code`   SMALLINT UNSIGNED NULL,
    `response_body`   TEXT NULL,                                    -- truncado a ~4KB
    `latency_ms`      INT UNSIGNED NULL,
    `error`           VARCHAR(500) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivered_at`    DATETIME NULL,
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_endpoint_status` (`endpoint_id`, `status`),
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_retry` (`status`, `next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
