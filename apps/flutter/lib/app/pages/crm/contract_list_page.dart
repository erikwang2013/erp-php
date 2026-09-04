// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
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

  // 合同状态: 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止
  static const List<String> _statusLabels = ['草稿', '待审批', '已审批', '执行中', '已完成', '已终止'];

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
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/crm/contract', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/contract', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/contract/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/contract/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 合同状态流转：弹出目标状态选择并调用 POST /admin/crm/contract/{id}/transition。
  Future<void> _transition(Map<String, dynamic> row) async {
    final current = row['status'] is int ? row['status'] as int : int.tryParse('${row['status']}') ?? 0;
    final targets = _allowedTransitions[current] ?? [];
    if (targets.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('当前状态无可流转的目标状态')),
      );
      return;
    }
    String? selected;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('合同状态流转'),
        content: SizedBox(
          width: 320,
          child: StatefulBuilder(builder: (ctx2, setLocal) {
            return DropdownButtonFormField<String>(
              initialValue: selected,
              decoration: const InputDecoration(labelText: '目标状态', isDense: true),
              items: [
                for (final t in targets)
                  DropdownMenuItem(value: '$t', child: Text(_statusLabels[t])),
              ],
              onChanged: (v) => setLocal(() => selected = v),
            );
          }),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('取消')),
          ElevatedButton(
            onPressed: () async {
              final toStatus = selected;
              if (toStatus == null) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('请选择目标状态')));
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
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('状态流转成功')));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('流转失败：$e')));
                }
              }
            },
            child: const Text('流转'),
          ),
        ],
      ),
    );
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: '名称', required: true),
    FormFieldConfig(name: 'code', label: '编码'),
  ];

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['名称', '编码', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
    '状态': _chip(_statusText(r['status'])),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.compare_arrows, size: 18, color: Colors.teal),
        tooltip: '状态流转', onPressed: () => _transition(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(String? s) {
    final color = switch (s) {
      '草稿' || '待审批' => Colors.orange,
      '已审批' || '执行中' || '已完成' => Colors.green,
      '已终止' => Colors.red,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(s ?? '', style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
