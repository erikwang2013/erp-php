-- ============================================================
-- 回滚文件: 2026_05_22_000012_finance_expansion_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 财务扩展 — 固定资产/税务/多币种/预算/成本利润中心（11 张）
-- 回滚内容: 删除本迁移创建的 11 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_05_22_000012_finance_expansion_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_finance_allocation`;
DROP TABLE IF EXISTS `erik_finance_profit_center`;
DROP TABLE IF EXISTS `erik_finance_cost_center`;
DROP TABLE IF EXISTS `erik_finance_budget_item`;
DROP TABLE IF EXISTS `erik_finance_budget`;
DROP TABLE IF EXISTS `erik_finance_exchange_rate`;
DROP TABLE IF EXISTS `erik_finance_currency`;
DROP TABLE IF EXISTS `erik_finance_tax_record`;
DROP TABLE IF EXISTS `erik_finance_tax_rate`;
DROP TABLE IF EXISTS `erik_finance_asset_depreciation`;
DROP TABLE IF EXISTS `erik_finance_asset`;

SET FOREIGN_KEY_CHECKS = 1;

-- 数据回滚需业务层处理！
-- 本迁移在 erik_finance_tax_rate（id 60000000000000001~3）与 erik_finance_currency（id 61000000000000001~4）插入种子数据，删除表时一并删除。
