// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';
import '../../l10n/app_l10n.dart';

// erp_oms_fulfillment 真实列：oms_order_id/warehouse_id/status/pick_task_id/
// pack_task_id/shipment_id。无 name/code 列（幻列已删，无文本可搜列不挂搜索框）：
// 订单渠道号/仓库名由后端随行带回 order_channel_no/warehouse_name；状态
// 0待处理/1分配中/2拣货中/3打包中/4待发货/5已发货/6已取消（WMS/TMS 任务驱动）。
class FulfillmentListPage extends StatefulWidget {
  const FulfillmentListPage({super.key});
  @override
  State<FulfillmentListPage> createState() => _FulfillmentListPageState();
}

class _FulfillmentListPageState extends State<FulfillmentListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 订单下拉 /admin/v1/oms/order（以渠道订单号为业务标识；失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadOrders() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/oms/order', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  /// 仓库下拉 /admin/v1/warehouse（失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadWarehouses() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/oms/fulfillment', params: {'page': '$_page', 'limit': '$_limit'});
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

  /// 弹窗前预取订单/仓库；编辑时原 FK 不在下拉中则补一行原值。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    final orders = await _loadOrders();
    final warehouses = await _loadWarehouses();
    if (!mounted) return _formFields([], []);
    var orderOptions = [
      for (final o in orders) '${o['id']} - ${o['channel_order_no'] ?? o['id']}',
    ];
    var warehouseOptions = [
      for (final w in warehouses) '${w['id']} - ${w['name'] ?? w['id']}',
    ];
    if (row != null) {
      final oid = '${row['oms_order_id'] ?? ''}';
      if (oid.isNotEmpty && !orderOptions.any((o) => o.startsWith('$oid - '))) {
        orderOptions = ['$oid - ${row['order_channel_no'] ?? oid}', ...orderOptions];
      }
      final wid = '${row['warehouse_id'] ?? ''}';
      if (wid.isNotEmpty && !warehouseOptions.any((o) => o.startsWith('$wid - '))) {
        warehouseOptions = ['$wid - ${row['warehouse_name'] ?? wid}', ...warehouseOptions];
      }
    }
    return _formFields(orderOptions, warehouseOptions);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/oms/fulfillment', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final fields = await _fieldsFor(row: row);
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: fields,
      initialData: _toEditData(row),
      onSubmit: (data) async {
        await ApiService.instance.put('/admin/v1/oms/fulfillment/${row['id']}', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['order_channel_no'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/oms/fulfillment/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields(List<String> orderOptions, List<String> warehouseOptions) => [
    FormFieldConfig(
      name: 'oms_order_id',
      label: AppL10n.of(context).omsChannelOrderNo,
      required: true,
      type: FormFieldType.dropdown,
      options: orderOptions,
    ),
    FormFieldConfig(
      name: 'warehouse_id',
      label: AppL10n.of(context).omsWarehouseId,
      required: true,
      type: FormFieldType.dropdown,
      options: warehouseOptions,
    ),
  ];

  /// 组装后端接收参数：FK 拆出 hashid；status 由拣/包/发动作驱动不经表单。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    return {
      'oms_order_id': pick('oms_order_id'),
      'warehouse_id': pick('warehouse_id'),
    };
  }

  /// 编辑回填：FK 转「id - 名称」选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final oid = '${row['oms_order_id'] ?? ''}';
    final wid = '${row['warehouse_id'] ?? ''}';
    final on = row['order_channel_no'];
    final wn = row['warehouse_name'];
    d['oms_order_id'] = oid.isEmpty ? '' : (on == null || '$on'.isEmpty ? oid : '$oid - $on');
    d['warehouse_id'] = (wid.isEmpty || wn == null || '$wn'.isEmpty) ? '' : '$wid - $wn';
    return d;
  }

  /// 详情页入口：详情页置脏返回时回传 changed=true → 刷新本列表。
  Future<void> _detail(Map<String, dynamic> row) async {
    final changed = await Get.toNamed('/oms/fulfillment/detail', arguments: {
      'id': '${row['id']}',
      'title': '${row['order_channel_no'] ?? row['id']}',
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
    onPageChanged: (p) {
      _page = p;
      _load();
    },
    pageTitle: AppL10n.of(context).omsFulfillmentTitle,
    moduleKey: 'oms',
    primaryColumnIndex: 0,

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
    return [l.omsChannelOrderNo, l.fieldWarehouse, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.omsChannelOrderNo: r['order_channel_no'] ?? '',
      l.fieldWarehouse: r['warehouse_name'] ?? '',
      l.commonStatus: _statusChip(r['status']),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.visibility_outlined, size: 18),
            tooltip: l.commonDetail,
            onPressed: () => _detail(r),
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

  // 状态 0待处理/1分配中/2拣货中/3打包中/4待发货/5已发货/6已取消
  Widget _statusChip(dynamic s) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final map = {
      0: (l.omsFulTaskPending, c.warningBg, c.warningText),
      1: (l.omsFulTaskAllocating, c.primaryBg, c.primaryPressed),
      2: (l.omsFulPicking, c.primaryBg, c.primaryPressed),
      3: (l.omsFulTaskPacking, c.primaryBg, c.primaryPressed),
      4: (l.omsFulTaskReadyToShip, c.warningBg, c.warningText),
      5: (l.omsFulShipped, c.successBg, c.successText),
      6: (l.omsFulTaskCancelled, c.dangerBg, c.dangerText),
    };
    final (text, bg, fg) = map[i] ?? ('$i', c.primaryBg, c.primaryPressed);
    return StatusBadge(label: text, bg: bg, fg: fg);
  }
}
