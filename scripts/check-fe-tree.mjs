#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 树形语义自检 —— scripts/check-fe-tree.mjs
 *
 * 用途：把树形工具的语义钉死在可执行断言里（两端都没有前端测试框架）：
 *   A) 表单树（React 独有，Angular 侧对应实现见 check-ng-tree-semantics.mjs）：
 *      1) buildTree 的子树成员表 = 自身 + 全部后代；
 *      2) toggleSubtree 勾父级 = 整棵子树一起增删，且**不回溯父级**（勾满子级不会补上父级）；
 *         这条守的是越权：库里存着父级而子级未授权的数据，一次点击不能把未授权的子级带出去；
 *      3) flattenIfTree 整树拍平打 __depth，非树响应原样返回（零拷贝）。
 *   B) 列表树折叠（**两端真身**跑同一批断言）：React apps/react/src/lib/tree.ts 与
 *      Angular apps/angular/src/app/pages/resource-page/columns.ts —— flattenTree 附
 *      __depth/__path/__kids、visibleRows 藏掉「祖先被折叠」的行（= 隐藏整棵子树）、
 *      toggleCollapsed 翻集合、rowKey 取标识；同一批夹具下两端的输出再逐字节比对。
 *      哪一端的字段名/函数名/语义漂了，这里直接红。
 *
 * 局限（如实记录）：跑的是纯函数真身，不起浏览器、不点箭头 —— 它证明语义两端一致，
 * 不证明页面上的真实表现（两端都没有 DOM 测试框架）。
 *
 * 用法：node scripts/check-fe-tree.mjs   —— 全过退出码 0，任一失败退出码 1。
 *   Angular 侧真身要经 @angular/core 解析：apps/angular/node_modules 缺失（未 npm install）
 *   时会直接抛错，不会假通过。
 */
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { fileURLToPath } from 'node:url';

// 两个源文件是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 保证脚本与 scripts/ 下其他自检一样 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

// Angular 的 columns.ts 是应用内模块，相对导入按 CLI 习惯不带扩展名（`../../core/format`），
// Node 的 ESM 解析器不认。这里就近补一层：xx → xx.ts、目录 → index.ts，只有本脚本受影响。
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

const REACT_TREE = new URL('../apps/react/src/lib/tree.ts', import.meta.url).href;
const NG_COLUMNS = new URL('../apps/angular/src/app/pages/resource-page/columns.ts', import.meta.url).href;
const react = await import(REACT_TREE);
const ng = await import(NG_COLUMNS);

