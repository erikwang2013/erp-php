/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, moneyCol, statusCol, textCol } from '../cells';
import { res, type DictMap, type MenuGroup, type ResourceConfig } from '../types';

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份，禁止跨表复用 */
// 四个状态都走 docStatus：与 React 端同构（两端都取 .dict/.filter），文案一份，另带出状态筛选项
const OPP = docStatus(['输单', '进行中', '已成交']); // 商机 erp_crm_opportunity
const CONTRACT = docStatus(['草稿', '待审批', '已审批', '执行中', '已完成', '已终止']); // 合同 erp_crm_contract
const QUOT = docStatus(['草稿', '已发送', '客户确认', '已转合同', '已失效']); // 报价 erp_crm_quotation
const TICKET = docStatus(['待处理', '处理中', '已解决', '已关闭']); // 工单 erp_crm_ticket

/**
 * cfg.dicts（逐键值字典）：列表列/详情抽屉/动作结果面板三处共用。
 * 文案逐字抄自 database/install.sql 该表该列注释；注释无中文的（以「lead 判定」标注）
 * 按 lead 口径定译名。禁止跨表复用。
 */
/** erp_crm_ticket（install.sql:1888 priority、1889 status、1890 category） */
const TICKET_DICTS: DictMap = {
  status: { 0: '待处理', 1: '处理中', 2: '已解决', 3: '已关闭' },
  priority: { 1: '低', 2: '中', 3: '高', 4: '紧急' },
  // DDL 无中文，译名为 lead 判定（tech/complaint/inquiry/return/other）
  category: { tech: '技术', complaint: '投诉', inquiry: '咨询', return: '退货', other: '其他' },
};
/** erp_crm_campaign（install.sql:1852 type、1853 status） */
const CAMPAIGN_DICTS: DictMap = {
  // DDL 无中文，译名为 lead 判定；event=活动（与 analytics 的 activity=活跃度 区分）
  type: { email: '邮件', sms: '短信', phone: '电话', event: '活动', social: '社交', other: '其他' },
  status: { 0: '计划中', 1: '进行中', 2: '已完成', 3: '已取消' },
};

const c = (title: string, endpoint: string, extra: Partial<ResourceConfig> = {}) =>
  res(title, endpoint, { moduleKey: 'crm', deleteNeedsPassword: true, ...extra });

