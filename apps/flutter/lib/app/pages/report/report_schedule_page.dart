// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class ReportSchedulePage extends StatefulWidget {
  const ReportSchedulePage({super.key});
  @override
  State<ReportSchedulePage> createState() => _ReportSchedulePageState();
}

class _ReportSchedulePageState extends State<ReportSchedulePage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 模板选项（id→名称），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _templateOptions = {};
  bool _templateLoaded = false;

  /// 接收人选项（id→姓名），同上。
  Map<String, String> _userOptions = {};
  bool _userLoaded = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/report/schedule', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载下拉选项（POST 的 template_id/recipients 必填且为 hashid，取自模板/用户列表行 id）。
  Future<bool> _ensureOptions() async {
    if (_templateLoaded && _userLoaded) return true;
    try {
      if (!_templateLoaded) {
        final tres = await ApiService.instance.get('/admin/v1/report', params: {'limit': '500'});
        final tlist = List<Map<String, dynamic>>.from((tres['data'] ?? {})['list'] ?? []);
        _templateOptions = {
          for (final t in tlist) '${t['id']}': '${t['name'] ?? t['code'] ?? ''}',
        };
        _templateLoaded = true;
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
      await ApiService.instance.post('/admin/v1/report/schedule', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureOptions() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/report/schedule/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent(row['name'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/report/schedule/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  String _freqLabel(int v) {
    final l10n = AppL10n.current;
    return switch (v) { 1 => l10n.freqDaily, 2 => l10n.freqWeekly, _ => l10n.freqMonthly };
  }

  // 字段与后端 ReportScheduleController::store 契约对齐：
  // erp_report_schedule 无 code 列；template_id/name/frequency(1每天2每周3每月)/recipients 必填；
  // recipients 兼容单接收人下拉（hashid），多接收人走逗号分隔文本。
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(
        name: 'template_id',
        label: l10n.fieldTemplate,
        required: true,
        type: FormFieldType.dropdown,
        options: _templateOptions.keys.toList(),
        optionLabels: _templateOptions,
      ),
      FormFieldConfig(name: 'name', label: l10n.fieldName, required: true),
      FormFieldConfig(
        name: 'frequency',
        label: l10n.fieldFrequency,
        required: true,
        type: FormFieldType.dropdown,
        options: const ['1', '2', '3'],
        optionLabels: {
          '1': l10n.freqDaily,
          '2': l10n.freqWeekly,
          '3': l10n.freqMonthly,
        },
      ),
      FormFieldConfig(
        name: 'recipients',
        label: l10n.fieldReceiver,
        required: true,
        type: FormFieldType.dropdown,
        options: _userOptions.keys.toList(),
        optionLabels: _userOptions,
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
    pageTitle: AppL10n.current.reportScheduleTitle,
    moduleKey: 'report',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldName, l10n.fieldTemplate, l10n.fieldReceiver, l10n.fieldFrequency, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldName: r['name'] ?? '',
      l10n.fieldTemplate: r['template_name'] ?? '',
      l10n.fieldReceiver: r['recipients_names'] ?? '',
      l10n.fieldFrequency: _freqLabel(int.tryParse('${r['frequency'] ?? ''}') ?? 0),
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

}
