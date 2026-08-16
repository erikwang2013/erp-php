// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// DataTableWrapper 通用组件测试：列头/行数据渲染、空态、加载态、
// 分页交互与搜索框。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/data_table_wrapper.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(
        home: Scaffold(
          body: SizedBox(height: 600, child: child),
        ),
      );

  const columns = ['ID', '用户名', '状态'];
  final rows = [
    {'ID': 1, '用户名': 'admin', '状态': '启用'},
    {'ID': 2, '用户名': 'ops', '状态': '禁用'},
  ];

  group('DataTableWrapper — 数据渲染', () {
    testWidgets('渲染列头与所有行数据', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
      )));

      for (final c in columns) {
        expect(find.text(c), findsOneWidget);
      }
      expect(find.text('admin'), findsOneWidget);
      expect(find.text('ops'), findsOneWidget);
    });

    testWidgets('空数据时显示「暂无数据」', (tester) async {
      await tester.pumpWidget(wrap(const DataTableWrapper(
        columns: columns,
        rows: [],
        total: 0,
        page: 1,
        limit: 10,
      )));

      expect(find.text('暂无数据'), findsOneWidget);
    });

    testWidgets('loading 时显示加载指示器', (tester) async {
      await tester.pumpWidget(wrap(const DataTableWrapper(
        columns: columns,
        rows: [],
        total: 0,
        page: 1,
        limit: 10,
        loading: true,
      )));

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });

    testWidgets('单元格值可为 Widget（自定义渲染）', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: const ['名称', '操作'],
        rows: [
          {
            '名称': '商品A',
            '操作': const Icon(Icons.edit, key: Key('edit-icon')),
          },
        ],
        total: 1,
        page: 1,
        limit: 10,
      )));

      expect(find.text('商品A'), findsOneWidget);
      expect(find.byKey(const Key('edit-icon')), findsOneWidget);
    });
  });

  group('DataTableWrapper — 分页', () {
    testWidgets('总条数大于单页容量时显示分页信息与「共 N 条」', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 25,
        page: 1,
        limit: 10,
      )));

      expect(find.text('共 25 条'), findsOneWidget);
      expect(find.text('1/3'), findsOneWidget);
    });

    testWidgets('点击下一页触发 onPageChanged(page+1)', (tester) async {
      int? changedPage;
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 25,
        page: 1,
        limit: 10,
        onPageChanged: (p) => changedPage = p,
      )));

      await tester.tap(find.byIcon(Icons.chevron_right));
      expect(changedPage, 2);
    });

    testWidgets('点击上一页触发 onPageChanged(page-1)', (tester) async {
      int? changedPage;
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 25,
        page: 2,
        limit: 10,
        onPageChanged: (p) => changedPage = p,
      )));

      await tester.tap(find.byIcon(Icons.chevron_left));
      expect(changedPage, 1);
    });

    testWidgets('首页时上一页按钮禁用', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 25,
        page: 1,
        limit: 10,
      )));

      final btn = tester.widget<IconButton>(
        find.ancestor(
          of: find.byIcon(Icons.chevron_left),
          matching: find.byType(IconButton),
        ),
      );
      expect(btn.onPressed, isNull);
    });

    testWidgets('末页时下一页按钮禁用', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 25,
        page: 3,
        limit: 10,
      )));

      final btn = tester.widget<IconButton>(
        find.ancestor(
          of: find.byIcon(Icons.chevron_right),
          matching: find.byType(IconButton),
        ),
      );
      expect(btn.onPressed, isNull);
    });
  });

  group('DataTableWrapper — 搜索与工具栏', () {
    testWidgets('onSearch 非空时显示搜索框', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
        onSearch: (_) {},
      )));

      expect(find.byType(TextField), findsOneWidget);
      expect(find.text('搜索...'), findsOneWidget);
    });

    testWidgets('搜索框提交时回调 onSearch 且保留初始关键字', (tester) async {
      String? searched;
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
        keyword: 'admin',
        onSearch: (k) => searched = k,
      )));

      // 关键字回填：TextField 内部文本 + 渲染文本各一处
      expect(find.text('admin'), findsNWidgets(2));

      await tester.enterText(find.byType(TextField), 'ops');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      expect(searched, 'ops');
    });

    testWidgets('传入 actions 时渲染工具栏按钮', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
        actions: [
          TextButton(
            key: const Key('export-btn'),
            onPressed: () {},
            child: const Text('导出'),
          ),
        ],
      )));

      expect(find.byKey(const Key('export-btn')), findsOneWidget);
    });
  });
}
