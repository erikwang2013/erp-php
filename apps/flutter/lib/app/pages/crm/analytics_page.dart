// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
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
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/crm/analytics/report', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _generate() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmAnalyticsGenerate, fields: [
      FormFieldConfig(name: 'name', label: l10n.crmAnalyticsReportName, required: true),
      FormFieldConfig(name: 'type', label: l10n.crmAnalyticsReportType, required: true, type: FormFieldType.dropdown,
          options: const ['customer', 'order', 'revenue', 'activity', 'retention']),
      FormFieldConfig(name: 'period_year', label: l10n.crmAnalyticsYear, required: true, type: FormFieldType.number),
      FormFieldConfig(name: 'period_value', label: l10n.crmAnalyticsPeriodValue, required: true, type: FormFieldType.number),
      FormFieldConfig(name: 'period_type', label: l10n.crmAnalyticsPeriodType, required: true, type: FormFieldType.dropdown,
          options: [
            '1=${l10n.crmAnalyticsMonth}',
            '2=${l10n.crmAnalyticsQuarter}',
            '3=${l10n.crmAnalyticsYearUnit}',
          ]),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/analytics/generate', data: {
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
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmAnalyticsNewMetric, fields: [
      FormFieldConfig(name: 'name', label: l10n.crmAnalyticsMetricName, required: true),
      FormFieldConfig(name: 'key', label: l10n.crmAnalyticsMetricKey, required: true),
      FormFieldConfig(name: 'type', label: l10n.crmAnalyticsMetricType, required: true, type: FormFieldType.dropdown,
          options: const ['count', 'ratio', 'average', 'sum']),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/analytics/metric', data: data);
      _load(); return true;
    });
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },

    actions: [
      ElevatedButton.icon(onPressed: _generate, icon: const Icon(Icons.insights, size: 18),
        label: Text(AppL10n.of(context).crmAnalyticsGenerate)),
      ElevatedButton.icon(onPressed: _createMetric, icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).crmAnalyticsNewMetric)),
    ],
  );

  List<String> _columns() => [AppL10n.current.crmName, AppL10n.current.crmCode];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.crmName: r['name'] ?? '',
    AppL10n.current.crmCode: r['code'] ?? '',
  };

}
