// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 枚举列不上屏机读值（中文界面）：状态/结果列必须出中文文案，后端存储值
// （pass/reject、TINYINT 1..6、is_read 0/1）不许原样贴到屏幕上。
// 覆盖三种形态：字符串枚举（质量检验结果）、数字枚举（HR 考勤状态）、
// 以及「读了不存在的列」——通知中心旧实现读 r['status']（erp_notification 无该列），该列恒空。
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/finance/payment_list_page.dart';
import 'package:admin_app/app/pages/finance/receipt_list_page.dart';
import 'package:admin_app/app/pages/hr/attendance_page.dart';
import 'package:admin_app/app/pages/notification/notification_page.dart';
import 'package:admin_app/app/pages/quality/iqc_list_page.dart';
import 'package:admin_app/app/pages/quality/nonconformity_list_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<FakeHttpClientAdapter> installApi(Map<String, Future<ResponseBody> Function(RequestOptions)> routes) async {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    final adapter = FakeHttpClientAdapter(routes: routes);
    ApiService.instance.dio.httpClientAdapter = adapter;
    return adapter;
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

  testWidgets('列表枚举列出中文文案，机读值不上屏', (tester) async {
    await installApi({
      '/admin/v1/quality/iqc': (o) => listOf([
            {
              'id': 'h1',
              'code': 'IQC-1',
              'product_name': '小米 14',
              'inspected_qty': 10,
              'passed_qty': 9,
              'rejected_qty': 1,
              'result': 'pass',
            },
          ]),
      '/admin/v1/hr/attendance': (o) => listOf([
            {'id': 'h2', 'name': '张三', 'date': '2026-09-22', 'status': 6},
          ]),
      '/admin/v1/notification/my': (o) => listOf([
            {'id': 'h3', 'title': '审批提醒', 'content': '有一条待审批', 'is_read': 0},
          ]),
    });

    // 字符串枚举：install.sql `检验结果: pass=合格 reject=不合格`
    await pump(tester, const IqcListPage());
    expect(find.text('合格'), findsOneWidget, reason: 'result=pass 应出「合格」');
    expect(find.text('pass'), findsNothing, reason: '机读串 pass 不许上屏');

    // 数字枚举：install.sql `状态: 1=正常 2=迟到 3=早退 4=缺卡 5=请假 6=出差`
    await pump(tester, const AttendancePage());
    expect(find.text('出差'), findsOneWidget, reason: 'status=6 应出「出差」');
    expect(find.text('6'), findsNothing, reason: '机读值 6 不许上屏');

    // 列名本就取错：erp_notification 只有 is_read（0未读1已读），没有 status 列
    await pump(tester, const NotificationPage());
    expect(find.text('未读'), findsOneWidget, reason: 'is_read=0 应出「未读」');
    expect(find.text('0'), findsNothing, reason: '机读值 0 不许上屏');
  });

  testWidgets('不合格品表单下拉：显示词表文案，提交值仍是存储值', (tester) async {
    final adapter = await installApi({
      '/admin/v1/quality/nonconformity': (o) => listOf([
            {
              'id': 'h1',
              'code': 'NC-1',
              'product_id': 'hP',
              'product_name': '小米 14',
              'defect_type': '划痕',
              'severity': 'critical',
              'disposition': 'accept',
              'status': 0,
            },
          ]),
      '/admin/v1/product': (o) => listOf([
            {'id': 'hP', 'name': '小米 14'},
          ]),
    });

    await pump(tester, const NonconformityListPage());
    await tester.tap(find.byIcon(Icons.edit).first);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    // 词表四端统一（lead 拍板）：critical=致命、accept=让步接收
    // 「致命」在列表行里也有，故限定在弹窗内断言，否则这条会假绿
    expect(find.descendant(of: find.byType(Dialog), matching: find.text('致命')), findsWidgets,
        reason: 'severity=critical 的表单下拉应显示「致命」');
    expect(find.text('让步接收'), findsWidgets, reason: 'disposition=accept 应出「让步接收」');
    expect(find.text('accept'), findsNothing, reason: '机读串 accept 不许上屏');
    expect(find.text('critical'), findsNothing, reason: '机读串 critical 不许上屏');

    await tester.tap(find.text('提交'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    final req = adapter.requests
        .where((r) => r.method == 'PUT' && r.path == '/admin/v1/quality/nonconformity/h1')
        .toList();
    expect(req, hasLength(1));
    final body = req.single.data as Map<String, dynamic>;
    expect(body['disposition'], 'accept', reason: '换的只是显示文案，提交值必须仍是存储值');
    expect(body['severity'], 'critical', reason: '换的只是显示文案，提交值必须仍是存储值');
  });

  testWidgets('收款方式枚举：列表出「其他」，编辑弹窗回存仍是 other', (tester) async {
    final adapter = await installApi({
      '/admin/v1/finance/receipt': (o) => listOf([
            {
              'id': 'h1',
              'code': 'RCV-1',
              'customer_id': 'hC',
              'customer_name': '客户A',
              'amount': '100.00',
              'method': 'other',
              'status': 0,
              'received_at': '2026-09-22T00:00:00Z',
            },
          ]),
      '/admin/v1/customer': (o) => listOf([
            {'id': 'hC', 'name': '客户A'},
          ]),
    });

    await pump(tester, const ReceiptListPage());
    // 词表口径与 Web 词典逐字对齐（domains/finance.ts method）：cash/bank/wechat/alipay/other
    expect(find.text('其他'), findsOneWidget, reason: 'method=other 应出「其他」，不许英文裸串上屏');
    expect(find.text('other'), findsNothing, reason: '机读串 other 不许上屏');

    await tester.tap(find.byIcon(Icons.edit).first);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    // 「其他」列表行里也有，故限定弹窗内断言，否则假绿
    expect(find.descendant(of: find.byType(Dialog), matching: find.text('其他')), findsWidgets,
        reason: '下拉选项缺 other 时 FormDialog 会把预填值置 null（下拉空态）');

    await tester.tap(find.text('提交'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    final req = adapter.requests
        .where((r) => r.method == 'PUT' && r.path == '/admin/v1/finance/receipt/h1')
        .toList();
    expect(req, hasLength(1));
    final body = req.single.data as Map<String, dynamic>;
    expect(body['method'], 'other', reason: '编辑保存不得把 other 静默改回缺省 bank');
  });

  testWidgets('收款方式编辑：wechat 行预填「微信」，提交回传机读串 wechat', (tester) async {
    final adapter = await installApi({
      '/admin/v1/finance/receipt': (o) => listOf([
            {
              'id': 'h5',
              'code': 'RCV-5',
              'customer_id': 'hC',
              'customer_name': '客户A',
              'amount': '300.00',
              'method': 'wechat',
              'status': 0,
              'received_at': '2026-09-22T00:00:00Z',
            },
          ]),
      '/admin/v1/customer': (o) => listOf([
            {'id': 'hC', 'name': '客户A'},
          ]),
    });

    await pump(tester, const ReceiptListPage());
    expect(find.text('微信'), findsOneWidget, reason: '列表列走词表，method=wechat 应出「微信」');
    expect(find.text('wechat'), findsNothing, reason: '机读串 wechat 不许上屏');

    await tester.tap(find.byIcon(Icons.edit).first);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    // 列表行里也有「微信」，故限定弹窗内断言，否则假绿
    expect(find.descendant(of: find.byType(Dialog), matching: find.text('微信')), findsWidgets,
        reason: '下拉须预填「微信」而不是落空（预填值不在 options 时 FormDialog 会置 null）');

    await tester.tap(find.text('提交'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    final req = adapter.requests
        .where((r) => r.method == 'PUT' && r.path == '/admin/v1/finance/receipt/h5')
        .toList();
    expect(req, hasLength(1));
    final body = req.single.data as Map<String, dynamic>;
    expect(body['method'], 'wechat', reason: '换的只是显示文案，提交值必须仍是机读串（不得变成「微信」）');
  });

  testWidgets('付款方式编辑：词表外的历史值前置占位项，回存原样（不被改写成 bank）', (tester) async {
    // method 是自由 VARCHAR（DDL 注释只列 4 值），外部写入/后续新增值可能落在词表外
    final adapter = await installApi({
      '/admin/v1/finance/payment': (o) => listOf([
            {
              'id': 'h2',
              'code': 'PAY-1',
              'supplier_id': 'hS',
              'supplier_name': '供应商A',
              'amount': '200.00',
              'method': 'cheque',
              'status': 0,
              'paid_at': '2026-09-22T00:00:00Z',
            },
          ]),
      '/admin/v1/supplier': (o) => listOf([
            {'id': 'hS', 'name': '供应商A'},
          ]),
    });

    await pump(tester, const PaymentListPage());
    await tester.tap(find.byIcon(Icons.edit).first);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.descendant(of: find.byType(Dialog), matching: find.text('cheque')), findsWidgets,
        reason: '词表外的当前值应前置进选项而不是落空（落空提交即被改成 bank）');

    await tester.tap(find.text('提交'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    final req = adapter.requests
        .where((r) => r.method == 'PUT' && r.path == '/admin/v1/finance/payment/h2')
        .toList();
    expect(req, hasLength(1));
    final body = req.single.data as Map<String, dynamic>;
    expect(body['method'], 'cheque', reason: '词表外的历史值不得被静默改写');
  });
}
