// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class MaintenancePlanPage extends StatefulWidget {
  const MaintenancePlanPage({super.key});
  @override
  State<MaintenancePlanPage> createState() => _MaintenancePlanPageState();
}

class _MaintenancePlanPageState extends State<MaintenancePlanPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 设备下拉：equipment_id 是后端 hashid 契约（EquipmentController::index 出口 encodeIds），
  /// 手输数字/编辑态回写的 hashid 直灌 BIGINT 列都会崩；选项值=行 id(hashid)，标签取设备编码。
  /// notify=false 供列表单元格解析用（失败仍回落原值，不打扰用户）。
  Map<String, String> _equipments = {};

  Future<bool> _ensureRefs({bool notify = true}) async {
    try {
      final res = await ApiService.instance.get('/admin/v1/eam/equipment', params: {'limit': '500'});
      final options = {
        for (final r in List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []))
          '${r['id']}': '${r['code'] ?? r['name'] ?? r['id']}',
      };
      if (mounted) {
        setState(() => _equipments = options);
      } else {
        _equipments = options;
      }
      return true;
    } catch (e) {
      if (notify && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  @override
  void initState() { super.initState(); _load(); _ensureRefs(notify: false); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/eam/maintenance', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    if (!await _ensureRefs() || !mounted) return;
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(_equipments), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/maintenance', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureRefs() || !mounted) return;
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit,
      fields: _formFields(dropdownOptionsWithCurrent(_equipments, row['equipment_id'])), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/maintenance/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.eamDeleteConfirmMsg('${row['name'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/maintenance/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields(Map<String, String> equipmentOptions) => [
    FormFieldConfig(name: 'equipment_id', label: AppL10n.current.eamEquipmentId, required: true,
        type: FormFieldType.dropdown, options: equipmentOptions.keys.toList(), optionLabels: equipmentOptions),
    FormFieldConfig(name: 'name', label: AppL10n.current.eamPlanName, required: true),
    // 显示文案走 optionLabels（值=存储值不变，提交仍是 daily..yearly），词表同本页频率列
    FormFieldConfig(name: 'frequency', label: AppL10n.current.eamFrequency, required: true, type: FormFieldType.dropdown,
        options: ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
        optionLabels: {for (final v in const ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']) v: _frequencyLabel(v)}),
    FormFieldConfig(name: 'last_date', label: AppL10n.current.eamLastDate),
    FormFieldConfig(name: 'next_date', label: AppL10n.current.eamNextDateFull),
    FormFieldConfig(name: 'assignee', label: AppL10n.current.eamAssignee),
    FormFieldConfig(name: 'status', label: AppL10n.current.commonStatus, type: FormFieldType.dropdown,
        options: ['0', '1'], optionLabels: {for (final v in const ['0', '1']) v: _statusLabel(v)}),
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
      pageTitle: AppL10n.current.eamMaintenanceTitle,
      moduleKey: 'eam',
      primaryColumnIndex: 0,

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
      rightAlignColumns: [3],
    );
  }

  List<String> _columns() => [
    AppL10n.current.eamPlanName,
    AppL10n.current.eamEquipmentId,
    AppL10n.current.eamFrequencyCol,
    AppL10n.current.eamNextDate,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  /// 频率列：值域即本页表单下拉的 options（daily/weekly/monthly/quarterly/yearly）；
  /// 状态列：TINYINT 0/1（列默认 1，同域 eam/equipment_list_page 的 0=禁用 1=启用）。
  /// 机读值不上屏，未知值原样回落。
  static String _frequencyLabel(Object? v) => switch ('$v') {
        'daily' => AppL10n.current.eamFrequencyDaily,
        'weekly' => AppL10n.current.eamFrequencyWeekly,
        'monthly' => AppL10n.current.eamFrequencyMonthly,
        'quarterly' => AppL10n.current.eamFrequencyQuarterly,
        'yearly' => AppL10n.current.eamFrequencyYearly,
        _ => '$v',
      };

  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.commonDisabled,
        '1' => AppL10n.current.commonEnabled,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.eamPlanName: r['name'] ?? '',
    AppL10n.current.eamEquipmentId: _equipments['${r['equipment_id']}'] ?? '-',
    AppL10n.current.eamFrequencyCol: _frequencyLabel(r['frequency']),
    AppL10n.current.eamNextDate: r['next_date'] ?? '',
    AppL10n.current.commonStatus: _statusLabel(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
}
