-- ============================================================================
-- Kyros Pulse - Esquema inicial
-- Motor: MySQL 8+ / InnoDB
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ----------------------------------------------------------------------------
-- Planes
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`              VARCHAR(50)  NOT NULL,
    `name`              VARCHAR(100) NOT NULL,
    `description`       TEXT NULL,
    `price_monthly`     DECIMAL(10,2) NOT NULL DEFAULT 0,
    `price_yearly`      DECIMAL(10,2) NOT NULL DEFAULT 0,
    `currency`          VARCHAR(3) NOT NULL DEFAULT 'USD',
    `max_users`         INT UNSIGNED NOT NULL DEFAULT 1,
    `max_contacts`      INT UNSIGNED NOT NULL DEFAULT 1000,
    `max_messages`      INT UNSIGNED NOT NULL DEFAULT 1000,
    `max_campaigns`     INT UNSIGNED NOT NULL DEFAULT 5,
    `max_automations`   INT UNSIGNED NOT NULL DEFAULT 5,
    `ai_enabled`        TINYINT(1) NOT NULL DEFAULT 0,
    `api_access`        TINYINT(1) NOT NULL DEFAULT 0,
    `advanced_reports`  TINYINT(1) NOT NULL DEFAULT 0,
    `support_level`     ENUM('email','chat','priority','dedicated') NOT NULL DEFAULT 'email',
    `features`          JSON NULL,
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`        INT NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plans_slug` (`slug`),
    KEY `idx_plans_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tenants (empresas)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`              CHAR(36) NOT NULL,
    `slug`              VARCHAR(80) NOT NULL,
    `name`              VARCHAR(150) NOT NULL,
    `legal_name`        VARCHAR(180) NULL,
    `tax_id`            VARCHAR(60) NULL,
    `email`             VARCHAR(180) NOT NULL,
    `phone`             VARCHAR(40) NULL,
    `country`           VARCHAR(2) NOT NULL DEFAULT 'DO',
    `currency`          VARCHAR(3) NOT NULL DEFAULT 'USD',
    `timezone`          VARCHAR(50) NOT NULL DEFAULT 'America/Santo_Domingo',
    `language`          VARCHAR(5) NOT NULL DEFAULT 'es',
    `logo`              VARCHAR(255) NULL,
    `website`           VARCHAR(255) NULL,
    `industry`          VARCHAR(80) NULL,
    `address`           VARCHAR(255) NULL,
    `plan_id`           BIGINT UNSIGNED NULL,
    `status`            ENUM('trial','active','suspended','cancelled','expired') NOT NULL DEFAULT 'trial',
    `trial_ends_at`     DATETIME NULL,
    `expires_at`        DATETIME NULL,
    `wasapi_api_key`    VARCHAR(255) NULL,
    `wasapi_phone`      VARCHAR(40) NULL,
    `wasapi_webhook`    VARCHAR(255) NULL,
    `resend_api_key`    VARCHAR(255) NULL,
    `resend_from_email` VARCHAR(180) NULL,
    `claude_api_key`    VARCHAR(255) NULL,
    `claude_model`      VARCHAR(80) NULL,
    `ai_assistant_name` VARCHAR(80) NULL,
    `ai_tone`           VARCHAR(50) NULL,
    `ai_enabled`        TINYINT(1) NOT NULL DEFAULT 0,
    `business_hours`    JSON NULL,
    `out_of_hours_msg`  TEXT NULL,
    `welcome_message`   TEXT NULL,
    `settings`          JSON NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenants_uuid` (`uuid`),
    UNIQUE KEY `uk_tenants_slug` (`slug`),
    UNIQUE KEY `uk_tenants_email` (`email`),
    KEY `idx_tenants_status` (`status`),
    KEY `idx_tenants_plan` (`plan_id`),
    CONSTRAINT `fk_tenants_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Suscripciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `plan_id`       BIGINT UNSIGNED NOT NULL,
    `period`        ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    `amount`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `currency`      VARCHAR(3) NOT NULL DEFAULT 'USD',
    `starts_at`     DATETIME NOT NULL,
    `ends_at`       DATETIME NOT NULL,
    `cancelled_at`  DATETIME NULL,
    `status`        ENUM('active','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
    `payment_method`VARCHAR(50) NULL,
    `notes`         TEXT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subscriptions_tenant` (`tenant_id`),
    KEY `idx_subscriptions_status` (`status`),
    CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Roles
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`          VARCHAR(50) NOT NULL,
    `name`          VARCHAR(80) NOT NULL,
    `description`   VARCHAR(255) NULL,
    `is_system`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Permisos
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`          VARCHAR(80) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `module`        VARCHAR(50) NOT NULL,
    `description`   VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Role <-> Permission
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
    `role_id`       BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`,`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Usuarios
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NULL,
    `uuid`              CHAR(36) NOT NULL,
    `first_name`        VARCHAR(80) NOT NULL,
    `last_name`         VARCHAR(80) NOT NULL,
    `email`             VARCHAR(180) NOT NULL,
    `phone`             VARCHAR(40) NULL,
    `password`          VARCHAR(255) NOT NULL,
    `avatar`            VARCHAR(255) NULL,
    `signature`         TEXT NULL,
    `language`          VARCHAR(5) NOT NULL DEFAULT 'es',
    `timezone`          VARCHAR(50) NULL,
    `is_super_admin`    TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified_at` DATETIME NULL,
    `last_login_at`     DATETIME NULL,
    `last_login_ip`     VARCHAR(45) NULL,
    `two_factor_secret` VARCHAR(255) NULL,
    `remember_token`    VARCHAR(100) NULL,
    `working_hours`     JSON NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    UNIQUE KEY `uk_users_uuid` (`uuid`),
    KEY `idx_users_tenant` (`tenant_id`),
    KEY `idx_users_active` (`is_active`),
    CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- User <-> Role (con tenant_id para asignacion por empresa)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
    `user_id`   BIGINT UNSIGNED NOT NULL,
    `role_id`   BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`user_id`,`role_id`),
    KEY `idx_ur_tenant` (`tenant_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tokens / verificacion
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(180) NOT NULL,
    `token`         VARCHAR(255) NOT NULL,
    `expires_at`    DATETIME NOT NULL,
    `used_at`       DATETIME NULL,
    `ip_address`    VARCHAR(45) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_email` (`email`),
    KEY `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_verifications`;
CREATE TABLE `email_verifications` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `token`         VARCHAR(255) NOT NULL,
    `expires_at`    DATETIME NOT NULL,
    `verified_at`   DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ev_token` (`token`),
    KEY `idx_ev_user` (`user_id`),
    CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- API Keys
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `name`          VARCHAR(120) NOT NULL,
    `key_hash`      VARCHAR(255) NOT NULL,
    `key_prefix`    VARCHAR(20) NOT NULL,
    `abilities`     JSON NULL,
    `last_used_at`  DATETIME NULL,
    `expires_at`    DATETIME NULL,
    `revoked_at`    DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_apikeys_hash` (`key_hash`),
    KEY `idx_apikeys_tenant` (`tenant_id`),
    CONSTRAINT `fk_apikeys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Etiquetas (tags)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(80) NOT NULL,
    `color`         VARCHAR(20) NOT NULL DEFAULT '#7C3AED',
    `description`   VARCHAR(255) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tags_tenant_name` (`tenant_id`,`name`),
    CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Contactos / clientes
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `uuid`              CHAR(36) NOT NULL,
    `first_name`        VARCHAR(120) NOT NULL,
    `last_name`         VARCHAR(120) NULL,
    `company`           VARCHAR(180) NULL,
    `position`          VARCHAR(120) NULL,
    `email`             VARCHAR(180) NULL,
    `phone`             VARCHAR(40) NULL,
    `whatsapp`          VARCHAR(40) NULL,
    `document_type`     VARCHAR(20) NULL,
    `document_number`   VARCHAR(60) NULL,
    `address`           VARCHAR(255) NULL,
    `city`              VARCHAR(80) NULL,
    `state`             VARCHAR(80) NULL,
    `country`           VARCHAR(2) NULL,
    `postal_code`       VARCHAR(20) NULL,
    `birthday`          DATE NULL,
    `gender`            ENUM('male','female','other') NULL,
    `source`            VARCHAR(60) NULL,
    `status`            ENUM('lead','active','inactive','vip','blocked') NOT NULL DEFAULT 'lead',
    `lifecycle_stage`   VARCHAR(50) NULL,
    `estimated_value`   DECIMAL(12,2) NULL,
    `score`             INT UNSIGNED NOT NULL DEFAULT 0,
    `assigned_to`       BIGINT UNSIGNED NULL,
    `last_interaction`  DATETIME NULL,
    `next_follow_up`    DATETIME NULL,
    `notes`             TEXT NULL,
    `custom_fields`     JSON NULL,
    `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
    `consent_at`        DATETIME NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_contacts_uuid` (`uuid`),
    UNIQUE KEY `uk_contacts_tenant_phone` (`tenant_id`,`phone`),
    UNIQUE KEY `uk_contacts_tenant_whatsapp` (`tenant_id`,`whatsapp`),
    KEY `idx_contacts_tenant` (`tenant_id`),
    KEY `idx_contacts_email` (`email`),
    KEY `idx_contacts_status` (`status`),
    KEY `idx_contacts_assigned` (`assigned_to`),
    KEY `idx_contacts_score` (`score`),
    CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_contacts_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Contact <-> Tag
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `contact_tags`;
CREATE TABLE `contact_tags` (
    `contact_id`    BIGINT UNSIGNED NOT NULL,
    `tag_id`        BIGINT UNSIGNED NOT NULL,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`contact_id`,`tag_id`),
    KEY `idx_ct_tenant` (`tenant_id`),
    CONSTRAINT `fk_ct_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ct_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ct_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Pipeline (etapas)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pipeline_stages`;
CREATE TABLE `pipeline_stages` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(80) NOT NULL,
    `slug`          VARCHAR(80) NOT NULL,
    `color`         VARCHAR(20) NOT NULL DEFAULT '#7C3AED',
    `probability`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_won`        TINYINT(1) NOT NULL DEFAULT 0,
    `is_lost`       TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order`    INT NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ps_tenant_slug` (`tenant_id`,`slug`),
    CONSTRAINT `fk_ps_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Leads / Oportunidades
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `contact_id`        BIGINT UNSIGNED NULL,
    `stage_id`          BIGINT UNSIGNED NOT NULL,
    `title`             VARCHAR(180) NOT NULL,
    `description`       TEXT NULL,
    `value`             DECIMAL(12,2) NOT NULL DEFAULT 0,
    `currency`          VARCHAR(3) NOT NULL DEFAULT 'USD',
    `probability`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expected_close`    DATE NULL,
    `actual_close`      DATE NULL,
    `source`            VARCHAR(60) NULL,
    `assigned_to`       BIGINT UNSIGNED NULL,
    `ai_score`          INT UNSIGNED NOT NULL DEFAULT 0,
    `ai_recommendation` TEXT NULL,
    `status`            ENUM('open','won','lost') NOT NULL DEFAULT 'open',
    `lost_reason`       VARCHAR(255) NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leads_tenant` (`tenant_id`),
    KEY `idx_leads_contact` (`contact_id`),
    KEY `idx_leads_stage` (`stage_id`),
    KEY `idx_leads_assigned` (`assigned_to`),
    KEY `idx_leads_status` (`status`),
    CONSTRAINT `fk_leads_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_leads_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_stage` FOREIGN KEY (`stage_id`) REFERENCES `pipeline_stages` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_leads_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Conversaciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `contact_id`        BIGINT UNSIGNED NOT NULL,
    `channel`           ENUM('whatsapp','email','sms','webchat','telegram','instagram','facebook') NOT NULL DEFAULT 'whatsapp',
    `external_id`       VARCHAR(120) NULL,
    `status`            ENUM('new','open','pending','resolved','closed') NOT NULL DEFAULT 'new',
    `priority`          ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `assigned_to`       BIGINT UNSIGNED NULL,
    `subject`           VARCHAR(255) NULL,
    `last_message_at`   DATETIME NULL,
    `last_message`      TEXT NULL,
    `unread_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `is_starred`        TINYINT(1) NOT NULL DEFAULT 0,
    `bot_enabled`       TINYINT(1) NOT NULL DEFAULT 1,
    `ai_summary`        TEXT NULL,
    `ai_sentiment`      ENUM('positive','neutral','negative') NULL,
    `ai_intent`         VARCHAR(120) NULL,
    `ai_next_action`    TEXT NULL,
    `closed_at`         DATETIME NULL,
    `closed_reason`     VARCHAR(255) NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_conv_tenant` (`tenant_id`),
    KEY `idx_conv_contact` (`contact_id`),
    KEY `idx_conv_status` (`status`),
    KEY `idx_conv_assigned` (`assigned_to`),
    KEY `idx_conv_last_msg` (`last_message_at`),
    CONSTRAINT `fk_conv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Mensajes
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `conversation_id`   BIGINT UNSIGNED NOT NULL,
    `contact_id`        BIGINT UNSIGNED NOT NULL,
    `user_id`           BIGINT UNSIGNED NULL,
    `direction`         ENUM('inbound','outbound') NOT NULL,
    `type`              ENUM('text','image','document','audio','video','location','contact','sticker','template','interactive','system') NOT NULL DEFAULT 'text',
    `content`           TEXT NULL,
    `media_url`         VARCHAR(500) NULL,
    `media_mime`        VARCHAR(80) NULL,
    `media_size`        BIGINT UNSIGNED NULL,
    `external_id`       VARCHAR(120) NULL,
    `template_name`     VARCHAR(120) NULL,
    `template_data`     JSON NULL,
    `status`            ENUM('queued','sent','delivered','read','failed','received') NOT NULL DEFAULT 'queued',
    `error_message`     TEXT NULL,
    `is_internal`       TINYINT(1) NOT NULL DEFAULT 0,
    `is_ai_generated`   TINYINT(1) NOT NULL DEFAULT 0,
    `metadata`          JSON NULL,
    `sent_at`           DATETIME NULL,
    `delivered_at`      DATETIME NULL,
    `read_at`           DATETIME NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_msg_tenant` (`tenant_id`),
    KEY `idx_msg_conversation` (`conversation_id`),
    KEY `idx_msg_contact` (`contact_id`),
    KEY `idx_msg_external` (`external_id`),
    KEY `idx_msg_status` (`status`),
    KEY `idx_msg_created` (`created_at`),
    CONSTRAINT `fk_msg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Asignaciones / transferencias de conversaciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `conversation_assignments`;
