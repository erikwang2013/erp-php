// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class SalesQuotationListPage extends StatefulWidget {
  const SalesQuotationListPage({super.key});
  @override
  State<SalesQuotationListPage> createState() => _SalesQuotationListPageState();
}

class _SalesQuotationListPageState extends State<SalesQuotationListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;

  // 后端 erp_sales_quotation 列: code/customer_id/total_amount/status/remark/
  // quoted_at（无 name 列）；store() 校验 code+customer_id 必填
  static List<String> get _statusLabels => [AppL10n.current.salesQuoteDraft, AppL10n.current.salesQuoteQuoted, AppL10n.current.salesQuoteConverted, AppL10n.current.salesQuoteExpired];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/sales/quotation', params: params);
      final d = res['data'];
      if (mounted) setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
    }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).salesQuotationAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/sales/quotation', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).salesQuotationEdit, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/sales/quotation/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['code'] ?? ''), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/quotation/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultQuotedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    return [
      FormFieldConfig(name: 'code', label: AppL10n.of(context).salesQuotationNo, hint: AppL10n.of(context).salesQuotationCodeHint),
      FormFieldConfig(name: 'customer_id', label: AppL10n.of(context).salesCustomerId, required: true, hint: AppL10n.of(context).salesCustomerIdHint),
      FormFieldConfig(name: 'total_amount', label: AppL10n.of(context).salesQuotationAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('1000.00')),
      FormFieldConfig(name: 'status', label: AppL10n.of(context).commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) '$i - ${_statusLabels[i]}'], initialValue: '0 - ${_statusLabels[0]}'),
      FormFieldConfig(name: 'quoted_at', label: AppL10n.of(context).salesQuotedAt, initialValue: defaultQuotedAt,
        hint: AppL10n.of(context).commonDateTimeFormat),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（仅真实表列；status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'QT${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'code': code,
      'customer_id': data['customer_id']?.trim(),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
      'status': statusRaw,
      'quoted_at': data['quoted_at']?.trim(),
      'remark': data['remark']?.trim() ?? '',
    };
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
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    filterBar: DropdownButton<String>(
      value: _statusFilter,
      hint: Text(AppL10n.of(context).commonStatus),
      items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.salesQuotationNo, AppL10n.current.salesCustomerId, AppL10n.current.salesQuotationAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesQuotationNo: r['code'] ?? '',
    AppL10n.current.salesCustomerId: r['customer_id'] ?? '',
    AppL10n.current.salesQuotationAmount: r['total_amount'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = _statusText(s);
    final c = AppColors.of(context);
    // §2.4：0草稿=待办(warning)，1已报价=进行中(primary)，2已转化=终态(success)，3已过期=失败(danger)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.primaryBg, c.primaryPressed),
      2 => (c.successBg, c.successText),
      3 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(label, style: TextStyle(color: fg, fontSize: 12)),
    );
  }
}
