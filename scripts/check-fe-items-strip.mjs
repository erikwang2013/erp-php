#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 编辑弹框「明细(items)不回填」自检 —— scripts/check-fe-items-strip.mjs
 *
 * 背景：编辑弹框先拉详情再并进表单初值（ResourcePage.openEdit / Angular 同款 openEdit），
 * `type:'items'` 字段会被明细回填，而明细在当前后端是**写不回去**的：
 *  - 更新接口要么不处理 items（ReceiveController::update、DeliveryController::update 只写
 *    remark：用户改了明细，保存提示成功但改动丢失），
 *  - 要么按整数校验明细行（MaterialIssueController::update、SubcontractIssueController::update
 *    用 (int) $row['sku_id'] 查 SKU：哈希串落 0，直接 422，整单都存不下去），
 *  - 明细行的 id 是裸雪花 id / 与控件的哈希选项对不上（HashidsService::encodeIds 只编码调用方
 *    列出的键，或空列表时按 *_id 全编码，两种口径都与编辑态表单不一致）。
 * 所以两端统一「明细仅新建期填写」：合并时把 `type:'items'` 字段从合并结果里摘掉。
 *
 * 本脚本守的不变量：
 *  1) 【行为】两端 mergeEditRow 真身执行（React lib/edit-row.ts、Angular pages/resource-page/edit-row.ts）：
 *     摘掉 items、详情覆盖列表行、不就地改传入的 row、detail 为 null 时静默回落、删除键取
 *     `initKey ?? key`、非 items 字段（含带 initKey 的树字段）不动 —— 两端同一批夹具结果逐字节相同；
 *  2) 【接线】两端 openEdit 只做「拉详情 → 交给 mergeEditRow」，不得再有就地 `delete merged[`；
 *  3) 【配置】发货/收货的表头四项与 items 标 createOnly（编辑态隐藏），其余 items 域不得标 ——
 *     标了就等于把它们也变成只读，不标则发货/收货是「填了、提示成功、改动被丢弃」；
 *  4) 【配套】编辑态 items 免必填：items 域全是 required，摘除后必填照拦则整单保存不了（比修前更糟）。
 *
 * 局限（如实记录）：行为断言跑的是抽出来的纯函数真身，仍**不起浏览器、不点按钮** ——
 * 它证明合并/摘除/必填豁免的语义两端一致，不证明页面上的真实表现（两端都没有 DOM 测试框架）。
 *
 * 用法：node scripts/check-fe-items-strip.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

// 两个 edit-row 模块是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 保证脚本与 scripts/ 下其他自检一样 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

const REACT_EDIT_ROW = new URL('../apps/react/src/lib/edit-row.ts', import.meta.url).href;
const NG_EDIT_ROW = new URL('../apps/angular/src/app/pages/resource-page/edit-row.ts', import.meta.url).href;
const { mergeEditRow: reactMerge } = await import(REACT_EDIT_ROW);
const { mergeEditRow: ngMerge } = await import(NG_EDIT_ROW);

let fails = 0;
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};
const eq = (name, got, want) => {
  const pass = JSON.stringify(got) === JSON.stringify(want);
  ok(name, pass, `got ${JSON.stringify(got)} want ${JSON.stringify(want)}`);
};

const read = (p) => fs.readFileSync(new URL(`../${p}`, import.meta.url), 'utf8');

/** 取函数体：从 declaration 标记后的第一个 '{' 起按大括号配平（React 是箭头函数，Angular 是方法）。 */
function methodBody(src, declaration) {
  const at = src.indexOf(declaration);
  if (at < 0) return '';
  return braced(src, src.indexOf('{', at));
}

/** 从 openAt（'{' 的位置）起按大括号配平，返回整块。 */
function braced(src, openAt) {
  if (openAt < 0) return '';
  let depth = 0;
  for (let i = openAt; i < src.length; i++) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}' && --depth === 0) return src.slice(openAt, i + 1);
  }
  return '';
}

