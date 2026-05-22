-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 销售模块表（9张表）
-- 包含: 报价单/报价明细/销售订单/订单明细/发货单/发货明细/退货单/退货明细/客户结算
-- ============================================================

-- ============================================================
-- 销售报价单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_quotation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '报价单号',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '报价总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已报价 2=已转订单 3=已失效',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `quoted_at` DATETIME DEFAULT NULL COMMENT '报价时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售报价单';

-- ============================================================
-- 销售报价明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_quotation_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `quotation_id` BIGINT UNSIGNED NOT NULL COMMENT '报价单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_quotation_id` (`quotation_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售报价明细';

-- ============================================================
-- 销售订单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '订单单号',
    `quotation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '报价单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核 2=部分发货 3=已发货 4=已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `ordered_at` DATETIME DEFAULT NULL COMMENT '下单时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_quotation_id` (`quotation_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单';

-- ============================================================
-- 销售订单明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_order_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '销售订单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订购数量',
    `delivered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已发数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单明细';

-- ============================================================
-- 销售发货单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_delivery` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '发货单号',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '销售订单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '发货仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待出库 1=已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `delivered_at` DATETIME DEFAULT NULL COMMENT '发货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货单';

-- ============================================================
-- 销售发货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_delivery_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单明细ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '发货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_order_item_id` (`order_item_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售发货明细';

-- ============================================================
-- 销售退货单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_return` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '退货单号',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '退货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待入库 1=已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货单';

-- ============================================================
-- 销售退货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_return_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `return_id` BIGINT UNSIGNED NOT NULL COMMENT '退货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库库位ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_return_id` (`return_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售退货明细';

-- ============================================================
-- 客户结算
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_sales_settlement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT '发货单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应收金额',
    `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未结算 1=部分结算 2=已结算',
    `settled_at` DATETIME DEFAULT NULL COMMENT '结算时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户结算';
