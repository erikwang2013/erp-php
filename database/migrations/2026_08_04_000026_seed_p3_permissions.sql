-- ============================================================
-- P3 体验增强模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 BI看板 / 设备管理(EAM) / 文档管理(DMS) 的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — BI看板 / 设备管理 / 文档管理
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000205, 0, 'BI看板', 'bi', 1, 'dashboard_customize', '/admin/bi', 17, NOW(), NOW()),
(31000000000000206, 0, '设备管理(EAM)', 'eam', 1, 'build', '/admin/eam', 18, NOW(), NOW()),
(31000000000000207, 0, '文档管理(DMS)', 'dms', 1, 'folder', '/admin/dms', 19, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — BI 看板
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000701, 31000000000000205, 'BI看板-查看',   'get.admin/bi/dashboard', 3, '', '', 1, NOW(), NOW()),
(31000000000000702, 31000000000000205, 'BI看板-创建',   'post.admin/bi/dashboard', 3, '', '', 2, NOW(), NOW()),
(31000000000000703, 31000000000000205, 'BI看板-更新',   'put.admin/bi/dashboard', 3, '', '', 3, NOW(), NOW()),
(31000000000000704, 31000000000000205, 'BI看板-删除',   'delete.admin/bi/dashboard', 3, '', '', 4, NOW(), NOW()),
(31000000000000705, 31000000000000205, '看板组件-查看', 'get.admin/bi/widget', 3, '', '', 5, NOW(), NOW()),
(31000000000000706, 31000000000000205, '看板组件-创建', 'post.admin/bi/widget', 3, '', '', 6, NOW(), NOW()),
(31000000000000707, 31000000000000205, '看板组件-更新', 'put.admin/bi/widget', 3, '', '', 7, NOW(), NOW()),
(31000000000000708, 31000000000000205, '看板组件-删除', 'delete.admin/bi/widget', 3, '', '', 8, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 设备管理 (EAM)
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000711, 31000000000000206, '设备台账-查看',   'get.admin/eam/equipment', 3, '', '', 1, NOW(), NOW()),
(31000000000000712, 31000000000000206, '设备台账-创建',   'post.admin/eam/equipment', 3, '', '', 2, NOW(), NOW()),
(31000000000000713, 31000000000000206, '设备台账-更新',   'put.admin/eam/equipment', 3, '', '', 3, NOW(), NOW()),
(31000000000000714, 31000000000000206, '设备台账-删除',   'delete.admin/eam/equipment', 3, '', '', 4, NOW(), NOW()),
(31000000000000715, 31000000000000206, '保养计划-查看',   'get.admin/eam/maintenance', 3, '', '', 5, NOW(), NOW()),
(31000000000000716, 31000000000000206, '保养计划-创建',   'post.admin/eam/maintenance', 3, '', '', 6, NOW(), NOW()),
(31000000000000717, 31000000000000206, '保养计划-更新',   'put.admin/eam/maintenance', 3, '', '', 7, NOW(), NOW()),
(31000000000000718, 31000000000000206, '保养计划-删除',   'delete.admin/eam/maintenance', 3, '', '', 8, NOW(), NOW()),
(31000000000000719, 31000000000000206, '维修工单-查看',   'get.admin/eam/repair', 3, '', '', 9, NOW(), NOW()),
(31000000000000720, 31000000000000206, '维修工单-创建',   'post.admin/eam/repair', 3, '', '', 10, NOW(), NOW()),
(31000000000000721, 31000000000000206, '维修工单-更新',   'put.admin/eam/repair', 3, '', '', 11, NOW(), NOW()),
(31000000000000722, 31000000000000206, '维修工单-删除',   'delete.admin/eam/repair', 3, '', '', 12, NOW(), NOW()),
(31000000000000723, 31000000000000206, '维修工单-状态流转', 'post.admin/eam/repair/{id}/transition', 3, '', '', 13, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 文档管理 (DMS)
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000731, 31000000000000207, '文档-查看',   'get.admin/dms/document', 3, '', '', 1, NOW(), NOW()),
(31000000000000732, 31000000000000207, '文档-创建',   'post.admin/dms/document', 3, '', '', 2, NOW(), NOW()),
(31000000000000733, 31000000000000207, '文档-更新',   'put.admin/dms/document', 3, '', '', 3, NOW(), NOW()),
(31000000000000734, 31000000000000207, '文档-删除',   'delete.admin/dms/document', 3, '', '', 4, NOW(), NOW()),
(31000000000000735, 31000000000000207, '文档分类-查看', 'get.admin/dms/categories', 3, '', '', 5, NOW(), NOW());

-- ============================================================
-- 超级管理员角色 (ID=10000000000000001) 关联所有新增权限
-- ============================================================
INSERT INTO `erik_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erik_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erik_admin_role_permission` WHERE `role_id` = 10000000000000001
);
