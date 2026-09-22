#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * HarmonyOS 端枚举上屏自检 —— scripts/check-harmonyos-enum.mjs
 *
 * 守一条不变量：**枚举列上屏的是本地化文案，不是机读串/裸数字，也不是别的表的文案**。
 *   A. 已修的渲染点必须走码表助手（`priorityLabel`/`channelLabel`/`statusLabel`/
 *      `<域>_status_${code}`/`packTypeLabel`），旧写法（`hint(this.row['priority'])`、
 *      `new RowBadge(kind, code)`、`status_enabled/disabled` 硬套布尔语义）必须不再存在；
 *   B. 码表键**逐值字面**以 `database/install.sql` 列注释为唯一事实源核对（脚本现读 DDL
 *      解析，DDL 改了这里立刻红）：每条键断言 ① 两份都在 ② 中文非空 ③ 中文 == DDL 字面
 *      ④ 英文非空 ⑤ 英 ≠ 中。「覆盖率」（键存在）不算证明 —— status_ff_0/1 就是键齐值错；
 *      只有码值、注释无中文的（channel/package_type/status_code）中文自拟，做 ①②④⑤；
 *   C. 两份 string.json（base / en_US）键集逐字相等、无重名、页面里字面量引用的键两边都在。
 *
 * 局限（如实记录）：**该端无工具链**（不能编译、不能运行 ArkTS），本脚本是静态读码 +
 * 资源键核对，不做类型检查、不起模拟器 —— 它证明渲染点接的是码表、键两侧齐备，
 * 不证明真机上的像素表现。
 *
 * 用法：node scripts/check-harmonyos-enum.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import { readFileSync, globSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

const url = (p) => new URL(p, import.meta.url);
const read = (p) => readFileSync(url(p), 'utf8');

let fails = 0;
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};

const HOS = '../apps/harmonyos/entry/src/main/';
const HOS_DIR = fileURLToPath(url(HOS)); // globSync 要绝对路径（相对 pattern 的返回路径按 cwd 解析会跑偏）
const page = (p) => read(HOS + 'ets/pages/' + p);
const json = (loc) => JSON.parse(read(HOS + `resources/${loc}/element/string.json`)).string;
const BASE = new Map(json('base').map((e) => [e.name, e.value]));
const EN = new Map(json('en_US').map((e) => [e.name, e.value]));

// install.sql 列注释 → 期望枚举（唯一事实源，现读）
const SQL = read('../database/install.sql');
const colComment = (table, col) => {
  const body = SQL.split('CREATE TABLE IF NOT EXISTS `' + table + '`')[1] ?? '';
  const m = body.match(new RegExp('`' + col + '`[^\\n]*?COMMENT \'([^\']*)\''));
  return m ? m[1] : '';
};
/** '状态: 0=待发货 1=已取件' → [['0','待发货'],['1','已取件']] */
const codeText = (comment) => [...comment.matchAll(/([0-9A-Za-z_]+)=([^\s/]+)/g)].map((m) => [m[1], m[2]]);
/** '状态码: picked_up/in_transit' → ['picked_up','in_transit'] */
const codeList = (comment) => (comment.split(/[:：]/)[1] ?? '').trim().split('/').filter((s) => s.length > 0);

