// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class NonconformityListPage extends StatefulWidget {
  const NonconformityListPage({super.key});
  @override
  State<NonconformityListPage> createState() => _NonconformityListPageState();
}

class _NonconformityListPageState extends State<NonconformityListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/quality/nonconformity', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/quality/nonconformity', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/quality/nonconformity/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/quality/nonconformity/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'code', label: l10n.fieldDefectNo, required: true),
      // 来源/严重程度/处置 options 为后端存储值（英文枚举），不参与翻译
      FormFieldConfig(name: 'source_type', label: l10n.fieldSourceType, type: FormFieldType.dropdown, options: ['iqc', 'ipqc', 'oqc']),
      FormFieldConfig(name: 'source_id', label: l10n.fieldSourceId, type: FormFieldType.number),
      FormFieldConfig(name: 'product_id', label: l10n.fieldProductId, type: FormFieldType.number),
      FormFieldConfig(name: 'defect_type', label: l10n.fieldDefectType, required: true),
      FormFieldConfig(name: 'defect_qty', label: l10n.fieldDefectQty, type: FormFieldType.number),
      FormFieldConfig(name: 'severity', label: l10n.fieldSeverity, type: FormFieldType.dropdown, options: ['minor', 'major', 'critical']),
      FormFieldConfig(name: 'disposition', label: l10n.fieldDisposition, type: FormFieldType.dropdown, options: ['pending', 'return', 'repair', 'scrap', 'accept']),
      FormFieldConfig(name: 'root_cause', label: l10n.fieldRootCause, type: FormFieldType.multiline),
      FormFieldConfig(name: 'corrective_action', label: l10n.fieldCorrectiveAction, type: FormFieldType.multiline),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown, options: ['0', '1', '2']),
      FormFieldConfig(name: 'reported_by', label: l10n.fieldReporter),
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
    return [l10n.fieldNo, l10n.fieldSource, l10n.fieldProductId, l10n.fieldDefectType, l10n.fieldQty, l10n.fieldSeverity, l10n.commonStatus, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldNo: r['code'] ?? '',
      l10n.fieldSource: r['source_type'] ?? '', // source_type 为后端值（iqc/ipqc/oqc），原样展示不翻译
      l10n.fieldProductId: r['product_id'] ?? '',
      l10n.fieldDefectType: r['defect_type'] ?? '',
      l10n.fieldQty: r['defect_qty'] ?? '',
      l10n.fieldSeverity: r['severity'] ?? '', // severity 为后端值（minor/major/critical），原样展示不翻译
      l10n.commonStatus: r['status'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
      ]),
    };
  }
}
