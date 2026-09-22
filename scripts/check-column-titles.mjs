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
  // 工单号：mfg 三页 index 按 order_id 反查 mfg_production_order.code（`$item['order_code'] = …`），
  // 另有 purchase/receive、sales/delivery、oms/rma 三处 `as order_code` 别名 —— 但那三页各自声明了
  // columns 且不含它 ⇒ 真正上屏的只有 mfg 三页（「工单号 / 订单号」二义，标题只能取一个，取上屏的那个）
  'order_code',
  // `*_by`/`assigned_to` 外键的名称兄弟键（B7 服务端补产出；键名机械 = 外键名切末 3 字符 + `_name`，
  // 同前端 columns.ts:379-384 / relation.ts:63 的取名口径）。合成键 install.sql 里没有 ——
  // 不列在这里就没有任何门禁守得住（漏登只在页面出英文 approvedName，无人报错）
  'approved_name',
  'assigned_name',
  'audited_name',
  'created_name',
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
const sqlCols = new Set();
const columns = new Set(EXTRA_KEYS);
for (const [, , body] of sql.matchAll(/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`([^`]+)`\s*\((.*?)^\)/gims)) {
  for (const m of body.matchAll(/^\s*`([^`]+)`\s+/gm)) {
    sqlCols.add(m[1]);
    columns.add(m[1]);
  }
}
const missing = [];
for (const c of columns) {
  if (HIDDEN.has(c)) continue;
  if (!ng[c]) missing.push(c);
}
if (missing.length) fail(`${missing.length} 个展示列未命中标题表（会显示英文）：${missing.slice(0, 20).join(', ')}`);

// 3) `*_name` / `*_code` 族键必须有真产出方 —— 前者是 `*_by`/`assigned_to` 外键的名称兄弟
//    （approved_name…），后者是从 `*_id` 反查 / leftJoin 出的业务编号（shipment_code…）。
//    前端只在行里真有这个键时才渲染（columns.ts:379-384 / relation.ts:63 的 siblingScalar）：
//    键名拼错、产出方被删、或登记了却没人产，都不会报错，只是那一列永远出「-」。
//    分类必须**完备**：往 EXTRA_KEYS 加一个族键而没在这儿分类 → 红（这个洞当初就是
//    「没有任何判据」才让 approved_name 靠人眼抓到）；synthetic ＝**本守卫不适用**（前端自造，或产出路径
//    不是行内标量，如 `with()` 预加载的关系对象）—— 只登记不断言，登记时要写清是哪一种。
//    `must` 带**产出方计数 pin**（第二项）：判据是「计数 === pin」，不是「计数 > 0」——
//    漏一个产出方（如把 `assigned_name` 写成 `assigned_by_name`）与多一个产出方都会现形；
//    其它车道真加了产出方就同步改这里的数与理由，别让 pin 过期成静默。
const KEY_CLASS = new Map([
  ['buyer_real_name', ['must', 1]], // RfqController::show 按 buyer_id 反查（buyer_name 被税票占用，故键名带 real_）
  ['approved_name', ['must', 3]], // approved_by 的名称兄弟（purchase/Apply 的 leftJoin 别名 + finance/Expense 批量回填）
  ['assigned_name', ['must', 3]], // assigned_to（wms pick/pack/putaway）
  ['audited_name', ['must', 1]], // audited_by（finance Invoice）
  ['created_name', ['must', 3]], // created_by（admin OpenApi/Webhook + hr Performance 计划）
  ['recipients_names', ['must', 1]], // 复数同族：ReportScheduleController::index 把 recipients 的 id 换成姓名串
  // `*_code` 族（同型洞：产出方没了 → 列上屏「-」、全门禁绿）。都是「行上只有 `*_id`，编号靠反查/别名补」
  ['shipment_code', ['must', 2]], // tms FreightInvoice/Tracking 按 shipment_id 反查
  ['receive_code', ['must', 2]], // purchase Return/Settlement 按 receive_id 反查
  ['delivery_code', ['must', 3]], // quality FinalCheck 的 `sales_delivery.code` 别名 + sales Return/Settlement 反查
  ['product_code', ['must', 6]], // 6 处：inventory/sales/purchase 的 `product.code` 别名 + Rfq 比价按 product_id 反查
  ['voucher_code', ['must', 1]], // finance SubsidiaryLedger 按 voucher_id 反查
  ['carrier_service_code', ['must', 1]], // tms FreightRate 按 carrier_service_id 反查
  ['receiving_code', ['must', 1]], // quality IncomingCheck 的 `purchase_receive.code` 别名
  ['production_order_code', ['must', 1]], // quality ProcessCheck 的 `mfg_production_order.code` 别名
  ['order_code', ['must', 6]], // mfg 三控制器数组位赋值（MaterialIssue:87 / WorkReport:98 / CostEntry:88）+ Receive:79 / Delivery:81 / Rma:60 三处别名
]);

const appPhp = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory()) walk(path.join(dir, e.name));
    else if (e.name.endsWith('.php')) appPhp.push(path.join(dir, e.name));
  }
})(path.join(ROOT, 'app'));

