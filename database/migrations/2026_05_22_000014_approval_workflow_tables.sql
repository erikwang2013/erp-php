-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 审批工作流引擎

CREATE TABLE IF NOT EXISTS `erik_approval_workflow` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL COMMENT '流程编码',
    `name` VARCHAR(100) NOT NULL COMMENT '流程名称',
    `target_type` VARCHAR(30) NOT NULL COMMENT '适用单据: purchase_apply/purchase_order/expense/leave/other',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `remark` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`), KEY `idx_target_type` (`target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批工作流模板表';

CREATE TABLE IF NOT EXISTS `erik_approval_node` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `workflow_id` BIGINT UNSIGNED NOT NULL COMMENT '工作流ID',
    `name` VARCHAR(50) NOT NULL COMMENT '节点名称',
    `approver_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1指定人2角色3部门负责人4直属上级',
    `approver_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批人ID(approver_type=1时)',
    `role_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID(approver_type=2时)',
    `seq` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批顺序',
    `condition_field` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '条件字段: amount/department',
    `condition_op` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '条件操作符: gt/gte/lt/lte/eq',
    `condition_value` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '条件值',
    `can_reject` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '可否驳回',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_workflow_id` (`workflow_id`), KEY `idx_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批节点表';

CREATE TABLE IF NOT EXISTS `erik_approval_instance` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `workflow_id` BIGINT UNSIGNED NOT NULL COMMENT '工作流ID',
    `target_type` VARCHAR(30) NOT NULL COMMENT '单据类型',
    `target_id` BIGINT UNSIGNED NOT NULL COMMENT '单据ID',
    `submitter_id` BIGINT UNSIGNED NOT NULL COMMENT '提交人ID',
    `current_node_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前审批节点ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0审批中1已通过2已驳回3已撤回',
    `submitted_at` DATETIME DEFAULT NULL COMMENT '提交时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_target` (`target_type`, `target_id`),
    KEY `idx_workflow_id` (`workflow_id`),
    KEY `idx_submitter_id` (`submitter_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批实例表';

CREATE TABLE IF NOT EXISTS `erik_approval_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `instance_id` BIGINT UNSIGNED NOT NULL COMMENT '审批实例ID',
    `node_id` BIGINT UNSIGNED NOT NULL COMMENT '审批节点ID',
    `approver_id` BIGINT UNSIGNED NOT NULL COMMENT '审批人ID',
    `action` TINYINT UNSIGNED NOT NULL COMMENT '1通过2驳回3转交',
    `comment` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '审批意见',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '审批时间',
    PRIMARY KEY (`id`),
    KEY `idx_instance_id` (`instance_id`),
    KEY `idx_approver_id` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批记录表';
