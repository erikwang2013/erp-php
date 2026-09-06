// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';

/// 财务报表页 — 覆盖端点：
/// GET  /admin/v1/finance/report/profit          （利润报表）
/// GET  /admin/v1/finance/report/balance-sheet   （资产负债表）
/// GET  /admin/v1/finance/report/cash-flow       （现金流量表）
/// GET  /admin/v1/finance/report/trial-balance   （试算平衡表）
/// GET  /admin/v1/finance/report/account-balance （科目余额）
/// POST /admin/v1/finance/report/close-period    （期末结转）
/// POST /admin/v1/finance/report/consolidate     （多币种合并）
/// POST /admin/v1/finance/report/ratios          （财务比率）
class FinanceReportPage extends StatefulWidget {
  const FinanceReportPage({super.key});
  @override
  State<FinanceReportPage> createState() => _FinanceReportPageState();
}

class _FinanceReportPageState extends State<FinanceReportPage> {
  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 8,
      child: Column(children: [
        TabBar(isScrollable: true, tabs: [
          Tab(text: AppL10n.of(context).financeReportProfit),
          Tab(text: AppL10n.of(context).financeReportBalanceSheet),
          Tab(text: AppL10n.of(context).financeReportCashFlow),
          Tab(text: AppL10n.of(context).financeReportTrialBalance),
          Tab(text: AppL10n.of(context).financeReportAccountBalance),
          Tab(text: AppL10n.of(context).financeReportClosePeriod),
          Tab(text: AppL10n.of(context).financeReportConsolidate),
          Tab(text: AppL10n.of(context).financeReportRatios),
        ]),
        const SizedBox(height: 8),
        Expanded(child: TabBarView(children: const [
          _ProfitTab(),
          _BalanceSheetTab(),
          _CashFlowTab(),
          _TrialBalanceTab(),
          _AccountBalanceTab(),
          _ClosePeriodTab(),
          _ConsolidateTab(),
          _RatiosTab(),
        ])),
      ]),
    );
  }
}

/// JSON 输入区（textarea + label）。
class _JsonInput extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final String hint;
  const _JsonInput({required this.controller, required this.label, required this.hint});
  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      maxLines: 4,
      style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        isDense: true,
        border: const OutlineInputBorder(),
      ),
    );
  }
}

/// 解析 JSON 输入；空串返回 null，解析失败抛 FormatException。
Object? _parseJson(String text, String fieldName) {
  final t = text.trim();
  if (t.isEmpty) return null;
  try {
    return jsonDecode(t);
  } catch (_) {
    throw FormatException(AppL10n.current.financeJsonInvalidMsg(fieldName));
  }
}

// ============ Tab 7: 合并报表 ============
class _ConsolidateTab extends StatefulWidget {
  const _ConsolidateTab();
  @override
  State<_ConsolidateTab> createState() => _ConsolidateTabState();
}

class _ConsolidateTabState extends State<_ConsolidateTab> {
  final _reportsCtrl = TextEditingController();
  final _currencyCtrl = TextEditingController(text: 'CNY');
  Map<String, dynamic> _result = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _reportsCtrl.dispose();
    _currencyCtrl.dispose();
    super.dispose();
  }

  /// 真实响应 data{base_currency, report_year, report_month, total_assets,
  /// total_liabilities, total_equity, revenue, net_profit,
  /// report_data:{generated_from, base_currency, subsidiaries[{ledger_id,
  /// company_id, code, name, currency, rate, source, 五项金额}]}}
  /// （见 ConsolidationService::consolidate 出口）；空/非数组请求 → 422 文案直接呈现。
  List<dynamic> get _subsidiaries {
    final rd = _result['report_data'];
    return (rd is Map && rd['subsidiaries'] is List)
        ? rd['subsidiaries'] as List
        : <dynamic>[];
  }

  Future<void> _run() async {
    setState(() { _loading = true; _error = null; _result = {}; });
    try {
      final reports = _parseJson(_reportsCtrl.text, 'subsidiary_reports');
      if (reports != null && reports is! List) {
        throw FormatException(AppL10n.of(context).financeJsonArrayRequired('subsidiary_reports'));
      }
      final res = await ApiService.instance.post('/admin/v1/finance/report/consolidate', data: {
        'subsidiary_reports': reports ?? [],
        'base_currency': _currencyCtrl.text.trim().isEmpty ? 'CNY' : _currencyCtrl.text.trim(),
      });
      if (mounted) setState(() { _result = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      // ApiException 只取 message（如 422/501 的业务文案），友好呈现
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
    }
  }

  @override
  Widget build(BuildContext context) {
    final subsidiaries = _subsidiaries;
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 420, child: _JsonInput(
          controller: _reportsCtrl,
          label: l10n.financeConsolidateJsonLabel,
          hint: l10n.financeConsolidateJsonHint,
        )),
        const SizedBox(height: 12),
        Row(children: [
          SizedBox(width: 140, child: TextField(
            controller: _currencyCtrl,
            decoration: InputDecoration(labelText: l10n.financeBaseCurrency, isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          ElevatedButton.icon(
            onPressed: _loading ? null : _run,
            icon: const Icon(Icons.merge_type, size: 18),
            label: Text(_loading ? l10n.financeConsolidating : l10n.financeExecuteConsolidate),
          ),
        ]),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: TextStyle(color: c.danger)),
        if (_result.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: l10n.financeBaseCurrency, value: _result['base_currency'], color: c.primary),
            _MetricCard(label: l10n.financeYear, value: _result['report_year'], color: c.primary),
            _MetricCard(label: l10n.financeMonth, value: _result['report_month'], color: c.primary),
            _MetricCard(label: l10n.financeTotalAssets, value: _result['total_assets'], color: c.success),
            _MetricCard(label: l10n.financeTotalLiabilities, value: _result['total_liabilities'], color: c.warning),
            _MetricCard(label: l10n.financeEquity, value: _result['total_equity'], color: c.warning),
            _MetricCard(label: l10n.financeRevenue, value: _result['revenue'], color: c.success),
            _MetricCard(label: l10n.financeYearProfit, value: _result['net_profit'], color: c.primary),
          ]),
          if (subsidiaries.isNotEmpty) ...[
            const SizedBox(height: 8),
            _ItemsTable(subsidiaries),
          ],
        ],
      ]),
    ));
  }
}

