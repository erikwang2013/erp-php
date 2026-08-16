// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';

/// 财务报表页 — 覆盖端点：
/// GET  /admin/finance/report/profit            （利润报表）
/// GET  /admin/finance/report/balance-sheet     （资产负债表）
/// GET  /admin/finance/report/cash-flow         （现金流量表）
/// GET  /admin/finance/report/trial-balance     （试算平衡表）
/// GET  /admin/finance/report/account-balance   （科目余额）
/// POST /admin/finance/report/close-period      （期末结转）
class FinanceReportPage extends StatefulWidget {
  const FinanceReportPage({super.key});
  @override
  State<FinanceReportPage> createState() => _FinanceReportPageState();
}

class _FinanceReportPageState extends State<FinanceReportPage> {
  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 6,
      child: Column(children: [
        const TabBar(isScrollable: true, tabs: [
          Tab(text: '利润报表'),
          Tab(text: '资产负债表'),
          Tab(text: '现金流量表'),
          Tab(text: '试算平衡表'),
          Tab(text: '科目余额'),
          Tab(text: '期末结转'),
        ]),
        const SizedBox(height: 8),
        Expanded(child: TabBarView(children: const [
          _ProfitTab(),
          _BalanceSheetTab(),
          _CashFlowTab(),
          _TrialBalanceTab(),
          _AccountBalanceTab(),
          _ClosePeriodTab(),
        ])),
      ]),
    );
  }
}

/// 期间输入行（年份/月份 + 查询按钮）。控制器由父级持有，避免重复创建。
class _PeriodBar extends StatelessWidget {
  final TextEditingController yearCtrl;
  final TextEditingController monthCtrl;
  final VoidCallback onLoad;
  final bool loading;
  const _PeriodBar({
    required this.yearCtrl,
    required this.monthCtrl,
    required this.onLoad,
    required this.loading,
  });

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      SizedBox(width: 110, child: TextField(
        controller: yearCtrl,
        decoration: const InputDecoration(labelText: '年份', isDense: true, border: OutlineInputBorder()),
      )),
      const SizedBox(width: 12),
      SizedBox(width: 90, child: TextField(
        controller: monthCtrl,
        decoration: const InputDecoration(labelText: '月份', isDense: true, border: OutlineInputBorder()),
      )),
      const SizedBox(width: 12),
      ElevatedButton.icon(
        onPressed: loading ? null : onLoad,
        icon: const Icon(Icons.search, size: 18),
        label: Text(loading ? '查询中...' : '查询'),
      ),
    ]);
  }
}

/// 指标卡片（金额自动格式化保留两位小数）。
class _MetricCard extends StatelessWidget {
  final String label;
  final dynamic value;
  final Color? color;
  const _MetricCard({required this.label, required this.value, this.color});

  String _fmt(dynamic v) {
    if (v == null || v == '') return '-';
    final n = double.tryParse('$v');
    if (n == null) return '$v';
    // 千分位 + 两位小数
    final parts = n.toStringAsFixed(2).split('.');
    final buf = StringBuffer();
    final intPart = parts[0];
    for (var i = 0; i < intPart.length; i++) {
      buf.write(intPart[i]);
      final rem = intPart.length - 1 - i;
      if (rem > 0 && rem % 3 == 0) buf.write(',');
    }
    return '$buf.${parts[1]}';
  }

  @override
  Widget build(BuildContext context) {
    final c = color ?? Theme.of(context).colorScheme.primary;
    return Card(
      margin: const EdgeInsets.only(right: 12, bottom: 12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(label, style: const TextStyle(fontSize: 13, color: Colors.grey)),
          const SizedBox(height: 6),
          Text(_fmt(value), style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: c)),
        ]),
      ),
    );
  }
}

