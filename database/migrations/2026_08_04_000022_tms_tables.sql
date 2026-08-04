-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: TMS运输管理系统表（7张表）
-- 包含: 承运商/承运商服务/运费费率/运单/物流轨迹/包裹明细/运费发票
-- ============================================================

-- ============================================================
-- TMS承运商
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_tms_carrier` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_carrier_service` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_freight_rate` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_shipment` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_tracking_event` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_shipment_package` (
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
CREATE TABLE IF NOT EXISTS `erik_tms_freight_invoice` (
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