// ── A. 渲染点接线：正向写法在、旧裸值写法不在 ────────────────────────────────
// [说明, 文件, 必存正则, 必无正则]
const WIRED = [
  ['oms 详情：优先级走码表',
    'oms/OrderDetailPage.ets',
    /value: this\.priorityLabel\(this\.row\['priority'\]\)/,
    /value: this\.hint\(this\.row\['priority'\]\)/],
  ['oms 详情：渠道走码表',
    'oms/OrderDetailPage.ets',
    /value: this\.channelLabel\(this\.row\['channel'\]\)/,
    /value: this\.hint\(this\.row\['channel'\]\)/],
  ['oms 详情：履约状态 0..5 全覆盖',
    'oms/OrderDetailPage.ets',
    /if \(code >= 0 && code <= 5\) \{\s*return L10n\.str\(this\.getUIContext\(\), `status_ff_\$\{code\}`\)/,
    /if \(code === 0 \|\| code === 1 \|\| code === 4 \|\| code === 5\)/],
  ['oms 列表：履约状态补 2/3',
    'oms/OrderListPage.ets',
    /L10n\.str\(ui, 'status_ff_2'\)[\s\S]{0,200}L10n\.str\(ui, 'status_ff_3'\)/,
    /其余码值（含表单手工 2\/3）直出数字/],
  ['tms 轨迹：徽章走码表（不贴 status_code 原文）',
    'tms/TrackingPage.ets',
    /new RowBadge\(kind, this\.statusLabel\(code\)\)/,
    /new RowBadge\(kind, code\)/],
  ['tms 轨迹：标题/圆徽走码表',
    'tms/TrackingPage.ets',
    /icon: this\.statusLabel\(\(item\['status_code'\] as string\) \?\? ''\)/,
    /icon: \(item\['status_code'\] as string\) \|\| '-'/],
  ['tms 运单：状态 0..5 码表（不再按布尔启用/禁用）',
    'tms/ShipmentListPage.ets',
    /L10n\.str\(this\.getUIContext\(\), `shipment_status_\$\{code\}`\)/,
    /app\.string\.status_(enabled|disabled)/],
  ['wms 上架：状态 0..2 码表',
    'wms/PutawayPage.ets',
    /L10n\.str\(this\.getUIContext\(\), `putaway_status_\$\{code\}`\)/,
    /app\.string\.status_(enabled|disabled)/],
  ['wms 收货：状态 0..3 码表',
    'wms/ReceivingPage.ets',
    /L10n\.str\(this\.getUIContext\(\), `receiving_status_\$\{code\}`\)/,
    /app\.string\.status_(enabled|disabled)/],
  ['mfg 生产工单：状态 0..3 码表',
    'mfg/ProductionOrderPage.ets',
    /L10n\.str\(this\.getUIContext\(\), `production_status_\$\{code\}`\)/,
    /app\.string\.status_(enabled|disabled)/],
  ['wms 打包：包装类型走码表',
    'wms/PackPage.ets',
    /subtitle: this\.packTypeLabel\(item\.package_type\)/,
    /subtitle: item\.package_type \|\|/],
  ['hr 员工：状态 1/2/3 三态（不再二值兜底成离职）',
    'hr/EmployeeListPage.ets',
    /if \(code === 3\) \{\s*return new RowBadge\('warning', \$r\('app\.string\.employee_status_suspended'\)\)/,
    /if \(item\.status === 1\) \{\s*return new RowBadge\('success', \$r\('app\.string\.employee_status_active'\)\)/],
  ['hr 员工表单：离职写 2（DDL 码值，不再写码表外的 0）',
    'hr/EmployeeFormView.ets',
    /this\.formStatus = 2; \}\)/,
    /this\.formStatus = 0; \}\)/],
];
for (const [what, file, want, ban] of WIRED) {
  const src = page(file);
  ok(`${file} —— ${what}`, want.test(src), `接线缺失（期望匹配 ${want}）`);
  ok(`${file} —— ${what}：旧写法已清`, !ban.test(src), `仍在：${ban}`);
}

// 布尔语义只许留在真的 0禁用/1启用 的表上（product/zone/user/role/dept/carrier/rate/channel）
const BOOL_OK = new Set([
  'ProductListPage.ets', 'ZoneListPage.ets', 'UserListPage.ets', 'RoleListPage.ets',
  'DepartmentPage.ets', 'CarrierListPage.ets', 'FreightRatePage.ets', 'ChannelListPage.ets',
]);
for (const f of globSync(HOS_DIR + 'ets/pages/**/*.ets')) {
  if (BOOL_OK.has(f.split('/').pop())) continue;
  const src = readFileSync(f, 'utf8');
  // 上面 4 个已修文件另有专项断言；这里扫其余页面，防新增页面再套布尔文案到枚举表上
  if (/status_(enabled|disabled)/.test(src) && !/FormView\.ets$/.test(f)
    && /private \w+Badge\(/.test(src)) {
    const name = f.split('/').pop();
    ok(`${name}：列表徽章未硬套启用/禁用（须核 install.sql）`, false,
      '该页有 *Badge 且引用 status_enabled/disabled —— 若该表 status 不是 0禁用/1启用，请改码表');
  }
}

// 已知缺口（如实记录，本脚本不判红）：下列表单的 status 仍是二值钮，而表 status 是多值生命周期。
// 改法需要一个 N 选一控件（本端现无此写法），属表单交互决策，留给后续批次。
for (const f of [
  'tms/ShipmentFormView.ets', 'mfg/ProductionOrderFormView.ets',
  'wms/PutawayFormView.ets', 'wms/ReceivingFormView.ets',
]) {
  const src = page(f);
  if (/this\.formStatus = 0; \}\)/.test(src) && /status_(enabled|disabled)/.test(src)) {
    console.log(`[已知缺口] ${f}：status 二值钮映射多值生命周期表，本次未改（需 N 选一控件）`);
  }
}

