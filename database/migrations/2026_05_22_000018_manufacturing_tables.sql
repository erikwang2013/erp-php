-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 生产制造表（8张表）
-- 包含: BOM/BOM明细/生产工单/生产工单明细/工艺路线/工作站/MRP计划/MRP计划明细
-- 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- BOM主表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_bom` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '成品产品ID',
    `code` VARCHAR(50) NOT NULL COMMENT 'BOM编码',
    `name` VARCHAR(200) NOT NULL COMMENT 'BOM名称',
    `version` VARCHAR(20) NOT NULL DEFAULT '1.0' COMMENT '版本号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已生效 2=已失效',
    `effective_date` DATE DEFAULT NULL COMMENT '生效日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_code` (`code`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='BOM主表';

-- ============================================================
-- BOM明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_bom_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `bom_id` BIGINT UNSIGNED NOT NULL COMMENT 'BOM ID',
    `component_product_id` BIGINT UNSIGNED NOT NULL COMMENT '组成件产品ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '用量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `scrap_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '损耗率(%)',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_bom_id` (`bom_id`),
    KEY `idx_component_product_id` (`component_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='BOM明细表';

-- ============================================================
-- 生产工单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_production_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '工单编码',
    `bom_id` BIGINT UNSIGNED NOT NULL COMMENT 'BOM ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仓库ID',
    `planned_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计划生产数量',
    `completed_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已完成数量',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待生产 1=生产中 2=已完成 3=已取消',
    `planned_start` DATE DEFAULT NULL COMMENT '计划开始日期',
    `planned_end` DATE DEFAULT NULL COMMENT '计划结束日期',
    `actual_start` DATETIME DEFAULT NULL COMMENT '实际开始时间',
    `actual_end` DATETIME DEFAULT NULL COMMENT '实际完成时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_bom_id` (`bom_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_planned_start` (`planned_start`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生产工单表';

-- ============================================================
-- 生产工单明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_production_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `planned_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计划数量',
    `completed_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已完成数量',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待生产 1=生产中 2=已完成',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生产工单明细表';

-- ============================================================
-- 工艺路线表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_routing` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `name` VARCHAR(100) NOT NULL COMMENT '工序名称',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '工序号',
    `workstation_id` BIGINT UNSIGNED NOT NULL COMMENT '工作站ID',
    `standard_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '标准工时（小时）',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '工艺描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_workstation_id` (`workstation_id`),
    KEY `idx_seq` (`product_id`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工艺路线表';

-- ============================================================
-- 工作站表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_workstation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '工作站编码',
    `name` VARCHAR(100) NOT NULL COMMENT '工作站名称',
    `capacity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每小时产能',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作站表';

-- ============================================================
-- MRP计划主表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_mrp_plan` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '计划编码',
    `period_year` INT UNSIGNED NOT NULL COMMENT '计划年份',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '计划月份',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已生成 2=已确认',
    `generated_at` DATETIME DEFAULT NULL COMMENT '生成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_period` (`period_year`, `period_month`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRP计划主表';

-- ============================================================
-- MRP计划明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_mfg_mrp_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `plan_id` BIGINT UNSIGNED NOT NULL COMMENT 'MRP计划ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `gross_requirement` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '毛需求量',
    `scheduled_receipt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计划接收量',
    `on_hand` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '现有库存量',
    `net_requirement` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '净需求量',
    `planned_order_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计划订单量',
    `planned_start` DATE DEFAULT NULL COMMENT '计划开始日期',
    `planned_end` DATE DEFAULT NULL COMMENT '计划结束日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_plan_id` (`plan_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRP计划明细表';
