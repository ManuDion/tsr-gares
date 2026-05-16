-- ============================================================================
-- TSR - Script de mise a niveau MySQL SANS perte de donnees (incremental)
-- Date: 2026-05-15
-- Objectif: completer la mise a niveau apres mysql_upgrade_safe_2026_05_09.sql
-- IMPORTANT:
-- 1) Faire une sauvegarde AVANT execution.
-- 2) Executer d'abord en preproduction.
-- 3) Ce script est idempotent: il peut etre relance.
-- ============================================================================

SET NAMES utf8mb4;
SET @db := DATABASE();

-- --------------------------------------------------------------------------
-- Helpers idempotents
-- --------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;
DROP PROCEDURE IF EXISTS sp_add_fk_if_missing;

DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
          AND column_name COLLATE utf8_general_ci = p_column COLLATE utf8_general_ci
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_index_sql TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
          AND index_name COLLATE utf8_general_ci = p_index COLLATE utf8_general_ci
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` ', p_index_sql);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_add_fk_if_missing(
    IN p_table VARCHAR(64),
    IN p_fk_name VARCHAR(64),
    IN p_fk_sql TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
          AND constraint_name COLLATE utf8_general_ci = p_fk_name COLLATE utf8_general_ci
          AND constraint_type = 'FOREIGN KEY'
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_fk_name, '` ', p_fk_sql);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- --------------------------------------------------------------------------
-- 1) Colonnes utilisateurs (multi-gares et collecte caissier)
-- --------------------------------------------------------------------------
CALL sp_add_column_if_missing('users', 'allow_multi_gare_entry', '`allow_multi_gare_entry` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gare_id`');
CALL sp_add_column_if_missing('users', 'cashier_collection_mode', '`cashier_collection_mode` VARCHAR(30) NOT NULL DEFAULT ''both'' AFTER `allow_multi_gare_entry`');

UPDATE `users`
SET `cashier_collection_mode` = 'both'
WHERE `cashier_collection_mode` IS NULL
   OR TRIM(`cashier_collection_mode`) = ''
   OR `cashier_collection_mode` NOT IN ('both', 'inter_only', 'national_only');

-- --------------------------------------------------------------------------
-- 2) Table de liaison parametrage bancaire -> gares
-- --------------------------------------------------------------------------
SET @can_create_override_gare := (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
              AND table_name COLLATE utf8_general_ci = 'bank_routing_overrides'
        )
        AND EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
              AND table_name COLLATE utf8_general_ci = 'gares'
        )
        THEN 1 ELSE 0
    END
);

SET @sql_override_gare := IF(
    @can_create_override_gare = 1,
    'CREATE TABLE IF NOT EXISTS `bank_routing_override_gare` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `bank_routing_override_id` BIGINT UNSIGNED NOT NULL,
      `gare_id` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NULL,
      `updated_at` TIMESTAMP NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `br_override_gare_unique` (`bank_routing_override_id`,`gare_id`),
      KEY `bank_routing_override_gare_bank_routing_override_id_index` (`bank_routing_override_id`),
      KEY `bank_routing_override_gare_gare_id_index` (`gare_id`),
      CONSTRAINT `bank_routing_override_gare_bank_routing_override_id_foreign`
        FOREIGN KEY (`bank_routing_override_id`) REFERENCES `bank_routing_overrides` (`id`) ON DELETE CASCADE,
      CONSTRAINT `bank_routing_override_gare_gare_id_foreign`
        FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT ''INFO: table bank_routing_overrides ou gares absente, creation bank_routing_override_gare ignoree'' AS info'
);
PREPARE stmt_override_gare FROM @sql_override_gare;
EXECUTE stmt_override_gare;
DEALLOCATE PREPARE stmt_override_gare;

-- --------------------------------------------------------------------------
-- 3) Contrainte anti-doublon versements (scope + gare + date + compte)
-- --------------------------------------------------------------------------
SET @dup_versement_count := (
    SELECT COUNT(*)
    FROM (
        SELECT `service_scope`, `gare_id`, `operation_date`, `account_type`, COUNT(*) c
        FROM `versement_bancaires`
        GROUP BY `service_scope`, `gare_id`, `operation_date`, `account_type`
        HAVING COUNT(*) > 1
    ) t
);

SET @has_versement_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
      AND table_name COLLATE utf8_general_ci = 'versement_bancaires'
      AND index_name COLLATE utf8_general_ci = 'versements_scope_gare_operation_account_unique'
      AND non_unique = 0
);

SET @sql_versement_unique := IF(
    @has_versement_unique > 0,
    'SELECT ''Index versements_scope_gare_operation_account_unique deja present'' AS info',
    IF(
        @dup_versement_count = 0,
        'ALTER TABLE `versement_bancaires` ADD UNIQUE KEY `versements_scope_gare_operation_account_unique` (`service_scope`,`gare_id`,`operation_date`,`account_type`)',
        'SELECT ''ATTENTION: doublons detectes dans versement_bancaires. Index unique NON applique. Nettoyez les doublons puis relancez.'' AS warning'
    )
);
PREPARE stmt_versement_unique FROM @sql_versement_unique;
EXECUTE stmt_versement_unique;
DEALLOCATE PREPARE stmt_versement_unique;

-- --------------------------------------------------------------------------
-- 4) Chat audio
-- --------------------------------------------------------------------------
CALL sp_add_column_if_missing('chat_messages', 'message_type', '`message_type` VARCHAR(20) NOT NULL DEFAULT ''text'' AFTER `content`');
CALL sp_add_index_if_missing('chat_messages', 'chat_messages_message_type_index', 'ADD INDEX `chat_messages_message_type_index` (`message_type`)');
CALL sp_add_column_if_missing('chat_messages', 'audio_disk', '`audio_disk` VARCHAR(50) NULL AFTER `message_type`');
CALL sp_add_column_if_missing('chat_messages', 'audio_path', '`audio_path` VARCHAR(255) NULL AFTER `audio_disk`');
CALL sp_add_column_if_missing('chat_messages', 'audio_mime_type', '`audio_mime_type` VARCHAR(120) NULL AFTER `audio_path`');
CALL sp_add_column_if_missing('chat_messages', 'audio_size', '`audio_size` BIGINT UNSIGNED NULL AFTER `audio_mime_type`');

UPDATE `chat_messages`
SET `message_type` = 'text'
WHERE `message_type` IS NULL OR TRIM(`message_type`) = '';

-- --------------------------------------------------------------------------
-- 5) Post-verifications
-- --------------------------------------------------------------------------
SELECT 'Doublons versements (scope+gare+date+compte)' AS controle, COUNT(*) AS total_groupes
FROM (
    SELECT service_scope, gare_id, operation_date, account_type, COUNT(*) c
    FROM versement_bancaires
    GROUP BY service_scope, gare_id, operation_date, account_type
    HAVING COUNT(*) > 1
) d;

-- --------------------------------------------------------------------------
-- Cleanup helpers
-- --------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;
DROP PROCEDURE IF EXISTS sp_add_fk_if_missing;

-- Fin du script
