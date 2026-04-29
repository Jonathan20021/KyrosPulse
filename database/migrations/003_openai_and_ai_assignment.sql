-- ============================================================================
-- Kyros Pulse - Soporte OpenAI + asignacion de IA por conversacion
-- ============================================================================
-- Idempotente: cada cambio se envuelve en un procedure helper que verifica
-- si la columna/indice ya existen antes de crearlos. Asi puedes volver a
-- ejecutar install.php sin errores de "Duplicate column".
-- ============================================================================

DROP PROCEDURE IF EXISTS kp003_add_col;
DROP PROCEDURE IF EXISTS kp003_add_idx;

DELIMITER //

CREATE PROCEDURE kp003_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
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

CREATE PROCEDURE kp003_add_idx(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255))
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

-- Tenants: nuevas columnas para OpenAI y selector de proveedor
CALL kp003_add_col('tenants', 'ai_provider',     "VARCHAR(20) NOT NULL DEFAULT 'claude' AFTER `claude_model`");
CALL kp003_add_col('tenants', 'openai_api_key',  "VARCHAR(255) NULL AFTER `ai_provider`");
CALL kp003_add_col('tenants', 'openai_model',    "VARCHAR(80) NULL AFTER `openai_api_key`");

-- Conversations: asignacion de agente IA + handoff
CALL kp003_add_col('conversations', 'ai_agent_id',     "BIGINT UNSIGNED NULL AFTER `assigned_to`");
CALL kp003_add_col('conversations', 'ai_takeover',     "TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_agent_id`");
CALL kp003_add_col('conversations', 'ai_paused_until', "DATETIME NULL AFTER `ai_takeover`");
CALL kp003_add_col('conversations', 'ai_score',        "TINYINT NULL AFTER `ai_intent`");
CALL kp003_add_idx('conversations', 'idx_conv_ai_agent', '`ai_agent_id`');

-- ai_agent_logs: payload de acciones ejecutadas + tokens
CALL kp003_add_col('ai_agent_logs', 'action_payload', "JSON NULL AFTER `action`");
CALL kp003_add_col('ai_agent_logs', 'tokens_input',   "INT UNSIGNED NULL AFTER `success`");
CALL kp003_add_col('ai_agent_logs', 'tokens_output',  "INT UNSIGNED NULL AFTER `tokens_input`");

-- Limpieza de procedures helpers
DROP PROCEDURE IF EXISTS kp003_add_col;
DROP PROCEDURE IF EXISTS kp003_add_idx;
