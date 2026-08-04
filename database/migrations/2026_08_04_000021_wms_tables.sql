-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: WMS仓储管理系统表（12张表）
-- 包含: 库区/库位扩展/ASN/ASN明细/收货任务/上架任务/上架明细/拣货任务/拣货明细/打包任务/波次/波次订单关联
-- ============================================================

-- ============================================================
-- WMS库区
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_wms_zone` (
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
-- WMS库位扩展（关联 erik_location，增加层级/容积/承重等WMS属性）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_wms_location` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `location_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 erik_location.id',
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
CREATE TABLE IF NOT EXISTS `erik_wms_asn` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_asn_item` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_receiving` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_putaway_task` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_putaway_item` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_pick_task` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_pick_item` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_pack_task` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_wave` (
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
CREATE TABLE IF NOT EXISTS `erik_wms_wave_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `wave_id` BIGINT UNSIGNED NOT NULL COMMENT '波次ID',
    `oms_order_id` BIGINT UNSIGNED NOT NULL COMMENT 'OMS订单ID',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wave_order` (`wave_id`, `oms_order_id`),
    KEY `idx_oms_order_id` (`oms_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WMS波次订单关联';
