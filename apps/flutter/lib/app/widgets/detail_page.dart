// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';
import '../services/api_service.dart';

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
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 12),
        ...children,
      ]),
    ),
  );
}

/// 单字段行：左侧标签、右侧值（空值显示 -）。
class DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const DetailRow({super.key, required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 140, child: Text(label, style: TextStyle(color: Colors.grey[600]))),
      Expanded(child: Text(value.isEmpty ? '-' : value)),
    ]),
  );
}

/// 明细表格：columns 为（表头, 数据 key）列表，rows 内以 key 取值。
class DetailItemsTable extends StatelessWidget {
  final List<(String, String)> columns;
  final List<Map<String, dynamic>> rows;

  const DetailItemsTable({super.key, required this.columns, required this.rows});

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const Text('-');
    return SizedBox(
      width: double.infinity,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          columns: [for (final c in columns) DataColumn(label: Text(c.$1))],
          rows: [
            for (final r in rows)
              DataRow(cells: [for (final c in columns) DataCell(Text('${r[c.$2] ?? ''}'))]),
          ],
        ),
      ),
    );
  }
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