/// 通用列表表格（items 为 Map 列表时渲染，用于试算平衡表明细）。
class _ItemsTable extends StatelessWidget {
  final List<dynamic> items;
  const _ItemsTable(this.items);

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const Center(child: Text('暂无明细数据'));
    final first = items.first;
    if (first is! Map) {
      return Card(child: Padding(padding: const EdgeInsets.all(12), child: Text('$items')));
    }
    final keys = first.keys.toList();
    return Card(
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          columnSpacing: 20,
          columns: [for (final k in keys) DataColumn(label: Text(k))],
          rows: [
            for (final item in items)
              DataRow(cells: [
                for (final k in keys)
                  DataCell(Text('${item[k] ?? ''}')),
              ]),
          ],
        ),
      ),
    );
  }
}

// ============ Tab 1: 利润报表 ============
class _ProfitTab extends StatefulWidget {
  const _ProfitTab();
  @override
  State<_ProfitTab> createState() => _ProfitTabState();
}

class _ProfitTabState extends State<_ProfitTab> {
  final _yearCtrl = TextEditingController(text: '${DateTime.now().year}');
  final _monthCtrl = TextEditingController(text: '${DateTime.now().month}');
  List<Map<String, dynamic>> _rows = [];
  Map<String, dynamic> _summary = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _yearCtrl.dispose();
    _monthCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/finance/report/profit', params: {
        'year': _yearCtrl.text.trim(),
        'month': _monthCtrl.text.trim(),
      });
      final d = res['data'];
      setState(() {
        _rows = List<Map<String, dynamic>>.from(d['list'] ?? []);
        _summary = Map<String, dynamic>.from(d['summary'] ?? {});
        _loading = false;
      });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _PeriodBar(yearCtrl: _yearCtrl, monthCtrl: _monthCtrl,
          onLoad: _load, loading: _loading),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_summary.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '营业收入', value: _summary['total_revenue'], color: Colors.green),
            _MetricCard(label: '营业成本', value: _summary['total_cost'], color: Colors.orange),
            _MetricCard(label: '费用合计', value: _summary['total_expense'], color: Colors.orange),
            _MetricCard(label: '利润', value: _summary['total_profit'], color: Colors.teal),
          ]),
        ],
        const SizedBox(height: 8),
        if (_rows.isNotEmpty)
          Card(child: DataTable(
            columnSpacing: 20,
            columns: const [
              DataColumn(label: Text('年度')), DataColumn(label: Text('月份')),
              DataColumn(label: Text('营业收入')), DataColumn(label: Text('营业成本')),
              DataColumn(label: Text('费用')), DataColumn(label: Text('利润')),
            ],
            rows: [
              for (final r in _rows)
                DataRow(cells: [
                  DataCell(Text('${r['year'] ?? ''}')),
                  DataCell(Text('${r['month'] ?? ''}')),
                  DataCell(Text('${r['revenue'] ?? ''}')),
                  DataCell(Text('${r['cost'] ?? ''}')),
                  DataCell(Text('${r['expense'] ?? ''}')),
                  DataCell(Text('${r['profit'] ?? ''}')),
                ]),
            ],
          )),
      ]),
    ));
  }
}

// ============ Tab 2: 资产负债表 ============
class _BalanceSheetTab extends StatefulWidget {
  const _BalanceSheetTab();
  @override
  State<_BalanceSheetTab> createState() => _BalanceSheetTabState();
}

