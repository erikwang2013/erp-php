// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
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
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/v1/finance/exchange-rate', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultDate = '${now.year}-${pad(now.month)}-${pad(now.day)}';
    return [
      FormFieldConfig(name: 'from_currency_id', label: AppL10n.of(context).financeOriginCurrencyId, required: true, hint: AppL10n.of(context).financeOriginCurrencyHint),
      FormFieldConfig(name: 'to_currency_id', label: AppL10n.of(context).financeTargetCurrencyId, required: true, hint: AppL10n.of(context).financeTargetCurrencyHint),
      FormFieldConfig(name: 'rate', label: AppL10n.of(context).financeRate, required: true, type: FormFieldType.number, hint: AppL10n.of(context).financeRateHint),
      FormFieldConfig(name: 'effective_date', label: AppL10n.of(context).financeEffectiveDate, required: true, initialValue: defaultDate,
        hint: AppL10n.of(context).commonDateFormat),
    ];
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).financeExchangeRateAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/exchange-rate', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).financeExchangeRateEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/exchange-rate/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).financeExchangeRateDeleteMsg, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/exchange-rate/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).financeExchangeRateAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.financeOriginCurrencyId, AppL10n.current.financeTargetCurrencyId, AppL10n.current.financeRate, AppL10n.current.financeEffectiveDate, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeOriginCurrencyId: r['from_currency_id'] ?? '',
    AppL10n.current.financeTargetCurrencyId: r['to_currency_id'] ?? '',
    AppL10n.current.financeRate: r['rate'] ?? '',
    AppL10n.current.financeEffectiveDate: r['effective_date'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
