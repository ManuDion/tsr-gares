-- ============================================================================
-- TSR - Script de mise a niveau MySQL SANS perte de donnees
-- Date: 2026-05-09
-- Objectif: aligner une base existante vers la version applicative courante.
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
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;

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

CREATE PROCEDURE sp_drop_index_if_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
          AND table_name COLLATE utf8_general_ci = p_table COLLATE utf8_general_ci
          AND index_name COLLATE utf8_general_ci = p_index COLLATE utf8_general_ci
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
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
-- 1) RH / structure de base
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL sp_add_column_if_missing('users', 'phone', '`phone` VARCHAR(40) NULL AFTER `name`');
CALL sp_add_column_if_missing('users', 'must_change_password', '`must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`');
CALL sp_add_column_if_missing('users', 'department_id', '`department_id` BIGINT UNSIGNED NULL AFTER `gare_id`');
CALL sp_add_index_if_missing('users', 'users_department_id_index', 'ADD INDEX `users_department_id_index` (`department_id`)');
CALL sp_add_fk_if_missing('users', 'users_department_id_foreign', 'FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL');

CREATE TABLE IF NOT EXISTS `employees` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_code` VARCHAR(60) NOT NULL,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `full_name` VARCHAR(255) NULL,
  `phone` VARCHAR(60) NULL,
  `email` VARCHAR(255) NULL,
  `job_title` VARCHAR(150) NULL,
  `hire_date` DATE NULL,
  `employment_status` VARCHAR(40) NOT NULL DEFAULT 'active',
  `user_id` BIGINT UNSIGNED NULL,
  `department_id` BIGINT UNSIGNED NULL,
  `gare_id` BIGINT UNSIGNED NULL,
  `mobile_app_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
  KEY `employees_department_id_gare_id_index` (`department_id`,`gare_id`),
  KEY `employees_user_id_index` (`user_id`),
  KEY `employees_department_id_index` (`department_id`),
  KEY `employees_gare_id_index` (`gare_id`),
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED NULL,
  `gare_id` BIGINT UNSIGNED NULL,
  `job_title` VARCHAR(150) NULL,
  `assigned_at` DATE NOT NULL,
  `ended_at` DATE NULL,
  `decision_reference` VARCHAR(120) NULL,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `employee_assignments_employee_id_index` (`employee_id`),
  KEY `employee_assignments_department_id_index` (`department_id`),
  KEY `employee_assignments_gare_id_index` (`gare_id`),
  KEY `employee_assignments_created_by_index` (`created_by`),
  CONSTRAINT `employee_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_assignments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignments_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(120) NOT NULL,
  `label` VARCHAR(180) NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `disk` VARCHAR(50) NOT NULL DEFAULT 'private',
  `path` VARCHAR(255) NOT NULL,
  `expires_at` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `employee_documents_employee_document_type_index` (`employee_id`,`document_type`),
  KEY `employee_documents_uploaded_by_index` (`uploaded_by`),
  CONSTRAINT `employee_documents_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 2) Finance - colonnes et tables de controle
-- --------------------------------------------------------------------------
CALL sp_add_column_if_missing('recettes', 'service_scope', '`service_scope` VARCHAR(30) NOT NULL DEFAULT ''gares'' AFTER `id`');
CALL sp_add_column_if_missing('depenses', 'service_scope', '`service_scope` VARCHAR(30) NOT NULL DEFAULT ''gares'' AFTER `id`');
CALL sp_add_column_if_missing('versement_bancaires', 'service_scope', '`service_scope` VARCHAR(30) NOT NULL DEFAULT ''gares'' AFTER `id`');
CALL sp_add_column_if_missing('verification_checks', 'service_scope', '`service_scope` VARCHAR(30) NOT NULL DEFAULT ''gares'' AFTER `id`');
CALL sp_add_column_if_missing('daily_controls', 'service_scope', '`service_scope` VARCHAR(30) NOT NULL DEFAULT ''gares'' AFTER `id`');

UPDATE `recettes` SET `service_scope` = 'gares' WHERE `service_scope` IS NULL OR `service_scope` = '';
UPDATE `depenses` SET `service_scope` = 'gares' WHERE `service_scope` IS NULL OR `service_scope` = '';
UPDATE `versement_bancaires` SET `service_scope` = 'gares' WHERE `service_scope` IS NULL OR `service_scope` = '';
UPDATE `verification_checks` SET `service_scope` = 'gares' WHERE `service_scope` IS NULL OR `service_scope` = '';
UPDATE `daily_controls` SET `service_scope` = 'gares' WHERE `service_scope` IS NULL OR `service_scope` = '';

