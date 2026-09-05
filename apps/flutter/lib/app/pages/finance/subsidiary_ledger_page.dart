// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';

/// 明细分类账 — GET /admin/finance/subsidiary-ledger（只读，支持科目/日期筛选）
class SubsidiaryLedgerPage extends StatefulWidget {
  const SubsidiaryLedgerPage({super.key});
  @override
  State<SubsidiaryLedgerPage> createState() => _SubsidiaryLedgerPageState();
}

class _SubsidiaryLedgerPageState extends State<SubsidiaryLedgerPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _accountId = '', _startDate = '', _endDate = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{
        'page': '$_page', 'limit': '$_limit',
        if (_accountId.isNotEmpty) 'account_id': _accountId,
        if (_startDate.isNotEmpty) 'start_date': _startDate,
        if (_endDate.isNotEmpty) 'end_date': _endDate,
      };
      final res = await ApiService.instance.get('/admin/v1/finance/subsidiary-ledger', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  static String _directionText(dynamic d) => '${d ?? ''}' == '2' ? AppL10n.current.financeCredit : AppL10n.current.financeDebit;

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    onPageChanged: (p) { _page = p; _load(); },
    filterBar: Row(mainAxisSize: MainAxisSize.min, children: [
      SizedBox(width: 90, child: TextField(
        decoration: InputDecoration(labelText: AppL10n.of(context).financeSubjectId, isDense: true),
        onSubmitted: (v) { _accountId = v; _page = 1; _load(); },
      )),
      const SizedBox(width: 8),
      SizedBox(width: 110, child: TextField(
        decoration: InputDecoration(labelText: AppL10n.of(context).financeStartDate, isDense: true),
        onSubmitted: (v) { _startDate = v; _page = 1; _load(); },
      )),
      const SizedBox(width: 8),
      SizedBox(width: 110, child: TextField(
        decoration: InputDecoration(labelText: AppL10n.of(context).financeEndDate, isDense: true),
        onSubmitted: (v) { _endDate = v; _page = 1; _load(); },
      )),
    ]),
  );

  List<String> _columns() => [AppL10n.current.financeDate, AppL10n.current.financeSummary, AppL10n.current.financeDirection, AppL10n.current.financeAmount, AppL10n.current.financeBalance];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeDate: r['entry_date'] ?? '',
    AppL10n.current.financeSummary: r['summary'] ?? '',
    AppL10n.current.financeDirection: _directionText(r['direction']),
    AppL10n.current.financeAmount: r['amount'] ?? '',
    AppL10n.current.financeBalance: r['balance'] ?? '',
  };
}
