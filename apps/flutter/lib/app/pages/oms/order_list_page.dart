// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../utils/format.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/status_badge.dart';
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
    // 必填外键（order_id）选项先就位再弹窗（失败已弹提示，此处直接返回）
    if (!await _ensureOrders()) return;
    if (!mounted) return;
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
    if (!await _ensureOrders()) return;
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).omsEditOrder,
      fields: _formFields(row: row),
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
        '${row['channel_order_no'] ?? row['id']}',
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

  /// 外键下拉数据源：FormFieldConfig 无 remote source（FormFieldType 只有
  /// text/number/dropdown/password/multiline），弹窗前自己预取喂静态 options/optionLabels
  /// —— 本工程既有惯用法，模板见 wms/pack_page.dart:68-86。原先两处都是手输 hashid。
  /// 选项不在 initState 拉：本页首屏只列订单，弹窗打开前才需要这份数据（也免得测试/离线
  /// 环境为一个没打开的弹窗发请求）。
  final Map<String, String> _orders = {}, _warehouses = {};

  /// 预取单个外键列表；失败返回 false（已弹提示）—— 必填写不进选项的空下拉
  /// 会让用户既选不了也提交不了，宁可不弹窗。
  /// 选项标签走 fmtText 落占位：名称键缺失/为空时贴出的会是 encodeIds 后的雪花码，对用户是噪声。
  Future<bool> _ensureRef(Map<String, String> target, String path, String labelKey) async {
    try {
      final res = await ApiService.instance.get(path, params: {'limit': '500'});
      target
        ..clear()
        ..addAll({
          for (final r in List<Map<String, dynamic>>.from(res['data']?['list'] ?? []))
            '${r['id']}': fmtText(r[labelKey]),
        });
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<bool> _ensureOrders() => _ensureRef(_orders, '/admin/v1/sales/order', 'code');
  Future<bool> _ensureWarehouses() => _ensureRef(_warehouses, '/admin/v1/warehouse', 'name');

  /// 编辑态：当前 FK 不在预取列表内时前置进选项 —— FormDialog 会把不在 options 里的
  /// 预填值置 null（form_dialog.dart:80-83），提交时该外键就被静默清空了（P2）。
  /// 返回新 map（前置孤儿项），未命中时原样返回 —— 口径同 quality/ipqc_list_page.dart::_primed。
  /// 选项标签同样只出可读名（fmtText），没有名称就出占位短横，绝不回落 hashid 本身。
  Map<String, String> _primed(Map<String, String> m, Map<String, dynamic> row, String idKey, String nameKey) {
    final id = '${row[idKey] ?? ''}';
    if (id.isEmpty || id == '0' || m.containsKey(id)) return m;
    return {id: fmtText(row[nameKey]), ...m};
  }

  /// 创建履约：选择发货仓库，调用 POST /admin/oms/order/{id}/fulfill。
  Future<void> _fulfill(Map<String, dynamic> row) async {
    if (!await _ensureWarehouses()) return;
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).omsFulfillCreate,
      fields: [
        FormFieldConfig(
          name: 'warehouse_id',
          label: AppL10n.of(context).omsWarehouseId,
          required: true,
          // 无可选项就退回文本框（退化成改动前的手输），不留空下拉
          type: _warehouses.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
          options: _warehouses.keys.toList(), optionLabels: _warehouses,
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
  // buyer_message/seller_note/priority/hold_until（表无 code 列：后端校验 order_id）
  static const List<String> _channelOptions = [
    'manual',
    'web',
    'mobile',
    'api',
    'marketplace',
    'edi',
    'pos',
  ];

  /// 渠道值域与文案 = Web 端同列字典 OMS_ORDER_CHANNEL（domains/fulfill.ts:48-56）。
  /// `edi`/`pos` 是语言中立值（EDI/POS 本就不译），走末臂原样回落，不另造中文。
  static String _channelLabel(Object? v) => switch ('$v') {
        'manual' => AppL10n.current.omsChannelManual,
        'web' => AppL10n.current.omsChannelWeb,
        'mobile' => AppL10n.current.omsChannelMobile,
        'api' => AppL10n.current.omsChannelApi,
        'marketplace' => AppL10n.current.omsChannelMarketplace,
        _ => '$v',
      };

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

  List<FormFieldConfig> _formFields({Map<String, dynamic>? row}) {
    final l = AppL10n.of(context);
    var orders = _orders;
    if (row != null) orders = _primed(orders, row, 'order_id', 'code');
    return [
      FormFieldConfig(
        name: 'order_id',
        label: l.omsOrderId,
        required: true,
        // 无可选项就退回文本框（退化成改动前的手输），不留空下拉
        type: orders.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
        options: orders.keys.toList(), optionLabels: orders,
        hint: l.omsOrderIdHint,
      ),
      FormFieldConfig(
        name: 'channel',
        label: l.omsChannel,
        type: FormFieldType.dropdown,
        options: _channelOptions,
        optionLabels: {for (final v in _channelOptions) v: _channelLabel(v)},
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

  /// 详情页入口：动作成功后详情页回传 changed=true → 刷新本列表。
  Future<void> _detail(Map<String, dynamic> row) async {
    final changed = await Get.toNamed('/oms/order/detail', arguments: {
      'id': '${row['id']}',
      'title': '${row['channel_order_no'] ?? ''}',
    });
    if (changed == true && mounted) _load();
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
    onRetry: _load, onRefresh: _load,
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
    pageTitle: AppL10n.of(context).omsOrderTitle,
    moduleKey: 'oms',
    primaryColumnIndex: 0,
    // 移动端行堆叠试点(设计 §5.2):窄屏(<768)改为纵向卡片流,不再横向滚表格。
    // 标题列取 primaryColumnIndex(0=渠道单号);动作列 4=commonAction(末列),
    // 单元格 Widget(4 枚图标)原样落卡片底部右对齐。
    stackOnNarrow: true,
    actionColumnIndex: 4,

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
      l.omsChannel: _channelLabel(r['channel']),
      l.omsFulfillStatus: _fulfillChip(r['fulfillment_status']),
      l.omsPaymentStatus: _payChip(r['payment_status']),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.visibility_outlined, size: 18),
            tooltip: l.commonDetail,
            onPressed: () => _detail(r),
          ),
          IconButton(
            icon: Icon(
              Icons.local_shipping,
              size: 18,
              color: AppColors.of(context).primary,
            ),
            tooltip: l.omsFulfill,
            onPressed: () => _fulfill(r),
          ),
          IconButton(
            icon: const Icon(Icons.edit, size: 18),
            onPressed: () => _edit(r),
          ),
          IconButton(
            icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger),
            onPressed: () => _delete(r),
          ),
        ],
      ),
    };
  }

  // §2.4：状态色按枚举 index 取(与 fulfillment_list_page 同法)，不再按本地化文案匹配——
  // 文案随语言变化，匹配会漏色。
  // 履约 0未分配=待办(warning)/1已分配·2拣货中·3已打包=进行中(primary)/4已发货·5已签收=终态(success)
  Widget _fulfillChip(dynamic s) {
    final c = AppColors.of(context);
    final i = s is int ? s : int.tryParse('$s') ?? -1;
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 || 2 || 3 => (c.primaryBg, c.primaryPressed),
      4 || 5 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: _fulfillmentText(s), bg: bg, fg: fg);
  }

  // 支付 0待支付=待办(warning)/1已支付=终态(success)/2部分退款=进行中(primary)/3已退款=失败(danger)
  Widget _payChip(dynamic s) {
    final c = AppColors.of(context);
    final i = s is int ? s : int.tryParse('$s') ?? -1;
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      3 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: _paymentText(s), bg: bg, fg: fg);
  }
}
