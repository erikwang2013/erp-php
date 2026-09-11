#!/usr/bin/env node
// ============================================================
// ng-zorro 权限树勾选语义自检 — scripts/check-ng-tree-semantics.mjs
// ------------------------------------------------------------
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 用途：把 apps/angular/src/app/pages/resource-page/resource-form.ts 依赖的
//   ng-zorro 树勾选语义钉死在可执行断言里。Angular 端没装测试运行器（package.json
//   的 test 是 ng test，但 devDependencies 里没有 karma/jasmine/jest），
//   所以这脚本直接用 Node 驱动库的 ESM 产物，不经过框架、不起浏览器。
//
// 守的是「越权」这条：非 strict 模式下库会把父级勾选级联到未授权的子节点，
//   直接吃库发射的 checkedKeys 就会把没授过的权限提交上去。resource-form 因此
//   用 nzCheckStrictly + 按被点节点子树增删的 delta 集合，本脚本逐条验证该方案。
//
// 用法：node scripts/check-ng-tree-semantics.mjs
//   全部通过退出码 0，任一失败退出码 1。
//   node_modules 缺失（未 npm install）时会直接抛错，不会假通过。
// ============================================================

// 库是部分编译产物，必须先加载 JIT 编译器。
// 路径走 import.meta.url 相对定位，脚本挪目录也不会解析不到裸包名。
const NM = new URL('../apps/angular/node_modules/', import.meta.url).href;
await import(`${NM}@angular/compiler/fesm2022/compiler.mjs`);
const { NzTreeBase } = await import(`${NM}ng-zorro-antd/fesm2022/ng-zorro-antd-core-tree.mjs`);
const { NzTreeService } = await import(`${NM}ng-zorro-antd/fesm2022/ng-zorro-antd-tree.mjs`);

