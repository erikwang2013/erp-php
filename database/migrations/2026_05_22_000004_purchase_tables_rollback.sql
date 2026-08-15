-- ============================================================
-- 回滚文件: 2026_05_22_000004_purchase_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 采购模块表（9 张）
-- 回滚内容: 删除本迁移创建的 9 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_05_22_000004_purchase_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_purchase_settlement`;
DROP TABLE IF EXISTS `erik_purchase_return_item`;
DROP TABLE IF EXISTS `erik_purchase_return`;
DROP TABLE IF EXISTS `erik_purchase_receive_item`;
DROP TABLE IF EXISTS `erik_purchase_receive`;
DROP TABLE IF EXISTS `erik_purchase_order_item`;
DROP TABLE IF EXISTS `erik_purchase_order`;
DROP TABLE IF EXISTS `erik_purchase_apply_item`;
DROP TABLE IF EXISTS `erik_purchase_apply`;

SET FOREIGN_KEY_CHECKS = 1;
