// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 列表表格渲染体(自 data_table_wrapper.dart 析出,原样搬移,受 500 行上限约束):
// 断点行高 + 视觉 3.0 表头/斑马纹/主列强调 + 右对齐列等宽数字。
// 仅由 DataTableWrapper 内部使用,页面请直接用 DataTableWrapper。
import 'package:flutter/material.dart';
import 'package:data_table_2/data_table_2.dart';
import '../theme/app_tokens.dart';

/// 断点行高:移动 56 / 桌面 44(§4/§5.2 + 视觉 3.0),表头样式走全局 dataTableTheme;
/// 视觉 3.0:表头行底 surfaceAlt 40%、偶行斑马纹低透明叠底、主业务列 w600。
class DataTableView extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final bool compact;

  /// 右对齐列索引;走 data_table_2 的 numeric 列语义(表头与单元格右对齐)。
  final List<int> rightAlign;

  /// 主业务列索引(w600 强调);-1 不强调。
  final int primaryColumn;

  const DataTableView({
    super.key,
    required this.columns,
    required this.rows,
    required this.compact,
    this.rightAlign = const [],
    this.primaryColumn = -1,
  });

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    final right = rightAlign.contains;
    final table = DataTable2(
      // 表头行底:surfaceAlt 40%(视觉 3.0)
      headingRowColor: WidgetStatePropertyAll(
        c.surfaceAlt.withValues(alpha: 0.4),
      ),
      columnSpacing: 12,
      horizontalMargin: 12,
      minWidth: columns.length * 130.0,
      columns: [
        for (var i = 0; i < columns.length; i++)
          DataColumn2(
            numeric: right(i),
            label: Text(
              columns[i],
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
      ],
      rows: [
        for (var i = 0; i < rows.length; i++)
          DataRow2(
            // 斑马纹:偶行(第 2/4/6…)surfaceAlt 低透明叠底
            color: i.isOdd
                ? WidgetStatePropertyAll(c.surfaceAlt.withValues(alpha: 0.35))
                : null,
            cells: [
              for (var j = 0; j < columns.length; j++)
                DataCell(
                  _cell(
                    rows[i][columns[j]],
                    right(j),
                    emphasize: j == primaryColumn,
                  ),
                ),
            ],
          ),
      ],
    );
    if (!compact) return table;
    return DataTableTheme(
      data: DataTableThemeData(
        dataRowMinHeight: AppMetrics.rowMobile,
        dataRowMaxHeight: AppMetrics.rowMobile,
      ),
      child: table,
    );
  }

  /// 单元格:Widget 原样保留(不包不套);纯文本在右对齐列补等宽数字特性
  /// (§3:数字一律 tabular figures),主业务列文本 w600 强调(视觉 3.0)。
  /// 文字色/字号仍走 dataTableTheme。
  Widget _cell(dynamic v, bool right, {bool emphasize = false}) {
    if (v is Widget) return v;
    TextStyle? style;
    if (emphasize) {
      style = const TextStyle(fontWeight: FontWeight.w600);
    }
    if (right) {
      const tabular = TextStyle(fontFeatures: [FontFeature.tabularFigures()]);
      style = style == null
          ? tabular
          : style.copyWith(fontFeatures: const [FontFeature.tabularFigures()]);
    }
    return Text('${v ?? ''}', style: style);
  }
}
