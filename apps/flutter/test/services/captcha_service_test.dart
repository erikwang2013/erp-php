// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// CaptchaService 单元测试：验证码生成与校验的请求构造与响应解析。
// 使用 FakeHttpClientAdapter 完全离线。
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/services/captcha_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  late Dio dio;
  late FakeHttpClientAdapter adapter;
  late CaptchaService service;

  setUp(() {
    adapter = FakeHttpClientAdapter();
    dio = Dio()..httpClientAdapter = adapter;
    service = CaptchaService(dio);
  });

  group('CaptchaService — 生成验证码', () {
    test('generate 成功解析 CaptchaData（含目标点击文字）', () async {
      adapter.routes['/api/captcha/generate'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {
              'key': 'captcha-key-1',
              'image': 'data:image/png;base64,iVBORw0KGgo=',
              'extra': {
                'targets': [
                  {'order': 1, 'text': '爱', 'x': 12, 'y': 34},
                  {'order': 2, 'text': '国', 'x': 56, 'y': 78},
                ],
              },
            },
          });

      final data = await service.generate(difficulty: 'medium');

      expect(data.key, 'captcha-key-1');
      expect(data.imageBase64, startsWith('data:image/png'));
      expect(data.targets, hasLength(2));
      expect(data.targets[0].order, 1);
      expect(data.targets[0].text, '爱');
      expect(data.targets[0].x, 12);
      expect(data.targets[0].y, 34);

      // 请求体应携带难度参数
      final req = adapter.requests.single;
      expect(req.path, '/api/captcha/generate');
      expect((req.data as Map)['difficulty'], 'medium');
    });

    test('generate 业务失败时抛出异常', () async {
      adapter.routes['/api/captcha/generate'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 5001,
            'message': '验证码服务不可用',
          });

      expect(
        () => service.generate(),
        throwsA(isA<Exception>()),
      );
    });

    test('targets 缺失时解析为空列表（容错）', () async {
      adapter.routes['/api/captcha/generate'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {'key': 'k', 'image': 'img'},
          });

      final data = await service.generate();
      expect(data.targets, isEmpty);
    });
  });

  group('CaptchaService — 校验验证码', () {
    test('verify 命中正确时返回 true', () async {
      adapter.routes['/api/captcha/verify'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {'valid': true},
          });

      final ok = await service.verify('captcha-key-1', const []);
      expect(ok, isTrue);

      final req = adapter.requests.single;
      expect((req.data as Map)['key'], 'captcha-key-1');
    });

    test('verify 不通过时返回 false', () async {
      adapter.routes['/api/captcha/verify'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {'valid': false},
          });

      final ok = await service.verify('captcha-key-1', const []);
      expect(ok, isFalse);
    });

    test('verify 请求体包含点击坐标（取整）', () async {
      adapter.routes['/api/captcha/verify'] = (options) async =>
          FakeHttpClientAdapter.jsonResponse({
            'code': 0,
            'data': {'valid': true},
          });

      await service.verify(
        'k',
        const [
          Offset(10.4, 20.6),
          Offset(99.5, 3.2),
        ],
      );

      final data = adapter.requests.single.data as Map;
      final clicks = data['clicks'] as List;
      expect(clicks[0], {'x': 10, 'y': 21});
      expect(clicks[1], {'x': 100, 'y': 3});
    });
  });
}
