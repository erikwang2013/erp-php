/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Badge } from '@/components/ui';
import { dateTime, money, statusText, statusTone, yesNo } from '@/lib/format';
import type { BadgeTone } from '@/lib/format';
import type { Column } from '@/components/DataTable';
import type { FieldOption, FilterDef } from '@/config/types';

/** 各域配置共用的列渲染片段 */

export type CellRow = Record<string, unknown>;

export const moneyCol = (key: string, title: string): Column<CellRow> => ({
  key,
  title,
  align: 'right',
  render: (r) => money(r[key]),
});

export const intCol = (key: string, title: string): Column<CellRow> => ({
  key,
  title,
  align: 'right',
});

export const dateCol = (key: string, title: string): Column<CellRow> => ({
  key,
  title,
  render: (r) => dateTime(r[key]),
});

export const textCol = (key: string, title: string, primary?: boolean): Column<CellRow> => ({
  key,
  title,
  primary,
});

/** 通用业务状态徽标（0 待处理 / 1|3 完成 / 2 处理中 / 4 取消）；key 缺省 'status'，异名列（如 fulfillment_status）显式传 */
export const statusCol = (dict?: Record<number, string>, key = 'status'): Column<CellRow> => ({
  key,
  title: '状态',
  render: (r) => <Badge text={statusText(r[key], dict)} tone={statusTone(r[key])} />,
});

/** 启用/禁用徽标 */
export const enabledCol = (): Column<CellRow> => ({
  key: 'status',
  title: '状态',
  render: (r) => <Badge text={yesNo(r.status)} tone={Number(r.status) === 0 ? 'd' : 's'} />,
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
export const docStatus = (labels: string[]) => {
  const dict: Record<number, string> = {};
  const options: FieldOption[] = [{ label: '全部', value: null }];
  labels.forEach((label, value) => {
    dict[value] = label;
    options.push({ label, value });
  });

  return { dict, filter: { key: 'status', label: '状态', options } as FilterDef };
};

/** 字符串状态（发票 draft/audited/voided、维修工单 open/...）：筛选值与徽标文案同源 */
export const strStatus = (
  labels: Record<string, string>,
  tones: Record<string, BadgeTone> = {},
) => ({
  filter: {
    key: 'status',
    label: '状态',
    options: [
      { label: '全部', value: null },
      ...Object.entries(labels).map(([value, label]) => ({ label, value })),
    ],
  } as FilterDef,
  col: {
    key: 'status',
    title: '状态',
    render: (r: CellRow) => {
      const v = String(r.status ?? '');
      return <Badge text={labels[v] ?? v} tone={tones[v] ?? 'i'} />;
    },
  } as Column<CellRow>,
});

/** 启用/禁用下拉选项 */
export const ON_OFF = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];
