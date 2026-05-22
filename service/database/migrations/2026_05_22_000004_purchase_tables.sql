-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 采购模块表（9张表）
-- 包含: 采购申请/申请明细/采购订单/订单明细/收货单/收货明细/退货单/退货明细/供应商结算
-- ============================================================

-- ============================================================
-- 采购申请单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_apply` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '申请单号',
    `apply_user_id` BIGINT UNSIGNED NOT NULL COMMENT '申请人ID',
    `department` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '申请部门',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审批 1=已批准 2=已驳回 3=已转订单',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `approved_at` DATETIME DEFAULT NULL COMMENT '审批时间',
    `approved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_apply_user_id` (`apply_user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请单';

-- ============================================================
-- 采购申请明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_apply_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `apply_id` BIGINT UNSIGNED NOT NULL COMMENT '采购申请ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '申请数量',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `estimated_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '预估单价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_apply_id` (`apply_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购申请明细';

-- ============================================================
-- 采购订单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '订单单号',
    `apply_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采购申请ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待审核 1=已审核 2=部分收货 3=已收货 4=已取消',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `ordered_at` DATETIME DEFAULT NULL COMMENT '下单时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_apply_id` (`apply_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单';

-- ============================================================
-- 采购订单明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_order_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '采购订单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订购数量',
    `received_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已收数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单明细';

-- ============================================================
-- 采购收货单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_receive` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '收货单号',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '采购订单ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收货仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待入库 1=已入库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `received_at` DATETIME DEFAULT NULL COMMENT '收货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货单';

-- ============================================================
-- 采购收货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_receive_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `order_item_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单明细ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '入库库位ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '收货数量',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '单价',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
    `unit` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_order_item_id` (`order_item_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_sku_id` (`sku_id`),
    KEY `idx_batch_code` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购收货明细';

-- ============================================================
-- 采购退货单
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_return` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '退货单号',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '退货仓库ID',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货总金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待出库 1=已出库',
    `remark` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    `returned_at` DATETIME DEFAULT NULL COMMENT '退货时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_warehouse_id` (`warehouse_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货单';

-- ============================================================
-- 采购退货明细
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_return_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `return_id` BIGINT UNSIGNED NOT NULL COMMENT '退货单ID',
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '产品ID',
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SKU ID',
    `batch_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '批次号',
    `location_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出库库位ID',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购退货明细';

-- ============================================================
-- 供应商结算
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_purchase_settlement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    `receive_id` BIGINT UNSIGNED NOT NULL COMMENT '收货单ID',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应付金额',
    `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已付金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未结算 1=部分结算 2=已结算',
    `settled_at` DATETIME DEFAULT NULL COMMENT '结算时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_receive_id` (`receive_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商结算';