/** 产出方 = **代码态的产出形状**（整行注释不算 —— 讲解注释与注释掉的旧产出跟真产出长得一模一样，
 *  `ApplyController` 就有一行「键名必须叫 approved_name」）：
 *    ① 数组位赋值 `['key'] = / ??= / .=`   ② 数组字面量 `'key' => …`   ③ SQL 别名 `as key`。
 *  只**读**这个键（`$x = $row['approved_name']`）不算产出方 —— 本守卫守的是有人把它写进行里。
 *  ponytail: 只认字面量写法；关系对象路径（`with('approver')` 预加载、前端走 `row.approver.name`）
 *  与动态拼键（`$row[$k]`）看不见 ⇒ 真出现时把该键登记成 synthetic（＝本守卫不适用），别改判据。 */
const codeLines = [];
for (const f of appPhp) {
  fs.readFileSync(f, 'utf8')
    .split('\n')
    .forEach((line, i) => {
      if (/^\s*(\/\/|\*|\/\*|#)/.test(line)) return;
      codeLines.push([f, i + 1, line]);
    });
}
const producersOf = (key) => {
  const write = new RegExp(`\\['${key}'\\]\\s*(\\?\\?|\\.)?\\s*=|['"]${key}['"]\\s*=>`);
  const as = new RegExp(`\\bas\\s+${key}\\b`, 'i');
  return codeLines.filter(([, , l]) => write.test(l) || as.test(l)).map(([f, i]) => `${path.relative(ROOT, f)}:${i}`);
};

const FAMILIES = [
  { label: '*_name', test: (k) => /_names?$/.test(k) },
  { label: '*_code', test: (k) => /_code$/.test(k) },
];
const familyKeys = EXTRA_KEYS.filter((k) => FAMILIES.some((f) => f.test(k)));
/* 分类集/键集/整族塌成空（EXTRA_KEYS 被改坏、某族键被整段删掉）时下面两条断言会静默全绿 ——
 * 与 check-ddl-dict.mjs 的覆盖度下限同款兜底：解析面塌了必须自己红，不许安静地什么都不比。 */
const goneFamilies = FAMILIES.filter((f) => !familyKeys.some((k) => f.test(k))).map((f) => f.label);
if (!familyKeys.length || !KEY_CLASS.size || goneFamilies.length) {
  fail(
    `*_name/*_code 守卫解析面塌了（EXTRA_KEYS 里 ${familyKeys.length} 个族键 / 分类 ${KEY_CLASS.size} 条` +
      ` / 整族消失：${goneFamilies.join(' ') || '无'}）—— 静默全绿`,
  );
}
const unclassified = familyKeys.filter((k) => !KEY_CLASS.has(k));
if (unclassified.length) {
  fail(`EXTRA_KEYS 里 ${unclassified.length} 个 *_name/*_code 键未分类（must / synthetic）：${unclassified.join(', ')}`);
}
/* pin 全命中自检（反方向）：KEY_CLASS 里有、族键集里没有 = 表腐（键被移出 EXTRA_KEYS 却没删这行），
 * 否则它会继续断言一个已经不上屏的键 —— 只登记不断言的那半边也得有人守。 */
const stalePins = [...KEY_CLASS.keys()].filter((k) => !familyKeys.includes(k));
if (stalePins.length) {
  fail(`KEY_CLASS 里 ${stalePins.length} 个键不在 EXTRA_KEYS 族键集里（删掉这些行或把键加回登记）：${stalePins.join(', ')}`);
}
const prodCount = [];
const badPin = [];
for (const [k, v] of KEY_CLASS) {
  const tup = Array.isArray(v);
  const cls = tup ? v[0] : v;
  if (cls === 'synthetic' && !tup) continue; // 只登记不断言
  /* 形态自检优先于断言：`'must'` 裸值（没带 pin）以前会被 `[cls, pin] = 'must'` 解成
   * cls='m' 而**静默跳过整个键**（负控实测：门禁绿、打印行里那个键不见了）。 */
  if (!tup || cls !== 'must' || !v[1]) {
    badPin.push(`${k} = ${JSON.stringify(v)}`);
    continue;
  }
  const [, pin] = v;
  const n = producersOf(k).length;
  prodCount.push(`${k}(${n})`);
  if (n !== pin) badPin.push(`${k}(实际 ${n} ≠ pin ${pin})`);
}
if (badPin.length) {
  fail(
    `产出方 pin 不合格 ${badPin.length} 处（must 必须写 ['must', N]，计数与实际情况不符也要改；丢了产出方 = 该列恒出「-」）：` +
      badPin.join('; '),
  );
}

console.log(`两端各 ${Object.keys(ng).length} 键（展示面 = install.sql ${sqlCols.size} 列名 + ${EXTRA_KEYS.length} 个非 DB 键，去重后 ${columns.size}）`);
const clsOf = (k) => {
  const v = KEY_CLASS.get(k);
  return Array.isArray(v) ? v[0] : v;
};
console.log(`*_name/*_code 键 ${familyKeys.length} 个（分类声明：${familyKeys.filter((k) => clsOf(k) === 'must').length} must / ${familyKeys.filter((k) => clsOf(k) === 'synthetic').length} synthetic）产出方命中：${prodCount.join(' ')}`);
if (bad.length) {
  console.error(`FAIL\n  ${bad.join('\n  ')}`);
  process.exit(1);
}
console.log('PASS 两端一致、无漏键（每个展示列都命中标题表）');
