#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 列推断自检：`src/app/pages/resource-page/columns.ts`
 *
 * 本仓 Angular 无测试运行器（angular.json 没有 test target），这里用 esbuild 把真身
 * 打成 ESM 再 import —— 跑的是源码本身，不是抄一份的副本（同 scripts/check-ng-spec-attrs.mjs 的思路）。
 *
 * 用法：node scripts/check-infer-columns.mjs   —— 全过 exit 0，有不符 exit 1
 */
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'node_modules/.cache/ngcheck/columns.mjs');

execFileSync(
  path.join(ROOT, 'node_modules/.bin/esbuild'),
  [
    'src/app/pages/resource-page/columns.ts',
    '--bundle',
    '--platform=node',
    '--format=esm',
    '--external:@angular/core',
    `--outfile=${OUT}`,
    '--log-level=warning',
  ],
  { cwd: ROOT, stdio: 'inherit' },
);

// i18n.service 在模块顶层读 localStorage（真实浏览器里才有）
globalThis.localStorage = { getItem: () => null, setItem() {} };
const { cellOf, inferColumns, keyTitle, relSources } = await import(OUT);

let total = 0;
let bad = 0;

/** 跑一组断言；分组只影响输出 */
function run(name, fn) {
  console.log(`\n── ${name} ──`);
  const cases = fn();
  for (const [label, got, want] of cases) {
    total++;
    const ok = JSON.stringify(got) === JSON.stringify(want);
    if (!ok) bad++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${label} → ${JSON.stringify(got)}`);
    if (!ok) console.error(`     期望 ${JSON.stringify(want)}`);
  }
  return cases;
}

/** 列定义压成 [key, title, kind] 便于比对 */
const slim = (rows, ...rest) =>
  inferColumns(rows, '/admin/v1/goods/product', ...rest).map((c) => [c.key, c.title, c.kind]);

run('A 表头标签：fields.label > 词典 > 驼峰兜底', () => [
  ['fields.label 命中', keyTitle('customer_id', '客户名称'), '客户名称'],
  ['词典命中', keyTitle('customer_id'), '客户'],
  ['驼峰兜底（未知键不留空白）', keyTitle('weird_key_x'), 'weirdKeyX'],
  [
    '推断列用 fields.label 覆盖词典',
    slim([{ customer_id: 'h1' }], [{ key: 'customer_id', label: '客户名称' }]),
    [['customer_id', '客户名称', 'text']],
  ],
]);

run('B 关联列 rule 1：`_name` 兄弟 → 名称列 + 隐藏 id 列', () => {
  const cols = slim(
    [{ customer_id: 'h1', customer_name: '客户甲' }],
    [{ key: 'customer_id', label: '客户' }],
  );
  const cell = cellOf(inferColumns([{ customer_id: 'h1', customer_name: '客户甲' }], '/x')[0], {
    customer_id: 'h1',
    customer_name: '客户甲',
  });
  return [
    ['id 列被隐藏、名称列用 id 列的标题', cols, [['customer_name', '客户', 'text']]],
    ['名称列渲染名称', cell.text, '客户甲'],
  ];
});

run('C 关联列 rule 2：嵌套关系对象 → 对象里的名称', () => {
  const rows = [{ category_id: '3p', category: { id: '3p', name: '配件' }, code: 'A' }];
  const cols = inferColumns(rows, '/admin/v1/goods/product');
  const rel = cols.find((c) => c.key === 'category');
  return [
    [
      '列键换成关系对象的键（id 列不重复出现）',
      slim(rows),
      [
        ['code', '编号', 'text'],
        ['category', '分类', 'rel'],
      ],
    ],
    ['对象名称渲染', cellOf(rel, rows[0]).text, '配件'],
  ];
});

run('D 关联列 rule 3：source 选项映射 id → 名称', () => {
  const fields = [
    { key: 'supplier_id', label: '供应商', source: { endpoint: '/admin/v1/supplier' } },
  ];
  const labels = { '/admin/v1/supplier': { h9: '供应商甲' } };
  const rows = [{ supplier_id: 'h9', name: '螺丝' }];
  const cols = inferColumns(rows, '/admin/v1/goods/product', fields, labels);
  const rel = cols.find((c) => c.key === 'supplier_id');
  const hit = cellOf(rel, { supplier_id: 'h9' });
  const miss = cellOf(rel, { supplier_id: 'h404' });
  return [
    ['列变 rel（不再显示裸 id）', [rel.title, rel.kind], ['供应商', 'rel']],
    ['命中 → 名称', hit.text, '供应商甲'],
    ['未命中 → 原值（不隐藏、不空）', miss.text, 'h404'],
    [
      '选项未加载（labels 为空）→ 退回普通列原值',
      slim(rows, fields),
      [
        ['name', '名称', 'text'],
        ['supplier_id', '供应商', 'text'],
      ],
    ],
  ];
});

run('E 关联列 rule 4：拿不到名称 → 原值照常显示', () => {
  const rows = [{ supplier_id: 'h7', name: '螺丝' }];
  const cols = inferColumns(rows, '/admin/v1/goods/product');
  return [
    [
      '无 fields/labels 时仍是普通列',
      slim(rows),
      [
        ['name', '名称', 'text'],
        ['supplier_id', '供应商', 'text'],
      ],
    ],
    ['单元格给原值', cellOf(cols[1], rows[0]).text, 'h7'],
    ['空值给短横（format.text 兜底）', cellOf(cols[1], { supplier_id: null }).text, '-'],
  ];
});

run('F 取数需求：只为「行内没有名称」的外键拉选项', () => {
  const fields = [
    { key: 'supplier_id', label: '供应商', source: { endpoint: '/admin/v1/supplier' } },
    { key: 'customer_id', label: '客户', source: { endpoint: '/admin/v1/customer' } },
  ];
  return [
    [
      '行内有 `<base>_name` / 关系对象 → 不拉',
      relSources(
        [
          { customer_id: 'h1', customer_name: '客户甲' },
          { supplier_id: 'h2', supplier: { name: '供应商甲' } },
        ],
        fields,
      ),
      [],
    ],
    [
      '行内只有裸 id → 按 endpoint 去重后拉起',
      relSources([{ supplier_id: 'h2' }, { supplier_id: 'h3' }, { customer_id: 'h4' }], fields).map(
        (s) => s.endpoint,
      ),
      ['/admin/v1/supplier', '/admin/v1/customer'],
    ],
    [
      '没有 source 字段 → 空',
      relSources([{ supplier_id: 'h2' }], [{ key: 'supplier_id', label: '供应商' }]),
      [],
    ],
  ];
});

if (bad) {
  console.error(`\nFAIL ${bad} 项不符（共 ${total} 例）`);
  process.exit(1);
}
console.log(`\nPASS ${total} 例全过`);
