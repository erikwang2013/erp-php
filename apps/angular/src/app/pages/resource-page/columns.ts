/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { COLUMN_TITLES } from '../../config/column-titles';
import type { ColumnDef, FieldSource, FormField, Row } from '../../config/types';
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

/** 键名 → 列标题：字段 label 优先，命中词典用中文，否则驼峰化（与 React keyTitle 同源） */
export function keyTitle(k: string, label?: string): string {
  const zh = label ?? COLUMN_TITLES[k];
  return zh ?? k.replace(/_([a-z])/g, (_m: string, c: string) => c.toUpperCase());
}

/** 关联 id → 名称映射表：endpoint → (id 字符串 → 名称)；由 OptionSource 加载后交给推断 */
export type RelLabels = Record<string, Record<string, string>>;

/** 非规范别名：`*_id` → 行内实际承载名称的字段（可扩充；未列出的走 `<base>_name`） */
const NAME_ALIAS: Record<string, string> = {
  partner_id: 'party_name',
  apply_user_id: 'employee_name',
  stage_id: 'stage_name',
};

/** 关系对象里取名称：name → title → label → code；空对象/数组取不到（不给 [object Object]） */
function relName(v: unknown): string {
  if (!v || typeof v !== 'object' || Array.isArray(v)) return '';
  const o = v as Row;
  for (const k of ['name', 'title', 'label', 'code']) {
    const s = o[k];
    if (s !== null && s !== undefined && s !== '' && typeof s !== 'object') return String(s);
  }
  return '';
}

/** 行内可用的关联名：`<base>_name` 兄弟（含别名）优先，其次 `<base>` 关系对象（with 预加载） */
function inlineRelName(row: Row, idKey: string, stem: string): string {
  const n = row[NAME_ALIAS[idKey] ?? `${stem}_name`];
  if (n !== null && n !== undefined && n !== '' && typeof n !== 'object') return String(n);
  return relName(row[stem]);
}

/**
 * 关联列取数需求（契约 B 的 rule 3）：行里出现的、`cfg.fields` 给了 source 的外键键中，
 * 行内没有名称可用（无 `<base>_name`、无 `<base>` 关系对象）的那些，按 endpoint 去重。
 * 页面据此预热 OptionSource —— 行内已有名称的不必白拉一次接口。
 */
export function relSources(rows: Row[], fields: FormField[] = []): FieldSource[] {
  const byKey = new Map<string, FieldSource>();
  for (const f of fields) if (f.source) byKey.set(f.key, f.source);
  const out = new Map<string, FieldSource>();
  if (!byKey.size) return [];
  for (const r of rows.slice(0, 3)) {
    for (const k of Object.keys(r)) {
      const src = k.endsWith('_id') ? byKey.get(k) : undefined;
      if (!src || out.has(src.endpoint)) continue;
      if (inlineRelName(r, k, k.slice(0, -'_id'.length)) !== '') continue;
      out.set(src.endpoint, src);
    }
  }
  return [...out.values()];
}

/**
 * 从行样本推断列定义 —— 配置不写 columns 时让任意后端资源两行配置即可上线。
 * 金额/日期/状态/数量按字段名识别；编号类字段提到最前并加粗。
 *
 * 关联列解析顺序（契约 B，逐条固定）：
 * 1) 行有 `<base>_name`（另有别名表）→ 名称列自己会渲染，外键列整个隐藏；
 * 2) 行有 `<base>` 关系对象（with 预加载）→ 在该外键的位置渲染对象里的名称；
 * 3) `cfg.fields` 里该键配了 source 且选项已加载（labels）→ 在该外键的位置渲染 id→名称；
 *    加载失败或该行未命中 → 名称取不到，回落 4；
 * 4) 兜底：按原值渲染该外键列，不隐藏（后端补 hashid 编码后这里就是 hashid）。
 * 注意 2)/3) 是「原位改名」而非删列：名称得有地方显示，所以列还在、只是不再显示裸 id。
 *
 * @param fields cfg.fields —— 其中的 label 优先做列标题（契约 A）
 * @param labels OptionSource 加载好的 id→名称映射（rule 3；未加载到就退回原值）
 */
