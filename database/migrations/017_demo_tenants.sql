-- ============================================================================
-- Evallish Pulse - Demo tenants con auto-borrado en 24h
-- ============================================================================
-- Permite que un visitante pruebe el SaaS con una cuenta y datos de muestra.
-- La cuenta (tenant + usuarios + datos) se elimina automaticamente 24h despues
-- de su creacion mediante cron/cleanup.php. ON DELETE CASCADE en todas las
-- tablas hijas garantiza la limpieza completa.
--
-- Las cuentas demo se identifican por is_demo=1. La fecha exacta de expiracion
-- se guarda en demo_expires_at para poder ajustar ventanas en el futuro.
-- Idempotente: helpers para no fallar en re-ejecuciones.
-- ============================================================================

DROP PROCEDURE IF EXISTS kp017_add_col;
DROP PROCEDURE IF EXISTS kp017_add_idx;

DELIMITER //

CREATE PROCEDURE kp017_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

CREATE PROCEDURE kp017_add_idx(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD KEY `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- Flag de cuenta demo (1 = creada desde landing, se borra a las 24h).
CALL kp017_add_col('tenants', 'is_demo',         "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");

-- Fecha exacta de expiracion del demo (NULL = no es demo).
CALL kp017_add_col('tenants', 'demo_expires_at', "DATETIME NULL DEFAULT NULL AFTER `is_demo`");

-- Index para que cron/cleanup.php pueda barrer las cuentas demo vencidas.
CALL kp017_add_idx('tenants', 'idx_tenants_demo_expires', '`is_demo`, `demo_expires_at`');

-- Branding: actualizar el nombre del SaaS a "Evallish Pulse" si quedaron
-- residuos de una instalacion anterior con "Kyros Pulse". INSERT IGNORE
-- en 005 no sobreescribe filas ya existentes.
UPDATE `saas_settings` SET `value` = 'Evallish Pulse'
    WHERE `setting_key` = 'brand_name' AND `value` = 'Kyros Pulse';

UPDATE `saas_settings` SET `value` = 'Evallish'
    WHERE `setting_key` = 'legal_company' AND `value` = 'Kyros Solutions';

-- Asegurar que show_pricing esta encendido (instalaciones viejas podrian no tenerlo).
INSERT INTO `saas_settings` (`setting_key`, `value`, `kind`)
VALUES ('show_pricing', '1', 'bool')
ON DUPLICATE KEY UPDATE `value` = '1';

-- ----------------------------------------------------------------------------
-- Licencias: forzar diferenciacion estricta entre planes.
-- Aplica solo a los planes seed (slug conocido). Si el super admin ya
-- personalizo un plan por la UI, esta UPDATE no toca otros slugs.
-- ----------------------------------------------------------------------------

-- Starter: sin IA, sin API, sin reportes avanzados.
UPDATE `plans`
   SET `ai_enabled` = 0, `api_access` = 0, `advanced_reports` = 0,
       `max_users` = 1, `max_contacts` = 500, `max_messages` = 500,
       `max_campaigns` = 1, `max_automations` = 3
 WHERE `slug` = 'starter';

-- Professional: con IA, sin API, sin reportes avanzados.
UPDATE `plans`
   SET `ai_enabled` = 1, `api_access` = 0, `advanced_reports` = 0,
       `max_users` = 5, `max_contacts` = 5000, `max_messages` = 5000,
       `max_campaigns` = 10, `max_automations` = 15
 WHERE `slug` = 'professional';

-- Business: con IA, API y reportes avanzados.
UPDATE `plans`
   SET `ai_enabled` = 1, `api_access` = 1, `advanced_reports` = 1,
       `max_users` = 20, `max_contacts` = 50000, `max_messages` = 50000,
       `max_campaigns` = 50, `max_automations` = 50
 WHERE `slug` = 'business';

-- Enterprise: todo encendido.
UPDATE `plans`
   SET `ai_enabled` = 1, `api_access` = 1, `advanced_reports` = 1,
       `max_users` = 999, `max_contacts` = 999999, `max_messages` = 999999,
       `max_campaigns` = 999, `max_automations` = 999
 WHERE `slug` = 'enterprise';

DROP PROCEDURE IF EXISTS kp017_add_col;
DROP PROCEDURE IF EXISTS kp017_add_idx;
