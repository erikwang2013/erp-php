// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class NonconformityListPage extends StatefulWidget {
  const NonconformityListPage({super.key});
  @override
  State<NonconformityListPage> createState() => _NonconformityListPageState();
}

class _NonconformityListPageState extends State<NonconformityListPage> {
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

      final res = await ApiService.instance.get('/admin/quality/nonconformity', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/quality/nonconformity', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/quality/nonconformity/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/quality/nonconformity/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'code', label: '不合格编号', required: true),
    FormFieldConfig(name: 'source_type', label: '来源类型', type: FormFieldType.dropdown, options: ['iqc', 'ipqc', 'oqc']),
    FormFieldConfig(name: 'source_id', label: '来源记录ID', type: FormFieldType.number),
    FormFieldConfig(name: 'product_id', label: '商品ID', type: FormFieldType.number),
    FormFieldConfig(name: 'defect_type', label: '缺陷类型', required: true),
    FormFieldConfig(name: 'defect_qty', label: '缺陷数量', type: FormFieldType.number),
    FormFieldConfig(name: 'severity', label: '严重程度', type: FormFieldType.dropdown, options: ['minor', 'major', 'critical']),
    FormFieldConfig(name: 'disposition', label: '处置方式', type: FormFieldType.dropdown, options: ['pending', 'return', 'repair', 'scrap', 'accept']),
    FormFieldConfig(name: 'root_cause', label: '根本原因', type: FormFieldType.multiline),
    FormFieldConfig(name: 'corrective_action', label: '纠正措施', type: FormFieldType.multiline),
    FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown, options: ['0', '1', '2']),
    FormFieldConfig(name: 'reported_by', label: '报告人'),
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

  List<String> _columns() => ['编号', '来源', '商品ID', '缺陷类型', '数量', '严重程度', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '编号': r['code'] ?? '',
    '来源': r['source_type'] ?? '',
    '商品ID': r['product_id'] ?? '',
    '缺陷类型': r['defect_type'] ?? '',
    '数量': r['defect_qty'] ?? '',
    '严重程度': r['severity'] ?? '',
    '状态': r['status'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
