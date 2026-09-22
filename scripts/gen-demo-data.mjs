#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 演示（测试）数据生成器。
 *
 * **结构来源是数据库本身（information_schema），不是解析 install.sql 文本。**
 * 早期版本用正则解析建表语句，反复漏列（如 `key`/保留字、非常规写法），
 * 于是改为问数据库 —— 它永远知道自己的真实结构，没有解析器可言。
 *
 * 用法（先在一个装了 install.sql 的库上跑）：
 *   TEST_DB_DATABASE=erp_demo_check node scripts/gen-demo-data.mjs            # 生成到 /tmp，不碰正式文件
 *   TEST_DB_DATABASE=erp_demo_check node scripts/gen-demo-data.mjs --check    # 只校验：仓内生成段与刚算出的产物逐字节比对，漂移点名到表
 *   TEST_DB_DATABASE=erp_demo_check node scripts/gen-demo-data.mjs --install  # 验证通过后再并入 database/install-demo.sql
 *
 * 两段式是刻意的：**生成器绝不直接写正式文件**。曾直接覆盖过一次，既删了手工段、又因唯一键撞车
 * 导不进去，靠 git 才救回 —— 现在必须先落 /tmp、导入验证过，才允许 --install 并入。
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const TARGET = path.join(ROOT, 'database/install-demo.sql');
const STAGE = '/tmp/install-demo.generated.sql';
const SENTINEL = '-- ==== 生成段开始（scripts/gen-demo-data.mjs 产出，勿手工编辑）====';
const ID_BASE = 410000000000000000n;
const ROWS = 3;

const HAND_WRITTEN = new Set([
  'erp_brand', 'erp_warehouse', 'erp_location', 'erp_product_spec', 'erp_product',
  'erp_product_sku', 'erp_customer_level', 'erp_customer', 'erp_supplier',
]);
const SEEDED = new Set([
  'erp_admin_permission', 'erp_admin_role', 'erp_admin_role_permission',
  'erp_finance_currency', 'erp_finance_tax_rate', 'erp_dms_category',
  'erp_crm_funnel_stage', 'erp_crm_analytics_metric',
]);
const SKIP = new Set(['erp_admin_user', 'erp_admin_user_role', 'erp_operation_log']);
const HAND_IDS = {
  erp_brand: ['410000000000000001', '410000000000000002', '410000000000000003'],
  erp_warehouse: ['410000000000000101', '410000000000000102'],
  erp_location: ['410000000000000201', '410000000000000202', '410000000000000203'],
  erp_product_spec: ['410000000000000301', '410000000000000302', '410000000000000303'],
  erp_product: ['410000000000000401', '410000000000000402', '410000000000000403', '410000000000000404', '410000000000000405', '410000000000000406'],
  erp_product_sku: ['410000000000000501', '410000000000000502', '410000000000000503', '410000000000000504', '410000000000000505', '410000000000000506'],
  erp_customer_level: ['410000000000000601', '410000000000000602', '410000000000000603'],
  erp_customer: ['410000000000000701', '410000000000000702', '410000000000000703'],
  erp_supplier: ['410000000000000801', '410000000000000802', '410000000000000803'],
};

/** 读 .env 的 DB_* 建临时 client 配置（不把口令放进命令行，避免 ps 泄露） */
function mysqlArgs(dbName) {
  const env = fs.readFileSync(path.join(ROOT, '.env'), 'utf8');
  const get = (k) => (env.match(new RegExp(`^${k}=(.*)$`, 'm')) ?? [, ''])[1].trim();
  const cnf = '/tmp/gen-demo.cnf';
  fs.writeFileSync(cnf, `[client]\nhost=${get('DB_HOST') || '127.0.0.1'}\nport=${get('DB_PORT') || 3306}\nuser=${get('DB_USERNAME') || 'root'}\npassword=${get('DB_PASSWORD')}\n`);
  fs.chmodSync(cnf, 0o600);
  return ['--defaults-extra-file=' + cnf, '-N', '-B', dbName];
}

