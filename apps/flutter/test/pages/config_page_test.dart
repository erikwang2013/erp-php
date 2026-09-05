// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 系统配置页 Widget 测试：mock /admin/config，验证配置项渲染与新增配置弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/config/config_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    adapter = FakeHttpClientAdapter(routes: {
      '/admin/v1/config': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'group': 'site', 'key': 'name', 'value': 'ERP 管理系统', 'type': 'string', 'description': '站点名称'},
            {'id': 2, 'group': 'site', 'key': 'page_size', 'value': '20', 'type': 'int', 'description': '分页大小'},
          ],
          'total': 2,
        },
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpConfig(WidgetTester tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: ConfigPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('ConfigPage — 渲染', () {
    testWidgets('渲染标题与配置项', (tester) async {
      await pumpConfig(tester);

      expect(find.text('系统配置'), findsOneWidget);
      expect(find.text('site.name'), findsOneWidget);
      expect(find.text('站点名称'), findsOneWidget);
      expect(find.text('ERP 管理系统'), findsOneWidget);
      expect(find.text('site.page_size'), findsOneWidget);
    });

    testWidgets('点击新增配置弹出表单', (tester) async {
      await pumpConfig(tester);

      await tester.tap(find.text('新增配置'));
      await tester.pump();

      expect(find.text('新增配置'), findsNWidgets(2)); // 标题 + 弹窗标题
      expect(find.text('分组'), findsOneWidget);
      expect(find.text('键'), findsOneWidget);
      expect(find.text('值'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
    });
  });
}
