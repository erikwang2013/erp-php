// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
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

  @override
  void initState() { super.initState(); _load(); }

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
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/repair', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
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

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.current.eamRepairCode, required: true),
    FormFieldConfig(name: 'equipment_id', label: AppL10n.current.eamEquipmentId, type: FormFieldType.number, required: true),
    FormFieldConfig(name: 'fault_description', label: AppL10n.current.eamFaultDescription, type: FormFieldType.multiline, required: true),
    FormFieldConfig(name: 'repair_type', label: AppL10n.current.eamRepairType, type: FormFieldType.dropdown, options: ['preventive', 'corrective', 'emergency']),
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
      total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
      keyword: _keyword,
      onSearch: (v) { _keyword = v; _page = 1; _load(); },
      onPageChanged: (p) { _page = p; _load(); },

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

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.eamRepairCode: r['code'] ?? '',
    AppL10n.current.eamEquipmentId: r['equipment_id'] ?? '',
    AppL10n.current.eamRepairType: r['repair_type'] ?? '',
    AppL10n.current.commonStatus: r['status'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      if ((r['status'] ?? 'open') == 'open')
        IconButton(icon: const Icon(Icons.play_arrow, size: 18, color: Colors.orange), tooltip: AppL10n.current.eamRepairStart,
            onPressed: () => _transition(r, 'in_progress', AppL10n.current.eamRepairStart)),
      if ((r['status'] ?? 'open') == 'in_progress')
        IconButton(icon: const Icon(Icons.check, size: 18, color: Colors.green), tooltip: AppL10n.current.eamRepairFinish,
            onPressed: () => _transition(r, 'completed', AppL10n.current.eamRepairFinish)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
