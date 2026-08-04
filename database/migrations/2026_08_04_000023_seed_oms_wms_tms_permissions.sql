-- ============================================================
-- OMS/WMS/TMS 模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 OMS/WMS/TMS 模块的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — OMS / WMS / TMS
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000201, NULL, '订单管理(OMS)', 'oms',   1, 'receipt_long',  '/admin/oms',  13, NOW(), NOW()),
(31000000000000202, NULL, '仓储管理(WMS)', 'wms',   1, 'warehouse',     '/admin/wms',  14, NOW(), NOW()),
(31000000000000203, NULL, '运输管理(TMS)', 'tms',   1, 'local_shipping','/admin/tms',  15, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — OMS 订单管理
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000301, 31000000000000201, 'OMS订单-查看',   'get.admin/oms/order',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000302, 31000000000000201, 'OMS订单-创建',   'post.admin/oms/order',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000303, 31000000000000201, 'OMS订单-更新',   'put.admin/oms/order',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000304, 31000000000000201, 'OMS订单-删除',   'delete.admin/oms/order', 3, NULL, NULL, 4, NOW(), NOW()),
(31000000000000305, 31000000000000201, 'OMS订单-分配',   'post.admin/oms/order/allocate', 3, NULL, NULL, 5, NOW(), NOW()),
(31000000000000306, 31000000000000201, 'OMS订单-履约',   'post.admin/oms/order/fulfill',  3, NULL, NULL, 6, NOW(), NOW()),
(31000000000000307, 31000000000000201, 'OMS订单-取消',   'post.admin/oms/order/cancel',   3, NULL, NULL, 7, NOW(), NOW());

-- OMS 履约管理
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000311, 31000000000000201, '履约-查看',   'get.admin/oms/fulfillment',    3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000312, 31000000000000201, '履约-创建',   'post.admin/oms/fulfillment',   3, NULL, NULL, 11, NOW(), NOW());

-- OMS RMA
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000321, 31000000000000201, 'RMA-查看',   'get.admin/oms/rma',       3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000322, 31000000000000201, 'RMA-创建',   'post.admin/oms/rma',      3, NULL, NULL, 16, NOW(), NOW()),
(31000000000000323, 31000000000000201, 'RMA-更新',   'put.admin/oms/rma',       3, NULL, NULL, 17, NOW(), NOW()),
(31000000000000324, 31000000000000201, 'RMA-删除',   'delete.admin/oms/rma',    3, NULL, NULL, 18, NOW(), NOW()),
(31000000000000325, 31000000000000201, 'RMA-审批',   'post.admin/oms/rma/approve',  3, NULL, NULL, 19, NOW(), NOW()),
(31000000000000326, 31000000000000201, 'RMA-收货',   'post.admin/oms/rma/receive',  3, NULL, NULL, 20, NOW(), NOW()),
(31000000000000327, 31000000000000201, 'RMA-退款',   'post.admin/oms/rma/refund',   3, NULL, NULL, 21, NOW(), NOW());

