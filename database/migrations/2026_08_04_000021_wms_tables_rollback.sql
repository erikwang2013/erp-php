-- ============================================================
-- 回滚文件: 2026_08_04_000021_wms_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: WMS 仓储管理系统表（12 张）
-- 回滚内容: 删除本迁移创建的 12 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_04_000021_wms_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_wms_wave_order`;
DROP TABLE IF EXISTS `erik_wms_wave`;
DROP TABLE IF EXISTS `erik_wms_pack_task`;
DROP TABLE IF EXISTS `erik_wms_pick_item`;
DROP TABLE IF EXISTS `erik_wms_pick_task`;
DROP TABLE IF EXISTS `erik_wms_putaway_item`;
DROP TABLE IF EXISTS `erik_wms_putaway_task`;
DROP TABLE IF EXISTS `erik_wms_receiving`;
DROP TABLE IF EXISTS `erik_wms_asn_item`;
DROP TABLE IF EXISTS `erik_wms_asn`;
DROP TABLE IF EXISTS `erik_wms_location`;
DROP TABLE IF EXISTS `erik_wms_zone`;

SET FOREIGN_KEY_CHECKS = 1;
