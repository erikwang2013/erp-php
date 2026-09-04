// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class EquipmentListPage extends StatefulWidget {
  const EquipmentListPage({super.key});
  @override
  State<EquipmentListPage> createState() => _EquipmentListPageState();
}

class _EquipmentListPageState extends State<EquipmentListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/eam/equipment', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/equipment', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/equipment/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/equipment/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'code', label: '设备编码', required: true),
    FormFieldConfig(name: 'name', label: '设备名称', required: true),
    FormFieldConfig(name: 'model', label: '型号'),
    FormFieldConfig(name: 'serial_number', label: '序列号'),
    FormFieldConfig(name: 'category', label: '设备分类'),
    FormFieldConfig(name: 'location', label: '存放位置'),
    FormFieldConfig(name: 'department_id', label: '部门ID', type: FormFieldType.number),
    FormFieldConfig(name: 'purchase_date', label: '购买日期'),
    FormFieldConfig(name: 'warranty_expiry', label: '保修到期'),
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

  List<String> _columns() => ['设备编码', '设备名称', '型号', '分类', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '设备编码': r['code'] ?? '',
    '设备名称': r['name'] ?? '',
    '型号': r['model'] ?? '',
    '分类': r['category'] ?? '',
    '状态': r['status'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
