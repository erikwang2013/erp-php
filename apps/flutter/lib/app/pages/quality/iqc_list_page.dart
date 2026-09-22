// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class IqcListPage extends StatefulWidget {
  const IqcListPage({super.key});
  @override
  State<IqcListPage> createState() => _IqcListPageState();
}

class _IqcListPageState extends State<IqcListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/quality/iqc', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 外键下拉数据源：值=行 id（hashid，符合对外契约），文案=可读名。
  /// 路由口径：收货单是 /admin/v1/purchase/receive（erp_purchase_receive，非 wms/receiving）。
  Map<String, String> _receives = {};
  Map<String, String> _products = {};
  Map<String, String> _standards = {};

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

  /// 预取三张关联表；失败返回 false（提示后不弹表单）——空下拉会让用户以为无处可选，
  /// 口径同 wms/pack_page.dart::_ensureRefs。
  Future<bool> _ensureRefs({Map<String, dynamic>? row}) async {
    try {
      _receives = await _refOptions('/admin/v1/purchase/receive', 'code');
      _products = await _refOptions('/admin/v1/product', 'name');
      _standards = await _refOptions('/admin/v1/quality/standard', 'name');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
    if (row != null) {
      _receives = _primed(_receives, row, 'receiving_id', 'receiving_code');
      _products = _primed(_products, row, 'product_id', 'product_name');
      _standards = _primed(_standards, row, 'standard_id', 'standard_name');
    }
    return true;
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs() || !mounted) return;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/quality/iqc', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs(row: row) || !mounted) return;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/quality/iqc/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['code'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/quality/iqc/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'code', label: l10n.fieldInspectNo, required: true),
      // 外键改为下拉（原先手输数字，等于逼用户抄 hashid）：值=hashid，后端 decodeIdFields 解码
      FormFieldConfig(name: 'receiving_id', label: l10n.fieldReceivingId, type: FormFieldType.dropdown, options: _receives.keys.toList(), optionLabels: _receives),
      FormFieldConfig(name: 'product_id', label: l10n.fieldProductId, type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products),
      FormFieldConfig(name: 'standard_id', label: l10n.fieldInspectionStdId, type: FormFieldType.dropdown, options: _standards.keys.toList(), optionLabels: _standards),
      FormFieldConfig(name: 'inspected_qty', label: l10n.fieldInspectedQty, type: FormFieldType.number),
      FormFieldConfig(name: 'passed_qty', label: l10n.fieldPassedQty, type: FormFieldType.number),
      FormFieldConfig(name: 'rejected_qty', label: l10n.fieldRejectedQty, type: FormFieldType.number),
      // 检验结果 options 为后端存储值（pass/reject），不参与翻译
      // 显示文案走 optionLabels（提交值仍是 pass/reject），词表同本页结果列：合格/不合格
      FormFieldConfig(name: 'result', label: l10n.fieldInspectResult, type: FormFieldType.dropdown,
          options: ['pass', 'reject'], optionLabels: {for (final v in const ['pass', 'reject']) v: _resultLabel(v)}),
      FormFieldConfig(name: 'inspector', label: l10n.fieldInspector),
      FormFieldConfig(name: 'remark', label: l10n.fieldRemark),
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
    pageTitle: AppL10n.current.qualityIqcTitle,
    moduleKey: 'quality',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldInspectNo, l10n.fieldProductName, l10n.qualityQtySummary, l10n.fieldResult, l10n.fieldInspector, l10n.commonAction];
  }

  /// 结果列值域 = install.sql 列注释 `检验结果: pass=合格 reject=不合格`，机读串不上屏。
  static String _resultLabel(Object? v) => switch ('$v') {
        'pass' => AppL10n.current.qualityResultPass,
        'reject' => AppL10n.current.qualityResultReject,
        _ => '$v',
      };

  /// 状态（仅表单下拉，列表无该列）值域 = install.sql 列注释
  /// `状态: 0=待处理 1=已完成`（erp_quality_iqc_record:4136）——
  /// 与 nonconformity 的 0/1/2 域不是同一张表，词表不共用。
  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.qualityStatusPending,
        '1' => AppL10n.current.qualityStatusCompleted,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldInspectNo: r['code'] ?? '',
      // 列内容就是商品名（后端 leftJoin 带出），标题随内容用「商品名称」而非「商品ID」；
      // 关联行缺失/未关联（FK=0）落「-」——裸 hashid 不上屏
      l10n.fieldProductName: r['product_name'] ?? '-',
      l10n.qualityQtySummary: '${r['inspected_qty'] ?? 0}/${r['passed_qty'] ?? 0}/${r['rejected_qty'] ?? 0}',
      l10n.fieldResult: _resultLabel(r['result']),
      l10n.fieldInspector: r['inspector'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }
}
