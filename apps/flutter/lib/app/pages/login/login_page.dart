// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/captcha_service.dart';
import '../../widgets/captcha_verify_dialog.dart';
import '../../l10n/app_l10n.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  // API 版本置于 URL 路径（/api/v1/*），无需版本请求头
  final _dio = Dio(BaseOptions(baseUrl: ApiService.baseUrl));

  bool _loading = false;
  bool _showPassword = false;
  String? _error;

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;

    if (username.isEmpty || password.isEmpty) {
      setState(() => _error = AppL10n.of(context).loginRequired);
      return;
    }
    setState(() => _error = null);

    // 点「登录」才弹验证码（通用模块），点完自动关闭并带回 key+clicks。
    // 坐标比对只在独立接口 /api/v1/captcha/verify 做一次；登录不再回传 clicks，
    // 仅凭 captcha_key 消费服务端写好的放行凭证。
    final result = await showCaptchaVerifyDialog(context);
    if (result == null || !mounted) return; // 用户关闭弹窗 = 取消登录

    setState(() => _loading = true);
    try {
      final verified = await CaptchaService(
        _dio,
      ).verify(result.key, result.clicks);
      if (!verified) {
        setState(() => _error = AppL10n.of(context).loginCaptchaFailed);
        return;
      }

      final resp = await _dio.post(
        '/api/v1/auth/login',
        data: {
          'username': username,
          'password': password,
          'captcha_key': result.key,
        },
      );

      if (resp.data['code'] == 0) {
        final data = resp.data['data'];
        await AuthService.saveLogin(
          token: data['access_token'] as String,
          refreshToken: data['refresh_token'] as String,
          username: data['user']['username'] as String,
        );
        if (mounted) Navigator.of(context).pushReplacementNamed('/dashboard');
      } else {
        setState(
          () => _error =
              resp.data['message'] ?? AppL10n.of(context).loginLoginFailed,
        );
      }
    } catch (e) {
      setState(() => _error = AppL10n.of(context).loginNetworkError);
    } finally {
      // pushReplacementNamed 的 future 要等目标路由被 pop 才 resolve（登出回到登录页时），
      // 彼时本 State 早已 dispose，必须 mounted 保护避免 setState-after-dispose。
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/mascot.png', width: 110, height: 110),
                const SizedBox(height: 12),
                Text(
                  l10n.loginTitle,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1677FF),
                  ),
                ),
                const SizedBox(height: 32),

                // Username
                TextField(
                  controller: _usernameCtrl,
                  decoration: InputDecoration(
                    labelText: l10n.loginUsername,
                    prefixIcon: const Icon(Icons.person_outline),
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),

                // Password
                TextField(
                  controller: _passwordCtrl,
                  obscureText: !_showPassword,
                  decoration: InputDecoration(
                    labelText: l10n.loginPassword,
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _showPassword
                            ? Icons.visibility_off_outlined
                            : Icons.visibility_outlined,
                      ),
                      tooltip: _showPassword ? '隐藏密码' : '显示密码',
                      onPressed: () =>
                          setState(() => _showPassword = !_showPassword),
                    ),
                    border: const OutlineInputBorder(),
                  ),
                  onSubmitted: (_) => _login(),
                ),
                const SizedBox(height: 20),

                // Error
                if (_error != null) ...[
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red[50],
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.error_outline,
                          color: Colors.red,
                          size: 18,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _error!,
                            style: const TextStyle(
                              color: Colors.red,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],

                // Login button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton(
                    onPressed: _loading ? null : _login,
                    child: _loading
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Text(
                            l10n.loginButton,
                            style: const TextStyle(fontSize: 16),
                          ),
                  ),
                ),
                const SizedBox(height: 20),

                Text(
                  'Copyright (c) 2026 erik — https://erik.xyz',
                  style: TextStyle(fontSize: 11, color: Colors.grey[400]),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
