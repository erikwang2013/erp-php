// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 薪资项配置 — /admin/hr/salary-item（itemIndex/itemStore/itemUpdate/itemDestroy）
class SalaryItemPage extends StatefulWidget {
  const SalaryItemPage({super.key});
  @override
  State<SalaryItemPage> createState() => _SalaryItemPageState();
}

class _SalaryItemPageState extends State<SalaryItemPage> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/hr/salary-item');
      setState(() { _rows = List<Map<String, dynamic>>.from(res['data']['list'] ?? []); _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增薪资项', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/hr/salary-item', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑薪资项', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/hr/salary-item/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/hr/salary-item/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'code', label: '编码', required: true),
    FormFieldConfig(name: 'name', label: '名称', required: true),
    FormFieldConfig(name: 'type', label: '类型(0=固定 1=浮动)'),
    FormFieldConfig(name: 'is_taxable', label: '是否计税(0/1)'),
    FormFieldConfig(name: 'default_amount', label: '默认金额'),
  ];

  static String _typeText(dynamic t) => '${t ?? ''}' == '1' ? '浮动' : '固定';

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _rows.length, page: 1, limit: _rows.length, loading: _loading,
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增薪资项')),
    ],
  );

  List<String> _columns() => ['编码', '名称', '类型', '计税', '默认金额', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '编码': r['code'] ?? '',
    '名称': r['name'] ?? '',
    '类型': _typeText(r['type']),
    '计税': '${r['is_taxable'] ?? ''}' == '1' ? '是' : '否',
    '默认金额': r['default_amount'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