CALL sp_add_index_if_missing('recettes', 'recettes_service_scope_index', 'ADD INDEX `recettes_service_scope_index` (`service_scope`)');
CALL sp_add_index_if_missing('depenses', 'depenses_service_scope_index', 'ADD INDEX `depenses_service_scope_index` (`service_scope`)');
CALL sp_add_index_if_missing('versement_bancaires', 'versement_bancaires_service_scope_index', 'ADD INDEX `versement_bancaires_service_scope_index` (`service_scope`)');
CALL sp_add_index_if_missing('verification_checks', 'verification_checks_service_scope_index', 'ADD INDEX `verification_checks_service_scope_index` (`service_scope`)');
CALL sp_add_index_if_missing('daily_controls', 'daily_controls_service_scope_index', 'ADD INDEX `daily_controls_service_scope_index` (`service_scope`)');

CALL sp_add_column_if_missing('recettes', 'ticket_inter_amount', '`ticket_inter_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `amount`');
CALL sp_add_column_if_missing('recettes', 'ticket_national_amount', '`ticket_national_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `ticket_inter_amount`');
CALL sp_add_column_if_missing('recettes', 'bagage_inter_amount', '`bagage_inter_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `ticket_national_amount`');
CALL sp_add_column_if_missing('recettes', 'bagage_national_amount', '`bagage_national_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `bagage_inter_amount`');

UPDATE `recettes`
SET `ticket_inter_amount` = `amount`,
    `ticket_national_amount` = 0,
    `bagage_inter_amount` = 0,
    `bagage_national_amount` = 0
WHERE (`ticket_inter_amount` = 0 AND `ticket_national_amount` = 0 AND `bagage_inter_amount` = 0 AND `bagage_national_amount` = 0);

CALL sp_add_column_if_missing('depenses', 'force_unlocked_until', '`force_unlocked_until` TIMESTAMP NULL AFTER `updated_by`');
CALL sp_add_column_if_missing('depenses', 'unlock_reason', '`unlock_reason` TEXT NULL AFTER `force_unlocked_until`');
CALL sp_add_column_if_missing('depenses', 'unlocked_by', '`unlocked_by` BIGINT UNSIGNED NULL AFTER `unlock_reason`');
CALL sp_add_fk_if_missing('depenses', 'depenses_unlocked_by_foreign', 'FOREIGN KEY (`unlocked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL');

CALL sp_add_column_if_missing('versement_bancaires', 'receipt_date', '`receipt_date` DATE NULL AFTER `operation_date`');
CALL sp_add_column_if_missing('versement_bancaires', 'force_unlocked_until', '`force_unlocked_until` TIMESTAMP NULL AFTER `updated_by`');
CALL sp_add_column_if_missing('versement_bancaires', 'unlock_reason', '`unlock_reason` TEXT NULL AFTER `force_unlocked_until`');
CALL sp_add_column_if_missing('versement_bancaires', 'unlocked_by', '`unlocked_by` BIGINT UNSIGNED NULL AFTER `unlock_reason`');
CALL sp_add_fk_if_missing('versement_bancaires', 'versement_bancaires_unlocked_by_foreign', 'FOREIGN KEY (`unlocked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL');

CALL sp_add_column_if_missing('versement_bancaires', 'account_type', '`account_type` VARCHAR(20) NOT NULL DEFAULT ''national'' AFTER `amount`');
CALL sp_add_index_if_missing('versement_bancaires', 'versement_bancaires_account_type_index', 'ADD INDEX `versement_bancaires_account_type_index` (`account_type`)');

