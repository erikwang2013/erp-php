// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class MaintenancePlanPage extends StatefulWidget {
  const MaintenancePlanPage({super.key});
  @override
  State<MaintenancePlanPage> createState() => _MaintenancePlanPageState();
}

class _MaintenancePlanPageState extends State<MaintenancePlanPage> {
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

      final res = await ApiService.instance.get('/admin/v1/eam/maintenance', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/maintenance', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/maintenance/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/maintenance/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'equipment_id', label: '设备ID', type: FormFieldType.number, required: true),
    FormFieldConfig(name: 'name', label: '计划名称', required: true),
    FormFieldConfig(name: 'frequency', label: '保养频率', required: true, type: FormFieldType.dropdown, options: ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']),
    FormFieldConfig(name: 'last_date', label: '上次保养日期'),
    FormFieldConfig(name: 'next_date', label: '下次保养日期'),
    FormFieldConfig(name: 'assignee', label: '负责人'),
    FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown, options: ['0', '1']),
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

  List<String> _columns() => ['计划名称', '设备ID', '频率', '下次日期', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '计划名称': r['name'] ?? '',
    '设备ID': r['equipment_id'] ?? '',
    '频率': r['frequency'] ?? '',
    '下次日期': r['next_date'] ?? '',
    '状态': r['status'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
