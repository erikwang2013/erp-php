/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, intCol, statusCol, textCol } from '../cells';
import { res, type MenuGroup, type ResourceConfig } from '../types';

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份 */
const PROD = docStatus(['待生产', '生产中', '已完成', '已取消']);
const MRP = docStatus(['草稿', '已生成', '已确认']);
const DRAFT_AUDIT = docStatus(['草稿', '已审核']);
const SUBCONTRACT = docStatus(['草稿', '已发料', '已收货', '已核销']);
const QC = docStatus(['待处理', '已完成']);
const NC = docStatus(['待处理', '处理中', '已关闭']);
const BOM = docStatus(['草稿', '已生效', '已失效']);

const m = (
  title: string,
  endpoint: string,
  extra: Partial<ResourceConfig> = {},
): ResourceConfig => res(title, endpoint, { moduleKey: 'mfg', deleteNeedsPassword: true, ...extra });

export const mfgMenus: MenuGroup[] = [
  {
    label: '生产制造',
    icon: 'factory',
    moduleKey: 'mfg',
    children: [
      {
        label: 'BOM 管理',
        path: '/mfg/bom',
        // 状态 0草稿/1已生效/2已失效（ManufacturingService::BOM_STATUS_FLOW）；生效的副作用是
        // 同产品其它已生效 BOM 全部转失效，故只有「生效」一个按钮，失效由新版本取代
        cfg: m('BOM 管理', '/admin/v1/mfg/bom', {
          filters: BOM.filter,
          fields: [{ key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'code', label: 'BOM 编码', required: true }, { key: 'name', label: 'BOM 名称', required: true }],
          actions: [{ label: '生效', icon: 'check', path: (r) => (Number(r.status) === 1 ? null : `/admin/v1/mfg/bom/${String(r.id)}/activate`), message: 'BOM 已生效，同产品其它版本已转失效' }],
        }),
      },
      {
        label: '生产工单',
        path: '/mfg/production',
        cfg: m('生产工单', '/admin/v1/mfg/production', {
          filters: PROD.filter,
          // 列表无 join：无产品名；真列 planned_quantity/planned_start
          columns: [textCol('code', '工单号', true), intCol('planned_quantity', '计划数'), intCol('completed_quantity', '完工数'), statusCol(PROD.dict), dateCol('planned_start', '计划开始')],
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
      // status 0=禁用/1=启用（erp_mfg_workstation.status 列注释）；不写 filters 的页没有状态字典，会落通用档
      { label: '工作站', path: '/mfg/workstation', cfg: m('工作站', '/admin/v1/mfg/workstation', { dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'code', label: '工作站编码', required: true }, { key: 'name', label: '工作站名称', required: true }] }) },
      {
        label: 'MRP 计划',
        path: '/mfg/mrp',
        cfg: m('MRP 计划', '/admin/v1/mfg/mrp', {
          filters: MRP.filter,
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
          filters: DRAFT_AUDIT.filter,
          fields: [
            { key: 'order_id', label: '生产工单', required: true, source: { endpoint: '/admin/v1/mfg/production', labelKey: 'code' } },
            { key: 'issue_date', label: '领料日期', type: 'date', defaultValue: undefined },
            { key: 'warehouse_id', label: '出库仓库', source: { endpoint: '/admin/v1/warehouse' } },
            {
              key: 'items',
              label: '领料明细',
              type: 'items',
              required: true,
              itemFields: [
                { key: 'sku_id', label: 'SKU ID', required: true, type: 'number', help: '后端按整数 SKU ID 校验（暂无 SKU 列表接口）' },
                { key: 'quantity', label: '数量', required: true, type: 'number' },
              ],
            },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/material-issue/${String(r.id)}/audit`, message: '领料单已审核' }],
        }),
      },
      {
        label: '工序报工',
        path: '/mfg/work-report',
        cfg: m('工序报工', '/admin/v1/mfg/work-report', {
          filters: DRAFT_AUDIT.filter,
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
      // status 0=草稿/1=已审核、entry_type 1=人工 2=制费 3=其他（erp_mfg_cost_entry 列注释）。
      // 不写 filters 时 status 落通用档（0 显「待处理」、1 显「已生效」—— 与「草稿/已审核」是两回事），
      // entry_type 非 status 形键推断不出枚举，列表与抽屉都裸出 1/2/3
      { label: '成本归集', path: '/mfg/cost-entry', cfg: m('成本归集', '/admin/v1/mfg/cost-entry', { dicts: { status: { 0: '草稿', 1: '已审核' }, entry_type: { 1: '人工', 2: '制费', 3: '其他' } }, fields: [{ key: 'order_id', label: '生产工单', required: true, source: { endpoint: '/admin/v1/mfg/production', labelKey: 'code' } }, { key: 'entry_type', label: '费用类型', required: true, type: 'select', options: [{ label: '人工', value: 1 }, { label: '制费', value: 2 }, { label: '其他', value: 3 }] }, { key: 'amount', label: '金额', required: true, type: 'number' }, { key: 'entry_date', label: '归集日期', type: 'date' }, { key: 'summary', label: '摘要' }], actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/cost-entry/${String(r.id)}/audit`, message: '已审核' }] }) },
      { label: '委外加工', path: '/mfg/subcontract', cfg: m('委外加工', '/admin/v1/mfg/subcontract', { filters: SUBCONTRACT.filter, fields: [{ key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } }, { key: 'product_id', label: '委外产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'warehouse_id', label: '收料仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'quantity', label: '委外数量', required: true, type: 'number' }, { key: 'unit_price', label: '加工单价', required: true, type: 'number' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      {
        label: '委外发料',
        path: '/mfg/subcontract-issue',
        cfg: m('委外发料', '/admin/v1/mfg/subcontract-issue', {
          filters: DRAFT_AUDIT.filter,
          fields: [
            { key: 'code', label: '发料单号', required: true },
            { key: 'subcontract_id', label: '委外订单', required: true, source: { endpoint: '/admin/v1/mfg/subcontract', labelKey: 'code' } },
            { key: 'warehouse_id', label: '发料仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'issue_date', label: '发料日期', type: 'date' },
            {
              key: 'items',
              label: '发料明细',
              type: 'items',
              required: true,
              itemFields: [
                { key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } },
                { key: 'sku_id', label: 'SKU ID', required: true, type: 'number', help: '后端按整数 SKU ID 校验（暂无 SKU 列表接口）' },
                { key: 'quantity', label: '数量', required: true, type: 'number' },
              ],
            },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [{ label: '审核', icon: 'check', path: (r) => `/admin/v1/mfg/subcontract-issue/${String(r.id)}/audit`, message: '发料单已审核' }],
        }),
      },
      {
        label: '委外收货',
        path: '/mfg/subcontract-receive',
        cfg: m('委外收货', '/admin/v1/mfg/subcontract-receive', {
          filters: DRAFT_AUDIT.filter,
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
      // 检验标准 status 0=禁用/1=启用（erp_quality_inspection_standard.status 列注释）；
      // 该页不写 filters，状态列没有字典可查会落通用档（1 显「已生效」）。
      // type 的标签取自仓内既有词条（「检验类型」的页标题/词条：来料检验/过程检验/出货检验，两端词典早有），
      // install.sql :4108 的列注释只给码 `iqc/ipqc/oqc`、没有中文可抄 —— 不是从注释抄的，别当抄错
      { label: '检验标准', path: '/quality/standard', cfg: res('检验标准', '/admin/v1/quality/standard', { moduleKey: 'quality', deleteNeedsPassword: true, dicts: { status: { 0: '禁用', 1: '启用' }, type: { iqc: '来料检验', ipqc: '过程检验', oqc: '出货检验' } }, fields: [{ key: 'name', label: '标准名称', required: true }] }) },
      // result 是机器串（列注释 '检验结果: pass=合格 reject=不合格'），推断不出枚举。
      // 该页已显式 columns 且没声明 result，列表不显示它 —— 缺的是详情抽屉那一行
      { label: '来料检验 IQC', path: '/quality/iqc', cfg: res('来料检验', '/admin/v1/quality/iqc', { moduleKey: 'quality', deleteNeedsPassword: true, filters: QC.filter, dicts: { result: { pass: '合格', reject: '不合格' } }, columns: [textCol('code', '编号', true), intCol('inspected_qty', '检验数量'), intCol('passed_qty', '合格数'), statusCol(QC.dict), dateCol('created_at', '创建时间')], fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      { label: '过程检验 IPQC', path: '/quality/ipqc', cfg: res('过程检验', '/admin/v1/quality/ipqc', { moduleKey: 'quality', deleteNeedsPassword: true, filters: QC.filter, dicts: { result: { pass: '合格', reject: '不合格' } }, fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      { label: '出货检验 OQC', path: '/quality/oqc', cfg: res('出货检验', '/admin/v1/quality/oqc', { moduleKey: 'quality', deleteNeedsPassword: true, filters: QC.filter, dicts: { result: { pass: '合格', reject: '不合格' } }, fields: [{ key: 'code', label: '检验单号', required: true }, { key: 'inspected_qty', label: '检验数量', required: true, type: 'number' }, { key: 'result', label: '检验结果', required: true, type: 'select', options: [{ label: '合格', value: 'pass' }, { label: '不合格', value: 'reject' }] }] }) },
      // severity/disposition 词表由 lead 拍板、四端统一（Flutter 同一份）。install.sql :4212-4213 的列注释
      // 只给机器串（minor/major/critical、pending/return/repair/scrap/accept），没有中文可抄。
      // source_type 与「检验标准」的 type 同源（:4207 与 :4208 同为 iqc/ipqc/oqc），标签取自仓内既有词条、
      // install.sql 只给码；Flutter fl-enum 已把这三个词上在同一键上，四端同词。
      // 该页显式 columns 只有三列，列表不渲染这几个键 —— 缺的是详情抽屉那几行
      { label: '不合格品', path: '/quality/nonconformity', cfg: res('不合格品', '/admin/v1/quality/nonconformity', { moduleKey: 'quality', deleteNeedsPassword: true, dicts: { severity: { minor: '轻微', major: '严重', critical: '致命' }, disposition: { pending: '待处理', return: '退货', repair: '返修', scrap: '报废', accept: '让步接收' }, source_type: { iqc: '来料检验', ipqc: '过程检验', oqc: '出货检验' } }, columns: [textCol('code', '编号', true), intCol('defect_qty', '缺陷数量'), statusCol(NC.dict)], fields: [{ key: 'code', label: '不合格品单号', required: true }, { key: 'defect_type', label: '缺陷类型', required: true }, { key: 'defect_qty', label: '缺陷数量', required: true, type: 'number' }] }) },
    ],
  },
];
