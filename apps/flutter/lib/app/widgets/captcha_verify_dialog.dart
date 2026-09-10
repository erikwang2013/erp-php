// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 通用验证码弹框（click 点击 / rotate 旋转 / slider 滑动，随服务端下发
// data.type 分支渲染）：任何「需要验证码验证后才能继续」的流程直接调用
//   final result = await showCaptchaVerifyDialog(context);
//   if (result == null) return; // 用户取消
//   // 先调独立接口 /api/v1/captcha/verify 校验 result.key + 类型作答，
//   // 通过后再凭 result.key 调业务接口（登录/注册不再接收/比对坐标）
import 'dart:convert';
import 'dart:math' as math;
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_service.dart';
import '../services/captcha_service.dart';
import '../l10n/app_l10n.dart';
import '../../l10n/app_localizations.dart';

/// 验证码弹框结果（key + 按类型作答：click=点击坐标 / rotate=顺时针旋钮读数
/// 0-359 / slider=拼图左缘位移 px）。调用方需先经独立接口
/// /api/v1/captcha/verify 校验作答，再凭 key 调业务接口。
class CaptchaResult {
  final String key;
  final List<Offset> clicks;
  final String type;
  final int? angle;
  final int? distance;

  const CaptchaResult(this.key, this.clicks,
      {this.type = 'click', this.angle, this.distance});
}

