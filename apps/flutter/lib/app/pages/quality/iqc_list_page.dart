// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class IqcListPage extends StatefulWidget {
  const IqcListPage({super.key});
  @override
  State<IqcListPage> createState() => _IqcListPageState();
}

class _IqcListPageState extends State<IqcListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/quality/iqc', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/quality/iqc', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/quality/iqc/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/quality/iqc/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'code', label: '检验单号', required: true),
    FormFieldConfig(name: 'receiving_id', label: '收货单ID', type: FormFieldType.number),
    FormFieldConfig(name: 'product_id', label: '商品ID', type: FormFieldType.number),
    FormFieldConfig(name: 'standard_id', label: '检验标准ID', type: FormFieldType.number),
    FormFieldConfig(name: 'inspected_qty', label: '检验数量', type: FormFieldType.number),
    FormFieldConfig(name: 'passed_qty', label: '合格数量', type: FormFieldType.number),
    FormFieldConfig(name: 'rejected_qty', label: '不合格数量', type: FormFieldType.number),
    FormFieldConfig(name: 'result', label: '检验结果', type: FormFieldType.dropdown, options: ['pass', 'reject']),
    FormFieldConfig(name: 'inspector', label: '检验员'),
    FormFieldConfig(name: 'remark', label: '备注'),
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

  List<String> _columns() => ['检验单号', '商品ID', '检验/合格/不合格', '结果', '检验员', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '检验单号': r['code'] ?? '',
    '商品ID': r['product_id'] ?? '',
    '检验/合格/不合格': '${r['inspected_qty'] ?? 0}/${r['passed_qty'] ?? 0}/${r['rejected_qty'] ?? 0}',
    '结果': r['result'] ?? '',
    '检验员': r['inspector'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
