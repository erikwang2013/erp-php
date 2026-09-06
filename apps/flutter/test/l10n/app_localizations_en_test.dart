// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 双语守卫（C0 基建）：
// (a) app_en.arb 中「已真译条目」（en≠zh 的 54 条 login/nav 等）零 CJK——
//     678 条 zh 抄录存量由后续 T 批消化；T 批交付若夹带中文字符此守卫立即变红
//     （全文件零 CJK 的严格版待 T 批后收紧启用）。
// (b) en / zh 同 key 的 {} 占位符 token 序列一致（防翻译挪位/丢参数）。
// (c) menuLabelsEn 与 menuConfig 每个唯一 zh label 双向全覆盖，且英文值零 CJK。
import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/config/menu_config.dart';

void main() {
  final zh = _loadArb('app_zh.arb');
  final en = _loadArb('app_en.arb');
  final cjk = RegExp('[一-鿿]');

  group('en 文案守卫', () {
    test('en≠zh 的已译条目零 CJK', () {
      final leaked = <String>[];
      for (final key in en.keys) {
        final env = en[key]!;
        if (env != zh[key] && cjk.hasMatch(env)) {
          leaked.add('$key=$env');
        }
      }
      expect(leaked, isEmpty, reason: '以下 en 条目仍含中文: $leaked');
    });

    test('{} 占位符 token 序列 en/zh 逐 key 一致', () {
      List<String> tokens(String s) => RegExp(
        r'\{([^{}]*)\}',
      ).allMatches(s).map((m) => m.group(1)!).toList();
      for (final key in en.keys) {
        final env = en[key]!;
        expect(tokens(env), tokens(zh[key]!), reason: 'key=$key 占位符不一致');
      }
    });
  });

  group('menuLabelsEn 守卫', () {
    test('覆盖 menuConfig 每个唯一 zh label，且英文值零 CJK', () {
      final labels = <String>{};
      void walk(List<MenuItem> items) {
        for (final item in items) {
          labels.add(item.label);
          final children = item.children;
          if (children != null) walk(children);
        }
      }

      walk(menuConfig);
      final missing = labels.difference(menuLabelsEn.keys.toSet());
      final extra = menuLabelsEn.keys.toSet().difference(labels);
      final cjkValues = menuLabelsEn.values.where(cjk.hasMatch).toList();
      expect(missing, isEmpty, reason: 'menuLabelsEn 缺少以下 zh label: $missing');
      expect(extra, isEmpty, reason: 'menuLabelsEn 含源配置已不存在的 label: $extra');
      expect(cjkValues, isEmpty, reason: 'menuLabelsEn 英文值含中文: $cjkValues');
    });
  });
}

/// 读取 lib/l10n 下的 arb 文件并返回翻译 key -> 值（剔除 @ 元数据）。
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
