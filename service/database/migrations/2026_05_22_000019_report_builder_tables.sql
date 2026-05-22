-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 自定义报表构建器表（5张表）
-- 包含: 报表模板/报表字段/报表筛选/报表数据集/报表调度
-- 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- 报表模板表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_report_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '模板编码',
    `name` VARCHAR(200) NOT NULL COMMENT '模板名称',
    `module` VARCHAR(50) NOT NULL COMMENT '关联模块: product/purchase/sales/inventory/finance/crm/hr/mfg/project',
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

-- ============================================================
-- 报表字段配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_report_field` (
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

-- ============================================================
-- 报表筛选条件表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_report_filter` (
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

-- ============================================================
-- 报表数据集表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_report_dataset` (
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

-- ============================================================
-- 报表调度配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_report_schedule` (
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