// ============ Tab 8: 财务比率 ============
class _RatiosTab extends StatefulWidget {
  const _RatiosTab();
  @override
  State<_RatiosTab> createState() => _RatiosTabState();
}

class _RatiosTabState extends State<_RatiosTab> {
  final _bsCtrl = TextEditingController(
    text: '{"current_assets":0,"current_liabilities":0,"total_liabilities":0,"total_assets":0}');
  final _psCtrl = TextEditingController(
    text: '{"net_profit":0,"revenue":0}');
  Map<String, dynamic> _result = {};
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _bsCtrl.dispose();
    _psCtrl.dispose();
    super.dispose();
  }

  Future<void> _run() async {
    setState(() { _loading = true; _error = null; _result = {}; });
    try {
      final bs = _parseJson(_bsCtrl.text, 'balance_sheet');
      if (bs != null && bs is! Map) {
        throw FormatException(AppL10n.of(context).financeJsonObjectRequired('balance_sheet'));
      }
      final ps = _parseJson(_psCtrl.text, 'profit_statement');
      if (ps != null && ps is! Map) {
        throw FormatException(AppL10n.of(context).financeJsonObjectRequired('profit_statement'));
      }
      final res = await ApiService.instance.post('/admin/v1/finance/report/ratios', data: {
        'balance_sheet': bs ?? {},
        'profit_statement': ps ?? {},
      });
      if (mounted) setState(() { _result = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = '$e'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(child: Padding(
      padding: const EdgeInsets.all(8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 420, child: _JsonInput(
          controller: _bsCtrl,
          label: AppL10n.of(context).financeBalanceSheetJsonLabel,
          hint: AppL10n.of(context).financeBalanceSheetJsonHint,
        )),
        const SizedBox(height: 12),
        SizedBox(width: 420, child: _JsonInput(
          controller: _psCtrl,
          label: AppL10n.of(context).financeProfitStatementJsonLabel,
          hint: AppL10n.of(context).financeProfitStatementJsonHint,
        )),
        const SizedBox(height: 12),
        ElevatedButton.icon(
          onPressed: _loading ? null : _run,
          icon: const Icon(Icons.calculate, size: 18),
          label: Text(_loading ? AppL10n.of(context).financeCalculating : AppL10n.of(context).financeCalcRatios),
        ),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_result.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeCurrentRatio, value: _result['current_ratio'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeDebtRatio, value: _result['debt_ratio'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeNetMargin, value: _result['net_profit_margin'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeRoa, value: _result['return_on_assets'], color: AppColors.of(context).primary),
          ]),
        ],
      ]),
    ));
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
        decoration: InputDecoration(labelText: AppL10n.of(context).financeYear, isDense: true, border: OutlineInputBorder()),
      )),
      const SizedBox(width: 12),
      SizedBox(width: 90, child: TextField(
        controller: monthCtrl,
        decoration: InputDecoration(labelText: AppL10n.of(context).financeMonth, isDense: true, border: OutlineInputBorder()),
      )),
      const SizedBox(width: 12),
      ElevatedButton.icon(
        onPressed: loading ? null : onLoad,
        icon: const Icon(Icons.search, size: 18),
        label: Text(loading ? AppL10n.of(context).financeQuerying : AppL10n.of(context).financeQuery),
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
          Text(label, style: TextStyle(fontSize: 13, color: AppColors.of(context).textSecondary)),
          const SizedBox(height: 6),
          Text(_fmt(value), style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: c)),
        ]),
      ),
    );
  }
}

