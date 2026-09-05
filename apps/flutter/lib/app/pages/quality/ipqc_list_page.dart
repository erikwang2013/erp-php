// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class IpqcListPage extends StatefulWidget {
  const IpqcListPage({super.key});
  @override
  State<IpqcListPage> createState() => _IpqcListPageState();
}

class _IpqcListPageState extends State<IpqcListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/quality/ipqc', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/quality/ipqc', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/quality/ipqc/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/quality/ipqc/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'code', label: l10n.fieldInspectNo, required: true),
      FormFieldConfig(name: 'production_order_id', label: l10n.fieldWorkOrderId, type: FormFieldType.number),
      FormFieldConfig(name: 'product_id', label: l10n.fieldProductId, type: FormFieldType.number),
      FormFieldConfig(name: 'workstation_id', label: l10n.fieldWorkstationId, type: FormFieldType.number),
      FormFieldConfig(name: 'standard_id', label: l10n.fieldInspectionStdId, type: FormFieldType.number),
      FormFieldConfig(name: 'inspected_qty', label: l10n.fieldInspectedQty, type: FormFieldType.number),
      FormFieldConfig(name: 'passed_qty', label: l10n.fieldPassedQty, type: FormFieldType.number),
      FormFieldConfig(name: 'rejected_qty', label: l10n.fieldRejectedQty, type: FormFieldType.number),
      // 检验结果 options 为后端存储值（pass/reject），不参与翻译
      FormFieldConfig(name: 'result', label: l10n.fieldInspectResult, type: FormFieldType.dropdown, options: ['pass', 'reject']),
      FormFieldConfig(name: 'inspector', label: l10n.fieldInspector),
      FormFieldConfig(name: 'remark', label: l10n.fieldRemark),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown, options: ['0', '1']),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldInspectNo, l10n.fieldWorkOrderIdShort, l10n.fieldProductId, l10n.qualityQtySummary, l10n.fieldResult, l10n.fieldInspector, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldInspectNo: r['code'] ?? '',
      l10n.fieldWorkOrderIdShort: r['production_order_id'] ?? '',
      l10n.fieldProductId: r['product_id'] ?? '',
      l10n.qualityQtySummary: '${r['inspected_qty'] ?? 0}/${r['passed_qty'] ?? 0}/${r['rejected_qty'] ?? 0}',
      l10n.fieldResult: r['result'] ?? '', // result 为后端值（pass/reject），原样展示不翻译
      l10n.fieldInspector: r['inspector'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
      ]),
    };
  }
}
