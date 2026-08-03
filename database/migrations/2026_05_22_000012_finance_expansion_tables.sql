-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 财务扩展 — 固定资产/税务/多币种/预算/成本利润中心

-- 固定资产表
CREATE TABLE IF NOT EXISTS `erik_finance_asset` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '资产编码',
    `name` VARCHAR(200) NOT NULL COMMENT '资产名称',
    `category` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '资产类别: 房屋建筑/机器设备/运输工具/办公设备/其他',
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

-- 折旧记录表
CREATE TABLE IF NOT EXISTS `erik_finance_asset_depreciation` (
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

-- 税率配置表
CREATE TABLE IF NOT EXISTS `erik_finance_tax_rate` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL COMMENT '税率名称',
    `rate` DECIMAL(6,4) NOT NULL DEFAULT 0.0000 COMMENT '税率(如0.13=13%)',
    `type` VARCHAR(30) NOT NULL DEFAULT 'vat' COMMENT '税种: vat增值税/cit企业所得税/pit个人所得税/stamp印花税/other',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='税率配置表';

INSERT INTO `erik_finance_tax_rate` (`id`, `name`, `rate`, `type`) VALUES
(60000000000000001, '增值税-标准税率', 0.1300, 'vat'),
(60000000000000002, '增值税-低税率', 0.0900, 'vat'),
(60000000000000003, '增值税-零税率', 0.0000, 'vat'),
(60000000000000004, '企业所得税', 0.2500, 'cit');

-- 税务记录表
CREATE TABLE IF NOT EXISTS `erik_finance_tax_record` (
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

-- 币种表
CREATE TABLE IF NOT EXISTS `erik_finance_currency` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(10) NOT NULL COMMENT '币种代码: CNY/USD/EUR/JPY/GBP',
    `name` VARCHAR(50) NOT NULL COMMENT '币种名称',
    `symbol` VARCHAR(5) NOT NULL DEFAULT '' COMMENT '货币符号: ¥/$/€',
    `is_base` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否本位币',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='币种表';

INSERT INTO `erik_finance_currency` (`id`, `code`, `name`, `symbol`, `is_base`) VALUES
(61000000000000001, 'CNY', '人民币', '¥', 1),
(61000000000000002, 'USD', '美元', '$', 0),
(61000000000000003, 'EUR', '欧元', '€', 0),
(61000000000000004, 'JPY', '日元', '¥', 0);

-- 汇率表
CREATE TABLE IF NOT EXISTS `erik_finance_exchange_rate` (
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

-- 预算表
CREATE TABLE IF NOT EXISTS `erik_finance_budget` (
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

-- 预算明细表
CREATE TABLE IF NOT EXISTS `erik_finance_budget_item` (
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

-- 成本中心
CREATE TABLE IF NOT EXISTS `erik_finance_cost_center` (
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

-- 利润中心
CREATE TABLE IF NOT EXISTS `erik_finance_profit_center` (
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

-- 费用分摊记录
CREATE TABLE IF NOT EXISTS `erik_finance_allocation` (
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
