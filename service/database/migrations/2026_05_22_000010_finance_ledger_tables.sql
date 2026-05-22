-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 财务总账/明细账/报表

-- 总账表（每科目每期一条汇总记录）
CREATE TABLE IF NOT EXISTS `erik_finance_general_ledger` (
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

-- 明细账表（每笔凭证分录的明细记录）
CREATE TABLE IF NOT EXISTS `erik_finance_subsidiary_ledger` (
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

-- 资产负债表快照
CREATE TABLE IF NOT EXISTS `erik_finance_balance_sheet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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
    UNIQUE KEY `uk_report_period` (`report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产负债表快照';

-- 现金流量表快照
CREATE TABLE IF NOT EXISTS `erik_finance_cash_flow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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
    UNIQUE KEY `uk_report_period` (`report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='现金流量表快照';
