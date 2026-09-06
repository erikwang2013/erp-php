// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 视觉 2.0 登录门面 smoke：桌面分栏(品牌 hero 右侧表单)与窄屏品牌带
// 两布局均渲染 mascot/标题/标语/输入框/按钮,无溢出无异常,基础交互可用。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/pages/login/login_page.dart';

void main() {
  Widget wrap() => const MaterialApp(home: LoginPage());

  Future<void> settle(WidgetTester tester) async {
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 300));
  }

  bool isMascot(Widget w) =>
      w is Image &&
      w.image is AssetImage &&
      (w.image as AssetImage).assetName == 'assets/mascot.png';

  testWidgets('桌面(≥768):品牌 hero 与表单分栏渲染,表单可交互', (tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(wrap());
    await settle(tester);

    expect(find.text('开放管理后台'), findsOneWidget);
    expect(find.text('一个平台，管好全部业务'), findsOneWidget);
    expect(find.byWidgetPredicate(isMascot), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(find.byType(FilledButton), findsOneWidget);
    expect(tester.takeException(), isNull);

    // 基础交互:空提交出必填提示(行为未被门面改造破坏)
    await tester.tap(find.byType(FilledButton));
    await tester.pump();
    expect(find.text('请输入用户名和密码'), findsOneWidget);
  });

  testWidgets('窄屏(<768):顶部品牌带 + 表单卡,无溢出', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(wrap());
    await settle(tester);

    expect(find.text('开放管理后台'), findsOneWidget);
    expect(find.text('一个平台，管好全部业务'), findsOneWidget);
    expect(find.byWidgetPredicate(isMascot), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(find.byType(FilledButton), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.enterText(find.byType(TextField).at(0), 'admin');
    await tester.enterText(find.byType(TextField).at(1), 'secret');
    await tester.tap(find.byType(FilledButton));
    await tester.pump(const Duration(milliseconds: 200));
    // 网络层被 flutter_test 拦截,登录停留在登录页(验证码弹框层)且不崩
    expect(find.byType(TextField), findsNWidgets(2));
    expect(tester.takeException(), isNull);
    // 收尾:放行在途请求的 dio 10s 超时定时器,避免测试结束仍有 pending Timer
    await tester.pump(const Duration(seconds: 11));
    await tester.pump(const Duration(milliseconds: 100));
  });
}
