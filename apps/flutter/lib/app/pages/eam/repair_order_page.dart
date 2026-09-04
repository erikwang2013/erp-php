// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class RepairOrderPage extends StatefulWidget {
  const RepairOrderPage({super.key});
  @override
  State<RepairOrderPage> createState() => _RepairOrderPageState();
}

class _RepairOrderPageState extends State<RepairOrderPage> {
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

      final res = await ApiService.instance.get('/admin/v1/eam/repair', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/repair', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/repair/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/repair/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Future<void> _transition(Map<String, dynamic> row, String status) async {
    await ConfirmDialog.show(context, title: '状态流转', content: '确定要将工单「${row['code'] ?? ''}」流转为「$status」吗？', onConfirm: (password) async {
      await ApiService.instance.post('/admin/v1/eam/repair/${row['id']}/transition', data: {'status': status});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'code', label: '工单编码', required: true),
    FormFieldConfig(name: 'equipment_id', label: '设备ID', type: FormFieldType.number, required: true),
    FormFieldConfig(name: 'fault_description', label: '故障描述', type: FormFieldType.multiline, required: true),
    FormFieldConfig(name: 'repair_type', label: '维修类型', type: FormFieldType.dropdown, options: ['preventive', 'corrective', 'emergency']),
    FormFieldConfig(name: 'assignee', label: '维修人'),
    FormFieldConfig(name: 'start_date', label: '开始时间'),
    FormFieldConfig(name: 'end_date', label: '结束时间'),
    FormFieldConfig(name: 'cost', label: '维修费用', type: FormFieldType.number),
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

  List<String> _columns() => ['工单编码', '设备ID', '维修类型', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '工单编码': r['code'] ?? '',
    '设备ID': r['equipment_id'] ?? '',
    '维修类型': r['repair_type'] ?? '',
    '状态': r['status'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      if ((r['status'] ?? 'open') == 'open')
        IconButton(icon: const Icon(Icons.play_arrow, size: 18, color: Colors.orange), tooltip: '开始维修',
            onPressed: () => _transition(r, 'in_progress')),
      if ((r['status'] ?? 'open') == 'in_progress')
        IconButton(icon: const Icon(Icons.check, size: 18, color: Colors.green), tooltip: '完成',
            onPressed: () => _transition(r, 'completed')),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
