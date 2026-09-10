/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ColumnDef } from './types';

/** 各域配置共用的列定义片段（kind 渲染语义由资源页引擎统一执行） */

export const moneyCol = (key: string, title: string): ColumnDef => ({
  key,
  title,
  align: 'right',
  kind: 'money',
});

export const intCol = (key: string, title: string): ColumnDef => ({
  key,
  title,
  align: 'right',
  kind: 'int',
});

export const dateCol = (key: string, title: string): ColumnDef => ({
  key,
  title,
  kind: 'datetime',
});

export const textCol = (key: string, title: string, primary?: boolean): ColumnDef => ({
  key,
  title,
  primary,
  kind: 'text',
});

/** 通用业务状态徽标（0 待处理 / 1|3 完成 / 2 处理中 / 4 取消） */
export const statusCol = (dict?: Record<number, string>): ColumnDef => ({
  key: 'status',
  title: '状态',
  kind: 'status',
  dict,
});

/** 启用/禁用徽标 */
export const enabledCol = (): ColumnDef => ({
  key: 'status',
  title: '状态',
  kind: 'enabled',
});

/** 「全部/启用/禁用」筛选 */
export const ST_FILTER = {
  key: 'status',
  label: '状态',
  options: [
    { label: '全部', value: null },
    { label: '启用', value: 1 },
    { label: '禁用', value: 0 },
  ],
};

/** 单据状态筛选（草稿→取消） */
export const DOC_FILTER = {
  key: 'status',
  label: '状态',
  options: [
    { label: '全部', value: null },
    { label: '草稿', value: 0 },
    { label: '待审核', value: 1 },
    { label: '已审核', value: 2 },
    { label: '已完成', value: 3 },
    { label: '已取消', value: 4 },
  ],
};

/** 单据状态字典 */
export const DOC_DICT: Record<number, string> = {
  0: '草稿',
  1: '待审核',
  2: '已审核',
  3: '已完成',
  4: '已取消',
};

/** 启用/禁用下拉选项 */
export const ON_OFF = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];
