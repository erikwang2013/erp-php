// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class PurchaseApplyListPage extends StatefulWidget {
  const PurchaseApplyListPage({super.key});
  @override
  State<PurchaseApplyListPage> createState() => _PurchaseApplyListPageState();
}

class _PurchaseApplyListPageState extends State<PurchaseApplyListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;

  // 后端 erp_purchase_apply 列: code/apply_user_id/department/status/remark/
  // approved_at/approved_by（无 name 列）
  List<String> get _statusLabels => [
    AppL10n.current.purchaseApplyStatusPending,
    AppL10n.current.purchaseApplyStatusApproved,
    AppL10n.current.purchaseApplyStatusRejected,
    AppL10n.current.purchaseApplyStatusOrdered,
  ];

  /// 下拉选项文案与后端 status 数字一一对应（提交时取 ' - ' 前缀）。
  String _statusOption(int i) => '$i - ${_statusLabels[i]}';

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/purchase/apply', params: params);
      final d = res['data'];
      if (mounted) setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
    }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseApplyAddTitle, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/purchase/apply', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.purchaseApplyEditTitle, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/purchase/apply/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.purchaseDeleteConfirmMsg('${row['code'] ?? ''}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/apply/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'code', label: l10n.purchaseApplyNo, hint: l10n.purchaseApplyNoHint),
      FormFieldConfig(name: 'apply_user_id', label: l10n.purchaseApplyUserId, required: true, hint: l10n.purchaseApplyUserIdHint),
      FormFieldConfig(name: 'department', label: l10n.purchaseApplyDept),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) _statusOption(i)], initialValue: _statusOption(0)),
      FormFieldConfig(name: 'remark', label: l10n.purchaseRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（仅真实表列；status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'PA${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'code': code,
      'apply_user_id': data['apply_user_id']?.trim(),
      'department': data['department']?.trim() ?? '',
      'status': statusRaw,
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
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.purchaseApplyNo, AppL10n.current.purchaseApplyUserId, AppL10n.current.purchaseApplyDept, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseApplyNo: r['code'] ?? '',
    AppL10n.current.purchaseApplyUserId: r['apply_user_id'] ?? '',
    AppL10n.current.purchaseApplyDept: r['department'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标：0待审批 橙，1已批准/3已转订单 绿，2已驳回 红，其余蓝。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final color = switch (i) {
      1 || 3 => Colors.green,
      2 => Colors.red,
      0 => Colors.orange,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text, style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
