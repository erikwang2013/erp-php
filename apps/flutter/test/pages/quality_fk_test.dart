// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 质量模块（IQC/IPQC/OQC/不合格品/检验标准）外键契约测试：
//  - 列表外键列必须上屏后端 leftJoin 带回的关联名（*_name / *_code），裸 hashid 不许上屏；
//    关联行缺失/未关联（FK=0）落「-」占位；
//  - 表单外键必须是预取下拉（值=hashid），提交时下发 hashid 而非名称 ——
//    后端 decodeIdFields 靠它解码落 BIGINT 列。
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/quality/ipqc_list_page.dart';
import 'package:admin_app/app/pages/quality/iqc_list_page.dart';
import 'package:admin_app/app/pages/quality/nonconformity_list_page.dart';
import 'package:admin_app/app/pages/quality/oqc_list_page.dart';
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

  group('质量列表 — 外键列上屏关联名', () {
    testWidgets('IQC 商品列显示商品名，hashid 不上屏', (tester) async {
      await installApi({
        '/admin/v1/quality/iqc': (o) => listOf([
              {'id': 'hash1', 'code': 'IQC-1', 'product_id': 'hashProduct', 'product_name': '小米 14', 'inspected_qty': 10},
            ]),
      });

      await pump(tester, const IqcListPage());

      expect(find.text('小米 14'), findsOneWidget);
      expect(find.text('hashProduct'), findsNothing, reason: '裸 hashid 不许上屏');
    });

    testWidgets('IQC 关联行缺失（FK=0）落「-」占位', (tester) async {
      await installApi({
        '/admin/v1/quality/iqc': (o) => listOf([
              {'id': 'hash1', 'code': 'IQC-1', 'product_id': 0, 'product_name': null, 'inspected_qty': 10},
            ]),
      });

      await pump(tester, const IqcListPage());

      expect(find.text('-'), findsWidgets);
    });

    testWidgets('IPQC 工单/商品列显示工单编码与商品名', (tester) async {
      await installApi({
        '/admin/v1/quality/ipqc': (o) => listOf([
              {
                'id': 'hash1',
                'code': 'IPQC-1',
                'production_order_id': 'hashOrder',
                'production_order_code': 'MO-20260922-1',
                'product_id': 'hashProduct',
                'product_name': '小米 14',
                'inspected_qty': 10,
              },
            ]),
      });

      await pump(tester, const IpqcListPage());

      expect(find.text('MO-20260922-1'), findsOneWidget);
      expect(find.text('小米 14'), findsOneWidget);
      expect(find.text('hashOrder'), findsNothing);
      expect(find.text('hashProduct'), findsNothing);
    });

    testWidgets('OQC 发货单/商品列显示发货单号与商品名', (tester) async {
      await installApi({
        '/admin/v1/quality/oqc': (o) => listOf([
              {
                'id': 'hash1',
                'code': 'OQC-1',
                'delivery_id': 'hashDelivery',
                'delivery_code': 'DN-20260922-1',
                'product_id': 'hashProduct',
                'product_name': '小米 14',
                'inspected_qty': 10,
              },
            ]),
      });

      await pump(tester, const OqcListPage());

      expect(find.text('DN-20260922-1'), findsOneWidget);
      expect(find.text('小米 14'), findsOneWidget);
      expect(find.text('hashDelivery'), findsNothing);
      expect(find.text('hashProduct'), findsNothing);
    });

    testWidgets('不合格品 商品列显示商品名', (tester) async {
      await installApi({
        '/admin/v1/quality/nonconformity': (o) => listOf([
              {'id': 'hash1', 'code': 'NC-1', 'product_id': 'hashProduct', 'product_name': '小米 14', 'defect_qty': 2},
            ]),
      });

      await pump(tester, const NonconformityListPage());

      expect(find.text('小米 14'), findsOneWidget);
      expect(find.text('hashProduct'), findsNothing);
    });
  });

  group('质量表单 — 外键预取下拉，提交 hashid', () {
    Future<void> installIqcApi() => installApi({
          '/admin/v1/quality/iqc': (o) => listOf([]),
          '/admin/v1/purchase/receive': (o) => listOf([
                {'id': 'hashReceive', 'code': 'RC-20260922-1'},
              ]),
          '/admin/v1/product': (o) => listOf([
                {'id': 'hashProduct', 'name': '小米 14', 'code': 'P001'},
              ]),
          '/admin/v1/quality/standard': (o) => listOf([
                {'id': 'hashStd', 'name': '外观标准', 'code': 'STD-1'},
              ]),
        });

    testWidgets('IQC 新增：商品为下拉（显示商品名）且提交 hashid', (tester) async {
      await installIqcApi();
      await pump(tester, const IqcListPage());

      await tester.tap(find.text('新增'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // 外键字段是下拉，不是手输文本框：下拉项显示关联名，值为 hashid
      final dropdowns = find.byType(DropdownButtonFormField<String>);
      expect(dropdowns, findsWidgets);
      await tester.tap(dropdowns.at(1)); // receiving / product / standard 三连中的第 2 个 = 商品
      await tester.pumpAndSettle();
      expect(find.text('小米 14'), findsWidgets, reason: '下拉项显示商品名而非 hashid');
      await tester.tap(find.text('小米 14').last);
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField).first, 'IQC-1'); // 检验单号（必填）
      await tester.tap(find.text('提交'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final req = adapter.requests.where((r) => r.method == 'POST' && r.path == '/admin/v1/quality/iqc').toList();
      expect(req, hasLength(1));
      final body = req.single.data as Map<String, dynamic>;
      expect(body['product_id'], 'hashProduct', reason: '下拉的「值」是 hashid，不是商品名');
      expect(body['code'], 'IQC-1');
    });

    testWidgets('IQC 编辑：当前值在预取结果之外时前置进选项，不被静默清空', (tester) async {
      await installApi({
        '/admin/v1/quality/iqc': (o) => listOf([
              {
                'id': 'hash1',
                'code': 'IQC-1',
                'product_id': 'hashOldProduct', // 不在 /admin/v1/product 的 500 行预取里
                'product_name': '停用旧商品',
                'inspected_qty': 10,
              },
            ]),
        '/admin/v1/purchase/receive': (o) => listOf([]),
        '/admin/v1/product': (o) => listOf([
              {'id': 'hashProduct', 'name': '小米 14'},
            ]),
        '/admin/v1/quality/standard': (o) => listOf([]),
      });
      await pump(tester, const IqcListPage());

      await tester.tap(find.byIcon(Icons.edit).first);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('停用旧商品'), findsWidgets, reason: '当前关联值应前置进选项而不是落空');
      expect(find.text('hashOldProduct'), findsNothing, reason: '前置项的文案取关联名，不贴 hashid');
    });
  });
}
