// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

/// 库位页 — 覆盖 GET/POST/PUT/DELETE /admin/v1/location
/// 契约（erp_location）：warehouse_id/code/name/status。
/// 旧表单以仓库名（幻列 warehouse）提交 → 引用永不落库；现改为
/// warehouse_id 下拉（/admin/v1/warehouse，值=hashid，后端双模解码）。
class LocationListPage extends StatefulWidget {
  const LocationListPage({super.key});
  @override
  State<LocationListPage> createState() => _LocationListPageState();
}

class _LocationListPageState extends State<LocationListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 仓库选项：id(hashid)→名称，取自 /admin/v1/warehouse（懒加载）。
  Map<String, String> _warehouseLabels = {};

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/location', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载仓库下拉（warehouse_id 必填；行内 warehouse_id 为 hashid，与下拉值同形可回填）。
  Future<bool> _ensureWarehouses() async {
    if (_warehouseLabels.isNotEmpty) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _warehouseLabels = { for (final r in list) '${r['id']}': '${r['name'] ?? r['code'] ?? ''}' };
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    if (!await _ensureWarehouses() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/location', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureWarehouses() || !mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/location/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent(row['name'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/location/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 仅提交真实表列：warehouse_id 必填下拉（hashid），code/name 文本，空值后端落 ''。
  Map<String, dynamic> _buildPayload(Map<String, String> data) => {
    'warehouse_id': data['warehouse_id']?.trim() ?? '',
    'code': data['code']?.trim() ?? '',
    'name': data['name']?.trim() ?? '',
  };

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.fieldName, required: true),
      FormFieldConfig(name: 'code', label: l10n.fieldCode),
      FormFieldConfig(
        name: 'warehouse_id',
        label: l10n.fieldWarehouse,
        required: true,
        type: FormFieldType.dropdown,
        options: _warehouseLabels.keys.toList(),
        optionLabels: _warehouseLabels,
      ),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.partnerLocationTitle,
    moduleKey: 'partner',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldName, l10n.fieldCode, l10n.fieldWarehouse, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldName: r['name'] ?? '',
      l10n.fieldCode: r['code'] ?? '',
      // 仓库列：后端已按 warehouse_id 补名称；旧幻列 warehouse 恒空
      l10n.fieldWarehouse: '${r['warehouse_name'] ?? r['warehouse_id'] ?? ''}',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

}