const REACT_PAGE = 'apps/react/src/components/ResourcePage.tsx';
const NG_PAGE = 'apps/angular/src/app/pages/resource-page/resource-page.ts';
const REACT_FORM = 'apps/react/src/components/FormFields.tsx';
const NG_FORM = 'apps/angular/src/app/pages/resource-page/resource-form.ts';

/* ── 1. 行为：两端 mergeEditRow 真身跑同一批夹具 ── */

// 夹具照抄真配置：采购收货的字段表（trade.ts）+ ReceiveController::show/index 的下发形状
// （列表行带 items、详情带 hashid 的 items[].sku_id）。
const RECEIVE_CFG = {
  fields: [
    { key: 'order_id', label: '采购订单', createOnly: true },
    { key: 'supplier_id', label: '供应商', createOnly: true },
    { key: 'warehouse_id', label: '仓库', createOnly: true },
    {
      key: 'items',
      label: '收货明细',
      type: 'items',
      createOnly: true,
      itemFields: [{ key: 'product_id' }, { key: 'quantity' }],
    },
    { key: 'remark', label: '备注', type: 'textarea' },
  ],
};
const LIST_ROW = {
  id: 'H1',
  code: 'RC001',
  order_id: 'H9',
  supplier_id: 'S1',
  items: [{ id: 'I9', sku_id: 'K1', quantity: 3 }],
  remark: '列表行',
};
const DETAIL_ROW = {
  id: 'H1',
  code: 'RC001',
  order_id: 'H9',
  supplier_id: 'S1',
  warehouse_id: 'W1',
  items: [{ id: 'I1', sku_id: 'K7', quantity: 5 }],
  remark: '详情',
};

const engines = [
  ['React', reactMerge, REACT_EDIT_ROW],
  ['Angular', ngMerge, NG_EDIT_ROW],
];
const results = {};
for (const [label, merge] of engines) {
  const row = structuredClone(LIST_ROW);
  const out = merge(RECEIVE_CFG, row, DETAIL_ROW);
  results[label] = out;
  ok(`${label}：合并结果里没有 items（明细不回填）`, !('items' in out), `out=${JSON.stringify(out)}`);
  ok(`${label}：详情覆盖列表行（warehouse_id 取详情那份）`, out.warehouse_id === 'W1');
  ok(`${label}：详情覆盖列表行（remark 取详情那份，不是列表行的）`, out.remark === '详情');
  ok(
    `${label}：不就地改传入的列表行（列表还握着同一个对象）`,
    Array.isArray(row.items) && row.items[0].sku_id === 'K1' && row.remark === '列表行',
    `row=${JSON.stringify(row)}`,
  );

  const noDetail = merge(RECEIVE_CFG, structuredClone(LIST_ROW), null);
  ok(`${label}：详情拉不到时静默回落列表行且仍摘 items`, !('items' in noDetail) && noDetail.code === 'RC001');
  ok(`${label}：回落时不凭空补字段`, !('warehouse_id' in noDetail));

  // 删除键取 initKey ?? key：与表单读初值的键一致（否则摘错键 = 摘了个寂寞）
  const initKeyCfg = { fields: [{ key: 'items', type: 'items', initKey: 'detail_rows' }] };
  const initKeyRow = { id: 'H2', items: [{ sku_id: 'K1' }], detail_rows: [{ sku_id: 'K2' }] };
  const initKeyOut = merge(initKeyCfg, initKeyRow, null);
  ok(
    `${label}：items 字段声明了 initKey 时按 initKey 摘`,
    !('detail_rows' in initKeyOut),
    `out=${JSON.stringify(initKeyOut)}`,
  );

  // 树字段（type:'multi' + initKey:'roles'）不是 items，不得被摘
  const treeCfg = {
    fields: [
      { key: 'role_ids', type: 'multi', initKey: 'roles' },
      { key: 'items', type: 'items' },
    ],
  };
  const treeOut = merge(treeCfg, { id: 'H3', roles: ['r1', 'r2'], items: [{ sku_id: 'K1' }] }, null);
  eq(`${label}：带 initKey 的树字段原样保留（只摘 items）`, treeOut.roles, ['r1', 'r2']);
  ok(`${label}：同一份结果里 items 已摘`, !('items' in treeOut));
}
eq('两端同一批夹具的结果逐字节相同', results.React, results.Angular);

