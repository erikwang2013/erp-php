/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, moneyCol, statusCol, strStatus, textCol } from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';

/** 财务管理域（最大域） */

const f = (title: string, endpoint: string, opts: Partial<import('@/config/types').ResourceConfig> = {}) =>
  res(title, endpoint, { moduleKey: 'finance', deleteNeedsPassword: true, ...opts });

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份 */
const VOUCHER = docStatus(['草稿', '已审核']);
const ARAP = docStatus(['未核销', '部分核销', '已核销']);
const FIN_DOC = docStatus(['待审核', '已审核']);
const EXPENSE = docStatus(['待审批', '已批准', '已驳回', '已打款']);
const BILL = docStatus(['在库', '已背书', '已贴现', '托收中', '已到期兑付', '已退票']);
const BUDGET = docStatus(['草稿', '已审批', '执行中', '已关闭']);
/** 发票状态为字符串（install.sql:4662 列注释：draft=开票申请 submitted=已提交审核 audited=已审核入账 voided=已作废） */
const INVOICE = strStatus(
  { draft: '开票申请', submitted: '已提交审核', audited: '已审核入账', voided: '已作废' },
  { audited: 's', voided: 'd', submitted: 'w' },
);

export const financeMenus: MenuGroup[] = [
  {
    label: '财务管理',
    icon: 'wallet',
    moduleKey: 'finance',
    children: [
      // 表无 name 列（$fillable 仅 code/voucher_date/remark）：不设「凭证名称」幻字段，编号留空自动生成
      { label: '记账凭证', path: '/finance/voucher', cfg: f('记账凭证', '/admin/v1/finance/voucher', { filters: VOUCHER.filter, columns: [textCol('code', '凭证号', true), dateCol('voucher_date', '凭证日期'), statusCol(VOUCHER.dict), dateCol('created_at', '创建时间')], fields: [{ key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      {
        label: '应收应付',
        path: '/finance/ar-ap',
        cfg: f('应收应付', '/admin/v1/finance/ar-ap', {
          filters: ARAP.filter,
          // 后端补 partner_name；表无 code/unsettled_amount 列
          columns: [
            { key: 'type', title: '类型', render: (r) => (Number(r.type) === 1 ? '应收' : '应付') },
            textCol('partner_name', '往来单位'),
            moneyCol('amount', '金额'),
            moneyCol('settled_amount', '已核销'),
            statusCol(ARAP.dict),
            dateCol('due_date', '到期日期'),
            dateCol('created_at', '创建时间'),
          ],
          fields: [{ key: 'type', label: '类型', type: 'select', options: [{ label: '应收', value: 1 }, { label: '应付', value: 2 }] }, { key: 'partner_id', label: '往来方', source: { endpoint: '/admin/v1/customer', labelKey: 'name' } }, { key: 'amount', label: '金额', type: 'number' }, { key: 'due_date', label: '到期日', type: 'date' }],
        }),
      },
      { label: '收款管理', path: '/finance/receipt', cfg: f('收款管理', '/admin/v1/finance/receipt', { filters: FIN_DOC.filter, columns: [textCol('code', '收款单号', true), textCol('customer_name', '客户'), moneyCol('amount', '金额'), statusCol(FIN_DOC.dict), dateCol('received_at', '收款时间')], fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'amount', label: '收款金额', required: true, type: 'number' }, { key: 'bank_account_id', label: '收款账户', source: { endpoint: '/admin/v1/finance/bank-account', labelKey: 'name' } }, { key: 'method', label: '收款方式', type: 'select', options: [{ label: '银行', value: 'bank' }, { label: '现金', value: 'cash' }, { label: '其他', value: 'other' }] }, { key: 'received_at', label: '收款日期', type: 'datetime' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      { label: '付款管理', path: '/finance/payment', cfg: f('付款管理', '/admin/v1/finance/payment', { filters: FIN_DOC.filter, columns: [textCol('code', '付款单号', true), textCol('supplier_name', '供应商'), moneyCol('amount', '金额'), statusCol(FIN_DOC.dict), dateCol('paid_at', '付款时间')], fields: [{ key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } }, { key: 'amount', label: '金额', required: true, type: 'number' }, { key: 'bank_account_id', label: '付款账户', source: { endpoint: '/admin/v1/finance/bank-account', labelKey: 'name' } }, { key: 'method', label: '付款方式', type: 'select', options: [{ label: '银行', value: 'bank' }, { label: '现金', value: 'cash' }, { label: '其他', value: 'other' }] }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      { label: '现金日记账', path: '/finance/cash-journal', cfg: f('现金日记账', '/admin/v1/finance/cash-journal', { filters: { key: 'direction', label: '方向', options: [{ label: '全部', value: null }, { label: '收入', value: 1 }, { label: '支出', value: 2 }] }, columns: [dateCol('journal_date', '日期'), { key: 'direction', title: '方向', render: (r) => (Number(r.direction) === 1 ? '收入' : '支出') }, moneyCol('amount', '金额'), moneyCol('balance', '余额'), textCol('summary', '摘要')] }) },
      { label: '费用报销', path: '/finance/expense', cfg: f('费用报销', '/admin/v1/finance/expense', { filters: EXPENSE.filter, columns: [textCol('code', '报销单号', true), textCol('apply_user_name', '申请人'), moneyCol('amount', '金额'), statusCol(EXPENSE.dict), dateCol('created_at', '创建时间')], fields: [{ key: 'apply_user_id', label: '申请人', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } }, { key: 'account_id', label: '费用科目', required: true, placeholder: '费用科目 ID', help: '费用科目无列表接口，按 ID 填写（后端双模解码）' }, { key: 'amount', label: '报销金额', type: 'number' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      {
        label: '发票管理',
        path: '/finance/invoice',
        cfg: f('发票管理', '/admin/v1/finance/invoice', {
          filters: INVOICE.filter,
          columns: [
            textCol('invoice_no', '发票号', true),
            { key: 'type', title: '类型', render: (r) => (r.type === 'ar' ? '应收' : '应付') },
            moneyCol('amount', '价税合计'),
            INVOICE.col,
            dateCol('invoice_date', '发票日期'),
          ],
          actions: [
            { label: '提交', icon: 'send', path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/submit`, message: '已提交' },
            { label: '审核', icon: 'check', path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/audit`, message: '已审核' },
            {
              label: '作废',
              icon: 'close',
              variant: 'icon-danger',
              path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/void`,
              // 后端要求作废原因非空（InvoiceService::void）
              bodyFields: [{ key: 'void_reason', label: '作废原因', type: 'textarea', required: true, full: true }],
              message: '已作废',
            },
          ],
        }),
      },
      {
        label: '承兑汇票',
        path: '/finance/bill',
        cfg: f('承兑汇票', '/admin/v1/finance/bill', {
          filters: BILL.filter,
          columns: [textCol('bill_no', '票据号', true), moneyCol('amount', '票面金额'), dateCol('due_date', '到期日'), statusCol(BILL.dict)],
          fields: [
            { key: 'bill_no', label: '票据号', required: true },
            { key: 'type', label: '承兑类型', required: true, type: 'select', options: [{ label: '银行承兑', value: 1 }, { label: '商业承兑', value: 2 }] },
            { key: 'direction', label: '方向', required: true, type: 'select', options: [{ label: '收票(应收)', value: 1 }, { label: '开票(应付)', value: 2 }] },
            { key: 'amount', label: '票面金额', required: true, type: 'number' },
            { key: 'due_date', label: '到期日', required: true, type: 'date' },
            { key: 'issue_date', label: '出票日期', type: 'date' },
            { key: 'drawer', label: '出票人' },
            { key: 'payee', label: '收款人' },
            { key: 'acceptor', label: '承兑人' },
            { key: 'bank_account_id', label: '托收账户', placeholder: 'hashid' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
          actions: [
            // 背书/贴现必填参数（FinanceBillService::endorse/discount）
            { label: '背书', icon: 'link', path: (r) => `/admin/v1/finance/bill/${String(r.id)}/endorse`, bodyFields: [{ key: 'endorsee', label: '被背书人', required: true }], message: '已背书' },
            { label: '贴现', icon: 'dollar', path: (r) => `/admin/v1/finance/bill/${String(r.id)}/discount`, bodyFields: [{ key: 'fee', label: '贴现息', required: true, type: 'number' }], message: '已贴现' },
            { label: '托收', icon: 'download', path: (r) => `/admin/v1/finance/bill/${String(r.id)}/collect` },
            { label: '兑付', icon: 'check', path: (r) => `/admin/v1/finance/bill/${String(r.id)}/cash` },
            { label: '拒付', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/finance/bill/${String(r.id)}/reject` },
          ],
        }),
      },
      {
        label: '固定资产',
        path: '/finance/asset',
        cfg: f('固定资产', '/admin/v1/finance/asset', {
          // 表无 original_value/use_date 列；status 1使用中2已处置3报废
          columns: [textCol('code', '资产编号', true), textCol('name', '资产名称'), moneyCol('purchase_amount', '原值'), moneyCol('net_value', '净值'), dateCol('purchase_date', '购置日期')],
          fields: [
            { key: 'name', label: '资产名称' },
            { key: 'code', label: '资产编码' },
            { key: 'category', label: '资产类别' },
            { key: 'purchase_date', label: '购置日期', type: 'date' },
            { key: 'purchase_amount', label: '购置金额', type: 'number' },
            { key: 'salvage_value', label: '残值', type: 'number' },
            { key: 'useful_life', label: '使用年限', type: 'number' },
            { key: 'depreciation_method', label: '折旧方法', type: 'select', options: [{ label: '直线法', value: 1 }] },
          ],
          actions: [{ label: '计提折旧', icon: 'calendar', path: (r) => `/admin/v1/finance/asset/${String(r.id)}/depreciate`, message: '折旧已计提' }],
        }),
      },
      { label: '银行账户', path: '/finance/bank-account', cfg: f('银行账户', '/admin/v1/finance/bank-account', { fields: [{ key: 'name', label: '账户名称', required: true }, { key: 'account_number', label: '银行账号' }, { key: 'bank_name', label: '开户银行' }, { key: 'balance', label: '账户余额', type: 'number' }] }) },
      { label: '税率管理', path: '/finance/tax-rate', cfg: f('税率管理', '/admin/v1/finance/tax-rate', { deleteNeedsPassword: false }) },
      { label: '纳税记录', path: '/finance/tax-record', cfg: f('纳税记录', '/admin/v1/finance/tax-record', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '多币种', path: '/finance/currency', cfg: f('币种管理', '/admin/v1/finance/currency', { fields: [{ key: 'code', label: '币种编码', required: true }, { key: 'name', label: '币种名称', required: true, placeholder: '如 人民币' }] }) },
      { label: '汇率管理', path: '/finance/exchange-rate', cfg: f('汇率管理', '/admin/v1/finance/exchange-rate', { fields: [{ key: 'from_currency_id', label: '来源币种', required: true, source: { endpoint: '/admin/v1/finance/currency', labelKey: 'code' } }, { key: 'to_currency_id', label: '目标币种', required: true, source: { endpoint: '/admin/v1/finance/currency', labelKey: 'code' } }, { key: 'rate', label: '汇率值', required: true, type: 'number' }, { key: 'effective_date', label: '生效日期', required: true, type: 'date' }] }) },
      {
        label: '预算管理',
        path: '/finance/budget',
        cfg: f('预算管理', '/admin/v1/finance/budget', {
          // 表无 budget_amount/used_amount 列（金额在 budget_item 明细）
          columns: [textCol('code', '预算编号', true), textCol('name', '预算名称'), { key: 'period_year', title: '预算年度', align: 'right' }, statusCol(BUDGET.dict)],
          fields: [
            { key: 'name', label: '预算名称', required: true },
            { key: 'period_year', label: '预算年度', required: true, type: 'number' },
          ],
          actions: [{ label: '预算对比', icon: 'chart', path: (r) => `/admin/v1/finance/budget/${String(r.id)}/comparison`, method: 'GET' }],
        }),
      },
      { label: '成本中心', path: '/finance/cost-center', cfg: f('成本中心', '/admin/v1/finance/cost-center', { fields: [{ key: 'code', label: '编码', required: true }, { key: 'name', label: '名称', required: true }] }) },
      { label: '利润中心', path: '/finance/profit-center', cfg: f('利润中心', '/admin/v1/finance/profit-center', { fields: [{ key: 'code', label: '编码', required: true }, { key: 'name', label: '名称', required: true }] }) },
      { label: '总账', path: '/finance/general-ledger', cfg: f('总账', '/admin/v1/finance/general-ledger', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '明细分类账', path: '/finance/subsidiary-ledger', cfg: f('明细分类账', '/admin/v1/finance/subsidiary-ledger', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '资产负债表', path: '/finance/balance-sheet', cfg: f('资产负债表', '/admin/v1/finance/report/balance-sheet', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '利润表', path: '/finance/profit-statement', cfg: f('利润表', '/admin/v1/finance/report/profit', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '现金流量表', path: '/finance/cash-flow', cfg: f('现金流量表', '/admin/v1/finance/report/cash-flow', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '进项发票池', path: '/finance/tax-input-invoice', cfg: f('进项发票池', '/admin/v1/finance/tax-input-invoice', { canDelete: false, deleteNeedsPassword: false, actions: [{ label: '验真', icon: 'shield', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/verify` }, { label: '勾选', icon: 'check', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/check` }, { label: '抵扣', icon: 'dollar', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/deduct`, bodyFields: [{ key: 'deduct_period', label: '抵扣期间', required: true, placeholder: 'YYYY-MM' }], message: '已抵扣' }] }) },
    ],
  },
  {
    label: '集团财务',
    icon: 'grid',
    moduleKey: 'finance',
    children: [
      {
        label: '多组织公司',
        path: '/finance/company',
        cfg: f('多组织公司', '/admin/v1/finance/company/list', {
          canDelete: false,
          deleteNeedsPassword: false,
          // 新增走专用 /finance/company/create（自动建账套+开账），不在泛型 CRUD 语义内
          actions: [
            { label: '启停', icon: 'activity', path: () => '/admin/v1/finance/company/toggle', body: (r) => ({ id: r.id, status: Number(r.status) === 1 ? 0 : 1 }), message: '状态已切换' },
          ],
        }),
      },
      {
        label: '账套期间',
        path: '/finance/ledger-period',
        cfg: f('账套期间', '/admin/v1/finance/ledger/period-list', {
          canDelete: false,
          deleteNeedsPassword: false,
          // 开账走专用 period-open（新期间非行操作），此处仅结账
          actions: [
            { label: '结账', icon: 'check', path: () => '/admin/v1/finance/ledger/period-close', body: (r) => ({ ledger_id: r.ledger_id, period: r.period }), message: '期间已结账' },
          ],
        }),
      },
      { label: '合并报表', path: '/finance/consolidation', cfg: f('合并报表', '/admin/v1/finance/consolidation/list', { canDelete: false, deleteNeedsPassword: false }) },
    ],
  },
];
