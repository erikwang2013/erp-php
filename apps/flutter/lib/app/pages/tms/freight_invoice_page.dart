// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class FreightInvoicePage extends StatefulWidget {
  const FreightInvoicePage({super.key});
  @override
  State<FreightInvoicePage> createState() => _FreightInvoicePageState();
}

class _FreightInvoicePageState extends State<FreightInvoicePage> {
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
        '/admin/v1/tms/freight-invoice',
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

  /// 承运商/运单下拉：各取 limit=500（失败降级空表）。
  Future<List<String>> _optionList(String endpoint, String Function(Map<String, dynamic>) label) async {
    try {
      final res = await ApiService.instance.get(endpoint, params: {'limit': '500'});
      final rows = List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
      return [for (final r in rows) '${r['id']} - ${label(r)}'];
    } catch (_) {
      return [];
    }
  }

  /// 弹窗前预取承运商/运单（编辑时补一行原值防回填落空）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    var carriers = await _optionList('/admin/v1/tms/carrier', (r) => '${r['name'] ?? r['id']}');
    var shipments = await _optionList(
      '/admin/v1/tms/shipment',
      (r) => '${r['tracking_no'] ?? r['code'] ?? r['id']}',
    );
    if (!mounted) return _formFields([], []);
    final cid = '${row?['carrier_id'] ?? ''}';
    if (cid.isNotEmpty && !carriers.any((o) => o.startsWith('$cid - '))) {
      carriers = [cid, ...carriers];
    }
    final sid = '${row?['shipment_id'] ?? ''}';
    if (sid.isNotEmpty && !shipments.any((o) => o.startsWith('$sid - '))) {
      shipments = [sid, ...shipments];
    }
    return _formFields(carriers, shipments);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post(
          '/admin/v1/tms/freight-invoice',
          data: _buildPayload(data),
        );
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
      initialData: _toEditData(row, fields),
      onSubmit: (data) async {
        await ApiService.instance.put(
          '/admin/v1/tms/freight-invoice/${row['id']}',
          data: _buildPayload(data),
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
      content: AppL10n.of(
        context,
      ).commonDeleteMsg('${row['code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete(
          '/admin/v1/tms/freight-invoice/${row['id']}',
          data: {'password': password},
        );
        _load();
        return true;
      },
    );
  }

  // erp_tms_freight_invoice 无 name 列，且 carrier_id/shipment_id 为 NOT NULL 无默认
  // （install.sql）；store 的 carrier_id/shipment_id decodeFlexibleId 失败即 422
  // "Invalid carrier/shipment" —— 原 'name' 幻字段无意义，必填的是承运商 + 运单。
  // 字段对齐 Web 两端（Angular/React fulfill 域：code/carrier_id/shipment_id/amount…）。
  List<FormFieldConfig> _formFields(List<String> carrierOptions, List<String> shipmentOptions) => [
    FormFieldConfig(name: 'code', label: AppL10n.of(context).commonCode),
    FormFieldConfig(
      name: 'carrier_id',
      label: AppL10n.of(context).tmsCarrierTitle,
      required: true,
      type: FormFieldType.dropdown,
      options: carrierOptions,
    ),
    FormFieldConfig(
      name: 'shipment_id',
      label: AppL10n.of(context).fieldTrackingNo,
      required: true,
      type: FormFieldType.dropdown,
      options: shipmentOptions,
    ),
    FormFieldConfig(name: 'amount', label: AppL10n.of(context).fieldAmount, type: FormFieldType.number),
  ];

  /// 组装后端接收参数：下拉项 'id - 名称' 取回 hashid。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    return {
      'code': data['code']?.trim() ?? '',
      'carrier_id': pick('carrier_id'),
      'shipment_id': pick('shipment_id'),
      'amount': data['amount']?.trim() ?? '',
    };
  }

  /// 编辑回填：下拉值必须与 options 字符串完全一致（FormDialog 匹配不上会置空），
  /// 而列表接口不带承运商/运单名，故按 id 前缀在选项里找 'id - 名称'、否则退回裸 id。
  Map<String, dynamic> _toEditData(
    Map<String, dynamic> row,
    List<FormFieldConfig> fields,
  ) {
    final d = Map<String, dynamic>.from(row);
    for (final key in ['carrier_id', 'shipment_id']) {
      final options = fields.firstWhere((f) => f.name == key).options;
      final id = '${row[key] ?? ''}';
      d[key] = id.isEmpty
          ? ''
          : options.firstWhere((o) => o.startsWith('$id - '), orElse: () => id);
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
    pageTitle: AppL10n.of(context).tmsFreightInvoiceTitle,
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

  // 列表接口不 join 承运商名：承运商列按原值（hashid）展示。
  List<String> _columns() => [
    AppL10n.of(context).commonCode,
    AppL10n.of(context).tmsCarrierTitle,
    AppL10n.of(context).fieldAmount,
    AppL10n.of(context).commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.commonCode: r['code'] ?? '',
      l.tmsCarrierTitle: r['carrier_id'] ?? '',
      l.fieldAmount: r['amount'] ?? '',
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
}
