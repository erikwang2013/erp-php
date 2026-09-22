// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class StandardListPage extends StatefulWidget {
  const StandardListPage({super.key});
  @override
  State<StandardListPage> createState() => _StandardListPageState();
}

class _StandardListPageState extends State<StandardListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/quality/standard', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 适用商品下拉数据源：值=行 id（hashid，符合对外契约），文案=商品名。
  Map<String, String> _products = {};

  Future<Map<String, String>> _refOptions(String path, String nameKey) async {
    final res = await ApiService.instance.get(path, params: {'limit': '500'});
    final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
    return {
      for (final r in list) '${r['id']}': '${r[nameKey] ?? r['code'] ?? r['id']}',
    };
  }

  /// 编辑态把当前值前置进选项：预取只取前 500 行，关联行在 500 之外时
  /// FormDialog 会把不在 options 的预填值置 null（form_dialog.dart:60-76），
  /// 可空外键会被静默清空。0=未关联不补（该值本就不该出现在下拉里）。
  Map<String, String> _primed(Map<String, String> m, Map<String, dynamic> row, String idKey, String nameKey) {
    final id = '${row[idKey] ?? ''}';
    if (id.isEmpty || id == '0' || m.containsKey(id)) return m;
    return {id: '${row[nameKey] ?? id}', ...m};
  }

  /// 预取商品；失败返回 false（提示后不弹表单）——空下拉会让用户以为无处可选，
  /// 口径同 wms/pack_page.dart::_ensureRefs。
  Future<bool> _ensureRefs({Map<String, dynamic>? row}) async {
    try {
      _products = await _refOptions('/admin/v1/product', 'name');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
    if (row != null) {
      _products = _primed(_products, row, 'product_id', 'product_name');
    }
    return true;
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/quality/standard', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs(row: row) || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/quality/standard/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['name'] ?? row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/quality/standard/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.fieldStdName, required: true),
      FormFieldConfig(name: 'code', label: l10n.fieldStdCode),
      // 适用商品改为下拉：值=hashid，后端 decodeIdFields 解码（标准列表不展示该列，仅表单用）
      FormFieldConfig(name: 'product_id', label: l10n.fieldProductId, type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products),
      // 检验类型：值=后端存储值（iqc/ipqc/oqc）原样提交，显示文案走 optionLabels，词表同本页类型列
      FormFieldConfig(name: 'type', label: l10n.fieldInspectType, type: FormFieldType.dropdown,
          options: ['iqc', 'ipqc', 'oqc'], optionLabels: {for (final v in const ['iqc', 'ipqc', 'oqc']) v: _typeLabel(v)}),
      FormFieldConfig(name: 'specification', label: l10n.fieldInspectSpec, type: FormFieldType.multiline),
      FormFieldConfig(name: 'sampling_plan', label: l10n.fieldSamplingPlan),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
          options: ['0', '1'], optionLabels: {for (final v in const ['0', '1']) v: _statusLabel(v)}),
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
    pageTitle: AppL10n.current.qualityStandardTitle,
    moduleKey: 'quality',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldStdName, l10n.fieldCode, l10n.fieldType, l10n.commonStatus, l10n.commonAction];
  }

  /// 检验类型/状态列值域 = install.sql 列注释
  /// （`检验类型: iqc/ipqc/oqc`、`状态: 0=禁用 1=启用`），机读串不上屏，未知值原样回落。
  static String _typeLabel(Object? v) => switch ('$v') {
        'iqc' => AppL10n.current.qualityTypeIqc,
        'ipqc' => AppL10n.current.qualityTypeIpqc,
        'oqc' => AppL10n.current.qualityTypeOqc,
        _ => '$v',
      };

  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.commonDisabled,
        '1' => AppL10n.current.commonEnabled,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldStdName: r['name'] ?? '',
      l10n.fieldCode: r['code'] ?? '',
      l10n.fieldType: _typeLabel(r['type']),
      l10n.commonStatus: _statusLabel(r['status']),
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }
}
