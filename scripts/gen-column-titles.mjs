#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 资源页推断列标题生成器 —— 把 database/install.sql 的列注释变成「字段名 → 中文标题」。
 *
 * 用法：
 *   node scripts/gen-column-titles.mjs          # 重写两端补充档 + /tmp/new_titles.txt
 *   node scripts/gen-column-titles.mjs --check  # 只校验，不落盘（两端一致 / 覆盖 / 无漏键）
 *
 * 产出（两端同结构，同一份内容）：
 *   apps/angular/src/app/config/column-titles-extra/part*.ts + index.ts
 *   apps/react/src/config/column-titles-extra/part*.ts + index.ts
 *   /tmp/new_titles.txt —— 去重后的新中文词条（每行一条），留给词典生成用
 *
 * 为什么标题表要加这一层：`columns.ts` 的 `keyTitle()` 兜底是蛇形转驼峰，
 * 词典以中文为 key，英文串查不到就原样显示 —— 于是没收录的列在页面上是英文表头。
 * install.sql 的列注释是全库唯一权威的中文名来源，故以此为种子补齐。
 *
 * 只读 install.sql + 本文件内的显式表，**不联网、不调 LLM、不碰词典生成器**。
 * 输出文件整份重写，不要手工编辑（手改会两端漂移）。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const CHECK_ONLY = process.argv.includes('--check');
const MAX_PER_PART = 400; // CLAUDE.md 500 行硬线，留出注释与包装的余量

const q = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
const HEADER = `/*\n * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz\n */\n`;

/* ── 1. install.sql → { 表 => { 列 => 注释 } }（与 /tmp/seed_titles.php 同解析） ────── */

const sql = fs.readFileSync(path.join(ROOT, 'database/install.sql'), 'utf8');
const CREATE_TABLE = /CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`([^`]+)`\s*\((.*?)^\)/gims;
/** @type {Map<string, string>} 列名 → 注释候选（同名跨表合并，靠 COLLIDE 裁决） */
const commentOf = new Map();

for (const [, , body] of sql.matchAll(CREATE_TABLE)) {
  for (const line of body.split('\n')) {
    const col = /^\s*`([^`]+)`\s+/.exec(line);
    if (!col) continue;
    const cm = /COMMENT\s+'((?:[^'\\]|\\.)*)'/.exec(line);
    const text = cm ? cm[1].replace(/\\(.)/g, '$1') : '';
    if (!text) continue;
    const list = commentOf.get(col[1]) ?? [];
    list.push(text);
    commentOf.set(col[1], list);
  }
}

