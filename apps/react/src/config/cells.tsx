/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Badge } from '@/components/ui';
import { dateTime, money, statusText, statusTone, yesNo } from '@/lib/format';
import type { Column } from '@/components/DataTable';

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

/** 通用业务状态徽标（0 待处理 / 1|3 完成 / 2 处理中 / 4 取消） */
export const statusCol = (dict?: Record<number, string>): Column<CellRow> => ({
  key: 'status',
  title: '状态',
  render: (r) => <Badge text={statusText(r.status, dict)} tone={statusTone(r.status)} />,
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
