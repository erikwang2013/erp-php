// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 财务报表页 Widget 测试（批3 契约修正）：
// 1. 合并报表 Tab7 用真实响应映射渲染（consolidate 出口 data 形状）；
//    请求体与后端校验对齐（subsidiary_reports 每项 ledger_id|company_id）。
// 2. 资产负债表 report_data 结构化明细（generated_from/lines 对象化，非 JSON 串）；
//    空态回退（后端解码兜底 []）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/finance/report_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

/// 后端 ConsolidationService::consolidate 出口形状（batch1 真实契约）。
Map<String, dynamic> consolidateResponse() => {
      'code': 0,
      'data': {
        'base_currency': 'CNY',
        'report_year': 2026,
        'report_month': 8,
        'total_assets': '1000',
        'total_liabilities': '400',
        'total_equity': '600',
        'revenue': '500',
        'net_profit': '80',
        'report_data': {
          'generated_from': 'live',
          'base_currency': 'CNY',
          'subsidiaries': [
            {
              'ledger_id': 'l-1',
              'company_id': 'c-1',
              'code': 'SUB-01',
              'name': '子公司A',
              'currency': 'USD',
              'rate': '7.20',
              'source': 'voucher',
              'total_assets': '200.00',
              'total_liabilities': '50.00',
              'total_equity': '150.00',
              'revenue': '100.00',
              'net_profit': '20.00',
            },
          ],
        },
      },
    };

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
  });

  Future<void> pumpReportPage(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: FinanceReportPage())));
    await tester.pump();
  }

  /// 切到指定 Tab（TabBar 可滚动，目标可能视口外）。
  Future<void> switchTab(WidgetTester tester, String label) async {
    await tester.scrollUntilVisible(
      find.text(label),
      120,
      scrollable: find.descendant(
        of: find.byType(TabBar),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.tap(find.text(label));
    await tester.pumpAndSettle();
  }

  testWidgets('Tab7 合并报表：真实响应指标+子公司子表渲染；请求体含 ledger_id 项', (tester) async {
    adapter = FakeHttpClientAdapter(routes: {
      '/admin/v1/finance/report/profit': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/v1/finance/report/consolidate': (o) async =>
          FakeHttpClientAdapter.jsonResponse(consolidateResponse()),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;

    await pumpReportPage(tester);
    await switchTab(tester, '合并报表');

    await tester.enterText(
      find.byType(TextField).first,
      '[{"ledger_id":"l-9","report_year":2026,"report_month":8}]',
    );
    await tester.tap(find.text('执行合并'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    // 指标卡（千分位+两位小数格式化）
    expect(find.text('1,000.00'), findsOneWidget); // total_assets
    expect(find.text('400.00'), findsOneWidget); // total_liabilities
    expect(find.text('80.00'), findsOneWidget); // net_profit
    expect(find.text('CNY'), findsNWidgets(2)); // base_currency 指标 + 本位币输入框
    // 子公司子表行（真实字段渲染）
    expect(find.text('子公司A'), findsOneWidget);
    expect(find.text('SUB-01'), findsOneWidget);
    expect(find.text('USD'), findsOneWidget);

    final req = adapter.requests
        .where((r) => r.path == '/admin/v1/finance/report/consolidate')
        .toList();
    expect(req, hasLength(1));
    final body = req.first.data as Map<String, dynamic>;
    expect(body['base_currency'], 'CNY');
    expect(body['subsidiary_reports'], [
      {'ledger_id': 'l-9', 'report_year': 2026, 'report_month': 8},
    ]);
  });

  testWidgets('资产负债表：report_data 结构化渲染（generated_from/lines），空态回退', (tester) async {
    var detail = true;
    adapter = FakeHttpClientAdapter(routes: {
      '/admin/v1/finance/report/profit': (o) async => FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
      '/admin/v1/finance/report/balance-sheet': (o) async =>
          FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'current_assets': '300',
          'total_assets': '1000',
          'total_liabilities': '400',
          'total_equity': '600',
          'report_data': detail
              ? {
                  'generated_from': 'voucher',
                  'lines': [
                    {'account_id': '1', 'code': '1001', 'name': '库存现金', 'balance': '100.00'},
                  ],
                }
              : [],
        },
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;

    await pumpReportPage(tester);
    await switchTab(tester, '资产负债表');

    // 一查：对象明细 → key: value 行 + lines 科目表
    await tester.tap(find.text('查询'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('generated_from: voucher'), findsOneWidget);
    expect(find.text('1001'), findsOneWidget);
    expect(find.text('库存现金'), findsOneWidget);
    expect(find.text('100.00'), findsOneWidget);

    // 二查：损坏明细（后端 decodeReportData 兜底 []）→ 空态文案
    detail = false;
    await tester.tap(find.text('查询'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('暂无明细数据'), findsOneWidget);
    expect(find.text('generated_from: voucher'), findsNothing);
  });
}
