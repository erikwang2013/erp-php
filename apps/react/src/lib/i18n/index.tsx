/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { zhEn } from '@/lib/i18n/zhEn';

/**
 * 前端 i18n（最小国际化，对齐 Flutter AppL10n）：
 * - 语言：zh（默认）/ en，localStorage 持久化 key `erp_locale`
 * - tr(中文) 反查词典，缺词条回退中文原文 —— 配置里的中文即 key，无需逐处改造
 * - 渲染侧用 useLocale() 订阅语言变化，切换即时刷新全部文案
 * - 与后端 I18n.php 的 Accept-Language 联动：语言切换后请求头随之变化
 */

export type Locale = 'zh' | 'en';

const LOCALE_KEY = 'erp_locale';

/** 模块级当前语言：tr()/acceptLanguage() 无需订阅即可读取（组件重渲染由 useTr 触发） */
let localeNow: Locale = readLocale();

interface I18nCtx {
  locale: Locale;
  setLocale: (l: Locale) => void;
  tr: TrFn;
}

/** 中文原文（可含 {param} 占位符）→ 英文；占位符由 vars 替换，缺词条回退原文 */
export type TrFn = (zh: string, vars?: Record<string, string | number>) => string;

function translate(zh: string, vars?: Record<string, string | number>): string {
  let v = localeNow === 'en' ? (zhEn[zh] ?? zh) : zh;
  if (localeNow === 'en' && vars && v.includes('{')) {
    for (const [k, val] of Object.entries(vars)) {
      v = v.split(`{${k}}`).join(String(val));
    }
  }
  return v;
}

const Ctx = createContext<I18nCtx | null>(null);

function readLocale(): Locale {
  try {
    const v = localStorage.getItem(LOCALE_KEY);
    return v === 'en' ? 'en' : 'zh';
  } catch {
    return 'zh';
  }
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(readLocale);

  const setLocale = useCallback((l: Locale) => {
    localeNow = l;
    setLocaleState(l);
    try {
      localStorage.setItem(LOCALE_KEY, l);
    } catch {
      /* 隐私模式等无 localStorage 场景静默跳过 */
    }
  }, []);

  const tr = useCallback<TrFn>((zh, vars) => {
    let v = locale === 'en' ? (zhEn[zh] ?? zh) : zh;
    if (locale === 'en' && vars && v.includes('{')) {
      for (const [k, val] of Object.entries(vars)) {
        v = v.split(`{${k}}`).join(String(val));
      }
    }
    return v;
  }, [locale]);

  const value = useMemo<I18nCtx>(() => ({ locale, setLocale, tr }), [locale, setLocale, tr]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useI18n(): I18nCtx {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error('useI18n 必须在 I18nProvider 内使用');
  return ctx;
}

/** 供 api.ts 等非组件环境读取当前语言（无订阅需求） */
export function currentLocale(): Locale {
  return localeNow;
}

/** 语言切换后向后端声明：webman I18n 按 Accept-Language 返回对应语言文案 */
export function acceptLanguage(): string {
  return localeNow === 'en' ? 'en' : 'zh-CN,zh;q=0.9,en;q=0.8';
}

/** 非组件环境直接翻译（词典缺失回退原文） */
export const tr: TrFn = translate;

/** 组件内使用：语言切换时触发重渲染 */
export function useTr(): TrFn {
  const { tr } = useI18n();
  return tr;
}
