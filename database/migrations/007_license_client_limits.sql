-- ============================================================================
-- Kyros Pulse - Licencias con limite de clientes (contactos) por tenant
-- ============================================================================
-- Anade gestion granular del limite de clientes (contactos) que cada tenant
-- puede mantener segun su licencia. El limite efectivo se resuelve asi:
--   1) tenants.max_contacts_override (si no es NULL)  -- override del super admin
--   2) plans.max_contacts                              -- limite del plan
--   3) 1000                                            -- fallback por defecto
--
-- client_limit_locked controla si el limite es un tope duro (1 = bloqueo total)
-- o suave (0 = solo advertencias, util para periodos de gracia).
--
-- Idempotente: usa procedures helper para no fallar en re-ejecuciones.
-- ============================================================================

DROP PROCEDURE IF EXISTS kp007_add_col;
DROP PROCEDURE IF EXISTS kp007_add_idx;

DELIMITER //

CREATE PROCEDURE kp007_add_col(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
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

CREATE PROCEDURE kp007_add_idx(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255))
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

-- Override de limite por tenant. NULL = usa el limite del plan.
CALL kp007_add_col('tenants', 'max_contacts_override', "INT UNSIGNED NULL DEFAULT NULL AFTER `plan_id`");

-- 1 = bloqueo duro al alcanzar limite. 0 = limite suave (solo advertencias).
CALL kp007_add_col('tenants', 'client_limit_locked',  "TINYINT(1) NOT NULL DEFAULT 1 AFTER `max_contacts_override`");

-- Cache de uso (opcional, recalculado al crear/borrar contactos). NULL = forzar recalculo.
CALL kp007_add_col('tenants', 'clients_count_cached', "INT UNSIGNED NULL DEFAULT NULL AFTER `client_limit_locked`");

-- Marca de la ultima vez que el tenant golpeo su limite (auditoria).
CALL kp007_add_col('tenants', 'client_limit_hit_at',  "DATETIME NULL DEFAULT NULL AFTER `clients_count_cached`");

-- Indice para queries del panel super admin (tenants ordenados por uso).
CALL kp007_add_idx('tenants', 'idx_tenants_clients_count', '`clients_count_cached`');

-- Limpieza de helpers
DROP PROCEDURE IF EXISTS kp007_add_col;
DROP PROCEDURE IF EXISTS kp007_add_idx;
