// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

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
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{
        'page': '$_page',
        'limit': '$_limit',
        'keyword': _keyword,
      };

      final res = await ApiService.instance.get(
        '/admin/v1/oms/order',
        params: params,
      );
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() {
        _rows = List<Map<String, dynamic>>.from(d['list'] ?? []);
        _total = d['total'] ?? 0;
        _loading = false;
        _error = null;
      });
      if (_rows.isEmpty && _page > 1) {
        _page--;
        _load();
        return;
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = ApiService.friendlyError(e);
        });
      }
    }
  }

  Future<void> _create() async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).omsAddOrder,
      fields: _formFields(),
      onSubmit: (data) async {
        final payload = _buildPayload(data);
        await ApiService.instance.post('/admin/v1/oms/order', data: payload);
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).omsEditOrder,
      fields: _formFields(),
      initialData: _toEditData(row),
      onSubmit: (data) async {
        final payload = _buildPayload(data);
        await ApiService.instance.put(
          '/admin/v1/oms/order/${row['id']}',
          data: payload,
        );
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg(
        '${row['channel_order_no'] ?? row['code'] ?? row['id']}',
      ),
      onConfirm: (password) async {
        await ApiService.instance.delete(
          '/admin/v1/oms/order/${row['id']}',
          data: {'password': password},
        );
        _load();
        return true;
      },
    );
  }

  /// 创建履约：填写发货仓库ID，调用 POST /admin/oms/order/{id}/fulfill。
  Future<void> _fulfill(Map<String, dynamic> row) async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).omsFulfillCreate,
      fields: [
        FormFieldConfig(
          name: 'warehouse_id',
          label: AppL10n.of(context).omsWarehouseId,
          required: true,
          hint: AppL10n.of(context).omsWarehouseIdHint,
        ),
      ],
      onSubmit: (data) async {
        await ApiService.instance.post(
          '/admin/v1/oms/order/${row['id']}/fulfill',
          data: {'warehouse_id': data['warehouse_id']?.trim()},
        );
        _load();
        return true;
      },
    );
  }

  // 后端 erp_oms_order 字段: order_id/channel/channel_order_no/channel_store/
  // fulfillment_status/payment_status/shipping_method/shipping_fee/
  // buyer_message/seller_note/priority/hold_until（store() 同时校验 code 必填）
  static const List<String> _channelOptions = [
    'manual',
    'web',
    'mobile',
    'api',
    'marketplace',
    'edi',
    'pos',
  ];

  /// 状态/优先级下拉文案（数字前缀与后端枚举一致，label 走 l10n）。
  List<String> get _fulfillmentLabels {
    final l = AppL10n.of(context);
    return [
      l.omsFulUnassigned,
      l.omsFulAssigned,
      l.omsFulPicking,
      l.omsFulPacked,
      l.omsFulShipped,
      l.omsFulSigned,
    ];
  }

  List<String> get _paymentLabels {
    final l = AppL10n.of(context);
    return [
      l.omsPayPending,
      l.omsPayPaid,
      l.omsPayPartialRefund,
      l.omsPayRefunded,
    ];
  }

  List<String> get _fulfillOptions => [
    for (final (i, s) in _fulfillmentLabels.indexed) '$i - $s',
  ];
  List<String> get _paymentOptions => [
    for (final (i, s) in _paymentLabels.indexed) '$i - $s',
  ];
  List<String> get _priorityOptions => [
    '1 - ${AppL10n.of(context).omsPriorityHigh}',
    '5 - ${AppL10n.of(context).omsPriorityNormal}',
    '9 - ${AppL10n.of(context).omsPriorityLow}',
  ];

  List<FormFieldConfig> _formFields() {
    final l = AppL10n.of(context);
    return [
      FormFieldConfig(
        name: 'code',
        label: l.omsOrderCode,
        required: true,
        hint: l.omsOrderCodeHint,
      ),
      FormFieldConfig(
        name: 'order_id',
        label: l.omsOrderId,
        required: true,
        hint: l.omsOrderIdHint,
      ),
      FormFieldConfig(
        name: 'channel',
        label: l.omsChannel,
        type: FormFieldType.dropdown,
        options: _channelOptions,
        initialValue: 'manual',
      ),
      FormFieldConfig(name: 'channel_order_no', label: l.omsChannelOrderNo),
      FormFieldConfig(name: 'channel_store', label: l.omsChannelStore),
      FormFieldConfig(
        name: 'fulfillment_status',
        label: l.omsFulfillStatus,
        type: FormFieldType.dropdown,
        options: _fulfillOptions,
        initialValue: '0 - ${l.omsFulUnassigned}',
      ),
      FormFieldConfig(
        name: 'payment_status',
        label: l.omsPaymentStatus,
        type: FormFieldType.dropdown,
        options: _paymentOptions,
        initialValue: '0 - ${l.omsPayPending}',
      ),
      FormFieldConfig(name: 'shipping_method', label: l.omsShippingMethod),
      FormFieldConfig(
        name: 'shipping_fee',
        label: l.omsShippingFee,
        type: FormFieldType.number,
        hint: l.omsShippingFeeHint,
      ),
      FormFieldConfig(
        name: 'priority',
        label: l.omsPriority,
        type: FormFieldType.dropdown,
        options: _priorityOptions,
        initialValue: '5 - ${l.omsPriorityNormal}',
      ),
      FormFieldConfig(
        name: 'buyer_message',
        label: l.omsBuyerMessage,
        type: FormFieldType.multiline,
      ),
      FormFieldConfig(
        name: 'seller_note',
        label: l.omsSellerNote,
        type: FormFieldType.multiline,
      ),
      FormFieldConfig(
        name: 'hold_until',
        label: l.omsHoldUntil,
        hint: l.omsHoldUntilHint,
      ),
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
      'shipping_fee': (data['shipping_fee']?.trim().isEmpty ?? true)
          ? '0'
          : data['shipping_fee']!.trim(),
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
      final l = AppL10n.of(context);
      d['priority'] =
          '$pr - ${pr == 1 ? l.omsPriorityHigh : (pr == 5 ? l.omsPriorityNormal : l.omsPriorityLow)}';
    }
    return d;
  }

  String _fulfillmentText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _fulfillmentLabels.length)
        ? _fulfillmentLabels[i]
        : '$s';
  }

  String _paymentText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _paymentLabels.length) ? _paymentLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total,
    page: _page,
    limit: _limit,
    loading: _loading,
    error: _error,
    onRetry: _load,
    keyword: _keyword,
    onSearch: (v) {
      _keyword = v;
      _page = 1;
      _load();
    },
    onPageChanged: (p) {
      _page = p;
      _load();
    },

    actions: [
      ElevatedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [
      l.omsChannelOrderNo,
      l.omsChannel,
      l.omsFulfillStatus,
      l.omsPaymentStatus,
      l.commonAction,
    ];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.omsChannelOrderNo: r['channel_order_no'] ?? '',
      l.omsChannel: r['channel'] ?? '',
      l.omsFulfillStatus: _chip(_fulfillmentText(r['fulfillment_status'])),
      l.omsPaymentStatus: _chip(_paymentText(r['payment_status'])),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(
              Icons.local_shipping,
              size: 18,
              color: Colors.teal,
            ),
            tooltip: l.omsFulfill,
            onPressed: () => _fulfill(r),
          ),
          IconButton(
            icon: const Icon(Icons.edit, size: 18),
            onPressed: () => _edit(r),
          ),
          IconButton(
            icon: const Icon(Icons.delete, size: 18, color: Colors.red),
            onPressed: () => _delete(r),
          ),
        ],
      ),
    };
  }

  Widget _chip(String s) {
    final l = AppL10n.of(context);
    final Color color;
    if (s == l.omsFulShipped || s == l.omsFulSigned || s == l.omsPayPaid) {
      color = Colors.green;
    } else if (s == l.omsFulPicking ||
        s == l.omsFulPacked ||
        s == l.omsPayPartialRefund) {
      color = Colors.orange;
    } else if (s == l.omsPayRefunded) {
      color = Colors.red;
    } else {
      color = Colors.blue;
    }
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
