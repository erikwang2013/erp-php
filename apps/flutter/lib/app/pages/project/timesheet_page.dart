// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class TimesheetPage extends StatefulWidget {
  const TimesheetPage({super.key});
  @override
  State<TimesheetPage> createState() => _TimesheetPageState();
}

class _TimesheetPageState extends State<TimesheetPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 项目选项（id→名称），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _projectOptions = {};
  bool _projectLoaded = false;

  /// 用户选项（id→姓名），同上。
  Map<String, String> _userOptions = {};
  bool _userLoaded = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/project/timesheet', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载下拉选项（POST 的 project_id/user_id 必填且为 hashid，取自对应列表行 id）。
  Future<bool> _ensureOptions() async {
    if (_projectLoaded && _userLoaded) return true;
    try {
      if (!_projectLoaded) {
        final pres = await ApiService.instance.get('/admin/v1/project', params: {'limit': '500'});
        final plist = List<Map<String, dynamic>>.from((pres['data'] ?? {})['list'] ?? []);
        _projectOptions = {
          for (final p in plist) '${p['id']}': '${p['name'] ?? p['code'] ?? ''}',
        };
        _projectLoaded = true;
      }
      if (!_userLoaded) {
        final ures = await ApiService.instance.get('/admin/v1/user', params: {'limit': '500'});
        final ulist = List<Map<String, dynamic>>.from((ures['data'] ?? {})['list'] ?? []);
        _userOptions = {
          for (final u in ulist) '${u['id']}': '${u['real_name'] ?? u['username'] ?? ''}',
        };
        _userLoaded = true;
      }
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    if (!await _ensureOptions() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/project/timesheet', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureOptions() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/project/timesheet/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['work_date'] ?? ''}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/project/timesheet/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 字段与后端 TimesheetController::store 契约对齐：
  // erp_project_timesheet 无 name/code 列（install.sql 权威），project_id/user_id/hours/work_date 必填；
  // work_date 走文本 YYYY-MM-DD（FormDialog 无日期控件）。
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(
        name: 'project_id',
        label: l10n.fieldProject,
        required: true,
        type: FormFieldType.dropdown,
        options: _projectOptions.keys.toList(),
        optionLabels: _projectOptions,
      ),
      FormFieldConfig(
        name: 'user_id',
        label: l10n.fieldUser,
        required: true,
        type: FormFieldType.dropdown,
        options: _userOptions.keys.toList(),
        optionLabels: _userOptions,
      ),
      FormFieldConfig(name: 'work_date', label: l10n.fieldWorkDate, required: true, hint: 'YYYY-MM-DD'),
      FormFieldConfig(name: 'hours', label: l10n.fieldHours, required: true, type: FormFieldType.number),
      FormFieldConfig(name: 'description', label: l10n.fieldContent),
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
    pageTitle: AppL10n.current.projectTimesheetTitle,
    moduleKey: 'project',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldWorkDate, l10n.fieldProject, l10n.fieldUser, l10n.fieldHours, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldWorkDate: r['work_date'] ?? '',
      l10n.fieldProject: r['project_name'] ?? '',
      l10n.fieldUser: r['user_name'] ?? '',
      l10n.fieldHours: r['hours'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

}
