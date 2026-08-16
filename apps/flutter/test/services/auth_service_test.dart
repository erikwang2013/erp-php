// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// AuthService 单元测试：Token 存取、登出清理、登录态判断。
// 使用 SharedPreferences.setMockInitialValues 提供内存版存储，完全离线。
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/services/auth_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    // 每个用例前重置存储与静态缓存，避免用例间相互污染。
    SharedPreferences.setMockInitialValues({});
    await AuthService.clearToken();
  });

  group('AuthService — 登录信息存取', () {
    test('saveLogin 后 token / refreshToken / username 均可读取', () async {
      await AuthService.saveLogin(
        token: 'access-token-1',
        refreshToken: 'refresh-token-1',
        username: 'admin',
      );

      expect(await AuthService.getToken(), 'access-token-1');
      expect(await AuthService.getRefreshToken(), 'refresh-token-1');
      expect(await AuthService.getUsername(), 'admin');
      expect(AuthService.cachedToken, 'access-token-1');
    });

    test('saveLogin 后 isLoggedIn 返回 true', () async {
      await AuthService.saveLogin(
        token: 't',
        refreshToken: 'r',
        username: 'u',
      );
      expect(await AuthService.isLoggedIn(), isTrue);
    });

    test('未登录时 getToken 返回 null、isLoggedIn 返回 false', () async {
      expect(await AuthService.getToken(), isNull);
      expect(await AuthService.getRefreshToken(), isNull);
      expect(await AuthService.getUsername(), isNull);
      expect(await AuthService.isLoggedIn(), isFalse);
    });

    test('Token 持久化到 SharedPreferences（重新初始化后仍可读取）', () async {
      await AuthService.saveLogin(
        token: 'persisted-token',
        refreshToken: 'persisted-refresh',
        username: 'ops',
      );

      // 模拟缓存失效：直接读取底层 prefs，应能看到持久化的值。
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('access_token'), 'persisted-token');
      expect(prefs.getString('refresh_token'), 'persisted-refresh');
      expect(prefs.getString('username'), 'ops');
    });
  });

  group('AuthService — 登出清理', () {
    test('clearToken 清空全部凭证与缓存', () async {
      await AuthService.saveLogin(
        token: 't',
        refreshToken: 'r',
        username: 'u',
      );
      expect(await AuthService.isLoggedIn(), isTrue);

      await AuthService.clearToken();

      expect(await AuthService.getToken(), isNull);
      expect(await AuthService.getRefreshToken(), isNull);
      expect(await AuthService.getUsername(), isNull);
      expect(AuthService.cachedToken, isNull);
      expect(await AuthService.isLoggedIn(), isFalse);

      // 底层 prefs 也应被清空。
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('access_token'), isNull);
      expect(prefs.getString('refresh_token'), isNull);
      expect(prefs.getString('username'), isNull);
    });

    test('clearToken 可安全重复调用（幂等）', () async {
      await AuthService.clearToken();
      await AuthService.clearToken();
      expect(await AuthService.isLoggedIn(), isFalse);
    });

    test('空字符串 token 视为未登录', () async {
      SharedPreferences.setMockInitialValues({'access_token': ''});
      // 清缓存后从 prefs 读取空串
      await AuthService.clearToken();
      // 重新注入 prefs，缓存为 null，会走 prefs 路径
      SharedPreferences.setMockInitialValues({'access_token': ''});
      // 由于缓存已被 clearToken 置空，getToken 会读 prefs 的空串
      expect(await AuthService.getToken(), '');
      expect(await AuthService.isLoggedIn(), isFalse);
    });
  });
}