/** 所有出现过的列名（含无注释的），用于覆盖率自检 */
const allColumns = new Set(commentOf.keys());
for (const [, , body] of sql.matchAll(CREATE_TABLE)) {
  for (const line of body.split('\n')) {
    const col = /^\s*`([^`]+)`\s+/.exec(line);
    if (col) allColumns.add(col[1]);
  }
}

/* ── 2. 两端既有标题表（冻结的 176 键，只读不改） ─────────────────────────────────── */

/** 从 TS 源码里抠出 `键: '值'` 字面量 —— 只读，故用正则而非 bundler */
function parseTable(file, openPat, closePat) {
  const src = fs.readFileSync(file, 'utf8');
  const start = src.search(openPat);
  if (start < 0) throw new Error(`找不到标题表字面量：${file}`);
  const body = src.slice(start, src.indexOf(closePat, start));
  const out = {};
  for (const m of body.matchAll(/^\s{2}([A-Za-z_][A-Za-z0-9_]*)\s*:\s*'((?:[^'\\]|\\.)*)'\s*,/gm)) {
    out[m[1]] = m[2].replace(/\\(.)/g, '$1');
  }
  return out;
}

const NG_TITLES = path.join(ROOT, 'apps/angular/src/app/config/column-titles.ts');
const RE_TITLES = path.join(ROOT, 'apps/react/src/lib/defaults.tsx');
const ANGULAR_BASE = parseTable(NG_TITLES, /const BASE: Record<string, string> = \{/, '\n};');
const REACT_BASE = parseTable(RE_TITLES, /const TITLES: Record<string, string> = \{/, '\n};');

/** 两端既有表逐键逐值相同 —— 既有契约，不满足就停（补档无从谈起） */
const drift = [
  ...Object.keys(ANGULAR_BASE).filter((k) => ANGULAR_BASE[k] !== REACT_BASE[k]),
  ...Object.keys(REACT_BASE).filter((k) => !(k in ANGULAR_BASE)),
];
if (drift.length) {
  console.error(`FAIL 两端既有标题表已漂移（${drift.length} 键）：${drift.slice(0, 10).join(', ')}`);
  process.exit(1);
}
const BASE = ANGULAR_BASE;

/* ── 3. 注释规范化（剥附注 → 分句截断 → trim） ───────────────────────────────────── */

const norm = (c) =>
  c
    .trim()
    .replace(/[（(][^）)]*[）)]/gu, '') // 剥括号附注
    .split(/[：:]/u)[0] // `状态: 1=男` → `状态`
    .split(/[，,、;；]/u)[0] // 取首个分句
    .split('。')[0]
    .trim();

const hasHan = (s) => /\p{Script=Han}/u.test(s);
/** 纯枚举注释（`1领取2释放3回收`）不是标题 —— 首字符是数字且数字 ≥2 个 */
const isEnum = (s) => /^\d/.test(s) && (s.match(/\d/g)?.length ?? 0) >= 2;
/** 只对连续 ≥3 个小写字母报警：ID/URL/IP/SKU 这类全大写缩写是合格标题 */
const hasLatinWord = (s) => /[a-z]{3,}/.test(s);

/** 注释实例 → 可用标题；'' = 该条注释不可用 */
const usable = (c) => {
  const n = norm(c);
  if (!n || !hasHan(n) || isEnum(n) || hasLatinWord(n) || n.length > 12) return '';
  return n;
};

/* ── 4. 显式裁决表 ────────────────────────────────────────────────────────────────
 * 三条规则：COLLIDE 裁决同名列（众数优先，并列取更通用者）；CLEAN 清洗夹英文/过长/纯枚举的
 * 注释；MANUAL 兜住 install.sql 给不出的键。每项都写清理由 —— 换人维护时不必再猜。
 */

/** 同名列多候选裁决（62 个列名；值是 `[标题, 理由]`，理由仅众数例外的才写） */
const COLLIDE = {
  action: ['操作动作', '主用方 operation_log 的注释'],
  balance: ['余额', '四候选各一，取最通用者'],
  source_id: ['来源单据ID', ''],
  rule_id: ['规则ID', '四候选各一（预警/考勤/社保各域），取通用者'],
  unit_cost: ['单位成本', '四候选各一，取注释主体'],
  total_cost: ['成本合计', '四候选各一，取通用者'],
  returned_at: ['退货时间', ''],
  quotation_id: ['报价单ID', ''],
  direction: ['方向', ''],
  source_type: ['来源类型', ''],
  company_id: ['组织ID', '众数 ×4（多组织口径）'],
  created_by: ['创建人ID', ''],
  report_data: ['完整报表数据JSON', ''],
  comment: ['评价', '三候选各一（审批/面试/评分），取通用者'],
  id: ['主键ID', ''],
  cost_price: ['成本单价', ''],
  is_base: ['是否基本单位', '两候选各一，商品单位是主用方'],
  biz_type: ['业务类型', ''],
  balance_after: ['交易后余额', '两候选各一，取更通用者'],
  operator_id: ['操作人ID', ''],
  points: ['积分', '两候选各一（可用/变动），取通用者'],
  expire_at: ['过期时间', '两候选各一，取更通用者'],
  received_quantity: ['实收数量', '两候选各一，取 ERP 习用词'],
  settled_at: ['结算时间', ''],
  quoted_at: ['报价时间', '两候选各一，与 quote_date=报价日期 区分'],
  from_location_id: ['调出库位ID', '两候选各一，与 to_location_id 成对'],
  to_location_id: ['调入库位ID', '两候选各一，与 from_location_id 成对'],
  base_currency: ['本位币', '两候选各一，取更通用者'],
  opened_at: ['开通时间', '两候选各一（开通/开账），取更通用者'],
  closed_at: ['关闭时间', '两候选各一（关闭/关账），取更通用者'],
  voucher_id: ['凭证ID', ''],
  paid_at: ['付款时间', '两候选各一，取更通用者'],
  opportunity_id: ['关联商机ID', ''],
  report_year: ['会计年度', ''],
  report_month: ['会计月份', ''],
  total_assets: ['资产总计', '两候选各一，取资产负债表标准口径'],
  total_liabilities: ['负债总计', '两候选各一，取资产负债表标准口径'],
  total_equity: ['所有者权益总计', '两候选各一，取资产负债表标准口径'],
  valid_until: ['有效期至', ''],
  net_value: ['净值', '两候选各一，取更通用者'],
  budget_amount: ['预算金额', ''],
  actual_cost: ['实际成本', '两候选各一，取更通用者'],
  query_config: ['查询配置', '两候选各一，去掉 JSON 后缀'],
  label: ['显示名', '两候选各一，与 field=字段名 区分'],
  progress: ['进度', '两候选各一，去掉 0-100 后缀'],
  reason: ['原因', '两候选各一（请假/退货），取通用者'],
  rater_type: ['评分人类型', '两候选各一，取快照前的原名'],
  plan_id: ['计划ID', '两候选各一（考核批次/MRP），取通用者'],
  audit_at: ['审核时间', ''],
  issue_id: ['领料单ID', '两候选各一（领料/发料同源），取领料单'],
  labor_cost: ['人工成本', '两候选各一，取不含「实际」的通用者'],
  overhead_cost: ['制造费用', '两候选各一，去掉「实际」前缀'],
  other_cost: ['其他成本', '两候选各一，去掉「实际」前缀'],
  field: ['字段名', '两候选各一，与 label=显示名 区分'],
  pick_task_id: ['拣货任务ID', '两候选各一，去掉 WMS 前缀'],
  pack_task_id: ['打包任务ID', '两候选各一，去前缀与「关联」'],
  shipment_id: ['运单ID', ''],
  picked_quantity: ['实拣数量', '两候选各一，取 ERP 习用词'],
  source_item_id: ['来源明细ID', '两候选各一，取更通用者'],
  tracking_no: ['物流单号', '两候选各一，取更短者'],
  receiving_id: ['收货任务ID', '两候选各一，去掉「关联采购」'],
  invoice_no: ['发票号', '两候选各一，取更短者'],
};

/** 注释需清洗：夹小写英文词 / 过长 / 纯枚举（生成器按规则会拒收，这里给人工结果） */
const CLEAN = {
  approver_type: ['审批人类型', '原注释是纯枚举「1指定人2角色3部门负责人4直属上级」'],
  content_hash: ['内容哈希', '原注释「内容sha256」夹英文词'],
  order_item_id: ['订单明细ID', '原注释「关联 SalesOrderItem.id」夹英文词'],
  dimensions: ['评分维度', '原注释「评分维度json」夹英文词'],
  app_secret_hash: ['密钥哈希', '原注释「API Secret 的 sha256 hex」夹英文词'],
  http_code: ['HTTP 状态码', '原注释「最近一次投递的 HTTP 状态码」过长且夹英文词'],
  is_internal: ['是否内部', '原注释是纯枚举「0对外1内部备忘」，取 1 侧语义'],
  is_read: ['是否已读', '原注释是纯枚举「0未读1已读」，取 1 侧语义'],
};

/** install.sql 给不出的键：29 个无注释列 + 23 个非 DB 列（页面上实见，服务端算出/嵌套对象） */
const MANUAL = {
  response: ['响应内容', 'crm_campaign_participant 无注释'],
  completed_at: ['完成时间', 'project_task 无注释'],
  joined_at: ['加入时间', 'project_member 无注释'],
  rma_id: ['售后单ID', 'oms_rma_item 无注释'],
  asn_id: ['到货通知单ID', 'wms_asn_item / wms_receiving 无注释（ASN）'],
  layout: ['布局', 'bi_dashboard 无注释'],
  dataset_id: ['数据集ID', 'bi_widget 无注释'],
  config: ['配置', 'bi_widget 无注释'],
  position_x: ['X 坐标', 'bi_widget 无注释'],
  position_y: ['Y 坐标', 'bi_widget 无注释'],
  width: ['宽度', 'bi_widget 无注释'],
  height: ['高度', 'bi_widget 无注释'],
  model: ['型号', 'eam_equipment 无注释'],
  serial_number: ['序列号', 'eam_equipment 无注释'],
  location: ['位置', 'eam_equipment / eam_spare_part 无注释'],
  warranty_expiry: ['保修到期日', 'eam_equipment 无注释'],
  last_date: ['上次维护日期', 'eam_maintenance_plan 无注释，该列名仅此表用'],
  next_date: ['下次维护日期', 'eam_maintenance_plan 无注释，该列名仅此表用'],
  assignee: ['负责人', 'eam_maintenance_plan / eam_repair_order 无注释，与 assignee_id 同口径'],
  stock_qty: ['库存数量', 'eam_spare_part 无注释'],
  min_stock: ['最小库存', 'eam_spare_part 无注释，与 min_quantity=最小库存阈值 区分'],
  version: ['版本', 'dms_document / dms_document_version 无注释'],
  author: ['作者', 'dms_document 无注释'],
  tags: ['标签', 'dms_document 无注释'],
  document_id: ['文档ID', 'dms_document_version 无注释'],
  changed_by: ['变更人', 'dms_document_version 无注释'],
  change_note: ['变更说明', 'dms_document_version 无注释'],
  app_key: ['应用 Key', 'openapi_app 无注释；注释里的 ak_ 前缀不适合做标题'],
  app_secret: ['应用密钥', 'openapi_app 无注释；注释说明「仅展示一次」不适合做标题'],
  default_ledger: ['默认账簿', '非 DB 列：/finance/company/list 的嵌套对象，纯注释种子会漏'],
  shipment_code: ['运单号', '非 DB 列：服务端算出的别名，纯注释种子会漏'],
  // 服务端算出的引用单号（与 shipment_code 同款别名，列表列 + 抽屉都可能走 keyTitle）
  receive_code: ['收货单号', '非 DB 列：服务端按 source_id 反查出的别名，纯注释种子会漏'],
  delivery_code: ['发货单号', '非 DB 列：服务端按 source_id 反查出的别名，纯注释种子会漏'],
  // 比价面板（/purchase/rfq/{id}/compare 回包，showResult 渲染）：容器键与算出来的字段都不是列
  rfq: ['询价单', '非 DB 列：比价回包的嵌套对象（询价单头）'],
  quotes: ['报价', '非 DB 列：比价回包的报价数组'],
  is_lowest: ['最低价', '非 DB 列：比价回包算出的最低价标记'],
  target_total: ['目标总额', '非 DB 列：比价回包算出的目标总额'],
  target_amount: ['目标金额', '非 DB 列：比价矩阵行金额（target_price × quantity）'],
  lowest_quote_id: ['最低价报价ID', '非 DB 列：比价回包算出的最低价报价（无对应 *_name 兄弟键，affix 兜底取不到）'],
  quote_prices: ['报价单价', '非 DB 列：比价矩阵的报价单价数组（供应商 × 单价）'],
  product_code: ['商品编码', '非 DB 列：比价矩阵补出的 product.code'],
  buyer_real_name: ['采购员', '非 DB 列：purchase_rfq.buyer_id 的名称兄弟键；buyer_name 已被税票的购买方名称占用，故避开'],
  // `*_by`/`assigned_to` 外键的名称兄弟键（B7 服务端补产出）。键名机械 = 外键名切末 3 字符 + `_name`
  // （同前端 columns.ts:379-384 / relation.ts:63 的取名口径；`_by`/`_to` 恰好也是 3 字符）。
  // 不登记就走 keyTitle 的 affix 兜底 —— base 是 approved/audited/created/assigned，都不是列名，
  // 取不到对端条目 ⇒ 落驼峰 approvedName；而原来那行（approved_by）已被 siblingScalar 跳过
  approved_name: ['审批人', '非 DB 列：finance/expense、oms/rma 的 approved_by 名称兄弟键'],
  assigned_name: ['指派人员', '非 DB 列：wms/{pick,pack,putaway}-task 的 assigned_to 名称兄弟键'],
  audited_name: ['审核人', '非 DB 列：finance/invoice 的 audited_by 名称兄弟键'],
  created_name: ['创建人', '非 DB 列：openapi/app、openapi/webhook 的 created_by 名称兄弟键'],
  // 工资条（/hr/salary/{id}/payslip 回包）：头行 + 明细 + 社保三段
  salary: ['工资条', '非 DB 列：payslip 回包的工资头行对象'],
  social: ['社保', '非 DB 列：payslip 回包的社保段（未绑定/计算失败时为 null）'],
  // 通用容器键与报表 report_data 内层
  items: ['明细', '非 DB 列：payslip/比价等回包的明细数组'],
  lines: ['明细行', '非 DB 列：报表 report_data.lines（凭证按科目汇总行）'],
  generated_from: ['数据来源', '非 DB 列：报表 report_data.generated_from（实时算 or 快照）'],
  voucher_count: ['凭证数', '非 DB 列：现金流量表 report_data.voucher_count'],
};

/* ── 5. 合并 ───────────────────────────────────────────────────────────────────── */

const extra = new Map(); // 键 → [标题, 来源]
const unresolved = []; // 有注释但规范化后不可用、又没进显式表的列名

for (const [col, list] of commentOf) {
  if (col in BASE) continue;
  const seen = new Map();
  for (const c of list) {
    const t = usable(c);
    if (t) seen.set(t, (seen.get(t) ?? 0) + 1);
  }
  if (seen.has(COLLIDE[col]?.[0])) continue; // 裁决表优先（仍在下面统一落表）
  if (!seen.size) {
    if (!(col in CLEAN) && !(col in MANUAL)) unresolved.push(col);
    continue;
  }
  if (seen.size === 1) {
    extra.set(col, [[...seen.keys()][0], 'install.sql 列注释']);
    continue;
  }
  if (!(col in COLLIDE)) unresolved.push(`${col}（多候选需裁决：${[...seen.keys()].join(' | ')}）`);
}

// 显式表逐条落表；未被注释支撑的键（无注释/非 DB 列）照收 —— 它们本就是来兜底的
for (const [col, [title, why]] of Object.entries({ ...COLLIDE, ...CLEAN, ...MANUAL })) {
  if (col in BASE) continue;
  const auto = extra.get(col);
  if (auto && auto[0] === title && !(col in COLLIDE)) continue; // 与注释结论一致，保留自动来源
  extra.set(col, [title, col in COLLIDE ? `裁决：${why || '众数'}` : why]);
}

// 裁决表里的值必须在注释候选或显式档里对得上，防手滑
for (const [col, [title]] of Object.entries({ ...CLEAN, ...MANUAL })) {
  if (col in BASE) continue;
  if (extra.get(col)?.[0] !== title) unresolved.push(`${col}（显式档未生效，实得 ${extra.get(col)?.[0]}）`);
}

if (unresolved.length) {
  console.error(`FAIL ${unresolved.length} 个列名没有可用标题，请补 COLLIDE/CLEAN/MANUAL：`);
  for (const u of unresolved) console.error(`  ${u}`);
  process.exit(1);
}

/* ── 6. 覆盖率闸门 ──────────────────────────────────────────────────────────────
 * 判据是「会不会掉进驼峰兜底」：每个展示列都得在标题表里有键。
 * 不看值的字形 —— 人工档的 `SKU ID` / `BOM` 是合格标题，命中了就合法。
 */

/** 与 columns.ts / defaults.tsx 的 HIDDEN 同集：这些列不展示，无需标题 */
const HIDDEN = new Set([
  'id', 'created_at', 'updated_at', 'deleted_at',
  'password', 'remember_token', 'tenant_id', 'org_id', 'version',
]);
const merged = { ...BASE, ...Object.fromEntries([...extra].map(([k, [t]]) => [k, t])) };
const missing = [...allColumns].filter((c) => !HIDDEN.has(c) && !merged[c]);
if (missing.length) {
  console.error(`FAIL 未命中标题表的展示列 ${missing.length} 个`);
  for (const c of missing.slice(0, 20)) console.error(`  ${c}`);
  process.exit(1);
}

/* ── 7. 落盘 ───────────────────────────────────────────────────────────────────── */

/** 稳定排序（按列名），保证幂等；分片只是为了 500 行硬线 */
const entries = [...extra].sort(([a], [b]) => (a < b ? -1 : 1));
const chunks = [];
for (let i = 0; i < entries.length; i += MAX_PER_PART) chunks.push(entries.slice(i, i + MAX_PER_PART));

const files = [];
for (let i = 0; i < chunks.length; i++) {
  const body = chunks[i].map(([k, [t, why]]) => `  ${k}: ${q(t)}, // ${why}`).join('\n');
  files.push([
    `config/column-titles-extra/part${i + 1}.ts`,
    `${HEADER}\n/** 生成物 —— 由 \`scripts/gen-column-titles.mjs\` 从 install.sql 列注释导出（第 ${i + 1}/${chunks.length} 片）。请勿手工编辑。 */\nexport const COLUMN_TITLES_PART${i + 1}: Record<string, string> = {\n${body}\n};\n`,
  ]);
}
const imports = chunks.map((_, i) => `import { COLUMN_TITLES_PART${i + 1} } from './part${i + 1}';`).join('\n');
const spreads = chunks.map((_, i) => `  ...COLUMN_TITLES_PART${i + 1},`).join('\n');
files.push([
  'config/column-titles-extra/index.ts',
  `${HEADER}\n/** 生成物 —— 由 \`scripts/gen-column-titles.mjs\` 汇总 part*.ts。请勿手工编辑。 */\n${imports}\n\nexport const COLUMN_TITLES_EXTRA: Record<string, string> = {\n${spreads}\n};\n`,
]);

