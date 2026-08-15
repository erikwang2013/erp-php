-- ============================================================
-- 回滚文件: 2026_08_04_000029_dms_category.sql
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 对应迁移: DMS 文档分类表（1 张）+ 分类种子数据
-- 回滚内容: 删除本迁移创建的 1 张表
-- 执行方式: mysql -h<host> -P<port> -u<user> -p <db> < 2026_08_04_000029_dms_category_rollback.sql
-- 注意: 回滚只还原表结构，不还原数据；数据回滚需业务层处理。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;  -- 防御性关闭外键检查（当前迁移无外键定义，保留以备后续）

-- 逆序删除本迁移创建的表（外键→表；当前迁移无外键，直接删表即可）
DROP TABLE IF EXISTS `erik_dms_category`;

SET FOREIGN_KEY_CHECKS = 1;

-- 数据回滚需业务层处理！
-- 本迁移在 erik_dms_category 插入 6 条分类数据（id 1~6），删除表时该数据一并删除。
