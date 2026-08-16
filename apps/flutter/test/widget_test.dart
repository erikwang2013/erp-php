// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// App 冒烟测试：应用能正常启动并渲染登录页。
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/main.dart';

void main() {
  testWidgets('Admin app smoke test — 启动后渲染登录页', (WidgetTester tester) async {
    await tester.pumpWidget(const AdminApp());
    // 等待首帧与异步初始化（验证码请求在测试环境被拦截为失败）
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    // 初始路由为 /login，应展示登录页标题与登录按钮
    expect(find.text('开放管理后台'), findsOneWidget);
    expect(find.text('登 录'), findsOneWidget);
  });
}
