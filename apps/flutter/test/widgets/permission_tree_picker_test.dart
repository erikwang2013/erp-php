// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// PermissionTreePicker 单元测试：3 层树拍平渲染、三态级联
// （父全勾/全清、子部分选=父 indeterminate、点击 indeterminate 父=全勾）、
// 折叠/展开与输出集合语义。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/permission_tree_picker.dart';

Map<String, dynamic> node(
  String id,
  String name, {
  String slug = '',
  List<Map<String, dynamic>> children = const [],
}) =>
    {
      'id': id,
      'name': name,
      'slug': slug,
      if (children.isNotEmpty) 'children': children,
    };

/// 销售域:订单目录(leaf1/leaf2)+ 客户目录(leaf3)
List<Map<String, dynamic>> tree() => [
      node('top1', '销售管理', slug: 'sales', children: [
        node('mid1', '订单管理', slug: 'sales:order', children: [
          node('leaf1', '订单查看', slug: 'sales:order:view'),
          node('leaf2', '订单导出', slug: 'sales:order:export'),
        ]),
        node('mid2', '客户管理', slug: 'sales:customer', children: [
          node('leaf3', '客户查看', slug: 'sales:customer:view'),
        ]),
      ]),
    ];

void main() {
  Checkbox boxOf(WidgetTester tester, String id) => tester.widget<Checkbox>(
        find.descendant(
          of: find.byKey(ValueKey(id)),
          matching: find.byType(Checkbox),
        ),
      );

  Future<Set<String>> pumpTree(
    WidgetTester tester, {
    Set<String> initial = const {},
    List<Set<String>>? emitted,
  }) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PermissionTreePicker(
            nodes: tree(),
            initialSelectedIds: initial,
            onChanged: (s) => emitted?.add(s.toSet()),
          ),
        ),
      ),
    ));
    return initial;
  }

  group('渲染', () {
    testWidgets('3 层树默认全展开,name+slug 全部可见', (tester) async {
      await pumpTree(tester);

      for (final t in ['销售管理', '订单管理', '订单查看', '订单导出', '客户管理', '客户查看']) {
        expect(find.text(t), findsOneWidget, reason: '应可见: $t');
      }
      expect(find.text('sales:order:view'), findsOneWidget);
      expect(find.text('sales'), findsOneWidget);
      expect(find.text('sales'), findsOneWidget);
    });

    testWidgets('无预选时全部 checkbox 未勾且无 indeterminate', (tester) async {
      await pumpTree(tester);

      for (final id in ['top1', 'mid1', 'leaf1']) {
        expect(boxOf(tester, id).value, isFalse, reason: '$id 应未勾');
      }
    });

    testWidgets('组行可折叠:收起点后后代行消失,再点展开恢复', (tester) async {
      await pumpTree(tester);

      // 点 top1 行的展开箭头(chevron_down 时表示展开 → 点击折叠)
      await tester.tap(find.byIcon(Icons.expand_more).first);
      await tester.pump();

      expect(find.text('订单管理'), findsNothing);
      expect(find.text('订单查看'), findsNothing);
      // 顶层自身仍在
      expect(find.text('销售管理'), findsOneWidget);

      await tester.tap(find.byIcon(Icons.chevron_right).first);
      await tester.pump();
      expect(find.text('订单管理'), findsOneWidget);
    });
  });

  group('三态级联', () {
    testWidgets('勾选父=全勾子(含父 id);再点父=全清(输出集合)', (tester) async {
      final emitted = <Set<String>>[];
      await pumpTree(tester, emitted: emitted);

      // 点叶子父节点 mid1 行 → 全勾:订单查看+订单导出+订单管理自身
      await tester.tap(find.text('订单管理'));
      await tester.pump();
      expect(emitted.last, {'mid1', 'leaf1', 'leaf2'});

      expect(boxOf(tester, 'mid1').value, isTrue);
      expect(boxOf(tester, 'leaf1').value, isTrue);
      expect(boxOf(tester, 'leaf2').value, isTrue);
      // 兄弟目录/顶级不受影响(客户目录下 leaf3 未勾)
      expect(boxOf(tester, 'top1').value, isNull);

      // 再点父 → 全清
      await tester.tap(find.text('订单管理'));
      await tester.pump();
      expect(emitted.last, isEmpty);
      expect(boxOf(tester, 'leaf1').value, isFalse);
    });

    testWidgets('子部分选=父 indeterminate(null);点 indeterminate 父=全勾', (tester) async {
      final emitted = <Set<String>>[];
      // 预置:仅 leaf1 已授(编辑回填场景)
      await pumpTree(tester, initial: {'leaf1'}, emitted: emitted);

      expect(boxOf(tester, 'leaf1').value, isTrue);
      expect(boxOf(tester, 'mid1').value, isNull, reason: '子部分选父应 indeterminate');
      expect(boxOf(tester, 'top1').value, isNull, reason: '孙级选中向上传导 indeterminate');
      expect(boxOf(tester, 'leaf2').value, isFalse);

      // 点击 indeterminate 父 = 全勾(mid1 + leaf1 + leaf2)
      await tester.tap(find.text('订单管理'));
      await tester.pump();
      expect(emitted.last, {'mid1', 'leaf1', 'leaf2'});
      expect(boxOf(tester, 'mid1').value, isTrue);
      expect(boxOf(tester, 'leaf2').value, isTrue);
    });

    testWidgets('叶子单独取消不影响兄弟;父保持为子全选后回传全勾', (tester) async {
      final emitted = <Set<String>>[];
      await pumpTree(tester, emitted: emitted);

      // 先全勾订单目录
      await tester.tap(find.text('订单管理'));
      await tester.pump();
      // 取消 leaf1
      await tester.tap(find.text('订单查看'));
      await tester.pump();

      expect(emitted.last, {'mid1', 'leaf2'}, reason: '只移除自身');
      expect(boxOf(tester, 'mid1').value, isTrue, reason: '父仍显式在集合中');
      expect(boxOf(tester, 'leaf1').value, isFalse);
      // 再点回 leaf1 恢复
      await tester.tap(find.text('订单查看'));
      await tester.pump();
      expect(emitted.last, {'mid1', 'leaf1', 'leaf2'});
    });

    testWidgets('整树全勾(点顶级);顶层父进集合', (tester) async {
      final emitted = <Set<String>>[];
      await pumpTree(tester, emitted: emitted);

      await tester.tap(find.text('销售管理'));
      await tester.pump();
      expect(
        emitted.last,
        {'top1', 'mid1', 'leaf1', 'leaf2', 'mid2', 'leaf3'},
        reason: '点顶级=全部节点入集合(含各级父)',
      );
      expect(boxOf(tester, 'leaf3').value, isTrue);
    });
  });
}
