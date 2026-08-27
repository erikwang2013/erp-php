// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
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
  String _keyword = '';
  static const List<String> _statuses = ['待审批', '已批准', '已拒绝'];
  String? _statusFilter;
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/hr/leave', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/hr/leave', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/hr/leave/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/hr/leave/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: '名称', required: true),
    FormFieldConfig(name: 'code', label: '编码'),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    filterBar: DropdownButton<String>(
      value: _statusFilter,
      hint: const Text('状态'),
      items: [for (final s in _statuses) DropdownMenuItem(value: s, child: Text(s))],
      onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  /// 请假审批/驳回：POST /admin/hr/leave/{id}/approve，二次确认。
  Future<void> _approve(Map<String, dynamic> row, {required bool approve}) async {
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(approve ? '批准请假' : '驳回请假'),
        content: Text(approve ? '确认批准该请假申请吗？' : '确认驳回该请假申请吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('取消')),
          ElevatedButton(
            style: approve
                ? ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white)
                : ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/hr/leave/${row['id']}/approve',
                    data: {'action': approve ? 'approve' : 'reject'});
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(approve ? '已批准' : '已驳回')));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('操作失败：$e')));
                }
              }
            },
            child: Text(approve ? '批准' : '驳回'),
          ),
        ],
      ),
    );
  }

  List<String> _columns() => ['名称', '编码', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
    '状态': _chip(r['status']),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      if (_pending(r))
        IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green),
          tooltip: '批准', onPressed: () => _approve(r, approve: true)),
      if (_pending(r))
        IconButton(icon: const Icon(Icons.cancel, size: 18, color: Colors.red),
          tooltip: '驳回', onPressed: () => _approve(r, approve: false)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  static bool _pending(Map<String, dynamic> r) {
    final s = r['status'];
    return (s is int ? s : int.tryParse('$s') ?? 0) == 0;
  }

  Widget _chip(String? s) {
    final color = switch (s) {
      '待审批' || '待审核' || '待收货' || '草稿' || '部分收货' || '部分发货' => Colors.orange,
      '已批准' || '已审核' || '已收货' || '已发货' || '已报价' || '已转订单' || '已完成' => Colors.green,
      '已驳回' || '已拒绝' || '已取消' || '已失效' => Colors.red,
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
