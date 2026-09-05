// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 销售结算管理页 — 覆盖 POST/GET/PUT/DELETE /admin/sales/settlement
/// 列表为应收记录薄视图；新增即收款核销（delivery_id + receipt_payment_id + amount），
/// 编辑仅调应收金额，status/received_amount/settled_at 由服务端推导返回
class SalesSettlementListPage extends StatefulWidget {
  const SalesSettlementListPage({super.key});
  @override
  State<SalesSettlementListPage> createState() => _SalesSettlementListPageState();
}

class _SalesSettlementListPageState extends State<SalesSettlementListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  static List<String> get _statusLabels => [AppL10n.current.salesSettlementUnsettled, AppL10n.current.salesSettlementPartSettled, AppL10n.current.salesSettlementSettled];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/sales/settlement', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 结算表单: 新增=核销登记（发货单+收款单+金额），编辑=仅调应收金额
  List<FormFieldConfig> _formFields({bool forCreate = true}) => [
    FormFieldConfig(name: 'delivery_id', label: AppL10n.of(context).salesDeliveryId, required: true),
    if (forCreate) FormFieldConfig(name: 'receipt_payment_id', label: AppL10n.of(context).salesReceiptPaymentId, required: true,
      hint: AppL10n.of(context).salesReceiptPaymentHint),
    FormFieldConfig(name: 'amount', label: forCreate ? AppL10n.of(context).salesWriteoffAmount : AppL10n.of(context).salesReceivableAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('1000.00')),
  ];

  Map<String, dynamic> _buildPayload(Map<String, String> data, {bool forCreate = true}) {
    return {
      'delivery_id': data['delivery_id']?.trim(),
      if (forCreate) 'receipt_payment_id': data['receipt_payment_id']?.trim(),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
    };
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).salesSettlementAdd, fields: _formFields(forCreate: true), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/sales/settlement', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).salesSettlementEdit, fields: _formFields(forCreate: false), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/sales/settlement/${row['id']}', data: _buildPayload(data, forCreate: false));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).salesSettlementDeleteMsg, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/settlement/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Map<String, dynamic> _toEditData(Map<String, dynamic> row) => Map<String, dynamic>.from(row);

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
    filterBar: DropdownButton<String>(
      value: _statusFilter,
      hint: Text(AppL10n.of(context).commonStatus),
      items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).salesSettlementAddButton)),
    ],
  );

  List<String> _columns() => [AppL10n.current.salesCustomerId, AppL10n.current.salesDeliveryId, AppL10n.current.salesReceivableAmount, AppL10n.current.salesReceivedAmount, AppL10n.current.commonStatus, AppL10n.current.salesSettledAt, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesCustomerId: r['customer_id'] ?? '',
    AppL10n.current.salesDeliveryId: r['delivery_id'] ?? '',
    AppL10n.current.salesReceivableAmount: r['amount'] ?? '',
    AppL10n.current.salesReceivedAmount: r['received_amount'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.salesSettledAt: r['settled_at'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(dynamic s) {
    const colors = [Colors.blue, Colors.orange, Colors.green];
    final i = s is int ? s : int.tryParse('$s');
    final color = (i == null || i < 0 || i >= colors.length) ? Colors.blue : colors[i];
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
