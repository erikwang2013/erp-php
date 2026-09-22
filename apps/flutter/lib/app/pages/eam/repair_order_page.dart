// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class RepairOrderPage extends StatefulWidget {
  const RepairOrderPage({super.key});
  @override
  State<RepairOrderPage> createState() => _RepairOrderPageState();
}

class _RepairOrderPageState extends State<RepairOrderPage> {
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

      final res = await ApiService.instance.get('/admin/v1/eam/repair', params: params);
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
      await ApiService.instance.post('/admin/v1/eam/repair', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureRefs() || !mounted) return;
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit,
      fields: _formFields(dropdownOptionsWithCurrent(_equipments, row['equipment_id'])), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/repair/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.eamDeleteConfirmMsg('${row['code'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/repair/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 工单状态流转：status 为后端枚举值直传；文案侧展示 statusLabel（已本地化）。
  Future<void> _transition(Map<String, dynamic> row, String status, String statusLabel) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.eamTransitionTitle,
      content: l10n.eamTransitionConfirm('${row['code'] ?? ''}', statusLabel),
      onConfirm: (password) async {
      await ApiService.instance.post('/admin/v1/eam/repair/${row['id']}/transition', data: {'status': status});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields(Map<String, String> equipmentOptions) => [
    FormFieldConfig(name: 'code', label: AppL10n.current.eamRepairCode, required: true),
    FormFieldConfig(name: 'equipment_id', label: AppL10n.current.eamEquipmentId, required: true,
        type: FormFieldType.dropdown, options: equipmentOptions.keys.toList(), optionLabels: equipmentOptions),
    FormFieldConfig(name: 'fault_description', label: AppL10n.current.eamFaultDescription, type: FormFieldType.multiline, required: true),
    // 显示文案走 optionLabels（值=存储值不变），词表同本页维修类型列
    FormFieldConfig(name: 'repair_type', label: AppL10n.current.eamRepairType, type: FormFieldType.dropdown,
        options: ['preventive', 'corrective', 'emergency'],
        optionLabels: {for (final v in const ['preventive', 'corrective', 'emergency']) v: _typeLabel(v)}),
    FormFieldConfig(name: 'assignee', label: AppL10n.current.eamRepairAssignee),
    FormFieldConfig(name: 'start_date', label: AppL10n.current.eamStartDate),
    FormFieldConfig(name: 'end_date', label: AppL10n.current.eamEndDate),
    FormFieldConfig(name: 'cost', label: AppL10n.current.eamRepairCost, type: FormFieldType.number),
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
      pageTitle: AppL10n.current.eamRepairTitle,
      moduleKey: 'eam',
      primaryColumnIndex: 0,

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.eamRepairCode,
    AppL10n.current.eamEquipmentId,
    AppL10n.current.eamRepairType,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  /// 维修类型/状态列：值域即表单下拉 options 与 RepairOrderController::STATUS_TRANSITIONS
  /// （open→in_progress→completed/cancelled），机读串不上屏，未知值原样回落。
  static String _typeLabel(Object? v) => switch ('$v') {
        'preventive' => AppL10n.current.eamRepairTypePreventive,
        'corrective' => AppL10n.current.eamRepairTypeCorrective,
        'emergency' => AppL10n.current.eamRepairTypeEmergency,
        _ => '$v',
      };

  static String _statusLabel(Object? v) => switch ('$v') {
        'open' => AppL10n.current.eamRepairStatusOpen,
        'in_progress' => AppL10n.current.eamRepairStatusInProgress,
        'completed' => AppL10n.current.eamRepairStatusCompleted,
        'cancelled' => AppL10n.current.eamRepairStatusCancelled,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.eamRepairCode: r['code'] ?? '',
    AppL10n.current.eamEquipmentId: _equipments['${r['equipment_id']}'] ?? '-',
    AppL10n.current.eamRepairType: _typeLabel(r['repair_type']),
    AppL10n.current.commonStatus: _statusLabel(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      if ((r['status'] ?? 'open') == 'open')
        IconButton(icon: Icon(Icons.play_arrow, size: 18, color: AppColors.of(context).warning), tooltip: AppL10n.current.eamRepairStart,
            onPressed: () => _transition(r, 'in_progress', AppL10n.current.eamRepairStart)),
      if ((r['status'] ?? 'open') == 'in_progress')
        IconButton(icon: Icon(Icons.check, size: 18, color: AppColors.of(context).success), tooltip: AppL10n.current.eamRepairFinish,
            onPressed: () => _transition(r, 'completed', AppL10n.current.eamRepairFinish)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
}
