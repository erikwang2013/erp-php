// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'report_page.dart';

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
      final res = await ApiService.instance.get('/admin/v1/finance/report/profit', params: {
        'year': _yearCtrl.text.trim(),
        'month': _monthCtrl.text.trim(),
      });
      final d = res['data'];
      if (mounted) {
        setState(() {
          _rows = List<Map<String, dynamic>>.from(d['list'] ?? []);
          _summary = Map<String, dynamic>.from(d['summary'] ?? {});
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = '$e'; });
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
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_summary.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeRevenue, value: _summary['total_revenue'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeCost, value: _summary['total_cost'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeExpensesTotal, value: _summary['total_expense'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeProfit, value: _summary['total_profit'], color: AppColors.of(context).primary),
          ]),
        ],
        const SizedBox(height: 8),
        if (_rows.isNotEmpty)
          Card(child: DataTable(
            columnSpacing: 20,
            columns: [
              DataColumn(label: Text(AppL10n.of(context).financeAnnual)), DataColumn(label: Text(AppL10n.of(context).financeMonth)),
              DataColumn(label: Text(AppL10n.of(context).financeRevenue)), DataColumn(label: Text(AppL10n.of(context).financeCost)),
              DataColumn(label: Text(AppL10n.of(context).financeExpense)), DataColumn(label: Text(AppL10n.of(context).financeProfit)),
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
      final res = await ApiService.instance.get('/admin/v1/finance/report/balance-sheet', params: {
        'report_year': _yearCtrl.text.trim(),
        'report_month': _monthCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      // ApiException → 服务端 message（422 等友好文案），其余转通用网络文案
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
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
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeCurrentAssets, value: _data['current_assets'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeNonCurrentAssets, value: _data['non_current_assets'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeTotalAssets, value: _data['total_assets'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeCurrentLiabilities, value: _data['current_liabilities'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeNonCurrentLiabilities, value: _data['non_current_liabilities'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeTotalLiabilities, value: _data['total_liabilities'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeEquity, value: _data['total_equity'], color: AppColors.of(context).primary),
          ]),
          const SizedBox(height: 4),
          // report_data 已是对象(lines[] 科目明细)——结构化渲染替代直显 JSON 串
          _ReportDataDetail(_data['report_data']),
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
      final res = await ApiService.instance.get('/admin/v1/finance/report/cash-flow', params: {
        'report_year': _yearCtrl.text.trim(),
        'report_month': _monthCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      // ApiException → 服务端 message（422 等友好文案），其余转通用网络文案
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
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
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeOperatingInflow, value: _data['operating_inflow'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeOperatingOutflow, value: _data['operating_outflow'], color: AppColors.of(context).danger),
            _MetricCard(label: AppL10n.of(context).financeOperatingNet, value: _data['operating_net'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeInvestingInflow, value: _data['investing_inflow'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeInvestingOutflow, value: _data['investing_outflow'], color: AppColors.of(context).danger),
            _MetricCard(label: AppL10n.of(context).financeInvestingNet, value: _data['investing_net'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeFinancingInflow, value: _data['financing_inflow'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeFinancingOutflow, value: _data['financing_outflow'], color: AppColors.of(context).danger),
            _MetricCard(label: AppL10n.of(context).financeFinancingNet, value: _data['financing_net'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeBeginningCash, value: _data['beginning_cash'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeEndingCash, value: _data['ending_cash'], color: AppColors.of(context).primary),
          ]),
          const SizedBox(height: 4),
          // report_data 对象(voucher_count/generated_from)——结构化渲染替代直显 JSON 串
          _ReportDataDetail(_data['report_data']),
        ],
      ]),
    ));
  }
}

