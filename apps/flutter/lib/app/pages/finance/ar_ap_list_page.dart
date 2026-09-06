// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 应收应付页 — 覆盖 GET/POST/PUT/DELETE /admin/v1/finance/ar-ap
/// 契约（erp_finance_ar_ap，无 name/code 列）：type(1应收|2应付)/partner_id/
/// source_type+source_id(手建自动 'manual'+snowflake)/amount/due_date/
/// status(0未核销|1部分核销|2已核销) 由后端核销流程维护
class ArApListPage extends StatefulWidget {
  const ArApListPage({super.key});
  @override
  State<ArApListPage> createState() => _ArApListPageState();
}

class _ArApListPageState extends State<ArApListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 往来方选项：值前缀 'c'(客户)/'s'(供应商) + 列表行 hashid，
  /// 前缀用于避免客户/供应商同 id 编码碰撞并约束类型一致性。
  Map<String, String> _customerOptions = {};
  Map<String, String> _supplierOptions = {};
  bool _partnersLoaded = false;

  List<String> get _typeLabels =>
      [AppL10n.current.financeArApReceivable, AppL10n.current.financeArApPayable];
  static List<String> get _statusLabels => [
        AppL10n.current.financeArApStatusOpen,
        AppL10n.current.financeArApStatusPartial,
        AppL10n.current.financeArApStatusSettled,
      ];

  /// 全量往来方选项（'c'+hashid / 's'+hashid → 展示名）。
  List<String> get _partnerKeys => [
        for (final k in _customerOptions.keys) 'c$k',
        for (final k in _supplierOptions.keys) 's$k',
      ];
  Map<String, String> get _partnerLabels => {
        for (final e in _customerOptions.entries) 'c${e.key}': e.value,
        for (final e in _supplierOptions.entries) 's${e.key}': e.value,
      };

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/finance/ar-ap', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载客户+供应商下拉选项（partner_id 必填且为 hashid）。
  Future<bool> _ensurePartners() async {
    if (_partnersLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/customer', params: {'limit': '500'});
      final cs = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      final res2 = await ApiService.instance.get('/admin/v1/supplier', params: {'limit': '500'});
      final ss = List<Map<String, dynamic>>.from((res2['data'] ?? {})['list'] ?? []);
      _customerOptions = { for (final c in cs) '${c['id']}': '${c['name'] ?? c['code'] ?? ''}' };
      _supplierOptions = { for (final s in ss) '${s['id']}': '${s['name'] ?? s['code'] ?? ''}' };
      _partnersLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _create() async {
    if (!await _ensurePartners() || !mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/ar-ap', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensurePartners() || !mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/ar-ap/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm,
        content: AppL10n.of(context).commonDeleteMsg(row['partner_name'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/ar-ap/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端 ArApController::store 契约对齐：type 必填(1应收/2应付) + partner
  // 必填下拉；往来单位前缀须与类型匹配（c↔应收、s↔应付）；amount 必填数字。
  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'type', label: l10n.financeArApType, required: true, type: FormFieldType.dropdown,
        options: const ['1', '2'], optionLabels: {'1': l10n.financeArApReceivable, '2': l10n.financeArApPayable},
        initialValue: '1'),
      FormFieldConfig(
        name: 'partner_id',
        label: l10n.financeArApPartner,
        required: true,
        type: FormFieldType.dropdown,
        options: _partnerKeys,
        optionLabels: _partnerLabels,
      ),
      FormFieldConfig(name: 'amount', label: l10n.financeAmount, type: FormFieldType.number, required: true,
        hint: l10n.commonExampleAmount('1000.00')),
      FormFieldConfig(name: 'due_date', label: l10n.financeArApDueDate, hint: l10n.commonDateFormat),
      FormFieldConfig(name: 'remark', label: l10n.commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 组装后端参数：partner_id 去前缀还原 hashid；类型与前缀不一致则拦截。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final type = data['type']?.trim() == '2' ? '2' : '1';
    final raw = data['partner_id'] ?? '';
    final expectedPrefix = type == '1' ? 'c' : 's';
    if (!raw.startsWith(expectedPrefix) || raw.length <= 1) {
      throw StateError(AppL10n.current.financeArApPartnerMismatch);
    }
    final payload = <String, dynamic>{
      'type': type,
      'partner_id': raw.substring(1),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
      'due_date': data['due_date']?.trim() ?? '',
      'remark': data['remark']?.trim() ?? '',
    };
    if ((data['due_date']?.trim().isEmpty ?? true)) payload.remove('due_date');
    return payload;
  }

  /// 编辑回填：type 转字符串；partner 按行类型加回前缀（无匹配则空 → 弹框回退待选）。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final t = row['type'] is int ? row['type'] as int : int.tryParse('${row['type']}') ?? 1;
    d['type'] = '$t';
    final pid = '${row['partner_id'] ?? ''}';
    d['partner_id'] = (t == 1 && _customerOptions.containsKey(pid))
        ? 'c$pid'
        : (t == 2 && _supplierOptions.containsKey(pid) ? 's$pid' : '');
    return d;
  }

  String _typeText(dynamic t) {
    final i = t is int ? t : int.tryParse('$t');
    return (i != null && i >= 1 && i <= _typeLabels.length) ? _typeLabels[i - 1] : '$t';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.financeArApTitle,
    moduleKey: 'finance',
    primaryColumnIndex: 1,
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
    rightAlignColumns: [2],
  );

  List<String> _columns() => [AppL10n.current.financeArApType, AppL10n.current.financeArApPartner,
    AppL10n.current.financeAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeArApType: _typeText(r['type']),
    AppL10n.current.financeArApPartner: r['partner_name'] ?? '',
    AppL10n.current.financeAmount: '${r['amount'] ?? ''}',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0未核销=待办(warning)，1部分核销=进行中(primary)，2已核销=终态(success)。
  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = (i != null && i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
    final c = AppColors.of(context);
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.primaryBg, c.primaryPressed),
      2 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: label, bg: bg, fg: fg);
  }
}
