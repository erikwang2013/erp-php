// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 登录页国际化渲染测试：en locale 下登录页关键文案渲染为英文。
// 通过配置 localizationsDelegates + supportedLocales 验证 arb 与页面接入生效；
// 网络请求在 flutter_test 环境默认被拦截（400），验证码加载走失败路径，天然离线。
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/pages/login/login_page.dart';
import 'package:admin_app/l10n/app_localizations.dart';

void main() {
  Widget wrap() => MaterialApp(
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
          AppLocalizations.delegate,
        ],
        home: const LoginPage(),
      );

  Future<void> pumpLogin(WidgetTester tester) async {
    await tester.pumpWidget(wrap());
    // 等待 _loadCaptcha 的网络失败回调完成
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));
  }

  testWidgets('en locale 下登录页渲染英文文案', (tester) async {
    await pumpLogin(tester);

    expect(find.text('Open ERP Admin'), findsOneWidget);
    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Log In'), findsOneWidget);
    // 中文文案不应出现
    expect(find.text('开放管理后台'), findsNothing);
    expect(find.text('登 录'), findsNothing);
  });

  testWidgets('en locale 下验证码加载失败提示为英文', (tester) async {
    await pumpLogin(tester);

    expect(find.text('Failed to load captcha'), findsOneWidget);
  });
}
