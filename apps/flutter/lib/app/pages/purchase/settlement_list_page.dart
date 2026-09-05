// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 采购结算管理页 — 覆盖 POST/GET/PUT/DELETE /admin/purchase/settlement
/// 列表为应付记录薄视图；新增即付款核销（receive_id + receipt_payment_id + amount），
/// 编辑仅调应付金额，status/paid_amount/settled_at 由服务端推导返回
class PurchaseSettlementListPage extends StatefulWidget {
  const PurchaseSettlementListPage({super.key});
  @override
  State<PurchaseSettlementListPage> createState() => _PurchaseSettlementListPageState();
}

class _PurchaseSettlementListPageState extends State<PurchaseSettlementListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  List<String> get _statusLabels => [
    AppL10n.current.purchaseSettleStatusUnsettled,
    AppL10n.current.purchaseSettleStatusPartial,
    AppL10n.current.purchaseSettleStatusSettled,
  ];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/purchase/settlement', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 结算表单: 新增=核销登记（收货单+付款单+金额），编辑=仅调应付金额
  List<FormFieldConfig> _formFields({bool forCreate = true}) {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'receive_id', label: l10n.purchaseReceiveId, required: true),
      if (forCreate) FormFieldConfig(name: 'receipt_payment_id', label: l10n.purchaseReceiptPaymentId, required: true,
        hint: l10n.purchaseReceiptPaymentIdHint),
      FormFieldConfig(name: 'amount', label: forCreate ? l10n.purchaseWriteoffAmount : l10n.purchasePayableAmount,
        type: FormFieldType.number, hint: l10n.purchaseAmountExampleHint),
    ];
  }

  Map<String, dynamic> _buildPayload(Map<String, String> data, {bool forCreate = true}) {
    return {
      'receive_id': data['receive_id']?.trim(),
      if (forCreate) 'receipt_payment_id': data['receipt_payment_id']?.trim(),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
    };
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseSettlementAddTitle, fields: _formFields(forCreate: true), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/purchase/settlement', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseSettlementEditTitle, fields: _formFields(forCreate: false), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/purchase/settlement/${row['id']}', data: _buildPayload(data, forCreate: false));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.purchaseSettlementDeleteMsg, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/settlement/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Map<String, dynamic> _toEditData(Map<String, dynamic> row) => Map<String, dynamic>.from(row);

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
      hint: Text(AppL10n.current.commonStatus),
      items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).purchaseSettlementAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.purchaseSupplierId, AppL10n.current.purchaseReceiveId, AppL10n.current.purchasePayableAmount, AppL10n.current.purchasePaidAmount, AppL10n.current.commonStatus, AppL10n.current.purchaseSettledAt, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseSupplierId: r['supplier_id'] ?? '',
    AppL10n.current.purchaseReceiveId: r['receive_id'] ?? '',
    AppL10n.current.purchasePayableAmount: r['amount'] ?? '',
    AppL10n.current.purchasePaidAmount: r['paid_amount'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.purchaseSettledAt: r['settled_at'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0未结算=待付款待办(warning)，1部分结算=进行中(primary)，2已结算=终态(success)。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final c = AppColors.of(context);
    // §2.4: 0未结算=待付款待办(warning)，1部分结算=进行中(primary)，2已结算=终态(success)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.primaryBg, c.primaryPressed),
      2 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text, style: TextStyle(color: fg, fontSize: 12)),
    );
  }
}
