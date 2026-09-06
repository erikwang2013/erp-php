// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// OMS 订单详情（批5 ③）：GET /admin/v1/oms/order/{id} + 子列表 GET
// /admin/v1/oms/fulfillment?oms_order_id=<hashid>（→ 履约详情 ④）。
// 注意：oms show 仅回平字段（行项在 erp_sales_order_item，属销售侧），
// 本页无商品明细卡。动作（状态门控，后端再校验）：
//   POST /admin/v1/oms/order/{id}/allocate   items[]（product_id/quantity
//     行内 int/double 校验后以原始数字发 —— 后端 reserveQuantity(int,float)
//     直收，非 hashid，无 decode）
//   POST /admin/v1/oms/order/{id}/fulfill    {warehouse_id}（hashid）
//   POST /admin/v1/oms/order/{id}/cancel
// 动作成功后刷新本页并置脏；返回时经 PopScope 回传 changed=true 供列表刷新。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/status_badge.dart';

class OmsOrderDetailPage extends StatefulWidget {
  final String? id;
  final String? title;
  const OmsOrderDetailPage({super.key, this.id, this.title});

  @override
  State<OmsOrderDetailPage> createState() => _OmsOrderDetailPageState();
}

class _OmsOrderDetailPageState extends State<OmsOrderDetailPage> {
  Map<String, dynamic>? _data;
  List<Map<String, dynamic>> _fulfillments = [];
  String? _error;
  bool _changed = false;
  bool _busy = false;

  String get _id =>
      widget.id ??
      ((Get.arguments is Map) ? '${(Get.arguments as Map)['id'] ?? ''}' : '');
  String get _title =>
      widget.title ??
      ((Get.arguments is Map) ? '${(Get.arguments as Map)['title'] ?? ''}' : '');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/oms/order/$_id');
      final data = Map<String, dynamic>.from(res['data'] ?? {});
      // 履约子表：失败静默（主卡仍可读），列表空则卡内显示 '-'
      List<Map<String, dynamic>> subs = [];
      try {
        final r2 = await ApiService.instance.get('/admin/v1/oms/fulfillment',
            params: {'oms_order_id': _id, 'page': '1', 'limit': '50'});
        subs = List<Map<String, dynamic>>.from(r2['data']['list'] ?? []);
      } catch (_) {}
      if (!mounted) return;
      setState(() {
        _data = data;
        _fulfillments = subs;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      debugPrint('[oms/order/detail] 加载失败: $e');
      setState(() => _error = ApiService.friendlyError(e));
    }
  }

