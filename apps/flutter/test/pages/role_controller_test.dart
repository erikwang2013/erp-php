// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// RoleController 单元测试：权限树会话缓存（P2）——首次拉取写缓存、二次
// loadPermissions 命中零请求、force 重拉、失败/空响应不写缓存、失败态位。
// 注意：dio 请求链在 fake async 下由 pump 驱动推进，故控制器调用不 await，
// 统一「裸调 + pump 固定时长 + 断言副作用」（与页面测试同一惯例）。
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/system/role/role_controller.dart';
import 'package:admin_app/app/services/api_service.dart';

import '../helpers/fake_http_client_adapter.dart';

/// 权限树样例（缓存对内容透明，仅需非空）。
List<dynamic> permTree() => [
      {
        'id': 't1',
        'name': '销售',
        'children': [
          {
            'id': 'm1',
            'name': '订单',
            'children': [
              {'id': 'l1', 'name': '查看'},
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
    // 模块级缓存跨测试残留，每测从冷缓存起步保证确定性
    RoleController.clearPermissionCache();
  });

  Future<void> pumpApp(WidgetTester tester) async {
    // GetMaterialApp 提供 Get.key overlay（失败路径 Get.snackbar 需要）
    await tester.pumpWidget(
      const GetMaterialApp(home: Scaffold(body: SizedBox())),
    );
  }

  /// 推进一次请求往返（dio 微任务 + 内部短 timer）。
  Future<void> settleRequest(WidgetTester tester) async {
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  int permGets() => adapter.requests
      .where((r) => r.method == 'GET' && r.path == '/admin/v1/permission')
      .length;

  Future<void> settleSnackbars(WidgetTester tester) async {
    Get.closeAllSnackbars();
    await tester.pump();
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
  }

  group('权限树会话缓存', () {
    testWidgets('首次加载写缓存;二次命中零请求;force 重拉', (tester) async {
      await pumpApp(tester);
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/permission': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': permTree()}),
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      final ctrl = RoleController();

      ctrl.loadPermissions();
      await settleRequest(tester);
      expect(permGets(), 1, reason: '冷启动应发一次请求');
      expect(ctrl.permissions, hasLength(1));
      expect(ctrl.permLoadFailed.value, isFalse);

      ctrl.loadPermissions();
      await settleRequest(tester);
      expect(permGets(), 1, reason: '二次(非 force)应命中会话缓存零请求');
      expect(ctrl.permissions, hasLength(1));

      ctrl.loadPermissions(force: true);
      await settleRequest(tester);
      expect(permGets(), 2, reason: 'force 应重拉');
    });

    testWidgets('失败:置失败态不写缓存;下次非 force 自动重拉成功', (tester) async {
      await pumpApp(tester);
      var fail = true;
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/permission': (o) async {
          if (fail) {
            throw DioException(
              requestOptions: RequestOptions(path: '/admin/v1/permission'),
            );
          }
          return FakeHttpClientAdapter.jsonResponse(
              {'code': 0, 'data': permTree()});
        },
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      final ctrl = RoleController();

      ctrl.loadPermissions();
      await settleRequest(tester);
      expect(ctrl.permLoadFailed.value, isTrue, reason: '失败应置失败态');
      expect(ctrl.permissions, isEmpty);
      expect(permGets(), 1);

      fail = false;
      ctrl.loadPermissions(); // 失败未写缓存 → 自动重拉
      await settleRequest(tester);
      expect(permGets(), 2);
      expect(ctrl.permLoadFailed.value, isFalse);
      expect(ctrl.permissions, hasLength(1));
      await settleSnackbars(tester);
    });

    testWidgets('force 失败不破坏既有缓存;命中路径清除失败态', (tester) async {
      await pumpApp(tester);
      var fail = false;
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/permission': (o) async {
          if (fail) {
            throw DioException(
              requestOptions: RequestOptions(path: '/admin/v1/permission'),
            );
          }
          return FakeHttpClientAdapter.jsonResponse(
              {'code': 0, 'data': permTree()});
        },
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      final ctrl = RoleController();

      ctrl.loadPermissions(); // 首拉写缓存
      await settleRequest(tester);
      expect(permGets(), 1);

      fail = true;
      ctrl.loadPermissions(force: true); // force 失败
      await settleRequest(tester);
      expect(permGets(), 2);
      expect(ctrl.permLoadFailed.value, isTrue);
      expect(ctrl.permissions, hasLength(1), reason: 'force 失败应保留既有缓存数据');

      fail = false;
      ctrl.loadPermissions(); // 命中未被破坏的缓存
      await settleRequest(tester);
      expect(permGets(), 2, reason: '非 force 命中缓存零请求');
      expect(ctrl.permLoadFailed.value, isFalse, reason: '命中即成功态');
      await settleSnackbars(tester);
    });

    testWidgets('空响应成功不写缓存:下次仍会拉取', (tester) async {
      await pumpApp(tester);
      adapter = FakeHttpClientAdapter(routes: {
        '/admin/v1/permission': (o) async =>
            FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': []}),
      });
      ApiService.instance.dio.httpClientAdapter = adapter;
      final ctrl = RoleController();

      ctrl.loadPermissions();
      await settleRequest(tester);
      expect(ctrl.permissions, isEmpty);
      expect(permGets(), 1);

      ctrl.loadPermissions();
      await settleRequest(tester);
      expect(permGets(), 2, reason: '空成功不缓存 → 再次调用应重拉');
    });
  });
}
