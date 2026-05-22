-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 库存/财务/CRM表（26张表）
-- 包含: 库存(11) + 财务(11) + CRM(4) + 漏斗阶段种子数据
-- ============================================================

-- ################################################################
-- SECTION: INVENTORY — 库存管理 (11 tables)
-- ################################################################

-- ============================================================
-- 实时库存表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory` (
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

-- ============================================================
-- 批次追踪表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory_batch` (
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

-- ============================================================
-- 序列号追踪表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory_serial` (
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

-- ============================================================
-- 库存流水日志表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory_flow` (
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

-- ============================================================
-- 调拨单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_transfer` (
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

-- ============================================================
-- 调拨明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_transfer_item` (
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

-- ============================================================
-- 盘点任务表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_check_task` (
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

-- ============================================================
-- 盘点明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_check_detail` (
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

-- ============================================================
-- 库存预警规则表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory_alert_rule` (
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

-- ============================================================
-- 库存预警日志表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_inventory_alert_log` (
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

-- ============================================================
-- 成本计算日志表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_cost_record` (
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
-- SECTION: FINANCE — 财务管理 (11 tables)
-- ################################################################

-- ============================================================
-- 会计科目表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_account` (
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

-- ============================================================
-- 记账凭证表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_voucher` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '凭证号',
    `voucher_date` DATE NOT NULL COMMENT '凭证日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已审核',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `audited_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_voucher_date` (`voucher_date`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记账凭证表';

-- ============================================================
-- 凭证分录明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_voucher_item` (
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

-- ============================================================
-- 应收应付明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_ar_ap` (
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
    KEY `idx_type` (`type`),
    KEY `idx_partner_id` (`partner_id`),
    KEY `idx_status` (`status`),
    KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应收应付明细表';

-- ============================================================
-- 银行账户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_bank_account` (
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

-- ============================================================
-- 收款单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_receipt` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收款单号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '收款账户ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '收款方式: cash=现金 bank=银行转账 wechat=微信 alipay=支付宝',
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

-- ============================================================
-- 付款单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_payment` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '付款单号',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `bank_account_id` BIGINT UNSIGNED NOT NULL COMMENT '付款账户ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
    `method` VARCHAR(20) NOT NULL DEFAULT 'bank' COMMENT '付款方式: cash=现金 bank=银行转账 wechat=微信 alipay=支付宝',
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

-- ============================================================
-- 核销记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_settlement` (
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

-- ============================================================
-- 现金日记账表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_cash_journal` (
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

-- ============================================================
-- 费用报销表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_expense` (
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

-- ============================================================
-- 利润快照表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_finance_profit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `year` SMALLINT UNSIGNED NOT NULL COMMENT '年份',
    `month` TINYINT UNSIGNED NOT NULL COMMENT '月份',
    `revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业收入',
    `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '营业成本',
    `expense` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '费用合计',
    `profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '利润',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_year_month` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='利润快照表';

-- ################################################################
-- SECTION: CRM — 客户关系管理 (4 tables)
-- ################################################################

-- ============================================================
-- 销售漏斗阶段配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_crm_funnel_stage` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '阶段名称',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `win_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '预计赢单率(%)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售漏斗阶段配置表';

-- ============================================================
-- 漏斗阶段种子数据
-- ============================================================
INSERT INTO `erik_crm_funnel_stage` (`id`, `name`, `sort`, `win_rate`, `status`) VALUES
(50000000000000001, '初步接触', 1, 10.00, 1),
(50000000000000002, '需求确认', 2, 25.00, 1),
(50000000000000003, '报价方案', 3, 40.00, 1),
(50000000000000004, '商务谈判', 4, 60.00, 1),
(50000000000000005, '成交', 5, 100.00, 1),
(50000000000000006, '输单', 6, 0.00, 1);

-- ============================================================
-- 销售机会表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_crm_opportunity` (
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

-- ============================================================
-- 跟进记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_crm_follow_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `opportunity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '销售机会ID',
    `method` VARCHAR(20) NOT NULL DEFAULT 'phone' COMMENT '跟进方式: phone=电话 visit=拜访 email=邮件 message=消息 other=其他',
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

-- ============================================================
-- 联系人表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_crm_contact` (
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
