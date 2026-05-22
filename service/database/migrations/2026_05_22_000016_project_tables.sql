-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 项目管理

CREATE TABLE IF NOT EXISTS `erik_project` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '项目编号',
    `name` VARCHAR(200) NOT NULL COMMENT '项目名称',
    `customer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户ID',
    `manager_user_id` BIGINT UNSIGNED NOT NULL COMMENT '项目经理ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0规划中1进行中2已延期3已完成4已取消',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '1低2中3高4紧急',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `budget_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '项目预算',
    `actual_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '实际成本',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进度百分比0-100',
    `description` TEXT COMMENT '项目描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`),
    KEY `idx_status` (`status`), KEY `idx_manager` (`manager_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目表';

CREATE TABLE IF NOT EXISTS `erik_project_task` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父任务ID(WBS)',
    `name` VARCHAR(200) NOT NULL COMMENT '任务名称',
    `assignee_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '负责人ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待开始1进行中2已完成3已延期',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `start_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `estimated_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '预估工时',
    `actual_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT '实际工时',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进度0-100',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_assignee` (`assignee_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目任务表(WBS)';

CREATE TABLE IF NOT EXISTS `erik_project_member` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '成员ID',
    `role` VARCHAR(30) NOT NULL DEFAULT 'member' COMMENT '角色: manager/developer/tester/designer/viewer',
    `joined_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_project_user` (`project_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目成员表';

CREATE TABLE IF NOT EXISTS `erik_project_timesheet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `hours` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT '工时(小时)',
    `work_date` DATE NOT NULL COMMENT '工作日期',
    `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '工作内容',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_user_date` (`user_id`, `work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目工时记录表';

CREATE TABLE IF NOT EXISTS `erik_project_gantt` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
    `dependency_task_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '前置任务ID',
    `gantt_data` JSON COMMENT '甘特图数据JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_task` (`task_id`),
    KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='甘特图数据表';
