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
      if (HIDDEN.has(k) || k.startsWith('__')) continue;
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

/**
 * 树形响应 → 平铺行：children 递归展开，节点带 `__depth`（列按深度缩进），
 * 展开后的 children 从行上摘掉，避免再被当成关系字段渲染/推断。
 */
export function flattenTree(rows: Row[], depth = 0): Row[] {
  const out: Row[] = [];
  for (const row of rows) {
    const kids = Array.isArray(row['children']) ? (row['children'] as Row[]) : [];
    const flat: Row = { ...row, __depth: depth };
    delete flat['children'];
    out.push(flat);
    if (kids.length) out.push(...flattenTree(kids, depth + 1));
  }
  return out;
}

/**
 * 详情弹窗条目（全字段，只跳过 id、内部标记与嵌套关系）。
 * 传 cols 时用列的正式标题（配置里写的 label）覆盖推断标题；列配了 `kind:'tags'` 的字段附带胶囊。
 */
export function inferDetailItems(
  row: Row,
  cols: ColumnDef[] = [],
): { k: string; v: string; tags?: string[] }[] {
  return Object.entries(row)
    .filter(([k, v]) => k !== 'id' && !k.startsWith('__') && !(v !== null && typeof v === 'object'))
    .map(([k, v]) => {
      const col = cols.find((c) => c.key === k);
      return {
        k: col?.title ?? keyTitle(k),
        v: isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v),
        tags: col?.kind === 'tags' ? specTags(v) : undefined,
      };
    });
}

/* spec-attrs:start —— 解析器无依赖、纯函数；scripts/check-ng-spec-attrs.mjs 抽取本段真身自检 */

/** 商品 `spec` 列宽：VARCHAR(200)。合成串超长必须拒绝提交，不静默截断 */
export const SPEC_MAX = 200;

/** 任意入参 → 待解析文本（非字符串对象先 JSON.stringify；null/undefined/空白 → ''） */
function specText(raw: unknown): string {
  return typeof raw === 'string'
    ? raw.trim()
    : raw === null || raw === undefined
      ? ''
      : (JSON.stringify(raw) ?? '');
}

/** 文本 → JSON 值；空串与坏 JSON 都回 undefined（调用方据此区分「原文回显」与「合法 null」） */
function specTryParse(s: string): unknown {
  if (s === '') return undefined;
  try {
    return JSON.parse(s);
  } catch {
    return undefined;
  }
}

/** 单个值 → 文本：数组（契约形态）按 `/` 连接，其余对象降级 JSON 原文（绝不出 [object Object]） */
function specVal(v: unknown): string {
  if (v === null || v === undefined) return '';
  if (Array.isArray(v)) return v.map(specVal).join('/');
  return typeof v === 'object' ? JSON.stringify(v) : String(v);
}

/** 单个属性值 → 值列表（契约是字符串数组；脏值按单元素处理，空串剔除） */
function specList(v: unknown): string[] {
  const out = Array.isArray(v) ? v.map(specVal) : [specVal(v)];
  return out.filter((s) => s !== '');
}

/**
 * `spec_attrs`（后端 `json_encode` 存的字符串）→ 一批「键:值」文本，供列表/详情渲染胶囊。
 * 线上取值很脏，六种形状全兜住：`{"颜色":["红","蓝"]}` / `[]` / `""` / `null` / `{a:{b:1}}` / `not-json`。
 * 契约：**永不抛异常**；空值一律 `[]`（不渲染，`"[]"` 是后端默认值不是异常）；
 * 非对象或解析失败原样回一条原文，不丢弃不改写（脏数据要看得见才好排查）。
 * 用显式 null/undefined 判定而非真值判定，`0`/`false` 值照常显示。
 */
export function specTags(raw: unknown): string[] {
  const s = specText(raw);
  if (s === '') return [];
  const val = specTryParse(s);
  if (val === undefined) return [s];
  if (val === null) return [];
  if (Array.isArray(val)) return val.length ? [s] : [];
  if (typeof val !== 'object') return [s];
  const obj = val as Record<string, unknown>;
  return Object.entries(obj).map(([k, v]) => `${k}:${specVal(v)}`);
}

/** attrs JSON → 有序属性组（值列表保留供逐项勾选；空值组保留，编辑器要能补值） */
export function specGroups(raw: unknown) {
  const val = specTryParse(specText(raw));
  if (val === null || val === undefined || typeof val !== 'object' || Array.isArray(val)) return [];
  return Object.entries(val as Record<string, unknown>).map(([k, v]) => ({ k, vs: specList(v) }));
}

/** attrs JSON → 编辑器行（值列表按 `/` 连成一个输入框） */
function specRows(raw: unknown) {
  return specGroups(raw).map((g) => ({ k: g.k, v: g.vs.join('/') }));
}

/**
 * 编辑器行 → 契约 JSON 字符串：值恒为**字符串数组**（服务端 normalizeSpecAttrs 只收这种形态，
 * 顶层数组/标量值/非字符串元素一律 422）。空属性名跳过、空值组丢弃（服务端虽收 `{"a":[]}` 但无意义），
 * 无有效行给 `{}` —— 服务端约定的空值形态（NULL 会被 fillableOnly 的 isset 过滤掉，故不能用空串表达）。
 */
function specJson(rows: unknown): string {
  const out = new Map();
  for (const r of Array.isArray(rows) ? rows : []) {
    const row = (r ?? {}) as Record<string, unknown>;
    const k = specVal(row['k']).trim();
    if (k === '') continue;
    const vs = specVal(row['v'])
      .split('/')
      .map((s) => s.trim())
      .filter((s) => s !== '');
    if (vs.length) out.set(k, vs);
  }
  return JSON.stringify(Object.fromEntries(out));
}

/** 选中项 → 商品 `spec` 字符串：组内 `/` 连接、组间空格（`颜色:红/蓝 尺寸:XL`）；空组不进串 */
export function specCompose(picked: unknown): string {
  const parts = [];
  for (const g of Array.isArray(picked) ? picked : []) {
    const grp = (g ?? {}) as Record<string, unknown>;
    const k = specVal(grp['k']).trim();
    const vs = (Array.isArray(grp['vs']) ? grp['vs'] : []).map(specVal).filter((s) => s !== '');
    if (k !== '' && vs.length) parts.push(`${k}:${vs.join('/')}`);
  }
  return parts.join(' ');
}
/* spec-attrs:end */

/** 单元格：文本 + 展示标记，读法与列定义解耦，模板不必再回查 ColumnDef */
export interface Cell {
  text: string;
  /** 有 tone 渲染徽标，无则纯文本 */
  tone?: BadgeTone;
  /** 胶囊列表（kind:'tags'）；非空时模板优先渲染，text 即被忽略 */
  tags?: string[];
  primary?: boolean;
  right?: boolean;
  /** 树形平铺层级（模板据此缩进；undefined = 不缩进） */
  depth?: number;
}

/** 按 kind 取单元格 —— 与 React cells.tsx / DataTable 默认渲染逐支对应 */
export function cellOf(c: ColumnDef, row: Row): Cell {
  const v = take(row, c.key);
  const cell: Cell = { text: '', primary: c.primary === true, right: c.align === 'right' };
  // 树形平铺（flattenTree）后的层级；非树响应没有 __depth，保持 undefined 不缩进
  if (c.indent && row['__depth'] !== undefined) cell.depth = Number(row['__depth']) || 0;
  switch (c.kind) {
    case 'tags':
      // 每条属性一枚胶囊；无属性（含后端默认 "{}"）留空文本，不把 JSON 原文贴出来
      cell.tags = specTags(v);
      break;
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
