// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 登录页表单校验 Widget 测试：必填校验、验证码缺失提示、加载失败提示。
// flutter_test 环境默认拦截真实网络（返回 400），验证码请求必然失败，
// 因此本测试天然离线，无需 mock 网络。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/pages/login/login_page.dart';

void main() {
  Widget wrap() => const MaterialApp(home: LoginPage());

  /// 让 initState 中的验证码加载异步完成（失败路径）。
  Future<void> pumpLogin(WidgetTester tester) async {
    await tester.pumpWidget(wrap());
    // 等待 _loadCaptcha 的网络失败回调完成
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('LoginPage — 渲染', () {
    testWidgets('渲染标题、用户名/密码输入框与登录按钮', (tester) async {
      await pumpLogin(tester);

      expect(find.text('开放管理后台'), findsOneWidget);
      expect(find.text('用户名'), findsOneWidget);
      expect(find.text('密码'), findsOneWidget);
      expect(find.byType(FilledButton), findsOneWidget);
      // 密码框应为密文模式
      final pwdField = tester.widget<TextField>(find.byType(TextField).at(1));
      expect(pwdField.obscureText, isTrue);
    });

    testWidgets('验证码加载失败时展示错误提示（离线环境）', (tester) async {
      await pumpLogin(tester);

      expect(find.text('验证码加载失败'), findsOneWidget);
    });
  });

  group('LoginPage — 必填校验', () {
    testWidgets('用户名密码均为空时提示「请输入用户名和密码」', (tester) async {
      await pumpLogin(tester);

      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
    });

    testWidgets('只填用户名不填密码时同样拦截', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
    });

    testWidgets('只填密码不填用户名时同样拦截', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
    });
  });

  group('LoginPage — 验证码缺失拦截', () {
    testWidgets('用户名密码已填但验证码未加载时提示「请加载验证码」', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请加载验证码'), findsOneWidget);
    });
  });
}
