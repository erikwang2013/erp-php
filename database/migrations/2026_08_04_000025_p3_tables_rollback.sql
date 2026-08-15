-- ============================================================
-- 回滚文件: 2026_08_04_000025_p3_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: P3 体验增强表 — BI/EAM/DMS（7 张）
-- 回滚内容: 删除本迁移创建的 7 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_04_000025_p3_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_dms_document_version`;
DROP TABLE IF EXISTS `erik_dms_document`;
DROP TABLE IF EXISTS `erik_eam_repair_order`;
DROP TABLE IF EXISTS `erik_eam_maintenance_plan`;
DROP TABLE IF EXISTS `erik_eam_equipment`;
DROP TABLE IF EXISTS `erik_bi_widget`;
DROP TABLE IF EXISTS `erik_bi_dashboard`;

SET FOREIGN_KEY_CHECKS = 1;
