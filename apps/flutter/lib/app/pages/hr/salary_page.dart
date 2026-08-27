// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
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

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/hr/salary', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增薪资记录', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/hr/salary', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑薪资', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/hr/salary/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除该薪资记录吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/hr/salary/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'employee_id', label: '员工ID', required: true),
    FormFieldConfig(name: 'period_year', label: '薪资年度', required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'period_month', label: '薪资月份', required: true, type: FormFieldType.number),
    FormFieldConfig(name: 'base_salary', label: '基本工资', type: FormFieldType.number, hint: '如 8000.00'),
    FormFieldConfig(name: 'performance', label: '绩效工资', type: FormFieldType.number, hint: '默认 0'),
    FormFieldConfig(name: 'overtime', label: '加班费', type: FormFieldType.number, hint: '默认 0'),
    FormFieldConfig(name: 'deduction', label: '扣款', type: FormFieldType.number, hint: '默认 0'),
    FormFieldConfig(name: 'tax', label: '个税', type: FormFieldType.number, hint: '默认 0'),
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
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('薪资发放'),
        content: Text('确认将「${row['period_year'] ?? ''}-${row['period_month'] ?? ''}」的薪资标记为已发放吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('取消')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/hr/salary/${row['id']}/pay');
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('薪资已发放')));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('发放失败：$e')));
                }
              }
            },
            child: const Text('确认发放'),
          ),
        ],
      ),
    );
  }

  /// 薪资试算：POST /admin/hr/salary/calculate，结果弹窗展示。
  Future<void> _calculate() async {
    await FormDialog.show(context, title: '薪资试算', fields: const [
      FormFieldConfig(name: 'base_salary', label: '基本工资', type: FormFieldType.number, initialValue: '8000'),
      FormFieldConfig(name: 'performance', label: '绩效工资', type: FormFieldType.number, initialValue: '0'),
      FormFieldConfig(name: 'overtime', label: '加班费', type: FormFieldType.number, initialValue: '0'),
      FormFieldConfig(name: 'deduction', label: '扣款', type: FormFieldType.number, initialValue: '0'),
    ], onSubmit: (data) async {
      String num(String key) =>
          (data[key]?.trim().isEmpty ?? true) ? '0' : data[key]!.trim();
      final res = await ApiService.instance.post('/admin/hr/salary/calculate', data: {
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
            title: const Text('试算结果'),
            content: SizedBox(
              width: 380,
              child: DataTable(
                columnSpacing: 24,
                columns: const [DataColumn(label: Text('项目')), DataColumn(label: Text('金额'))],
                rows: [
                  _kv('应发工资', d['gross']),
                  _kv('社保(个人)', d['social_insurance']),
                  _kv('公积金', d['housing_fund']),
                  _kv('应纳税所得额', d['taxable_income']),
                  _kv('个税', d['tax']),
                  _kv('扣款', d['deduction']),
                  _kv('实发工资', d['net']),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('关闭')),
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
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [
      ElevatedButton.icon(onPressed: _calculate, icon: const Icon(Icons.calculate, size: 18), label: const Text('计算薪资')),
      const SizedBox(width: 8),
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['员工ID', '期间', '基本工资', '实发工资', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '员工ID': r['employee_id'] ?? '',
    '期间': '${r['period_year'] ?? ''}-${r['period_month'] ?? ''}',
    '基本工资': r['base_salary'] ?? '',
    '实发工资': r['net_salary'] ?? '',
    '状态': ((r['status'] ?? 0) == 1) ? '已发放' : '未发放',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      if ((r['status'] ?? 0) != 1)
        IconButton(icon: const Icon(Icons.paid, size: 18, color: Colors.green), tooltip: '发放', onPressed: () => _pay(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };
}
