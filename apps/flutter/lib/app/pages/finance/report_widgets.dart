// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'report_page.dart';

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

