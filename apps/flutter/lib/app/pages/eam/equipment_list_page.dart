// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class EquipmentListPage extends StatefulWidget {
  const EquipmentListPage({super.key});
  @override
  State<EquipmentListPage> createState() => _EquipmentListPageState();
}

class _EquipmentListPageState extends State<EquipmentListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 部门下拉：department_id 是后端 hashid 契约（DepartmentController::index 出口 encodeIds），
  /// 手输数字/编辑态回写的 hashid 直灌 BIGINT 列都会崩；选项值=行 id(hashid)，标签取部门名。
  /// 该字段可空（空=不指定，后端 normalizeFkData 按「不修改」处理），失败不阻断弹窗。
  Map<String, String> _departments = {};

  Future<void> _ensureRefs() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/hr/department');
      final options = {
        for (final r in List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []))
          '${r['id']}': '${r['name'] ?? r['code'] ?? r['id']}',
      };
      if (mounted) {
        setState(() => _departments = options);
      } else {
        _departments = options;
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
    }
  }

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/eam/equipment', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    await _ensureRefs();
    if (!mounted) return;
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(_departments), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/equipment', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await _ensureRefs();
    if (!mounted) return;
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit,
      fields: _formFields(dropdownOptionsWithCurrent(_departments, row['department_id'])), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/equipment/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.eamDeleteConfirmMsg('${row['name'] ?? row['code'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/equipment/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields(Map<String, String> departmentOptions) => [
    FormFieldConfig(name: 'code', label: AppL10n.current.eamEquipmentCode, required: true),
    FormFieldConfig(name: 'name', label: AppL10n.current.eamEquipmentName, required: true),
    FormFieldConfig(name: 'model', label: AppL10n.current.eamModel),
    FormFieldConfig(name: 'serial_number', label: AppL10n.current.eamSerialNumber),
    FormFieldConfig(name: 'category', label: AppL10n.current.eamCategory),
    FormFieldConfig(name: 'location', label: AppL10n.current.eamLocation),
    FormFieldConfig(name: 'department_id', label: AppL10n.current.eamDepartmentId,
        type: FormFieldType.dropdown, options: departmentOptions.keys.toList(), optionLabels: departmentOptions),
    FormFieldConfig(name: 'purchase_date', label: AppL10n.current.eamPurchaseDate),
    FormFieldConfig(name: 'warranty_expiry', label: AppL10n.current.eamWarrantyExpiry),
    FormFieldConfig(name: 'status', label: AppL10n.current.commonStatus, type: FormFieldType.dropdown, options: const ['0', '1'], optionLabels: {'0': AppL10n.current.commonDisabled, '1': AppL10n.current.commonEnabled}),
  ];

  /// 状态列/详情值：0/1 走后端语义（TINYINT 默认 1=启用），未知值原样回落
  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.commonDisabled,
        '1' => AppL10n.current.commonEnabled,
        _ => '$v',
      };

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
      pageTitle: AppL10n.current.eamEquipmentTitle,
      moduleKey: 'eam',
      primaryColumnIndex: 0,

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.eamEquipmentCode,
    AppL10n.current.eamEquipmentName,
    AppL10n.current.eamModel,
    AppL10n.current.eamCategoryCol,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.eamEquipmentCode: r['code'] ?? '',
    AppL10n.current.eamEquipmentName: r['name'] ?? '',
    AppL10n.current.eamModel: r['model'] ?? '',
    AppL10n.current.eamCategoryCol: r['category'] ?? '',
    AppL10n.current.commonStatus: _statusLabel(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
}
