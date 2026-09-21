#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * React 树工具自检 —— scripts/check-fe-tree.mjs
 *
 * 用途：把 apps/react/src/lib/tree.ts 的三条语义钉死在可执行断言里（React 端没有测试框架）：
 *   1) buildTree 的子树成员表 = 自身 + 全部后代；
 *   2) toggleSubtree 勾父级 = 整棵子树一起增删，且**不回溯父级**（勾满子级不会补上父级）；
 *      这条守的是越权：库里存着父级而子级未授权的数据，一次点击不能把未授权的子级带出去；
 *   3) flattenIfTree 整树拍平打 __depth，非树响应原样返回（零拷贝）。
 * 语义与 Angular 端一致，见 scripts/check-ng-tree-semantics.mjs（那边跑的是库真身，这边是纯函数）。
 *
 * 用法：node scripts/check-fe-tree.mjs   —— 全过退出码 0，任一失败退出码 1。
 */
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// tree.ts 是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 保证脚本与 scripts/ 下其他自检一样 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

const TREE_TS = new URL('../apps/react/src/lib/tree.ts', import.meta.url).href;
const { buildTree, flattenIfTree, toggleSubtree } = await import(TREE_TS);

let fails = 0;
const eq = (name, got, want) => {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: got ${JSON.stringify(got)}${ok ? '' : ` want ${JSON.stringify(want)}`}`);
};

// d(目录) -> m1,m2,m3；另一分支 x（行形态即后端权限树：id/name/children）
const ROWS = [
  { id: 'd', name: 'd', children: [{ id: 'm1', name: 'm1' }, { id: 'm2', name: 'm2' }, { id: 'm3', name: 'm3' }] },
  { id: 'x', name: 'x' },
];

const tree = buildTree(ROWS);
eq('buildTree：顶级两个节点，d 带三个子节点', tree.nodes.map((n) => n.key), ['d', 'x']);
eq('buildTree：节点名取 labelKey', tree.nodes[0].children.map((n) => n.label), ['m1', 'm2', 'm3']);
eq('buildTree：子树成员表 key → 自身 + 全部后代', tree.subtree['d'], ['d', 'm1', 'm2', 'm3']);
eq('buildTree：叶子子树只有自身', tree.subtree['x'], ['x']);
eq('buildTree：labelKey/valueKey 可换', buildTree([{ code: 'c', title: 't' }], 'title', 'code').nodes, [
  { key: 'c', label: 't', children: [] },
]);

// 1. 勾父级 = 整棵子树
let picks = toggleSubtree(tree, [], 'd');
eq('勾父级 d：整棵子树一起进集合', picks, ['d', 'm1', 'm2', 'm3']);

// 2. 清父级 = 整棵子树一起清
eq('清父级 d：整棵子树一起出集合', toggleSubtree(tree, picks, 'd'), []);

// 3. 点叶子只动自己，不回溯父级
eq('勾叶子 m1：只加自身，父级不被回溯', toggleSubtree(tree, [], 'm1'), ['m1']);

// 4. 勾满子级也不会补上父级（父级未授权就得显示未授权）
picks = ['m1', 'm2', 'm3'].reduce((acc, k) => toggleSubtree(tree, acc, k), []);
eq('逐个勾满子级：集合里没有父级 d', picks, ['m1', 'm2', 'm3']);

// 5. 库里存着父级、子级未授权：点击别的分支不得凭空补出未授权的子级
picks = toggleSubtree(tree, ['d'], 'x');
eq('点 x 分支：集合仍是 {d,x}，未授权的 m2/m3 没被带出来', picks, ['d', 'x']);

// 6. 不产生重复项（重复 key 会让后端 sync 报重复）
eq('重复勾同一节点不会产生重复项', toggleSubtree(tree, ['x'], 'x'), []);

// 7. flattenIfTree：整树拍平带 __depth、children 摘掉；非树响应零拷贝原样返回
const flat = flattenIfTree(ROWS);
eq('flattenIfTree：深度优先拍平顺序', flat.map((r) => r.id), ['d', 'm1', 'm2', 'm3', 'x']);
eq('flattenIfTree：按层级打 __depth', flat.map((r) => r.__depth), [0, 1, 1, 1, 0]);
eq('flattenIfTree：children 已摘掉', flat.some((r) => 'children' in r), false);
eq('flattenIfTree：原行的其它字段保留', flat[0].name, 'd');
const plain = [{ id: 1 }];
eq('flattenIfTree：非树响应原样返回（同一引用）', flattenIfTree(plain) === plain, true);

console.log(fails ? `\n${fails} 项失败` : '\n全部通过');
process.exit(fails ? 1 : 0);
