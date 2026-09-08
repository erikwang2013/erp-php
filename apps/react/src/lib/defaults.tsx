/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import type { Column } from '@/components/DataTable';
import { Badge } from '@/components/ui';
import type { Row } from '@/config/types';
import { dateTime, money, statusText, statusTone, text } from '@/lib/format';
import { tr } from '@/lib/i18n';

/**
 * 列配置推断。
 *
 * ResourceConfig 可以不写 columns —— 引擎从首批行数据推断列，
 * 按字段名识别金额/日期/状态/数量，让任意后端资源两行配置即可上线。
 * 需要精调的资源仍在配置里显式给出 columns 覆盖。
 */

/** 字段名 → 中文列标题 */
const TITLES: Record<string, string> = {
  code: '编号',
  no: '编号',
  name: '名称',
  title: '标题',
  type: '类型',
  status: '状态',
  quantity: '数量',
  amount: '金额',
  total_amount: '金额',
  total: '合计',
  price: '单价',
  cost: '成本',
  subtotal: '小计',
  remark: '备注',
  description: '说明',
  note: '备注',
  phone: '手机',
  email: '邮箱',
  address: '地址',
  username: '用户名',
  real_name: '姓名',
  created_at: '创建时间',
  updated_at: '更新时间',
  due_date: '到期日',
  start_date: '开始日期',
  end_date: '结束日期',
  currency: '币种',
  discount: '折扣',
  tax: '税额',
  level: '等级',
  channel: '渠道',
  owner: '负责人',
};

/** 完全不展示的内部字段 */
const HIDDEN = new Set([
  'id',
  'created_at',
  'updated_at',
  'deleted_at',
  'password',
  'remember_token',
  'tenant_id',
  'org_id',
  'version',
]);

const isMoney = (k: string) =>
  /(amount|price|cost|subtotal|balance|total|fee|salary|wage|amount_tax|tax|rate$)/.test(k) &&
  !/(_at|_no|_id)$/.test(k);

const isDate = (k: string) =>
  k.endsWith('_at') || k.endsWith('_date') || k === 'time' || k === 'date';

const isStatus = (k: string) => k === 'status' || k === 'state' || k.endsWith('_status');

const isInt = (k: string) =>
  /(quantity|qty|count|num|days|hours|age|stock|weight|width|height|length)/.test(k) &&
  !isMoney(k);

/** 常见状态字典（按资源前缀细化，未命中走通用档） */
const STATUS_DICTS: Record<string, Record<number, string>> = {
  purchase: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  sales: { 0: '草稿', 1: '待审核', 2: '已审核', 3: '已完成', 4: '已取消' },
  crm: { 0: '未开始', 1: '跟进中', 2: '已报价', 3: '赢单', 4: '输单' },
};

export function keyTitle(k: string): string {
  if (TITLES[k]) return tr(TITLES[k]);
  return k.replace(/_([a-z])/g, (_, c: string) => c.toUpperCase());
}

/** 从行样本推断列定义 */
export function inferColumns(
  rows: Row[],
  endpoint: string,
  limit = 8,
): Column<Row>[] {
  const keys: string[] = [];
  for (const r of rows.slice(0, 3)) {
    for (const k of Object.keys(r)) {
      if (HIDDEN.has(k)) continue;
      if (/^(created_at|updated_at|deleted_at)$/.test(k)) continue;
      if (k === 'id') continue;
      const v = r[k];
      // 跳过嵌套对象/数组（关系字段），避免渲染成 [object Object]
      if (v !== null && typeof v === 'object') continue;
      if (!keys.includes(k)) keys.push(k);
      if (keys.length >= limit) break;
    }
    if (keys.length >= limit) break;
  }

  // 编号类字段提到最前并加粗
  keys.sort((a, b) => {
    const pa = a === 'code' ? 0 : a === 'no' ? 0 : a === 'name' ? 1 : 2;
    const pb = b === 'code' ? 0 : b === 'no' ? 0 : b === 'name' ? 1 : 2;
    return pa - pb;
  });

  const prefix = endpoint.split('/')[3] ?? '';
  const dict = STATUS_DICTS[prefix];

  return keys.map((k) => {
    const primary = k === 'code' || k === 'no' || k === 'name';
    let render: ((row: Row) => ReactNode) | undefined;
    let align: 'right' | undefined;

    if (isStatus(k)) {
      render = (row) => (
        <Badge text={statusText(row[k], dict)} tone={statusTone(row[k])} solid />
      );
    } else if (isMoney(k)) {
      align = 'right';
      render = (row) => money(row[k]);
    } else if (isDate(k)) {
      render = (row) => <span className="muted">{dateTime(row[k])}</span>;
    } else if (isInt(k)) {
      align = 'right';
    } else {
      render = (row) => text(row[k]);
    }

    return {
      key: k,
      title: keyTitle(k),
      primary,
      align,
      render,
    };
  });
}

/** 推断详情字段（全字段，跳过 id 与嵌套关系） */
export function inferDetailItems(row: Row): { k: string; v: ReactNode }[] {
  return Object.entries(row)
    .filter(([k, v]) => {
      if (k === 'id') return false;
      if (v !== null && typeof v === 'object') return false;
      return true;
    })
    .map(([k, v]) => ({
      k: keyTitle(k),
      v: isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v),
    }));
}
