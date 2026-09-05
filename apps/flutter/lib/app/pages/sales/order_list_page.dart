// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class SalesOrderListPage extends StatefulWidget {
  const SalesOrderListPage({super.key});
  @override
  State<SalesOrderListPage> createState() => _SalesOrderListPageState();
}

class _SalesOrderListPageState extends State<SalesOrderListPage> {
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
      final res = await ApiService.instance.get('/admin/v1/sales/order', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).salesOrderAdd, fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/v1/sales/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).salesOrderEdit, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/sales/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/order/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 销售结算：打开结算表单（金额/日期/方式 → 后端 amount/received_amount/settled_at/status），
  /// 提交 POST /admin/sales/settlement。
  Future<void> _settle(Map<String, dynamic> row) async {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultSettledAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    await FormDialog.show(context, title: AppL10n.of(context).salesSettleTitle, fields: [
      FormFieldConfig(name: 'customer_id', label: AppL10n.of(context).salesCustomerId, required: true, initialValue: '${row['customer_id'] ?? ''}'),
      FormFieldConfig(name: 'delivery_id', label: AppL10n.of(context).salesDeliveryId, required: true),
      FormFieldConfig(name: 'amount', label: AppL10n.of(context).salesReceivableAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('1000.00')),
      FormFieldConfig(name: 'received_amount', label: AppL10n.of(context).salesReceivedAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonDefaultZero),
      FormFieldConfig(name: 'status', label: AppL10n.of(context).salesSettleStatus, type: FormFieldType.dropdown,
        options: ['0 - ${AppL10n.of(context).salesSettlementUnsettled}', '1 - ${AppL10n.of(context).salesSettlementPartSettled}', '2 - ${AppL10n.of(context).salesSettlementSettled}'], initialValue: '0 - ${AppL10n.of(context).salesSettlementUnsettled}'),
      FormFieldConfig(name: 'settled_at', label: AppL10n.of(context).salesSettledAt, initialValue: defaultSettledAt,
        hint: AppL10n.of(context).commonDateTimeFormat),
    ], onSubmit: (data) async {
      final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
      await ApiService.instance.post('/admin/v1/sales/settlement', data: {
        'customer_id': data['customer_id']?.trim(),
        'delivery_id': data['delivery_id']?.trim(),
        'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
        'received_amount': (data['received_amount']?.trim().isEmpty ?? true) ? '0' : data['received_amount']!.trim(),
        'status': statusRaw,
        'settled_at': data['settled_at']?.trim(),
      });
      _load(); return true;
    });
  }

  // 后端 erp_sales_order 字段: code/customer_id/warehouse_id/total_amount/
  // discount_amount/status/remark/ordered_at（store() 同时校验 name 必填）
  static List<String> get _statusLabels => [AppL10n.current.salesOrderPending, AppL10n.current.salesOrderReviewed, AppL10n.current.salesOrderPartShipped, AppL10n.current.salesOrderShipped, AppL10n.current.salesOrderCancelled];

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultOrderedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    return [
      FormFieldConfig(name: 'name', label: AppL10n.of(context).salesOrderName, required: true, hint: AppL10n.of(context).commonRequiredBackend),
      FormFieldConfig(name: 'code', label: AppL10n.of(context).salesOrderNo, hint: AppL10n.of(context).salesOrderCodeHint),
      FormFieldConfig(name: 'customer_id', label: AppL10n.of(context).salesCustomerId, required: true, hint: AppL10n.of(context).salesCustomerIdHint),
      FormFieldConfig(name: 'warehouse_id', label: AppL10n.of(context).salesWarehouseId, hint: AppL10n.of(context).salesWarehouseIdHint),
      FormFieldConfig(name: 'total_amount', label: AppL10n.of(context).salesOrderTotalAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('100.00')),
      FormFieldConfig(name: 'discount_amount', label: AppL10n.of(context).salesDiscountAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonDefaultZero),
      FormFieldConfig(name: 'status', label: AppL10n.of(context).commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) '$i - ${_statusLabels[i]}'], initialValue: '0 - ${_statusLabels[0]}'),
      FormFieldConfig(name: 'ordered_at', label: AppL10n.of(context).salesOrderedAt, initialValue: defaultOrderedAt,
        hint: AppL10n.of(context).commonDateTimeFormat),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'SO${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'name': data['name'],
      'code': code,
      'customer_id': data['customer_id']?.trim(),
      'warehouse_id': (data['warehouse_id']?.trim().isEmpty ?? true) ? '0' : data['warehouse_id']!.trim(),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
      'discount_amount': (data['discount_amount']?.trim().isEmpty ?? true) ? '0' : data['discount_amount']!.trim(),
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

  List<String> _columns() => [AppL10n.current.salesOrderNo, AppL10n.current.salesCustomerId, AppL10n.current.salesTotalAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesOrderNo: r['code'] ?? '',
    AppL10n.current.salesCustomerId: r['customer_id'] ?? '',
    AppL10n.current.salesTotalAmount: r['total_amount'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: Icon(Icons.paid, size: 18, color: AppColors.of(context).primary),
        tooltip: AppL10n.current.salesSettleTooltip, onPressed: () => _settle(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = _statusText(s);
    final c = AppColors.of(context);
    // §2.4：0待审批=待办(warning)，1已审批/3已发货=终态(success)，2部分发货=进行中(primary)，4已取消=失败(danger)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 || 3 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      4 => (c.dangerBg, c.dangerText),
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