CREATE TABLE IF NOT EXISTS `depense_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `depense_id` BIGINT UNSIGNED NOT NULL,
  `modified_by` BIGINT UNSIGNED NOT NULL,
  `before` JSON NOT NULL,
  `after` JSON NOT NULL,
  `comment` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `depense_histories_depense_id_index` (`depense_id`),
  KEY `depense_histories_modified_by_index` (`modified_by`),
  CONSTRAINT `depense_histories_depense_id_foreign` FOREIGN KEY (`depense_id`) REFERENCES `depenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `depense_histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `versement_bancaire_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `versement_bancaire_id` BIGINT UNSIGNED NOT NULL,
  `modified_by` BIGINT UNSIGNED NOT NULL,
  `before` JSON NOT NULL,
  `after` JSON NOT NULL,
  `comment` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `versement_bancaire_histories_versement_bancaire_id_index` (`versement_bancaire_id`),
  KEY `versement_bancaire_histories_modified_by_index` (`modified_by`),
  CONSTRAINT `versement_bancaire_histories_versement_bancaire_id_foreign` FOREIGN KEY (`versement_bancaire_id`) REFERENCES `versement_bancaires` (`id`) ON DELETE CASCADE,
  CONSTRAINT `versement_bancaire_histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 3) Multi-service, caisse, gares virtuelles, contraintes bancaires
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_service_modules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `module` VARCHAR(30) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_service_modules_user_id_module_unique` (`user_id`,`module`),
  KEY `user_service_modules_module_index` (`module`),
  CONSTRAINT `user_service_modules_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL sp_add_column_if_missing('gares', 'versement_mode', '`versement_mode` VARCHAR(20) NOT NULL DEFAULT ''direct'' AFTER `address`');
CALL sp_add_column_if_missing('gares', 'cashier_user_id', '`cashier_user_id` BIGINT UNSIGNED NULL AFTER `versement_mode`');
CALL sp_add_column_if_missing('gares', 'activity_mode', '`activity_mode` VARCHAR(20) NOT NULL DEFAULT ''mixed'' AFTER `cashier_user_id`');
CALL sp_add_column_if_missing('gares', 'is_virtual', '`is_virtual` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activity_mode`');
CALL sp_add_column_if_missing('gares', 'virtual_owner_user_id', '`virtual_owner_user_id` BIGINT UNSIGNED NULL AFTER `is_virtual`');
CALL sp_add_column_if_missing('gares', 'virtual_scope', '`virtual_scope` VARCHAR(30) NULL AFTER `virtual_owner_user_id`');

CALL sp_add_index_if_missing('gares', 'gares_versement_mode_index', 'ADD INDEX `gares_versement_mode_index` (`versement_mode`)');
CALL sp_add_index_if_missing('gares', 'gares_cashier_user_id_index', 'ADD INDEX `gares_cashier_user_id_index` (`cashier_user_id`)');
CALL sp_add_index_if_missing('gares', 'gares_activity_mode_index', 'ADD INDEX `gares_activity_mode_index` (`activity_mode`)');
CALL sp_add_index_if_missing('gares', 'gares_is_virtual_index', 'ADD INDEX `gares_is_virtual_index` (`is_virtual`)');
CALL sp_add_index_if_missing('gares', 'gares_virtual_owner_user_id_index', 'ADD INDEX `gares_virtual_owner_user_id_index` (`virtual_owner_user_id`)');
CALL sp_add_index_if_missing('gares', 'gares_virtual_scope_index', 'ADD INDEX `gares_virtual_scope_index` (`virtual_scope`)');

CALL sp_add_fk_if_missing('gares', 'gares_cashier_user_id_foreign', 'FOREIGN KEY (`cashier_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
CALL sp_add_fk_if_missing('gares', 'gares_virtual_owner_user_id_foreign', 'FOREIGN KEY (`virtual_owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');

CREATE TABLE IF NOT EXISTS `cashier_receipt_confirmations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_scope` VARCHAR(30) NOT NULL DEFAULT 'gares',
  `gare_id` BIGINT UNSIGNED NOT NULL,
  `cashier_id` BIGINT UNSIGNED NOT NULL,
  `operation_date` DATE NOT NULL,
  `expected_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `expected_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `expected_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_at` TIMESTAMP NULL,
  `verified_by` BIGINT UNSIGNED NULL,
  `note` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cashier_receipts_scope_gare_cashier_date_unique` (`service_scope`,`gare_id`,`cashier_id`,`operation_date`),
  KEY `cashier_receipts_scope_cashier_date_index` (`service_scope`,`cashier_id`,`operation_date`),
  CONSTRAINT `cashier_receipt_confirmations_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cashier_receipt_confirmations_cashier_id_foreign` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cashier_receipt_confirmations_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bank_routing_overrides` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_scope` VARCHAR(20) NOT NULL DEFAULT 'gares',
  `forced_account_type` VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `bank_routing_overrides_service_scope_index` (`service_scope`),
  KEY `bank_routing_overrides_is_active_index` (`is_active`),
  KEY `bank_routing_scope_period_idx` (`service_scope`,`start_date`,`end_date`),
  CONSTRAINT `bank_routing_overrides_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_routing_overrides_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 4) Verification et controles
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verification_checks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_scope` VARCHAR(30) NOT NULL DEFAULT 'gares',
  `gare_id` BIGINT UNSIGNED NOT NULL,
  `operation_date` DATE NOT NULL,
  `recettes_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `recettes_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `recettes_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `depenses_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `depenses_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `depenses_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `versements_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `versements_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `versements_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `expected_versement` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `expected_inter_versement` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `expected_national_versement` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `difference` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `difference_inter` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `difference_national` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `control_mode` VARCHAR(30) NOT NULL DEFAULT 'direct',
  `modifications_enabled_until` TIMESTAMP NULL,
  `review_note` TEXT NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `verification_checks_operation_date_status_index` (`operation_date`,`status`),
  KEY `verification_checks_gare_id_index` (`gare_id`),
  KEY `verification_checks_service_scope_index` (`service_scope`),
  KEY `verification_checks_control_mode_index` (`control_mode`),
  CONSTRAINT `verification_checks_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verification_checks_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL sp_add_column_if_missing('verification_checks', 'recettes_inter_total', '`recettes_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `recettes_total`');
