// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class CampaignListPage extends StatefulWidget {
  const CampaignListPage({super.key});
  @override
  State<CampaignListPage> createState() => _CampaignListPageState();
}

class _CampaignListPageState extends State<CampaignListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 负责人选项（id→姓名，id 为用户列表行 hashid），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _ownerOptions = {};
  bool _ownersLoaded = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/crm/campaign', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载负责人下拉选项（owner_user_id 必填且为 hashid，取自 /admin/v1/user 列表行 id）。
  Future<bool> _ensureOwners() async {
    if (_ownersLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/user', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _ownerOptions = {
        for (final u in list) '${u['id']}': '${u['real_name'] ?? u['username'] ?? ''}',
      };
      _ownersLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureOwners() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/campaign', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureOwners() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/campaign/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['name'] ?? row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/campaign/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端 CampaignController::store 契约对齐：name/owner_user_id 必填（负责人为 /admin/v1/user 下拉）
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.commonName, required: true),
      FormFieldConfig(
        name: 'owner_user_id',
        label: l10n.fieldOwner,
        required: true,
        type: FormFieldType.dropdown,
        options: _ownerOptions.keys.toList(),
        optionLabels: _ownerOptions,
      ),
      FormFieldConfig(name: 'code', label: l10n.crmCode),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.crmCampaignTitle,
    moduleKey: 'crm',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.commonName, AppL10n.current.crmCode, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.commonName: r['name'] ?? '',
    AppL10n.current.crmCode: r['code'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
