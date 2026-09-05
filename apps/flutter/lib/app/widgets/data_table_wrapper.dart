// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:data_table_2/data_table_2.dart';
import '../l10n/app_l10n.dart';

class DataTableWrapper extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final int total, page, limit;
  final bool loading;

  /// 非空时显示错误态（含重试按钮），与空数据态区分。
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
    // limit<=0（如整页取数、无分页调用方传 0）时 0/0=NaN，.ceil() 抛异常 → 兜底为 1
    final tp = limit <= 0 ? 1 : (total / limit).ceil();
    return Column(children: [
      if (onSearch != null || actions != null)
        Padding(padding: const EdgeInsets.only(bottom: 8), child: Row(children: [
          if (onSearch != null) SizedBox(width: 280, child: _SearchField(initialText: keyword, onSearch: onSearch)),
          if (filterBar != null) ...[const SizedBox(width: 12), filterBar!],
          const Spacer(),
          ...?actions,
        ])),
      Expanded(child: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
          // 加载失败与「暂无数据」必须可区分：错误态带重试按钮
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.error_outline, color: Colors.red, size: 36),
              const SizedBox(height: 8),
              Text(error!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 12),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh, size: 16), label: Text(AppL10n.of(context).commonRetry)),
            ]))
          : rows.isEmpty
          ? Center(child: Text(AppL10n.of(context).commonNoData))
          : DataTable2(
              columnSpacing: 12, horizontalMargin: 12, minWidth: columns.length * 130.0,
              columns: columns.map((c) => DataColumn2(label: Text(c, style: const TextStyle(fontWeight: FontWeight.w600)))).toList(),
              rows: rows.map((r) => DataRow2(cells: columns.map((c) => DataCell(r[c] is Widget ? r[c] as Widget : Text('${r[c] ?? ''}'))).toList())).toList(),
            )),
      if (tp > 1) Padding(padding: const EdgeInsets.only(top: 8), child: Row(mainAxisAlignment: MainAxisAlignment.end, children: [
        Text(AppL10n.of(context).commonTotalPages(total), style: const TextStyle(fontSize: 13, color: Colors.grey)), const SizedBox(width: 16),
        IconButton(icon: const Icon(Icons.chevron_left, size: 20), onPressed: page > 1 ? () => onPageChanged?.call(page - 1) : null),
        Text('$page/${tp > 0 ? tp : 1}', style: const TextStyle(fontSize: 13)),
        IconButton(icon: const Icon(Icons.chevron_right, size: 20), onPressed: page < tp ? () => onPageChanged?.call(page + 1) : null),
      ])),
    ]);
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
  late final TextEditingController _controller = TextEditingController(text: widget.initialText);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _controller,
      decoration: InputDecoration(
        hintText: AppL10n.of(context).commonSearchHint,
        prefixIcon: const Icon(Icons.search, size: 20),
        isDense: true,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      ),
      onSubmitted: widget.onSearch,
    );
  }
}
