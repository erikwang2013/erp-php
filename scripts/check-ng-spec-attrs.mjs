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
function loadSpecTags() {
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
    return new Function(`${js}\nreturn specTags;`)();
  } catch (e) {
    console.error(`FAIL 剥注解后无法求值（注解词表漂了？）：\n${js}`);
    throw e;
  }
}

const specTags = loadSpecTags();

/** [输入, 期望]；输入保持原值形态（含非字符串），断言走 deepStrictEqual 语义 */
const CASES = [
  ['{"颜色":"红","尺寸":"XL"}', ['颜色:红', '尺寸:XL']], // 正常
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
  ['{"a":null}', ['a:']],
  ['123', ['123']],
  ['[1,2]', ['[1,2]']],
  [{ 颜色: '红' }, ['颜色:红']], // 后端哪天直接给对象也兜住
  [[{ a: 1 }], ['[{"a":1}]']],
];

let bad = 0;
for (const [input, want] of CASES) {
  let got;
  try {
    got = specTags(input);
  } catch (e) {
    got = `抛异常 ${e.message}`;
  }
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${JSON.stringify(input)} → ${JSON.stringify(got)}`);
  if (!ok) console.error(`     期望 ${JSON.stringify(want)}`);
  if (String(got).includes('[object Object]')) {
    bad++;
    console.error('     FAIL 出现 [object Object]');
  }
}

if (bad) {
  console.error(`FAIL ${bad} 项不符（共 ${CASES.length} 例）`);
  process.exit(1);
}
console.log(`PASS ${CASES.length} 例全过，无异常、无 [object Object]`);
