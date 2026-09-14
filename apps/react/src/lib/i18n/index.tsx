/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

/**
 * 前端 i18n（对齐 Angular core/i18n.service）：
 * - 语言：zh（默认，词典即原文）/ en / ko / ru / de / fr / es / pt / hi / ar / bn / id / ja
 * - localStorage 持久化 key `erp_locale`
 * - tr(中文) 反查词典，缺词条回退中文原文 —— 配置里的中文即 key，无需逐处改造
 * - 渲染侧用 useTr()/useI18n() 订阅语言变化，切换即时刷新全部文案
 * - 与后端 I18n.php 的 Accept-Language 联动：语言切换后请求头随之变化
 *
 * 词典一律**懒加载**：中文是原文（无需词典），其余 12 个语种各自一个 chunk，
 * 只有真正切到该语种才下载 —— 否则 12 份词典会把首屏包撑爆。
 */

export type Locale =
  | 'zh' | 'en' | 'ko' | 'ru' | 'de' | 'fr' | 'es' | 'pt' | 'hi' | 'ar' | 'bn' | 'id' | 'ja';

/** 语种清单（切换器共用；label 用各语种自称，不翻译） */
export const LOCALES: ReadonlyArray<{ code: Locale; label: string }> = [
  { code: 'zh', label: '简体中文' },
  { code: 'en', label: 'English' },
  { code: 'ja', label: '日本語' },
  { code: 'ko', label: '한국어' },
  { code: 'de', label: 'Deutsch' },
  { code: 'fr', label: 'Français' },
  { code: 'es', label: 'Español' },
  { code: 'pt', label: 'Português' },
  { code: 'ru', label: 'Русский' },
  { code: 'ar', label: 'العربية' },
  { code: 'hi', label: 'हिन्दी' },
  { code: 'bn', label: 'বাংলা' },
  { code: 'id', label: 'Bahasa Indonesia' },
];

/** 非中文语种的词典加载器（静态 import 说明符，Vite 可静态分析、各成一个 chunk） */
const LOADERS: Record<Exclude<Locale, 'zh'>, () => Promise<Record<string, string>>> = {
  en: () => import('@/lib/i18n/zhEn').then((m) => m.zhEn),
  ja: () => import('@/lib/i18n/zhJa').then((m) => m.zhJa),
  ko: () => import('@/lib/i18n/zhKo').then((m) => m.zhKo),
  de: () => import('@/lib/i18n/zhDe').then((m) => m.zhDe),
  fr: () => import('@/lib/i18n/zhFr').then((m) => m.zhFr),
  es: () => import('@/lib/i18n/zhEs').then((m) => m.zhEs),
  pt: () => import('@/lib/i18n/zhPt').then((m) => m.zhPt),
  ru: () => import('@/lib/i18n/zhRu').then((m) => m.zhRu),
  ar: () => import('@/lib/i18n/zhAr').then((m) => m.zhAr),
  hi: () => import('@/lib/i18n/zhHi').then((m) => m.zhHi),
  bn: () => import('@/lib/i18n/zhBn').then((m) => m.zhBn),
  id: () => import('@/lib/i18n/zhId').then((m) => m.zhId),
};

const LOCALE_KEY = 'erp_locale';

/** 模块级当前语言与词典：tr()/acceptLanguage() 无需订阅即可读取（组件重渲染由 useTr 触发） */
let localeNow: Locale = readLocale();
let dictNow: Record<string, string> = {};

/** 中文原文（可含 {param} 占位符）→ 当前语种；占位符由 vars 替换，缺词条回退原文 */
export type TrFn = (zh: string, vars?: Record<string, string | number>) => string;

interface I18nCtx {
  locale: Locale;
  setLocale: (l: Locale) => void;
  tr: TrFn;
}

function applyVars(s: string, vars?: Record<string, string | number>): string {
  if (!vars) return s;
  let out = s;
  for (const [k, val] of Object.entries(vars)) {
    out = out.split(`{${k}}`).join(String(val));
  }
  return out;
}

function translate(zh: string, vars?: Record<string, string | number>): string {
  if (localeNow === 'zh') return applyVars(zh, vars);
  const v = dictNow[zh] ?? zh;
  return vars && v.includes('{') ? applyVars(v, vars) : v;
}

/** 装填词典（幂等；失败则保持空词典 → 缺词条回退中文） */
async function loadDict(l: Locale): Promise<void> {
  if (l === 'zh') {
    dictNow = {};
    return;
  }
  try {
    dictNow = await LOADERS[l]();
  } catch {
    dictNow = {};
  }
}

const Ctx = createContext<I18nCtx | null>(null);

function readLocale(): Locale {
  try {
    const v = localStorage.getItem(LOCALE_KEY);
    return (LOCALES.some((l) => l.code === v) ? v : 'zh') as Locale;
  } catch {
    return 'zh';
  }
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(readLocale);
  /** 词典装填完成的版本号：词典是异步来的，装好后必须触发一次重渲染，否则界面停在空词典状态 */
  const [dictVersion, setDictVersion] = useState(0);

  useEffect(() => {
    let alive = true;
    void loadDict(locale).then(() => {
      if (alive) setDictVersion((v) => v + 1);
    });
    return () => {
      alive = false;
    };
  }, [locale]);

  const setLocale = useCallback((l: Locale) => {
    localeNow = l;
    setLocaleState(l);
    try {
      localStorage.setItem(LOCALE_KEY, l);
    } catch {
      /* 隐私模式等无 localStorage 场景静默跳过 */
    }
  }, []);

  // dictVersion 进依赖：词典装填完成会重建 tr，触发下游文案刷新
  const tr = useCallback<TrFn>((zh, vars) => translate(zh, vars), [locale, dictVersion]);

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
  const l = localeNow;
  if (l === 'zh') return 'zh-CN,zh;q=0.9,en;q=0.8';
  return `${l},${l}-*;q=0.9,en;q=0.8`;
}

/** 非组件环境直接翻译（词典缺失回退原文） */
export const tr: TrFn = translate;

/** 组件内使用：语言切换时触发重渲染 */
export function useTr(): TrFn {
  return useI18n().tr;
}
