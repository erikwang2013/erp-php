#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 规格属性解析器自检：`apps/angular/src/app/pages/resource-page/columns.ts`
 *
 * 后端 `spec_attrs` 是 json_encode 存进 TEXT 的字符串，线上取值很脏
 * （`[]` / `""` / `null` / 嵌套对象 / 非 JSON 全有）。解析器要从源码里抽出来跑真身，
 * 不复制一份 —— 复制的副本过了不算数。Angular 端没有测试运行器，这里用 Node 直跑纯函数。
 *
 * 用法：node scripts/check-ng-spec-attrs.mjs   —— 全过 exit 0，有不符 exit 1
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const FILE = path.join(
  path.dirname(fileURLToPath(import.meta.url)),
  '..',
  'apps/angular/src/app/pages/resource-page/columns.ts',
);
const START = '/* spec-attrs:start';
const END = '/* spec-attrs:end */';

/** 抽标记段并剥掉 TS 注解 —— 只覆盖该段实际用到的注解词表，其它注解出现即报错 */
function loadSpec() {
  const src = fs.readFileSync(FILE, 'utf8');
  const from = src.indexOf(START);
  const to = src.indexOf(END);
  if (from < 0 || to < 0) throw new Error(`未找到标记段 ${START} … ${END}`);
  const seg = src.slice(from, to);
  if (!seg.includes('function specTags')) throw new Error('标记段里没有 specTags');
  const js = seg
    .replace(/\bexport\s+/g, '')
    .replace(/: unknown/g, '')
    .replace(/: string\[\]/g, '')
    .replace(/: string/g, '')
    .replace(/ as Record<string, unknown>/g, '');
  try {
    return new Function(
      `${js}\nreturn { specTags, specGroups, specRows, specJson, specCompose, SPEC_MAX };`,
    )();
  } catch (e) {
    console.error(`FAIL 剥注解后无法求值（注解词表漂了？）：\n${js}`);
    throw e;
  }
}

const { specTags, specGroups, specRows, specJson, specCompose, SPEC_MAX } = loadSpec();

let bad = 0;
let total = 0;

