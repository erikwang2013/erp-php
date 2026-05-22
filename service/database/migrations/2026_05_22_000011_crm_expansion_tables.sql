-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: CRM扩展 — 公海池/合同管理

-- 客户公海池配置（按客户等级设置回收规则）
CREATE TABLE IF NOT EXISTS `erik_crm_customer_pool_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `level_id` BIGINT UNSIGNED NOT NULL COMMENT '客户等级ID',
    `reclaim_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT '无跟进自动回收天数',
    `max_claims` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT '每人最大领取数',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level_id` (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户公海池规则表';

-- 公海池客户状态（扩展客户表，标记是否在公海池）
CREATE TABLE IF NOT EXISTS `erik_crm_pool_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `action` TINYINT UNSIGNED NOT NULL COMMENT '1领取2释放3回收',
    `from_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '原归属人ID',
    `to_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新归属人ID',
    `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '操作备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公海池操作记录表';

-- 合同表
CREATE TABLE IF NOT EXISTS `erik_crm_contract` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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

-- 合同明细
CREATE TABLE IF NOT EXISTS `erik_crm_contract_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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

-- CRM报价表（独立于销售报价，CRM内部使用）
CREATE TABLE IF NOT EXISTS `erik_crm_quotation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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

-- CRM报价明细
CREATE TABLE IF NOT EXISTS `erik_crm_quotation_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
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
