// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class TicketListPage extends StatefulWidget {
  const TicketListPage({super.key});
  @override
  State<TicketListPage> createState() => _TicketListPageState();
}

class _TicketListPageState extends State<TicketListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      
      final res = await ApiService.instance.get('/admin/v1/crm/ticket', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/ticket', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/ticket/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/ticket/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Future<void> _assign(Map<String, dynamic> row) async {
    final users = await _fetchUsers();
    if (!mounted) return;
    if (users.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('暂无可选用户')));
      return;
    }
    var selected = users.first['id'].toString();
    var submitting = false;
    await showDialog<void>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: const Text('指派工单'),
          content: DropdownButtonFormField<String>(
            initialValue: selected,
            decoration: const InputDecoration(labelText: '指派人'),
            items: [
              for (final u in users)
                DropdownMenuItem(value: u['id'].toString(), child: Text('${u['username']}')),
            ],
            onChanged: submitting ? null : (v) => setState(() => selected = v ?? ''),
          ),
          actions: [
            TextButton(onPressed: submitting ? null : () => Navigator.pop(ctx), child: const Text('取消')),
            ElevatedButton(
              onPressed: submitting ? null : () async {
                setState(() => submitting = true);
                try {
                  await ApiService.instance.post('/admin/v1/crm/ticket/${row['id']}/assign',
                      data: {'assignee_user_id': int.parse(selected)});
                  if (ctx.mounted) Navigator.pop(ctx);
                  _load();
                } catch (e) {
                  if (ctx.mounted) {
                    setState(() => submitting = false);
                    ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('指派失败：$e')));
                  }
                }
              },
              child: submitting
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('指派'),
            ),
          ],
        ),
      ),
    );
  }

  Future<List<Map<String, dynamic>>> _fetchUsers() async {
    final res = await ApiService.instance.get('/admin/v1/user', params: {'page': '1', 'limit': '200'});
    return List<Map<String, dynamic>>.from((res['data']['list'] ?? []) as List);
  }

  Future<void> _resolve(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '解决工单', fields: const [
      FormFieldConfig(name: 'content', label: '解决说明', hint: '选填', type: FormFieldType.multiline),
    ], submitText: '确认解决', onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/ticket/${row['id']}/resolve', data: data);
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
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['名称', '编码', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '名称': r['name'] ?? '',
    '编码': r['code'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.person_add, size: 18), tooltip: '指派', onPressed: () => _assign(r)),
      IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green), tooltip: '解决', onPressed: () => _resolve(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

}
