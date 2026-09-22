/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import type { Column } from '@/components/DataTable';
import { Badge } from '@/components/ui';
import { COLUMN_TITLES_EXTRA } from '@/config/column-titles-extra';
import type { DictMap, FieldSource, FilterDef, FormField, Row } from '@/config/types';
import { mapText } from '@/config/cells';
import { dateTime, money, statusText, statusTone, text, yesNo } from '@/lib/format';
import { tr } from '@/lib/i18n';
import { fkText, isRelKey, nameKeyOf, REL_ALIAS, relationValue } from '@/lib/relation';

/**
 * 列配置推断。
 *
 * ResourceConfig 可以不写 columns —— 引擎从首批行数据推断列，
 * 按字段名识别金额/日期/状态/数量，让任意后端资源两行配置即可上线。
 * 需要精调的资源仍在配置里显式给出 columns 覆盖。
 */

/** 字段名 → 中文列标题
 *
 * 种子：① 全量 config/domains 的 {key,label} 按 key 去重（同 key 冲突取出现最多者）；
 * ② 常见外键/时间/通用键；③ 原有通用条目。三者冲突时保持通用口径，
 * 页面级差异交给 inferColumns 的 fields.label 覆盖。
 */
const TITLES: Record<string, string> = {
  // 补充档先铺底（install.sql 列注释生成，勿手改），下列人工档覆盖同名键
  ...COLUMN_TITLES_EXTRA,
  acceptor: '承兑人',
  account_id: '费用科目',
  address: '地址',
  amount: '金额',
  app_id: '所属应用',
  app_name: '应用名称',
  attrs: '规格属性',
  apply_user_id: '申请人',
  assignee_id: '负责人',
  assignee_user_id: '指派人',
  bank_account: '银行账号',
  bank_account_id: '收款账户',
  bank_name: '开户行',
  barcode: '条码',
  base_salary: '基本工资',
  bill_no: '票据号',
  bom_id: 'BOM',
  brand_id: '品牌',
  candidate_id: '候选人',
  // 非 DB 列：FreightRateController::index 按 carrier_service_id 反查 tms_carrier_service.code
  // （沿用该列 install.sql 注释原词，与本表其它别名同款，不另造第二条说法）
  carrier_service_code: '服务编码',
  carrier_service_id: '承运商服务',
  category: '工单分类',
  category_id: '分类',
  channel: '渠道',
  city: '城市',
  code: '编号',
  contact_person: '联系人',
  content: '文档内容',
  cost: '成本',
  course_type: '课程类型',
  created_at: '创建时间',
  credit_days: '信用账期(天)',
  credit_limit: '信用额度',
  credits: '学分',
  currency: '币种',
  customer_id: '客户',
  dashboard_id: '所属看板',
  date: '日期',
  days: '天数',
  deduction: '扣款',
  defect_qty: '缺陷数量',
  defect_type: '缺陷类型',
  deleted_at: '删除时间',
  delivery_id: '发货单',
  department: '申请部门',
  department_id: '部门',
  depreciation_method: '折旧方法',
  description: '说明',
  discount: '折扣',
  drawer: '出票人',
  due_date: '到期日',
  duration_hours: '课时(小时)',
  effective_date: '生效日期',
  email: '邮箱',
  employee_id: '员工',
  enabled: '是否启用',
  end_date: '结束日期',
  entry_date: '归集日期',
  entry_type: '费用类型',
  equipment_id: '设备',
  event: '订阅事件',
  expected_salary: '期望薪资',
  fault_description: '故障描述',
  follow_user_id: '跟进人',
  frequency: '保养频率',
  from_currency_id: '来源币种',
  group: '分组',
  headcount: '招聘人数',
  hours: '工时数',
  icon: '图标',
  inspected_qty: '检验数量',
  interview_date: '面试日期',
  issue_date: '出票日期',
  // withCount 派生的非 DB 列（RfqController::index / RfqQuoteController::index）：漏了就驼峰化上屏
  items_count: '明细行数',
  job_id: '应聘职位',
  job_title: '职位名称',
  key: '配置键',
  lecturer: '讲师',
  level: '等级',
  level_id: '等级',
  location_id: '库位',
  logo: 'LOGO 地址',
  manager: '负责人',
  manager_user_id: '负责人',
  // 非 DB 列：比价回包（RfqController::compare）的比价矩阵块，嵌套对象标题 = 回包键
  matrix: '比价矩阵',
  max_quantity: '最大库存阈值',
  method: '收款方式',
  min_quantity: '最小库存阈值',
  module: '所属模块',
  name: '名称',
  no: '编号',
  note: '备注',
  offered_salary: 'Offer 薪资',
  onboard_date: '入职日期',
  // 非 DB 列：FulfillmentController::index 按 oms_order_id 带出 oms_order.channel_order_no
  order_channel_no: '渠道订单号',
  // 非 DB 列：mfg 三页 index 按 order_id 反查 mfg_production_order.code 带出
  // （MaterialIssue/WorkReport/CostEntry）；purchase/receive、sales/delivery、oms/rma 的值侧别名同为该键。
  // 措辞沿用词典既有「工单编码」（该列 install.sql 注释原词），不另造第二条
  order_code: '工单编码',
  order_id: '生产工单',
  order_no: '订单号',
  overtime: '加班费',
  owner: '负责人',
  owner_user_id: '负责人',
  parent_id: '上级部门',
  partner_id: '往来方',
  password: '密码',
  path: '路径',
  permission_ids: '权限',
  payee: '收款人',
  performance: '绩效工资',
  period_month: '计划月份',
  period_type: '周期类型',
  period_year: '预算年度',
  phone: '手机',
  planned_quantity: '计划数量',
  position_id: '职位',
  price: '单价',
  priority: '优先级',
  product_id: '产品',
  // 非 DB 列：ProcessCheckController::index leftJoin mfg_production_order.code
  // （沿用该列 install.sql 注释原词「工单编码」，词典里已有该条，不另造第二条）
  production_order_code: '工单编码',
  project_id: '所属项目',
  purchase_amount: '购置金额',
  purchase_date: '购置日期',
  qualified_qty: '合格数量',
  quantity: '数量',
  quotes_count: '报价数',
  rate: '汇率值',
  real_name: '姓名',
  receipt_payment_id: '付款单',
  receive_date: '收料日期',
  receive_id: '收货单',
  received_at: '收款日期',
  // 非 DB 列：IncomingCheckController::index leftJoin purchase_receive.code
  receiving_code: '收货单号',
  // 非 DB 列：ReportScheduleController::index 把 recipients 的 id 换成姓名（Flutter 同用「接收人」）
  recipients_names: '接收人',
  remark: '备注',
  repair_type: '维修类型',
  report_date: '报工日期',
  requirement: '任职要求',
  result: '检验结果',
  round_no: '轮次',
  routing_id: '工序',
  rule_name: '规则名称',
  salvage_value: '残值',
  seq: '工序序号',
  sign_date: '签订日期',
  sku_id: 'SKU ID',
  slug: '标识',
  social_base_max: '缴费基数上限',
  social_base_min: '缴费基数下限',
  sort: '排序',
  source: '来源渠道',
  spec: '规格型号',
  stage: '阶段',
  start_date: '开始日期',
  status: '状态',
  subcontract_id: '委外订单',
  subtotal: '小计',
  summary: '摘要',
  supplier_id: '供应商',
  target_type: '目标类型',
  target_url: '回调地址',
  task_date: '点检日期',
  task_id: '关联任务',
  tax: '税额',
  tax_number: '税号',
  tax_rate: '税率',
  template_id: '报表模板',
  time: '时间',
  title: '标题',
  to_currency_id: '目标币种',
  total: '合计',
  total_amount: '金额',
  total_price: '总金额',
  type: '类型',
  unit: '单位',
  unit_id: '单位',
  unit_price: '加工单价',
  updated_at: '更新时间',
  useful_life: '使用年限',
  user_id: '用户',
  username: '用户名',
  // 角色权限页的列标题与它同字（RoleController::index/show 的 withCount('users')）；
  // 兜底档：列配置若被删，详情行也不会显出 usersCount
  users_count: '用户数',
  valid_from: '生效日期',
  value: '值',
  // 非 DB 列：SubsidiaryLedgerController::index 按 voucher_id 反查 finance_voucher.code
  voucher_code: '凭证号',
  warehouse_id: '仓库',
  work_date: '工作日期',
  workstation_id: '工作站',
  zone_id: '库区',
};

