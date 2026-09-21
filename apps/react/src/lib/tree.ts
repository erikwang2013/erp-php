/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { Row } from '@/config/types';

/**
 * 树形数据的纯函数（列表分层缩进 + 表单树字段共用）。
 * 语义与 Angular 端 `pages/resource-page/columns.ts` 的 flattenTree、
 * `resource-form.ts` 的 buildTreeData / onTreeCheck 逐条一致，两端行为必须同步改。
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

/**
 * 树形响应 → 平铺行：children 递归展开，节点带 `__depth`（列按 `indent` 缩进），
 * 展开后的 children 从行上摘掉，避免再被当成关系字段渲染或推断。
 */
export function flattenTree(rows: Row[], depth = 0): Row[] {
  const out: Row[] = [];
  for (const row of rows) {
    const kids = Array.isArray(row['children']) ? (row['children'] as Row[]) : [];
    const flat: Row = { ...row, __depth: depth };
    delete flat['children'];
    out.push(flat);
    if (kids.length) out.push(...flattenTree(kids, depth + 1));
  }
  return out;
}

/** 列表响应：整树下发的接口（权限）拍平打 `__depth`；非树响应原样返回（不白拷一遍行） */
export const flattenIfTree = (rows: Row[]): Row[] =>
  rows.some((r) => Array.isArray(r['children'])) ? flattenTree(rows) : rows;
