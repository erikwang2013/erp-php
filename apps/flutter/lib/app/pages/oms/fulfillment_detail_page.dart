// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 履约单详情（批5 ④）：GET /admin/v1/oms/fulfillment/{id}（warehouse_name
// leftJoin 带出；pick/pack/shipment id >0 时以 hashid 下发，0/null 隐藏）。
// 仓库/订单/拣货/打包/运单 → 引用卡下钻。被动页，无写操作。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../../l10n/app_localizations.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';
import '../../widgets/reference_card.dart';
import '../../utils/format.dart';

class FulfillmentDetailPage extends StatelessWidget {
  final String? id;
  final String? title;
  const FulfillmentDetailPage({super.key, this.id, this.title});

  String get _id =>
      id ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['id'] ?? ''}' : '');
  String get _title =>
      title ?? ((Get.arguments is Map) ? '${(Get.arguments as Map)['title'] ?? ''}' : '');

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return DetailPage(
      title: _title.isNotEmpty ? _title : l.omsFulfillmentTitle,
      endpoint: '/admin/v1/oms/fulfillment/$_id',
      builder: (context, d) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DetailCard(title: l.detailBasicInfo, children: [
            _refRow(context, d, l.partnerWarehouseTitle, 'warehouse_name',
                'warehouse_id', 'warehouse'),
            // show 只 join 了 warehouse.name：OMS 订单无名称兄弟键（列表页的
            // order_channel_no 来自 index，不在 show 回包里）→ nameKey 传 null 落「-」
            _refRow(context, d, l.detailOrderRef, null, 'oms_order_id', 'order'),
            detailStatusRow(context, label: l.commonStatus,
                text: _taskText(l, _asInt(d['status'])),
                bg: AppColors.of(context).primaryBg,
                fg: AppColors.of(context).primaryPressed),
            _optionalRefRow(context, d, l.detailPickTask, 'pick_task_id', 'pick'),
            _optionalRefRow(context, d, l.detailPackTask, 'pack_task_id', 'pack'),
            _optionalRefRow(context, d, l.detailShipment, 'shipment_id', 'shipment'),
            detailRow(d, l.detailCreatedAt, 'created_at', fmt: fmtDateTime),
          ]),
          DetailCard(title: l.detailItems, children: [
            DetailItemsTable(
              columns: [
                (l.fieldProductName, 'product_name'),
                (l.fieldAllocatedQty, 'allocated_quantity'),
                (l.fieldPickedQty, 'picked_quantity'),
                (l.fieldPackedQty, 'packed_quantity'),
                (l.fieldShippedQty, 'shipped_quantity'),
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

  /// 名称/id 行：名称优先，id 非空时整行链接到引用卡。
  /// [nameKey] 为 null = 该 show 未 join 名称兄弟键（如 oms_order_id）→ 落「-」；
  /// 名称取不到也不回落裸 hashid（契约 rule ④，引用卡里看得到单据本身）。
  Widget _refRow(BuildContext context, Map<String, dynamic> d, String label,
      String? nameKey, String idKey, String resource) {
    final id = '${d[idKey] ?? ''}';
    final name = nameKey == null ? '' : '${d[nameKey] ?? ''}';
    final text = name.isEmpty ? '-' : name;
    if (id.isEmpty) return DetailRow(label: label, value: text);
    return DetailRow(
      label: label,
      value: text,
      onTap: () => showReferenceCard(context, resource: resource, id: id),
    );
  }

  /// 可选任务 id（>0 才显示；0/null 隐藏）→ 引用卡。
  /// 值不贴 hashid：WMS/TMS 任务号在引用卡里（show 未带 code 兄弟键），本行只做入口。
  Widget _optionalRefRow(BuildContext context, Map<String, dynamic> d,
      String label, String idKey, String resource) {
    final id = '${d[idKey] ?? ''}';
    final i = int.tryParse(id);
    if (id.isEmpty || (i != null && i <= 0)) return const SizedBox.shrink();
    return DetailRow(
      label: label,
      value: '-',
      onTap: () => showReferenceCard(context, resource: resource, id: id),
    );
  }

  int _asInt(dynamic v) => v is int ? v : (int.tryParse('$v') ?? -1);

  /// 履约任务状态 0-6（erp_oms_fulfillment.status 注释，与 OMS 订单详情同源）。
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
}
