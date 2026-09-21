#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 详情条目语义自检 —— scripts/check-fe-detail-items.mjs
 *
 * 守一条不变量：**详情弹窗与列表同源，且永不出现裸雪花 ID**。
 *   - 状态字段在详情里必须是字典文案（「已审核」）而不是裸数字（`2`）；
 *   - 外键的三种解析途径（`*_name` 兄弟 / 关系对象 / `fields.source` 选项）任一命中即出名称；
 *   - 三条全落空时落「-」占位 —— 后端补 hashid 编码后原值是一串雪花编码，
 *     界面上没有任何可粘贴的去处，贴出来只是噪声；
 *   - `*_id` 的名称兄弟已成列时，裸外键不再单独出一行。
 *
 * 做法：跑 **Angular 端真身**（apps/angular/.../resource-page/columns.ts 的 inferColumns /
 * cellOf / inferDetailItems 是纯函数，可直接 import）；React 端对应实现是 .tsx（含 JSX，
 * Node 擦不掉类型也转不了 JSX），按本仓既有约定只做**静态接线检查**（同 check-fe-edit-seq.mjs）。
 *
 * 局限（如实记录）：跑的是纯函数真身，不起浏览器、不点弹窗 —— 它证明取数语义与两端接线形状，
 * 不证明页面上的真实表现（两端都没有 DOM 测试框架）。
 *
 * 用法：node scripts/check-fe-detail-items.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { fileURLToPath } from 'node:url';

// columns.ts 是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 与 scripts/check-fe-tree.mjs 同款，保证 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

// columns.ts 的相对导入按 CLI 习惯不带扩展名（`../../core/format`），Node 的 ESM 解析器不认。
// 就近补一层：xx → xx.ts、目录 → index.ts，只有本脚本受影响。
registerHooks({
  resolve(spec, ctx, next) {
    if (spec.startsWith('.') && !/\.[cm]?[jt]s$/.test(spec)) {
      for (const cand of [spec + '.ts', spec + '/index.ts']) {
        try {
          return next(cand, ctx);
        } catch {
          // 换下一个候选
        }
      }
    }
    return next(spec, ctx);
  },
});

