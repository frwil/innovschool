-- Base de données alignée avec le schéma Doctrine des entités
-- Généré le 2026-06-01

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `admission_reductions`;
CREATE TABLE `admission_reductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reduction_amount` bigint NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `pending_approval` tinyint(1) NOT NULL DEFAULT '1',
  `approval_owners` json DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `school_id` int NOT NULL,
  `school_class_period_id` int NOT NULL,
  `school_period_id` int NOT NULL,
  `student_id` int NOT NULL,
  `reduction_modal_id` int NOT NULL,
  `requested_by_id` int DEFAULT NULL,
  `approved_by_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BA9FE4EBC32A47EE` (`school_id`),
  KEY `IDX_BA9FE4EB14463F54` (`school_class_period_id`),
  KEY `IDX_BA9FE4EB9DC4B963` (`school_period_id`),
  KEY `IDX_BA9FE4EBCB944F1A` (`student_id`),
  KEY `IDX_BA9FE4EB8373D28E` (`reduction_modal_id`),
  KEY `IDX_BA9FE4EB_requested_by` (`requested_by_id`),
  KEY `IDX_BA9FE4EB_approved_by` (`approved_by_id`),
  CONSTRAINT `FK_BA9FE4EB14463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_BA9FE4EB8373D28E` FOREIGN KEY (`reduction_modal_id`) REFERENCES `school_class_payment_modals` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_BA9FE4EB9DC4B963` FOREIGN KEY (`school_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_BA9FE4EBC32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_BA9FE4EBCB944F1A` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_BA9FE4EB_requested_by` FOREIGN KEY (`requested_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_BA9FE4EB_approved_by` FOREIGN KEY (`approved_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `app_license`;
CREATE TABLE `app_license` (
  `id` int NOT NULL AUTO_INCREMENT,
  `licence_start_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `licence_duration` int NOT NULL DEFAULT '30',
  `licence_amount` int NOT NULL DEFAULT '0',
  `license_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `license_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `school_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_app_license_school` (`school_id`),
  CONSTRAINT `FK_app_license_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `class_occurence`;
CREATE TABLE `class_occurence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `classe_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_class_occurence_name` (`name`),
  KEY `IDX_class_occurence_classe` (`classe_id`),
  CONSTRAINT `FK_class_occurence_classe` FOREIGN KEY (`classe_id`) REFERENCES `classe` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `class_subject_module`;
CREATE TABLE `class_subject_module` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `module_notation` float NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subject_id` int NOT NULL,
  `class_id` int NOT NULL,
  `period_id` int NOT NULL,
  `school_id` int NOT NULL,
  `module_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subject_class_period_school_module` (`subject_id`, `class_id`, `period_id`, `school_id`, `module_id`),
  KEY `IDX_CSM_class` (`class_id`),
  KEY `IDX_CSM_period` (`period_id`),
  KEY `IDX_CSM_school` (`school_id`),
  KEY `IDX_CSM_module` (`module_id`),
  CONSTRAINT `FK_CSM_subject` FOREIGN KEY (`subject_id`) REFERENCES `study_subject` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_CSM_class` FOREIGN KEY (`class_id`) REFERENCES `school_class_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_CSM_period` FOREIGN KEY (`period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_CSM_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_CSM_module` FOREIGN KEY (`module_id`) REFERENCES `subjects_modules` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `classe`;
CREATE TABLE `classe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `study_level_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_classe_name` (`name`),
  KEY `IDX_classe_study_level` (`study_level_id`),
  CONSTRAINT `FK_classe_study_level` FOREIGN KEY (`study_level_id`) REFERENCES `study_level` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `core_update`;
CREATE TABLE `core_update` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `doctrine_migration_versions`;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `evaluation`;
CREATE TABLE `evaluation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_note` float NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_id` int NOT NULL,
  `class_subject_module_id` bigint NOT NULL,
  `time_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_evaluation` (`student_id`, `class_subject_module_id`, `time_id`),
  KEY `IDX_evaluation_csm` (`class_subject_module_id`),
  KEY `IDX_evaluation_time` (`time_id`),
  CONSTRAINT `FK_evaluation_student` FOREIGN KEY (`student_id`) REFERENCES `student_class` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_evaluation_csm` FOREIGN KEY (`class_subject_module_id`) REFERENCES `class_subject_module` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_evaluation_time` FOREIGN KEY (`time_id`) REFERENCES `school_evaluation_time` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `evaluation_appreciation_bareme`;
CREATE TABLE `evaluation_appreciation_bareme` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_appreciation_max_note` bigint NOT NULL,
  `evaluation_appreciation_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `evaluation_appreciation_full_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `evaluation_appreciation_template_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_value_maxnote` (`evaluation_appreciation_value`, `evaluation_appreciation_max_note`),
  UNIQUE KEY `unique_value_template` (`evaluation_appreciation_value`, `evaluation_appreciation_template_id`),
  KEY `IDX_bareme_template` (`evaluation_appreciation_template_id`),
  CONSTRAINT `FK_bareme_template` FOREIGN KEY (`evaluation_appreciation_template_id`) REFERENCES `evaluation_appreciation_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `evaluation_appreciation_template`;
CREATE TABLE `evaluation_appreciation_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_eat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `fees`;
CREATE TABLE `fees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `amount` double NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `messenger_messages`;
CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `migration_log`;
CREATE TABLE `migration_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `passing_grade` double NOT NULL DEFAULT '10',
  `options` json NOT NULL,
  `created_ids` json NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'executed',
  `executed_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `executed_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `config_summary` json NOT NULL,
  `student_stats` json NOT NULL,
  `school_id` int NOT NULL,
  `source_period_id` int NOT NULL,
  `target_period_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_399FA51EC32A47EE` (`school_id`),
  KEY `IDX_399FA51E8415E910` (`source_period_id`),
  KEY `IDX_399FA51ED83F08F2` (`target_period_id`),
  CONSTRAINT `FK_399FA51E8415E910` FOREIGN KEY (`source_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_399FA51EC32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_399FA51ED83F08F2` FOREIGN KEY (`target_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `modalities_subscriptions`;
CREATE TABLE `modalities_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subscription_date` datetime NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `is_full` tinyint(1) NOT NULL DEFAULT '0',
  `is_full_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_id` int NOT NULL,
  `school_class_period_id` int NOT NULL,
  `school_period_id` int NOT NULL,
  `payment_modal_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_6582079CCB944F1A` (`student_id`),
  KEY `IDX_6582079C14463F54` (`school_class_period_id`),
  KEY `IDX_6582079C9DC4B963` (`school_period_id`),
  KEY `IDX_6582079CCC33534F` (`payment_modal_id`),
  CONSTRAINT `FK_6582079C14463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_6582079C9DC4B963` FOREIGN KEY (`school_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_6582079CCB944F1A` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_6582079CCC33534F` FOREIGN KEY (`payment_modal_id`) REFERENCES `school_class_payment_modals` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `module`;
CREATE TABLE `module` (
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `operation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `timestamp` datetime NOT NULL,
  `performed_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `amount` double NOT NULL,
  `created_at` datetime NOT NULL,
  `for_month` date NOT NULL,
  `author_id` int DEFAULT NULL,
  `fees_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_6D28840DF675F31B` (`author_id`),
  KEY `IDX_6D28840D9C6BD325` (`fees_id`),
  CONSTRAINT `FK_6D28840D9C6BD325` FOREIGN KEY (`fees_id`) REFERENCES `fees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_6D28840DF675F31B` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `registration_card_base_config`;
CREATE TABLE `registration_card_base_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `card_header` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_motto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `head_officer_sign` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_bg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sign_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_header_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `national_motto_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `double_header_layout` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `report_card_template`;
CREATE TABLE `report_card_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `header_left` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `header_right` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `national_motto_left` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `national_motto_right` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `header_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_header_left` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_header_right` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `school_values_left` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `school_values_right` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evaluation_appreciation_template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_report_card_template_name` (`name`),
  KEY `IDX_rct_eval_template` (`evaluation_appreciation_template_id`),
  CONSTRAINT `FK_rct_eval_template` FOREIGN KEY (`evaluation_appreciation_template_id`) REFERENCES `evaluation_appreciation_template` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school`;
CREATE TABLE `school` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_acces_at` datetime NOT NULL,
  `customer` tinyint(1) NOT NULL DEFAULT '0',
  `acronym` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_only` tinyint(1) NOT NULL DEFAULT '0',
  `school_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `registration_base_config_id` int DEFAULT NULL,
  `evaluation_appreciation_template_id` int DEFAULT NULL,
  `report_card_template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_F99EDABBB069795C` (`registration_base_config_id`),
  UNIQUE KEY `UNIQ_F99EDABBB_school_number` (`school_number`),
  KEY `IDX_school_eval_template` (`evaluation_appreciation_template_id`),
  KEY `IDX_school_report_card_template` (`report_card_template_id`),
  CONSTRAINT `FK_F99EDABBB069795C` FOREIGN KEY (`registration_base_config_id`) REFERENCES `registration_card_base_config` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_school_eval_template` FOREIGN KEY (`evaluation_appreciation_template_id`) REFERENCES `evaluation_appreciation_template` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_school_report_card_template` FOREIGN KEY (`report_card_template_id`) REFERENCES `report_card_template` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_admission_payments`;
CREATE TABLE `school_class_admission_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_date` datetime NOT NULL,
  `payment_amount` bigint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `modal_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_class_period_id` int NOT NULL,
  `student_id` int NOT NULL,
  `school_id` int NOT NULL,
  `school_period_id` int NOT NULL,
  `payment_modal_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_33FDA22314463F54` (`school_class_period_id`),
  KEY `IDX_33FDA223CB944F1A` (`student_id`),
  KEY `IDX_33FDA223C32A47EE` (`school_id`),
  KEY `IDX_33FDA2239DC4B963` (`school_period_id`),
  KEY `IDX_33FDA223_payment_modal` (`payment_modal_id`),
  CONSTRAINT `FK_33FDA22314463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_33FDA2239DC4B963` FOREIGN KEY (`school_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_33FDA223C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_33FDA223CB944F1A` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_33FDA223_payment_modal` FOREIGN KEY (`payment_modal_id`) REFERENCES `school_class_payment_modals` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_attendance`;
CREATE TABLE `school_class_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendanc_json` json NOT NULL,
  `evaluation_id` int DEFAULT NULL,
  `school_class_period_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_410B528456C5646` (`evaluation_id`),
  KEY `IDX_410B528214463F54` (`school_class_period_id`),
  CONSTRAINT `FK_410B52814463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_410B528456C5646` FOREIGN KEY (`evaluation_id`) REFERENCES `school_evaluation` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `school_class_numbering_type`;
CREATE TABLE `school_class_numbering_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numbering_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int NOT NULL,
  `classe_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_numbering_type` (`classe_id`, `school_id`, `numbering_type`),
  KEY `IDX_scnt_school` (`school_id`),
  CONSTRAINT `FK_scnt_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_scnt_classe` FOREIGN KEY (`classe_id`) REFERENCES `classe` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_payment_modals`;
CREATE TABLE `school_class_payment_modals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `due_date` date NOT NULL,
  `modal_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modal_priority` int NOT NULL DEFAULT '100',
  `school_class_period_id` int DEFAULT NULL,
  `school_period_id` int NOT NULL,
  `school_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_school_period` (`school_class_period_id`, `school_id`, `school_period_id`, `modal_type`, `label`),
  KEY `IDX_662FC67914463F54` (`school_class_period_id`),
  KEY `IDX_662FC6799DC4B963` (`school_period_id`),
  KEY `IDX_662FC679C32A47EE` (`school_id`),
  CONSTRAINT `FK_662FC67914463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_662FC6799DC4B963` FOREIGN KEY (`school_period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_662FC679C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_period`;
CREATE TABLE `school_class_period` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_id` int DEFAULT NULL,
  `period_id` int DEFAULT NULL,
  `class_occurence_id` int NOT NULL,
  `evaluation_appreciation_template_id` int DEFAULT NULL,
  `class_master_id` int DEFAULT NULL,
  `report_card_template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_school_period_class` (`school_id`, `period_id`, `class_occurence_id`),
  KEY `IDX_33B1AF85C32A47EE` (`school_id`),
  KEY `IDX_33B1AF85EC8B7ADE` (`period_id`),
  KEY `IDX_scp_class_occurence` (`class_occurence_id`),
  KEY `IDX_scp_eval_template` (`evaluation_appreciation_template_id`),
  KEY `IDX_scp_class_master` (`class_master_id`),
  KEY `IDX_scp_report_card_template` (`report_card_template_id`),
  CONSTRAINT `FK_33B1AF85C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_33B1AF85EC8B7ADE` FOREIGN KEY (`period_id`) REFERENCES `school_period` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_scp_class_occurence` FOREIGN KEY (`class_occurence_id`) REFERENCES `class_occurence` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_scp_eval_template` FOREIGN KEY (`evaluation_appreciation_template_id`) REFERENCES `evaluation_appreciation_template` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_scp_class_master` FOREIGN KEY (`class_master_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_scp_report_card_template` FOREIGN KEY (`report_card_template_id`) REFERENCES `report_card_template` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_subject`;
CREATE TABLE `school_class_subject` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coefficient` int NOT NULL DEFAULT '1',
  `awaited_skills` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `school_class_period_id` int NOT NULL,
  `study_subject_id` int NOT NULL,
  `group_id` int DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `section_category_subject_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scp_subject` (`study_subject_id`, `school_class_period_id`),
  KEY `IDX_scs_scp` (`school_class_period_id`),
  KEY `IDX_scs_group` (`group_id`),
  KEY `IDX_scs_teacher` (`teacher_id`),
  KEY `IDX_scs_section_category_subject` (`section_category_subject_id`),
  CONSTRAINT `FK_scs_scp` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_scs_subject` FOREIGN KEY (`study_subject_id`) REFERENCES `study_subject` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_scs_group` FOREIGN KEY (`group_id`) REFERENCES `subject_group` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_scs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_scs_section_category_subject` FOREIGN KEY (`section_category_subject_id`) REFERENCES `section_category_subject` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_class_subject_evaluation_time_not_applicable`;
CREATE TABLE `school_class_subject_evaluation_time_not_applicable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `not_applicable` tinyint(1) NOT NULL DEFAULT '1',
  `school_class_subject_id` int NOT NULL,
  `school_evaluation_time_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_C8CD1AA56BCD643E` (`school_class_subject_id`),
  KEY `IDX_C8CD1AA542C6795B` (`school_evaluation_time_id`),
  CONSTRAINT `FK_C8CD1AA542C6795B` FOREIGN KEY (`school_evaluation_time_id`) REFERENCES `school_evaluation_time` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_C8CD1AA56BCD643E` FOREIGN KEY (`school_class_subject_id`) REFERENCES `school_class_subject` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `school_evaluation`;
CREATE TABLE `school_evaluation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creted_at` datetime NOT NULL,
  `frame_id` int DEFAULT NULL,
  `time_id` int DEFAULT NULL,
  `period_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A68225873FA3C347` (`frame_id`),
  KEY `IDX_A68225875EEADD3B` (`time_id`),
  KEY `IDX_A6822587EC8B7ADE` (`period_id`),
  CONSTRAINT `FK_A68225873FA3C347` FOREIGN KEY (`frame_id`) REFERENCES `school_evaluation_frame` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_A68225875EEADD3B` FOREIGN KEY (`time_id`) REFERENCES `school_evaluation_time` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_A6822587EC8B7ADE` FOREIGN KEY (`period_id`) REFERENCES `school_period` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_evaluation_frame`;
CREATE TABLE `school_evaluation_frame` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_evaluation_time`;
CREATE TABLE `school_evaluation_time` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evaluation_frame_id` int DEFAULT NULL,
  `type_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_set_frame` (`evaluation_frame_id`),
  KEY `IDX_set_type` (`type_id`),
  CONSTRAINT `FK_set_frame` FOREIGN KEY (`evaluation_frame_id`) REFERENCES `school_evaluation_frame` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_set_type` FOREIGN KEY (`type_id`) REFERENCES `school_evaluation_time_type` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_evaluation_time_type`;
CREATE TABLE `school_evaluation_time_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_sett_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_license_payment`;
CREATE TABLE `school_license_payment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_payment` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_amount` int NOT NULL DEFAULT '0',
  `payment_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_slp_license` (`license_id`),
  CONSTRAINT `FK_slp_license` FOREIGN KEY (`license_id`) REFERENCES `app_license` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_period`;
CREATE TABLE `school_period` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_section`;
CREATE TABLE `school_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_id` int DEFAULT NULL,
  `study_level_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BF5C4877C32A47EE` (`school_id`),
  KEY `IDX_BF5C4877D823E37A` (`study_level_id`),
  CONSTRAINT `FK_BF5C4877C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_BF5C4877D823E37A` FOREIGN KEY (`study_level_id`) REFERENCES `study_level` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `school_study_type`;
CREATE TABLE `school_study_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_id` int NOT NULL,
  `studies_type_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_school_studies_type` (`school_id`, `studies_type_id`),
  KEY `IDX_sst_studies_type` (`studies_type_id`),
  CONSTRAINT `FK_sst_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_sst_studies_type` FOREIGN KEY (`studies_type_id`) REFERENCES `studies_type` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `section_category_subject`;
CREATE TABLE `section_category_subject` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_category_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DC5615E04B7E29D` (`section_category_id`),
  CONSTRAINT `FK_DC5615E04B7E29D` FOREIGN KEY (`section_category_id`) REFERENCES `classe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `student_attendance`;
CREATE TABLE `student_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `abscence` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `school_class_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_803CE070CB944F1A` (`student_id`),
  KEY `IDX_803CE07014463F54` (`school_class_id`),
  CONSTRAINT `FK_803CE07014463F54` FOREIGN KEY (`school_class_id`) REFERENCES `school_class_period` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_803CE070CB944F1A` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `student_class`;
CREATE TABLE `student_class` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `school_class_period_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_class` (`school_class_period_id`, `student_id`),
  KEY `IDX_657C6002CB944F1A` (`student_id`),
  CONSTRAINT `FK_657C600214463F54` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_657C6002CB944F1A` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `student_class_attendance`;
CREATE TABLE `student_class_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `heures_absence` int NOT NULL DEFAULT '0',
  `absences_justifiee` int NOT NULL DEFAULT '0',
  `retard` int NOT NULL DEFAULT '0',
  `retard_injustifie` int NOT NULL DEFAULT '0',
  `retenue` int NOT NULL DEFAULT '0',
  `avertissement_discipline` int NOT NULL DEFAULT '0',
  `blame` int NOT NULL DEFAULT '0',
  `jour_exclusion` int NOT NULL DEFAULT '0',
  `exclusion_definitive` tinyint(1) NOT NULL DEFAULT '0',
  `student_class_id` int NOT NULL,
  `time_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_time_student_class` (`time_id`, `student_class_id`),
  KEY `IDX_sca_student_class` (`student_class_id`),
  CONSTRAINT `FK_sca_time` FOREIGN KEY (`time_id`) REFERENCES `school_evaluation_time` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_sca_student_class` FOREIGN KEY (`student_class_id`) REFERENCES `student_class` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `student_class_timetable_presence`;
CREATE TABLE `student_class_timetable_presence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` tinyint NOT NULL DEFAULT '0',
  `date_presence` date NOT NULL,
  `locked` tinyint NOT NULL DEFAULT '0',
  `student_class_id` int NOT NULL,
  `time_table_slot_id` int NOT NULL,
  `school_evaluation_time_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_presence_per_studentclass_slot_datepresence` (`student_class_id`, `time_table_slot_id`, `date_presence`),
  KEY `IDX_sctp_slot` (`time_table_slot_id`),
  KEY `IDX_sctp_eval_time` (`school_evaluation_time_id`),
  CONSTRAINT `FK_sctp_student_class` FOREIGN KEY (`student_class_id`) REFERENCES `student_class` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_sctp_slot` FOREIGN KEY (`time_table_slot_id`) REFERENCES `timetable_slot` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_sctp_eval_time` FOREIGN KEY (`school_evaluation_time_id`) REFERENCES `school_evaluation_time` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `studies_type`;
CREATE TABLE `studies_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_studies_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `study_level`;
CREATE TABLE `study_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `study_subject`;
CREATE TABLE `study_subject` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_study_subject_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `subject_group`;
CREATE TABLE `subject_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pos_order` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int NOT NULL,
  `period_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_subject_group_description` (`description`),
  KEY `IDX_sg_period` (`period_id`),
  KEY `IDX_sg_school` (`school_id`),
  CONSTRAINT `FK_sg_period` FOREIGN KEY (`period_id`) REFERENCES `school_period` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_sg_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `subjects_modules`;
CREATE TABLE `subjects_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_sm_module_name` (`module_name`),
  UNIQUE KEY `UNIQ_sm_module_slug` (`module_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `timetable`;
CREATE TABLE `timetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `school_id` int NOT NULL,
  `period_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_timetable_period` (`period_id`),
  KEY `IDX_timetable_school` (`school_id`),
  KEY `IDX_timetable_teacher` (`teacher_id`),
  CONSTRAINT `FK_timetable_period` FOREIGN KEY (`period_id`) REFERENCES `school_period` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_timetable_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_timetable_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `timetable_day`;
CREATE TABLE `timetable_day` (
  `id` int NOT NULL AUTO_INCREMENT,
  `day_of_week` int NOT NULL,
  `timetable_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_timetable_day` (`timetable_id`, `day_of_week`),
  CONSTRAINT `FK_6600A558CC306847` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `timetable_slot`;
CREATE TABLE `timetable_slot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `timetable_day_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `school_class_period_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_timetable_slot` (`school_class_period_id`, `subject_id`, `timetable_day_id`),
  UNIQUE KEY `unique_timetable_slot_end_time` (`end_time`, `start_time`, `timetable_day_id`, `school_class_period_id`),
  KEY `IDX_ts_subject` (`subject_id`),
  CONSTRAINT `FK_ts_scp` FOREIGN KEY (`school_class_period_id`) REFERENCES `school_class_period` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_ts_subject` FOREIGN KEY (`subject_id`) REFERENCES `study_subject` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_ts_timetable_day` FOREIGN KEY (`timetable_day_id`) REFERENCES `timetable_day` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enabled` tinyint(1) NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_at` datetime DEFAULT NULL,
  `capacity` json DEFAULT NULL,
  `infos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gender` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `repeated` tinyint(1) NOT NULL DEFAULT '0',
  `registration_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `national_registration_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `religion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `place_of_birth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_password` tinyint(1) NOT NULL DEFAULT '1',
  `school_id` int DEFAULT NULL,
  `tutor_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_USERNAME` (`username`),
  UNIQUE KEY `UNIQ_8D93D64938CEDFBE` (`registration_number`),
  UNIQUE KEY `UNIQ_8D93D649_national_registration_number` (`national_registration_number`),
  KEY `IDX_8D93D649C32A47EE` (`school_id`),
  KEY `IDX_8D93D649208F64F1` (`tutor_id`),
  CONSTRAINT `FK_8D93D649208F64F1` FOREIGN KEY (`tutor_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_8D93D649C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_base_configuration`;
CREATE TABLE `user_base_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_list` json NOT NULL,
  `section_list` json NOT NULL,
  `user_id` int NOT NULL,
  `school_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_school` (`user_id`, `school_id`),
  KEY `IDX_811D7695C32A47EE` (`school_id`),
  CONSTRAINT `FK_811D7695A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_811D7695C32A47EE` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
