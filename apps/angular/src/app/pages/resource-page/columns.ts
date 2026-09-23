/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { COLUMN_TITLES } from '../../config/column-titles';
import type { ColumnDef, DictMap, FieldSource, FilterDef, FormField, Row } from '../../config/types';
import { tr } from '../../core/i18n.service';
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

/**
 * 布尔开关列名：`enabled` 与 `is_*`（是否X）。install.sql 里这 17 列全是 TINYINT 0/1，
 * 但列注释常为空（`erp_finance_tax_rate.enabled` 就是），按注释识别不出来 —— 列名是唯一线索。
 */
const isBool = (k: string): boolean => k === 'enabled' || /^is_[a-z_]+$/.test(k);

/** 「是否X」的通用文案（DDL 里统一 0=否 1=是）；语义特异的表（is_read=未读/已读）由页面 dicts 覆盖 */
const BOOL_DICT: Record<number, string> = { 0: '否', 1: '是' };

/**
 * 筛选定义归一化：`undefined → []`、单个对象 `→ [f]`、数组原样（同引用）。
 * `cfg.filters` 是「单个或一组」的联合类型，引擎与门禁都从这里取统一形状。
 */
export function filterList(f?: FilterDef | FilterDef[]): FilterDef[] {
  if (!f) return [];
  return Array.isArray(f) ? f : [f];
}

/**
 * 筛选定义 → 状态字典。`docStatus()` 生成的 filter.options 就是字典本身（首项「全部」无值），
 * 所以声明了状态筛选的资源无需另写 columns，状态列也能拿到本表真枚举。
 */
function dictFromFilter(filter?: FilterDef): Record<number | string, string> | undefined {
  if (!filter || filter.key !== 'status') return undefined;
  const dict: Record<number | string, string> = {};
  // options 自 2026-09-23 起可选（source 型筛选无静态选项）：规则一字未改，只补空数组兜底
  for (const o of filter.options ?? []) if (typeof o.value === 'number') dict[o.value] = o.label;
  return Object.keys(dict).length > 0 ? dict : undefined;
}

/** 键名 → 列标题：字段 label 优先，命中词典用中文，否则回落到对端词条、最后驼峰化（与 React keyTitle 同源） */
export function keyTitle(k: string, label?: string): string {
  const zh = label ?? COLUMN_TITLES[k];
  // `*_name` / `*_id` 未收录时回落到对端条目（如 supplier_name 取 supplier_id 的「供应商」）。
  // React 侧一直有这条，Angular 侧缺了 —— 同一份回包在两端一个出中文、一个出 supplierName
  if (!zh) {
    const affix = /^(.+)_(name|id)$/.exec(k);
    if (affix) {
      const base = affix[1];
      const other = COLUMN_TITLES[`${base}_${affix[2] === 'name' ? 'id' : 'name'}`] ?? COLUMN_TITLES[base];
      if (other) return other;
    }
  }
  return zh ?? k.replace(/_([a-z])/g, (_m: string, c: string) => c.toUpperCase());
}

/** 关联 id → 名称映射表：endpoint → (id 字符串 → 名称)；由 OptionSource 加载后交给推断 */
export type RelLabels = Record<string, Record<string, string>>;