  /// 动作成功：刷新本页 + 置脏（PopScope 返回时携带 changed=true）+ 提示。
  Future<void> _run(Future<void> Function() op, String okMsg) async {
    setState(() => _busy = true);
    try {
      await op();
      if (!mounted) return;
      setState(() => _changed = true);
      _load();
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(okMsg)));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// 简单确认（cancel / rma 类动作共用）：文案走 detailConfirmOp(action)。
  Future<void> _confirmRun(String actionLabel, Future<void> Function() op,
      {required bool destructive}) async {
    final l10n = AppL10n.of(context);
    final okMsg = l10n.commonOpSuccess;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(actionLabel),
        content: Text(l10n.detailConfirmOp(actionLabel)),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: Text(l10n.commonCancel)),
          TextButton(
            style: destructive
                ? TextButton.styleFrom(foregroundColor: Theme.of(ctx).colorScheme.error)
                : null,
            onPressed: () => Navigator.of(ctx).pop(true),
            child: Text(l10n.commonConfirm),
          ),
        ],
      ),
    );
    if (ok == true) await _run(op, okMsg);
  }

  // ---- 动作 ----
  Future<void> _allocate() => _allocateDialog();

  Future<void> _allocateDialog() async {
    final l = AppL10n.of(context);
    final lines = <({TextEditingController pid, TextEditingController qty})>[
      (pid: TextEditingController(), qty: TextEditingController()),
    ];
    try {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => StatefulBuilder(
          builder: (ctx2, setLocal) => AlertDialog(
            title: Text(l.omsOrderAllocate),
            content: SizedBox(
              width: 420,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (var i = 0; i < lines.length; i++) ...[
                      Row(children: [
                        Expanded(
                            child: TextField(
                          controller: lines[i].pid,
                          keyboardType: TextInputType.number,
                          decoration: InputDecoration(
                              labelText: l.detailAllocateProductId,
                              isDense: true),
                        )),
                        const SizedBox(width: 8),
                        Expanded(
                            child: TextField(
                          controller: lines[i].qty,
                          keyboardType:
                              const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(
                              labelText: l.fieldQty, isDense: true),
                        )),
                        IconButton(
                          icon: const Icon(Icons.remove_circle_outline, size: 18),
                          onPressed: lines.length > 1
                              ? () => setLocal(() {
                                    lines.removeAt(i);
                                  })
                              : null,
                        ),
                      ]),
                      const SizedBox(height: 8),
                    ],
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        icon: const Icon(Icons.add, size: 18),
                        label: Text(l.commonAdd),
                        onPressed: () => setLocal(() => lines.add(
                            (pid: TextEditingController(),
                                qty: TextEditingController()))),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                  onPressed: () => Navigator.of(ctx).pop(false),
                  child: Text(l.commonCancel)),
              ElevatedButton(
                onPressed: () => Navigator.of(ctx).pop(true),
                child: Text(l.commonConfirm),
              ),
            ],
          ),
        ),
      );
      if (ok != true) return;
      final items = <Map<String, dynamic>>[];
      for (final line in lines) {
        final pidText = line.pid.text.trim();
        if (pidText.isEmpty) continue; // 空行跳过
        final qtyText = line.qty.text.trim();
        if (qtyText.isEmpty) {
          throw Exception(l.detailAllocateQtyRequired);
        }
        // 行内 int/double 校验：后端 reserveQuantity(int,float) 直收原始数字，
        // String 直发会触发 PHP TypeError（reviewer-b5 F2），非法行拦截不发。
        final pid = int.tryParse(pidText);
        final qty = double.tryParse(qtyText);
        if (pid == null || pid <= 0) {
          throw Exception(l.detailAllocatePidInvalid);
        }
        if (qty == null || qty <= 0) {
          throw Exception(l.detailAllocateQtyInvalid);
        }
        items.add({'product_id': pid, 'quantity': qty});
      }
      if (items.isEmpty) throw Exception(l.detailAllocateEmpty);
      await _run(
        () => ApiService.instance.post('/admin/v1/oms/order/$_id/allocate',
            data: {'items': items}),
        l.commonOpSuccess,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      // showDialog future 在 pop 时即完成，但路由退场动画期间 TextField 仍
      // 引用控制器 —— 立即 dispose 会触发「used after being disposed」，
      // 等退场（默认 dialog 转场 ~200ms）结束后再释放。
      // ponytail: 固定 350ms；若自定义更长转场需随之调整。
      await Future<void>.delayed(const Duration(milliseconds: 350));
      for (final line in lines) {
        line.pid.dispose();
        line.qty.dispose();
      }
    }
  }

  Future<void> _fulfill() async {
    final l = AppL10n.of(context);
    final ok = await FormDialog.show(context, title: l.omsFulfillCreate, fields: [
      FormFieldConfig(name: 'warehouse_id', label: l.omsWarehouseId,
          required: true, hint: l.omsWarehouseIdHint),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/oms/order/$_id/fulfill',
          data: {'warehouse_id': data['warehouse_id']?.trim()});
      setState(() => _changed = true);
      _load();
      return true;
    });
    if (ok && mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(l.commonOpSuccess)));
    }
  }

  Future<void> _cancel() => _confirmRun(
        AppL10n.of(context).omsOrderCancel,
        () => ApiService.instance.post('/admin/v1/oms/order/$_id/cancel'),
        destructive: true,
      );

  // ---- 展示 ----
  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.of(context).pop(_changed ? true : null);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_title.isNotEmpty ? _title : l.omsOrderTitle),
        ),
        body: _error != null
            ? Center(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text('${l.commonLoadFailed}：$_error'),
                  const SizedBox(height: 12),
                  ElevatedButton(
                      onPressed: _load, child: Text(l.commonRetry)),
                ]))
            : _data == null
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      DetailCard(title: l.detailBasicInfo,
                          children: _headerRows(context, _data!)),
                      // 无商品明细卡：oms show 平字段，行项属销售侧（reviewer-b5 F1）
                      DetailCard(title: l.detailFulfillments, children: [
                        _fulfillmentSubtable(context),
                      ]),
                      if (_actions().isNotEmpty)
                        DetailCard(title: l.commonAction, children: [
                          Wrap(spacing: 12, children: _actions()),
                        ]),
                    ],
                  ),
      ),
    );
  }

  List<Widget> _actions() {
    final l = AppL10n.of(context);
    final s = _status(_data!['fulfillment_status']);
    return [
      if (s == 0)
        FilledButton.icon(
          icon: const Icon(Icons.inventory_2, size: 18),
          label: Text(l.omsOrderAllocate),
          onPressed: _busy ? null : _allocate,
        ),
      if (s == 1)
        FilledButton.icon(
          icon: const Icon(Icons.local_shipping, size: 18),
          label: Text(l.omsFulfillCreate),
          onPressed: _busy ? null : _fulfill,
        ),
      if (s >= 0 && s <= 3)
        OutlinedButton.icon(
          icon: const Icon(Icons.cancel, size: 18),
          label: Text(l.omsOrderCancel),
          onPressed: _busy ? null : _cancel,
        ),
    ];
  }

  List<Widget> _headerRows(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final s = _status(d['fulfillment_status']);
    final p = _pay(d['payment_status']);
    return [
      detailRow(d, l.omsChannelOrderNo, 'channel_order_no'),
      if ('${d['channel'] ?? ''}'.isNotEmpty) detailRow(d, l.omsChannel, 'channel'),
      if ('${d['channel_store'] ?? ''}'.isNotEmpty)
        detailRow(d, l.omsChannelStore, 'channel_store'),
      detailStatusRow(context, label: l.omsFulfillStatus,
          text: _fulText(l, s), bg: _fulColor(s).$1, fg: _fulColor(s).$2),
      detailStatusRow(context, label: l.omsPaymentStatus,
          text: _payText(l, p), bg: _payColor(p).$1, fg: _payColor(p).$2),
      if ('${d['shipping_method'] ?? ''}'.isNotEmpty)
        detailRow(d, l.omsShippingMethod, 'shipping_method'),
      if ('${d['shipping_fee'] ?? ''}'.isNotEmpty &&
          '${d['shipping_fee']}' != '0')
        detailRow(d, l.omsShippingFee, 'shipping_fee'),
      _priorityRow(context, d),
      if ('${d['buyer_message'] ?? ''}'.isNotEmpty)
        detailRow(d, l.omsBuyerMessage, 'buyer_message'),
      if ('${d['seller_note'] ?? ''}'.isNotEmpty)
        detailRow(d, l.omsSellerNote, 'seller_note'),
      if ('${d['hold_until'] ?? ''}'.isNotEmpty && '${d['hold_until']}' != 'null')
        detailRow(d, l.omsHoldUntil, 'hold_until'),
      detailRow(d, l.detailCreatedAt, 'created_at'),
    ];
  }

  /// 优先级：1=最高 5=正常 9=最低（键与新增/编辑表单同源）。
  Widget _priorityRow(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final i = _asInt(d['priority']);
    return DetailRow(
      label: l.omsPriority,
      value: switch (i) {
        1 => l.omsPriorityHigh,
        5 => l.omsPriorityNormal,
        9 => l.omsPriorityLow,
        _ => '${d['priority'] ?? ''}',
      },
    );
  }

  /// 履约子表行 → 履约详情 ④；状态列徽标按 erp_oms_fulfillment.status 0-6。
  Widget _fulfillmentSubtable(BuildContext context) {
    final l = AppL10n.of(context);
    if (_fulfillments.isEmpty) return const Text('-');
    return DetailItemsTable(
      columns: [
        (l.commonStatus, 'status'),
        (l.partnerWarehouseTitle, 'warehouse_name'),
        (l.detailCreatedAt, 'created_at'),
        (l.commonAction, 'id'),
      ],
      rows: _fulfillments,
      cell: (row, key) {
        if (key == 'status') {
          return StatusBadge(
            label: _taskText(l, _asInt(row['status'])),
            bg: AppColors.of(context).primaryBg,
            fg: AppColors.of(context).primaryPressed,
          );
        }
        if (key == 'id') {
          return InkWell(
            onTap: () => Get.toNamed('/oms/fulfillment/detail',
                arguments: {'id': '${row['id']}', 'title': ''}),
            child: Text(l.detailViewDoc,
                style: TextStyle(
                    color: Theme.of(context).colorScheme.primary,
                    decoration: TextDecoration.underline)),
          );
        }
        return null;
      },
    );
  }

  int _asInt(dynamic v) => v is int ? v : (int.tryParse('$v') ?? -1);
  int _status(dynamic v) => _asInt(v);
  int _pay(dynamic v) => _asInt(v);

  // 履约任务状态 0-6（erp_oms_fulfillment.status 注释）
  String _taskText(AppLocalizations l, int s) => switch (s) {
        0 => l.omsFulTaskPending,
        1 => l.omsFulTaskAllocating,
        2 => l.omsFulPicking,
        3 => l.omsFulTaskPacking,
        4 => l.omsFulTaskReadyToShip,
        5 => l.omsFulShipped,
        6 => l.omsFulTaskCancelled,
        _ => '$s',
      };

  // 订单履约状态 0-5
  String _fulText(AppLocalizations l, int s) => switch (s) {
        0 => l.omsFulUnassigned,
        1 => l.omsFulAssigned,
        2 => l.omsFulPicking,
        3 => l.omsFulPacked,
        4 => l.omsFulShipped,
        5 => l.omsFulSigned,
        _ => '$s',
      };

  String _payText(AppLocalizations l, int s) => switch (s) {
        0 => l.omsPayPending,
        1 => l.omsPayPaid,
        2 => l.omsPayPartialRefund,
        3 => l.omsPayRefunded,
        _ => '$s',
      };

  /// §2.4 履约色：0未分配=待办(warning)、1-3进行中(primary)、4-5终态(success)。
  (Color, Color) _fulColor(int s) {
    final c = AppColors.of(context);
    return switch (s) {
      0 => (c.warningBg, c.warningText),
      4 || 5 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }

  /// §2.4 支付色：0=warning、1=success、2=primary、3=danger。
  (Color, Color) _payColor(int s) {
    final c = AppColors.of(context);
    return switch (s) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      3 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }
}
