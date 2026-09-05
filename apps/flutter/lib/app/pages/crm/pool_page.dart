// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';

class PoolPage extends StatefulWidget {
  const PoolPage({super.key});
  @override
  State<PoolPage> createState() => _PoolPageState();
}

class _PoolPageState extends State<PoolPage> {
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

      final res = await ApiService.instance.get('/admin/v1/crm/pool', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _claim(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmPoolClaimTitle, fields: [
      FormFieldConfig(name: 'remark', label: l10n.crmRemark, hint: l10n.crmOptional),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/pool/claim/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _release(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.crmPoolRelease, fields: [
      FormFieldConfig(name: 'remark', label: l10n.crmRemark, hint: l10n.crmOptional),
    ], onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/crm/pool/release/${row['id']}', data: data);
      _load(); return true;
    });
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
  );

  List<String> _columns() => [AppL10n.current.crmName, AppL10n.current.crmCode, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.crmName: r['name'] ?? '',
    AppL10n.current.crmCode: r['code'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.person_add, size: 18), tooltip: AppL10n.current.crmPoolClaim, onPressed: () => _claim(r)),
      IconButton(icon: Icon(Icons.logout, size: 18, color: AppColors.of(context).warning), tooltip: AppL10n.current.crmPoolRelease, onPressed: () => _release(r)),
    ]),
  };

}
