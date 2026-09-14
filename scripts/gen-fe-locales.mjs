#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 前端词典多语生成器 —— 把 core/zh-en 的 1441 条（中文 → 英文）翻成其余语种，
 * 产出 core/zh-<code>.ts。
 *
 * 用法：
 *   node scripts/gen-fe-locales.mjs --list                 # 看各语种进度
 *   node scripts/gen-fe-locales.mjs --locale ja --limit 20 # 冒烟：先跑 20 条验通路
 *   node scripts/gen-fe-locales.mjs --all                  # 全量（可断点续跑）
 *
 * 设计：
 *  - **译文来源用英文而非中文**：zh-en 已经在手，英文作为中转语对多数语言质量更好；
 *    没有英文值的兜底用中文原文。
 *  - **断点续跑**：每条译文即时落 /tmp/fe-locales-cache/<code>.json，重跑只补缺口。
 *    跑一半断了不用重来（全量约 1.5 万条，必须可续）。
 *  - **凭证只读不打印**：从 ~/.claude/settings.json 的 env 块取网关地址与 token，
 *    任何日志都不输出其内容。
 *  - 输出文件由本脚本整份重写（含版权头），**不要手工编辑**。
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const CORE = path.join(ROOT, 'apps/angular/src/app/core');
const CACHE = '/tmp/fe-locales-cache';
const BATCH = 40;
const CONCURRENCY = 6;

const LOCALES = {
  ja: 'zhJa', ko: 'zhKo', de: 'zhDe', fr: 'zhFr', es: 'zhEs', pt: 'zhPt',
  ru: 'zhRu', ar: 'zhAr', hi: 'zhHi', bn: 'zhBn', id: 'zhId',
};
const LANG_NAME = {
  ja: 'Japanese', ko: 'Korean', de: 'German', fr: 'French', es: 'Spanish',
  pt: 'Portuguese', ru: 'Russian', ar: 'Arabic', hi: 'Hindi', bn: 'Bengali', id: 'Indonesian',
};

const q = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

/** 英文词典 → { 中文: 英文 }；两端目录与文件命名不同，故分开取 */
function loadEnDict(app) {
  const out = {};
  if (app === 'react') {
    const p = path.join(ROOT, 'apps/react/src/lib/i18n/zhEn.ts');
    const s = fs.readFileSync(p, 'utf8');
    Object.assign(out, new Function(`return ${s.slice(s.indexOf('{', s.indexOf('= {')), s.lastIndexOf('};') + 1)}`)());
    return out;
  }
  const dir = path.join(CORE, 'zh-en');
  for (const f of fs.readdirSync(dir).filter((x) => /^part\d+\.ts$/.test(x))) {
    const s = fs.readFileSync(path.join(dir, f), 'utf8');
    Object.assign(out, new Function(`return ${s.slice(s.indexOf('{', s.indexOf('= {')), s.lastIndexOf('};') + 1)}`)());
  }
  return out;
}
/** 产物路径/导出名：Angular 是 core/zh-ja.ts + zhJa；React 是 lib/i18n/zhJa.ts + zhJa */
const outDir = (app) => (app === 'react' ? path.join(ROOT, 'apps/react/src/lib/i18n') : CORE);
const outFile = (app, code) => (app === 'react' ? `zh${code[0].toUpperCase()}${code.slice(1)}.ts` : `zh-${code}.ts`);
/** 头部描述差异：生成命令与键集基准文件（两端命名不同） */
const genBy = (app) => (app === 'react' ? 'scripts/gen-fe-locales.mjs --app react' : 'scripts/gen-fe-locales.mjs');
const keySrc = (app) => (app === 'react' ? 'zhEn' : 'core/zh-en');

/** 网关凭证（只读，不打印） */
function creds() {
  const st = JSON.parse(fs.readFileSync(path.join(os.homedir(), '.claude/settings.json'), 'utf8'));
  const env = st.env ?? {};
  const base = String(env.ANTHROPIC_BASE_URL ?? '').replace(/\/$/, '');
  const token = String(env.ANTHROPIC_AUTH_TOKEN ?? '');
  const model = String(env.ANTHROPIC_MODEL ?? '');
  if (!base || !token || !model) throw new Error('网关凭证缺失（BASE_URL / AUTH_TOKEN / MODEL）');
  return { base, token, model };
}

