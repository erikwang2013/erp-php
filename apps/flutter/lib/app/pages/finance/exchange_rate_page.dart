// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 汇率管理页 — 覆盖 GET/POST/PUT/DELETE /admin/finance/exchange-rate
/// 后端字段: from_currency_id / to_currency_id / rate / effective_date
class ExchangeRatePage extends StatefulWidget {
  const ExchangeRatePage({super.key});
  @override
  State<ExchangeRatePage> createState() => _ExchangeRatePageState();
}

class _ExchangeRatePageState extends State<ExchangeRatePage> {
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
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/finance/exchange-rate', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultDate = '${now.year}-${pad(now.month)}-${pad(now.day)}';
    return [
      FormFieldConfig(name: 'from_currency_id', label: '原币ID', required: true, hint: '币种列表中的数字ID，如 61000000000000002=USD'),
      FormFieldConfig(name: 'to_currency_id', label: '目标币ID', required: true, hint: '如 61000000000000001=CNY'),
      FormFieldConfig(name: 'rate', label: '汇率', required: true, type: FormFieldType.number, hint: '如 7.250000'),
      FormFieldConfig(name: 'effective_date', label: '生效日期', required: true, initialValue: defaultDate,
        hint: '格式 YYYY-MM-DD'),
    ];
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增汇率', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/finance/exchange-rate', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑汇率', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/finance/exchange-rate/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除该汇率记录吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/finance/exchange-rate/${row['id']}', data: {'password': password});
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
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增汇率')),
    ],
  );

  List<String> _columns() => ['原币ID', '目标币ID', '汇率', '生效日期', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '原币ID': r['from_currency_id'] ?? '',
    '目标币ID': r['to_currency_id'] ?? '',
    '汇率': r['rate'] ?? '',
    '生效日期': r['effective_date'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
