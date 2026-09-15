#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 后端词典多语生成器 —— 以 zh_CN 为源，生成 resource/translations/<locale>/{common,modules,validation}.php。
 *
 * 用法：
 *   node scripts/gen-be-locales.mjs --list
 *   node scripts/gen-be-locales.mjs --locale ja --limit 20   # 冒烟
 *   node scripts/gen-be-locales.mjs --all                    # 全量（可断点续跑）
 *
 * **两类文件的处理方式不同，这是本脚本最容易错的地方**：
 *  - `common.php` / `modules.php`：**英文即 key**（键是英文原文）。译文只需翻「键」本身，
 *    故请求里发的是英文键；`en` 语种这两个文件**留空**——引擎查不到会直接返回键，即英文原文。
 *  - `validation.php`：**框架按规则名取值**（`required` / `string` …），键必须原样保留，
 *    只翻**值**。若按英文即 key 处理会把规则名改掉，框架查不到就直接显示 `validation.required`（踩过）。
 *
 * 其余同 gen-fe-locales：断点续跑（每条即时落盘）、凭证只读不打印、输出文件勿手工编辑。
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const T = path.join(ROOT, 'resource/translations');
const CACHE = '/tmp/be-locales-cache';
const BATCH = 40;
const CONCURRENCY = 5;

const LOCALES = {
  en: 'English', ja: 'Japanese', ko: 'Korean', de: 'German', fr: 'French', es: 'Spanish',
  pt: 'Portuguese', ru: 'Russian', ar: 'Arabic', hi: 'Hindi', bn: 'Bengali', id: 'Indonesian',
};

/** 解析前端同款的 PHP 词典（仅取 'k' => 'v', 形式的单行条目） */
function parsePhp(file) {
  const out = {};
  if (!fs.existsSync(file)) return out;
  for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
    const m = line.match(/^\s*'((?:[^'\\]|\\.)+)'\s*=>\s*'((?:[^'\\]|\\.)*)',?\s*$/);
    if (m) out[m[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\')] = m[2].replace(/\\'/g, "'").replace(/\\\\/g, '\\');
  }
  return out;
}
const phpEscape = (s) => `'${String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
const HEAD = `<?php\n\n/*\n * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz\n */\n\ndeclare(strict_types=1);\n\n`;

function creds() {
  const st = JSON.parse(fs.readFileSync(path.join(os.homedir(), '.claude/settings.json'), 'utf8'));
  const env = st.env ?? {};
  const base = String(env.ANTHROPIC_BASE_URL ?? '').replace(/\/$/, '');
  const token = String(env.ANTHROPIC_AUTH_TOKEN ?? '');
  const model = String(env.ANTHROPIC_MODEL ?? '');
  if (!base || !token || !model) throw new Error('网关凭证缺失');
  return { base, token, model };
}

async function translate(items, langName, c) {
  const r = await fetch(`${c.base}/v1/messages`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'anthropic-version': '2023-06-01', authorization: `Bearer ${c.token}` },
    body: JSON.stringify({
      model: c.model,
      // 本网关的模型是思考型：**思考 token 也计入 max_tokens**，给小了会截断输出
      // 导致 JSON 解析失败（曾整批 20 条全失败）。给足预算，并靠下面的二分重试兜底。
      max_tokens: 16000,
      system:
        `You are a professional software translator for an ERP system's server-side messages.\n` +
        `Translate into ${langName}. Input is a JSON object id -> English text. ` +
        `Reply with ONLY a JSON object with the same ids and translated values. No fences, no prose. ` +
        `Keep placeholders like :attribute :name :count :max and {x} verbatim. Short and natural.`,
      messages: [{ role: 'user', content: JSON.stringify(items) }],
    }),
  });
  if (!r.ok) throw new Error(`HTTP ${r.status}: ${(await r.text()).slice(0, 160)}`);
  const j = await r.json();
  const text = (j.content ?? []).filter((b) => b.type === 'text').map((b) => b.text).join('');
  return JSON.parse(text.replace(/^```(?:json)?/m, '').replace(/```\s*$/m, '').trim());
}