CREATE TABLE `conversation_assignments` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `conversation_id`   BIGINT UNSIGNED NOT NULL,
    `from_user_id`      BIGINT UNSIGNED NULL,
    `to_user_id`        BIGINT UNSIGNED NULL,
    `action`            ENUM('assigned','transferred','unassigned','closed','reopened') NOT NULL,
    `note`              TEXT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ca_tenant` (`tenant_id`),
    KEY `idx_ca_conv` (`conversation_id`),
    CONSTRAINT `fk_ca_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ca_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tickets
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `code`              VARCHAR(40) NOT NULL,
    `contact_id`        BIGINT UNSIGNED NULL,
    `conversation_id`   BIGINT UNSIGNED NULL,
    `subject`           VARCHAR(255) NOT NULL,
    `description`       TEXT NULL,
    `status`            ENUM('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
    `priority`          ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    `category`          VARCHAR(80) NULL,
    `channel`           VARCHAR(40) NULL,
    `assigned_to`       BIGINT UNSIGNED NULL,
    `created_by`        BIGINT UNSIGNED NULL,
    `due_at`            DATETIME NULL,
    `sla_breached`      TINYINT(1) NOT NULL DEFAULT 0,
    `resolved_at`       DATETIME NULL,
    `closed_at`         DATETIME NULL,
    `satisfaction`      TINYINT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tickets_tenant_code` (`tenant_id`,`code`),
    KEY `idx_tickets_status` (`status`),
    KEY `idx_tickets_priority` (`priority`),
    KEY `idx_tickets_assigned` (`assigned_to`),
    CONSTRAINT `fk_tickets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tickets_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_comments`;
CREATE TABLE `ticket_comments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `ticket_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `body`          TEXT NOT NULL,
    `is_internal`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tc_ticket` (`ticket_id`),
    CONSTRAINT `fk_tc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tareas
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `title`             VARCHAR(200) NOT NULL,
    `description`       TEXT NULL,
    `contact_id`        BIGINT UNSIGNED NULL,
    `lead_id`           BIGINT UNSIGNED NULL,
    `assigned_to`       BIGINT UNSIGNED NULL,
    `created_by`        BIGINT UNSIGNED NULL,
    `due_at`            DATETIME NULL,
    `status`            ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    `priority`          ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `remind_email`      TINYINT(1) NOT NULL DEFAULT 0,
    `remind_whatsapp`   TINYINT(1) NOT NULL DEFAULT 0,
    `repeat_pattern`    VARCHAR(40) NULL,
    `completed_at`      DATETIME NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_tenant` (`tenant_id`),
    KEY `idx_tasks_assigned` (`assigned_to`),
    KEY `idx_tasks_status` (`status`),
    KEY `idx_tasks_due` (`due_at`),
    CONSTRAINT `fk_tasks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Plantillas de mensaje
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `category`      VARCHAR(60) NULL,
    `language`      VARCHAR(5) NOT NULL DEFAULT 'es',
    `body`          TEXT NOT NULL,
    `variables`     JSON NULL,
    `external_id`   VARCHAR(120) NULL,
    `status`        ENUM('draft','pending','approved','rejected','disabled') NOT NULL DEFAULT 'draft',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tpl_tenant` (`tenant_id`),
    CONSTRAINT `fk_tpl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Respuestas rapidas
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `quick_replies`;
CREATE TABLE `quick_replies` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `shortcut`      VARCHAR(60) NOT NULL,
    `title`         VARCHAR(120) NULL,
    `body`          TEXT NOT NULL,
    `created_by`    BIGINT UNSIGNED NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_qr_tenant_shortcut` (`tenant_id`,`shortcut`),
    CONSTRAINT `fk_qr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_qr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Campanas
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE `campaigns` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `name`              VARCHAR(150) NOT NULL,
    `description`       TEXT NULL,
    `channel`           ENUM('whatsapp','email','sms') NOT NULL DEFAULT 'whatsapp',
    `template_id`       BIGINT UNSIGNED NULL,
    `message`           TEXT NULL,
    `variables`         JSON NULL,
    `segment_id`        BIGINT UNSIGNED NULL,
    `audience_filters`  JSON NULL,
    `scheduled_at`      DATETIME NULL,
    `started_at`        DATETIME NULL,
    `finished_at`       DATETIME NULL,
    `status`            ENUM('draft','scheduled','sending','completed','paused','cancelled','failed') NOT NULL DEFAULT 'draft',
    `total_recipients`  INT UNSIGNED NOT NULL DEFAULT 0,
    `total_sent`        INT UNSIGNED NOT NULL DEFAULT 0,
    `total_delivered`   INT UNSIGNED NOT NULL DEFAULT 0,
    `total_read`        INT UNSIGNED NOT NULL DEFAULT 0,
    `total_replied`     INT UNSIGNED NOT NULL DEFAULT 0,
    `total_failed`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`        BIGINT UNSIGNED NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_camp_tenant` (`tenant_id`),
    KEY `idx_camp_status` (`status`),
    CONSTRAINT `fk_camp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_camp_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_camp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `campaign_recipients`;
CREATE TABLE `campaign_recipients` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `campaign_id`   BIGINT UNSIGNED NOT NULL,
    `contact_id`    BIGINT UNSIGNED NOT NULL,
    `phone`         VARCHAR(40) NULL,
    `email`         VARCHAR(180) NULL,
    `status`        ENUM('pending','sent','delivered','read','replied','failed','unsubscribed') NOT NULL DEFAULT 'pending',
    `external_id`   VARCHAR(120) NULL,
    `error_message` TEXT NULL,
    `sent_at`       DATETIME NULL,
    `delivered_at`  DATETIME NULL,
    `read_at`       DATETIME NULL,
    `replied_at`    DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cr_campaign` (`campaign_id`),
    KEY `idx_cr_contact` (`contact_id`),
    KEY `idx_cr_status` (`status`),
    CONSTRAINT `fk_cr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Segmentos
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `segments`;
CREATE TABLE `segments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `description`   VARCHAR(255) NULL,
    `filters`       JSON NULL,
    `is_dynamic`    TINYINT(1) NOT NULL DEFAULT 1,
    `count_cached`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_seg_tenant` (`tenant_id`),
    CONSTRAINT `fk_seg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Automatizaciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `automations`;
CREATE TABLE `automations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `description`   TEXT NULL,
    `trigger_event` VARCHAR(80) NOT NULL,
    `conditions`    JSON NULL,
    `actions`       JSON NOT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `runs_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `last_run_at`   DATETIME NULL,
    `created_by`    BIGINT UNSIGNED NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_auto_tenant` (`tenant_id`),
    KEY `idx_auto_active` (`is_active`),
    CONSTRAINT `fk_auto_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_auto_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `automation_logs`;
CREATE TABLE `automation_logs` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `automation_id`     BIGINT UNSIGNED NOT NULL,
    `entity_type`       VARCHAR(60) NULL,
    `entity_id`         BIGINT UNSIGNED NULL,
    `status`            ENUM('success','failed','skipped') NOT NULL,
    `actions_executed`  JSON NULL,
    `error_message`     TEXT NULL,
    `execution_time_ms` INT UNSIGNED NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_tenant` (`tenant_id`),
    KEY `idx_al_auto` (`automation_id`),
    CONSTRAINT `fk_al_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_al_auto` FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Logs de integraciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `direction`     ENUM('outbound','inbound','webhook') NOT NULL,
    `endpoint`      VARCHAR(255) NULL,
    `request_body`  TEXT NULL,
    `response_body` TEXT NULL,
    `status_code`   INT NULL,
    `success`       TINYINT(1) NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,
    `duration_ms`   INT UNSIGNED NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wal_tenant` (`tenant_id`),
    KEY `idx_wal_created` (`created_at`),
    CONSTRAINT `fk_wal_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NULL,
    `to_email`      VARCHAR(180) NOT NULL,
    `from_email`    VARCHAR(180) NULL,
    `subject`       VARCHAR(255) NULL,
    `template`      VARCHAR(120) NULL,
    `status`        ENUM('queued','sent','delivered','failed','bounced','complained') NOT NULL DEFAULT 'queued',
    `external_id`   VARCHAR(120) NULL,
    `error_message` TEXT NULL,
    `sent_at`       DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_el_tenant` (`tenant_id`),
    KEY `idx_el_status` (`status`),
    KEY `idx_el_to` (`to_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_logs`;
CREATE TABLE `ai_logs` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         BIGINT UNSIGNED NOT NULL,
    `user_id`           BIGINT UNSIGNED NULL,
    `conversation_id`   BIGINT UNSIGNED NULL,
    `feature`           VARCHAR(80) NOT NULL,
    `model`             VARCHAR(80) NULL,
    `prompt`            TEXT NULL,
    `response`          TEXT NULL,
    `tokens_input`      INT UNSIGNED NULL,
    `tokens_output`     INT UNSIGNED NULL,
    `cost`              DECIMAL(10,6) NULL,
    `duration_ms`       INT UNSIGNED NULL,
    `success`           TINYINT(1) NOT NULL DEFAULT 1,
    `error_message`     TEXT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ail_tenant` (`tenant_id`),
    KEY `idx_ail_feature` (`feature`),
    CONSTRAINT `fk_ail_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Base de conocimiento (entrenamiento IA)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `knowledge_base`;
CREATE TABLE `knowledge_base` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `category`      VARCHAR(80) NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `content`       TEXT NOT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`    INT NOT NULL DEFAULT 0,
    `created_by`    BIGINT UNSIGNED NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kb_tenant` (`tenant_id`),
    KEY `idx_kb_category` (`category`),
    CONSTRAINT `fk_kb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Archivos
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `entity_type`   VARCHAR(60) NULL,
    `entity_id`     BIGINT UNSIGNED NULL,
    `name`          VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `path`          VARCHAR(500) NOT NULL,
    `mime`          VARCHAR(80) NULL,
    `size`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `extension`     VARCHAR(20) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_files_tenant` (`tenant_id`),
    KEY `idx_files_entity` (`entity_type`,`entity_id`),
    CONSTRAINT `fk_files_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Notificaciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `type`          VARCHAR(60) NOT NULL,
    `title`         VARCHAR(180) NOT NULL,
    `body`          TEXT NULL,
    `link`          VARCHAR(500) NULL,
    `icon`          VARCHAR(80) NULL,
    `read_at`       DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`),
    KEY `idx_notif_read` (`read_at`),
    CONSTRAINT `fk_notif_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Auditoria
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `action`        VARCHAR(80) NOT NULL,
    `entity_type`   VARCHAR(60) NULL,
    `entity_id`     BIGINT UNSIGNED NULL,
    `old_values`    JSON NULL,
    `new_values`    JSON NULL,
    `ip_address`    VARCHAR(45) NULL,
    `user_agent`    VARCHAR(500) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_tenant` (`tenant_id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_entity` (`entity_type`,`entity_id`),
    KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Configuraciones
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     BIGINT UNSIGNED NULL,
    `key`           VARCHAR(120) NOT NULL,
    `value`         TEXT NULL,
    `type`          VARCHAR(20) NOT NULL DEFAULT 'string',
    `is_public`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_tenant_key` (`tenant_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Sesiones de rate limit (storage simple)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_hash`      VARCHAR(64) NOT NULL,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`    DATETIME NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rl_key` (`key_hash`),
    KEY `idx_rl_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
