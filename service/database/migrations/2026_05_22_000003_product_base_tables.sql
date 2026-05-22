-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 产品基础数据表（11张表）
-- 包含: 分类/品牌/产品/SKU/多单位/价格策略/仓库/库位/供应商/客户等级/客户
-- 注意: 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- 产品分类表（树形结构）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_category` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级分类ID，0表示顶级',
    `name` VARCHAR(50) NOT NULL COMMENT '分类名称',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分类编码',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品分类表';

-- ============================================================
-- 品牌表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_brand` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '品牌名称',
    `logo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '品牌Logo URL',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '品牌描述',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='品牌表';

-- ============================================================
-- 产品主表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_product` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '产品分类ID',
    `brand_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品牌ID',
    `code` VARCHAR(50) NOT NULL COMMENT '产品编码',
    `name` VARCHAR(200) NOT NULL COMMENT '产品名称',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '产品条码',
    `spec` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '产品规格',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '基本单位',
    `image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '产品图片URL',
    `description` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '产品描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_brand_id` (`brand_id`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品主表';

-- ============================================================
-- 产品SKU表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_product_sku` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_code` VARCHAR(50) NOT NULL COMMENT 'SKU编码',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'SKU条码',
    `spec_attrs` TEXT COMMENT '规格属性JSON字符串，如 {"颜色":"红色","尺寸":"XL"}',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本价',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku_code` (`sku_code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品SKU表';

-- ============================================================
-- 产品多单位换算表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_product_unit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `unit_name` VARCHAR(20) NOT NULL COMMENT '单位名称，如箱、件、个',
    `conversion_rate` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '换算比率，相对基本单位的换算系数',
    `is_base` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否基本单位: 0=否 1=是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品多单位换算表';

-- ============================================================
-- 产品价格策略表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_product_price` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID，0表示产品级价格',
    `price_type` VARCHAR(20) NOT NULL DEFAULT 'default' COMMENT '价格类型: default=默认售价 wholesale=批发价 retail=零售价 purchase=采购价 custom=客户专属价',
    `customer_level_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户等级ID，0表示不限等级',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '价格',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_price_type` (`price_type`),
    KEY `idx_customer_level_id` (`customer_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品价格策略表';

-- ============================================================
-- 仓库表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_warehouse` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '仓库名称',
    `code` VARCHAR(50) NOT NULL COMMENT '仓库编码',
    `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '仓库地址',
    `manager` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '仓库负责人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库表';

-- ============================================================
-- 库位表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_location` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `code` VARCHAR(50) NOT NULL COMMENT '库位编码',
    `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '库位名称',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warehouse_code` (`warehouse_id`, `code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库位表';

-- ============================================================
-- 供应商表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_supplier` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '供应商编码',
    `name` VARCHAR(200) NOT NULL COMMENT '供应商名称',
    `contact_person` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '地址',
    `bank_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '开户银行',
    `bank_account` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行账号（加密存储）',
    `tax_number` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '税号',
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '税率(%)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商表';

-- ============================================================
-- 客户等级表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_customer_level` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '等级名称，如VIP、普通会员',
    `discount` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '默认折扣(%)',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户等级表';

-- ============================================================
-- 客户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_customer` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '客户编码',
    `name` VARCHAR(200) NOT NULL COMMENT '客户名称',
    `level_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户等级ID',
    `contact_person` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '地址',
    `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '信用额度',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_level_id` (`level_id`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户表';