/** 跑一组 [输入, 期望] 用例；deepStrictEqual 语义走 JSON 比较，顺带查 [object Object] */
function run(name, fn, cases) {
  console.log(`\n── ${name} ──`);
  for (const [input, want] of cases) {
    total++;
    let got;
    try {
      got = fn(input);
    } catch (e) {
      got = `抛异常 ${e.message}`;
    }
    const ok = JSON.stringify(got) === JSON.stringify(want);
    if (!ok) bad++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${JSON.stringify(input)} → ${JSON.stringify(got)}`);
    if (!ok) console.error(`     期望 ${JSON.stringify(want)}`);
    if (JSON.stringify(got).includes('[object Object]')) {
      bad++;
      console.error('     FAIL 出现 [object Object]');
    }
  }
}

/** [输入, 期望]；输入保持原值形态（含非字符串），断言走 deepStrictEqual 语义 */
run('specTags 列表/详情胶囊', specTags, [
  ['{"颜色":"红","尺寸":"XL"}', ['颜色:红', '尺寸:XL']], // 正常
  ['{"颜色":["红","蓝"],"尺寸":["S","M","L"]}', ['颜色:红/蓝', '尺寸:S/M/L']], // 契约形态
  ['{"a":["x",["y","z"]]}', ['a:x/y/z']], // 嵌套数组摊平
  ['{"a":[{"b":1}]}', ['a:{"b":1}']], // 数组内对象降级 JSON 原文
  ['{"颜色":[]}', ['颜色:']], // 空值组：键还在，值空
  ['[]', []], // 后端默认值
  ['', []],
  [null, []],
  ['{"a":{"b":1}}', ['a:{"b":1}']], // 嵌套对象不能变 [object Object]
  ['not-json', ['not-json']], // 脏数据原样可见
  ['null', []], // 字符串 'null'
  ['{}', []],
  [undefined, []],
  ['   ', []],
  ['  {"k":"v"}  ', ['k:v']], // 前后空白
  ['{"颜色":0}', ['颜色:0']], // 0 值照常显示（不能用真值判定）
  ['{"a":false}', ['a:false']],
  ['{"a":[0,false]}', ['a:0/false']], // 数组里的 0/false 也不能被真值判定吃掉
  ['{"a":null}', ['a:']],
  ['123', ['123']],
  ['[1,2]', ['[1,2]']],
  [{ 颜色: '红' }, ['颜色:红']], // 后端哪天直接给对象也兜住
  [[{ a: 1 }], ['[{"a":1}]']],
]);

run('specGroups 属性组（编辑器数据源）', specGroups, [
  ['{"颜色":["红","蓝"],"尺寸":[]}', [{ k: '颜色', vs: ['红', '蓝'] }, { k: '尺寸', vs: [] }]],
  ['{"a":1}', [{ k: 'a', vs: ['1'] }]], // 脏标量按单元素
  ['{}', []],
  ['[]', []],
  ['not-json', []], // 列表要渲染，坏数据不进编辑器
  ['blue', []], // 顶层标量不是属性组
]);

run('specRows 编辑器行', specRows, [
  ['{"颜色":["红","蓝"],"尺寸":["XL"]}', [{ k: '颜色', v: '红/蓝' }, { k: '尺寸', v: 'XL' }]],
  ['{"颜色":[]}', [{ k: '颜色', v: '' }]],
  ['{}', []],
]);

run('specJson 编辑器行 → 契约 JSON', specJson, [
  [[{ k: '颜色', v: '红/蓝' }], '{"颜色":["红","蓝"]}'], // 值恒为字符串数组
  [[{ k: '颜色', v: ' 红 / 蓝 ' }], '{"颜色":["红","蓝"]}'], // 逐值 trim、丢空段
  [[{ k: '颜色', v: '' }], '{}'], // 空值组丢弃
  [[{ k: '', v: '红' }], '{}'], // 空属性名跳过
  [[], '{}'], // 服务端约定的空值形态（NULL 会被 fillableOnly 过滤掉）
  ['not-array', '{}'],
  [undefined, '{}'],
  [[{ k: ' 颜色 ', v: '红' }], '{"颜色":["红"]}'], // 属性名也 trim
]);

run('specCompose 选中项 → 商品 spec 字符串', specCompose, [
  [
    [
      { k: '颜色', vs: ['红', '蓝'] },
      { k: '尺寸', vs: ['XL'] },
    ],
    '颜色:红/蓝 尺寸:XL',
  ],
  [[{ k: '尺寸', vs: [] }], ''], // 空组不进串
  [[{ k: '', vs: ['红'] }], ''],
  [[], ''],
  ['not-array', ''],
]);

/** 往返：编辑器行 → 契约 JSON → 解析回行，值列表必须原样回来 */
console.log('\n── 往返 specRows → specJson → specRows ──');
{
  const rows = [
    { k: '颜色', v: '红/蓝' },
    { k: '尺寸', v: 'S/M/L' },
  ];
  total++;
  const got = specRows(specJson(rows));
  const ok = JSON.stringify(got) === JSON.stringify(rows);
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${JSON.stringify(rows)} → ${specJson(rows)} → ${JSON.stringify(got)}`);
}

/** 超长门禁：resource-form 提交时按 SPEC_MAX 判定，恰好 200 放行、201 拒绝 */
console.log('\n── 超长门禁（SPEC_MAX）──');
{
  const mk = (n) => specCompose([{ k: 'a', vs: ['x'.repeat(n)] }]);
  const at = mk(SPEC_MAX - 2); // 'a:' 占 2 字符
  const over = mk(SPEC_MAX - 1);
  const checks = [
    ['SPEC_MAX === 200（对齐 erp_product.spec VARCHAR(200)）', SPEC_MAX === 200],
    [`恰好 ${SPEC_MAX} 字符放行`, at.length === SPEC_MAX],
    [`${SPEC_MAX + 1} 字符超限`, over.length === SPEC_MAX + 1],
  ];
  for (const [label, ok] of checks) {
    total++;
    if (!ok) bad++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${label}`);
  }
}

if (bad) {
  console.error(`\nFAIL ${bad} 项不符（共 ${total} 例）`);
  process.exit(1);
}
console.log(`\nPASS ${total} 例全过，无异常、无 [object Object]`);
