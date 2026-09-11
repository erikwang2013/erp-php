// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class ProjectListPage extends StatefulWidget {
  const ProjectListPage({super.key});
  @override
  State<ProjectListPage> createState() => _ProjectListPageState();
}

class _ProjectListPageState extends State<ProjectListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 负责人选项（id→姓名），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _managerOptions = {};
  bool _managerLoaded = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/project', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载负责人选项（POST 的 manager_user_id 必填且为 hashid，取自 /admin/v1/user 列表行 id）。
  Future<bool> _ensureManagers() async {
    if (_managerLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/user', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _managerOptions = {
        for (final u in list) '${u['id']}': '${u['real_name'] ?? u['username'] ?? ''}',
      };
      _managerLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    if (!await _ensureManagers() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/project', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureManagers() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/project/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent(row['name'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/project/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 字段与后端 ProjectController::store 契约对齐：
  // name/code/manager_user_id 必填（code 因 uk_code 唯一约束不可为空，空串第二次提交即 1062 重复键）。
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.commonName, required: true),
      FormFieldConfig(name: 'code', label: l10n.fieldCode, required: true),
      FormFieldConfig(
        name: 'manager_user_id',
        label: l10n.fieldManager,
        required: true,
        type: FormFieldType.dropdown,
        options: _managerOptions.keys.toList(),
        optionLabels: _managerOptions,
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
    pageTitle: AppL10n.current.projectListTitle,
    moduleKey: 'project',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.commonName, l10n.fieldCode, l10n.fieldManager, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.commonName: r['name'] ?? '',
      l10n.fieldCode: r['code'] ?? '',
      l10n.fieldManager: r['manager_name'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

}