/// 弹出验证码弹框；作答完成自动关闭并返回 [CaptchaResult]，用户关闭返回 null
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
  // 服务端图片逻辑尺寸（后端画布固定 300x200，见 poster-php AbstractCaptcha；
  // rotate 为 200x200 方形，见 RotateCaptcha）
  static const double _imgW = 300;
  static const double _imgH = 200;
  static const double _imgRot = 200;
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
  Uint8List? _puzzle; // slider 拼图片（可能带 data: 前缀，解码时统一剥离）
  final List<Offset> _clicks = [];
  // rotate：顺时针旋钮读数（0-359），图随之顺时针旋转，摆正时读数=服务端秘密角
  double _rot = 0;
  // slider：拼图左缘相对原点(0)的位移，原生 px（服务端校验口径）
  double _sliderNative = 0;
  bool _loading = true;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  /// 剥离可选的 data:image/...;base64, 前缀（后端可能原样透传驱动输出）
  Uint8List _decodeImage(String base64) => base64Decode(
      base64.replaceFirst(RegExp(r'^data:image/\w+;base64,'), ''));

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
      _clicks.clear();
      _rot = 0;
      _sliderNative = 0;
      _puzzle = null;
    });
    try {
      final data = await _captcha.generate();
      if (!mounted) return;
      setState(() {
        _data = data;
        _image = _decodeImage(data.imageBase64);
        final pz = data.puzzleBase64;
        _puzzle = pz == null ? null : _decodeImage(pz);
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
    if (_data == null || _loading || _clicks.length >= _data!.targets.length) {
      return;
    }

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

  void _submitRotate() {
    final key = _data!.key;
    Navigator.of(context)
        .pop(CaptchaResult(key, const [], type: 'rotate', angle: _rot.round()));
  }

  void _submitSlider() {
    final key = _data!.key;
    Navigator.of(context).pop(CaptchaResult(key, const [],
        type: 'slider', distance: _sliderNative.round()));
  }

  // ---------- 提示行 ----------

  String _hintText(AppLocalizations l10n) {
    if (_loading || _data == null) return l10n.loginCaptchaPrompt('…');
    final type = _data!.type;
    if (type == 'rotate') return l10n.loginCaptchaRotate;
    if (type == 'slider') return l10n.loginCaptchaSlider;
    final targets = _data!.targets;
    return targets.isEmpty
        ? l10n.loginCaptchaPrompt('…')
        : l10n.loginCaptchaPrompt(
            targets.map((t) => '"${t.text}"').join(' → '));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final type = _data?.type ?? 'click';
    final isClick = type == 'click';

    return AlertDialog(
      contentPadding: const EdgeInsets.fromLTRB(16, 18, 16, 10),
      content: SizedBox(
        width: _viewW,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 18,
              child: Text(
                _hintText(l10n),
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
            else if (isClick)
              _buildClickBody(l10n)
            else if (type == 'rotate')
              _buildRotateBody(l10n)
            else
              _buildSliderBody(l10n),
            const SizedBox(height: 4),
            // 底行：click 计数+刷新；rotate/slider 作答读数+提交+刷新
            if (isClick)
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    l10n.loginCaptchaClicked(_clicks.length, _data!.targets.length),
                    style: const TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                  TextButton.icon(
                    icon: const Icon(Icons.refresh, size: 16),
                    label: Text(l10n.loginRefresh),
                    onPressed: _loading ? null : _load,
                  ),
                ],
              )
            else
              Row(
                children: [
                  if (type == 'slider')
                    Text(
                      '${_sliderNative.round()} px',
                      style:
                          const TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                  const Spacer(),
                  TextButton(
                    onPressed: type == 'rotate' ? _submitRotate : _submitSlider,
                    child: Text(l10n.commonSubmit),
                  ),
                  TextButton.icon(
                    icon: const Icon(Icons.refresh, size: 16),
                    label: Text(l10n.loginRefresh),
                    onPressed: _load,
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  /// click：图 + 点选收集（一比一保留原交互；点满自动关闭回传）
  Widget _buildClickBody(AppLocalizations l10n) {
    return ClipRRect(
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
                        AppLocalizations.of(context)?.loginCaptchaLoadFailed ??
                            '',
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
    );
  }

  /// rotate：方形画布内图像随旋钮顺时针旋转（正角=视觉顺时针，y 向下坐标系），
  /// 旋到正位时读数即答案角。内容为圆遮罩（透明角），ClipRect 只是防溢出。
  Widget _buildRotateBody(AppLocalizations l10n) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        ClipRect(
          child: SizedBox(
            width: _imgRot,
            height: _imgRot,
            child: Transform.rotate(
              angle: _rot * math.pi / 180,
              child: Image.memory(
                _image!,
                fit: BoxFit.fill,
                gaplessPlayback: true,
              ),
            ),
          ),
        ),
        Row(
          children: [
            Text(
              '${_rot.round()}°',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
            Expanded(
              child: Slider(
                value: _rot,
                min: 0,
                max: 359,
                onChanged: (v) => setState(() => _rot = v),
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// slider：300x200 背景图 + 从原点(x=0)起的拼图横向拖动。拖距按
  /// 显示宽→原生 300 换算（scale = 300/显示宽），作答=拼图左缘原生位移。
  /// 缺口纵坐标服务端不下发且校验只比横向位移，拼图垂直居中即可。
  Widget _buildSliderBody(AppLocalizations l10n) {
    final data = _data!;
    final puzzle = _puzzle;
    final pw = data.puzzleW ?? 50;
    final ph = data.puzzleH ?? 50;
    if (puzzle == null) {
      // puzzle 缺失属后端异常，复用加载失败文案；底部刷新按钮可重试
      return SizedBox(
        height: _viewH,
        child: Center(
          child: Text(
            l10n.loginCaptchaLoadFailed,
            style: const TextStyle(fontSize: 12, color: Colors.red),
          ),
        ),
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;
        final scale = _imgW / w; // 显示→原生 px
        final bw = w;
        final bh = w * _imgH / _imgW; // 等比 3:2
        final maxNative = _imgW - pw;
        final pieceW = pw / scale;
        final pieceH = ph / scale;
        return GestureDetector(
          behavior: HitTestBehavior.opaque,
          onHorizontalDragUpdate: (d) => setState(() {
            _sliderNative =
                (_sliderNative + d.delta.dx * scale).clamp(0.0, maxNative);
          }),
          child: SizedBox(
            width: bw,
            height: bh,
            child: Stack(
              children: [
                Positioned.fill(
                  child: Image.memory(
                    _image!,
                    fit: BoxFit.fill,
                    gaplessPlayback: true,
                  ),
                ),
                Positioned(
                  left: _sliderNative / scale,
                  top: (bh - pieceH) / 2,
                  child: Image.memory(
                    puzzle,
                    width: pieceW,
                    height: pieceH,
                    fit: BoxFit.fill,
                    gaplessPlayback: true,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
