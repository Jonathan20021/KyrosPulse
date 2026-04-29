-- ============================================================================
-- Kyros Pulse - Changelog publico + SaaS settings (branding y landing)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `changelog_entries` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version`      VARCHAR(40) NULL,
    `title`        VARCHAR(180) NOT NULL,
    `slug`         VARCHAR(200) NOT NULL,
    `category`     ENUM('feature','improvement','fix','security','breaking','announcement') NOT NULL DEFAULT 'feature',
    `summary`      VARCHAR(500) NULL,
    `body`         MEDIUMTEXT NULL,
    `tags`         JSON NULL,
    `author`       VARCHAR(120) NULL,
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `is_featured`  TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` DATETIME NULL,
    `created_by`   BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_changelog_slug` (`slug`),
    KEY `idx_changelog_published` (`is_published`, `published_at`),
    KEY `idx_changelog_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings globales del SaaS (branding, copy del landing, contacto)
-- Usamos `setting_key` para evitar la palabra reservada `key`.
CREATE TABLE IF NOT EXISTS `saas_settings` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(120) NOT NULL,
    `value`       MEDIUMTEXT NULL,
    `kind`        VARCHAR(20) NOT NULL DEFAULT 'string',
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_saas_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed defaults para landing/branding
INSERT IGNORE INTO `saas_settings` (`setting_key`, `value`, `kind`) VALUES
    ('brand_name',          'Kyros Pulse', 'string'),
    ('brand_tagline',       'El sistema operativo de tu negocio digital', 'string'),
    ('hero_eyebrow',        'CRM + WhatsApp + IA en una plataforma', 'string'),
    ('hero_headline',       'Convierte cada conversacion en una venta — sin contratar mas personal', 'string'),
    ('hero_sub',            'Vendedores, soporte y agendadores IA que atienden por ti 24/7. WhatsApp multiagente, CRM, automatizaciones, catalogo y reportes en un solo panel.', 'string'),
    ('cta_primary_label',   'Empezar gratis 14 dias', 'string'),
    ('cta_primary_url',     '/register', 'string'),
    ('cta_secondary_label', 'Solicitar demo', 'string'),
    ('cta_secondary_url',   'https://wa.me/18495555555?text=Hola%20Kyros%20Pulse', 'string'),
    ('contact_email',       'hola@kyrosrd.com', 'string'),
    ('contact_phone',       '+1 809 555 5555', 'string'),
    ('contact_whatsapp',    'https://wa.me/18495555555', 'string'),
    ('social_x',            '', 'string'),
    ('social_linkedin',     '', 'string'),
    ('social_instagram',    '', 'string'),
    ('show_pricing',        '1', 'bool'),
    ('show_changelog',      '1', 'bool'),
    ('legal_company',       'Kyros Solutions', 'string');
