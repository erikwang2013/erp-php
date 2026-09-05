// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 角色管理页 Widget 测试：mock /admin/role 与 /admin/permission，
// 验证角色卡片渲染与新增角色弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/role/role_list_page.dart';
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
      '/admin/v1/role': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'name': '超级管理员', 'slug': 'super', 'users_count': 1, 'status': 1, 'description': '全部权限'},
            {'id': 2, 'name': '只读用户', 'slug': 'readonly', 'users_count': 0, 'status': 0, 'description': ''},
          ],
          'total': 2,
        },
      }),
      '/admin/v1/permission': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': [
          {'id': 1, 'name': '用户管理', 'slug': 'user:list'},
          {'id': 2, 'name': '系统配置', 'slug': 'config:list'},
        ],
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpRoleList(WidgetTester tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: RoleListPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('RoleListPage — 渲染', () {
    testWidgets('渲染标题与角色卡片', (tester) async {
      await pumpRoleList(tester);

      expect(find.text('角色管理'), findsOneWidget);
      expect(find.text('超级管理员'), findsOneWidget);
      expect(find.text('标识: super | 用户数: 1 | 全部权限'), findsOneWidget);
      expect(find.text('启用'), findsOneWidget);
      expect(find.text('禁用'), findsOneWidget);
    });

    testWidgets('渲染新增角色按钮', (tester) async {
      await pumpRoleList(tester);

      expect(find.text('新增角色'), findsOneWidget);
    });
  });

  group('RoleListPage — 新增角色弹窗', () {
    testWidgets('点击新增角色展示权限分配列表', (tester) async {
      await pumpRoleList(tester);

      await tester.tap(find.text('新增角色'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('权限分配:'), findsOneWidget);
      expect(find.text('用户管理'), findsOneWidget);
      expect(find.text('系统配置'), findsOneWidget);
      expect(find.text('名称'), findsOneWidget);
      expect(find.text('标识'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
    });
  });
}
