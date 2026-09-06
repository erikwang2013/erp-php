// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// PermissionTreePicker 单元测试：可见行剪枝（惰性渲染基座，P1-f）、
// 三态级联（父全勾/全清、子部分选=父 indeterminate、点击 indeterminate
// 父=全勾）、折叠/展开与输出集合语义。
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

/// 深链：c0 > c1 > ... > c{length-1}（单链，递归/预计算极端深度语义用）。
List<Map<String, dynamic>> chain(int length) {
  var tail = node('c${length - 1}', '链${length - 1}');
  for (var i = length - 2; i >= 0; i--) {
    tail = node('c$i', '链$i', children: [tail]);
  }
  return [tail];
}

/// 359 节点宽树：10 顶层组 × 各 4 目录 × 各 ~7-8 叶子（总数精确 [total]）。
/// 折叠默认态可见行 = 顶层 + 直接子目录 = 10 + 40 = 50。
List<Map<String, dynamic>> wideTree(int total) {
  const tops = 10, subs = 4;
  final slots = tops * subs;
  final perSlot = (total - tops - slots) ~/ slots;
  final extra = (total - tops - slots) % slots;
  return [
    for (var t = 0; t < tops; t++)
      node('t$t', '顶层$t', children: [
        for (var s = 0; s < subs; s++)
          node('s$t-$s', '目录$t-$s', children: [
            for (var l = 0;
                l < perSlot + ((t * subs + s) < extra ? 1 : 0);
                l++)
              node('l$t-$s-$l', '叶子$t-$s-$l'),
          ]),
      ]),
  ];
}

