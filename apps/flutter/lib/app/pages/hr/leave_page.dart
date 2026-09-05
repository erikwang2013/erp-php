// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class LeavePage extends StatefulWidget {
  const LeavePage({super.key});
  @override
  State<LeavePage> createState() => _LeavePageState();
}

class _LeavePageState extends State<LeavePage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String? _statusFilter;
  String? _typeFilter;
  bool _loading = true;
  String? _error;

  // 后端 erp_hr_leave 列: employee_id/type/start_date/end_date/days/status/reason；
  // 列表带 employee 关联（员工姓名），type 枚举见 install.sql COMMENT
  // 请假类型/状态显示文案经 AppL10n 取当前语言（枚举下标与后端一致）。
  static List<String> get _typeLabels => [
    AppL10n.current.hrLeaveTypeAnnual,
    AppL10n.current.hrLeaveTypePersonal,
    AppL10n.current.hrLeaveTypeSick,
    AppL10n.current.hrLeaveTypeMarriage,
    AppL10n.current.hrLeaveTypeMaternity,
    AppL10n.current.hrLeaveTypeCompensatory,
  ];
  static List<String> get _statusLabels => [
    AppL10n.current.hrLeaveStatusPending,
    AppL10n.current.hrLeaveStatusApproved,
    AppL10n.current.hrLeaveStatusRejected,
  ];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      // 后端 leaveIndex 仅支持 employee_id/type/status 筛选，无 keyword
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      if (_typeFilter != null) params['type'] = _typeFilter!;
      final res = await ApiService.instance.get('/admin/v1/hr/leave', params: params);
      final d = res['data'];
      if (mounted) setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); });
    }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrLeaveCreateTitle, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/hr/leave', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrLeaveEditTitle, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/hr/leave/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.hrLeaveDeleteConfirm, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/hr/leave/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 请假类型/状态/天数均为后端校验必填；日期需 YYYY-MM-DD 字符串（不做日历组件）。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'employee_id', label: AppL10n.current.hrEmployeeId, required: true, hint: AppL10n.current.hrLeaveEmployeeHint),
    FormFieldConfig(name: 'type', label: AppL10n.current.hrLeaveType, type: FormFieldType.dropdown, required: true,
      options: [for (var i = 0; i < _typeLabels.length; i++) '${i + 1} - ${_typeLabels[i]}'], initialValue: '1 - ${_typeLabels[0]}'),
    FormFieldConfig(name: 'start_date', label: AppL10n.current.hrLeaveStartDate, required: true, hint: AppL10n.current.hrLeaveDateHint),
    FormFieldConfig(name: 'end_date', label: AppL10n.current.hrLeaveEndDate, required: true, hint: AppL10n.current.hrLeaveDateHint),
    FormFieldConfig(name: 'days', label: AppL10n.current.hrLeaveDays, type: FormFieldType.number, required: true, hint: AppL10n.current.hrLeaveDaysHint),
    FormFieldConfig(name: 'reason', label: AppL10n.current.hrLeaveReason, type: FormFieldType.multiline),
  ];

  /// 把表单提交值转换为后端 leaveStore()/leaveUpdate() 接收的参数（type 拆出数字；
  /// status 由后端强制，create 恒为 0，update 禁止携带）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final typeRaw = (data['type'] ?? '').split(' - ').first.trim();
    return {
      'employee_id': data['employee_id']?.trim(),
      'type': typeRaw,
      'start_date': data['start_date']?.trim(),
      'end_date': data['end_date']?.trim(),
      'days': data['days']?.trim(),
      'reason': data['reason']?.trim() ?? '',
    };
  }

  /// 编辑回填：把后端数字 type 转回下拉选项文案；非待审批不可编辑（leaveUpdate 服务端拦截）。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final t = d['type'];
    final i = t is int ? t : int.tryParse('$t') ?? 0;
    if (i >= 1 && i <= _typeLabels.length) {
      d['type'] = '$i - ${_typeLabels[i - 1]}';
    }
    return d;
  }

  /// 员工列：优先 employee 关联中的姓名，缺失时回退员工ID。
  static String _empLabel(Map<String, dynamic> r) {
    final emp = r['employee'];
    if (emp is Map<String, dynamic>) {
      final n = emp['name'];
      if (n != null && '$n'.isNotEmpty) return '$n';
    }
    return '${r['employee_id'] ?? ''}';
  }

  static String _typeText(dynamic t) {
    final i = t is int ? t : int.tryParse('$t') ?? 0;
    return (i >= 1 && i <= _typeLabels.length) ? _typeLabels[i - 1] : '$t';
  }

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  static bool _pending(Map<String, dynamic> r) {
    final s = r['status'];
    return (s is int ? s : int.tryParse('$s') ?? 0) == 0;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return DataTableWrapper(
      columns: _columns(),
      rows: _rows.map((r) => _rowToMap(r)).toList(),
      total: _total, page: _page, limit: _limit, loading: _loading,
      error: _error, onRetry: _load,
      onPageChanged: (p) { _page = p; _load(); },
      filterBar: Row(mainAxisSize: MainAxisSize.min, children: [
        DropdownButton<String>(
          value: _typeFilter,
          hint: Text(l10n.hrLeaveTypeHint),
          items: [for (var i = 0; i < _typeLabels.length; i++) DropdownMenuItem(value: '${i + 1}', child: Text(_typeLabels[i]))],
          onChanged: (v) { _typeFilter = v; _page = 1; _load(); },
        ),
        const SizedBox(width: 16),
        DropdownButton<String>(
          value: _statusFilter,
          hint: Text(l10n.commonStatus),
          items: [for (var i = 0; i < _statusLabels.length; i++) DropdownMenuItem(value: '$i', child: Text(_statusLabels[i]))],
          onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
        ),
      ]),
      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.hrEmployeeTitle,
    AppL10n.current.hrLeaveType,
    AppL10n.current.hrLeavePeriod,
    AppL10n.current.hrLeaveDaysCol,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.hrEmployeeTitle: _empLabel(r),
    AppL10n.current.hrLeaveType: _typeText(r['type']),
    AppL10n.current.hrLeavePeriod: '${r['start_date'] ?? ''} ~ ${r['end_date'] ?? ''}',
    AppL10n.current.hrLeaveDaysCol: r['days'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      if (_pending(r))
        IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green),
          tooltip: AppL10n.current.hrLeaveApprove, onPressed: () => _approve(r, approve: true)),
      if (_pending(r))
        IconButton(icon: const Icon(Icons.cancel, size: 18, color: Colors.red),
          tooltip: AppL10n.current.hrLeaveReject, onPressed: () => _approve(r, approve: false)),
      // 服务端仅允许修改待审批记录，故编辑入口同样按待审批展示
      if (_pending(r))
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  /// 请假审批/驳回：POST /admin/hr/leave/{id}/approve，二次确认。
  Future<void> _approve(Map<String, dynamic> row, {required bool approve}) async {
    final l10n = AppL10n.of(context);
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(approve ? l10n.hrLeaveApproveTitle : l10n.hrLeaveRejectTitle),
        content: Text(approve ? l10n.hrLeaveApproveConfirm : l10n.hrLeaveRejectConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(l10n.commonCancel)),
          ElevatedButton(
            style: approve
                ? ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white)
                : ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/v1/hr/leave/${row['id']}/approve',
                    data: {'action': approve ? 'approve' : 'reject'});
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text(approve ? AppL10n.current.hrLeaveStatusApproved : AppL10n.current.hrLeaveStatusRejected)));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(AppL10n.of(ctx).commonOpFailedMsg('$e'))));
                }
              }
            },
            child: Text(approve ? l10n.hrLeaveApprove : l10n.hrLeaveReject),
          ),
        ],
      ),
    );
  }

  /// 状态 chip：颜色按后端枚举下标（0 待审批/1 已批准/2 已驳回）决定，
  /// 文案经 _statusText 走当前语言，避免对翻译后文案做字符串比较。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? -1;
    final color = switch (i) {
      0 => Colors.orange,
      1 => Colors.green,
      2 => Colors.red,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(_statusText(s), style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
