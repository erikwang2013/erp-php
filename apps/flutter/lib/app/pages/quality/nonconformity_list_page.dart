// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
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

  /// 商品下拉数据源：值=行 id（hashid，符合对外契约），文案=商品名。
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
      await ApiService.instance.post('/admin/v1/quality/nonconformity', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs(row: row) || !mounted) return;
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

  // 表单下拉的值域（=后端存储值，提交原样，数据面一字未动）
  static const _sourceValues = ['iqc', 'ipqc', 'oqc'];
  static const _severityValues = ['minor', 'major', 'critical'];
  static const _dispositionValues = ['pending', 'return', 'repair', 'scrap', 'accept'];
  static const _statusValues = ['0', '1', '2'];

  /// 下拉项显示文案：值是存储值，label 走词表（form_dialog.dart:255 用 optionLabels[o] ?? o）。
  /// 本页列表列已翻的枚举，表单里不许再露机读串 —— 词表与列表列同源。
  static Map<String, String> _labelsOf(List<String> values, String Function(Object?) label) =>
      {for (final v in values) v: label(v)};

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'code', label: l10n.fieldDefectNo, required: true),
      FormFieldConfig(name: 'source_type', label: l10n.fieldSourceType, type: FormFieldType.dropdown,
        options: _sourceValues, optionLabels: _labelsOf(_sourceValues, _sourceLabel)),
      // source_id 保持文本框：多态外键（source_type 决定指向 iqc/ipqc/oqc 哪张表）无法级联下拉
      FormFieldConfig(name: 'source_id', label: l10n.fieldSourceId, type: FormFieldType.number),
      // 商品改为下拉：值=hashid，后端 decodeIdFields 解码
      FormFieldConfig(name: 'product_id', label: l10n.fieldProductId, type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products),
      FormFieldConfig(name: 'defect_type', label: l10n.fieldDefectType, required: true),
      FormFieldConfig(name: 'defect_qty', label: l10n.fieldDefectQty, type: FormFieldType.number),
      FormFieldConfig(name: 'severity', label: l10n.fieldSeverity, type: FormFieldType.dropdown,
        options: _severityValues, optionLabels: _labelsOf(_severityValues, _severityLabel)),
      FormFieldConfig(name: 'disposition', label: l10n.fieldDisposition, type: FormFieldType.dropdown,
        options: _dispositionValues, optionLabels: _labelsOf(_dispositionValues, _dispositionLabel)),
      FormFieldConfig(name: 'root_cause', label: l10n.fieldRootCause, type: FormFieldType.multiline),
      FormFieldConfig(name: 'corrective_action', label: l10n.fieldCorrectiveAction, type: FormFieldType.multiline),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
        options: _statusValues, optionLabels: _labelsOf(_statusValues, _statusLabel)),
      FormFieldConfig(name: 'reported_by', label: l10n.fieldReporter),
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
    pageTitle: AppL10n.current.qualityNonconformityTitle,
    moduleKey: 'quality',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldNo, l10n.fieldSource, l10n.fieldProductName, l10n.fieldDefectType, l10n.fieldQty, l10n.fieldSeverity, l10n.commonStatus, l10n.commonAction];
  }

  /// 来源/严重程度/状态三列值域 = install.sql 列注释
  /// （`来源类型: iqc/ipqc/oqc`、`严重程度: minor/major/critical`、`状态: 0=待处理 1=处理中 2=已关闭`），
  /// 机读串不上屏，未知值原样回落。
  static String _sourceLabel(Object? v) => switch ('$v') {
        'iqc' => AppL10n.current.qualityTypeIqc,
        'ipqc' => AppL10n.current.qualityTypeIpqc,
        'oqc' => AppL10n.current.qualityTypeOqc,
        _ => '$v',
      };

  static String _severityLabel(Object? v) => switch ('$v') {
        'minor' => AppL10n.current.qualitySeverityMinor,
        'major' => AppL10n.current.qualitySeverityMajor,
        'critical' => AppL10n.current.qualitySeverityCritical,
        _ => '$v',
      };

  /// 处置方式词表 = lead 拍板四端统一（install.sql 列注释 `处置方式: pending/return/repair/scrap/accept`），
  /// 中文不是本页自选：轻微/严重/致命 与 待处理/退货/返修/报废/让步接收 必须与两端 Web 逐字一致。
  static String _dispositionLabel(Object? v) => switch ('$v') {
        'pending' => AppL10n.current.qualityDispositionPending,
        'return' => AppL10n.current.qualityDispositionReturn,
        'repair' => AppL10n.current.qualityDispositionRepair,
        'scrap' => AppL10n.current.qualityDispositionScrap,
        'accept' => AppL10n.current.qualityDispositionAccept,
        _ => '$v',
      };

  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.qualityNcStatusPending,
        '1' => AppL10n.current.qualityNcStatusProcessing,
        '2' => AppL10n.current.qualityNcStatusClosed,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldNo: r['code'] ?? '',
      l10n.fieldSource: _sourceLabel(r['source_type']),
      // 列内容就是商品名（后端 leftJoin 带出），标题随内容用「商品名称」而非「商品ID」；
      // 关联行缺失/未关联（FK=0）落「-」——裸 hashid 不上屏
      l10n.fieldProductName: r['product_name'] ?? '-',
      l10n.fieldDefectType: r['defect_type'] ?? '',
      l10n.fieldQty: r['defect_qty'] ?? '',
      l10n.fieldSeverity: _severityLabel(r['severity']),
      l10n.commonStatus: _statusLabel(r['status']),
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }
}
