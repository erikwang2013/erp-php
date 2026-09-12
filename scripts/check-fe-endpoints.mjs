#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 前端端点契约探测 —— apps/angular 与 apps/react 的配置里声明的每个 API 路径，
 * 是不是真的"接上了"后端？
 *
 * 为什么用未登录探测：**401/422 = 路由存在（拦在鉴权/校验），404 = 没接上**。
 * 不需要凭据就能区分这两类，而"配置里写了 endpoint、后端却没这个路由"正是本项目
 * 反复出现的缺陷类型（E2E 前缀错、库存 store 校验不存在的字段都属这一类）。
 *
 * 用法：
 *   php start.php start -d          # 先起服务
 *   node scripts/check-fe-endpoints.mjs
 * 退出码：0 = 全部命中；1 = 有端点未命中（会列出）。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASE = process.env.FE_BASE ?? 'http://127.0.0.1:8788';

/** 从两端 config 目录抽出所有形如 '/admin/...'、'/api/...' 的字面量 */
function collectPaths() {
  const dirs = [
    path.join(ROOT, 'apps/angular/src/app/config'),
    path.join(ROOT, 'apps/react/src/config'),
  ];
  const found = new Set();
  const walk = (dir) => {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (/\.tsx?$/.test(e.name)) {
        const src = fs.readFileSync(p, 'utf8');
        for (const m of src.matchAll(/['"](\/(?:admin|api|open)\/[a-zA-Z0-9/_.-]*)['"]/g)) {
          found.add(m[1]);
        }
      }
    }
  };
  dirs.forEach(walk);
  // 只探 /admin/v1 与 /api/v1（带版本前缀才是真实路由；/admin/user 这类是权限标识串，不是端点）
  return [...found].filter((p) => /^\/(admin|api|open)\/v\d+/.test(p)).sort();
}

async function probe(p) {
  const tries = ['GET', 'POST'];
  let last = null;
  for (const method of tries) {
    let res;
    try {
      res = await fetch(BASE + p, {
        method,
        headers: { 'content-type': 'application/json' },
        body: method === 'POST' ? '{}' : undefined,
      });
    } catch (e) {
      return { kind: 'error', detail: `${method} → ${e.message}` };
    }
    const json = await res.json().catch(() => null);
    // 业务码：0=成功；401=未登录；403=无权限；404=路由未命中；422=参数校验
    const code = json?.code;
    if (code === 404) {
      last = { kind: 'missing', detail: `${method} → HTTP ${res.status} code=404 ${json?.message ?? ''}` };
      continue;
    }
    return { kind: 'hit', detail: `${method} → HTTP ${res.status} code=${code} ${json?.message ?? ''}` };
  }
  return last;
}

const paths = collectPaths();
console.log(`探测 ${paths.length} 个端点（端点来源：两端 config 目录）→ ${BASE}\n`);

const missing = [];
const hits = [];
const others = [];
for (const p of paths) {
  const r = await probe(p);
  if (r.kind === 'hit') hits.push(`${p}  ${r.detail}`);
  else if (r.kind === 'missing') missing.push(`${p}  ${r.detail}`);
  else others.push(`${p}  ${r.detail}`);
}

console.log(`命中 ${hits.length} / 未接上 ${missing.length} / 其它 ${others.length}\n`);
if (missing.length) {
  console.error('✗ 配置里声明、后端却无此路由（404）：');
  missing.forEach((m) => console.error('  ' + m));
}
if (others.length) {
  console.log('\n其它（网络/非 JSON 响应，需人工看）：');
  others.forEach((m) => console.log('  ' + m));
}
console.log('\n（401/403/422 均算命中：路由存在，只是被鉴权或校验拦下）');
process.exit(missing.length ? 1 : 0);