export const crmMenus: MenuGroup[] = [
  {
    label: 'CRM',
    icon: 'star',
    moduleKey: 'crm',
    children: [
      {
        label: '商机管理',
        path: '/crm/opportunity',
        cfg: {
          title: '商机管理',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          endpoint: '/admin/v1/crm/opportunity',
          filters: OPP.filter,
          // 表无 code 列；customer_name/stage_name 由 OpportunityController::enrichNames 补
          columns: [textCol('name', '商机名称', true), textCol('customer_name', '客户'), textCol('stage_name', '阶段'), moneyCol('estimated_amount', '金额'), statusCol(OPP.dict), dateCol('created_at', '创建时间')],
          fields: [
            { key: 'name', label: '商机名称', required: true },
            { key: 'estimated_amount', label: '预计金额', type: 'number' },
            { key: 'stage_id', label: '漏斗阶段', required: true, source: { endpoint: '/admin/v1/crm/funnel' } },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      // erp_crm_contact：is_primary 0=否 1=是（install.sql:1435）、status 0=禁用 1=启用（1436）
      { label: '联系人', path: '/crm/contact', cfg: c('联系人', '/admin/v1/crm/contact', { dicts: { is_primary: { 0: '否', 1: '是' }, status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'name', label: '联系人名称', required: true }] }) },
      {
        label: '公海池',
        path: '/crm/pool',
        cfg: {
          title: '公海池',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          canDelete: false, // 后端无 pool destroy 路由，公海客户只认领/释放
          endpoint: '/admin/v1/crm/pool',
          // 池内行是 erp_customer（PoolController::index → poolCustomers → list(Customer::class)，
          // 恒 status=0 且 owner_user_id=0），不是 erp_crm_pool_record —— 后者只被写入、无任何列表出口。
          // erp_customer（install.sql:366 credit_frozen、369 status）
          dicts: { status: { 0: '禁用', 1: '启用' }, credit_frozen: { 0: '正常', 1: '冻结' } },
          // contact_person 是 erp_customer 的真列名（install.sql:360）；`contact` 不在行里，该列会恒空
          columns: [textCol('name', '客户', true), textCol('contact_person', '联系人'), textCol('phone', '电话')],
          actions: [
            { label: '认领', icon: 'user', path: (r) => `/admin/v1/crm/pool/claim/${String(r.id)}`, message: '已认领' },
            { label: '释放', icon: 'logout', variant: 'icon-danger', path: (r) => `/admin/v1/crm/pool/release/${String(r.id)}`, message: '已释放回公海' },
          ],
        },
      },
      {
        label: '合同管理',
        path: '/crm/contract',
        cfg: {
          title: '合同管理',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          endpoint: '/admin/v1/crm/contract',
          filters: CONTRACT.filter,
          // 列表无 join：无客户名；金额真列 total_amount、签订时间 signed_at（ContractController::index 不补名）
          columns: [textCol('code', '合同号', true), textCol('name', '合同名称'), moneyCol('total_amount', '金额'), statusCol(CONTRACT.dict), dateCol('signed_at', '签订日期')],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'name', label: '合同名称', required: true },
            { key: 'total_amount', label: '合同金额', type: 'number' },
            { key: 'signed_at', label: '签订日期', type: 'date' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '状态流转', icon: 'activity', path: (r) => `/admin/v1/crm/contract/${String(r.id)}/transition`, message: '状态已更新' }],
        },
      },
      {
        label: '报价单',
        path: '/crm/quotation',
        cfg: {
          title: 'CRM 报价单',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          endpoint: '/admin/v1/crm/quotation',
          // QuotationController::index 先按裸 ID 补引用名再编码 FK（反序则 (int) 拿 hashid 恒 0），回包含 customer_name
          filters: QUOT.filter,
          columns: [textCol('code', '编号', true), textCol('customer_name', '客户'), moneyCol('total_amount', '金额'), statusCol(QUOT.dict), dateCol('quoted_at', '报价时间')],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'total_amount', label: '金额', type: 'number' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '转合同', icon: 'file', path: (r) => `/admin/v1/crm/quotation/${String(r.id)}/to-contract`, message: '已生成合同' }],
        },
      },
      { label: '营销活动', path: '/crm/campaign', cfg: c('营销活动', '/admin/v1/crm/campaign', { dicts: CAMPAIGN_DICTS, fields: [{ key: 'name', label: '活动名称', required: true }, { key: 'owner_user_id', label: '负责人', required: true, source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      {
        label: '服务工单',
        path: '/crm/ticket',
        cfg: {
          title: '服务工单',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          endpoint: '/admin/v1/crm/ticket',
          // 列只覆盖 status；priority/category 在抽屉与「派单/解决」结果面板（回整行）里靠 dicts
          dicts: TICKET_DICTS,
          filters: TICKET.filter,
          // customer_name 由 TicketController::index:98 补（表无 name 列，title 才是主文本）
          columns: [textCol('code', '工单号', true), textCol('title', '标题'), textCol('customer_name', '客户'), statusCol(TICKET.dict), dateCol('created_at', '创建时间')],
          fields: [
            { key: 'title', label: '工单标题', required: true },
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'category', label: '工单分类' },
            { key: 'priority', label: '优先级', type: 'number' },
            { key: 'assignee_user_id', label: '指派人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } },
          ],
          actions: [
            { label: '派单', icon: 'send', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/assign`, message: '已派单' },
            { label: '回复', icon: 'edit', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/reply`, message: '已回复' },
            { label: '解决', icon: 'check', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/resolve`, message: '工单已解决' },
          ],
        },
      },
      // erp_crm_follow_record.method（install.sql:1413）：DDL 无中文，译名为 lead 判定（message=短信）
      { label: '跟进记录', path: '/crm/follow', cfg: c('跟进记录', '/admin/v1/crm/follow', { dicts: { method: { phone: '电话', visit: '拜访', email: '邮件', message: '短信', other: '其他' } }, fields: [{ key: 'content', label: '跟进内容', type: 'textarea', full: true }, { key: 'customer_id', label: '客户', source: { endpoint: '/admin/v1/customer' } }, { key: 'follow_user_id', label: '跟进人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      // erp_crm_funnel_stage.status: 0=禁用 1=启用（install.sql:1378）
      { label: '销售漏斗', path: '/crm/funnel', cfg: c('销售漏斗', '/admin/v1/crm/funnel', { dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'name', label: '漏斗阶段名称', required: true }] }) },
      // erp_crm_analytics_report：period_type 1月度2季度3年度（install.sql:1920）；
      // type（install.sql:1919）DDL 无中文，译名为 lead 判定（activity=活跃度，与 campaign 的 event=活动 区分）
      { label: '客户分析', path: '/crm/analytics', cfg: c('客户分析', '/admin/v1/crm/analytics/report', { canDelete: false, deleteNeedsPassword: false, dicts: { period_type: { 1: '月度', 2: '季度', 3: '年度' }, type: { customer: '客户', order: '订单', revenue: '营收', activity: '活跃度', retention: '留存率' } } }) },
    ],
  },
];
