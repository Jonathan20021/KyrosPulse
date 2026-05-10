-- ============================================================================
-- Kyros Pulse - Seguridad transversal
-- ============================================================================
-- Anade:
--   * user_2fa             : TOTP secret + estado por usuario
--   * user_recovery_codes  : codigos one-time-use (10 por usuario)
--   * login_attempts       : tracking + lockout temporal por email/IP
--   * user_sessions        : sesiones activas con IP, UA, last_seen
--   * security_events      : log de eventos sensibles (login, 2fa, password, etc)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_2fa` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `secret`          VARCHAR(64) NOT NULL,                       -- base32 TOTP secret
    `enabled`         TINYINT(1) NOT NULL DEFAULT 0,              -- 0 = secret generado pero no verificado
    `enabled_at`      DATETIME NULL,
    `last_used_code`  VARCHAR(8) NULL,                            -- previene reuso del mismo codigo en ventana
    `last_used_at`    DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `code_hash`  CHAR(64) NOT NULL,                                -- sha256(plain)
    `used_at`    DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user` (`user_id`, `used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(160) NOT NULL,
    `ip`         VARCHAR(64) NOT NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `success`    TINYINT(1) NOT NULL DEFAULT 0,
    `reason`     VARCHAR(60) NULL,                                 -- bad_password, locked, 2fa_required, etc.
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_email_time` (`email`, `created_at`),
    KEY `idx_ip_time`    (`ip`, `created_at`),
    KEY `idx_user_time`  (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NULL,
    `session_id`    CHAR(64) NOT NULL,                             -- hash(session_id de PHP)
    `ip`            VARCHAR(64) NULL,
    `user_agent`    VARCHAR(255) NULL,
    `last_seen_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at`    DATETIME NULL,
    UNIQUE KEY `uniq_session` (`session_id`),
    KEY `idx_user_active` (`user_id`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_events` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NULL,
    `user_id`    BIGINT UNSIGNED NULL,
    `event`      VARCHAR(60) NOT NULL,                             -- login_ok, login_fail, 2fa_enabled, password_change, api_key_create, session_revoke...
    `severity`   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `ip`         VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `metadata`   JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tenant_time` (`tenant_id`, `created_at`),
    KEY `idx_user_time`   (`user_id`, `created_at`),
    KEY `idx_event`       (`event`, `created_at`),
    KEY `idx_severity`    (`severity`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