CALL sp_add_column_if_missing('verification_checks', 'recettes_national_total', '`recettes_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `recettes_inter_total`');
CALL sp_add_column_if_missing('verification_checks', 'depenses_inter_total', '`depenses_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `depenses_total`');
CALL sp_add_column_if_missing('verification_checks', 'depenses_national_total', '`depenses_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `depenses_inter_total`');
CALL sp_add_column_if_missing('verification_checks', 'versements_inter_total', '`versements_inter_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `versements_total`');
CALL sp_add_column_if_missing('verification_checks', 'versements_national_total', '`versements_national_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `versements_inter_total`');
CALL sp_add_column_if_missing('verification_checks', 'expected_inter_versement', '`expected_inter_versement` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `expected_versement`');
CALL sp_add_column_if_missing('verification_checks', 'expected_national_versement', '`expected_national_versement` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `expected_inter_versement`');
CALL sp_add_column_if_missing('verification_checks', 'difference_inter', '`difference_inter` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `difference`');
CALL sp_add_column_if_missing('verification_checks', 'difference_national', '`difference_national` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `difference_inter`');
CALL sp_add_column_if_missing('verification_checks', 'control_mode', '`control_mode` VARCHAR(30) NOT NULL DEFAULT ''direct'' AFTER `status`');
CALL sp_add_index_if_missing('verification_checks', 'verification_checks_control_mode_index', 'ADD INDEX `verification_checks_control_mode_index` (`control_mode`)');

CALL sp_drop_index_if_exists('verification_checks', 'verification_checks_gare_id_operation_date_unique');
CALL sp_add_index_if_missing('verification_checks', 'verification_checks_scope_gare_operation_unique', 'ADD UNIQUE KEY `verification_checks_scope_gare_operation_unique` (`service_scope`,`gare_id`,`operation_date`)');

CALL sp_drop_index_if_exists('daily_controls', 'daily_controls_gare_id_concerned_date_unique');
CALL sp_add_index_if_missing('daily_controls', 'daily_controls_scope_gare_concerned_unique', 'ADD UNIQUE KEY `daily_controls_scope_gare_concerned_unique` (`service_scope`,`gare_id`,`concerned_date`)');

