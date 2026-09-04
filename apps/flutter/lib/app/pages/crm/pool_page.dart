// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';

class PoolPage extends StatefulWidget {
  const PoolPage({super.key});
  @override
  State<PoolPage> createState() => _PoolPageState();
}

class _PoolPageState extends State<PoolPage> {
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
      
      final res = await ApiService.instance.get('/admin/v1/crm/pool', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _claim(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '领取客户', fields: const [
      FormFieldConfig(name: 'remark', label: '备注', hint: '选填'),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/pool/claim/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _release(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '释放回公海', fields: const [
      FormFieldConfig(name: 'remark', label: '备注', hint: '选填'),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/pool/release/${row['id']}', data: data);
      _load(); return true;
    });
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
  );

  List<String> _columns() => ['名称', '编码', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.person_add, size: 18), tooltip: '领取', onPressed: () => _claim(r)),
      IconButton(icon: const Icon(Icons.logout, size: 18, color: Colors.orange), tooltip: '释放回公海', onPressed: () => _release(r)),
    ]),
  };

}