/** 非规范别名：`*_id` → 行内实际承载名称的字段（可扩充；未列出的走 `<base>_name`） */
const NAME_ALIAS: Record<string, string> = {
  partner_id: 'party_name',
  // 费用报销/采购申请 → 申请人姓名（ExpenseController::index:83、ApplyController::index:57 起行内补 apply_user_name）。
  // 原值 employee_name 全仓无产出方（install.sql 无此列，唯一出现处是 BankPayrollService 读取的入参数组），
  // 别名指过去等于没指 —— 详情抽屉里「申请人」还会多出一行「-」（兄弟键没被认作名称，裸外键不跳过）
  apply_user_id: 'apply_user_name',
  stage_id: 'stage_name',
  // 采购订单 → 采购申请单号（OrderController::index leftJoin purchase_apply 带出的 apply_code）
  apply_id: 'apply_code',
  // 采购/销售结算 → 收货单号、发货单号（SettlementController::format 按 source_id 反查带出）
  receive_id: 'receive_code',
  delivery_id: 'delivery_code',
  // 询价单比价面板 → 采购员姓名（RfqController::compare；buyer_name 键属税票的购买方名称，不能复用）
  buyer_id: 'buyer_real_name',
  // 来料检验 → 收货单号（IncomingCheckController::index leftJoin purchase_receive 带出的 receiving_code）
  receiving_id: 'receiving_code',
  // 单号 alias 全仓统一叫 order_code（无一处产出 order_name）：purchase/ReceiveController:79、
  // sales/DeliveryController:81、oms/RmaController:59 走 leftJoin as order_code；
  // manufacturing 的 WorkReport:98 / MaterialIssue:87 / CostEntry:88 走行内反查。
  // 缺这条时默认找 order_name（不存在）→ 6 个页面的单号列全落「-」
  order_id: 'order_code',
  // 询价单明细 → 询价单号（RfqQuoteController::index:63 行内补 rfq_no；全仓无 rfq_name）
  rfq_id: 'rfq_no',
  // 过程检验 → 工单编码（ProcessCheckController::index leftJoin mfg_production_order 带出的 production_order_code）
  production_order_id: 'production_order_code',
  // 项目/部门负责人 → 姓名（ProjectController::index:95、DepartmentController::index:261 行内补 manager_name；
  // 两页都没写显式 columns，缺别名时多出一列「负责人 -」，与真正的姓名列同名并存）
  manager_user_id: 'manager_name',
  // 运单 → 运单号（tms/FreightInvoiceController::index、tms/TrackingController:68 行内补 shipment_code；
  // 运单无 name 列，默认兄弟 shipment_name 不存在）
  shipment_id: 'shipment_code',
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
 * 行内「外键键」判别 —— 不止 `_id` 后缀。DDL 里 `_by` 全是 actor 外键
 * （install.sql: created_by×6、approved_by×3、reported_by、changed_by、audited_by），
 * `assigned_to` 是用户外键；同后缀另有 `valid_to`（DATE，install.sql:3858），
 * 故 `_to` 按值形状区分：编码后的外键 ID 是纯数字/数字串，日期串不是。
 */
function isRelKey(k: string, v: unknown): boolean {
  if (k === 'id' || k.startsWith('__')) return false;
  if (k.endsWith('_id') || k.endsWith('_by')) return true;
  return k.endsWith('_to') && /^\d+$/.test(String(v ?? ''));
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
 * @param filter 本资源的筛选（单个或一组，见 filterList）——其中 key 为 status 的那条的选项即状态字典
 * @param dicts cfg.dicts —— 逐键值字典，优先于状态筛选（见 types.ts）
 */
export function inferColumns(
  rows: Row[],
  // 位置参数保留：调用点与 React defaults.tsx 同序（ResourcePage 传 cfg.endpoint）。
  // 2026-09-22 删掉「按 endpoint 第 4 段猜前缀档」后本函数不再读它，加下划线避开 noUnusedParameters
  _endpoint: string,
  fields: FormField[] = [],
  labels: RelLabels = {},
  limit = 8,
  filter?: FilterDef | FilterDef[],
  dicts?: DictMap,
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

  // 字典只有一个来源：本资源状态筛选带的（`docStatus()`/`ST_FILTER` 的 options 就是该表枚举的真身）。
  // 2026-09-22 删掉「按 endpoint 第 4 段猜前缀档」与 `COMMON_STATUS` 通用档：猜错比裸值更隐蔽
  // （erp_purchase_receive 的 2=已收货曾被猜成「已审核」），未命中一律原值直出（见 format.statusText）
  //
  // 多筛选（数组）下只认 key==='status' 的那条，其余筛选（集团/年份/月份）天然不参与字典；
  // 带 `options` 才可能给字典 —— 只有 source 的 status 筛选没有静态枚举可取（dictFromFilter 按 options 取值）
  const dict = dictFromFilter(filterList(filter).find((f) => f.key === 'status' && f.options));

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
    // 显式逐键字典优先于按字段名的识别：status 键仍走 status 支（保住徽标与色带），
    // 只把字典换成 cfg 里的真枚举；其余枚举键（type/priority/is_lowest…）走 map 支出文案
    const kd = dicts?.[k];
    if (isStatus(k)) cols.push({ ...base, kind: 'status', dict: kd ?? dict });
    else if (kd) cols.push({ ...base, kind: 'map', dict: kd });
    // 布尔开关：enabled 走启用/禁用徽标（与 cells.enabledCol 同文案），is_* 走 否/是。
    // 必须排在 isMoney/isInt 之前 —— is_taxable 命中 isMoney 的 `tax`、is_managed 命中 isInt 的 `age`
    else if (k === 'enabled') cols.push({ ...base, kind: 'enabled' });
    else if (isBool(k)) cols.push({ ...base, kind: 'map', dict: BOOL_DICT });
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
  dicts?: DictMap,
  fields?: FormField[],
): { k: string; v: string; tone?: BadgeTone; tags?: string[] }[] {
  const byKey = new Map(cols.map((c) => [c.key, c]));
  // 本页字段声明的措辞：列数被 limit 截掉、或本页写了显式 columns 的键，抽屉里没有列标题可用，
  // 只能落到全局 COLUMN_TITLES —— 于是「同一个键在不同页语义不同」时必错（order_id 全局「生产工单」，
  // 但 /oms/rma 是「关联订单」）。这里把本页 fields 的 label 插在 keyTitle 之前兜底
  const fieldLabel = new Map((fields ?? []).map((f) => [f.key, f.label]));
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
    // 裸外键：名称兄弟（rule 1/别名）或关系对象列（rule 2）已经在别处承担了名称，这里不再出行。
    // 兄弟既可能是已声明的列，也可能是行里的标量键（抽屉会把它作为自己那行渲染出来，否则
    // 同一份名称会「兄弟一行 + 外键一行」重复出两次）
    if (isRelKey(k, v)) {
      const stem = k.slice(0, -'_id'.length);
      const nameKey = NAME_ALIAS[k] ?? `${stem}_name`;
      const sv = row[nameKey];
      const siblingScalar = sv !== null && sv !== undefined && sv !== '' && typeof sv !== 'object';
      if (byKey.has(nameKey) || byKey.has(stem) || siblingScalar) continue;
    }
    const cell = col ? cellOf(col, row) : fallbackCell(k, v, dicts, row);
    items.push({
      k: col?.title ?? fieldLabel.get(k) ?? keyTitle(k),
      v: cell.text,
      tone: cell.tone,
      tags: cell.tags,
    });
  }
  return items;
}

/** cols 未覆盖的键的兜底取数，识别口径与 inferColumns 一致 */
function fallbackCell(k: string, v: unknown, dicts?: DictMap, row?: Row): Cell {
  // 逐键字典先于字段名识别：列数被 limit 截掉的枚举键（第 9 列起的 type/priority…）
  // 只能走这条兜底，没有字典就在这里裸出 0/1
  const kd = dicts?.[k];
  if (kd) return { text: mapText(v, kd) };
  // 布尔开关：与 inferColumns 同口径（列数被 limit 截掉的开关键只能走这条兜底）
  if (k === 'enabled') return { text: yesNo(v), tone: Number(v) === 0 ? 'd' : 's' };
  if (isBool(k)) return { text: mapText(v, BOOL_DICT) };
  // 裸外键：`_id`/`_by`/`assigned_to` 这类键存的都是编码后的 ID（旧口径只认 `_id`，
  // 于是 approved_by 被当普通文本贴出来，正是用户报的「详情页显示 ID 值」）。
  // 行里有名称兄弟（含 NAME_ALIAS 别名）就出名称，取不到才落占位——裸 ID 贴出来只是噪声
  if (isRelKey(k, v)) {
    return { text: text(row ? inlineRelName(row, k, k.slice(0, -3)) || undefined : undefined) };
  }
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
function cellText(
  k: string,
  v: unknown,
  row?: Row,
  dicts?: DictMap,
): string {
  if (isPlainObject(v) || Array.isArray(v)) return JSON.stringify(v) ?? '';
  // 外键：同行有 `<base>_name`（含别名）或 `<base>` 关系对象时出名称，两条都落空落「-」。
  // 动作/报表回包里的编码 id 贴到面板上既是英文键又是裸 hashid，没有任何可粘贴的去处
  if (row && k.endsWith('_id')) return text(inlineRelName(row, k, k.slice(0, -3)));
  // 逐键字典（cfg.dicts）与列/详情同源：比价面板的 status/is_lowest 是裸 0/1 时在此收口
  const kd = dicts?.[k];
  if (kd) return mapText(v, kd);
  return isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v);
}

/**
 * 任意 JSON → 渲染分块（动作结果弹窗与报表对象页共用）：
 * 对象 → 键值表 + 值里的对象/数组递归成嵌套块；对象数组 → 表格（列取各行键并集，按首现序）；
 * 标量数组 → 顿号连接的一行。
 *
 * @param dicts cfg.dicts —— 回包里的键与本资源同名的枚举（比价面板的 status/is_lowest）出文案
 */
export function resultBlocks(
  data: unknown,
  title = '',
  dicts?: DictMap,
): ResultBlock[] {
  const out: ResultBlock[] = [];
  walkResult(data, title, 0, out, dicts);
  return out;
}

function walkResult(
  v: unknown,
  title: string,
  depth: number,
  out: ResultBlock[],
  dicts?: DictMap,
): void {
  // 超过夹顶深度不再递归，但也不静默丢数据：降级成一行 JSON
  if (depth > RESULT_MAX_DEPTH) {
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: cellText('', v, undefined, dicts) }] });
    return;
  }
  if (Array.isArray(v)) {
    const rows = v.filter(isPlainObject);
    if (rows.length && rows.length === v.length) {
      // 列键取各行键并集（按首现序，跳过 id）：表头出标题（keyTitle），
      // 单元格按同一 key 序取数 —— 二者必须用同一份 keys 才对齐
      const keys: string[] = [];
      for (const r of rows) for (const k of Object.keys(r)) if (k !== 'id' && !keys.includes(k)) keys.push(k);
      out.push({
        title,
        depth,
        head: keys.map((k) => keyTitle(k)),
        cells: rows.slice(0, RESULT_MAX_ROWS).map((r) => keys.map((k) => cellText(k, r[k], r, dicts))),
        kv: [],
      });
      return;
    }
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: v.map((x) => cellText('', x, undefined, dicts)).join('、') }] });
    return;
  }
  if (!isPlainObject(v)) {
    if (title) out.push({ title, depth, head: [], cells: [], kv: [{ k: title, v: cellText('', v, undefined, dicts) }] });
    return;
  }
  const kv: { k: string; v: string }[] = [];
  const nested: [unknown, string][] = [];
  for (const [k, val] of Object.entries(v)) {
    if (k === 'id' || k.startsWith('__')) continue;
    // 嵌套块的分块标题就是它那一行的键（items/rfq/quotes），模板只做 `| tr` —— 以中文为键，
    // 原样传下去就是英文键上屏；与 kv 同一口径先过 keyTitle
    if (isPlainObject(val) || Array.isArray(val)) nested.push([val, keyTitle(k)]);
    else kv.push({ k: keyTitle(k), v: cellText(k, val, v, dicts) });
  }
  if (kv.length || !nested.length) out.push({ title, depth, head: [], cells: [], kv });
  for (const [val, k] of nested) walkResult(val, k, depth + 1, out, dicts);
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

/**
 * 值 → 字典文案：命中过 `tr`（词典词条要出当前语种），表外值原样直出
 * （真实数据优先，与 HarmonyOS 同口径）。空值给空串，由调用方决定占位。
 * 与 React `config/cells.tsx` 的 mapText 逐字同义。
 */
function mapText(v: unknown, dict: Record<number | string, string>): string {
  if (v === null || v === undefined || v === '') return '';
  const hit = dict[String(v)];
  return hit === undefined ? String(v) : tr(hit);
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
    case 'map':
      cell.text = mapText(v, c.dict ?? {});
      break;
    default:
      // 裸列：直出（text 类走 '-' 兜底，见 format.text）。
      // 外键键没有 kind（配置里手写的 `{ key: 'level_id', title: '等级' }`）时也落「-」占位：
      // 直出的是 encodeIds 后的 hashid，界面上没有可粘贴的去处 —— 与 React DataTable 的
      // `c.key.endsWith('_id') ? fkText(...)` 兜底同口径（rule 4）
      cell.text = c.key !== 'id' && c.key.endsWith('_id') ? '-' : String(v ?? '');
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
