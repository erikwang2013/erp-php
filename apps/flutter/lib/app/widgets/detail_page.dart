// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';
import '../services/api_service.dart';
import '../theme/app_tokens.dart';
import 'status_badge.dart';

/// 详情页通用骨架：GET 拉取 → 加载/错误/重试 → 内容区。
class DetailPage extends StatefulWidget {
  final String title;
  final String endpoint;
  final Widget Function(BuildContext context, Map<String, dynamic> data) builder;

  const DetailPage({super.key, required this.title, required this.endpoint, required this.builder});

  @override
  State<DetailPage> createState() => _DetailPageState();
}

class _DetailPageState extends State<DetailPage> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _data = null; _error = null; });
    try {
      final res = await ApiService.instance.get(widget.endpoint);
      if (!mounted) return; // 等待期间页面已被返回销毁
      setState(() => _data = Map<String, dynamic>.from(res['data'] ?? {}));
    } catch (e) {
      // friendlyError 翻译为当前语言文案；原始异常进 debugPrint 供排障
      if (!mounted) return;
      debugPrint('[detail] $widget.endpoint 加载失败: $e');
      setState(() => _error = ApiService.friendlyError(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: _error != null
          ? Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${AppL10n.of(context).commonLoadFailed}：$_error'),
                const SizedBox(height: 12),
                ElevatedButton(onPressed: _load, child: Text(AppL10n.of(context).commonRetry)),
              ]),
            )
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : widget.builder(context, _data!),
    );
  }
}

/// 分组卡片：标题 + 若干字段行/表格。
class DetailCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const DetailCard({super.key, required this.title, required this.children});

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 16),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // 区块标题 14/600 + divider(§5.5)
        Text(title,
            style: const TextStyle(
                fontSize: 14, fontWeight: FontWeight.w600)),
        const SizedBox(height: 12),
        const Divider(),
        const SizedBox(height: 12),
        ...children,
      ]),
    ),
  );
}

/// 单字段行：左侧标签、右侧值（空值显示 -）。
/// [onTap] 非空时值以可点击的链接样式渲染（引用卡等下钻入口）。
class DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final VoidCallback? onTap;

  const DetailRow({super.key, required this.label, required this.value, this.onTap});

  @override
  Widget build(BuildContext context) {
    final hint = AppColors.of(context).textHint;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // label 12/hint、值 14/primary;空值「-」用 hint(§5.5)
        SizedBox(
            width: 140,
            child: Text(label,
                style: TextStyle(fontSize: 12, color: hint))),
        Expanded(child: _value(context, hint)),
      ]),
    );
  }

  Widget _value(BuildContext context, Color hint) {
    if (value.isEmpty) {
      return Text('-', style: TextStyle(color: hint));
    }
    if (onTap == null) return Text(value);
    return InkWell(
      onTap: onTap,
      child: Text(value, style: TextStyle(color: Theme.of(context).colorScheme.primary)),
    );
  }
}

/// 明细表格：columns 为（表头, 数据 key）列表，rows 内以 key 取值。
/// [cell] 可选：key 为列数据 key，返回该格自定义 Widget（返回 null 时回落
/// 纯文本默认值）。
class DetailItemsTable extends StatelessWidget {
  final List<(String, String)> columns;
  final List<Map<String, dynamic>> rows;
  final Widget? Function(Map<String, dynamic> row, String key)? cell;

  const DetailItemsTable({super.key, required this.columns, required this.rows, this.cell});

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const Text('-');
    return SizedBox(
      width: double.infinity,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        // 明细子表密集档:行 40、表头 36(§5.5)
        child: DataTableTheme(
          data: const DataTableThemeData(
            dataRowMinHeight: 40,
            dataRowMaxHeight: 40,
            headingRowHeight: 36,
          ),
          child: DataTable(
            columns: [for (final c in columns) DataColumn(label: Text(c.$1))],
            rows: [
              for (final r in rows)
                DataRow(cells: [
                  for (final c in columns)
                    DataCell(cell?.call(r, c.$2) ??
                        Text('${r[c.$2] ?? ''}')),
                ]),
            ],
          ),
        ),
      ),
    );
  }
}

/// 状态徽标行（label 左列 + StatusBadge），text 空时整行隐藏。各页状态色
/// 自持（§2.4 域色约定），文本与列表页 chips 同 key 同源。
Widget detailStatusRow(BuildContext context,
    {required String label,
    required String text,
    required Color bg,
    required Color fg}) {
  if (text.isEmpty) return const SizedBox.shrink();
  return Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(
          width: 140,
          child: Text(label,
              style: TextStyle(fontSize: 12, color: AppColors.of(context).textHint))),
      StatusBadge(label: text, bg: bg, fg: fg),
    ]),
  );
}

/// 从 data 取值构造字段行（空值显示 -）。
DetailRow detailRow(Map<String, dynamic> data, String label, String key) =>
    DetailRow(label: label, value: '${data[key] ?? ''}');

/// 关联对象展示：优先取关联关系（order.code / supplier.name 等）名称，否则回退原始 ID。
String detailRelName(Map<String, dynamic> data, String relKey, String idKey) {
  final rel = data[relKey];
  if (rel is Map && (rel['name'] ?? rel['code'] ?? '') != '') {
    return '${rel['name'] ?? rel['code']}';
  }
  return '${data[idKey] ?? ''}';
}
