// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class SparePartPage extends StatefulWidget {
  const SparePartPage({super.key});
  @override
  State<SparePartPage> createState() => _SparePartPageState();
}

class _SparePartPageState extends State<SparePartPage> {
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

      final res = await ApiService.instance.get('/admin/v1/eam/spare-part', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/eam/spare-part', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/eam/spare-part/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.eamDeleteConfirmMsg('${row['name'] ?? row['code'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/eam/spare-part/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.current.eamSpareCode, required: true),
    FormFieldConfig(name: 'name', label: AppL10n.current.eamSpareName, required: true),
    FormFieldConfig(name: 'equipment_id', label: AppL10n.current.eamEquipmentId, type: FormFieldType.number),
    FormFieldConfig(name: 'spec', label: AppL10n.current.eamSpareSpec),
    FormFieldConfig(name: 'unit', label: AppL10n.current.eamUnit),
    FormFieldConfig(name: 'stock_qty', label: AppL10n.current.eamStockQty, type: FormFieldType.number),
    FormFieldConfig(name: 'min_stock', label: AppL10n.current.eamMinStock, type: FormFieldType.number),
    FormFieldConfig(name: 'location', label: AppL10n.current.eamLocation),
    FormFieldConfig(name: 'status', label: AppL10n.current.commonStatus, type: FormFieldType.dropdown, options: ['0', '1']),
  ];

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return DataTableWrapper(
      columns: _columns(),
      rows: _rows.map((r) => _rowToMap(r)).toList(),
      total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
      keyword: _keyword,
      onSearch: (v) { _keyword = v; _page = 1; _load(); },
      onPageChanged: (p) { _page = p; _load(); },

      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.eamSpareCode,
    AppL10n.current.eamSpareName,
    AppL10n.current.eamSpareSpecCol,
    AppL10n.current.eamStockCol,
    AppL10n.current.eamMinStock,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.eamSpareCode: r['code'] ?? '',
    AppL10n.current.eamSpareName: r['name'] ?? '',
    AppL10n.current.eamSpareSpecCol: r['spec'] ?? '',
    AppL10n.current.eamStockCol: r['stock_qty'] ?? '',
    AppL10n.current.eamMinStock: r['min_stock'] ?? '',
    AppL10n.current.commonStatus: r['status'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
