// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 银行账户管理页 — 覆盖 GET/POST/PUT/DELETE /admin/finance/bank-account
/// 后端字段: name / account_number / bank_name / balance / status
class BankAccountPage extends StatefulWidget {
  const BankAccountPage({super.key});
  @override
  State<BankAccountPage> createState() => _BankAccountPageState();
}

class _BankAccountPageState extends State<BankAccountPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;

  static const List<String> _statusLabels = ['禁用', '启用'];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/finance/bank-account', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: '账户名称', required: true, hint: '必填（后端校验）'),
    FormFieldConfig(name: 'account_number', label: '银行账号'),
    FormFieldConfig(name: 'bank_name', label: '开户银行'),
    FormFieldConfig(name: 'balance', label: '账户余额', type: FormFieldType.number, hint: '默认 0'),
    FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown,
      options: ['0 - 禁用', '1 - 启用'], initialValue: '1 - 启用'),
  ];

  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'name': data['name'],
      'account_number': data['account_number']?.trim() ?? '',
      'bank_name': data['bank_name']?.trim() ?? '',
      'balance': (data['balance']?.trim().isEmpty ?? true) ? '0' : data['balance']!.trim(),
      'status': statusRaw,
    };
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增银行账户', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/bank-account', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑银行账户', fields: _formFields(), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/bank-account/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除银行账户「${row['name'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/bank-account/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 编辑回填：把后端数字 status 转回下拉选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final s = d['status'];
    if (s is int && s >= 0 && s < _statusLabels.length) {
      d['status'] = '$s - ${_statusLabels[s]}';
    }
    return d;
  }

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 1;
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
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增账户')),
    ],
  );

  List<String> _columns() => ['账户名称', '银行账号', '开户银行', '余额', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '账户名称': r['name'] ?? '',
    '银行账号': r['account_number'] ?? '',
    '开户银行': r['bank_name'] ?? '',
    '余额': r['balance'] ?? '',
    '状态': _chip(_statusText(r['status'])),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(String? s) {
    final color = switch (s) {
      '启用' => Colors.green,
      '禁用' => Colors.red,
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
