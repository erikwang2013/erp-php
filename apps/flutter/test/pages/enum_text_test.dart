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
}