// ── B. 码表键 vs install.sql 列注释（唯一事实源，逐值字面断言） ──────────────
// 每条键四连断言：① 两份都在 ② 中文非空（注释带中文时 == DDL 字面）③ 英文非空 ④ 英 ≠ 中。
// 「覆盖率」（键存在）不算证明 —— 值抄错照样绿，本批就是漏在 status_ff_0/1 的值上。
// 注释只有码值、无中文的（channel/package_type/status_code）：中文自拟，只做 ①②③④，不强绑 DDL。
const TABLES = [
  ['erp_tms_shipment', 'status', 'shipment_status_', true],
  ['erp_mfg_production_order', 'status', 'production_status_', true],
  ['erp_wms_putaway_task', 'status', 'putaway_status_', true],
  ['erp_wms_receiving', 'status', 'receiving_status_', true],
  ['erp_oms_order', 'priority', 'oms_priority_', true],
  ['erp_oms_order', 'fulfillment_status', 'status_ff_', true], // 0/1 曾漂移（待分配/履约中），已按 DDL 改正
  ['erp_oms_order', 'channel', 'oms_channel_', false],
  ['erp_wms_pack_task', 'package_type', 'pack_type_', false],
  ['erp_tms_tracking_event', 'status_code', 'tracking_status_', false],
];
// 中英同形是正确渲染（缩写），不是漏译：仅此两键豁免「英 ≠ 中」
const SAME_IN_BOTH = new Set(['oms_channel_edi', 'oms_channel_pos']);
for (const [table, col, prefix, ddlZh] of TABLES) {
  const comment = colComment(table, col);
  ok(`${table}.${col} 列注释可解析`, comment.length > 0, 'install.sql 里没读到 COMMENT');
  const pairs = codeText(comment);
  const list = pairs.length > 0 ? pairs.map((p) => p[0]) : codeList(comment);
  ok(`${table}.${col} 码值非空`, list.length > 0, `注释原文：${comment}`);
  for (const code of list) {
    const key = prefix + code;
    const zh = BASE.get(key);
    const en = EN.get(key);
    ok(`${key} 两份 string.json 都在`, zh !== undefined && en !== undefined, `base=${zh} en=${en}`);
    ok(`${key} 中文非空`, typeof zh === 'string' && zh.length > 0, `实际 ${zh}`);
    if (ddlZh) {
      const want = pairs.find((p) => p[0] === code)?.[1];
      ok(`${key} 中文 == DDL「${want}」`, zh === want, `实际 ${zh}`);
    }
    ok(`${key} 英文非空`, typeof en === 'string' && en.length > 0, `实际 ${en}`);
    ok(`${key} 英文 != 中文（非镜像漏译）`, SAME_IN_BOTH.has(key) || en !== zh, `两边同为 ${zh}`);
  }
}

// 员工状态：键名不按码值编号（active/resigned/suspended），按 DDL 顺序逐位核文案
for (const [i, suffix] of ['active', 'resigned', 'suspended'].entries()) {
  const key = 'employee_status_' + suffix;
  const want = codeText(colComment('erp_hr_employee', 'status'))[i]?.[1];
  ok(`${key} 两边都在`, BASE.has(key) && EN.has(key));
  ok(`${key} 中文 == DDL「${want}」`, BASE.get(key) === want, `实际 ${BASE.get(key)}`);
  ok(`${key} 英文非空且 != 中文`, Boolean(EN.get(key)) && EN.get(key) !== BASE.get(key), `实际 ${EN.get(key)}`);
}

// 全码表覆盖：键数 == 码值数（防止缺一个码悄悄落回裸值）
for (const [table, col, prefix] of TABLES) {
  const comment = colComment(table, col);
  const pairs = codeText(comment);
  const list = pairs.length > 0 ? pairs.map((p) => p[0]) : codeList(comment);
  const have = [...BASE.keys()].filter((k) => k.startsWith(prefix) && /_\d+$|_[a-z_]+$/.test(k));
  ok(`${prefix}* 覆盖 ${table}.${col} 全部 ${list.length} 码（现有 ${have.length} 键）`,
    list.every((c) => have.includes(prefix + c)), `缺：${list.filter((c) => !have.includes(prefix + c))}`);
}

// ── C. 两份 string.json 键集 / 引用完整性 ───────────────────────────────────
ok('base 与 en_US 键数相等', BASE.size === EN.size, `base=${BASE.size} en=${EN.size}`);
const baseOnly = [...BASE.keys()].filter((k) => !EN.has(k));
const enOnly = [...EN.keys()].filter((k) => !BASE.has(k));
ok('无单边键（孤儿键）', baseOnly.length === 0 && enOnly.length === 0,
  `仅 base：${baseOnly} 仅 en_US：${enOnly}`);
const dup = (arr) => arr.filter((n, i) => arr.indexOf(n) !== i);
ok('无重名键', dup([...BASE.keys()]).length === 0, `重名：${dup([...BASE.keys()])}`);

// 页面里字面量引用的键（$r 静态取 / L10n.str 静态首参）必须两边都在
const used = new Set();
for (const f of globSync(HOS_DIR + 'ets/**/*.ets')) {
  const src = readFileSync(f, 'utf8');
  for (const m of src.matchAll(/\$r\('app\.string\.([a-z0-9_]+)'\)/g)) used.add(m[1]);
  for (const m of src.matchAll(/L10n\.str\([^,]+,\s*'([a-z0-9_]+)'/g)) used.add(m[1]);
}
const dangling = [...used].filter((k) => !BASE.has(k) || !EN.has(k));
ok(`字面量引用的 ${used.size} 个键两边都有`, dangling.length === 0, `悬空：${dangling}`);

console.log(fails === 0 ? '\n全部通过' : `\n${fails} 例失败`);
process.exit(fails === 0 ? 0 : 1);