class _BalanceSheetTabState extends State<_BalanceSheetTab> {
  final _yearCtrl = TextEditingController(text: '${DateTime.now().year}');
  final _monthCtrl = TextEditingController(text: '${DateTime.now().month}');
  Map<String, dynamic> _data = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _yearCtrl.dispose();
    _monthCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/finance/report/balance-sheet', params: {
        'report_year': _yearCtrl.text.trim(),
        'report_month': _monthCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _PeriodBar(yearCtrl: _yearCtrl, monthCtrl: _monthCtrl,
          onLoad: _load, loading: _loading),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '流动资产', value: _data['current_assets'], color: Colors.blue),
            _MetricCard(label: '非流动资产', value: _data['non_current_assets'], color: Colors.blue),
            _MetricCard(label: '资产总计', value: _data['total_assets'], color: Colors.green),
            _MetricCard(label: '流动负债', value: _data['current_liabilities'], color: Colors.orange),
            _MetricCard(label: '非流动负债', value: _data['non_current_liabilities'], color: Colors.orange),
            _MetricCard(label: '负债总计', value: _data['total_liabilities'], color: Colors.orange),
            _MetricCard(label: '所有者权益', value: _data['total_equity'], color: Colors.teal),
          ]),
          const SizedBox(height: 4),
          if (_data['report_data'] != null)
            Text('报表说明: ${_data['report_data']}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
        ],
      ]),
    ));
  }
}

// ============ Tab 3: 现金流量表 ============
class _CashFlowTab extends StatefulWidget {
  const _CashFlowTab();
  @override
  State<_CashFlowTab> createState() => _CashFlowTabState();
}

class _CashFlowTabState extends State<_CashFlowTab> {
  final _yearCtrl = TextEditingController(text: '${DateTime.now().year}');
  final _monthCtrl = TextEditingController(text: '${DateTime.now().month}');
  Map<String, dynamic> _data = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _yearCtrl.dispose();
    _monthCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/finance/report/cash-flow', params: {
        'report_year': _yearCtrl.text.trim(),
        'report_month': _monthCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _PeriodBar(yearCtrl: _yearCtrl, monthCtrl: _monthCtrl,
          onLoad: _load, loading: _loading),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '经营活动流入', value: _data['operating_inflow'], color: Colors.green),
            _MetricCard(label: '经营活动流出', value: _data['operating_outflow'], color: Colors.red),
            _MetricCard(label: '经营活动净额', value: _data['operating_net'], color: Colors.teal),
            _MetricCard(label: '投资活动流入', value: _data['investing_inflow'], color: Colors.green),
            _MetricCard(label: '投资活动流出', value: _data['investing_outflow'], color: Colors.red),
            _MetricCard(label: '投资活动净额', value: _data['investing_net'], color: Colors.teal),
            _MetricCard(label: '筹资活动流入', value: _data['financing_inflow'], color: Colors.green),
            _MetricCard(label: '筹资活动流出', value: _data['financing_outflow'], color: Colors.red),
            _MetricCard(label: '筹资活动净额', value: _data['financing_net'], color: Colors.teal),
            _MetricCard(label: '期初现金', value: _data['beginning_cash'], color: Colors.blue),
            _MetricCard(label: '期末现金', value: _data['ending_cash'], color: Colors.indigo),
          ]),
          const SizedBox(height: 4),
          if (_data['report_data'] != null)
            Text('报表说明: ${_data['report_data']}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
        ],
      ]),
    ));
  }
}

// ============ Tab 4: 试算平衡表 ============
class _TrialBalanceTab extends StatefulWidget {
  const _TrialBalanceTab();
  @override
  State<_TrialBalanceTab> createState() => _TrialBalanceTabState();
}

class _TrialBalanceTabState extends State<_TrialBalanceTab> {
  final _periodCtrl = TextEditingController(text: _defaultPeriod());
  Map<String, dynamic> _data = {};
  bool _loading = false;
  String? _error;

  static String _defaultPeriod() {
    final now = DateTime.now();
    return '${now.year}-${now.month.toString().padLeft(2, '0')}';
  }

  @override
  void dispose() {
    _periodCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/finance/report/trial-balance', params: {
        'period': _periodCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _data['items'] is List ? _data['items'] as List : <dynamic>[];
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          SizedBox(width: 160, child: TextField(
            controller: _periodCtrl,
            decoration: const InputDecoration(labelText: '期间 YYYY-MM', isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          ElevatedButton.icon(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.search, size: 18),
            label: Text(_loading ? '查询中...' : '查询'),
          ),
        ]),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '借方合计', value: _data['total_debit'], color: Colors.blue),
            _MetricCard(label: '贷方合计', value: _data['total_credit'], color: Colors.orange),
          ]),
          const SizedBox(height: 8),
          _ItemsTable(items),
        ],
      ]),
    ));
  }
}

