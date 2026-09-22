/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { tr } from '@/lib/i18n';

/** 通用展示格式化 */

/** 金额：千分位 + 2 位小数；空值返回空串 */
export function money(v: unknown, digits = 2): string {
  if (v === null || v === undefined || v === '') return '';
  const n = Number(v);
  if (!Number.isFinite(n)) return String(v);
  return n.toLocaleString('en-US', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}

/** 整数千分位 */
export function int(v: unknown): string {
  if (v === null || v === undefined || v === '') return '';
  const n = Number(v);
  return Number.isFinite(n) ? Math.round(n).toLocaleString('en-US') : String(v);
}

/**
 * 日期时间显示：秒级 `Y-m-d H:i:s`。
 *
 * 后端 datetime 列经 Eloquent `serializeDate()` 下发 **ISO-8601 UTC**
 * （`2026-09-21T14:18:43.000000Z`，见 tests 实测），带时区标记的值必须换算到
 * 本机时区再显示 —— 直接截前 19 字符会把 UTC 墙钟当本地时间（东八区差 8 小时）。
 * 裸串 `Y-m-d H:i:s` / `Y-m-d`（DATE 列、未 cast 的字段）无时区语义，按原样显示。
 */
export function dateTime(v: unknown): string {
  const s = v === null || v === undefined ? '' : String(v);
  if (!s) return '';
  if (!/(Z|[+-]\d{2}:?\d{2})$/.test(s)) return s.slice(0, 19).replace('T', ' ');
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s.slice(0, 19).replace('T', ' ');
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} `
    + `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

export function date(v: unknown): string {
  const s = dateTime(v);
  return s ? s.slice(0, 10) : '';
}

/** 文本兜底：null/undefined/'' → 短横 */
export function text(v: unknown): string {
  const s = v === null || v === undefined ? '' : String(v);
  return s === '' ? '-' : s;
}

/** 异常 → 可展示文案（Error.message，否则兜底「操作失败」） */
export function errMsg(e: unknown): string {
  return e instanceof Error ? e.message : '操作失败';
}

/** 截断长文本 */
export function clip(s: unknown, n = 24): string {
  const t = text(s);
  return t.length > n ? `${t.slice(0, n)}…` : t;
}

/**
 * 状态徽标语义映射（与 Flutter StatusBadge 同规则）
 * 0=待办/待审(warning) 1|3=终态(success) 2=进行中(primary) 4=失败/取消(danger)
 */
export type BadgeTone = 's' | 'w' | 'd' | 'i';

export function statusTone(status: unknown): BadgeTone {
  const n = Number(status);
  if (n === 1 || n === 3) return 's';
  if (n === 4) return 'd';
  if (n === 0) return 'w';
  return 'i';
}

/**
 * 状态文案：**字典命中 → 词典文案；未命中 → 原值直出**（与 Angular `core/format.ts` 的 statusText、
 * 以及 `cells.tsx` 的 `strStatus`（`labels[v] ?? v`）同口径）。
 *
 * 不再有「猜」的兜底：曾退 `COMMON_STATUS` 通用档、最后造 `状态N` —— 两条都编造中文
 * （`erp_hr_employee.status=0` 被猜成「待处理」，而页面字典只定义了 1/2/3）。真实数据优先。
 */
export function statusText(status: unknown, dict?: Record<number | string, string>): string {
  const label = dict?.[String(status ?? '')];
  return label ? tr(label) : text(status);
}

/** 布尔 → 启用/禁用 */
export function yesNo(status: unknown): string {
  return tr(Number(status) === 0 ? '禁用' : '启用');
}

/** hashid 兜底：缺失时显示「-」而不是 0 */
export function hid(v: unknown): string {
  return v === null || v === undefined || v === '' ? '-' : String(v);
}

/** 字节 → 可读大小 */
export function bytes(v: unknown): string {
  const n = Number(v) || 0;
  if (n < 1024) return `${n} B`;
  if (n < 1024 ** 2) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 ** 2).toFixed(1)} MB`;
}
