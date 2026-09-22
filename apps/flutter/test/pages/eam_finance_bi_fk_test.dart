// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// eam / finance / bi 三模块外键契约测试：
//  - 列表外键列必须上屏预取回来的关联名（设备编码 / 币种 code / 模板名 / 用户名），
//    裸 hashid 不许上屏；解不出（引用行已删、超出预取 500 上限、未关联）落「-」；
//  - 表单外键必须是预取下拉（选项值=hashid），提交下发 hashid 而非手输数字 ——
//    后端 decodeFlexibleId / decodeIdFields 靠它解码落 BIGINT 列（数字直填/ hashid 直填
//    BIGINT 分别会写错行与 1366）；
//  - 编辑态当前值在预取结果之外时前置占位项，不被 FormDialog 静默清空。
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/l10n/app_l10n.dart';
import 'package:admin_app/app/pages/bi/dashboard_list_page.dart';
import 'package:admin_app/app/pages/bi/dataset_list_page.dart';
import 'package:admin_app/app/pages/eam/maintenance_plan_page.dart';
import 'package:admin_app/app/pages/finance/exchange_rate_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  Future<void> installApi(Map<String, Future<ResponseBody> Function(RequestOptions)> routes) async {
    adapter = FakeHttpClientAdapter(routes: routes);
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    ApiService.instance.dio.httpClientAdapter = adapter;
  }

  Future<void> pump(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(MaterialApp(home: Scaffold(body: page)));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  Future<ResponseBody> listOf(List<Map<String, dynamic>> rows) async =>
      FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {'list': rows, 'total': rows.length},
      });

  /// 打开弹窗后选中第 [index] 个下拉的 [label] 项（下拉项文案 ≠ 值）。
  Future<void> pickDropdown(WidgetTester tester, int index, String label) async {
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(index));
    await tester.pumpAndSettle();
    await tester.tap(find.text(label).last);
    await tester.pumpAndSettle();
  }

  /// 取本次 [method] [path] 的请求体（唯一一条）。
  Map<String, dynamic> bodyOf(String method, String path) {
    final req = adapter.requests.where((r) => r.method == method && r.path == path).toList();
    expect(req, hasLength(1), reason: '$method $path 应恰好发出一次');
    return req.single.data as Map<String, dynamic>;
  }

  group('eam 保养计划 — 设备外键', () {
    final planRows = [
      {'id': 'hashPlan', 'name': '月度保养', 'equipment_id': 'hashEq', 'frequency': 'monthly', 'status': '1'},
      {'id': 'hashPlan2', 'name': '无设备计划', 'equipment_id': 0, 'frequency': 'yearly', 'status': '1'},
    ];

    testWidgets('列表设备列显示设备编码，裸 hashid 不上屏（未关联落「-」）', (tester) async {
      await installApi({
        '/admin/v1/eam/maintenance': (o) => listOf(planRows),
        '/admin/v1/eam/equipment': (o) => listOf([
              {'id': 'hashEq', 'code': 'EQ-001', 'name': '注塑机'},
            ]),
      });

      await pump(tester, const MaintenancePlanPage());

      expect(find.text('EQ-001'), findsOneWidget);
      expect(find.text('hashEq'), findsNothing, reason: '裸 hashid 不许上屏');
      expect(find.text('注塑机'), findsNothing, reason: '设备编码优先于名称');
      expect(find.text('-'), findsWidgets, reason: 'FK=0 落占位');
    });

    testWidgets('新增：设备是预取下拉，提交下发 hashid', (tester) async {
      await installApi({
        '/admin/v1/eam/maintenance': (o) => listOf([]),
        '/admin/v1/eam/equipment': (o) => listOf([
              {'id': 'hashEq', 'code': 'EQ-001'},
            ]),
      });
      await pump(tester, const MaintenancePlanPage());

      await tester.tap(find.text(AppL10n.current.commonAdd));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      await pickDropdown(tester, 0, 'EQ-001'); // 设备（text 手输 → 下拉）
      // 频率（必填）：下拉项显示词表文案（optionLabels），不再贴机读值 monthly；
      // 提交值仍是 monthly（后端存储值），故本行只改"怎么选"，不改这条用例的断言
      await pickDropdown(tester, 1, AppL10n.current.eamFrequencyMonthly);
      await tester.enterText(find.byType(TextFormField).first, '月度保养');
      await tester.tap(find.text(AppL10n.current.commonSubmit));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final body = bodyOf('POST', '/admin/v1/eam/maintenance');
      expect(body['equipment_id'], 'hashEq', reason: '下拉的「值」是 hashid，不是手输数字/编码');
      expect(body['name'], '月度保养');
    });

    testWidgets('编辑：当前值不在预取结果内时前置占位项，回存仍是原 hashid（不被静默清空）', (tester) async {
      await installApi({
        '/admin/v1/eam/maintenance': (o) => listOf([
              {'id': 'hashPlan', 'name': '月度保养', 'equipment_id': 'hashOldEq', 'frequency': 'monthly', 'status': '1'},
            ]),
        // 该设备已停用/超出 500 行预取窗口 → 不在 options 里
        '/admin/v1/eam/equipment': (o) => listOf([
              {'id': 'hashEq', 'code': 'EQ-001'},
            ]),
      });
      await pump(tester, const MaintenancePlanPage());

      await tester.tap(find.byIcon(Icons.edit).first);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // 占位项文案取原值（本模块列表行不带关联名，同 inventory/alert_list_page.dart:90-96 口径）
      expect(find.text('hashOldEq'), findsWidgets, reason: '当前值应前置进选项而不是落空');

      await tester.tap(find.text(AppL10n.current.commonSubmit));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final body = bodyOf('PUT', '/admin/v1/eam/maintenance/hashPlan');
      expect(body['equipment_id'], 'hashOldEq', reason: '未被静默清空（FormDialog 对不在 options 的预填值会置 null）');
    });
  });

  group('finance 汇率 — 币种外键', () {
    testWidgets('列表两列显示币种 code（不是 hashid），新增提交两个 hashid', (tester) async {
      await installApi({
        '/admin/v1/finance/exchange-rate': (o) => listOf([
              {'id': 'hashRate', 'from_currency_id': 'hashCNY', 'to_currency_id': 'hashUSD', 'rate': '7.2', 'effective_date': '2026-09-01'},
            ]),
        '/admin/v1/finance/currency': (o) => listOf([
              {'id': 'hashCNY', 'code': 'CNY'},
              {'id': 'hashUSD', 'code': 'USD'},
            ]),
      });
      await pump(tester, const ExchangeRatePage());

      expect(find.text('CNY'), findsOneWidget);
      expect(find.text('USD'), findsOneWidget);
      expect(find.text('hashCNY'), findsNothing);
      expect(find.text('hashUSD'), findsNothing);

      await tester.tap(find.text(AppL10n.current.financeExchangeRateAdd));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      await pickDropdown(tester, 0, 'CNY');
      await pickDropdown(tester, 1, 'USD');
      await tester.enterText(find.byType(TextFormField).first, '7.35'); // rate
      await tester.tap(find.text(AppL10n.current.commonSubmit));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final body = bodyOf('POST', '/admin/v1/finance/exchange-rate');
      expect(body['from_currency_id'], 'hashCNY');
      expect(body['to_currency_id'], 'hashUSD');
      expect(body['rate'], '7.35');
    });
  });

  group('bi — 模板 / 用户外键', () {
    testWidgets('数据集列表显示模板名，新增提交 template_id hashid', (tester) async {
      await installApi({
        '/admin/v1/bi/dataset': (o) => listOf([
              {'id': 'hashDs', 'name': '月度销售', 'template_id': 'hashTpl', 'rows_count': 12},
            ]),
        '/admin/v1/report': (o) => listOf([
              {'id': 'hashTpl', 'name': '销售模板', 'code': 'RPT-1'},
            ]),
      });
      await pump(tester, const DatasetListPage());

      expect(find.text('销售模板'), findsOneWidget);
      expect(find.text('hashTpl'), findsNothing, reason: '裸 hashid 不许上屏');

      await tester.tap(find.text(AppL10n.current.commonAdd));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      await pickDropdown(tester, 0, '销售模板');
      await tester.enterText(find.byType(TextFormField).first, '季度销售');
      await tester.tap(find.text(AppL10n.current.commonSubmit));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final body = bodyOf('POST', '/admin/v1/bi/dataset');
      expect(body['template_id'], 'hashTpl');
      expect(body['name'], '季度销售');
    });

    testWidgets('看板列表显示用户名，新增提交 user_id hashid', (tester) async {
      await installApi({
        '/admin/v1/bi/dashboard': (o) => listOf([
              {'id': 'hashDash', 'name': '销售看板', 'user_id': 'hashUser', 'status': '1'},
            ]),
        '/admin/v1/user': (o) => listOf([
              {'id': 'hashUser', 'username': 'alice'},
            ]),
      });
      await pump(tester, const DashboardListPage());

      expect(find.text('alice'), findsOneWidget);
      expect(find.text('hashUser'), findsNothing);

      await tester.tap(find.text(AppL10n.current.commonAdd));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      await pickDropdown(tester, 0, 'alice'); // user_id 可空下拉
      await tester.enterText(find.byType(TextFormField).first, '看板A');
      await tester.tap(find.text(AppL10n.current.commonSubmit));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final body = bodyOf('POST', '/admin/v1/bi/dashboard');
      expect(body['user_id'], 'hashUser');
      expect(body['name'], '看板A');
    });

    testWidgets('图表管理：类型列走词表（bar/line/pie/table/kpi 机读值不上屏）', (tester) async {
      await installApi({
        '/admin/v1/bi/dashboard': (o) => listOf([
              {'id': 'hashDash', 'name': '销售看板', 'user_id': 'hashUser', 'status': '1'},
            ]),
        '/admin/v1/bi/dataset': (o) => listOf([]),
        '/admin/v1/bi/widget': (o) => listOf([
              {'id': 'hashW1', 'name': '柱图', 'type': 'bar', 'dataset_id': 'hashDs'},
              {'id': 'hashW2', 'name': '指标卡', 'type': 'kpi', 'dataset_id': 'hashDs'},
            ]),
      });
      await pump(tester, const DashboardListPage());

      await tester.tap(find.byIcon(Icons.dashboard_customize).first);
      await tester.pumpAndSettle();

      expect(find.text(AppL10n.current.biChartTypeLabel(AppL10n.current.biChartTypeBar)), findsOneWidget);
      expect(find.text(AppL10n.current.biChartTypeLabel(AppL10n.current.biChartTypeKpi)), findsOneWidget);
      expect(find.text('bar'), findsNothing, reason: '机读值不上屏');
      expect(find.text('kpi'), findsNothing, reason: '机读值不上屏');
    });
  });
}
