// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 仪表盘页 Widget 测试：mock /admin/dashboard 及 OMS/WMS/TMS 看板接口，
// 验证统计卡片、最近操作日志与标签页切换。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/dashboard/dashboard_page.dart';
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
      '/admin/v1/dashboard': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'stats': [
            {'label': '用户总数', 'value': '128', 'icon': 'people', 'color': '#1677FF', 'trend': 12.0},
            {'label': '今日订单', 'value': '36', 'icon': 'bolt', 'color': '#52C41A', 'trend': -4.0},
            {'label': '新增用户', 'value': '8', 'icon': 'person_add', 'color': '#FA8C16'},
            {'label': '待办事项', 'value': '5', 'icon': 'description', 'color': '#722ED1'},
          ],
          'trends': {
            'series': [
              {'name': '订单量', 'data': [10, 20, 15, 30, 25]},
            ],
          },
          'distribution': {
            'user_status': [
              {'value': 100},
              {'value': 28},
            ],
          },
          'recent_logs': [
            {'user_name': 'admin', 'action': '登录系统', 'created_at': '2026-08-26 09:00:00', 'ip': '127.0.0.1'},
          ],
        },
      }),
      '/admin/v1/dashboard/oms': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {'pending_orders': 3, 'picking_orders': 2, 'shipped_today': 9, 'pending_rma': 1},
      }),
      '/admin/v1/dashboard/wms': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {'pending_receiving': 4, 'pending_putaway': 1, 'pending_picks': 6, 'pending_packs': 2},
      }),
      '/admin/v1/dashboard/tms': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {'pending_shipments': 7, 'in_transit': 3, 'delivered_today': 5, 'exception_shipments': 0},
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpDashboard(WidgetTester tester) async {
    // Ahem 测试字体的行高比 Roboto 高，把文本缩放 0.85 模拟真实设备，
    // 避免统计卡片 mainAxisExtent 120 内 3px 的 RenderFlex 溢出。
    await tester.pumpWidget(MaterialApp(
      home: MediaQuery(
        data: MediaQueryData(textScaler: const TextScaler.linear(0.85)),
        child: const Scaffold(body: DashboardPage()),
      ),
    ));
    // 等待三个看板接口的异步加载完成
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));
  }

  group('DashboardPage — 总览', () {
    testWidgets('加载后渲染统计卡片与数值', (tester) async {
      await pumpDashboard(tester);

      expect(find.text('用户总数'), findsOneWidget);
      expect(find.text('128'), findsOneWidget);
      expect(find.text('今日订单'), findsOneWidget);
      expect(find.text('36'), findsOneWidget);
    });

    testWidgets('渲染最近操作日志', (tester) async {
      await pumpDashboard(tester);

      expect(find.text('登录系统'), findsOneWidget);
      expect(find.text('127.0.0.1'), findsOneWidget);
    });

    testWidgets('渲染 OMS/WMS/TMS 三个标签', (tester) async {
      await pumpDashboard(tester);

      expect(find.text('OMS'), findsOneWidget);
      expect(find.text('WMS'), findsOneWidget);
      expect(find.text('TMS'), findsOneWidget);
    });
  });

  group('DashboardPage — 标签页切换', () {
    testWidgets('切到 OMS 标签显示运营统计卡片', (tester) async {
      await pumpDashboard(tester);

      await tester.tap(find.text('OMS'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('待处理订单'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);
      expect(find.text('今日发货'), findsOneWidget);
      expect(find.text('9'), findsOneWidget);
    });

    testWidgets('切到 WMS 标签显示仓储统计', (tester) async {
      await pumpDashboard(tester);

      await tester.tap(find.text('WMS'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('待拣货'), findsOneWidget);
      expect(find.text('6'), findsOneWidget);
    });
  });
}
