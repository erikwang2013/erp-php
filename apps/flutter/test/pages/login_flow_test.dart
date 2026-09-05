// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 登录流程 Widget 测试：通过自定义 HttpOverrides 拦截 LoginPage 内部
// Dio 的验证码与登录请求（页面自建 Dio，无法注入 adapter），
// 覆盖验证码成功加载与完整登录成功（保存会话 + 跳转）两条链路。
import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/pages/login/login_page.dart';
import 'package:admin_app/app/services/auth_service.dart';

/// 1x1 透明 PNG，作为验证码图片的 base64。
const _kPng1x1 =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/// 按路径返回 JSON 的假 HttpClient（覆盖 dart:io 网络层）。
class FakeHttpOverrides extends HttpOverrides {
  final Map<String, Map<String, dynamic>> routes;

  /// 已请求的路径（按调用顺序），用于断言独立校验先于登录请求。
  final List<String> log = [];

  FakeHttpOverrides(this.routes);

  @override
  HttpClient createHttpClient(SecurityContext? context) =>
      FakeHttpClient(routes, log);
}

class FakeHttpClient implements HttpClient {
  final Map<String, Map<String, dynamic>> routes;
  final List<String> log;
  FakeHttpClient(this.routes, this.log);

  @override
  bool get autoUncompress => false;
  @override
  set autoUncompress(bool value) {}

  @override
  set idleTimeout(Duration value) {}

  @override
  set connectionTimeout(Duration? value) {}

  @override
  Future<HttpClientRequest> openUrl(String method, Uri url) async {
    log.add(url.path);
    final body =
        routes[url.path] ?? {'code': 400, 'message': '未 mock 的路径: ${url.path}'};
    return FakeHttpClientRequest(jsonEncode(body));
  }

  @override
  void close({bool force = false}) {}

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError('FakeHttpClient.${invocation.memberName}');
}

class FakeHttpClientRequest implements HttpClientRequest {
  final String body;
  final HttpHeaders _headers = FakeHttpHeaders();
  FakeHttpClientRequest(this.body);

  @override
  HttpHeaders get headers => _headers;

  @override
  Future<HttpClientResponse> get done async =>
      FakeHttpClientResponse(200, 'OK', body);

  @override
  Future<HttpClientResponse> close() async =>
      FakeHttpClientResponse(200, 'OK', body);

  @override
  void add(List<int> data) {}

  @override
  Future<void> addStream(Stream<List<int>> stream) async {}

  @override
  set contentLength(int value) {}

  @override
  set followRedirects(bool value) {}

  @override
  set maxRedirects(int value) {}

  @override
  set persistentConnection(bool value) {}

  @override
  dynamic noSuchMethod(Invocation invocation) => throw UnimplementedError(
    'FakeHttpClientRequest.${invocation.memberName}',
  );
}

class FakeHttpHeaders implements HttpHeaders {
  @override
  String value(String name) =>
      name.toLowerCase() == 'content-type' ? 'application/json' : '';

  @override
  void add(String name, Object value, {bool preserveHeaderCase = false}) {}

  @override
  void set(String name, Object value, {bool preserveHeaderCase = false}) {}

  @override
  void forEach(void Function(String name, List<String> values) action) {
    action('content-type', ['application/json']);
  }

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError('FakeHttpHeaders.${invocation.memberName}');
}

