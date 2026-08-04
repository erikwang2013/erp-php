-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: OMS订单管理系统表（8张表）
-- 包含: OMS订单扩展/订单地址/履约记录/履约明细/RMA/RMA明细/库存预占/销售渠道
-- ============================================================

-- ============================================================
-- OMS订单扩展（关联 erik_sales_order）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_oms_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 erik_sales_order.id',
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
CREATE TABLE IF NOT EXISTS `erik_oms_order_address` (
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
CREATE TABLE IF NOT EXISTS `erik_oms_fulfillment` (
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
CREATE TABLE IF NOT EXISTS `erik_oms_fulfillment_item` (
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
CREATE TABLE IF NOT EXISTS `erik_oms_rma` (
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
CREATE TABLE IF NOT EXISTS `erik_oms_rma_item` (
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
CREATE TABLE IF NOT EXISTS `erik_oms_inventory_reservation` (
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
CREATE TABLE IF NOT EXISTS `erik_channel` (
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
