// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_tms_tracking_event 真实列：shipment_id/status_code/description/location/
// event_time/raw_data。无 name/code/status 列（幻列已删）：运单号由后端随行带回
// shipment_code；status_code 为字符串状态码 picked_up/in_transit/out_for_delivery/
// delivered/exception（labels 走 tmsShipStatus*，out_for_delivery 已补 key）；
// location 发生地点已暴露（raw_data 为承运商回传原始 JSON，不落表展示）。
class TrackingPage extends StatefulWidget {
  const TrackingPage({super.key});
  @override
  State<TrackingPage> createState() => _TrackingPageState();
}

class _TrackingPageState extends State<TrackingPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 运单下拉 /admin/v1/tms/shipment（行 code 为运单号；失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadShipments() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/tms/shipment', params: {'limit': '500'});
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
      final res = await ApiService.instance.get('/admin/v1/tms/tracking', params: {'page': '$_page', 'limit': '$_limit'});
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

  /// 弹窗前预取运单；编辑时原运单不在列表则补一行原值。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    final shipments = await _loadShipments();
    if (!mounted) return _formFields([]);
    var options = [
      for (final s in shipments) '${s['id']} - ${s['code'] ?? s['tracking_no'] ?? s['id']}',
    ];
    if (row != null) {
      final sid = '${row['shipment_id'] ?? ''}';
      if (sid.isNotEmpty && !options.any((o) => o.startsWith('$sid - '))) {
        options = ['$sid - ${row['shipment_code'] ?? sid}', ...options];
      }
    }
    return _formFields(options);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/tms/tracking', data: _buildPayload(data));
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
        await ApiService.instance.put('/admin/v1/tms/tracking/${row['id']}', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['shipment_code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/tms/tracking/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields(List<String> shipmentOptions) => [
    FormFieldConfig(
      name: 'shipment_id',
      label: AppL10n.of(context).fieldTrackingNo,
      required: true,
      type: FormFieldType.dropdown,
      options: shipmentOptions,
    ),
    FormFieldConfig(
      name: 'status_code',
      label: AppL10n.of(context).commonStatus,
      type: FormFieldType.dropdown,
      options: _statusOptions,
      initialValue: 'in_transit - ${_statusText('in_transit')}',
    ),
    FormFieldConfig(name: 'description', label: AppL10n.of(context).fieldDescription, type: FormFieldType.multiline),
    FormFieldConfig(name: 'location', label: AppL10n.of(context).fieldLocation),
    FormFieldConfig(name: 'event_time', label: AppL10n.of(context).fieldTime),
  ];

  List<String> get _statusOptions => [
    for (final code in const ['picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'exception'])
      '$code - ${_statusText(code)}',
  ];

  /// 状态码 → 文案（缺失 key 的码原样展示，避免误导性借用）。
  String _statusText(String code) {
    final l = AppL10n.of(context);
    switch (code) {
      case 'picked_up':
        return l.tmsShipStatusPickedUp;
      case 'in_transit':
        return l.tmsShipStatusInTransit;
      case 'out_for_delivery':
        return l.tmsShipStatusOutForDelivery;
      case 'delivered':
        return l.tmsShipStatusDelivered;
      case 'exception':
        return l.tmsShipStatusException;
      default:
        return code;
    }
  }

  /// 组装后端接收参数：shipment_id/status_code 均取码段；location 原样；
  /// 时间可空留空即 null（后端 ''→null 已覆盖）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    final time = data['event_time']?.trim();
    return {
      'shipment_id': pick('shipment_id'),
      'status_code': pick('status_code'),
      'description': data['description']?.trim() ?? '',
      'location': data['location']?.trim() ?? '',
      'event_time': (time == null || time.isEmpty) ? null : time,
    };
  }

  /// 编辑回填：FK/状态码转选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final sid = '${row['shipment_id'] ?? ''}';
    final sc = row['shipment_code'];
    d['shipment_id'] = sid.isEmpty ? '' : (sc == null || '$sc'.isEmpty ? sid : '$sid - $sc');
    final code = '${row['status_code'] ?? ''}';
    if (code.isNotEmpty) {
      d['status_code'] = '$code - ${_statusText(code)}';
    }
    return d;
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
    pageTitle: AppL10n.of(context).tmsTrackingTitle,
    moduleKey: 'tms',
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
    return [l.fieldTrackingNo, l.commonStatus, l.fieldTime, l.fieldLocation, l.fieldDescription, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.fieldTrackingNo: r['shipment_code'] ?? '',
      l.commonStatus: _chip('${r['status_code'] ?? ''}'),
      l.fieldTime: r['event_time'] ?? '',
      l.fieldLocation: r['location'] ?? '',
      l.fieldDescription: r['description'] ?? '',
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
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

  Widget _chip(String code) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final (text, bg, fg) = switch (code) {
      'picked_up' => (l.tmsShipStatusPickedUp, c.primaryBg, c.primaryPressed),
      'in_transit' => (l.tmsShipStatusInTransit, c.primaryBg, c.primaryPressed),
      'out_for_delivery' => (l.tmsShipStatusOutForDelivery, c.primaryBg, c.primaryPressed),
      'delivered' => (l.tmsShipStatusDelivered, c.successBg, c.successText),
      'exception' => (l.tmsShipStatusException, c.dangerBg, c.dangerText),
      _ => (code, c.warningBg, c.warningText),
    };
    return StatusBadge(label: text, bg: bg, fg: fg);
  }
}
