/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, intCol, moneyCol, statusCol, strStatus, textCol } from '../cells';
import { res, type DictMap, type MenuGroup, type ResourceConfig } from '../types';

/** 财务管理域（最大域） */

const f = (title: string, endpoint: string, opts: Partial<ResourceConfig> = {}) =>
  // 删除一律要二次密码（后端 BaseController::confirmPassword，财务域无例外）
  res(title, endpoint, { moduleKey: 'finance', deleteNeedsPassword: true, ...opts });

const VOUCHER = docStatus(['草稿', '已审核']);
const ARAP = docStatus(['未核销', '部分核销', '已核销']);
const RECEIPT = docStatus(['待审核', '已审核']);
const PAYMENT = docStatus(['待审核', '已审核']);
const EXPENSE = docStatus(['待审批', '已批准', '已驳回', '已打款']);
const BILL = docStatus(['在库', '已背书', '已贴现', '托收中', '已到期兑付', '已退票']);
const BUDGET = docStatus(['草稿', '已审批', '执行中', '已关闭']);
/** 发票状态是字符串（InvoiceService：draft→submitted→audited、voided 终态） */
const INVOICE = strStatus({ draft: '开票申请', submitted: '已提交审核', audited: '已审核入账', voided: '已作废' });

/**
 * cfg.dicts（逐键值字典）：文案逐字抄自 database/install.sql 该表该列的注释，
 * 禁止跨表复用、禁止按语义猜。引擎只在推断列认 status/state/*_status，
 * type/direction/source… 这些键没有字典就裸出 0/1 或机器串（列表列/详情抽屉/结果面板三处同源）。
 */
/** erp_finance_invoice（install.sql:4644 issue_status、4648 biz_type） */
const INVOICE_DICTS: DictMap = {
  issue_status: { none: '未开具', issued: '已开具', voided: '已红冲' },
  biz_type: { purchase_receive: '收货单', sales_delivery: '发货单', manual: '手工' },
};
/** erp_finance_bill（install.sql:1290 type、1291 direction、1302 source_type） */
const BILL_DICTS: DictMap = {
  type: { 1: '银行承兑', 2: '商业承兑' },
  direction: { 1: '收票(应收)', 2: '开票(应付)' },
  source_type: { manual: '手工', receipt: '关联收款单' },
};
/** erp_tax_input_invoice（install.sql:4701 verify_status、4703 deduct_status、4705 source） */
const TAX_POOL_DICTS: DictMap = {
  verify_status: { 0: '待验真', 1: '验真通过', 2: '验真失败' },
  deduct_status: { 0: '未勾选', 1: '已勾选待抵扣', 2: '已抵扣' },
  source: { manual: '手工', excel: '批量导入' },
};
/** erp_finance_receipt.method（install.sql:1202）与 erp_finance_payment.method（1221）：
 * 两张表该列注释**逐字相同**（cash/bank/wechat/alipay），故共用一份，非跨表套用语义。
 * DDL 无中文，译名为 lead 判定；`other` 不在 DDL 码表里，但 apidoc 写 bank/cash/other。
 * 两页表单 options 逐字对齐本词典（含 wechat/alipay，值仍是机读串）—— 表单是这套码的唯一写入方，
 * 选项与词典不一致时提交出来的值在列表/抽屉里就没有译名 */
const PAY_METHOD_DICTS: DictMap = {
  method: { cash: '现金', bank: '银行转账', wechat: '微信', alipay: '支付宝', other: '其他' },
};
/** erp_finance_asset（install.sql:1697 depreciation_method、1701 status） */
const ASSET_DICTS: DictMap = {
  // '1直线法2双倍余额递减法3年数总和法'（逐字抄）
  depreciation_method: { 1: '直线法', 2: '双倍余额递减法', 3: '年数总和法' },
  // '1使用中2已处置3报废'（逐字抄；不是 0/1 那套，别按状态列猜文案）
  status: { 1: '使用中', 2: '已处置', 3: '报废' },
};
/** erp_finance_tax_rate.type（install.sql:1727 税种: vat/cit/pit/stamp/other）：DDL 无中文，译名为 lead 判定 */
const TAX_RATE_DICTS: DictMap = {
  type: { vat: '增值税', cit: '企业所得税', pit: '个人所得税', stamp: '印花税', other: '其他' },
};

