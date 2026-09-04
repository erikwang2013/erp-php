// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class OmsOrderListPage extends StatefulWidget {
  const OmsOrderListPage({super.key});
  @override
  State<OmsOrderListPage> createState() => _OmsOrderListPageState();
}

class _OmsOrderListPageState extends State<OmsOrderListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      
      final res = await ApiService.instance.get('/admin/v1/oms/order', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增OMS订单', fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/v1/oms/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑OMS订单', fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/oms/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['channel_order_no'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/oms/order/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 创建履约：填写发货仓库ID，调用 POST /admin/oms/order/{id}/fulfill。
  Future<void> _fulfill(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '创建履约', fields: const [
      FormFieldConfig(name: 'warehouse_id', label: '发货仓库ID', required: true, hint: '后端要求提供发货仓库'),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/oms/order/${row['id']}/fulfill', data: {
        'warehouse_id': data['warehouse_id']?.trim(),
      });
      _load(); return true;
    });
  }

  // 后端 erp_oms_order 字段: order_id/channel/channel_order_no/channel_store/
  // fulfillment_status/payment_status/shipping_method/shipping_fee/
  // buyer_message/seller_note/priority/hold_until（store() 同时校验 code 必填）
  static const List<String> _channelOptions = ['manual', 'web', 'mobile', 'api', 'marketplace', 'edi', 'pos'];
  static const List<String> _fulfillmentLabels = ['未分配', '已分配', '拣货中', '已打包', '已发货', '已签收'];
  static const List<String> _paymentLabels = ['待支付', '已支付', '部分退款', '已退款'];
  static const List<String> _priorityOptions = ['1 - 最高', '5 - 正常', '9 - 最低'];

  List<FormFieldConfig> _formFields() {
    return [
      FormFieldConfig(name: 'code', label: '订单编码', required: true, hint: '必填（后端校验），如 OM+时间戳'),
      FormFieldConfig(name: 'order_id', label: '关联销售订单ID', required: true, hint: '从销售订单列表页获取数字ID'),
      FormFieldConfig(name: 'channel', label: '渠道', type: FormFieldType.dropdown,
        options: _channelOptions, initialValue: 'manual'),
      FormFieldConfig(name: 'channel_order_no', label: '渠道订单号'),
      FormFieldConfig(name: 'channel_store', label: '渠道店铺名称'),
      FormFieldConfig(name: 'fulfillment_status', label: '履约状态', type: FormFieldType.dropdown,
        options: ['0 - 未分配', '1 - 已分配', '2 - 拣货中', '3 - 已打包', '4 - 已发货', '5 - 已签收'],
        initialValue: '0 - 未分配'),
      FormFieldConfig(name: 'payment_status', label: '支付状态', type: FormFieldType.dropdown,
        options: ['0 - 待支付', '1 - 已支付', '2 - 部分退款', '3 - 已退款'], initialValue: '0 - 待支付'),
      FormFieldConfig(name: 'shipping_method', label: '配送方式'),
      FormFieldConfig(name: 'shipping_fee', label: '运费', type: FormFieldType.number, hint: '如 10.00'),
      FormFieldConfig(name: 'priority', label: '优先级', type: FormFieldType.dropdown,
        options: _priorityOptions, initialValue: '5 - 正常'),
      FormFieldConfig(name: 'buyer_message', label: '买家备注', type: FormFieldType.multiline),
      FormFieldConfig(name: 'seller_note', label: '卖家备注', type: FormFieldType.multiline),
      FormFieldConfig(name: 'hold_until', label: '冻结时间', hint: '格式 YYYY-MM-DD HH:mm:ss，可留空'),
    ];
  }

  /// 组装后端 store()/update() 接收的参数（状态/优先级拆出数字/枚举值）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    return {
      'code': data['code']?.trim() ?? '',
      'order_id': data['order_id']?.trim(),
      'channel': data['channel']?.trim(),
      'channel_order_no': data['channel_order_no']?.trim() ?? '',
      'channel_store': data['channel_store']?.trim() ?? '',
      'fulfillment_status': pick('fulfillment_status'),
      'payment_status': pick('payment_status'),
      'shipping_method': data['shipping_method']?.trim() ?? '',
      'shipping_fee': (data['shipping_fee']?.trim().isEmpty ?? true) ? '0' : data['shipping_fee']!.trim(),
      'priority': pick('priority'),
      'buyer_message': data['buyer_message']?.trim() ?? '',
      'seller_note': data['seller_note']?.trim() ?? '',
      'hold_until': data['hold_until']?.trim(),
    };
  }

  /// 编辑回填：把后端数字状态/优先级转回下拉选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final f = d['fulfillment_status'];
    if (f is int && f >= 0 && f < _fulfillmentLabels.length) {
      d['fulfillment_status'] = '$f - ${_fulfillmentLabels[f]}';
    }
    final p = d['payment_status'];
    if (p is int && p >= 0 && p < _paymentLabels.length) {
      d['payment_status'] = '$p - ${_paymentLabels[p]}';
    }
    final pr = d['priority'];
    if (pr is int && (pr == 1 || pr == 5 || pr == 9)) {
      d['priority'] = '$pr - ${pr == 1 ? '最高' : (pr == 5 ? '正常' : '最低')}';
    }
    return d;
  }

  static String _fulfillmentText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _fulfillmentLabels.length) ? _fulfillmentLabels[i] : '$s';
  }

  static String _paymentText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _paymentLabels.length) ? _paymentLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['渠道订单号', '渠道', '履约状态', '支付状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '渠道订单号': r['channel_order_no'] ?? '',
    '渠道': r['channel'] ?? '',
    '履约状态': _chip(_fulfillmentText(r['fulfillment_status'])),
    '支付状态': _chip(_paymentText(r['payment_status'])),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.local_shipping, size: 18, color: Colors.teal),
        tooltip: '履约', onPressed: () => _fulfill(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(String s) {
    final color = switch (s) {
      '已发货' || '已签收' || '已支付' => Colors.green,
      '拣货中' || '已打包' || '部分退款' => Colors.orange,
      '已退款' => Colors.red,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(s, style: TextStyle(color: color, fontSize: 12)),
    );
  }

}