-- --------------------------------------------------------------------------
-- 5) Chat, notifications, courrier interne
-- --------------------------------------------------------------------------
CALL sp_add_column_if_missing('notification_histories', 'source_key', '`source_key` VARCHAR(190) NULL AFTER `type`');
CALL sp_add_index_if_missing('notification_histories', 'notification_histories_source_key_index', 'ADD INDEX `notification_histories_source_key_index` (`source_key`)');

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `conversation_type` VARCHAR(30) NOT NULL DEFAULT 'direct',
  `service_module` VARCHAR(30) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `last_message_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_conversation_type_index` (`conversation_type`),
  KEY `conversations_service_module_index` (`service_module`),
  KEY `conversations_created_by_index` (`created_by`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL sp_add_column_if_missing('conversations', 'conversation_type', '`conversation_type` VARCHAR(30) NOT NULL DEFAULT ''direct'' AFTER `is_group`');
CALL sp_add_column_if_missing('conversations', 'service_module', '`service_module` VARCHAR(30) NULL AFTER `conversation_type`');
CALL sp_add_index_if_missing('conversations', 'conversations_conversation_type_index', 'ADD INDEX `conversations_conversation_type_index` (`conversation_type`)');
CALL sp_add_index_if_missing('conversations', 'conversations_service_module_index', 'ADD INDEX `conversations_service_module_index` (`service_module`)');

UPDATE `conversations`
SET `conversation_type` = CASE WHEN `is_group` = 1 THEN 'service_internal' ELSE 'direct' END
WHERE `conversation_type` IS NULL OR `conversation_type` = '';

UPDATE `conversations`
SET `conversation_type` = 'service_internal'
WHERE `conversation_type` = 'inter_service';

CREATE TABLE IF NOT EXISTS `courriers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(100) NULL,
  `subject` VARCHAR(255) NOT NULL,
  `direction` VARCHAR(30) NOT NULL DEFAULT 'internal',
  `priority` VARCHAR(30) NOT NULL DEFAULT 'normal',
  `status` VARCHAR(40) NOT NULL DEFAULT 'draft',
  `origin_department_id` BIGINT UNSIGNED NULL,
  `destination_department_id` BIGINT UNSIGNED NULL,
  `gare_id` BIGINT UNSIGNED NULL,
  `received_at` TIMESTAMP NULL,
  `due_at` TIMESTAMP NULL,
  `description` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courriers_reference_unique` (`reference`),
  KEY `courriers_direction_status_index` (`direction`,`status`),
  KEY `courriers_origin_department_id_index` (`origin_department_id`),
  KEY `courriers_destination_department_id_index` (`destination_department_id`),
  KEY `courriers_gare_id_index` (`gare_id`),
  KEY `courriers_created_by_index` (`created_by`),
  KEY `courriers_updated_by_index` (`updated_by`),
  CONSTRAINT `courriers_origin_department_id_foreign` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courriers_destination_department_id_foreign` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courriers_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courriers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courriers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `workflow_transfers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_type` VARCHAR(255) NULL,
  `subject_id` BIGINT UNSIGNED NULL,
  `reference` VARCHAR(100) NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `origin_department_id` BIGINT UNSIGNED NULL,
  `destination_department_id` BIGINT UNSIGNED NULL,
  `transferred_by` BIGINT UNSIGNED NULL,
  `received_by` BIGINT UNSIGNED NULL,
  `transferred_at` TIMESTAMP NULL,
  `received_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_transfers_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `workflow_transfers_status_destination_department_id_index` (`status`,`destination_department_id`),
  KEY `workflow_transfers_origin_department_id_index` (`origin_department_id`),
  KEY `workflow_transfers_transferred_by_index` (`transferred_by`),
  KEY `workflow_transfers_received_by_index` (`received_by`),
  CONSTRAINT `workflow_transfers_origin_department_id_foreign` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflow_transfers_destination_department_id_foreign` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflow_transfers_transferred_by_foreign` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflow_transfers_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- 6) Contrainte anti-doublon sur recettes (scope + gare + date)
-- --------------------------------------------------------------------------
SET @dup_count := (
    SELECT COUNT(*)
    FROM (
        SELECT `service_scope`, `gare_id`, `operation_date`, COUNT(*) c
        FROM `recettes`
        GROUP BY `service_scope`, `gare_id`, `operation_date`
        HAVING COUNT(*) > 1
    ) t
);

SET @has_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema COLLATE utf8_general_ci = @db COLLATE utf8_general_ci
      AND table_name COLLATE utf8_general_ci = 'recettes'
      AND index_name COLLATE utf8_general_ci = 'recettes_scope_gare_operation_unique'
      AND non_unique = 0
);

SET @sql_unique := IF(
    @has_unique > 0,
    'SELECT ''Index recettes_scope_gare_operation_unique deja present'' AS info',
    IF(
        @dup_count = 0,
        'ALTER TABLE `recettes` ADD UNIQUE KEY `recettes_scope_gare_operation_unique` (`service_scope`,`gare_id`,`operation_date`)',
        'SELECT ''ATTENTION: doublons detectes dans recettes. Index unique NON applique. Nettoyez les doublons puis relancez.'' AS warning'
    )
);
PREPARE stmt_unique FROM @sql_unique;
EXECUTE stmt_unique;
DEALLOCATE PREPARE stmt_unique;

-- --------------------------------------------------------------------------
-- 7) Post-verifications
-- --------------------------------------------------------------------------
SELECT 'Doublons recettes (scope+gare+date)' AS controle, COUNT(*) AS total_groupes
FROM (
    SELECT service_scope, gare_id, operation_date, COUNT(*) c
    FROM recettes
    GROUP BY service_scope, gare_id, operation_date
    HAVING COUNT(*) > 1
) d;

SELECT 'Gares inter_only rattachees a un user_service_modules=courrier (controle metier)' AS controle,
       COUNT(*) AS total
FROM gares g
JOIN users u ON u.gare_id = g.id
JOIN user_service_modules usm ON usm.user_id = u.id AND usm.module = 'courrier'
WHERE g.activity_mode = 'inter_only';

-- --------------------------------------------------------------------------
-- Cleanup helpers
-- --------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;
DROP PROCEDURE IF EXISTS sp_add_fk_if_missing;
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;

-- Fin du script
