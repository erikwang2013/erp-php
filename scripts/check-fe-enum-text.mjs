#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 枚举 / 计数值不上屏自检 —— scripts/check-fe-enum-text.mjs
 *
 * 守一条不变量：**详情条目与结果面板上的键是中文标题、值是当前语种的文案**，
 * 机读串（`sales_order`）、camelCase 兜底（`itemsCount`）、裸枚举（`中标标记 1`）都算漏。
 *   A. 我的审批 `target_type` 是后端无白名单的机读串 → 出中文；值域外原样直出（真实数据优先，不落「-」）；
 *   B. `withCount` 派生的计数列（`items_count`/`quotes_count`/`users_count`）不在 install.sql 里，
 *      掉出标题表就落 camelCase 兜底 → 详情页出「itemsCount 5」；
 *   C. `erp_purchase_rfq_quote.awarded` 是 TINYINT 0/1 → 不映射就出「中标标记 1」；
 *   D. 两端接线与词典逐字对齐（React 是 .tsx，只做静态比对）。
 *
 *   E. `cfg.dicts`（逐键值字典）通道：列表列 / 详情抽屉 / 动作结果面板三处同源，
 *      枚举键（`type`/`is_lowest`…）不再裸出 0/1 —— 三处的「带字典」与「不带字典」各自断言，
 *      证明文案确实由这一条通道带出（不是恰好被别的兜底蒙对）。
 *   F. 同一通道的引擎语义 + Angular/React 两端接线（调用点漏传 dicts 则引擎再对也是空转）。
 *   G. 域配置字典不变量（G1 译名须已注册进两端词典，缺一条在 en 下露中文；G2 两端 8 个域配置的
 *      dicts 字面量逐串同源，只改一端 = 另一端照旧漏）。
 *
 * 做法：跑 **Angular 端真身**（`resource-page/columns.ts` 的 `inferColumns`/`cellOf`/
 * `inferDetailItems`/`keyTitle` 是纯函数，可直接 import），夹具喂**域配置里的真列**
 * （不是手搓列）：把列改回裸列、把值映射拿掉，本脚本立刻失败。
 * React 端 .tsx 含 JSX（Node 擦不掉类型也转不了 JSX），按本仓既有约定只做静态接线检查
 * （同 check-fe-edit-seq.mjs）。
 *
 * 局限（如实记录）：跑的是纯函数真身，不起浏览器、不点弹窗 —— 它证明取数语义与两端接线形状，
 * 不证明页面上的真实表现（两端都没有 DOM 测试框架）。
 *
 * 用法：node scripts/check-fe-enum-text.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { fileURLToPath } from 'node:url';

// columns.ts 是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 与 scripts/check-fe-detail-items.mjs 同款，保证 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

