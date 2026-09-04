-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 开放ERP系统 — 完整安装SQL
--
-- 本文件由以下29个迁移文件合并而成：
--   000000_init_tables.sql            — 管理后台核心表
--   000001_seed_permissions.sql       — RBAC权限种子数据
--   000002_add_source_to_operation_log.sql — 操作日志来源端字段（已合并入建表语句）
--   000003_product_base_tables.sql    — 产品基础数据表
--   000004_purchase_tables.sql        — 采购模块表
--   000005_sales_tables.sql           — 销售模块表
--   000006_inventory_finance_crm_tables.sql — 库存/财务/CRM基础表
--   000009_seed_erp_permissions.sql   — ERP模块权限种子数据
--   000010_finance_ledger_tables.sql  — 财务总账/明细账/报表
--   000011_crm_expansion_tables.sql   — CRM扩展(公海池/合同)
--   000012_finance_expansion_tables.sql — 财务扩展(固定资产/税务/多币种/预算)
--   000013_crm_extra_tables.sql       — CRM扩展(营销/工单/分析)
--   000014_approval_workflow_tables.sql — 审批工作流引擎
--   000015_notification_tables.sql    — 消息通知系统
--   000016_project_tables.sql         — 项目管理
--   000017_hr_tables.sql              — 人力资源
--   000018_manufacturing_tables.sql   — 生产制造
--   000019_report_builder_tables.sql  — 自定义报表构建器
--   000020_oms_tables.sql             — 订单管理(OMS)
--   000021_wms_tables.sql             — 仓储管理(WMS)
--   000022_tms_tables.sql             — 运输管理(TMS)
--   000023_seed_oms_wms_tms_permissions.sql — OMS/WMS/TMS权限种子
--   000024_qms_tables.sql             — 质量管理(QMS)
--   000025_p3_tables.sql              — P3体验增强表
--   000026_seed_p3_permissions.sql    — P3权限种子
--   000027_eam_spare_part.sql         — 设备备件(EAM)
--   000028_seed_qms_permissions.sql   — QMS权限种子
--   000029_dms_category.sql           — 文档管理分类表
--   000030_seed_service_permissions.sql — 财务报表/TMS运费/质检服务权限种子
--
-- 执行方式:
--   mysql -u root -p 数据库名 < install.sql
--
-- 重要约定:
--   - 数据库字符集: utf8mb4 / utf8mb4_unicode_ci
--   - 表前缀: erp_
--   - 主键 id: BIGINT UNSIGNED NOT NULL，由 snowflake-php 在应用层生成
--   - 存储引擎: InnoDB
-- ============================================================

-- ################################################################
-- PART 1: 管理后台核心数据表
-- ################################################################

-- ============================================================
-- 管理用户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_admin_user` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt哈希）',
    `real_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
    `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像URL',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `id_card` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证号（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理用户表';

-- ============================================================
-- 角色表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_admin_role` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
    `slug` VARCHAR(50) NOT NULL COMMENT '角色标识，用于权限判断',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '角色描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

-- ============================================================
-- 权限表（菜单/按钮/接口）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_admin_permission` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级权限ID，0表示顶级',
    `name` VARCHAR(50) NOT NULL COMMENT '权限名称',
    `slug` VARCHAR(100) NOT NULL COMMENT '权限标识，格式: 模块.操作（如 user.create）',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=菜单 2=按钮 3=API接口',
    `icon` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '菜单图标（仅type=1时使用）',
    `path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '前端路由路径（仅type=1时使用）',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限表';

