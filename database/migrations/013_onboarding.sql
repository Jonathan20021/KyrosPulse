-- ============================================================================
-- Kyros Pulse - Onboarding wizard state
-- ============================================================================
-- Anade columnas a `tenants` para trackear el progreso del wizard de
-- onboarding sin tocar el resto del schema. Idempotente.
-- ============================================================================

DROP PROCEDURE IF EXISTS kp013_add_col;
DELIMITER //
CREATE PROCEDURE kp013_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
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

-- onboarding_step: 0..5 (welcome, business, channel, agent, workflow, done)
-- onboarding_completed_at: NULL hasta que el usuario completa o saltea
-- onboarding_skipped: 1 si dijo "salir y no preguntar mas"
CALL kp013_add_col('tenants', 'onboarding_step',         'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL kp013_add_col('tenants', 'onboarding_completed_at', 'DATETIME NULL');
CALL kp013_add_col('tenants', 'onboarding_skipped',      'TINYINT(1) NOT NULL DEFAULT 0');

DROP PROCEDURE IF EXISTS kp013_add_col;
