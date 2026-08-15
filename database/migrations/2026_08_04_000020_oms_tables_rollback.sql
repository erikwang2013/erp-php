-- ============================================================
-- 回滚文件: 2026_08_04_000020_oms_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: OMS 订单管理系统表（8 张）
-- 回滚内容: 删除本迁移创建的 8 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_04_000020_oms_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_channel`;
DROP TABLE IF EXISTS `erik_oms_inventory_reservation`;
DROP TABLE IF EXISTS `erik_oms_rma_item`;
DROP TABLE IF EXISTS `erik_oms_rma`;
DROP TABLE IF EXISTS `erik_oms_fulfillment_item`;
DROP TABLE IF EXISTS `erik_oms_fulfillment`;
DROP TABLE IF EXISTS `erik_oms_order_address`;
DROP TABLE IF EXISTS `erik_oms_order`;

SET FOREIGN_KEY_CHECKS = 1;
