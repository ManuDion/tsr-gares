-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 22 avr. 2026 à 17:00
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `tsr_gares_finance`
--

-- --------------------------------------------------------

--
-- Structure de la table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gare_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before`)),
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `administrative_documents`
--

CREATE TABLE `administrative_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(120) NOT NULL,
  `label` varchar(180) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `disk` varchar(50) NOT NULL DEFAULT 'private',
  `path` varchar(255) NOT NULL,
  `expires_at` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `last_renewed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conversations`
--

CREATE TABLE `conversations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `is_group` tinyint(1) NOT NULL DEFAULT 0,
  `conversation_type` varchar(30) NOT NULL DEFAULT 'direct',
  `service_module` varchar(30) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conversation_user`
--

CREATE TABLE `conversation_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `courriers`
--

CREATE TABLE `courriers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `direction` varchar(30) NOT NULL DEFAULT 'internal',
  `priority` varchar(30) NOT NULL DEFAULT 'normal',
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `origin_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destination_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gare_id` bigint(20) UNSIGNED DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `daily_controls`
--

CREATE TABLE `daily_controls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_scope` varchar(30) NOT NULL DEFAULT 'gares',
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `control_date` date NOT NULL,
  `concerned_date` date NOT NULL,
  `has_recette` tinyint(1) NOT NULL DEFAULT 0,
  `has_depense` tinyint(1) NOT NULL DEFAULT 0,
  `has_versement` tinyint(1) NOT NULL DEFAULT 0,
  `is_compliant` tinyint(1) NOT NULL DEFAULT 0,
  `missing_operations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`missing_operations`)),
  `generated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `daily_controls`
--

INSERT INTO `daily_controls` (`id`, `service_scope`, `gare_id`, `control_date`, `concerned_date`, `has_recette`, `has_depense`, `has_versement`, `is_compliant`, `missing_operations`, `generated_by`, `created_at`, `updated_at`) VALUES
(28, 'gares', 6, '2026-04-22', '2026-04-21', 0, 0, 0, 0, '[\"recette\",\"depense\",\"versement_bancaire\"]', NULL, '2026-04-22 08:01:00', '2026-04-22 08:01:00'),
(29, 'courrier', 6, '2026-04-22', '2026-04-21', 0, 0, 0, 0, '[\"recette\",\"depense\",\"versement_bancaire\"]', NULL, '2026-04-22 08:29:49', '2026-04-22 08:29:49');

-- --------------------------------------------------------

--
-- Structure de la table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'DIR', 'Direction générale', 'Pilotage global du progiciel TSR.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(2, 'EXP', 'Exploitation / Gares', 'Gestion des gares et opérations terrain.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(3, 'FIN', 'Finance / Comptabilité', 'Suivi financier, rapprochements et contrôles.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(4, 'CTL', 'Contrôle interne', 'Contrôles, conformité et audit.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(5, 'ADM', 'Administratif', 'Gestion administrative et documentaire.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(6, 'RH', 'Service RH', 'Socle de préparation du module RH et du cycle administratif du personnel.', 1, '2026-04-21 09:24:01', '2026-04-21 15:46:03'),
(7, 'CRR', 'Courrier / Secrétariat', 'Base préparatoire pour le futur module courrier.', 1, '2026-04-21 09:24:01', '2026-04-21 09:24:01'),
(8, 'GARES', 'Service de gestion des gares', 'Module principal de gestion des gares : recettes, dépenses, versements et vérifications.', 1, '2026-04-21 15:46:03', '2026-04-21 15:46:03'),
(9, 'DOCS', 'Service de gestion des documents', 'Service de gestion des documents administratifs et réglementaires.', 1, '2026-04-21 15:46:03', '2026-04-21 15:46:03'),
(10, 'COURRIER', 'Service courrier', 'Service courrier fonctionnant avec la même logique métier que le service de gestion des gares.', 1, '2026-04-21 15:46:03', '2026-04-21 15:46:03');

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_scope` varchar(30) NOT NULL DEFAULT 'gares',
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `operation_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `motif` varchar(150) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `force_unlocked_until` timestamp NULL DEFAULT NULL,
  `unlock_reason` text DEFAULT NULL,
  `unlocked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depense_histories`
--

CREATE TABLE `depense_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `depense_id` bigint(20) UNSIGNED NOT NULL,
  `modified_by` bigint(20) UNSIGNED NOT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`before`)),
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`after`)),
  `comment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_analyses`
--

CREATE TABLE `document_analyses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `piece_justificative_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `provider` varchar(60) DEFAULT NULL,
  `extracted_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_data`)),
  `confidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`confidence`)),
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employees`
--

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(60) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_status` varchar(40) NOT NULL DEFAULT 'active',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gare_id` bigint(20) UNSIGNED DEFAULT NULL,
  `mobile_app_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employee_assignments`
--

CREATE TABLE `employee_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gare_id` bigint(20) UNSIGNED DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `assigned_at` date NOT NULL,
  `ended_at` date DEFAULT NULL,
  `decision_reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(120) NOT NULL,
  `label` varchar(180) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `disk` varchar(50) NOT NULL DEFAULT 'private',
  `path` varchar(255) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `gares`