export const financeMenus: MenuGroup[] = [
  {
    label: '财务管理',
    icon: 'wallet',
    moduleKey: 'finance',
    children: [
      // 凭证状态机 0草稿→1已审核（VoucherController::update 只放行 0→1，且已审核不可再改；
      // 期间已结账时后端 422 回原因）
      { label: '记账凭证', path: '/finance/voucher', cfg: f('记账凭证', '/admin/v1/finance/voucher', { filters: VOUCHER.filter, columns: [textCol('code', '凭证号', true), dateCol('voucher_date', '凭证日期'), statusCol(VOUCHER.dict), dateCol('created_at', '创建时间')], fields: [{ key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '审核', icon: 'check', path: (r) => (Number(r.status) === 0 ? `/admin/v1/finance/voucher/${String(r.id)}` : null), method: 'PUT', body: () => ({ status: 1 }), message: '凭证已审核' }] }) },
      { label: '应收应付', path: '/finance/ar-ap', cfg: f('应收应付', '/admin/v1/finance/ar-ap', {
          filters: ARAP.filter,
          // 表无 code 列，往来单位名由后端补 partner_name；type 1=应收 2=应付
          columns: [{ key: 'type', title: '类型', kind: 'map', dict: { 1: '应收', 2: '应付' } }, textCol('partner_name', '往来单位', true), moneyCol('amount', '金额'), moneyCol('settled_amount', '已核销'), statusCol(ARAP.dict), dateCol('due_date', '到期日期'), dateCol('created_at', '创建时间')], fields: [{ key: 'type', label: '类型', type: 'select', options: [{ label: '应收', value: 1 }, { label: '应付', value: 2 }] }, { key: 'partner_id', label: '往来方', source: { endpoint: '/admin/v1/customer', labelKey: 'name' } }, { key: 'amount', label: '金额', type: 'number' }, { key: 'due_date', label: '到期日', type: 'date' }] }) },
      { label: '收款管理', path: '/finance/receipt', cfg: f('收款管理', '/admin/v1/finance/receipt', { dicts: PAY_METHOD_DICTS, filters: RECEIPT.filter, columns: [textCol('code', '收款单号', true), textCol('customer_name', '客户'), moneyCol('amount', '金额'), statusCol(RECEIPT.dict), dateCol('received_at', '收款时间')], fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'amount', label: '收款金额', required: true, type: 'number' }, { key: 'bank_account_id', label: '收款账户', source: { endpoint: '/admin/v1/finance/bank-account', labelKey: 'name' } }, { key: 'method', label: '收款方式', type: 'select', options: [{ label: '现金', value: 'cash' }, { label: '银行转账', value: 'bank' }, { label: '微信', value: 'wechat' }, { label: '支付宝', value: 'alipay' }, { label: '其他', value: 'other' }] }, { key: 'received_at', label: '收款日期', type: 'datetime' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      { label: '付款管理', path: '/finance/payment', cfg: f('付款管理', '/admin/v1/finance/payment', { dicts: PAY_METHOD_DICTS, filters: PAYMENT.filter, columns: [textCol('code', '付款单号', true), textCol('supplier_name', '供应商'), moneyCol('amount', '金额'), statusCol(PAYMENT.dict), dateCol('paid_at', '付款时间')], fields: [{ key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } }, { key: 'amount', label: '金额', required: true, type: 'number' }, { key: 'bank_account_id', label: '付款账户', source: { endpoint: '/admin/v1/finance/bank-account', labelKey: 'name' } }, { key: 'method', label: '付款方式', type: 'select', options: [{ label: '现金', value: 'cash' }, { label: '银行转账', value: 'bank' }, { label: '微信', value: 'wechat' }, { label: '支付宝', value: 'alipay' }, { label: '其他', value: 'other' }] }, { key: 'remark', label: '备注', type: 'textarea', full: true }] }) },
      { label: '现金日记账', path: '/finance/cash-journal', cfg: f('现金日记账', '/admin/v1/finance/cash-journal', { filters: { key: 'direction', label: '方向', options: [{ label: '全部', value: null }, { label: '收入', value: 1 }, { label: '支出', value: 2 }] }, columns: [dateCol('journal_date', '日期'), { key: 'direction', title: '方向', kind: 'map', dict: { 1: '收入', 2: '支出' } }, moneyCol('amount', '金额'), moneyCol('balance', '余额'), textCol('summary', '摘要')] }) },
      { label: '费用报销', path: '/finance/expense', cfg: f('费用报销', '/admin/v1/finance/expense', { filters: EXPENSE.filter, columns: [textCol('code', '编号', true), textCol('apply_user_name', '申请人'), moneyCol('amount', '金额'), statusCol(EXPENSE.dict), dateCol('created_at', '创建时间')], fields: [{ key: 'apply_user_id', label: '申请人', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } }, { key: 'account_id', label: '费用科目', required: true, placeholder: '费用科目 ID', help: '费用科目无列表接口，按 ID 填写（后端双模解码）' }, { key: 'amount', label: '报销金额', type: 'number' }, { key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '批准', icon: 'check', path: (r) => (Number(r.status) === 0 ? `/admin/v1/finance/expense/${String(r.id)}` : null), method: 'PUT', body: () => ({ status: 1 }), message: '已批准' }] }) },
      {
        label: '发票管理',
        path: '/finance/invoice',
        cfg: f('发票管理', '/admin/v1/finance/invoice', {
          filters: INVOICE.filter,
          // 列表列已写明，dicts 补的是抽屉里那两列没覆盖的枚举键（issue_status/biz_type）
          dicts: INVOICE_DICTS,
          columns: [textCol('invoice_no', '发票号', true), { key: 'type', title: '类型', kind: 'map', dict: { ar: '应收', ap: '应付' } }, moneyCol('amount', '价税合计'), INVOICE.col, dateCol('invoice_date', '发票日期')],
          actions: [
            { label: '提交', icon: 'send', path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/submit`, message: '已提交' },
            { label: '审核', icon: 'check', path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/audit`, message: '已审核' },
            {
              label: '作废',
              icon: 'close',
              variant: 'icon-danger',
              path: (r) => `/admin/v1/finance/invoice/${String(r.id)}/void`,
              // InvoiceService::void 要求作废原因非空
              bodyFields: [{ key: 'void_reason', label: '作废原因', required: true, type: 'textarea', full: true }],
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
          dicts: BILL_DICTS,
          // 表无 code（真列 bill_no）；type=票据类型 1银行承兑 2商业承兑，direction=方向 1收票 2开票
          columns: [textCol('bill_no', '票据号', true), moneyCol('amount', '票面金额'), dateCol('due_date', '到期日'), statusCol(BILL.dict)],
          fields: [
            { key: 'bill_no', label: '票据号', required: true },
            { key: 'type', label: '承兑类型', required: true, type: 'select', options: [{ label: '银行承兑', value: 1 }, { label: '商业承兑', value: 2 }] }, { key: 'direction', label: '方向', required: true, type: 'select', options: [{ label: '收票(应收)', value: 1 }, { label: '开票(应付)', value: 2 }] },
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
            {
              label: '背书',
              icon: 'link',
              path: (r) => `/admin/v1/finance/bill/${String(r.id)}/endorse`,
              bodyFields: [{ key: 'endorsee', label: '被背书人', required: true }],
            },
            {
              label: '贴现',
              icon: 'dollar',
              path: (r) => `/admin/v1/finance/bill/${String(r.id)}/discount`,
              bodyFields: [{ key: 'fee', label: '贴现息', required: true, type: 'number', help: '0 ~ 票面金额' }],
            },
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
          // 列里没有这两键，靠 dicts 供抽屉与结果面板（status 不按状态列猜，1≠已生效）
          dicts: ASSET_DICTS,
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
      // erp_finance_bank_account.status: 0=禁用 1=启用（install.sql:1188）
      { label: '银行账户', path: '/finance/bank-account', cfg: f('银行账户', '/admin/v1/finance/bank-account', { dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'name', label: '账户名称', required: true }, { key: 'account_number', label: '银行账号' }, { key: 'bank_name', label: '开户银行' }, { key: 'balance', label: '账户余额', type: 'number' }] }) },
      { label: '税率管理', path: '/finance/tax-rate', cfg: f('税率管理', '/admin/v1/finance/tax-rate', { dicts: TAX_RATE_DICTS, deleteNeedsPassword: false }) },
      // erp_finance_tax_record.source_type（install.sql:1737，表无 status 列）：
      // DDL 无中文，此译名为 lead 判定
      { label: '纳税记录', path: '/finance/tax-record', cfg: f('纳税记录', '/admin/v1/finance/tax-record', { canDelete: false, deleteNeedsPassword: false, dicts: { source_type: { sales: '销售', purchase: '采购' } } }) },
      // erp_finance_currency.status（install.sql:1756 注释为空，lead 裁定 0=禁用 1=启用）；
      // code 保持原样（ISO 4217 码即标准展示形态，不翻）
      { label: '多币种', path: '/finance/currency', cfg: f('币种管理', '/admin/v1/finance/currency', { dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'code', label: '币种编码', required: true }, { key: 'name', label: '币种名称', required: true, placeholder: '如 人民币' }] }) },
      { label: '汇率管理', path: '/finance/exchange-rate', cfg: f('汇率管理', '/admin/v1/finance/exchange-rate', { fields: [{ key: 'from_currency_id', label: '来源币种', required: true, source: { endpoint: '/admin/v1/finance/currency', labelKey: 'code' } }, { key: 'to_currency_id', label: '目标币种', required: true, source: { endpoint: '/admin/v1/finance/currency', labelKey: 'code' } }, { key: 'rate', label: '汇率值', required: true, type: 'number' }, { key: 'effective_date', label: '生效日期', required: true, type: 'date' }] }) },
      {
        label: '预算管理',
        path: '/finance/budget',
        cfg: f('预算管理', '/admin/v1/finance/budget', {
          // 表只有 code/name/period_year/cost_center_id/status（无预算额/已用列）
          columns: [textCol('code', '编号', true), textCol('name', '预算名称'), intCol('period_year', '预算年度'), statusCol(BUDGET.dict)],
          fields: [
            { key: 'name', label: '预算名称', required: true },
            { key: 'period_year', label: '预算年度', required: true, type: 'number' },
          ],
          actions: [{ label: '预算对比', icon: 'chart', path: (r) => `/admin/v1/finance/budget/${String(r.id)}/comparison`, method: 'GET' }],
        }),
      },
      // index 整树下发（CostCenterController::buildTree → encodeIds），引擎拍平后按
      // __depth 缩进名称列；列集与原推断一致，仅去掉 parent_id（通用标题「上级部门」
      // 对成本中心是错的，值又是裸 hashid，层级已由缩进列表达）
      // 两页 status 无 DDL 注释、控制器 apidoc 也只有「状态」：TINYINT DEFAULT 1 同族 33/38 为启用语义，
      // 列是显式声明的，字典必须挂列上（statusCol(dict)），cfg.dicts 对显式列无效
      { label: '成本中心', path: '/finance/cost-center', cfg: f('成本中心', '/admin/v1/finance/cost-center', { columns: [textCol('code', '编号', true), { ...textCol('name', '名称', true), indent: true }, textCol('manager', '负责人'), statusCol({ 0: '禁用', 1: '启用' })], fields: [{ key: 'code', label: '编码', required: true }, { key: 'name', label: '名称', required: true }] }) },
      { label: '利润中心', path: '/finance/profit-center', cfg: f('利润中心', '/admin/v1/finance/profit-center', { columns: [textCol('code', '编号', true), { ...textCol('name', '名称', true), indent: true }, textCol('manager', '负责人'), statusCol({ 0: '禁用', 1: '启用' })], fields: [{ key: 'code', label: '编码', required: true }, { key: 'name', label: '名称', required: true }] }) },
      { label: '总账', path: '/finance/general-ledger', cfg: f('总账', '/admin/v1/finance/general-ledger', { canDelete: false, deleteNeedsPassword: false }) },
      // erp_finance_subsidiary_ledger.direction: 1借方2贷方（install.sql:1472）
      { label: '明细分类账', path: '/finance/subsidiary-ledger', cfg: f('明细分类账', '/admin/v1/finance/subsidiary-ledger', { canDelete: false, deleteNeedsPassword: false, dicts: { direction: { 1: '借方', 2: '贷方' } } }) },
      { label: '资产负债表', path: '/finance/balance-sheet', cfg: f('资产负债表', '/admin/v1/finance/report/balance-sheet', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '利润表', path: '/finance/profit-statement', cfg: f('利润表', '/admin/v1/finance/report/profit', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '现金流量表', path: '/finance/cash-flow', cfg: f('现金流量表', '/admin/v1/finance/report/cash-flow', { canDelete: false, deleteNeedsPassword: false }) },
      { label: '进项发票池', path: '/finance/tax-input-invoice', cfg: f('进项发票池', '/admin/v1/finance/tax-input-invoice', { canDelete: false, deleteNeedsPassword: false, dicts: TAX_POOL_DICTS, actions: [{ label: '验真', icon: 'shield', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/verify` }, { label: '勾选', icon: 'check', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/check` }, { label: '抵扣', icon: 'dollar', path: (r) => `/admin/v1/finance/tax-input-invoice/${String(r.id)}/deduct`, bodyFields: [{ key: 'deduct_period', label: '抵扣期间', required: true, placeholder: 'YYYY-MM' }], message: '已抵扣' }] }) },
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
          // erp_company.status: 0=停用 1=启用（install.sql:1050）
          dicts: { status: { 0: '停用', 1: '启用' } },
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
          // erp_finance_period.status: 0=开 1=关（install.sql:1100）
          dicts: { status: { 0: '开', 1: '关' } },
          // 开账走专用 period-open（新期间非行操作），此处仅结账
          actions: [
            { label: '结账', icon: 'check', path: () => '/admin/v1/finance/ledger/period-close', body: (r) => ({ ledger_id: r.ledger_id, period: r.period }), message: '期间已结账' },
          ],
        }),
      },
      // erp_finance_consolidation_report.status: 0=草稿 1=已出（install.sql:1531）
      { label: '合并报表', path: '/finance/consolidation', cfg: f('合并报表', '/admin/v1/finance/consolidation/list', { canDelete: false, deleteNeedsPassword: false, dicts: { status: { 0: '草稿', 1: '已出' } } }) },
    ],
  },
];
