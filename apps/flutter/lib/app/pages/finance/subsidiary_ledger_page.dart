// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
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

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{
        'page': '$_page', 'limit': '$_limit',
        if (_accountId.isNotEmpty) 'account_id': _accountId,
        if (_startDate.isNotEmpty) 'start_date': _startDate,
        if (_endDate.isNotEmpty) 'end_date': _endDate,
      };
      final res = await ApiService.instance.get('/admin/finance/subsidiary-ledger', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  static String _directionText(dynamic d) => '${d ?? ''}' == '2' ? '贷' : '借';

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    onPageChanged: (p) { _page = p; _load(); },
    filterBar: Row(mainAxisSize: MainAxisSize.min, children: [
      SizedBox(width: 90, child: TextField(
        decoration: const InputDecoration(labelText: '科目ID', isDense: true),
        onSubmitted: (v) { _accountId = v; _page = 1; _load(); },
      )),
      const SizedBox(width: 8),
      SizedBox(width: 110, child: TextField(
        decoration: const InputDecoration(labelText: '开始日期', isDense: true),
        onSubmitted: (v) { _startDate = v; _page = 1; _load(); },
      )),
      const SizedBox(width: 8),
      SizedBox(width: 110, child: TextField(
        decoration: const InputDecoration(labelText: '结束日期', isDense: true),
        onSubmitted: (v) { _endDate = v; _page = 1; _load(); },
      )),
    ]),
  );

  List<String> _columns() => ['日期', '摘要', '方向', '金额', '余额'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '日期': r['entry_date'] ?? '',
    '摘要': r['summary'] ?? '',
    '方向': _directionText(r['direction']),
    '金额': r['amount'] ?? '',
    '余额': r['balance'] ?? '',
  };
}