/** 完全不展示的内部字段 */
const HIDDEN = new Set([
  'id',
  'created_at',
  'updated_at',
  'deleted_at',
  'password',
  'remember_token',
  'tenant_id',
  'org_id',
  'version',
]);

const isMoney = (k: string) =>
  /(amount|price|cost|subtotal|balance|total|fee|salary|wage|amount_tax|tax|rate$)/.test(k) &&
  !/(_at|_no|_id)$/.test(k);

const isDate = (k: string) =>
  k.endsWith('_at') || k.endsWith('_date') || k === 'time' || k === 'date';

const isStatus = (k: string) => k === 'status' || k === 'state' || k.endsWith('_status');

const isInt = (k: string) =>
  /(quantity|qty|count|num|days|hours|age|stock|weight|width|height|length)/.test(k) &&
  !isMoney(k);

/**
 * 布尔开关列名：`enabled` 与 `is_*`（是否X）。install.sql 里这 17 列全是 TINYINT 0/1，
 * 但列注释常为空（`erp_finance_tax_rate.enabled` 就是），按注释识别不出来 —— 列名是唯一线索。
 * （与 Angular columns.ts 的 isBool 同口径）
 */
const isBool = (k: string) => k === 'enabled' || /^is_[a-z_]+$/.test(k);

