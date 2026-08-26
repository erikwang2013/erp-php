// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 个人中心页 Widget 测试：渲染表单、保存资料请求、修改密码弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/profile/profile_page.dart';
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
      '/admin/profile': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/profile/password': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

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

      final req = adapter.requests.where((r) => r.path == '/admin/profile').toList();
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
  });
}
