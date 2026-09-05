// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 用户管理页 Widget 测试：mock /admin/user，验证列表渲染、搜索、
// 状态筛选、全选与删除确认弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/user/user_list_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  Future<FakeHttpClientAdapter> buildAdapter() async {
    return FakeHttpClientAdapter(routes: {
      '/admin/v1/user': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'username': 'admin', 'real_name': '管理员', 'phone': '13800138000', 'email': 'admin@erp.local', 'status': 1, 'last_login_at': '2026-08-26 09:00:00'},
            {'id': 2, 'username': 'guest', 'real_name': '访客', 'phone': '', 'email': '', 'status': 0, 'last_login_at': null},
          ],
          'total': 2,
        },
      }),
    });
  }

  setUp(() async {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    adapter = await buildAdapter();
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpUserList(WidgetTester tester) async {
    // 用户表有 8 列，加宽测试视口避免横向溢出
    tester.view.physicalSize = const Size(1600, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: UserListPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('UserListPage — 渲染', () {
    testWidgets('渲染标题与新增用户按钮', (tester) async {
      await pumpUserList(tester);

      expect(find.text('用户管理'), findsOneWidget);
      expect(find.text('新增用户'), findsOneWidget);
    });

    testWidgets('渲染用户列表行数据与状态徽章', (tester) async {
      await pumpUserList(tester);

      expect(find.text('admin'), findsOneWidget);
      expect(find.text('管理员'), findsOneWidget);
      expect(find.text('13800138000'), findsOneWidget);
      // 状态徽章（筛选区的 ChoiceChip 也含「启用/禁用」字样，限定在 Chip 内查找）
      expect(find.widgetWithText(Chip, '启用'), findsOneWidget);
      expect(find.widgetWithText(Chip, '禁用'), findsOneWidget);
      expect(find.text('第 1 页 / 共 1 页 (2 条)'), findsOneWidget);
    });
  });

  group('UserListPage — 搜索', () {
    testWidgets('提交搜索词后以 keyword 参数重新请求', (tester) async {
      await pumpUserList(tester);

      await tester.enterText(find.byType(TextField), 'admin');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final req = adapter.requests.last;
      expect(req.path, '/admin/v1/user');
      expect(req.queryParameters['keyword'], 'admin');
    });

    testWidgets('点击状态筛选「禁用」后携带 status 参数', (tester) async {
      await pumpUserList(tester);

      await tester.tap(find.widgetWithText(ChoiceChip, '禁用'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final req = adapter.requests.last;
      expect(req.queryParameters['status'], 0);
    });
  });

  group('UserListPage — 删除交互', () {
    testWidgets('选中用户后出现批量删除按钮，点击弹出确认框', (tester) async {
      await pumpUserList(tester);

      // Material DataTable 对带 onSelectChanged 的行整行可点，点击行文本即可勾选
      await tester.tap(find.text('admin'));
      await tester.pump();

      expect(find.text('删除(1)'), findsOneWidget);

      await tester.tap(find.text('删除(1)'));
      await tester.pump();

      expect(find.text('确认批量删除'), findsOneWidget);
      expect(find.text('确定要删除选中的 1 个用户吗？'), findsOneWidget);

      // 取消不发起请求
      await tester.tap(find.text('取消'));
      await tester.pump();
      expect(adapter.requests.where((r) => r.path.contains('destroy')), isEmpty);
    });

    testWidgets('行内删除按钮弹出单条删除确认框', (tester) async {
      await pumpUserList(tester);

      await tester.tap(find.byIcon(Icons.delete).first);
      await tester.pump();

      expect(find.text('确认删除'), findsOneWidget);
      expect(find.text('确定要删除用户「admin」吗？'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
    });
  });
}
