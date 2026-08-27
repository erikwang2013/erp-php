// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 公海池页面动作端点测试：领取 / 释放回公海。通过 mock ApiService 单例离线运行。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/crm/pool_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<void> pump(WidgetTester tester, FakeHttpClientAdapter adapter) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    ApiService.instance.dio.httpClientAdapter = adapter;

    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: PoolPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  FakeHttpClientAdapter adapterWithList() => FakeHttpClientAdapter(routes: {
        '/admin/crm/pool': (o) async => FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {
                'list': [
                  {'id': 1, 'name': '张三', 'code': 'C001'},
                ],
                'total': 1,
              },
            }),
      });

  testWidgets('渲染公海池行数据与操作按钮', (tester) async {
    await pump(tester, adapterWithList());

    expect(find.text('张三'), findsOneWidget);
    expect(find.text('C001'), findsOneWidget);
    expect(find.byIcon(Icons.person_add), findsOneWidget);
    expect(find.byIcon(Icons.logout), findsOneWidget);
  });

  testWidgets('领取客户：提交 remark 并刷新列表', (tester) async {
    final adapter = adapterWithList();
    await pump(tester, adapter);

    await tester.tap(find.byIcon(Icons.person_add));
    await tester.pumpAndSettle();
    expect(find.text('领取客户'), findsOneWidget);

    await tester.enterText(
        find.descendant(of: find.byType(AlertDialog), matching: find.byType(TextField)),
        '我要跟进');
    await tester.tap(find.text('提交'));
    await tester.pumpAndSettle();

    final call = adapter.requests.firstWhere((r) => r.path == '/admin/crm/pool/claim/1');
    expect(call.method, 'POST');
    expect((call.data as Map)['remark'], '我要跟进');
    expect(adapter.requests.where((r) => r.path == '/admin/crm/pool').length, 2); // 初始 + 刷新
  });

  testWidgets('释放回公海：提交 remark 并刷新列表', (tester) async {
    final adapter = adapterWithList();
    await pump(tester, adapter);

    await tester.tap(find.byIcon(Icons.logout));
    await tester.pumpAndSettle();
    expect(find.text('释放回公海'), findsOneWidget);

    await tester.tap(find.text('提交'));
    await tester.pumpAndSettle();

    final call = adapter.requests.firstWhere((r) => r.path == '/admin/crm/pool/release/1');
    expect(call.method, 'POST');
    expect(adapter.requests.where((r) => r.path == '/admin/crm/pool').length, 2);
  });
}
