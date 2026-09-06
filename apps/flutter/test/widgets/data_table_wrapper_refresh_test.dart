// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// DataTableWrapper 页面刷新测试：工具栏刷新按钮点击触发 onRefresh、
// 内容区下拉触发 onRefresh（表格态与空态各一例）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/data_table_wrapper.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(
    home: Scaffold(body: SizedBox(height: 600, width: 1000, child: child)),
  );

  const columns = ['ID', '用户名', '状态'];
  final rows = [
    {'ID': 1, '用户名': 'admin', '状态': '启用'},
    {'ID': 2, '用户名': 'ops', '状态': '禁用'},
  ];

  testWidgets('工具栏刷新按钮点击触发 onRefresh', (tester) async {
    var calls = 0;
    await tester.pumpWidget(
      wrap(
        DataTableWrapper(
          columns: columns,
          rows: rows,
          total: 2,
          page: 1,
          limit: 10,
          onRefresh: () async => calls++,
        ),
      ),
    );

    await tester.tap(find.byIcon(Icons.refresh));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });

  testWidgets('loading 中工具栏刷新按钮禁用', (tester) async {
    var calls = 0;
    await tester.pumpWidget(
      wrap(
        DataTableWrapper(
          columns: columns,
          rows: rows,
          total: 2,
          page: 1,
          limit: 10,
          loading: true,
          onRefresh: () async => calls++,
        ),
      ),
    );

    final btn = tester.widget<IconButton>(
      find.ancestor(
        of: find.byIcon(Icons.refresh),
        matching: find.byType(IconButton),
      ),
    );
    expect(btn.onPressed, isNull);
  });

  testWidgets('数据表内容区下拉触发 onRefresh', (tester) async {
    var calls = 0;
    await tester.pumpWidget(
      wrap(
        DataTableWrapper(
          columns: columns,
          rows: rows,
          total: 2,
          page: 1,
          limit: 10,
          onRefresh: () async => calls++,
        ),
      ),
    );

    // 从行文本处向下拖动超过触发阈值
    await tester.fling(find.text('admin'), const Offset(0, 300), 1000);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });

  testWidgets('空数据态下拉触发 onRefresh', (tester) async {
    var calls = 0;
    await tester.pumpWidget(
      wrap(
        DataTableWrapper(
          columns: columns,
          rows: [],
          total: 0,
          page: 1,
          limit: 10,
          onRefresh: () async => calls++,
        ),
      ),
    );

    await tester.fling(find.text('暂无数据'), const Offset(0, 300), 1000);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });
}
