/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { signal } from '@angular/core';
import { zhEn } from './zh-en';

/**
 * 前端 i18n（最小国际化，对齐 React lib/i18n）：
 * - 语言：zh（默认）/ en，localStorage 持久化 key `erp_locale`
 * - tr(中文) 反查词典，缺词条回退中文原文 —— 配置里的中文即 key，无需逐处改造
 * - 语言切换后组件模板经 tr()（内部读 signal）在下一次变更检测取新值，即时刷新
 * - 与后端 Accept-Language 联动：语言切换后请求头随之变化
 */

export type Locale = 'zh' | 'en';

export type TrFn = (zh: string, vars?: Record<string, string | number>) => string;

const LOCALE_KEY = 'erp_locale';

function readLocale(): Locale {
  try {
    const v = localStorage.getItem(LOCALE_KEY);
    return v === 'en' ? 'en' : 'zh';
  } catch {
    return 'zh';
  }
}

const localeNow = signal<Locale>(readLocale());

export function setLocale(l: Locale): void {
  localeNow.set(l);
  try {
    localStorage.setItem(LOCALE_KEY, l);
  } catch {
    /* 隐私模式等无 localStorage 场景静默跳过 */
  }
}

/** 组件内可用作模板调用的方法字段，语言切换后重渲染取新值 */
export function currentLocale(): Locale {
  return localeNow();
}

/** 语言切换后向后端声明：webman I18n 按 Accept-Language 返回对应语言文案 */
export function acceptLanguage(): string {
  return currentLocale() === 'en' ? 'en' : 'zh-CN,zh;q=0.9,en;q=0.8';
}

/** 中文原文（可含 {param} 占位符）→ 英文；占位符由 vars 替换，缺词条回退原文 */
export function tr(zh: string, vars?: Record<string, string | number>): string {
  let v = currentLocale() === 'en' ? (zhEn[zh] ?? zh) : zh;
  if (currentLocale() === 'en' && vars && v.includes('{')) {
    for (const [k, val] of Object.entries(vars)) {
      v = v.split(`{${k}}`).join(String(val));
    }
  }
  return v;
}
