// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 操作日志页 Widget 测试：mock /admin/log，验证日志表格与分页信息渲染。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/log/log_page.dart';
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
'/admin/v1/log': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'user_name': 'admin', 'method': 'GET', 'path': '/admin/user', 'ip': '127.0.0.1', 'created_at': '2026-08-26 09:00:00'},
            {'id': 2, 'user_name': '', 'method': 'POST', 'path': '/admin/config', 'ip': '10.0.0.8', 'created_at': '2026-08-26 08:30:00'},
          ],
          'total': 2,
        },
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpLog(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: LogPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('LogPage — 渲染', () {
    testWidgets('渲染日志表格列头与行数据', (tester) async {
      await pumpLog(tester);

      expect(find.text('操作者'), findsOneWidget);
      expect(find.text('方法'), findsOneWidget);
      expect(find.text('路径'), findsOneWidget);
      expect(find.text('admin'), findsOneWidget);
      expect(find.text('GET'), findsOneWidget);
      expect(find.text('/admin/user'), findsOneWidget);
      expect(find.text('127.0.0.1'), findsOneWidget);
    });

    testWidgets('渲染分页信息', (tester) async {
      await pumpLog(tester);

      expect(find.text('1 / 1 (2条)'), findsOneWidget);
    });
  });
}
