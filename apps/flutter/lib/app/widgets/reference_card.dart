// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 引用卡共用弹层：跨资源下钻（customer/supplier/warehouse/product/pick/
// pack/shipment/order 的 GET show 小表）。详情页/行项目里的 hashid 字段
// 通过它收口，不再新增页面路由。加载/失败(可重试)/成功三态。
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../services/api_service.dart';
import '../theme/app_tokens.dart';
import 'detail_page.dart';
import 'status_badge.dart';

/// 弹出资源引用卡。resource ∈ {customer, supplier, warehouse, product,
/// pick, pack, shipment, order}；id 为后端 show 端点接收的 hashid。
Future<void> showReferenceCard(BuildContext context,
    {required String resource, required String id}) {
  return showDialog<void>(
    context: context,
    builder: (_) => _ReferenceCardDialog(resource: resource, id: id),
  );
}

/// 单元格级引用链接（行项目商品等）：id 非空时以主色下划线文本打开引用卡。
Widget refLinkCell(BuildContext context,
    {required String text, required String id, required String resource}) {
  if (id.isEmpty) return Text(text);
  return InkWell(
    onTap: () => showReferenceCard(context, resource: resource, id: id),
    child: Text(text,
        style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            decoration: TextDecoration.underline)),
  );
}

/// resource → API 路径前缀（/admin/v1/.../show）。
String referenceEndpoint(String resource) => switch (resource) {
  'customer' => '/admin/v1/customer',
  'supplier' => '/admin/v1/supplier',
  'warehouse' => '/admin/v1/warehouse',
  'product' => '/admin/v1/product',
  'pick' => '/admin/v1/wms/pick',
  'pack' => '/admin/v1/wms/pack',
  'shipment' => '/admin/v1/tms/shipment',
  'order' => '/admin/v1/oms/order',
  _ => '/admin/v1/oms/order',
};

class _ReferenceCardDialog extends StatefulWidget {
  final String resource;
  final String id;
  const _ReferenceCardDialog({required this.resource, required this.id});

  @override
  State<_ReferenceCardDialog> createState() => _ReferenceCardDialogState();
}

