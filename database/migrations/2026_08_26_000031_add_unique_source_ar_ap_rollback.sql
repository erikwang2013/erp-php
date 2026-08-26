-- ============================================================
-- 回滚文件: 2026_08_26_000031_add_unique_source_ar_ap.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 应收应付来源唯一索引（含存量重复数据去重前置）
-- 回滚内容: 删除 uk_source 唯一索引
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_26_000031_add_unique_source_ar_ap_rollback.sql
-- 注意: 回滚仅删除唯一索引；正向迁移中的去重（删除重复记录并改指核销记录）
--       不可回滚，被删记录无法恢复，回滚前请确认无需保留。
-- ============================================================

ALTER TABLE `erik_finance_ar_ap`
    DROP KEY `uk_source`;