const DB = process.env.TEST_DB_DATABASE;
if (!DB) {
  console.error('必须设 TEST_DB_DATABASE（指向已导入 install.sql 的库）');
  process.exit(1);
}
const args = mysqlArgs(DB);
const q = (sql) => execFileSync('mysql', [...args, '-e', sql], { encoding: 'utf8' }).trim();

// 列：名 / 类型 / 是否可空 / 有无默认 / 是否自增 / 长度 / 注释（注释点名外键目标表，见 fkIds）/ 默认值文本
// 默认值文本**排在最后**：它是唯一可能含制表符的字段，放末尾就不会把前面几列挤错位。
const colRows = q(
  `SELECT table_name, column_name, data_type, is_nullable, column_default IS NOT NULL, extra,
          COALESCE(character_maximum_length, 0), COALESCE(column_comment, ''), COALESCE(column_default, '')
     FROM information_schema.columns WHERE table_schema='${DB}' ORDER BY table_name, ordinal_position`
).split('\n').filter(Boolean).map((l) => l.split('\t'));
// 唯一索引（含主键）：非唯一索引不算
const uniqRows = q(
  `SELECT table_name, index_name, column_name FROM information_schema.statistics
    WHERE table_schema='${DB}' AND non_unique=0 ORDER BY table_name, index_name, seq_in_index`
).split('\n').filter(Boolean).map((l) => l.split('\t'));

const schema = new Map();
for (const [t, c, type, nullable, hasDef, extra, len, cm, def] of colRows) {
  if (!schema.has(t)) schema.set(t, { cols: [], uniq: new Set(), pk: 'id' });
  schema.get(t).cols.push({ name: c, type: (type || '').toUpperCase(), notNull: nullable === 'NO', hasDefault: hasDef === '1', auto: /auto_increment/i.test(extra || ''), max: Number(len) || 0, comment: cm, def: def || '' });
}
for (const [t, idx, c] of uniqRows) {
  const s = schema.get(t);
  if (!s) continue;
  if (idx === 'PRIMARY') { s.pk = c; continue; }
  s.uniq.add(c);
}

const short = (t) => (t.startsWith('erp_') ? t.slice(4) : t);
const INT = new Set(['BIGINT', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'INTEGER']);
const NUM = new Set([...INT, 'DECIMAL', 'DOUBLE', 'FLOAT']);
const STR = new Set(['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT']);
/**
 * 名字规则定不了目标表、但列注释能把目标表钉死的列。只收「目标唯一」的：
 *   erp_check_detail.check_id              盘点任务ID   → erp_check_task（后缀 `_check` 在库里无表）
 *   erp_wms_putaway_item.putaway_id        上架任务ID   → erp_wms_putaway_task（表名是 `*_putaway_task`）
 *   erp_mfg_bom_item.component_product_id  组成件产品ID → erp_product（库里没有 component_product 表）
 *   erp_finance_allocation.{source,target}_center_id → erp_finance_cost_center（注释写「成本中心」；
 *     库里另有 erp_finance_profit_center，两者都以后缀 `_center` 入候选，靠 A2 的长度兜底太脆 ⇒ 钉死）
 *   erp_cost_record.flow_id                库存流水ID   → erp_inventory_flow（后缀 `_flow` 有两个候选：
 *     erp_inventory_flow「库存流水日志表」与 erp_mfg_wip_flow「工单成本归集流水」，A2 按名字短的长度兜底
 *     会选后者 —— 注释与表注释对得上的是前者）
 * 刻意不收三张真多态外键（approval_instance.target_id / finance_ar_ap.partner_id /
 * finance_settlement.receipt_payment_id）：目标表由判别列决定，猜一个就是假关联。
 */