class _ReferenceCardDialogState extends State<_ReferenceCardDialog> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _data = null;
      _error = null;
    });
    try {
      final res = await ApiService.instance
          .get('${referenceEndpoint(widget.resource)}/${widget.id}');
      if (!mounted) return;
      setState(() => _data = Map<String, dynamic>.from(res['data'] ?? {}));
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = ApiService.friendlyError(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final title = switch (widget.resource) {
      'customer' => l.detailRefCustomer,
      'supplier' => l.detailRefSupplier,
      'warehouse' => l.detailRefWarehouse,
      'product' => l.detailRefProduct,
      'pick' => l.detailRefPick,
      'pack' => l.detailRefPack,
      'shipment' => l.detailRefShipment,
      _ => l.detailRefOrder,
    };
    return AlertDialog(
      title: Text(title),
      content: SizedBox(
        width: 420,
        child: _error != null
            ? Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${l.commonLoadFailed}：$_error',
                    style: const TextStyle(fontSize: 13)),
                const SizedBox(height: 12),
                ElevatedButton(
                    onPressed: _load, child: Text(l.commonRetry)),
              ])
            : _data == null
                ? const SizedBox(
                    height: 120,
                    child: Center(child: CircularProgressIndicator()))
                : SingleChildScrollView(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: _rows(context, _data!),
                    ),
                  ),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(l.commonClose)),
      ],
    );
  }

  /// 每资源的字段白名单行（展示键 4-8 个，不做全字段 dump）。
  List<Widget> _rows(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    switch (widget.resource) {
      case 'customer':
        return [
          _fieldRow(l.fieldName, d['name']),
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldContact, d['contact_person']),
          _fieldRow(l.fieldPhone, d['phone']),
          _fieldRow(l.fieldEmail, d['email']),
          _fieldRow(l.fieldAddress, d['address']),
          _statusRow(context, _enabledLabel(l, d['status']), _enabledText(l, d['status'])),
        ];
      case 'supplier':
        return [
          _fieldRow(l.fieldName, d['name']),
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldContact, d['contact_person']),
          _fieldRow(l.fieldPhone, d['phone']),
          _fieldRow(l.fieldEmail, d['email']),
          _fieldRow(l.fieldAddress, d['address']),
          _statusRow(context, _enabledLabel(l, d['status']), _enabledText(l, d['status'])),
        ];
      case 'warehouse':
        return [
          _fieldRow(l.fieldName, d['name']),
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldManager, d['manager']),
          _fieldRow(l.fieldPhone, d['phone']),
          _fieldRow(l.fieldAddress, d['address']),
          _statusRow(context, _enabledLabel(l, d['status']), _enabledText(l, d['status'])),
        ];
      case 'product':
        return [
          _fieldRow(l.fieldName, d['name']),
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldBarcode, d['barcode']),
          _fieldRow(l.fieldSpec, d['spec']),
          _fieldRow(l.fieldUnit, d['unit']),
          _statusRow(context, _enabledLabel(l, d['status']), _enabledText(l, d['status'])),
        ];
      case 'pick':
        return [
          _fieldRow(l.fieldCode, d['code']),
          _typeRow(context, _pickTypeLabel(l, d['type']), _pickTypeText(l, d['type'])),
          _statusRow(context, _pickStatusLabel(l, d['status']), _pickStatusText(l, d['status'])),
          _fieldRow(l.detailCreatedAt, d['completed_at'] ?? d['created_at']),
        ];
      case 'pack':
        return [
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldPackageType, d['package_type']),
          _statusRow(context, _packStatusLabel(l, d['status']), _packStatusText(l, d['status'])),
        ];
      case 'shipment':
        return [
          _fieldRow(l.fieldCode, d['code']),
          _fieldRow(l.fieldTrackingNo, d['tracking_no']),
          _statusRow(context, _shipStatusLabel(l, d['status']), _shipStatusText(l, d['status'])),
        ];
      default: // order（OMS 订单）
        return [
          _fieldRow(l.omsChannelOrderNo, d['channel_order_no']),
          _fieldRow(l.omsChannel, d['channel']),
          _statusRow(context, l.omsFulfillStatus, _orderFulText(l, d['fulfillment_status'])),
          _statusRow(context, l.omsPaymentStatus, _orderPayText(l, d['payment_status'])),
          _fieldRow(l.omsShippingFee, d['shipping_fee']),
          _fieldRow(l.detailCreatedAt, d['created_at']),
        ];
    }
  }

  /// 状态行（右值非空时渲染徽标）。
  Widget _statusRow(BuildContext context, String label, String text) {
    if (text.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(
            width: 140,
            child: Text(label,
                style: TextStyle(fontSize: 12, color: AppColors.of(context).textHint))),
        StatusBadge(label: text, bg: AppColors.of(context).primaryBg, fg: AppColors.of(context).primaryPressed),
      ]),
    );
  }

  Widget _typeRow(BuildContext context, String label, String text) {
    if (text.isEmpty) return const SizedBox.shrink();
    return _statusRow(context, label, text);
  }

  Widget _fieldRow(String label, dynamic v) =>
      DetailRow(label: label, value: '${v ?? ''}');

  String _enabledLabel(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return i == null || (i != 1 && i != 0) ? '' : l.commonStatus;
  }

  String _enabledText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) { 0 => l.commonDisabled, 1 => l.commonEnabled, _ => '' };
  }

  String _pickTypeText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      1 => l.wmsPickTypeByOrder,
      2 => l.wmsPickTypeByBatch,
      3 => l.wmsPickTypeByZone,
      4 => l.wmsPickTypeByWave,
      _ => '',
    };
  }

  String _pickTypeLabel(AppLocalizations l, dynamic s) =>
      _pickTypeText(l, s).isEmpty ? '' : l.fieldType;

  String _pickStatusText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.wmsPickStatusPending,
      1 => l.omsFulPicking, // 拣货中（与履约域同词）
      2 => l.wmsStatusDone,
      3 => l.omsFulTaskCancelled, // 已取消（与履约任务同词）
      _ => '',
    };
  }

  String _pickStatusLabel(AppLocalizations l, dynamic s) =>
      _pickStatusText(l, s).isEmpty ? '' : l.commonStatus;

  String _packStatusText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.wmsPackStatusPending,
      1 => l.omsFulTaskPacking, // 打包中（同词复用）
      2 => l.wmsStatusDone,
      _ => '',
    };
  }

  String _packStatusLabel(AppLocalizations l, dynamic s) =>
      _packStatusText(l, s).isEmpty ? '' : l.commonStatus;

  String _shipStatusText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.omsFulTaskReadyToShip, // 待发货（同词复用）
      1 => l.tmsShipStatusPickedUp,
      2 => l.tmsShipStatusInTransit,
      3 => l.tmsShipStatusDelivered,
      4 => l.tmsShipStatusException,
      5 => l.tmsShipStatusReturned,
      _ => '',
    };
  }

  String _shipStatusLabel(AppLocalizations l, dynamic s) =>
      _shipStatusText(l, s).isEmpty ? '' : l.commonStatus;

  String _orderFulText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.omsFulUnassigned,
      1 => l.omsFulAssigned,
      2 => l.omsFulPicking,
      3 => l.omsFulPacked,
      4 => l.omsFulShipped,
      5 => l.omsFulSigned,
      _ => '',
    };
  }

  String _orderPayText(AppLocalizations l, dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l.omsPayPending,
      1 => l.omsPayPaid,
      2 => l.omsPayPartialRefund,
      3 => l.omsPayRefunded,
      _ => '',
    };
  }
}
