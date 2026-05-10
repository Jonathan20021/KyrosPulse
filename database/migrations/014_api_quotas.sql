-- ============================================================================
-- Kyros Pulse - API quotas por plan y tracking de uso mensual
-- ============================================================================
-- Anade:
--   plans.api_quota_monthly         : cuota mensual del plan (0 = sin acceso, -1 = ilimitado)
--   tenants.api_quota_override      : override del super admin (NULL = usa plan)
--   tenants.api_calls_period        : contador del periodo actual (auto-reset)
--   tenants.api_period_starts_at    : inicio del periodo de conteo (auto-roll mensual)
--   tenants.api_quota_alerted_at    : ultima vez que avisamos "estas al 80%/100%"
-- ============================================================================

DROP PROCEDURE IF EXISTS kp014_add_col;
DELIMITER //
CREATE PROCEDURE kp014_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
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
DELIMITER ;

CALL kp014_add_col('plans',   'api_quota_monthly',     'INT NOT NULL DEFAULT 1000');
CALL kp014_add_col('tenants', 'api_quota_override',    'INT NULL');
CALL kp014_add_col('tenants', 'api_calls_period',      'INT UNSIGNED NOT NULL DEFAULT 0');
CALL kp014_add_col('tenants', 'api_period_starts_at',  'DATETIME NULL');
CALL kp014_add_col('tenants', 'api_quota_alerted_at',  'DATETIME NULL');

DROP PROCEDURE IF EXISTS kp014_add_col;

-- Defaults razonables por slug si existen
UPDATE `plans` SET `api_quota_monthly` = 1000      WHERE `slug` = 'free'       AND `api_quota_monthly` = 0;
UPDATE `plans` SET `api_quota_monthly` = 50000     WHERE `slug` = 'basic';
UPDATE `plans` SET `api_quota_monthly` = 250000    WHERE `slug` = 'pro';
UPDATE `plans` SET `api_quota_monthly` = -1        WHERE `slug` = 'enterprise';
