-- open-erp schema upgrade — 2026-10-30
--
-- 仅用于已部署数据库；全新安装直接执行 database/install.sql 即可（已包含本文件内容）。
-- 用法（先备份）：mysql -u <user> -p <database> < database/upgrade-2026-10-30.sql
--
-- 索引部分幂等：已存在的同名索引会跳过。
-- 薪资唯一键：若 erp_hr_salary 已有同员工同月重复行，本脚本会中止（SIGNAL），
-- 请先人工确认保留哪一行、去重后再重跑。不会自动删除工资数据。

-- ============================================================
-- 1. 索引/唯一键：存在则跳过
-- ============================================================
DROP PROCEDURE IF EXISTS _erp_add_index_if_missing;
DELIMITER //
CREATE PROCEDURE _erp_add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255),
    IN p_unique TINYINT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND index_name = p_index
    ) THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `', p_table, '` ADD ',
            IF(p_unique = 1, 'UNIQUE ', ''),
            'KEY `', p_index, '` (', p_columns, ')'
        );
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- 2. 薪资唯一键：先检查重复，再添加
-- ============================================================
DROP PROCEDURE IF EXISTS _erp_assert_no_duplicate_salary;
DELIMITER //
CREATE PROCEDURE _erp_assert_no_duplicate_salary()
BEGIN
    DECLARE dup_count INT DEFAULT 0;
    SELECT COUNT(*) INTO dup_count FROM (
        SELECT 1
        FROM erp_hr_salary
        GROUP BY employee_id, period_year, period_month
        HAVING COUNT(*) > 1
    ) AS duplicates;
    IF dup_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'erp_hr_salary 存在同员工同月重复行，请先人工去重再执行本升级脚本';
    END IF;
END//
DELIMITER ;

CALL _erp_assert_no_duplicate_salary();
CALL _erp_add_index_if_missing(
    'erp_hr_salary',
    'uk_employee_period',
    '`employee_id`,`period_year`,`period_month`',
    1
);

-- ============================================================
-- 3. 仪表盘/报表日期索引（whereDate→半开区间后生效）
-- ============================================================
CALL _erp_add_index_if_missing('erp_sales_order', 'idx_ordered_at', '`ordered_at`', 0);
CALL _erp_add_index_if_missing('erp_purchase_order', 'idx_ordered_at', '`ordered_at`', 0);
CALL _erp_add_index_if_missing('erp_finance_receipt', 'idx_received_at', '`received_at`', 0);
CALL _erp_add_index_if_missing('erp_finance_payment', 'idx_paid_at', '`paid_at`', 0);
CALL _erp_add_index_if_missing('erp_oms_order', 'idx_status_updated', '`fulfillment_status`,`updated_at`', 0);
CALL _erp_add_index_if_missing('erp_tms_shipment', 'idx_status_delivery', '`status`,`actual_delivery_at`', 0);

DROP PROCEDURE _erp_assert_no_duplicate_salary;
DROP PROCEDURE _erp_add_index_if_missing;

-- ============================================================
-- 4. 历史报表数据集（可选清理）
-- ============================================================
-- 修复前生成的 report_dataset 可能包含 SELECT * 或敏感表数据；result() 会按
-- query_sql 拒绝返回，但如需彻底清掉历史副本，确认后执行：
-- DELETE FROM erp_report_dataset;

-- ============================================================
-- 5. 银行账号历史明文
-- ============================================================
-- 新写入的 account_number 会由 EncryptableOrLegacy cast 加密；历史明文行不会由
-- SQL 自动加密。维护窗口内需用应用侧一次性脚本（读取原值→isEncrypted 为真跳过，
-- 否则加密后 UPDATE 原列），或逐条重新保存账户。
