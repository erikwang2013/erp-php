/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 演示（测试）数据 —— 安装向导勾选「带测试数据」时，在 install.sql 之后执行本文件。
 *
 * 设计约定（改本文件前先读）：
 *  1. **本文件只有数据，没有 DDL**。schema 的唯一事实源永远是 install.sql ——
 *     不复制那 227 条 CREATE TABLE，避免两份 DDL 漂移。
 *  2. **只放演示业务数据**；必需种子（权限/角色/币种/税率/CRM 阶段…）在 install.sql 里，
 *     本文件不得重复插入，否则与种子冲突。
 *  3. 管理员账号由安装向导第 5 步创建，本文件**不建用户**。
 *  4. **ID 段：41xxxxxxxxxxxxxxx**（演示数据专用）。要清空演示数据即按该前缀删除：
 *       DELETE FROM erp_product_sku   WHERE id BETWEEN 410000000000000000 AND 419999999999999999;
 *       DELETE FROM erp_product       WHERE id BETWEEN 410000000000000000 AND 419999999999999999;
 *       ... （按外键顺序：先子表后父表）
 *  5. 商品未挂分类（本库无 product_category 表；分类资源见 /admin/v1/category，
 *     其表归属未确认，故演示数据先留 category_id=0）。
 */

-- ============================================================
-- 品牌
-- ============================================================
INSERT INTO `erp_brand` (`id`, `name`, `logo`, `description`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000001, '华为',   '', '华为技术有限公司',       1, 1, NOW(), NOW()),
(410000000000000002, '小米',   '', '小米科技有限责任公司',   2, 1, NOW(), NOW()),
(410000000000000003, '联想',   '', '联想集团',               3, 1, NOW(), NOW());

-- ============================================================
-- 仓库 + 库位
-- ============================================================
INSERT INTO `erp_warehouse` (`id`, `name`, `code`, `address`, `manager`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000101, '上海中心仓', 'WH-SH-01', '上海市浦东新区张江路 100 号', '张伟', '13800000001', 1, NOW(), NOW()),
(410000000000000102, '深圳前置仓', 'WH-SZ-01', '深圳市南山区科技园南路 8 号', '李娜', '13800000002', 1, NOW(), NOW());

INSERT INTO `erp_location` (`id`, `warehouse_id`, `code`, `name`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000201, 410000000000000101, 'A-01-01', 'A 区 01 排 01 位', 1, NOW(), NOW()),
(410000000000000202, 410000000000000101, 'A-01-02', 'A 区 01 排 02 位', 1, NOW(), NOW()),
(410000000000000203, 410000000000000102, 'B-01-01', 'B 区 01 排 01 位', 1, NOW(), NOW());

-- ============================================================
-- 商品规格（含 attrs JSON：属性名 → 值数组）
-- 这是本文件最能体现「规格属性」的一处：attrs 为 JSON 对象字符串，
-- 与后端 ProductService::normalizeSpecAttrs 的口径一致（值必须是字符串数组）。
-- ============================================================
INSERT INTO `erp_product_spec` (`id`, `name`, `sort`, `status`, `attrs`, `created_at`, `updated_at`) VALUES
(410000000000000301, '手机颜色', 1, 1, '{"颜色":["曜石黑","雪域白","冰川蓝"]}', NOW(), NOW()),
(410000000000000302, '手机容量', 2, 1, '{"容量":["128GB","256GB","512GB"]}',      NOW(), NOW()),
(410000000000000303, '笔记本配置', 3, 1, '{"内存":["16GB","32GB"],"硬盘":["512GB","1TB"]}', NOW(), NOW());

-- ============================================================
-- 商品
-- ============================================================
INSERT INTO `erp_product` (`id`, `category_id`, `brand_id`, `code`, `name`, `barcode`, `spec`, `unit`, `image`, `description`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000401, 0, 410000000000000001, 'P-HW-M60',   '华为 Mate 60 Pro',   '6901443000001', '手机颜色', '台', '', '旗舰机型，演示数据', 1, NOW(), NOW()),
(410000000000000402, 0, 410000000000000001, 'P-HW-M60P',  '华为 Mate 60 Pro+',  '6901443000002', '手机颜色', '台', '', '旗舰机型，演示数据', 1, NOW(), NOW()),
(410000000000000403, 0, 410000000000000002, 'P-MI-14',    '小米 14',            '6901443000003', '手机容量', '台', '', '演示数据',           1, NOW(), NOW()),
(410000000000000404, 0, 410000000000000002, 'P-MI-PAD',   '小米平板 6',         '6901443000004', '手机容量', '台', '', '演示数据',           1, NOW(), NOW()),
(410000000000000405, 0, 410000000000000003, 'P-LN-X1',    '联想 ThinkPad X1',   '6901443000005', '笔记本配置', '台', '', '演示数据',         1, NOW(), NOW()),
(410000000000000406, 0, 410000000000000003, 'P-LN-Y7000', '联想拯救者 Y7000',   '6901443000006', '笔记本配置', '台', '', '演示数据',         1, NOW(), NOW());

-- ============================================================
-- SKU（spec_id 指向上面三条规格；不同 SKU 的差异由 sku_code 与所选规格体现）
-- 注：SKU 不再自带 spec_attrs 副本（属性值只在 erp_product_spec.attrs），
--     故同一 parent 的多个 SKU 共享同一 spec_id 是正常的。
-- ============================================================
INSERT INTO `erp_product_sku` (`id`, `product_id`, `spec_id`, `sku_code`, `barcode`, `cost_price`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000501, 410000000000000401, 410000000000000301, 'P-HW-M60-BK',    '6901443000101', 5200.00, 1, NOW(), NOW()),
(410000000000000502, 410000000000000401, 410000000000000301, 'P-HW-M60-WH',    '6901443000102', 5200.00, 1, NOW(), NOW()),
(410000000000000503, 410000000000000403, 410000000000000302, 'P-MI-14-128',    '6901443000103', 3600.00, 1, NOW(), NOW()),
(410000000000000504, 410000000000000403, 410000000000000302, 'P-MI-14-256',    '6901443000104', 3900.00, 1, NOW(), NOW()),
(410000000000000505, 410000000000000405, 410000000000000303, 'P-LN-X1-16-512', '6901443000105', 8800.00, 1, NOW(), NOW()),
(410000000000000506, 410000000000000405, 410000000000000303, 'P-LN-X1-32-1T',  '6901443000106', 9800.00, 1, NOW(), NOW());

-- ============================================================
-- 客户等级 + 客户
-- ============================================================
INSERT INTO `erp_customer_level` (`id`, `name`, `discount`, `sort`, `created_at`, `updated_at`) VALUES
(410000000000000601, '普通客户', 1.00, 1, NOW(), NOW()),
(410000000000000602, '银牌客户', 0.95, 2, NOW(), NOW()),
(410000000000000603, '金牌客户', 0.90, 3, NOW(), NOW());

INSERT INTO `erp_customer` (`id`, `code`, `name`, `level_id`, `contact_person`, `phone`, `email`, `address`, `credit_limit`, `credit_days`, `credit_frozen`, `credit_over_ratio`, `credit_overdue_limit_amount`, `status`, `created_at`, `updated_at`) VALUES
(410000000000000701, 'C-0001', '上海鸿运商贸有限公司', 410000000000000603, '王强', '13900000001', 'wangqiang@example.com', '上海市黄浦区南京东路 100 号', 500000.00, 30, 0, 10.00, 0.00, 1, NOW(), NOW()),
(410000000000000702, 'C-0002', '杭州联通数码店',       410000000000000602, '赵敏', '13900000002', 'zhaomin@example.com',   '杭州市西湖区文三路 200 号',   200000.00, 30, 0, 10.00, 0.00, 1, NOW(), NOW()),
(410000000000000703, 'C-0003', '广州讯捷电子',         410000000000000601, '陈杰', '13900000003', 'chenjie@example.com',   '广州市天河区体育西路 50 号',  100000.00, 15, 0, 10.00, 0.00, 1, NOW(), NOW());

-- ============================================================
-- 供应商
-- ============================================================
INSERT INTO `erp_supplier` (`id`, `code`, `name`, `contact_person`, `phone`, `email`, `address`, `bank_name`, `bank_account`, `tax_number`, `tax_rate`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000000801, 'S-0001', '华为技术有限公司',   '刘洋', '13700000001', 'liuyang@example.com', '深圳市龙岗区坂田华为基地', '中国银行深圳分行', '6222020000000001', '91440300100000000X', 13.00, 1, '演示数据', NOW(), NOW()),
(410000000000000802, 'S-0002', '小米通讯技术有限公司', '孙丽', '13700000002', 'sunli@example.com',   '北京市海淀区清河中街 68 号',   '招商银行北京分行', '6222020000000002', '91110108000000000Y', 13.00, 1, '演示数据', NOW(), NOW()),
(410000000000000803, 'S-0003', '联想（北京）有限公司', '周涛', '13700000003', 'zhoutao@example.com', '北京市海淀区上地西路 6 号',    '工商银行北京分行', '6222020000000003', '91110108000000000Z', 13.00, 1, '演示数据', NOW(), NOW());

-- ==== 生成段开始（scripts/gen-demo-data.mjs 产出，勿手工编辑）====
-- 共 207 张表（手工段已覆盖的表、install.sql 已种子的表均不在其中）

