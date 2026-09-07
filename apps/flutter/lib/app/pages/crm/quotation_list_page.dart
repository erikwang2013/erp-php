// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class CrmQuotationListPage extends StatefulWidget {
  const CrmQuotationListPage({super.key});
  @override
  State<CrmQuotationListPage> createState() => _CrmQuotationListPageState();
}

class _CrmQuotationListPageState extends State<CrmQuotationListPage> {
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

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/crm/quotation', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/quotation', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/quotation/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/quotation/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 报价转合同：填写合同编号/名称/备注，调用 POST /admin/crm/quotation/{id}/to-contract。
  Future<void> _toContract(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmQuotationToContract, fields: [
      FormFieldConfig(name: 'code', label: l10n.crmContractCode, hint: l10n.crmQuotationCodeHint),
      FormFieldConfig(name: 'name', label: l10n.crmContractName, hint: l10n.crmQuotationNameHint),
      FormFieldConfig(name: 'remark', label: l10n.crmRemark, type: FormFieldType.multiline),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/quotation/${row['id']}/to-contract', data: {
        'code': data['code']?.trim(),
        'name': data['name']?.trim(),
        'remark': data['remark']?.trim() ?? '',
      });
      _load(); return true;
    });
  }

  // 与后端契约对齐（erp_crm_quotation）：code/customer_id/owner_user_id NOT NULL（负责人由后端
  // 默认当前管理员）；表无 name 列（幻键已移除）。单号留空自动生成 QT+时间戳。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.current.crmCode, hint: AppL10n.current.salesQuotationCodeHint),
    FormFieldConfig(
      name: 'customer_id',
      label: AppL10n.current.fieldCustomer,
      required: true,
      type: FormFieldType.dropdown,
      options: _customerOptions.keys.toList(),
      optionLabels: _customerOptions,
    ),
    FormFieldConfig(name: 'total_amount', label: AppL10n.current.crmAmount, type: FormFieldType.number),
  ];

  /// 组装后端 store()/update() 接收的参数（仅真实表列；幻键 name 不再发送）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'QT${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    return {
      'code': code,
      'customer_id': data['customer_id']?.trim() ?? '',
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '' : data['total_amount']!.trim(),
    };
  }

  String _p2(int v) => v.toString().padLeft(2, '0');

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.crmQuotationTitle,
    moduleKey: 'crm',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.crmCode, AppL10n.current.fieldCustomer, AppL10n.current.crmAmount, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.crmCode: r['code'] ?? '',
    AppL10n.current.fieldCustomer: r['customer_name'] ?? r['customer_id'] ?? '',
    AppL10n.current.crmAmount: r['total_amount'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: Icon(Icons.handshake, size: 18, color: AppColors.of(context).primary),
        tooltip: AppL10n.current.crmQuotationConvert, onPressed: () => _toContract(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
