// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
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

  /// 客户选项（id→名称，id 为客户列表行 hashid），打开新增/编辑弹窗前懒加载一次。
  Map<String, String> _customerOptions = {};
  bool _customersLoaded = false;

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

  /// 加载客户下拉选项（customer_id 必填且为 hashid，取自 /admin/v1/customer 列表行 id）。
  Future<bool> _ensureCustomers() async {
    if (_customersLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/customer', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _customerOptions = {
        for (final c in list) '${c['id']}': '${c['name'] ?? c['code'] ?? ''}',
      };
      _customersLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/ticket', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/ticket/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['title'] ?? row['code'] ?? row['id']}'),
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
                DropdownMenuItem(value: u['id'].toString(), child: Text('${u['real_name'] ?? u['username'] ?? ''}')),
            ],
            onChanged: submitting ? null : (v) => setState(() => selected = v ?? ''),
          ),
          actions: [
            TextButton(onPressed: submitting ? null : () => Navigator.pop(ctx), child: Text(l10n.commonCancel)),
            ElevatedButton(
              onPressed: submitting ? null : () async {
                setState(() => submitting = true);
                try {
                  // 用户列表行 id 为 hashid 原串，后端 assign 做双模解码，勿 int.parse
                  await ApiService.instance.post('/admin/v1/crm/ticket/${row['id']}/assign',
                      data: {'assignee_user_id': selected});
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

  // 与后端 TicketController::store 契约对齐（表无 name 列）：
  // title/customer_id 必填；code 真列但留空后端自动生成（uk_code 唯一）
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'title', label: l10n.fieldTitle, required: true),
      FormFieldConfig(
        name: 'customer_id',
        label: l10n.fieldCustomer,
        required: true,
        type: FormFieldType.dropdown,
        options: _customerOptions.keys.toList(),
        optionLabels: _customerOptions,
      ),
      FormFieldConfig(name: 'code', label: l10n.crmCode),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.crmTicketTitle,
    moduleKey: 'crm',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.fieldTitle, AppL10n.current.fieldCustomer, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.fieldTitle: r['title'] ?? '',
    AppL10n.current.fieldCustomer: r['customer_name'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.person_add, size: 18), tooltip: AppL10n.current.crmTicketAssign, onPressed: () => _assign(r)),
      IconButton(icon: Icon(Icons.check_circle, size: 18, color: AppColors.of(context).success), tooltip: AppL10n.current.crmTicketResolve, onPressed: () => _resolve(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
