// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 银行账户管理页 — 覆盖 GET/POST/PUT/DELETE /admin/finance/bank-account
/// 后端字段: name / account_number / bank_name / balance / status
class BankAccountPage extends StatefulWidget {
  const BankAccountPage({super.key});
  @override
  State<BankAccountPage> createState() => _BankAccountPageState();
}

class _BankAccountPageState extends State<BankAccountPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  static List<String> get _statusLabels => [AppL10n.current.dashboardDisabled, AppL10n.current.dashboardEnabled];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/finance/bank-account', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.of(context).financeBankAccountName, required: true, hint: AppL10n.of(context).commonRequiredBackend),
    FormFieldConfig(name: 'account_number', label: AppL10n.of(context).financeBankAccountNumber),
    FormFieldConfig(name: 'bank_name', label: AppL10n.of(context).financeBankBankName),
    FormFieldConfig(name: 'balance', label: AppL10n.of(context).financeBankAccountBalance, type: FormFieldType.number, hint: AppL10n.of(context).commonDefaultZero),
    FormFieldConfig(name: 'status', label: AppL10n.of(context).commonStatus, type: FormFieldType.dropdown,
      options: ['0 - ${_statusLabels[0]}', '1 - ${_statusLabels[1]}'], initialValue: '1 - ${_statusLabels[1]}'),
  ];

  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'name': data['name'],
      'account_number': data['account_number']?.trim() ?? '',
      'bank_name': data['bank_name']?.trim() ?? '',
      'balance': (data['balance']?.trim().isEmpty ?? true) ? '0' : data['balance']!.trim(),
      'status': statusRaw,
    };
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).financeBankAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/bank-account', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).financeBankEdit, fields: _formFields(), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/bank-account/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).financeBankAccountDeleteMsg(row['name'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/bank-account/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 编辑回填：把后端数字 status 转回下拉选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final s = d['status'];
    if (s is int && s >= 0 && s < _statusLabels.length) {
      d['status'] = '$s - ${_statusLabels[s]}';
    }
    return d;
  }

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
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
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).financeBankAddButton)),
    ],
  );

  List<String> _columns() => [AppL10n.current.financeBankAccountName, AppL10n.current.financeBankAccountNumber, AppL10n.current.financeBankBankName, AppL10n.current.financeBalance, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeBankAccountName: r['name'] ?? '',
    AppL10n.current.financeBankAccountNumber: r['account_number'] ?? '',
    AppL10n.current.financeBankBankName: r['bank_name'] ?? '',
    AppL10n.current.financeBalance: r['balance'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final color = (i == null || i < 0 || i >= _statusLabels.length)
        ? Colors.blue
        : (i == 1 ? Colors.green : Colors.red);
    final label = _statusText(s);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