export function inferColumns(
  rows: Row[],
  endpoint: string,
  fields: FormField[] = [],
  labels: RelLabels = {},
  limit = 8,
): ColumnDef[] {
  const sample = rows.slice(0, 3);
  const labelOf = new Map<string, string>();
  const sourceOf = new Map<string, FieldSource>();
  for (const f of fields) {
    if (!labelOf.has(f.key)) labelOf.set(f.key, f.label);
    if (f.source && !sourceOf.has(f.key)) sourceOf.set(f.key, f.source);
  }
  const titleOf = (k: string): string => {
    if (labelOf.has(k) || COLUMN_TITLES[k]) return keyTitle(k, labelOf.get(k));
    // `<base>_name` 名称列自己没文案时借 `<base>_id` 的：外键列被它顶掉了，标题得跟着（customer_name 列 → 「客户」）
    if (k.endsWith('_name')) {
      const idKey = `${k.slice(0, -'_name'.length)}_id`;
      if (labelOf.has(idKey) || COLUMN_TITLES[idKey]) return keyTitle(idKey, labelOf.get(idKey));
    }
    return keyTitle(k); // 未知键兜底：驼峰化，绝不留空白表头
  };

  const keys: string[] = [];
  for (const r of sample) {
    for (const k of Object.keys(r)) {
      if (HIDDEN.has(k) || k.startsWith('__')) continue;
      const v = r[k];
      // 嵌套对象/数组是关系字段：不单独成列，rule 2 会把它挂到 `<base>_id` 的位置上
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

  const cols: ColumnDef[] = [];
  for (const k of keys) {
    if (k.endsWith('_id')) {
      const stem = k.slice(0, -'_id'.length);
      const nameKey = NAME_ALIAS[k] ?? `${stem}_name`;
      const hasNameCol = sample.some((r) => {
        const v = r[nameKey];
        return v !== null && v !== undefined && v !== '' && typeof v !== 'object';
      });
      if (hasNameCol) continue; // rule 1
      if (sample.some((r) => relName(r[stem]) !== '')) {
        // rule 2：列键换成关系对象所在的键，cellOf 从对象里取名称
        cols.push({ key: stem, title: titleOf(k), kind: 'rel' });
        continue;
      }
      const src = sourceOf.get(k);
      const map = src ? labels[src.endpoint] : undefined;
      if (map && Object.keys(map).length) {
        cols.push({ key: k, title: titleOf(k), kind: 'rel', rel: map }); // rule 3
        continue;
      }
    }
    // rule 4 / 普通列：按字段名识别 kind（外键识别不出任何 kind，走 text 原值直出）
    const base: ColumnDef = {
      key: k,
      title: titleOf(k),
      primary: k === 'code' || k === 'no' || k === 'name',
    };
    if (isStatus(k)) cols.push({ ...base, kind: 'status', dict });
    else if (isMoney(k)) cols.push({ ...base, kind: 'money', align: 'right' });
    else if (isDate(k)) cols.push({ ...base, kind: 'datetime' });
    else if (isInt(k)) cols.push({ ...base, kind: 'int', align: 'right' });
    else cols.push({ ...base, kind: 'text' });
  }
  return cols;
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
 * 规格属性 JSON 字符串（如 `erp_product_spec.attrs`，后端 `json_encode` 存的）→ 一批「键:值」文本，供列表/详情渲染胶囊。
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
    case 'rel': {
      // 关系对象（rule 2）→ 取对象里的名称；id（rule 3）→ 查映射；都没命中 → 原值（rule 4）
      if (v !== null && typeof v === 'object') {
        cell.text = relName(v);
        break;
      }
      const hit = v === null || v === undefined ? undefined : c.rel?.[String(v)];
      cell.text = hit !== undefined && hit !== '' ? hit : text(v);
      break;
    }
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