// 相对导入按 CLI 习惯不带扩展名（`../../core/format`），Node 的 ESM 解析器不认。
// 就近补一层：xx → xx.ts、目录 → index.ts，只有本脚本受影响。
registerHooks({
  resolve(spec, ctx, next) {
    // React 侧两个浏览器依赖叶子用替身（真身是 .tsx / 需要 localStorage），
    // 只顶替 i18n 的 tr/locale 与 options 的 optionLabel，relation.ts 仍是真身。
    if (/^@\/lib\/(i18n|options)(\/index)?$/.test(spec)) {
      const src =
        spec.includes('i18n')
          ? `export const currentLocale=()=>'zh';export const tr=(s)=>s;export const setLocale=()=>{};export const acceptLanguage=()=>'zh-CN';export const getToken=()=>'';`
          : `export const optionLabel=()=>undefined;export const loadOptions=async()=>{};`;
      return { url: 'data:text/javascript;base64,' + Buffer.from(src).toString('base64'), shortCircuit: true };
    }
    // React 的 `@/` 别名（vite 里配的），只在加载 React 引擎真身时用到
    if (spec.startsWith('@/')) {
      const base = at('../apps/react/src/');
      for (const cand of [spec.slice(2), spec.slice(2) + '.ts', spec.slice(2) + '/index.ts']) {
        try {
          return next(new URL(cand, base).href, ctx);
        } catch {
          // 换下一个候选
        }
      }
    }
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
  const okv = JSON.stringify(got) === JSON.stringify(want);
  if (!okv) fails++;
  console.log(`${okv ? 'PASS' : 'FAIL'} ${name}: got ${JSON.stringify(got)}${okv ? '' : ` want ${JSON.stringify(want)}`}`);
};
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};

const url = (p) => new URL(p, import.meta.url);
const at = (p) => url(p).href;
const { cellOf, inferColumns, inferDetailItems, keyTitle, resultBlocks } = await import(
  at('../apps/angular/src/app/pages/resource-page/columns.ts')
);
const { setLocale, tr } = await import(at('../apps/angular/src/app/core/i18n.service.ts'));
const { zhEn } = await import(at('../apps/angular/src/app/core/zh-en/index.ts'));

console.log('── A. 我的审批 target_type：机读串不上屏（两端） ──');

/* 六项 canonical 映射：后端 ApprovalController::TARGET_REGISTRY 的四项 + 列注释遗留的
 * leave/other（registry 外但确实会出现），文案与 HarmonyOS ApprovalPage 的 l10n 表逐字一致。
 * 值域外的值（后端 submit 无白名单，客户端可自由传入）直显原文 —— 真实数据优先，不落「-」。 */
const TARGET_PAIRS = [
  ['sales_order', '销售订单'],
  ['purchase_apply', '采购申请'],
  ['purchase_order', '采购订单'],
  ['expense', '费用报销'],
  ['leave', '请假'],
  ['other', '其他'],
];

/** 域配置里某个路由的 cfg（两端都是 `{ label, path, cfg }` 的 children 平铺） */
const cfgOf = (groups, path) =>
  groups.flatMap((g) => g.children ?? []).find((c) => c.path === path)?.cfg;
const mgmtMenus = (await import(at('../apps/angular/src/app/config/domains/mgmt.ts'))).mgmtMenus;
const tradeMenus = (await import(at('../apps/angular/src/app/config/domains/trade.ts'))).tradeMenus;
const systemMenus = (await import(at('../apps/angular/src/app/config/domains/system.ts'))).systemMenus;
const financeMenus = (await import(at('../apps/angular/src/app/config/domains/finance.ts'))).financeMenus;

// Angular 真身：直接取域配置里 /workflow/my 的列（改回 textCol 裸列这条立刻失败），跑 cellOf
const MY_APPROVAL = cfgOf(mgmtMenus, '/workflow/my');
const ttCol = MY_APPROVAL?.columns?.find((c) => c.key === 'target_type');
ok(
  '域配置里有 /workflow/my 的 target_type 列',
  Boolean(ttCol),
  JSON.stringify(MY_APPROVAL?.columns?.map((c) => c.key)),
);
const ttText = (v) => (ttCol ? cellOf(ttCol, { target_type: v }).text : '<无 target_type 列>');

for (const [raw, label] of TARGET_PAIRS) eq(`zh：${raw} → ${label}`, ttText(raw), label);
eq('表外值原样直出（不落 -）', ttText('legacy_thing'), 'legacy_thing');
eq('空值不留机读串', ttText(null), '');

console.log('── B. withCount 计数列：标题出中文，不落 camelCase ──');

/* 三个 withCount 键的出处（都不在 install.sql 的列名里，靠标题表兜）：
 *   RfqController::index ~54   → quotes_count / items_count
 *   RfqQuoteController::index:45 → items_count
 *   RoleController::index:46, show:97 → users_count
 * 夹具用域配置里的**真列**跑 inferColumns/inferDetailItems，等于走详情抽屉的同一条路。 */
const RFQ = cfgOf(tradeMenus, '/purchase/rfq');
const QUOTE = cfgOf(tradeMenus, '/purchase/rfq-quote');
const ROLE = cfgOf(systemMenus, '/system/role');

/** 详情条目：列表列 + 行（详情抽屉渲染的正是列表行，两端同源）。
 *  列的组合口径照抄 resource-page.ts:286 `cfg.columns ?? inferColumns(...)` ——
 *  声明了 columns 的资源**不会**再补推导列，所以计数键只能靠标题表兜，awarded 只能靠显式列兜。 */
const detailOf = (cfg, row) =>
  inferDetailItems(
    row,
    cfg.columns ?? inferColumns([row], cfg.endpoint, cfg.fields ?? [], {}, 8),
  );
/** 键若掉进 camelCase 兜底，页面上出现的就是这个串 */
const camelOf = (k) => k.replace(/_([a-z])/g, (_m, c) => c.toUpperCase());

const COUNT_CASES = [
  ['询价单', RFQ, 'items_count', '明细行数', 5],
  ['询价单', RFQ, 'quotes_count', '报价数', 3],
  ['供应商报价', QUOTE, 'items_count', '明细行数', 2],
  ['角色权限', ROLE, 'users_count', '用户数', 4],
];
for (const [name, cfg, key, title, n] of COUNT_CASES) {
  ok(`${name}：域配置取到 ${cfg?.endpoint ?? name}`, Boolean(cfg?.columns?.length), '未取到 cfg');
  const items = detailOf(cfg, { id: 'HASH_X', code: 'X-1', status: 1, [key]: n });
  const it = items.find((x) => x.k === title);
  ok(`${name}：${key} → 标题「${title}」`, Boolean(it), JSON.stringify(items.map((x) => x.k)));
  eq(`${name}：${key} 值原样出计数（${n}）`, it?.v, String(n));
  ok(
    `${name}：${key} 不落 camelCase（${camelOf(key)}）`,
    !items.some((x) => x.k === camelOf(key)),
    JSON.stringify(items.map((x) => x.k)),
  );
}
// 兜底链本身：标题表没命中的键才走 camelCase —— 上面四条若全绿，说明三个键都在表里
eq('标题表兜底链：未收录键仍转驼峰', keyTitle('some_unknown_key'), 'someUnknownKey');

console.log('── C. 中标标记 awarded：值出文案，不裸 0/1 ──');

const awCol = QUOTE?.columns?.find((c) => c.key === 'awarded');
ok('供应商报价列表声明了 awarded 列', Boolean(awCol), JSON.stringify(QUOTE?.columns?.map((c) => c.key)));
eq('awarded 列走值字典（kind=map）', awCol?.kind, 'map');
const awText = (v) => (awCol ? cellOf(awCol, { awarded: v }).text : '<无 awarded 列>');
eq('zh：1 → 已中标', awText(1), '已中标');
eq('zh：0 → 未中标', awText(0), '未中标');
eq('zh：字符串 "1"（后端 TINYINT 可能回串）→ 已中标', awText('1'), '已中标');
eq('zh：表外值 2 原样直出', awText(2), '2');
eq('zh：空值不留数字', awText(null), '');

// 详情条目（用户报的就是这里：「中标标记 1」）
const awDetail = (v) =>
  detailOf(QUOTE, {
    id: 'HASH_Q',
    rfq_no: 'RFQ-20260922-01',
    supplier_name: '宁波某某供应商',
    amount: 1234.5,
    awarded: v,
    quote_date: '2026-09-22',
  }).find((x) => x.k === '中标标记');
eq('详情：中标标记 1 → 「已中标」', awDetail(1)?.v, '已中标');
eq('详情：中标标记 0 → 「未中标」', awDetail(0)?.v, '未中标');
eq('详情：表外值 2 原样直出', awDetail(2)?.v, '2');

console.log('── D. 切到 en：同一条渲染出英文（词典缺词条就露中文原文） ──');

setLocale('en');
// 词典是懒加载 chunk，等它装填（与 check-fe-detail-items.mjs 同款）
for (let i = 0; i < 20 && tr('sales_order') !== 'Sales Order'; i++) await new Promise((r) => setTimeout(r, 0));
eq('en：target_type → Sales Order', ttText('sales_order'), 'Sales Order');
eq('en：值域外仍原样直出', ttText('legacy_thing'), 'legacy_thing');
for (const [zh, en] of [
  ['明细行数', 'Line Items'],
  ['报价数', 'Quotes'],
  ['用户数', 'Users'],
  ['已中标', 'Won'],
  ['未中标', 'Not Won'],
]) {
  eq(`en：${zh} → ${en}`, tr(zh), en);
}
// 文案必须过 t() 词典（标签是配置里的中文，详情模板再过一次 tr）
for (const [zh, en] of [
  ['明细行数', 'Line Items'],
  ['报价数', 'Quotes'],
]) {
  eq(`en：详情标签 ${zh} 过词典 → ${en}`, zhEn[zh] ? tr(zh) : '<词典缺键>', en);
}
// 共享 map 支的另外 4 处内联 dict 列（Angular system.ts:132 / finance.ts:36、:39、:46）：
// 只有 Angular 侧跑得到真身，这里顺手证明「dict 值过 tr、en 下出译文」
const MAP_COLS = [
  ['权限类型', cfgOf(systemMenus, '/system/permission'), 'type', 1, 'Directory'],
  ['应收应付类型', cfgOf(financeMenus, '/finance/ar-ap'), 'type', 2, 'Payable'],
  ['日记账方向', cfgOf(financeMenus, '/finance/cash-journal'), 'direction', 1, 'Income'],
  ['发票类型', cfgOf(financeMenus, '/finance/invoice'), 'type', 'ar', 'Receivable'],
];
for (const [name, cfg, key, v, en] of MAP_COLS) {
  const col = cfg?.columns?.find((c) => c.key === key);
  eq(`en：${name} map 列 → ${en}`, col ? cellOf(col, { [key]: v }).text : '<无该列>', en);
}
/* 表外值口径：旧 React 三元式把「非第一档」全当第二档（3 → 「应付」、'legacy' → 「应付」），
 * 与 Angular 的「字典未命中即原样直出」不一致 —— 这条锁住对齐后的行为 */
const invTypeCol = cfgOf(financeMenus, '/finance/invoice')?.columns?.find((c) => c.key === 'type');
eq('en：发票类型表外值原样直出（不再回落「应付」）', cellOf(invTypeCol, { type: 'legacy' }).text, 'legacy');
setLocale('zh');

console.log('── E. React 端接线 + 两端词典逐字对齐 ──');

/** 取文件里的 `const <name>: Record<string, string> = { … };` 字面量 → [[键, 值]]。
 *  单行写法的 AWARDED 与多行写法都吃（列表里没有嵌套花括号，`[^}]*` 收得住） */
const pairsOf = (src, name) => {
  const m = new RegExp(`const ${name}: Record<string, string> = \\{([^}]*)\\}`).exec(src);
  return m ? [...m[1].matchAll(/(\w+):\s*'([^']+)'/g)].map((x) => [x[1], x[2]]) : null;
};
const read = (p) => readFileSync(url(p), 'utf8');
const NG_TRADE = read('../apps/angular/src/app/config/domains/trade.ts');
const REACT_TRADE = read('../apps/react/src/config/domains/trade.ts');

eq(
  '两端 AWARDED 逐字一致',
  pairsOf(REACT_TRADE, 'AWARDED'),
  pairsOf(NG_TRADE, 'AWARDED'),
);
eq('AWARDED == 0/1 两项（值域不漂移）', pairsOf(REACT_TRADE, 'AWARDED'), [
  ['0', '未中标'],
  ['1', '已中标'],
]);
ok(
  'React 供应商报价 awarded 走 mapText',
  /mapText\(r\.awarded, AWARDED\)/.test(REACT_TRADE),
  '未找到 awarded 的 mapText 渲染',
);
ok(
  'Angular awarded 列引用 AWARDED const（不是内联字面量）',
  /key: 'awarded'[\s\S]{0,120}dict: AWARDED/.test(NG_TRADE),
  '未找到 awarded 列与 AWARDED 的绑定',
);

// React 详情标签：声明列的 title 是中文原文，DescList 不像 Angular 模板那样对标签过 `| tr` ——
// 不在 inferDetailItems 里补一次 tr，en 下「中标标记」这类标签就露中文（只有 keyTitle 那条路翻了）
const REACT_DEFAULTS = read('../apps/react/src/lib/defaults.tsx');
const REACT_RELATION = read('../apps/react/src/lib/relation.ts');
ok(
  'React 详情标签过词典（声明列的 title 也 tr）',
  // 中段可插字段 label（order_id 在 /oms/rma 是「关联订单」），但两端必须仍是
  // col.title 先 tr、末档落 keyTitle —— 只要求链条形状，不钉死中间有几档
  /k: col\?\.title \? tr\(col\.title\)[\s\S]{0,160}keyTitle\(k\)/.test(REACT_DEFAULTS),
  '未找到 col.title 的 tr 包装',
);

// 与 Angular 对齐：另外 4 处内联 dict 列在 React 端必须走同一个 mapText（不是各写一份三元式）
const REACT_SYSTEM = read('../apps/react/src/config/domains/system.tsx');
const REACT_FINANCE = read('../apps/react/src/config/domains/finance.ts');
for (const [what, re, src] of [
  ['权限类型', /mapText\(r\.type, \{ 1: '目录', 2: '菜单', 3: '按钮' \}\)/, REACT_SYSTEM],
  ['应收应付类型', /mapText\(r\.type, \{ 1: '应收', 2: '应付' \}\)/, REACT_FINANCE],
  ['日记账方向', /mapText\(r\.direction, \{ 1: '收入', 2: '支出' \}\)/, REACT_FINANCE],
  ['发票类型', /mapText\(r\.type, \{ ar: '应收', ap: '应付' \}\)/, REACT_FINANCE],
]) {
  ok(`React ${what} 走 mapText`, re.test(src), '未找到 mapText 接线');
}

// React 词典（纯数据字面量，可直接求值）
const reactDictSrc = read('../apps/react/src/lib/i18n/zhEn.ts');
const reactDict = new Function(
  `return ${reactDictSrc.slice(reactDictSrc.indexOf('{', reactDictSrc.indexOf('= {')), reactDictSrc.lastIndexOf('};') + 1)}`,
)();
// 三个计数标题 + 中标两项：Angular zh-en 是 11 语种词典的输入源，React 侧另有自己的一份，
// 缺一条就在 en 下露中文（React 的 DescList 不 t() 标签，靠 keyTitle → tr(TITLES[k])）
for (const [zh, en] of [
  ['明细行数', 'Line Items'],
  ['报价数', 'Quotes'],
  ['用户数', 'Users'],
  ['已中标', 'Won'],
  ['未中标', 'Not Won'],
  ['销售订单', 'Sales Order'],
  // 上面 4 处 map 列的字典值：缺一条，那 4 列在 en 下露中文（Angular 走共享 map 支、React 走 mapText）
  ['目录', 'Directory'],
  ['菜单', 'Menu'],
  ['按钮', 'Button'],
  ['应收', 'Receivable'],
  ['应付', 'Payable'],
  ['收入', 'Income'],
  ['支出', 'Expense'],
]) {
  eq(`Angular 词典 ${zh}`, zhEn[zh], en);
  eq(`React 词典 ${zh}`, reactDict[zh], en);
}

console.log('── F. cfg.dicts 逐键值通道：列表列 / 详情抽屉 / 结果面板 ──');

/* 同一份字典三处共用（types.ts 的 DictMap）。夹具用**真引擎**跑：
 * 不带 dicts 的对照组证明裸 0/1 确实会漏（否则本节的「已修」断言可能被别处的兜底蒙对）。 */
const EMP_DICT = { 1: '在职', 2: '离职', 3: '停职' };
const EMP_ROW = { name: '张三', status: 2, type: 1 };
const empCol = (dicts) =>
  inferColumns([EMP_ROW], '/admin/v1/hr/employee', [], {}, 8, undefined, dicts).find(
    (c) => c.key === 'status',
  );

// 不变量 1：status 键仍走徽标支（保住色带），只是字典换成 cfg 里的真枚举
eq('无 dicts：status 2 落通用档（处理中）', cellOf(empCol(undefined), EMP_ROW).text, '处理中');
eq('有 dicts：status 2 → 离职', cellOf(empCol({ status: EMP_DICT }), EMP_ROW).text, '离职');
eq('status 列仍是徽标 kind（不退化成纯文本 map）', empCol({ status: EMP_DICT })?.kind, 'status');
ok('status 徽标带 tone（有 dicts 时色带不丢）', Boolean(cellOf(empCol({ status: EMP_DICT }), EMP_ROW).tone));

// 不变量 2：非 status 形键（type/priority/…）靠 dicts 走 map 支
const typeCol = (dicts) =>
  inferColumns([EMP_ROW], '/admin/v1/hr/employee', [], {}, 8, undefined, dicts).find((c) => c.key === 'type');
eq('无 dicts：type 1 裸出数字', cellOf(typeCol(undefined), EMP_ROW).text, '1');
eq('有 dicts：type 1 → 收货区', cellOf(typeCol({ type: { 1: '收货区' } }), EMP_ROW).text, '收货区');
eq('非 status 键走 map kind', typeCol({ type: { 1: '收货区' } })?.kind, 'map');

// 不变量 3：详情抽屉里列数被 limit 截掉的键走 fallbackCell，字典同样要吃上
const detailWith = (dicts) => inferDetailItems({ name: '张三', type: 1 }, [], dicts);
eq('无 dicts：抽屉 type 1 裸出数字', detailWith(undefined).find((i) => i.k === '类型')?.v, '1');
eq('有 dicts：抽屉 type 1 → 退货区', detailWith({ type: { 1: '退货区' } }).find((i) => i.k === '类型')?.v, '退货区');

// 不变量 3b：抽屉里的裸外键 —— `_id` 与 `_by` 都不许把原值（编码后 ID）当普通文本贴出来，
// 这正是用户报的「详情页显示 ID 值」。行里有名称兄弟（含 NAME_ALIAS 别名）就出名称，否则落占位
const fkRow = {
  id: 'H',
  approved_by: '410000000000000402',
  assigned_to: '410000000000000403',
  account_id: '410000000000000404',
  account_name: '现金',
  valid_to: '2026-12-31',
};
const fkItems = inferDetailItems(fkRow, [], undefined, undefined);
const fkVals = fkItems.map((i) => i.v);
const fkKeys = fkItems.map((i) => i.k);
ok(
  '抽屉：`_by`/`_to` 外键不贴裸 ID（approved_by / assigned_to）',
  !fkVals.includes('410000000000000402') && !fkVals.includes('410000000000000403'),
  'approved_by/assigned_to 的原值上屏了',
);
ok(
  '抽屉：标签不落原始字段键（驼峰形式也算：account_name → accountName），并取到名称兄弟',
  fkVals.includes('现金') &&
    !fkKeys.some((l) =>
      ['approved_by', 'assigned_to', 'account_id', 'account_name', 'valid_to'].some(
        (k) => l.toLowerCase() === k.toLowerCase() || l === k.replace(/_([a-z])/g, (_, c) => c.toUpperCase()),
      ),
    ),
  `标签漏成原始键或未取名称兄弟：labels=${JSON.stringify(fkKeys)}`,
);
ok(
  '抽屉：单号类外键走别名（order_id→order_code、rfq_id→rfq_no），不出裸 hashid',
  (() => {
    const it = inferDetailItems(
      {
        id: 'D',
        order_id: '410000000000000405',
        order_code: 'SO-2026-0001',
        rfq_id: '410000000000000406',
        rfq_no: 'XJ202609220001',
      },
      [],
      undefined,
      undefined,
    );
    const vs = it.map((i) => i.v);
    return (
      vs.includes('SO-2026-0001') &&
      vs.includes('XJ202609220001') &&
      !vs.includes('410000000000000405') &&
      !vs.includes('410000000000000406')
    );
  })(),
  '未取单号别名或漏裸 hashid',
);
ok(
  '抽屉：`_to` 后缀的日期不被当外键吃掉（valid_to 是 DATE，install.sql:3858）',
  fkVals.includes('2026-12-31'),
  'valid_to 被当外键落成占位',
);

// 不变量 4：动作结果面板（比价）同源 —— 这就是列注释里记的 is_lowest / status 裸值
const PANEL = { status: 1, is_lowest: 0 };
const PANEL_DICTS = { status: { 0: '草稿', 1: '待审核' }, is_lowest: { 0: '否', 1: '是' } };
const panelKv = (dicts) =>
  Object.fromEntries(resultBlocks(PANEL, '', dicts).flatMap((b) => b.kv.map((it) => [it.k, it.v])));
eq('无 dicts：面板 status/is_lowest 裸出数字', panelKv(undefined), { 状态: '1', 最低价: '0' });
eq('有 dicts：面板两值出文案', panelKv(PANEL_DICTS), { 状态: '待审核', 最低价: '否' });

// Angular 接线同查：上面四条跑的是引擎真身，若调用点没把 cfg.dicts 传下去，
// 引擎再对也是空转（本节所有 PASS 都作废），所以接线与语义一起断言
const NG_RP = read('../apps/angular/src/app/pages/resource-page/resource-page.ts');
for (const [what, re] of [
  ['列表推断传 cfg.dicts', /inferColumns\(this\.rows\(\), cfg\.endpoint, cfg\.fields, this\.relLabels\(\), 8, cfg\.filters, cfg\.dicts\)/],
  // 尾部容一个可选实参（并发车道给 inferDetailItems 加了 fields），但 dicts 必须在第三位
  ['详情抽屉传 cfg.dicts', /inferDetailItems\(d, this\.cols\(\), this\.cfg\(\)\?\.dicts[,)]/],
  ['动作结果面板传 cfg.dicts', /resultBlocks\(r\.data, tr\(r\.title\), this\.cfg\(\)\?\.dicts\)/],
  ['报表面板传 cfg.dicts', /resultBlocks\(r, '', this\.cfg\(\)\?\.dicts\)/],
]) {
  ok(`Angular：${what}`, re.test(NG_RP), '接线缺失');
}

// React 端只做静态接线检查（.tsx 含 JSX，Node 擦不掉）：三处调用点 + 两个分支
const REACT_RP = read('../apps/react/src/components/ResourcePage.tsx');
const REACT_FF = read('../apps/react/src/components/FormFields.tsx');
for (const [what, re, src] of [
  ['列表推断传 cfg.dicts', /inferColumns\(rows, cfg\.endpoint, cfg\.fields, 8, cfg\.filters, cfg\.dicts\)/, REACT_RP],
  ['详情抽屉传 cfg.dicts', /inferDetailItems\(detail, cols, cfg\.dicts[,)]/, REACT_RP],
  ['动作结果面板传 cfg.dicts', /<ResultView data=\{result\.data\} dicts=\{cfg\.dicts\} \/>/, REACT_RP],
  ['报表面板传 cfg.dicts', /<ResultView data=\{report\} dicts=\{cfg\.dicts\} \/>/, REACT_RP],
  ['inferColumns 的逐键字典支（kd → mapText）', /else if \(kd\) \{\s*render = \(row\) => mapText\(row\[k\], kd\)/, REACT_DEFAULTS],
  ['fallbackValue 吃 dicts（第 4 参起是 row，为的是裸外键取名称兄弟）', /function fallbackValue\(k: string, v: unknown, dicts\?: DictMap, row\?: Row\)/, REACT_DEFAULTS],
  ['React 详情兜底：外键（含 `_by`/`_to`）统一走关联名 fkText，不贴原值', /if \(isRelKey\(k, v\)\) return fkText\(row \?\? \{\}, k\)/, REACT_DEFAULTS],
  ['React 外键判别 isRelKey（`_to` 按值形状，避免吃掉 valid_to 的日期）', /export function isRelKey\(k: string, v: unknown\): boolean/, REACT_RELATION],
  ['React nameKeyOf 认 `_by` 后缀（approved_by 这类不以 _id 结尾的外键）', /k\.endsWith\('_by'\)/, REACT_RELATION],
  ['ResultView 递归透传 dicts', /function resultCell\(k: string, v: unknown, dicts\?: DictMap\)/, REACT_FF],
]) {
  ok(`React：${what}`, re.test(src), '接线缺失');
}

// React 外键分支跑真身：relation.ts 是纯 TS（.tsx 才擦不掉），链路只拖 format→i18n
// 与 options→api（两者是浏览器环境依赖：localStorage/axios）。两个叶子用 data: URL 顶替，
// 验的仍是 relation.ts 真身的判别与取数分支（src 支不在断言内，故 options 替身无碍）。
globalThis.localStorage = { getItem: () => null, setItem() {}, removeItem() {} };
const { isRelKey: rIsRelKey, nameKeyOf: rNameKeyOf, fkText: rFkText } = await import(
  at('../apps/react/src/lib/relation.ts')
);
ok(
  'React 真身：_by/_to 判为外键，valid_to 的日期不误判，id 不判',
  rIsRelKey('approved_by', '410000000000000402') &&
    rIsRelKey('assigned_to', '410000000000000403') &&
    rIsRelKey('valid_to', '410000000000000409') &&
    !rIsRelKey('valid_to', '2026-12-31') &&
    !rIsRelKey('id', 'H'),
  'isRelKey 判别错',
);
ok(
  'React 真身：fkText 取名称兄弟（account_id → 现金）、无兄弟落占位（assigned_to → -），不贴裸 ID',
  rFkText(fkRow, 'account_id') === '现金' && rFkText(fkRow, 'assigned_to') === '-',
  `account_id=${rFkText(fkRow, 'account_id')} assigned_to=${rFkText(fkRow, 'assigned_to')}`,
);
ok(
  'React 真身：nameKeyOf 认 _by/_to，非外键键返回 undefined',
  rNameKeyOf('approved_by') === 'approved_name' &&
    rNameKeyOf('assigned_to') === 'assigned_name' &&
    rNameKeyOf('enabled') === undefined,
  'nameKeyOf 后缀判别错',
);
ok(
  'React 真身：order_id/rfq_id 走单号别名（不落「-」）',
  rFkText({ id: 'D', order_id: '410000000000000405', order_code: 'SO-2026-0001' }, 'order_id') ===
    'SO-2026-0001' &&
    rFkText({ id: 'Q', rfq_id: '410000000000000406', rfq_no: 'XJ202609220001' }, 'rfq_id') ===
      'XJ202609220001',
  '未取 order_code/rfq_no',
);

console.log('── G. 域配置字典不变量：译名已注册 + 两端域字典同源 ──');

/* G1：真域配置里的每个字典译名都必须是两端词典的键。缺一条 = 该页在 en 下露中文，
 * 且 11 语种翻译表（Angular zh-en 是输入源）也拿不到该词条。这条是新增枚举词条的注册锁：
 * 只加 `dicts` 不加词条 → 本段红。 */
const DOMAINS = ['trade', 'goods', 'fulfill', 'mfg', 'finance', 'crm', 'mgmt', 'system'];
const domainMenus = {};
for (const d of DOMAINS) {
  domainMenus[d] = (await import(at(`../apps/angular/src/app/config/domains/${d}.ts`)))[`${d}Menus`];
}
const entries = [];
const walkMenus = (node, fn) => {
  if (Array.isArray(node)) node.forEach((n) => walkMenus(n, fn));
  else if (node !== null && typeof node === 'object') {
    if (node.cfg) fn(node);
    walkMenus(node.children, fn);
  }
};
for (const [d, menus] of Object.entries(domainMenus)) {
  walkMenus(menus, (m) => {
    const push = (k, map) => {
      for (const [v, t] of Object.entries(map ?? {})) entries.push([d, m.path, k, v, String(t)]);
    };
    for (const [k, map] of Object.entries(m.cfg.dicts ?? {})) push(k, map);
    // 挂列上的字典（`kind:'map'` 的 dict、statusCol(dict)、strStatus 的 rel）：原先只看 cfg.dicts，
    // 这些译名一个都进不来（实测本轮 26 个新译名里有 4 个只挂列上）→ 它们在 en 下露中文却无人拦。
    for (const c of m.cfg.columns ?? []) {
      push(c.key, c.dict);
      push(c.key, c.rel);
    }
  });
}
const hasKey = (dict, k) => Object.prototype.hasOwnProperty.call(dict, k);
// 只有**含中文**的译名才需要注册：`edi: 'EDI'`/`pos: 'POS'`/`api: 'API'`/币种码 `CNY`
// 这类语言中立值在 en 下本来就该原样出，注册了反而多余（也正因此「值=键」对它们合法）。
const CJK = /\p{Script=Han}/u;
const labels = [...new Set(entries.map((e) => e[4]))].filter((t) => CJK.test(t));
const unreg = labels.filter((t) => !hasKey(zhEn, t) || !hasKey(reactDict, t));
ok(
  `域字典 ${entries.length} 条 / 含中文译名 ${labels.length} 个，全部已注册进两端词典`,
  unreg.length === 0,
  `未注册 ${unreg.length} 个：${JSON.stringify(unreg)}`,
);
const selfSame = entries.filter(([, , , v, t]) => v === t && CJK.test(t));
ok('无「键=值」的伪译名（拿机读串当中文文案）', selfSame.length === 0, JSON.stringify(selfSame.slice(0, 6)));
const numLabel = entries.filter(([, , , , t]) => /^\d+$/.test(t));
ok('无纯数字译名（等于没翻）', numLabel.length === 0, JSON.stringify(numLabel.slice(0, 6)));

/* G2：两端域配置是两份手写文件，只改一端 = 另一端照旧漏。比对字典构造里的引号串**集合**，
 * 对换行/缩进/尾逗号、对「同一码表拆成几个常量」都不敏感；同一页面在两端各写一份，集合不同即漂移。
 * 覆盖范围 = 内联字面量（下列 6 种写法）＋ 字典形态的模块级常量（对象字面量初值、`docStatus([...])` 工厂初值）。
 * 常量**不按引用式挑**：引用式是逐形状的猫鼠游戏 —— 实测每补一种写法就翻出另一种不对称
 * （`statusCol(PACK.dict)` / `doc(…, PACK, …)` 两端各走一边），而两端本就各自声明同名常量，全收才对称。
 * 残余（已知、实测过的盲区）：**数组形态的常量**（`const ON_OFF = [{ label: '启用', value: 1 }, …]`）
 * 不收 —— 收数组就得再分「字典数组」与「菜单/字段描述表数组」，实测后者会把整份页面配置当字典串。
 * 负控：改 Angular goods 的 ON_OFF 文案，本段照绿（两端都靠 G4 的行为断言兜，那里按真引擎渲染）。
 * 字面量有四种写法，只认第一种会把挂列的字典整批漏掉（实测：fulfill 的 wave/pick/putaway/pack
 * 把字典当工厂**位置参数**传，/finance/cost-center 的 statusCol({...}) 也看不见）：
 *   ① `dicts: { ... }`  ② 列上 `dict: { ... }`（kind:'map'）与 `statusCol({ ... })`
 *   ③ React 内联 `render: (r) => mapText(r.x, { ... })`  ④ 工厂位置参数 `..., undefined, { ... }`
 *      （Angular 的 doc() 少一个 undefined 占位，是 `[列], { ... }` —— 两端的写法必须都收，只收一端
 *      会假红：实测 fulfill 的 package_type 两处逐字节相同，却因触发式只认 React 那侧而报漂移）
 * 注意：agents 落地顺序是 Angular 先、React 后，中途跑本段会阶段性偏红（红即未落完）。 */
/** 从 `{` 处切出平衡块（跳过字符串），返回块尾下标 */
function blockEnd(src, i) {
  let depth = 0;
  for (let j = i; j < src.length; j++) {
    const c = src[j];
    if (c === "'" || c === '"') {
      const q = c;
      j++;
      while (j < src.length && src[j] !== q) j += src[j] === '\\' ? 2 : 1;
    } else if (c === '{') depth++;
    else if (c === '}') {
      depth--;
      if (depth === 0) return j;
    }
  }
  return src.length - 1;
}
/** 从 `=` 后切到语句末（顶层 `;`），括号与字符串都算过 —— 数组/对象/工厂调用三种初值都行 */
function stmtEnd(src, i) {
  let depth = 0;
  for (let j = i; j < src.length; j++) {
    const c = src[j];
    if (c === "'" || c === '"') {
      const q = c;
      j++;
      while (j < src.length && src[j] !== q) j += src[j] === '\\' ? 2 : 1;
    } else if (c === '{' || c === '[' || c === '(') depth++;
    else if (c === '}' || c === ']' || c === ')') depth--;
    else if (c === ';' && depth <= 0) return j + 1;
  }
  return src.length;
}
function dictLiterals(src) {
  const out = [];
  // 常量字典（`const X = { … }`）只在被 `dict: X` / `mapText(r.k, X)` / `statusCol(X)` **引用**时带进来。
  // 少这一步，常量引用的列就是零覆盖：`/oms/order.channel`（`dict: OMS_ORDER_CHANNEL`）在两端门禁里
  // 都不出现 —— G2 只认带 `{` 的写法，G1 只 import Angular 端，于是 React 端把常量改错也照绿（dom-fulfill 实测指出）。
  const consts = new Map();
  // 取整个 `const X = …;` 语句而不是只取 `{…}` 块：两端写法不同形 —— Angular 用对象字面量
  // `const OPP = { 0:'输单', … }`，React 用工厂调用 `const OPP = docStatus(['输单', …])`，
  // 只认花括号会把 React 那侧整批漏掉（实测：crm 假红 9 条）。
  // 名字与 `=` 之间允许类型标注：`const OMS_ORDER_CHANNEL: Record<string, string> = {…}`、
  // `const INVOICE_DICTS: DictMap = {…}` 都是本仓的常态写法 —— 只认 `const NAME =` 会把它们整批跳过，
  // 而**两端都跳过**就成了对称盲区（负控实测：只改 React 的 `manual: '手工'` 门禁照绿）。
  // 初值只收**对象字面量 `{…}`** 与**工厂调用 `Name(…)`**（docStatus/strStatus），不收数组与箭头函数：
  // 数组常量在两端是**整份菜单/字段描述表**（`const systemMenus: MenuGroup[] = [ … ]`），收进来就是
  // 把整个页面配置当字典串（键名、端点、类型、提示语全进集合）—— 实测 Angular 的 system.ts 整份被收、
  // React 那份因内含 `render: (r) => …` 被「跳过箭头函数」规则剔掉，成了 108 串 vs 11 串的假红。
  for (const c of src.matchAll(/\bconst\s+([A-Za-z_$][\w$]*)\s*(?::[^=;]{1,120})?=/g)) {
    const after = src.slice(c.index + c[0].length).replace(/^\s*/, '');
    if (!after.startsWith('{') && !/^[A-Za-z_$][\w$]*\s*\(/.test(after)) continue;
    consts.set(c[1], src.slice(c.index, stmtEnd(src, c.index + c[0].length)));
  }
  // 模块级常量**全都纳入**（不按引用式挑）：引用式是逐形状的猫鼠游戏 —— 实测每补一种写法就翻出另一种不对称
  // （`statusCol(PACK.dict)` / `doc(…, PACK, […])` 两端各走一边），而两端本就各自声明同名常量，
  // 全收是天然对称的判据。数组里的字段类型串（'select'）等同收，两端一致即无影响。
  const re =
    /dicts:\s*\{|\bdict:\s*\{|\bstatusCol\(\s*\{|mapText\([^,()]*,\s*\{|\bundefined,\s*\{|\],\s*\{/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    const i = m.index + m[0].length - 1;
    const j = blockEnd(src, i);
    out.push(src.slice(i, j + 1));
    re.lastIndex = j + 1;
  }
  return out.join('\n') + '\n' + [...consts.values()].join('\n');
}
/** 取字典串**集合**（去重）：
 *  去重 —— 两端把同一张码表拆/合成几个常量是等价写法（实测 finance：Angular 的 RECEIPT/PAYMENT/FIN_DOC
 *  三个常量与 React 的 FIN_DOC 一个常量逐字相同，按多集比会假红「待审核/已审核」）；
 *  丢单字母串 —— 那是徽标色带 `{ audited: 's', … }` 之类的色号（React 的 strStatus/docStatus 第二参），
 *  不是文案。两者都不是「只改一端」的漂移，留着只会训练人忽略红灯。 */
const litStrings = (src) =>
  [...new Set(dictLiterals(src).match(/'[^']*'|"[^"]*"/g) ?? [])]
    .filter((s) => !/^['"][A-Za-z]['"]$/.test(s))
    .sort();
const multiDiff = (a, b) => {
  const rest = [...b];
  return a.filter((x) => {
    const i = rest.indexOf(x);
    return i < 0 ? true : (rest.splice(i, 1), false);
  });
};
for (const d of DOMAINS) {
  const ng = litStrings(read(`../apps/angular/src/app/config/domains/${d}.ts`));
  const re = litStrings(
    read(`../apps/react/src/config/domains/${d === 'system' ? 'system.tsx' : d + '.ts'}`),
  );
  ok(
    `域字典两端同源：${d}（Angular ${ng.length} 串 / React ${re.length} 串）`,
    JSON.stringify(ng) === JSON.stringify(re),
    `仅 Angular：${JSON.stringify(multiDiff(ng, re))}；仅 React：${JSON.stringify(multiDiff(re, ng))}`,
  );
}

/* G3：布尔开关列（`enabled`、`is_*`）不许在列表或详情里裸出 0/1。
 * 这些列在 install.sql 里全是 TINYINT，但列注释常为空（erp_finance_tax_rate.enabled 就是），
 * 按注释识别不出来 —— 引擎只能按列名识别（Angular columns.ts / React defaults.tsx 的 isBool 分支）。
 * 断的是**真引擎渲染**，期望值不取自引擎：页面 dicts 优先，其次 enabled=禁用/启用、is_*=否/是（DDL 语义）。
 * 少了那个分支，/finance/tax-rate 的「状态」就是用户报的裸 `1`。键名现读 DDL，新增布尔列自动纳入。 */
const sqlSrc = read('../database/install.sql');
const boolKeys = [
  ...new Set([...sqlSrc.matchAll(/^\s+`((?:enabled|is_[a-z_]+))`\s+TINYINT/gm)].map((m) => m[1])),
].sort();
const allPages = [];
for (const menus of Object.values(domainMenus)) walkMenus(menus, (m) => allPages.push(m));
const boolLeaks = [];
for (const m of allPages) {
  for (const k of boolKeys) {
    for (const v of [0, 1]) {
      const kd = m.cfg.dicts?.[k];
      const want = String(
        kd?.[v] ?? kd?.[String(v)] ?? (k === 'enabled' ? (v === 0 ? '禁用' : '启用') : v === 0 ? '否' : '是'),
      );
      const row = { id: 'H', code: 'X-1', [k]: v };
      // 显式声明 columns 的页本就没有这个键（只剩抽屉通道），列「无」不算漏
      const cols = m.cfg.columns ?? inferColumns([row], m.cfg.endpoint, m.cfg.fields, {}, 8, m.cfg.filters, m.cfg.dicts);
      const col = cols.find((c) => c.key === k);
      const list = col ? cellOf(col, row).text : null;
      const drawn = inferDetailItems(row, cols, m.cfg.dicts).find((i) => i.k === keyTitle(k));
      const draw = String(drawn ? drawn.v : '(无此行)');
      if ((list !== null && list !== want) || draw !== want) {
        boolLeaks.push(`${m.path} ${k}=${v} 期望「${want}」列「${list ?? '(无此列)'}」抽屉「${draw}」`);
      }
    }
  }
}
ok(
  `布尔开关 ${allPages.length} 页 × ${boolKeys.length} 键 × 2 值：列与抽屉都不裸 0/1（${boolKeys.join(' ')}）`,
  boolLeaks.length === 0,
  boolLeaks.slice(0, 8).join('；') + (boolLeaks.length > 8 ? ` …共 ${boolLeaks.length} 处` : ''),
);

/* G4：表单/筛选能提交的码，列表与详情都得显示成文案。
 * 判据只取**应用自己声明的码**（表单 options + 筛选 options），不猜 DDL —— 能提交就一定能显示，
 * 裸码出现在任何一栏就是用户看到的那类「状态/类型还是数字」。 */
const optLeaks = [];
let optChecked = 0;
for (const m of allPages) {
  const codes = new Map();
  const add = (k, v) => {
    if (!codes.has(k)) codes.set(k, new Set());
    codes.get(k).add(String(v));
  };
  for (const f of m.cfg.fields ?? []) for (const o of f.options ?? []) if (o?.value != null && o.value !== '') add(f.key, o.value);
  for (const fl of [m.cfg.filters].filter(Boolean)) for (const o of fl.options ?? []) if (o?.value != null && o.value !== '') add(fl.key, o.value);
  for (const [k, vs] of codes) {
    for (const sv of vs) {
      const v = /^\d+$/.test(sv) ? Number(sv) : sv;
      const row = { id: 'H', code: 'X-1', [k]: v };
      const cols = m.cfg.columns ?? inferColumns([row], m.cfg.endpoint, m.cfg.fields, {}, 8, m.cfg.filters, m.cfg.dicts);
      const col = cols.find((c) => c.key === k);
      const list = col ? cellOf(col, row).text : null;
      const drawn = inferDetailItems(row, cols, m.cfg.dicts).find((i) => i.k === keyTitle(k));
      const draw = String(drawn ? drawn.v : '(无此行)');
      const bare = (t) => t === sv || (/^\d+$/.test(sv) && t === Number(sv).toFixed(2));
      optChecked++;
      if ((list !== null && bare(list)) || bare(draw)) {
        optLeaks.push(`${m.path} .${k}=${sv} 列「${list ?? '(无此列)'}」抽屉「${draw}」`);
      }
    }
  }
}
ok(
  `表单/筛选声明的码 ${optChecked} 处都渲染成文案、不裸码`,
  optLeaks.length === 0,
  optLeaks.slice(0, 8).join('；') + (optLeaks.length > 8 ? ` …共 ${optLeaks.length} 处` : ''),
);
// React 是另一份手写引擎，行为测不到（JSX 跑不进 node）—— 静态锁：两处推断/兜底都得在，yesNo 得引入
const cnt = (re) => (REACT_DEFAULTS.match(re) ?? []).length;
ok(
  'React 引擎同口：isBool 定义 + 列推断 + 详情兜底三处都在（yesNo 已引入）',
  cnt(/const isBool = \(k: string\)/g) === 1 &&
    cnt(/} else if \(k === 'enabled'\) \{/g) === 1 &&
    cnt(/if \(k === 'enabled'\) return <Badge text=\{yesNo/g) === 1 &&
    cnt(/isBool\(k\)/g) === 2 &&
    /text, yesNo \}/.test(REACT_DEFAULTS),
  `isBool=${cnt(/const isBool = \(k: string\)/g)} 列支=${cnt(/} else if \(k === 'enabled'\) \{/g)} 兜底支=${cnt(/if \(k === 'enabled'\) return <Badge text=\{yesNo/g)} isBool支=${cnt(/isBool\(k\)/g)}`,
);

console.log(fails === 0 ? '\n全部通过' : `\n${fails} 例失败`);
process.exit(fails === 0 ? 0 : 1);
