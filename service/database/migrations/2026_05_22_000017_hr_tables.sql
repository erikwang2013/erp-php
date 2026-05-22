-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 人力资源管理表（8张表）
-- 包含: 部门/职位/员工/考勤规则/考勤记录/请假/薪资/薪资项
-- 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- 部门表（树形结构）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_department` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级部门ID，0表示顶级',
    `code` VARCHAR(50) NOT NULL COMMENT '部门编码',
    `name` VARCHAR(100) NOT NULL COMMENT '部门名称',
    `manager_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门负责人用户ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

-- ============================================================
-- 职位表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_position` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属部门ID',
    `code` VARCHAR(50) NOT NULL COMMENT '职位编码',
    `name` VARCHAR(100) NOT NULL COMMENT '职位名称',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职级，值越大级别越高',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='职位表';

-- ============================================================
-- 员工表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_employee` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '员工编码',
    `name` VARCHAR(50) NOT NULL COMMENT '员工姓名',
    `department_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID',
    `position_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职位ID',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '性别: 1=男 2=女',
    `birthday` DATE DEFAULT NULL COMMENT '出生日期',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '电子邮箱（加密存储）',
    `id_card` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证号（加密存储）',
    `hire_date` DATE DEFAULT NULL COMMENT '入职日期',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=在职 2=离职 3=停职',
    `bank_account` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '银行账号（加密存储）',
    `emergency_contact` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '紧急联系人',
    `emergency_phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '紧急联系电话（加密存储）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_position_id` (`position_id`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工表';

-- ============================================================
-- 考勤规则表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_attendance_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '规则名称',
    `clock_in_time` TIME NOT NULL COMMENT '上班打卡时间',
    `clock_out_time` TIME NOT NULL COMMENT '下班打卡时间',
    `late_grace` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '迟到宽限分钟数',
    `early_grace` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '早退宽限分钟数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤规则表';

-- ============================================================
-- 考勤记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_attendance` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `rule_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '考勤规则ID',
    `work_date` DATE NOT NULL COMMENT '工作日期',
    `clock_in` DATETIME DEFAULT NULL COMMENT '上班打卡时间',
    `clock_out` DATETIME DEFAULT NULL COMMENT '下班打卡时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=正常 2=迟到 3=早退 4=缺卡 5=请假 6=出差',
    `late_minutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '迟到分钟数',
    `early_minutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '早退分钟数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_work_date` (`work_date`),
    KEY `idx_employee_date` (`employee_id`, `work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤记录表';

-- ============================================================
-- 请假表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_leave` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '请假类型: 1=年假 2=事假 3=病假 4=婚假 5=产假 6=调休',
    `start_date` DATE NOT NULL COMMENT '开始日期',
    `end_date` DATE NOT NULL COMMENT '结束日期',
    `days` DECIMAL(4,1) NOT NULL DEFAULT 0.0 COMMENT '请假天数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批状态: 0=待审批 1=已批准 2=已驳回',
    `reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '请假原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_start_date` (`start_date`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='请假表';

-- ============================================================
-- 薪资表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_salary` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    `period_year` INT UNSIGNED NOT NULL COMMENT '薪资年份',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '薪资月份',
    `base_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '基本工资',
    `performance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '绩效工资',
    `overtime` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '加班工资',
    `deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '扣款合计',
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '个人所得税',
    `net_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '实发工资',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发放',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_period` (`period_year`, `period_month`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='薪资表';

-- ============================================================
-- 薪资项表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_hr_salary_item` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(50) NOT NULL COMMENT '薪资项编码',
    `name` VARCHAR(100) NOT NULL COMMENT '薪资项名称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=收入 2=扣除',
    `is_taxable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否计税: 0=否 1=是',
    `default_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '默认金额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='薪资项表';
