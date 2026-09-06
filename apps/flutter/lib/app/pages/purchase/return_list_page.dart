// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 采购退货页 — 覆盖 GET/POST/PUT/DELETE /admin/v1/purchase/return
/// 契约（erp_purchase_return，无 name 列）：code/receive_id/supplier_id/warehouse_id/
/// total_amount/status(0待出库|1已出库)/remark/returned_at
class PurchaseReturnListPage extends StatefulWidget {
  const PurchaseReturnListPage({super.key});
  @override
  State<PurchaseReturnListPage> createState() => _PurchaseReturnListPageState();
}

class _PurchaseReturnListPageState extends State<PurchaseReturnListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 收货单选项：id(hashid)→{label, supplier_id, warehouse_id}，取自 /admin/v1/purchase/receive
  /// 列表行（含嵌套 supplier/warehouse 关联）。打开新增/编辑弹窗前懒加载。
  Map<String, String> _receiveLabels = {};
  Map<String, Map<String, String>> _receiveMeta = {};

  static List<String> get _statusLabels =>
      [AppL10n.current.purchaseReturnStatusPending, AppL10n.current.purchaseReturnStatusDone];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/purchase/return', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载收货单下拉（receive_id 必填 hashid；其 supplier/warehouse 由该单自动带出，杜绝手动错配）。
  Future<bool> _ensureReceives() async {
    if (_receiveLabels.isNotEmpty) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/purchase/receive', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _receiveLabels = { for (final r in list) '${r['id']}': '${r['code'] ?? ''}' };
      _receiveMeta = {
        for (final r in list)
          '${r['id']}': {'supplier_id': '${r['supplier_id'] ?? ''}', 'warehouse_id': '${r['warehouse_id'] ?? ''}'}
      };
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    if (!await _ensureReceives() || !mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/purchase/return', data: _buildPayload(data, null));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureReceives() || !mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/purchase/return/${row['id']}', data: _buildPayload(data, row));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm,
        content: AppL10n.of(context).purchaseDeleteConfirmMsg('${row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/return/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端 ReturnController::store 契约对齐：code 留空自动生成 PRN+时间戳（uk_code 唯一）；
  // receive 必填下拉，supplier/warehouse 由其自动带出（三个 FK 表列均 NOT NULL）
  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    return [
      FormFieldConfig(name: 'code', label: AppL10n.of(context).purchaseReturnNo, hint: AppL10n.of(context).purchaseReturnNoHint),
      FormFieldConfig(
        name: 'receive_id',
        label: AppL10n.of(context).purchaseReceiveNo,
        required: true,
        type: FormFieldType.dropdown,
        options: _receiveLabels.keys.toList(),
        optionLabels: _receiveLabels,
      ),
      FormFieldConfig(name: 'total_amount', label: AppL10n.of(context).purchaseTotalAmount, type: FormFieldType.number,
        hint: AppL10n.of(context).purchaseAmountExampleHint),
      FormFieldConfig(name: 'returned_at',
        label: AppL10n.of(context).purchaseReturnedAt,
        initialValue: '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}',
        hint: AppL10n.of(context).purchaseDateTimeHint),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).purchaseRemark, type: FormFieldType.multiline),
    ];
  }

  /// 组装后端 store()/update() 接收的参数（仅真实表列；supplier/warehouse 随收货单选自动带出）。
  /// [row] 为编辑行时回退用（收货单选项失效时沿用原行 FK，保持引用不被抹除）。
  Map<String, dynamic> _buildPayload(Map<String, String> data, Map<String, dynamic>? row) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'PRN${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final receiveId = data['receive_id']?.trim() ?? '';
    String fkOf(String field) {
      final viaReceive = _receiveMeta[receiveId]?[field];
      if (viaReceive != null && viaReceive.isNotEmpty) return viaReceive;
      return '${row?[field] ?? ''}';
    }
    return {
      'code': code,
      'receive_id': receiveId,
      'supplier_id': fkOf('supplier_id'),
      'warehouse_id': fkOf('warehouse_id'),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
      'returned_at': data['returned_at']?.trim(),
      'remark': data['remark']?.trim() ?? '',
    };
  }

  String _p2(int v) => v.toString().padLeft(2, '0');

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.purchaseReturnTitle,
    moduleKey: 'purchase',
    primaryColumnIndex: 0,
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
    rightAlignColumns: [3],
  );

  List<String> _columns() => [AppL10n.current.purchaseReturnNo, AppL10n.current.purchaseReceiveNo,
    AppL10n.current.purchaseReceiveSupplier, AppL10n.current.purchaseTotalAmount,
    AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseReturnNo: r['code'] ?? '',
    AppL10n.current.purchaseReceiveNo: r['receive_code'] ?? '',
    AppL10n.current.purchaseReceiveSupplier: r['supplier_name'] ?? '',
    AppL10n.current.purchaseTotalAmount: '${r['total_amount'] ?? ''}',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

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
