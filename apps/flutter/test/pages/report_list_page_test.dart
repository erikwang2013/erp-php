// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 报表列表页 Widget 测试：覆盖列表渲染、执行报表后的结果详情对话框
// （数据集ID/结果行数/生成时间/结果表格）及关闭交互。mock ApiService 离线运行。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/report/report_list_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<void> pump(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(MaterialApp(home: Scaffold(body: page)));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  Future<void> installApi(FakeHttpClientAdapter adapter) async {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    ApiService.instance.dio.httpClientAdapter = adapter;
  }

  group('报表列表', () {
    testWidgets('渲染报表列与行数据及执行按钮', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/report': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '销售月报', 'code': 'RPT-SALE-01'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const ReportListPage());

      expect(find.text('名称'), findsOneWidget);
      expect(find.text('编码'), findsOneWidget);
      expect(find.text('销售月报'), findsOneWidget);
      expect(find.text('RPT-SALE-01'), findsOneWidget);
      expect(find.byTooltip('执行'), findsOneWidget);
    });
  });

  group('报表执行结果详情', () {
    testWidgets('点击执行展示详情对话框（数据集/行数/生成时间/结果表格）', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/report': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '销售月报', 'code': 'RPT-SALE-01'},
            ],
            'total': 1,
          },
        }),
        '/admin/report/1/execute': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {'dataset_id': 'ds-20260827-001'},
        }),
        '/admin/report/1/result': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'dataset_id': 'ds-20260827-001',
            'rows_count': 2,
            'generated_at': '2026-08-27 10:00:00',
            'data': [
              {'月份': '2026-07', '金额': '1000.00'},
              {'月份': '2026-08', '金额': '1280.00'},
            ],
          },
        }),
      }));

      await pump(tester, const ReportListPage());

      await tester.tap(find.byTooltip('执行'));
      await tester.pumpAndSettle();

      expect(find.text('报表结果：销售月报'), findsOneWidget);
      expect(find.textContaining('数据集ID: ds-20260827-001'), findsOneWidget);
      expect(find.text('结果行数: 2'), findsOneWidget);
      expect(find.text('生成时间: 2026-08-27 10:00:00'), findsOneWidget);
      expect(find.text('2026-07'), findsOneWidget);
      expect(find.text('1280.00'), findsOneWidget);
    });

    testWidgets('结果为空时提示暂无数据行，关闭按钮可退出详情', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/report': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '销售月报', 'code': 'RPT-SALE-01'},
            ],
            'total': 1,
          },
        }),
        '/admin/report/1/execute': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {'dataset_id': 'ds-20260827-002'},
        }),
        '/admin/report/1/result': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'dataset_id': 'ds-20260827-002',
            'data': <dynamic>[],
          },
        }),
      }));

      await pump(tester, const ReportListPage());

      await tester.tap(find.byTooltip('执行'));
      await tester.pumpAndSettle();

      expect(find.text('查询成功，暂无数据行'), findsOneWidget);

      await tester.tap(find.text('关闭'));
      await tester.pumpAndSettle();

      expect(find.text('查询成功，暂无数据行'), findsNothing);
    });
  });
}