const MANUAL_FK = {
  'erp_check_detail.check_id': 'erp_check_task',
  'erp_wms_putaway_item.putaway_id': 'erp_wms_putaway_task',
  'erp_mfg_bom_item.component_product_id': 'erp_product',
  'erp_finance_allocation.source_center_id': 'erp_finance_cost_center',
  'erp_finance_allocation.target_center_id': 'erp_finance_cost_center',
  'erp_cost_record.flow_id': 'erp_inventory_flow',
  'erp_oms_inventory_reservation.source_id': 'erp_oms_order',
};
/**
 * 多态外键：`<base>_id` 指向哪张表由同表的判别列（`<base>_type` / `biz_type`）决定，指向任何单一
 * 目标都是假关联 —— 段里 12 个 `source_id` 曾整片指到 erp_finance_voucher_source（A2 后缀匹配的
 * 唯一 `*_source` 表），而它们的注释写的是「来源单据ID」，判别列分别是 manual/receipt、
 * purchase_receive/sales_delivery、iqc/ipqc/oqc、领料/人工/制费…。
 * **只收逐列核过注释的**，不做「表里有 `<base>_type` 就算多态」的通用判定：命中集合 16 列里有误伤 ——
 *   erp_hr_perf_score.rater_id：`rater_type` 是「1自评/2上级/3同事360」的角色快照，rater 永远是员工，
 *     现指 erp_hr_employee 是对的（且它是 uk_plan_emp_rater_indicator 成员）；
 *   erp_oms_inventory_reservation.source_id：注释直接写「OMS订单ID」⇒ 目标唯一，进 MANUAL_FK。
 * 值给 0；唯一键成员退化为行号（uk_source(source_type, source_id) 三行同值会撞）。
 */
const POLY_FK = new Set([
  'erp_finance_ar_ap.source_id',
  'erp_finance_bill.source_id',
  'erp_finance_cash_journal.source_id',
  'erp_finance_invoice.source_id',
  'erp_finance_invoice_match_log.source_id',
  'erp_finance_tax_record.source_id',
  'erp_finance_voucher_source.source_id',
  'erp_inventory_flow.source_id',
  'erp_mfg_wip_flow.source_id',
  'erp_notification.source_id',
  'erp_quality_nonconformity.source_id',
]);
// 用户外键的基名：目标表 erp_admin_user 既不在生成范围也不在种子范围（文件头约定 3/6：本文件不建用户）
// ⇒ 填 0 是设计，不是解析失败。警告块里单独一类，别混进「无目标表」。
const USER_BASES = new Set(['user', 'submitter', 'approver', 'operator', 'creator', 'updater', 'auditor', 'handler']);
const isUserFk = (base) => USER_BASES.has(base) || base.endsWith('_user');
// 外键列名：`*_id` 一律算；`*_by` 只在整数列时才算 —— erp_dms_document_version.changed_by /
// erp_quality_nonconformity.reported_by 是 varchar，存的是人名，当外键填 0 是错的。
const fkName = (col) => col.name.endsWith('_id') || (col.name.endsWith('_by') && INT.has(col.type));

/**
 * 列注释里点名的目标表。只认两种写法：
 *   `关联 erp_sales_order.id` / `模板ID（erp_hr_kpi_template.id）`  → 取 `.id` 前的表名
 *   `生产工单ID(erp_mfg_production_order)` / `社保规则ID(erp_hr_social_rule)` → 括号里就是一整张表名
 * 故意不收「源 erp_product_sku.product_id」这类**出处**说明 —— 它指的是「那张表的某个列」，
 * 不是「外键指向那张表」；收进来会把 product_id 指到 SKU 上（install.sql 里真有这条注释）。
 */
function commentTable(col) {
  const cm = col.comment || '';
  const byDot = cm.match(/erp_[a-z0-9_]+\.id(?![a-z0-9_])/);
  if (byDot) return byDot[0].slice(0, -3);
  const byParen = cm.match(/[（(]\s*(erp_[a-z0-9_]+)\s*[）)]/);
  return byParen ? byParen[1] : null;
}

