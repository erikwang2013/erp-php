// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class PurchaseReceiveListPage extends StatefulWidget {
  const PurchaseReceiveListPage({super.key});
  @override
  State<PurchaseReceiveListPage> createState() => _PurchaseReceiveListPageState();
}

class _PurchaseReceiveListPageState extends State<PurchaseReceiveListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;

  // 后端 erp_purchase_receive 列: code/order_id/supplier_id/warehouse_id/status/
  // remark/received_at；列表带 order/supplier/warehouse 关联
  List<String> get _statusLabels => [
    AppL10n.current.purchaseReceiveStatusPending,
    AppL10n.current.purchaseReceiveStatusDone,
  ];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/purchase/receive', params: params);
      final d = res['data'];
      if (mounted) setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
    }
  }

  /// 收货单由采购订单发起收货生成（store() 需 items 明细并执行入库/应付，
  /// 非列表页可录入），故本页不做新增；编辑仅改备注（update() 只接收 remark）。
  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseReceiveEditRemarkTitle, fields: [
      FormFieldConfig(name: 'remark', label: l10n.purchaseRemark, type: FormFieldType.multiline),
    ], initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/purchase/receive/${row['id']}',
          data: {'remark': data['remark']?.trim() ?? ''});
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.purchaseDeleteConfirmMsg('${row['code'] ?? ''}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/receive/${row['id']}', data: {'password': password});
      _load(); return true;
    });
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
      hint: Text(AppL10n.current.commonStatus),
      items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
  );

  List<String> _columns() => [AppL10n.current.purchaseReceiveNo, AppL10n.current.purchaseReceiveOrder, AppL10n.current.purchaseReceiveSupplier, AppL10n.current.purchaseReceiveWarehouse, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseReceiveNo: r['code'] ?? '',
    AppL10n.current.purchaseReceiveOrder: _relName(r['order'], 'code', r['order_id']),
    AppL10n.current.purchaseReceiveSupplier: _relName(r['supplier'], 'name', r['supplier_id']),
    AppL10n.current.purchaseReceiveWarehouse: _relName(r['warehouse'], 'name', r['warehouse_id']),
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 关联对象存在时优先显示名称（order.code / supplier.name / warehouse.name），否则回退 ID。
  static String _relName(dynamic rel, String field, dynamic fallback) {
    if (rel is Map<String, dynamic>) {
      final v = rel[field];
      if (v != null && '$v'.isNotEmpty) return '$v';
    }
    return '${fallback ?? ''}';
  }

  /// 状态徽标：0待入库 橙，1已入库 绿，其余蓝。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final c = AppColors.of(context);
    // §2.4: 0待入库=待办(warning)，1已入库=终态(success)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
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