/** 「是否X」的通用文案（DDL 里统一 0=否 1=是）；语义特异的表（is_read=未读/已读）由页面 dicts 覆盖 */
const BOOL_DICT: Record<number, string> = { 0: '否', 1: '是' };

/**
 * 筛选定义 → 状态字典。`docStatus()` 生成的 filter.options 就是字典本身（首项「全部」无值），
 * 所以声明了状态筛选的资源无需另写 columns，状态列也能拿到本表真枚举。
 */
function dictFromFilter(filter?: FilterDef): Record<number, string> | undefined {
  if (!filter || filter.key !== 'status') return undefined;
  const dict: Record<number, string> = {};
  for (const o of filter.options) if (typeof o.value === 'number') dict[o.value] = o.label;
  return Object.keys(dict).length > 0 ? dict : undefined;
}

export function keyTitle(k: string): string {
  if (TITLES[k]) return tr(TITLES[k]);
  // `*_name` / `*_id` 未收录时回落到对端条目：关联列（如 customer_name）靠它拿到中文表头
  const affix = /^(.+)_(name|id)$/.exec(k);
  if (affix) {
    const base = affix[1];
    const other = TITLES[`${base}_${affix[2] === 'name' ? 'id' : 'name'}`] ?? TITLES[base];
    if (other) return tr(other);
  }
  return k.replace(/_([a-z])/g, (_, c: string) => c.toUpperCase());
}

/**
 * 从行样本推断列定义。
 * fields 用于两处：`{key,label}` 给出本页列标题；`{key,source}` 给出外键的远程名称源。
 * filter 是本资源的状态筛选，其选项即状态字典（见 dictFromFilter）。
 * dicts 是 cfg.dicts —— 逐键值字典，优先于状态筛选（见 config/types.ts）。
 */
