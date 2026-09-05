// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/app/services/captcha_service.dart';
import 'package:admin_app/app/widgets/captcha_verify_dialog.dart';

// 1x1 透明 PNG（单层 base64，与生产接口同格式）
const String _kPng1x1 =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

class _FakeCaptcha extends CaptchaService {
  _FakeCaptcha() : super(Dio());

  @override
  Future<CaptchaData> generate({String difficulty = 'medium'}) async {
    return CaptchaData(
      key: 'fake-key',
      imageBase64: _kPng1x1,
      targets: [
        CaptchaTarget(order: 1, text: '云'),
        CaptchaTarget(order: 2, text: '风'),
        CaptchaTarget(order: 3, text: '山'),
      ],
    );
  }
}

void main() {
  testWidgets('验证码弹框：解析渲染正常，点完目标自动返回结果', (tester) async {
    CaptchaResult? returned;
    final fake = _FakeCaptcha();

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: Builder(builder: (context) => Center(
          child: ElevatedButton(
            onPressed: () async {
              returned = await showCaptchaVerifyDialog(context, captcha: fake);
            },
            child: const Text('open'),
          ),
        )),
      ),
    ));

    // 点按钮 → 弹框出现，提示目标字（证明 generate 解析成功）
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.textContaining('"云"'), findsOneWidget);

    // 验证码图已渲染（Image.memory 解码无异常，无 errorBuilder 红字）
    final imgFinder = find.descendant(
      of: find.byType(CaptchaVerifyDialog),
      matching: find.byType(Image),
    );
    expect(imgFinder, findsOneWidget);
    expect(find.textContaining('解码失败'), findsNothing);

    // 点满 3 个目标（图区内的不同位置）
    final rect = tester.getRect(imgFinder);
    for (final offset in [const Offset(-40, -20), Offset.zero, const Offset(40, 20)]) {
      await tester.tapAt(rect.center + offset);
      await tester.pump();
    }

    // 300ms 后自动 pop 回传结果
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(find.byType(CaptchaVerifyDialog), findsNothing);
    expect(returned, isNotNull);
    expect(returned!.key, 'fake-key');
    expect(returned!.clicks.length, 3);
  });

  testWidgets('小屏手机（360dp）打开弹框不溢出', (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final fake = _FakeCaptcha();
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: Builder(builder: (context) => Center(
          child: ElevatedButton(
            onPressed: () => showCaptchaVerifyDialog(context, captcha: fake),
            child: const Text('open'),
          ),
        )),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.byType(CaptchaVerifyDialog), findsOneWidget);
    // 无 RenderFlex/布局溢出异常
    expect(tester.takeException(), isNull);
    // 验证码图仍完整可交互
    final imgFinder = find.descendant(
      of: find.byType(CaptchaVerifyDialog),
      matching: find.byType(Image),
    );
    expect(imgFinder, findsOneWidget);
  });
}
