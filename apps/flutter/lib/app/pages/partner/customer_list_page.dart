// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class CustomerListPage extends StatefulWidget {
  const CustomerListPage({super.key});
  @override
  State<CustomerListPage> createState() => _CustomerListPageState();
}

class _CustomerListPageState extends State<CustomerListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  /// 客户等级下拉选项：id(hashid)→名称，取自 /admin/v1/customer-level（懒加载一次）。
  Map<String, String> _levelLabels = {};

  /// 加载客户等级下拉（level_id 可选，0=无等级；值为 hashid，后端双模解码）。
  Future<bool> _ensureLevels() async {
    if (_levelLabels.isNotEmpty) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/customer-level');
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _levelLabels = { for (final r in list) '${r['id']}': '${r['name'] ?? ''}' };
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/customer', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    if (!await _ensureLevels() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/customer', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureLevels() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/customer/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent(row['name'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/customer/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 幻键修正：contact→contact_person、level→level_id（erp_customer 真实列），
  // 旧键永不落库导致联系人/等级被静默抹除；level_id 改下拉选等级（值=hashid，
  // 后端双模解码；0/留空=无等级）。列表行 level_id 为原生整数不可回填 → 编辑时下拉为空。
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.fieldName, required: true),
      FormFieldConfig(name: 'code', label: l10n.fieldCode),
      FormFieldConfig(name: 'contact_person', label: l10n.fieldContact),
      FormFieldConfig(
        name: 'level_id',
        label: l10n.fieldLevel,
        type: FormFieldType.dropdown,
        options: _levelLabels.keys.toList(),
        optionLabels: _levelLabels,
      ),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.partnerCustomerTitle,
    moduleKey: 'partner',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldName, l10n.fieldCode, l10n.fieldContact, l10n.fieldLevel, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    final levelId = r['level_id'];
    return {
      l10n.fieldName: r['name'] ?? '',
      l10n.fieldCode: r['code'] ?? '',
      // 列表行无等级名称关联（后端未 enrich），等级列展示原生 level_id；0=无等级留空
      l10n.fieldContact: r['contact_person'] ?? '',
      l10n.fieldLevel: (levelId == null || '$levelId' == '0') ? '' : '$levelId',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

}
