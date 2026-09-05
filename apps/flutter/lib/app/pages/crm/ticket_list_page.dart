// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
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
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/crm/ticket', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/ticket', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/ticket/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['name'] ?? row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/ticket/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Future<void> _assign(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    final users = await _fetchUsers();
    if (!mounted) return;
    if (users.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.crmTicketNoAssignableUser)));
      return;
    }
    var selected = users.first['id'].toString();
    var submitting = false;
    await showDialog<void>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: Text(l10n.crmTicketAssignTitle),
          content: DropdownButtonFormField<String>(
            initialValue: selected,
            decoration: InputDecoration(labelText: l10n.crmTicketAssignee),
            items: [
              for (final u in users)
                DropdownMenuItem(value: u['id'].toString(), child: Text('${u['username']}')),
            ],
            onChanged: submitting ? null : (v) => setState(() => selected = v ?? ''),
          ),
          actions: [
            TextButton(onPressed: submitting ? null : () => Navigator.pop(ctx), child: Text(l10n.commonCancel)),
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
                    ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(l10n.commonOpFailedMsg('$e'))));
                  }
                }
              },
              child: submitting
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.crmTicketAssign),
            ),
          ],
        ),
      ),
    );
  }

  Future<List<Map<String, dynamic>>> _fetchUsers() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/user', params: {'page': '1', 'limit': '200'});
      final list = (res['data']?['list'] ?? []) as List? ?? [];
      return [for (final u in list) Map<String, dynamic>.from(u as Map)];
    } catch (e) {
      debugPrint('[ticket] 加载可选用户失败: $e');
      return const []; // 空列表 → 调用方已显示「暂无可选用户」
    }
  }

  Future<void> _resolve(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmTicketResolveTitle, fields: [
      FormFieldConfig(name: 'content', label: l10n.crmTicketResolveNote, hint: l10n.crmOptional, type: FormFieldType.multiline),
    ], submitText: l10n.crmTicketConfirmResolve, onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/ticket/${row['id']}/resolve', data: data);
      _load(); return true;
    });
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

  List<String> _columns() => [AppL10n.current.crmName, AppL10n.current.crmCode, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.crmName: r['name'] ?? '',
    AppL10n.current.crmCode: r['code'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.person_add, size: 18), tooltip: AppL10n.current.crmTicketAssign, onPressed: () => _assign(r)),
      IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green), tooltip: AppL10n.current.crmTicketResolve, onPressed: () => _resolve(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

}
