-- P3 Experience Enhancement Tables
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

CREATE TABLE IF NOT EXISTS `erik_bi_dashboard` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `layout` JSON DEFAULT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_bi_widget` (
    `id` BIGINT UNSIGNED NOT NULL,
    `dashboard_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `type` VARCHAR(50) NOT NULL DEFAULT 'table',
    `dataset_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `config` JSON DEFAULT NULL,
    `position_x` INT NOT NULL DEFAULT 0,
    `position_y` INT NOT NULL DEFAULT 0,
    `width` INT NOT NULL DEFAULT 4,
    `height` INT NOT NULL DEFAULT 3,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_dashboard_id` (`dashboard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_eam_equipment` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(100) NOT NULL DEFAULT '',
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `model` VARCHAR(100) NOT NULL DEFAULT '',
    `serial_number` VARCHAR(100) NOT NULL DEFAULT '',
    `category` VARCHAR(50) NOT NULL DEFAULT '',
    `location` VARCHAR(200) NOT NULL DEFAULT '',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `purchase_date` DATE NULL DEFAULT NULL,
    `warranty_expiry` DATE NULL DEFAULT NULL,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_eam_maintenance_plan` (
    `id` BIGINT UNSIGNED NOT NULL,
    `equipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `frequency` VARCHAR(50) NOT NULL DEFAULT '',
    `last_date` DATE NULL DEFAULT NULL,
    `next_date` DATE NULL DEFAULT NULL,
    `assignee` VARCHAR(100) NOT NULL DEFAULT '',
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_eam_repair_order` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(100) NOT NULL DEFAULT '',
    `equipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `fault_description` TEXT DEFAULT NULL,
    `repair_type` VARCHAR(50) NOT NULL DEFAULT 'corrective',
    `assignee` VARCHAR(100) NOT NULL DEFAULT '',
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'open',
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_dms_document` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(100) NOT NULL DEFAULT '',
    `title` VARCHAR(500) NOT NULL DEFAULT '',
    `category` VARCHAR(100) NOT NULL DEFAULT '',
    `version` INT NOT NULL DEFAULT 1,
    `author` VARCHAR(100) NOT NULL DEFAULT '',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `content` LONGTEXT DEFAULT NULL,
    `tags` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erik_dms_document_version` (
    `id` BIGINT UNSIGNED NOT NULL,
    `document_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `version` INT NOT NULL DEFAULT 1,
    `content` LONGTEXT DEFAULT NULL,
    `changed_by` VARCHAR(100) NOT NULL DEFAULT '',
    `change_note` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
