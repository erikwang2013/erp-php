// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 系统配置页 Widget 测试：mock /admin/config，验证配置项渲染与新增配置弹窗。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/config/config_page.dart';
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
      '/admin/v1/config': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'group': 'site', 'key': 'name', 'value': 'ERP 管理系统', 'type': 'string', 'description': '站点名称'},
            {'id': 2, 'group': 'site', 'key': 'page_size', 'value': '20', 'type': 'int', 'description': '分页大小'},
          ],
          'total': 2,
        },
      }),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  Future<void> pumpConfig(WidgetTester tester) async {
    // GetMaterialApp 注册 Get.key 导航，使保存成功后的 Get.snackbar 可正常弹出
    await tester.pumpWidget(const GetMaterialApp(home: Scaffold(body: ConfigPage())));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  /// 关闭 Get.snackbar 并等动画与自动关闭计时器走完，避免测试结束时残留 Timer/动画。
  Future<void> settleSnackbars(WidgetTester tester) async {
    Get.closeAllSnackbars();
    await tester.pump();
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
  }

  /// 弹框内的第 [i] 个文本输入框。
  Finder dialogField(int i) => find
      .descendant(of: find.byType(AlertDialog), matching: find.byType(TextField))
      .at(i);

  group('ConfigPage — 渲染', () {
    testWidgets('渲染标题与配置项', (tester) async {
      await pumpConfig(tester);

      expect(find.text('系统配置'), findsOneWidget);
      expect(find.text('site.name'), findsOneWidget);
      expect(find.text('站点名称'), findsOneWidget);
      expect(find.text('ERP 管理系统'), findsOneWidget);
      expect(find.text('site.page_size'), findsOneWidget);
    });

    testWidgets('点击新增配置弹出表单(type 下拉含 5 枚举)', (tester) async {
      await pumpConfig(tester);

      await tester.tap(find.text('新增配置'));
      await tester.pump();

      expect(find.text('新增配置'), findsNWidgets(2)); // 标题 + 弹窗标题
      expect(find.text('分组 *'), findsOneWidget); // FormDialog 必填带 * 后缀
      expect(find.text('键 *'), findsOneWidget);
      expect(find.text('值'), findsOneWidget);
      expect(find.text('说明'), findsOneWidget);

      // type 枚举下拉:string|int|bool|json|array(erp_system_config.type 注释集)。
      // 页内列表 Chip 与弹框下拉均含 'string',取弹框内(后一个)
      await tester.tap(find.text('string').last);
      await tester.pumpAndSettle();
      // 菜单项与页内 type Chip 文案共存,用 findsWidgets 断言出现
      for (final t in ['int', 'bool', 'json', 'array']) {
        expect(find.text(t), findsWidgets, reason: '下拉菜单应含 $t');
      }
      // 选中 int 关闭菜单,再取消关闭弹框
      await tester.tap(find.text('int').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('取消'));
      await tester.pump();
      await tester.pumpAndSettle();
    });

    testWidgets('新增提交:POST body 完整(id 为 null)', (tester) async {
      await pumpConfig(tester);

      await tester.tap(find.text('新增配置'));
      await tester.pump();
      await tester.enterText(dialogField(0), 'site');
      await tester.enterText(dialogField(1), 'logo');
      await tester.enterText(dialogField(2), 'logo.png');
      await tester.tap(find.text('保存'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final req = adapter.requests
          .where((r) => r.method == 'POST' && r.path == '/admin/v1/config')
          .toList();
      expect(req, hasLength(1));
      final body = req.single.data as Map<String, dynamic>;
      expect(body['group'], 'site');
      expect(body['key'], 'logo');
      expect(body['value'], 'logo.png');
      expect(body['type'], 'string');
      expect(body['id'], isNull);

      await settleSnackbars(tester);
    });

    testWidgets('编辑弹框:group/key 只读、type 预填现有值', (tester) async {
      await pumpConfig(tester);

      await tester.tap(find.byIcon(Icons.edit).first); // site.name(string)
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('编辑配置'), findsOneWidget);
      expect(tester.widget<TextField>(dialogField(0)).enabled, isFalse,
          reason: '编辑时 group 禁止修改(uk_group_key)');
      expect(tester.widget<TextField>(dialogField(1)).enabled, isFalse,
          reason: '编辑时 key 禁止修改');
      expect(find.text('string'), findsWidgets); // 页内 Chip + 类型下拉预填

      await tester.tap(find.text('取消'));
      await tester.pump();
    });
  });
}
