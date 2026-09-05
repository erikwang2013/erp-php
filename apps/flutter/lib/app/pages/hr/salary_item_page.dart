// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 薪资项配置 — /admin/hr/salary-item（itemIndex/itemStore/itemUpdate/itemDestroy）
class SalaryItemPage extends StatefulWidget {
  const SalaryItemPage({super.key});
  @override
  State<SalaryItemPage> createState() => _SalaryItemPageState();
}

class _SalaryItemPageState extends State<SalaryItemPage> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/hr/salary-item');
      if (mounted) setState(() { _rows = List<Map<String, dynamic>>.from(res['data']['list'] ?? []); _loading = false; _error = null; });
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrSalaryItemCreateTitle, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/hr/salary-item', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrSalaryItemEditTitle, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/hr/salary-item/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
      content: l10n.hrDeleteConfirmMsg('${row['name'] ?? '${row['id']}'}'),
      onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/hr/salary-item/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.current.hrCode, required: true),
    FormFieldConfig(name: 'name', label: AppL10n.current.hrName, required: true),
    FormFieldConfig(name: 'type', label: AppL10n.current.hrSalaryItemType),
    FormFieldConfig(name: 'is_taxable', label: AppL10n.current.hrSalaryItemTaxable),
    FormFieldConfig(name: 'default_amount', label: AppL10n.current.hrSalaryItemDefault),
  ];

  /// 后端 type: 0=固定 1=浮动；这里只做文案映射，枚举值本身直传列表。
  static String _typeText(dynamic t) => '${t ?? ''}' == '1'
      ? AppL10n.current.hrSalaryItemTypeFloat
      : AppL10n.current.hrSalaryItemTypeFixed;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return DataTableWrapper(
      columns: _columns(),
      rows: _rows.map((r) => _rowToMap(r)).toList(),
      total: _rows.length, page: 1, limit: _rows.length, loading: _loading, error: _error, onRetry: _load,
      actions: [
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.hrSalaryItemCreateTitle)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.hrCode,
    AppL10n.current.hrName,
    AppL10n.current.hrSalaryItemTypeShort,
    AppL10n.current.hrSalaryItemTaxShort,
    AppL10n.current.hrSalaryItemDefault,
    AppL10n.current.commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.hrCode: r['code'] ?? '',
    AppL10n.current.hrName: r['name'] ?? '',
    AppL10n.current.hrSalaryItemTypeShort: _typeText(r['type']),
    AppL10n.current.hrSalaryItemTaxShort: '${r['is_taxable'] ?? ''}' == '1'
        ? AppL10n.current.hrYes : AppL10n.current.hrNo,
    AppL10n.current.hrSalaryItemDefault: r['default_amount'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