-- OMS 渠道管理
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000331, 31000000000000201, '渠道-查看',   'get.admin/oms/channel',    3, NULL, NULL, 25, NOW(), NOW()),
(31000000000000332, 31000000000000201, '渠道-创建',   'post.admin/oms/channel',   3, NULL, NULL, 26, NOW(), NOW()),
(31000000000000333, 31000000000000201, '渠道-更新',   'put.admin/oms/channel',    3, NULL, NULL, 27, NOW(), NOW()),
(31000000000000334, 31000000000000201, '渠道-删除',   'delete.admin/oms/channel', 3, NULL, NULL, 28, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — WMS 仓储管理
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000401, 31000000000000202, '库区-查看',   'get.admin/wms/zone',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000402, 31000000000000202, '库区-创建',   'post.admin/wms/zone',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000403, 31000000000000202, '库区-更新',   'put.admin/wms/zone',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000404, 31000000000000202, '库区-删除',   'delete.admin/wms/zone', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000411, 31000000000000202, 'WMS库位-查看',   'get.admin/wms/location',    3, NULL, NULL, 8, NOW(), NOW()),
(31000000000000412, 31000000000000202, 'WMS库位-创建',   'post.admin/wms/location',   3, NULL, NULL, 9, NOW(), NOW()),
(31000000000000413, 31000000000000202, 'WMS库位-更新',   'put.admin/wms/location',    3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000414, 31000000000000202, 'WMS库位-删除',   'delete.admin/wms/location', 3, NULL, NULL, 11, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000421, 31000000000000202, 'ASN-查看',   'get.admin/wms/asn',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000422, 31000000000000202, 'ASN-创建',   'post.admin/wms/asn',   3, NULL, NULL, 16, NOW(), NOW()),
(31000000000000423, 31000000000000202, 'ASN-更新',   'put.admin/wms/asn',    3, NULL, NULL, 17, NOW(), NOW()),
(31000000000000424, 31000000000000202, 'ASN-删除',   'delete.admin/wms/asn', 3, NULL, NULL, 18, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000431, 31000000000000202, '收货-查看',   'get.admin/wms/receiving',           3, NULL, NULL, 22, NOW(), NOW()),
(31000000000000432, 31000000000000202, '收货-创建',   'post.admin/wms/receiving',          3, NULL, NULL, 23, NOW(), NOW()),
(31000000000000433, 31000000000000202, '收货-完成',   'post.admin/wms/receiving/complete', 3, NULL, NULL, 24, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000441, 31000000000000202, '上架-查看',   'get.admin/wms/putaway',           3, NULL, NULL, 28, NOW(), NOW()),
(31000000000000442, 31000000000000202, '上架-创建',   'post.admin/wms/putaway',          3, NULL, NULL, 29, NOW(), NOW()),
(31000000000000443, 31000000000000202, '上架-完成',   'post.admin/wms/putaway/complete', 3, NULL, NULL, 30, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000451, 31000000000000202, '波次-查看',   'get.admin/wms/wave',          3, NULL, NULL, 35, NOW(), NOW()),
(31000000000000452, 31000000000000202, '波次-创建',   'post.admin/wms/wave',         3, NULL, NULL, 36, NOW(), NOW()),
(31000000000000453, 31000000000000202, '波次-释放',   'post.admin/wms/wave/release', 3, NULL, NULL, 37, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000461, 31000000000000202, '拣货-查看',   'get.admin/wms/pick',             3, NULL, NULL, 42, NOW(), NOW()),
(31000000000000462, 31000000000000202, '拣货-开始',   'post.admin/wms/pick/start',      3, NULL, NULL, 43, NOW(), NOW()),
(31000000000000463, 31000000000000202, '拣货-确认',   'post.admin/wms/pick/confirm',    3, NULL, NULL, 44, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000471, 31000000000000202, '打包-查看',   'get.admin/wms/pack',             3, NULL, NULL, 48, NOW(), NOW()),
(31000000000000472, 31000000000000202, '打包-开始',   'post.admin/wms/pack/start',      3, NULL, NULL, 49, NOW(), NOW()),
(31000000000000473, 31000000000000202, '打包-完成',   'post.admin/wms/pack/complete',   3, NULL, NULL, 50, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — TMS 运输管理
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000501, 31000000000000203, '承运商-查看',   'get.admin/tms/carrier',    3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000502, 31000000000000203, '承运商-创建',   'post.admin/tms/carrier',   3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000503, 31000000000000203, '承运商-更新',   'put.admin/tms/carrier',    3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000504, 31000000000000203, '承运商-删除',   'delete.admin/tms/carrier', 3, NULL, NULL, 4, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000511, 31000000000000203, '承运商服务-查看',   'get.admin/tms/service',    3, NULL, NULL, 8, NOW(), NOW()),
(31000000000000512, 31000000000000203, '承运商服务-创建',   'post.admin/tms/service',   3, NULL, NULL, 9, NOW(), NOW()),
(31000000000000513, 31000000000000203, '承运商服务-更新',   'put.admin/tms/service',    3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000514, 31000000000000203, '承运商服务-删除',   'delete.admin/tms/service', 3, NULL, NULL, 11, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000521, 31000000000000203, '运费费率-查看',   'get.admin/tms/freight-rate',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000522, 31000000000000203, '运费费率-创建',   'post.admin/tms/freight-rate',   3, NULL, NULL, 16, NOW(), NOW()),
(31000000000000523, 31000000000000203, '运费费率-更新',   'put.admin/tms/freight-rate',    3, NULL, NULL, 17, NOW(), NOW()),
(31000000000000524, 31000000000000203, '运费费率-删除',   'delete.admin/tms/freight-rate', 3, NULL, NULL, 18, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000531, 31000000000000203, '运单-查看',   'get.admin/tms/shipment',           3, NULL, NULL, 22, NOW(), NOW()),
(31000000000000532, 31000000000000203, '运单-创建',   'post.admin/tms/shipment',          3, NULL, NULL, 23, NOW(), NOW()),
(31000000000000533, 31000000000000203, '运单-发货',   'post.admin/tms/shipment/ship',     3, NULL, NULL, 24, NOW(), NOW()),
(31000000000000534, 31000000000000203, '运单-面单',   'post.admin/tms/shipment/get-label',3, NULL, NULL, 25, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000541, 31000000000000203, '轨迹-查看',   'get.admin/tms/tracking',    3, NULL, NULL, 30, NOW(), NOW()),
(31000000000000542, 31000000000000203, '轨迹-回调',   'post.admin/tms/tracking/callback', 3, NULL, NULL, 31, NOW(), NOW());

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000551, 31000000000000203, '运费发票-查看',   'get.admin/tms/freight-invoice',    3, NULL, NULL, 35, NOW(), NOW()),
(31000000000000552, 31000000000000203, '运费发票-创建',   'post.admin/tms/freight-invoice',   3, NULL, NULL, 36, NOW(), NOW()),
(31000000000000553, 31000000000000203, '运费发票-确认',   'post.admin/tms/freight-invoice/confirm', 3, NULL, NULL, 37, NOW(), NOW()),
(31000000000000554, 31000000000000203, '运费发票-付款',   'post.admin/tms/freight-invoice/pay',     3, NULL, NULL, 38, NOW(), NOW());
