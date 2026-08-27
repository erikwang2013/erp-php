// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';

class CrmAnalyticsPage extends StatefulWidget {
  const CrmAnalyticsPage({super.key});
  @override
  State<CrmAnalyticsPage> createState() => _CrmAnalyticsPageState();
}

class _CrmAnalyticsPageState extends State<CrmAnalyticsPage> {
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
      
      final res = await ApiService.instance.get('/admin/crm/analytics/report', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _generate() async {
    await FormDialog.show(context, title: '生成报表', fields: const [
      FormFieldConfig(name: 'name', label: '报表名称', required: true),
      FormFieldConfig(name: 'type', label: '报表类型', required: true, type: FormFieldType.dropdown,
          options: ['customer', 'order', 'revenue', 'activity', 'retention']),
      FormFieldConfig(name: 'period_year', label: '年度', required: true, type: FormFieldType.number),
      FormFieldConfig(name: 'period_value', label: '期间值', required: true, type: FormFieldType.number),
      FormFieldConfig(name: 'period_type', label: '期间类型', required: true, type: FormFieldType.dropdown,
          options: ['1=月', '2=季', '3=年']),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/crm/analytics/generate', data: {
        'name': data['name'] ?? '',
        'type': data['type'] ?? '',
        'period_year': int.parse(data['period_year']!),
        'period_value': int.parse(data['period_value']!),
        'period_type': int.parse(data['period_type']!.split('=').first),
      });
      _load(); return true;
    });
  }

  Future<void> _createMetric() async {
    await FormDialog.show(context, title: '新建指标', fields: const [
      FormFieldConfig(name: 'name', label: '指标名称', required: true),
      FormFieldConfig(name: 'key', label: '指标键名', required: true),
      FormFieldConfig(name: 'type', label: '指标类型', required: true, type: FormFieldType.dropdown,
          options: ['count', 'ratio', 'average', 'sum']),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/crm/analytics/metric', data: data);
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
      ElevatedButton.icon(onPressed: _generate, icon: const Icon(Icons.insights, size: 18), label: const Text('生成报表')),
      ElevatedButton.icon(onPressed: _createMetric, icon: const Icon(Icons.add, size: 18), label: const Text('新建指标')),
    ],
  );

  List<String> _columns() => ['名称', '编码'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
  };

}
