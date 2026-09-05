// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 登录页 Widget 测试：必填校验、密码可见性切换、点「登录」弹出验证码弹框
// 及其失败/取消行为。flutter_test 环境默认拦截真实网络（返回 400），
// 验证码弹框内请求必然失败，因此本测试天然离线，无需 mock 网络。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/pages/login/login_page.dart';
import 'package:admin_app/app/widgets/captcha_verify_dialog.dart';

void main() {
  Widget wrap() => const MaterialApp(home: LoginPage());

  /// 让登录页就绪 + 验证码弹框的异步请求（失败路径）完成。
  Future<void> pumpLogin(WidgetTester tester) async {
    await tester.pumpWidget(wrap());
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
      // 密码框应为密文模式，且带可见性切换按钮
      final pwdField = tester.widget<TextField>(find.byType(TextField).at(1));
      expect(pwdField.obscureText, isTrue);
      expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);
    });

    testWidgets('点击眼睛图标切换密码可见性', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(1), 'secret');
      expect(tester.widget<TextField>(find.byType(TextField).at(1)).obscureText, isTrue);

      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pump();
      expect(tester.widget<TextField>(find.byType(TextField).at(1)).obscureText, isFalse);
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);

      await tester.tap(find.byIcon(Icons.visibility_off_outlined));
      await tester.pump();
      expect(tester.widget<TextField>(find.byType(TextField).at(1)).obscureText, isTrue);
    });
  });

  group('LoginPage — 必填校验', () {
    testWidgets('用户名密码均为空时提示「请输入用户名和密码」', (tester) async {
      await pumpLogin(tester);

      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
      // 校验不通过时不弹验证码
      expect(find.byType(CaptchaVerifyDialog), findsNothing);
    });

    testWidgets('只填用户名不填密码时同样拦截', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
      expect(find.byType(CaptchaVerifyDialog), findsNothing);
    });

    testWidgets('只填密码不填用户名时同样拦截', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();

      expect(find.text('请输入用户名和密码'), findsOneWidget);
      expect(find.byType(CaptchaVerifyDialog), findsNothing);
    });
  });

  group('LoginPage — 验证码弹框（离线环境）', () {
    testWidgets('用户名密码已填时点击登录弹出验证码弹框', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150)); // 弹框转场

      expect(find.byType(CaptchaVerifyDialog), findsOneWidget);
    });

    testWidgets('离线时弹框内展示验证码加载失败与重试按钮', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150));
      // 等待弹框内 generate 请求失败回调
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('验证码加载失败'), findsOneWidget);
      // 失败态中央与底部栏各有一个刷新入口
      expect(find.byIcon(Icons.refresh), findsNWidgets(2));
    });

    testWidgets('用户关闭验证码弹框视为取消登录，不发起登录请求', (tester) async {
      await pumpLogin(tester);

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150));
      expect(find.byType(CaptchaVerifyDialog), findsOneWidget);

      // 点击遮罩关闭弹框（取消）
      await tester.tapAt(const Offset(10, 10));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.byType(CaptchaVerifyDialog), findsNothing);
      // 取消后仍在登录页、无网络错误提示（未发起登录请求）
      expect(find.byType(FilledButton), findsOneWidget);
      expect(find.text('网络连接失败，请检查网络'), findsNothing);
    });
  });
}
