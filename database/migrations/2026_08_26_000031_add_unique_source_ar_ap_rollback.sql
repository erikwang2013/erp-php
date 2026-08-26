-- ============================================================
-- 回滚文件: 2026_08_26_000031_add_unique_source_ar_ap.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 应收应付来源唯一索引
-- 回滚内容: 删除 uk_source 唯一索引
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_26_000031_add_unique_source_ar_ap_rollback.sql
-- 注意: 回滚前若库中已存在重复 (source_type, source_id) 数据需先人工去重。
-- ============================================================

ALTER TABLE `erik_finance_ar_ap`
    DROP KEY `uk_source`;
