// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class VoucherListPage extends StatefulWidget {
  const VoucherListPage({super.key});
  @override
  State<VoucherListPage> createState() => _VoucherListPageState();
}

class _VoucherListPageState extends State<VoucherListPage> {
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
      
      final res = await ApiService.instance.get('/admin/v1/finance/voucher', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).financeVoucherAdd, fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/v1/finance/voucher', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).financeVoucherEdit, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/finance/voucher/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/voucher/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 后端 erp_finance_voucher 字段: code/voucher_date/status(0草稿/1已审核)/remark
  // store() 同时校验 name 必填；传 items 时走 DoubleEntryService::createVoucher，
  // 接收 items[{account_id|account_subject_id, summary, debit_amount, credit_amount}]
  // 并校验借贷平衡（借方合计 == 贷方合计）。
  static List<String> get _statusLabels => [AppL10n.current.financeVoucherDraft, AppL10n.current.financeVoucherReviewed];

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultDate = '${now.year}-${pad(now.month)}-${pad(now.day)}';
    return [
      FormFieldConfig(name: 'name', label: AppL10n.of(context).financeVoucherName, required: true, hint: AppL10n.of(context).commonRequiredBackend),
      FormFieldConfig(name: 'code', label: AppL10n.of(context).financeVoucherCode, hint: AppL10n.of(context).financeVoucherCodeHint),
      FormFieldConfig(name: 'voucher_date', label: AppL10n.of(context).financeVoucherDate, required: true, initialValue: defaultDate,
        hint: AppL10n.of(context).commonDateFormat),
      FormFieldConfig(name: 'status', label: AppL10n.of(context).commonStatus, type: FormFieldType.dropdown,
        options: ['0 - ${_statusLabels[0]}', '1 - ${_statusLabels[1]}'], initialValue: '0 - ${_statusLabels[0]}'),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
      // 简单明细项（至少一行）：科目ID、摘要、借方金额、贷方金额。
      // 提交时若科目ID非空则组装为 items 列表，由后端 DoubleEntryService 校验借贷平衡。
      FormFieldConfig(name: 'item_account_id', label: AppL10n.of(context).financeVoucherItemSubject, hint: AppL10n.of(context).financeVoucherItemSubjectHint),
      FormFieldConfig(name: 'item_summary', label: AppL10n.of(context).financeVoucherItemSummary),
      FormFieldConfig(name: 'item_debit_amount', label: AppL10n.of(context).financeVoucherItemDebit, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('100.00')),
      FormFieldConfig(name: 'item_credit_amount', label: AppL10n.of(context).financeVoucherItemCredit, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('100.00')),
    ];
  }

  /// 组装后端 store()/update() 接收的参数；科目ID非空时附带 items 明细。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'VCH${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    final payload = <String, dynamic>{
      'name': data['name'],
      'code': code,
      'voucher_date': data['voucher_date']?.trim(),
      'status': statusRaw,
      'remark': data['remark']?.trim() ?? '',
    };

    final accountId = data['item_account_id']?.trim() ?? '';
    if (accountId.isNotEmpty) {
      payload['items'] = [
        {
          'account_id': accountId,
          'summary': data['item_summary']?.trim() ?? '',
          'debit_amount': (data['item_debit_amount']?.trim().isEmpty ?? true) ? '0' : data['item_debit_amount']!.trim(),
          'credit_amount': (data['item_credit_amount']?.trim().isEmpty ?? true) ? '0' : data['item_credit_amount']!.trim(),
        },
      ];
    }
    return payload;
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

  String _p2(int v) => v.toString().padLeft(2, '0');

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
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.financeVoucherCode, AppL10n.current.financeVoucherDate, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeVoucherCode: r['code'] ?? '',
    AppL10n.current.financeVoucherDate: r['voucher_date'] ?? '',
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
        : (i == 1 ? Colors.green : Colors.orange);
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
