/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, moneyCol, statusCol, textCol } from '../cells';
import { res, type MenuGroup } from '../types';

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份，禁止跨表复用 */
const OPP = { 0: '输单', 1: '进行中', 2: '已成交' }; // 商机 erp_crm_opportunity
const CONTRACT = { 0: '草稿', 1: '待审批', 2: '已审批', 3: '执行中', 4: '已完成', 5: '已终止' }; // 合同 erp_crm_contract
const QUOT = { 0: '草稿', 1: '已发送', 2: '客户确认', 3: '已转合同', 4: '已失效' }; // 报价 erp_crm_quotation
const TICKET = { 0: '待处理', 1: '处理中', 2: '已解决', 3: '已关闭' }; // 工单 erp_crm_ticket

const c = (title: string, endpoint: string, extra: Partial<import('../types').ResourceConfig> = {}) =>
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
          columns: [textCol('name', '商机名称', true), textCol('customer_name', '客户'), textCol('stage_name', '阶段'), moneyCol('estimated_amount', '金额'), statusCol(OPP), dateCol('created_at', '创建时间')],
          fields: [
            { key: 'name', label: '商机名称', required: true },
            { key: 'estimated_amount', label: '预计金额', type: 'number' },
            { key: 'stage_id', label: '漏斗阶段', required: true, source: { endpoint: '/admin/v1/crm/funnel' } },
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
          deleteNeedsPassword: true,
          canDelete: false, // 后端无 pool destroy 路由，公海客户只认领/释放
          endpoint: '/admin/v1/crm/pool',
          columns: [textCol('name', '客户', true), textCol('contact', '联系人'), textCol('phone', '电话')],
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
          columns: [textCol('code', '合同号', true), textCol('name', '合同名称'), moneyCol('total_amount', '金额'), statusCol(CONTRACT), dateCol('signed_at', '签订日期')],
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
          columns: [textCol('code', '编号', true), textCol('customer_name', '客户'), moneyCol('total_amount', '金额'), statusCol(QUOT)],
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
          columns: [textCol('code', '工单号', true), textCol('title', '标题'), textCol('customer_name', '客户'), statusCol(TICKET)],
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
      { label: '跟进记录', path: '/crm/follow', cfg: c('跟进记录', '/admin/v1/crm/follow', { fields: [{ key: 'content', label: '跟进内容', type: 'textarea', full: true }, { key: 'customer_id', label: '客户', source: { endpoint: '/admin/v1/customer' } }, { key: 'follow_user_id', label: '跟进人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '销售漏斗', path: '/crm/funnel', cfg: c('销售漏斗', '/admin/v1/crm/funnel', { fields: [{ key: 'name', label: '漏斗阶段名称', required: true }] }) },
      { label: '客户分析', path: '/crm/analytics', cfg: c('客户分析', '/admin/v1/crm/analytics/report', { canDelete: false, deleteNeedsPassword: false }) },
    ],
  },
];
