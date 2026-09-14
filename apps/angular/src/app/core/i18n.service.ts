/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { signal } from '@angular/core';

/**
 * 前端 i18n（对齐 React lib/i18n）：
 * - 语言：zh（默认，词典即原文）/ en / ko / ru / de / fr / es / pt / hi / ar / bn / id / ja
 * - localStorage 持久化 key `erp_locale`
 * - tr(中文) 反查词典，缺词条回退中文原文 —— 配置里的中文即 key，无需逐处改造
 * - 语言切换后组件模板经 tr()（内部读 signal）在下一次变更检测取新值，即时刷新
 * - 与后端 Accept-Language 联动：语言切换后请求头随之变化
 *
 * 词典一律**懒加载**：中文是原文（无需词典），其余 12 个语种各自一个 chunk，
 * 只有真正切到该语种才下载 —— 否则 12 份 × ~50KB 会把首屏包撑爆。
 */

export type Locale =
  'zh' | 'en' | 'ko' | 'ru' | 'de' | 'fr' | 'es' | 'pt' | 'hi' | 'ar' | 'bn' | 'id' | 'ja';

/** 非中文语种（词典文件 core/zh-<code>.ts 的字母表） */
export type DictLocale = Exclude<Locale, 'zh'>;

/** 语种清单（切换器与校验共用；label 用各语种自称，不翻译） */
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

/** 非中文语种的词典加载器（静态 import 说明符，打包器可静态分析、各成一个 chunk） */
const LOADERS: Record<Exclude<Locale, 'zh'>, () => Promise<Record<string, string>>> = {
  en: () => import('./zh-en').then((m) => m.zhEn),
  ja: () => import('./zh-ja').then((m) => m.zhJa),
  ko: () => import('./zh-ko').then((m) => m.zhKo),
  de: () => import('./zh-de').then((m) => m.zhDe),
  fr: () => import('./zh-fr').then((m) => m.zhFr),
  es: () => import('./zh-es').then((m) => m.zhEs),
  pt: () => import('./zh-pt').then((m) => m.zhPt),
  ru: () => import('./zh-ru').then((m) => m.zhRu),
  ar: () => import('./zh-ar').then((m) => m.zhAr),
  hi: () => import('./zh-hi').then((m) => m.zhHi),
  bn: () => import('./zh-bn').then((m) => m.zhBn),
  id: () => import('./zh-id').then((m) => m.zhId),
};

export type TrFn = (zh: string, vars?: Record<string, string | number>) => string;

const LOCALE_KEY = 'erp_locale';

function readLocale(): Locale {
  try {
    const v = localStorage.getItem(LOCALE_KEY);
    return (LOCALES.some((l) => l.code === v) ? v : 'zh') as Locale;
  } catch {
    return 'zh';
  }
}

const localeNow = signal<Locale>(readLocale());
/**
 * 当前语种词典。中文为原文，词典恒为空对象；其余语种在 setLocale/首次渲染前异步装填。
 * 单独一个 signal：装填完成即触发一次重渲染（模板用的是 impure 管道）。
 */
const dictNow = signal<Record<string, string>>({});

/** 装填词典（幂等；同一语种重复调用不会重复下载） */
async function loadDict(l: Locale): Promise<void> {
  if (l === 'zh') {
    dictNow.set({});
    return;
  }
  try {
    dictNow.set(await LOADERS[l]());
  } catch {
    dictNow.set({}); // 词典加载失败：缺词条回退中文原文，不影响使用
  }
}

/** 应用启动时调用一次：把持久化的语种词典装进来（默认 zh 则零开销） */
export function initLocale(): void {
  void loadDict(localeNow());
}

export function setLocale(l: Locale): void {
  localeNow.set(l);
  void loadDict(l);
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
  const l = currentLocale();
  if (l === 'zh') return 'zh-CN,zh;q=0.9,en;q=0.8';
  return `${l},${l}-*;q=0.9,en;q=0.8`;
}

/** 中文原文（可含 {param} 占位符）→ 当前语种；占位符由 vars 替换，缺词条回退原文 */
export function tr(zh: string, vars?: Record<string, string | number>): string {
  if (currentLocale() === 'zh') return applyVars(zh, vars);
  let v = dictNow()[zh] ?? zh;
  if (vars && v.includes('{')) v = applyVars(v, vars);
  return v;
}

function applyVars(s: string, vars?: Record<string, string | number>): string {
  if (!vars) return s;
  let out = s;
  for (const [k, val] of Object.entries(vars)) {
    out = out.split(`{${k}}`).join(String(val));
  }
  return out;
}
