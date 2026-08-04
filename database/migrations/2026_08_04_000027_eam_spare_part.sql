-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
CREATE TABLE IF NOT EXISTS `erik_eam_spare_part` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(100) NOT NULL DEFAULT '',
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `equipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `spec` VARCHAR(200) NOT NULL DEFAULT '',
    `unit` VARCHAR(20) NOT NULL DEFAULT '',
    `stock_qty` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `min_stock` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `location` VARCHAR(200) NOT NULL DEFAULT '',
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
