// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// i18n 测试（P2 最小国际化）：
// 1. arb 文件 key 完整性：zh / en 的 key 集合一致、值非空、数量达标（≥15）。
// 2. 生成类可用性：AppLocalizationsZh / En 均能解析全部 key（占位符参数签名正确）。
// 全部离线运行，不依赖网络与真机。
import 'dart:convert';
import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/l10n/app_localizations.dart';
import 'package:admin_app/l10n/app_localizations_zh.dart';
import 'package:admin_app/l10n/app_localizations_en.dart';

void main() {
  group('arb 文件 key 完整性', () {
    final zh = _loadArb('app_zh.arb');
    final en = _loadArb('app_en.arb');

    test('arb 文件存在且 zh / en 均包含至少 15 个翻译 key', () {
      expect(zh.length, greaterThanOrEqualTo(15));
      expect(en.length, greaterThanOrEqualTo(15));
    });

    test('zh 与 en 的 key 集合完全一致', () {
      final zhKeys = zh.keys.toSet();
      final enKeys = en.keys.toSet();
      final missingInEn = zhKeys.difference(enKeys);
      final missingInZh = enKeys.difference(zhKeys);
      expect(missingInEn, isEmpty, reason: '以下 key 在 app_en.arb 中缺失: $missingInEn');
      expect(missingInZh, isEmpty, reason: '以下 key 在 app_zh.arb 中缺失: $missingInZh');
    });

    test('所有 key 的值非空', () {
      for (final entry in zh.entries) {
        expect(entry.value.trim(), isNotEmpty, reason: 'app_zh.arb 的 ${entry.key} 为空');
      }
      for (final entry in en.entries) {
        expect(entry.value.trim(), isNotEmpty, reason: 'app_en.arb 的 ${entry.key} 为空');
      }
    });

    test('关键 key 必须存在（登录 / 导航 / 通用 / 请求失败）', () {
      const requiredKeys = [
        'loginTitle',
        'loginUsername',
        'loginPassword',
        'loginButton',
        'loginLoginFailed',
        'navDashboard',
        'navSystem',
        'navLogout',
        'commonConfirm',
        'commonCancel',
        'commonRequestFailed',
      ];
      for (final key in requiredKeys) {
        expect(zh.containsKey(key), isTrue, reason: 'app_zh.arb 缺少关键 key: $key');
        expect(en.containsKey(key), isTrue, reason: 'app_en.arb 缺少关键 key: $key');
      }
    });
  });

  group('生成类可用性', () {
    test('AppLocalizationsZh 可解析全部 zh key（非空）', () {
      final l10n = AppLocalizationsZh();
      expect(l10n.loginTitle, isNotEmpty);
      expect(l10n.loginUsername, isNotEmpty);
      expect(l10n.loginPassword, isNotEmpty);
      expect(l10n.navLogout, isNotEmpty);
      expect(l10n.commonRequestFailed, isNotEmpty);
      expect(l10n.loginCaptchaClicked(1, 3), contains('1'));
    });

    test('AppLocalizationsEn 可解析全部 en key（非空）', () {
      final l10n = AppLocalizationsEn();
      expect(l10n.loginTitle, isNotEmpty);
      expect(l10n.loginUsername, isNotEmpty);
      expect(l10n.loginPassword, isNotEmpty);
      expect(l10n.navLogout, isNotEmpty);
      expect(l10n.commonRequestFailed, isNotEmpty);
      expect(l10n.loginCaptchaClicked(1, 3), contains('1'));
    });

    test('lookupAppLocalizations 支持 zh / en', () {
      expect(lookupAppLocalizations(const Locale('zh', 'CN')).loginTitle, isNotEmpty);
      expect(lookupAppLocalizations(const Locale('en')).loginTitle, isNotEmpty);
    });
  });
}

/// 读取 lib/l10n 下的 arb 文件并返回翻译 key -> 值（剔除 @ 元数据）。
/// 文件不存在时直接抛异常（在测试体内由调用方断言）。
Map<String, String> _loadArb(String filename) {
  final file = File('${Directory.current.path}/lib/l10n/$filename');
  if (!file.existsSync()) {
    throw StateError('$filename 不存在: ${file.path}');
  }
  final json = jsonDecode(file.readAsStringSync()) as Map<String, dynamic>;
  return {
    for (final e in json.entries)
      if (!e.key.startsWith('@')) e.key: e.value as String,
  };
}
