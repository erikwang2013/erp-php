// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class CostProfitPage extends StatefulWidget {
  const CostProfitPage({super.key});
  @override
  State<CostProfitPage> createState() => _CostProfitPageState();
}

class _CostProfitPageState extends State<CostProfitPage> {
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
      
      final res = await ApiService.instance.get('/admin/v1/finance/cost-center', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      // 后端整树下发（CostCenterController::buildTree → encodeIds），且不带 total：
      // 原实现只读 list 顶层，子节点整批不可见。就地拍平后子节点才进列表。
      final list = _flatten(List<Map<String, dynamic>>.from(d['list'] ?? []));
      setState(() { _rows = list; _total = list.length; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 树 → 平铺行，行上带 `_depth`（名称列按它缩进）。DataTableWrapper 无层级列概念，
  /// ponytail: 用全角空格前缀代替 Web 端的折叠箭头，升级路径 = 给 wrapper 加 depth 形参。
  List<Map<String, dynamic>> _flatten(List<Map<String, dynamic>> rows, [int depth = 0]) => [
    for (final r in rows) ...[
      {...r, '_depth': depth},
      ..._flatten(List<Map<String, dynamic>>.from(r['children'] ?? []), depth + 1),
    ],
  ];

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/cost-center', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/cost-center/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['name'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/cost-center/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.of(context).commonName, required: true),
    FormFieldConfig(name: 'code', label: AppL10n.of(context).commonCode),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.financeCostProfitTitle,
    moduleKey: 'finance',
    primaryColumnIndex: 0,
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() => [AppL10n.current.commonName, AppL10n.current.commonCode, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    // 名称列按层级缩进（子节点才看得出从属；见 _flatten）
    AppL10n.current.commonName: '${'　' * ((r['_depth'] as int?) ?? 0)}${r['name'] ?? ''}',
    AppL10n.current.commonCode: r['code'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