function fkIds(col, table, ids) {
  const colName = col.name;
  if (!fkName(col)) return null;
  // 规则 P：多态外键不参与猜测（见 POLY_FK 注释）
  if (POLY_FK.has(`${table}.${colName}`)) return null;
  // 规则 0：名字规则定不了的少数列，映射表直接钉死（见 MANUAL_FK 注释）
  const forced = MANUAL_FK[`${table}.${colName}`];
  if (forced && ids.get(forced)?.length) return ids.get(forced);
  // 规则 A：列注释点名了目标表就用它。名字猜测在真实库里错得离谱 —— order_id 会收敛到
  // 字母序最前的 erp_eam_repair_order（见下方后缀匹配），rule_id 撞上 erp_crm_customer_pool_rule。
  const named = commentTable(col);
  if (named && ids.has(named) && ids.get(named).length) return ids.get(named);
  // 规则 A1：方向前缀（from_/to_/source_/target_/src_/dst_）不是实体名的一部分 —— `to_warehouse_id`
  // 的目标表是 erp_warehouse，不是 erp_to_warehouse。剥掉前缀后走同一套规则，且只在原名落空后才试。
  const base = colName.slice(0, -3);
  const bases = [base, base.replace(/^(from|to|source|target|src|dst)_/, '')].filter((b, i, a) => b && a.indexOf(b) === i);
  const mine = short(table);
  const score = (t) => (short(t).split('_')[0] === mine.split('_')[0] ? 0 : 2) + (t.includes(mine) ? 0 : 1);
  for (const b of bases) {
    const exact = [`erp_${b}`, `erp_${b}s`, `erp_product_${b}`];
    for (const t of exact) if (ids.has(t) && ids.get(t).length) return ids.get(t);
    // 规则 A2：注释没点名时的兜底 —— 候选按「同模块 → 表名含引用方短名 → 名字更短」排序后取第一个。
    // 单纯取后缀命中里的第一个（= 字母序最前）就是跨模块乱指的根源。
    const cands = [...ids.keys()].filter((t) => t.endsWith(`_${b}`) && ids.get(t).length);
    cands.sort((a, b2) => score(a) - score(b2) || a.length - b2.length || (a < b2 ? -1 : 1));
    if (cands.length) return ids.get(cands[0]);
  }
  return null;
}

/** 字符串字面量：按列上限截断（短列否则会 Data too long），并转义单引号 */
function qs(v, col) {
  let s = String(v);
  if (col.max > 0 && s.length > col.max) s = s.slice(0, col.max);
  return `'${s.replace(/'/g, "''")}'`;
}

// 「去处」侧的方向前缀：from_/to_、source_/target_ 指同一张表时，两列取同一行会生成
// 「从 A 仓调到 A 仓」「从 A 成本中心分摊到 A」这种业务上不成立的行（erp_transfer 的 3 行就会
// 全成自环）。去处侧错开一行即可 —— 3 行时正好是 A→B / B→C / C→A，也是签入段里手工调出的形状。
const SECOND_DIR = /^(to|target|dst)_/;