let fails = 0;
const eq = (name, got, want) => {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: got ${JSON.stringify(got)}${ok ? '' : ` want ${JSON.stringify(want)}`}`);
};

// d(目录) -> m1,m2,m3；另一分支 x
const RAW = [
  { title: 'd', key: 'd', children: [{ title: 'm1', key: 'm1' }, { title: 'm2', key: 'm2' }, { title: 'm3', key: 'm3' }] },
  { title: 'x', key: 'x' },
];
const KEYS = ['d', 'm1', 'm2', 'm3', 'x'];
const subs = { d: ['d', 'm1', 'm2', 'm3'], m1: ['m1'], m2: ['m2'], m3: ['m3'], x: ['x'] };

const mk = () => {
  const svc = new NzTreeService();
  svc.initTree(new NzTreeBase(svc).coerceTreeNodes(RAW));
  return svc;
};
const base = (svc) => new NzTreeBase(svc);
const node = (svc, k) => base(svc).getTreeNodeByKey(k);
// 组件的 [nzCheckedKeys] 重下发
const rebind = (svc, picks, strictly) => svc.conductCheck(picks, strictly);
// 库内 clickCheckbox + eventTriggerChanged('check')：strictly 时不做 conduct
const click = (svc, k, strictly) => {
  const n = node(svc, k);
  n.isChecked = !n.isChecked;
  n.isHalfChecked = false;
  svc.setCheckedNodeList(n);
  if (!strictly) svc.conduct(n);
  return n;
};
// 组件的 onTreeCheck()：按被点节点的子树增删 vals 里的集合
const delta = (cur, n) => {
  const out = new Set(cur);
  for (const k of subs[String(n.key)] ?? [String(n.key)]) (n.isChecked ? out.add(k) : out.delete(k));
  return [...out];
};
const checked = (svc) => KEYS.filter((k) => node(svc, k).isChecked);
const half = (svc) => KEYS.filter((k) => node(svc, k).isHalfChecked);

// ── 1. 非 strict：seed 含父级 → conductCheck 走 setter → checkedNodeList → conductDown → 整棵子树被勾 ──
let s = mk();
rebind(s, ['d', 'm1'], false);
console.log('非 strict seed {d,m1} →', checked(s));
eq('非 strict：seed 父级会级联把未授权的 m2/m3 也勾上', [node(s, 'm2').isChecked, node(s, 'm3').isChecked], [true, true]);

// ── 2. 发射值（getCheckedNodeKeys）在别的分支点一下就带上未授权的 m2：所以不能当提交值 ──
const s2 = mk();
rebind(s2, ['d', 'm1'], false);
click(s2, 'x', false);
const emitted = s2.getCheckedNodeKeys().map(String).sort();
eq('非 strict：一次点击后发射值含未授权的 m2（直接吃发射值 = 越权）', emitted.includes('m2'), true);

// ── 3. strict：seed 不级联，渲染状态 == vals 集合（「库里有父级、子级未授权」能如实显示）──
s = mk();
rebind(s, ['d', 'm1'], true);
eq('strict：seed {d,m1} 后只有 d/m1 被勾', checked(s), ['d', 'm1']);
eq('strict：m2 可被单独管理（没有虚假勾选）', node(s, 'm2').isChecked, false);

// ── 4. strict + delta：点 m2（未勾→勾）只加自身，父级不被回溯 ──
const s4 = mk();
rebind(s4, ['d', 'm1'], true);
let picks = delta(['d', 'm1'], click(s4, 'm2', true));
rebind(s4, picks, true);
eq('strict：点 m2 后集合', picks.slice().sort(), ['d', 'm1', 'm2']);
eq('strict：渲染 == 集合', checked(s4), ['d', 'm1', 'm2']);

// ── 5. strict：点父级（已勾→清）整棵子树一起清 ──
picks = delta(picks, click(s4, 'd', true));
rebind(s4, picks, true);
eq('strict：清父级后集合', picks, []);
eq('strict：清父级后渲染全清', checked(s4), []);

// ── 6. strict：自动勾满子级不会把父级算进来（不回溯父级 = Flutter 同规则）──
const s6 = mk();
let p6 = [];
for (const k of ['m1', 'm2', 'm3']) {
  p6 = delta(p6, click(s6, k, true));
  rebind(s6, p6, true);
}
eq('strict：逐个勾满子级后集合只有三叶子', p6.slice().sort(), ['m1', 'm2', 'm3']);
eq('strict：此时父级 d 未被勾、也没有半选残留', [node(s6, 'd').isChecked, ...half(s6)], [false]);

// ── 7. strict：连点两个叶子，第一个不弹回（architect 的必做自检）──
const s7 = mk();
let p7 = [];
p7 = delta(p7, click(s7, 'x', true));
rebind(s7, p7, true);
p7 = delta(p7, click(s7, 'm3', true));
rebind(s7, p7, true);
eq('strict：连点两位后都还在', [node(s7, 'x').isChecked, node(s7, 'm3').isChecked], [true, true]);
eq('strict：连点后集合', p7.slice().sort(), ['m3', 'x']);

// ── 8. 单选：数据后到那一帧。组件绑的 selectedKeys 是 [String(cur)] 字面量（每次重算都是新引用），
//        Angular 会重触发输入；库内顺序是 nzData → … → nzSelectedKeys，所以先 initTree 再选。──
const s8 = new NzTreeService();
s8.initTree(new NzTreeBase(s8).coerceTreeNodes([])); // 首帧：树还空（父级这时选不上，正常）
s8.conductSelectedKeys(['d'], false);
s8.initTree(new NzTreeBase(s8).coerceTreeNodes(RAW)); // 数据到达帧
s8.conductSelectedKeys(['d'], false); // 同帧稍后的 nzSelectedKeys
eq('单选：数据到达后父级被预选中', base(s8).getTreeNodeByKey('d').isSelected, true);

// ── 9. 单选：改选别的节点旧的不残留（conductSelectedKeys 先清 selectedNodeList）──
s8.conductSelectedKeys(['m2'], false);
eq('单选：改选 m2 后父级不再选中', base(s8).getTreeNodeByKey('d').isSelected, false);
eq('单选：m2 选中', base(s8).getTreeNodeByKey('m2').isSelected, true);
s8.conductSelectedKeys([], false);
eq('单选：空 keys（=顶级）把选中清干净', KEYS.filter((k) => node(s8, k).isSelected), []);

console.log(fails ? `\n${fails} 项失败` : '\n全部通过');
process.exit(fails ? 1 : 0);
