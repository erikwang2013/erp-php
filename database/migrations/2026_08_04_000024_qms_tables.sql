-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: QMS质量管理系统表（5张表）
-- 包含: 检验标准/IQC来料检验/IPQC过程检验/OQC出货检验/不合格品
-- ============================================================

-- ============================================================
-- QMS检验标准
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_quality_inspection_standard` (
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
CREATE TABLE IF NOT EXISTS `erik_quality_iqc_record` (
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
CREATE TABLE IF NOT EXISTS `erik_quality_ipqc_record` (
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
CREATE TABLE IF NOT EXISTS `erik_quality_oqc_record` (
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
CREATE TABLE IF NOT EXISTS `erik_quality_nonconformity` (
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
