// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// ERP 各业务域代表列表页 Widget 测试：商品、销售订单、库存预警、
// 财务应收应付、项目、审批。全部通过 mock ApiService 单例离线运行。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/finance/ar_ap_list_page.dart';
import 'package:admin_app/app/pages/inventory/alert_list_page.dart';
import 'package:admin_app/app/pages/inventory/inventory_list_page.dart';
import 'package:admin_app/app/pages/product/product_list_page.dart';
import 'package:admin_app/app/pages/project/project_list_page.dart';
import 'package:admin_app/app/pages/sales/order_list_page.dart';
import 'package:admin_app/app/pages/workflow/my_approval_page.dart';
import 'package:admin_app/app/widgets/status_badge.dart';
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

  group('商品列表', () {
    testWidgets('渲染商品列与行数据', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/product': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '笔记本电脑', 'code': 'P001', 'spec': '15 寸', 'price': '5999.00'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const ProductListPage());

      expect(find.text('商品名称'), findsOneWidget);
      expect(find.text('编码'), findsOneWidget);
      expect(find.text('规格'), findsOneWidget);
      expect(find.text('价格'), findsOneWidget);
      expect(find.text('笔记本电脑'), findsOneWidget);
      expect(find.text('P001'), findsOneWidget);
    });
  });

  group('销售订单列表', () {
    testWidgets('渲染订单编号与状态（3=已发货）', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/sales/order': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'code': 'SO20260801', 'customer_id': '1001', 'customer_name': '华南科技', 'total_amount': '1280.00', 'status': 3},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const SalesOrderListPage());

      expect(find.text('订单编号'), findsOneWidget);
      expect(find.text('SO20260801'), findsOneWidget);
      expect(find.text('1280.00'), findsOneWidget);
      // 批3：客户名由列表 leftJoin 的 customer_name 带出（行无 name 列）
      expect(find.text('华南科技'), findsOneWidget);
      // 状态筛选已改为 chips 行(§5.2)，同一文案会同时出现在筛选 chips 与行内
      // StatusBadge —— 此处断言行内徽标，而非裸文本
      expect(find.descendant(of: find.byType(StatusBadge), matching: find.text('已发货')), findsOneWidget);
    });
  });

  group('库存列表', () {
    testWidgets('渲染真实库存列（批3：product_name/product_code/warehouse_name/batch_code/quantity/cost_price）', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/inventory': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'product_name': '内存条 8G', 'product_code': 'MEM-8G', 'warehouse_name': '主仓', 'batch_code': 'B2026-01', 'quantity': '120', 'cost_price': '320.00'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const InventoryListPage());

      expect(find.text('商品名称'), findsOneWidget);
      expect(find.text('商品编码'), findsOneWidget);
      expect(find.text('仓库'), findsOneWidget);
      expect(find.text('批次号'), findsOneWidget);
      expect(find.text('数量'), findsOneWidget);
      expect(find.text('成本价'), findsOneWidget);
      expect(find.text('内存条 8G'), findsOneWidget);
      expect(find.text('MEM-8G'), findsOneWidget);
      expect(find.text('主仓'), findsOneWidget);
      expect(find.text('B2026-01'), findsOneWidget);
      expect(find.text('120'), findsOneWidget);
      expect(find.text('320.00'), findsOneWidget);
    });
  });

  group('库存预警列表', () {
    testWidgets('渲染预警名称与编码', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/inventory/alert': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '内存条', 'code': 'MEM-8G'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const InventoryAlertListPage());

      expect(find.text('名称'), findsOneWidget);
      expect(find.text('内存条'), findsOneWidget);
      expect(find.text('MEM-8G'), findsOneWidget);
    });
  });

  group('财务应收应付列表', () {
    testWidgets('渲染往来单位名称与编码', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/finance/ar-ap': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': '华东供应商', 'code': 'SUP-001'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const ArApListPage());

      expect(find.text('华东供应商'), findsOneWidget);
      expect(find.text('SUP-001'), findsOneWidget);
    });
  });

  group('项目列表', () {
    testWidgets('渲染项目名称与编码', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/project': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 1, 'name': 'ERP 二期', 'code': 'PRJ-2026-01'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const ProjectListPage());

      expect(find.text('ERP 二期'), findsOneWidget);
      expect(find.text('PRJ-2026-01'), findsOneWidget);
    });
  });

  group('我的审批列表', () {
    testWidgets('渲染审批单据与状态（0=审批中）及通过/驳回操作', (tester) async {
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/approval/my': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {'id': 10, 'target_type': '采购订单', 'target_id': 'PO-88', 'status': 0, 'submitted_at': '2026-08-26 10:00:00'},
            ],
            'total': 1,
          },
        }),
      }));

      await pump(tester, const MyApprovalPage());

      expect(find.text('单据类型'), findsOneWidget);
      expect(find.text('采购订单'), findsOneWidget);
      expect(find.text('PO-88'), findsOneWidget);
      expect(find.text('审批中'), findsOneWidget);
      expect(find.byTooltip('通过'), findsOneWidget);
      expect(find.byTooltip('驳回'), findsOneWidget);
    });
  });
}
