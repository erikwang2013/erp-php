-- ============================================================
-- 回滚文件: 2026_05_22_000006_inventory_finance_crm_tables.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: 库存/财务/CRM 表（26 张）+ 漏斗阶段种子数据
-- 回滚内容: 删除本迁移创建的 26 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_05_22_000006_inventory_finance_crm_tables_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_crm_contact`;
DROP TABLE IF EXISTS `erik_crm_follow_record`;
DROP TABLE IF EXISTS `erik_crm_opportunity`;
DROP TABLE IF EXISTS `erik_crm_funnel_stage`;
DROP TABLE IF EXISTS `erik_finance_profit`;
DROP TABLE IF EXISTS `erik_finance_expense`;
DROP TABLE IF EXISTS `erik_finance_cash_journal`;
DROP TABLE IF EXISTS `erik_finance_settlement`;
DROP TABLE IF EXISTS `erik_finance_payment`;
DROP TABLE IF EXISTS `erik_finance_receipt`;
DROP TABLE IF EXISTS `erik_finance_bank_account`;
DROP TABLE IF EXISTS `erik_finance_ar_ap`;
DROP TABLE IF EXISTS `erik_finance_voucher_item`;
DROP TABLE IF EXISTS `erik_finance_voucher`;
DROP TABLE IF EXISTS `erik_finance_account`;
DROP TABLE IF EXISTS `erik_cost_record`;
DROP TABLE IF EXISTS `erik_inventory_alert_log`;
DROP TABLE IF EXISTS `erik_inventory_alert_rule`;
DROP TABLE IF EXISTS `erik_check_detail`;
DROP TABLE IF EXISTS `erik_check_task`;
DROP TABLE IF EXISTS `erik_transfer_item`;
DROP TABLE IF EXISTS `erik_transfer`;
DROP TABLE IF EXISTS `erik_inventory_flow`;
DROP TABLE IF EXISTS `erik_inventory_serial`;
DROP TABLE IF EXISTS `erik_inventory_batch`;
DROP TABLE IF EXISTS `erik_inventory`;

SET FOREIGN_KEY_CHECKS = 1;

-- 数据回滚需业务层处理！
-- 本迁移在 erik_crm_funnel_stage 插入 6 条阶段数据（id 50000000000000001~6），删除表时该数据一并删除。
