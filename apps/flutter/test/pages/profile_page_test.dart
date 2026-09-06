// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 个人中心页 Widget 测试：渲染表单、保存资料请求、修改密码弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/profile/profile_page.dart';
import 'package:admin_app/app/pages/system/role/role_controller.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

/// 权限树样例（缓存对内容透明，仅需非空）。
List<dynamic> permTree() => [
      {
        'id': 't1',
        'name': '销售',
        'children': [
          {
            'id': 'm1',
            'name': '订单',
            'children': [
              {'id': 'l1', 'name': '查看'},
            ],
          },
        ],
      },
    ];

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    // 模块级缓存跨测试残留，每测从冷缓存起步保证确定性
    RoleController.clearPermissionCache();
    adapter = FakeHttpClientAdapter(routes: {
      '/admin/v1/profile': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/v1/profile/password': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/v1/profile/logout': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/v1/permission': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': permTree()}),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  int permGets() => adapter.requests
      .where((r) => r.method == 'GET' && r.path == '/admin/v1/permission')
      .length;

  Future<void> pumpProfile(WidgetTester tester) async {
    // GetMaterialApp 注册 Get.key 导航，使保存成功后的 Get.snackbar 可正常弹出
    await tester.pumpWidget(const GetMaterialApp(home: Scaffold(body: ProfilePage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  /// 关闭 Get.snackbar 并等动画与自动关闭计时器走完，避免测试结束时残留 Timer/动画。
  Future<void> settleSnackbars(WidgetTester tester) async {
    Get.closeAllSnackbars();
    await tester.pump();
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
  }

  group('ProfilePage — 渲染', () {
    testWidgets('渲染个人中心表单', (tester) async {
      await pumpProfile(tester);

      expect(find.text('个人中心'), findsOneWidget);
      expect(find.text('姓名'), findsOneWidget);
      expect(find.text('手机号'), findsOneWidget);
      expect(find.text('邮箱'), findsOneWidget);
      expect(find.text('保存'), findsOneWidget);
      expect(find.text('修改密码'), findsOneWidget);
      expect(find.text('退出登录'), findsOneWidget);
    });
  });

  group('ProfilePage — 交互', () {
    testWidgets('填写资料点击保存发起 PUT 请求', (tester) async {
      await pumpProfile(tester);

      await tester.enterText(find.byType(TextField).at(0), '张三');
      await tester.enterText(find.byType(TextField).at(1), '13900000000');
      await tester.tap(find.text('保存'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final req = adapter.requests.where((r) => r.path == '/admin/v1/profile').toList();
      expect(req, hasLength(1));
      final body = req.first.data as Map<String, dynamic>;
      expect(body['real_name'], '张三');
      expect(body['phone'], '13900000000');

      // 保存成功后弹出「个人信息更新成功」snackbar，等其动画与计时器结束
      expect(find.text('个人信息更新成功'), findsOneWidget);
      await settleSnackbars(tester);
    });

    testWidgets('点击修改密码弹出密码表单', (tester) async {
      await pumpProfile(tester);

      await tester.tap(find.text('修改密码'));
      await tester.pump();

      expect(find.text('修改密码'), findsNWidgets(2)); // 列表项 + 弹窗标题
      expect(find.text('旧密码'), findsOneWidget);
      expect(find.text('新密码 (6-32位)'), findsOneWidget);
      expect(find.text('确认新密码'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
      await settleSnackbars(tester);
    });

    testWidgets('登出清空权限树会话缓存（批3：换号登录不得复用旧权限树）', (tester) async {
      // 先灌入会话缓存：拉取一次权限树（裸调 + pump 惯例，dio 请求由 pump 推进）
      final ctrl = RoleController();
      ctrl.loadPermissions();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(permGets(), 1, reason: '冷启动应发一次请求');
      expect(ctrl.permissions, hasLength(1));

      await tester.pumpWidget(GetMaterialApp(
        home: const Scaffold(body: ProfilePage()),
        // 登出 offAllNamed('/login')，需预注册目标路由
        getPages: [GetPage(name: '/login', page: () => const Scaffold(body: SizedBox()))],
      ));
      await tester.pump();

      await tester.tap(find.text('退出登录'));
      await tester.pump();
      expect(find.text('确定退出'), findsOneWidget);
      await tester.tap(find.text('确定退出'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(
        adapter.requests.where((r) => r.path == '/admin/v1/profile/logout'),
        hasLength(1),
      );

      // 缓存已随登出清空：同会话新控制器再拉权限树应重新发请求
      final ctrl2 = RoleController();
      ctrl2.loadPermissions();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(permGets(), 2, reason: '登出清缓存后二次拉取应重新请求');
      await settleSnackbars(tester);
    });
  });
}
