// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'report_page.dart';

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

