// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 列表页表格容器(主文档 §5.2):搜索+筛选+操作行 → 加载/错误/空/表格三态
// → 分页;移动(<768)行高 56,桌面 48。签名保持对外不变。
import 'package:flutter/material.dart';
import 'package:data_table_2/data_table_2.dart';
import '../l10n/app_l10n.dart';

class DataTableWrapper extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final int total, page, limit;
  final bool loading;

  /// 非空时显示错误态(含重试按钮),与空数据态区分。
  final String? error;
  final VoidCallback? onRetry;

  final ValueChanged<int>? onPageChanged;
  final ValueChanged<String>? onSearch;

  /// Current search keyword; the search box is seeded with it so the text
  /// survives reloads.
  final String keyword;
  final Widget? filterBar;
  final List<Widget>? actions;

  const DataTableWrapper({
    super.key,
    required this.columns,
    required this.rows,
    required this.total,
    required this.page,
    required this.limit,
    this.loading = false,
    this.error,
    this.onRetry,
    this.onPageChanged,
    this.onSearch,
    this.keyword = '',
    this.filterBar,
    this.actions,
  });

  @override
  Widget build(BuildContext context) {
    // limit<=0(如整页取数、无分页调用方传 0)时 0/0=NaN,.ceil() 抛异常 → 兜底为 1
    final tp = limit <= 0 ? 1 : (total / limit).ceil();
    return LayoutBuilder(builder: (context, constraints) {
      final compact = constraints.maxWidth < 768;
      final scheme = Theme.of(context).colorScheme;
      final toolbar = <Widget>[
        if (onSearch != null)
          SizedBox(
            width: compact ? double.infinity : 240,
            child: _SearchField(initialText: keyword, onSearch: onSearch),
          ),
        if (filterBar != null) ...[const SizedBox(width: 12), filterBar!],
        const Spacer(),
        ...?actions,
      ];
      return Column(children: [
        if (onSearch != null || actions != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(children: toolbar),
          ),
        Expanded(child: loading
          // 加载态:3 行骨架(行高同数据行,surface_alt 50%),禁整页菊花(§5.2)
          ? Column(children: [
              for (var i = 0; i < 3; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Container(
                    height: compact ? 56 : 48,
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainerHighest.withValues(alpha: 0.5),
                      borderRadius: BorderRadius.circular(6),
                    ),
                  ),
                ),
            ])
          : error != null
            // 加载失败与「暂无数据」必须可区分:错误态带重试按钮
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.error_outline,
                    color: scheme.error, size: 36),
                const SizedBox(height: 8),
                Text(error!,
                    style: TextStyle(fontSize: 14, color: scheme.error)),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: onRetry,
                  style: OutlinedButton.styleFrom(
                      minimumSize: const Size(72, 32)),
                  icon: const Icon(Icons.refresh, size: 16),
                  label: Text(AppL10n.of(context).commonRetry),
                ),
              ]))
            : rows.isEmpty
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.inbox_outlined,
                    size: 48, color: scheme.onSurfaceVariant),
                const SizedBox(height: 12),
                Text(AppL10n.of(context).commonNoData,
                    style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: scheme.onSurfaceVariant)),
              ]))
            : _DataTable(columns: columns, rows: rows, compact: compact)),
        if (tp > 1)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Row(mainAxisAlignment: MainAxisAlignment.end, children: [
              Text(AppL10n.of(context).commonTotalPages(total),
                  style: TextStyle(fontSize: 13, color: scheme.onSurfaceVariant)),
              const SizedBox(width: 16),
              IconButton(
                  icon: const Icon(Icons.chevron_left, size: 20),
                  onPressed:
                      page > 1 ? () => onPageChanged?.call(page - 1) : null),
              Text('$page/${tp > 0 ? tp : 1}',
                  style: TextStyle(fontSize: 13, color: scheme.onSurface)),
              IconButton(
                  icon: const Icon(Icons.chevron_right, size: 20),
                  onPressed:
                      page < tp ? () => onPageChanged?.call(page + 1) : null),
            ]),
          ),
      ]);
    });
  }
}

/// 断点行高:移动 56 / 桌面 48(§4/§5.2),表头样式走全局 dataTableTheme。
class _DataTable extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final bool compact;

  const _DataTable(
      {required this.columns, required this.rows, required this.compact});

  @override
  Widget build(BuildContext context) {
    final table = DataTable2(
      columnSpacing: 12,
      horizontalMargin: 12,
      minWidth: columns.length * 130.0,
      columns: columns
          .map((c) => DataColumn2(
              label: Text(c, style: const TextStyle(fontWeight: FontWeight.w600))))
          .toList(),
      rows: rows
          .map((r) => DataRow2(
              cells: columns
                  .map((c) => DataCell(r[c] is Widget
                      ? r[c] as Widget
                      : Text('${r[c] ?? ''}')))
                  .toList()))
          .toList(),
    );
    if (!compact) return table;
    return DataTableTheme(
      data: DataTableThemeData(
        dataRowMinHeight: 56,
        dataRowMaxHeight: 56,
      ),
      child: table,
    );
  }
}

/// Search input that keeps its text across wrapper rebuilds.
class _SearchField extends StatefulWidget {
  final String initialText;
  final ValueChanged<String>? onSearch;
  const _SearchField({required this.initialText, this.onSearch});

  @override
  State<_SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<_SearchField> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.initialText);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _controller,
      // 边框/圆角/聚焦色走全局 inputDecorationTheme(§4:r6/primary 1.5)
      decoration: InputDecoration(
        hintText: AppL10n.of(context).commonSearchHint,
        prefixIcon: const Icon(Icons.search, size: 20),
      ),
      onSubmitted: widget.onSearch,
    );
  }
}
