// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 批5 联动测试：
// 1) 销售订单详情渲染（header + items 表）与引用卡下钻（商品/客户）；
// 2) 引用卡错误态 → 重试 → 成功态；
// 3) 审批详情 approve 动作 → 刷新（状态 0→1）+ PopScope 返回 changed=true；
// 4) OMS 订单详情：无幻键明细卡（F1）+ allocate 行内 int/double 校验（F2）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/oms/order_detail_page.dart';
import 'package:admin_app/app/pages/sales/order_detail_page.dart';
import 'package:admin_app/app/pages/workflow/approval_detail_page.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'package:admin_app/app/widgets/reference_card.dart';
import 'package:admin_app/app/widgets/status_badge.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<void> installApi(FakeHttpClientAdapter adapter) async {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    ApiService.instance.dio.httpClientAdapter = adapter;
  }

  Future<void> settle(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  /// 排空 SnackBar 队列（含退场动画帧），避免遮蔽后续断言/pending timer。
  Future<void> drainSnackBars(WidgetTester tester) async {
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
    }
  }

  group('销售订单详情', () {
    testWidgets('渲染头部/明细表，行项与客户可下钻引用卡', (tester) async {
      var productFetched = 0;
      var customerFetched = 0;
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/sales/order/SO1': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {
                'id': 'SO1',
                'code': 'SO20260801',
                'customer_id': 'C9',
                'customer_name': '华南科技',
                'status': 3,
                'ordered_at': '2026-08-01 10:00:00',
                'total_amount': '1280.00',
                'discount_amount': '10.00',
                'remark': '加急',
                'items': [
                  {
                    'id': 'I1',
                    'product_id': 'P9',
                    'product_name': '笔记本电脑',
                    'quantity': '1',
                    'price': '1280.00',
                    'amount': '1280.00',
                    'unit': '台',
                  },
                ],
              },
            }),
        '/admin/v1/product/P9': (o) async {
          productFetched++;
          return FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'id': 'P9',
              'name': '笔记本电脑',
              'code': 'NB-01',
              'spec': '15 寸',
              'unit': '台',
              'status': 1,
            },
          });
        },
        '/admin/v1/customer/C9': (o) async {
          customerFetched++;
          return FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'id': 'C9',
              'name': '华南科技',
              'code': 'C-01',
              'phone': '13800000000',
              'status': 1,
            },
          });
        },
      }));

      await tester.pumpWidget(MaterialApp(
          home: Scaffold(body: SalesOrderDetailPage(id: 'SO1'))));
      await settle(tester);

      // header 行 + 状态徽标 + 金额
      expect(find.text('SO20260801'), findsOneWidget);
      expect(find.text('华南科技'), findsOneWidget);
      expect(find.descendant(
          of: find.byType(StatusBadge), matching: find.text('已发货')),
          findsOneWidget);
      expect(find.text('1280.00'), findsNWidgets(3)); // 单价 + 行金额 + 总金额
      expect(find.text('1'), findsOneWidget); // 行数量
      expect(find.text('台'), findsOneWidget); // 行单位
      expect(find.text('加急'), findsOneWidget);

      // 行项商品下钻引用卡
      await tester.tap(find.text('笔记本电脑'));
      await settle(tester);
      expect(productFetched, 1);
      expect(find.text('商品信息'), findsOneWidget);
      expect(find.text('NB-01'), findsOneWidget);
      await tester.tap(find.text('关闭'));
      await settle(tester);

      // 客户行下钻引用卡
      await tester.tap(find.text('华南科技'));
      await settle(tester);
      expect(customerFetched, 1);
      expect(find.text('客户信息'), findsOneWidget);
      expect(find.text('13800000000'), findsOneWidget);
    });
  });

  group('引用卡三态', () {
    testWidgets('失败 → 重试 → 成功', (tester) async {
      var calls = 0;
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/warehouse/W1': (o) async {
          calls++;
          if (calls == 1) throw Exception('boom');
          return FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'id': 'W1',
              'name': '主仓',
              'code': 'WH-01',
              'manager': '李四',
              'status': 1,
            },
          });
        },
      }));

      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) => ElevatedButton(
              onPressed: () => showReferenceCard(
                  context, resource: 'warehouse', id: 'W1'),
              child: const Text('打开'),
            ),
          ),
        ),
      ));
      await tester.tap(find.text('打开'));
      await settle(tester);
      expect(find.textContaining('加载失败'), findsOneWidget);
      expect(find.text('重试'), findsOneWidget);

      await tester.tap(find.text('重试'));
      await settle(tester);
      expect(calls, 2);
      expect(find.text('仓库信息'), findsOneWidget);
      expect(find.text('主仓'), findsOneWidget);
      expect(find.text('WH-01'), findsOneWidget);
    });
  });

  group('审批详情动作', () {
    testWidgets('批准 → 本页刷新为已通过 → 返回携带 changed=true', (tester) async {
      var approved = false;
      var showCalls = 0;
      var approveCalls = 0;
      var approvePath = '';
      var approveComment = '';
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/approval/A1': (o) async {
          showCalls++;
          return FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'id': 'A1',
              'workflow_name': '销售订单审批',
              'current_node_name': '经理审批',
              'submitter_name': '张三',
              'target_type': 'sales_order',
              'target_id': 'SO-1',
              'target_ref': 'SO1h',
              'status': approved ? 1 : 0,
              'submitted_at': '2026-08-26 10:00:00',
              'records': [
                {
                  'id': 'r1',
                  'operator_name': '张三',
                  'action': 1,
                  'comment': '提交',
                  'created_at': '2026-08-26 09:00:00',
                },
              ],
            },
          });
        },
        '/admin/v1/approval/A1/approve': (o) async {
          approveCalls++;
          approved = true;
          approvePath = o.path;
          approveComment = '${(o.data as Map)['comment'] ?? ''}';
          return FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}});
        },
      }));

      bool? popResult;
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) => ElevatedButton(
              onPressed: () async {
                popResult = await Navigator.of(context).push<bool>(
                  MaterialPageRoute(
                      builder: (_) => const ApprovalDetailPage(id: 'A1')),
                );
              },
              child: const Text('打开'),
            ),
          ),
        ),
      ));
      await tester.tap(find.text('打开'));
      await settle(tester);

      // 审批中状态 + 记录时间线 + 单据跳转入口
      expect(showCalls, 1);
      expect(find.text('销售订单审批'), findsOneWidget);
      expect(find.text('经理审批'), findsOneWidget);
      expect(find.descendant(
          of: find.byType(StatusBadge), matching: find.text('审批中')),
          findsOneWidget);
      expect(find.text('查看单据'), findsOneWidget);
      expect(find.textContaining('张三'), findsWidgets);

      // 批准（comment 可选）：输入意见走 typed-input 路径（F4 dispose 崩溃族
      // 的触发前提，reviewer 探针口径）
      await tester.tap(find.widgetWithText(FilledButton, '通过'));
      await settle(tester);
      expect(find.text('通过审批'), findsOneWidget);
      await tester.enterText(find.byType(TextField), '同意，放行');
      await tester.tap(find.widgetWithText(ElevatedButton, '确定'));
      await settle(tester);

      // 刷新后状态 1 → 徽标「已通过」
      expect(approvePath, '/admin/v1/approval/A1/approve');
      expect(approveComment, '同意，放行');
      expect(approveCalls, 1);
      expect(showCalls, 2);
      expect(find.descendant(
          of: find.byType(StatusBadge), matching: find.text('已通过')),
          findsOneWidget);
      expect(find.text('操作成功'), findsWidgets);

      // 返回 → PopScope 回传 changed=true（列表据此刷新）
      await tester.tap(find.byType(BackButton));
      await settle(tester);
      expect(popResult, isTrue);

      // 让 SnackBar 计时器到期，避免 pending timer 报错
      await tester.pump(const Duration(seconds: 5));
    });
  });

  group('OMS 订单详情', () {
    testWidgets('F1：无商品明细卡；F2：allocate 非法行拦截、合法行发 int/double',
        (tester) async {
      final posts = <Map<String, dynamic>>[];
      await installApi(FakeHttpClientAdapter(routes: {
        '/admin/v1/oms/order/O1': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {
                'id': 'O1',
                'channel_order_no': 'CH-20260801',
                'channel': '淘宝',
                'fulfillment_status': 0,
                'payment_status': 0,
                'priority': 5,
                'created_at': '2026-08-01 10:00:00',
              },
            }),
        '/admin/v1/oms/fulfillment': (o) async =>
            FakeHttpClientAdapter.jsonResponse({
              'code': 0,
              'data': {
                'list': [
                  {
                    'id': 'F1h',
                    'status': 4,
                    'warehouse_name': '主仓',
                    'created_at': '2026-08-01 11:00:00',
                  },
                ],
              },
            }),
        '/admin/v1/oms/order/O1/allocate': (o) async {
          posts.add(Map<String, dynamic>.from(o.data as Map));
          return FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}});
        },
      }));

      await tester.pumpWidget(MaterialApp(
          home: Scaffold(body: OmsOrderDetailPage(id: 'O1'))));
      await settle(tester);

      // F1：响应含 items 也不会渲染幻键卡；头字段 + 履约子表存在
      expect(find.text('商品明细'), findsNothing);
      expect(find.text('CH-20260801'), findsOneWidget);
      expect(find.text('未分配'), findsWidgets); // 状态徽标
      expect(find.text('主仓'), findsOneWidget); // 履约子表仓库名
      expect(find.text('查看单据'), findsOneWidget); // 履约子表行 → ④ 跳转

      // F2：非法 product_id → 拦截，不发请求
      await tester.tap(find.widgetWithText(FilledButton, '分配库存'));
      await settle(tester);
      await tester.enterText(
          find.widgetWithText(TextField, '商品ID（数字）'), 'abc');
      await tester.enterText(find.widgetWithText(TextField, '数量'), '1');
      await tester.tap(find.widgetWithText(ElevatedButton, '确定'));
      await settle(tester);
      expect(posts, isEmpty);
      expect(find.textContaining('商品ID须为正整数'), findsOneWidget);
      await drainSnackBars(tester); // 到期退场，避免排队遮蔽下一条断言

      // 空行 + 非法数量行 → 拦截
      await tester.tap(find.widgetWithText(FilledButton, '分配库存'));
      await settle(tester);
      await tester.enterText(
          find.widgetWithText(TextField, '商品ID（数字）'), '9');
      await tester.enterText(find.widgetWithText(TextField, '数量'), '-2');
      await tester.tap(find.widgetWithText(ElevatedButton, '确定'));
      await settle(tester);
      expect(posts, isEmpty);
      expect(find.textContaining('分配数量须为正数'), findsOneWidget);
      await drainSnackBars(tester); // 到期退场，避免排队遮蔽成功提示

      // 合法行 → POST body 为原始数字 int/double（非 String，防 PHP TypeError）
      await tester.tap(find.widgetWithText(FilledButton, '分配库存'));
      await settle(tester);
      await tester.enterText(
          find.widgetWithText(TextField, '商品ID（数字）'), '9');
      await tester.enterText(find.widgetWithText(TextField, '数量'), '2.5');
      await tester.tap(find.widgetWithText(ElevatedButton, '确定'));
      await settle(tester);
      expect(posts, hasLength(1));
      expect(posts.first['items'], [
        {'product_id': 9, 'quantity': 2.5},
      ]);

      // SnackBar 计时器到期，避免 pending timer 报错
      await drainSnackBars(tester);
    });
  });
}