export function inferColumns(
  rows: Row[],
  // 位置参数保留：调用点与 Angular columns.ts 同序（ResourcePage 传 cfg.endpoint）。
  // 2026-09-22 删掉「按 endpoint 第 4 段猜前缀档」后本函数不再读它，加下划线避开 noUnusedParameters
  _endpoint: string,
  fields?: FormField[],
  limit = 8,
  filter?: FilterDef,
  dicts?: DictMap,
): Column<Row>[] {
  const sample = rows.slice(0, 3);
  const present = new Set<string>();
  for (const r of sample) for (const k of Object.keys(r)) present.add(k);

  // 有关联名可用的外键：隐藏该 `*_id` 列，名称由 `*_name` 列或本列渲染承担
  const relName = new Map<string, string>();
  const relSrc = new Map<string, FieldSource>();
  for (const k of present) {
    if (!k.endsWith('_id') || k === 'id') continue;
    const alias = REL_ALIAS[k];
    const base = k.slice(0, -3);
    const sibling = [alias, `${base}_name`].find((n) => n && present.has(n));
    if (sibling) relName.set(k, sibling);
  }
  for (const f of fields ?? []) if (f.source) relSrc.set(f.key, f.source);

  const titles = new Map((fields ?? []).map((f) => [f.key, f.label]));
  const keys: string[] = [];
  for (const r of sample) {
    for (const k of Object.keys(r)) {
      if (HIDDEN.has(k)) continue;
      if (/^(created_at|updated_at|deleted_at)$/.test(k)) continue;
      if (k === 'id') continue;
      const v = r[k];
      // 跳过嵌套对象/数组（关系字段），避免渲染成 [object Object]；
      // 有 `*_id` 同伴的关系对象由那样列渲染名称（见 relationValue）
      if (v !== null && typeof v === 'object') continue;
      if (!keys.includes(k)) keys.push(k);
      if (keys.length >= limit) break;
    }
    if (keys.length >= limit) break;
  }

  // 有名称兄弟且兄弟本身成列时，隐去外键列（名称由兄弟列展示）；
  // 兄弟被 limit 截掉时保留外键列，改由它渲染名称
  const shown = keys.filter((k) => {
    const name = relName.get(k);
    return !name || !keys.includes(name);
  });

  // 编号类字段提到最前并加粗
  shown.sort((a, b) => {
    const pa = a === 'code' ? 0 : a === 'no' ? 0 : a === 'name' ? 1 : 2;
    const pb = b === 'code' ? 0 : b === 'no' ? 0 : b === 'name' ? 1 : 2;
    return pa - pb;
  });

  // 字典只有一个来源：本资源状态筛选带的（`docStatus()`/`ST_FILTER` 的 options 就是该表枚举的真身）。
  // 与 Angular `columns.ts` 同口径：2026-09-22 删掉「按 endpoint 第 4 段猜前缀档」与通用档，
  // 未命中一律原值直出（见 lib/format.ts 的 statusText）
  const dict = dictFromFilter(filter);

  return shown.map((k) => {
    const primary = k === 'code' || k === 'no' || k === 'name';
    let render: ((row: Row) => ReactNode) | undefined;
    let align: 'right' | undefined;

    const nameKey = relName.get(k);
    const src = relSrc.get(k);
    // 凡是 `*_id` 一律按关联列渲染：三种解析途径（`*_name` 兄弟 / 关系对象 / fields.source 选项）
    // 任一命中就出名称，全未命中由 relationValue 落「-」占位。裸 hashid 贴出来对用户没有意义
    if (k.endsWith('_id')) {
      return {
        key: k,
        title: titles.get(k) ?? keyTitle(k),
        primary: false,
        render: (row) => text(relationValue(row, k, nameKey, src)),
      };
    }

    // 显式逐键字典优先于按字段名的识别：status 键仍走徽标支，只把字典换成 cfg 里的真枚举；
    // 其余枚举键（type/priority/is_lowest…）按 mapText 出文案（与 Angular kind:'map' 同源）
    const kd = dicts?.[k];
    if (isStatus(k)) {
      render = (row) => (
        <Badge text={statusText(row[k], kd ?? dict)} tone={statusTone(row[k])} solid />
      );
    } else if (kd) {
      render = (row) => mapText(row[k], kd);
      // 布尔开关：enabled 走启用/禁用徽标（与 cells.enabledCol 同文案），is_* 走 否/是。
      // 必须排在 isMoney/isInt 之前 —— is_taxable 命中 isMoney 的 `tax`、is_managed 命中 isInt 的 `age`
    } else if (k === 'enabled') {
      render = (row) => <Badge text={yesNo(row[k])} tone={Number(row[k]) === 0 ? 'd' : 's'} />;
    } else if (isBool(k)) {
      render = (row) => mapText(row[k], BOOL_DICT);
    } else if (isMoney(k)) {
      align = 'right';
      render = (row) => money(row[k]);
    } else if (isDate(k)) {
      render = (row) => <span className="muted">{dateTime(row[k])}</span>;
    } else if (isInt(k)) {
      align = 'right';
    } else {
      render = (row) => text(row[k]);
    }

    return {
      key: k,
      title: titles.get(k) ?? keyTitle(k),
      primary,
      align,
      render,
    };
  });
}

