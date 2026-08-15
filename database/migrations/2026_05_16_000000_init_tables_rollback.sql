-- ============================================================
-- 回滚文件: 2026_05_16_000000_init_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 初始化管理后台核心数据表（7 张）
-- 回滚内容: 删除本迁移创建的 7 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_05_16_000000_init_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_operation_log`;
DROP TABLE IF EXISTS `erik_system_config`;
DROP TABLE IF EXISTS `erik_admin_role_permission`;
DROP TABLE IF EXISTS `erik_admin_user_role`;
DROP TABLE IF EXISTS `erik_admin_permission`;
DROP TABLE IF EXISTS `erik_admin_role`;
DROP TABLE IF EXISTS `erik_admin_user`;

SET FOREIGN_KEY_CHECKS = 1;

-- 数据回滚需业务层处理！
-- 本迁移在 erik_admin_role 插入超级管理员（id=10000000000000001），删除表时该数据一并删除。
