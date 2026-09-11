// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class OpportunityListPage extends StatefulWidget {
  const OpportunityListPage({super.key});
  @override
  State<OpportunityListPage> createState() => _OpportunityListPageState();
}

class _OpportunityListPageState extends State<OpportunityListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 客户选项（id→名称，id 为客户列表行 hashid），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _customerOptions = {};
  bool _customersLoaded = false;

  /// 漏斗阶段选项（id→阶段名，id 为 /admin/v1/crm/funnel 列表行 hashid，仅启用 status=1）。
  Map<String, String> _stageOptions = {};
  bool _stagesLoaded = false;

  /// 加载客户下拉选项（customer_id 必填且为 hashid，取自 /admin/v1/customer 列表行 id）。
  Future<bool> _ensureCustomers() async {
    if (_customersLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/customer', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _customerOptions = {
        for (final c in list) '${c['id']}': '${c['name'] ?? c['code'] ?? ''}',
      };
      _customersLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  /// 加载漏斗阶段下拉选项（stage_id 必填，erp_crm_opportunity.stage_id → erp_crm_funnel_stage.id）。
  Future<bool> _ensureStages() async {
    if (_stagesLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/crm/funnel', params: {'limit': '500', 'status': '1'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _stageOptions = {
        for (final s in list) '${s['id']}': '${s['name'] ?? ''}',
      };
      _stagesLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/crm/opportunity', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !await _ensureStages() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/opportunity', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !await _ensureStages() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/opportunity/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  /// 组装后端 store()/update() 接收的参数（仅真实表列；amount→estimated_amount 字段名对齐，
  /// stage→stage_id 下拉值即 hashid；幻键 code 不再发送）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) => {
    'name': data['name']?.trim() ?? '',
    'customer_id': data['customer_id']?.trim() ?? '',
    'stage_id': data['stage_id']?.trim() ?? '',
    'estimated_amount': (data['estimated_amount']?.trim().isEmpty ?? true) ? '' : data['estimated_amount']!.trim(),
  };

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['name'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/opportunity/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端契约对齐（erp_crm_opportunity）：name/customer_id/stage_id NOT NULL；
  // 表无 code 列，金额列名为 estimated_amount（幻键 code/amount/stage 已移除）
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.current.commonName, required: true),
    FormFieldConfig(
      name: 'customer_id',
      label: AppL10n.current.fieldCustomer,
      required: true,
      type: FormFieldType.dropdown,
      options: _customerOptions.keys.toList(),
      optionLabels: _customerOptions,
    ),
    FormFieldConfig(
      name: 'stage_id',
      label: AppL10n.current.crmOpportunityStage,
      required: true,
      type: FormFieldType.dropdown,
      options: _stageOptions.keys.toList(),
      optionLabels: _stageOptions,
    ),
    FormFieldConfig(name: 'estimated_amount', label: AppL10n.current.crmAmount, type: FormFieldType.number),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.crmOpportunityTitle,
    moduleKey: 'crm',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
    rightAlignColumns: [2],
  );

  List<String> _columns() => [AppL10n.current.commonName, AppL10n.current.fieldCustomer, AppL10n.current.crmAmount, AppL10n.current.crmOpportunityStage, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.commonName: r['name'] ?? '',
    AppL10n.current.fieldCustomer: r['customer_name'] ?? r['customer_id'] ?? '',
    AppL10n.current.crmAmount: r['estimated_amount'] ?? '',
    AppL10n.current.crmOpportunityStage: r['stage_name'] ?? r['stage_id'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
