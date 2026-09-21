/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, moneyCol, statusCol, textCol } from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';

/**
 * CRM。状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份，禁止跨表复用
 * （商机 0输单1进行中2已成交，合同/报价/工单各不相同）。
 */
const OPP = docStatus(['输单', '进行中', '已成交']);
const CONTRACT = docStatus(['草稿', '待审批', '已审批', '执行中', '已完成', '已终止']);
const QUOT = docStatus(['草稿', '已发送', '客户确认', '已转合同', '已失效']);
const TICKET = docStatus(['待处理', '处理中', '已解决', '已关闭']);

const c = (title: string, endpoint: string, extra: Partial<import('@/config/types').ResourceConfig> = {}) =>
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
          columns: [
            textCol('name', '商机名称', true),
            textCol('customer_name', '客户'),
            textCol('stage_name', '阶段'),
            moneyCol('estimated_amount', '预计金额'),
            statusCol(OPP.dict),
            dateCol('created_at', '创建时间'),
          ],
          // customer_id/stage_id 后端 NOT NULL 无默认，缺一即 422
          fields: [
            { key: 'name', label: '商机名称', required: true },
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'stage_id', label: '漏斗阶段', required: true, source: { endpoint: '/admin/v1/crm/funnel' } },
            { key: 'estimated_amount', label: '预计金额', type: 'number' },
            { key: 'expected_close_date', label: '预计成交日期', type: 'date' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      { label: '联系人', path: '/crm/contact', cfg: c('联系人', '/admin/v1/crm/contact', { fields: [{ key: 'name', label: '联系人名称', required: true }] }) },
      {
        label: '公海池',
        path: '/crm/pool',
        cfg: {
          title: '公海池',
          moduleKey: 'crm',
          endpoint: '/admin/v1/crm/pool',
          canDelete: false, // 后端 :317 仅 any index + claim/release，无 DELETE 路由（故不设 deleteNeedsPassword）
          // 池内为客户行（PoolController::poolCustomers），列名同客户表
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
          // 列表无 join：无客户名；金额真列 total_amount、签订时间 signed_at
          columns: [textCol('code', '合同号', true), textCol('name', '合同名称'), moneyCol('total_amount', '金额'), statusCol(CONTRACT.dict), dateCol('signed_at', '签订日期')],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'name', label: '合同名称', required: true },
            { key: 'total_amount', label: '合同金额', type: 'number' },
            { key: 'signed_at', label: '签订日期', type: 'date' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [
            {
              label: '状态流转',
              icon: 'activity',
              // 4已完成/5已终止无出边（CrmService::CONTRACT_STATUS_FLOW）
              path: (r) => ([4, 5].includes(Number(r.status)) ? null : `/admin/v1/crm/contract/${String(r.id)}/transition`),
              // 后端 to_status 缺省 -1 → 恒定 422（ContractController::transition）
              bodyFields: [
                {
                  key: 'to_status',
                  label: '目标状态',
                  required: true,
                  type: 'select',
                  options: Object.entries(CONTRACT.dict).map(([value, label]) => ({ label, value: Number(value) })),
                },
              ],
              message: '状态已更新',
            },
          ],
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
          filters: QUOT.filter,
          // 列表无 join：无客户名；金额真列 total_amount、报价时间 quoted_at
          columns: [textCol('code', '编号', true), moneyCol('total_amount', '金额'), statusCol(QUOT.dict), dateCol('quoted_at', '报价时间')],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'total_amount', label: '金额', type: 'number' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '转合同', icon: 'file', path: (r) => `/admin/v1/crm/quotation/${String(r.id)}/to-contract`, message: '已生成合同' }],
        },
      },
      { label: '营销活动', path: '/crm/campaign', cfg: c('营销活动', '/admin/v1/crm/campaign', { fields: [{ key: 'name', label: '活动名称', required: true }, { key: 'owner_user_id', label: '负责人', required: true, source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      {
        label: '服务工单',
        path: '/crm/ticket',
        cfg: {
          title: '服务工单',
          moduleKey: 'crm',
          deleteNeedsPassword: true,
          endpoint: '/admin/v1/crm/ticket',
          filters: TICKET.filter,
          // 列表无 join：无客户名
          columns: [textCol('code', '工单号', true), textCol('title', '标题'), statusCol(TICKET.dict), dateCol('created_at', '创建时间')],
          fields: [
            { key: 'title', label: '工单标题', required: true },
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'category', label: '工单分类' },
            { key: 'priority', label: '优先级', type: 'select', defaultValue: 1, options: [{ label: '低', value: 1 }, { label: '中', value: 2 }, { label: '高', value: 3 }, { label: '紧急', value: 4 }] },
            { key: 'assignee_user_id', label: '指派人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } },
          ],
          actions: [
            // assignee_user_id 缺省 0 直接 422（TicketController::assign）
            { label: '派单', icon: 'send', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/assign`, bodyFields: [{ key: 'assignee_user_id', label: '指派给', required: true, source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }], message: '已派单' },
            // 后端 content 必填（TicketController::reply）
            { label: '回复', icon: 'edit', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/reply`, bodyFields: [{ key: 'content', label: '回复内容', type: 'textarea', required: true, full: true }], message: '已回复' },
            { label: '解决', icon: 'check', path: (r) => `/admin/v1/crm/ticket/${String(r.id)}/resolve`, message: '工单已解决' },
          ],
        },
      },
      { label: '跟进记录', path: '/crm/follow', cfg: c('跟进记录', '/admin/v1/crm/follow', { fields: [{ key: 'content', label: '跟进内容', type: 'textarea', full: true }, { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'follow_user_id', label: '跟进人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '销售漏斗', path: '/crm/funnel', cfg: c('销售漏斗', '/admin/v1/crm/funnel', { fields: [{ key: 'name', label: '漏斗阶段名称', required: true }] }) },
      { label: '客户分析', path: '/crm/analytics', cfg: c('客户分析', '/admin/v1/crm/analytics/report', { canDelete: false, deleteNeedsPassword: false }) },
    ],
  },
];
