#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 列标题表闸门：两端 `config/column-titles-extra/part*.ts` 切片 + 人工档字面量。
 *
 * 为什么需要它：`keyTitle()` 的兜底是蛇形转驼峰英文，词典又是以中文为 key
 * （查不到原样返回）—— 表里漏一个键，用户就在页面上看见英文表头，且没有任何报错。
 * install.sql 的列注释覆盖不到非 DB 列（如服务端算出的 shipment_code），
 * 所以「逐个展示列必须命中标题表」这条只能显式跑，跑不过就是漏键。
 *
 * 判据是**命中**，不是「值是不是中文」：`SKU ID` / `BOM` 这类 ASCII 专有名词是合格
 * 标题（词典里有对应条目），只有没命中、掉进驼峰兜底的才是缺陷。
 *
 * 用法：node scripts/check-column-titles.mjs   —— 全过 exit 0，有漏键/漂移 exit 1
 * 重生：node scripts/gen-column-titles.mjs     （本脚本只读，不写任何文件）
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const MAX_LINES = 500; // CLAUDE.md 硬线；切片就是为这条才切的

/** install.sql 覆盖不到的键：非 DB 列（服务端算出/嵌套对象）——漏了就退回英文。
 *  「展示列」是 install.sql 列名 + 这张表：报表/动作回包的键把数组当表格、对象当键值表渲染，
 *  每个键都会过 keyTitle，容器键（items/lines/rfq）与算出来的字段（target_total/is_lowest）全在内。 */
const EXTRA_KEYS = [
  'default_ledger',
  'shipment_code',
  'receive_code',
  'delivery_code',
  // 比价面板（/purchase/rfq/{id}/compare）
  'rfq',
  'quotes',
  'items',
  'is_lowest',
  'target_total',
  'target_amount',
  'lowest_quote_id',
  'quote_prices',
  'product_code',
  'buyer_real_name',
  // withCount 派生的计数列（RfqController::index / RfqQuoteController::index / RoleController::index）：非 DB 列
  'items_count',
  'quotes_count',
  'users_count',
  // 工资条（/hr/salary/{id}/payslip）
  'salary',
  'social',
  // 财务报表 report_data 内层
  'lines',
  'generated_from',
  'voucher_count',
  // 服务端按 FK 反查出的编号别名（与 receive_code/delivery_code 同款，select 或 pluck 带出）
  'voucher_code',
  'order_channel_no',
  'carrier_service_code',
  'receiving_code',
  'production_order_code',
  // ReportScheduleController::index 把 recipients 的 id 换成姓名（_names 不匹配 (name|id)$，回落驼峰）
  'recipients_names',
  // 比价回包的比价矩阵块
  'matrix',
];

/** 与 columns.ts / defaults.tsx 的 HIDDEN 同集：不展示的列不要求标题 */
const HIDDEN = new Set([
  'id', 'created_at', 'updated_at', 'deleted_at',
  'password', 'remember_token', 'tenant_id', 'org_id', 'version',
]);

/** 取 TS 里的纯数据对象字面量（无依赖，切片用法同 check-ng-i18n-dict.mjs）。
 *  人工档文件里还有合并导出等其他字面量，故只取 marker 之后**第一个** `\n};` 收尾的那块。 */
function literalAt(file, marker) {
  const src = fs.readFileSync(file, 'utf8');
  const m = marker.exec(src);
  const start = m && src.indexOf('{', m.index + m[0].length);
  const end = start < 0 ? -1 : src.indexOf('\n};', start);
  if (end < 0) throw new Error(`找不到字面量 ${marker}：${file}`);
  // 人工档里可能先铺一层 `...补充档`，切片本体不含它（两端合并顺序由各自文件保证）
  return src.slice(start, end + 3).replace(/^\s*\.\.\..*$/gm, '');
}

const bad = [];
const fail = (msg) => bad.push(msg);

/** 读一端：人工档字面量 + 全部切片；跨切片重名静默覆盖，只有这里拦得住 */
function loadSide(label, baseFile, baseMarker, dir) {
  const out = new Function(`return ${literalAt(baseFile, baseMarker)}`)();
  const parts = fs.readdirSync(dir).filter((f) => /^part\d+\.ts$/.test(f)).sort();
  if (!parts.length) fail(`${label}：未找到切片 ${dir}`);
  const owner = new Map();
  for (const f of parts) {
    const file = path.join(dir, f);
    const lines = fs.readFileSync(file, 'utf8').split('\n').length;
    if (lines > MAX_LINES) fail(`${label}/${f}：${lines} 行超 ${MAX_LINES}`);
    for (const [k, v] of Object.entries(
      new Function(`return ${literalAt(file, /export const COLUMN_TITLES_PART\d+/)}`)(),
    )) {
      if (owner.has(k)) fail(`${label}：跨切片重复键 '${k}'（${owner.get(k)} / ${f}）`);
      else owner.set(k, f);
      if (k in out) continue; // 人工档优先，切片里不该有同名键
      out[k] = v;
    }
  }
  return out;
}

const NG = path.join(ROOT, 'apps/angular/src/app/config');
const RE = path.join(ROOT, 'apps/react/src');
const ng = loadSide(
  'angular',
  path.join(NG, 'column-titles.ts'),
  /const BASE: Record<string, string> =/,
  path.join(NG, 'column-titles-extra'),
);
const re = loadSide(
  'react',
  path.join(RE, 'lib/defaults.tsx'),
  /const TITLES: Record<string, string> =/,
  path.join(RE, 'config/column-titles-extra'),
);

// 1) 两端逐键逐值相同（既有契约：漂移了页面就两端不一致）
const drift = [
  ...Object.keys(ng).filter((k) => ng[k] !== re[k]),
  ...Object.keys(re).filter((k) => !(k in ng)),
];
if (drift.length) fail(`两端标题表漂移 ${drift.length} 键：${drift.slice(0, 10).join(', ')}`);

// 2) install.sql 的每个展示列 + 非 DB 键，都要在标题表里命中（没命中 = 掉进驼峰兜底）
const sql = fs.readFileSync(path.join(ROOT, 'database/install.sql'), 'utf8');
const columns = new Set(EXTRA_KEYS);
for (const [, , body] of sql.matchAll(/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`([^`]+)`\s*\((.*?)^\)/gims)) {
  for (const m of body.matchAll(/^\s*`([^`]+)`\s+/gm)) columns.add(m[1]);
}
const missing = [];
for (const c of columns) {
  if (HIDDEN.has(c)) continue;
  if (!ng[c]) missing.push(c);
}
if (missing.length) fail(`${missing.length} 个展示列未命中标题表（会显示英文）：${missing.slice(0, 20).join(', ')}`);

console.log(`两端各 ${Object.keys(ng).length} 键（install.sql ${columns.size} 列名，含 ${EXTRA_KEYS.length} 个非 DB 键）`);
if (bad.length) {
  console.error(`FAIL\n  ${bad.join('\n  ')}`);
  process.exit(1);
}
console.log('PASS 两端一致、无漏键（每个展示列都命中标题表）');
