-- DMS Document Category Table
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

CREATE TABLE IF NOT EXISTS `erik_dms_category` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT '',
    `sort` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `erik_dms_category` (`id`, `name`, `sort`, `status`) VALUES
(1, '制度规范', 1, 1),
(2, '流程文档', 2, 1),
(3, '技术文档', 3, 1),
(4, '合同协议', 4, 1),
(5, '培训材料', 5, 1),
(6, '其他', 99, 1);
