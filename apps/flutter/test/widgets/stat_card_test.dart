// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// StatCard 通用组件渲染测试：标题/数值/图标/趋势箭头与配色。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/stat_card.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

  group('StatCard — 基础渲染', () {
    testWidgets('渲染标题、数值与图标', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '今日订单',
        value: '128',
        icon: Icons.shopping_bag,
        color: Color(0xFF1677FF),
      )));

      expect(find.text('今日订单'), findsOneWidget);
      expect(find.text('128'), findsOneWidget);
      expect(find.byIcon(Icons.shopping_bag), findsOneWidget);
    });

    testWidgets('无 trend 时不显示趋势区域', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '库存',
        value: '999',
        icon: Icons.inventory_2,
        color: Colors.blue,
      )));

      expect(find.byIcon(Icons.arrow_upward), findsNothing);
      expect(find.byIcon(Icons.arrow_downward), findsNothing);
    });
  });

  group('StatCard — 趋势展示', () {
    testWidgets('正趋势显示上升箭头与百分比（默认升=好，绿色）', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '销售额',
        value: '¥52,000',
        icon: Icons.paid,
        color: Color(0xFF52C41A),
        trend: 12.5,
      )));

      expect(find.byIcon(Icons.arrow_upward), findsOneWidget);
      expect(find.text('12.5%'), findsOneWidget);
    });

    testWidgets('负趋势显示下降箭头', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '退货率',
        value: '3.2%',
        icon: Icons.assignment_return,
        color: Colors.orange,
        trend: -3.2,
      )));

      expect(find.byIcon(Icons.arrow_downward), findsOneWidget);
      // value 与 trend 文本相同（均为 3.2%），匹配 2 处
      expect(find.text('3.2%'), findsNWidgets(2));
    });

    testWidgets('trendIsGood=false 时上升趋势显示红色（降=好场景）', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '故障数',
        value: '4',
        icon: Icons.warning_amber,
        color: Colors.red,
        trend: 30.0,
        trendIsGood: false,
      )));

      // 上升箭头存在，但文字颜色应为红色（bad）
      final text = tester.widget<Text>(find.text('30.0%'));
      expect(text.style?.color, Colors.red);
    });

    testWidgets('trendIsGood=false 时下降趋势显示绿色（好）', (tester) async {
      await tester.pumpWidget(wrap(const StatCard(
        title: '故障数',
        value: '2',
        icon: Icons.warning_amber,
        color: Colors.red,
        trend: -30.0,
        trendIsGood: false,
      )));

      final text = tester.widget<Text>(find.text('30.0%'));
      expect(text.style?.color, Colors.green);
      expect(find.byIcon(Icons.arrow_downward), findsOneWidget);
    });
  });
}
