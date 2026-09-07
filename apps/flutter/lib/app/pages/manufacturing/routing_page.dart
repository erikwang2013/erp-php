// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class RoutingPage extends StatefulWidget {
  const RoutingPage({super.key});
  @override
  State<RoutingPage> createState() => _RoutingPageState();
}

class _RoutingPageState extends State<RoutingPage> {
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

      final res = await ApiService.instance.get('/admin/v1/mfg/routing', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/mfg/routing', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/mfg/routing/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.manufacturingDeleteConfirmMsg('${row['name'] ?? row['code'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/mfg/routing/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端契约对齐（erp_mfg_routing）：product_id/name/seq/workstation_id NOT NULL 且 store()
  // 均 required；表无 code 列（幻键已移除）。FK 为整数（后端 required|integer），与 quality
  // 模块同款数字输入（行内 id 即原生值，下拉选项为 hashid 不可回填，故不用下拉）。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'product_id', label: AppL10n.current.fieldProductId, required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'name', label: AppL10n.current.manufacturingName, required: true),
    FormFieldConfig(name: 'seq', label: AppL10n.current.manufacturingSeq, required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'workstation_id', label: AppL10n.current.fieldWorkstationId, required: true, type: FormFieldType.number),
  ];

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return DataTableWrapper(
      columns: _columns(),
      rows: _rows.map((r) => _rowToMap(r)).toList(),
      total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
      keyword: _keyword,
      onSearch: (v) { _keyword = v; _page = 1; _load(); },
      onPageChanged: (p) { _page = p; _load(); },
      pageTitle: AppL10n.current.mfgRoutingTitle,
      moduleKey: 'mfg',
      primaryColumnIndex: 0,

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.manufacturingName,
    AppL10n.current.fieldProductId,
    AppL10n.current.manufacturingSeq,
    AppL10n.current.fieldWorkstationId,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.manufacturingName: r['name'] ?? '',
    AppL10n.current.fieldProductId: r['product_id'] ?? '',
    AppL10n.current.manufacturingSeq: r['seq'] ?? '',
    AppL10n.current.fieldWorkstationId: r['workstation_id'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

}
