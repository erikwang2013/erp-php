// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 登录门面(视觉 2.0):≥768 桌面分栏 = 左品牌 hero(品牌渐变+光晕/网格底纹+
// mascot+标语,深底白字) / 右玻璃拟态卡(r16,半透明白);窄屏 = hero 压缩为
// 顶部品牌带 + mascot 入场。表单字段/密码眼/验证码弹框交互与文案不变。
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/captcha_service.dart';
import '../../widgets/captcha_verify_dialog.dart';
import '../../theme/app_tokens.dart';
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
    final wide = MediaQuery.sizeOf(context).width >= 768;
    return Scaffold(
      backgroundColor: Colors.white,
      body: wide ? _buildDesktop(context) : _buildMobile(context),
    );
  }

  // ---------- 桌面(≥768):左品牌 hero + 右表单面板 ----------

  Widget _buildDesktop(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(flex: 11, child: _brandHero(context, wide: true)),
        Expanded(flex: 9, child: _formPanel(context)),
      ],
    );
  }

  // ---------- 窄屏(<768):顶部品牌带 + 表单卡 ----------

  Widget _buildMobile(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFEAF3FF), Colors.white],
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: SingleChildScrollView(
          child: Column(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(AppMetrics.radiusFacade + 8),
                ),
                child: _brandHero(context, wide: false),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 28, 24, 32),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 400),
                  child: _Entrance(
                    duration: const Duration(milliseconds: 300),
                    child: _formCard(context),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// 品牌 hero:品牌渐变底 + 光晕/网格底纹(painter)+ mascot 入场 + 名称/标语。
  Widget _brandHero(BuildContext context, {required bool wide}) {
    final l10n = AppL10n.of(context);
    return Container(
      decoration: const BoxDecoration(gradient: AppColors.brandGradient),
      child: CustomPaint(
        painter: const _BrandTexturePainter(),
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            wide ? 48 : 28,
            wide ? 32 : 28,
            wide ? 48 : 28,
            wide ? 32 : 40,
          ),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _Entrance(
                  duration: const Duration(milliseconds: 400),
                  offset: 6,
                  child: Image.asset(
                    'assets/mascot.png',
                    width: wide ? 148 : 84,
                    height: wide ? 148 : 84,
                  ),
                ),
                SizedBox(height: wide ? 20 : 14),
                _Entrance(
                  duration: const Duration(milliseconds: 300),
                  child: Column(
                    children: [
                      Text(
                        l10n.loginTitle,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: wide ? 34 : 24,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          letterSpacing: 0.5,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        l10n.loginSlogan,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: wide ? 15 : 13,
                          color: Colors.white.withValues(alpha: 0.78),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ---------- 表单面板/玻璃卡(两模式共用字段体) ----------

  Widget _formPanel(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Colors.white, Color(0xFFEAF3FF)],
        ),
      ),
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: _Entrance(child: _formCard(context)),
          ),
        ),
      ),
    );
  }

  /// 玻璃拟态卡:r16 + 半透明白。Web(桌面浏览器)退化近白半透明
  /// (跳过 BackdropFilter 防低端机卡顿),其余平台叠 18px 背景模糊。
  Widget _formCard(BuildContext context) {
    final l10n = AppL10n.of(context);
    final r = BorderRadius.circular(AppMetrics.radiusFacade);
    final blur = !kIsWeb;
    final body = Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: blur ? 0.72 : 0.92),
        borderRadius: r,
        border: Border.all(color: Colors.white.withValues(alpha: 0.85)),
      ),
      padding: const EdgeInsets.fromLTRB(32, 32, 32, 28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
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
                onPressed: () => setState(() => _showPassword = !_showPassword),
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
                  const Icon(Icons.error_outline, color: Colors.red, size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _error!,
                      style: const TextStyle(color: Colors.red, fontSize: 13),
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
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Colors.grey[400]),
          ),
        ],
      ),
    );
    if (!blur) {
      return Container(
        decoration: BoxDecoration(
          borderRadius: r,
          boxShadow: const [AppShadows.facadeCard],
        ),
        child: body,
      );
    }
    return Container(
      decoration: BoxDecoration(
        borderRadius: r,
        boxShadow: const [AppShadows.facadeCard],
      ),
      child: ClipRRect(
        borderRadius: r,
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 18, sigmaY: 18),
          child: body,
        ),
      ),
    );
  }
}

/// 一次性淡入 + 上移动效(默认 250ms/4px,参照视觉 2.0 动效基线);
/// 系统「减少动态」(disableAnimations)时直接渲染终态,动效可关。
class _Entrance extends StatelessWidget {
  final Widget child;
  final Duration duration;
  final double offset;
  const _Entrance({
    required this.child,
    this.duration = const Duration(milliseconds: 250),
    this.offset = 4,
  });

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: duration,
      curve: Curves.easeOutCubic,
      child: child,
      builder: (_, v, c) => Opacity(
        opacity: v,
        child: Transform.translate(
          offset: Offset(0, (1 - v) * offset),
          child: c,
        ),
      ),
    );
  }
}

/// 品牌 hero 底纹:浅色网格线 + 两团品牌色光晕(纯绘制,无模糊滤镜开销)。
class _BrandTexturePainter extends CustomPainter {
  const _BrandTexturePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final grid = Paint()
      ..color = Colors.white.withValues(alpha: 0.05)
      ..strokeWidth = 1;
    const step = 32.0;
    for (var x = 0.0; x <= size.width; x += step) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    }
    for (var y = 0.0; y <= size.height; y += step) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }
    final glow = Paint();
    // 左上主蓝光晕 + 右下青(辅色)光晕,尺寸随容器缩放
    canvas.drawCircle(
      Offset(size.width * 0.18, size.height * 0.16),
      size.width * 0.42,
      glow
        ..shader = const RadialGradient(
          colors: [Color(0x4069B1FF), Colors.transparent],
        ).createShader(Offset.zero & size),
    );
    canvas.drawCircle(
      Offset(size.width * 0.88, size.height * 0.9),
      size.width * 0.4,
      glow
        ..shader = const RadialGradient(
          colors: [Color(0x384DD0E1), Colors.transparent],
        ).createShader(Offset.zero & size),
    );
  }

  @override
  bool shouldRepaint(covariant _BrandTexturePainter oldDelegate) => false;
}
