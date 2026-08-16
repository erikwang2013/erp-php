// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 销售结算管理页 — 覆盖 POST/GET/PUT/DELETE /admin/sales/settlement
/// 后端字段: customer_id / delivery_id / amount / received_amount / status / settled_at
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

  static const List<String> _statusLabels = ['未结算', '部分结算', '已结算'];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/sales/settlement', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  /// 结算表单（金额/日期/方式 → 后端字段 amount/received_amount/settled_at/status）
  List<FormFieldConfig> _formFields({Map<String, dynamic>? prefill}) {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultSettledAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    return [
      FormFieldConfig(name: 'customer_id', label: '客户ID', required: true,
        initialValue: '${prefill?['customer_id'] ?? ''}'),
      FormFieldConfig(name: 'delivery_id', label: '发货单ID', required: true),
      FormFieldConfig(name: 'amount', label: '应收金额', type: FormFieldType.number, hint: '如 1000.00'),
      FormFieldConfig(name: 'received_amount', label: '已收金额', type: FormFieldType.number, hint: '默认 0'),
      FormFieldConfig(name: 'status', label: '结算状态', type: FormFieldType.dropdown,
        options: ['0 - 未结算', '1 - 部分结算', '2 - 已结算'], initialValue: '0 - 未结算'),
      FormFieldConfig(name: 'settled_at', label: '结算时间', initialValue: defaultSettledAt,
        hint: '格式 YYYY-MM-DD HH:mm:ss'),
    ];
  }

  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'customer_id': data['customer_id']?.trim(),
      'delivery_id': data['delivery_id']?.trim(),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
      'received_amount': (data['received_amount']?.trim().isEmpty ?? true) ? '0' : data['received_amount']!.trim(),
      'status': statusRaw,
      'settled_at': data['settled_at']?.trim(),
    };
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增销售结算', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/sales/settlement', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑销售结算', fields: _formFields(), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/sales/settlement/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除该销售结算记录吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/sales/settlement/${row['id']}', data: {'password': password});
      _load(); return true;
    });
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

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    filterBar: DropdownButton<String>(
      value: _statusFilter,
      hint: const Text('状态'),
      items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增结算')),
    ],
  );

  List<String> _columns() => ['客户ID', '发货单ID', '应收金额', '已收金额', '状态', '结算时间', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '客户ID': r['customer_id'] ?? '',
    '发货单ID': r['delivery_id'] ?? '',
    '应收金额': r['amount'] ?? '',
    '已收金额': r['received_amount'] ?? '',
    '状态': _chip(_statusText(r['status'])),
    '结算时间': r['settled_at'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(String? s) {
    final color = switch (s) {
      '已结算' => Colors.green,
      '部分结算' => Colors.orange,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(s ?? '', style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
