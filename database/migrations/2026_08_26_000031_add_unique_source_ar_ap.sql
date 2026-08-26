-- ============================================================
-- 迁移文件: 2026_08_26_000031_add_unique_source_ar_ap.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 应收应付来源唯一索引
-- 目的: erik_finance_ar_ap 增加 (source_type, source_id) 唯一索引，
--       防止并发 createAr/createAp 对同一来源单据重复建应收应付记录
--       （应用层 exists() 检查之外的最后防线，冲突由 FinanceService 捕获转业务异常）
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_26_000031_add_unique_source_ar_ap.sql
-- ============================================================

ALTER TABLE `erik_finance_ar_ap`
    ADD UNIQUE KEY `uk_source` (`source_type`, `source_id`);
