// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 视觉 2.0 动效/空数据分支测试：KPI 数值 400ms 滚动（终值前不出现终值、
// 滚动结束后到位）、reduce-motion 直出终值、全空数据下图表区画空态不炸。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/dashboard/dashboard_page.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'package:admin_app/app/widgets/empty_state.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  Map<String, dynamic> data() => {
    'code': 0,
    'data': {
      'stats': [
        {
          'label': '用户总数',
          'value': '128',
          'icon': 'people',
          'color': '#1677FF',
          'trend': 12.0,
        },
        {
          'label': '操作日志',
          'value': '6',
          'icon': 'description',
          'color': '#722ED1',
        },
      ],
      'trends': {
        'series': [
          {
            'name': '累计用户',
            'data': [10, 20, 30, 40],
          },
          {
            'name': '操作日志',
            'data': [1, 2, 3, 4],
          },
        ],
      },
      'distribution': {
        'user_status': [
          {'value': 100},
          {'value': 28},
        ],
      },
      'recent_logs': [],
    },
  };

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    adapter = FakeHttpClientAdapter(
      routes: {
        '/admin/v1/dashboard': (o) async =>
            FakeHttpClientAdapter.jsonResponse(data()),
        '/admin/v1/dashboard/oms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/wms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/tms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/sales': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/finance': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/inventory': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      },
    );
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpDashboard(
    WidgetTester tester, {
    bool reduceMotion = false,
  }) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(
            textScaler: const TextScaler.linear(0.85),
            disableAnimations: reduceMotion,
          ),
          child: const Scaffold(body: DashboardPage()),
        ),
      ),
    );
    // 让总览接口完成、KPI 卡首帧开始滚动
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('KPI 数值滚动:加载后未到终值,400ms 结束后显示终值', (tester) async {
    await pumpDashboard(tester);

    // 滚动中(约 50ms):终值尚未出现
    expect(find.text('128'), findsNothing);
    expect(find.text('6'), findsNothing);

    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('128'), findsOneWidget);
    expect(find.text('6'), findsOneWidget);
  });

  testWidgets('reduce-motion:数值直出终值不滚动', (tester) async {
    await pumpDashboard(tester, reduceMotion: true);

    expect(find.text('128'), findsOneWidget);
    expect(find.text('6'), findsOneWidget);
  });

  testWidgets('图表全空数据:总览与经营各图区画空态且不抛异常', (tester) async {
    // 全空负载(总览 stats/trends/distribution + 经营 sales 系列)
    adapter = FakeHttpClientAdapter(
      routes: {
        '/admin/v1/dashboard': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'stats': [],
            'trends': {'series': []},
            'distribution': {'user_status': []},
            'recent_logs': [],
          },
        }),
        '/admin/v1/dashboard/oms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/wms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/tms': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
        '/admin/v1/dashboard/sales': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {
                'trend': {'dates': [], 'amounts': []},
                'top_products': [],
                'status_distribution': [],
              },
            }),
        '/admin/v1/dashboard/finance': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {'ar_aging': [], 'ap_aging': []},
            }),
        '/admin/v1/dashboard/inventory': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {'total_value': 0, 'alert_low': 0, 'alert_high': 0},
            }),
      },
    );
    ApiService.instance.dio.httpClientAdapter = adapter;

    await pumpDashboard(tester);
    await tester.pump(const Duration(milliseconds: 500));

    // 总览:KPI 空态文案 + 趋势/分布图空态
    expect(find.text('暂无数据'), findsWidgets);
    expect(find.byType(EmptyState), findsWidgets);
    expect(tester.takeException(), isNull);

    // 经营 tab:销售趋势/订单状态环形空态(不炸)
    final bizTab = find
        .descendant(of: find.byType(TabBar), matching: find.byType(Tab))
        .at(1);
    await tester.tap(bizTab);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.byType(EmptyState), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}
