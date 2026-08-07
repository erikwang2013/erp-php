-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: CRM扩展 — 营销活动/服务工单/客户分析

-- 营销活动表
CREATE TABLE IF NOT EXISTS `erik_crm_campaign` (
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

-- 活动参与记录
CREATE TABLE IF NOT EXISTS `erik_crm_campaign_participant` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `campaign_id` BIGINT UNSIGNED NOT NULL COMMENT '活动ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `contact_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '联系人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0已邀请1已参与2已转化3已退订',
    `response` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '反馈/备注',
    `participated_at` DATETIME DEFAULT NULL COMMENT '参与时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='营销活动参与记录表';

-- 服务工单表
CREATE TABLE IF NOT EXISTS `erik_crm_ticket` (
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

-- 工单回复表
CREATE TABLE IF NOT EXISTS `erik_crm_ticket_reply` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `ticket_id` BIGINT UNSIGNED NOT NULL COMMENT '工单ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '回复人ID(0=客户)',
    `content` TEXT COMMENT '回复内容',
    `is_internal` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0对外1内部备忘',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单回复表';

-- 客户分析报表
CREATE TABLE IF NOT EXISTS `erik_crm_analytics_report` (
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

-- 分析指标定义
CREATE TABLE IF NOT EXISTS `erik_crm_analytics_metric` (
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

INSERT INTO `erik_crm_analytics_metric` (`id`, `name`, `key`, `type`) VALUES
(70000000000000001, '新增客户数', 'new_customers', 'count'),
(70000000000000002, '活跃客户数', 'active_customers', 'count'),
(70000000000000003, '客户留存率', 'retention_rate', 'ratio'),
(70000000000000004, '平均客单价', 'avg_order_value', 'average'),
(70000000000000005, '客户生命周期价值', 'clv', 'sum'),
(70000000000000006, '服务工单解决率', 'ticket_resolution_rate', 'ratio');
