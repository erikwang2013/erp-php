/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { Row } from '@/config/types';

/**
 * 树形数据的纯函数（列表分层缩进/折叠 + 表单树字段共用）。
 * 语义与 Angular 端 `pages/resource-page/columns.ts` 的 flattenTree / visibleRows /
 * toggleCollapsed、`resource-form.ts` 的 buildTreeData / onTreeCheck 逐条一致，
 * 两端行为必须同步改 —— `scripts/check-fe-tree.mjs` 引两端真身跑同一批断言，不一致即红。
 *
 * 列表折叠：flatten 给每行附 `__path`（根到父的 key 链），页面维护 collapsed 集合，
 * `visibleRows` 滤掉「祖先被折叠」的行（= 隐藏整棵子树）。默认全展开（空集）。
 *
 * 无任何运行时依赖（只 import type），`scripts/check-fe-tree.mjs` 直接跑本文件自检。
 */

/** 树节点：label 取源的 labelKey（默认 name），key 取 valueKey（默认 id） */
export interface TreeNode {
  key: string;
  label: string;
  children: TreeNode[];
}

/** 树的两份视图：nodes 供渲染，subtree 供「勾父级 = 整棵子树」增删 */
export interface TreeData {
  nodes: TreeNode[];
  subtree: Record<string, string[]>;
}

/** 空树常量：树数据异步到货，控件首帧要有个稳定引用 */
export const EMPTY_TREE: TreeData = { nodes: [], subtree: {} };

/**
 * 嵌套行 → 树节点 + 子树成员表（key → 自身与全部后代的 key）。
 * 后序写入子树表：算到自己时子节点的子树已在表里。
 */
export function buildTree(rows: Row[], labelKey = 'name', valueKey = 'id'): TreeData {
  const subtree: Record<string, string[]> = {};
  const walk = (list: Row[]): TreeNode[] =>
    list.map((r) => {
      const key = String(r[valueKey] ?? '');
      const kids = Array.isArray(r['children']) ? (r['children'] as Row[]) : [];
      const children = kids.length ? walk(kids) : [];
      subtree[key] = [key, ...children.flatMap((c) => subtree[c.key] ?? [])];
      return { key, label: String(r[labelKey] ?? ''), children };
    });
  return { nodes: walk(rows), subtree };
}

/**
 * 复选树勾选一位：被点节点**连同其整棵子树**加入/移出集合，不回溯父级。
 * 与 Angular / Flutter 同规则（父勾 = 整棵子树、输出即勾选集）；子级勾满也不会自动补上父级。
 * 不用「全量重算」是因为库里可能存着父级而子级未授权，任何一次点击都会凭空补出未授权的子级。
 */
export function toggleSubtree(data: TreeData, cur: string[], key: string): string[] {
  const keys = data.subtree[key] ?? [key];
  const on = !cur.includes(key);
  const out = new Set(cur);
  for (const k of keys) {
    if (on) out.add(k);
    else out.delete(k);
  }
  return [...out];
}

/** 行的树标识：树接口的行都带 id（hashid 字符串），取不到时按空串（两端同口径） */
export const rowKey = (row: Row): string => String(row['id'] ?? '');

/**
 * 树形响应 → 平铺行：children 递归展开，节点带 `__depth`（列按 `indent` 缩进）、
 * `__path`（根到父的 key 链，折叠过滤用）、`__kids`（有无子节点，叶子不画箭头），
 * 展开后的 children 从行上摘掉，避免再被当成关系字段渲染或推断。
 */
export function flattenTree(rows: Row[], depth = 0, path: string[] = []): Row[] {
  const out: Row[] = [];
  for (const row of rows) {
    const kids = Array.isArray(row['children']) ? (row['children'] as Row[]) : [];
    const flat: Row = { ...row, __depth: depth, __path: path, __kids: kids.length > 0 };
    delete flat['children'];
    out.push(flat);
    if (kids.length) out.push(...flattenTree(kids, depth + 1, [...path, rowKey(row)]));
  }
  return out;
}

/** 列表响应：整树下发的接口（权限）拍平打 `__depth`；非树响应原样返回（不白拷一遍行） */
export const flattenIfTree = (rows: Row[]): Row[] =>
  rows.some((r) => Array.isArray(r['children'])) ? flattenTree(rows) : rows;

/**
 * 折叠集合下的可见行：`__path` 上任一祖先被折叠 → 该行连同整棵子树一起隐藏。
 * 折叠集为空时零拷贝返回（没折过是常见路径，非树响应也走这条）。
 */
export function visibleRows(rows: Row[], collapsed: ReadonlySet<string>): Row[] {
  if (!collapsed.size) return rows;
  const path = (r: Row): string[] | undefined => r['__path'] as string[] | undefined;
  // 非树行没有 __path，任何折叠集都藏不住它
  return rows.filter((r) => !path(r)?.some((k) => collapsed.has(k)));
}

/** 切换一行折叠态，返回新集合（React state 要新引用；Angular 端同语义） */
export function toggleCollapsed(cur: ReadonlySet<string>, key: string): Set<string> {
  const out = new Set(cur);
  if (!out.delete(key)) out.add(key);
  return out;
}