INSERT INTO `erp_approval_instance` (`id`, `workflow_id`, `target_type`, `target_id`, `submitter_id`, `current_node_id`, `status`, `created_at`, `updated_at`) VALUES
(410000000000001001, 0, 'd1', 1, 0, 0, 0, NOW(), NOW()),
(410000000000002001, 0, 'd2', 2, 0, 0, 0, NOW(), NOW()),
(410000000000003001, 0, 'd3', 3, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_approval_node` (`id`, `workflow_id`, `name`, `approver_type`, `approver_id`, `role_id`, `seq`, `condition_field`, `condition_op`, `condition_value`, `can_reject`, `created_at`) VALUES
(410000000000004001, 0, '演示approval_node', 0, 0, 0, 0, 'd', 'd', 'd', 0, NOW()),
(410000000000005001, 0, '演示approval_node', 0, 0, 0, 0, 'd', 'd', 'd', 0, NOW()),
(410000000000006001, 0, '演示approval_node', 0, 0, 0, 0, 'd', 'd', 'd', 0, NOW());

INSERT INTO `erp_approval_record` (`id`, `instance_id`, `node_id`, `approver_id`, `action`, `comment`, `created_at`) VALUES
(410000000000007001, 410000000000001001, 410000000000004001, 0, 0, 'd', NOW()),
(410000000000008001, 410000000000002001, 410000000000005001, 0, 0, 'd', NOW()),
(410000000000009001, 410000000000003001, 410000000000006001, 0, 0, 'd', NOW());

INSERT INTO `erp_approval_workflow` (`id`, `code`, `name`, `target_type`, `enabled`, `remark`, `canvas_json`, `created_at`, `updated_at`) VALUES
(410000000000010001, 'DEMO-APPROVAL_WORKFLOW-1', '演示approval_workflow', 'd', 0, '演示数据', 'd', NOW(), NOW()),
(410000000000011001, 'DEMO-APPROVAL_WORKFLOW-2', '演示approval_workflow', 'd', 0, '演示数据', 'd', NOW(), NOW()),
(410000000000012001, 'DEMO-APPROVAL_WORKFLOW-3', '演示approval_workflow', 'd', 0, '演示数据', 'd', NOW(), NOW());

INSERT INTO `erp_bi_dashboard` (`id`, `name`, `user_id`, `status`) VALUES
(410000000000013001, '演示bi_dashboard', 0, 0),
(410000000000014001, '演示bi_dashboard', 0, 0),
(410000000000015001, '演示bi_dashboard', 0, 0);

INSERT INTO `erp_bi_widget` (`id`, `dashboard_id`, `name`, `type`, `dataset_id`, `position_x`, `position_y`, `width`, `height`) VALUES
(410000000000016001, 410000000000013001, '演示bi_widget', 'd', 0, 0, 0, 0, 0),
(410000000000017001, 410000000000014001, '演示bi_widget', 'd', 0, 0, 0, 0, 0),
(410000000000018001, 410000000000015001, '演示bi_widget', 'd', 0, 0, 0, 0, 0);

INSERT INTO `erp_category` (`id`, `parent_id`, `name`, `code`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(410000000000019001, 0, '演示category', 'DEMO-CATEGORY-1', 0, 0, NOW(), NOW()),
(410000000000020001, 0, '演示category', 'DEMO-CATEGORY-2', 0, 0, NOW(), NOW()),
(410000000000021001, 0, '演示category', 'DEMO-CATEGORY-3', 0, 0, NOW(), NOW());

INSERT INTO `erp_channel` (`id`, `code`, `name`, `type`, `status`, `created_at`, `updated_at`) VALUES
(410000000000022001, 'DEMO-CHANNEL-1', '演示channel', 'd', 0, NOW(), NOW()),
(410000000000023001, 'DEMO-CHANNEL-2', '演示channel', 'd', 0, NOW(), NOW()),
(410000000000024001, 'DEMO-CHANNEL-3', '演示channel', 'd', 0, NOW(), NOW());

INSERT INTO `erp_check_detail` (`id`, `check_id`, `product_id`, `sku_id`, `location_id`, `batch_code`, `book_quantity`, `actual_quantity`, `diff_quantity`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000025001, 0, 410000000000000401, 410000000000000501, 410000000000000201, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000026001, 0, 410000000000000402, 410000000000000502, 410000000000000202, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000027001, 0, 410000000000000403, 410000000000000503, 410000000000000203, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_check_task` (`id`, `code`, `warehouse_id`, `type`, `status`, `check_user_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000028001, 'DEMO-CHECK_TASK-1', 410000000000000101, 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000029001, 'DEMO-CHECK_TASK-2', 410000000000000102, 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000030001, 'DEMO-CHECK_TASK-3', 410000000000000101, 0, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_company` (`id`, `code`, `name`, `parent_id`, `base_currency`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000031001, 'DEMO-COMPANY-1', '演示company', 0, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000032001, 'DEMO-COMPANY-2', '演示company', 0, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000033001, 'DEMO-COMPANY-3', '演示company', 0, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_cost_record` (`id`, `product_id`, `sku_id`, `flow_id`, `type`, `quantity`, `unit_cost`, `before_avg_cost`, `after_avg_cost`, `created_at`) VALUES
(410000000000034001, 410000000000000401, 410000000000000501, 0, 0, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000035001, 410000000000000402, 410000000000000502, 0, 0, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000036001, 410000000000000403, 410000000000000503, 0, 0, 0.00, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_crm_analytics_report` (`id`, `name`, `type`, `period_type`, `period_year`, `period_value`, `created_at`) VALUES
(410000000000037001, '演示crm_analytics_report', 'd', 0, 0, 0, NOW()),
(410000000000038001, '演示crm_analytics_report', 'd', 0, 0, 0, NOW()),
(410000000000039001, '演示crm_analytics_report', 'd', 0, 0, 0, NOW());

INSERT INTO `erp_crm_campaign` (`id`, `code`, `name`, `type`, `status`, `budget_amount`, `actual_cost`, `target_audience`, `owner_user_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000040001, 'DEMO-CRM_CAMPAIGN-1', '演示crm_campaign', 'd', 0, 0.00, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000041001, 'DEMO-CRM_CAMPAIGN-2', '演示crm_campaign', 'd', 0, 0.00, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000042001, 'DEMO-CRM_CAMPAIGN-3', '演示crm_campaign', 'd', 0, 0.00, 0.00, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_crm_campaign_participant` (`id`, `campaign_id`, `customer_id`, `contact_id`, `status`, `response`, `created_at`) VALUES
(410000000000043001, 410000000000040001, 410000000000000701, 0, 0, 'd', NOW()),
(410000000000044001, 410000000000041001, 410000000000000702, 0, 0, 'd', NOW()),
(410000000000045001, 410000000000042001, 410000000000000703, 0, 0, 'd', NOW());

INSERT INTO `erp_crm_contact` (`id`, `customer_id`, `name`, `position`, `phone`, `email`, `is_primary`, `status`, `created_at`, `updated_at`) VALUES
(410000000000046001, 410000000000000701, '演示crm_contact', 'd', '13800000001', 'demo1@example.com', 0, 0, NOW(), NOW()),
(410000000000047001, 410000000000000702, '演示crm_contact', 'd', '13800000002', 'demo2@example.com', 0, 0, NOW(), NOW()),
(410000000000048001, 410000000000000703, '演示crm_contact', 'd', '13800000003', 'demo3@example.com', 0, 0, NOW(), NOW());

INSERT INTO `erp_crm_contract` (`id`, `code`, `name`, `customer_id`, `opportunity_id`, `quotation_id`, `total_amount`, `status`, `owner_user_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000049001, 'DEMO-CRM_CONTRACT-1', '演示crm_contract', 410000000000000701, 0, 0, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000050001, 'DEMO-CRM_CONTRACT-2', '演示crm_contract', 410000000000000702, 0, 0, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000051001, 'DEMO-CRM_CONTRACT-3', '演示crm_contract', 410000000000000703, 0, 0, 0.00, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_crm_contract_item` (`id`, `contract_id`, `product_id`, `sku_id`, `quantity`, `price`, `amount`, `unit`, `created_at`) VALUES
(410000000000052001, 410000000000049001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 'd', NOW()),
(410000000000053001, 410000000000050001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 'd', NOW()),
(410000000000054001, 410000000000051001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 'd', NOW());

INSERT INTO `erp_crm_customer_pool_rule` (`id`, `level_id`, `reclaim_days`, `max_claims`, `enabled`, `created_at`, `updated_at`) VALUES
(410000000000055001, 410000000000000601, 0, 0, 0, NOW(), NOW()),
(410000000000056001, 410000000000000602, 0, 0, 0, NOW(), NOW()),
(410000000000057001, 410000000000000603, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_crm_follow_record` (`id`, `customer_id`, `contact_id`, `opportunity_id`, `method`, `next_plan`, `follow_user_id`, `created_at`) VALUES
(410000000000058001, 410000000000000701, 410000000000046001, 0, 'd', 'd', 0, NOW()),
(410000000000059001, 410000000000000702, 410000000000047001, 0, 'd', 'd', 0, NOW()),
(410000000000060001, 410000000000000703, 410000000000048001, 0, 'd', 'd', 0, NOW());

INSERT INTO `erp_crm_opportunity` (`id`, `customer_id`, `stage_id`, `name`, `estimated_amount`, `probability`, `owner_user_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000061001, 410000000000000701, 0, '演示crm_opportunity', 0.00, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000062001, 410000000000000702, 0, '演示crm_opportunity', 0.00, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000063001, 410000000000000703, 0, '演示crm_opportunity', 0.00, 0.00, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_crm_pool_record` (`id`, `customer_id`, `action`, `from_user_id`, `to_user_id`, `remark`, `created_at`) VALUES
(410000000000064001, 410000000000000701, 0, 0, 0, '演示数据', NOW()),
(410000000000065001, 410000000000000702, 0, 0, 0, '演示数据', NOW()),
(410000000000066001, 410000000000000703, 0, 0, 0, '演示数据', NOW());

INSERT INTO `erp_crm_quotation` (`id`, `code`, `customer_id`, `opportunity_id`, `total_amount`, `status`, `remark`, `owner_user_id`, `created_at`, `updated_at`) VALUES
(410000000000067001, 'DEMO-CRM_QUOTATION-1', 410000000000000701, 410000000000061001, 0.00, 0, '演示数据', 0, NOW(), NOW()),
(410000000000068001, 'DEMO-CRM_QUOTATION-2', 410000000000000702, 410000000000062001, 0.00, 0, '演示数据', 0, NOW(), NOW()),
(410000000000069001, 'DEMO-CRM_QUOTATION-3', 410000000000000703, 410000000000063001, 0.00, 0, '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_crm_quotation_item` (`id`, `quotation_id`, `product_id`, `sku_id`, `quantity`, `price`, `amount`, `unit`, `created_at`) VALUES
(410000000000070001, 410000000000067001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 'd', NOW()),
(410000000000071001, 410000000000068001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 'd', NOW()),
(410000000000072001, 410000000000069001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 'd', NOW());

INSERT INTO `erp_crm_ticket` (`id`, `code`, `customer_id`, `contact_id`, `title`, `priority`, `status`, `category`, `assignee_user_id`, `created_at`, `updated_at`) VALUES
(410000000000073001, 'DEMO-CRM_TICKET-1', 410000000000000701, 410000000000046001, '演示crm_ticket', 0, 0, 'd', 0, NOW(), NOW()),
(410000000000074001, 'DEMO-CRM_TICKET-2', 410000000000000702, 410000000000047001, '演示crm_ticket', 0, 0, 'd', 0, NOW(), NOW()),
(410000000000075001, 'DEMO-CRM_TICKET-3', 410000000000000703, 410000000000048001, '演示crm_ticket', 0, 0, 'd', 0, NOW(), NOW());

INSERT INTO `erp_crm_ticket_reply` (`id`, `ticket_id`, `user_id`, `is_internal`, `created_at`) VALUES
(410000000000076001, 410000000000073001, 0, 0, NOW()),
(410000000000077001, 410000000000074001, 0, 0, NOW()),
(410000000000078001, 410000000000075001, 0, 0, NOW());

INSERT INTO `erp_custom_field_definition` (`id`, `entity_type`, `field_key`, `label`, `field_type`, `is_required`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(410000000000079001, 'd1', 'd1', '演示custom_field_definition', 'd', 0, 0, 0, NOW(), NOW()),
(410000000000080001, 'd2', 'd2', '演示custom_field_definition', 'd', 0, 0, 0, NOW(), NOW()),
(410000000000081001, 'd3', 'd3', '演示custom_field_definition', 'd', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_dms_document` (`id`, `code`, `title`, `category`, `version`, `author`, `status`, `tags`) VALUES
(410000000000082001, 'DEMO-DMS_DOCUMENT-1', '演示dms_document', 'd', 0, 'd', 1, 'd'),
(410000000000083001, 'DEMO-DMS_DOCUMENT-2', '演示dms_document', 'd', 0, 'd', 1, 'd'),
(410000000000084001, 'DEMO-DMS_DOCUMENT-3', '演示dms_document', 'd', 0, 'd', 1, 'd');

INSERT INTO `erp_dms_document_version` (`id`, `document_id`, `version`, `changed_by`, `change_note`) VALUES
(410000000000085001, 410000000000082001, 0, 'd', 'd'),
(410000000000086001, 410000000000083001, 0, 'd', 'd'),
(410000000000087001, 410000000000084001, 0, 'd', 'd');

INSERT INTO `erp_eam_equipment` (`id`, `code`, `name`, `model`, `serial_number`, `category`, `location`, `department_id`, `status`) VALUES
(410000000000088001, 'DEMO-EAM_EQUIPMENT-1', '演示eam_equipment', 'd', 'd', 'd', 'd', 0, 0),
(410000000000089001, 'DEMO-EAM_EQUIPMENT-2', '演示eam_equipment', 'd', 'd', 'd', 'd', 0, 0),
(410000000000090001, 'DEMO-EAM_EQUIPMENT-3', '演示eam_equipment', 'd', 'd', 'd', 'd', 0, 0);

INSERT INTO `erp_eam_inspection_result` (`id`, `task_id`, `item_name`, `result`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000091001, 410000000000028001, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000092001, 410000000000029001, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000093001, 410000000000030001, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_eam_inspection_task` (`id`, `equipment_id`, `source_plan_id`, `task_date`, `assignee_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000094001, 410000000000088001, 0, CURDATE(), 0, 0, '演示数据', NOW(), NOW()),
(410000000000095001, 410000000000089001, 0, CURDATE(), 0, 0, '演示数据', NOW(), NOW()),
(410000000000096001, 410000000000090001, 0, CURDATE(), 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_eam_maintenance_plan` (`id`, `equipment_id`, `name`, `frequency`, `assignee`, `status`) VALUES
(410000000000097001, 410000000000088001, '演示eam_maintenance_plan', 'd', 'd', 0),
(410000000000098001, 410000000000089001, '演示eam_maintenance_plan', 'd', 'd', 0),
(410000000000099001, 410000000000090001, '演示eam_maintenance_plan', 'd', 'd', 0);

INSERT INTO `erp_eam_repair_order` (`id`, `code`, `equipment_id`, `repair_type`, `assignee`, `cost`, `status`) VALUES
(410000000000100001, 'DEMO-EAM_REPAIR_ORDER-1', 410000000000088001, 'd', 'd', 0.00, 1),
(410000000000101001, 'DEMO-EAM_REPAIR_ORDER-2', 410000000000089001, 'd', 'd', 0.00, 1),
(410000000000102001, 'DEMO-EAM_REPAIR_ORDER-3', 410000000000090001, 'd', 'd', 0.00, 1);

INSERT INTO `erp_eam_spare_part` (`id`, `code`, `name`, `equipment_id`, `spec`, `unit`, `stock_qty`, `min_stock`, `location`, `status`) VALUES
(410000000000103001, 'DEMO-EAM_SPARE_PART-1', '演示eam_spare_part', 410000000000088001, 'd', 'd', 0.00, 0.00, 'd', 0),
(410000000000104001, 'DEMO-EAM_SPARE_PART-2', '演示eam_spare_part', 410000000000089001, 'd', 'd', 0.00, 0.00, 'd', 0),
(410000000000105001, 'DEMO-EAM_SPARE_PART-3', '演示eam_spare_part', 410000000000090001, 'd', 'd', 0.00, 0.00, 'd', 0);

INSERT INTO `erp_finance_account` (`id`, `parent_id`, `code`, `name`, `type`, `direction`, `status`, `created_at`, `updated_at`) VALUES
(410000000000106001, 0, 'DEMO-FINANCE_ACCOUNT-1', '演示finance_account', 0, 0, 0, NOW(), NOW()),
(410000000000107001, 0, 'DEMO-FINANCE_ACCOUNT-2', '演示finance_account', 0, 0, 0, NOW(), NOW()),
(410000000000108001, 0, 'DEMO-FINANCE_ACCOUNT-3', '演示finance_account', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_finance_allocation` (`id`, `source_center_id`, `target_center_id`, `amount`, `basis`, `period_year`, `period_month`, `created_at`) VALUES
(410000000000109001, 0, 0, 0.00, 'd', 0, 0, NOW()),
(410000000000110001, 0, 0, 0.00, 'd', 0, 0, NOW()),
(410000000000111001, 0, 0, 0.00, 'd', 0, 0, NOW());

INSERT INTO `erp_finance_ar_ap` (`id`, `type`, `partner_id`, `source_type`, `source_id`, `amount`, `settled_amount`, `status`, `created_at`, `updated_at`) VALUES
(410000000000112001, 0, 0, 'd1', 1, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000113001, 0, 0, 'd2', 2, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000114001, 0, 0, 'd3', 3, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_finance_asset` (`id`, `code`, `name`, `category`, `purchase_amount`, `salvage_value`, `useful_life`, `depreciation_method`, `monthly_depreciation`, `accumulated_depreciation`, `net_value`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000115001, 'DEMO-FINANCE_ASSET-1', '演示finance_asset', 'd', 0.00, 0.00, 0, 0, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000116001, 'DEMO-FINANCE_ASSET-2', '演示finance_asset', 'd', 0.00, 0.00, 0, 0, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000117001, 'DEMO-FINANCE_ASSET-3', '演示finance_asset', 'd', 0.00, 0.00, 0, 0, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_asset_depreciation` (`id`, `asset_id`, `period_year`, `period_month`, `depreciation_amount`, `accumulated_amount`, `net_value`, `created_at`) VALUES
(410000000000118001, 410000000000115001, 1, 1, 0.00, 0.00, 0.00, NOW()),
(410000000000119001, 410000000000116001, 2, 2, 0.00, 0.00, 0.00, NOW()),
(410000000000120001, 410000000000117001, 3, 3, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_finance_balance_sheet` (`id`, `ledger_id`, `report_year`, `report_month`, `total_assets`, `total_liabilities`, `total_equity`, `current_assets`, `non_current_assets`, `current_liabilities`, `non_current_liabilities`, `created_at`) VALUES
(410000000000121001, 1, 1, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000122001, 2, 2, 2, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000123001, 3, 3, 3, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_finance_bank_account` (`id`, `name`, `account_number`, `bank_name`, `balance`, `status`, `created_at`, `updated_at`) VALUES
(410000000000124001, '演示finance_bank_account', 'd', 'd', 0.00, 0, NOW(), NOW()),
(410000000000125001, '演示finance_bank_account', 'd', 'd', 0.00, 0, NOW(), NOW()),
(410000000000126001, '演示finance_bank_account', 'd', 'd', 0.00, 0, NOW(), NOW());

INSERT INTO `erp_finance_bank_recon_match` (`id`, `bank_account_id`, `statement_id`, `cash_journal_id`, `match_type`, `created_by`, `created_at`) VALUES
(410000000000127001, 410000000000124001, 1, 1, 0, 0, NOW()),
(410000000000128001, 410000000000125001, 2, 2, 0, 0, NOW()),
(410000000000129001, 410000000000126001, 3, 3, 0, 0, NOW());

INSERT INTO `erp_finance_bank_statement` (`id`, `bank_account_id`, `stmt_date`, `direction`, `amount`, `counterparty`, `reference`, `import_batch`, `created_at`, `updated_at`) VALUES
(410000000000130001, 410000000000124001, CURDATE(), 0, 0.00, 'd', 'd', 'd', NOW(), NOW()),
(410000000000131001, 410000000000125001, CURDATE(), 0, 0.00, 'd', 'd', 'd', NOW(), NOW()),
(410000000000132001, 410000000000126001, CURDATE(), 0, 0.00, 'd', 'd', 'd', NOW(), NOW());

INSERT INTO `erp_finance_bill` (`id`, `bill_no`, `type`, `direction`, `drawer`, `payee`, `acceptor`, `endorsee`, `due_date`, `amount`, `discount_fee`, `bank_account_id`, `status`, `source_type`, `source_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000133001, 'd1', 0, 0, 'd', 'd', 'd', 'd', CURDATE(), 0.00, 0.00, 410000000000124001, 0, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000134001, 'd2', 0, 0, 'd', 'd', 'd', 'd', CURDATE(), 0.00, 0.00, 410000000000125001, 0, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000135001, 'd3', 0, 0, 'd', 'd', 'd', 'd', CURDATE(), 0.00, 0.00, 410000000000126001, 0, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_budget` (`id`, `code`, `name`, `period_year`, `cost_center_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000136001, 'DEMO-FINANCE_BUDGET-1', '演示finance_budget', 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000137001, 'DEMO-FINANCE_BUDGET-2', '演示finance_budget', 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000138001, 'DEMO-FINANCE_BUDGET-3', '演示finance_budget', 0, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_budget_item` (`id`, `budget_id`, `account_id`, `period_month`, `budget_amount`, `actual_amount`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000139001, 410000000000136001, 410000000000106001, 0, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000140001, 410000000000137001, 410000000000107001, 0, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000141001, 410000000000138001, 410000000000108001, 0, 0.00, 0.00, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_cash_flow` (`id`, `ledger_id`, `report_year`, `report_month`, `operating_inflow`, `operating_outflow`, `operating_net`, `investing_inflow`, `investing_outflow`, `investing_net`, `financing_inflow`, `financing_outflow`, `financing_net`, `beginning_cash`, `ending_cash`, `created_at`) VALUES
(410000000000142001, 1, 1, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000143001, 2, 2, 2, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000144001, 3, 3, 3, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_finance_cash_journal` (`id`, `bank_account_id`, `direction`, `amount`, `balance`, `source_type`, `source_id`, `summary`, `journal_date`, `created_at`) VALUES
(410000000000145001, 410000000000124001, 0, 0.00, 0.00, 'd', 0, 'd', CURDATE(), NOW()),
(410000000000146001, 410000000000125001, 0, 0.00, 0.00, 'd', 0, 'd', CURDATE(), NOW()),
(410000000000147001, 410000000000126001, 0, 0.00, 0.00, 'd', 0, 'd', CURDATE(), NOW());

INSERT INTO `erp_finance_consolidation_report` (`id`, `company_id`, `report_year`, `report_month`, `base_currency`, `status`, `total_assets`, `total_liabilities`, `total_equity`, `revenue`, `net_profit`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000148001, 410000000000031001, 0, 0, 'd', 0, 0.00, 0.00, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000149001, 410000000000032001, 0, 0, 'd', 0, 0.00, 0.00, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000150001, 410000000000033001, 0, 0, 'd', 0, 0.00, 0.00, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_cost_account_config` (`id`, `cost_type`, `account_id`, `status`, `created_at`, `updated_at`) VALUES
(410000000000151001, 1, 410000000000106001, 0, NOW(), NOW()),
(410000000000152001, 2, 410000000000107001, 0, NOW(), NOW()),
(410000000000153001, 3, 410000000000108001, 0, NOW(), NOW());

INSERT INTO `erp_finance_cost_center` (`id`, `parent_id`, `code`, `name`, `manager`, `status`, `created_at`, `updated_at`) VALUES
(410000000000154001, 0, 'DEMO-FINANCE_COST_CENTER-1', '演示finance_cost_center', 'd', 0, NOW(), NOW()),
(410000000000155001, 0, 'DEMO-FINANCE_COST_CENTER-2', '演示finance_cost_center', 'd', 0, NOW(), NOW()),
(410000000000156001, 0, 'DEMO-FINANCE_COST_CENTER-3', '演示finance_cost_center', 'd', 0, NOW(), NOW());

INSERT INTO `erp_finance_elimination_item` (`id`, `report_id`, `account_code`, `summary`, `debit_amount`, `credit_amount`, `created_at`, `updated_at`) VALUES
(410000000000157001, 410000000000037001, 'd', 'd', 0.00, 0.00, NOW(), NOW()),
(410000000000158001, 410000000000038001, 'd', 'd', 0.00, 0.00, NOW(), NOW()),
(410000000000159001, 410000000000039001, 'd', 'd', 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_exchange_rate` (`id`, `from_currency_id`, `to_currency_id`, `rate`, `effective_date`, `created_at`) VALUES
(410000000000160001, 1, 1, 0.00, CURDATE(), NOW()),
(410000000000161001, 2, 2, 0.00, CURDATE(), NOW()),
(410000000000162001, 3, 3, 0.00, CURDATE(), NOW());

INSERT INTO `erp_finance_expense` (`id`, `code`, `apply_user_id`, `account_id`, `amount`, `status`, `remark`, `approved_by`, `created_at`, `updated_at`) VALUES
(410000000000163001, 'DEMO-FINANCE_EXPENSE-1', 0, 410000000000106001, 0.00, 0, '演示数据', 0, NOW(), NOW()),
(410000000000164001, 'DEMO-FINANCE_EXPENSE-2', 0, 410000000000107001, 0.00, 0, '演示数据', 0, NOW(), NOW()),
(410000000000165001, 'DEMO-FINANCE_EXPENSE-3', 0, 410000000000108001, 0.00, 0, '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_finance_general_ledger` (`id`, `account_id`, `period_year`, `period_month`, `opening_debit`, `opening_credit`, `period_debit`, `period_credit`, `closing_debit`, `closing_credit`, `created_at`, `updated_at`) VALUES
(410000000000166001, 410000000000106001, 1, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000167001, 410000000000107001, 2, 2, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000168001, 410000000000108001, 3, 3, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_invoice` (`id`, `invoice_no`, `electronic_no`, `issue_status`, `type`, `customer_id`, `supplier_id`, `biz_type`, `source_id`, `untaxed_amount`, `tax_amount`, `amount`, `currency`, `status`, `void_reason`, `audited_by`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000169001, 'd1', 'd', 'd', 'd', 410000000000000701, 410000000000000801, 'd', 0, 0.00, 0.00, 0.00, 'd', 1, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000170001, 'd2', 'd', 'd', 'd', 410000000000000702, 410000000000000802, 'd', 0, 0.00, 0.00, 0.00, 'd', 1, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000171001, 'd3', 'd', 'd', 'd', 410000000000000703, 410000000000000803, 'd', 0, 0.00, 0.00, 0.00, 'd', 1, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_invoice_item` (`id`, `invoice_id`, `quantity`, `price`, `amount`, `tax_rate`, `tax_amount`, `line_total`, `created_at`, `updated_at`) VALUES
(410000000000172001, 410000000000169001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000173001, 410000000000170001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000174001, 410000000000171001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_invoice_match_log` (`id`, `invoice_id`, `source_type`, `source_id`, `invoiced_total`, `result`, `created_at`, `updated_at`) VALUES
(410000000000175001, 410000000000169001, 'd', 0, 0.00, 'd', NOW(), NOW()),
(410000000000176001, 410000000000170001, 'd', 0, 0.00, 'd', NOW(), NOW()),
(410000000000177001, 410000000000171001, 'd', 0, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_finance_ledger` (`id`, `company_id`, `code`, `name`, `currency`, `is_default`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000178001, 410000000000031001, 'DEMO-FINANCE_LEDGER-1', '演示finance_ledger', 'd', 0, 0, '演示数据', NOW(), NOW()),
(410000000000179001, 410000000000032001, 'DEMO-FINANCE_LEDGER-2', '演示finance_ledger', 'd', 0, 0, '演示数据', NOW(), NOW()),
(410000000000180001, 410000000000033001, 'DEMO-FINANCE_LEDGER-3', '演示finance_ledger', 'd', 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_payment` (`id`, `code`, `supplier_id`, `bank_account_id`, `amount`, `method`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000181001, 'DEMO-FINANCE_PAYMENT-1', 410000000000000801, 410000000000124001, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000182001, 'DEMO-FINANCE_PAYMENT-2', 410000000000000802, 410000000000125001, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000183001, 'DEMO-FINANCE_PAYMENT-3', 410000000000000803, 410000000000126001, 0.00, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_period` (`id`, `ledger_id`, `period`, `status`, `opened_at`, `created_at`, `updated_at`) VALUES
(410000000000184001, 410000000000166001, 'd1', 0, NOW(), NOW(), NOW()),
(410000000000185001, 410000000000167001, 'd2', 0, NOW(), NOW(), NOW()),
(410000000000186001, 410000000000168001, 'd3', 0, NOW(), NOW(), NOW());

INSERT INTO `erp_finance_profit` (`id`, `ledger_id`, `year`, `month`, `revenue`, `cost`, `expense`, `profit`, `created_at`, `updated_at`) VALUES
(410000000000187001, 410000000000166001, 1, 1, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000188001, 410000000000167001, 2, 2, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000189001, 410000000000168001, 3, 3, 0.00, 0.00, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_profit_center` (`id`, `parent_id`, `code`, `name`, `manager`, `status`, `created_at`, `updated_at`) VALUES
(410000000000190001, 0, 'DEMO-FINANCE_PROFIT_CENTER-1', '演示finance_profit_center', 'd', 0, NOW(), NOW()),
(410000000000191001, 0, 'DEMO-FINANCE_PROFIT_CENTER-2', '演示finance_profit_center', 'd', 0, NOW(), NOW()),
(410000000000192001, 0, 'DEMO-FINANCE_PROFIT_CENTER-3', '演示finance_profit_center', 'd', 0, NOW(), NOW());

INSERT INTO `erp_finance_receipt` (`id`, `code`, `customer_id`, `bank_account_id`, `amount`, `method`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000193001, 'DEMO-FINANCE_RECEIPT-1', 410000000000000701, 410000000000124001, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000194001, 'DEMO-FINANCE_RECEIPT-2', 410000000000000702, 410000000000125001, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000195001, 'DEMO-FINANCE_RECEIPT-3', 410000000000000703, 410000000000126001, 0.00, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_settlement` (`id`, `ar_ap_id`, `receipt_payment_id`, `type`, `amount`, `created_at`, `updated_at`) VALUES
(410000000000196001, 410000000000112001, 0, 0, 0.00, NOW(), NOW()),
(410000000000197001, 410000000000113001, 0, 0, 0.00, NOW(), NOW()),
(410000000000198001, 410000000000114001, 0, 0, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_subsidiary_ledger` (`id`, `account_id`, `voucher_id`, `voucher_item_id`, `direction`, `amount`, `balance`, `summary`, `entry_date`, `created_at`) VALUES
(410000000000199001, 410000000000106001, 0, 0, 0, 0.00, 0.00, 'd', CURDATE(), NOW()),
(410000000000200001, 410000000000107001, 0, 0, 0, 0.00, 0.00, 'd', CURDATE(), NOW()),
(410000000000201001, 410000000000108001, 0, 0, 0, 0.00, 0.00, 'd', CURDATE(), NOW());

INSERT INTO `erp_finance_tax_record` (`id`, `tax_rate_id`, `source_type`, `source_id`, `taxable_amount`, `tax_amount`, `period_year`, `period_month`, `created_at`) VALUES
(410000000000202001, 0, 'd', 0, 0.00, 0.00, 0, 0, NOW()),
(410000000000203001, 0, 'd', 0, 0.00, 0.00, 0, 0, NOW()),
(410000000000204001, 0, 'd', 0, 0.00, 0.00, 0, 0, NOW());

INSERT INTO `erp_finance_voucher` (`id`, `code`, `voucher_date`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000205001, 'DEMO-FINANCE_VOUCHER-1', CURDATE(), 0, '演示数据', NOW(), NOW()),
(410000000000206001, 'DEMO-FINANCE_VOUCHER-2', CURDATE(), 0, '演示数据', NOW(), NOW()),
(410000000000207001, 'DEMO-FINANCE_VOUCHER-3', CURDATE(), 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_finance_voucher_item` (`id`, `voucher_id`, `account_id`, `summary`, `debit_amount`, `credit_amount`, `created_at`, `updated_at`) VALUES
(410000000000208001, 410000000000205001, 410000000000106001, 'd', 0.00, 0.00, NOW(), NOW()),
(410000000000209001, 410000000000206001, 410000000000107001, 'd', 0.00, 0.00, NOW(), NOW()),
(410000000000210001, 410000000000207001, 410000000000108001, 'd', 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_finance_voucher_source` (`id`, `voucher_id`, `source_type`, `source_id`, `created_at`) VALUES
(410000000000211001, 410000000000205001, 'd1', 1, NOW()),
(410000000000212001, 410000000000206001, 'd2', 2, NOW()),
(410000000000213001, 410000000000207001, 'd3', 3, NOW());

INSERT INTO `erp_hr_attendance` (`id`, `employee_id`, `rule_id`, `work_date`, `status`, `late_minutes`, `early_minutes`, `created_at`) VALUES
(410000000000214001, 0, 410000000000055001, CURDATE(), 0, 0, 0, NOW()),
(410000000000215001, 0, 410000000000056001, CURDATE(), 0, 0, 0, NOW()),
(410000000000216001, 0, 410000000000057001, CURDATE(), 0, 0, 0, NOW());

INSERT INTO `erp_hr_attendance_rule` (`id`, `name`, `clock_in_time`, `clock_out_time`, `late_grace`, `early_grace`, `created_at`, `updated_at`) VALUES
(410000000000217001, '演示hr_attendance_rule', '00:00:00', '00:00:00', 0, 0, NOW(), NOW()),
(410000000000218001, '演示hr_attendance_rule', '00:00:00', '00:00:00', 0, 0, NOW(), NOW()),
(410000000000219001, '演示hr_attendance_rule', '00:00:00', '00:00:00', 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_candidate` (`id`, `name`, `phone`, `source`, `job_id`, `status`, `expected_salary`, `created_at`, `updated_at`) VALUES
(410000000000220001, '演示hr_candidate', '13800000001', 'd', 0, 0, 0.00, NOW(), NOW()),
(410000000000221001, '演示hr_candidate', '13800000002', 'd', 0, 0, 0.00, NOW(), NOW()),
(410000000000222001, '演示hr_candidate', '13800000003', 'd', 0, 0, 0.00, NOW(), NOW());

INSERT INTO `erp_hr_course` (`id`, `title`, `course_type`, `lecturer`, `credits`, `duration_hours`, `status`, `created_at`, `updated_at`) VALUES
(410000000000223001, '演示hr_course', 'd', 'd', 0, 0.00, 0, NOW(), NOW()),
(410000000000224001, '演示hr_course', 'd', 'd', 0, 0.00, 0, NOW(), NOW()),
(410000000000225001, '演示hr_course', 'd', 'd', 0, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_hr_course_enrollment` (`id`, `course_id`, `employee_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(410000000000226001, 410000000000223001, 1, 0, 0, NOW(), NOW()),
(410000000000227001, 410000000000224001, 2, 0, 0, NOW(), NOW()),
(410000000000228001, 410000000000225001, 3, 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_department` (`id`, `parent_id`, `code`, `name`, `manager_user_id`, `status`, `created_at`, `updated_at`) VALUES
(410000000000229001, 0, 'DEMO-HR_DEPARTMENT-1', '演示hr_department', 0, 0, NOW(), NOW()),
(410000000000230001, 0, 'DEMO-HR_DEPARTMENT-2', '演示hr_department', 0, 0, NOW(), NOW()),
(410000000000231001, 0, 'DEMO-HR_DEPARTMENT-3', '演示hr_department', 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_employee` (`id`, `code`, `name`, `department_id`, `position_id`, `gender`, `phone`, `email`, `id_card`, `status`, `bank_account`, `emergency_contact`, `emergency_phone`, `created_at`, `updated_at`) VALUES
(410000000000232001, 'DEMO-HR_EMPLOYEE-1', '演示hr_employee', 410000000000229001, 0, 0, '13800000001', 'demo1@example.com', 'd', 0, 'd', 'd', 'd', NOW(), NOW()),
(410000000000233001, 'DEMO-HR_EMPLOYEE-2', '演示hr_employee', 410000000000230001, 0, 0, '13800000002', 'demo2@example.com', 'd', 0, 'd', 'd', 'd', NOW(), NOW()),
(410000000000234001, 'DEMO-HR_EMPLOYEE-3', '演示hr_employee', 410000000000231001, 0, 0, '13800000003', 'demo3@example.com', 'd', 0, 'd', 'd', 'd', NOW(), NOW());

INSERT INTO `erp_hr_employee_social` (`id`, `employee_id`, `rule_id`, `base_amount`, `created_at`, `updated_at`) VALUES
(410000000000235001, 410000000000232001, 410000000000055001, 0.00, NOW(), NOW()),
(410000000000236001, 410000000000233001, 410000000000056001, 0.00, NOW(), NOW()),
(410000000000237001, 410000000000234001, 410000000000057001, 0.00, NOW(), NOW());

INSERT INTO `erp_hr_interview` (`id`, `candidate_id`, `round_no`, `interviewer_id`, `interview_date`, `result`, `comment`, `created_at`, `updated_at`) VALUES
(410000000000238001, 410000000000220001, 0, 0, CURDATE(), 0, 'd', NOW(), NOW()),
(410000000000239001, 410000000000221001, 0, 0, CURDATE(), 0, 'd', NOW(), NOW()),
(410000000000240001, 410000000000222001, 0, 0, CURDATE(), 0, 'd', NOW(), NOW());

INSERT INTO `erp_hr_job` (`id`, `job_title`, `department_id`, `headcount`, `status`, `created_at`, `updated_at`) VALUES
(410000000000241001, 'd', 410000000000229001, 0, 0, NOW(), NOW()),
(410000000000242001, 'd', 410000000000230001, 0, 0, NOW(), NOW()),
(410000000000243001, 'd', 410000000000231001, 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_kpi_template` (`id`, `name`, `period_type`, `status`, `created_at`, `updated_at`) VALUES
(410000000000244001, '演示hr_kpi_template', 'd', 0, NOW(), NOW()),
(410000000000245001, '演示hr_kpi_template', 'd', 0, NOW(), NOW()),
(410000000000246001, '演示hr_kpi_template', 'd', 0, NOW(), NOW());

INSERT INTO `erp_hr_kpi_template_item` (`id`, `template_id`, `indicator`, `weight`, `target_value`, `rater_type`, `sort`, `created_at`, `updated_at`) VALUES
(410000000000247001, 410000000000244001, 'd', 0.00, 'd', 0, 0, NOW(), NOW()),
(410000000000248001, 410000000000245001, 'd', 0.00, 'd', 0, 0, NOW(), NOW()),
(410000000000249001, 410000000000246001, 'd', 0.00, 'd', 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_leave` (`id`, `employee_id`, `type`, `start_date`, `end_date`, `days`, `status`, `reason`, `created_at`, `updated_at`) VALUES
(410000000000250001, 410000000000232001, 0, CURDATE(), CURDATE(), 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000251001, 410000000000233001, 0, CURDATE(), CURDATE(), 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000252001, 410000000000234001, 0, CURDATE(), CURDATE(), 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_hr_offer` (`id`, `candidate_id`, `offered_salary`, `status`, `created_at`, `updated_at`) VALUES
(410000000000253001, 410000000000220001, 0.00, 0, NOW(), NOW()),
(410000000000254001, 410000000000221001, 0.00, 0, NOW(), NOW()),
(410000000000255001, 410000000000222001, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_hr_perf_plan` (`id`, `template_id`, `period_start`, `period_end`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(410000000000256001, 410000000000244001, CURDATE(), CURDATE(), 0, 0, NOW(), NOW()),
(410000000000257001, 410000000000245001, CURDATE(), CURDATE(), 0, 0, NOW(), NOW()),
(410000000000258001, 410000000000246001, CURDATE(), CURDATE(), 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_perf_score` (`id`, `plan_id`, `employee_id`, `rater_id`, `rater_type`, `indicator`, `score`, `comment`, `created_at`, `updated_at`) VALUES
(410000000000259001, 410000000000097001, 410000000000232001, 1, 0, 'd1', 0.00, 'd', NOW(), NOW()),
(410000000000260001, 410000000000098001, 410000000000233001, 2, 0, 'd2', 0.00, 'd', NOW(), NOW()),
(410000000000261001, 410000000000099001, 410000000000234001, 3, 0, 'd3', 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_hr_position` (`id`, `department_id`, `code`, `name`, `rank`, `status`, `created_at`, `updated_at`) VALUES
(410000000000262001, 410000000000229001, 'DEMO-HR_POSITION-1', '演示hr_position', 0, 0, NOW(), NOW()),
(410000000000263001, 410000000000230001, 'DEMO-HR_POSITION-2', '演示hr_position', 0, 0, NOW(), NOW()),
(410000000000264001, 410000000000231001, 'DEMO-HR_POSITION-3', '演示hr_position', 0, 0, NOW(), NOW());

INSERT INTO `erp_hr_salary` (`id`, `employee_id`, `period_year`, `period_month`, `base_salary`, `performance`, `piece_wage`, `overtime`, `deduction`, `tax`, `net_salary`, `status`, `created_at`, `updated_at`) VALUES
(410000000000265001, 410000000000232001, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000266001, 410000000000233001, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000267001, 410000000000234001, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_hr_salary_item` (`id`, `code`, `name`, `type`, `is_taxable`, `default_amount`, `created_at`, `updated_at`) VALUES
(410000000000268001, 'DEMO-HR_SALARY_ITEM-1', '演示hr_salary_item', 0, 0, 0.00, NOW(), NOW()),
(410000000000269001, 'DEMO-HR_SALARY_ITEM-2', '演示hr_salary_item', 0, 0, 0.00, NOW(), NOW()),
(410000000000270001, 'DEMO-HR_SALARY_ITEM-3', '演示hr_salary_item', 0, 0, 0.00, NOW(), NOW());

INSERT INTO `erp_hr_social_rate` (`id`, `rule_id`, `insurance_type`, `personal_rate`, `company_rate`, `created_at`, `updated_at`) VALUES
(410000000000271001, 410000000000055001, 'd1', 0.00, 0.00, NOW(), NOW()),
(410000000000272001, 410000000000056001, 'd2', 0.00, 0.00, NOW(), NOW()),
(410000000000273001, 410000000000057001, 'd3', 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_hr_social_rule` (`id`, `city`, `rule_name`, `social_base_min`, `social_base_max`, `created_at`, `updated_at`) VALUES
(410000000000274001, 'd1', 'd1', 0.00, 0.00, NOW(), NOW()),
(410000000000275001, 'd2', 'd2', 0.00, 0.00, NOW(), NOW()),
(410000000000276001, 'd3', 'd3', 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_inventory` (`id`, `product_id`, `sku_id`, `warehouse_id`, `location_id`, `batch_code`, `quantity`, `cost_price`, `created_at`, `updated_at`) VALUES
(410000000000277001, 410000000000000401, 410000000000000501, 410000000000000101, 410000000000000201, 'd1', 0.00, 0.00, NOW(), NOW()),
(410000000000278001, 410000000000000402, 410000000000000502, 410000000000000102, 410000000000000202, 'd2', 0.00, 0.00, NOW(), NOW()),
(410000000000279001, 410000000000000403, 410000000000000503, 410000000000000101, 410000000000000203, 'd3', 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_inventory_alert_log` (`id`, `rule_id`, `product_id`, `sku_id`, `warehouse_id`, `current_quantity`, `alert_type`, `created_at`) VALUES
(410000000000280001, 410000000000055001, 410000000000000401, 410000000000000501, 410000000000000101, 0.00, 0, NOW()),
(410000000000281001, 410000000000056001, 410000000000000402, 410000000000000502, 410000000000000102, 0.00, 0, NOW()),
(410000000000282001, 410000000000057001, 410000000000000403, 410000000000000503, 410000000000000101, 0.00, 0, NOW());

INSERT INTO `erp_inventory_alert_rule` (`id`, `product_id`, `sku_id`, `warehouse_id`, `min_quantity`, `max_quantity`, `enabled`, `created_at`, `updated_at`) VALUES
(410000000000283001, 410000000000000401, 410000000000000501, 410000000000000101, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000284001, 410000000000000402, 410000000000000502, 410000000000000102, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000285001, 410000000000000403, 410000000000000503, 410000000000000101, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_inventory_batch` (`id`, `product_id`, `sku_id`, `batch_code`, `created_at`, `updated_at`) VALUES
(410000000000286001, 410000000000000401, 410000000000000501, 'd1', NOW(), NOW()),
(410000000000287001, 410000000000000402, 410000000000000502, 'd2', NOW(), NOW()),
(410000000000288001, 410000000000000403, 410000000000000503, 'd3', NOW(), NOW());

INSERT INTO `erp_inventory_flow` (`id`, `product_id`, `sku_id`, `warehouse_id`, `location_id`, `batch_code`, `direction`, `quantity`, `cost_price`, `source_type`, `source_id`, `created_at`) VALUES
(410000000000289001, 410000000000000401, 410000000000000501, 410000000000000101, 410000000000000201, 'd', 0, 0.00, 0.00, 'd', 410000000000211001, NOW()),
(410000000000290001, 410000000000000402, 410000000000000502, 410000000000000102, 410000000000000202, 'd', 0, 0.00, 0.00, 'd', 410000000000212001, NOW()),
(410000000000291001, 410000000000000403, 410000000000000503, 410000000000000101, 410000000000000203, 'd', 0, 0.00, 0.00, 'd', 410000000000213001, NOW());

INSERT INTO `erp_inventory_serial` (`id`, `product_id`, `sku_id`, `serial_code`, `status`, `in_flow_id`, `out_flow_id`, `created_at`, `updated_at`) VALUES
(410000000000292001, 410000000000000401, 410000000000000501, 'd1', 0, 0, 0, NOW(), NOW()),
(410000000000293001, 410000000000000402, 410000000000000502, 'd2', 0, 0, 0, NOW(), NOW()),
(410000000000294001, 410000000000000403, 410000000000000503, 'd3', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_member` (`id`, `phone`, `name`, `level`, `customer_id`, `source`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000295001, '13800000001', '演示member', 0, 410000000000000701, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000296001, '13800000002', '演示member', 0, 410000000000000702, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000297001, '13800000003', '演示member', 0, 410000000000000703, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_member_balance_account` (`id`, `member_id`, `balance`, `created_at`, `updated_at`) VALUES
(410000000000298001, 410000000000295001, 0.00, NOW(), NOW()),
(410000000000299001, 410000000000296001, 0.00, NOW(), NOW()),
(410000000000300001, 410000000000297001, 0.00, NOW(), NOW());

INSERT INTO `erp_member_balance_log` (`id`, `member_id`, `biz_type`, `biz_id`, `amount`, `balance_after`, `operator_id`, `remark`, `created_at`) VALUES
(410000000000301001, 410000000000295001, 'd', 0, 0.00, 0.00, 0, '演示数据', NOW()),
(410000000000302001, 410000000000296001, 'd', 0, 0.00, 0.00, 0, '演示数据', NOW()),
(410000000000303001, 410000000000297001, 'd', 0, 0.00, 0.00, 0, '演示数据', NOW());

INSERT INTO `erp_member_coupon` (`id`, `member_id`, `template_id`, `status`, `received_at`, `order_source`, `created_at`, `updated_at`) VALUES
(410000000000304001, 410000000000295001, 410000000000244001, 0, NOW(), 'd', NOW(), NOW()),
(410000000000305001, 410000000000296001, 410000000000245001, 0, NOW(), 'd', NOW(), NOW()),
(410000000000306001, 410000000000297001, 410000000000246001, 0, NOW(), 'd', NOW(), NOW());

INSERT INTO `erp_member_coupon_template` (`id`, `name`, `coupon_type`, `threshold_amount`, `discount_value`, `valid_days`, `total_qty`, `issued_qty`, `status`, `created_at`, `updated_at`) VALUES
(410000000000307001, '演示member_coupon_template', 0, 0.00, 0.00, 0, 0, 0, 0, NOW(), NOW()),
(410000000000308001, '演示member_coupon_template', 0, 0.00, 0.00, 0, 0, 0, 0, NOW(), NOW()),
(410000000000309001, '演示member_coupon_template', 0, 0.00, 0.00, 0, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_member_point_account` (`id`, `member_id`, `points`, `created_at`, `updated_at`) VALUES
(410000000000310001, 410000000000295001, 0, NOW(), NOW()),
(410000000000311001, 410000000000296001, 0, NOW(), NOW()),
(410000000000312001, 410000000000297001, 0, NOW(), NOW());

INSERT INTO `erp_member_point_log` (`id`, `member_id`, `biz_type`, `biz_id`, `points`, `points_after`, `operator_id`, `remark`, `created_at`) VALUES
(410000000000313001, 410000000000295001, 'd', 0, 0, 0, 0, '演示数据', NOW()),
(410000000000314001, 410000000000296001, 'd', 0, 0, 0, 0, '演示数据', NOW()),
(410000000000315001, 410000000000297001, 'd', 0, 0, 0, 0, '演示数据', NOW());

INSERT INTO `erp_mfg_bom` (`id`, `product_id`, `code`, `name`, `version`, `status`, `created_at`, `updated_at`) VALUES
(410000000000316001, 410000000000000401, 'DEMO-MFG_BOM-1', '演示mfg_bom', 1, 0, NOW(), NOW()),
(410000000000317001, 410000000000000402, 'DEMO-MFG_BOM-2', '演示mfg_bom', 2, 0, NOW(), NOW()),
(410000000000318001, 410000000000000403, 'DEMO-MFG_BOM-3', '演示mfg_bom', 3, 0, NOW(), NOW());

INSERT INTO `erp_mfg_bom_item` (`id`, `bom_id`, `component_product_id`, `quantity`, `unit`, `scrap_rate`, `seq`, `created_at`) VALUES
(410000000000319001, 410000000000316001, 0, 0.00, 'd', 0.00, 0, NOW()),
(410000000000320001, 410000000000317001, 0, 0.00, 'd', 0.00, 0, NOW()),
(410000000000321001, 410000000000318001, 0, 0.00, 'd', 0.00, 0, NOW());

INSERT INTO `erp_mfg_capacity_calendar` (`id`, `workstation_id`, `work_date`, `available_hours`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000322001, 1, CURDATE(), 0.00, '演示数据', NOW(), NOW()),
(410000000000323001, 2, CURDATE(), 0.00, '演示数据', NOW(), NOW()),
(410000000000324001, 3, CURDATE(), 0.00, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_cost_entry` (`id`, `code`, `order_id`, `entry_type`, `amount`, `entry_date`, `status`, `summary`, `created_at`, `updated_at`) VALUES
(410000000000325001, 'DEMO-MFG_COST_ENTRY-1', 410000000000100001, 0, 0.00, CURDATE(), 0, 'd', NOW(), NOW()),
(410000000000326001, 'DEMO-MFG_COST_ENTRY-2', 410000000000101001, 0, 0.00, CURDATE(), 0, 'd', NOW(), NOW()),
(410000000000327001, 'DEMO-MFG_COST_ENTRY-3', 410000000000102001, 0, 0.00, CURDATE(), 0, 'd', NOW(), NOW());

INSERT INTO `erp_mfg_material_issue` (`id`, `code`, `order_id`, `warehouse_id`, `issue_date`, `status`, `total_cost`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000328001, 'DEMO-MFG_MATERIAL_ISSUE-1', 410000000000100001, 410000000000000101, CURDATE(), 0, 0.00, '演示数据', NOW(), NOW()),
(410000000000329001, 'DEMO-MFG_MATERIAL_ISSUE-2', 410000000000101001, 410000000000000102, CURDATE(), 0, 0.00, '演示数据', NOW(), NOW()),
(410000000000330001, 'DEMO-MFG_MATERIAL_ISSUE-3', 410000000000102001, 410000000000000101, CURDATE(), 0, 0.00, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_material_issue_item` (`id`, `issue_id`, `product_id`, `sku_id`, `quantity`, `unit_cost`, `amount`, `created_at`) VALUES
(410000000000331001, 410000000000328001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, NOW()),
(410000000000332001, 410000000000329001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, NOW()),
(410000000000333001, 410000000000330001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_mfg_mrp_item` (`id`, `plan_id`, `product_id`, `gross_requirement`, `scheduled_receipt`, `on_hand`, `net_requirement`, `planned_order_qty`, `created_at`) VALUES
(410000000000334001, 410000000000097001, 410000000000000401, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000335001, 410000000000098001, 410000000000000402, 0.00, 0.00, 0.00, 0.00, 0.00, NOW()),
(410000000000336001, 410000000000099001, 410000000000000403, 0.00, 0.00, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_mfg_mrp_plan` (`id`, `code`, `period_year`, `period_month`, `status`, `created_at`, `updated_at`) VALUES
(410000000000337001, 'DEMO-MFG_MRP_PLAN-1', 0, 0, 0, NOW(), NOW()),
(410000000000338001, 'DEMO-MFG_MRP_PLAN-2', 0, 0, 0, NOW(), NOW()),
(410000000000339001, 'DEMO-MFG_MRP_PLAN-3', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_mfg_order_cost` (`id`, `order_id`, `finished_qty`, `standard_material_cost`, `actual_material_cost`, `labor_cost`, `overhead_cost`, `other_cost`, `material_diff`, `total_cost`, `unit_cost`, `voucher_id`, `status`, `created_at`, `updated_at`) VALUES
(410000000000340001, 410000000000100001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 410000000000205001, 0, NOW(), NOW()),
(410000000000341001, 410000000000101001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 410000000000206001, 0, NOW(), NOW()),
(410000000000342001, 410000000000102001, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 410000000000207001, 0, NOW(), NOW());

INSERT INTO `erp_mfg_piece_wage` (`id`, `employee_id`, `period_year`, `period_month`, `quantity`, `amount`, `created_at`, `updated_at`) VALUES
(410000000000343001, 410000000000232001, 1, 1, 0.00, 0.00, NOW(), NOW()),
(410000000000344001, 410000000000233001, 2, 2, 0.00, 0.00, NOW(), NOW()),
(410000000000345001, 410000000000234001, 3, 3, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_mfg_production_item` (`id`, `order_id`, `product_id`, `planned_quantity`, `completed_quantity`, `status`, `created_at`) VALUES
(410000000000346001, 410000000000100001, 410000000000000401, 0.00, 0.00, 0, NOW()),
(410000000000347001, 410000000000101001, 410000000000000402, 0.00, 0.00, 0, NOW()),
(410000000000348001, 410000000000102001, 410000000000000403, 0.00, 0.00, 0, NOW());

INSERT INTO `erp_mfg_production_order` (`id`, `code`, `bom_id`, `warehouse_id`, `planned_quantity`, `completed_quantity`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000349001, 'DEMO-MFG_PRODUCTION_ORDER-1', 410000000000316001, 410000000000000101, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000350001, 'DEMO-MFG_PRODUCTION_ORDER-2', 410000000000317001, 410000000000000102, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000351001, 'DEMO-MFG_PRODUCTION_ORDER-3', 410000000000318001, 410000000000000101, 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_routing` (`id`, `product_id`, `name`, `seq`, `workstation_id`, `standard_hours`, `piece_rate`, `description`, `created_at`) VALUES
(410000000000352001, 410000000000000401, '演示mfg_routing', 0, 0, 0.00, 0.00, '演示数据', NOW()),
(410000000000353001, 410000000000000402, '演示mfg_routing', 0, 0, 0.00, 0.00, '演示数据', NOW()),
(410000000000354001, 410000000000000403, '演示mfg_routing', 0, 0, 0.00, 0.00, '演示数据', NOW());

INSERT INTO `erp_mfg_subcontract` (`id`, `code`, `supplier_id`, `product_id`, `warehouse_id`, `quantity`, `unit_price`, `amount`, `issued_amount`, `received_qty`, `consumed_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000355001, 'DEMO-MFG_SUBCONTRACT-1', 410000000000000801, 410000000000000401, 410000000000000101, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000356001, 'DEMO-MFG_SUBCONTRACT-2', 410000000000000802, 410000000000000402, 410000000000000102, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000357001, 'DEMO-MFG_SUBCONTRACT-3', 410000000000000803, 410000000000000403, 410000000000000101, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_subcontract_issue` (`id`, `code`, `subcontract_id`, `warehouse_id`, `issue_date`, `total_cost`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000358001, 'DEMO-MFG_SUBCONTRACT_ISSUE-1', 410000000000355001, 410000000000000101, CURDATE(), 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000359001, 'DEMO-MFG_SUBCONTRACT_ISSUE-2', 410000000000356001, 410000000000000102, CURDATE(), 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000360001, 'DEMO-MFG_SUBCONTRACT_ISSUE-3', 410000000000357001, 410000000000000101, CURDATE(), 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_subcontract_issue_item` (`id`, `issue_id`, `product_id`, `sku_id`, `quantity`, `unit_cost`, `amount`, `created_at`) VALUES
(410000000000361001, 410000000000328001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, NOW()),
(410000000000362001, 410000000000329001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, NOW()),
(410000000000363001, 410000000000330001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, NOW());

INSERT INTO `erp_mfg_subcontract_receive` (`id`, `code`, `subcontract_id`, `warehouse_id`, `receive_date`, `quantity`, `unit_price`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000364001, 'DEMO-MFG_SUBCONTRACT_RECEIVE-1', 410000000000355001, 410000000000000101, CURDATE(), 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000365001, 'DEMO-MFG_SUBCONTRACT_RECEIVE-2', 410000000000356001, 410000000000000102, CURDATE(), 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000366001, 'DEMO-MFG_SUBCONTRACT_RECEIVE-3', 410000000000357001, 410000000000000101, CURDATE(), 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_wip` (`id`, `order_id`, `material_cost`, `labor_cost`, `overhead_cost`, `other_cost`, `total_cost`, `status`, `created_at`, `updated_at`) VALUES
(410000000000367001, 410000000000100001, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000368001, 410000000000101001, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000369001, 410000000000102001, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_mfg_wip_flow` (`id`, `wip_id`, `order_id`, `source_type`, `source_id`, `amount`, `direction`, `flow_date`, `created_at`) VALUES
(410000000000370001, 410000000000367001, 410000000000100001, 0, 410000000000211001, 0.00, 0, CURDATE(), NOW()),
(410000000000371001, 410000000000368001, 410000000000101001, 0, 410000000000212001, 0.00, 0, CURDATE(), NOW()),
(410000000000372001, 410000000000369001, 410000000000102001, 0, 410000000000213001, 0.00, 0, CURDATE(), NOW());

INSERT INTO `erp_mfg_work_report` (`id`, `code`, `order_id`, `product_id`, `routing_id`, `workstation_id`, `employee_id`, `report_date`, `quantity`, `qualified_qty`, `piece_rate`, `amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000373001, 'DEMO-MFG_WORK_REPORT-1', 410000000000100001, 410000000000000401, 410000000000352001, 0, 410000000000232001, CURDATE(), 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000374001, 'DEMO-MFG_WORK_REPORT-2', 410000000000101001, 410000000000000402, 410000000000353001, 0, 410000000000233001, CURDATE(), 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000375001, 'DEMO-MFG_WORK_REPORT-3', 410000000000102001, 410000000000000403, 410000000000354001, 0, 410000000000234001, CURDATE(), 0.00, 0.00, 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_mfg_workstation` (`id`, `code`, `name`, `capacity`, `status`, `created_at`) VALUES
(410000000000376001, 'DEMO-MFG_WORKSTATION-1', '演示mfg_workstation', 0, 0, NOW()),
(410000000000377001, 'DEMO-MFG_WORKSTATION-2', '演示mfg_workstation', 0, 0, NOW()),
(410000000000378001, 'DEMO-MFG_WORKSTATION-3', '演示mfg_workstation', 0, 0, NOW());

INSERT INTO `erp_notification` (`id`, `user_id`, `title`, `type`, `source_type`, `source_id`, `is_read`, `created_at`) VALUES
(410000000000379001, 0, '演示notification', 'd', 'd', 410000000000211001, 0, NOW()),
(410000000000380001, 0, '演示notification', 'd', 'd', 410000000000212001, 0, NOW()),
(410000000000381001, 0, '演示notification', 'd', 'd', 410000000000213001, 0, NOW());

INSERT INTO `erp_notification_channel_log` (`id`, `channel`, `to`, `subject`, `content`, `content_hash`, `status`, `message_id`, `error`, `sent_at`, `operator_id`, `created_at`, `updated_at`) VALUES
(410000000000382001, 'd', 'd', 'd', '演示数据', 'd', 0, 0, 'd', NOW(), 0, NOW(), NOW()),
(410000000000383001, 'd', 'd', 'd', '演示数据', 'd', 0, 0, 'd', NOW(), 0, NOW(), NOW()),
(410000000000384001, 'd', 'd', 'd', '演示数据', 'd', 0, 0, 'd', NOW(), 0, NOW(), NOW());

INSERT INTO `erp_notification_setting` (`id`, `user_id`, `notify_type`, `in_app`, `email`, `sms`, `created_at`, `updated_at`) VALUES
(410000000000385001, 1, 'd1', 0, 0, 0, NOW(), NOW()),
(410000000000386001, 2, 'd2', 0, 0, 0, NOW(), NOW()),
(410000000000387001, 3, 'd3', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_notification_template` (`id`, `code`, `name`, `title_tpl`, `channels`, `enabled`, `created_at`, `updated_at`) VALUES
(410000000000388001, 'DEMO-NOTIFICATION_TEMPLATE-1', '演示notification_template', 'd', 'd', 0, NOW(), NOW()),
(410000000000389001, 'DEMO-NOTIFICATION_TEMPLATE-2', '演示notification_template', 'd', 'd', 0, NOW(), NOW()),
(410000000000390001, 'DEMO-NOTIFICATION_TEMPLATE-3', '演示notification_template', 'd', 'd', 0, NOW(), NOW());

INSERT INTO `erp_oms_fulfillment` (`id`, `oms_order_id`, `warehouse_id`, `status`, `pick_task_id`, `pack_task_id`, `shipment_id`, `created_at`, `updated_at`) VALUES
(410000000000391001, 0, 410000000000000101, 0, 0, 0, 0, NOW(), NOW()),
(410000000000392001, 0, 410000000000000102, 0, 0, 0, 0, NOW(), NOW()),
(410000000000393001, 0, 410000000000000101, 0, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_oms_fulfillment_item` (`id`, `fulfillment_id`, `order_item_id`, `product_id`, `sku_id`, `allocated_quantity`, `picked_quantity`, `packed_quantity`, `shipped_quantity`, `created_at`, `updated_at`) VALUES
(410000000000394001, 410000000000391001, 0, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000395001, 410000000000392001, 0, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000396001, 410000000000393001, 0, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_oms_inventory_reservation` (`id`, `product_id`, `sku_id`, `warehouse_id`, `location_id`, `batch_code`, `source_type`, `source_id`, `source_item_id`, `reserved_quantity`, `status`, `created_at`, `updated_at`) VALUES
(410000000000397001, 410000000000000401, 410000000000000501, 410000000000000101, 410000000000000201, 'd', 'd', 410000000000211001, 0, 0.00, 0, NOW(), NOW()),
(410000000000398001, 410000000000000402, 410000000000000502, 410000000000000102, 410000000000000202, 'd', 'd', 410000000000212001, 0, 0.00, 0, NOW(), NOW()),
(410000000000399001, 410000000000000403, 410000000000000503, 410000000000000101, 410000000000000203, 'd', 'd', 410000000000213001, 0, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_oms_order` (`id`, `order_id`, `channel`, `channel_order_no`, `channel_store`, `fulfillment_status`, `payment_status`, `shipping_method`, `shipping_fee`, `buyer_message`, `seller_note`, `priority`, `created_at`, `updated_at`) VALUES
(410000000000400001, 410000000000100001, 'd', 'd', 'd', 0, 0, 'd', 0.00, 'd', 'd', 0, NOW(), NOW()),
(410000000000401001, 410000000000101001, 'd', 'd', 'd', 0, 0, 'd', 0.00, 'd', 'd', 0, NOW(), NOW()),
(410000000000402001, 410000000000102001, 'd', 'd', 'd', 0, 0, 'd', 0.00, 'd', 'd', 0, NOW(), NOW());

INSERT INTO `erp_oms_order_address` (`id`, `order_id`, `type`, `contact_name`, `phone`, `email`, `country`, `state`, `city`, `district`, `address_line1`, `address_line2`, `postal_code`, `created_at`, `updated_at`) VALUES
(410000000000403001, 410000000000100001, 0, 'd', '13800000001', 'demo1@example.com', 'd', 'd', 'd', 'd', 'd', 'd', 'd', NOW(), NOW()),
(410000000000404001, 410000000000101001, 0, 'd', '13800000002', 'demo2@example.com', 'd', 'd', 'd', 'd', 'd', 'd', 'd', NOW(), NOW()),
(410000000000405001, 410000000000102001, 0, 'd', '13800000003', 'demo3@example.com', 'd', 'd', 'd', 'd', 'd', 'd', 'd', NOW(), NOW());

INSERT INTO `erp_oms_rma` (`id`, `code`, `order_id`, `customer_id`, `type`, `reason`, `status`, `refund_amount`, `return_shipping_fee`, `return_shipment_id`, `approved_by`, `created_at`, `updated_at`) VALUES
(410000000000406001, 'DEMO-OMS_RMA-1', 410000000000100001, 410000000000000701, 0, '演示数据', 0, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000407001, 'DEMO-OMS_RMA-2', 410000000000101001, 410000000000000702, 0, '演示数据', 0, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000408001, 'DEMO-OMS_RMA-3', 410000000000102001, 410000000000000703, 0, '演示数据', 0, 0.00, 0.00, 0, 0, NOW(), NOW());

INSERT INTO `erp_oms_rma_item` (`id`, `rma_id`, `order_item_id`, `product_id`, `sku_id`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000409001, 410000000000406001, 0, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000410001, 410000000000407001, 0, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000411001, 410000000000408001, 0, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_openapi_app` (`id`, `app_name`, `app_key`, `app_secret`, `app_secret_hash`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(410000000000412001, 'd', 'd1', 'd', 'd', 0, 0, NOW(), NOW()),
(410000000000413001, 'd', 'd2', 'd', 'd', 0, 0, NOW(), NOW()),
(410000000000414001, 'd', 'd3', 'd', 'd', 0, 0, NOW(), NOW());

INSERT INTO `erp_print_template` (`id`, `code`, `name`, `target_type`, `content`, `paper_size`, `orientation`, `enabled`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000415001, 'DEMO-PRINT_TEMPLATE-1', '演示print_template', 'd', '演示数据', 'd', 'd', 0, '演示数据', NOW(), NOW()),
(410000000000416001, 'DEMO-PRINT_TEMPLATE-2', '演示print_template', 'd', '演示数据', 'd', 'd', 0, '演示数据', NOW(), NOW()),
(410000000000417001, 'DEMO-PRINT_TEMPLATE-3', '演示print_template', 'd', '演示数据', 'd', 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_product_price` (`id`, `product_id`, `sku_id`, `price_type`, `customer_level_id`, `price`, `created_at`, `updated_at`) VALUES
(410000000000418001, 410000000000000401, 410000000000000501, 'd', 410000000000000601, 0.00, NOW(), NOW()),
(410000000000419001, 410000000000000402, 410000000000000502, 'd', 410000000000000602, 0.00, NOW(), NOW()),
(410000000000420001, 410000000000000403, 410000000000000503, 'd', 410000000000000603, 0.00, NOW(), NOW());

INSERT INTO `erp_product_unit` (`id`, `product_id`, `unit_name`, `conversion_rate`, `is_base`, `created_at`, `updated_at`) VALUES
(410000000000421001, 410000000000000401, 'd', 0.00, 0, NOW(), NOW()),
(410000000000422001, 410000000000000402, 'd', 0.00, 0, NOW(), NOW()),
(410000000000423001, 410000000000000403, 'd', 0.00, 0, NOW(), NOW());

INSERT INTO `erp_project` (`id`, `code`, `name`, `customer_id`, `manager_user_id`, `status`, `priority`, `budget_amount`, `actual_cost`, `progress`, `created_at`, `updated_at`) VALUES
(410000000000424001, 'DEMO-PROJECT-1', '演示project', 410000000000000701, 0, 0, 0, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000425001, 'DEMO-PROJECT-2', '演示project', 410000000000000702, 0, 0, 0, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000426001, 'DEMO-PROJECT-3', '演示project', 410000000000000703, 0, 0, 0, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_project_cost` (`id`, `project_id`, `task_id`, `employee_id`, `work_date`, `source_type`, `timesheet_id`, `category`, `hours`, `rate`, `cost`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000427001, 410000000000424001, 410000000000028001, 410000000000232001, CURDATE(), 'd', 0, 0, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000428001, 410000000000425001, 410000000000029001, 410000000000233001, CURDATE(), 'd', 0, 0, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW()),
(410000000000429001, 410000000000426001, 410000000000030001, 410000000000234001, CURDATE(), 'd', 0, 0, 0.00, 0.00, 0.00, '演示数据', NOW(), NOW());

INSERT INTO `erp_project_gantt` (`id`, `project_id`, `task_id`, `dependency_task_id`, `created_at`, `updated_at`) VALUES
(410000000000430001, 410000000000424001, 410000000000028001, 0, NOW(), NOW()),
(410000000000431001, 410000000000425001, 410000000000029001, 0, NOW(), NOW()),
(410000000000432001, 410000000000426001, 410000000000030001, 0, NOW(), NOW());

INSERT INTO `erp_project_member` (`id`, `project_id`, `user_id`, `role`, `hourly_rate`, `created_at`) VALUES
(410000000000433001, 410000000000424001, 1, 'd', 0.00, NOW()),
(410000000000434001, 410000000000425001, 2, 'd', 0.00, NOW()),
(410000000000435001, 410000000000426001, 3, 'd', 0.00, NOW());

INSERT INTO `erp_project_task` (`id`, `project_id`, `parent_id`, `name`, `assignee_user_id`, `status`, `priority`, `estimated_hours`, `actual_hours`, `progress`, `seq`, `created_at`, `updated_at`) VALUES
(410000000000436001, 410000000000424001, 0, '演示project_task', 0, 0, 0, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000437001, 410000000000425001, 0, '演示project_task', 0, 0, 0, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000438001, 410000000000426001, 0, '演示project_task', 0, 0, 0, 0.00, 0.00, 0, 0, NOW(), NOW());

INSERT INTO `erp_project_timesheet` (`id`, `project_id`, `task_id`, `user_id`, `hours`, `work_date`, `description`, `created_at`, `updated_at`) VALUES
(410000000000439001, 410000000000424001, 410000000000028001, 0, 0.00, CURDATE(), '演示数据', NOW(), NOW()),
(410000000000440001, 410000000000425001, 410000000000029001, 0, 0.00, CURDATE(), '演示数据', NOW(), NOW()),
(410000000000441001, 410000000000426001, 410000000000030001, 0, 0.00, CURDATE(), '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_apply` (`id`, `code`, `apply_user_id`, `department`, `status`, `remark`, `approved_by`, `created_at`, `updated_at`) VALUES
(410000000000442001, 'DEMO-PURCHASE_APPLY-1', 0, 'd', 0, '演示数据', 0, NOW(), NOW()),
(410000000000443001, 'DEMO-PURCHASE_APPLY-2', 0, 'd', 0, '演示数据', 0, NOW(), NOW()),
(410000000000444001, 'DEMO-PURCHASE_APPLY-3', 0, 'd', 0, '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_purchase_apply_item` (`id`, `apply_id`, `product_id`, `sku_id`, `quantity`, `unit`, `estimated_price`, `created_at`, `updated_at`) VALUES
(410000000000445001, 410000000000442001, 410000000000000401, 410000000000000501, 0.00, 'd', 0.00, NOW(), NOW()),
(410000000000446001, 410000000000443001, 410000000000000402, 410000000000000502, 0.00, 'd', 0.00, NOW(), NOW()),
(410000000000447001, 410000000000444001, 410000000000000403, 410000000000000503, 0.00, 'd', 0.00, NOW(), NOW());

INSERT INTO `erp_purchase_order` (`id`, `code`, `apply_id`, `supplier_id`, `warehouse_id`, `total_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000448001, 'DEMO-PURCHASE_ORDER-1', 410000000000442001, 410000000000000801, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000449001, 'DEMO-PURCHASE_ORDER-2', 410000000000443001, 410000000000000802, 410000000000000102, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000450001, 'DEMO-PURCHASE_ORDER-3', 410000000000444001, 410000000000000803, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_order_item` (`id`, `order_id`, `product_id`, `sku_id`, `quantity`, `received_quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000451001, 410000000000100001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000452001, 410000000000101001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000453001, 410000000000102001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_purchase_receive` (`id`, `code`, `order_id`, `supplier_id`, `warehouse_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000454001, 'DEMO-PURCHASE_RECEIVE-1', 410000000000100001, 410000000000000801, 410000000000000101, 0, '演示数据', NOW(), NOW()),
(410000000000455001, 'DEMO-PURCHASE_RECEIVE-2', 410000000000101001, 410000000000000802, 410000000000000102, 0, '演示数据', NOW(), NOW()),
(410000000000456001, 'DEMO-PURCHASE_RECEIVE-3', 410000000000102001, 410000000000000803, 410000000000000101, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_receive_item` (`id`, `receive_id`, `order_item_id`, `product_id`, `sku_id`, `location_id`, `batch_code`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000457001, 410000000000364001, 410000000000451001, 410000000000000401, 410000000000000501, 410000000000000201, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000458001, 410000000000365001, 410000000000452001, 410000000000000402, 410000000000000502, 410000000000000202, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000459001, 410000000000366001, 410000000000453001, 410000000000000403, 410000000000000503, 410000000000000203, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_purchase_return` (`id`, `code`, `receive_id`, `supplier_id`, `warehouse_id`, `total_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000460001, 'DEMO-PURCHASE_RETURN-1', 410000000000364001, 410000000000000801, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000461001, 'DEMO-PURCHASE_RETURN-2', 410000000000365001, 410000000000000802, 410000000000000102, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000462001, 'DEMO-PURCHASE_RETURN-3', 410000000000366001, 410000000000000803, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_return_item` (`id`, `return_id`, `product_id`, `sku_id`, `batch_code`, `location_id`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000463001, 410000000000460001, 410000000000000401, 410000000000000501, 'd', 410000000000000201, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000464001, 410000000000461001, 410000000000000402, 410000000000000502, 'd', 410000000000000202, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000465001, 410000000000462001, 410000000000000403, 410000000000000503, 'd', 410000000000000203, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_purchase_rfq` (`id`, `rfq_no`, `buyer_id`, `supplier_range`, `status`, `awarded_quote_id`, `auditor_id`, `audit_remark`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000466001, 'd1', 0, 'd', 0, 0, 0, 'd', '演示数据', NOW(), NOW()),
(410000000000467001, 'd2', 0, 'd', 0, 0, 0, 'd', '演示数据', NOW(), NOW()),
(410000000000468001, 'd3', 0, 'd', 0, 0, 0, 'd', '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_rfq_item` (`id`, `rfq_id`, `product_id`, `quantity`, `unit`, `target_price`, `created_at`, `updated_at`) VALUES
(410000000000469001, 410000000000466001, 410000000000000401, 0.00, 'd', 0.00, NOW(), NOW()),
(410000000000470001, 410000000000467001, 410000000000000402, 0.00, 'd', 0.00, NOW(), NOW()),
(410000000000471001, 410000000000468001, 410000000000000403, 0.00, 'd', 0.00, NOW(), NOW());

INSERT INTO `erp_purchase_rfq_quote` (`id`, `rfq_id`, `supplier_id`, `amount`, `awarded`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000472001, 410000000000466001, 410000000000000801, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000473001, 410000000000467001, 410000000000000802, 0.00, 0, 0, '演示数据', NOW(), NOW()),
(410000000000474001, 410000000000468001, 410000000000000803, 0.00, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_purchase_rfq_quote_item` (`id`, `quote_id`, `rfq_item_id`, `product_id`, `unit_price`, `amount`, `created_at`, `updated_at`) VALUES
(410000000000475001, 410000000000472001, 410000000000469001, 410000000000000401, 0.00, 0.00, NOW(), NOW()),
(410000000000476001, 410000000000473001, 410000000000470001, 410000000000000402, 0.00, 0.00, NOW(), NOW()),
(410000000000477001, 410000000000474001, 410000000000471001, 410000000000000403, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_purchase_settlement` (`id`, `supplier_id`, `receive_id`, `amount`, `paid_amount`, `status`, `created_at`, `updated_at`) VALUES
(410000000000478001, 410000000000000801, 410000000000364001, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000479001, 410000000000000802, 410000000000365001, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000480001, 410000000000000803, 410000000000366001, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_quality_inspection_standard` (`id`, `name`, `code`, `product_id`, `type`, `sampling_plan`, `status`, `created_at`, `updated_at`) VALUES
(410000000000481001, '演示quality_inspection_standard', 'DEMO-QUALITY_INSPECTION_STANDARD-1', 410000000000000401, 'd', 'd', 0, NOW(), NOW()),
(410000000000482001, '演示quality_inspection_standard', 'DEMO-QUALITY_INSPECTION_STANDARD-2', 410000000000000402, 'd', 'd', 0, NOW(), NOW()),
(410000000000483001, '演示quality_inspection_standard', 'DEMO-QUALITY_INSPECTION_STANDARD-3', 410000000000000403, 'd', 'd', 0, NOW(), NOW());

INSERT INTO `erp_quality_ipqc_record` (`id`, `code`, `production_order_id`, `product_id`, `workstation_id`, `standard_id`, `inspected_qty`, `passed_qty`, `rejected_qty`, `result`, `inspector`, `remark`, `status`, `created_at`, `updated_at`) VALUES
(410000000000484001, 'DEMO-QUALITY_IPQC_RECORD-1', 410000000000349001, 410000000000000401, 410000000000376001, 410000000000481001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000485001, 'DEMO-QUALITY_IPQC_RECORD-2', 410000000000350001, 410000000000000402, 410000000000377001, 410000000000482001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000486001, 'DEMO-QUALITY_IPQC_RECORD-3', 410000000000351001, 410000000000000403, 410000000000378001, 410000000000483001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_quality_iqc_record` (`id`, `code`, `receiving_id`, `product_id`, `standard_id`, `inspected_qty`, `passed_qty`, `rejected_qty`, `result`, `inspector`, `remark`, `status`, `created_at`, `updated_at`) VALUES
(410000000000487001, 'DEMO-QUALITY_IQC_RECORD-1', 0, 410000000000000401, 410000000000481001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000488001, 'DEMO-QUALITY_IQC_RECORD-2', 0, 410000000000000402, 410000000000482001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000489001, 'DEMO-QUALITY_IQC_RECORD-3', 0, 410000000000000403, 410000000000483001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_quality_nonconformity` (`id`, `code`, `source_type`, `source_id`, `product_id`, `defect_type`, `defect_qty`, `severity`, `disposition`, `status`, `reported_by`, `created_at`, `updated_at`) VALUES
(410000000000490001, 'DEMO-QUALITY_NONCONFORMITY-1', 'd', 410000000000211001, 410000000000000401, 'd', 0, 'd', 'd', 0, 'd', NOW(), NOW()),
(410000000000491001, 'DEMO-QUALITY_NONCONFORMITY-2', 'd', 410000000000212001, 410000000000000402, 'd', 0, 'd', 'd', 0, 'd', NOW(), NOW()),
(410000000000492001, 'DEMO-QUALITY_NONCONFORMITY-3', 'd', 410000000000213001, 410000000000000403, 'd', 0, 'd', 'd', 0, 'd', NOW(), NOW());

INSERT INTO `erp_quality_oqc_record` (`id`, `code`, `delivery_id`, `product_id`, `standard_id`, `inspected_qty`, `passed_qty`, `rejected_qty`, `result`, `inspector`, `remark`, `status`, `created_at`, `updated_at`) VALUES
(410000000000493001, 'DEMO-QUALITY_OQC_RECORD-1', 0, 410000000000000401, 410000000000481001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000494001, 'DEMO-QUALITY_OQC_RECORD-2', 0, 410000000000000402, 410000000000482001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW()),
(410000000000495001, 'DEMO-QUALITY_OQC_RECORD-3', 0, 410000000000000403, 410000000000483001, 0, 0, 0, 'd', 'd', '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_report_dataset` (`id`, `template_id`, `name`, `rows_count`, `created_at`) VALUES
(410000000000496001, 410000000000244001, '演示report_dataset', 0, NOW()),
(410000000000497001, 410000000000245001, '演示report_dataset', 0, NOW()),
(410000000000498001, 410000000000246001, '演示report_dataset', 0, NOW());

INSERT INTO `erp_report_field` (`id`, `template_id`, `name`, `field`, `label`, `data_type`, `aggregator`, `sort_order`, `width`, `visible`, `created_at`) VALUES
(410000000000499001, 410000000000244001, '演示report_field', 'd', '演示report_field', 'd', 'd', 0, 0, 0, NOW()),
(410000000000500001, 410000000000245001, '演示report_field', 'd', '演示report_field', 'd', 'd', 0, 0, 0, NOW()),
(410000000000501001, 410000000000246001, '演示report_field', 'd', '演示report_field', 'd', 'd', 0, 0, 0, NOW());

INSERT INTO `erp_report_filter` (`id`, `template_id`, `name`, `field`, `filter_type`, `default_value`, `required`, `created_at`) VALUES
(410000000000502001, 410000000000244001, '演示report_filter', 'd', 'd', 'd', 0, NOW()),
(410000000000503001, 410000000000245001, '演示report_filter', 'd', 'd', 'd', 0, NOW()),
(410000000000504001, 410000000000246001, '演示report_filter', 'd', 'd', 'd', 0, NOW());

INSERT INTO `erp_report_schedule` (`id`, `template_id`, `name`, `frequency`, `enabled`, `created_at`, `updated_at`) VALUES
(410000000000505001, 410000000000244001, '演示report_schedule', 0, 0, NOW(), NOW()),
(410000000000506001, 410000000000245001, '演示report_schedule', 0, 0, NOW(), NOW()),
(410000000000507001, 410000000000246001, '演示report_schedule', 0, 0, NOW(), NOW());

INSERT INTO `erp_report_template` (`id`, `code`, `name`, `module`, `chart_type`, `status`, `created_at`, `updated_at`) VALUES
(410000000000508001, 'DEMO-REPORT_TEMPLATE-1', '演示report_template', 'd', 'd', 0, NOW(), NOW()),
(410000000000509001, 'DEMO-REPORT_TEMPLATE-2', '演示report_template', 'd', 'd', 0, NOW(), NOW()),
(410000000000510001, 'DEMO-REPORT_TEMPLATE-3', '演示report_template', 'd', 'd', 0, NOW(), NOW());

INSERT INTO `erp_sales_delivery` (`id`, `code`, `order_id`, `customer_id`, `warehouse_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000511001, 'DEMO-SALES_DELIVERY-1', 410000000000100001, 410000000000000701, 410000000000000101, 0, '演示数据', NOW(), NOW()),
(410000000000512001, 'DEMO-SALES_DELIVERY-2', 410000000000101001, 410000000000000702, 410000000000000102, 0, '演示数据', NOW(), NOW()),
(410000000000513001, 'DEMO-SALES_DELIVERY-3', 410000000000102001, 410000000000000703, 410000000000000101, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_sales_delivery_item` (`id`, `delivery_id`, `order_item_id`, `product_id`, `sku_id`, `location_id`, `batch_code`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000514001, 410000000000511001, 410000000000451001, 410000000000000401, 410000000000000501, 410000000000000201, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000515001, 410000000000512001, 410000000000452001, 410000000000000402, 410000000000000502, 410000000000000202, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000516001, 410000000000513001, 410000000000453001, 410000000000000403, 410000000000000503, 410000000000000203, 'd', 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_sales_order` (`id`, `code`, `quotation_id`, `customer_id`, `warehouse_id`, `total_amount`, `discount_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000517001, 'DEMO-SALES_ORDER-1', 410000000000067001, 410000000000000701, 410000000000000101, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000518001, 'DEMO-SALES_ORDER-2', 410000000000068001, 410000000000000702, 410000000000000102, 0.00, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000519001, 'DEMO-SALES_ORDER-3', 410000000000069001, 410000000000000703, 410000000000000101, 0.00, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_sales_order_item` (`id`, `order_id`, `product_id`, `sku_id`, `quantity`, `delivered_quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000520001, 410000000000100001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000521001, 410000000000101001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000522001, 410000000000102001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_sales_quotation` (`id`, `code`, `customer_id`, `total_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000523001, 'DEMO-SALES_QUOTATION-1', 410000000000000701, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000524001, 'DEMO-SALES_QUOTATION-2', 410000000000000702, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000525001, 'DEMO-SALES_QUOTATION-3', 410000000000000703, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_sales_quotation_item` (`id`, `quotation_id`, `product_id`, `sku_id`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000526001, 410000000000067001, 410000000000000401, 410000000000000501, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000527001, 410000000000068001, 410000000000000402, 410000000000000502, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000528001, 410000000000069001, 410000000000000403, 410000000000000503, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_sales_return` (`id`, `code`, `delivery_id`, `customer_id`, `warehouse_id`, `total_amount`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000529001, 'DEMO-SALES_RETURN-1', 410000000000511001, 410000000000000701, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000530001, 'DEMO-SALES_RETURN-2', 410000000000512001, 410000000000000702, 410000000000000102, 0.00, 0, '演示数据', NOW(), NOW()),
(410000000000531001, 'DEMO-SALES_RETURN-3', 410000000000513001, 410000000000000703, 410000000000000101, 0.00, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_sales_return_item` (`id`, `return_id`, `product_id`, `sku_id`, `batch_code`, `location_id`, `quantity`, `price`, `amount`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000532001, 410000000000460001, 410000000000000401, 410000000000000501, 'd', 410000000000000201, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000533001, 410000000000461001, 410000000000000402, 410000000000000502, 'd', 410000000000000202, 0.00, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000534001, 410000000000462001, 410000000000000403, 410000000000000503, 'd', 410000000000000203, 0.00, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_sales_settlement` (`id`, `customer_id`, `delivery_id`, `amount`, `received_amount`, `status`, `created_at`, `updated_at`) VALUES
(410000000000535001, 410000000000000701, 410000000000511001, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000536001, 410000000000000702, 410000000000512001, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000537001, 410000000000000703, 410000000000513001, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_supplier_assessment` (`id`, `supplier_id`, `total_score`, `grade`, `assessor_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000538001, 410000000000000801, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000539001, 410000000000000802, 0.00, 'd', 0, '演示数据', NOW(), NOW()),
(410000000000540001, 410000000000000803, 0.00, 'd', 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_system_config` (`id`, `group`, `key`, `type`, `description`, `created_at`, `updated_at`) VALUES
(410000000000541001, 'g1', 'DEMO-SYSTEM_CONFIG-1', 'd', '演示数据', NOW(), NOW()),
(410000000000542001, 'g2', 'DEMO-SYSTEM_CONFIG-2', 'd', '演示数据', NOW(), NOW()),
(410000000000543001, 'g3', 'DEMO-SYSTEM_CONFIG-3', 'd', '演示数据', NOW(), NOW());

INSERT INTO `erp_tax_input_invoice` (`id`, `invoice_code`, `invoice_no`, `issue_date`, `seller_name`, `seller_tax_no`, `buyer_name`, `buyer_tax_no`, `amount`, `untaxed_amount`, `tax_amount`, `verify_status`, `deduct_status`, `deduct_period`, `source`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000544001, 'd1', 'd1', CURDATE(), 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0, 0, 'd', 'd', '演示数据', NOW(), NOW()),
(410000000000545001, 'd2', 'd2', CURDATE(), 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0, 0, 'd', 'd', '演示数据', NOW(), NOW()),
(410000000000546001, 'd3', 'd3', CURDATE(), 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0, 0, 'd', 'd', '演示数据', NOW(), NOW());

INSERT INTO `erp_tax_issue_log` (`id`, `invoice_id`, `action`, `bill_no`, `platform`, `success`, `error`, `operator_id`, `created_at`) VALUES
(410000000000547001, 410000000000169001, 'd', 'd', 'd', 0, 'd', 0, NOW()),
(410000000000548001, 410000000000170001, 'd', 'd', 'd', 0, 'd', 0, NOW()),
(410000000000549001, 410000000000171001, 'd', 'd', 'd', 0, 'd', 0, NOW());

INSERT INTO `erp_tenant` (`id`, `company_id`, `tenant_code`, `plan`, `status`, `expire_at`, `remark`, `created_by`, `created_at`, `updated_at`) VALUES
(410000000000550001, 410000000000031001, 'd1', 0, 0, NOW(), '演示数据', 0, NOW(), NOW()),
(410000000000551001, 410000000000032001, 'd2', 0, 0, NOW(), '演示数据', 0, NOW(), NOW()),
(410000000000552001, 410000000000033001, 'd3', 0, 0, NOW(), '演示数据', 0, NOW(), NOW());

INSERT INTO `erp_tms_carrier` (`id`, `code`, `name`, `type`, `website`, `tracking_url_template`, `api_provider`, `contact_phone`, `status`, `created_at`, `updated_at`) VALUES
(410000000000553001, 'DEMO-TMS_CARRIER-1', '演示tms_carrier', 'd', 'd', 'd', 'd', 'd', 0, NOW(), NOW()),
(410000000000554001, 'DEMO-TMS_CARRIER-2', '演示tms_carrier', 'd', 'd', 'd', 'd', 'd', 0, NOW(), NOW()),
(410000000000555001, 'DEMO-TMS_CARRIER-3', '演示tms_carrier', 'd', 'd', 'd', 'd', 'd', 0, NOW(), NOW());

INSERT INTO `erp_tms_carrier_service` (`id`, `carrier_id`, `code`, `name`, `type`, `estimated_days_min`, `estimated_days_max`, `status`, `created_at`, `updated_at`) VALUES
(410000000000556001, 410000000000553001, 'DEMO-TMS_CARRIER_SERVICE-1', '演示tms_carrier_service', 'd', 0, 0, 0, NOW(), NOW()),
(410000000000557001, 410000000000554001, 'DEMO-TMS_CARRIER_SERVICE-2', '演示tms_carrier_service', 'd', 0, 0, 0, NOW(), NOW()),
(410000000000558001, 410000000000555001, 'DEMO-TMS_CARRIER_SERVICE-3', '演示tms_carrier_service', 'd', 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_tms_freight_invoice` (`id`, `code`, `carrier_id`, `shipment_id`, `amount`, `currency`, `status`, `created_at`, `updated_at`) VALUES
(410000000000559001, 'DEMO-TMS_FREIGHT_INVOICE-1', 410000000000553001, 0, 0.00, 'd', 0, NOW(), NOW()),
(410000000000560001, 'DEMO-TMS_FREIGHT_INVOICE-2', 410000000000554001, 0, 0.00, 'd', 0, NOW(), NOW()),
(410000000000561001, 'DEMO-TMS_FREIGHT_INVOICE-3', 410000000000555001, 0, 0.00, 'd', 0, NOW(), NOW());

INSERT INTO `erp_tms_freight_rate` (`id`, `carrier_service_id`, `origin_country`, `origin_zone`, `dest_country`, `dest_zone`, `weight_from_kg`, `weight_to_kg`, `base_rate`, `per_kg_rate`, `fuel_surcharge_pct`, `currency`, `valid_from`, `status`, `created_at`, `updated_at`) VALUES
(410000000000562001, 410000000000556001, 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0.00, 0.00, 'd', CURDATE(), 0, NOW(), NOW()),
(410000000000563001, 410000000000557001, 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0.00, 0.00, 'd', CURDATE(), 0, NOW(), NOW()),
(410000000000564001, 410000000000558001, 'd', 'd', 'd', 'd', 0.00, 0.00, 0.00, 0.00, 0.00, 'd', CURDATE(), 0, NOW(), NOW());

INSERT INTO `erp_tms_shipment` (`id`, `code`, `carrier_service_id`, `tracking_no`, `status`, `shipping_label_url`, `total_weight_kg`, `total_volume_cm3`, `package_count`, `freight_charge`, `insurance_charge`, `currency`, `created_at`, `updated_at`) VALUES
(410000000000565001, 'DEMO-TMS_SHIPMENT-1', 410000000000556001, 'd', 0, 'd', 0.00, 0.00, 0, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000566001, 'DEMO-TMS_SHIPMENT-2', 410000000000557001, 'd', 0, 'd', 0.00, 0.00, 0, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000567001, 'DEMO-TMS_SHIPMENT-3', 410000000000558001, 'd', 0, 'd', 0.00, 0.00, 0, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_tms_shipment_package` (`id`, `shipment_id`, `pack_task_id`, `package_no`, `weight_kg`, `length_cm`, `width_cm`, `height_cm`, `declared_value`, `created_at`, `updated_at`) VALUES
(410000000000568001, 410000000000565001, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000569001, 410000000000566001, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW()),
(410000000000570001, 410000000000567001, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0.00, NOW(), NOW());

INSERT INTO `erp_tms_tracking_event` (`id`, `shipment_id`, `status_code`, `description`, `location`, `created_at`) VALUES
(410000000000571001, 410000000000565001, 'd', '演示数据', 'd', NOW()),
(410000000000572001, 410000000000566001, 'd', '演示数据', 'd', NOW()),
(410000000000573001, 410000000000567001, 'd', '演示数据', 'd', NOW());

INSERT INTO `erp_transfer` (`id`, `code`, `from_warehouse_id`, `to_warehouse_id`, `status`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000574001, 'DEMO-TRANSFER-1', 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000575001, 'DEMO-TRANSFER-2', 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000576001, 'DEMO-TRANSFER-3', 0, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_transfer_item` (`id`, `transfer_id`, `product_id`, `sku_id`, `batch_code`, `from_location_id`, `to_location_id`, `quantity`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000577001, 410000000000574001, 410000000000000401, 410000000000000501, 'd', 0, 0, 0.00, 'd', NOW(), NOW()),
(410000000000578001, 410000000000575001, 410000000000000402, 410000000000000502, 'd', 0, 0, 0.00, 'd', NOW(), NOW()),
(410000000000579001, 410000000000576001, 410000000000000403, 410000000000000503, 'd', 0, 0, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_webhook_delivery_log` (`id`, `subscription_id`, `event`, `payload`, `status`, `attempts`, `response_summary`, `created_at`, `updated_at`) VALUES
(410000000000580001, 0, 'd', '{}', 1, 0, 'd', NOW(), NOW()),
(410000000000581001, 0, 'd', '{}', 1, 0, 'd', NOW(), NOW()),
(410000000000582001, 0, 'd', '{}', 1, 0, 'd', NOW(), NOW());

INSERT INTO `erp_webhook_subscription` (`id`, `app_id`, `event`, `target_url`, `secret`, `enabled`, `last_status`, `failed_count`, `created_by`, `created_at`, `updated_at`) VALUES
(410000000000583001, 410000000000412001, '{}', 'd', 'd', 0, 'd', 0, 0, NOW(), NOW()),
(410000000000584001, 410000000000413001, '{}', 'd', 'd', 0, 'd', 0, 0, NOW(), NOW()),
(410000000000585001, 410000000000414001, '{}', 'd', 'd', 0, 'd', 0, 0, NOW(), NOW());

INSERT INTO `erp_wms_asn` (`id`, `code`, `supplier_id`, `warehouse_id`, `purchase_order_id`, `carrier`, `tracking_no`, `status`, `total_packages`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000586001, 'DEMO-WMS_ASN-1', 410000000000000801, 410000000000000101, 410000000000448001, 'd', 'd', 0, 0, '演示数据', NOW(), NOW()),
(410000000000587001, 'DEMO-WMS_ASN-2', 410000000000000802, 410000000000000102, 410000000000449001, 'd', 'd', 0, 0, '演示数据', NOW(), NOW()),
(410000000000588001, 'DEMO-WMS_ASN-3', 410000000000000803, 410000000000000101, 410000000000450001, 'd', 'd', 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_wms_asn_item` (`id`, `asn_id`, `product_id`, `sku_id`, `expected_quantity`, `received_quantity`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000589001, 410000000000586001, 410000000000000401, 410000000000000501, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000590001, 410000000000587001, 410000000000000402, 410000000000000502, 0.00, 0.00, 'd', NOW(), NOW()),
(410000000000591001, 410000000000588001, 410000000000000403, 410000000000000503, 0.00, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_wms_location` (`id`, `location_id`, `zone_id`, `aisle`, `rack`, `level`, `bin`, `barcode`, `length_cm`, `width_cm`, `height_cm`, `max_weight_kg`, `max_volume_cm3`, `pick_sequence`, `status`, `created_at`, `updated_at`) VALUES
(410000000000592001, 410000000000000201, 0, 'd', 'd', 'd', 'd', 'DEMO-WMS_LOCATION-1', 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000593001, 410000000000000202, 0, 'd', 'd', 'd', 'd', 'DEMO-WMS_LOCATION-2', 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NOW(), NOW()),
(410000000000594001, 410000000000000203, 0, 'd', 'd', 'd', 'd', 'DEMO-WMS_LOCATION-3', 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NOW(), NOW());

INSERT INTO `erp_wms_pack_task` (`id`, `code`, `warehouse_id`, `status`, `package_type`, `weight_kg`, `length_cm`, `width_cm`, `height_cm`, `assigned_to`, `created_at`, `updated_at`) VALUES
(410000000000595001, 'DEMO-WMS_PACK_TASK-1', 410000000000000101, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000596001, 'DEMO-WMS_PACK_TASK-2', 410000000000000102, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW()),
(410000000000597001, 'DEMO-WMS_PACK_TASK-3', 410000000000000101, 0, 'd', 0.00, 0.00, 0.00, 0.00, 0, NOW(), NOW());

INSERT INTO `erp_wms_pick_item` (`id`, `pick_task_id`, `product_id`, `sku_id`, `batch_code`, `location_id`, `ordered_quantity`, `picked_quantity`, `unit`, `status`, `created_at`, `updated_at`) VALUES
(410000000000598001, 0, 410000000000000401, 410000000000000501, 'd', 410000000000000201, 0.00, 0.00, 'd', 0, NOW(), NOW()),
(410000000000599001, 0, 410000000000000402, 410000000000000502, 'd', 410000000000000202, 0.00, 0.00, 'd', 0, NOW(), NOW()),
(410000000000600001, 0, 410000000000000403, 410000000000000503, 'd', 410000000000000203, 0.00, 0.00, 'd', 0, NOW(), NOW());

INSERT INTO `erp_wms_pick_task` (`id`, `code`, `warehouse_id`, `wave_id`, `type`, `status`, `assigned_to`, `priority`, `created_at`, `updated_at`) VALUES
(410000000000601001, 'DEMO-WMS_PICK_TASK-1', 410000000000000101, 0, 0, 0, 0, 0, NOW(), NOW()),
(410000000000602001, 'DEMO-WMS_PICK_TASK-2', 410000000000000102, 0, 0, 0, 0, 0, NOW(), NOW()),
(410000000000603001, 'DEMO-WMS_PICK_TASK-3', 410000000000000101, 0, 0, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_wms_putaway_item` (`id`, `putaway_id`, `product_id`, `sku_id`, `batch_code`, `from_location_id`, `to_location_id`, `quantity`, `unit`, `created_at`, `updated_at`) VALUES
(410000000000604001, 0, 410000000000000401, 410000000000000501, 'd', 0, 0, 0.00, 'd', NOW(), NOW()),
(410000000000605001, 0, 410000000000000402, 410000000000000502, 'd', 0, 0, 0.00, 'd', NOW(), NOW()),
(410000000000606001, 0, 410000000000000403, 410000000000000503, 'd', 0, 0, 0.00, 'd', NOW(), NOW());

INSERT INTO `erp_wms_putaway_task` (`id`, `code`, `warehouse_id`, `receiving_id`, `status`, `strategy`, `assigned_to`, `created_at`, `updated_at`) VALUES
(410000000000607001, 'DEMO-WMS_PUTAWAY_TASK-1', 410000000000000101, 0, 0, 'd', 0, NOW(), NOW()),
(410000000000608001, 'DEMO-WMS_PUTAWAY_TASK-2', 410000000000000102, 0, 0, 'd', 0, NOW(), NOW()),
(410000000000609001, 'DEMO-WMS_PUTAWAY_TASK-3', 410000000000000101, 0, 0, 'd', 0, NOW(), NOW());

INSERT INTO `erp_wms_receiving` (`id`, `code`, `asn_id`, `warehouse_id`, `dock_location_id`, `status`, `receiver_id`, `remark`, `created_at`, `updated_at`) VALUES
(410000000000610001, 'DEMO-WMS_RECEIVING-1', 410000000000586001, 410000000000000101, 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000611001, 'DEMO-WMS_RECEIVING-2', 410000000000587001, 410000000000000102, 0, 0, 0, '演示数据', NOW(), NOW()),
(410000000000612001, 'DEMO-WMS_RECEIVING-3', 410000000000588001, 410000000000000101, 0, 0, 0, '演示数据', NOW(), NOW());

INSERT INTO `erp_wms_wave` (`id`, `code`, `warehouse_id`, `type`, `status`, `priority`, `created_at`, `updated_at`) VALUES
(410000000000613001, 'DEMO-WMS_WAVE-1', 410000000000000101, 0, 0, 0, NOW(), NOW()),
(410000000000614001, 'DEMO-WMS_WAVE-2', 410000000000000102, 0, 0, 0, NOW(), NOW()),
(410000000000615001, 'DEMO-WMS_WAVE-3', 410000000000000101, 0, 0, 0, NOW(), NOW());

INSERT INTO `erp_wms_wave_order` (`id`, `wave_id`, `oms_order_id`, `sort`, `created_at`) VALUES
(410000000000616001, 410000000000613001, 410000000000400001, 0, NOW()),
(410000000000617001, 410000000000614001, 410000000000401001, 0, NOW()),
(410000000000618001, 410000000000615001, 410000000000402001, 0, NOW());

INSERT INTO `erp_wms_zone` (`id`, `warehouse_id`, `code`, `name`, `type`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(410000000000619001, 410000000000000101, 'DEMO-WMS_ZONE-1', '演示wms_zone', 0, 0, 0, NOW(), NOW()),
(410000000000620001, 410000000000000102, 'DEMO-WMS_ZONE-2', '演示wms_zone', 0, 0, 0, NOW(), NOW()),
(410000000000621001, 410000000000000101, 'DEMO-WMS_ZONE-3', '演示wms_zone', 0, 0, 0, NOW(), NOW());
