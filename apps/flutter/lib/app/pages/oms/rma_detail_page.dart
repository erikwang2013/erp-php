// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// RMA 退换单详情（批5 ⑤）：GET /admin/v1/oms/rma/{id}。动作（控制器状态门控）：
//   POST /oms/rma/{id}/approve  {approved: true}  → 待审核 → 已批准(1)
//   POST /oms/rma/{id}/approve  {approved: false} → 待审核 → 已拒绝(5)
//   POST /oms/rma/{id}/receive                      已退回(2) → 已收货(3)
//   POST /oms/rma/{id}/refund                       已批准(1)/已收货(3) → 已退款(4)
// 动作成功刷新本页并置脏；返回时 PopScope 回传 changed=true。行项 product_id
// → 商品引用卡。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';
import '../../widgets/reference_card.dart';

class OmsRmaDetailPage extends StatefulWidget {
  final String? id;
  final String? title;
  const OmsRmaDetailPage({super.key, this.id, this.title});

  @override
  State<OmsRmaDetailPage> createState() => _OmsRmaDetailPageState();
}

class _OmsRmaDetailPageState extends State<OmsRmaDetailPage> {
  Map<String, dynamic>? _data;
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
      final res = await ApiService.instance.get('/admin/v1/oms/rma/$_id');
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(res['data'] ?? {});
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      debugPrint('[oms/rma/detail] 加载失败: $e');
      setState(() => _error = ApiService.friendlyError(e));
    }
  }

  /// 动作成功：刷新本页 + 置脏 + 提示。
  Future<void> _run(Future<void> Function() op) async {
    setState(() => _busy = true);
    try {
      await op();
      if (!mounted) return;
      setState(() => _changed = true);
      _load();
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppL10n.of(context).commonOpSuccess)));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiService.friendlyError(e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmAction(String label,
      Future<void> Function() op) async {
    final l10n = AppL10n.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(label),
        content: Text(l10n.detailConfirmOp(label)),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: Text(l10n.commonCancel)),
          ElevatedButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: Text(l10n.commonConfirm)),
        ],
      ),
    );
    if (ok == true) await _run(op);
  }

  // ---- 动作 ----
  Future<void> _approve() => _confirmAction(AppL10n.of(context).omsRmaApprove,
      () => ApiService.instance
          .post('/admin/v1/oms/rma/$_id/approve', data: {'approved': true}));

  Future<void> _reject() => _confirmAction(AppL10n.of(context).omsRmaReject,
      () => ApiService.instance
          .post('/admin/v1/oms/rma/$_id/approve', data: {'approved': false}));

  Future<void> _receive() => _confirmAction(AppL10n.of(context).omsRmaReceive,
      () => ApiService.instance.post('/admin/v1/oms/rma/$_id/receive'));

  Future<void> _refund() => _confirmAction(AppL10n.of(context).omsRmaRefund,
      () => ApiService.instance.post('/admin/v1/oms/rma/$_id/refund'));

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
        appBar: AppBar(title: Text(_title.isNotEmpty ? _title : l.omsRmaTitle)),
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
                      DetailCard(title: l.detailItems, children: [
                        DetailItemsTable(
                          columns: [
                            (l.fieldProductName, 'product_name'),
                            (l.fieldQty, 'quantity'),
                            (l.fieldPrice, 'price'),
                            (l.fieldAmount, 'amount'),
                            (l.fieldUnit, 'unit'),
                          ],
                          rows:
                              List<Map<String, dynamic>>.from(_data!['items'] ?? []),
                          cell: (row, key) => key == 'product_name'
                              ? refLinkCell(context,
                                  text: '${row['product_name'] ?? ''}',
                                  id: '${row['product_id'] ?? ''}',
                                  resource: 'product')
                              : null,
                        ),
                      ]),
                      if (_actions().isNotEmpty)
                        DetailCard(title: l.commonAction,
                            children: [
                              Wrap(spacing: 12, children: _actions())
                            ]),
                    ],
                  ),
      ),
    );
  }

  List<Widget> _actions() {
    final l = AppL10n.of(context);
    final s = _asInt(_data!['status']);
    return [
      if (s == 0)
        FilledButton.icon(
            icon: const Icon(Icons.check_circle, size: 18),
            label: Text(l.omsRmaApprove),
            onPressed: _busy ? null : _approve),
      if (s == 0)
        OutlinedButton.icon(
            icon: const Icon(Icons.cancel, size: 18),
            label: Text(l.omsRmaReject),
            onPressed: _busy ? null : _reject),
      if (s == 2)
        FilledButton.icon(
            icon: const Icon(Icons.inventory, size: 18),
            label: Text(l.omsRmaReceive),
            onPressed: _busy ? null : _receive),
      if (s == 1 || s == 3)
        FilledButton.icon(
            icon: const Icon(Icons.payments, size: 18),
            label: Text(l.omsRmaRefund),
            onPressed: _busy ? null : _refund),
    ];
  }

  List<Widget> _headerRows(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final s = _asInt(d['status']);
    final t = _asInt(d['type']);
    return [
      detailRow(d, l.detailRmaCode, 'code'),
      _idRow(context, d, l.detailOrderRef, 'order_id'),
      _idRow(context, d, l.partnerCustomerTitle, 'customer_id'),
      if (t >= 1 && t <= 3)
        detailStatusRow(context, label: l.fieldType,
            text: _typeText(l, t),
            bg: AppColors.of(context).primaryBg,
            fg: AppColors.of(context).primaryPressed),
      detailStatusRow(context, label: l.commonStatus,
          text: _statusText(l, s),
          bg: _chipColor(s).$1, fg: _chipColor(s).$2),
      if ('${d['reason'] ?? ''}'.isNotEmpty) detailRow(d, l.omsRmaReason, 'reason'),
      if ('${d['refund_amount'] ?? ''}'.isNotEmpty &&
          '${d['refund_amount']}' != '0')
        detailRow(d, l.omsRmaRefundAmount, 'refund_amount'),
      if ('${d['return_shipping_fee'] ?? ''}'.isNotEmpty &&
          '${d['return_shipping_fee']}' != '0')
        detailRow(d, l.omsRmaReturnShippingFee, 'return_shipping_fee'),
      if ('${d['approved_at'] ?? ''}'.isNotEmpty && '${d['approved_at']}' != 'null')
        detailRow(d, l.detailApprovedAt, 'approved_at'),
      if ('${d['returned_at'] ?? ''}'.isNotEmpty && '${d['returned_at']}' != 'null')
        detailRow(d, l.detailReturnedAt, 'returned_at'),
      if ('${d['received_at'] ?? ''}'.isNotEmpty && '${d['received_at']}' != 'null')
        detailRow(d, l.detailReceivedAt, 'received_at'),
      detailRow(d, l.detailCreatedAt, 'created_at'),
    ];
  }

  /// 原始数字 id 行（无名称可 join，纯展示不回退为链接）。
  Widget _idRow(BuildContext context, Map<String, dynamic> d, String label,
      String key) {
    final v = '${d[key] ?? ''}';
    if (v.isEmpty || v == '0' || v == 'null') return const SizedBox.shrink();
    return DetailRow(label: label, value: v);
  }

  int _asInt(dynamic v) => v is int ? v : (int.tryParse('$v') ?? -1);

  String _typeText(AppLocalizations l, int t) => switch (t) {
        1 => l.omsRmaTypeReturn,
        2 => l.omsRmaTypeExchange,
        3 => l.omsRmaTypeRepair,
        _ => '$t',
      };

  String _statusText(AppLocalizations l, int s) => switch (s) {
        0 => l.omsRmaStatusPending,
        1 => l.omsRmaStatusApproved,
        2 => l.omsRmaStatusReturned,
        3 => l.omsRmaStatusReceived,
        4 => l.omsRmaStatusRefunded,
        5 => l.omsRmaStatusRejected,
        _ => '$s',
      };

  // §2.4：0待审核=待办(warning)，1/3/4=终态(success)，2已退回=进行中(primary)，
  // 5已拒绝=失败(danger)
  (Color, Color) _chipColor(int s) {
    final c = AppColors.of(context);
    return switch (s) {
      0 => (c.warningBg, c.warningText),
      1 || 3 || 4 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      5 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }
}
