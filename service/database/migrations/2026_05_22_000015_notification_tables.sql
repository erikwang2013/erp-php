-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 消息通知系统

CREATE TABLE IF NOT EXISTS `erik_notification` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '接收用户ID',
    `title` VARCHAR(200) NOT NULL COMMENT '通知标题',
    `content` TEXT COMMENT '通知内容',
    `type` VARCHAR(30) NOT NULL DEFAULT 'system' COMMENT '类型: system/approval/alert/reminder',
    `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源类型',
    `source_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '来源ID',
    `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未读1已读',
    `read_at` DATETIME DEFAULT NULL COMMENT '阅读时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知消息表';

CREATE TABLE IF NOT EXISTS `erik_notification_template` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `code` VARCHAR(50) NOT NULL COMMENT '模板编码',
    `name` VARCHAR(100) NOT NULL COMMENT '模板名称',
    `title_tpl` VARCHAR(200) NOT NULL COMMENT '标题模板',
    `content_tpl` TEXT COMMENT '内容模板(支持{变量})',
    `channels` VARCHAR(100) NOT NULL DEFAULT 'in_app' COMMENT '发送渠道: in_app,email,sms逗号分隔',
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知模板表';

CREATE TABLE IF NOT EXISTS `erik_notification_setting` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `notify_type` VARCHAR(30) NOT NULL COMMENT '通知类型',
    `in_app` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '站内通知',
    `email` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邮件通知',
    `sms` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '短信通知',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_type` (`user_id`, `notify_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知设置表';
