/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response;
import 'auth_service.dart';
import '../l10n/app_l10n.dart';

class ApiService {
  static final ApiService _instance = ApiService._();
  factory ApiService() => _instance;

  /// Shared singleton instance (alternative to the factory constructor).
  static ApiService get instance => _instance;

  late final Dio dio;
  static const String baseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://erp.test');

  ApiService._() {
    dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {
        'Content-Type': 'application/json',
      },
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await AuthService.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      // 后端 HTTP 状态码恒 200、业务码在 body.code（app/middleware/AdminAuth.php:34 不设状态码），
      // token 过期不会走 onError：在信封层把 code==401 翻译成 DioException 交下面的 onError 统一处理。
      // reject 的第二个参数必须为 true（dio rejectCallFollowing），否则本拦截器的 onError 不会执行。
      onResponse: (response, handler) {
        final body = response.data;
        if (body is Map &&
            body['code'] == 401 &&
            !_isCredentialEndpoint(response.requestOptions.path)) {
          handler.reject(
            DioException(
              requestOptions: response.requestOptions,
              response: response,
              type: DioExceptionType.badResponse,
            ),
            true,
          );
          return;
        }
        handler.next(response);
      },
      onError: (error, handler) async {
        final resp = error.response;
        final respBody = resp?.data;
        // HTTP 401（网关/带状态码的端点）或信封层 code==401（本项目常态）均视为凭据失效
        if (resp?.statusCode == 401 || (respBody is Map && respBody['code'] == 401)) {
          final path = error.requestOptions.path;
          final isCredentialEndpoint = _isCredentialEndpoint(path);
          final isReplay = error.requestOptions.extra['replayed'] == true;
          if (isReplay) {
            // 刷新成功后重放的请求仍 401 → token 彻底失效：登出，防重放死循环。
            await AuthService.clearToken();
            Future.microtask(() => Get.offAllNamed('/login'));
          } else {
            final refreshed = isCredentialEndpoint ? false : await tryRefresh();
            if (refreshed) {
              // 刷新成功：重放原请求（请求体/查询参数原样，onRequest 会自动附带新 token）。
              // 重放请求经 extra['replayed'] 标记，若仍 401，内层 onError 已自行登出并抛错，
              // 这里统一转交 handler.next，让原请求以对应异常收尾（不得让异常逃出 onError，
              // 否则原请求 Future 永不 settle）。
              final options = error.requestOptions;
              options.extra['replayed'] = true;
              try {
                handler.resolve(await dio.fetch(options));
              } catch (e) {
                handler.next(e is DioException ? e : error);
              }
              return;
            }
            await AuthService.clearToken();
            Future.microtask(() => Get.offAllNamed('/login'));
          }
        }
        handler.next(error);
      },
    ));
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? params}) async {
    try {
      final resp = await dio.get(path, queryParameters: params);
      return _handleResponse(resp);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode ?? -1, friendlyError(e));
    }
  }

  Future<Map<String, dynamic>> post(String path, {dynamic data}) async {
    try {
      final resp = await dio.post(path, data: data);
      return _handleResponse(resp);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode ?? -1, friendlyError(e));
    }
  }

  Future<Map<String, dynamic>> put(String path, {dynamic data}) async {
    try {
      final resp = await dio.put(path, data: data);
      return _handleResponse(resp);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode ?? -1, friendlyError(e));
    }
  }

  Future<Map<String, dynamic>> delete(String path, {dynamic data}) async {
    try {
      final resp = await dio.delete(path, data: data);
      return _handleResponse(resp);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode ?? -1, friendlyError(e));
    }
  }

  /// 将异常映射为当前语言（AppL10n.current）下的用户可读消息。
  /// 与后端 app/common/I18n.php 的 key 风格对齐：api.* / common.*。
  static String friendlyError(Object e) {
    final l10n = AppL10n.current;
    if (e is ApiException) return e.message; // 业务错误已在抛出处翻译
    if (e is DioException) {
      switch (e.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return l10n.apiTimeoutError;
        case DioExceptionType.connectionError:
          return l10n.apiNetworkError;
        case DioExceptionType.badResponse:
          if (e.response?.statusCode == 401) return l10n.apiUnauthorized;
          return l10n.commonRequestFailed;
        default:
          return l10n.commonRequestFailed;
      }
    }
    return l10n.commonRequestFailed;
  }

  Map<String, dynamic> _handleResponse(Response resp) {
    // body 可能是网关 HTML/数组等非 Map：此处容错并统一抛 ApiException，
    // 避免裸 TypeError 逃出 get/post 的 `on DioException` 捕获范围。
    final body = resp.data;
    if (body is! Map<String, dynamic>) {
      throw ApiException(-1, AppL10n.current.commonRequestFailed);
    }
    final code = body['code'];
    if (code != 0) {
      // 统一错误提示走 i18n（key 与后端 app/common/I18n.php 的 common.* 风格对齐）
      throw ApiException(code is int ? code : -1,
          body['message'] is String ? body['message'] as String : AppL10n.current.commonRequestFailed);
    }
    return body;
  }

  /// 凭据类端点：refresh 自身的 401（token 彻底失效）不得再触发 tryRefresh（否则递归）；
  /// 登录/验证码等预授权端点 401 是凭据错误而非过期，也不触发刷新。
  /// onResponse 的 401 翻译同样跳过这些端点，保留其原有业务异常提示（如登录密码错误）。
  static bool _isCredentialEndpoint(String path) =>
      path.endsWith('/auth/refresh') ||
      path.contains('/auth/login') ||
      path.contains('/captcha/');

  Future<bool>? _refreshFuture;

  /// 单飞刷新：N 个并发请求同时 401 时共享同一次刷新，后续调用直接复用
  /// 进行中的 Future。若不合并，服务端在第一次刷新后即轮换 refresh_token，
  /// 其余并发刷新全部失败 → 各失败路径 clearToken() 把刚写入的新 token
  /// 又清掉并跳登录，已登录用户被随机登出。
  Future<bool> tryRefresh() =>
      _refreshFuture ??= _doRefresh().whenComplete(() => _refreshFuture = null);

  Future<bool> _doRefresh() async {
    final refreshToken = await AuthService.getRefreshToken();
    if (refreshToken == null) return false;
    try {
      final resp = await dio.post('/api/v1/auth/refresh', data: {'refresh_token': refreshToken});
      final body = resp.data;
      final data = body is Map ? body['data'] : null;
      if (body is Map && body['code'] == 0 && data is Map) {
        await AuthService.saveLogin(
          token: data['access_token'],
          refreshToken: data['refresh_token'],
          // refresh 的 data 只有 token（AuthController.php:352-359 无 user）：
          // 缺省时保留已缓存用户名，避免顶栏用户名被空串覆盖
          username: data['user']?['username'] ?? await AuthService.getUsername() ?? '',
        );
        return true;
      }
    } catch (e) {
      debugPrint('[api] refresh 失败: $e');
    }
    return false;
  }
}

class ApiException implements Exception {
  final int code;
  final String message;
  ApiException(this.code, this.message);

  @override
  String toString() => 'ApiException($code): $message';
}