/**
 * 推断详情字段：行的每个标量字段优先**复用列表那一列的渲染器**，cols 没覆盖到的键按字段名兜底。
 *
 * 详情原先自己一套 `text(v)`，于是同一份数据在列表里是「已审核」徽标、点开详情却变成 `2`，
 * 外键在列表里是客户名、详情里却回落到裸 hashid。走列渲染器后两处天然同源。
 *
 * 两处跳过与列表口径一致：`*_id` 的名称兄弟已成列时不重复出一行（inferColumns 里那个外键列
 * 就是被兄弟顶掉的），嵌套对象/数组由关系的列承担。
 */
export function inferDetailItems(
  row: Row,
  cols: Column<Row>[] = [],
  dicts?: DictMap,
  fields?: FormField[],
): { k: string; v: ReactNode }[] {
  const byKey = new Map(cols.filter((c) => c.key !== '__actions').map((c) => [c.key, c]));
  // 本页字段声明的措辞：列数被 limit 截掉、或本页写了显式 columns 的键，抽屉里没有列标题可用，
  // 只能落到全局 TITLES —— 于是「同一个键在不同页语义不同」时必错（order_id 全局「生产工单」，
  // 但 /oms/rma 是「关联订单」）。这里把本页 fields 的 label 插在 keyTitle 之前兜底
  const fieldLabel = new Map((fields ?? []).map((f) => [f.key, f.label]));
  return Object.entries(row)
    .filter(([k, v]) => {
      if (k === 'id' || k.startsWith('__')) return false;
      if (v !== null && typeof v === 'object') return false;
      // 名称兄弟已成一列、或已是行里的标量键（抽屉会把它作为自己那行渲染）→ 裸外键不出行，
      // 否则同一份名称会「兄弟一行 + 外键一行」重复出两次
      if (isRelKey(k, v)) {
        const nameKey = nameKeyOf(k);
        if (nameKey && byKey.has(nameKey)) return false;
        const sv = row[nameKey ?? ''];
        if (sv !== null && sv !== undefined && sv !== '' && typeof sv !== 'object') return false;
      }
      return true;
    })
    .map(([k, v]) => {
      const col = byKey.get(k);
      const pageLabel = fieldLabel.get(k);
      return {
        // 配置里声明的标题是中文原文，而详情抽屉不像 Angular 模板那样对标签再过 `| tr`：
        // 不过一遍的话 en 下**已声明列**的标签露中文（keyTitle 那条路本来就在 tr）。
        // 优先级：列标题 > 本页字段 label（order_id 在 /oms/rma 是「关联订单」）> 全局词条
        k: col?.title ? tr(col.title) : pageLabel ? tr(pageLabel) : keyTitle(k),
        v: col?.render ? col.render(row) : fallbackValue(k, v, dicts, row),
      };
    });
}

/** cols 未覆盖的键（列数被 limit 截断、非首批样本字段）的兜底渲染，识别口径同 inferColumns */
function fallbackValue(k: string, v: unknown, dicts?: DictMap, row?: Row): ReactNode {
  // 逐键字典先于字段名识别：列数被 limit 截掉的枚举键（第 9 列起的 type/priority…）
  // 只能走这条兜底，没有字典就在这里裸出 0/1
  const kd = dicts?.[k];
  if (kd) return mapText(v, kd);
  // 布尔开关：与 inferColumns 同口径（列数被 limit 截掉的开关键只能走这条兜底）
  if (k === 'enabled') return <Badge text={yesNo(v)} tone={Number(v) === 0 ? 'd' : 's'} />;
  if (isBool(k)) return mapText(v, BOOL_DICT);
  // 裸外键：`_id`/`_by`/`assigned_to` 这类键存的都是编码后的 ID（旧口径只认 `_id`，
  // 于是 approved_by 被当普通文本贴出来）。行里有名称兄弟（含 REL_ALIAS 别名）就出名称，
  // 取不到落 `-` 占位——裸 ID 贴出来只是噪声
  if (isRelKey(k, v)) return fkText(row ?? {}, k);
  if (isStatus(k)) return <Badge text={statusText(v)} tone={statusTone(v)} />;
  if (isDate(k)) return dateTime(v);
  if (isMoney(k)) return money(v);
  return text(v);
}