-- ============================================================
-- 用户角色关联表（多对多中间表）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_admin_user_role` (
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';

-- ============================================================
-- 角色权限关联表（多对多中间表）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_admin_role_permission` (
    `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限ID',
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- ============================================================
-- 系统配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_system_config` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `group` VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT '配置分组标识',
    `key` VARCHAR(100) NOT NULL COMMENT '配置键名',
    `value` TEXT COMMENT '配置值',
    `type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型: string|int|bool|json|array',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置项说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================================
-- 操作日志表（已合并 source 字段，来自迁移 000002）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_operation_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
    `action` VARCHAR(100) NOT NULL COMMENT '操作动作，如 admin.user.store',
    `method` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '请求方法: GET|POST|PUT|DELETE',
    `path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '请求路径',
    `ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作IP',
    `source` VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '操作来源端: ipados|macos|windows|linux|ios|android|harmonyos|web',
    `input` TEXT COMMENT '请求参数（敏感字段已脱敏）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ################################################################
-- PART 2: 产品基础数据表
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_category` (
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

CREATE TABLE IF NOT EXISTS `erp_brand` (
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

CREATE TABLE IF NOT EXISTS `erp_product` (
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

CREATE TABLE IF NOT EXISTS `erp_product_sku` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_code` VARCHAR(50) NOT NULL COMMENT 'SKU编码',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'SKU条码',
    `spec_attrs` TEXT COMMENT '规格属性JSON字符串',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本价',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku_code` (`sku_code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品SKU表';

CREATE TABLE IF NOT EXISTS `erp_product_unit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `unit_name` VARCHAR(20) NOT NULL COMMENT '单位名称',
    `conversion_rate` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT '换算比率',
    `is_base` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否基本单位: 0=否 1=是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品多单位换算表';

CREATE TABLE IF NOT EXISTS `erp_product_price` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID，0表示产品级价格',
    `price_type` VARCHAR(20) NOT NULL DEFAULT 'default' COMMENT '价格类型: default/wholesale/retail/purchase/custom',
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

CREATE TABLE IF NOT EXISTS `erp_warehouse` (
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

CREATE TABLE IF NOT EXISTS `erp_location` (
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

CREATE TABLE IF NOT EXISTS `erp_supplier` (
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

CREATE TABLE IF NOT EXISTS `erp_customer_level` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '等级名称',
    `discount` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '默认折扣(%)',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户等级表';

CREATE TABLE IF NOT EXISTS `erp_customer` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '客户编码',
    `name` VARCHAR(200) NOT NULL COMMENT '客户名称',
    `level_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户等级ID',
    `contact_person` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '地址',
    `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '信用额度',
    `credit_days` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '信用账期天数, 0=无账期(due_date为空)',
    `credit_frozen` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '信用冻结: 0=正常 1=冻结(阻断一切新销售单据)',
    `credit_over_ratio` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT '允许超限比例%, 0=严格不超限',
    `credit_overdue_limit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '允许超期未收余额上限, 0=不允许任何超期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `owner_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '归属用户ID（0=未认领，公海客户）',
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

-- ################################################################
-- PART 3: 采购模块表
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_purchase_apply` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '申请单号',
    `apply_user_id` BIGINT UNSIGNED NOT NULL COMMENT '申请人ID',
    `department` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '申请部门',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审批 1=已批准 2=已驳回 3=已转订单',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `approved_at` DATETIME DEFAULT NULL COMMENT '审批时间',
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_apply_user_id` (`apply_user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请单';

CREATE TABLE IF NOT EXISTS `erp_purchase_apply_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `apply_id` BIGINT UNSIGNED NOT NULL COMMENT '采购申请ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '申请数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `estimated_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预估单价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_apply_id` (`apply_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请明细';

CREATE TABLE IF NOT EXISTS `erp_purchase_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '订单单号',
    `apply_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采购申请ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核 2=部分收货 3=已收货 4=已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `ordered_at` DATETIME DEFAULT NULL COMMENT '下单时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_apply_id` (`apply_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单';

CREATE TABLE IF NOT EXISTS `erp_purchase_order_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '采购订单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订购数量',
    `received_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单明细';

CREATE TABLE IF NOT EXISTS `erp_purchase_receive` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收货单号',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '采购订单ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收货仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待入库 1=已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `received_at` DATETIME DEFAULT NULL COMMENT '收货时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货单';

CREATE TABLE IF NOT EXISTS `erp_purchase_receive_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单明细ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_order_item_id` (`order_item_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货明细';

CREATE TABLE IF NOT EXISTS `erp_purchase_return` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '退货单号',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '退货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待出库 1=已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货单';

CREATE TABLE IF NOT EXISTS `erp_purchase_return_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `return_id` BIGINT UNSIGNED NOT NULL COMMENT '退货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库库位ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_return_id` (`return_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货明细';

CREATE TABLE IF NOT EXISTS `erp_purchase_settlement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应付金额',
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已付金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未结算 1=部分结算 2=已结算',
    `settled_at` DATETIME DEFAULT NULL COMMENT '结算时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商结算';

-- ################################################################
-- PART 4: 销售模块表
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_sales_quotation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '报价单号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已报价 2=已转订单 3=已失效',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `quoted_at` DATETIME DEFAULT NULL COMMENT '报价时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售报价单';

CREATE TABLE IF NOT EXISTS `erp_sales_quotation_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `quotation_id` BIGINT UNSIGNED NOT NULL COMMENT '报价单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_quotation_id` (`quotation_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售报价明细';

CREATE TABLE IF NOT EXISTS `erp_sales_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '订单单号',
    `quotation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '报价单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核 2=部分发货 3=已发货 4=已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `ordered_at` DATETIME DEFAULT NULL COMMENT '下单时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_quotation_id` (`quotation_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单';

CREATE TABLE IF NOT EXISTS `erp_sales_order_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '销售订单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订购数量',
    `delivered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已发数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单明细';

CREATE TABLE IF NOT EXISTS `erp_sales_delivery` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '发货单号',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '销售订单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '发货仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待出库 1=已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `delivered_at` DATETIME DEFAULT NULL COMMENT '发货时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货单';

CREATE TABLE IF NOT EXISTS `erp_sales_delivery_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单明细ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '发货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_order_item_id` (`order_item_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货明细';

CREATE TABLE IF NOT EXISTS `erp_sales_return` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '退货单号',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '退货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待入库 1=已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货单';

CREATE TABLE IF NOT EXISTS `erp_sales_return_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `return_id` BIGINT UNSIGNED NOT NULL COMMENT '退货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库库位ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_return_id` (`return_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货明细';

CREATE TABLE IF NOT EXISTS `erp_sales_settlement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应收金额',
    `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未结算 1=部分结算 2=已结算',
    `settled_at` DATETIME DEFAULT NULL COMMENT '结算时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户结算';

-- ################################################################
-- PART 5: 库存管理
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_inventory` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '库存数量',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本单价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_sku_warehouse_location_batch` (`product_id`, `sku_id`, `warehouse_id`, `location_id`, `batch_code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='实时库存表';

CREATE TABLE IF NOT EXISTS `erp_inventory_batch` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL COMMENT '批次号',
    `production_date` DATE DEFAULT NULL COMMENT '生产日期',
    `expiry_date` DATE DEFAULT NULL COMMENT '过期日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_sku_batch` (`product_id`, `sku_id`, `batch_code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='批次追踪表';

CREATE TABLE IF NOT EXISTS `erp_inventory_serial` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `serial_code` VARCHAR(100) NOT NULL COMMENT '序列号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=在库 1=已出',
    `in_flow_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库流水ID',
    `out_flow_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库流水ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_serial_code` (`serial_code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='序列号追踪表';

CREATE TABLE IF NOT EXISTS `erp_inventory_flow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '方向: 1=入库 2=出库',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动数量',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本单价',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源单据类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存流水日志表';

CREATE TABLE IF NOT EXISTS `erp_transfer` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '调拨单号',
    `from_warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '调出仓库ID',
    `to_warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '调入仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待调拨 1=已调出 2=已调入 3=已完成',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `transferred_at` DATETIME DEFAULT NULL COMMENT '调拨时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_from_warehouse_id` (`from_warehouse_id`),
    KEY `idx_to_warehouse_id` (`to_warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='调拨单';

CREATE TABLE IF NOT EXISTS `erp_transfer_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `transfer_id` BIGINT UNSIGNED NOT NULL COMMENT '调拨单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `from_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '调出库位ID',
    `to_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '调入库位ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '调拨数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_transfer_id` (`transfer_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='调拨明细';

CREATE TABLE IF NOT EXISTS `erp_check_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '盘点单号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=计划盘点 2=动态盘点',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待盘点 1=已盘点 2=已处理',
    `check_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '盘点人ID',
    `checked_at` DATETIME DEFAULT NULL COMMENT '盘点时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_check_user_id` (`check_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘点任务表';

CREATE TABLE IF NOT EXISTS `erp_check_detail` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `check_id` BIGINT UNSIGNED NOT NULL COMMENT '盘点任务ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `book_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '账面数量',
    `actual_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际数量',
    `diff_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '差异数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_check_id` (`check_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘点明细表';

CREATE TABLE IF NOT EXISTS `erp_inventory_alert_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仓库ID，0表示全部仓库',
    `min_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最小库存阈值',
    `max_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最大库存阈值',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否启用: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存预警规则表';

CREATE TABLE IF NOT EXISTS `erp_inventory_alert_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rule_id` BIGINT UNSIGNED NOT NULL COMMENT '预警规则ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `current_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '当前库存数量',
    `alert_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '预警类型: 1=低于下限 2=高于上限',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_rule_id` (`rule_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存预警日志表';

CREATE TABLE IF NOT EXISTS `erp_cost_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `flow_id` BIGINT UNSIGNED NOT NULL COMMENT '库存流水ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '类型: 1=入库 2=出库',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '本次单位成本',
    `before_avg_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '移动平均前成本',
    `after_avg_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '移动平均后成本',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_flow_id` (`flow_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='成本计算日志表';

-- ################################################################
-- PART 6: 财务管理基础
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_company` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '组织编码',
    `name` VARCHAR(200) NOT NULL COMMENT '组织名称',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级组织ID, 0=顶级(集团)',
    `base_currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '本位币',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=停用 1=启用',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='组织/公司(多组织)';

CREATE TABLE IF NOT EXISTS `erp_finance_ledger` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `company_id` BIGINT UNSIGNED NOT NULL COMMENT '组织ID',
    `code` VARCHAR(50) NOT NULL COMMENT '账套编码(组织内唯一)',
    `name` VARCHAR(200) NOT NULL COMMENT '账套名称',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '记账币种',
    `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认账套: 0=否 1=是',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=停用 1=启用',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company_code` (`company_id`, `code`),
    KEY `idx_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务账套(F1)';

CREATE TABLE IF NOT EXISTS `erp_finance_period` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `ledger_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
    `period` VARCHAR(7) NOT NULL COMMENT '会计期间 YYYY-MM',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=开 1=关',
    `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '开账时间',
    `closed_at` DATETIME DEFAULT NULL COMMENT '关账时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ledger_period` (`ledger_id`, `period`),
    KEY `idx_ledger_id` (`ledger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会计期间(F1)';

CREATE TABLE IF NOT EXISTS `erp_finance_account` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级科目ID，0表示一级科目',
    `code` VARCHAR(50) NOT NULL COMMENT '科目编码',
    `name` VARCHAR(200) NOT NULL COMMENT '科目名称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=资产 2=负债 3=权益 4=收入 5=费用',
    `direction` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '余额方向: 1=借 2=贷',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会计科目表';

CREATE TABLE IF NOT EXISTS `erp_finance_voucher` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '凭证号',
    `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '账套ID, NULL=旧数据(默认账套)',
    `voucher_date` DATE NOT NULL COMMENT '凭证日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `audited_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_ledger_id` (`ledger_id`),
    KEY `idx_voucher_date` (`voucher_date`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记账凭证表';

CREATE TABLE IF NOT EXISTS `erp_finance_voucher_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `voucher_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证ID',
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '科目ID',
    `summary` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '摘要',
    `debit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '借方金额',
    `credit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '贷方金额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_voucher_id` (`voucher_id`),
    KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='凭证分录明细表';

CREATE TABLE IF NOT EXISTS `erp_finance_ar_ap` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '类型: 1=应收 2=应付',
    `partner_id` BIGINT UNSIGNED NOT NULL COMMENT '往来对象ID（客户/供应商）',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源单据类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `settled_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已核销金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未核销 1=部分核销 2=已核销',
    `due_date` DATE DEFAULT NULL COMMENT '到期日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_source` (`source_type`, `source_id`),
    KEY `idx_type` (`type`),
    KEY `idx_partner_id` (`partner_id`),
    KEY `idx_status` (`status`),
    KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应收应付明细表';

CREATE TABLE IF NOT EXISTS `erp_finance_bank_account` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '账户名称',
    `account_number` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行账号（加密存储）',
    `bank_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '开户银行名称',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_bank_name` (`bank_name`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='银行账户表';

CREATE TABLE IF NOT EXISTS `erp_finance_receipt` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收款单号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '收款账户ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '收款方式: cash/bank/wechat/alipay',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `received_at` DATETIME DEFAULT NULL COMMENT '收款时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_bank_account_id` (`bank_account_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收款单';

CREATE TABLE IF NOT EXISTS `erp_finance_payment` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '付款单号',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '付款账户ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '付款方式: cash/bank/wechat/alipay',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `paid_at` DATETIME DEFAULT NULL COMMENT '付款时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_bank_account_id` (`bank_account_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款单';

CREATE TABLE IF NOT EXISTS `erp_finance_settlement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `ar_ap_id` BIGINT UNSIGNED NOT NULL COMMENT '应收应付明细ID',
    `receipt_payment_id` BIGINT UNSIGNED NOT NULL COMMENT '收款/付款单ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '核销类型: 1=应收核销 2=应付核销',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '核销金额',
    `settled_at` DATETIME DEFAULT NULL COMMENT '核销时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_ar_ap_id` (`ar_ap_id`),
    KEY `idx_receipt_payment_id` (`receipt_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='核销记录表';

CREATE TABLE IF NOT EXISTS `erp_finance_cash_journal` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '银行账户ID',
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '方向: 1=收入 2=支出',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '交易后余额',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源单据类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `summary` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '摘要',
    `journal_date` DATE NOT NULL COMMENT '记账日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_bank_account_id` (`bank_account_id`),
    KEY `idx_journal_date` (`journal_date`),
    KEY `idx_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='现金日记账表';

CREATE TABLE IF NOT EXISTS `erp_finance_expense` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '报销单号',
    `apply_user_id` BIGINT UNSIGNED NOT NULL COMMENT '申请人ID',
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '费用科目ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报销金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审批 1=已批准 2=已驳回 3=已打款',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `approved_at` DATETIME DEFAULT NULL COMMENT '审批时间',
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `paid_at` DATETIME DEFAULT NULL COMMENT '打款时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_apply_user_id` (`apply_user_id`),
    KEY `idx_account_id` (`account_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='费用报销表';

CREATE TABLE IF NOT EXISTS `erp_finance_bill` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `bill_no` VARCHAR(50) NOT NULL COMMENT '票号(唯一)',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '票据类型: 1=银行承兑 2=商业承兑',
    `direction` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '方向: 1=收票(应收) 2=开票(应付)',
    `drawer` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '出票人',
    `payee` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '收款人',
    `acceptor` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '承兑人',
    `endorsee` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '被背书人(已背书时)',
    `issue_date` DATE DEFAULT NULL COMMENT '出票日期',
    `due_date` DATE NOT NULL COMMENT '到期日',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '票面金额',
    `discount_fee` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '贴现息(已贴现时记录)',
    `bank_account_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '托收银行账户ID，0=未指定',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=在库 1=已背书 2=已贴现 3=托收中 4=已到期兑付 5=已退票',
    `source_type` VARCHAR(30) NOT NULL DEFAULT 'manual' COMMENT '来源类型: manual=手工 receipt=关联收款单',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID(关联收款单时)',
    `endorsed_at` DATETIME DEFAULT NULL COMMENT '背书时间',
    `discounted_at` DATETIME DEFAULT NULL COMMENT '贴现时间',
    `collected_at` DATETIME DEFAULT NULL COMMENT '托收时间',
    `cashed_at` DATETIME DEFAULT NULL COMMENT '兑付/解付时间',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退票时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bill_no` (`bill_no`),
    KEY `idx_direction_status` (`direction`, `status`),
    KEY `idx_due_date` (`due_date`),
    KEY `idx_bank_account_id` (`bank_account_id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='承兑汇票票据台账(P2-F6)';

CREATE TABLE IF NOT EXISTS `erp_finance_bank_statement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '银行账户ID',
    `stmt_date` DATE NOT NULL COMMENT '交易日期',
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '方向: 1=收入 2=支出',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '发生额',
    `counterparty` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '对方户名',
    `reference` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '摘要/流水号',
    `balance_after` DECIMAL(14,2) DEFAULT NULL COMMENT '交易后余额(银行流水可空)',
    `import_batch` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '导入批次号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_account_date` (`bank_account_id`, `stmt_date`),
    KEY `idx_import_batch` (`import_batch`),
    KEY `idx_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='银企对账单行(P2-F6)';

CREATE TABLE IF NOT EXISTS `erp_finance_bank_recon_match` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '银行账户ID(与两侧一致)',
    `statement_id` BIGINT UNSIGNED NOT NULL COMMENT '对账单行ID',
    `cash_journal_id` BIGINT UNSIGNED NOT NULL COMMENT '日记账行ID',
    `match_type` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '匹配方式: 1=自动(金额+日期窗口+方向) 2=自动(摘要命中) 3=手工',
    `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_statement` (`bank_account_id`, `statement_id`),
    UNIQUE KEY `uk_journal` (`bank_account_id`, `cash_journal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='银企对账核销匹配(P2-F6)';

CREATE TABLE IF NOT EXISTS `erp_finance_profit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `company_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '组织ID, NULL=旧数据(默认公司)',
    `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '账套ID, NULL=旧数据(默认账套)',
    `year` SMALLINT UNSIGNED NOT NULL COMMENT '年份',
    `month` TINYINT UNSIGNED NOT NULL COMMENT '月份',
    `revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业收入',
    `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业成本',
    `expense` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '费用合计',
    `profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '利润',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ledger_period` (`ledger_id`, `year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='利润快照表';

-- ################################################################
-- PART 7: CRM基础
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_crm_funnel_stage` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '阶段名称',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值',
    `win_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '预计赢单率(%)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售漏斗阶段配置表';

CREATE TABLE IF NOT EXISTS `erp_crm_opportunity` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `stage_id` BIGINT UNSIGNED NOT NULL COMMENT '漏斗阶段ID',
    `name` VARCHAR(200) NOT NULL COMMENT '机会名称',
    `estimated_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预计金额',
    `probability` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '成交概率(%)',
    `expected_close_date` DATE DEFAULT NULL COMMENT '预计成交日期',
    `owner_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '负责人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=输单 1=进行中 2=已成交',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_stage_id` (`stage_id`),
    KEY `idx_owner_user_id` (`owner_user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售机会表';

CREATE TABLE IF NOT EXISTS `erp_crm_follow_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `opportunity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '销售机会ID',
    `method` VARCHAR(20) NOT NULL DEFAULT 'phone' COMMENT '跟进方式: phone/visit/email/message/other',
    `content` TEXT COMMENT '跟进内容',
    `next_plan` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '下一步计划',
    `next_follow_at` DATETIME DEFAULT NULL COMMENT '下次跟进时间',
    `follow_user_id` BIGINT UNSIGNED NOT NULL COMMENT '跟进人ID',
    `followed_at` DATETIME DEFAULT NULL COMMENT '跟进时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_opportunity_id` (`opportunity_id`),
    KEY `idx_follow_user_id` (`follow_user_id`),
    KEY `idx_next_follow_at` (`next_follow_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跟进记录表';

CREATE TABLE IF NOT EXISTS `erp_crm_contact` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `name` VARCHAR(50) NOT NULL COMMENT '联系人姓名',
    `position` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '职位',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `is_primary` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否首要联系人: 0=否 1=是',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_is_primary` (`is_primary`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系人表';

-- ################################################################
-- PART 8: 财务总账/明细账/三表
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_finance_general_ledger` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '科目ID',
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT '会计年度',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '会计月份(0=全年)',
    `opening_debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期初借方余额',
    `opening_credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期初贷方余额',
    `period_debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '本期借方发生额',
    `period_credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '本期贷方发生额',
    `closing_debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期末借方余额',
    `closing_credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期末贷方余额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_account_period` (`account_id`, `period_year`, `period_month`),
    KEY `idx_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='总账表';

CREATE TABLE IF NOT EXISTS `erp_finance_subsidiary_ledger` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '科目ID',
    `voucher_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证ID',
    `voucher_item_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证分录ID',
    `direction` TINYINT UNSIGNED NOT NULL COMMENT '1借方2贷方',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '余额',
    `summary` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '摘要',
    `entry_date` DATE NOT NULL COMMENT '记账日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_account_date` (`account_id`, `entry_date`),
    KEY `idx_voucher_id` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='明细账表';

CREATE TABLE IF NOT EXISTS `erp_finance_balance_sheet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `company_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '组织ID, NULL=旧数据(默认公司)',
    `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '账套ID, NULL=旧数据(默认账套)',
    `report_year` SMALLINT UNSIGNED NOT NULL COMMENT '会计年度',
    `report_month` TINYINT UNSIGNED NOT NULL COMMENT '会计月份',
    `total_assets` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '资产总计',
    `total_liabilities` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '负债总计',
    `total_equity` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '所有者权益总计',
    `current_assets` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '流动资产',
    `non_current_assets` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '非流动资产',
    `current_liabilities` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '流动负债',
    `non_current_liabilities` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '非流动负债',
    `report_data` JSON COMMENT '完整报表数据JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ledger_report` (`ledger_id`, `report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产负债表快照';

CREATE TABLE IF NOT EXISTS `erp_finance_cash_flow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `company_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '组织ID, NULL=旧数据(默认公司)',
    `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '账套ID, NULL=旧数据(默认账套)',
    `report_year` SMALLINT UNSIGNED NOT NULL COMMENT '会计年度',
    `report_month` TINYINT UNSIGNED NOT NULL COMMENT '会计月份',
    `operating_inflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '经营活动现金流入',
    `operating_outflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '经营活动现金流出',
    `operating_net` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '经营活动净流量',
    `investing_inflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '投资活动现金流入',
    `investing_outflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '投资活动现金流出',
    `investing_net` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '投资活动净流量',
    `financing_inflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '筹资活动现金流入',
    `financing_outflow` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '筹资活动现金流出',
    `financing_net` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '筹资活动净流量',
    `beginning_cash` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '期初现金余额',
    `ending_cash` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '期末现金余额',
    `report_data` JSON COMMENT '完整报表数据JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ledger_report` (`ledger_id`, `report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='现金流量表快照';

CREATE TABLE IF NOT EXISTS `erp_finance_consolidation_report` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `company_id` BIGINT UNSIGNED NOT NULL COMMENT '合并主体(集团)组织ID',
    `report_year` SMALLINT UNSIGNED NOT NULL COMMENT '报表年度',
    `report_month` TINYINT UNSIGNED NOT NULL COMMENT '报表月份 1-12',
    `base_currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '合并币种',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已出',
    `total_assets` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '资产合计',
    `total_liabilities` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '负债合计',
    `total_equity` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '权益合计',
    `revenue` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '营业收入',
    `net_profit` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '净利润',
    `report_data` JSON COMMENT '合并底稿明细(子公司贡献+抵销分录)',
    `issued_at` DATETIME DEFAULT NULL COMMENT '出表时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_company_period` (`company_id`, `report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='集团合并报表(F2)';

CREATE TABLE IF NOT EXISTS `erp_finance_elimination_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `report_id` BIGINT UNSIGNED NOT NULL COMMENT '合并报表ID',
    `account_code` VARCHAR(50) NOT NULL COMMENT '科目编码(须存在于 erp_finance_account)',
    `summary` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '抵销说明',
    `debit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '借方金额',
    `credit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '贷方金额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合并抵销分录行(F2)';

CREATE TABLE IF NOT EXISTS `erp_finance_voucher_source` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `voucher_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证ID',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源单据类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_voucher` (`voucher_id`),
    UNIQUE KEY `uk_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='凭证来源防重轨';

CREATE TABLE IF NOT EXISTS `erp_finance_cost_account_config` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `cost_type` TINYINT UNSIGNED NOT NULL COMMENT '成本类型: 1=材料 2=人工 3=制费 4=存货/产成品 5=差异',
    `account_id` BIGINT UNSIGNED NOT NULL COMMENT '会计科目ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=停用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cost_type` (`cost_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='成本结转科目映射配置';

-- ################################################################
-- PART 9: CRM扩展 — 公海池/合同
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_crm_customer_pool_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `level_id` BIGINT UNSIGNED NOT NULL COMMENT '客户等级ID',
    `reclaim_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT '无跟进自动回收天数',
    `max_claims` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT '每人最大领取数',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level_id` (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户公海池规则表';

CREATE TABLE IF NOT EXISTS `erp_crm_pool_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `action` TINYINT UNSIGNED NOT NULL COMMENT '1领取2释放3回收',
    `from_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '原归属人ID',
    `to_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新归属人ID',
    `remark` VARCHAR(200) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公海池操作记录表';

CREATE TABLE IF NOT EXISTS `erp_crm_contract` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '合同编号',
    `name` VARCHAR(200) NOT NULL COMMENT '合同名称',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `opportunity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联商机ID',
    `quotation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联报价ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '合同总额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿1待审批2已审批3执行中4已完成5已终止',
    `signed_at` DATE DEFAULT NULL COMMENT '签订日期',
    `start_date` DATE DEFAULT NULL COMMENT '开始日期',
    `end_date` DATE DEFAULT NULL COMMENT '结束日期',
    `owner_user_id` BIGINT UNSIGNED NOT NULL COMMENT '负责人ID',
    `content` TEXT COMMENT '合同条款内容',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_opportunity_id` (`opportunity_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同表';

CREATE TABLE IF NOT EXISTS `erp_crm_contract_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `contract_id` BIGINT UNSIGNED NOT NULL COMMENT '合同ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contract_id` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同明细表';

CREATE TABLE IF NOT EXISTS `erp_crm_quotation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '报价单号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `opportunity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联商机ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价总额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿1已发送2客户确认3已转合同4已失效',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `quoted_at` DATETIME DEFAULT NULL COMMENT '报价日期',
    `valid_until` DATE DEFAULT NULL COMMENT '有效期至',
    `owner_user_id` BIGINT UNSIGNED NOT NULL COMMENT '负责人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_opportunity_id` (`opportunity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CRM报价表';

CREATE TABLE IF NOT EXISTS `erp_crm_quotation_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `quotation_id` BIGINT UNSIGNED NOT NULL COMMENT '报价ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CRM报价明细表';

-- ################################################################
-- PART 10: 财务扩展 — 固定资产/税务/多币种/预算
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_finance_asset` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '资产编码',
    `name` VARCHAR(200) NOT NULL COMMENT '资产名称',
    `category` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '资产类别',
    `purchase_date` DATE DEFAULT NULL COMMENT '购置日期',
    `purchase_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '原值',
    `salvage_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '残值',
    `useful_life` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用年限(月)',
    `depreciation_method` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1直线法2双倍余额递减法3年数总和法',
    `monthly_depreciation` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '月折旧额',
    `accumulated_depreciation` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '累计折旧',
    `net_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '净值',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1使用中2已处置3报废',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_code` (`code`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='固定资产表';

CREATE TABLE IF NOT EXISTS `erp_finance_asset_depreciation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `asset_id` BIGINT UNSIGNED NOT NULL COMMENT '资产ID',
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT '会计年度',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '会计月份',
    `depreciation_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '折旧金额',
    `accumulated_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '累计折旧',
    `net_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '折旧后净值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_asset_period` (`asset_id`, `period_year`, `period_month`),
    KEY `idx_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产折旧记录表';

CREATE TABLE IF NOT EXISTS `erp_finance_tax_rate` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '税率名称',
    `rate` DECIMAL(6,4) NOT NULL DEFAULT 0.0000 COMMENT '税率(如0.13=13%)',
    `type` VARCHAR(30) NOT NULL DEFAULT 'vat' COMMENT '税种: vat/cit/pit/stamp/other',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='税率配置表';

CREATE TABLE IF NOT EXISTS `erp_finance_tax_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `tax_rate_id` BIGINT UNSIGNED NOT NULL COMMENT '税率ID',
    `source_type` VARCHAR(30) NOT NULL COMMENT '来源类型: sales/purchase',
    `source_id` BIGINT UNSIGNED NOT NULL COMMENT '来源单ID',
    `taxable_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '计税金额',
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '税额',
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT '所属年度',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '所属月份',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tax_rate_id` (`tax_rate_id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='税务记录表';

CREATE TABLE IF NOT EXISTS `erp_finance_currency` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(10) NOT NULL COMMENT '币种代码: CNY/USD/EUR/JPY/GBP',
    `name` VARCHAR(50) NOT NULL COMMENT '币种名称',
    `symbol` VARCHAR(5) NOT NULL DEFAULT '' COMMENT '货币符号',
    `is_base` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否本位币',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='币种表';

CREATE TABLE IF NOT EXISTS `erp_finance_exchange_rate` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `from_currency_id` BIGINT UNSIGNED NOT NULL COMMENT '原币ID',
    `to_currency_id` BIGINT UNSIGNED NOT NULL COMMENT '目标币ID',
    `rate` DECIMAL(14,6) NOT NULL DEFAULT 1.000000 COMMENT '汇率',
    `effective_date` DATE NOT NULL COMMENT '生效日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_currency_date` (`from_currency_id`, `to_currency_id`, `effective_date`),
    KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='汇率表';

CREATE TABLE IF NOT EXISTS `erp_finance_budget` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '预算编号',
    `name` VARCHAR(200) NOT NULL COMMENT '预算名称',
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT '预算年度',
    `cost_center_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成本中心ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿1已审批2执行中3已关闭',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_period` (`period_year`), KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='预算表';

CREATE TABLE IF NOT EXISTS `erp_finance_budget_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `budget_id` BIGINT UNSIGNED NOT NULL COMMENT '预算ID',
    `account_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联科目ID',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '预算月份(0=全年)',
    `budget_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '预算金额',
    `actual_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际金额',
    `remark` VARCHAR(200) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_budget_id` (`budget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='预算明细表';

CREATE TABLE IF NOT EXISTS `erp_finance_cost_center` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级成本中心ID',
    `code` VARCHAR(50) NOT NULL COMMENT '编码',
    `name` VARCHAR(100) NOT NULL COMMENT '名称',
    `manager` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '负责人',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='成本中心表';

CREATE TABLE IF NOT EXISTS `erp_finance_profit_center` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级利润中心ID',
    `code` VARCHAR(50) NOT NULL COMMENT '编码',
    `name` VARCHAR(100) NOT NULL COMMENT '名称',
    `manager` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '负责人',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='利润中心表';

CREATE TABLE IF NOT EXISTS `erp_finance_allocation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `source_center_id` BIGINT UNSIGNED NOT NULL COMMENT '来源成本中心ID',
    `target_center_id` BIGINT UNSIGNED NOT NULL COMMENT '目标成本中心ID',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '分摊金额',
    `basis` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分摊依据: revenue/headcount/area/direct',
    `period_year` SMALLINT UNSIGNED NOT NULL,
    `period_month` TINYINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_source` (`source_center_id`),
    KEY `idx_target` (`target_center_id`),
    KEY `idx_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='费用分摊记录表';

-- ################################################################
-- PART 11: CRM扩展 — 营销/工单/分析
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_crm_campaign` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '活动编号',
    `name` VARCHAR(200) NOT NULL COMMENT '活动名称',
    `type` VARCHAR(30) NOT NULL DEFAULT 'email' COMMENT '类型: email/sms/phone/event/social/other',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0计划中1进行中2已完成3已取消',
    `budget_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预算金额',
    `actual_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际花费',
    `start_date` DATE DEFAULT NULL COMMENT '开始日期',
    `end_date` DATE DEFAULT NULL COMMENT '结束日期',
    `target_audience` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '目标受众',
    `description` TEXT COMMENT '活动描述',
    `owner_user_id` BIGINT UNSIGNED NOT NULL COMMENT '负责人ID',
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_status` (`status`), KEY `idx_owner` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='营销活动表';

CREATE TABLE IF NOT EXISTS `erp_crm_campaign_participant` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `campaign_id` BIGINT UNSIGNED NOT NULL COMMENT '活动ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0已邀请1已参与2已转化3已退订',
    `response` VARCHAR(500) NOT NULL DEFAULT '',
    `participated_at` DATETIME DEFAULT NULL COMMENT '参与时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='营销活动参与记录表';

CREATE TABLE IF NOT EXISTS `erp_crm_ticket` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '工单编号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `title` VARCHAR(200) NOT NULL COMMENT '工单标题',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1低2中3高4紧急',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待处理1处理中2已解决3已关闭',
    `category` VARCHAR(30) NOT NULL DEFAULT 'other' COMMENT '分类: tech/complaint/inquiry/return/other',
    `assignee_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '指派人ID',
    `resolved_at` DATETIME DEFAULT NULL COMMENT '解决时间',
    `closed_at` DATETIME DEFAULT NULL COMMENT '关闭时间',
    `content` TEXT COMMENT '工单内容',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_assignee` (`assignee_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='服务工单表';

CREATE TABLE IF NOT EXISTS `erp_crm_ticket_reply` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `ticket_id` BIGINT UNSIGNED NOT NULL COMMENT '工单ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '回复人ID(0=客户)',
    `content` TEXT COMMENT '回复内容',
    `is_internal` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0对外1内部备忘',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单回复表';

CREATE TABLE IF NOT EXISTS `erp_crm_analytics_report` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '报表名称',
    `type` VARCHAR(30) NOT NULL COMMENT '类型: customer/order/revenue/activity/retention',
    `period_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1月度2季度3年度',
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT '年份',
    `period_value` TINYINT UNSIGNED NOT NULL COMMENT '期数(月/季度)',
    `report_data` JSON COMMENT '报表数据JSON',
    `generated_at` DATETIME DEFAULT NULL COMMENT '生成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_period` (`period_year`, `period_value`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户分析报表';

CREATE TABLE IF NOT EXISTS `erp_crm_analytics_metric` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '指标名称',
    `key` VARCHAR(50) NOT NULL COMMENT '指标键名',
    `type` VARCHAR(30) NOT NULL COMMENT '类型: count/sum/average/ratio',
    `query_config` JSON COMMENT '查询配置JSON',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分析指标定义表';

-- ################################################################
-- PART 12: 审批工作流引擎
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_approval_workflow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL COMMENT '流程编码',
    `name` VARCHAR(100) NOT NULL COMMENT '流程名称',
    `target_type` VARCHAR(30) NOT NULL COMMENT '适用单据: purchase_apply/purchase_order/expense/leave/other',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `canvas_json` TEXT NOT NULL DEFAULT ('') COMMENT '画布快照(节点坐标+边+条件, P1-B3 设计器; 执行真相源仍是 erp_approval_node; 需 MySQL 8.0.13+ 表达式默认值语法)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_target_type` (`target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批工作流模板表';

CREATE TABLE IF NOT EXISTS `erp_approval_node` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `workflow_id` BIGINT UNSIGNED NOT NULL COMMENT '工作流ID',
    `name` VARCHAR(50) NOT NULL COMMENT '节点名称',
    `approver_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1指定人2角色3部门负责人4直属上级',
    `approver_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `role_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批顺序',
    `condition_field` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '条件字段: amount/department',
    `condition_op` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '条件操作符: gt/gte/lt/lte/eq',
    `condition_value` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '条件值',
    `can_reject` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '可否驳回',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_workflow_id` (`workflow_id`), KEY `idx_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批节点表';

CREATE TABLE IF NOT EXISTS `erp_approval_instance` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `workflow_id` BIGINT UNSIGNED NOT NULL COMMENT '工作流ID',
    `target_type` VARCHAR(30) NOT NULL COMMENT '单据类型',
    `target_id` BIGINT UNSIGNED NOT NULL COMMENT '单据ID',
    `submitter_id` BIGINT UNSIGNED NOT NULL COMMENT '提交人ID',
    `current_node_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前审批节点ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0审批中1已通过2已驳回3已撤回',
    `submitted_at` DATETIME DEFAULT NULL COMMENT '提交时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_target` (`target_type`, `target_id`),
    KEY `idx_workflow_id` (`workflow_id`),
    KEY `idx_submitter_id` (`submitter_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批实例表';

CREATE TABLE IF NOT EXISTS `erp_approval_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `instance_id` BIGINT UNSIGNED NOT NULL COMMENT '审批实例ID',
    `node_id` BIGINT UNSIGNED NOT NULL COMMENT '审批节点ID',
    `approver_id` BIGINT UNSIGNED NOT NULL COMMENT '审批人ID',
    `action` TINYINT UNSIGNED NOT NULL COMMENT '1通过2驳回3转交',
    `comment` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '审批意见',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '审批时间',
    PRIMARY KEY (`id`),
    KEY `idx_instance_id` (`instance_id`),
    KEY `idx_approver_id` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批记录表';

CREATE TABLE IF NOT EXISTS `erp_print_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '模板编码',
    `name` VARCHAR(100) NOT NULL COMMENT '模板名称',
    `target_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '适用单据类型(如 sales_order/purchase_order), 空=通用',
    `content` MEDIUMTEXT NOT NULL COMMENT 'HTML模板体(含{{占位符}}; 中文需@font-face声明CJK字体)',
    `paper_size` VARCHAR(20) NOT NULL DEFAULT 'A4' COMMENT '纸张规格: A4/A5/Letter/Legal',
    `orientation` VARCHAR(10) NOT NULL DEFAULT 'portrait' COMMENT '页面方向: portrait/landscape',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '启用: 0=停用 1=启用',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_target_type` (`target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='单据打印模板表';

-- ################################################################
-- PART 13: 消息通知系统
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_notification` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '接收用户ID',
    `title` VARCHAR(200) NOT NULL COMMENT '通知标题',
    `content` TEXT COMMENT '通知内容',
    `type` VARCHAR(30) NOT NULL DEFAULT 'system' COMMENT '类型: system/approval/alert/reminder',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源ID',
    `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未读1已读',
    `read_at` DATETIME DEFAULT NULL COMMENT '阅读时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知消息表';

CREATE TABLE IF NOT EXISTS `erp_notification_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL COMMENT '模板编码',
    `name` VARCHAR(100) NOT NULL COMMENT '模板名称',
    `title_tpl` VARCHAR(200) NOT NULL COMMENT '标题模板',
    `content_tpl` TEXT COMMENT '内容模板(支持{变量})',
    `channels` VARCHAR(100) NOT NULL DEFAULT 'in_app' COMMENT '发送渠道: in_app,email,sms',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知模板表';

CREATE TABLE IF NOT EXISTS `erp_notification_setting` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `notify_type` VARCHAR(30) NOT NULL COMMENT '通知类型',
    `in_app` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '站内通知',
    `email` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邮件通知',
    `sms` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '短信通知',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_type` (`user_id`, `notify_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知设置表';

-- ################################################################
-- PART 14: 项目管理
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_project` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '项目编号',
    `name` VARCHAR(200) NOT NULL COMMENT '项目名称',
    `customer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户ID',
    `manager_user_id` BIGINT UNSIGNED NOT NULL COMMENT '项目经理ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0规划中1进行中2已延期3已完成4已取消',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '1低2中3高4紧急',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `budget_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '项目预算',
    `actual_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际成本',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进度百分比0-100',
    `description` TEXT COMMENT '项目描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`),
    KEY `idx_status` (`status`), KEY `idx_manager` (`manager_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目表';

CREATE TABLE IF NOT EXISTS `erp_project_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父任务ID(WBS)',
    `name` VARCHAR(200) NOT NULL COMMENT '任务名称',
    `assignee_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '负责人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待开始1进行中2已完成3已延期',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `start_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `estimated_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '预估工时',
    `actual_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '实际工时',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进度0-100',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_assignee` (`assignee_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目任务表(WBS)';

CREATE TABLE IF NOT EXISTS `erp_project_member` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '成员ID',
    `role` VARCHAR(30) NOT NULL DEFAULT 'member' COMMENT '角色: manager/developer/tester/designer/viewer',
    `joined_at` DATETIME DEFAULT NULL,
    `hourly_rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '费率(元/小时), 0=未配置',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_project_user` (`project_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目成员表';

CREATE TABLE IF NOT EXISTS `erp_project_cost` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务ID, 0=无',
    `employee_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '员工ID(项目成员user_id), 0=无',
    `work_date` DATE NOT NULL COMMENT '发生日期',
    `source_type` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT '来源: timesheet=工时归集 manual=手工录入',
    `timesheet_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源工时记录ID, 0=非工时来源',
    `category` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类别: 1=人工 2=材料 3=其他',
    `hours` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '工时数(非工时来源=0)',
    `rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '费率快照(元/小时)',
    `cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '成本(bcmath hours×rate, half-up 2位)',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_work_date` (`work_date`),
    KEY `idx_timesheet_id` (`timesheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目成本归集台账(P1, 工时归集幂等+手工录入)';

CREATE TABLE IF NOT EXISTS `erp_project_timesheet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `hours` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT '工时(小时)',
    `work_date` DATE NOT NULL COMMENT '工作日期',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '工作内容',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_user_date` (`user_id`, `work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目工时记录表';

CREATE TABLE IF NOT EXISTS `erp_project_gantt` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
    `dependency_task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '前置任务ID',
    `gantt_data` JSON COMMENT '甘特图数据JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_task` (`task_id`),
    KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='甘特图数据表';

-- ################################################################
-- PART 15: 人力资源管理
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_hr_department` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级部门ID，0表示顶级',
    `code` VARCHAR(50) NOT NULL COMMENT '部门编码',
    `name` VARCHAR(100) NOT NULL COMMENT '部门名称',
    `manager_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门负责人用户ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

CREATE TABLE IF NOT EXISTS `erp_hr_position` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属部门ID',
    `code` VARCHAR(50) NOT NULL COMMENT '职位编码',
    `name` VARCHAR(100) NOT NULL COMMENT '职位名称',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职级，值越大级别越高',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='职位表';

CREATE TABLE IF NOT EXISTS `erp_hr_employee` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '员工编码',
    `name` VARCHAR(50) NOT NULL COMMENT '员工姓名',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID',
    `position_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职位ID',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '性别: 1=男 2=女',
    `birthday` DATE DEFAULT NULL COMMENT '出生日期',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `id_card` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证号（加密存储）',
    `hire_date` DATE DEFAULT NULL COMMENT '入职日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=在职 2=离职 3=停职',
    `bank_account` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行账号（加密存储）',
    `emergency_contact` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '紧急联系人',
    `emergency_phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '紧急联系电话（加密存储）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_position_id` (`position_id`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工表';

CREATE TABLE IF NOT EXISTS `erp_hr_attendance_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '规则名称',
    `clock_in_time` TIME NOT NULL COMMENT '上班打卡时间',
    `clock_out_time` TIME NOT NULL COMMENT '下班打卡时间',
    `late_grace` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '迟到宽限分钟数',
    `early_grace` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '早退宽限分钟数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤规则表';

CREATE TABLE IF NOT EXISTS `erp_hr_attendance` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `rule_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '考勤规则ID',
    `work_date` DATE NOT NULL COMMENT '工作日期',
    `clock_in` DATETIME DEFAULT NULL COMMENT '上班打卡时间',
    `clock_out` DATETIME DEFAULT NULL COMMENT '下班打卡时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=正常 2=迟到 3=早退 4=缺卡 5=请假 6=出差',
    `late_minutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '迟到分钟数',
    `early_minutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '早退分钟数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_work_date` (`work_date`),
    KEY `idx_employee_date` (`employee_id`, `work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤记录表';

CREATE TABLE IF NOT EXISTS `erp_hr_leave` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '请假类型: 1=年假 2=事假 3=病假 4=婚假 5=产假 6=调休',
    `start_date` DATE NOT NULL COMMENT '开始日期',
    `end_date` DATE NOT NULL COMMENT '结束日期',
    `days` DECIMAL(4,1) NOT NULL DEFAULT 0.0 COMMENT '请假天数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批状态: 0=待审批 1=已批准 2=已驳回',
    `reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '请假原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_start_date` (`start_date`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='请假表';

CREATE TABLE IF NOT EXISTS `erp_hr_salary` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `period_year` INT UNSIGNED NOT NULL COMMENT '薪资年份',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '薪资月份',
    `base_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '基本工资',
    `performance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '绩效工资',
    `piece_wage` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '计件工资(自动归集)',
    `overtime` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '加班工资',
    `deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '扣款合计',
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '个人所得税',
    `net_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '实发工资',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发放',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_period` (`period_year`, `period_month`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='薪资表';

CREATE TABLE IF NOT EXISTS `erp_hr_salary_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '薪资项编码',
    `name` VARCHAR(100) NOT NULL COMMENT '薪资项名称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=收入 2=扣除',
    `is_taxable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否计税: 0=否 1=是',
    `default_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '默认金额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='薪资项表';

CREATE TABLE IF NOT EXISTS `erp_hr_job` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `job_title` VARCHAR(100) NOT NULL COMMENT '职位名称',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID（erp_hr_department.id）',
    `headcount` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '招聘人数',
    `requirement` TEXT NULL COMMENT '任职要求',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0草稿/1发布中/2已关闭',
    `publish_at` DATETIME NULL COMMENT '发布时间',
    `close_at` DATETIME NULL COMMENT '关闭时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '删除时间（软删除）',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='招聘职位(P1-H1)';

CREATE TABLE IF NOT EXISTS `erp_hr_candidate` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '姓名',
    `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '联系电话（普通索引，允许重复，防假唯一）',
    `source` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源：自主/猎头/内推/招聘网站',
    `job_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '应聘职位ID（erp_hr_job.id）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0新简历/1初筛通过/2面试中/3已发Offer/4已入职/5已淘汰',
    `expected_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期望薪资（元）',
    `resume_summary` TEXT NULL COMMENT '简历摘要',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_phone` (`phone`),
    KEY `idx_job` (`job_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='候选人(P1-H1)';

CREATE TABLE IF NOT EXISTS `erp_hr_interview` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `candidate_id` BIGINT UNSIGNED NOT NULL COMMENT '候选人ID（erp_hr_candidate.id）',
    `round_no` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '面试轮次（同候选人自动递增）',
    `interviewer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '面试官ID（erp_hr_employee.id）',
    `interview_date` DATE NOT NULL COMMENT '面试日期',
    `result` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '结果：0待定/1通过/2不通过',
    `comment` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '面试评价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_candidate` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='面试记录(P1-H1)';

CREATE TABLE IF NOT EXISTS `erp_hr_offer` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `candidate_id` BIGINT UNSIGNED NOT NULL COMMENT '候选人ID（erp_hr_candidate.id）',
    `offered_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Offer薪资（元）',
    `onboard_date` DATE NULL COMMENT '预计入职日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0草稿/1已发出/2已接受/3已拒绝',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_candidate` (`candidate_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Offer记录(P1-H1)';

CREATE TABLE IF NOT EXISTS `erp_hr_kpi_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '模板名称',
    `period_type` VARCHAR(20) NOT NULL DEFAULT 'monthly' COMMENT '考核周期类型：monthly月度/quarterly季度/yearly年度',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0草稿/1启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KPI考核模板(P1-H2)';

CREATE TABLE IF NOT EXISTS `erp_hr_kpi_template_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '模板ID（erp_hr_kpi_template.id）',
    `indicator` VARCHAR(100) NOT NULL COMMENT '指标名称',
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '权重（%，合计须=100.00）',
    `target_value` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '目标值描述',
    `rater_type` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '评分人类型：1自评/2上级/3同事360',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序（升序）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KPI模板指标项(P1-H2, 整存整替)';

CREATE TABLE IF NOT EXISTS `erp_hr_perf_plan` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '模板ID（erp_hr_kpi_template.id）',
    `period_start` DATE NOT NULL COMMENT '考核周期开始（含）',
    `period_end` DATE NOT NULL COMMENT '考核周期结束（含）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0草稿/1进行中/2已归档',
    `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人ID（erp_hr_employee.id）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_template` (`template_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考核批次(P1-H2)';

CREATE TABLE IF NOT EXISTS `erp_hr_perf_score` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `plan_id` BIGINT UNSIGNED NOT NULL COMMENT '考核批次ID（erp_hr_perf_plan.id）',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '被考核员工ID（erp_hr_employee.id）',
    `rater_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评分人ID（erp_hr_employee.id，自评=被考核人）',
    `rater_type` TINYINT UNSIGNED NOT NULL COMMENT '评分人类型快照：1自评/2上级/3同事360',
    `indicator` VARCHAR(100) NOT NULL COMMENT '指标名称（快照）',
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '得分（0.00~100.00）',
    `comment` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '评分评语',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plan_emp_rater_indicator` (`plan_id`, `employee_id`, `rater_id`, `indicator`),
    KEY `idx_plan_employee` (`plan_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考核评分记录(P1-H2, 同人同指标重复提交=覆盖)';

-- ################################################################
-- PART 16: 生产制造
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_mfg_bom` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_bom_item` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_production_order` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_production_item` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_routing` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `name` VARCHAR(100) NOT NULL COMMENT '工序名称',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '工序号',
    `workstation_id` BIGINT UNSIGNED NOT NULL COMMENT '工作站ID',
    `standard_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '标准工时（小时）',
    `piece_rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计件单价(元/合格件, 0=无计件)',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '工艺描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_workstation_id` (`workstation_id`),
    KEY `idx_seq` (`product_id`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工艺路线表';

CREATE TABLE IF NOT EXISTS `erp_mfg_workstation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '工作站编码',
    `name` VARCHAR(100) NOT NULL COMMENT '工作站名称',
    `capacity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每小时产能',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作站表';

CREATE TABLE IF NOT EXISTS `erp_mfg_capacity_calendar` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `workstation_id` BIGINT UNSIGNED NOT NULL COMMENT '工作站ID(erp_mfg_workstation.id)',
    `work_date` DATE NOT NULL COMMENT '日期',
    `available_hours` DECIMAL(5,2) NOT NULL DEFAULT 8.00 COMMENT '可用工时(小时, 0=闭厂)',
    `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注(停机/检修/班次调整等)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ws_date` (`workstation_id`, `work_date`),
    KEY `idx_work_date` (`work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作站日历例外表(默认周一~五8小时, 仅存覆盖日)';

CREATE TABLE IF NOT EXISTS `erp_mfg_mrp_plan` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_mrp_item` (
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

CREATE TABLE IF NOT EXISTS `erp_mfg_material_issue` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '领料单编码',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '领料仓库ID',
    `issue_date` DATE NOT NULL COMMENT '领料日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '领料总成本（审核时快照）',
    `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生产领料单';

CREATE TABLE IF NOT EXISTS `erp_mfg_material_issue_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `issue_id` BIGINT UNSIGNED NOT NULL COMMENT '领料单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '物料产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物料SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '领料数量',
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '审核时点移动加权均价快照',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '金额=数量×均价快照',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_issue_sku` (`issue_id`, `sku_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生产领料单明细';

CREATE TABLE IF NOT EXISTS `erp_mfg_cost_entry` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '费用单编码',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `entry_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=人工 2=制费 3=其他',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `entry_date` DATE NOT NULL COMMENT '费用发生日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `summary` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '摘要',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_entry_type` (`entry_type`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生产成本归集单';

CREATE TABLE IF NOT EXISTS `erp_mfg_wip` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `material_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '材料成本(实领)',
    `labor_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '人工成本',
    `overhead_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '制造费用',
    `other_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '其他成本',
    `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '成本合计',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=在制 1=已完工结转 2=已生成结转凭证',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单生产成本台账';

CREATE TABLE IF NOT EXISTS `erp_mfg_wip_flow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `wip_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'WIP台账ID',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `source_type` TINYINT UNSIGNED NOT NULL COMMENT '来源类型: 1=领料 2=人工 3=制费 4=其他 5=完工转出',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `direction` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '方向: 1=归集加 2=转出减',
    `flow_date` DATE NOT NULL COMMENT '发生日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_wip_id` (`wip_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单成本归集流水';

CREATE TABLE IF NOT EXISTS `erp_mfg_order_cost` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID',
    `finished_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '完工数量',
    `standard_material_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '标准材料成本',
    `actual_material_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际材料成本',
    `labor_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际人工成本',
    `overhead_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际制造费用',
    `other_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际其他成本',
    `material_diff` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '材料成本差异(实际-标准)',
    `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '完工成本合计(实际)',
    `unit_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '单位成本=合计/完工数量',
    `voucher_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '结转凭证ID，0=未生成',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=已结算 1=已生成凭证',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单完工成本结算表';

CREATE TABLE IF NOT EXISTS `erp_mfg_work_report` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '报工单编码',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生产工单ID(erp_mfg_production_order)',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID(须与工序所属产品一致)',
    `routing_id` BIGINT UNSIGNED NOT NULL COMMENT '工序ID(erp_mfg_routing)',
    `workstation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '工作站ID(erp_mfg_workstation)',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '报工员工ID(erp_hr_employee)',
    `report_date` DATE NOT NULL COMMENT '报工日期(计件归集期间依据)',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报工数量',
    `qualified_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '合格数量(计件工资与人工成本依据)',
    `piece_rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计件单价(审核快照, 源 erp_mfg_routing.piece_rate)',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '计件金额(审核快照=合格数量×单价)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_employee_date` (`employee_id`, `report_date`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工序报工单(P1-M1)';

CREATE TABLE IF NOT EXISTS `erp_mfg_piece_wage` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID(erp_hr_employee)',
    `period_year` INT UNSIGNED NOT NULL COMMENT '计件年度',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '计件月份 1-12',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '计件合格数量合计',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '计件工资合计',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_period` (`employee_id`, `period_year`, `period_month`),
    KEY `idx_period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='计件工资月累计表(P1-M1)';

CREATE TABLE IF NOT EXISTS `erp_mfg_subcontract` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '委外订单编码',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID(erp_supplier)',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '委外加工产品ID(erp_product_sku.product_id)',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收料仓库ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '委外数量',
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '委外加工单价',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '加工费快照(数量×单价, 发料审核时写入)',
    `issued_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '已发料金额累计(发料单审核成本快照)',
    `received_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收货数量',
    `consumed_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '核销成本快照(=核销时 issued_amount, v1 全额冲抵)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发料 2=已收货 3=已核销',
    `audit_at` DATETIME DEFAULT NULL COMMENT '发料审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='委外订单(P1-M2)';

CREATE TABLE IF NOT EXISTS `erp_mfg_subcontract_issue` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '发料单编码',
    `subcontract_id` BIGINT UNSIGNED NOT NULL COMMENT '委外订单ID(erp_mfg_subcontract)',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '出库仓库ID',
    `issue_date` DATE NOT NULL COMMENT '发料日期',
    `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '发料成本快照(审核时累计各行金额)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_subcontract_id` (`subcontract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='委外发料单(P1-M2)';

CREATE TABLE IF NOT EXISTS `erp_mfg_subcontract_issue_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `issue_id` BIGINT UNSIGNED NOT NULL COMMENT '发料单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '材料产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL COMMENT '材料SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '发料数量',
    `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '出库成本单价(审核快照, 移动加权)',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '发料金额(审核快照=数量×单价)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_issue_sku` (`issue_id`, `sku_id`),
    KEY `idx_issue_id` (`issue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='委外发料单明细(P1-M2)';

CREATE TABLE IF NOT EXISTS `erp_mfg_subcontract_receive` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收料单编码',
    `subcontract_id` BIGINT UNSIGNED NOT NULL COMMENT '委外订单ID(erp_mfg_subcontract)',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收料仓库ID(入库)',
    `receive_date` DATE NOT NULL COMMENT '收料日期',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收料数量(≤委外单未收数量)',
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '入库成本单价(审核快照=委外单加工单价)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_subcontract_id` (`subcontract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='委外收料单(P1-M2)';

-- ################################################################
-- PART 17: 自定义报表构建器
-- ################################################################

CREATE TABLE IF NOT EXISTS `erp_report_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '模板编码',
    `name` VARCHAR(200) NOT NULL COMMENT '模板名称',
    `module` VARCHAR(50) NOT NULL COMMENT '关联模块',
    `query_config` JSON DEFAULT NULL COMMENT '查询配置: table, joins, where, group_by',
    `chart_type` VARCHAR(20) NOT NULL DEFAULT 'table' COMMENT '图表类型: table/bar/line/pie/kpi',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_module` (`module`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报表模板表';

CREATE TABLE IF NOT EXISTS `erp_report_field` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '报表模板ID',
    `name` VARCHAR(100) NOT NULL COMMENT '字段名',
    `field` VARCHAR(100) NOT NULL COMMENT '字段名: table.column',
    `label` VARCHAR(100) NOT NULL COMMENT '显示名',
    `data_type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '数据类型: string/number/date/currency',
    `aggregator` VARCHAR(10) NOT NULL DEFAULT 'none' COMMENT '聚合函数: sum/avg/count/max/min/none',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序号',
    `width` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '列宽度（像素），0表示自适应',
    `visible` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否可见: 0=否 1=是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报表字段配置表';

CREATE TABLE IF NOT EXISTS `erp_report_filter` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '报表模板ID',
    `name` VARCHAR(100) NOT NULL COMMENT '筛选条件名称',
    `field` VARCHAR(100) NOT NULL COMMENT '筛选字段: table.column',
    `filter_type` VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT '筛选类型: text/select/date_range/number_range',
    `default_value` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '默认值',
    `required` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否必填: 0=否 1=是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报表筛选条件表';

CREATE TABLE IF NOT EXISTS `erp_report_dataset` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '报表模板ID',
    `name` VARCHAR(200) NOT NULL COMMENT '数据集名称',
    `query_sql` TEXT DEFAULT NULL COMMENT '实际执行的SQL语句',
    `data` JSON DEFAULT NULL COMMENT '查询结果数据',
    `rows_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '结果行数',
    `generated_at` DATETIME DEFAULT NULL COMMENT '生成时间',
    `parameters` JSON DEFAULT NULL COMMENT '执行时使用的参数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_generated_at` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报表数据集表';

CREATE TABLE IF NOT EXISTS `erp_report_schedule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `template_id` BIGINT UNSIGNED NOT NULL COMMENT '报表模板ID',
    `name` VARCHAR(200) NOT NULL COMMENT '调度任务名称',
    `frequency` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '发送频率: 1=每天 2=每周 3=每月',
    `recipients` TEXT DEFAULT NULL COMMENT '接收人（逗号分隔用户ID）',
    `next_run_at` DATETIME DEFAULT NULL COMMENT '下次执行时间',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否启用: 0=否 1=是',
    `last_run_at` DATETIME DEFAULT NULL COMMENT '上次执行时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_next_run_at` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报表调度配置表';

-- ################################################################
-- PART 18: 种子数据
-- ################################################################

-- ============================================================
-- 默认超级管理员角色
-- ============================================================
INSERT INTO `erp_admin_role` (`id`, `name`, `slug`, `description`, `status`) VALUES
(10000000000000001, '超级管理员', 'super_admin', '系统超级管理员，拥有所有权限', 1);

-- ============================================================
-- 漏斗阶段种子数据
-- ============================================================
INSERT INTO `erp_crm_funnel_stage` (`id`, `name`, `sort`, `win_rate`, `status`) VALUES
(50000000000000001, '初步接触', 1, 10.00, 1),
(50000000000000002, '需求确认', 2, 25.00, 1),
(50000000000000003, '报价方案', 3, 40.00, 1),
(50000000000000004, '商务谈判', 4, 60.00, 1),
(50000000000000005, '成交', 5, 100.00, 1),
(50000000000000006, '输单', 6, 0.00, 1);

-- ============================================================
-- 税率种子数据
-- ============================================================
INSERT INTO `erp_finance_tax_rate` (`id`, `name`, `rate`, `type`) VALUES
(60000000000000001, '增值税-标准税率', 0.1300, 'vat'),
(60000000000000002, '增值税-低税率', 0.0900, 'vat'),
(60000000000000003, '增值税-零税率', 0.0000, 'vat'),
(60000000000000004, '企业所得税', 0.2500, 'cit');

-- ============================================================
-- 币种种子数据
-- ============================================================
INSERT INTO `erp_finance_currency` (`id`, `code`, `name`, `symbol`, `is_base`) VALUES
(61000000000000001, 'CNY', '人民币', '¥', 1),
(61000000000000002, 'USD', '美元', '$', 0),
(61000000000000003, 'EUR', '欧元', '€', 0),
(61000000000000004, 'JPY', '日元', '¥', 0);

-- ============================================================
-- 客户分析指标种子数据
-- ============================================================
INSERT INTO `erp_crm_analytics_metric` (`id`, `name`, `key`, `type`) VALUES
(70000000000000001, '新增客户数', 'new_customers', 'count'),
(70000000000000002, '活跃客户数', 'active_customers', 'count'),
(70000000000000003, '客户留存率', 'retention_rate', 'ratio'),
(70000000000000004, '平均客单价', 'avg_order_value', 'average'),
(70000000000000005, '客户生命周期价值', 'clv', 'sum'),
(70000000000000006, '服务工单解决率', 'ticket_resolution_rate', 'ratio');

-- ============================================================
-- 管理系统权限种子数据 — 菜单 (type=1)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000001, 0, '仪表盘',    'dashboard',     1, 'dashboard', '/dashboard',        1, NOW(), NOW()),
(21000000000000002, 0, '用户管理',  'user',           1, 'people',    '/admin/user',        2, NOW(), NOW()),
(21000000000000003, 0, '角色管理',  'role',           1, 'shield',    '/admin/role',        3, NOW(), NOW()),
(21000000000000004, 0, '权限管理',  'permission',     1, 'lock',      '/admin/permission',  4, NOW(), NOW()),
(21000000000000005, 0, '系统配置',  'config',         1, 'settings',  '/admin/config',      5, NOW(), NOW()),
(21000000000000006, 0, '操作日志',  'log',            1, 'article',   '/admin/log',         6, NOW(), NOW());

-- ============================================================
-- ERP模块菜单权限 (type=1)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000001, 0, '商品管理', 'product',    1, 'inventory',     '/admin/product',    7, NOW(), NOW()),
(31000000000000002, 0, '采购管理', 'purchase',    1, 'shopping_cart', '/admin/purchase',   8, NOW(), NOW()),
(31000000000000003, 0, '销售管理', 'sales',       1, 'sell',          '/admin/sales',      9, NOW(), NOW()),
(31000000000000004, 0, '库存管理', 'inventory',   1, 'warehouse',     '/admin/inventory', 10, NOW(), NOW()),
(31000000000000005, 0, '财务管理', 'finance',     1, 'account_balance', '/admin/finance',  11, NOW(), NOW()),
(31000000000000006, 0, 'CRM',       'crm',        1, 'people',        '/admin/crm',       12, NOW(), NOW());

-- ============================================================
-- 管理系统按钮权限 (type=2)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000011, 21000000000000002, '批量删除',     'batch.destroy', 2, '', '', 1, NOW(), NOW()),
(21000000000000012, 21000000000000002, '批量启用/禁用', 'batch.status', 2, '', '', 2, NOW(), NOW()),
(21000000000000013, 21000000000000002, '导入用户',     'import.users', 2, '', '', 3, NOW(), NOW()),
(21000000000000014, 21000000000000002, '导出Excel',     'export.excel', 2, '', '', 4, NOW(), NOW()),
(21000000000000015, 21000000000000002, '导出PDF',       'export.pdf', 2, '', '', 5, NOW(), NOW()),
(21000000000000016, 21000000000000002, '文件上传',     'upload', 2, '', '', 6, NOW(), NOW());

-- ============================================================
-- 管理系统API权限 (type=3)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000021, 21000000000000001, '查看仪表盘',   'get.admin/dashboard', 3, '', '', 1, NOW(), NOW()),
(21000000000000031, 21000000000000002, '查看用户',     'get.admin/user', 3, '', '', 1, NOW(), NOW()),
(21000000000000032, 21000000000000002, '创建用户',     'post.admin/user', 3, '', '', 2, NOW(), NOW()),
(21000000000000033, 21000000000000002, '更新用户',     'put.admin/user', 3, '', '', 3, NOW(), NOW()),
(21000000000000034, 21000000000000002, '删除用户',     'delete.admin/user', 3, '', '', 4, NOW(), NOW()),
(21000000000000035, 21000000000000002, '批量删除用户', 'post.admin/user/batch/destroy', 3, '', '', 5, NOW(), NOW()),
(21000000000000036, 21000000000000002, '批量启禁用',   'post.admin/user/batch/status', 3, '', '', 6, NOW(), NOW()),
(21000000000000041, 21000000000000003, '查看角色', 'get.admin/role', 3, '', '', 1, NOW(), NOW()),
(21000000000000042, 21000000000000003, '创建角色', 'post.admin/role', 3, '', '', 2, NOW(), NOW()),
(21000000000000043, 21000000000000003, '更新角色', 'put.admin/role', 3, '', '', 3, NOW(), NOW()),
(21000000000000044, 21000000000000003, '删除角色', 'delete.admin/role', 3, '', '', 4, NOW(), NOW()),
(21000000000000051, 21000000000000004, '查看权限', 'get.admin/permission', 3, '', '', 1, NOW(), NOW()),
(21000000000000052, 21000000000000004, '创建权限', 'post.admin/permission', 3, '', '', 2, NOW(), NOW()),
(21000000000000053, 21000000000000004, '更新权限', 'put.admin/permission', 3, '', '', 3, NOW(), NOW()),
(21000000000000054, 21000000000000004, '删除权限', 'delete.admin/permission', 3, '', '', 4, NOW(), NOW()),
(21000000000000061, 21000000000000005, '查看配置', 'get.admin/config', 3, '', '', 1, NOW(), NOW()),
(21000000000000062, 21000000000000005, '创建配置', 'post.admin/config', 3, '', '', 2, NOW(), NOW()),
(21000000000000063, 21000000000000005, '更新配置', 'put.admin/config', 3, '', '', 3, NOW(), NOW()),
(21000000000000064, 21000000000000005, '删除配置', 'delete.admin/config', 3, '', '', 4, NOW(), NOW()),
(21000000000000071, 21000000000000006, '查看日志', 'get.admin/log', 3, '', '', 1, NOW(), NOW()),
(21000000000000081, 0, '个人中心-更新信息', 'put.admin/profile', 3, '', '', 1, NOW(), NOW()),
(21000000000000082, 0, '个人中心-修改密码', 'put.admin/profile/password', 3, '', '', 2, NOW(), NOW()),
(21000000000000083, 0, '个人中心-登出',     'post.admin/profile/logout', 3, '', '', 3, NOW(), NOW()),
(21000000000000091, 0, '导出Excel', 'post.admin/export/excel', 3, '', '', 1, NOW(), NOW()),
(21000000000000092, 0, '导出PDF',   'post.admin/export/pdf', 3, '', '', 2, NOW(), NOW()),
(21000000000000093, 0, '导入用户', 'post.admin/import/users', 3, '', '', 1, NOW(), NOW()),
(21000000000000094, 0, '文件上传', 'post.admin/upload', 3, '', '', 1, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — 商品
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000011, 31000000000000001, '商品-查看',   'get.admin/product', 3, '', '', 1, NOW(), NOW()),
(31000000000000012, 31000000000000001, '商品-创建',   'post.admin/product', 3, '', '', 2, NOW(), NOW()),
(31000000000000013, 31000000000000001, '商品-更新',   'put.admin/product', 3, '', '', 3, NOW(), NOW()),
(31000000000000014, 31000000000000001, '商品-删除',   'delete.admin/product', 3, '', '', 4, NOW(), NOW()),
(31000000000000021, 31000000000000001, '分类-查看',   'get.admin/category', 3, '', '', 5, NOW(), NOW()),
(31000000000000022, 31000000000000001, '分类-创建',   'post.admin/category', 3, '', '', 6, NOW(), NOW()),
(31000000000000023, 31000000000000001, '分类-更新',   'put.admin/category', 3, '', '', 7, NOW(), NOW()),
(31000000000000024, 31000000000000001, '分类-删除',   'delete.admin/category', 3, '', '', 8, NOW(), NOW()),
(31000000000000031, 31000000000000001, '品牌-查看',   'get.admin/brand', 3, '', '',  9, NOW(), NOW()),
(31000000000000032, 31000000000000001, '品牌-创建',   'post.admin/brand', 3, '', '', 10, NOW(), NOW()),
(31000000000000033, 31000000000000001, '品牌-更新',   'put.admin/brand', 3, '', '', 11, NOW(), NOW()),
(31000000000000034, 31000000000000001, '品牌-删除',   'delete.admin/brand', 3, '', '', 12, NOW(), NOW()),
(31000000000000041, 31000000000000001, '仓库-查看',   'get.admin/warehouse', 3, '', '', 13, NOW(), NOW()),
(31000000000000042, 31000000000000001, '仓库-创建',   'post.admin/warehouse', 3, '', '', 14, NOW(), NOW()),
(31000000000000043, 31000000000000001, '仓库-更新',   'put.admin/warehouse', 3, '', '', 15, NOW(), NOW()),
(31000000000000044, 31000000000000001, '仓库-删除',   'delete.admin/warehouse', 3, '', '', 16, NOW(), NOW()),
(31000000000000045, 31000000000000001, '库位-查看(按仓库)', 'get.admin/warehouse/locations', 3, '', '', 17, NOW(), NOW()),
(31000000000000051, 31000000000000001, '库位-查看',   'get.admin/location', 3, '', '', 18, NOW(), NOW()),
(31000000000000052, 31000000000000001, '库位-创建',   'post.admin/location', 3, '', '', 19, NOW(), NOW()),
(31000000000000053, 31000000000000001, '库位-更新',   'put.admin/location', 3, '', '', 20, NOW(), NOW()),
(31000000000000054, 31000000000000001, '库位-删除',   'delete.admin/location', 3, '', '', 21, NOW(), NOW()),
(31000000000000061, 31000000000000001, '供应商-查看', 'get.admin/supplier', 3, '', '', 22, NOW(), NOW()),
(31000000000000062, 31000000000000001, '供应商-创建', 'post.admin/supplier', 3, '', '', 23, NOW(), NOW()),
(31000000000000063, 31000000000000001, '供应商-更新', 'put.admin/supplier', 3, '', '', 24, NOW(), NOW()),
(31000000000000064, 31000000000000001, '供应商-删除', 'delete.admin/supplier', 3, '', '', 25, NOW(), NOW()),
(31000000000000071, 31000000000000001, '客户-查看',   'get.admin/customer', 3, '', '', 26, NOW(), NOW()),
(31000000000000072, 31000000000000001, '客户-创建',   'post.admin/customer', 3, '', '', 27, NOW(), NOW()),
(31000000000000073, 31000000000000001, '客户-更新',   'put.admin/customer', 3, '', '', 28, NOW(), NOW()),
(31000000000000074, 31000000000000001, '客户-删除',   'delete.admin/customer', 3, '', '', 29, NOW(), NOW()),
(31000000000000075, 31000000000000001, '客户等级',    'any.admin/customer-level', 3, '', '', 30, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — 采购
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000081, 31000000000000002, '采购申请-查看', 'get.admin/purchase/apply', 3, '', '', 1, NOW(), NOW()),
(31000000000000082, 31000000000000002, '采购申请-创建', 'post.admin/purchase/apply', 3, '', '', 2, NOW(), NOW()),
(31000000000000083, 31000000000000002, '采购申请-更新', 'put.admin/purchase/apply', 3, '', '', 3, NOW(), NOW()),
(31000000000000084, 31000000000000002, '采购申请-删除', 'delete.admin/purchase/apply', 3, '', '', 4, NOW(), NOW()),
(31000000000000091, 31000000000000002, '采购订单-查看', 'get.admin/purchase/order', 3, '', '', 5, NOW(), NOW()),
(31000000000000092, 31000000000000002, '采购订单-创建', 'post.admin/purchase/order', 3, '', '', 6, NOW(), NOW()),
(31000000000000093, 31000000000000002, '采购订单-更新', 'put.admin/purchase/order', 3, '', '', 7, NOW(), NOW()),
(31000000000000094, 31000000000000002, '采购订单-删除', 'delete.admin/purchase/order', 3, '', '', 8, NOW(), NOW()),
(31000000000000101, 31000000000000002, '采购收货-查看', 'get.admin/purchase/receive', 3, '', '',  9, NOW(), NOW()),
(31000000000000102, 31000000000000002, '采购收货-创建', 'post.admin/purchase/receive', 3, '', '', 10, NOW(), NOW()),
(31000000000000103, 31000000000000002, '采购收货-更新', 'put.admin/purchase/receive', 3, '', '', 11, NOW(), NOW()),
(31000000000000104, 31000000000000002, '采购收货-删除', 'delete.admin/purchase/receive', 3, '', '', 12, NOW(), NOW()),
(31000000000000111, 31000000000000002, '采购退货-查看', 'get.admin/purchase/return', 3, '', '', 13, NOW(), NOW()),
(31000000000000112, 31000000000000002, '采购退货-创建', 'post.admin/purchase/return', 3, '', '', 14, NOW(), NOW()),
(31000000000000113, 31000000000000002, '采购退货-更新', 'put.admin/purchase/return', 3, '', '', 15, NOW(), NOW()),
(31000000000000114, 31000000000000002, '采购退货-删除', 'delete.admin/purchase/return', 3, '', '', 16, NOW(), NOW()),
(31000000000000121, 31000000000000002, '采购结算', 'any.admin/purchase/settlement', 3, '', '', 17, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — 销售
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000131, 31000000000000003, '销售报价-查看', 'get.admin/sales/quotation', 3, '', '', 1, NOW(), NOW()),
(31000000000000132, 31000000000000003, '销售报价-创建', 'post.admin/sales/quotation', 3, '', '', 2, NOW(), NOW()),
(31000000000000133, 31000000000000003, '销售报价-更新', 'put.admin/sales/quotation', 3, '', '', 3, NOW(), NOW()),
(31000000000000134, 31000000000000003, '销售报价-删除', 'delete.admin/sales/quotation', 3, '', '', 4, NOW(), NOW()),
(31000000000000141, 31000000000000003, '销售订单-查看', 'get.admin/sales/order', 3, '', '', 5, NOW(), NOW()),
(31000000000000142, 31000000000000003, '销售订单-创建', 'post.admin/sales/order', 3, '', '', 6, NOW(), NOW()),
(31000000000000143, 31000000000000003, '销售订单-更新', 'put.admin/sales/order', 3, '', '', 7, NOW(), NOW()),
(31000000000000144, 31000000000000003, '销售订单-删除', 'delete.admin/sales/order', 3, '', '', 8, NOW(), NOW()),
(31000000000000151, 31000000000000003, '销售发货-查看', 'get.admin/sales/delivery', 3, '', '',  9, NOW(), NOW()),
(31000000000000152, 31000000000000003, '销售发货-创建', 'post.admin/sales/delivery', 3, '', '', 10, NOW(), NOW()),
(31000000000000153, 31000000000000003, '销售发货-更新', 'put.admin/sales/delivery', 3, '', '', 11, NOW(), NOW()),
(31000000000000154, 31000000000000003, '销售发货-删除', 'delete.admin/sales/delivery', 3, '', '', 12, NOW(), NOW()),
(31000000000000161, 31000000000000003, '销售退货-查看', 'get.admin/sales/return', 3, '', '', 13, NOW(), NOW()),
(31000000000000162, 31000000000000003, '销售退货-创建', 'post.admin/sales/return', 3, '', '', 14, NOW(), NOW()),
(31000000000000163, 31000000000000003, '销售退货-更新', 'put.admin/sales/return', 3, '', '', 15, NOW(), NOW()),
(31000000000000164, 31000000000000003, '销售退货-删除', 'delete.admin/sales/return', 3, '', '', 16, NOW(), NOW()),
(31000000000000171, 31000000000000003, '销售结算', 'any.admin/sales/settlement', 3, '', '', 17, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — 库存
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000181, 31000000000000004, '库存总览',       'any.admin/inventory', 3, '', '', 1, NOW(), NOW()),
(31000000000000182, 31000000000000004, '库存流水',       'any.admin/inventory/flow', 3, '', '', 2, NOW(), NOW()),
(31000000000000183, 31000000000000004, '调拨-查看',      'get.admin/inventory/transfer', 3, '', '', 3, NOW(), NOW()),
(31000000000000184, 31000000000000004, '调拨-创建',      'post.admin/inventory/transfer', 3, '', '', 4, NOW(), NOW()),
(31000000000000185, 31000000000000004, '调拨-更新',      'put.admin/inventory/transfer', 3, '', '', 5, NOW(), NOW()),
(31000000000000186, 31000000000000004, '调拨-删除',      'delete.admin/inventory/transfer', 3, '', '', 6, NOW(), NOW()),
(31000000000000187, 31000000000000004, '盘点-查看',      'get.admin/inventory/check', 3, '', '', 7, NOW(), NOW()),
(31000000000000188, 31000000000000004, '盘点-创建',      'post.admin/inventory/check', 3, '', '', 8, NOW(), NOW()),
(31000000000000189, 31000000000000004, '盘点-更新',      'put.admin/inventory/check', 3, '', '', 9, NOW(), NOW()),
(31000000000000190, 31000000000000004, '盘点-删除',      'delete.admin/inventory/check', 3, '', '', 10, NOW(), NOW()),
(31000000000000191, 31000000000000004, '预警-查看',      'get.admin/inventory/alert', 3, '', '', 11, NOW(), NOW()),
(31000000000000192, 31000000000000004, '预警-创建',      'post.admin/inventory/alert', 3, '', '', 12, NOW(), NOW()),
(31000000000000193, 31000000000000004, '预警-更新',      'put.admin/inventory/alert', 3, '', '', 13, NOW(), NOW()),
(31000000000000194, 31000000000000004, '预警-删除',      'delete.admin/inventory/alert', 3, '', '', 14, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — 财务
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000201, 31000000000000005, '账户-查看',      'get.admin/finance/account', 3, '', '', 1, NOW(), NOW()),
(31000000000000202, 31000000000000005, '账户-创建',      'post.admin/finance/account', 3, '', '', 2, NOW(), NOW()),
(31000000000000203, 31000000000000005, '账户-更新',      'put.admin/finance/account', 3, '', '', 3, NOW(), NOW()),
(31000000000000204, 31000000000000005, '账户-删除',      'delete.admin/finance/account', 3, '', '', 4, NOW(), NOW()),
(31000000000000211, 31000000000000005, '凭证-查看',      'get.admin/finance/voucher', 3, '', '', 5, NOW(), NOW()),
(31000000000000212, 31000000000000005, '凭证-创建',      'post.admin/finance/voucher', 3, '', '', 6, NOW(), NOW()),
(31000000000000213, 31000000000000005, '凭证-更新',      'put.admin/finance/voucher', 3, '', '', 7, NOW(), NOW()),
(31000000000000214, 31000000000000005, '凭证-删除',      'delete.admin/finance/voucher', 3, '', '', 8, NOW(), NOW()),
(31000000000000221, 31000000000000005, '收款-查看',      'get.admin/finance/receipt', 3, '', '',  9, NOW(), NOW()),
(31000000000000222, 31000000000000005, '收款-创建',      'post.admin/finance/receipt', 3, '', '', 10, NOW(), NOW()),
(31000000000000223, 31000000000000005, '收款-更新',      'put.admin/finance/receipt', 3, '', '', 11, NOW(), NOW()),
(31000000000000224, 31000000000000005, '收款-删除',      'delete.admin/finance/receipt', 3, '', '', 12, NOW(), NOW()),
(31000000000000231, 31000000000000005, '付款-查看',      'get.admin/finance/payment', 3, '', '', 13, NOW(), NOW()),
(31000000000000232, 31000000000000005, '付款-创建',      'post.admin/finance/payment', 3, '', '', 14, NOW(), NOW()),
(31000000000000233, 31000000000000005, '付款-更新',      'put.admin/finance/payment', 3, '', '', 15, NOW(), NOW()),
(31000000000000234, 31000000000000005, '付款-删除',      'delete.admin/finance/payment', 3, '', '', 16, NOW(), NOW()),
(31000000000000241, 31000000000000005, '现金日记账',     'any.admin/finance/cash-journal', 3, '', '', 17, NOW(), NOW()),
(31000000000000251, 31000000000000005, '费用-查看',      'get.admin/finance/expense', 3, '', '', 18, NOW(), NOW()),
(31000000000000252, 31000000000000005, '费用-创建',      'post.admin/finance/expense', 3, '', '', 19, NOW(), NOW()),
(31000000000000253, 31000000000000005, '费用-更新',      'put.admin/finance/expense', 3, '', '', 20, NOW(), NOW()),
(31000000000000254, 31000000000000005, '费用-删除',      'delete.admin/finance/expense', 3, '', '', 21, NOW(), NOW()),
(31000000000000261, 31000000000000005, '利润报表',       'any.admin/finance/report/profit', 3, '', '', 22, NOW(), NOW()),
(31000000000000271, 31000000000000005, '银行账户-查看',  'get.admin/finance/bank-account', 3, '', '', 23, NOW(), NOW()),
(31000000000000272, 31000000000000005, '银行账户-创建',  'post.admin/finance/bank-account', 3, '', '', 24, NOW(), NOW()),
(31000000000000273, 31000000000000005, '银行账户-更新',  'put.admin/finance/bank-account', 3, '', '', 25, NOW(), NOW()),
(31000000000000274, 31000000000000005, '银行账户-删除',  'delete.admin/finance/bank-account', 3, '', '', 26, NOW(), NOW());

-- ============================================================
-- ERP模块API权限 (type=3) — CRM
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000281, 31000000000000006, '商机-查看',      'get.admin/crm/opportunity', 3, '', '', 1, NOW(), NOW()),
(31000000000000282, 31000000000000006, '商机-创建',      'post.admin/crm/opportunity', 3, '', '', 2, NOW(), NOW()),
(31000000000000283, 31000000000000006, '商机-更新',      'put.admin/crm/opportunity', 3, '', '', 3, NOW(), NOW()),
(31000000000000284, 31000000000000006, '商机-删除',      'delete.admin/crm/opportunity', 3, '', '', 4, NOW(), NOW()),
(31000000000000291, 31000000000000006, '跟进记录-查看',  'get.admin/crm/follow', 3, '', '', 5, NOW(), NOW()),
(31000000000000292, 31000000000000006, '跟进记录-创建',  'post.admin/crm/follow', 3, '', '', 6, NOW(), NOW()),
(31000000000000293, 31000000000000006, '跟进记录-更新',  'put.admin/crm/follow', 3, '', '', 7, NOW(), NOW()),
(31000000000000294, 31000000000000006, '跟进记录-删除',  'delete.admin/crm/follow', 3, '', '', 8, NOW(), NOW()),
(31000000000000301, 31000000000000006, '销售漏斗-查看',  'get.admin/crm/funnel', 3, '', '',  9, NOW(), NOW()),
(31000000000000302, 31000000000000006, '销售漏斗-创建',  'post.admin/crm/funnel', 3, '', '', 10, NOW(), NOW()),
(31000000000000303, 31000000000000006, '销售漏斗-更新',  'put.admin/crm/funnel', 3, '', '', 11, NOW(), NOW()),
(31000000000000304, 31000000000000006, '销售漏斗-删除',  'delete.admin/crm/funnel', 3, '', '', 12, NOW(), NOW()),
(31000000000000311, 31000000000000006, '联系人-查看',    'get.admin/crm/contact', 3, '', '', 13, NOW(), NOW()),
(31000000000000312, 31000000000000006, '联系人-创建',    'post.admin/crm/contact', 3, '', '', 14, NOW(), NOW()),
(31000000000000313, 31000000000000006, '联系人-更新',    'put.admin/crm/contact', 3, '', '', 15, NOW(), NOW()),
(31000000000000314, 31000000000000006, '联系人-删除',    'delete.admin/crm/contact', 3, '', '', 16, NOW(), NOW());

-- ============================================================
-- 仪表盘扩展API权限
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000321, 21000000000000001, '销售仪表盘',   'any.admin/dashboard/sales', 3, '', '', 2, NOW(), NOW()),
(31000000000000322, 21000000000000001, '库存仪表盘',   'any.admin/dashboard/inventory', 3, '', '', 3, NOW(), NOW()),
(31000000000000323, 21000000000000001, '财务仪表盘',   'any.admin/dashboard/finance', 3, '', '', 4, NOW(), NOW());

-- ============================================================
-- 超级管理员角色关联所有权限
-- ============================================================
INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erp_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erp_admin_role_permission` WHERE `role_id` = 10000000000000001
);

-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: OMS订单管理系统表（8张表）
-- 包含: OMS订单扩展/订单地址/履约记录/履约明细/RMA/RMA明细/库存预占/销售渠道
-- ============================================================

-- ============================================================
-- OMS订单扩展（关联 erp_sales_order）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 erp_sales_order.id',
    `channel` VARCHAR(30) NOT NULL DEFAULT 'manual' COMMENT '渠道: manual/web/mobile/api/marketplace/edi/pos',
    `channel_order_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '渠道订单号',
    `channel_store` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '渠道店铺名称',
    `fulfillment_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '履约状态: 0=未分配 1=已分配 2=拣货中 3=已打包 4=已发货 5=已签收',
    `payment_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '支付状态: 0=待支付 1=已支付 2=部分退款 3=已退款',
    `shipping_method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '配送方式',
    `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '运费',
    `buyer_message` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '买家备注',
    `seller_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '卖家备注',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '优先级: 1=最高 5=正常 9=最低',
    `hold_until` DATETIME DEFAULT NULL COMMENT '冻结到指定时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_id` (`order_id`),
    KEY `idx_channel` (`channel`),
    KEY `idx_fulfillment_status` (`fulfillment_status`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS订单扩展';

-- ============================================================
-- OMS订单地址（收货/账单地址，支持多国格式）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_order_address` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT 'OMS订单ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=收货地址 2=账单地址',
    `contact_name` VARCHAR(100) NOT NULL COMMENT '联系人',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电话（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `country` VARCHAR(50) NOT NULL DEFAULT 'CN' COMMENT '国家ISO代码',
    `state` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '省/州',
    `city` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '城市',
    `district` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '区/县',
    `address_line1` VARCHAR(300) NOT NULL DEFAULT '' COMMENT '地址行1',
    `address_line2` VARCHAR(300) NOT NULL DEFAULT '' COMMENT '地址行2',
    `postal_code` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '邮编',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id_type` (`order_id`, `type`),
    KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS订单地址';

-- ============================================================
-- OMS履约记录（关联WMS任务 + TMS运单）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_fulfillment` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `oms_order_id` BIGINT UNSIGNED NOT NULL COMMENT 'OMS订单ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '发货仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=分配中 2=拣货中 3=打包中 4=待发货 5=已发货 6=已取消',
    `pick_task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'WMS拣货任务ID',
    `pack_task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'WMS打包任务ID',
    `shipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TMS运单ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_oms_order_id` (`oms_order_id`),
    KEY `idx_status` (`status`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS履约记录';

-- ============================================================
-- OMS履约明细（行项级别的履约进度追踪）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_fulfillment_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `fulfillment_id` BIGINT UNSIGNED NOT NULL COMMENT '履约记录ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 SalesOrderItem.id',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `allocated_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已分配数量',
    `picked_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已拣数量',
    `packed_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已打包数量',
    `shipped_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已发数量',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_fulfillment_id` (`fulfillment_id`),
    KEY `idx_order_item_id` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS履约明细';

-- ============================================================
-- OMS退换货授权(RMA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_rma` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT 'RMA单号',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '原订单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=退货 2=换货 3=维修',
    `reason` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '退货原因',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已批准 2=已退回 3=已收货 4=已退款 5=已拒绝',
    `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退款金额',
    `return_shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货运费',
    `return_shipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TMS退货运单ID',
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `approved_at` DATETIME DEFAULT NULL COMMENT '审批时间',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退回时间',
    `received_at` DATETIME DEFAULT NULL COMMENT '收货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS退换货';

-- ============================================================
-- OMS退换货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_rma_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rma_id` BIGINT UNSIGNED NOT NULL COMMENT 'RMA ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL COMMENT '订单明细ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退款单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退款金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_rma_id` (`rma_id`),
    KEY `idx_order_item_id` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS退换货明细';

-- ============================================================
-- OMS库存预占（逻辑锁层，不改动物理库存）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_oms_inventory_reservation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `source_type` VARCHAR(30) NOT NULL COMMENT '来源类型: oms_order',
    `source_id` BIGINT UNSIGNED NOT NULL COMMENT '来源ID（OMS订单ID）',
    `source_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源明细ID',
    `reserved_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预占数量',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=已预留 2=已释放 3=已消耗',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_source` (`source_type`, `source_id`),
    KEY `idx_inventory` (`product_id`, `sku_id`, `warehouse_id`, `location_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OMS库存预占';

-- ============================================================
-- 销售渠道定义
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_channel` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(30) NOT NULL COMMENT '渠道编码',
    `name` VARCHAR(100) NOT NULL COMMENT '渠道名称',
    `type` VARCHAR(20) NOT NULL DEFAULT 'direct' COMMENT '类型: direct/marketplace/edi/pos',
    `config` JSON DEFAULT NULL COMMENT '渠道配置（JSON）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售渠道';
-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: WMS仓储管理系统表（12张表）
-- 包含: 库区/库位扩展/ASN/ASN明细/收货任务/上架任务/上架明细/拣货任务/拣货明细/打包任务/波次/波次订单关联
-- ============================================================

-- ============================================================
-- WMS库区
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_zone` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `code` VARCHAR(30) NOT NULL COMMENT '库区编码',
    `name` VARCHAR(100) NOT NULL COMMENT '库区名称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=收货区 2=存储区 3=拣货区 4=打包区 5=发货区 6=退货区 7=质检区',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warehouse_code` (`warehouse_id`, `code`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS库区';

-- ============================================================
-- WMS库位扩展（关联 erp_location，增加层级/容积/承重等WMS属性）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_location` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `location_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 erp_location.id',
    `zone_id` BIGINT UNSIGNED NOT NULL COMMENT '库区ID',
    `aisle` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '巷道',
    `rack` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '货架',
    `level` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '层',
    `bin` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '货位',
    `barcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '库位条码',
    `length_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '长(cm)',
    `width_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '宽(cm)',
    `height_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '高(cm)',
    `max_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最大承重(kg)',
    `max_volume_cm3` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最大容积(cm³)',
    `pick_sequence` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '拣货顺序',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=可用 0=禁用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_location_id` (`location_id`),
    KEY `idx_zone_id` (`zone_id`),
    KEY `idx_barcode` (`barcode`),
    KEY `idx_pick_sequence` (`pick_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS库位扩展';

-- ============================================================
-- WMS预到货通知(ASN)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_asn` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT 'ASN单号',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收货仓库ID',
    `purchase_order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采购订单ID',
    `expected_arrive_at` DATETIME DEFAULT NULL COMMENT '预计到货时间',
    `arrived_at` DATETIME DEFAULT NULL COMMENT '实际到货时间',
    `carrier` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '承运商',
    `tracking_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '物流单号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待收货 1=收货中 2=已收货 3=已上架',
    `total_packages` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总件数',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS预到货通知';

-- ============================================================
-- WMS预到货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_asn_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `asn_id` BIGINT UNSIGNED NOT NULL COMMENT 'ASN ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `expected_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预期数量',
    `received_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实收数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_asn_id` (`asn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS预到货明细';

-- ============================================================
-- WMS收货任务
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_receiving` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收货单号',
    `asn_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ASN ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `dock_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货月台库位ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待收货 1=收货中 2=已完成 3=已质检',
    `receiver_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货人ID',
    `received_at` DATETIME DEFAULT NULL COMMENT '收货完成时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_asn_id` (`asn_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS收货任务';

-- ============================================================
-- WMS上架任务
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_putaway_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '上架任务号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `receiving_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货任务ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待上架 1=上架中 2=已完成',
    `strategy` VARCHAR(30) NOT NULL DEFAULT 'fifo' COMMENT '上架策略: fifo/lifo/zone_fixed/abc',
    `assigned_to` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '指派人员ID',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS上架任务';

-- ============================================================
-- WMS上架明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_putaway_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `putaway_id` BIGINT UNSIGNED NOT NULL COMMENT '上架任务ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `from_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源库位（收货暂存区）',
    `to_location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '目标库位',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '上架数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_putaway_id` (`putaway_id`),
    KEY `idx_to_location_id` (`to_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS上架明细';

-- ============================================================
-- WMS拣货任务
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_pick_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '拣货任务号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `wave_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '波次ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=按单拣货 2=批量拣货 3=分区拣货 4=波次拣货',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待拣货 1=拣货中 2=已完成 3=已取消',
    `assigned_to` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '指派人员ID',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '优先级: 1=最高 5=正常',
    `started_at` DATETIME DEFAULT NULL COMMENT '开始时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_wave_id` (`wave_id`),
    KEY `idx_status` (`status`),
    KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS拣货任务';

-- ============================================================
-- WMS拣货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_pick_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `pick_task_id` BIGINT UNSIGNED NOT NULL COMMENT '拣货任务ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `location_id` BIGINT UNSIGNED NOT NULL COMMENT '拣货库位ID',
    `ordered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应拣数量',
    `picked_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实拣数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待拣 1=已拣',
    `picked_at` DATETIME DEFAULT NULL COMMENT '拣货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_pick_task_id` (`pick_task_id`),
    KEY `idx_location_id` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS拣货明细';

-- ============================================================
-- WMS打包任务
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_pack_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '打包任务号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待打包 1=打包中 2=已完成',
    `package_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '包装类型: box/bag/pallet/envelope',
    `weight_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT '重量(kg)',
    `length_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '长(cm)',
    `width_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '宽(cm)',
    `height_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '高(cm)',
    `assigned_to` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '指派人员ID',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS打包任务';

-- ============================================================
-- WMS波次（按波次聚合订单统一拣货）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_wave` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '波次号',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=拣货波次 2=发货波次',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=处理中 2=已完成',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '优先级: 1=最高 5=正常',
    `scheduled_at` DATETIME DEFAULT NULL COMMENT '计划时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS波次';

-- ============================================================
-- WMS波次-订单关联
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_wms_wave_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `wave_id` BIGINT UNSIGNED NOT NULL COMMENT '波次ID',
    `oms_order_id` BIGINT UNSIGNED NOT NULL COMMENT 'OMS订单ID',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wave_order` (`wave_id`, `oms_order_id`),
    KEY `idx_oms_order_id` (`oms_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS波次订单关联';
-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: TMS运输管理系统表（7张表）
-- 包含: 承运商/承运商服务/运费费率/运单/物流轨迹/包裹明细/运费发票
-- ============================================================

-- ============================================================
-- TMS承运商
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_carrier` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(30) NOT NULL COMMENT '承运商编码',
    `name` VARCHAR(100) NOT NULL COMMENT '承运商名称',
    `type` VARCHAR(30) NOT NULL DEFAULT 'express' COMMENT '类型: express/ltl/ftl/air/ocean/rail',
    `website` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '官网',
    `tracking_url_template` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '物流追踪URL模板',
    `api_provider` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'API供应商: custom/shippo/afterShip/17track',
    `api_config` JSON DEFAULT NULL COMMENT 'API配置（JSON）',
    `contact_phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '联系电话（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS承运商';

-- ============================================================
-- TMS承运商服务
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_carrier_service` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `carrier_id` BIGINT UNSIGNED NOT NULL COMMENT '承运商ID',
    `code` VARCHAR(50) NOT NULL COMMENT '服务编码',
    `name` VARCHAR(100) NOT NULL COMMENT '服务名称',
    `type` VARCHAR(30) NOT NULL DEFAULT 'standard' COMMENT '服务类型: standard/express/overnight/2day/economy',
    `estimated_days_min` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '预计最少天数',
    `estimated_days_max` INT UNSIGNED NOT NULL DEFAULT 3 COMMENT '预计最多天数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_carrier_service` (`carrier_id`, `code`),
    KEY `idx_carrier_id` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS承运商服务';

-- ============================================================
-- TMS运费费率卡
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_freight_rate` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `carrier_service_id` BIGINT UNSIGNED NOT NULL COMMENT '承运商服务ID',
    `origin_country` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '始发国ISO代码',
    `origin_zone` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '始发区域',
    `dest_country` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '目的国ISO代码',
    `dest_zone` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '目的区域',
    `weight_from_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT '重量起(kg)',
    `weight_to_kg` DECIMAL(10,3) NOT NULL DEFAULT 999.000 COMMENT '重量止(kg)',
    `base_rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '起步价',
    `per_kg_rate` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT '每公斤单价',
    `fuel_surcharge_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '燃油附加费率(%)',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY' COMMENT '币种',
    `valid_from` DATE NOT NULL COMMENT '生效日期',
    `valid_to` DATE DEFAULT NULL COMMENT '失效日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_carrier_service_id` (`carrier_service_id`),
    KEY `idx_dest` (`dest_country`, `dest_zone`),
    KEY `idx_valid` (`valid_from`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS运费费率';

-- ============================================================
-- TMS运单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_shipment` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '运单号（内部）',
    `carrier_service_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '承运商服务ID',
    `tracking_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '物流追踪号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待发货 1=已取件 2=运输中 3=已送达 4=异常 5=已退回',
    `shipping_label_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '面单URL',
    `estimated_delivery_at` DATETIME DEFAULT NULL COMMENT '预计送达时间',
    `actual_delivery_at` DATETIME DEFAULT NULL COMMENT '实际签收时间',
    `origin_address_snapshot` JSON DEFAULT NULL COMMENT '发件地址快照',
    `dest_address_snapshot` JSON DEFAULT NULL COMMENT '收件地址快照',
    `total_weight_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT '总重量(kg)',
    `total_volume_cm3` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '总体积(cm³)',
    `package_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '包裹数量',
    `freight_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '运费',
    `insurance_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '保价费',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY' COMMENT '币种',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_tracking_no` (`tracking_no`),
    KEY `idx_carrier_service_id` (`carrier_service_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS运单';

-- ============================================================
-- TMS物流轨迹
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_tracking_event` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `shipment_id` BIGINT UNSIGNED NOT NULL COMMENT '运单ID',
    `status_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '状态码: picked_up/in_transit/out_for_delivery/delivered/exception',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '事件描述',
    `location` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '发生地点',
    `event_time` DATETIME DEFAULT NULL COMMENT '事件时间',
    `raw_data` JSON DEFAULT NULL COMMENT '原始数据',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_shipment_id` (`shipment_id`),
    KEY `idx_event_time` (`event_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS物流轨迹';

-- ============================================================
-- TMS包裹明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_shipment_package` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `shipment_id` BIGINT UNSIGNED NOT NULL COMMENT '运单ID',
    `pack_task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联WMS打包任务ID',
    `package_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '包裹编号',
    `weight_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT '重量(kg)',
    `length_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '长(cm)',
    `width_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '宽(cm)',
    `height_cm` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '高(cm)',
    `declared_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '申报价值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS包裹明细';

-- ============================================================
-- TMS运费发票
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_tms_freight_invoice` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '运费发票号',
    `carrier_id` BIGINT UNSIGNED NOT NULL COMMENT '承运商ID',
    `shipment_id` BIGINT UNSIGNED NOT NULL COMMENT '运单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY' COMMENT '币种',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已确认 2=已付款',
    `invoice_date` DATE DEFAULT NULL COMMENT '发票日期',
    `due_date` DATE DEFAULT NULL COMMENT '到期日',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_carrier_id` (`carrier_id`),
    KEY `idx_shipment_id` (`shipment_id`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TMS运费发票';
-- ============================================================
-- OMS/WMS/TMS 模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 OMS/WMS/TMS 模块的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — OMS / WMS / TMS
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000208, 0, '订单管理(OMS)', 'oms',   1, 'receipt_long',  '/admin/oms',  13, NOW(), NOW()),
(31000000000000209, 0, '仓储管理(WMS)', 'wms',   1, 'warehouse',     '/admin/wms',  14, NOW(), NOW()),
(31000000000000210, 0, '运输管理(TMS)', 'tms',   1, 'local_shipping','/admin/tms',  15, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — OMS 订单管理
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000341, 31000000000000208, 'OMS订单-查看',   'get.admin/oms/order', 3, '', '', 1, NOW(), NOW()),
(31000000000000342, 31000000000000208, 'OMS订单-创建',   'post.admin/oms/order', 3, '', '', 2, NOW(), NOW()),
(31000000000000343, 31000000000000208, 'OMS订单-更新',   'put.admin/oms/order', 3, '', '', 3, NOW(), NOW()),
(31000000000000344, 31000000000000208, 'OMS订单-删除',   'delete.admin/oms/order', 3, '', '', 4, NOW(), NOW()),
(31000000000000345, 31000000000000208, 'OMS订单-分配',   'post.admin/oms/order/allocate', 3, '', '', 5, NOW(), NOW()),
(31000000000000346, 31000000000000208, 'OMS订单-履约',   'post.admin/oms/order/fulfill', 3, '', '', 6, NOW(), NOW()),
(31000000000000347, 31000000000000208, 'OMS订单-取消',   'post.admin/oms/order/cancel', 3, '', '', 7, NOW(), NOW());

-- OMS 履约管理
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000351, 31000000000000208, '履约-查看',   'get.admin/oms/fulfillment', 3, '', '', 10, NOW(), NOW()),
(31000000000000352, 31000000000000208, '履约-创建',   'post.admin/oms/fulfillment', 3, '', '', 11, NOW(), NOW());

-- OMS RMA
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000361, 31000000000000208, 'RMA-查看',   'get.admin/oms/rma', 3, '', '', 15, NOW(), NOW()),
(31000000000000362, 31000000000000208, 'RMA-创建',   'post.admin/oms/rma', 3, '', '', 16, NOW(), NOW()),
(31000000000000363, 31000000000000208, 'RMA-更新',   'put.admin/oms/rma', 3, '', '', 17, NOW(), NOW()),
(31000000000000364, 31000000000000208, 'RMA-删除',   'delete.admin/oms/rma', 3, '', '', 18, NOW(), NOW()),
(31000000000000365, 31000000000000208, 'RMA-审批',   'post.admin/oms/rma/approve', 3, '', '', 19, NOW(), NOW()),
(31000000000000366, 31000000000000208, 'RMA-收货',   'post.admin/oms/rma/receive', 3, '', '', 20, NOW(), NOW()),
(31000000000000367, 31000000000000208, 'RMA-退款',   'post.admin/oms/rma/refund', 3, '', '', 21, NOW(), NOW());

-- OMS 渠道管理
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000371, 31000000000000208, '渠道-查看',   'get.admin/oms/channel', 3, '', '', 25, NOW(), NOW()),
(31000000000000372, 31000000000000208, '渠道-创建',   'post.admin/oms/channel', 3, '', '', 26, NOW(), NOW()),
(31000000000000373, 31000000000000208, '渠道-更新',   'put.admin/oms/channel', 3, '', '', 27, NOW(), NOW()),
(31000000000000374, 31000000000000208, '渠道-删除',   'delete.admin/oms/channel', 3, '', '', 28, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — WMS 仓储管理
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000401, 31000000000000209, '库区-查看',   'get.admin/wms/zone', 3, '', '', 1, NOW(), NOW()),
(31000000000000402, 31000000000000209, '库区-创建',   'post.admin/wms/zone', 3, '', '', 2, NOW(), NOW()),
(31000000000000403, 31000000000000209, '库区-更新',   'put.admin/wms/zone', 3, '', '', 3, NOW(), NOW()),
(31000000000000404, 31000000000000209, '库区-删除',   'delete.admin/wms/zone', 3, '', '', 4, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000411, 31000000000000209, 'WMS库位-查看',   'get.admin/wms/location', 3, '', '', 8, NOW(), NOW()),
(31000000000000412, 31000000000000209, 'WMS库位-创建',   'post.admin/wms/location', 3, '', '', 9, NOW(), NOW()),
(31000000000000413, 31000000000000209, 'WMS库位-更新',   'put.admin/wms/location', 3, '', '', 10, NOW(), NOW()),
(31000000000000414, 31000000000000209, 'WMS库位-删除',   'delete.admin/wms/location', 3, '', '', 11, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000421, 31000000000000209, 'ASN-查看',   'get.admin/wms/asn', 3, '', '', 15, NOW(), NOW()),
(31000000000000422, 31000000000000209, 'ASN-创建',   'post.admin/wms/asn', 3, '', '', 16, NOW(), NOW()),
(31000000000000423, 31000000000000209, 'ASN-更新',   'put.admin/wms/asn', 3, '', '', 17, NOW(), NOW()),
(31000000000000424, 31000000000000209, 'ASN-删除',   'delete.admin/wms/asn', 3, '', '', 18, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000431, 31000000000000209, '收货-查看',   'get.admin/wms/receiving', 3, '', '', 22, NOW(), NOW()),
(31000000000000432, 31000000000000209, '收货-创建',   'post.admin/wms/receiving', 3, '', '', 23, NOW(), NOW()),
(31000000000000433, 31000000000000209, '收货-完成',   'post.admin/wms/receiving/complete', 3, '', '', 24, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000441, 31000000000000209, '上架-查看',   'get.admin/wms/putaway', 3, '', '', 28, NOW(), NOW()),
(31000000000000442, 31000000000000209, '上架-创建',   'post.admin/wms/putaway', 3, '', '', 29, NOW(), NOW()),
(31000000000000443, 31000000000000209, '上架-完成',   'post.admin/wms/putaway/complete', 3, '', '', 30, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000451, 31000000000000209, '波次-查看',   'get.admin/wms/wave', 3, '', '', 35, NOW(), NOW()),
(31000000000000452, 31000000000000209, '波次-创建',   'post.admin/wms/wave', 3, '', '', 36, NOW(), NOW()),
(31000000000000453, 31000000000000209, '波次-释放',   'post.admin/wms/wave/release', 3, '', '', 37, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000461, 31000000000000209, '拣货-查看',   'get.admin/wms/pick', 3, '', '', 42, NOW(), NOW()),
(31000000000000462, 31000000000000209, '拣货-开始',   'post.admin/wms/pick/start', 3, '', '', 43, NOW(), NOW()),
(31000000000000463, 31000000000000209, '拣货-确认',   'post.admin/wms/pick/confirm', 3, '', '', 44, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000471, 31000000000000209, '打包-查看',   'get.admin/wms/pack', 3, '', '', 48, NOW(), NOW()),
(31000000000000472, 31000000000000209, '打包-开始',   'post.admin/wms/pack/start', 3, '', '', 49, NOW(), NOW()),
(31000000000000473, 31000000000000209, '打包-完成',   'post.admin/wms/pack/complete', 3, '', '', 50, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — TMS 运输管理
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000501, 31000000000000210, '承运商-查看',   'get.admin/tms/carrier', 3, '', '', 1, NOW(), NOW()),
(31000000000000502, 31000000000000210, '承运商-创建',   'post.admin/tms/carrier', 3, '', '', 2, NOW(), NOW()),
(31000000000000503, 31000000000000210, '承运商-更新',   'put.admin/tms/carrier', 3, '', '', 3, NOW(), NOW()),
(31000000000000504, 31000000000000210, '承运商-删除',   'delete.admin/tms/carrier', 3, '', '', 4, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000511, 31000000000000210, '承运商服务-查看',   'get.admin/tms/service', 3, '', '', 8, NOW(), NOW()),
(31000000000000512, 31000000000000210, '承运商服务-创建',   'post.admin/tms/service', 3, '', '', 9, NOW(), NOW()),
(31000000000000513, 31000000000000210, '承运商服务-更新',   'put.admin/tms/service', 3, '', '', 10, NOW(), NOW()),
(31000000000000514, 31000000000000210, '承运商服务-删除',   'delete.admin/tms/service', 3, '', '', 11, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000521, 31000000000000210, '运费费率-查看',   'get.admin/tms/freight-rate', 3, '', '', 15, NOW(), NOW()),
(31000000000000522, 31000000000000210, '运费费率-创建',   'post.admin/tms/freight-rate', 3, '', '', 16, NOW(), NOW()),
(31000000000000523, 31000000000000210, '运费费率-更新',   'put.admin/tms/freight-rate', 3, '', '', 17, NOW(), NOW()),
(31000000000000524, 31000000000000210, '运费费率-删除',   'delete.admin/tms/freight-rate', 3, '', '', 18, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000531, 31000000000000210, '运单-查看',   'get.admin/tms/shipment', 3, '', '', 22, NOW(), NOW()),
(31000000000000532, 31000000000000210, '运单-创建',   'post.admin/tms/shipment', 3, '', '', 23, NOW(), NOW()),
(31000000000000533, 31000000000000210, '运单-发货',   'post.admin/tms/shipment/ship', 3, '', '', 24, NOW(), NOW()),
(31000000000000534, 31000000000000210, '运单-面单',   'post.admin/tms/shipment/get-label',3, '', '', 25, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000541, 31000000000000210, '轨迹-查看',   'get.admin/tms/tracking', 3, '', '', 30, NOW(), NOW()),
(31000000000000542, 31000000000000210, '轨迹-回调',   'post.admin/tms/tracking/callback', 3, '', '', 31, NOW(), NOW());

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000551, 31000000000000210, '运费发票-查看',   'get.admin/tms/freight-invoice', 3, '', '', 35, NOW(), NOW()),
(31000000000000552, 31000000000000210, '运费发票-创建',   'post.admin/tms/freight-invoice', 3, '', '', 36, NOW(), NOW()),
(31000000000000553, 31000000000000210, '运费发票-确认',   'post.admin/tms/freight-invoice/confirm', 3, '', '', 37, NOW(), NOW()),
(31000000000000554, 31000000000000210, '运费发票-付款',   'post.admin/tms/freight-invoice/pay', 3, '', '', 38, NOW(), NOW());
-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: QMS质量管理系统表（5张表）
-- 包含: 检验标准/IQC来料检验/IPQC过程检验/OQC出货检验/不合格品
-- ============================================================

-- ============================================================
-- QMS检验标准
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_quality_inspection_standard` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(200) NOT NULL COMMENT '标准名称',
    `code` VARCHAR(50) NOT NULL COMMENT '标准编码',
    `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '适用商品ID',
    `type` VARCHAR(30) NOT NULL DEFAULT 'iqc' COMMENT '检验类型: iqc/ipqc/oqc',
    `specification` TEXT DEFAULT NULL COMMENT '检验规格说明',
    `sampling_plan` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '抽样方案',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QMS检验标准';

-- ============================================================
-- QMS来料检验记录 (IQC)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_quality_iqc_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '检验单号',
    `receiving_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联采购收货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品ID',
    `standard_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验标准ID',
    `inspected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验数量',
    `passed_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合格数量',
    `rejected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '不合格数量',
    `result` VARCHAR(20) NOT NULL DEFAULT 'pass' COMMENT '检验结果: pass=合格 reject=不合格',
    `inspector` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '检验员',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=已完成',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_receiving_id` (`receiving_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_standard_id` (`standard_id`),
    KEY `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QMS来料检验记录(IQC)';

-- ============================================================
-- QMS过程检验记录 (IPQC)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_quality_ipqc_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '检验单号',
    `production_order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生产工单ID',
    `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品ID',
    `workstation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '工作站ID',
    `standard_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验标准ID',
    `inspected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验数量',
    `passed_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合格数量',
    `rejected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '不合格数量',
    `result` VARCHAR(20) NOT NULL DEFAULT 'pass' COMMENT '检验结果: pass=合格 reject=不合格',
    `inspector` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '检验员',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=已完成',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_production_order_id` (`production_order_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_workstation_id` (`workstation_id`),
    KEY `idx_standard_id` (`standard_id`),
    KEY `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QMS过程检验记录(IPQC)';

-- ============================================================
-- QMS出货检验记录 (OQC)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_quality_oqc_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '检验单号',
    `delivery_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联销售发货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品ID',
    `standard_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验标准ID',
    `inspected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '检验数量',
    `passed_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合格数量',
    `rejected_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '不合格数量',
    `result` VARCHAR(20) NOT NULL DEFAULT 'pass' COMMENT '检验结果: pass=合格 reject=不合格',
    `inspector` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '检验员',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=已完成',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_standard_id` (`standard_id`),
    KEY `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QMS出货检验记录(OQC)';

-- ============================================================
-- QMS不合格品
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_quality_nonconformity` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '不合格品编号',
    `source_type` VARCHAR(30) NOT NULL DEFAULT 'iqc' COMMENT '来源类型: iqc/ipqc/oqc',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源记录ID（检验单ID）',
    `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品ID',
    `defect_type` VARCHAR(100) NOT NULL COMMENT '缺陷类型',
    `defect_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '缺陷数量',
    `severity` VARCHAR(20) NOT NULL DEFAULT 'minor' COMMENT '严重程度: minor/major/critical',
    `disposition` VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT '处置方式: pending/return/repair/scrap/accept',
    `root_cause` TEXT DEFAULT NULL COMMENT '根本原因',
    `corrective_action` TEXT DEFAULT NULL COMMENT '纠正措施',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理 1=处理中 2=已关闭',
    `reported_by` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '报告人',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_source_type` (`source_type`, `source_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_severity` (`severity`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QMS不合格品';
-- P3 Experience Enhancement Tables
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

CREATE TABLE IF NOT EXISTS `erp_bi_dashboard` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `layout` JSON DEFAULT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `erp_bi_widget` (
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

CREATE TABLE IF NOT EXISTS `erp_eam_equipment` (
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

CREATE TABLE IF NOT EXISTS `erp_eam_maintenance_plan` (
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

CREATE TABLE IF NOT EXISTS `erp_eam_repair_order` (
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

CREATE TABLE IF NOT EXISTS `erp_dms_document` (
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

CREATE TABLE IF NOT EXISTS `erp_dms_document_version` (
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
-- ============================================================
-- P3 体验增强模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 BI看板 / 设备管理(EAM) / 文档管理(DMS) 的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — BI看板 / 设备管理 / 文档管理
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000205, 0, 'BI看板', 'bi', 1, 'dashboard_customize', '/admin/bi', 17, NOW(), NOW()),
(31000000000000206, 0, '设备管理(EAM)', 'eam', 1, 'build', '/admin/eam', 18, NOW(), NOW()),
(31000000000000207, 0, '文档管理(DMS)', 'dms', 1, 'folder', '/admin/dms', 19, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — BI 看板
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000701, 31000000000000205, 'BI看板-查看',   'get.admin/bi/dashboard', 3, '', '', 1, NOW(), NOW()),
(31000000000000702, 31000000000000205, 'BI看板-创建',   'post.admin/bi/dashboard', 3, '', '', 2, NOW(), NOW()),
(31000000000000703, 31000000000000205, 'BI看板-更新',   'put.admin/bi/dashboard', 3, '', '', 3, NOW(), NOW()),
(31000000000000704, 31000000000000205, 'BI看板-删除',   'delete.admin/bi/dashboard', 3, '', '', 4, NOW(), NOW()),
(31000000000000705, 31000000000000205, '看板组件-查看', 'get.admin/bi/widget', 3, '', '', 5, NOW(), NOW()),
(31000000000000706, 31000000000000205, '看板组件-创建', 'post.admin/bi/widget', 3, '', '', 6, NOW(), NOW()),
(31000000000000707, 31000000000000205, '看板组件-更新', 'put.admin/bi/widget', 3, '', '', 7, NOW(), NOW()),
(31000000000000708, 31000000000000205, '看板组件-删除', 'delete.admin/bi/widget', 3, '', '', 8, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 设备管理 (EAM)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000711, 31000000000000206, '设备台账-查看',   'get.admin/eam/equipment', 3, '', '', 1, NOW(), NOW()),
(31000000000000712, 31000000000000206, '设备台账-创建',   'post.admin/eam/equipment', 3, '', '', 2, NOW(), NOW()),
(31000000000000713, 31000000000000206, '设备台账-更新',   'put.admin/eam/equipment', 3, '', '', 3, NOW(), NOW()),
(31000000000000714, 31000000000000206, '设备台账-删除',   'delete.admin/eam/equipment', 3, '', '', 4, NOW(), NOW()),
(31000000000000715, 31000000000000206, '保养计划-查看',   'get.admin/eam/maintenance', 3, '', '', 5, NOW(), NOW()),
(31000000000000716, 31000000000000206, '保养计划-创建',   'post.admin/eam/maintenance', 3, '', '', 6, NOW(), NOW()),
(31000000000000717, 31000000000000206, '保养计划-更新',   'put.admin/eam/maintenance', 3, '', '', 7, NOW(), NOW()),
(31000000000000718, 31000000000000206, '保养计划-删除',   'delete.admin/eam/maintenance', 3, '', '', 8, NOW(), NOW()),
(31000000000000719, 31000000000000206, '维修工单-查看',   'get.admin/eam/repair', 3, '', '', 9, NOW(), NOW()),
(31000000000000720, 31000000000000206, '维修工单-创建',   'post.admin/eam/repair', 3, '', '', 10, NOW(), NOW()),
(31000000000000721, 31000000000000206, '维修工单-更新',   'put.admin/eam/repair', 3, '', '', 11, NOW(), NOW()),
(31000000000000722, 31000000000000206, '维修工单-删除',   'delete.admin/eam/repair', 3, '', '', 12, NOW(), NOW()),
(31000000000000723, 31000000000000206, '维修工单-状态流转', 'post.admin/eam/repair/{id}/transition', 3, '', '', 13, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 文档管理 (DMS)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000731, 31000000000000207, '文档-查看',   'get.admin/dms/document', 3, '', '', 1, NOW(), NOW()),
(31000000000000732, 31000000000000207, '文档-创建',   'post.admin/dms/document', 3, '', '', 2, NOW(), NOW()),
(31000000000000733, 31000000000000207, '文档-更新',   'put.admin/dms/document', 3, '', '', 3, NOW(), NOW()),
(31000000000000734, 31000000000000207, '文档-删除',   'delete.admin/dms/document', 3, '', '', 4, NOW(), NOW()),
(31000000000000735, 31000000000000207, '文档分类-查看', 'get.admin/dms/categories', 3, '', '', 5, NOW(), NOW());

-- ============================================================
-- 超级管理员角色 (ID=10000000000000001) 关联所有新增权限
-- ============================================================
INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erp_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erp_admin_role_permission` WHERE `role_id` = 10000000000000001
);
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
CREATE TABLE IF NOT EXISTS `erp_eam_spare_part` (
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

CREATE TABLE IF NOT EXISTS `erp_eam_inspection_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键(雪花ID)',
    `equipment_id` BIGINT UNSIGNED NOT NULL COMMENT '设备ID',
    `source_plan_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源保养计划ID, 0=临时点检',
    `task_date` DATE NOT NULL COMMENT '点检日期',
    `assignee_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '负责人ID(用户ID), 0=未指定',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=待执行 1=已完成 2=异常待维修 3=已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_equipment_date` (`equipment_id`, `task_date`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备点检任务(E1)';

CREATE TABLE IF NOT EXISTS `erp_eam_inspection_result` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键(雪花ID)',
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '点检任务ID',
    `item_name` VARCHAR(100) NOT NULL COMMENT '点检项名称',
    `result` TINYINT NOT NULL DEFAULT 0 COMMENT '结果: 0=正常 1=异常',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '结果备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备点检结果明细(E1)';

-- ============================================================
-- QMS质量管理模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 QMS 模块的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — QMS 质量管理
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000215, 0, '质量管理(QMS)', 'quality', 1, 'verified_user', '/admin/quality', 16, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 检验标准
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000601, 31000000000000215, '检验标准-查看',   'get.admin/quality/standard', 3, '', '', 1, NOW(), NOW()),
(31000000000000602, 31000000000000215, '检验标准-创建',   'post.admin/quality/standard', 3, '', '', 2, NOW(), NOW()),
(31000000000000603, 31000000000000215, '检验标准-更新',   'put.admin/quality/standard', 3, '', '', 3, NOW(), NOW()),
(31000000000000604, 31000000000000215, '检验标准-删除',   'delete.admin/quality/standard', 3, '', '', 4, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 来料检验 (IQC)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000611, 31000000000000215, 'IQC-查看',   'get.admin/quality/iqc', 3, '', '', 8, NOW(), NOW()),
(31000000000000612, 31000000000000215, 'IQC-创建',   'post.admin/quality/iqc', 3, '', '', 9, NOW(), NOW()),
(31000000000000613, 31000000000000215, 'IQC-更新',   'put.admin/quality/iqc', 3, '', '', 10, NOW(), NOW()),
(31000000000000614, 31000000000000215, 'IQC-删除',   'delete.admin/quality/iqc', 3, '', '', 11, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 过程检验 (IPQC)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000621, 31000000000000215, 'IPQC-查看',   'get.admin/quality/ipqc', 3, '', '', 15, NOW(), NOW()),
(31000000000000622, 31000000000000215, 'IPQC-创建',   'post.admin/quality/ipqc', 3, '', '', 16, NOW(), NOW()),
(31000000000000623, 31000000000000215, 'IPQC-更新',   'put.admin/quality/ipqc', 3, '', '', 17, NOW(), NOW()),
(31000000000000624, 31000000000000215, 'IPQC-删除',   'delete.admin/quality/ipqc', 3, '', '', 18, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 出货检验 (OQC)
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000631, 31000000000000215, 'OQC-查看',   'get.admin/quality/oqc', 3, '', '', 22, NOW(), NOW()),
(31000000000000632, 31000000000000215, 'OQC-创建',   'post.admin/quality/oqc', 3, '', '', 23, NOW(), NOW()),
(31000000000000633, 31000000000000215, 'OQC-更新',   'put.admin/quality/oqc', 3, '', '', 24, NOW(), NOW()),
(31000000000000634, 31000000000000215, 'OQC-删除',   'delete.admin/quality/oqc', 3, '', '', 25, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 不合格品
-- ============================================================
INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000641, 31000000000000215, '不合格品-查看',   'get.admin/quality/nonconformity', 3, '', '', 29, NOW(), NOW()),
(31000000000000642, 31000000000000215, '不合格品-创建',   'post.admin/quality/nonconformity', 3, '', '', 30, NOW(), NOW()),
(31000000000000643, 31000000000000215, '不合格品-更新',   'put.admin/quality/nonconformity', 3, '', '', 31, NOW(), NOW()),
(31000000000000644, 31000000000000215, '不合格品-删除',   'delete.admin/quality/nonconformity', 3, '', '', 32, NOW(), NOW());

-- DMS Document Category Table
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

CREATE TABLE IF NOT EXISTS `erp_dms_category` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT '',
    `sort` INT NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `erp_dms_category` (`id`, `name`, `sort`, `status`) VALUES
(1, '制度规范', 1, 1),
(2, '流程文档', 2, 1),
(3, '技术文档', 3, 1),
(4, '合同协议', 4, 1),
(5, '培训材料', 5, 1),
(6, '其他', 99, 1);

-- ============================================================
-- P0 寻源采购：询比价单 → 供应商报价 → 比价 → 中标 → 转采购订单 + 供应商准入评分
-- 金额列一律 DECIMAL，禁止 float；snowflake 主键 + 软删 deleted_at 双时间戳
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_purchase_rfq` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rfq_no` VARCHAR(50) NOT NULL COMMENT '询价单号',
    `buyer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采购员ID',
    `supplier_range` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '供应商范围（逗号分隔供应商ID或说明）',
    `require_date` DATETIME DEFAULT NULL COMMENT '需求日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发布(询价中) 2=已中标 3=已关闭 4=已取消',
    `awarded_quote_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中标报价ID，0=未中标（防重复中标）',
    `auditor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人ID',
    `audited_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `audit_remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '审核意见',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rfq_no` (`rfq_no`),
    KEY `idx_buyer_id` (`buyer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_require_date` (`require_date`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购询价单';

CREATE TABLE IF NOT EXISTS `erp_purchase_rfq_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rfq_id` BIGINT UNSIGNED NOT NULL COMMENT '询价单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '需求数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `target_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '目标单价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_rfq_id` (`rfq_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购询价单明细';

CREATE TABLE IF NOT EXISTS `erp_purchase_rfq_quote` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rfq_id` BIGINT UNSIGNED NOT NULL COMMENT '询价单ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '报价总额（冗余，由明细bcmath汇总）',
    `quote_date` DATETIME DEFAULT NULL COMMENT '报价日期',
    `valid_until` DATE DEFAULT NULL COMMENT '报价有效期截止日',
    `awarded` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中标标记: 0=未中标 1=中标',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=有效 1=已作废',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_rfq_id` (`rfq_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_awarded` (`awarded`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商报价';

CREATE TABLE IF NOT EXISTS `erp_purchase_rfq_quote_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `quote_id` BIGINT UNSIGNED NOT NULL COMMENT '报价ID',
    `rfq_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '询价单明细ID（取需求数量/单位）',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价单价',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '行金额 = 单价×需求数量（bcmath）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_quote_id` (`quote_id`),
    KEY `idx_rfq_item_id` (`rfq_item_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商报价明细';

CREATE TABLE IF NOT EXISTS `erp_supplier_assessment` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `total_score` DECIMAL(5,1) NOT NULL DEFAULT 0.0 COMMENT '总分（0-100）',
    `grade` CHAR(1) NOT NULL DEFAULT 'C' COMMENT '等级: A/B/C（A≥90, B≥70, 其余C）',
    `dimensions` JSON DEFAULT NULL COMMENT '评分维度json（如质量/价格/交期/服务各分）',
    `assessor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评估人ID',
    `assessed_at` DATETIME DEFAULT NULL COMMENT '评估日期',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_assessed_at` (`assessed_at`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商准入评分';

-- ============================================================
-- P0 发票管理：应收/应付发票 + 开票申请状态流 + 三单匹配（防超开）
-- 边界说明：发票为税务票据追踪单据，不新增 ARAP 分录、不联动收付款/核销/结算。
-- ============================================================
CREATE TABLE IF NOT EXISTS `erp_finance_invoice` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `invoice_no` VARCHAR(50) NOT NULL COMMENT '发票号(唯一)',
    `electronic_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '数电票号码(平台回写，空=未开具)',
    `issue_status` VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT '数电出口状态: none=未开具 issued=已开具 voided=已红冲',
    `type` VARCHAR(10) NOT NULL DEFAULT 'ar' COMMENT '类型: ar=应收发票 ap=应付发票',
    `customer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户ID(应收，type=ar 时必填)',
    `supplier_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '供应商ID(应付，type=ap 时必填)',
    `biz_type` VARCHAR(30) NOT NULL DEFAULT 'manual' COMMENT '来源类型: purchase_receive=收货单 sales_delivery=发货单 manual=手工',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID(收货单/发货单ID，manual 为 0)',
    `invoice_date` DATE DEFAULT NULL COMMENT '发票日期',
    `untaxed_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '不含税金额(=Σ行金额，服务端计算)',
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '税额(=Σ行税额，服务端计算)',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '价税合计(=不含税+税额)',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '币种',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT '状态: draft=开票申请 submitted=已提交审核 audited=已审核入账 voided=已作废',
    `void_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '作废原因',
    `audited_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人ID',
    `audited_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invoice_no` (`invoice_no`),
    KEY `idx_source` (`biz_type`, `source_id`, `status`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票(应收/应付)';

CREATE TABLE IF NOT EXISTS `erp_finance_invoice_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `invoice_id` BIGINT UNSIGNED NOT NULL COMMENT '发票ID',
    `product_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '产品ID(可空)',
    `source_item_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '来源单据行ID(收货/发货明细ID，可空)',
    `quantity` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '不含税金额(服务端计算)',
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '税率(小数，如 0.13=13%)',
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '税额(服务端计算)',
    `line_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '含税金额(行小计=金额+税额)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_invoice_id` (`invoice_id`),
    KEY `idx_source_item_id` (`source_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票明细';

CREATE TABLE IF NOT EXISTS `erp_tax_input_invoice` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `invoice_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发票代码(数电票无代码，为空)',
    `invoice_no` VARCHAR(50) NOT NULL COMMENT '发票号码',
    `issue_date` DATE NOT NULL COMMENT '开票日期',
    `seller_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '销售方名称',
    `seller_tax_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '销售方税号(验真规则入参)',
    `buyer_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '购买方名称',
    `buyer_tax_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '购买方税号',
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '价税合计(发票总额)',
    `untaxed_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '不含税金额',
    `tax_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '税额',
    `verify_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '验真状态: 0=待验真 1=验真通过 2=验真失败',
    `verify_at` DATETIME DEFAULT NULL COMMENT '验真时间',
    `deduct_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抵扣状态: 0=未勾选 1=已勾选待抵扣 2=已抵扣',
    `deduct_period` VARCHAR(7) NOT NULL DEFAULT '' COMMENT '抵扣期间(YYYY-MM，抵扣时记录，空=未抵扣)',
    `source` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT '登记来源: manual=手工 excel=批量导入',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code_no` (`invoice_code`, `invoice_no`),
    KEY `idx_verify_status` (`verify_status`),
    KEY `idx_deduct` (`deduct_status`, `deduct_period`),
    KEY `idx_issue_date` (`issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='进项发票池(P2-F5)';

CREATE TABLE IF NOT EXISTS `erp_tax_issue_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `invoice_id` BIGINT UNSIGNED NOT NULL COMMENT '发票ID(erp_finance_invoice.id)',
    `action` VARCHAR(10) NOT NULL COMMENT '动作: issue=开票 void=红冲',
    `bill_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '数电票号码(平台回写，失败为空)',
    `platform` VARCHAR(30) NOT NULL DEFAULT 'mock' COMMENT '平台适配器标识(platform())',
    `request` JSON DEFAULT NULL COMMENT '请求报文(适配器入参)',
    `response` JSON DEFAULT NULL COMMENT '响应报文(适配器返回值)',
    `success` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否成功: 0=失败 1=成功',
    `error` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
    `operator_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数电票开票/红冲日志(P2-F5)';

CREATE TABLE IF NOT EXISTS `erp_finance_invoice_match_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `invoice_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发票ID(0=校验未通过的拟开票尝试)',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源类型: purchase_receive/sales_delivery',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源单据ID',
    `invoiced_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '已开票金额累计(status!=voided，不含本次)',
    `result` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '校验结果: ok=恰好=余额 under=小于余额 over=超开(拦截)',
    `detail` JSON COMMENT '明细(来源总额/未开票余额/本次金额/供应商或客户一致性等)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_invoice_id` (`invoice_id`),
    KEY `idx_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三单匹配校验日志';

-- ============================================================
-- P0 OpenAPI 平台：第三方应用 + Webhook 订阅与投递
-- ============================================================
-- ------------------------------------------------------------
-- 第三方开放平台应用表
-- 认证：请求头 X-API-Key=app_key + X-Timestamp + X-Signature(HMAC-SHA256)
-- 说明：app_secret 必须可解密（HMAC 校验需要原始密钥），故使用模型
-- Encryptable 加密存储（AES-256-CBC，密钥 ENCRYPTABLE_KEY）；
-- app_secret_hash 仅作密钥一致性/完整性校验（sha256 hex），不可用于签名验证。
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `erp_openapi_app` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `app_name` VARCHAR(100) NOT NULL COMMENT '应用名称',
    `app_key` VARCHAR(64) NOT NULL COMMENT 'API Key（ak_ 前缀，公开标识，客户端明文携带）',
    `app_secret` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'API Secret（加密存储，创建/重置时仅明文展示一次）',
    `app_secret_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'API Secret 的 sha256 hex（完整性校验，不可逆推密钥）',
    `scopes` JSON DEFAULT NULL COMMENT '允许访问的路径前缀数组；NULL/空数组=不限制（示例：["/open/v1/orders"]）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_app_key` (`app_key`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方开放平台应用表';

-- ------------------------------------------------------------
-- Webhook 订阅表（归属于某个开放平台应用）
-- event 为事件名数组，支持通配 "*"（订阅全部事件）
-- secret 用于生成 X-Webhook-Signature = HMAC-SHA256(secret, payload) 供接收方验签
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `erp_webhook_subscription` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `app_id` BIGINT UNSIGNED NOT NULL COMMENT '所属开放平台应用ID（erp_openapi_app.id）',
    `event` JSON NOT NULL COMMENT '订阅事件名数组，支持 "*" 通配（如 ["order.created"]）',
    `target_url` VARCHAR(500) NOT NULL COMMENT '接收方回调 URL（POST）',
    `secret` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '签名密钥（加密存储，接收方侧需自行留存）',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '订阅状态: 0=停用 1=启用',
    `last_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '最近一次投递结果: success/failed/空=未投递',
    `last_delivered_at` DATETIME DEFAULT NULL COMMENT '最近一次成功投递时间',
    `failed_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续失败计数（成功后归零）',
    `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_app_id` (`app_id`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook订阅表';

-- ------------------------------------------------------------
-- Webhook 投递日志表（一次事件投递一条记录，重试在 attempts/next_retry_at 上累积）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `erp_webhook_delivery_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `subscription_id` BIGINT UNSIGNED NOT NULL COMMENT '订阅ID（erp_webhook_subscription.id）',
    `event` VARCHAR(100) NOT NULL COMMENT '事件名',
    `payload` JSON NOT NULL COMMENT '事件载荷',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '投递状态: pending/success/failed',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已尝试次数（首次=1）',
    `next_retry_at` DATETIME DEFAULT NULL COMMENT '下次重试时间（指数退避；达到最大次数后置 NULL=放弃）',
    `http_code` SMALLINT UNSIGNED DEFAULT NULL COMMENT '最近一次投递的 HTTP 状态码',
    `response_summary` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '响应体/错误信息摘要（截断前 500 字符）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_subscription_id` (`subscription_id`),
    KEY `idx_retry` (`status`, `next_retry_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook投递日志表';

-- Service wiring permission seeds
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

INSERT INTO `erp_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000740, 31000000000000005, '期末结转-执行',     'post.admin/finance/report/close-period', 3, '', '', 30, NOW(), NOW()),
(31000000000000741, 31000000000000005, '多币种合并-执行',   'post.admin/finance/report/consolidate', 3, '', '', 31, NOW(), NOW()),
(31000000000000742, 31000000000000005, '财务指标-计算',     'post.admin/finance/report/ratios', 3, '', '', 32, NOW(), NOW()),
(31000000000000743, 31000000000000005, '试算平衡-查看',     'get.admin/finance/report/trial-balance', 3, '', '', 33, NOW(), NOW()),
(31000000000000744, 31000000000000005, '科目余额-查看',     'get.admin/finance/report/account-balance', 3, '', '', 34, NOW(), NOW()),
(31000000000000745, 31000000000000210, '运费-计算',         'post.admin/tms/freight-rate/calculate', 3, '', '', 19, NOW(), NOW()),
(31000000000000746, 31000000000000210, '运费-比价',         'get.admin/tms/freight-rate/rate-shop', 3, '', '', 20, NOW(), NOW()),
(31000000000000747, 31000000000000215, '检验-登记',         'post.admin/quality/inspection/record', 3, '', '', 33, NOW(), NOW()),
(31000000000000748, 31000000000000215, '检验-合格率',       'post.admin/quality/inspection/pass-rate', 3, '', '', 34, NOW(), NOW()),
-- 超级管理员通配权限：角色描述即"拥有所有权限"，后续新增端点无需逐条补种子
(31000000000000749, 0, '全部权限',           '*', 3, '', '', 0, NOW(), NOW());

-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 安装完成
-- ============================================================
-- 超级管理员角色 (ID=10000000000000001) 关联全部权限（含末尾新增的 QMS/服务接线权限）
-- ============================================================
INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erp_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erp_admin_role_permission` WHERE `role_id` = 10000000000000001
);