async function translate(items, langName, c) {
  const body = {
    model: c.model,
    max_tokens: 8000,
    system:
      `You are a professional software UI translator. Translate the given UI strings into ${langName}.\n` +
      'Input is a JSON object mapping an id to an English string. Reply with ONLY a JSON object, ' +
      'same ids, values are the translations. No markdown fences, no commentary. ' +
      'Keep placeholders like {name} {count} {n} verbatim. Keep it short and idiomatic for UI labels.',
    messages: [{ role: 'user', content: JSON.stringify(items) }],
  };
  const r = await fetch(`${c.base}/v1/messages`, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      'anthropic-version': '2023-06-01',
      authorization: `Bearer ${c.token}`,
    },
    body: JSON.stringify(body),
  });
  if (!r.ok) throw new Error(`HTTP ${r.status}: ${(await r.text()).slice(0, 200)}`);
  const j = await r.json();
  const text = (j.content ?? []).filter((b) => b.type === 'text').map((b) => b.text).join('');
  const json = text.replace(/^```(?:json)?/m, '').replace(/```\s*$/m, '').trim();
  return JSON.parse(json);
}

const args = process.argv.slice(2);
const arg = (k, d) => {
  const i = args.indexOf(k);
  return i === -1 ? d : args[i + 1];
};
const APP = arg('--app', 'angular'); // angular | react
const en = loadEnDict(APP);
const keys = Object.keys(en);
fs.mkdirSync(CACHE, { recursive: true });
const cacheFile = (code) => path.join(CACHE, `${code}.json`);
const loadCache = (code) => (fs.existsSync(cacheFile(code)) ? JSON.parse(fs.readFileSync(cacheFile(code), 'utf8')) : {});

if (args.includes('--list')) {
  console.log(`源词典 ${keys.length} 条（${keySrc(APP)}）`);
  for (const [code, exp] of Object.entries(LOCALES)) {
    const have = Object.keys(loadCache(code)).length;
    console.log(`  ${code} (${LANG_NAME[code]}): ${have}/${keys.length}  ${exp ? '' : '[缺导出名]'}`);
  }
  process.exit(0);
}

const targets = args.includes('--all') ? Object.keys(LOCALES) : [arg('--locale', 'ja')];
const limit = Number(arg('--limit', '0')) || keys.length;
const c = creds();

for (const code of targets) {
  const cache = loadCache(code);
  const todo = keys.filter((k) => typeof cache[k] !== 'string').slice(0, limit);
  console.log(`[${code}] 待译 ${todo.length} / 共 ${keys.length}`);
  const batches = [];
  for (let i = 0; i < todo.length; i += BATCH) batches.push(todo.slice(i, i + BATCH));
  let done = 0, failed = 0;
  const worker = async () => {
    for (;;) {
      const b = batches.shift();
      if (!b) return;
      const items = Object.fromEntries(b.map((k, i) => [String(i), en[k] || k]));
      try {
        const out = await translate(items, LANG_NAME[code], c);
        b.forEach((k, i) => {
          const v = out[String(i)];
          if (typeof v === 'string' && v.trim() !== '') cache[k] = v;
          else failed++;
        });
        done += b.length;
        fs.writeFileSync(cacheFile(code), JSON.stringify(cache));
        if (done % 200 < BATCH) console.log(`  [${code}] ${done}/${todo.length}`);
      } catch (e) {
        failed += b.length;
        console.log(`  [${code}] 批次失败：${e.message}`);
      }
    }
  };
  await Promise.all(Array.from({ length: CONCURRENCY }, worker));
  fs.writeFileSync(cacheFile(code), JSON.stringify(cache));

  // 写出词典文件（键集与 zh 源一致；缺失的键省略 → 运行时回退中文）
  const entries = keys.filter((k) => typeof cache[k] === 'string').map((k) => `  ${q(k)}: ${q(cache[k])},`);
  const head = `/*\n * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz\n */\n\n` +
    `/**\n * 中文原文 → ${LANG_NAME[code]} 词典（${code}）。由 ${genBy(APP)} 生成，**请勿手工编辑**。\n` +
    ` * 缺词条时回退中文原文；键集须与 ${keySrc(APP)} 完全一致。\n */\n`;
  const out = path.join(outDir(APP), outFile(APP, code));
  fs.writeFileSync(out, `${head}export const ${LOCALES[code]}: Record<string, string> = {\n${entries.join('\n')}\n};\n`);
  console.log(`[${code}] 已写 ${path.relative(ROOT, out)}（${entries.length} 条，失败/缺 ${failed}）`);
}
