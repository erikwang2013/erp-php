/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { COLUMN_TITLES } from '../../config/column-titles';
import type { ColumnDef, FieldSource, FilterDef, FormField, Row } from '../../config/types';
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
const STATUS_DICTS: Record<string, Record<number | string, string>> = {
  purchase: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  sales: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  crm: { 0: '未开始', 1: '跟进中', 2: '已报价', 3: '赢单', 4: '输单' },
};

/**
 * 筛选定义 → 状态字典。`docStatus()` 生成的 filter.options 就是字典本身（首项「全部」无值），
 * 所以声明了状态筛选的资源无需另写 columns，状态列也能拿到本表真枚举。
 */
function dictFromFilter(filter?: FilterDef): Record<number | string, string> | undefined {
  if (!filter || filter.key !== 'status') return undefined;
  const dict: Record<number | string, string> = {};
  for (const o of filter.options) if (typeof o.value === 'number') dict[o.value] = o.label;
  return Object.keys(dict).length > 0 ? dict : undefined;
}

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
  // 采购订单 → 采购申请单号（OrderController::index leftJoin purchase_apply 带出的 apply_code）
  apply_id: 'apply_code',
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
 * 4) 兜底：列还在，值落「-」占位 —— 后端补 hashid 编码后原值就是一串雪花编码，
 *    对用户没有任何可粘贴的去处，贴出来只是噪声。
 * 注意 2)/3) 是「原位改名」而非删列：名称得有地方显示，所以列还在、只是不再显示裸 id。
 *
 * @param fields cfg.fields —— 其中的 label 优先做列标题（契约 A）
 * @param labels OptionSource 加载好的 id→名称映射（rule 3；未加载到就退回原值）
 * @param filter 本资源的状态筛选，其选项即状态字典（见 dictFromFilter）
 */
export function inferColumns(
  rows: Row[],
  endpoint: string,
  fields: FormField[] = [],
  labels: RelLabels = {},
  limit = 8,
  filter?: FilterDef,
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

  // 字典优先级：本资源状态筛选带的（就是该表枚举的真身）→ /admin/v1/{资源} 第 4 段前缀档 → 通用档。
  // 前缀档只收录了 purchase/sales/crm，其余模块猜出来的文案会张冠李戴
  // （如 erp_hr_leave 的 2=已驳回 被猜成通用档的「处理中」）
  const dict = dictFromFilter(filter) ?? STATUS_DICTS[endpoint.split('/')[3] ?? ''];

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
      // rule 4：三条解析途径全落空 —— 仍按关联列渲染，cellOf 的 rel 分支落「-」占位。
      // 裸 hashid 贴出来对用户没有意义（没有哪一页能粘回去）
      cols.push({ key: k, title: titleOf(k), kind: 'rel' });
      continue;
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

/** 行的树标识：树接口的行都带 id（hashid 字符串），取不到时按空串（两端同口径） */
export const rowKey = (row: Row): string => String(row['id'] ?? '');

/**
 * 树形响应 → 平铺行：children 递归展开，节点带 `__depth`（列按深度缩进）、
 * `__path`（根到父的 key 链，折叠过滤用）、`__kids`（有无子节点，叶子不画箭头），
 * 展开后的 children 从行上摘掉，避免再被当成关系字段渲染或推断。
 */
export function flattenTree(rows: Row[], depth = 0, path: string[] = []): Row[] {
  const out: Row[] = [];
  for (const row of rows) {
    const kids = Array.isArray(row['children']) ? (row['children'] as Row[]) : [];
    const flat: Row = { ...row, __depth: depth, __path: path, __kids: kids.length > 0 };
    delete flat['children'];
    out.push(flat);
    if (kids.length) out.push(...flattenTree(kids, depth + 1, [...path, rowKey(row)]));
  }
  return out;
}

/**
 * 折叠集合下的可见行：`__path` 上任一祖先被折叠 → 该行连同整棵子树一起隐藏。
 * 折叠集为空时零拷贝返回（没折过是常见路径，非树响应也走这条）。
 * 与 React `lib/tree.ts` 的同名函数逐条一致（scripts/check-fe-tree.mjs 引两端真身跑同一批断言）。
 */
export function visibleRows(rows: Row[], collapsed: ReadonlySet<string>): Row[] {
  if (!collapsed.size) return rows;
  const path = (r: Row): string[] | undefined => r['__path'] as string[] | undefined;
  // 非树行没有 __path，任何折叠集都藏不住它
  return rows.filter((r) => !path(r)?.some((k) => collapsed.has(k)));
}

/** 切换一行折叠态，返回新集合（模板信号要新引用；React 端同语义） */
export function toggleCollapsed(cur: ReadonlySet<string>, key: string): Set<string> {
  const out = new Set(cur);
  if (!out.delete(key)) out.add(key);
  return out;
}

/**
 * 详情弹窗条目（全字段，只跳过 id、内部标记与嵌套关系）。
 *
 * 值**复用列表那一列的取数**（cellOf）：同一个字段在表格里是「已审核」徽标 + 字典文案、
 * 详情里却回落到裸数字 `2`，外键在表格里是客户名、详情里却是裸 hashid —— 走同一个 cellOf
 * 两处天然同源。cols 没覆盖到的键（列数被 limit 截断）按字段名兜底。
 *
 * `*_id` 的名称兄弟已成列时，裸外键不再单独出一行（列表里那个外键列就是被兄弟顶掉的）。
 */
export function inferDetailItems(
  row: Row,
  cols: ColumnDef[] = [],
): { k: string; v: string; tone?: BadgeTone; tags?: string[] }[] {
  const byKey = new Map(cols.map((c) => [c.key, c]));
  const items: { k: string; v: string; tone?: BadgeTone; tags?: string[] }[] = [];
  for (const [k, v] of Object.entries(row)) {
    if (k === 'id' || k.startsWith('__')) continue;
    const col = byKey.get(k);
    if (v !== null && typeof v === 'object') {
      // 关系对象：rule 2 把列键换成了 `<base>`（外键列原位改名），跟着出对象里的名称。
      // 没有对应列的嵌套值（数组、无人认领的关系）不出行
      if (!col) continue;
      const cell = cellOf(col, row);
      items.push({ k: col.title, v: cell.text, tone: cell.tone, tags: cell.tags });
      continue;
    }
    // 裸外键：名称兄弟（rule 1）或关系对象列（rule 2）已经在别处承担了名称，这里不再出行
    if (k.endsWith('_id')) {
      const stem = k.slice(0, -'_id'.length);
      if (byKey.has(NAME_ALIAS[k] ?? `${stem}_name`) || byKey.has(stem)) continue;
    }
    const cell = col ? cellOf(col, row) : fallbackCell(k, v);
    items.push({ k: col?.title ?? keyTitle(k), v: cell.text, tone: cell.tone, tags: cell.tags });
  }
  return items;
}

/** cols 未覆盖的键的兜底取数，识别口径与 inferColumns 一致 */
function fallbackCell(k: string, v: unknown): Cell {
  // 裸外键：cols 没覆盖到它（资源写了显式 columns，或名称兄弟被截断）时给占位。
  // 关联名取得到的话，早就以 `*_name` 列或关系对象列的形式进来了；剩下的原值是编码后的
  // 雪花 ID，贴出来只是噪声——详情页出现裸 ID 正是这一条漏的
  if (k.endsWith('_id')) return { text: text(undefined) };
  if (isStatus(k)) return { text: statusText(v), tone: statusTone(v) };
  if (isDate(k)) return { text: dateTime(v) };
  if (isMoney(k)) return { text: money(v) };
  return { text: text(v) };
}

/* ── 动作结果 / 报表对象 → 渲染分块 ── */

/** 结果分块：head 非空 = 表格（cells 与 head 同序）；否则键值表（读 kv）；depth 供嵌套缩进 */
export interface ResultBlock {
  title: string;
  depth: number;
  head: string[];
  cells: string[][];
  kv: { k: string; v: string }[];
}

// ponytail: 报表整表下发，行数与嵌套深度先夹顶防渲染卡死；真需要再换虚拟滚动
const RESULT_MAX_ROWS = 500;
const RESULT_MAX_DEPTH = 4;

const isPlainObject = (v: unknown): v is Row => !!v && typeof v === 'object' && !Array.isArray(v);

/** 单元格文本：日期/金额按列名格式化，其余 text 兜底；对象/数组降级 JSON（绝不出 [object Object]） */
function cellText(k: string, v: unknown): string {
  if (isPlainObject(v) || Array.isArray(v)) return JSON.stringify(v) ?? '';
  return isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v);
}