let fails = 0;
const eq = (name, got, want) => {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: got ${JSON.stringify(got)}${ok ? '' : ` want ${JSON.stringify(want)}`}`);
};

/* ── 夹具：d(目录) → m1,m2,m3；另一分支 x（行形态即后端权限树：id/name/children） ── */
const ROWS = [
  { id: 'd', name: 'd', children: [{ id: 'm1', name: 'm1' }, { id: 'm2', name: 'm2' }, { id: 'm3', name: 'm3' }] },
  { id: 'x', name: 'x' },
];

/* ── A. 表单树：buildTree / toggleSubtree / flattenIfTree（React 端实现） ── */
const tree = react.buildTree(ROWS);
eq('buildTree：顶级两个节点，d 带三个子节点', tree.nodes.map((n) => n.key), ['d', 'x']);
eq('buildTree：节点名取 labelKey', tree.nodes[0].children.map((n) => n.label), ['m1', 'm2', 'm3']);
eq('buildTree：子树成员表 key → 自身 + 全部后代', tree.subtree['d'], ['d', 'm1', 'm2', 'm3']);
eq('buildTree：叶子子树只有自身', tree.subtree['x'], ['x']);
eq('buildTree：labelKey/valueKey 可换', react.buildTree([{ code: 'c', title: 't' }], 'title', 'code').nodes, [
  { key: 'c', label: 't', children: [] },
]);

// 1. 勾父级 = 整棵子树
let picks = react.toggleSubtree(tree, [], 'd');
eq('勾父级 d：整棵子树一起进集合', picks, ['d', 'm1', 'm2', 'm3']);

// 2. 清父级 = 整棵子树一起清
eq('清父级 d：整棵子树一起出集合', react.toggleSubtree(tree, picks, 'd'), []);

// 3. 点叶子只动自己，不回溯父级
eq('勾叶子 m1：只加自身，父级不被回溯', react.toggleSubtree(tree, [], 'm1'), ['m1']);

// 4. 勾满子级也不会补上父级（父级未授权就得显示未授权）
picks = ['m1', 'm2', 'm3'].reduce((acc, k) => react.toggleSubtree(tree, acc, k), []);
eq('逐个勾满子级：集合里没有父级 d', picks, ['m1', 'm2', 'm3']);

// 5. 库里存着父级、子级未授权：点击别的分支不得凭空补出未授权的子级
picks = react.toggleSubtree(tree, ['d'], 'x');
eq('点 x 分支：集合仍是 {d,x}，未授权的 m2/m3 没被带出来', picks, ['d', 'x']);

// 6. 不产生重复项（重复 key 会让后端 sync 报重复）
eq('重复勾同一节点不会产生重复项', react.toggleSubtree(tree, ['x'], 'x'), []);

// 7. flattenIfTree：整树拍平带 __depth、children 摘掉；非树响应零拷贝原样返回
const flatIf = react.flattenIfTree(ROWS);
eq('flattenIfTree：深度优先拍平顺序', flatIf.map((r) => r.id), ['d', 'm1', 'm2', 'm3', 'x']);
eq('flattenIfTree：按层级打 __depth', flatIf.map((r) => r.__depth), [0, 1, 1, 1, 0]);
eq('flattenIfTree：children 已摘掉', flatIf.some((r) => 'children' in r), false);
const plain = [{ id: 1 }];
eq('flattenIfTree：非树响应原样返回（同一引用）', react.flattenIfTree(plain) === plain, true);

/* ── B. 列表树折叠：两端真身跑同一批断言 ── */
/** 深层夹具：g → d → m1（折祖父要连隔代后代一起藏） */
const DEEP = [{ id: 'g', name: 'g', children: [{ id: 'd', name: 'd', children: [{ id: 'm1', name: 'm1' }] }] }];

const shared = (label, m) => {
  const flat = m.flattenTree(ROWS);
  eq(`${label} flattenTree：深度优先拍平顺序`, flat.map((r) => r.id), ['d', 'm1', 'm2', 'm3', 'x']);
  eq(`${label} flattenTree：按层级打 __depth`, flat.map((r) => r.__depth), [0, 1, 1, 1, 0]);
  eq(`${label} flattenTree：__path 是根到父的 key 链`, flat.map((r) => r.__path), [[], ['d'], ['d'], ['d'], []]);
  eq(`${label} flattenTree：__kids 只对非叶子为真`, flat.map((r) => r.__kids), [true, false, false, false, false]);
  eq(`${label} flattenTree：children 已摘掉`, flat.some((r) => 'children' in r), false);
  eq(`${label} flattenTree：原行的其它字段保留`, flat[0].name, 'd');
  eq(`${label} rowKey：取 id，缺 id 按空串（两端同口径）`, [m.rowKey(flat[0]), m.rowKey({})], ['d', '']);

  eq(`${label} visibleRows：空折叠集原样返回（同一引用）`, m.visibleRows(flat, new Set()) === flat, true);
  eq(`${label} visibleRows：折叠 d → 藏掉 d 的整棵子树`, m.visibleRows(flat, new Set(['d'])).map((r) => r.id), [
    'd',
    'x',
  ]);
  eq(`${label} visibleRows：折叠叶子 m1 → 一行都不少`, m.visibleRows(flat, new Set(['m1'])).length, 5);
  const deep = m.flattenTree(DEEP);
  eq(`${label} visibleRows：折叠祖父 g → 隔代（d 下）一并隐藏`, m.visibleRows(deep, new Set(['g'])).map((r) => r.id), [
    'g',
  ]);
  eq(`${label} visibleRows：折中层 d → 只留 g、d`, m.visibleRows(deep, new Set(['d'])).map((r) => r.id), ['g', 'd']);
  eq(`${label} visibleRows：非树行没有 __path，藏不住`, m.visibleRows([{ id: 'z' }], new Set(['z'])).map((r) => r.id), [
    'z',
  ]);

  const cur = new Set(['d']);
  const next = m.toggleCollapsed(cur, 'x');
  eq(`${label} toggleCollapsed：翻入新 key`, [...next].sort(), ['d', 'x']);
  eq(`${label} toggleCollapsed：不改入参（返回新集合）`, [...cur], ['d']);
  eq(`${label} toggleCollapsed：翻出已有的 key`, [...m.toggleCollapsed(next, 'x')].sort(), ['d']);
};

shared('React', react);
shared('Angular', ng);

/* ── C. 两端逐字节一致（字段名/函数名/语义漂了就红） ── */
const relFlat = react.flattenTree(ROWS);
const ngFlat = ng.flattenTree(ROWS);
eq('两端 flattenTree 输出逐字节相同', ngFlat, relFlat);
eq('两端 visibleRows（折叠 d）输出相同', ng.visibleRows(ngFlat, new Set(['d'])), react.visibleRows(relFlat, new Set(['d'])));
eq('两端 rowKey 相同（缺 id 的行）', ng.rowKey({}), react.rowKey({}));

/* ── D. 接线：折叠真的接在页面上（纯函数全对但没人调用同样白搭） ── */
const read = (rel) => readFileSync(new URL(rel, import.meta.url), 'utf8');
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};
const reactPage = read('../apps/react/src/components/ResourcePage.tsx');
const reactTable = read('../apps/react/src/components/DataTable.tsx');
const ngPage = read('../apps/angular/src/app/pages/resource-page/resource-page.ts');
const ngHtml = read('../apps/angular/src/app/pages/resource-page/resource-page.html');

ok(
  'React：ResourcePage 交给表格的行过 visibleRows，并把折叠集与回调透传',
  /rows=\{visibleRows\(rows, collapsed\)\}/.test(reactPage) &&
    /collapsed=\{collapsed\}/.test(reactPage) &&
    /onToggleCollapse=\{\(k\) => setCollapsed\(\(c\) => toggleCollapsed\(c, k\)\)\}/.test(reactPage),
  'ResourcePage.tsx 少了 rows={visibleRows(...)} / collapsed / onToggleCollapse 之一',
);
ok(
  'React：DataTable 只在分层列（indent + __depth）画箭头，且点击回传 key',
  /c\.indent && row\['__depth'\] !== undefined/.test(reactTable) && /onToggle\?\.\(key\)/.test(reactTable),
  'DataTable.tsx 的 TreeCaret 判据或回调断了',
);
ok(
  'Angular：sliceLocal 折叠在前、切页在后（跨页不留孤儿子行）',
  /visibleRows\(this\.local \?\? \[\], this\.collapsed\(\)\)/.test(ngPage),
  'resource-page.ts 的 sliceLocal 没接 visibleRows',
);
ok(
  'Angular：toggleCollapse 翻集合后按可见行重切当前页',
  /this\.collapsed\.set\(toggleCollapsed\(this\.collapsed\(\), key\)\)/.test(ngPage) && /if \(this\.local\) this\.sliceLocal\(\)/.test(ngPage),
  'resource-page.ts 的 toggleCollapse 没重切或没翻集合',
);
ok(
  'Angular：模板在分层列上给有子节点的行画箭头，叶子留同宽占位',
  /cell\.depth !== undefined/.test(ngHtml) &&
    /\(click\)="toggleCollapse\(r\.key\)"/.test(ngHtml) &&
    /class="tree-caret-gap"/.test(ngHtml),
  'resource-page.html 少了箭头 / 点击接线 / 叶子占位',
);

console.log(fails ? `\n${fails} 项失败` : '\n全部通过');
process.exit(fails ? 1 : 0);
