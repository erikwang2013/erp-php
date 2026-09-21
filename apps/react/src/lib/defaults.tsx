/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import type { Column } from '@/components/DataTable';
import { Badge } from '@/components/ui';
import { COLUMN_TITLES_EXTRA } from '@/config/column-titles-extra';
import type { FieldSource, FormField, Row } from '@/config/types';
import { dateTime, money, statusText, statusTone, text } from '@/lib/format';
import { tr } from '@/lib/i18n';
import { optionLabel } from '@/lib/options';

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
  max_quantity: '最大库存阈值',
  method: '收款方式',
  min_quantity: '最小库存阈值',
  module: '所属模块',
  name: '名称',
  no: '编号',
  note: '备注',
  offered_salary: 'Offer 薪资',
  onboard_date: '入职日期',
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
  project_id: '所属项目',
  purchase_amount: '购置金额',
  purchase_date: '购置日期',
  qualified_qty: '合格数量',
  quantity: '数量',
  rate: '汇率值',
  real_name: '姓名',
  receipt_payment_id: '付款单',
  receive_date: '收料日期',
  receive_id: '收货单',
  received_at: '收款日期',
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
  valid_from: '生效日期',
  value: '值',
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

/** 常见状态字典（按资源前缀细化，未命中走通用档） */
const STATUS_DICTS: Record<string, Record<number, string>> = {
  purchase: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  sales: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  crm: { 0: '未开始', 1: '跟进中', 2: '已报价', 3: '赢单', 4: '输单' },
};

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

/** 外键 → 名称兄弟键的非规范别名（结构可扩充） */
const REL_ALIAS: Record<string, string> = {
  partner_id: 'party_name',
  apply_user_id: 'employee_name',
  stage_id: 'stage_name',
};

const isObj = (v: unknown): v is Row =>
  typeof v === 'object' && v !== null && !Array.isArray(v);

/**
 * 关联列取值，顺序固定：① `<base>_name` 兄弟 → ② `<base>` 嵌套关系对象
 * → ③ fields.source 远程选项 → ④ 原值（后端补 hashid 后这里就是编码占位）。
 */
function relationValue(
  row: Row,
  idKey: string,
  nameKey?: string,
  src?: FieldSource,
): unknown {
  if (nameKey) {
    const n = row[nameKey];
    if (n !== null && n !== undefined && n !== '') return n;
  }
  const o = row[idKey.slice(0, -3)];
  if (isObj(o)) {
    const n = o.name ?? o.title ?? o.label ?? o.code;
    if (n !== null && n !== undefined && n !== '') return n;
  }
  if (src) {
    const n = optionLabel(src, row[idKey]);
    if (n) return n;
  }
  return row[idKey];
}

/**
 * 从行样本推断列定义。
 * fields 用于两处：`{key,label}` 给出本页列标题；`{key,source}` 给出外键的远程名称源。
 */
export function inferColumns(
  rows: Row[],
  endpoint: string,
  fields?: FormField[],
  limit = 8,
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

  const prefix = endpoint.split('/')[3] ?? '';
  const dict = STATUS_DICTS[prefix];

  return shown.map((k) => {
    const primary = k === 'code' || k === 'no' || k === 'name';
    let render: ((row: Row) => ReactNode) | undefined;
    let align: 'right' | undefined;

    const nameKey = relName.get(k);
    const src = relSrc.get(k);
    const base = k.slice(0, -3);
    const nested = k.endsWith('_id') && present.has(base) && sample.some((r) => isObj(r[base]));
    if (nameKey || src || nested) {
      // 外键列本身承载名称（`*_name` 同伴成列时已在 shown 里隐去）
      return {
        key: k,
        title: titles.get(k) ?? keyTitle(k),
        primary: false,
        render: (row) => text(relationValue(row, k, nameKey, src)),
      };
    }

    if (isStatus(k)) {
      render = (row) => (
        <Badge text={statusText(row[k], dict)} tone={statusTone(row[k])} solid />
      );
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

/** 推断详情字段（全字段，跳过 id 与嵌套关系） */
export function inferDetailItems(row: Row): { k: string; v: ReactNode }[] {
  return Object.entries(row)
    .filter(([k, v]) => {
      if (k === 'id') return false;
      if (v !== null && typeof v === 'object') return false;
      return true;
    })
    .map(([k, v]) => ({
      k: keyTitle(k),
      v: isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v),
    }));
}
