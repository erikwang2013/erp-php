-- Service wiring permission seeds
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(31000000000000740, 31000000000000005, '期末结转-执行',     'post.admin/finance/report/close-period',    3, NULL, NULL, 30, NOW(), NOW()),
(31000000000000741, 31000000000000005, '多币种合并-执行',   'post.admin/finance/report/consolidate',     3, NULL, NULL, 31, NOW(), NOW()),
(31000000000000742, 31000000000000005, '财务指标-计算',     'post.admin/finance/report/ratios',          3, NULL, NULL, 32, NOW(), NOW()),
(31000000000000743, 31000000000000005, '试算平衡-查看',     'get.admin/finance/report/trial-balance',    3, NULL, NULL, 33, NOW(), NOW()),
(31000000000000744, 31000000000000005, '科目余额-查看',     'get.admin/finance/report/account-balance',  3, NULL, NULL, 34, NOW(), NOW()),
(31000000000000745, 31000000000000203, '运费-计算',         'post.admin/tms/freight-rate/calculate',     3, NULL, NULL, 19, NOW(), NOW()),
(31000000000000746, 31000000000000203, '运费-比价',         'get.admin/tms/freight-rate/rate-shop',      3, NULL, NULL, 20, NOW(), NOW()),
(31000000000000747, 31000000000000204, '检验-登记',         'post.admin/quality/inspection/record',      3, NULL, NULL, 33, NOW(), NOW()),
(31000000000000748, 31000000000000204, '检验-合格率',       'post.admin/quality/inspection/pass-rate',   3, NULL, NULL, 34, NOW(), NOW());