class FakeHttpClientResponse extends StreamView<List<int>>
    implements HttpClientResponse {
  @override
  final int statusCode;
  @override
  final String reasonPhrase;
  @override
  final HttpHeaders headers = FakeHttpHeaders();

  FakeHttpClientResponse(this.statusCode, this.reasonPhrase, String body)
    : super(Stream.value(utf8.encode(body)));

  @override
  bool get isRedirect => false;
  @override
  bool get persistentConnection => false;
  @override
  HttpClientResponseCompressionState get compressionState =>
      HttpClientResponseCompressionState.notCompressed;
  @override
  int get contentLength => 0;
  @override
  List<RedirectInfo> get redirects => const [];

  @override
  dynamic noSuchMethod(Invocation invocation) => throw UnimplementedError(
    'FakeHttpClientResponse.${invocation.memberName}',
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    Get.testMode = true;
    Get.reset();
    SharedPreferences.setMockInitialValues({});
  });

  /// 登录页内容较高（验证码 + 表单 + 按钮），加高测试视口避免按钮落在屏幕外。
  Future<void> pumpLogin(WidgetTester tester, Widget home) async {
    tester.view.physicalSize = const Size(800, 1100);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(home);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
  }

  FakeHttpOverrides overridesWith({Map<String, Map<String, dynamic>>? extra}) {
    return FakeHttpOverrides({
      // 与 captcha_service.dart 实际请求路径一致（/api/v1/*）
      '/api/v1/captcha/generate': {
        'code': 0,
        'data': {
          'key': 'captcha-key-1',
          'image': _kPng1x1,
          // 弹框需目标非空才会收集点击并自动提交
          'extra': {
            'targets': <Map<String, dynamic>>[
              {'order': 1, 'text': '云'},
            ],
          },
        },
      },
      ...?extra,
    });
  }

  group('LoginPage — 验证码弹框成功链路', () {
    testWidgets('填写账号点登录弹出弹框，验证码加载成功后展示图片无错误提示', (tester) async {
      HttpOverrides.global = overridesWith();
      addTearDown(() => HttpOverrides.global = null);

      await pumpLogin(tester, const MaterialApp(home: LoginPage()));

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150)); // 弹框转场
      await tester.pump(const Duration(milliseconds: 100)); // generate 异步完成

      expect(find.text('验证码加载失败'), findsNothing);
      // 弹框内渲染出验证码图（MemoryImage 解码成功）
      expect(
        find.byWidgetPredicate((w) => w is Image && w.image is MemoryImage),
        findsOneWidget,
      );
      // 提示目标字来自 generate 响应
      expect(find.textContaining('"云"'), findsOneWidget);
    });
  });

  group('LoginPage — 完整登录成功', () {
    testWidgets('点满目标后先经独立 /captcha/verify 校验，再凭 key 登录，保存会话并跳转仪表盘', (
      tester,
    ) async {
      final overrides = overridesWith(
        extra: {
          // 独立校验端点：通过（登录页先于 /auth/login 调它）
          '/api/v1/captcha/verify': {
            'code': 0,
            'message': '验证通过',
            'data': {'valid': true},
          },
          '/api/v1/auth/login': {
            'code': 0,
            'data': {
              'access_token': 'test-token',
              'refresh_token': 'test-refresh',
              'user': {'username': 'admin'},
            },
          },
        },
      );
      HttpOverrides.global = overrides;
      addTearDown(() => HttpOverrides.global = null);

      await pumpLogin(
        tester,
        MaterialApp(
          home: const LoginPage(),
          routes: {
            '/dashboard': (_) => const Scaffold(body: Text('登录成功，已进入仪表盘')),
          },
        ),
      );

      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150)); // 弹框转场
      await tester.pump(const Duration(milliseconds: 100)); // 图片渲染完成

      // 点击验证码图（仅 1 个目标，点一次即点满）→ 300ms 后自动 pop 带回结果
      final imgRect = tester.getRect(
        find.byWidgetPredicate((w) => w is Image && w.image is MemoryImage),
      );
      await tester.tapAt(imgRect.center);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400)); // 自动关闭延迟
      await tester.pump(); // pop 后登录请求发出
      await tester.pump(const Duration(milliseconds: 100)); // 登录请求完成并保存会话
      await tester.pump(
        const Duration(milliseconds: 300),
      ); // 等待 pushReplacementNamed 转场结束
      await tester.pump();

      // 独立校验先于登录请求（登录接口不再比对坐标，只消费放行凭证）
      final verifyIdx = overrides.log.indexOf('/api/v1/captcha/verify');
      final loginIdx = overrides.log.indexOf('/api/v1/auth/login');
      expect(verifyIdx, isNot(-1));
      expect(loginIdx, isNot(-1));
      expect(verifyIdx, lessThan(loginIdx));
      // 会话已保存
      expect(await AuthService.getToken(), 'test-token');
      expect(await AuthService.getRefreshToken(), 'test-refresh');
      // 已跳转到仪表盘
      expect(find.text('登录成功，已进入仪表盘'), findsOneWidget);
    });
  });
}
