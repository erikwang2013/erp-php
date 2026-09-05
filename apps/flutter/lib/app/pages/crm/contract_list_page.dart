// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 合同管理页 — 覆盖 GET/POST/PUT/DELETE /admin/crm/contract
/// 及 POST /admin/crm/contract/{id}/transition（合同状态流转）
class ContractListPage extends StatefulWidget {
  const ContractListPage({super.key});
  @override
  State<ContractListPage> createState() => _ContractListPageState();
}

class _ContractListPageState extends State<ContractListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  // 合同状态: 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止
  List<String> get _statusLabels => [
    AppL10n.current.crmContractStatusDraft,
    AppL10n.current.crmContractStatusPending,
    AppL10n.current.crmContractStatusApproved,
    AppL10n.current.crmContractStatusActive,
    AppL10n.current.crmContractStatusDone,
    AppL10n.current.crmContractStatusTerminated,
  ];

  /// 允许的状态流转表（与后端 ContractController::transition 一致）。
  static const Map<int, List<int>> _allowedTransitions = {
    0: [1],
    1: [2, 0],
    2: [3],
    3: [4, 5],
    4: [],
    5: [],
  };

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/crm/contract', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/contract', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/contract/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['name'] ?? row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/contract/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 合同状态流转：弹出目标状态选择并调用 POST /admin/crm/contract/{id}/transition。
  Future<void> _transition(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    final current = row['status'] is int ? row['status'] as int : int.tryParse('${row['status']}') ?? 0;
    final targets = _allowedTransitions[current] ?? [];
    if (targets.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.crmContractNoTarget)),
      );
      return;
    }
    String? selected;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.crmContractTransitionTitle),
        content: SizedBox(
          width: 320,
          child: StatefulBuilder(builder: (ctx2, setLocal) {
            return DropdownButtonFormField<String>(
              initialValue: selected,
              decoration: InputDecoration(labelText: l10n.crmContractTargetStatus, isDense: true),
              items: [
                for (final t in targets)
                  DropdownMenuItem(value: '$t', child: Text(_statusLabels[t])),
              ],
              onChanged: (v) => setLocal(() => selected = v),
            );
          }),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(l10n.commonCancel)),
          ElevatedButton(
            onPressed: () async {
              final toStatus = selected;
              if (toStatus == null) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(l10n.crmContractSelectTarget)));
                }
                return;
              }
              try {
                await ApiService.instance.post('/admin/v1/crm/contract/${row['id']}/transition', data: {
                  'to_status': toStatus,
                });
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.crmContractTransitionOk)));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(l10n.commonOpFailedMsg('$e'))));
                }
              }
            },
            child: Text(l10n.crmContractTransition),
          ),
        ],
      ),
    );
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.current.crmName, required: true),
    FormFieldConfig(name: 'code', label: AppL10n.current.crmCode),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.crmName, AppL10n.current.crmCode, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.crmName: r['name'] ?? '',
    AppL10n.current.crmCode: r['code'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: Icon(Icons.compare_arrows, size: 18, color: AppColors.of(context).primary),
        tooltip: AppL10n.current.crmContractTransitionTooltip, onPressed: () => _transition(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0草稿/1待审批=待办(warning)，2已审批/4已完成=终态(success)，
  /// 3执行中=进行中(primary)，5已终止=失败(danger)。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final c = AppColors.of(context);
    final (bg, fg) = switch (i) {
      0 || 1 => (c.warningBg, c.warningText),
      2 || 4 => (c.successBg, c.successText),
      3 => (c.primaryBg, c.primaryPressed),
      5 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text, style: TextStyle(color: fg, fontSize: 12)),
    );
  }
}
