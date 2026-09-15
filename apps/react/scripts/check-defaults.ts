/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * inferColumns 关联列解析的最小自检（无测试运行器，用断言脚本直调纯函数）。
 *
 * 运行：
 *   npx vite build --ssr scripts/check-defaults.ts --outDir node_modules/.cache/selfcheck --logLevel warn
 *   node node_modules/.cache/selfcheck/check-defaults.js
 */

import assert from 'node:assert/strict';

// api.ts 在模块顶层读 localStorage，必须先补桩再动态引入
const store = new Map<string, string>();
(globalThis as Record<string, unknown>).localStorage = {
  getItem: (k: string) => store.get(k) ?? null,
  setItem: (k: string, v: string) => void store.set(k, v),
  removeItem: (k: string) => void store.delete(k),
};
const HOUSE = [
  { id: 'h1', name: '华东仓' },
  { id: 'h2', name: '华南仓' },
];
(globalThis as Record<string, unknown>).fetch = async (url: string) => ({
  status: 200,
  json: async () => ({ code: 0, message: 'ok', data: { list: HOUSE, total: 2, page: 1, limit: 100 } }),
  _url: url,
});

const { inferColumns, keyTitle } = await import('@/lib/defaults');
const { prefetch } = await import('@/lib/options');
type Column = { key: string; title: string; render?: (row: Record<string, unknown>) => unknown };
type Row = Record<string, unknown>;

const ok: string[] = [];
const check = (name: string, fn: () => void) => {
  fn();
  ok.push(name);
};
const titleOf = (cols: Column[], k: string) => cols.find((c) => c.key === k)?.title;
const cellOf = (cols: Column[], k: string, row: Row) =>
  String(cols.find((c) => c.key === k)?.render?.(row) ?? '<no-column>');

// ── 场景 1：静态数据（全在一个资源页里覆盖 A/B 六种情形）───────────────
const fields = [
  { key: 'warehouse_id', label: '发货仓库', source: { endpoint: '/admin/v1/warehouse' } },
  { key: 'quantity', label: '下单数量' },
];
const rows: Row[] = [
  {
    id: 1,
    code: 'SO-1',
    some_unknown_field: 'x',
    warehouse_id: 'h1',
    quantity: 3,
    customer_id: 11,
    customer_name: '张三',
    supplier_id: 22,
    category_id: 33,
    category: { id: 33, name: '电子产品' },
  },
];
const cols = inferColumns(rows, '/admin/v1/sales/order', fields);

check('A1 fields.label 命中（含覆盖 TITLES）', () => {
  assert.equal(titleOf(cols, 'warehouse_id'), '发货仓库');
  assert.equal(titleOf(cols, 'quantity'), '下单数量');
});
check('A2 TITLES 命中', () => {
  assert.equal(keyTitle('supplier_id'), '供应商');
  assert.equal(titleOf(cols, 'supplier_id'), '供应商');
});
check('A3 驼峰兜底保留', () => {
  assert.equal(titleOf(cols, 'some_unknown_field'), 'someUnknownField');
  assert.equal(keyTitle('totally_unknown'), 'totallyUnknown');
});
check('B1 *_id 有 *_name 兄弟 → 隐藏 id 列，名称列照常渲染', () => {
  assert.equal(cols.some((c) => c.key === 'customer_id'), false);
  assert.equal(titleOf(cols, 'customer_name'), '客户'); // 表头由 customer_id 条目派生
  assert.equal(cellOf(cols, 'customer_name', rows[0]), '张三');
});
check('B2 嵌套关系对象 → 渲染 name，且对象键本身不成列', () => {
  assert.equal(titleOf(cols, 'category_id'), '分类');
  assert.equal(cellOf(cols, 'category_id', rows[0]), '电子产品');
  assert.equal(cols.some((c) => c.key === 'category'), false);
});
check('B4 无名称可用 → 保留该列并渲染原值', () => {
  assert.equal(cellOf(cols, 'supplier_id', rows[0]), '22');
});
check('B1b 名称兄弟被列数上限截掉 → 保留外键列并渲染名称', () => {
  const wide: Row = {
    // code + k1..k6 占满 8 列额度，customer_id 刚好第 8 列，customer_name 被截掉
    id: 1, code: 'X', k1: 1, k2: 2, k3: 3, k4: 4, k5: 5, k6: 6,
    customer_id: 11, customer_name: '张三',
  };
  const wideCols = inferColumns([wide], '/admin/v1/sales/order');
  assert.equal(wideCols.some((c) => c.key === 'customer_name'), false); // 超出 limit，未成列
  assert.equal(cellOf(wideCols, 'customer_id', wide), '张三');
});

// ── 场景 2：远程选项（B3），预取前回落原值、预取后换成名称 ──────────────
const rows2: Row[] = [{ id: 2, warehouse_id: 'h2' }];
const cols2 = inferColumns(rows2, '/admin/v1/purchase/order', [
  { key: 'warehouse_id', label: '收货仓库', source: { endpoint: '/admin/v1/warehouse' } },
]);
check('B3a source 未加载 → 回落原值', () => {
  assert.equal(cellOf(cols2, 'warehouse_id', rows2[0]), 'h2');
});
await prefetch('/admin/v1/warehouse');
check('B3b prefetch 后同一列对象直接渲染名称（共享缓存 + 同步读）', () => {
  assert.equal(cellOf(cols2, 'warehouse_id', rows2[0]), '华南仓');
});

console.log(`PASS ${ok.length} checks`);
for (const n of ok) console.log(`  ok ${n}`);