/// report_data 对象化明细（批1 契约）：report_data 已是对象而非 JSON 串——
/// 标量键逐行 key: value，值为 List 的键（如 lines/subsidiaries）用 _ItemsTable
/// 平铺；空串/损坏 JSON（后端兜底 []）渲染空态文案。
class _ReportDataDetail extends StatelessWidget {
  final dynamic reportData;
  const _ReportDataDetail(this.reportData);

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    if (reportData is! Map || reportData.isEmpty) {
      return Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Text(l10n.financeNoDetailData, style: TextStyle(fontSize: 12, color: c.textHint)),
      );
    }
    final rd = Map<String, dynamic>.from(reportData as Map);
    final scalars = <MapEntry<String, dynamic>>[];
    final lists = <MapEntry<String, List<dynamic>>>[];
    for (final e in rd.entries) {
      if (e.value is List && (e.value as List).isNotEmpty) {
        lists.add(MapEntry(e.key, (e.value as List)));
      } else if (e.value is! List) {
        scalars.add(e);
      }
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      for (final e in scalars)
        Padding(
          padding: const EdgeInsets.only(top: 2),
          child: Text('${e.key}: ${e.value ?? ''}', style: TextStyle(fontSize: 12, color: c.textSecondary)),
        ),
      for (final e in lists) ...[
        const SizedBox(height: 6),
        Text('${e.key} (${e.value.length})', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: c.textSecondary)),
        const SizedBox(height: 4),
        _ItemsTable(e.value),
      ],
    ]);
  }
}

/// 通用列表表格（items 为 Map 列表时渲染，用于试算平衡表明细）。
class _ItemsTable extends StatelessWidget {
  final List<dynamic> items;
  const _ItemsTable(this.items);

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return Center(child: Text(AppL10n.of(context).financeNoDetailData));
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
      final res = await ApiService.instance.get('/admin/v1/finance/report/trial-balance', params: {
        'period': _periodCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = '$e'; });
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
            decoration: InputDecoration(labelText: AppL10n.of(context).financePeriod, isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          ElevatedButton.icon(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.search, size: 18),
            label: Text(_loading ? AppL10n.of(context).financeQuerying : AppL10n.of(context).financeQuery),
          ),
        ]),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeDebitTotal, value: _data['total_debit'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeCreditTotal, value: _data['total_credit'], color: AppColors.of(context).warning),
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
      setState(() => _error = AppL10n.of(context).financeAccountBalanceRequired);
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/v1/finance/report/account-balance', params: {
        'account_subject_id': subjectId,
        'period': _periodCtrl.text.trim(),
      });
      setState(() { _data = Map<String, dynamic>.from(res['data']); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = '$e'; });
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
            decoration: InputDecoration(labelText: '${AppL10n.of(context).financeSubjectId} *', isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          SizedBox(width: 160, child: TextField(
            controller: _periodCtrl,
            decoration: InputDecoration(labelText: AppL10n.of(context).financePeriodOptional, isDense: true, border: OutlineInputBorder()),
          )),
          const SizedBox(width: 12),
          ElevatedButton.icon(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.search, size: 18),
            label: Text(_loading ? AppL10n.of(context).financeQuerying : AppL10n.of(context).financeQuery),
          ),
        ]),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_data.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeOpeningDebit, value: _data['opening_debit'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeOpeningCredit, value: _data['opening_credit'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeCurrentDebit, value: _data['current_debit'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeCurrentCredit, value: _data['current_credit'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeClosingDebit, value: _data['closing_debit'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeClosingCredit, value: _data['closing_credit'], color: AppColors.of(context).primary),
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
      final res = await ApiService.instance.post('/admin/v1/finance/report/close-period', data: {
        'year': int.tryParse(_yearCtrl.text.trim()) ?? DateTime.now().year,
        'month': int.tryParse(_monthCtrl.text.trim()) ?? DateTime.now().month,
      });
      if (mounted) setState(() { _result = Map<String, dynamic>.from(res['data']); _loading = false; });
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
          onLoad: _close, loading: _loading),
        const SizedBox(height: 8),
        if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
        if (_result.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(children: [
            _MetricCard(label: AppL10n.of(context).financeRevenueCarry, value: _result['revenue_total'], color: AppColors.of(context).success),
            _MetricCard(label: AppL10n.of(context).financeExpenseCarry, value: _result['expense_total'], color: AppColors.of(context).warning),
            _MetricCard(label: AppL10n.of(context).financeYearProfit, value: _result['net_profit'], color: AppColors.of(context).primary),
            _MetricCard(label: AppL10n.of(context).financeCloseStatus, value: _result['status'], color: AppColors.of(context).primary),
          ]),
          if (_result['message'] != null)
            Padding(padding: const EdgeInsets.only(top: 4), child: Text('${_result['message']}')),
          if (_result['voucher_id'] != null)
            Padding(padding: const EdgeInsets.only(top: 4), child: Text(AppL10n.of(context).financeVoucherIdMsg('${_result['voucher_id']}'))),
        ],
      ]),
    ));
  }
}
