// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_tms_freight_rate 真实列全暴露：carrier_service_id/origin_*/dest_*（区域组）/
// weight_from_kg/weight_to_kg/base_rate/per_kg_rate/fuel_surcharge_pct/currency/
// valid_from/valid_to/status。无 name/code 列（幻列已删）：服务名由后端随行带回；
// 承运服务下拉走 /admin/v1/tms/service（limit=500，行 id 为 hashid、label 取 name），
// 编辑时原服务不在列表则补一行原值（FormDialog 对无匹配下拉值自动落 null 兜底）。
// 重量止留空按 999 入库（=列默认值/不限上界），币种留空按 CNY（列默认值）。
class FreightRatePage extends StatefulWidget {
  const FreightRatePage({super.key});
  @override
  State<FreightRatePage> createState() => _FreightRatePageState();
}

class _FreightRatePageState extends State<FreightRatePage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

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
      final res = await ApiService.instance.get('/admin/v1/tms/freight-rate', params: {'page': '$_page', 'limit': '$_limit'});
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

  /// 承运服务下拉 /admin/v1/tms/service（行 id 为 hashid，label 取 name 列；
  /// 失败降级空表——弹窗不因服务列表不可用而阻塞）。
  Future<List<Map<String, dynamic>>> _loadServices() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/tms/service', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  /// 弹窗前预取服务下拉；编辑时原服务不在列表则补一行原值（hashid+随行名）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    final services = await _loadServices();
    if (!mounted) return _formFields([]);
    var options = [
      for (final s in services) '${s['id']} - ${s['name'] ?? s['code'] ?? s['id']}',
    ];
    if (row != null) {
      final sid = '${row['carrier_service_id'] ?? ''}';
      if (sid.isNotEmpty && !options.any((o) => o.startsWith('$sid - '))) {
        options = ['$sid - ${row['carrier_service_name'] ?? row['carrier_service_code'] ?? sid}', ...options];
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
        await ApiService.instance.post('/admin/v1/tms/freight-rate', data: _buildPayload(data));
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
        await ApiService.instance.put('/admin/v1/tms/freight-rate/${row['id']}', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['carrier_service_name'] ?? row['carrier_service_code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/tms/freight-rate/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields(List<String> serviceOptions) {
    final l = AppL10n.of(context);
    return [
      FormFieldConfig(
        name: 'carrier_service_id',
        label: l.tmsCarrierService,
        required: true,
        type: FormFieldType.dropdown,
        options: serviceOptions,
      ),
      FormFieldConfig(name: 'origin_country', label: l.tmsFreightOriginCountry),
      FormFieldConfig(name: 'origin_zone', label: l.tmsFreightOriginZone),
      FormFieldConfig(name: 'dest_country', label: l.tmsFreightDestCountry),
      FormFieldConfig(name: 'dest_zone', label: l.tmsFreightDestZone),
      FormFieldConfig(name: 'weight_from_kg', label: l.tmsFreightWeightFromKg, type: FormFieldType.number),
      FormFieldConfig(name: 'weight_to_kg', label: l.tmsFreightWeightToKg, type: FormFieldType.number),
      FormFieldConfig(name: 'base_rate', label: l.tmsFreightBaseRate, type: FormFieldType.number),
      FormFieldConfig(name: 'per_kg_rate', label: l.tmsFreightPerKgRate, type: FormFieldType.number),
      FormFieldConfig(name: 'fuel_surcharge_pct', label: l.tmsFreightFuelSurchargePct, type: FormFieldType.number),
      FormFieldConfig(name: 'currency', label: l.tmsFreightCurrency),
      FormFieldConfig(name: 'valid_from', label: l.financeEffectiveDate, required: true, hint: 'YYYY-MM-DD'),
      FormFieldConfig(name: 'valid_to', label: l.tmsFreightValidTo, hint: 'YYYY-MM-DD'),
      FormFieldConfig(
        name: 'status',
        label: l.commonStatus,
        type: FormFieldType.dropdown,
        options: _statusOptions,
        initialValue: '1 - ${l.commonEnabled}',
      ),
    ];
  }

  List<String> get _statusOptions {
    final l = AppL10n.of(context);
    return ['1 - ${l.commonEnabled}', '0 - ${l.commonDisabled}'];
  }

  /// 组装后端接收参数（status 拆出 0/1；数字留空按列默认 0（重量止/币种分别为
  /// 999/CNY=不限上界/默认币种）；valid_from 为空时不发送，新建由后端 required
  /// 拦截、编辑保持原生效日——DATE 列不可写空串；valid_to 空发 null 由后端落空）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    String num(String key, [String emptyTo = '0']) {
      final v = data[key]?.trim();
      return (v == null || v.isEmpty) ? emptyTo : v;
    }
    String str(String key) => (data[key] ?? '').trim();

    final vf = str('valid_from');
    final vt = str('valid_to');
    final currency = str('currency');
    return {
      'carrier_service_id': pick('carrier_service_id'),
      'origin_country': str('origin_country'),
      'origin_zone': str('origin_zone'),
      'dest_country': str('dest_country'),
      'dest_zone': str('dest_zone'),
      'weight_from_kg': num('weight_from_kg'),
      'weight_to_kg': num('weight_to_kg', '999'),
      'base_rate': num('base_rate'),
      'per_kg_rate': num('per_kg_rate'),
      'fuel_surcharge_pct': num('fuel_surcharge_pct'),
      'currency': currency.isEmpty ? 'CNY' : currency,
      if (vf.isNotEmpty) 'valid_from': vf,
      'valid_to': vt.isEmpty ? null : vt,
      'status': pick('status'),
    };
  }

  /// 编辑回填：status int → 选项文案；FK/hashid → 选项文案（含列表外补行）。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final l = AppL10n.of(context);
    final status = row['status'];
    d['status'] = (status is int && status == 1) || '$status' == '1'
        ? '1 - ${l.commonEnabled}'
        : '0 - ${l.commonDisabled}';
    final sid = '${row['carrier_service_id'] ?? ''}';
    final name = row['carrier_service_name'] ?? row['carrier_service_code'] ?? sid;
    d['carrier_service_id'] = sid.isEmpty ? '' : '$sid - $name';
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
    pageTitle: AppL10n.of(context).tmsFreightRateTitle,
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

  /// 数值展示：去掉浮点尾巴 0（12.0→12，999.0→999），空值留空。
  String _fmt(dynamic v) {
    if (v == null) return '';
    final s = '$v';
    return s.contains('.') ? s.replaceFirst(RegExp(r'\.?0+$'), '') : s;
  }

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [
      l.tmsCarrierService, l.tmsFreightOriginCountry, l.tmsFreightOriginZone,
      l.tmsFreightDestCountry, l.tmsFreightDestZone,
      l.tmsFreightWeightFromKg, l.tmsFreightWeightToKg,
      l.tmsFreightBaseRate, l.tmsFreightPerKgRate, l.tmsFreightFuelSurchargePct,
      l.tmsFreightCurrency, l.financeEffectiveDate, l.tmsFreightValidTo,
      l.commonStatus, l.commonAction,
    ];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.tmsCarrierService: r['carrier_service_name'] ?? r['carrier_service_code'] ?? '',
      l.tmsFreightOriginCountry: r['origin_country'] ?? '',
      l.tmsFreightOriginZone: r['origin_zone'] ?? '',
      l.tmsFreightDestCountry: r['dest_country'] ?? '',
      l.tmsFreightDestZone: r['dest_zone'] ?? '',
      l.tmsFreightWeightFromKg: _fmt(r['weight_from_kg']),
      l.tmsFreightWeightToKg: _fmt(r['weight_to_kg']),
      l.tmsFreightBaseRate: _fmt(r['base_rate']),
      l.tmsFreightPerKgRate: _fmt(r['per_kg_rate']),
      l.tmsFreightFuelSurchargePct: _fmt(r['fuel_surcharge_pct']),
      l.tmsFreightCurrency: r['currency'] ?? '',
      l.financeEffectiveDate: r['valid_from'] ?? '',
      l.tmsFreightValidTo: r['valid_to'] ?? '',
      l.commonStatus: _chip(r['status']),
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

  Widget _chip(dynamic status) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final on = status is int ? status == 1 : '$status' == '1';
    return StatusBadge(
      label: on ? l.commonEnabled : l.commonDisabled,
      bg: on ? c.successBg : c.warningBg,
      fg: on ? c.successText : c.warningText,
    );
  }
}
