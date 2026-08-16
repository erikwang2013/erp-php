// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class CrmQuotationListPage extends StatefulWidget {
  const CrmQuotationListPage({super.key});
  @override
  State<CrmQuotationListPage> createState() => _CrmQuotationListPageState();
}

class _CrmQuotationListPageState extends State<CrmQuotationListPage> {
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
      
      final res = await ApiService.instance.get('/admin/crm/quotation', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/crm/quotation', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/crm/quotation/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/crm/quotation/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 报价转合同：填写合同编号/名称/备注，调用 POST /admin/crm/quotation/{id}/to-contract。
  Future<void> _toContract(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '报价转合同', fields: const [
      FormFieldConfig(name: 'code', label: '合同编号', hint: '留空自动生成 CT+时间戳'),
      FormFieldConfig(name: 'name', label: '合同名称', hint: '留空默认 合同-报价单号'),
      FormFieldConfig(name: 'remark', label: '备注', type: FormFieldType.multiline),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/crm/quotation/${row['id']}/to-contract', data: {
        'code': data['code']?.trim(),
        'name': data['name']?.trim(),
        'remark': data['remark']?.trim() ?? '',
      });
      _load(); return true;
    });
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
      IconButton(icon: const Icon(Icons.handshake, size: 18, color: Colors.teal),
        tooltip: '转合同', onPressed: () => _toContract(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

}