// ============ Tab 5: 科目余额 ============
class _AccountBalanceTab extends StatefulWidget {
  const _AccountBalanceTab();
  @override
  State<_AccountBalanceTab> createState() => _AccountBalanceTabState();
}

class _AccountBalanceTabState extends State<_AccountBalanceTab> {
  final _subjectCtrl = TextEditingController();
  final _periodCtrl = TextEditingController();
  Map<String, dynamic> _data = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _subjectCtrl.dispose();
    _periodCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final subjectId = _subjectCtrl.text.trim();
    if (subjectId.isEmpty) {
      setState(() => _error = '请输入科目ID（account_subject_id 必填）');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/finance/report/account-balance', params: {
        'account_subject_id': subjectId,
        'period': _periodCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          SizedBox(width: 130, child: TextField(
            controller: _subjectCtrl,
            decoration: const InputDecoration(labelText: '科目ID *', isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          SizedBox(width: 160, child: TextField(
            controller: _periodCtrl,
            decoration: const InputDecoration(labelText: '期间 YYYY-MM(可选)', isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          ElevatedButton.icon(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.search, size: 18),
            label: Text(_loading ? '查询中...' : '查询'),
          ),
        ]),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '期初借方', value: _data['opening_debit'], color: Colors.blue),
            _MetricCard(label: '期初贷方', value: _data['opening_credit'], color: Colors.orange),
            _MetricCard(label: '本期借方', value: _data['current_debit'], color: Colors.blue),
            _MetricCard(label: '本期贷方', value: _data['current_credit'], color: Colors.orange),
            _MetricCard(label: '期末借方', value: _data['closing_debit'], color: Colors.teal),
            _MetricCard(label: '期末贷方', value: _data['closing_credit'], color: Colors.teal),
          ]),
        ],
      ]),
    ));
  }
}

// ============ Tab 6: 期末结转 ============
class _ClosePeriodTab extends StatefulWidget {
  const _ClosePeriodTab();
  @override
  State<_ClosePeriodTab> createState() => _ClosePeriodTabState();
}

class _ClosePeriodTabState extends State<_ClosePeriodTab> {
  final _yearCtrl = TextEditingController(text: '${DateTime.now().year}');
  final _monthCtrl = TextEditingController(text: '${DateTime.now().month}');
  Map<String, dynamic> _result = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _yearCtrl.dispose();
    _monthCtrl.dispose();
    super.dispose();
  }

  Future<void> _close() async {
    setState(() { _loading = true; _error = null; _result = {}; });
    try {
      final res = await ApiService.instance.post('/admin/finance/report/close-period', data: {
        'year': int.tryParse(_yearCtrl.text.trim()) ?? DateTime.now().year,
        'month': int.tryParse(_monthCtrl.text.trim()) ?? DateTime.now().month,
      });
      setState(() { _result = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _PeriodBar(yearCtrl: _yearCtrl, monthCtrl: _monthCtrl,
          onLoad: _close, loading: _loading),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
        if (_result.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: '收入结转', value: _result['revenue_total'], color: Colors.green),
            _MetricCard(label: '费用结转', value: _result['expense_total'], color: Colors.orange),
            _MetricCard(label: '本年利润', value: _result['net_profit'], color: Colors.teal),
            _MetricCard(label: '结转状态', value: _result['status'], color: Colors.blue),
          ]),
          if (_result['message'] != null)
            Padding(padding: const EdgeInsets.only(top: 4), child: Text('${_result['message']}')),
          if (_result['voucher_id'] != null)
            Padding(padding: const EdgeInsets.only(top: 4), child: Text('凭证ID: ${_result['voucher_id']}')),
        ],
      ]),
    ));
  }
}
