-- ============================================================
-- QMS质量管理模块权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 QMS 模块的菜单权限和 API 权限
-- 超级管理员 (super_admin) 自动获得所有新增权限
-- ============================================================

-- ============================================================
-- 菜单权限 (type=1) — QMS 质量管理
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000204, NULL, '质量管理(QMS)', 'quality', 1, 'verified_user', '/admin/quality', 16, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 检验标准
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000601, 31000000000000204, '检验标准-查看',   'get.admin/quality/standard',       3, NULL, NULL, 1, NOW(), NOW()),
(31000000000000602, 31000000000000204, '检验标准-创建',   'post.admin/quality/standard',      3, NULL, NULL, 2, NOW(), NOW()),
(31000000000000603, 31000000000000204, '检验标准-更新',   'put.admin/quality/standard',       3, NULL, NULL, 3, NOW(), NOW()),
(31000000000000604, 31000000000000204, '检验标准-删除',   'delete.admin/quality/standard',    3, NULL, NULL, 4, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 来料检验 (IQC)
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000611, 31000000000000204, 'IQC-查看',   'get.admin/quality/iqc',    3, NULL, NULL, 8, NOW(), NOW()),
(31000000000000612, 31000000000000204, 'IQC-创建',   'post.admin/quality/iqc',   3, NULL, NULL, 9, NOW(), NOW()),
(31000000000000613, 31000000000000204, 'IQC-更新',   'put.admin/quality/iqc',    3, NULL, NULL, 10, NOW(), NOW()),
(31000000000000614, 31000000000000204, 'IQC-删除',   'delete.admin/quality/iqc', 3, NULL, NULL, 11, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 过程检验 (IPQC)
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000621, 31000000000000204, 'IPQC-查看',   'get.admin/quality/ipqc',    3, NULL, NULL, 15, NOW(), NOW()),
(31000000000000622, 31000000000000204, 'IPQC-创建',   'post.admin/quality/ipqc',   3, NULL, NULL, 16, NOW(), NOW()),
(31000000000000623, 31000000000000204, 'IPQC-更新',   'put.admin/quality/ipqc',    3, NULL, NULL, 17, NOW(), NOW()),
(31000000000000624, 31000000000000204, 'IPQC-删除',   'delete.admin/quality/ipqc', 3, NULL, NULL, 18, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 出货检验 (OQC)
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000631, 31000000000000204, 'OQC-查看',   'get.admin/quality/oqc',    3, NULL, NULL, 22, NOW(), NOW()),
(31000000000000632, 31000000000000204, 'OQC-创建',   'post.admin/quality/oqc',   3, NULL, NULL, 23, NOW(), NOW()),
(31000000000000633, 31000000000000204, 'OQC-更新',   'put.admin/quality/oqc',    3, NULL, NULL, 24, NOW(), NOW()),
(31000000000000634, 31000000000000204, 'OQC-删除',   'delete.admin/quality/oqc', 3, NULL, NULL, 25, NOW(), NOW());

-- ============================================================
-- API 权限 (type=3) — 不合格品
-- ============================================================
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000641, 31000000000000204, '不合格品-查看',   'get.admin/quality/nonconformity',    3, NULL, NULL, 29, NOW(), NOW()),
(31000000000000642, 31000000000000204, '不合格品-创建',   'post.admin/quality/nonconformity',   3, NULL, NULL, 30, NOW(), NOW()),
(31000000000000643, 31000000000000204, '不合格品-更新',   'put.admin/quality/nonconformity',    3, NULL, NULL, 31, NOW(), NOW()),
(31000000000000644, 31000000000000204, '不合格品-删除',   'delete.admin/quality/nonconformity', 3, NULL, NULL, 32, NOW(), NOW());
