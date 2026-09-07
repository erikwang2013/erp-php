// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_tms_freight_rate 真实列：carrier_service_id/origin_*/dest_*/weight_*/
// base_rate/per_kg_rate/fuel_surcharge_pct/currency/valid_from/valid_to/status。
// 无 name/code 列（幻列已删）：承运服务名/编码由后端随行带回；地区/重量段/燃油/
// 币种字段无可用标签 key，本页先收敛于起步价+每公斤单价（缺省 0=不限区域/重量段
// 整卡费率），完整费率卡待补 key 后扩展（见报告）。
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

  Future<void> _create() async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: _formFields(),
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/tms/freight-rate', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: _formFields(),
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

  List<FormFieldConfig> _formFields() {
    final l = AppL10n.of(context);
    return [
      // 承运服务无独立列表接口：填数字ID/hashid 均可（后端双模解码），需先建承运商服务
      FormFieldConfig(name: 'carrier_service_id', label: l.tmsCarrierTitle, required: true),
      FormFieldConfig(name: 'valid_from', label: l.financeEffectiveDate, required: true),
      FormFieldConfig(name: 'base_rate', label: l.fieldAmount, type: FormFieldType.number),
      FormFieldConfig(name: 'per_kg_rate', label: l.fieldPrice, type: FormFieldType.number),
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

  /// 组装后端接收参数（status 拆出 0/1；留空费率补 0；valid_from 为空时不发送，
  /// 新建由后端 required 拦截、编辑保持原生效日——DATE 列不可写空串）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    String num(String key) {
      final v = data[key]?.trim();
      return (v == null || v.isEmpty) ? '0' : v;
    }

    final vf = data['valid_from']?.trim();
    return {
      'carrier_service_id': data['carrier_service_id']?.trim() ?? '',
      if (vf != null && vf.isNotEmpty) 'valid_from': vf,
      'base_rate': num('base_rate'),
      'per_kg_rate': num('per_kg_rate'),
      'status': pick('status'),
    };
  }

  /// 编辑回填：status int → 选项文案；数字原样。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final l = AppL10n.of(context);
    final status = row['status'];
    d['status'] = (status is int && status == 1) || '$status' == '1'
        ? '1 - ${l.commonEnabled}'
        : '0 - ${l.commonDisabled}';
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

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [l.tmsCarrierTitle, l.financeEffectiveDate, l.fieldAmount, l.fieldPrice, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.tmsCarrierTitle: r['carrier_service_name'] ?? r['carrier_service_code'] ?? '',
      l.financeEffectiveDate: r['valid_from'] ?? '',
      l.fieldAmount: '${r['base_rate'] ?? 0}',
      l.fieldPrice: '${r['per_kg_rate'] ?? 0}',
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
