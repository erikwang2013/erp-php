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

/** 日期时间：兼容 `Y-m-d H:i:s`，截到秒 */
export function dateTime(v: unknown): string {
  const s = v === null || v === undefined ? '' : String(v);
  if (!s) return '';
  return s.length > 19 ? s.slice(0, 19).replace('T', ' ') : s.replace('T', ' ');
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

/** 常见状态码 → 文案（各域字典不同，这里只兜底通用档） */
export const COMMON_STATUS: Record<number, string> = {
  0: '待处理',
  1: '已生效',
  2: '处理中',
  3: '已完成',
  4: '已取消',
};

export function statusText(status: unknown, dict?: Record<number, string>): string {
  const n = Number(status);
  if (Number.isNaN(n)) return text(status);
  return tr(dict?.[n] ?? COMMON_STATUS[n] ?? `状态${n}`);
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
