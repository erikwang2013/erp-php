#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Angular 词典完整性检查：`apps/angular/src/app/core/zh-en/part*.ts`
 *
 * 词典由 index.ts 展开合并（后写覆盖先写），而 TS1117「同一对象字面量不得重名」
 * 只拦得住单文件内的重复 —— 跨切片的重复词条会静默覆盖，只有这里能拦。
 * 顺带守住「单文件 < 500 行」规矩（词典正是为这条规矩才切的片）。
 *
 * 用法：node scripts/check-ng-i18n-dict.mjs   —— 全过 exit 0，有重复 exit 1
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = path.join(
  path.dirname(fileURLToPath(import.meta.url)),
  '..',
  'apps/angular/src/app/core/zh-en',
);
const MAX_LINES = 500;

/** 取切片文件里的对象字面量（纯数据，无依赖）并返回其键 */
function keysOf(file) {
  const src = fs.readFileSync(path.join(DIR, file), 'utf8');
  const obj = new Function(`return ${src.slice(src.indexOf('{', src.indexOf('= {')), src.lastIndexOf('};') + 1)}`)();
  return Object.keys(obj);
}

const files = fs.readdirSync(DIR).filter((f) => /^part\d+\.ts$/.test(f)).sort();
if (files.length === 0) {
  console.error(`FAIL 未找到词典切片：${DIR}`);
  process.exit(1);
}

const owner = new Map();
const dup = [];
const tooLong = [];
for (const f of files) {
  const lines = fs.readFileSync(path.join(DIR, f), 'utf8').split('\n').length;
  if (lines > MAX_LINES) tooLong.push(`${f} ${lines} 行`);
  for (const k of keysOf(f)) {
    if (owner.has(k)) dup.push(`'${k}'  ← ${owner.get(k)} / ${f}`);
    else owner.set(k, f);
  }
}

console.log(`${files.length} 个切片，共 ${owner.size} 条词条`);
if (dup.length) {
  console.error(`FAIL 跨切片重复词条 ${dup.length} 条（后写者静默覆盖）：\n${dup.join('\n')}`);
  process.exit(1);
}
if (tooLong.length) {
  console.error(`FAIL 切片超 ${MAX_LINES} 行：${tooLong.join('、')}`);
  process.exit(1);
}
console.log('PASS 无重复词条，切片长度均在限内');
