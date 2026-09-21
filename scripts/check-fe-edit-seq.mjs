#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * React 编辑弹框竞态守卫自检 —— scripts/check-fe-edit-seq.mjs
 *
 * 守两件事：
 *  1) 行为：apps/react/src/lib/seq.ts 的 seqGuard 语义（只认最后一次调用）——
 *     连点两行编辑时，先发后到的详情响应必须被丢弃，否则框里显示的是另一条记录，
 *     且「显示的值」与「提交用的 id」会来自两条记录。
 *  2) 接线：ResourcePage.tsx 里**所有**改变编辑态的入口都过这道守卫 ——
 *     openEdit 的 setEditing 在 isCurrent 判定之下，新增/关闭/保存成功走 editTo（先 bump），
 *     全文件不得出现第三个裸 setEditing 调用点。这句是防「为了省一次请求/图快」把守卫删掉。
 *
 * 局限（如实记录）：本机没有浏览器/DOM 测试运行器，第 2 项是**静态结构检查**，
 * 只证明接线的形状，不证明点击行为；真正的点击链路由人工/联调覆盖。
 *
 * 用法：node scripts/check-fe-edit-seq.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

// seq.ts 是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 与 scripts/check-fe-tree.mjs 同款，保证 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

let fails = 0;
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};

/* ── 1. seqGuard 行为 ── */

const { seqGuard } = await import(new URL('../apps/react/src/lib/seq.ts', import.meta.url).href);

// 单次调用：自己的响应生效
let g = seqGuard();
const s1 = g.bump();
ok('单次调用：自己的响应生效', g.isCurrent(s1) === true);

// 连点两行编辑：A 在飞 → B 在飞，A 先发后到 → A 的响应必须作废、B 的生效
g = seqGuard();
const a = g.bump();
const b = g.bump();
ok('连点两行：先发的 A 响应被丢弃（不能把 A 填进 B 的框）', g.isCurrent(a) === false);
ok('连点两行：后发的 B 响应生效', g.isCurrent(b) === true);

// 期间点了「新增」/「关闭」：在飞的详情响应全部作废，不能把列表行回填成编辑态
g = seqGuard();
const c = g.bump();
g.bump(); // editTo('new') / editTo(null)
ok('新增或关闭后：在飞的详情响应作废（不会把框顶回旧记录）', g.isCurrent(c) === false);

// 作废不是一次性的：之后新开的编辑照常生效
const d = g.bump();
ok('作废之后新开的编辑仍然生效', g.isCurrent(d) === true);

// 同一个守卫实例内序号单调递增不重号（跨实例从 0 重新计数，本就是这样设计的）
const g2 = seqGuard();
const seqs = [g2.bump(), g2.bump(), g2.bump()];
ok('同一守卫内序号单调递增不重号', seqs[0] === 1 && seqs[1] === 2 && seqs[2] === 3, `实际 ${seqs.join(',')}`);

/* ── 2. ResourcePage 接线（静态结构） ── */

const SRC = 'apps/react/src/components/ResourcePage.tsx';
const src = fs.readFileSync(new URL(`../${SRC}`, import.meta.url), 'utf8');
const body = (name) => {
  const m = new RegExp(`const ${name}[\\s\\S]*?\\n  \\};`).exec(src);
  return m ? m[0] : '';
};

const openEdit = body('openEdit');
const editTo = body('editTo');
ok(`${SRC}：openEdit 先 bump 取号`, /bump\(\)/.test(openEdit), '未找到 bump()');
ok(`${SRC}：openEdit 的 setEditing 在 isCurrent 判定之下`, /isCurrent\(seq\)[^\n]*setEditing\(/.test(openEdit));
ok(
  `${SRC}：openEdit 只有一处 setEditing（try 与 catch 共用一次判定）`,
  (openEdit.match(/setEditing\(/g) ?? []).length === 1,
);
ok(`${SRC}：editTo 先 bump 再改编辑态`, /bump\(\)[\s\S]*setEditing\(/.test(editTo));
ok(
  `${SRC}：全文件仅两处裸 setEditing（openEdit / editTo 各一），新增/关闭/保存成功不得绕过`,
  (src.match(/setEditing\(/g) ?? []).length === 2,
  `实际 ${(src.match(/setEditing\(/g) ?? []).length} 处`,
);
ok(`${SRC}：序号守卫是 per 实例的 useRef`, /useRef\(seqGuard\(\)\)/.test(src));
ok(
  `${SRC}：新增/关闭/保存成功三个入口都走 editTo`,
  (src.match(/editTo\(/g) ?? []).length >= 3,
  `实际 ${(src.match(/editTo\(/g) ?? []).length} 处（三个调用点：新增/关闭/保存成功）`,
);

console.log(
  fails
    ? `\n${fails} 项失败`
    : '\n全部通过（第 1 组为行为断言，第 2 组为静态接线断言 —— 点击链路仍需联调）',
);
process.exit(fails ? 1 : 0);
