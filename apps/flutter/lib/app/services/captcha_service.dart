// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:ui';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'api_service.dart';

class CaptchaService {
  final Dio _dio;

  CaptchaService(this._dio);

  Future<CaptchaData> generate({String difficulty = 'medium'}) async {
    final resp = await _dio.post('/api/v1/captcha/generate', data: {
      'difficulty': difficulty,
    });
    final body = resp.data;
    // 与 ApiService 同款错误形状：body 非 Map（网关 HTML 等）或业务 code!=0
    // 统一抛 ApiException（含服务端 message），裸 Exception 会被翻译层漏掉。
    if (body is! Map || body['code'] != 0) {
      throw ApiException(
        body is Map && body['code'] is int ? body['code'] as int : -1,
        body is Map && body['message'] is String
            ? body['message'] as String
            : ApiService.friendlyError(DioException(
                requestOptions: resp.requestOptions,
                type: DioExceptionType.badResponse,
              )),
      );
    }
    return CaptchaData.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// 校验失败/异常一律返回 false 并留日志（弹框失败路径文案由调用方统一处理）。
  Future<bool> verify(String key, List<Offset> clicks) async {
    try {
      final resp = await _dio.post('/api/v1/captcha/verify', data: {
        'key': key,
        'clicks': clicks.map((c) => {'x': c.dx.round(), 'y': c.dy.round()}).toList(),
      });
      final body = resp.data;
      if (body is! Map || body['code'] != 0) {
        debugPrint('[captcha] verify 业务失败: ${body is Map ? body['message'] : body}');
        return false;
      }
      return body['data']?['valid'] == true;
    } catch (e) {
      debugPrint('[captcha] verify 请求异常: $e');
      return false;
    }
  }
}

class CaptchaData {
  final String key;
  final String imageBase64;
  final List<CaptchaTarget> targets;

  CaptchaData({required this.key, required this.imageBase64, required this.targets});

  factory CaptchaData.fromJson(Map<String, dynamic> json) {
    return CaptchaData(
      key: json['key'] as String,
      imageBase64: json['image'] as String,
      targets: (json['extra']?['targets'] as List?)
          ?.map((t) => CaptchaTarget.fromJson(t))
          .toList() ?? [],
    );
  }
}

class CaptchaTarget {
  final int order;
  final String text;
  // 服务端不下发坐标（属校验秘密），仅 order+text 用于客户端提示
  final int? x;
  final int? y;

  CaptchaTarget({required this.order, required this.text, this.x, this.y});

  factory CaptchaTarget.fromJson(Map<String, dynamic> json) {
    return CaptchaTarget(
      order: json['order'] as int,
      text: json['text'] as String,
      x: json['x'] as int?,
      y: json['y'] as int?,
    );
  }
}