/* ── 2. 接线：openEdit 不得再自己摘 ── */

const pages = [
  ['React', REACT_PAGE, 'const openEdit'],
  ['Angular', NG_PAGE, 'async openEdit('],
];
for (const [label, path, decl] of pages) {
  const src = read(path);
  const body = methodBody(src, decl);
  ok(`${label}（${path}）：找到 openEdit`, body !== '', '方法体为空');
  ok(
    `${label}：openEdit 把初值交给 mergeEditRow`,
    /mergeEditRow\(\s*cfg,\s*row,\s*detail\s*\)/.test(body),
    `openEdit 未接到 mergeEditRow：${body.replace(/\s+/g, ' ').slice(0, 100)}`,
  );
  ok(
    `${label}：openEdit 里不再有就地摘除（摘除只有一份，在 edit-row 里）`,
    !/delete merged\[/.test(body),
    'openEdit 又自己摘了一遍 —— 两端会各摘各的',
  );
  ok(
    `${label}：全文件只此一处接线（无残留的 delete merged[）`,
    !/delete merged\[/.test(src),
    `实际 ${(src.match(/delete merged\[/g) ?? []).length} 处就地摘除`,
  );
}

for (const [label, href] of [['React', REACT_EDIT_ROW], ['Angular', NG_EDIT_ROW]]) {
  const src = fs.readFileSync(fileURLToPath(href), 'utf8');
  ok(
    `${label}：edit-row.ts 里先复制行再合并、合并后才摘`,
    /\{\s*\.\.\.row,\s*\.\.\.\(detail \?\? \{\}\)\s*\}/.test(src),
    '合并写法变了 —— 摘除可能落到了别人持有的对象上',
  );
  ok(
    `${label}：edit-row.ts 只摘 type === 'items' 且按 initKey ?? key`,
    (src.match(/delete merged\[/g) ?? []).length === 1 && /f\.type === 'items'\) delete merged\[f\.initKey \?\? f\.key\]/.test(src),
    '摘除条件/键口径变了',
  );
}

/* ── 3. 配置：发货/收货 表头四项与明细标 createOnly，其余 items 域不得标 ── */

/** 从 openAt（'[' 的位置）起按方括号配平，返回整块 */
function bracketed(src, openAt) {
  if (openAt < 0) return '';
  let depth = 0;
  for (let i = openAt; i < src.length; i++) {
    if (src[i] === '[') depth++;
    else if (src[i] === ']' && --depth === 0) return src.slice(openAt, i + 1);
  }
  return '';
}

/**
 * 某 endpoint 所属资源的 fields 数组里标了 createOnly 的字段键集。
 * 窗口取该 endpoint 之后的第一个 `fields: [` 并配平到它的 `]`（不能截到「下一个 endpoint」——
 * 字段自己的 source.endpoint 也是 endpoint，那样只能看到第一个字段）。
 * `(?:[^{}]|\{[^{}]*\})*?` 允许跨一层嵌套对象（如 source: {...}），但过不了字段对象的 `}`，不会串到下一个字段。
 */
function createOnlyKeys(src, endpoint) {
  const at = src.indexOf(`endpoint: '${endpoint}'`);
  if (at < 0) return null;
  const fieldsAt = src.indexOf('fields: [', at);
  if (fieldsAt < 0) return null;
  const arr = bracketed(src, src.indexOf('[', fieldsAt));
  return [...arr.matchAll(/key: '(\w+)'(?:[^{}]|\{[^{}]*\})*?createOnly: true/g)].map((m) => m[1]).sort();
}

/** 每个 items 字段对象的片段：从 `key: 'items'` 到下一个 itemFields（createOnly 在二者之间） */
function itemsWindows(src) {
  const out = [];
  for (let at = src.indexOf("key: 'items'"); at >= 0; at = src.indexOf("key: 'items'", at + 1)) {
    const end = src.indexOf('itemFields', at);
    if (end > 0) out.push(src.slice(at, end));
  }
  return out;
}

const EXPECTED = {
  '/admin/v1/purchase/receive': ['items', 'order_id', 'supplier_id', 'warehouse_id'],
  '/admin/v1/sales/delivery': ['customer_id', 'items', 'order_id', 'warehouse_id'],
};
const configs = [
  ['React', 'apps/react/src/config/domains'],
  ['Angular', 'apps/angular/src/app/config/domains'],
];
const sets = {};
for (const [label, dir] of configs) {
  const trade = read(`${dir}/trade.ts`);
  for (const [endpoint, want] of Object.entries(EXPECTED)) {
    const got = createOnlyKeys(trade, endpoint);
    sets[`${label}${endpoint}`] = got;
    eq(`${label}：${endpoint} 标 createOnly 的字段集`, got, want);
  }
  // 其余 items 域（询价/报价/领料/发料/履约分配）明细仍可编辑：编辑态自有「明细仅新建期填写」兜住，
  // 标 createOnly 会把它们的明细在新增时也藏起来 —— 那是另一个语义，别顺手标。
  for (const file of ['mfg.ts', 'fulfill.ts']) {
    const src = read(`${dir}/${file}`);
    const windows = itemsWindows(src);
    ok(
      `${label}：${file} 的 items 字段未被标 createOnly`,
      windows.length > 0 && windows.every((w) => !w.includes('createOnly')),
      `${file} 里 ${windows.length} 个 items 段，标了 createOnly 的会影响新增态`,
    );
  }
}
eq(
  '两端 createOnly 字段集一致（改一端忘另一端即失败）',
  Object.entries(sets)
    .filter(([k]) => k.startsWith('React'))
    .map(([k, v]) => [k.replace('React', ''), v]),
  Object.entries(sets)
    .filter(([k]) => k.startsWith('Angular'))
    .map(([k, v]) => [k.replace('Angular', ''), v]),
);

/* ── 4. 配套：两端「编辑态 items 免必填」 ── */

/** submit 里必填循环的片段：从函数体开头到必填判定那行为止 */
function requiredLoop(body, marker) {
  const at = body.indexOf(marker);
  return at < 0 ? '' : body.slice(0, at);
}

const requiredCases = [
  [REACT_FORM, methodBody(read(REACT_FORM), 'const submit'), 'f.required && isEmpty', 'React'],
  [NG_FORM, methodBody(read(NG_FORM), 'async submit('), 'const empty =', 'Angular'],
];
for (const [path, body, marker, label] of requiredCases) {
  const before = requiredLoop(body, marker);
  const m = before.match(/f\.type === 'items' && row !== null\) continue;/);
  ok(
    `${label}（${path}）：submit 必填循环里有编辑态 items 豁免`,
    m !== null,
    '缺这一行 → items 域编辑态明细为空即被必填拦死，整单存不了',
  );
  ok(
    `${label}：豁免限定 row !== null（新增态仍必填）`,
    m !== null && /row !== null/.test(m[0]),
    '豁免未限定编辑态 —— 新增态明细会变成非必填',
  );
}

console.log(
  fails
    ? `\n${fails} 项失败`
    : '\n全部通过（两端 mergeEditRow 真身行为一致 + 接线唯一 + 发货/收货 createOnly 配置 + 编辑态必填豁免在位）',
);
process.exit(fails ? 1 : 0);