/**
 * 任意 JSON → 渲染分块（动作结果弹窗与报表对象页共用）：
 * 对象 → 键值表 + 值里的对象/数组递归成嵌套块；对象数组 → 表格（列取各行键并集，按首现序）；
 * 标量数组 → 顿号连接的一行。
 */
export function resultBlocks(data: unknown, title = ''): ResultBlock[] {
  const out: ResultBlock[] = [];
  walkResult(data, title, 0, out);
  return out;
}

function walkResult(v: unknown, title: string, depth: number, out: ResultBlock[]): void {
  // 超过夹顶深度不再递归，但也不静默丢数据：降级成一行 JSON
  if (depth > RESULT_MAX_DEPTH) {
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: cellText('', v) }] });
    return;
  }
  if (Array.isArray(v)) {
    const rows = v.filter(isPlainObject);
    if (rows.length && rows.length === v.length) {
      const head: string[] = [];
      for (const r of rows) for (const k of Object.keys(r)) if (!head.includes(k)) head.push(k);
      out.push({
        title,
        depth,
        head,
        cells: rows.slice(0, RESULT_MAX_ROWS).map((r) => head.map((k) => cellText(k, r[k]))),
        kv: [],
      });
      return;
    }
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: v.map((x) => cellText('', x)).join('、') }] });
    return;
  }
  if (!isPlainObject(v)) {
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: cellText('', v) }] });
    return;
  }
  const kv: { k: string; v: string }[] = [];
  const nested: [unknown, string][] = [];
  for (const [k, val] of Object.entries(v)) {
    if (k === 'id' || k.startsWith('__')) continue;
    if (isPlainObject(val) || Array.isArray(val)) nested.push([val, k]);
    else kv.push({ k: keyTitle(k), v: cellText(k, val) });
  }
  if (kv.length || !nested.length) out.push({ title, depth, head: [], cells: [], kv });
  for (const [val, k] of nested) walkResult(val, k, depth + 1, out);
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
      // 关系对象（rule 2）→ 取对象里的名称；id（rule 3）→ 查映射；
      // 都没命中（rule 4）→ 占位短横：裸 hashid 在任何页面都没有可粘贴的去处，贴出来只是噪声
      if (v !== null && typeof v === 'object') {
        cell.text = text(relName(v));
        break;
      }
      const hit = v === null || v === undefined ? undefined : c.rel?.[String(v)];
      cell.text = hit !== undefined && hit !== '' ? hit : '-';
      break;
    }
    case 'map': {
      if (v === null || v === undefined || v === '') break;
      // 对象键按字符串存：数字字典与字符串字典（draft…）都命中这一支
      const hit = c.dict?.[String(v)];
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