--

CREATE TABLE `gares` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `city` varchar(120) NOT NULL,
  `zone` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `gares`
--

INSERT INTO `gares` (`id`, `code`, `name`, `city`, `zone`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'ADJ-01', 'ADJAME 1', 'ABIDJAN', 'SUD', NULL, 1, '2026-04-21 15:56:35', '2026-04-21 16:00:48');

-- --------------------------------------------------------

--
-- Structure de la table `gare_user`
--

CREATE TABLE `gare_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_financial_tables', 1),
(3, '2026_04_03_000002_upgrade_versements_for_ocr_flow', 1),
(4, '2026_04_06_120000_create_activity_logs_table', 1),
(5, '2026_04_13_120000_add_verification_chat_and_admin_documents_modules', 1),
(6, '2026_04_14_090000_add_depense_unlock_and_histories', 2),
(7, '2026_04_20_100000_add_recette_breakdown_fields', 3),
(8, '2026_04_28_090000_prepare_phase5_foundation_tables', 4),
(9, '2026_05_10_090000_refonte_services_and_rh_foundation', 5),
(10, '2026_05_11_120000_add_conversation_type_to_conversations_table', 6),
(11, '2026_05_11_130000_add_service_module_to_conversations_table', 7);

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notification_histories`
--

CREATE TABLE `notification_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(80) NOT NULL,
  `source_key` varchar(190) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'generated',
  `control_date` date DEFAULT NULL,
  `concerned_date` date DEFAULT NULL,
  `gares` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gares`)),
  `operations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`operations`)),
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notification_histories`
--

INSERT INTO `notification_histories` (`id`, `user_id`, `type`, `source_key`, `subject`, `content`, `status`, `control_date`, `concerned_date`, `gares`, `operations`, `payload`, `read_at`, `created_at`, `updated_at`) VALUES
(141, 6, 'daily_control_alert', 'daily-control:courrier:2026-04-21:6', 'Alerte de non-saisie', '[Courrier] Une ou plusieurs gares n\'ont pas finalisé leurs saisies du 2026-04-21.', 'generated', '2026-04-22', '2026-04-21', '[\"ADJAME 1\"]', '[\"recette\",\"depense\",\"versement_bancaire\",\"courrier\"]', '{\"anomaly_count\":1,\"module\":\"courrier\"}', NULL, '2026-04-22 08:29:49', '2026-04-22 08:29:49'),
(142, 6, 'daily_control_alert', 'daily-control:gares:2026-04-21:6', 'Alerte de non-saisie', '[Gares] Une ou plusieurs gares n\'ont pas finalisé leurs saisies du 2026-04-21.', 'generated', '2026-04-22', '2026-04-21', '[\"ADJAME 1\"]', '[\"recette\",\"depense\",\"versement_bancaire\",\"gares\"]', '{\"anomaly_count\":1,\"module\":\"gares\"}', NULL, '2026-04-22 08:30:27', '2026-04-22 08:30:27');

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `piece_justificatives`
--

CREATE TABLE `piece_justificatives` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attachable_type` varchar(255) DEFAULT NULL,
  `attachable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `document_type` varchar(80) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `disk` varchar(50) NOT NULL DEFAULT 'private',
  `path` varchar(255) NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recettes`
--

CREATE TABLE `recettes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_scope` varchar(30) NOT NULL DEFAULT 'gares',
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `operation_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `ticket_inter_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `ticket_national_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bagage_inter_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bagage_national_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `force_unlocked_until` timestamp NULL DEFAULT NULL,
  `unlock_reason` text DEFAULT NULL,
  `unlocked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recette_histories`
--

