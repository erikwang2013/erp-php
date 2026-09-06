// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 采购订单详情（批5 ②）：GET /admin/v1/purchase/order/{id}（show leftJoin
// supplier 带出 supplier_name，items 内嵌 product_name/product_code）。
// 行项 product_id、供应商 supplier_id → 引用卡下钻。被动页，无写操作。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../../l10n/app_localizations.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';
import '../../widgets/reference_card.dart';

class PurchaseOrderDetailPage extends StatelessWidget {
  final String? id;
  final String? title;
  const PurchaseOrderDetailPage({super.key, this.id, this.title});

  String get _id =>
      id ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['id'] ?? ''}' : '');
  String get _title =>
      title ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['title'] ?? ''}' : '');

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return DetailPage(
      title: _title.isNotEmpty ? _title : l.purchaseOrderTitle,
      endpoint: '/admin/v1/purchase/order/$_id',
      builder: (context, d) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DetailCard(title: l.detailBasicInfo, children: [
            detailRow(d, l.purchaseOrderCode, 'code'),
            _supplierRow(context, d),
            detailStatusRow(context, label: l.commonStatus,
                text: _statusText(l, d['status']),
                bg: _chipColor(context, d['status']).$1,
                fg: _chipColor(context, d['status']).$2),
            detailRow(d, l.purchaseOrderTimeLabel, 'ordered_at'),
            detailRow(d, l.purchaseTotalAmount, 'total_amount'),
            if ('${d['remark'] ?? ''}'.isNotEmpty) detailRow(d, l.purchaseRemark, 'remark'),
          ]),
          DetailCard(title: l.detailItems, children: [
            DetailItemsTable(
              columns: [
                (l.fieldProductName, 'product_name'),
                (l.fieldQty, 'quantity'),
                (l.fieldPrice, 'price'),
                (l.fieldAmount, 'amount'),
                (l.fieldUnit, 'unit'),
              ],
              rows: List<Map<String, dynamic>>.from(d['items'] ?? []),
              cell: (row, key) => key == 'product_name'
                  ? refLinkCell(context,
                      text: '${row['product_name'] ?? ''}',
                      id: '${row['product_id'] ?? ''}',
                      resource: 'product')
                  : null,
            ),
          ]),
        ],
      ),
    );
  }

  Widget _supplierRow(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final sid = '${d['supplier_id'] ?? ''}';
    final name = '${d['supplier_name'] ?? ''}';
    final text = name.isEmpty ? sid : name;
    if (sid.isEmpty) return DetailRow(label: l.partnerSupplierTitle, value: text);
    return DetailRow(
      label: l.partnerSupplierTitle,
      value: text,
      onTap: () => showReferenceCard(context, resource: 'supplier', id: sid),
    );
  }

  /// 0待审核 1已审核 2部分收货 3已收货 4已取消（键与列表页同源）。
  String _statusText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.purchaseOrderStatusPending,
      1 => l.purchaseOrderStatusApproved,
      2 => l.purchaseOrderStatusPartReceived,
      3 => l.purchaseOrderStatusReceived,
      4 => l.purchaseOrderStatusCancelled,
      _ => '$s',
    };
  }

  (Color, Color) _chipColor(BuildContext context, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final c = AppColors.of(context);
    // §2.4：0=待办(warning)，1/3=终态(success)，2=进行中(primary)，4=失败(danger)
    return switch (i) {
      0 => (c.warningBg, c.warningText),
      1 || 3 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      4 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }
}
