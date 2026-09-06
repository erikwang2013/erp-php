// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 销售订单详情（批5 ①）：GET /admin/v1/sales/order/{id}（show leftJoin customer
// 带出 customer_name，items 内嵌 product_name/product_code）。
// 行项 product_id、客户 customer_id → 引用卡下钻。被动页，无写操作。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../../l10n/app_localizations.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';
import '../../widgets/reference_card.dart';

class SalesOrderDetailPage extends StatelessWidget {
  final String? id;
  final String? title;
  const SalesOrderDetailPage({super.key, this.id, this.title});

  String get _id =>
      id ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['id'] ?? ''}' : '');
  String get _title =>
      title ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['title'] ?? ''}' : '');

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return DetailPage(
      title: _title.isNotEmpty ? _title : l.salesOrderTitle,
      endpoint: '/admin/v1/sales/order/$_id',
      builder: (context, d) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DetailCard(title: l.detailBasicInfo, children: [
            detailRow(d, l.salesOrderNo, 'code'),
            _customerRow(context, d),
            detailStatusRow(context, label: l.commonStatus,
                text: _statusText(l, d['status']),
                bg: _chipColor(context, d['status']).$1,
                fg: _chipColor(context, d['status']).$2),
            detailRow(d, l.salesOrderedAt, 'ordered_at'),
            detailRow(d, l.salesTotalAmount, 'total_amount'),
            if ('${d['discount_amount'] ?? ''}'.isNotEmpty)
              detailRow(d, l.salesDiscountAmount, 'discount_amount'),
            if ('${d['remark'] ?? ''}'.isNotEmpty) detailRow(d, l.commonRemark, 'remark'),
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

  /// 客户行：customer_name（无 name 时回退 hashid）；customer_id 非空 → 引用卡。
  Widget _customerRow(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final cid = '${d['customer_id'] ?? ''}';
    final name = '${d['customer_name'] ?? ''}';
    final text = name.isEmpty ? cid : name;
    if (cid.isEmpty) return DetailRow(label: l.partnerCustomerTitle, value: text);
    return DetailRow(
      label: l.partnerCustomerTitle,
      value: text,
      onTap: () => showReferenceCard(context, resource: 'customer', id: cid),
    );
  }

  /// 状态文案（键与列表页 chips 同源）：0待审批 1已审批 2部分发货 3已发货 4已取消。
  String _statusText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.salesOrderPending,
      1 => l.salesOrderReviewed,
      2 => l.salesOrderPartShipped,
      3 => l.salesOrderShipped,
      4 => l.salesOrderCancelled,
      _ => '$s',
    };
  }

  (Color, Color) _chipColor(BuildContext context, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final c = AppColors.of(context);
    // §2.4：0=待办(warning)，1已审批/3已发货=终态(success)，2部分发货=进行中(primary)，4已取消=失败(danger)
    return switch (i) {
      0 => (c.warningBg, c.warningText),
      1 || 3 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      4 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }
}
