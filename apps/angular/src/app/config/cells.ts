/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ColumnDef, FieldOption, FilterDef } from './types';

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
  // 外键列一律按关联列渲染（cellOf 的 rel 分支）：行内有名称/关系对象或 rel 映射才出值，
  // 全落空落「-」占位。显式写 textCol('xxx_id') 的列此前会把裸 hashid 贴到列表上
  kind: key.endsWith('_id') ? 'rel' : 'text',
});

/** 通用业务状态徽标（0 待处理 / 1|3 完成 / 2 处理中 / 4 取消）；key 缺省 'status'，异名列（如 fulfillment_status）显式传 */
export const statusCol = (dict?: Record<number, string>, key = 'status'): ColumnDef => ({
  key,
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

/**
 * 单据状态字典 + 筛选：labels 下标即状态值。
 * 各表枚举互不相同（见 database/install.sql 的 `status` 列注释），一个资源一份，禁止跨表复用。
 */
export const docStatus = (labels: string[]): { dict: Record<number, string>; filter: FilterDef } => {
  const dict: Record<number, string> = {};
  const options: FieldOption[] = [{ label: '全部', value: null }];
  labels.forEach((label, value) => {
    dict[value] = label;
    options.push({ label, value });
  });

  return { dict, filter: { key: 'status', label: '状态', options } };
};

/**
 * 字符串状态（发票 draft/audited/voided、维修工单 open/…）：筛选值与文案同源。
 * 引擎的状态渲染按 Number() 取 dict，字符串命中不了，故文案走 rel 映射（同义、无色带）。
 */
export const strStatus = (labels: Record<string, string>): { col: ColumnDef; filter: FilterDef } => ({
  col: { key: 'status', title: '状态', kind: 'rel', rel: labels },
  filter: {
    key: 'status',
    label: '状态',
    options: [
      { label: '全部', value: null },
      ...Object.entries(labels).map(([value, label]) => ({ label, value })),
    ],
  },
});

/** 启用/禁用下拉选项 */
export const ON_OFF = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];
