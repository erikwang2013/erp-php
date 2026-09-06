// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 前端 i18n 门面（最小国际化）：
// - Widget 环境：AppL10n.of(context) 取当前 Localizations；未配置 delegates
//   （如离线单元测试）时回退到中文，保证既有测试可离线运行。
// - 非 Widget 环境（ApiService 等无 BuildContext 的服务）：AppL10n.current。
// - key 命名与后端 app/common/I18n.php 的 "file.key" 风格对齐：arb key 使用
//   命名空间前缀 + camelCase（如 login.title -> loginTitle、common.confirm ->
//   commonConfirm），与后端翻译键一一对应，便于未来前后端文案统一管理。
import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../l10n/app_localizations.dart';

class AppL10n {
  /// 支持的 locale：中文（默认）+ 英文。
  static const List<Locale> supportedLocales = [
    Locale('zh', 'CN'),
    Locale('en'),
  ];

  /// 语言偏好持久化 key；取值 'system' | 'zh' | 'en'，默认跟随系统。
  static const String _prefsKey = 'locale';

  static Locale _locale = const Locale('zh', 'CN');
  static String _prefCode = 'system';

  /// 语言切换通知：setLocale 后触发 AdminApp 重建以刷新全部文案。
  static final ValueNotifier<Locale> localeNotifier = ValueNotifier<Locale>(
    _locale,
  );

  static Locale get locale => _locale;

  /// 启动时读取持久化语言偏好（'locale'），默认 'system' 跟随系统语言：
  /// 系统 zh* → 中文，其余 → 英文。main() 在 runApp 前调用。
  /// ponytail: 不做运行中 OS 语言热监听——重启即按新系统语言正确，热切留待
  /// 真正需要时接 platformDispatcher.onLocaleChanged。
  static Future<void> init() async {
    WidgetsFlutterBinding.ensureInitialized();
    String code = 'system';
    try {
      final prefs = await SharedPreferences.getInstance();
      code = prefs.getString(_prefsKey) ?? 'system';
    } catch (_) {
      // 无 shared_preferences 环境（纯测试）静默回退跟随系统。
    }
    _apply(code);
  }

  /// 运行时切换语言（en/zh）：立即生效并持久化，重启后保持。
  /// 静默容错：无插件环境（单元测试）不落盘也不抛错。
  static void setLocale(Locale locale) {
    _apply(locale.languageCode == 'zh' ? 'zh' : 'en');
    _persist();
  }

  /// 恢复跟随系统语言并持久化；下次启动（或本方法调用后）按系统语言生效。
  static void setSystemFollow() {
    _apply('system');
    _persist();
  }

  static void _apply(String code) {
    _prefCode = code;
    final Locale next;
    if (code == 'zh') {
      next = const Locale('zh', 'CN');
    } else if (code == 'en') {
      next = const Locale('en');
    } else {
      final system = WidgetsBinding.instance.platformDispatcher.locale;
      next = system.languageCode.startsWith('zh')
          ? const Locale('zh', 'CN')
          : const Locale('en');
    }
    _locale = next;
    localeNotifier.value = next;
  }

  static Future<void> _persist() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, _prefCode);
    } catch (_) {
      // 无 shared_preferences 环境（单元测试）静默跳过。
    }
  }

  /// Widget 环境翻译入口；无 Localizations delegate 时回退中文。
  static AppLocalizations of(BuildContext context) =>
      AppLocalizations.of(context) ?? lookupAppLocalizations(_locale);

  /// 非 Widget 环境（如 ApiService 错误消息）翻译入口。
  static AppLocalizations get current => lookupAppLocalizations(_locale);
}
