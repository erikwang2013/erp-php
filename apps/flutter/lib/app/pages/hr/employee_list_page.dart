// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 注意：电话 / 邮箱 / 身份证号等敏感字段由服务端加密存储，列表仅展示脱敏结果。
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class EmployeeListPage extends StatefulWidget {
  const EmployeeListPage({super.key});
  @override
  State<EmployeeListPage> createState() => _EmployeeListPageState();
}

class _EmployeeListPageState extends State<EmployeeListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/hr/employee', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/hr/employee', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/hr/employee/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.hrDeleteConfirmMsg('${row['name'] ?? row['code'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/hr/employee/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 幻键修正：department/position（部门/职位名）非表列，提交后被 $fillable 白名单
  // 静默丢弃 → 员工部门/职位永不落库。现改用真实列 department_id/position_id：
  // EmployeeController 不做 id 解码且模型按 integer 存储，故用数字输入填原生 ID
  //（/admin/v1/hr/department|position 仅暴露 hashid，无法作下拉值回填）。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.current.commonCode, required: true), // 后端 store 必填 code，旧表单缺此键致新增恒 422
    FormFieldConfig(name: 'name', label: AppL10n.current.hrEmpName, required: true),
    FormFieldConfig(name: 'department_id', label: AppL10n.current.hrEmpDepartment, type: FormFieldType.number),
    FormFieldConfig(name: 'phone', label: AppL10n.current.hrEmpPhone),
    FormFieldConfig(name: 'position_id', label: AppL10n.current.hrEmpPosition, type: FormFieldType.number),
  ];

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return DataTableWrapper(
      columns: _columns(),
      rows: _rows.map((r) => _rowToMap(r)).toList(),
      total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
      keyword: _keyword,
      onSearch: (v) { _keyword = v; _page = 1; _load(); },
      onPageChanged: (p) { _page = p; _load(); },
      pageTitle: AppL10n.current.hrEmployeeListTitle,
      moduleKey: 'hr',
      primaryColumnIndex: 0,

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.hrEmpName,
    AppL10n.current.hrEmpDepartment,
    AppL10n.current.hrEmpPhone,
    AppL10n.current.hrEmpPosition,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.hrEmpName: r['name'] ?? '',
    // department/position 为后端带出的嵌套关联对象（含 name），取名称展示，缺失回退原生 ID
    AppL10n.current.hrEmpDepartment: _relName(r['department'], r['department_id']),
    AppL10n.current.hrEmpPhone: r['phone'] ?? '',
    AppL10n.current.hrEmpPosition: _relName(r['position'], r['position_id']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 关联对象存在时优先显示其名称，否则回退行内原生 ID（0/空则留空）。
  static String _relName(dynamic rel, dynamic fallback) {
    if (rel is Map<String, dynamic>) {
      final v = rel['name'];
      if (v != null && '$v'.isNotEmpty) return '$v';
    }
    final id = fallback;
    return (id == null || '$id' == '0' || '$id' == '') ? '' : '$id';
  }

}
