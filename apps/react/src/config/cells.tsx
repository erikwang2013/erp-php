/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Badge } from '@/components/ui';
import { dateTime, money, statusText, statusTone, yesNo } from '@/lib/format';
import type { BadgeTone } from '@/lib/format';
import { tr } from '@/lib/i18n';
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

/** 通用业务状态徽标（色带：0 待办 w / 1|3 终态 s / 2 进行中 i / 4 取消 d）；文案只由传入字典给，
 *  字典未命中落原值 —— 不再有通用档文案。key 缺省 'status'，异名列（如 fulfillment_status）显式传 */
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
 * 年份筛选选项：首项「全部」（value=null ⇒ 不下发该参数）+ 近 span 年。
 * 按**模块加载时**的本机年算（配置是模块级常量，跨年刷新页面即更新），值为年份整数。
 */
export const yearOptions = (span = 5): FieldOption[] => {
  const thisYear = new Date().getFullYear();
  return [
    { label: '全部', value: null },
    ...Array.from({ length: span }, (_, i) => ({ label: String(thisYear - i), value: thisYear - i })),
  ];
};

/** 月份筛选选项：首项「全部」+ 1-12（值域同 install.sql 该列注释「报表月份 1-12」） */
export const monthOptions = (): FieldOption[] => [
  { label: '全部', value: null },
  ...Array.from({ length: 12 }, (_, i) => ({ label: String(i + 1), value: i + 1 })),
];

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

/**
 * 字符串状态（发票 draft/audited/voided、维修工单 open/...）：筛选值与徽标文案同源。
 * 兜底与命中的口径两端一致：命中出 `tr(文案)`、**表外值原值直出**（Angular 侧走 `kind:'map'` → `mapText`，
 * 命中同样过 tr —— 这 8 个标签在 12 语种译文表里都有，只直出不出译文会让 en 等语种下两端分叉）。
 * 色带不收：本端是 Badge+tone、Angular 是纯文本，属已上报的观感差异（本次只统一兜底语义）。
 */
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
      return <Badge text={tr(labels[v] ?? v)} tone={tones[v] ?? 'i'} />;
    },
  } as Column<CellRow>,
});

/**
 * 字典列取值（对齐 Angular cellOf 的 kind:'map' 支）：机读串 → 文案。
 * 字典命中 = 词典词条，过 tr 出当前语种；表外值原样直出（真实数据优先，不落 '-'）。
 */
export const mapText = (v: unknown, dict: Record<string, string>): string => {
  if (v === null || v === undefined || v === '') return '';
  const hit = dict[String(v)];
  return hit === undefined ? String(v) : tr(hit);
};

/** 启用/禁用下拉选项 */
export const ON_OFF = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];
