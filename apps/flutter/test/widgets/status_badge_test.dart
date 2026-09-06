// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// StatusBadge 状态徽标测试：胶囊形(StadiumBorder,视觉 3.0)与配色透传。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/theme/app_tokens.dart';
import 'package:admin_app/app/widgets/status_badge.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

  ShapeDecoration decorationOf(WidgetTester tester) {
    final container = tester.widget<Container>(find.byType(Container));
    final deco = container.decoration;
    expect(deco, isA<ShapeDecoration>(),
        reason: '徽标应为 ShapeDecoration(胶囊 StadiumBorder)');
    return deco as ShapeDecoration;
  }

  testWidgets('默认浅底式:胶囊形,底色/文字色透传', (tester) async {
    final c = AppColors.light;
    await tester.pumpWidget(wrap(StatusBadge(
      label: '已审核',
      bg: c.successBg,
      fg: c.successText,
    )));

    final deco = decorationOf(tester);
    expect(deco.shape, isA<StadiumBorder>(), reason: '视觉 3.0 徽标应为胶囊');
    expect(deco.color, c.successBg);
    final text = tester.widget<Text>(find.text('已审核'));
    expect(text.style?.color, c.successText);
  });

  testWidgets('实心版同样胶囊形且单色底白字', (tester) async {
    await tester.pumpWidget(wrap(StatusBadge.solid(
      label: '作废',
      color: AppColors.light.danger,
    )));

    final deco = decorationOf(tester);
    expect(deco.shape, isA<StadiumBorder>());
    expect(deco.color, AppColors.light.danger);
    final text = tester.widget<Text>(find.text('作废'));
    expect(text.style?.color, Colors.white);
  });
}
