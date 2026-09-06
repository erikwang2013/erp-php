// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// DataTableWrapper 通用组件测试：列头/行数据渲染、空态、加载态、
// 分页交互与搜索框、视觉 3.0 加性参数(页头行/斑马纹/主列强调)。
import 'package:data_table_2/data_table_2.dart';
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
      )));

      expect(find.text('暂无数据'), findsOneWidget);
    });

    testWidgets('loading 时显示三行骨架屏(§5.2,禁整页菊花)', (tester) async {
      await tester.pumpWidget(wrap(const DataTableWrapper(
        columns: columns,
        rows: [],
        total: 0,
        page: 1,
        limit: 10,
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
        loading: true,
      )));

      // 骨架行:行高同数据行、surface_alt 50% 透明底,共 3 行
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.text('暂无数据'), findsNothing);
      final skeletons = find.byWidgetPredicate((w) =>
          w is Container &&
          w.decoration is BoxDecoration &&
          (w.decoration as BoxDecoration).color?.a == 0.5);
      expect(skeletons, findsNWidgets(3));
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
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

  group('DataTableWrapper — 视觉 3.0 加性参数', () {
    testWidgets('pageTitle 渲染模块色竖条+标题+「共 N 条」;缺省完全不渲染', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 5,
        page: 1,
        limit: 10,
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
        pageTitle: '订单列表',
        moduleKey: 'wms',
      )));

      // 8×16 竖条取 wms 模块色(与 HOS V0-h 同源 chart_6 #13C2C2)
      final bar = tester.widget<Container>(find.byWidgetPredicate(
        (w) =>
            w is Container &&
            w.decoration is BoxDecoration &&
            (w.decoration as BoxDecoration).color == const Color(0xFF13C2C2),
      ));
      expect(bar.constraints?.maxWidth, 8);
      expect(bar.constraints?.maxHeight, 16);
      expect(find.text('订单列表'), findsOneWidget);
      // total 5/limit 10 无分页脚,「共 N 条」仅页头一处
      expect(find.text('共 5 条'), findsOneWidget);
    });

    testWidgets('pageTitle 不传时旧调用渲染完全不变(无竖条/无页头计数)', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 5,
        page: 1,
        limit: 10,
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
      )));

      expect(find.text('共 5 条'), findsNothing, reason: '未开页头不应渲染计数');
      final bars = find.byWidgetPredicate(
        (w) =>
            w is Container &&
            w.decoration is BoxDecoration &&
            w.constraints?.maxWidth == 8,
      );
      expect(bars, findsNothing, reason: '未开页头不应渲染模块色竖条');
    });

    testWidgets('斑马纹:偶行 surfaceAlt 低透明叠底、表头行同底 40%', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
      )));

      final table = tester.widget<DataTable2>(find.byType(DataTable2));
      final rows2 = table.rows;
      expect(rows2, hasLength(2));
      // 第 1 行无叠底
      expect(rows2[0].color?.resolve(<WidgetState>{}), isNull);
      // 第 2 行 surfaceAlt @ 35%
      final zebra = rows2[1].color?.resolve(<WidgetState>{});
      expect(zebra, isNotNull);
      expect(zebra!.a, closeTo(0.35, 0.001));

      final heading = table.headingRowColor?.resolve(<WidgetState>{});
      expect(heading, isNotNull);
      expect(heading!.a, closeTo(0.4, 0.001));
    });

    testWidgets('primaryColumnIndex 主列文本 w600,其余列原样', (tester) async {
      await tester.pumpWidget(wrap(DataTableWrapper(
        columns: columns,
        rows: rows,
        total: 2,
        page: 1,
        limit: 10,
        // 离线渲染测试:非网络数据源,按契约显式传 null
        onRefresh: null,
        primaryColumnIndex: 1,
      )));

      // 主列(用户名)两行 w600
      for (final name in ['admin', 'ops']) {
        final t = tester.widget<Text>(find.text(name));
        expect(t.style?.fontWeight, FontWeight.w600);
      }
      // 非主列(状态)不强调
      final status = tester.widget<Text>(find.text('启用'));
      expect(status.style?.fontWeight, isNot(FontWeight.w600));
    });
  });

  group('moduleAccent 模块→模块色映射(与 HOS V0-h 同源,零新增 hex)', () {
    test('hr/tms 紫、oms/wms 青、purchase 橙、mfg 绿、finance 红、其余回退蓝', () {
      expect(moduleAccent('hr'), const Color(0xFF722ED1));
      expect(moduleAccent('tms'), const Color(0xFF722ED1));
      expect(moduleAccent('oms'), const Color(0xFF13C2C2));
      expect(moduleAccent('wms'), const Color(0xFF13C2C2));
      expect(moduleAccent('purchase'), const Color(0xFFFA8C16));
      expect(moduleAccent('mfg'), const Color(0xFF52C41A));
      expect(moduleAccent('finance'), const Color(0xFFFF4D4F));
      expect(moduleAccent('system'), const Color(0xFF1677FF));
      expect(moduleAccent('sales'), const Color(0xFF1677FF));
      expect(moduleAccent('whatever'), const Color(0xFF1677FF));
    });
  });
}
