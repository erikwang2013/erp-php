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
import '../../l10n/app_localizations.dart';

class AppL10n {
  /// 支持的 locale：中文（默认）+ 英文。
  static const List<Locale> supportedLocales = [
    Locale('zh', 'CN'),
    Locale('en'),
  ];

  static Locale _locale = const Locale('zh', 'CN');

  /// 语言切换通知：setLocale 后触发 AdminApp 重建以刷新全部文案。
  static final ValueNotifier<Locale> localeNotifier = ValueNotifier<Locale>(_locale);

  static Locale get locale => _locale;

  /// 运行时切换语言（en/zh）。后续可接系统语言或用户偏好（SharedPreferences）。
  static void setLocale(Locale locale) {
    _locale = locale;
    localeNotifier.value = locale;
  }

  /// Widget 环境翻译入口；无 Localizations delegate 时回退中文。
  static AppLocalizations of(BuildContext context) =>
      AppLocalizations.of(context) ?? lookupAppLocalizations(_locale);

  /// 非 Widget 环境（如 ApiService 错误消息）翻译入口。
  static AppLocalizations get current => lookupAppLocalizations(_locale);
}