const APPS = ['apps/angular/src/app', 'apps/react/src'];
if (CHECK_ONLY) {
  /* `--check` 必须**真读盘**：以前这里只打印计数就 rc=0，于是「改 react 侧切片的值 / 删掉整片 /
   * 给 install.sql 加列」三种漂移全都静默通过，而它打印的「N 条落盘条目」声称了一件没做的事。
   * 判据 = 生成的文本与仓内落盘物**逐字节**一致（等价于写临时目录再比，但不写盘）+ 文件集不多不少。 */
  const drift = [];
  for (const app of APPS) {
    const dir = path.join(ROOT, app, 'config/column-titles-extra');
    const want = new Map(files.map(([rel, text]) => [path.basename(rel), text]));
    for (const [name, text] of want) {
      const p = path.join(dir, name);
      if (!fs.existsSync(p)) drift.push(`${app}/config/column-titles-extra/${name} 缺失`);
      else if (fs.readFileSync(p, 'utf8') !== text) drift.push(`${app}/config/column-titles-extra/${name} 内容漂移`);
    }
    for (const f of fs.existsSync(dir) ? fs.readdirSync(dir) : []) {
      if (/^(part\d+|index)\.ts$/.test(f) && !want.has(f)) drift.push(`${app}/config/column-titles-extra/${f} 多余（install.sql 里没有对应条目）`);
    }
  }
  if (drift.length) {
    console.error(`FAIL 落盘物与 install.sql 导出不一致 ${drift.length} 处（跑 node scripts/gen-column-titles.mjs 重生）：`);
    for (const d of drift.slice(0, 20)) console.error(`  ${d}`);
    process.exit(1);
  }
  console.log(`ok   ${extra.size} 个新键 / ${entries.length} 条落盘条目 / ${chunks.length} 片 —— 两端落盘物逐字节一致`);
} else {
  for (const app of APPS) {
    const dir = path.join(ROOT, app, 'config/column-titles-extra');
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    for (const [rel, text] of files) fs.writeFileSync(path.join(dir, path.basename(rel)), text);
  }
  const words = [...new Set(entries.map(([, [t]]) => t))].sort();
  fs.writeFileSync('/tmp/new_titles.txt', words.join('\n') + '\n');
  console.log(`ok   新键 ${extra.size}（既有 ${Object.keys(BASE).length} → 合计 ${Object.keys(merged).length}）`);
  console.log(`ok   分片 ${chunks.length} 份，两端各写入 ${path.join(APPS[0], 'config/column-titles-extra')} 等 ${APPS.length} 处`);
  console.log(`ok   新词条 ${words.length} 条 → /tmp/new_titles.txt`);
}
