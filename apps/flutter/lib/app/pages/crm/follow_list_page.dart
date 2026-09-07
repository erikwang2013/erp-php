// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 跟进记录 — GET/POST/PUT/DELETE /admin/crm/follow
class FollowListPage extends StatefulWidget {
  const FollowListPage({super.key});
  @override
  State<FollowListPage> createState() => _FollowListPageState();
}

class _FollowListPageState extends State<FollowListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
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
      // 后端索引无关键词/状态搜索（表无 name/code/status 列），仅分页拉取
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/v1/crm/follow', params: params);
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
    await FormDialog.show(context, title: l10n.crmFollowAddTitle, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/follow', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureCustomers() || !mounted) return;
    await FormDialog.show(context, title: l10n.crmFollowEditTitle, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/crm/follow/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  /// 组装后端 store()/update() 接收的参数（仅真实表列；幻键 name/code 不再发送；
  /// follow_user_id 由后端默认当前登录管理员，编辑时不回传以免改挂他人）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) => {
    'customer_id': data['customer_id']?.trim() ?? '',
    'method': data['method']?.trim() ?? '',
    'content': data['content']?.trim() ?? '',
  };

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.crmDeleteConfirmMsg('${row['customer_name'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/crm/follow/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端契约对齐（erp_crm_follow_record）：customer_id/follow_user_id NOT NULL（跟进人由后端
  // 默认当前管理员）；表无 name/code/status 列（幻键已移除）。method 为后端存储值，原样提交。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(
      name: 'customer_id',
      label: AppL10n.current.fieldCustomer,
      required: true,
      type: FormFieldType.dropdown,
      options: _customerOptions.keys.toList(),
      optionLabels: _customerOptions,
    ),
    FormFieldConfig(name: 'method', label: AppL10n.current.fieldMethod, type: FormFieldType.dropdown,
      options: const ['phone', 'visit', 'email', 'message', 'other']),
    FormFieldConfig(name: 'content', label: AppL10n.current.crmFollowContent, type: FormFieldType.multiline),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.crmFollowTitle,
    moduleKey: 'crm',
    primaryColumnIndex: 0,
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).crmFollowAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.fieldCustomer, AppL10n.current.fieldMethod, AppL10n.current.crmFollowContent, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.fieldCustomer: r['customer_name'] ?? r['customer_id'] ?? '',
    AppL10n.current.fieldMethod: r['method'] ?? '', // method 为后端存储值（phone/visit/email/message/other），原样展示不翻译
    AppL10n.current.crmFollowContent: r['content'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
}