void main() {
  Checkbox boxOf(WidgetTester tester, String id) => tester.widget<Checkbox>(
        find.descendant(
          of: find.byKey(ValueKey(id)),
          matching: find.byType(Checkbox),
        ),
      );

  /// [initiallyExpanded] 三态透传；null=组件新默认（仅展开第一级）。
  Future<void> pumpTree(
    WidgetTester tester, {
    Set<String> initial = const {},
    List<Set<String>>? emitted,
    bool? initiallyExpanded,
  }) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PermissionTreePicker(
            nodes: tree(),
            initialSelectedIds: initial,
            initiallyExpanded: initiallyExpanded,
            onChanged: (s) => emitted?.add(s.toSet()),
          ),
        ),
      ),
    ));
  }

  group('可见行剪枝（P1-f 惰性基座）', () {
    Set<String> ids(Iterable<PermissionTreeRow> rows) =>
        rows.map((r) => '${r.node['id']}').toSet();

    test('默认(不传参)仅展开第一级:顶层+直接子可见,孙级剪枝', () {
      final rows = collectVisibleRows(tree(), initialCollapsedIds(tree()));
      expect(ids(rows), {'top1', 'mid1', 'mid2'},
          reason: '默认折叠集=含子节点的孙级目录(mid1/mid2),顶层组展开');
      expect(rows.every((r) => r.depth <= 1), isTrue);
    });

    test('true=全展开兼容(空折叠)/false=仅顶层行可见', () {
      final nodes = tree();
      expect(initialCollapsedIds(nodes, initiallyExpanded: true), isEmpty);
      expect(ids(collectVisibleRows(nodes, const {})),
          {'top1', 'mid1', 'mid2', 'leaf1', 'leaf2', 'leaf3'});
      final collapsedAll =
          initialCollapsedIds(nodes, initiallyExpanded: false);
      expect(ids(collectVisibleRows(nodes, collapsedAll)), {'top1'});
    });

    test('359 节点宽树:折叠默认可见行 50(<60),全展开 359', () {
      final nodes = wideTree(359);
      // 树总节点数精确(10 顶层 + 40 目录 + 309 叶子)
      final collapsed = initialCollapsedIds(nodes);
      expect(collapsed, hasLength(40), reason: '折叠集=40 个含子目录');
      final rows = collectVisibleRows(nodes, collapsed);
      expect(rows.length, lessThan(60), reason: '首屏行数须 <60(实际 50)');
      expect(rows, hasLength(50), reason: '顶层 10 + 直接子 40');
      expect(collectVisibleRows(nodes, const {}), hasLength(359),
          reason: '显式全展开仍渲染整树(兼容路径)');
    });

    test('展开目录:仅该目录子树行加入,兄弟折叠子树保持剪枝', () {
      final nodes = tree();
      final collapsed = initialCollapsedIds(nodes); // {mid1, mid2}
      final before = collectVisibleRows(nodes, collapsed);
      // 展开 mid1（从折叠集移除）→ 精确加入 leaf1/leaf2
      final opened = collectVisibleRows(nodes, collapsed..remove('mid1'));
      expect(ids(opened).difference(ids(before)), {'leaf1', 'leaf2'});
      expect(ids(opened), {'top1', 'mid1', 'mid2', 'leaf1', 'leaf2'});
      // 收回 mid1 → 恢复初始行集
      expect(ids(collectVisibleRows(nodes, collapsed..add('mid1'))),
          ids(before));
    });

    test('descendant 预计算与朴素逐行递归语义等价', () {
      // 旧实现 _hasDescendantSelected 的朴素版（逐节点递归）作参照
      bool naive(Map<String, dynamic> n, Set<String> selected) {
        for (final c in (n['children'] as List<dynamic>? ?? const [])
            .whereType<Map<String, dynamic>>()) {
          if (selected.contains('${c['id']}') || naive(c, selected)) {
            return true;
          }
        }
        return false;
      }

      Set<String> allIds(List<Map<String, dynamic>> nodes) {
        final out = <String>{};
        void walk(List<Map<String, dynamic>> list) {
          for (final n in list) {
            out.add('${n['id']}');
            walk(
                (n['children'] as List<dynamic>? ?? const [])
                    .whereType<Map<String, dynamic>>()
                    .toList());
          }
        }

        walk(nodes);
        return out;
      }

      final samples = [
        tree(),
        chain(5),
        wideTree(359),
        const <Map<String, dynamic>>[], // 空树
      ];
      for (final nodes in samples) {
        final map = collectDescendantSets(nodes);
        void walk(List<Map<String, dynamic>> list) {
          for (final n in list) {
            for (final selected in [
              <String>{},
              {'leaf1'},
              {'c2'},
              {'t3', 's3-1'},
              allIds(nodes),
            ]) {
              expect(
                map['${n['id']}']!.any(selected.contains),
                naive(n, selected),
                reason: '节点 ${n['id']} 在选中集 $selected 下语义不一致',
              );
            }
            walk(
                (n['children'] as List<dynamic>? ?? const [])
                    .whereType<Map<String, dynamic>>()
                    .toList());
          }
        }

        walk(nodes);
      }
    });
  });

  group('渲染', () {
    testWidgets('默认(不传参):顶层+直接子可见,孙级需展开目录才出现', (tester) async {
      await pumpTree(tester);

      expect(find.text('销售管理'), findsOneWidget);
      expect(find.text('订单管理'), findsOneWidget);
      expect(find.text('客户管理'), findsOneWidget);
      // 孙级(叶子)默认剪枝不可见
      expect(find.text('订单查看'), findsNothing);
      expect(find.text('订单导出'), findsNothing);
      expect(find.text('sales:order:view'), findsNothing);

      // 点订单管理行内展开箭头 → 孙级出现
      await tester.tap(find.descendant(
        of: find.byKey(const ValueKey('mid1')),
        matching: find.byIcon(Icons.chevron_right),
      ));
      await tester.pump();
      expect(find.text('订单查看'), findsOneWidget);
      expect(find.text('sales:order:view'), findsOneWidget);
      // 客户目录仍折叠
      expect(find.text('客户查看'), findsNothing);
    });

    testWidgets('initiallyExpanded:true 全展开兼容(历史默认),name+slug 可见',
        (tester) async {
      await pumpTree(tester, initiallyExpanded: true);

      for (final t in ['销售管理', '订单管理', '订单查看', '订单导出', '客户管理', '客户查看']) {
        expect(find.text(t), findsOneWidget, reason: '应可见: $t');
      }
      expect(find.text('sales:order:view'), findsOneWidget);
    });

    testWidgets('无预选时 checkbox 未勾且无 indeterminate', (tester) async {
      await pumpTree(tester, initiallyExpanded: true);

      for (final id in ['top1', 'mid1', 'leaf1']) {
        expect(boxOf(tester, id).value, isFalse, reason: '$id 应未勾');
      }
    });

    testWidgets('组行可折叠:收起点后后代行消失,再点展开恢复', (tester) async {
      await pumpTree(tester);

      // 点 top1 行的展开箭头(expand_more 表示展开 → 点击折叠)
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
    // 三态语义与展开态解耦：统一全展开(显式 true)便于直接断言任意层节点。
    testWidgets('勾选父=全勾子(含父 id);再点父=全清(输出集合)', (tester) async {
      final emitted = <Set<String>>[];
      await pumpTree(tester, emitted: emitted, initiallyExpanded: true);

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
      await pumpTree(tester,
          initial: {'leaf1'}, emitted: emitted, initiallyExpanded: true);

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
      await pumpTree(tester, emitted: emitted, initiallyExpanded: true);

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
      await pumpTree(tester, emitted: emitted, initiallyExpanded: true);

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
