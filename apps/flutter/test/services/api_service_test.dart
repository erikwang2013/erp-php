// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// ApiService 拦截器行为测试：
//   1. 请求自动附带 Authorization 头
//   2. 401 自动触发 tryRefresh（刷新成功保留会话）
//   3. 刷新失败清除本地 token
// 全部通过注入 FakeHttpClientAdapter 完成，离线运行。
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/services/api_service.dart';
import 'package:admin_app/app/services/auth_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUp(() async {
    Get.testMode = true; // 纯 Dart 测试环境禁用 Get 的 contextless 导航检查
    SharedPreferences.setMockInitialValues({});
    await AuthService.clearToken();
    adapter = FakeHttpClientAdapter();
    ApiService.instance.dio.httpClientAdapter = adapter;
  });

  group('ApiService — 请求头注入', () {
    test('已登录时请求自动携带 Bearer Token', () async {
      await AuthService.saveLogin(
        token: 'abc123',
        refreshToken: 'refresh-abc',
        username: 'admin',
      );

      // 清掉缓存路径，确保走 prefs 读取
      await ApiService.instance.get('/api/user');

      expect(adapter.requests, isNotEmpty);
      final options = adapter.requests.first;
      expect(options.headers['Authorization'], 'Bearer abc123');
      expect(options.path, '/api/user');
    });

    test('未登录时请求不带 Authorization 头', () async {
      await ApiService.instance.get('/api/public');

      final options = adapter.requests.first;
      expect(options.headers.containsKey('Authorization'), isFalse);
    });

    test('get 成功返回业务响应体', () async {
      adapter.routes['/api/user'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {'id': 1, 'username': 'admin'},
          });

      final resp = await ApiService.instance.get('/api/user');
      expect(resp['code'], 0);
      expect((resp['data'] as Map)['username'], 'admin');
    });

    test('业务 code != 0 时抛出 ApiException', () async {
      adapter.routes['/api/user'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 4001,
            'message': '参数错误',
          });

      expect(
        () => ApiService.instance.get('/api/user'),
        throwsA(isA<ApiException>()
            .having((e) => e.code, 'code', 4001)
            .having((e) => e.message, 'message', '参数错误')),
      );
    });
  });

  group('ApiService — 401 自动刷新', () {
    test('401 时调用 refresh 接口并使用新 token 更新会话', () async {
      // 预置旧会话
      await AuthService.saveLogin(
        token: 'old-token',
        refreshToken: 'refresh-token',
        username: 'admin',
      );

      // refresh 接口返回成功 + 新 token；其余路径一律 401
      adapter.routes['/api/auth/refresh'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'access_token': 'new-token',
              'refresh_token': 'new-refresh',
              'user': {'username': 'admin'},
            },
          });
      adapter.fallback = (options) async => FakeHttpClientAdapter.jsonResponse(
            {'code': 401, 'message': 'unauthorized'},
            statusCode: 401,
          );

      // 访问受保护接口 → 401 → 拦截器自动刷新
      await expectLater(
        ApiService.instance.get('/api/user'),
        throwsA(isA<DioException>()),
      );

      // 会话应已用新 token 更新
      expect(await AuthService.getToken(), 'new-token');
      expect(await AuthService.getRefreshToken(), 'new-refresh');
      // 刷新请求的请求体应携带 refresh_token
      final refreshReq = adapter.requests
          .where((r) => r.path == '/api/auth/refresh')
          .toList();
      expect(refreshReq, hasLength(1));
      final data = refreshReq.first.data as Map<String, dynamic>;
      expect(data['refresh_token'], 'refresh-token');
    });

    test('401 且刷新失败时清除本地 token', () async {
      await AuthService.saveLogin(
        token: 'old-token',
        refreshToken: 'refresh-token',
        username: 'admin',
      );

      // refresh 接口返回业务失败（HTTP 200 + code != 0），其余 401
      adapter.routes['/api/auth/refresh'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 4002,
            'message': 'refresh token 已失效',
          });
      adapter.fallback = (options) async => FakeHttpClientAdapter.jsonResponse(
            {'code': 401, 'message': 'unauthorized'},
            statusCode: 401,
          );

      await expectLater(
        ApiService.instance.get('/api/user'),
        throwsA(isA<DioException>()),
      );

      // token 应被清除，回到未登录状态
      expect(await AuthService.getToken(), isNull);
      expect(await AuthService.isLoggedIn(), isFalse);
    });

    test('无 refresh token 时 tryRefresh 直接返回 false 且不发请求', () async {
      // 只保存 access token，无 refresh token
      SharedPreferences.setMockInitialValues({'access_token': 'only-access'});
      await AuthService.clearToken(); // 清缓存
      SharedPreferences.setMockInitialValues({'access_token': 'only-access'});

      final result = await ApiService.instance.tryRefresh();
      expect(result, isFalse);
      // 不应发出任何 refresh 请求
      expect(adapter.requests, isEmpty);
    });

    test('refresh 接口网络异常时 tryRefresh 返回 false', () async {
      await AuthService.saveLogin(
        token: 't',
        refreshToken: 'r',
        username: 'u',
      );
      adapter.fallback = (options) async => throw DioException(
            requestOptions: options,
            type: DioExceptionType.connectionError,
          );

      final result = await ApiService.instance.tryRefresh();
      expect(result, isFalse);
    });
  });
}
