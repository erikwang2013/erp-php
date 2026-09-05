// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 薪资管理页 — 覆盖 GET/POST/PUT/DELETE /admin/hr/salary
/// 及 POST /admin/hr/salary/calculate（薪资试算）
/// 后端字段: employee_id / period_year / period_month / base_salary /
/// performance / overtime / deduction / tax / net_salary / status
class SalaryPage extends StatefulWidget {
  const SalaryPage({super.key});
  @override
  State<SalaryPage> createState() => _SalaryPageState();
}

class _SalaryPageState extends State<SalaryPage> {
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
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/v1/hr/salary', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrSalaryCreateTitle, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/hr/salary', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrSalaryEditTitle, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/hr/salary/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.hrSalaryDeleteConfirm, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/hr/salary/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'employee_id', label: AppL10n.current.hrEmployeeId, required: true),
    FormFieldConfig(name: 'period_year', label: AppL10n.current.hrSalaryYear, required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'period_month', label: AppL10n.current.hrSalaryMonth, required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'base_salary', label: AppL10n.current.hrSalaryBase, type: FormFieldType.number, hint: AppL10n.current.hrSalaryAmountHint),
    FormFieldConfig(name: 'performance', label: AppL10n.current.hrSalaryPerformance, type: FormFieldType.number, hint: AppL10n.current.hrSalaryZeroHint),
    FormFieldConfig(name: 'overtime', label: AppL10n.current.hrSalaryOvertime, type: FormFieldType.number, hint: AppL10n.current.hrSalaryZeroHint),
    FormFieldConfig(name: 'deduction', label: AppL10n.current.hrSalaryDeduction, type: FormFieldType.number, hint: AppL10n.current.hrSalaryZeroHint),
    FormFieldConfig(name: 'tax', label: AppL10n.current.hrSalaryTax, type: FormFieldType.number, hint: AppL10n.current.hrSalaryZeroHint),
  ];

  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String num(String key) =>
        (data[key]?.trim().isEmpty ?? true) ? '0' : data[key]!.trim();
    return {
      'employee_id': data['employee_id']?.trim(),
      'period_year': data['period_year']?.trim(),
      'period_month': data['period_month']?.trim(),
      'base_salary': num('base_salary'),
      'performance': num('performance'),
      'overtime': num('overtime'),
      'deduction': num('deduction'),
      'tax': num('tax'),
    };
  }

  /// 薪资发放：POST /admin/hr/salary/{id}/pay，二次确认 + 失败提示。
  Future<void> _pay(Map<String, dynamic> row) async {
    final l10n = AppL10n.of(context);
    final period = '${row['period_year'] ?? ''}-${row['period_month'] ?? ''}';
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.hrSalaryPayTitle),
        content: Text(l10n.hrSalaryPayConfirm(period)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(l10n.commonCancel)),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/v1/hr/salary/${row['id']}/pay');
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppL10n.current.hrSalaryPaidSnack)));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(AppL10n.of(ctx).hrSalaryPayFailedMsg('$e'))));
                }
              }
            },
            child: Text(l10n.hrSalaryPayAction),
          ),
        ],
      ),
    );
  }

  /// 薪资试算：POST /admin/hr/salary/calculate，结果弹窗展示。
  Future<void> _calculate() async {
    final l10n = AppL10n.of(context);
    await FormDialog.show(context, title: l10n.hrSalaryCalcTitle, fields: [
      FormFieldConfig(name: 'base_salary', label: AppL10n.current.hrSalaryBase, type: FormFieldType.number, initialValue: '8000'),
      FormFieldConfig(name: 'performance', label: AppL10n.current.hrSalaryPerformance, type: FormFieldType.number, initialValue: '0'),
      FormFieldConfig(name: 'overtime', label: AppL10n.current.hrSalaryOvertime, type: FormFieldType.number, initialValue: '0'),
      FormFieldConfig(name: 'deduction', label: AppL10n.current.hrSalaryDeduction, type: FormFieldType.number, initialValue: '0'),
    ], onSubmit: (data) async {
      String num(String key) =>
          (data[key]?.trim().isEmpty ?? true) ? '0' : data[key]!.trim();
      final res = await ApiService.instance.post('/admin/v1/hr/salary/calculate', data: {
        'base_salary': num('base_salary'),
        'performance': num('performance'),
        'overtime': num('overtime'),
        'deduction': num('deduction'),
      });
      final d = Map<String, dynamic>.from(res['data']);
      if (mounted) {
        await showDialog<void>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(l10n.hrCalcResultTitle),
            content: SizedBox(
              width: 380,
              child: DataTable(
                columnSpacing: 24,
                columns: [
                  DataColumn(label: Text(l10n.hrCalcItem)),
                  DataColumn(label: Text(l10n.hrCalcAmount)),
                ],
                rows: [
                  _kv(l10n.hrCalcGross, d['gross']),
                  _kv(l10n.hrCalcSocial, d['social_insurance']),
                  _kv(l10n.hrCalcHousing, d['housing_fund']),
                  _kv(l10n.hrCalcTaxable, d['taxable_income']),
                  _kv(l10n.hrSalaryTax, d['tax']),
                  _kv(l10n.hrSalaryDeduction, d['deduction']),
                  _kv(l10n.hrSalaryNet, d['net']),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(l10n.hrCalcClose)),
            ],
          ),
        );
      }
      return true;
    });
  }

  DataRow _kv(String label, dynamic v) => DataRow(cells: [
    DataCell(Text(label, style: const TextStyle(fontWeight: FontWeight.w500))),
    DataCell(Text('${v ?? '-'}')),
  ]);

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
        ElevatedButton.icon(onPressed: _calculate, icon: const Icon(Icons.calculate, size: 18), label: Text(l10n.hrSalaryCalcAction)),
        const SizedBox(width: 8),
        ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.commonAdd)),
      ],
    );
  }

  List<String> _columns() => [
    AppL10n.current.hrEmployeeId,
    AppL10n.current.hrSalaryPeriod,
    AppL10n.current.hrSalaryBase,
    AppL10n.current.hrSalaryNet,
    AppL10n.current.commonStatus,
    AppL10n.current.commonAction,
  ];

  /// 后端 status 可能返回 int 或字符串数字，宽容解析。
  static bool _paid(Map<String, dynamic> r) {
    final s = r['status'];
    return (s is int ? s : int.tryParse('$s') ?? 0) == 1;
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.hrEmployeeId: r['employee_id'] ?? '',
    AppL10n.current.hrSalaryPeriod: '${r['period_year'] ?? ''}-${r['period_month'] ?? ''}',
    AppL10n.current.hrSalaryBase: r['base_salary'] ?? '',
    AppL10n.current.hrSalaryNet: r['net_salary'] ?? '',
    AppL10n.current.commonStatus: _paid(r)
        ? AppL10n.current.hrSalaryStatusPaid : AppL10n.current.hrSalaryStatusUnpaid,
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      if (!_paid(r))
        IconButton(icon: const Icon(Icons.paid, size: 18, color: Colors.green), tooltip: AppL10n.current.hrSalaryPay, onPressed: () => _pay(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
