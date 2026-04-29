-- ============================================================================
-- Kyros Pulse - Soporte OpenAI + asignacion de IA por conversacion
-- ============================================================================
-- IMPORTANTE: este archivo usa solo statements simples separados por ';'.
-- Para hacerlo idempotente, cada ALTER TABLE va envuelto en un IF NOT EXISTS
-- a nivel de cliente (el script install.php ignora errores de "Duplicate column"
-- cuando se vuelve a ejecutar). Si tu pipeline NO tolera errores, ejecuta este
-- archivo solo una vez por instalacion.
-- ============================================================================

-- Tenants: nuevas columnas para OpenAI y selector de proveedor
ALTER TABLE `tenants` ADD COLUMN `ai_provider`    VARCHAR(20)  NOT NULL DEFAULT 'claude' AFTER `claude_model`;
ALTER TABLE `tenants` ADD COLUMN `openai_api_key` VARCHAR(255) NULL                       AFTER `ai_provider`;
ALTER TABLE `tenants` ADD COLUMN `openai_model`   VARCHAR(80)  NULL                       AFTER `openai_api_key`;

-- Conversations: asignacion de agente IA + handoff
ALTER TABLE `conversations` ADD COLUMN `ai_agent_id`     BIGINT UNSIGNED NULL              AFTER `assigned_to`;
ALTER TABLE `conversations` ADD COLUMN `ai_takeover`     TINYINT(1)      NOT NULL DEFAULT 0 AFTER `ai_agent_id`;
ALTER TABLE `conversations` ADD COLUMN `ai_paused_until` DATETIME        NULL              AFTER `ai_takeover`;
ALTER TABLE `conversations` ADD COLUMN `ai_score`        TINYINT         NULL              AFTER `ai_intent`;
ALTER TABLE `conversations` ADD KEY `idx_conv_ai_agent` (`ai_agent_id`);

-- ai_agent_logs: payload de acciones ejecutadas + tokens
ALTER TABLE `ai_agent_logs` ADD COLUMN `action_payload` JSON         NULL AFTER `action`;
ALTER TABLE `ai_agent_logs` ADD COLUMN `tokens_input`   INT UNSIGNED NULL AFTER `success`;
ALTER TABLE `ai_agent_logs` ADD COLUMN `tokens_output`  INT UNSIGNED NULL AFTER `tokens_input`;
