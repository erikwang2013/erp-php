/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, DOC_DICT, DOC_FILTER, intCol, moneyCol, statusCol, textCol } from '@/config/cells';
import { res, type MenuGroup, type ResourceConfig } from '@/config/types';

const m = (
  title: string,
  endpoint: string,
  extra: Partial<ResourceConfig> = {},
): ResourceConfig => res(title, endpoint, { moduleKey: 'mfg', ...extra });

export const mfgMenus: MenuGroup[] = [
  {
    label: '生产制造',
    icon: 'factory',
    moduleKey: 'mfg',
    children: [
      { label: 'BOM 管理', path: '/mfg/bom', cfg: m('BOM 管理', '/admin/v1/mfg/bom', { fields: [{ key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'code', label: 'BOM 编码', required: true }, { key: 'name', label: 'BOM 名称', required: true }] }) },
      {
        label: '生产工单',
        path: '/mfg/production',
        cfg: m('生产工单', '/admin/v1/mfg/production', {
          filters: DOC_FILTER,
          columns: [textCol('code', '工单号', true), textCol('product_name', '产品'), intCol('quantity', '计划数'), intCol('completed_quantity', '完工数'), statusCol(DOC_DICT), dateCol('plan_date', '计划日期')],
          fields: [
            { key: 'code', label: '工单编码', required: true },
            { key: 'bom_id', label: 'BOM', required: true, source: { endpoint: '/admin/v1/mfg/bom', labelKey: 'name' } },
            { key: 'planned_quantity', label: '计划数量', required: true, type: 'number' },
          ],
          actions: [
            { label: '开工', icon: 'send', path: (r) => `/admin/v1/mfg/production/${String(r.id)}/start`, message: '工单已开工' },
            { label: '完工', icon: 'check', path: (r) => `/admin/v1/mfg/production/${String(r.id)}/complete`, message: '工单已完工' },
          ],
        }),
      },
      { label: '工艺路线', path: '/mfg/routing', cfg: m('工艺路线', '/admin/v1/mfg/routing', { fields: [{ key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'name', label: '工序名称', required: true }, { key: 'seq', label: '工序序号', required: true, type: 'number' }, { key: 'workstation_id', label: '工作站', required: true, source: { endpoint: '/admin/v1/mfg/workstation', labelKey: 'name' } }] }) },
      { label: '工作站', path: '/mfg/workstation', cfg: m('工作站', '/admin/v1/mfg/workstation', { fields: [{ key: 'code', label: '工作站编码', required: true }, { key: 'name', label: '工作站名称', required: true }] }) },
      {
        label: 'MRP 计划',
        path: '/mfg/mrp',
        cfg: m('MRP 计划', '/admin/v1/mfg/mrp', {
          filters: DOC_FILTER,
          fields: [
            { key: 'code', label: '计划编码', required: true },
            { key: 'period_year', label: '计划年度', required: true, type: 'number' },
            { key: 'period_month', label: '计划月份', required: true, type: 'number' },
          ],
          actions: [{ label: 'MRP 运算', icon: 'activity', path: (r) => `/admin/v1/mfg/mrp/${String(r.id)}/generate`, message: '运算完成' }],
        }),
      },
      {
        label: '生产领料',
        path: '/mfg/material-issue',
        cfg: m('生产领料', '/admin/v1/mfg/material-issue', {
          filters: DOC_FILTER,
          fields: [
            { key: 'order_id', label: '生产工单', required: true, source: { endpoint: '/admin/v1/mfg/production', labelKey: 'code' } },
            { key: 'issue_date', label: '领料日期', type: 'date', defaultValue: undefined },
            { key: 'warehouse_id', label: '出库仓库', source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/material-issue/${String(r.id)}/audit`, message: '领料单已审核' }],
        }),
      },
      {
        label: '工序报工',
        path: '/mfg/work-report',
        cfg: m('工序报工', '/admin/v1/mfg/work-report', {
          filters: DOC_FILTER,
          fields: [
            { key: 'order_id', label: '生产工单', required: true, source: { endpoint: '/admin/v1/mfg/production', labelKey: 'code' } },
            { key: 'routing_id', label: '工序', required: true, source: { endpoint: '/admin/v1/mfg/routing', labelKey: 'name' } },
            { key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } },
            { key: 'employee_id', label: '报工员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } },
            { key: 'report_date', label: '报工日期', type: 'date' },
            { key: 'quantity', label: '报工数量', required: true, type: 'number' },
            { key: 'qualified_qty', label: '合格数量', type: 'number' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/work-report/${String(r.id)}/audit`, message: '报工已审核' }],
        }),
      },
      { label: '成本归集', path: '/mfg/cost-entry', cfg: m('成本归集', '/admin/v1/mfg/cost-entry', { fields: [{ key: 'order_id', label: '生产工单', required: true, source: { endpoint: '/admin/v1/mfg/production', labelKey: 'code' } }, { key: 'entry_type', label: '费用类型', required: true, type: 'select', options: [{ label: '人工', value: 1 }, { label: '制费', value: 2 }, { label: '其他', value: 3 }] }, { key: 'amount', label: '金额', required: true, type: 'number' }, { key: 'entry_date', label: '归集日期', type: 'date' }, { key: 'summary', label: '摘要' }], actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/cost-entry/${String(r.id)}/audit`, message: '已审核' }] }) },
      { label: '委外加工', path: '/mfg/subcontract', cfg: m('委外加工', '/admin/v1/mfg/subcontract', { filters: DOC_FILTER, fields: [{ key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } }, { key: 'product_id', label: '委外产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'warehouse_id', label: '收料仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'quantity', label: '委外数量', required: true, type: 'number' }, { key: 'unit_price', label: '加工单价', required: true, type: 'number' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      {
        label: '委外发料',
        path: '/mfg/subcontract-issue',
        cfg: m('委外发料', '/admin/v1/mfg/subcontract-issue', {
          filters: DOC_FILTER,
          fields: [
            { key: 'code', label: '发料单号', required: true },
            { key: 'subcontract_id', label: '委外订单', required: true, source: { endpoint: '/admin/v1/mfg/subcontract', labelKey: 'code' } },
            { key: 'warehouse_id', label: '发料仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'issue_date', label: '发料日期', type: 'date' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/subcontract-issue/${String(r.id)}/audit`, message: '发料单已审核' }],
        }),
      },
      {
        label: '委外收货',
        path: '/mfg/subcontract-receive',
        cfg: m('委外收货', '/admin/v1/mfg/subcontract-receive', {
          filters: DOC_FILTER,
          fields: [
            { key: 'code', label: '收料单号', required: true },
            { key: 'subcontract_id', label: '委外订单', required: true, source: { endpoint: '/admin/v1/mfg/subcontract', labelKey: 'code' } },
            { key: 'warehouse_id', label: '收料仓库', source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'receive_date', label: '收料日期', type: 'date' },
            { key: 'quantity', label: '收料数量', required: true, type: 'number' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/subcontract-receive/${String(r.id)}/audit`, message: '收料单已审核' }],
        }),
      },
    ],
  },
  {
    label: '质量管理',
    icon: 'shield',
    moduleKey: 'quality',
    children: [
      { label: '检验标准', path: '/quality/standard', cfg: res('检验标准', '/admin/v1/quality/standard', { moduleKey: 'quality', fields: [{ key: 'name', label: '标准名称', required: true }] }) },
      { label: '来料检验 IQC', path: '/quality/iqc', cfg: res('来料检验', '/admin/v1/quality/iqc', { moduleKey: 'quality', filters: DOC_FILTER, columns: [textCol('code', '编号', true), textCol('supplier_name', '供应商'), intCol('submit_quantity', '提交数'), intCol('pass_quantity', '合格数'), statusCol(DOC_DICT), dateCol('created_at', '创建时间')], fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      { label: '过程检验 IPQC', path: '/quality/ipqc', cfg: res('过程检验', '/admin/v1/quality/ipqc', { moduleKey: 'quality', filters: DOC_FILTER, fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      { label: '出货检验 OQC', path: '/quality/oqc', cfg: res('出货检验', '/admin/v1/quality/oqc', { moduleKey: 'quality', filters: DOC_FILTER, fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      { label: '不合格品', path: '/quality/nonconformity', cfg: res('不合格品', '/admin/v1/quality/nonconformity', { moduleKey: 'quality', columns: [textCol('code', '编号', true), textCol('product_name', '产品'), moneyCol('amount', '金额'), statusCol(DOC_DICT)], fields: [{ key: 'code', label: '不合格品单号', required: true }, { key: 'defect_type', label: '缺陷类型', required: true }, { key: 'defect_qty', label: '缺陷数量', required: true, type: 'number' }] }) },
    ],
  },
];
