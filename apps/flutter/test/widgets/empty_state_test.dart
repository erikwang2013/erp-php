// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 共享空态组件测试(视觉 2.0 质感轴):默认「暂无数据」+ 灰阶 mascot、
// 自定义文案/操作按钮可点。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/empty_state.dart';

void main() {
  testWidgets('默认空态:灰阶 mascot + 「暂无数据」', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: EmptyState())),
    );

    expect(find.text('暂无数据'), findsOneWidget);
    expect(find.byType(ColorFiltered), findsOneWidget);
    expect(
      find.byWidgetPredicate((w) => w is Image && w.image is AssetImage),
      findsOneWidget,
    );
  });

  testWidgets('自定义标题/副文案/操作按钮可交互', (tester) async {
    var clicked = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: EmptyState(
            title: '搜索无结果',
            subtitle: '换个关键词试试',
            action: OutlinedButton(
              onPressed: () => clicked++,
              child: const Text('清空筛选'),
            ),
          ),
        ),
      ),
    );

    expect(find.text('搜索无结果'), findsOneWidget);
    expect(find.text('换个关键词试试'), findsOneWidget);
    await tester.tap(find.text('清空筛选'));
    expect(clicked, 1);
  });
}
