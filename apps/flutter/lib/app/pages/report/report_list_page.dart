// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

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
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/report', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/report', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/report/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['name'] ?? row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/report/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 执行报表：POST /admin/report/{id}/execute，再 GET /admin/report/{id}/result 展示结果。
  Future<void> _execute(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    try {
      final exec = await ApiService.instance.post('/admin/v1/report/${row['id']}/execute');
      final execData = Map<String, dynamic>.from(exec['data'] ?? {});
      final datasetId = execData['dataset_id'];
      final res = await ApiService.instance.get('/admin/v1/report/${row['id']}/result', params: {
        if (datasetId != null) 'dataset_id': '$datasetId',
      });
      final d = Map<String, dynamic>.from(res['data'] ?? {});
      final rawData = d['data'];
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(l10n.reportResultTitle('${row['name'] ?? row['code'] ?? ''}')),
          content: SizedBox(
            width: 720,
            child: SingleChildScrollView(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                Text(l10n.reportFieldDatasetId('${d['dataset_id'] ?? datasetId ?? '-'}')),
                const SizedBox(height: 4),
                Text(l10n.reportFieldRowCount('${d['rows_count'] ?? (rawData is List ? rawData.length : '-')}')),
                const SizedBox(height: 4),
                if (d['generated_at'] != null) Text(l10n.reportFieldGeneratedAt('${d['generated_at']}')),
                const SizedBox(height: 12),
                if (rawData is List && rawData.isNotEmpty) _resultTable(rawData)
                else if (rawData is List)
                  Text(l10n.reportNoRows)
                else
                  Text(l10n.reportFieldResult('$rawData')),
              ]),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(l10n.commonClose)),
          ],
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.reportExecuteFailedMsg('$e'))));
      }
    }
  }

  /// 把报表结果行渲染为通用表格（列名取第一行 keys）。
  Widget _resultTable(List<dynamic> rawData) {
    final first = rawData.first;
    if (first is! Map || first.isEmpty) return Text(AppL10n.current.reportNoRows);
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

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.fieldName, required: true),
      FormFieldConfig(name: 'code', label: l10n.fieldCode),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldName, l10n.fieldCode, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldName: r['name'] ?? '',
      l10n.fieldCode: r['code'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.play_arrow, size: 18, color: Colors.teal),
          tooltip: l10n.reportExecute, onPressed: () => _execute(r)),
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
      ]),
    };
  }

}
