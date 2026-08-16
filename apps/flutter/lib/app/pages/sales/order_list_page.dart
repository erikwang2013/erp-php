// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
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

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/sales/order', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增销售订单', fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/sales/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑销售订单', fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/sales/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/sales/order/${row['id']}', data: {'password': password});
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
    await FormDialog.show(context, title: '销售结算', fields: [
      FormFieldConfig(name: 'customer_id', label: '客户ID', required: true, initialValue: '${row['customer_id'] ?? ''}'),
      FormFieldConfig(name: 'delivery_id', label: '发货单ID', required: true),
      FormFieldConfig(name: 'amount', label: '应收金额', type: FormFieldType.number, hint: '如 1000.00'),
      FormFieldConfig(name: 'received_amount', label: '已收金额', type: FormFieldType.number, hint: '默认 0'),
      FormFieldConfig(name: 'status', label: '结算状态', type: FormFieldType.dropdown,
        options: ['0 - 未结算', '1 - 部分结算', '2 - 已结算'], initialValue: '0 - 未结算'),
      FormFieldConfig(name: 'settled_at', label: '结算时间', initialValue: defaultSettledAt,
        hint: '格式 YYYY-MM-DD HH:mm:ss'),
    ], onSubmit: (data) async {
      final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
      await ApiService.instance.post('/admin/sales/settlement', data: {
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

  // 后端 erik_sales_order 字段: code/customer_id/warehouse_id/total_amount/
  // discount_amount/status/remark/ordered_at（store() 同时校验 name 必填）
  static const List<String> _statusLabels = ['待审核', '已审核', '部分发货', '已发货', '已取消'];

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultOrderedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    return [
      FormFieldConfig(name: 'name', label: '订单名称', required: true, hint: '必填（后端校验）'),
      FormFieldConfig(name: 'code', label: '订单编号', hint: '留空自动生成 SO+时间戳'),
      FormFieldConfig(name: 'customer_id', label: '客户ID', required: true, hint: '从客户列表页获取数字ID'),
      FormFieldConfig(name: 'warehouse_id', label: '发货仓库ID', hint: '留空为0'),
      FormFieldConfig(name: 'total_amount', label: '订单总金额', type: FormFieldType.number, hint: '如 100.00'),
      FormFieldConfig(name: 'discount_amount', label: '优惠金额', type: FormFieldType.number, hint: '默认0'),
      FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown,
        options: ['0 - 待审核', '1 - 已审核', '2 - 部分发货', '3 - 已发货', '4 - 已取消'], initialValue: '0 - 待审核'),
      FormFieldConfig(name: 'ordered_at', label: '下单时间', initialValue: defaultOrderedAt,
        hint: '格式 YYYY-MM-DD HH:mm:ss'),
      FormFieldConfig(name: 'remark', label: '备注', type: FormFieldType.multiline),
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
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['订单编号', '客户ID', '总金额', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '订单编号': r['code'] ?? '',
    '客户ID': r['customer_id'] ?? '',
    '总金额': r['total_amount'] ?? '',
    '状态': _chip(_statusText(r['status'])),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.paid, size: 18, color: Colors.teal),
        tooltip: '结算', onPressed: () => _settle(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
  Widget _chip(String? s) {
    final color = switch (s) {
      '待审批' || '待审核' || '待收货' || '草稿' || '部分收货' || '部分发货' => Colors.orange,
      '已批准' || '已审核' || '已收货' || '已发货' || '已报价' || '已转订单' || '已完成' => Colors.green,
      '已驳回' || '已拒绝' || '已取消' || '已失效' => Colors.red,
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