let fails = 0;
const eq = (name, got, want) => {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: got ${JSON.stringify(got)}${ok ? '' : ` want ${JSON.stringify(want)}`}`);
};
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};

const NG = new URL('../apps/angular/src/app/pages/resource-page/columns.ts', import.meta.url).href;
const { cellOf, inferColumns, inferDetailItems, keyTitle } = await import(NG);

/* ── 夹具：采购订单列表行（后端 index 会 leftJoin supplier 带出 supplier_name） ── */

const ORDER = {
  id: 'HASH_ORDER',
  code: 'PO202609210001',
  supplier_id: 'HASH_SUPPLIER',
  supplier_name: '宁波某某供应商',
  total_amount: 1234.5,
  status: 2,
  created_at: '2026-09-21T14:18:43.000000Z',
};
const ORDER_COLS = inferColumns([ORDER], '/admin/v1/purchase/order', [], {}, 8);
const ORDER_ITEMS = inferDetailItems(ORDER, ORDER_COLS);

/** 条目按标题取值（详情只出 {k,v}，没有原始键） */
const itemByTitle = (items, title) => items.find((it) => it.k === title);

console.log('── A. 详情与列表同源 ──');

// status 列的字典来自资源前缀 purchase（inferColumns 的 STATUS_DICTS）
eq('状态出字典文案（不是裸数字 2）', itemByTitle(ORDER_ITEMS, '状态')?.v, '已审核');
eq('状态带徽标 tone（与列表同一判定）', itemByTitle(ORDER_ITEMS, '状态')?.tone, cellOf(ORDER_COLS.find((c) => c.key === 'status'), ORDER).tone);
ok(
  '状态条目不是裸数字',
  !ORDER_ITEMS.some((it) => it.v === '2'),
  JSON.stringify(ORDER_ITEMS),
);

// 状态字典优先取本资源声明的状态筛选（docStatus 生成的 options 就是字典）。
// erp_hr_leave 的枚举是 0 待审批 / 1 已批准 / 2 已驳回，而端点前缀 'hr' 不在 STATUS_DICTS 里：
// 不传 filter 就会落通用档，把 2 猜成「处理中」——错得比裸数字更隐蔽。
const LEAVE_FILTER = {
  key: 'status',
  label: '状态',
  options: [
    { label: '全部', value: null },
    { label: '待审批', value: 0 },
    { label: '已批准', value: 1 },
    { label: '已驳回', value: 2 },
  ],
};
const LEAVE = { id: 'HASH_L', days: 2, status: 2, created_at: '2026-09-21T14:18:43.000000Z' };
const leaveCols = inferColumns([LEAVE], '/admin/v1/hr/leave', [], {}, 8, LEAVE_FILTER);
eq('状态筛选即字典 → 2 出「已驳回」', cellOf(leaveCols.find((c) => c.key === 'status'), LEAVE).text, '已驳回');
eq('详情条目同步（不是通用档的「处理中」）', itemByTitle(inferDetailItems(LEAVE, leaveCols), '状态')?.v, '已驳回');
// 未声明状态筛选的资源退回前缀档/通用档，行为不变
eq('无 filter 时退回前缀档（purchase）', cellOf(ORDER_COLS.find((c) => c.key === 'status'), ORDER).text, '已审核');

// 每一条与同名列的 cellOf 输出逐字对齐（列表渲染什么，详情就渲染什么）
const drift = ORDER_ITEMS.filter((it) => {
  const col = ORDER_COLS.find((c) => c.title === it.k && c.key !== '');
  if (!col) return false;
  const cell = cellOf(col, ORDER);
  return cell.text !== it.v || (cell.tone ?? undefined) !== (it.tone ?? undefined);
});
ok('每条详情的值与同名列表列逐字一致', drift.length === 0, JSON.stringify(drift));

eq('金额仍走千分位', itemByTitle(ORDER_ITEMS, '金额')?.v, '1,234.50');
ok(
  '时间仍走本地时区格式化（不是原始 ISO）',
  !ORDER_ITEMS.some((it) => String(it.v).includes('T') && String(it.v).includes('Z')),
  JSON.stringify(ORDER_ITEMS.map((it) => it.v)),
);

console.log('── B. 永不出现裸雪花 ID ──');

const hasBareHash = (items) => items.some((it) => String(it.v).startsWith('HASH_'));
ok('详情无裸外键 hashid', !hasBareHash(ORDER_ITEMS), JSON.stringify(ORDER_ITEMS));
ok('详情无裸主键 id', !ORDER_ITEMS.some((it) => it.v === 'HASH_ORDER'));
ok('列表无裸外键 hashid', !ORDER_COLS.some((c) => cellOf(c, ORDER).text.startsWith('HASH_')));
ok(
  '名称兄弟已成列时裸外键不出行（supplier_id 被 supplier_name 顶掉）',
  ORDER_COLS.filter((c) => c.key === 'supplier_id').length === 0
    && !ORDER_ITEMS.some((it) => it.v === 'HASH_SUPPLIER'),
  JSON.stringify(ORDER_COLS.map((c) => c.key)),
);

// 兜底档：资源写了显式 columns，其中没有这个外键（如采购订单的 apply_id 既不在 columns 里，
// 行里也没有 apply_code 兄弟）——此时 FK 落「-」占位，绝不回落裸 hashid。
// 这条走的是 fallbackCell，不是 rel 列（后者由 inferColumns 的 rule 4 建，恒落占位）
const ORPHAN = { id: 'HASH_O', code: 'PO1', apply_id: 'HASH_APPLY', total_amount: 10 };
const EXPLICIT_COLS = [
  { key: 'code', title: '编号', kind: 'text' },
  { key: 'total_amount', title: '金额', kind: 'money' },
];
ok('夹具确认：显式 columns 里没有这个外键', !EXPLICIT_COLS.some((c) => c.key === 'apply_id'));
eq(
  '显式 columns 未覆盖的外键 → 详情落占位（不是裸 hashid）',
  itemByTitle(inferDetailItems(ORPHAN, EXPLICIT_COLS), keyTitle('apply_id'))?.v,
  '-',
);

console.log('── C. 外键三条解析途径 ──');

// 途径 2：`<base>` 关系对象（with 预加载）
const NESTED = { id: 'HASH_X', account_id: 'HASH_ACC', account: { id: 'HASH_ACC', name: '差旅费' } };
const nestedCols = inferColumns([NESTED], '/admin/v1/finance/expense', [], {}, 8);
eq('关系对象 → 出对象里的名称', itemByTitle(inferDetailItems(NESTED, nestedCols), '费用科目')?.v, '差旅费');

// 途径 2 的列表侧：`<base>` 关系对象在列表列里换成关系键（BOM 的 with product 走这条）
const BOMROW = {
  id: 'HASH_B',
  product_id: 'HASH_P',
  product: { id: 'HASH_P', name: '演示成品' },
  code: 'BOM-1',
  name: 'BOM',
  version: '1.0',
  status: 2,
  effective_date: '2026-09-22',
  created_at: '2026-09-22T00:00:00.000000Z',
};
const BOM_FILTER = {
  key: 'status',
  label: '状态',
  options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '已生效', value: 1 }, { label: '已失效', value: 2 }],
};
const bomCols = inferColumns([BOMROW], '/admin/v1/mfg/bom', [], {}, 8, BOM_FILTER);
eq('关系对象 → 列表列出产品名', cellOf(bomCols.find((c) => c.key === 'product'), BOMROW).text, '演示成品');
eq('状态筛选即字典 → 已失效不是通用档的文案', cellOf(bomCols.find((c) => c.key === 'status'), BOMROW).text, '已失效');

// 途径 3：cfg.fields 的 source + OptionSource 加载好的 id→名称映射
const SRC_FIELD = [{ key: 'account_id', label: '费用科目', source: { endpoint: '/admin/v1/finance/account' } }];
const ROW3 = { id: 'HASH_X', account_id: 'HASH_ACC' };
const hitCols = inferColumns([ROW3], '/admin/v1/finance/expense', SRC_FIELD, { '/admin/v1/finance/account': { HASH_ACC: '差旅费' } }, 8);
eq('source 映射命中 → 出名称', itemByTitle(inferDetailItems(ROW3, hitCols), '费用科目')?.v, '差旅费');

// 途径 1/2/3 全落空（选项未加载 / 该行未命中）→ 占位，而不是回落裸 hashid
const missCols = inferColumns([ROW3], '/admin/v1/finance/expense', SRC_FIELD, { '/admin/v1/finance/account': { HASH_OTHER: '办公费' } }, 8);
const missItems = inferDetailItems(ROW3, missCols);
eq('source 未命中 → 占位短横', itemByTitle(missItems, '费用科目')?.v, '-');
ok('列表同格也落占位', cellOf(missCols.find((c) => c.key === 'account_id'), ROW3).text === '-');

const bareCols = inferColumns([ROW3], '/admin/v1/finance/expense', [], {}, 8);
eq('无任何解析途径 → 占位短横', itemByTitle(inferDetailItems(ROW3, bareCols), '费用科目')?.v, '-');

console.log('── D. 内部字段不出条目 ──');

const WIRED = { id: 'HASH_X', __depth: 1, __path: [], name: '节点', children: [{ a: 1 }] };
const wiredItems = inferDetailItems(WIRED, inferColumns([WIRED], '/admin/v1/hr/department', [], {}, 8));
ok('id / __ 前缀 / 嵌套对象都不出行', wiredItems.length === 1 && wiredItems[0].v === '节点', JSON.stringify(wiredItems));

console.log('── E. React 端接线（静态） ──');

const REACT_PAGE = readFileSync(new URL('../apps/react/src/components/ResourcePage.tsx', import.meta.url), 'utf8');
ok(
  'ResourcePage 详情把列传给 inferDetailItems（详情与列表同源）',
  /inferDetailItems\(\s*detail\s*,\s*cols\s*\)/.test(REACT_PAGE),
  '未找到 inferDetailItems(detail, cols)',
);

const REACT_DEFAULTS = readFileSync(new URL('../apps/react/src/lib/defaults.tsx', import.meta.url), 'utf8');
ok(
  'relationValue 末支不再回落裸外键值',
  !/return\s+row\[idKey\]/.test(REACT_DEFAULTS),
  'relationValue 仍在 return row[idKey]',
);
ok(
  'React inferColumns 把 `*_id` 一律按关联列渲染',
  /if\s*\(\s*k\.endsWith\('_id'\)\s*\)\s*\{/.test(REACT_DEFAULTS),
  '未找到 `*_id` 分支',
);
ok(
  'React ResourcePage 把 cfg.filters 传进 inferColumns（状态字典与 Angular 同源）',
  /inferColumns\(\s*rows\s*,\s*cfg\.endpoint\s*,\s*cfg\.fields\s*,\s*\d+\s*,\s*cfg\.filters\s*\)/.test(REACT_PAGE),
  '未找到 inferColumns(..., cfg.filters)',
);

console.log(fails === 0 ? '\n全部通过' : `\n${fails} 例失败`);
process.exit(fails === 0 ? 0 : 1);
