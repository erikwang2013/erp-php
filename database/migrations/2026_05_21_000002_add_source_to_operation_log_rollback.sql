-- ============================================================
-- 回滚文件: 2026_05_21_000002_add_source_to_operation_log.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 操作日志表增加操作来源端字段（结构变更）
-- 回滚内容: 删除 erik_operation_log.source 列及其索引 idx_source
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 本文件
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

-- 逆序撤销迁移中的结构变更（先删索引，再删列；删除列时会自动级联删除其索引，此处显式写出以清晰表达）
ALTER TABLE `erik_operation_log` DROP KEY `idx_source`;
ALTER TABLE `erik_operation_log` DROP COLUMN `source`;
