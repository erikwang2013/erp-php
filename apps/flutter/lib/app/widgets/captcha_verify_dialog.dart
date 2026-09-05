// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 通用点击验证码弹框：任何「需要验证码验证后才能继续」的流程直接调用
//   final result = await showCaptchaVerifyDialog(context);
//   if (result == null) return; // 用户取消
//   // 先调独立接口 /api/v1/captcha/verify 校验 result.key + result.clicks，
//   // 通过后再凭 result.key 调业务接口（登录/注册不再接收/比对坐标）
import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_service.dart';
import '../services/captcha_service.dart';
import '../l10n/app_l10n.dart';

/// 验证码弹框结果（key + 用户点击坐标）。
/// 调用方需先经独立接口 /api/v1/captcha/verify 校验坐标，再凭 key 调业务接口。
class CaptchaResult {
  final String key;
  final List<Offset> clicks;

  const CaptchaResult(this.key, this.clicks);
}

/// 弹出验证码弹框；验证完成自动关闭并返回 [CaptchaResult]，用户关闭返回 null
Future<CaptchaResult?> showCaptchaVerifyDialog(
  BuildContext context, {
  CaptchaService? captcha,
}) {
  return showDialog<CaptchaResult>(
    context: context,
    barrierDismissible: true,
    builder: (_) => CaptchaVerifyDialog(captcha: captcha),
  );
}

class CaptchaVerifyDialog extends StatefulWidget {
  /// 可注入（测试用）；默认使用全局 API 地址
  final CaptchaService? captcha;

  const CaptchaVerifyDialog({super.key, this.captcha});

  @override
  State<CaptchaVerifyDialog> createState() => _CaptchaVerifyDialogState();
}

class _CaptchaVerifyDialogState extends State<CaptchaVerifyDialog> {
  // 服务端图片逻辑尺寸（后端画布固定 300x200，见 poster-php AbstractCaptcha）
  static const double _imgW = 300;
  static const double _imgH = 200;
  // 弹框内固定显示尺寸（等比，避免依赖 dialog 拉伸测量导致布局坍缩）
  static const double _viewW = 320;
  static const double _viewH = 200;

  late final CaptchaService _captcha =
      widget.captcha ??
      CaptchaService(
        Dio(
          BaseOptions(
            baseUrl: ApiService.baseUrl,
            // 与 ApiService 一致：10s 超时，避免弹框请求无限挂起
            connectTimeout: const Duration(seconds: 10),
            receiveTimeout: const Duration(seconds: 10),
          ),
        ),
      );

  CaptchaData? _data;
  Uint8List? _image;
  final List<Offset> _clicks = [];
  bool _loading = true;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
      _clicks.clear();
    });
    try {
      final data = await _captcha.generate();
      if (!mounted) return;
      setState(() {
        _data = data;
        _image = base64Decode(
          data.imageBase64.replaceFirst(RegExp(r'^data:image/\w+;base64,'), ''),
        );
        _loading = false;
      });
    } catch (e) {
      debugPrint('captcha generate/parse failed: $e');
      if (!mounted) return;
      setState(() {
        _loading = false;
        _failed = true;
      });
    }
  }

  void _onImageTap(TapUpDetails detail) {
    if (_data == null || _loading || _clicks.length >= _data!.targets.length)
      return;

    // 弹框内坐标 → 服务端逻辑图坐标
    final imgX = (detail.localPosition.dx * _imgW / _viewW).round();
    final imgY = (detail.localPosition.dy * _imgH / _viewH).round();
    setState(() => _clicks.add(Offset(imgX.toDouble(), imgY.toDouble())));

    // 全部点完 → 自动关闭并回传（延时让最后一枚标记绘制出来）。
    // 点满瞬间即快照 key+clicks：延迟期内点「刷新」清空 _clicks 也不影响提交结果。
    if (_clicks.length >= _data!.targets.length) {
      final key = _data!.key;
      final clicks = List<Offset>.unmodifiable(_clicks);
      Future.delayed(const Duration(milliseconds: 300), () {
        if (mounted) {
          Navigator.of(context).pop(CaptchaResult(key, clicks));
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final targets = _data?.targets ?? const [];

    return AlertDialog(
      contentPadding: const EdgeInsets.fromLTRB(16, 18, 16, 10),
      content: SizedBox(
        width: _viewW,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 提示目标字（加载完成前显示省略占位，避免高度跳动）
            SizedBox(
              height: 18,
              child: Text(
                _loading || targets.isEmpty
                    ? l10n.loginCaptchaPrompt('…')
                    : l10n.loginCaptchaPrompt(
                        targets.map((t) => '"${t.text}"').join(' → '),
                      ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13, color: Colors.black87),
              ),
            ),
            const SizedBox(height: 8),
            if (_loading)
              const SizedBox(
                height: _viewH,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_failed || _image == null)
              SizedBox(
                height: _viewH,
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.loginCaptchaLoadFailed,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Colors.grey,
                        ),
                      ),
                      const SizedBox(height: 4),
                      TextButton.icon(
                        icon: const Icon(Icons.refresh, size: 18),
                        label: Text(l10n.loginRefresh),
                        onPressed: _load,
                      ),
                    ],
                  ),
                ),
              )
            else
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTapUp: _onImageTap,
                  child: SizedBox(
                    width: _viewW,
                    height: _viewH,
                    child: Stack(
                      children: [
                        Positioned.fill(
                          child: Image.memory(
                            _image!,
                            fit: BoxFit.fill,
                            gaplessPlayback: true,
                            errorBuilder: (context, error, stack) => Center(
                              child: Padding(
                                padding: const EdgeInsets.all(8),
                                child: Text(
                                  '验证码图片解码失败: $error',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Colors.red,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                        for (final (i, c) in _clicks.indexed)
                          Positioned(
                            left: c.dx / _imgW * _viewW - 14,
                            top: c.dy / _imgH * _viewH - 14,
                            child: Container(
                              width: 28,
                              height: 28,
                              decoration: const BoxDecoration(
                                color: Color(0xFF1677FF),
                                shape: BoxShape.circle,
                              ),
                              child: Center(
                                child: Text(
                                  '${i + 1}',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  l10n.loginCaptchaClicked(_clicks.length, targets.length),
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
                TextButton.icon(
                  icon: const Icon(Icons.refresh, size: 16),
                  label: Text(l10n.loginRefresh),
                  onPressed: _loading ? null : _load,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
