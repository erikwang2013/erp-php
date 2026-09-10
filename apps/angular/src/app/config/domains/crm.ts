/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, moneyCol, statusCol, textCol } from '../cells';
import { res, type MenuGroup } from '../types';

const STAGE = { 0: '未开始', 1: '跟进中', 2: '已报价', 3: '赢单', 4: '输单' };

const c = (title: string, endpoint: string, extra: Partial<import('../types').ResourceConfig> = {}) =>
  res(title, endpoint, { moduleKey: 'crm', ...extra });

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
          endpoint: '/admin/v1/crm/opportunity',
          columns: [textCol('code', '编号', true), textCol('name', '商机名称'), textCol('customer_name', '客户'), moneyCol('amount', '金额'), statusCol(STAGE), dateCol('created_at', '创建时间')],
          fields: [
            { key: 'name', label: '商机名称', required: true },
            { key: 'amount', label: '预计金额', type: 'number' },
            { key: 'stage', label: '阶段', type: 'select', options: [{ label: '未开始', value: 0 }, { label: '跟进中', value: 1 }, { label: '已报价', value: 2 }, { label: '赢单', value: 3 }, { label: '输单', value: 4 }] },
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
          endpoint: '/admin/v1/crm/contract',
          columns: [textCol('code', '合同号', true), textCol('name', '合同名称'), textCol('customer_name', '客户'), moneyCol('amount', '金额'), statusCol(STAGE), dateCol('sign_date', '签订日期')],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'name', label: '合同名称', required: true },
            { key: 'amount', label: '合同金额', type: 'number' },
            { key: 'sign_date', label: '签订日期', type: 'date' },
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
          endpoint: '/admin/v1/crm/quotation',
          columns: [textCol('code', '编号', true), textCol('customer_name', '客户'), moneyCol('amount', '金额'), statusCol(STAGE)],
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'amount', label: '金额', type: 'number' },
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
          endpoint: '/admin/v1/crm/ticket',
          columns: [textCol('code', '工单号', true), textCol('title', '标题'), textCol('customer_name', '客户'), statusCol(STAGE)],
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
      { label: '跟进记录', path: '/crm/follow', cfg: c('跟进记录', '/admin/v1/crm/follow', { fields: [{ key: 'name', label: '跟进内容', required: true }, { key: 'customer_id', label: '客户', source: { endpoint: '/admin/v1/customer' } }, { key: 'follow_user_id', label: '跟进人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '销售漏斗', path: '/crm/funnel', cfg: c('销售漏斗', '/admin/v1/crm/funnel', { fields: [{ key: 'name', label: '漏斗阶段名称', required: true }] }) },
      { label: '客户分析', path: '/crm/analytics', cfg: c('客户分析', '/admin/v1/crm/analytics/report') },
    ],
  },
];