function lit(col, n, table, ids, isUniq) {
  const name = col.name;
  const fk = fkIds(col, table, ids);
  if (fk) return fk[(n - 1 + (SECOND_DIR.test(name) ? 1 : 0)) % fk.length];
  // 规则 B：必填外键（NOT NULL 且无默认值）解析不到目标表时，不能装作没事 —— 记下来，
  // 收尾时打到 stderr 并写进生成段注释。静默填 0 的数据看起来正常，是这轮修了 23 列的那种坑。
  // 用户外键（目标表 erp_admin_user）是文件头约定 3/6 的设计，单列一类，不混进「无目标表」。
  // 多态外键单列一类：值 0（或唯一键成员的行号）是设计，不是「没找到目标表」，别混进 unresolved
  const poly = POLY_FK.has(`${table}.${name}`);
  if (poly) polyFk.add(`${table}.${name}`);
  if (!poly && fkName(col) && col.notNull && !col.hasDefault) {
    (isUserFk(name.slice(0, -3)) ? userFk : unresolved).add(`${table}.${name}`);
  }
  // 唯一键列绝不允许常量（否则三行同值必撞 uk_*）；无对应表时退化为行号
  if (fkName(col)) return isUniq ? String(n) : '0';
  // 规则 C：DDL 默认值优先于「类型/列名兜底」。默认值按构造落在列的域内 —— varchar 的 status
  // 默认 'draft'、tinyint 的 status 默认 1，而兜底给的是 'd' / 0，两者都在域外（前端只能裸出
  // '1'、0 读作未启用）。唯一键列例外（三行同值会撞 uk_*）；数值列只在默认值非零时启用
  // （DEFAULT 0.00 满地都是，换掉它只会让全文抖动而无收益）。
  // 表达式默认值（`text DEFAULT ('')`）在 MySQL 8 里回的是 `_utf8mb4\'\'` 这种畸形串 ⇒ 只认普通字面量。
  if (!isUniq && col.hasDefault && /^[^'\\\t\n]+$/.test(col.def)) {
    if (STR.has(col.type)) return qs(col.def, col);
    const num = Number(col.def);
    if (NUM.has(col.type) && Number.isFinite(num) && num !== 0) return String(num);
  }
  // 规则 D：年度/月份列 DDL 没有默认值，类型兜底只会给 0（月份）或行号（UK 成员）——
  // 于是「报表年度=1」「薪资月份=0」这种一眼假的数据。年度取当前年（与 `_at` 取 NOW() 同一个
  // 道理：演示数据要看着像现在），月份取行号 1..N；报表类的 (账套,年,月) UK 由月份的差异满足。
  if (INT.has(col.type)) {
    if (/^(?:.*_)?year$/.test(name)) return 'YEAR(CURDATE())';
    if (/^(?:.*_)?month$/.test(name)) return String(n);
    // 规则 E：注释宣告了码表（`类型: 1=入库 2=出库`）时取**首个码**。前提是它和兜底的 0 不同 ——
    // `状态: 0=待盘点 1=已盘点` 的首码就是 0，不动（0 是注释宣告过的值，不是域外）。
    // 落点在 INT 兜底这一支 ⇒ 抢不到规则 C（DDL 默认值，133 个命中列里 125 个已由它给对）与
    // 规则 D（年/月，如 uk_account_period 的 period_month 要逐行不同）；`status` 名字规则本来就在
    // INT 兜底之后，INT 列永远到不了那里。唯一键列除外（三行同值会撞 uk_*）。
    const code = (col.comment || '').match(/(?:^|[\s：:（(,，/])(\d{1,2})\s*=[^\s=]/);
    if (!isUniq && code && Number(code[1]) !== 0) return String(Number(code[1]));
  }
  // **类型优先于列名**：真实库里存在「列名叫 email 但类型是 INT」这类情形，
  // 若先按列名给字符串就会 Incorrect integer value。数值列一律给数字。
  if (INT.has(col.type)) return isUniq ? String(n) : '0';
  if (['DECIMAL', 'DOUBLE', 'FLOAT'].includes(col.type)) return '0.00';
  if (['status', 'enabled', 'is_base', 'is_default', 'is_primary'].includes(name)) return '1';
  if (name.startsWith('is_') || name === 'credit_frozen') return '0';
  if (name === 'sort') return String(n);
  if (/^(name|title|label)$/.test(name)) return qs(`演示${short(table)}${isUniq ? n : ''}`, col);
  if (/^(code|no|number|sku_code|barcode|username|slug|key)$/.test(name)) return qs(`DEMO-${short(table).toUpperCase()}-${n}`, col);
  if (name === 'group' || name === 'group_name') return qs(`g${n}`, col);
  // `*_at` 是 DATE 列时只能给 CURDATE()：NOW() 会被 MySQL 截断成日期并留下 Note 1292
  // （erp_tenant.expire_at 就是这种，装库时那 3 条告警就出自这里）
  if (name.endsWith('_at')) return col.type === 'DATE' ? 'CURDATE()' : 'NOW()';
  if (name === 'phone' || name === 'mobile') return qs(`1380000000${n}`, col);
  if (name === 'email') return qs(`demo${n}@example.com`, col);
  if (name === 'password') return qs('$2y$10$demodemodemodemodemodemodemodemodemodemodemodemodemo', col);
  if (/^(remark|description|address|content|note|reason)$/.test(name)) return qs(`演示数据${isUniq ? n : ''}`, col);
  if (name === 'version') return String(n);
  if (INT.has(col.type)) return isUniq ? String(n) : '0';
  if (['DECIMAL', 'DOUBLE', 'FLOAT'].includes(col.type)) return '0.00';
  if (col.type === 'JSON') return "'{}'";
  if (/^(DATETIME|TIMESTAMP)/.test(col.type)) return 'NOW()';
  if (col.type === 'DATE') return 'CURDATE()';
  if (col.type === 'TIME') return "'00:00:00'";
  if (col.type === 'ENUM') return 'NULL';
  return qs(`d${isUniq ? n : ''}`, col); // 兜底：极短值，短列也放得下
}

const ids = new Map(Object.entries(HAND_IDS));
let seq = 1n;
// 两趟：先把所有表的 ID 分配完，再生成字面量 —— 注释点名的目标表可能按字母序排在引用方之后
// （erp_hr_attendance.rule_id → erp_hr_attendance_rule），一趟里取不到就白白退化成 0。
const tables = [...schema].filter(([t]) => !HAND_WRITTEN.has(t) && !SEEDED.has(t) && !SKIP.has(t));
for (const [table] of tables) {
  ids.set(table, Array.from({ length: ROWS }, () => (ID_BASE + seq++ * 1000n + BigInt(1)).toString()));
}
// 种子表（install.sql 已插的币种/税率/CRM 阶段…）不在生成范围内，但它们**有真实行**；
// 引用它们的列（exchange_rate.from_currency_id、crm_opportunity.stage_id、tax_record.tax_rate_id…）
// 之前只能填 0。id 从库里读而不是在生成器里抄一份：种子行来自 install.sql，问数据库不会漂。
// erp_admin_role_permission 是复合主键的中间表，没有 id 列，跳过。
for (const t of SEEDED) {
  const s = schema.get(t);
  if (!s || s.pk !== 'id') continue;
  const rows = q(`SELECT id FROM \`${t}\` ORDER BY id LIMIT ${ROWS}`).split('\n').filter(Boolean);
  if (rows.length) ids.set(t, rows);
}
const unresolved = new Set();
const userFk = new Set();
const polyFk = new Set();
const blocks = [];
for (const [table, s] of tables) {
  const rowIds = ids.get(table);
  // 需显式给值的列：NOT NULL（无默认或非自增）或 参与唯一键
  const need = s.cols.filter((c) => c.name !== s.pk && !c.auto && (c.notNull || s.uniq.has(c.name)));
  const rows = rowIds.map((id, i) => {
    const n = i + 1;
    return `(${[id, ...need.map((c) => lit(c, n, table, ids, s.uniq.has(c.name)))].join(', ')})`;
  });
  const collist = ['`' + s.pk + '`', ...need.map((c) => '`' + c.name + '`')].join(', ');
  blocks.push(`INSERT INTO \`${table}\` (${collist}) VALUES\n${rows.join(',\n')};`);
}

if (unresolved.size) {
  console.error(`警告：${unresolved.size} 个必填外键（NOT NULL 且无默认值）没有唯一目标表，已填 0：\n  ${[...unresolved].join('\n  ')}`);
}
if (userFk.size) {
  console.error(`提示：${userFk.size} 个用户外键按设计填 0（本文件不建用户，见文件头约定 3/6）`);
}
if (polyFk.size) {
  console.error(`提示：${polyFk.size} 个多态外键按设计填 0/行号（目标表由判别列决定，见段头块）`);
}
const note = (title, set) => (set.size ? `-- ${title} ${set.size} 个：\n${[...set].map((c) => `--   ${c}`).join('\n')}\n` : '');
const warn = note('未解析的必填外键（多态：目标表由判别列决定，值是 0，不是真实关联）', unresolved)
  + note('按设计填 0 的多态外键（目标表由判别列决定，0/行号不是真实关联）', polyFk)
  + note('按设计填 0 的用户外键（本文件不建用户，见文件头约定 3/6）', userFk);
const generated = `${SENTINEL}\n-- 共 ${blocks.length} 张表（手工段已覆盖的表、install.sql 已种子的表均不在其中）\n${warn}\n${blocks.join('\n\n')}\n`;
// 只在比对/落盘处用到的辅助：把生成段切成「表名 → 该表的 INSERT 原文」
const byTable = (text) => {
  const out = new Map();
  let t = null;
  let buf = [];
  for (const line of text.split('\n')) {
    const g = line.match(/^INSERT INTO `([^`]+)`/);
    if (g) {
      if (t) out.set(t, buf.join('\n').trim());
      t = g[1];
      buf = [];
    }
    if (t) buf.push(line);
  }
  if (t) out.set(t, buf.join('\n').trim());
  return out;
};
if (process.argv.includes('--check')) {
  /* `--check` 的判据是**逐字节**：仓内生成段与本次算出的产物完全一致才算过。旧坑是「只打印计数、
   * 不读被检查的文件」，于是「改一个值 / 删一段 / install.sql 加一列」三种漂移全都 rc=0。
   * 不一致时点名到表 —— 光说「有漂移」等于让人自己去 diff 三百行。 */
  const cur = fs.readFileSync(TARGET, 'utf8');
  const at = cur.indexOf(SENTINEL);
  const drift = [];
  if (at === -1) {
    drift.push(`${TARGET} 里没有生成段哨兵（手工段被整段覆盖过？）`);
  } else if (cur.slice(at) !== generated) {
    const old = byTable(cur.slice(at));
    const want = byTable(generated);
    for (const t of old.keys()) if (!want.has(t)) drift.push(`${TARGET}: 生成段里多了 ${t}（install.sql 已无此表 / 表名变了）`);
    for (const [t, text] of want) {
      if (!old.has(t)) drift.push(`${TARGET}: 生成段缺 ${t}`);
      else if (old.get(t) !== text) drift.push(`${TARGET}: ${t} 内容漂移（列/值/行数与 install.sql 现状不一致）`);
    }
    if (!drift.length) drift.push(`${TARGET}: 生成段段头或分隔漂移（${blocks.length} 张表的内容本身一致）`);
  }
  if (drift.length) {
    console.error(`FAIL 生成段与当前 install.sql 结构不一致 ${drift.length} 处（跑 node scripts/gen-demo-data.mjs 重生）：`);
    for (const d of drift.slice(0, 20)) console.error(`  ${d}`);
    process.exit(1);
  }
  console.log(`ok   生成段逐字节一致：${blocks.length} 张表 / ${path.relative(ROOT, TARGET)}（含 ${unresolved.size} 个未解析、${polyFk.size} 个按设计的多态外键、${userFk.size} 个用户外键填 0）`);
} else if (!process.argv.includes('--install')) {
  fs.writeFileSync(STAGE, generated);
  console.log(`已生成 ${blocks.length} 段 → ${STAGE}（未触碰正式文件）`);
} else {
  const cur = fs.readFileSync(TARGET, 'utf8');
  const at = cur.indexOf(SENTINEL);
  fs.writeFileSync(TARGET, (at === -1 ? cur.trimEnd() + '\n\n' : cur.slice(0, at)) + generated);
  console.log(`已并入 ${blocks.length} 段 → ${TARGET}（手工段保留）`);
}
