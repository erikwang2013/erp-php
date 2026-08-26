-- ============================================================
-- 迁移文件: 2026_08_26_000031_add_unique_source_ar_ap.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 应收应付来源唯一索引（含存量重复数据去重前置）
-- 目的: erik_finance_ar_ap 增加 (source_type, source_id) 唯一索引，
--       防止并发 createAr/createAp 对同一来源单据重复建应收应付记录
--       （应用层 exists() 检查之外的最后防线，冲突由 FinanceService 捕获转业务异常）。
-- 前置去重: 存量库中可能存在重复 (source_type, source_id) 记录，直接加唯一索引会失败，
--       故先做去重 —— 每组重复保留 settled_amount 最大（并列取 id 最小）的一条为主记录，
--       将 erik_finance_settlement.ar_ap_id 从被删记录改指主记录后删除其余记录。
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_26_000031_add_unique_source_ar_ap.sql
-- ============================================================

-- 1. 找出每个重复分组中的待删记录及其主记录 keep_id
--    （主记录 = settled_amount 最大、并列取 id 最小；临时表每条语句只引用一次，
--    规避 MySQL 1137 Can't reopen table 限制）
CREATE TEMPORARY TABLE `tmp_ar_ap_dup` AS
SELECT `id`, `keep_id`
FROM (
    SELECT a.`id`,
           FIRST_VALUE(a.`id`) OVER (
               PARTITION BY a.`source_type`, a.`source_id`
               ORDER BY a.`settled_amount` DESC, a.`id` ASC
           ) AS `keep_id`
    FROM `erik_finance_ar_ap` a
    JOIN (
        SELECT `source_type`, `source_id`
        FROM `erik_finance_ar_ap`
        GROUP BY `source_type`, `source_id`
        HAVING COUNT(*) > 1
    ) d ON a.`source_type` = d.`source_type` AND a.`source_id` = d.`source_id`
) t
WHERE `id` <> `keep_id`;

-- 2. 核销记录从被删记录改指主记录
UPDATE `erik_finance_settlement` s
JOIN `tmp_ar_ap_dup` del ON s.`ar_ap_id` = del.`id`
SET s.`ar_ap_id` = del.`keep_id`;

-- 3. 删除重复记录（多表 DELETE 规避自引用子查询限制）
DELETE a FROM `erik_finance_ar_ap` a
JOIN `tmp_ar_ap_dup` del ON a.`id` = del.`id`;

DROP TEMPORARY TABLE `tmp_ar_ap_dup`;

-- 4. 加唯一索引
ALTER TABLE `erik_finance_ar_ap`
    ADD UNIQUE KEY `uk_source` (`source_type`, `source_id`);
