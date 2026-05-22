-- ============================================================
-- ERP 模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 ERP 模块的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — 6 个 ERP 模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000001, NULL, '商品管理', 'product',    1, 'inventory',     '/admin/product',    7, NOW(), NOW()),
(31000000000000002, NULL, '采购管理', 'purchase',    1, 'shopping_cart', '/admin/purchase',   8, NOW(), NOW()),
(31000000000000003, NULL, '销售管理', 'sales',       1, 'sell',          '/admin/sales',      9, NOW(), NOW()),
(31000000000000004, NULL, '库存管理', 'inventory',   1, 'warehouse',     '/admin/inventory', 10, NOW(), NOW()),
(31000000000000005, NULL, '财务管理', 'finance',     1, 'account_balance', '/admin/finance',  11, NOW(), NOW()),
(31000000000000006, NULL, 'CRM',       'crm',        1, 'people',        '/admin/crm',       12, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 商品基础数据
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000011, 31000000000000001, '商品-查看',   'get.admin/product',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000012, 31000000000000001, '商品-创建',   'post.admin/product',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000013, 31000000000000001, '商品-更新',   'put.admin/product',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000014, 31000000000000001, '商品-删除',   'delete.admin/product', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000021, 31000000000000001, '分类-查看',   'get.admin/category',    3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000022, 31000000000000001, '分类-创建',   'post.admin/category',   3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000023, 31000000000000001, '分类-更新',   'put.admin/category',    3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000024, 31000000000000001, '分类-删除',   'delete.admin/category', 3, NULL, NULL, 8, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000031, 31000000000000001, '品牌-查看',   'get.admin/brand',    3, NULL, NULL,  9, NOW(), NOW()),
(31000000000000032, 31000000000000001, '品牌-创建',   'post.admin/brand',   3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000033, 31000000000000001, '品牌-更新',   'put.admin/brand',    3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000034, 31000000000000001, '品牌-删除',   'delete.admin/brand', 3, NULL, NULL, 12, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000041, 31000000000000001, '仓库-查看',   'get.admin/warehouse',    3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000042, 31000000000000001, '仓库-创建',   'post.admin/warehouse',   3, NULL, NULL, 14, NOW(), NOW()),
(31000000000000043, 31000000000000001, '仓库-更新',   'put.admin/warehouse',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000044, 31000000000000001, '仓库-删除',   'delete.admin/warehouse', 3, NULL, NULL, 16, NOW(), NOW()),
(31000000000000045, 31000000000000001, '库位-查看(按仓库)', 'get.admin/warehouse/locations', 3, NULL, NULL, 17, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000051, 31000000000000001, '库位-查看',   'get.admin/location',    3, NULL, NULL, 18, NOW(), NOW()),
(31000000000000052, 31000000000000001, '库位-创建',   'post.admin/location',   3, NULL, NULL, 19, NOW(), NOW()),
(31000000000000053, 31000000000000001, '库位-更新',   'put.admin/location',    3, NULL, NULL, 20, NOW(), NOW()),
(31000000000000054, 31000000000000001, '库位-删除',   'delete.admin/location', 3, NULL, NULL, 21, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000061, 31000000000000001, '供应商-查看', 'get.admin/supplier',    3, NULL, NULL, 22, NOW(), NOW()),
(31000000000000062, 31000000000000001, '供应商-创建', 'post.admin/supplier',   3, NULL, NULL, 23, NOW(), NOW()),
(31000000000000063, 31000000000000001, '供应商-更新', 'put.admin/supplier',    3, NULL, NULL, 24, NOW(), NOW()),
(31000000000000064, 31000000000000001, '供应商-删除', 'delete.admin/supplier', 3, NULL, NULL, 25, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000071, 31000000000000001, '客户-查看',   'get.admin/customer',    3, NULL, NULL, 26, NOW(), NOW()),
(31000000000000072, 31000000000000001, '客户-创建',   'post.admin/customer',   3, NULL, NULL, 27, NOW(), NOW()),
(31000000000000073, 31000000000000001, '客户-更新',   'put.admin/customer',    3, NULL, NULL, 28, NOW(), NOW()),
(31000000000000074, 31000000000000001, '客户-删除',   'delete.admin/customer', 3, NULL, NULL, 29, NOW(), NOW()),
(31000000000000075, 31000000000000001, '客户等级',    'any.admin/customer-level', 3, NULL, NULL, 30, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 采购模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000081, 31000000000000002, '采购申请-查看', 'get.admin/purchase/apply',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000082, 31000000000000002, '采购申请-创建', 'post.admin/purchase/apply',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000083, 31000000000000002, '采购申请-更新', 'put.admin/purchase/apply',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000084, 31000000000000002, '采购申请-删除', 'delete.admin/purchase/apply', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000091, 31000000000000002, '采购订单-查看', 'get.admin/purchase/order',    3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000092, 31000000000000002, '采购订单-创建', 'post.admin/purchase/order',   3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000093, 31000000000000002, '采购订单-更新', 'put.admin/purchase/order',    3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000094, 31000000000000002, '采购订单-删除', 'delete.admin/purchase/order', 3, NULL, NULL, 8, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000101, 31000000000000002, '采购收货-查看', 'get.admin/purchase/receive',    3, NULL, NULL,  9, NOW(), NOW()),
(31000000000000102, 31000000000000002, '采购收货-创建', 'post.admin/purchase/receive',   3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000103, 31000000000000002, '采购收货-更新', 'put.admin/purchase/receive',    3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000104, 31000000000000002, '采购收货-删除', 'delete.admin/purchase/receive', 3, NULL, NULL, 12, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000111, 31000000000000002, '采购退货-查看', 'get.admin/purchase/return',    3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000112, 31000000000000002, '采购退货-创建', 'post.admin/purchase/return',   3, NULL, NULL, 14, NOW(), NOW()),
(31000000000000113, 31000000000000002, '采购退货-更新', 'put.admin/purchase/return',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000114, 31000000000000002, '采购退货-删除', 'delete.admin/purchase/return', 3, NULL, NULL, 16, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000121, 31000000000000002, '采购结算', 'any.admin/purchase/settlement', 3, NULL, NULL, 17, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 销售模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000131, 31000000000000003, '销售报价-查看', 'get.admin/sales/quotation',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000132, 31000000000000003, '销售报价-创建', 'post.admin/sales/quotation',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000133, 31000000000000003, '销售报价-更新', 'put.admin/sales/quotation',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000134, 31000000000000003, '销售报价-删除', 'delete.admin/sales/quotation', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000141, 31000000000000003, '销售订单-查看', 'get.admin/sales/order',    3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000142, 31000000000000003, '销售订单-创建', 'post.admin/sales/order',   3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000143, 31000000000000003, '销售订单-更新', 'put.admin/sales/order',    3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000144, 31000000000000003, '销售订单-删除', 'delete.admin/sales/order', 3, NULL, NULL, 8, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000151, 31000000000000003, '销售发货-查看', 'get.admin/sales/delivery',    3, NULL, NULL,  9, NOW(), NOW()),
(31000000000000152, 31000000000000003, '销售发货-创建', 'post.admin/sales/delivery',   3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000153, 31000000000000003, '销售发货-更新', 'put.admin/sales/delivery',    3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000154, 31000000000000003, '销售发货-删除', 'delete.admin/sales/delivery', 3, NULL, NULL, 12, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000161, 31000000000000003, '销售退货-查看', 'get.admin/sales/return',    3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000162, 31000000000000003, '销售退货-创建', 'post.admin/sales/return',   3, NULL, NULL, 14, NOW(), NOW()),
(31000000000000163, 31000000000000003, '销售退货-更新', 'put.admin/sales/return',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000164, 31000000000000003, '销售退货-删除', 'delete.admin/sales/return', 3, NULL, NULL, 16, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000171, 31000000000000003, '销售结算', 'any.admin/sales/settlement', 3, NULL, NULL, 17, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 库存模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000181, 31000000000000004, '库存总览',       'any.admin/inventory',         3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000182, 31000000000000004, '库存流水',       'any.admin/inventory/flow',     3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000183, 31000000000000004, '调拨-查看',      'get.admin/inventory/transfer',  3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000184, 31000000000000004, '调拨-创建',      'post.admin/inventory/transfer', 3, NULL, NULL, 4, NOW(), NOW()),
(31000000000000185, 31000000000000004, '调拨-更新',      'put.admin/inventory/transfer',  3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000186, 31000000000000004, '调拨-删除',      'delete.admin/inventory/transfer', 3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000187, 31000000000000004, '盘点-查看',      'get.admin/inventory/check',     3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000188, 31000000000000004, '盘点-创建',      'post.admin/inventory/check',    3, NULL, NULL, 8, NOW(), NOW()),
(31000000000000189, 31000000000000004, '盘点-更新',      'put.admin/inventory/check',     3, NULL, NULL, 9, NOW(), NOW()),
(31000000000000190, 31000000000000004, '盘点-删除',      'delete.admin/inventory/check',  3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000191, 31000000000000004, '预警-查看',      'get.admin/inventory/alert',     3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000192, 31000000000000004, '预警-创建',      'post.admin/inventory/alert',    3, NULL, NULL, 12, NOW(), NOW()),
(31000000000000193, 31000000000000004, '预警-更新',      'put.admin/inventory/alert',     3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000194, 31000000000000004, '预警-删除',      'delete.admin/inventory/alert',  3, NULL, NULL, 14, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 财务模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000201, 31000000000000005, '账户-查看',      'get.admin/finance/account',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000202, 31000000000000005, '账户-创建',      'post.admin/finance/account',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000203, 31000000000000005, '账户-更新',      'put.admin/finance/account',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000204, 31000000000000005, '账户-删除',      'delete.admin/finance/account', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000211, 31000000000000005, '凭证-查看',      'get.admin/finance/voucher',    3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000212, 31000000000000005, '凭证-创建',      'post.admin/finance/voucher',   3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000213, 31000000000000005, '凭证-更新',      'put.admin/finance/voucher',    3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000214, 31000000000000005, '凭证-删除',      'delete.admin/finance/voucher', 3, NULL, NULL, 8, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000221, 31000000000000005, '收款-查看',      'get.admin/finance/receipt',    3, NULL, NULL,  9, NOW(), NOW()),
(31000000000000222, 31000000000000005, '收款-创建',      'post.admin/finance/receipt',   3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000223, 31000000000000005, '收款-更新',      'put.admin/finance/receipt',    3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000224, 31000000000000005, '收款-删除',      'delete.admin/finance/receipt', 3, NULL, NULL, 12, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000231, 31000000000000005, '付款-查看',      'get.admin/finance/payment',    3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000232, 31000000000000005, '付款-创建',      'post.admin/finance/payment',   3, NULL, NULL, 14, NOW(), NOW()),
(31000000000000233, 31000000000000005, '付款-更新',      'put.admin/finance/payment',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000234, 31000000000000005, '付款-删除',      'delete.admin/finance/payment', 3, NULL, NULL, 16, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000241, 31000000000000005, '现金日记账',     'any.admin/finance/cash-journal',    3, NULL, NULL, 17, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000251, 31000000000000005, '费用-查看',      'get.admin/finance/expense',    3, NULL, NULL, 18, NOW(), NOW()),
(31000000000000252, 31000000000000005, '费用-创建',      'post.admin/finance/expense',   3, NULL, NULL, 19, NOW(), NOW()),
(31000000000000253, 31000000000000005, '费用-更新',      'put.admin/finance/expense',    3, NULL, NULL, 20, NOW(), NOW()),
(31000000000000254, 31000000000000005, '费用-删除',      'delete.admin/finance/expense', 3, NULL, NULL, 21, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000261, 31000000000000005, '利润报表',       'any.admin/finance/report/profit',    3, NULL, NULL, 22, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000271, 31000000000000005, '银行账户-查看',  'get.admin/finance/bank-account',    3, NULL, NULL, 23, NOW(), NOW()),
(31000000000000272, 31000000000000005, '银行账户-创建',  'post.admin/finance/bank-account',   3, NULL, NULL, 24, NOW(), NOW()),
(31000000000000273, 31000000000000005, '银行账户-更新',  'put.admin/finance/bank-account',    3, NULL, NULL, 25, NOW(), NOW()),
(31000000000000274, 31000000000000005, '银行账户-删除',  'delete.admin/finance/bank-account', 3, NULL, NULL, 26, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — CRM 模块
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000281, 31000000000000006, '商机-查看',      'get.admin/crm/opportunity',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000282, 31000000000000006, '商机-创建',      'post.admin/crm/opportunity',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000283, 31000000000000006, '商机-更新',      'put.admin/crm/opportunity',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000284, 31000000000000006, '商机-删除',      'delete.admin/crm/opportunity', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000291, 31000000000000006, '跟进记录-查看',  'get.admin/crm/follow',    3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000292, 31000000000000006, '跟进记录-创建',  'post.admin/crm/follow',   3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000293, 31000000000000006, '跟进记录-更新',  'put.admin/crm/follow',    3, NULL, NULL, 7, NOW(), NOW()),
(31000000000000294, 31000000000000006, '跟进记录-删除',  'delete.admin/crm/follow', 3, NULL, NULL, 8, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000301, 31000000000000006, '销售漏斗-查看',  'get.admin/crm/funnel',    3, NULL, NULL,  9, NOW(), NOW()),
(31000000000000302, 31000000000000006, '销售漏斗-创建',  'post.admin/crm/funnel',   3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000303, 31000000000000006, '销售漏斗-更新',  'put.admin/crm/funnel',    3, NULL, NULL, 11, NOW(), NOW()),
(31000000000000304, 31000000000000006, '销售漏斗-删除',  'delete.admin/crm/funnel', 3, NULL, NULL, 12, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000311, 31000000000000006, '联系人-查看',    'get.admin/crm/contact',    3, NULL, NULL, 13, NOW(), NOW()),
(31000000000000312, 31000000000000006, '联系人-创建',    'post.admin/crm/contact',   3, NULL, NULL, 14, NOW(), NOW()),
(31000000000000313, 31000000000000006, '联系人-更新',    'put.admin/crm/contact',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000314, 31000000000000006, '联系人-删除',    'delete.admin/crm/contact', 3, NULL, NULL, 16, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 仪表盘扩展
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000321, 21000000000000001, '销售仪表盘',   'any.admin/dashboard/sales',    3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000322, 21000000000000001, '库存仪表盘',   'any.admin/dashboard/inventory', 3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000323, 21000000000000001, '财务仪表盘',   'any.admin/dashboard/finance',   3, NULL, NULL, 4, NOW(), NOW());

-- ============================================================
-- 超级管理员角色 (ID=10000000000000001) 关联所有新增权限
-- ============================================================
INSERT INTO `erik_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erik_admin_permission`
WHERE `id` >= 31000000000000001 AND `id` <= 31000000000000323
  AND `id` NOT IN (
    SELECT `permission_id` FROM `erik_admin_role_permission` WHERE `role_id` = 10000000000000001
  );
