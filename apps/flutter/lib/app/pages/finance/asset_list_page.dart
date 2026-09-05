// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class AssetListPage extends StatefulWidget {
  const AssetListPage({super.key});
  @override
  State<AssetListPage> createState() => _AssetListPageState();
}

class _AssetListPageState extends State<AssetListPage> {
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
      
      final res = await ApiService.instance.get('/admin/v1/finance/asset', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/asset', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/asset/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['name'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/asset/${row['id']}', data: {'password': password});
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
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  /// 计提折旧：POST /admin/finance/asset/{id}/depreciate
  Future<void> _depreciate(Map<String, dynamic> row) async {
    final now = DateTime.now();
    await FormDialog.show(context, title: AppL10n.of(context).financeAssetDepreciate,
      fields: [
        FormFieldConfig(name: 'period_year', label: AppL10n.of(context).financeAssetDepYear, type: FormFieldType.number, initialValue: '${now.year}'),
        FormFieldConfig(name: 'period_month', label: AppL10n.of(context).financeAssetDepMonth, type: FormFieldType.number, initialValue: '${now.month}'),
      ],
      submitText: AppL10n.of(context).financeAssetConfirmDepreciate,
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/finance/asset/${row['id']}/depreciate', data: {
          'period_year': int.tryParse(data['period_year'] ?? '') ?? now.year,
          'period_month': int.tryParse(data['period_month'] ?? '') ?? now.month,
        });
        _load();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppL10n.of(context).financeAssetDepreciated)));
        }
        return true;
      },
    );
  }

  List<String> _columns() => [AppL10n.current.commonName, AppL10n.current.commonCode, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.commonName: r['name'] ?? '',
    AppL10n.current.commonCode: r['code'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.trending_down, size: 18), tooltip: AppL10n.current.financeAssetDepreciate, onPressed: () => _depreciate(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

}
