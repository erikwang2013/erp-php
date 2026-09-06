/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import preferences from '@ohos.data.preferences';
import { common } from '@kit.AbilityKit';
import Want from '@ohos.app.ability.Want';
import { i18n } from '@kit.LocalizationKit';

/**
 * 应用语言管理 — 'system'（跟随系统）| 'zh-CN' | 'en-US'，双写策略同 TokenManager：
 * AppStorage 内存态（运行期零延迟读取）+ Preferences 磁盘持久化。
 *
 * - init：冷启动由 EntryAbility 在 loadContent 前调用（幂等，内部兜底不阻断启动）。
 *   恢复语言 → 写 AppStorage → 非 system 时调 i18n.System.setAppPreferredLanguage，
 *   使 base/en_US 资源覆盖按首选语言生效。
 * - setLanguage：持久化 + 内存 + 首选语言下发 + restartApp 重启应用，
 *   令全量界面文案按新语言重建（后续语言设置页调用）。
 *
 * 渲染取文约定：静态文案用 $r（ArkUI 随首选语言自动解析）；含 %s/%d 的插值串
 * 用 utils/L10n.str 按名解析。
 */
export class LanguageManager {
  // Preferences 存储文件名与 key（与 TokenManager 分库互不干扰）
  private static readonly STORE_NAME = 'open_admin_settings';
  private static readonly LANGUAGE_KEY = 'appLanguage';
  // AppStorage key 与上述一致（语言设置页经 AppStorage 读取当前值）
  static readonly APP_LANGUAGE_KEY = 'appLanguage';

  private static prefs: preferences.Preferences | null = null;
  // init 时暂存 Ability 上下文，供 setLanguage 重启应用（utils 类无组件 getHostContext）
  private static abilityContext: common.UIAbilityContext | null = null;

  /**
   * 初始化：由 EntryAbility 冷启动时调用，幂等可重复执行。
   * 内部全兜底：preferences 异常时语言保持 system，不阻断启动流程。
   *
   * @param context Ability 上下文（EntryAbility 的 this.context）
   */
  static async init(context: common.UIAbilityContext): Promise<void> {
    try {
      LanguageManager.abilityContext = context;
      const options: preferences.Options = { name: LanguageManager.STORE_NAME };
      LanguageManager.prefs = await preferences.getPreferences(context, options);
      const lang = await LanguageManager.prefs.get(LanguageManager.LANGUAGE_KEY, 'system') as string;
      AppStorage.setOrCreate(LanguageManager.APP_LANGUAGE_KEY, lang);
      if (lang !== 'system') {
        LanguageManager.applyPreferred(lang);
      }
    } catch (err) {
      // 首启/存储异常不阻断启动，语言保持 system
      console.error('LanguageManager init failed', err);
      AppStorage.setOrCreate(LanguageManager.APP_LANGUAGE_KEY, 'system');
    }
  }

  /** 当前语言（读 AppStorage 内存态；init 前兜底 'system'） */
  static getLanguage(): string {
    return AppStorage.get(LanguageManager.APP_LANGUAGE_KEY) as string ?? 'system';
  }

  /**
   * 设置应用语言（'system' | 'zh-CN' | 'en-US'）：
   * 持久化 → 内存 → 首选语言下发 → 重启应用使全量文案生效。
   */
  static async setLanguage(lang: string): Promise<void> {
    if (lang !== 'system' && lang !== 'zh-CN' && lang !== 'en-US') {
      return;
    }
    AppStorage.setOrCreate(LanguageManager.APP_LANGUAGE_KEY, lang);
    const prefs = LanguageManager.prefs;
    if (prefs) {
      try {
        await prefs.put(LanguageManager.LANGUAGE_KEY, lang);
        await prefs.flush();
      } catch (err) {
        // 落盘失败仅告警：内存态仍生效，重启后回到旧语言
        console.error('LanguageManager persist failed', err);
      }
    }
    if (lang !== 'system') {
      LanguageManager.applyPreferred(lang);
    }
    // 重启后 EntryAbility.init 按已持久化语言重建（冷启动全量覆盖生效）；
    // restartApp 属 ApplicationContext，须自 Ability 上下文经 getApplicationContext 取得
    const ctx = LanguageManager.abilityContext;
    if (ctx) {
      const want: Want = {};
      ctx.getApplicationContext().restartApp(want);
    }
  }

  /** 首选语言下发（@ohos.i18n System，API 12）；失败仅告警，文案由 $r 解析兜底 */
  private static applyPreferred(lang: string): void {
    try {
      i18n.System.setAppPreferredLanguage(lang);
    } catch (err) {
      console.error('setAppPreferredLanguage failed', lang, err);
    }
  }
}
