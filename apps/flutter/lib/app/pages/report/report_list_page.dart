// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class ReportListPage extends StatefulWidget {
  const ReportListPage({super.key});
  @override
  State<ReportListPage> createState() => _ReportListPageState();
}

class _ReportListPageState extends State<ReportListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      
      final res = await ApiService.instance.get('/admin/report', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/report', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/report/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/report/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 执行报表：POST /admin/report/{id}/execute，再 GET /admin/report/{id}/result 展示结果。
  Future<void> _execute(Map<String, dynamic> row) async {
    try {
      final exec = await ApiService.instance.post('/admin/report/${row['id']}/execute');
      final execData = Map<String, dynamic>.from(exec['data'] ?? {});
      final datasetId = execData['dataset_id'];
      final res = await ApiService.instance.get('/admin/report/${row['id']}/result', params: {
        if (datasetId != null) 'dataset_id': '$datasetId',
      });
      final d = Map<String, dynamic>.from(res['data'] ?? {});
      final rawData = d['data'];
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text('报表结果：${row['name'] ?? row['code'] ?? ''}'),
          content: SizedBox(
            width: 720,
            child: SingleChildScrollView(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                Text('数据集ID: ${d['dataset_id'] ?? datasetId ?? '-'}'),
                const SizedBox(height: 4),
                Text('结果行数: ${d['rows_count'] ?? (rawData is List ? rawData.length : '-')}'),
                const SizedBox(height: 4),
                if (d['generated_at'] != null) Text('生成时间: ${d['generated_at']}'),
                const SizedBox(height: 12),
                if (rawData is List && rawData.isNotEmpty) _resultTable(rawData)
                else if (rawData is List)
                  const Text('查询成功，暂无数据行')
                else
                  Text('结果: $rawData'),
              ]),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('关闭')),
          ],
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('执行失败：$e')));
      }
    }
  }

  /// 把报表结果行渲染为通用表格（列名取第一行 keys）。
  Widget _resultTable(List<dynamic> rawData) {
    final first = rawData.first;
    if (first is! Map || first.isEmpty) return const Text('查询成功，暂无数据行');
    final keys = first.keys.toList();
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columnSpacing: 20,
        columns: [for (final k in keys) DataColumn(label: Text(k))],
        rows: [
          for (final item in rawData)
            if (item is Map)
              DataRow(cells: [
                for (final k in keys) DataCell(Text('${item[k] ?? ''}')),
              ]),
        ],
      ),
    );
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: '名称', required: true),
    FormFieldConfig(name: 'code', label: '编码'),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['名称', '编码', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.play_arrow, size: 18, color: Colors.teal),
        tooltip: '执行', onPressed: () => _execute(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

}