CREATE TABLE `recette_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recette_id` bigint(20) UNSIGNED NOT NULL,
  `modified_by` bigint(20) UNSIGNED NOT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`before`)),
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`after`)),
  `comment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gare_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(40) NOT NULL DEFAULT 'chef_de_gare',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `gare_id`, `department_id`, `name`, `phone`, `email`, `password`, `role`, `is_active`, `must_change_password`, `remember_token`, `created_at`, `updated_at`) VALUES
(6, NULL, 8, 'Administrateur TSR', '+2250708688585', 'admin@gestiontsr.com', '$2y$12$QFcRCh9TMAnKHUQso9UQRO/cg9AIvsfxlaYw.jBk2aOu4V042LGUy', 'admin', 1, 0, NULL, '2026-04-21 09:24:02', '2026-04-21 15:47:14');

-- --------------------------------------------------------

--
-- Structure de la table `verification_checks`
--

CREATE TABLE `verification_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_scope` varchar(30) NOT NULL DEFAULT 'gares',
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `operation_date` date NOT NULL,
  `recettes_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `depenses_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `versements_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `expected_versement` decimal(14,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `modifications_enabled_until` timestamp NULL DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `verification_checks`
--

INSERT INTO `verification_checks` (`id`, `service_scope`, `gare_id`, `operation_date`, `recettes_total`, `depenses_total`, `versements_total`, `expected_versement`, `difference`, `status`, `modifications_enabled_until`, `review_note`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(29, 'gares', 6, '2026-04-21', 0.00, 0.00, 0.00, 0.00, 0.00, 'conforme', NULL, NULL, NULL, NULL, '2026-04-22 08:01:00', '2026-04-22 08:01:00'),
(30, 'courrier', 6, '2026-04-21', 0.00, 0.00, 0.00, 0.00, 0.00, 'conforme', NULL, NULL, NULL, NULL, '2026-04-22 08:29:49', '2026-04-22 08:29:49');

-- --------------------------------------------------------

--
-- Structure de la table `versement_bancaires`
--

CREATE TABLE `versement_bancaires` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_scope` varchar(30) NOT NULL DEFAULT 'gares',
  `gare_id` bigint(20) UNSIGNED NOT NULL,
  `operation_date` date NOT NULL,
  `receipt_date` date DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `force_unlocked_until` timestamp NULL DEFAULT NULL,
  `unlock_reason` text DEFAULT NULL,
  `unlocked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `versement_bancaire_histories`
--

CREATE TABLE `versement_bancaire_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `versement_bancaire_id` bigint(20) UNSIGNED NOT NULL,
  `modified_by` bigint(20) UNSIGNED NOT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`before`)),
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`after`)),
  `comment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `workflow_transfers`
--

CREATE TABLE `workflow_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `origin_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destination_department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `transferred_by` bigint(20) UNSIGNED DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `transferred_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_user_id_foreign` (`user_id`),
  ADD KEY `activity_logs_gare_id_foreign` (`gare_id`),
  ADD KEY `activity_logs_event_type_created_at_index` (`event_type`,`created_at`),
  ADD KEY `activity_logs_entity_type_entity_id_index` (`entity_type`,`entity_id`);

--
-- Index pour la table `administrative_documents`
--
ALTER TABLE `administrative_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `administrative_documents_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `administrative_documents_updated_by_foreign` (`updated_by`),
  ADD KEY `administrative_documents_expires_at_is_active_index` (`expires_at`,`is_active`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_messages_user_id_foreign` (`user_id`),
  ADD KEY `chat_messages_conversation_id_created_at_index` (`conversation_id`,`created_at`);

--
-- Index pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversations_created_by_foreign` (`created_by`),
  ADD KEY `conversations_conversation_type_index` (`conversation_type`),
  ADD KEY `conversations_service_module_index` (`service_module`);

--
-- Index pour la table `conversation_user`
--
ALTER TABLE `conversation_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversation_user_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  ADD KEY `conversation_user_user_id_foreign` (`user_id`);

--
-- Index pour la table `courriers`
--
ALTER TABLE `courriers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `courriers_reference_unique` (`reference`),
  ADD KEY `courriers_origin_department_id_foreign` (`origin_department_id`),
  ADD KEY `courriers_destination_department_id_foreign` (`destination_department_id`),
  ADD KEY `courriers_gare_id_foreign` (`gare_id`),
  ADD KEY `courriers_created_by_foreign` (`created_by`),
  ADD KEY `courriers_updated_by_foreign` (`updated_by`),
  ADD KEY `courriers_direction_status_index` (`direction`,`status`);

--
-- Index pour la table `daily_controls`
--
ALTER TABLE `daily_controls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `daily_controls_scope_gare_concerned_unique` (`service_scope`,`gare_id`,`concerned_date`),
  ADD KEY `daily_controls_generated_by_foreign` (`generated_by`),
  ADD KEY `daily_controls_service_scope_index` (`service_scope`),
  ADD KEY `daily_controls_gare_id_index` (`gare_id`);

--
-- Index pour la table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_code_unique` (`code`);

--
-- Index pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `depenses_created_by_foreign` (`created_by`),
  ADD KEY `depenses_updated_by_foreign` (`updated_by`),
  ADD KEY `depenses_gare_id_operation_date_index` (`gare_id`,`operation_date`),
  ADD KEY `depenses_unlocked_by_foreign` (`unlocked_by`),
  ADD KEY `depenses_service_scope_index` (`service_scope`);

--
-- Index pour la table `depense_histories`
--
ALTER TABLE `depense_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `depense_histories_depense_id_foreign` (`depense_id`),
  ADD KEY `depense_histories_modified_by_foreign` (`modified_by`);

--
-- Index pour la table `document_analyses`
--
ALTER TABLE `document_analyses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_analyses_piece_justificative_id_foreign` (`piece_justificative_id`);

--
-- Index pour la table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
  ADD KEY `employees_user_id_foreign` (`user_id`),
  ADD KEY `employees_gare_id_foreign` (`gare_id`),
  ADD KEY `employees_department_id_gare_id_index` (`department_id`,`gare_id`);

--
-- Index pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_assignments_employee_id_foreign` (`employee_id`),
  ADD KEY `employee_assignments_department_id_foreign` (`department_id`),
  ADD KEY `employee_assignments_gare_id_foreign` (`gare_id`),
  ADD KEY `employee_assignments_created_by_foreign` (`created_by`);

--
-- Index pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_documents_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `employee_documents_employee_id_document_type_index` (`employee_id`,`document_type`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `gares`
--
ALTER TABLE `gares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gares_code_unique` (`code`);

--
-- Index pour la table `gare_user`
--
ALTER TABLE `gare_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gare_user_gare_id_user_id_unique` (`gare_id`,`user_id`),
  ADD KEY `gare_user_user_id_foreign` (`user_id`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Index pour la table `notification_histories`
--
ALTER TABLE `notification_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_histories_user_id_foreign` (`user_id`),
  ADD KEY `notification_histories_source_key_index` (`source_key`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Index pour la table `piece_justificatives`
--
ALTER TABLE `piece_justificatives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `piece_justificatives_attachable_type_attachable_id_index` (`attachable_type`,`attachable_id`),
  ADD KEY `piece_justificatives_uploaded_by_foreign` (`uploaded_by`);

--
-- Index pour la table `recettes`
--
ALTER TABLE `recettes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recettes_created_by_foreign` (`created_by`),
  ADD KEY `recettes_updated_by_foreign` (`updated_by`),
  ADD KEY `recettes_unlocked_by_foreign` (`unlocked_by`),
  ADD KEY `recettes_gare_id_operation_date_index` (`gare_id`,`operation_date`),
  ADD KEY `recettes_service_scope_index` (`service_scope`);

--
-- Index pour la table `recette_histories`
--
ALTER TABLE `recette_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recette_histories_recette_id_foreign` (`recette_id`),
  ADD KEY `recette_histories_modified_by_foreign` (`modified_by`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_gare_id_foreign` (`gare_id`),
  ADD KEY `users_department_id_foreign` (`department_id`);

--
-- Index pour la table `verification_checks`
--
ALTER TABLE `verification_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `verification_checks_scope_gare_operation_unique` (`service_scope`,`gare_id`,`operation_date`),
  ADD KEY `verification_checks_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `verification_checks_operation_date_status_index` (`operation_date`,`status`),
  ADD KEY `verification_checks_service_scope_index` (`service_scope`),
  ADD KEY `verification_checks_gare_id_index` (`gare_id`);

--
-- Index pour la table `versement_bancaires`
--
ALTER TABLE `versement_bancaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `versement_bancaires_created_by_foreign` (`created_by`),
  ADD KEY `versement_bancaires_updated_by_foreign` (`updated_by`),
  ADD KEY `versement_bancaires_gare_id_operation_date_index` (`gare_id`,`operation_date`),
  ADD KEY `versement_bancaires_unlocked_by_foreign` (`unlocked_by`),
  ADD KEY `versement_bancaires_service_scope_index` (`service_scope`);

--
-- Index pour la table `versement_bancaire_histories`
--
ALTER TABLE `versement_bancaire_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `versement_bancaire_histories_versement_bancaire_id_foreign` (`versement_bancaire_id`),
  ADD KEY `versement_bancaire_histories_modified_by_foreign` (`modified_by`);

--
-- Index pour la table `workflow_transfers`
--
ALTER TABLE `workflow_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_transfers_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  ADD KEY `workflow_transfers_origin_department_id_foreign` (`origin_department_id`),
  ADD KEY `workflow_transfers_destination_department_id_foreign` (`destination_department_id`),
  ADD KEY `workflow_transfers_transferred_by_foreign` (`transferred_by`),
  ADD KEY `workflow_transfers_received_by_foreign` (`received_by`),
  ADD KEY `workflow_transfers_status_destination_department_id_index` (`status`,`destination_department_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT pour la table `administrative_documents`
--
ALTER TABLE `administrative_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `conversation_user`
--
ALTER TABLE `conversation_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `courriers`
--
ALTER TABLE `courriers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `daily_controls`
--
ALTER TABLE `daily_controls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `depense_histories`
--
ALTER TABLE `depense_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_analyses`
--
ALTER TABLE `document_analyses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `gares`
--
ALTER TABLE `gares`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `gare_user`
--
ALTER TABLE `gare_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `notification_histories`
--
ALTER TABLE `notification_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT pour la table `piece_justificatives`
--
ALTER TABLE `piece_justificatives`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `recettes`
--
ALTER TABLE `recettes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT pour la table `recette_histories`
--
ALTER TABLE `recette_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `verification_checks`
--
ALTER TABLE `verification_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `versement_bancaires`
--
ALTER TABLE `versement_bancaires`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT pour la table `versement_bancaire_histories`
--
ALTER TABLE `versement_bancaire_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `workflow_transfers`
--
ALTER TABLE `workflow_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `administrative_documents`
--
ALTER TABLE `administrative_documents`
  ADD CONSTRAINT `administrative_documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `administrative_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `conversation_user`
--
ALTER TABLE `conversation_user`
  ADD CONSTRAINT `conversation_user_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `courriers`
--
ALTER TABLE `courriers`
  ADD CONSTRAINT `courriers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courriers_destination_department_id_foreign` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courriers_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courriers_origin_department_id_foreign` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courriers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `daily_controls`
--
ALTER TABLE `daily_controls`
  ADD CONSTRAINT `daily_controls_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_controls_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD CONSTRAINT `depenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `depenses_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `depenses_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `depenses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `depense_histories`
--
ALTER TABLE `depense_histories`
  ADD CONSTRAINT `depense_histories_depense_id_foreign` FOREIGN KEY (`depense_id`) REFERENCES `depenses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `depense_histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `document_analyses`
--
ALTER TABLE `document_analyses`
  ADD CONSTRAINT `document_analyses_piece_justificative_id_foreign` FOREIGN KEY (`piece_justificative_id`) REFERENCES `piece_justificatives` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD CONSTRAINT `employee_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_assignments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_assignments_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `gare_user`
--
ALTER TABLE `gare_user`
  ADD CONSTRAINT `gare_user_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gare_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notification_histories`
--
ALTER TABLE `notification_histories`
  ADD CONSTRAINT `notification_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `piece_justificatives`
--
ALTER TABLE `piece_justificatives`
  ADD CONSTRAINT `piece_justificatives_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `recettes`
--
ALTER TABLE `recettes`
  ADD CONSTRAINT `recettes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `recettes_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recettes_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `recettes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `recette_histories`
--
ALTER TABLE `recette_histories`
  ADD CONSTRAINT `recette_histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `recette_histories_recette_id_foreign` FOREIGN KEY (`recette_id`) REFERENCES `recettes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `verification_checks`
--
ALTER TABLE `verification_checks`
  ADD CONSTRAINT `verification_checks_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `verification_checks_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `versement_bancaires`
--
ALTER TABLE `versement_bancaires`
  ADD CONSTRAINT `versement_bancaires_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `versement_bancaires_gare_id_foreign` FOREIGN KEY (`gare_id`) REFERENCES `gares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `versement_bancaires_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `versement_bancaires_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `versement_bancaire_histories`
--
ALTER TABLE `versement_bancaire_histories`
  ADD CONSTRAINT `versement_bancaire_histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `versement_bancaire_histories_versement_bancaire_id_foreign` FOREIGN KEY (`versement_bancaire_id`) REFERENCES `versement_bancaires` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `workflow_transfers`
--
ALTER TABLE `workflow_transfers`
  ADD CONSTRAINT `workflow_transfers_destination_department_id_foreign` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_transfers_origin_department_id_foreign` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_transfers_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_transfers_transferred_by_foreign` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
