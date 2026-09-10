/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ColumnDef, Row } from '../../config/types';
import {
  date,
  dateTime,
  int,
  money,
  statusText,
  statusTone,
  text,
  yesNo,
  type BadgeTone,
} from '../../core/format';

/**
 * 列推断 + 单元格取值 —— 对齐 React `lib/defaults.tsx` / `config/cells.tsx` 的语义。
 *
 * React 端的列渲染是 JSX 闭包（Column.render），Angular 端配置里不写回调，
 * 改由引擎按 ColumnDef.kind 解释：本文件是所有渲染语义的唯一落点，
 * 模板只负责把取好的文本贴到 DOM 上（模板无法被 tsc 检查，逻辑必须留在 TS 里）。
 */

/** 点号路径取行内值（与 React DataTable 的 take 同义） */
export function take(row: Row, key: string): unknown {
  return key
    .split('.')
    .reduce<unknown>(
      (o, k) => (o && typeof o === 'object' ? (o as Record<string, unknown>)[k] : undefined),
      row,
    );
}

/** 字段名 → 中文列标题（只给中文原文，翻译交给模板 | tr，语言切换才会重渲染） */
const TITLES: Record<string, string> = {
  code: '编号',
  no: '编号',
  name: '名称',
  title: '标题',
  type: '类型',
  status: '状态',
  quantity: '数量',
  amount: '金额',
  total_amount: '金额',
  total: '合计',
  price: '单价',
  cost: '成本',
  subtotal: '小计',
  remark: '备注',
  description: '说明',
  note: '备注',
  phone: '手机',
  email: '邮箱',
  address: '地址',
  username: '用户名',
  real_name: '姓名',
  created_at: '创建时间',
  updated_at: '更新时间',
  due_date: '到期日',
  start_date: '开始日期',
  end_date: '结束日期',
  currency: '币种',
  discount: '折扣',
  tax: '税额',
  level: '等级',
  channel: '渠道',
  owner: '负责人',
};

/** 完全不展示的内部字段（含 id 与三个时间戳，推断时直接跳过） */
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

const isMoney = (k: string): boolean =>
  /(amount|price|cost|subtotal|balance|total|fee|salary|wage|amount_tax|tax|rate$)/.test(k) &&
  !/(_at|_no|_id)$/.test(k);

const isDate = (k: string): boolean =>
  k.endsWith('_at') || k.endsWith('_date') || k === 'time' || k === 'date';

const isStatus = (k: string): boolean => k === 'status' || k === 'state' || k.endsWith('_status');

const isInt = (k: string): boolean =>
  /(quantity|qty|count|num|days|hours|age|stock|weight|width|height|length)/.test(k) && !isMoney(k);

/** 常见状态字典（按资源前缀细化，未命中走通用档 COMMON_STATUS） */
const STATUS_DICTS: Record<string, Record<number, string>> = {
  purchase: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  sales: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  crm: { 0: '未开始', 1: '跟进中', 2: '已报价', 3: '赢单', 4: '输单' },
};

/** 键名 → 列标题：命中词典用中文，否则驼峰化（与 React keyTitle 同源） */
export function keyTitle(k: string): string {
  const zh = TITLES[k];
  return zh ?? k.replace(/_([a-z])/g, (_m: string, c: string) => c.toUpperCase());
}

/**
 * 从行样本推断列定义 —— 配置不写 columns 时让任意后端资源两行配置即可上线。
 * 金额/日期/状态/数量按字段名识别；编号类字段提到最前并加粗。
 */
export function inferColumns(rows: Row[], endpoint: string, limit = 8): ColumnDef[] {
  const keys: string[] = [];
  for (const r of rows.slice(0, 3)) {
    for (const k of Object.keys(r)) {
      if (HIDDEN.has(k)) continue;
      const v = r[k];
      // 嵌套对象/数组是关系字段，渲染出来只会是 [object Object]
      if (v !== null && typeof v === 'object') continue;
      if (!keys.includes(k)) keys.push(k);
      if (keys.length >= limit) break;
    }
    if (keys.length >= limit) break;
  }

  keys.sort((a, b) => {
    const rank = (k: string): number => (k === 'code' || k === 'no' ? 0 : k === 'name' ? 1 : 2);
    return rank(a) - rank(b);
  });

  // /admin/v1/{资源} 的第 4 段即资源名，用它挑状态字典
  const dict = STATUS_DICTS[endpoint.split('/')[3] ?? ''];

  return keys.map((k): ColumnDef => {
    const base: ColumnDef = {
      key: k,
      title: keyTitle(k),
      primary: k === 'code' || k === 'no' || k === 'name',
    };
    if (isStatus(k)) return { ...base, kind: 'status', dict };
    if (isMoney(k)) return { ...base, kind: 'money', align: 'right' };
    if (isDate(k)) return { ...base, kind: 'datetime' };
    if (isInt(k)) return { ...base, kind: 'int', align: 'right' };
    return { ...base, kind: 'text' };
  });
}

/** 详情弹窗条目（全字段，只跳过 id 与嵌套关系） */
export function inferDetailItems(row: Row): { k: string; v: string }[] {
  return Object.entries(row)
    .filter(([k, v]) => k !== 'id' && !(v !== null && typeof v === 'object'))
    .map(([k, v]) => ({
      k: keyTitle(k),
      v: isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v),
    }));
}

/** 单元格：文本 + 展示标记，读法与列定义解耦，模板不必再回查 ColumnDef */
export interface Cell {
  text: string;
  /** 有 tone 渲染徽标，无则纯文本 */
  tone?: BadgeTone;
  primary?: boolean;
  right?: boolean;
}

/** 按 kind 取单元格 —— 与 React cells.tsx / DataTable 默认渲染逐支对应 */
export function cellOf(c: ColumnDef, row: Row): Cell {
  const v = take(row, c.key);
  const cell: Cell = { text: '', primary: c.primary === true, right: c.align === 'right' };
  switch (c.kind) {
    case 'money':
      cell.text = money(v);
      break;
    case 'int':
      cell.text = int(v);
      break;
    case 'datetime':
      cell.text = dateTime(v);
      break;
    case 'date':
      cell.text = date(v);
      break;
    case 'text':
      cell.text = text(v);
      break;
    case 'status':
      cell.text = statusText(v, c.dict);
      cell.tone = statusTone(v);
      break;
    case 'enabled':
      cell.text = yesNo(v);
      cell.tone = Number(v) === 0 ? 'd' : 's';
      break;
    case 'map': {
      if (v === null || v === undefined || v === '') break;
      const hit = c.dict?.[Number(v)];
      cell.text = hit !== undefined ? hit : String(v);
      break;
    }
    default:
      // 裸列：直出（text 类走 '-' 兜底，见 format.text）
      cell.text = String(v ?? '');
      break;
  }
  return cell;
}

/** tone → nz-tag 预设色（与 React Badge 的 s/w/d/i 同义） */
export const TONE_COLOR: Record<BadgeTone, string> = {
  s: 'success',
  w: 'warning',
  d: 'error',
  i: 'default',
};
