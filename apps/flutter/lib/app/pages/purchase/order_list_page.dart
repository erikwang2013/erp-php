// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class PurchaseOrderListPage extends StatefulWidget {
  const PurchaseOrderListPage({super.key});
  @override
  State<PurchaseOrderListPage> createState() => _PurchaseOrderListPageState();
}

class _PurchaseOrderListPageState extends State<PurchaseOrderListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
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
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/purchase/order', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseOrderAddTitle, fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/v1/purchase/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseOrderEditTitle, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/purchase/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.purchaseDeleteConfirmMsg('${row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/order/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 采购结算：打开结算表单（金额/日期/方式 → 后端 amount/paid_amount/settled_at/status），
  /// 提交 POST /admin/purchase/settlement。
  Future<void> _settle(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultSettledAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    await FormDialog.show(context, title: l10n.purchaseSettleDialog, fields: [
      FormFieldConfig(name: 'supplier_id', label: l10n.purchaseSupplierId, required: true, initialValue: '${row['supplier_id'] ?? ''}'),
      FormFieldConfig(name: 'receive_id', label: l10n.purchaseReceiveId, required: true),
      FormFieldConfig(name: 'amount', label: l10n.purchasePayableAmount, type: FormFieldType.number, hint: l10n.purchaseAmountExampleHint),
      FormFieldConfig(name: 'paid_amount', label: l10n.purchasePaidAmount, type: FormFieldType.number, hint: l10n.purchasePaidDefaultHint),
      FormFieldConfig(name: 'status', label: l10n.purchaseSettleStatusLabel, type: FormFieldType.dropdown,
        options: _settleStatusOptions(), initialValue: _settleStatusOption(0)),
      FormFieldConfig(name: 'settled_at', label: l10n.purchaseSettledAt, initialValue: defaultSettledAt,
        hint: l10n.purchaseDateTimeHint),
    ], onSubmit: (data) async {
      final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
      await ApiService.instance.post('/admin/v1/purchase/settlement', data: {
        'supplier_id': data['supplier_id']?.trim(),
        'receive_id': data['receive_id']?.trim(),
        'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
        'paid_amount': (data['paid_amount']?.trim().isEmpty ?? true) ? '0' : data['paid_amount']!.trim(),
        'status': statusRaw,
        'settled_at': data['settled_at']?.trim(),
      });
      _load(); return true;
    });
  }

  // 后端 erp_purchase_order 字段: code/apply_id/supplier_id/warehouse_id/
  // total_amount/status/remark/ordered_at（store() 同时校验 name 必填）
  List<String> get _statusLabels => [
    AppL10n.current.purchaseOrderStatusPending,
    AppL10n.current.purchaseOrderStatusApproved,
    AppL10n.current.purchaseOrderStatusPartReceived,
    AppL10n.current.purchaseOrderStatusReceived,
    AppL10n.current.purchaseOrderStatusCancelled,
  ];

  /// 下拉选项文案与后端 status 数字一一对应（提交时取 ' - ' 前缀）。
  String _statusOption(int i) => '$i - ${_statusLabels[i]}';

  List<String> get _settleStatusLabels => [
    AppL10n.current.purchaseSettleStatusUnsettled,
    AppL10n.current.purchaseSettleStatusPartial,
    AppL10n.current.purchaseSettleStatusSettled,
  ];

  String _settleStatusOption(int i) => '$i - ${_settleStatusLabels[i]}';
  List<String> _settleStatusOptions() => [for (var i = 0; i < _settleStatusLabels.length; i++) _settleStatusOption(i)];

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultOrderedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    return [
      FormFieldConfig(name: 'name', label: l10n.purchaseOrderName, required: true, hint: l10n.purchaseOrderNameRequiredHint),
      FormFieldConfig(name: 'code', label: l10n.purchaseOrderCode, hint: l10n.purchaseOrderCodeHint),
      FormFieldConfig(name: 'supplier_id', label: l10n.purchaseSupplierId, required: true, hint: l10n.purchaseSupplierIdHint),
      FormFieldConfig(name: 'apply_id', label: l10n.purchaseApplyId, hint: l10n.purchaseZeroHint),
      FormFieldConfig(name: 'warehouse_id', label: l10n.purchaseWarehouseId, hint: l10n.purchaseZeroHint),
      FormFieldConfig(name: 'total_amount', label: l10n.purchaseOrderTotalAmount, type: FormFieldType.number, hint: l10n.purchaseOrderTotalHint),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) _statusOption(i)], initialValue: _statusOption(0)),
      FormFieldConfig(name: 'ordered_at', label: l10n.purchaseOrderTimeLabel, initialValue: defaultOrderedAt,
        hint: l10n.purchaseDateTimeHint),
      FormFieldConfig(name: 'remark', label: l10n.purchaseRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'PO${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'name': data['name'],
      'code': code,
      'supplier_id': data['supplier_id']?.trim(),
      'apply_id': (data['apply_id']?.trim().isEmpty ?? true) ? '0' : data['apply_id']!.trim(),
      'warehouse_id': (data['warehouse_id']?.trim().isEmpty ?? true) ? '0' : data['warehouse_id']!.trim(),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
      'status': statusRaw,
      'ordered_at': data['ordered_at']?.trim(),
      'remark': data['remark']?.trim() ?? '',
    };
  }

  /// 编辑回填：把后端数字 status 转回下拉选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final s = d['status'];
    if (s is int && s >= 0 && s < _statusLabels.length) {
      d['status'] = _statusOption(s);
    }
    return d;
  }

  String _p2(int v) => v.toString().padLeft(2, '0');

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
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.purchaseOrderCode, AppL10n.current.purchaseSupplierId, AppL10n.current.purchaseTotalAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseOrderCode: r['code'] ?? '',
    AppL10n.current.purchaseSupplierId: r['supplier_id'] ?? '',
    AppL10n.current.purchaseTotalAmount: r['total_amount'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: Icon(Icons.paid, size: 18, color: AppColors.of(context).primary),
        tooltip: AppL10n.current.purchaseSettle, onPressed: () => _settle(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0待审核=待办(warning)，1已审核/3已收货=终态(success)，
  /// 2部分收货=进行中(primary)，4已取消=失败(danger)。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final c = AppColors.of(context);
    // §2.4: 0待审核=待办(warning)，1已审核/3已收货=终态(success)，2部分收货=进行中(primary)，
    // 4已取消=失败(danger)
    final (bg, fg) = switch (i) {
      1 || 3 => (c.successBg, c.successText),
      4 => (c.dangerBg, c.dangerText),
      2 => (c.primaryBg, c.primaryPressed),
      0 => (c.warningBg, c.warningText),
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