/** 逐文件翻一批 key→source 文本；cache 结构 { '<file>::<key>': 译文 } */
async function runFile(loc, langName, file, srcMap, cache, c) {
  const todo = Object.keys(srcMap).filter((k) => typeof cache[`${file}::${k}`] !== 'string');
  const batches = [];
  for (let i = 0; i < todo.length; i += BATCH) batches.push(todo.slice(i, i + BATCH));
  let failed = 0;
  /** 发一批；失败则**二分重试**（截断/超时都能自愈），小到 5 条仍失败才计入 failed */
  const send = async (list) => {
    if (list.length === 0) return;
    const items = Object.fromEntries(list.map((k, i) => [String(i), srcMap[k]]));
    try {
      const out = await translate(items, langName, c);
      list.forEach((k, i) => {
        const v = out[String(i)];
        if (typeof v === 'string' && v.trim() !== '') cache[`${file}::${k}`] = v;
        else failed++;
      });
      fs.writeFileSync(`${CACHE}/${loc}.json`, JSON.stringify(cache));
    } catch (e) {
      if (list.length <= 5) {
        failed += list.length;
        console.log(`    ! ${file} 小批失败：${String(e.message).slice(0, 120)}`);
        return;
      }
      const mid = Math.ceil(list.length / 2);
      await send(list.slice(0, mid));
      await send(list.slice(mid));
    }
  };
  const worker = async () => {
    for (;;) {
      const b = batches.shift();
      if (!b) return;
      await send(b);
    }
  };
  await Promise.all(Array.from({ length: CONCURRENCY }, worker));
  return { total: Object.keys(srcMap).length, failed };
}

const args = process.argv.slice(2);
const arg = (k, d) => (args.indexOf(k) === -1 ? d : args[args.indexOf(k) + 1]);
fs.mkdirSync(CACHE, { recursive: true });
const common = parsePhp(`${T}/zh_CN/common.php`);
const modules = parsePhp(`${T}/zh_CN/modules.php`);
const install = parsePhp(`${T}/zh_CN/install.php`);
const validation = parsePhp(`${T}/zh_CN/validation.php`);
/**
 * validation.php 里的 `attributes` 是**嵌套数组**（字段名 → 显示名），扁平解析器拿不到它，
 * 早期版本因此把这一段从各语种文件里整块丢掉（回归）。这里从源文件抽出原文，生成时逐字补回 ——
 * 它是否翻译属独立问题；原样保留至少不比"回退中文"更差。
 */
const ATTR_BLOCK = (() => {
  const src = fs.readFileSync(`${T}/zh_CN/validation.php`, 'utf8');
  const m = src.match(/\n\s*'attributes'\s*=>\s*\[[\s\S]*?\n\s*\],?\n/);
  return m ? m[0].trimEnd() : '';
})();

if (args.includes('--list')) {
  console.log(`源：common ${Object.keys(common).length} / modules ${Object.keys(modules).length} / install ${Object.keys(install).length} / validation ${Object.keys(validation).length}`);
  for (const code of Object.keys(LOCALES)) {
    const f = `${CACHE}/${code}.json`;
    const n = fs.existsSync(f) ? Object.keys(JSON.parse(fs.readFileSync(f, 'utf8'))).length : 0;
    console.log(`  ${code}: 已译 ${n}`);
  }
  process.exit(0);
}

const targets = args.includes('--all') ? Object.keys(LOCALES) : [arg('--locale', 'ja')];
const limit = Number(arg('--limit', '0')) || 0;
const c = creds();

for (const loc of targets) {
  const cf = `${CACHE}/${loc}.json`;
  const cache = fs.existsSync(cf) ? JSON.parse(fs.readFileSync(cf, 'utf8')) : {};
  const langName = LOCALES[loc];
  // common/modules：英文即 key → 源文本就是键本身（en 留空）；validation：源文本是中文值，键保留
  const work = [
    ['common', loc === 'en' ? {} : common],
    ['modules', loc === 'en' ? {} : modules],
    ['install', loc === 'en' ? {} : install],
    ['validation', Object.fromEntries(Object.entries(validation))], // key=规则名, 源文本=中文值
  ];
  const pick = (file, map) =>
    limit ? Object.fromEntries(Object.entries(map).slice(0, limit)) : map;

  for (const [file, map] of work) {
    if (Object.keys(map).length === 0) continue;
    // 请求用的源文本：common/modules 用键（英文）；validation 用值（中文）
    const srcMap = Object.fromEntries(
      Object.entries(pick(file, map)).map(([k, v]) => [k, file === 'validation' ? v : k])
    );
    const r = await runFile(loc, langName, file, srcMap, cache, c);
    console.log(`[${loc}/${file}] ${r.total} 条，失败 ${r.failed}`);
  }
  fs.writeFileSync(cf, JSON.stringify(cache));

  for (const [file, map] of work) {
    const entries = Object.keys(map)
      .filter((k) => typeof cache[`${file}::${k}`] === 'string')
      .map((k) => `    ${phpEscape(k)} => ${phpEscape(cache[`${file}::${k}`])},`);
    const note = file === 'validation'
      ? '校验消息：键为框架规则名，不可改'
      : `英文即 key：键是英文原文${loc === 'en' ? '（en 留空，引擎直接回键）' : ''}`;
    const tail = file === 'validation' && ATTR_BLOCK ? `\n${ATTR_BLOCK}` : '';
    fs.writeFileSync(
      `${T}/${loc}/${file}.php`,
      `${HEAD}// ${loc} · ${note}\nreturn [\n${entries.join('\n')}${tail}\n];\n`
    );
  }
  console.log(`[${loc}] 已写 ${work.length} 个文件`);
}
