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
 * 「枚举/计数值不上屏」另有一支：scripts/check-fe-enum-text.mjs（我的审批 `target_type`、
 * withCount 计数列标题、`awarded` 0/1）—— 同源不变量不同，拆开各自守。
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
import { readFileSync, readdirSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { join } from 'node:path';
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
const { cellOf, filterList, inferColumns, inferDetailItems, keyTitle, resultBlocks } = await import(NG);

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

// 这条 fixture 没有状态筛选 ⇒ 没有字典。2026-09-22 起「按 endpoint 段猜前缀档」已删
// （STATUS_DICTS 的 purchase/sales/crm 三档 + COMMON_STATUS 通用档 + `状态N`）：猜错比裸值更隐蔽
// （erp_purchase_receive 的 2=已收货曾被猜成「已审核」；erp_hr_employee 的 0 曾被猜成「待处理」）。
// 契约改为**原值直出**（真实数据优先），状态文案的正面契约由下面 LEAVE_FILTER（筛选即字典）那组守。
eq('无状态筛选 → 原值直出（不猜 purchase 前缀档的「已审核」）', itemByTitle(ORDER_ITEMS, '状态')?.v, '2');
eq('状态带徽标 tone（与列表同一判定）', itemByTitle(ORDER_ITEMS, '状态')?.tone, cellOf(ORDER_COLS.find((c) => c.key === 'status'), ORDER).tone);
ok(
  '状态未命中不编造文案（不造「状态2」，也不出前缀档/通用档的「已审核」「处理中」）',
  !ORDER_ITEMS.some(
    (it) => it.v === '已审核' || it.v === '处理中' || /^状态\s*\d+$/.test(String(it.v)),
  ),
  JSON.stringify(ORDER_ITEMS),
);

// 状态字典优先取本资源声明的状态筛选（docStatus/ST_FILTER 生成的 options 就是字典）。
// erp_hr_leave 的枚举是 0 待审批 / 1 已批准 / 2 已驳回。
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
// 无 filter 且无 cfg.dicts ⇒ 无字典 ⇒ 原值直出（负控：把前缀档加回 purchase 段，这条立刻变红）
eq('无 filter 时原值直出（不再按 endpoint 段猜枚举）', cellOf(ORDER_COLS.find((c) => c.key === 'status'), ORDER).text, '2');
// 字典的另一个来源 cfg.dicts 仍然生效 —— 真枚举（receiving 的 2=已收货）能出文案
const orderDictCols = inferColumns([ORDER], '/admin/v1/purchase/order', [], {}, 8, undefined, { status: { 2: '已收货' } });
eq('cfg.dicts 提供真枚举 → 2 出「已收货」', cellOf(orderDictCols.find((c) => c.key === 'status'), ORDER).text, '已收货');

// 带 `source`（远程选项）的筛选**不得参与字典**：dictFromFilter 只认 key==='status' **且有静态 options** 的那条；只有 source 的 status 无枚举可当字典，不能顶掉有 options 的那条。
const SRC_ONLY = { key: 'status', label: '状态', source: { endpoint: '/admin/v1/meta/status/list' } };
const statusColOf = (filters) => inferColumns([LEAVE], '/admin/v1/hr/leave', [], {}, 8, filters).find((c) => c.key === 'status');
eq('只有 source、无静态 options 的 status 筛选 → 不参与字典（原值直出）', cellOf(statusColOf(SRC_ONLY), LEAVE).text, '2');
eq('数组形 [source 那条, options 那条] → 取有 options 的', cellOf(statusColOf([SRC_ONLY, LEAVE_FILTER]), LEAVE).text, '已驳回');
eq('数组形 [options 那条, source 那条] → 同上（与顺序无关）', cellOf(statusColOf([LEAVE_FILTER, SRC_ONLY]), LEAVE).text, '已驳回');

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

// 显式 columns 里手写的「裸对象列」（配置写 `{ key: 'level_id', title: '等级' }`，没有 kind）：
// 以前落 cellOf 的 default 支直出 row[k]（encodeIds 后的 hashid），列上就出现一串雪花编码。
// 守 default 支的 `*_id` 兜底 —— 与 React DataTable 的 fkText 兜底同口径（客户等级 是实测漏点）
eq(
  '裸对象外键列（无 kind）落占位（不是裸 hashid）',
  cellOf({ key: 'level_id', title: '等级' }, { level_id: 'HASH_LEVEL' }).text,
  '-',
);
eq(
  '主键 id 列不受兜底影响（仍直出）',
  cellOf({ key: 'id', title: 'ID' }, { id: 'HASH_ORDER' }).text,
  'HASH_ORDER',
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
// 尾参不写死（`[,)]`）：本脚本守的是「detail/cols 有没有传」，后面再接 cfg.dicts 之类
// 的追加参数不该判红；完整实参表由 scripts/check-fe-enum-text.mjs:364 钉住。
ok(
  'ResourcePage 详情把列传给 inferDetailItems（详情与列表同源）',
  /inferDetailItems\(\s*detail\s*,\s*cols\s*[,)]/.test(REACT_PAGE),
  '未找到 inferDetailItems(detail, cols, …)',
);

const REACT_DEFAULTS = readFileSync(new URL('../apps/react/src/lib/defaults.tsx', import.meta.url), 'utf8');
ok(
  'relationValue 末支不再回落裸外键值',
  !/return\s+row\[idKey\]/.test(REACT_DEFAULTS),
  'relationValue 仍在 return row[idKey]',
);
// B2 的 React 面：删掉的「按 endpoint 第 4 段猜前缀档」（purchase/sales/crm 通用档）没有行为面
// 门禁能守 —— defaults.tsx 含 JSX，Node 起不来真身，只能钉源码形状。
// 负控：把前缀档加回 purchase 段（A 段那条 Angular 断言随即变红），这条同时变红。
ok(
  'React 引擎不再按 endpoint 段猜前缀档（STATUS_DICTS）',
  !/STATUS_DICTS|split\('\/'\)\[3\]/.test(REACT_DEFAULTS),
  'defaults.tsx 仍在按 endpoint 段取前缀档',
);
ok(
  'React inferColumns 把 `*_id` 一律按关联列渲染',
  /if\s*\(\s*k\.endsWith\('_id'\)\s*\)\s*\{/.test(REACT_DEFAULTS),
  '未找到 `*_id` 分支',
);
ok(
  'React ResourcePage 把 cfg.filters 传进 inferColumns（状态字典与 Angular 同源）',
  /inferColumns\(\s*rows\s*,\s*cfg\.endpoint\s*,\s*cfg\.fields\s*,\s*\d+\s*,\s*cfg\.filters\s*[,)]/.test(REACT_PAGE),
  '未找到 inferColumns(..., cfg.filters, …)',
);

console.log('── F. 动作/报表回包（resultBlocks 真身） ──');

/* 比价面板夹具：询价单头 + 行对比矩阵 + 报价表（RfqController::compare 的回包形状） */
const COMPARE = {
  rfq: { id: 'HASH_RFQ', code: 'RFQ1', buyer_id: 'HASH_BUYER', buyer_real_name: '张三', status: 1 },
  target_total: '300.00',
  lowest_quote_id: 'HASH_LQ',
  items: [
    {
      rfq_item_id: 'HASH_ITEM',
      product_id: 'HASH_P',
      product_code: 'P-0001',
      product_name: '演示成品',
      quantity: '10',
      unit: '个',
      target_price: '10',
      target_amount: '100.00',
    },
  ],
  quotes: [{ amount: '95.00', supplier_name: '宁波某某供应商', is_lowest: 1 }],
};
const blocks = resultBlocks(COMPARE);
const titles = blocks.map((b) => b.title);
const blockBy = (t) => blocks.find((b) => b.title === t);
const headOf = (t) => blockBy(t)?.head ?? [];

ok('回包不出现裸外键/主键 hashid', !JSON.stringify(blocks).includes('HASH_'), JSON.stringify(blocks));
ok(
  '嵌套块标题过 keyTitle（items/rfq/quotes 不带英文键上屏）',
  ['明细', '询价单', '报价'].every((t) => titles.includes(t)),
  titles.join(' | '),
);
ok(
  '矩阵列标题命中标题表（目标金额/商品编码）',
  headOf('明细').includes('目标金额') && headOf('明细').includes('商品编码'),
  JSON.stringify(headOf('明细')),
);
ok('报价表列标题命中标题表（供应商/最低价）', headOf('报价').includes('供应商') && headOf('报价').includes('最低价'), JSON.stringify(headOf('报价')));
// 别名表：buyer_id → buyer_real_name（buyer_name 键属税票的购买方名称，撞名会出「购买方名称 → 张三」）
eq('询价单头：采购员出 buyer_real_name（别名）', blockBy('询价单')?.kv.find((x) => x.k === '采购员ID')?.v, '张三');
// 无名称兄弟键的外键（lowest_quote_id）：按契约落占位，不回落裸 hashid
eq('无兄弟键的外键落占位', blocks[0].kv.find((x) => x.k === '最低价报价ID')?.v, '-');

console.log('── G. 两端别名表一致 + React 接线（静态） ──');

const aliasOf = (src, name) => {
  const body = src.slice(src.indexOf(name + ':'), src.indexOf('};', src.indexOf(name + ':')));
  return Object.fromEntries([...body.matchAll(/^\s*([a-z0-9_]+):\s*'([a-z0-9_]+)',/gm)].map((m) => [m[1], m[2]]));
};
const REL = aliasOf(readFileSync(new URL(NG), 'utf8'), 'NAME_ALIAS');
const REACT_REL = readFileSync(new URL('../apps/react/src/lib/relation.ts', import.meta.url), 'utf8');
ok(
  '两端别名表逐键逐值相同（Angular NAME_ALIAS = React REL_ALIAS）',
  JSON.stringify(aliasOf(REACT_REL, 'REL_ALIAS')) === JSON.stringify(REL),
  `react=${JSON.stringify(aliasOf(REACT_REL, 'REL_ALIAS'))} ng=${JSON.stringify(REL)}`,
);

const REACT_DT = readFileSync(new URL('../apps/react/src/components/DataTable.tsx', import.meta.url), 'utf8');
ok(
  'DataTable 的 `*_id` 兜底走 fkText（不是 String(take(...))）',
  /key\.endsWith\('_id'\)[\s\S]{0,80}fkText\(row, c\.key\)/.test(REACT_DT),
  '未找到 fkText(row, c.key) 兜底',
);
const REACT_FF = readFileSync(new URL('../apps/react/src/components/FormFields.tsx', import.meta.url), 'utf8');
ok('ResultView 两处（数组列 / 对象行）都走 fkText', (REACT_FF.match(/fkText\(/g) ?? []).length >= 2, 'ResultView 里 fkText 调用不足 2 处');

// `filterList` 两端同语义（undefined→[]、单对象→[f]、数组→原样）：Angular 真身 import；React 的 .tsx Node 起不来 ⇒ 抽其**函数体**交给 Function 执行，不是抄一份规则。
const FL_SRC = /export function filterList\([^)]*\)[^{]*\{([\s\S]*?)\n\}/.exec(REACT_DEFAULTS)?.[1];
let reactFL = null; try { if (FL_SRC) reactFL = new Function('f', FL_SRC); } catch { /* 抽不出可执行体 ⇒ 下面判红 */ }
const FL_CASES = [[undefined], [LEAVE_FILTER], [[SRC_ONLY, LEAVE_FILTER]]];
ok('React filterList 可抽成可执行体（静默跳过=这条断言不存在）', !!reactFL, '未找到函数体或含 Node 执行不了的语法');
eq('两端 filterList 三输入同行为（undefined→[]、单对象→[f]、数组→全量且保序）', reactFL ? FL_CASES.map((c) => JSON.stringify(reactFL(...c))) : null,
  FL_CASES.map((c) => JSON.stringify(filterList(...c))));

// 两端 status 字典的**取值谓词**必须同形且带 `&& f.options` 守卫：A 组验的是 Angular 真身行为，React 少这个守卫时
// A 组照绿（真引擎实测顺序相关：`[source, options]` 同键两条时 Angular 出字典、React 裸数字码）。
const dpOf = (s) => /dictFromFilter\(filterList\(\w+\)\.find\(\((?:\w+)\) => ([^)]*)\)\)/.exec(s.replace(/\s+/g, ' ').replaceAll('"', "'"))?.[1]?.replace(/\b\w+\./g, 'F.');
eq('两端 status 字典取值谓词同形且都带 options 守卫（React 缺守卫时这条红，两端同错也红）', [dpOf(REACT_DEFAULTS), dpOf(readFileSync(new URL(NG), 'utf8'))], ["F.key === 'status' && F.options", "F.key === 'status' && F.options"]);

console.log('── H. 移动端：详情行/导航标题不落裸外键（静态） ──');

// HarmonyOS（ArkTS）与 Flutter（Dart）在本机没有 DOM/运行时可跑，按本仓约定做静态接线检查
// （同 E/G 组的 React 端）。守的是同一类缺陷：外键原值（encodeIds 后的 hashid 串、或未编码的
// 明文 int）被当作字段值上屏 —— 「详情页显示ID值」的移动端形态。
const collect = (dir, ext, out) => {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) collect(p, ext, out);
    else if (e.name.endsWith(ext)) out.push(p);
  }
};
const ETS = [];
const DART = [];
collect(fileURLToPath(new URL('../apps/harmonyos/entry/src/main/ets', import.meta.url)), '.ets', ETS);
collect(fileURLToPath(new URL('../apps/flutter/lib', import.meta.url)), '.dart', DART);

/** 逐行匹配，命中即报（含文件:行号，便于直接改） */
const hitLines = (files, re) =>
  files.flatMap((f) =>
    readFileSync(f, 'utf8')
      .split('\n')
      .map((line, i) => ({ f: f.slice(f.indexOf('/apps/') + 1), n: i + 1, line: line.trim() }))
      .filter((x) => re.test(x.line)),
  );

// 详情行：外键只出名称兄弟键/关系对象（后端 leftJoin 带出），没有就落「-」占位
const detailHits = hitLines(ETS, /DetailRow\(\{[^}]*\[['"][a-z_]+_id['"]\]/).concat(
  hitLines(DART, /(detailRow|DetailRow)\(.*\[['"][a-z_]+_id['"]\]/),
);
ok('详情行不把外键当字段值（ArkTS + Dart）', detailHits.length === 0, JSON.stringify(detailHits));

// 导航标题：原来把单据ID当 title 传给详情页，AppBar 直接印出纯数字 ID
const titleHits = hitLines(ETS, /(title|subtitle): *[^,]*\[['"][a-z_]+_id['"]\]/).concat(
  hitLines(DART, /'title':.*\[['"][a-z_]+_id['"]\]/),
);
ok('行标题/导航标题不从外键取值（回落文案标题）', titleHits.length === 0, JSON.stringify(titleHits));

// 辅助函数体内的变量形态：`value: id` / `name.isEmpty ? id : name`。
// 上面两条判别子只认字面量 `['xxx_id']`，helper 里传进来的是变量（如
// FulfillmentDetailPage._optionalRefRow 的 `value: id`）—— 归零后由本条守住。
const fallbackHits = hitLines(DART, /value: *id\b|\.isEmpty *\? *id\b/);
ok('详情行辅助函数不回落裸 id（Dart 变量形态）', fallbackHits.length === 0, JSON.stringify(fallbackHits));

// 列表单元格里的 `名称 ?? 外键 id` 兜底：名称取不到时贴出的是 encodeIds 后的 hashid
// （销售/采购/报价/CRM 列表的客户列、费用科目、仓库列等 9 处，2026-09-22 归零）
const idFallbackRe = /\?\? *[A-Za-z_][A-Za-z0-9_]*\[['"][a-z_]+_id['"]\]/;
const idFallbackHits = hitLines(DART, idFallbackRe).concat(hitLines(ETS, idFallbackRe));
ok('列表单元格不回落裸外键（?? 取 *_id）', idFallbackHits.length === 0, JSON.stringify(idFallbackHits));

// 行标题/副行直接拼外键属性（ArkTS 形态：`subtitle: ... + String(item.warehouse_id ?? '-')`）。
// 判别子用 `.*`（不能用 `[^,]*`：L10n.str(ctx, 'key', …) 的逗号在属性之前会截断匹配 —— 窄判别子
// 会静默漏报，2026-09-22 负控时踩过）。Dart 侧的等价形态（`['xxx_id']` 下标）由上面两条覆盖
const attrHits = hitLines(ETS, /(title|subtitle):.*\b[A-Za-z_][A-Za-z0-9_]*\.[a-z_]+_id\b/);
ok('行标题/副行不拼外键属性（ArkTS）', attrHits.length === 0, JSON.stringify(attrHits));

console.log('── I. 全页面扫：外键裸值 / 标签落原始键（140 页真配置 × 真引擎） ──');

/* 上面各节是夹具驱动（点状），这一节是面状：把 8 个域的**真配置**逐页取出来，按生产口径
 * （`cfg.columns ?? inferColumns(...)`）渲染抽屉，喂一行「外键＝编码后 ID」形状的合成行，断言两件事：
 *   ① 任何 `*_id`/`*_by`/数字 `*_to` 键的原值都不得作为值上屏（无名称兄弟时落「-」占位）；
 *   ② 标签不得回落成原始键（含驼峰：`account_name` → `accountName`）—— 用户报的「中文语言下还是英文」。
 * 合成行会造出集合键的假行（ng-titles 的探针陷阱①），但假行的标签走标题表、值不是外键形状，
 * 两条断言都不受影响；真出现即为「显式声明 columns 的页绕过了兜底」这类真缺陷。 */
const DOMAINS = ['trade', 'goods', 'fulfill', 'mfg', 'finance', 'crm', 'mgmt', 'system'];
const allPages = [];
for (const d of DOMAINS) {
  const menus = (await import(new URL(`../apps/angular/src/app/config/domains/${d}.ts`, import.meta.url).href))[
    `${d}Menus`
  ];
  const walk = (list) =>
    list.forEach((x) => {
      if (x.path && x.cfg) allPages.push(x);
      if (x.children) walk(x.children);
    });
  walk(menus);
}
const fkShape = (k, v) =>
  k !== 'id' && !k.startsWith('__') && (k.endsWith('_id') || k.endsWith('_by') || (k.endsWith('_to') && /^\d+$/.test(String(v ?? ''))));
const sweepLeaks = [];
let sentinelCount = 0;
// 只喂配置声明的键是不够的：`approved_by`/`assigned_to` 这类外键**从不出现在 cfg.columns/fields 里**，
// 只在后端行里 —— 上一版扫器因此对 `_by` 类完全失明（变异控制：把 isRelKey 退回只认 `_id`，扫器照样全绿）。
// 故从 DDL 取**全部数值型 FK 列名**做超集喂入（页面用不到也无害；真出现裸值即是一处活体）。
const fkCols = [
  ...new Set(
    [...readFileSync(new URL('../database/install.sql', import.meta.url), 'utf8').matchAll(
      /^\s+`([a-z_]+)`\s+(?:BIGINT|INT|SMALLINT)\b/gm,
    )]
      .map((m) => m[1])
      .filter((k) => k.endsWith('_id') || k.endsWith('_by') || k.endsWith('_to')),
  ),
];
for (const m of allPages) {
  const keys = new Set([
    ...Object.keys(m.cfg.dicts ?? {}),
    ...(m.cfg.columns ?? []).map((c) => c.key),
    ...(m.cfg.fields ?? []).map((f) => f.key),
    // `[cfg.filters]` 在数组形状下每项是**数组**、`f.key` 得 undefined ⇒ 下面 `k.endsWith` 崩（2026-09-23 本门禁 rc=1 的原因）。
    ...filterList(m.cfg.filters).map((f) => f.key),
  ]);
  const row = { id: 'H', code: 'X-1' };
  const sentinel = new Map();
  let i = 0;
  for (const k of [...keys, ...fkCols]) {
    if (k === 'id' || k in row) continue;
    const fkish = k.endsWith('_id') || k.endsWith('_by') || k.endsWith('_to');
    const raw = fkish ? `41000000000000${String(i).padStart(2, '0')}` : `值${i}`;
    row[k] = raw;
    if (fkShape(k, raw)) sentinel.set(k, raw);
    i++;
  }
  const cols = m.cfg.columns ?? inferColumns([row], m.cfg.endpoint, m.cfg.fields, {}, 8, m.cfg.filters, m.cfg.dicts);
  const items = inferDetailItems(row, cols, m.cfg.dicts, m.cfg.fields);
  const vals = items.map((x) => String(x.v));
  const labels = items.map((x) => String(x.k));
  for (const [k, raw] of sentinel) {
    sentinelCount++;
    if (vals.includes(raw)) sweepLeaks.push(`${m.path} ${k} 裸值上屏`);
  }
  for (const k of keys) {
    // 集合键是合成行的假行走廊（`role_ids` 在 erp_admin_user 里根本没有这列，角色走 with('roles')；
    // `items` 由 index 的 with 决定），标签断言对它们无意义 —— 2026-09-22 变异控制时正是它造出唯一假阳
    if (k === 'id' || k.endsWith('_ids') || ['items', 'roles', 'permissions', 'children'].includes(k)) continue;
    const camel = k.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
    if (labels.includes(k) || labels.includes(camel)) {
      sweepLeaks.push(`${m.path} 标签落原始键 ${k}`);
    }
  }
}
ok(
  `外键裸值 / 原始键标签全页扫：${allPages.length} 页 / ${sentinelCount} 个外键哨兵 / ${allPages.reduce((n, m) => n + (m.cfg.columns?.length ?? m.cfg.fields?.length ?? 0), 0)} 个页面键`,
  sweepLeaks.length === 0 && sentinelCount > 0,
  sweepLeaks.length === 0
    ? `哨兵数为 ${sentinelCount}（0 说明合成行没造出外键键，断言空转）`
    : sweepLeaks.slice(0, 8).join('；') + (sweepLeaks.length > 8 ? ` …共 ${sweepLeaks.length} 处` : ''),
);

console.log(fails === 0 ? '\n全部通过' : `\n${fails} 例失败`);
process.exit(fails === 0 ? 0 : 1);
