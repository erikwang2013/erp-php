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

// 列：名 / 类型 / 是否可空 / 有无默认 / 是否自增
const colRows = q(
  `SELECT table_name, column_name, data_type, is_nullable, column_default IS NOT NULL, extra,
          COALESCE(character_maximum_length, 0)
     FROM information_schema.columns WHERE table_schema='${DB}' ORDER BY table_name, ordinal_position`
).split('\n').filter(Boolean).map((l) => l.split('\t'));
// 唯一索引（含主键）：非唯一索引不算
const uniqRows = q(
  `SELECT table_name, index_name, column_name FROM information_schema.statistics
    WHERE table_schema='${DB}' AND non_unique=0 ORDER BY table_name, index_name, seq_in_index`
).split('\n').filter(Boolean).map((l) => l.split('\t'));

const schema = new Map();
for (const [t, c, type, nullable, hasDef, extra, len] of colRows) {
  if (!schema.has(t)) schema.set(t, { cols: [], uniq: new Set(), pk: 'id' });
  schema.get(t).cols.push({ name: c, type: (type || '').toUpperCase(), notNull: nullable === 'NO', hasDefault: hasDef === '1', auto: /auto_increment/i.test(extra || ''), max: Number(len) || 0 });
}
for (const [t, idx, c] of uniqRows) {
  const s = schema.get(t);
  if (!s) continue;
  if (idx === 'PRIMARY') { s.pk = c; continue; }
  s.uniq.add(c);
}

const short = (t) => (t.startsWith('erp_') ? t.slice(4) : t);
const INT = new Set(['BIGINT', 'INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'INTEGER']);

function fkIds(colName, ids) {
  if (!colName.endsWith('_id')) return null;
  const base = colName.slice(0, -3);
  const exact = [`erp_${base}`, `erp_${base}s`, `erp_product_${base}`];
  for (const t of exact) if (ids.has(t) && ids.get(t).length) return ids.get(t);
  // 后缀匹配：level_id → erp_customer_level / erp_supplier_level 之类
  for (const t of ids.keys()) {
    if (t.endsWith(`_${base}`) && ids.get(t).length) return ids.get(t);
  }
  return null;
}

/** 字符串字面量：按列上限截断（短列否则会 Data too long），并转义单引号 */
function qs(v, col) {
  let s = String(v);
  if (col.max > 0 && s.length > col.max) s = s.slice(0, col.max);
  return `'${s.replace(/'/g, "''")}'`;
}

function lit(col, n, table, ids, isUniq) {
  const name = col.name;
  const fk = fkIds(name, ids);
  if (fk) return fk[(n - 1) % fk.length];
  // 唯一键列绝不允许常量（否则三行同值必撞 uk_*）；无对应表时退化为行号
  if (name.endsWith('_id')) return isUniq ? String(n) : '0';
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
  if (name.endsWith('_at')) return 'NOW()';
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
const blocks = [];
for (const [table, s] of schema) {
  if (HAND_WRITTEN.has(table) || SEEDED.has(table) || SKIP.has(table)) continue;
  const rowIds = Array.from({ length: ROWS }, () => (ID_BASE + seq++ * 1000n + BigInt(1)).toString());
  // 需显式给值的列：NOT NULL（无默认或非自增）或 参与唯一键
  const need = s.cols.filter((c) => c.name !== s.pk && !c.auto && (c.notNull || s.uniq.has(c.name)));
  const rows = rowIds.map((id, i) => {
    const n = i + 1;
    return `(${[id, ...need.map((c) => lit(c, n, table, ids, s.uniq.has(c.name)))].join(', ')})`;
  });
  ids.set(table, rowIds);
  const collist = ['`' + s.pk + '`', ...need.map((c) => '`' + c.name + '`')].join(', ');
  blocks.push(`INSERT INTO \`${table}\` (${collist}) VALUES\n${rows.join(',\n')};`);
}

const generated = `${SENTINEL}\n-- 共 ${blocks.length} 张表（手工段已覆盖的表、install.sql 已种子的表均不在其中）\n\n${blocks.join('\n\n')}\n`;
if (!process.argv.includes('--install')) {
  fs.writeFileSync(STAGE, generated);
  console.log(`已生成 ${blocks.length} 段 → ${STAGE}（未触碰正式文件）`);
} else {
  const cur = fs.readFileSync(TARGET, 'utf8');
  const at = cur.indexOf(SENTINEL);
  fs.writeFileSync(TARGET, (at === -1 ? cur.trimEnd() + '\n\n' : cur.slice(0, at)) + generated);
  console.log(`已并入 ${blocks.length} 段 → ${TARGET}（手工段保留）`);
}
