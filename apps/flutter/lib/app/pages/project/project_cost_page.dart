// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class ProjectCostPage extends StatefulWidget {
  const ProjectCostPage({super.key});
  @override
  State<ProjectCostPage> createState() => _ProjectCostPageState();
}

class _ProjectCostPageState extends State<ProjectCostPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 项目/任务/员工下拉（id→名称），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _projectOptions = {};
  Map<String, String> _taskOptions = {};
  Map<String, String> _employeeOptions = {};

  // 列表「项目」列与下拉共用同一份 id→名称映射：initState 一并预取（rule ③），
  // 取不到落「-」（rule ④），裸 hashid 不上屏。
  @override
  void initState() { super.initState(); _load(); _loadProjectNames(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/project/cost', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 单端点选项（id→名称）；失败降级空表，弹窗仍可打开。
  Future<Map<String, String>> _options(String endpoint, String Function(Map<String, dynamic>) label) async {
    try {
      final res = await ApiService.instance.get(endpoint, params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
      return {for (final r in list) '${r['id']}': label(r)};
    } catch (_) {
      return {};
    }
  }

  /// 列表项目列的名称来源：/admin/v1/project 的 id→名称（ProjectCostController::index
  /// 不带项目名，rule ③）；名下取不到名称时不写 hashid 当名称用。
  Future<void> _loadProjectNames() async {
    final m = await _options('/admin/v1/project', (r) => '${r['name'] ?? ''}');
    if (mounted && m.isNotEmpty) setState(() => _projectOptions = m);
  }

  /// 弹窗前预取三个外键；编辑行的原值不在最新列表时补一行裸 id（否则回填被置空）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    _projectOptions = await _options('/admin/v1/project', (r) => '${r['name'] ?? r['id']}');
    _taskOptions = await _options('/admin/v1/project/task', (r) => '${r['name'] ?? r['id']}');
    _employeeOptions = await _options('/admin/v1/hr/employee', (r) => '${r['name'] ?? r['id']}');
    if (!mounted) return _formFields();
    void keepCurrent(String key, Map<String, String> map) {
      final cur = '${row?[key] ?? ''}';
      if (cur.isNotEmpty && !map.containsKey(cur)) map[cur] = cur;
    }
    keepCurrent('project_id', _projectOptions);
    keepCurrent('task_id', _taskOptions);
    keepCurrent('employee_id', _employeeOptions);
    return _formFields();
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: fields, onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/project/cost', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final fields = await _fieldsFor(row: row);
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: fields, initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/project/cost/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteContent('${row['work_date'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/project/cost/${row['id']}', data: {'password': password});
        _load(); return true;
      },
    );
  }

  // 字段对齐 ProjectCostController::store：project_id/work_date/category/hours/cost
  // 的 validator 全为 required（hours、cost 的「按类别必填」只写在注释里，validator 是
  // 硬门禁），且 category=1 时后端按 工时×费率 自算金额、忽略传入 cost；
  // category=2/3 时忽略 hours/rate。故 hours/cost 留空时补 '0'：既不触发 required 的
  // 422，错误信息也由服务层给（如「成本金额必须大于0」）。work_date 走文本 YYYY-MM-DD
  // （FormDialog 无日期控件，同 project/timesheet_page.dart）。
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
      FormFieldConfig(name: 'work_date', label: l10n.fieldOccurDate, required: true, hint: l10n.commonDateFormat),
      FormFieldConfig(
        name: 'category',
        label: l10n.fieldCostCategory,
        required: true,
        type: FormFieldType.dropdown,
        options: const ['1', '2', '3'],
        optionLabels: {
          '1': l10n.projectCostLabor,
          '2': l10n.projectCostMaterial,
          '3': l10n.projectCostOther,
        },
        initialValue: '1',
      ),
      FormFieldConfig(name: 'hours', label: l10n.fieldHours, type: FormFieldType.number),
      FormFieldConfig(name: 'rate', label: l10n.fieldRate, type: FormFieldType.number),
      FormFieldConfig(name: 'cost', label: l10n.fieldAmount, type: FormFieldType.number),
      FormFieldConfig(
        name: 'task_id',
        label: l10n.fieldLinkedTask,
        type: FormFieldType.dropdown,
        options: _taskOptions.keys.toList(),
        optionLabels: _taskOptions,
      ),
      FormFieldConfig(
        name: 'employee_id',
        label: l10n.fieldEmployee,
        type: FormFieldType.dropdown,
        options: _employeeOptions.keys.toList(),
        optionLabels: _employeeOptions,
      ),
      FormFieldConfig(name: 'remark', label: l10n.commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 组装后端接收参数：下拉项已是 hashid 键；hours/cost 空串补 '0'（见 _formFields 注释）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String num(String key) {
      final v = (data[key] ?? '').trim();
      return v.isEmpty ? '0' : v;
    }
    return {
      'project_id': data['project_id'] ?? '',
      'work_date': data['work_date']?.trim() ?? '',
      'category': data['category'] ?? '1',
      'hours': num('hours'),
      'rate': num('rate'),
      'cost': num('cost'),
      'task_id': data['task_id'] ?? '',
      'employee_id': data['employee_id'] ?? '',
      'remark': data['remark']?.trim() ?? '',
    };
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.projectCostTitle,
    moduleKey: 'project',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldProject, l10n.fieldOccurDate, l10n.fieldCostCategory, l10n.fieldAmount, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    final category = {
      '1': l10n.projectCostLabor,
      '2': l10n.projectCostMaterial,
      '3': l10n.projectCostOther,
    }['${r['category']}'] ?? '${r['category'] ?? ''}';
    // 项目列：下拉那份 id→名称（rule ③）；未加载/未命中（含下拉为补行填的裸 id）落「-」
    final projectId = '${r['project_id'] ?? ''}';
    final projectName = _projectOptions[projectId];
    return {
      l10n.fieldProject:
          (projectName == null || projectName.isEmpty || projectName == projectId) ? '-' : projectName,
      l10n.fieldOccurDate: r['work_date'] ?? '',
      l10n.fieldCostCategory: category,
      l10n.fieldAmount: r['cost'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }
}
