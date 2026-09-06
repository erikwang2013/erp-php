// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 角色管理页 Widget 测试：mock /admin/role 与 /admin/permission，
// 验证角色卡片渲染、新增角色弹窗（权限树）与编辑预选/保存回归
// （历史 bug：仅比顶层权限 → 编辑保存静默清空）。
import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/role/role_controller.dart';
import 'package:admin_app/app/pages/system/role/role_list_page.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

/// 三级权限树:top1(域) → mid1(目录) → leaf1/leaf2(叶子按钮/API)。
List<Map<String, dynamic>> permissionTree() => [
      {
        'id': 'top1',
        'name': '销售管理',
        'slug': 'sales',
        'type': 1,
        'children': [
          {
            'id': 'mid1',
            'name': '订单管理',
            'slug': 'sales:order',
            'type': 1,
            'children': [
              {'id': 'leaf1', 'name': '订单查看', 'slug': 'sales:order:view', 'type': 3},
              {'id': 'leaf2', 'name': '订单导出', 'slug': 'sales:order:export', 'type': 3},
            ],
          },
        ],
      },
    ];

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
    adapter = FakeHttpClientAdapter(routes: {
      '/admin/v1/role': (o) async => FakeHttpClientAdapter.jsonResponse({
        'code': 0,
        'data': {
          'list': [
            {'id': 1, 'name': '超级管理员', 'slug': 'super', 'users_count': 1, 'status': 1, 'description': '全部权限'},
            {
              'id': 'r2',
              'name': '只读用户',
              'slug': 'readonly',
              'users_count': 0,
              'status': 0,
              'description': '',
              // 后端按授权逐条下发 hashid id 数组（349d50e 契约）;预选须递归标记到树深处
              'permissions': ['leaf1', 'leaf2'],
            },
          ],
          'total': 2,
        },
      }),
      '/admin/v1/permission': (o) async =>
          FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': permissionTree()}),
    });
    ApiService.instance.dio.httpClientAdapter = adapter;
    // 会话缓存模块级残留:每测冷启动,权限响应态测试可预测首拉必发请求
    RoleController.clearPermissionCache();
  });

  Future<void> pumpRoleList(WidgetTester tester) async {
    // GetMaterialApp 注册 Get.key 导航，使保存成功后的 Get.snackbar 可正常弹出
    await tester.pumpWidget(const GetMaterialApp(home: Scaffold(body: RoleListPage())));
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

  group('RoleListPage — 渲染', () {
    testWidgets('渲染标题与角色卡片', (tester) async {
      await pumpRoleList(tester);

      expect(find.text('角色管理'), findsOneWidget);
      expect(find.text('超级管理员'), findsOneWidget);
      expect(find.text('标识: super | 用户数: 1 | 全部权限'), findsOneWidget);
      expect(find.text('启用'), findsOneWidget);
      expect(find.text('禁用'), findsOneWidget);
    });

    testWidgets('渲染新增角色按钮', (tester) async {
      await pumpRoleList(tester);

      expect(find.text('新增角色'), findsOneWidget);
    });
  });

  group('RoleListPage — 新增角色弹窗', () {
    testWidgets('点击新增角色展示权限树', (tester) async {
      await pumpRoleList(tester);

      await tester.tap(find.text('新增角色'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('权限分配:'), findsOneWidget);
      expect(find.text('名称 *'), findsOneWidget); // FormDialog 必填带 * 后缀
      expect(find.text('标识 *'), findsOneWidget);
      expect(find.text('状态'), findsOneWidget);
      // 权限树默认仅展开第一级（P1-f 惰性默认）:顶层+直接子可见,孙级剪枝
      expect(find.text('销售管理'), findsOneWidget);
      expect(find.text('订单管理'), findsOneWidget);
      expect(find.text('订单查看'), findsNothing);
      expect(find.text('sales:order:view'), findsNothing);
      // 展开订单管理目录 → 孙级行出现
      await tester.tap(find.descendant(
        of: find.byKey(const ValueKey('mid1')),
        matching: find.byIcon(Icons.chevron_right),
      ));
      await tester.pump();
      expect(find.text('订单查看'), findsOneWidget);
      expect(find.text('sales:order:view'), findsOneWidget);
      // 无预选:可见行 checkbox 均未勾且无 indeterminate
      for (final id in ['top1', 'mid1', 'leaf1']) {
        final box = tester.widget<Checkbox>(find.descendant(
          of: find.byKey(ValueKey(id)),
          matching: find.byType(Checkbox),
        ));
        expect(box.value, isFalse, reason: '$id 应未勾选');
      }

      await tester.tap(find.text('取消'));
      await tester.pump();
    });
  });

  group('RoleListPage — 权限区响应态(P2)', () {
    testWidgets('权限晚到:弹框先 loading,数据到达 Obx 自动出树(非假空态)', (tester) async {
      final gate = Completer<void>();
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/role': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {
                'id': 1,
                'name': '超级管理员',
                'slug': 'super',
                'users_count': 1,
                'status': 1,
                'description': '全部权限'
              },
            ],
            'total': 1,
          },
        }),
        '/admin/v1/permission': (o) async {
          await gate.future; // 权限响应晚于弹框打开
          return FakeHttpClientAdapter.jsonResponse(
              {'code': 0, 'data': permissionTree()});
        },
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      await pumpRoleList(tester);

      await tester.tap(find.text('新增角色'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // 加载中:树区菊花,而非直读快照的假空态
      expect(
        find.descendant(
          of: find.byType(AlertDialog),
          matching: find.byType(CircularProgressIndicator),
        ),
        findsOneWidget,
      );
      expect(find.text('暂无数据'), findsNothing);
      expect(find.text('销售管理'), findsNothing);

      // 数据到达 → Obx 自动刷新出树(无需手动重试/关开弹框)
      gate.complete();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.text('销售管理'), findsOneWidget);
      expect(find.text('订单管理'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
    });

    testWidgets('权限加载失败:错误+重试钮;点重试(force)成功出树', (tester) async {
      var permCalls = 0;
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/role': (o) async => FakeHttpClientAdapter.jsonResponse({
          'code': 0,
          'data': {
            'list': [
              {
                'id': 1,
                'name': '超级管理员',
                'slug': 'super',
                'users_count': 1,
                'status': 1,
                'description': '全部权限'
              },
            ],
            'total': 1,
          },
        }),
        '/admin/v1/permission': (o) async {
          permCalls++;
          if (permCalls == 1) {
            // 首次(页面 onInit 预取)失败
            throw DioException(
              requestOptions: RequestOptions(path: '/admin/v1/permission'),
            );
          }
          return FakeHttpClientAdapter.jsonResponse(
              {'code': 0, 'data': permissionTree()});
        },
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      await pumpRoleList(tester);

      await tester.tap(find.text('新增角色'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(permCalls, 1);
      expect(
        find.descendant(
          of: find.byType(AlertDialog),
          matching: find.text('加载失败'),
        ),
        findsOneWidget,
        reason: '弹框权限区应显示失败态',
      );
      expect(find.text('重试'), findsOneWidget);
      expect(find.text('暂无数据'), findsNothing);

      // 点重试 → force 重拉成功 → 树出现
      await tester.tap(find.text('重试'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(permCalls, 2);
      expect(find.text('销售管理'), findsOneWidget);

      await tester.tap(find.text('取消'));
      await tester.pump();
      await settleSnackbars(tester); // 首拉失败 snackbar 收尾
    });
  });

  group('RoleListPage — 编辑预选与保存(静默清空回归)', () {
    Future<void> openEditDialog(WidgetTester tester) async {
      await pumpRoleList(tester);
      // 行内编辑按钮第 2 个(只读用户,带已授叶子权限)
      await tester.tap(find.byIcon(Icons.edit).at(1));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
    }

    testWidgets('叶子授权递归预选:深叶子勾选、目录 indeterminate', (tester) async {
      await openEditDialog(tester);

      expect(find.text('编辑角色'), findsWidgets);
      // 默认一级展开:折叠目录内已授叶子不可见,但目录行呈 indeterminate
      // （后代判定走预计算 descendant 集,与行可见性无关）
      expect(find.byKey(const ValueKey('leaf1')), findsNothing,
          reason: '孙级默认折叠剪枝不可见');
      for (final id in ['mid1', 'top1']) {
        final box = tester.widget<Checkbox>(find.descendant(
          of: find.byKey(ValueKey(id)),
          matching: find.byType(Checkbox),
        ));
        expect(box.value, isNull, reason: '$id 应呈 indeterminate');
      }

      // 展开订单管理目录 → 已授叶子行出现
      await tester.tap(find.descendant(
        of: find.byKey(const ValueKey('mid1')),
        matching: find.byIcon(Icons.chevron_right),
      ));
      await tester.pump();
      // 已授叶子 leaf1/leaf2 勾选
      for (final id in ['leaf1', 'leaf2']) {
        final box = tester.widget<Checkbox>(find.descendant(
          of: find.byKey(ValueKey(id)),
          matching: find.byType(Checkbox),
        ));
        expect(box.value, isTrue, reason: '$id 已授权应勾选');
      }
    });

    testWidgets('不改动直接保存:授权集合原样提交,不再静默清空', (tester) async {
      await openEditDialog(tester);

      final before = adapter.requests.length;
      await tester.tap(find.text('保存'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      final putReqs = adapter.requests
          .skip(before)
          .where((r) => r.method == 'PUT' && r.path == '/admin/v1/role/r2')
          .toList();
      expect(putReqs, hasLength(1));
      final body = putReqs.single.data as Map<String, dynamic>;
      final ids = (body['permission_ids'] as List).cast<String>();
      expect(ids, containsAll(['leaf1', 'leaf2']),
          reason: '预选的叶子权限必须随保存原样提交(回归:旧实现传空清空角色授权)');
      expect(body['status'], 0, reason: '编辑角色状态应随表单提交');

      await settleSnackbars(tester);
    });

    testWidgets('点击 indeterminate 目录行 = 全勾(父+后代入集合)', (tester) async {
      await openEditDialog(tester);

      // 点目录行文本(行 tap 区域为整行)
      await tester.tap(find.text('订单管理'));
      await tester.pump();

      // 勾选后再点一次父行 = 全清
      final topBox = tester.widget<Checkbox>(find.descendant(
        of: find.byKey(const ValueKey('mid1')),
        matching: find.byType(Checkbox),
      ));
      expect(topBox.value, isTrue, reason: '点击 indeterminate 父后应全勾');
      await tester.tap(find.text('订单管理'));
      await tester.pump();
      final cleared = tester.widget<Checkbox>(find.descendant(
        of: find.byKey(const ValueKey('mid1')),
        matching: find.byType(Checkbox),
      ));
      expect(cleared.value, isFalse, reason: '已勾父再点应全清');
    });
  });
}
