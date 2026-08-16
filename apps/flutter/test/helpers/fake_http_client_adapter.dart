// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 测试专用 HTTP 适配器：拦截 Dio 的所有请求，按路径返回预设响应，
// 使测试完全离线（不依赖后端、不发起真实网络请求）。
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// 可注入 Dio 的假适配器，用于在单元测试中替代真实网络层。
///
/// 用法：
/// ```dart
/// final adapter = FakeHttpClientAdapter(
///   routes: {
///     '/api/user': (options) async =>
///         FakeHttpClientAdapter.jsonResponse({'code': 0, 'data': {}}),
///   },
/// );
/// dio.httpClientAdapter = adapter;
/// ```
class FakeHttpClientAdapter implements HttpClientAdapter {
  /// 所有已发出请求的 [RequestOptions]，便于断言请求头/请求体。
  final List<RequestOptions> requests = [];

  /// 路径 → 响应构造器；未命中时回退到 [fallback] 或默认成功响应。
  final Map<String, Future<ResponseBody> Function(RequestOptions options)> routes;

  /// 未命中 [routes] 时的兜底响应构造器（可空，测试中可后置赋值）。
  Future<ResponseBody> Function(RequestOptions options)? fallback;

  FakeHttpClientAdapter({Map<String, Future<ResponseBody> Function(RequestOptions)>? routes, this.fallback})
      : routes = routes ?? {};

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final handler = routes[options.path];
    if (handler != null) return handler(options);
    if (fallback != null) return fallback!(options);
    // 默认返回业务成功（code=0），大多数测试无需显式配置。
    return jsonResponse({'code': 0, 'data': <String, dynamic>{}});
  }

  @override
  void close({bool force = false}) {}

  /// 构造一个 JSON 响应体（Content-Type: application/json）。
  static ResponseBody jsonResponse(Object data, {int statusCode = 200}) {
    return ResponseBody.fromString(
      jsonEncode(data),
      statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  /// 构造一个二进制响应体（用于导出文件等 bytes 响应）。
  static ResponseBody bytesResponse(Uint8List bytes, {int statusCode = 200}) {
    return ResponseBody.fromBytes(
      bytes,
      statusCode,
      headers: {
        Headers.contentTypeHeader: ['application/octet-stream'],
      },
    );
  }
}
