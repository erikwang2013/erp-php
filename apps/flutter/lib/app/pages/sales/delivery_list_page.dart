// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 销售发货页 — 覆盖 GET/PUT/DELETE /admin/v1/sales/delivery
/// 契约（erp_sales_delivery，无 name 列）：code/order_id/customer_id/warehouse_id/
/// status(0待出库|1已出库)/remark/delivered_at；列表带 order/customer/warehouse 关联。
/// 状态文案复用采购退货键（同为 待出库/已出库 语义）。
class SalesDeliveryListPage extends StatefulWidget {
  const SalesDeliveryListPage({super.key});
  @override
  State<SalesDeliveryListPage> createState() => _SalesDeliveryListPageState();
}

class _SalesDeliveryListPageState extends State<SalesDeliveryListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  static List<String> get _statusLabels =>
      [AppL10n.current.purchaseReturnStatusPending, AppL10n.current.purchaseReturnStatusDone];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/sales/delivery', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 发货单由销售订单发起发货生成（store() 需 items 明细并执行出库/生成应收，
  /// 非列表页可录入），故本页不做新增；编辑仅改备注（update() 只接收 remark）。
  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: [
      FormFieldConfig(name: 'remark', label: l10n.commonRemark, type: FormFieldType.multiline),
    ], initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/sales/delivery/${row['id']}',
          data: {'remark': data['remark']?.trim() ?? ''});
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.commonDeleteMsg('${row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/delivery/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.salesDeliveryTitle,
    moduleKey: 'sales',
    primaryColumnIndex: 0,
  );

  List<String> _columns() => [AppL10n.current.salesDeliveryNo, AppL10n.current.salesOrderNo,
    AppL10n.current.fieldCustomer, AppL10n.current.fieldWarehouse,
    AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesDeliveryNo: r['code'] ?? '',
    AppL10n.current.salesOrderNo: _relName(r['order'], 'code', r['order_id']),
    AppL10n.current.fieldCustomer: _relName(r['customer'], 'name', r['customer_id']),
    AppL10n.current.fieldWarehouse: _relName(r['warehouse'], 'name', r['warehouse_id']),
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 关联对象存在时优先显示名称（order.code / customer.name / warehouse.name），否则回退 ID。
  static String _relName(dynamic rel, String field, dynamic fallback) {
    if (rel is Map<String, dynamic>) {
      final v = rel[field];
      if (v != null && '$v'.isNotEmpty) return '$v';
    }
    return '${fallback ?? ''}';
  }

  /// 状态徽标（§2.4）：0待出库=待办(warning)，1已出库=终态(success)。
  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = (i != null && i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
    final c = AppColors.of(context);
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: label, bg: bg, fg: fg);
  }
}
