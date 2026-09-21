// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 路由可达性守卫：main.dart 的 _pageBuilders 里每个已实现页面，必须能在
// getPages 里真正注册（= 出现在 menu_config.dart 的菜单路由，或 main.dart
// 的显式 fadeUpPage 路由），否则页面写好了却在应用内点不到。
// 该缺陷真实发生过：/bi/dataset 与 /eam/spare-part 曾只有 builder、无路由。
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  group('已实现页面路由可达性', () {
    test('_pageBuilders 每个 key 都在 getPages 注册', () {
      final mainDart = _read('lib/main.dart');
      final menuDart = _read('lib/app/config/menu_config.dart');

      // _pageBuilders: '<route>': () => ... 起于 `_pageBuilders` 声明，止于 `};`
      final start = mainDart.indexOf('_pageBuilders');
      final end = mainDart.indexOf('};', start);
      final builders = RegExp(r"^\s*'([^']+)':", multiLine: true)
          .allMatches(mainDart.substring(start, end))
          .map((m) => m.group(1)!)
          .toSet();

      final registered = <String>{
        ...RegExp(r"route:\s*'([^']+)'")
            .allMatches(menuDart)
            .map((m) => m.group(1)!),
        ...RegExp(r"fadeUpPage\('([^']+)'")
            .allMatches(mainDart)
            .map((m) => m.group(1)!),
      };

      expect(builders, isNotEmpty, reason: '_pageBuilders 解析结果为空，正则已失效');
      expect(
        builders.difference(registered),
        isEmpty,
        reason: '以下页面已实现但未注册路由，应用内不可达（补进 menu_config.dart 菜单或 main.dart getPages）',
      );
    });
  });
}

String _read(String relative) {
  final file = File('${Directory.current.path}/$relative');
  if (!file.existsSync()) {
    throw StateError('$relative 不存在: ${file.path}');
  }
  return file.readAsStringSync();
}
