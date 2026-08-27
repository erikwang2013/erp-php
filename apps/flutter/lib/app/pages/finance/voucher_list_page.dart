// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class VoucherListPage extends StatefulWidget {
  const VoucherListPage({super.key});
  @override
  State<VoucherListPage> createState() => _VoucherListPageState();
}

class _VoucherListPageState extends State<VoucherListPage> {
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
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      
      final res = await ApiService.instance.get('/admin/finance/voucher', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增记账凭证', fields: _formFields(), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.post('/admin/finance/voucher', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑记账凭证', fields: _formFields(),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/finance/voucher/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['code'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/finance/voucher/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 后端 erp_finance_voucher 字段: code/voucher_date/status(0草稿/1已审核)/remark
  // store() 同时校验 name 必填；传 items 时走 DoubleEntryService::createVoucher，
  // 接收 items[{account_id|account_subject_id, summary, debit_amount, credit_amount}]
  // 并校验借贷平衡（借方合计 == 贷方合计）。
  static const List<String> _statusLabels = ['草稿', '已审核'];

  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultDate = '${now.year}-${pad(now.month)}-${pad(now.day)}';
    return [
      FormFieldConfig(name: 'name', label: '凭证名称', required: true, hint: '必填（后端校验）'),
      FormFieldConfig(name: 'code', label: '凭证号', hint: '留空自动生成 VCH+时间戳'),
      FormFieldConfig(name: 'voucher_date', label: '凭证日期', required: true, initialValue: defaultDate,
        hint: '格式 YYYY-MM-DD'),
      FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown,
        options: ['0 - 草稿', '1 - 已审核'], initialValue: '0 - 草稿'),
      FormFieldConfig(name: 'remark', label: '备注', type: FormFieldType.multiline),
      // 简单明细项（至少一行）：科目ID、摘要、借方金额、贷方金额。
      // 提交时若科目ID非空则组装为 items 列表，由后端 DoubleEntryService 校验借贷平衡。
      FormFieldConfig(name: 'item_account_id', label: '明细-科目ID', hint: '从科目列表获取数字ID，填了则按明细创建'),
      FormFieldConfig(name: 'item_summary', label: '明细-摘要'),
      FormFieldConfig(name: 'item_debit_amount', label: '明细-借方金额', type: FormFieldType.number, hint: '如 100.00'),
      FormFieldConfig(name: 'item_credit_amount', label: '明细-贷方金额', type: FormFieldType.number, hint: '如 100.00'),
    ];
  }

  /// 组装后端 store()/update() 接收的参数；科目ID非空时附带 items 明细。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'VCH${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    final payload = <String, dynamic>{
      'name': data['name'],
      'code': code,
      'voucher_date': data['voucher_date']?.trim(),
      'status': statusRaw,
      'remark': data['remark']?.trim() ?? '',
    };

    final accountId = data['item_account_id']?.trim() ?? '';
    if (accountId.isNotEmpty) {
      payload['items'] = [
        {
          'account_id': accountId,
          'summary': data['item_summary']?.trim() ?? '',
          'debit_amount': (data['item_debit_amount']?.trim().isEmpty ?? true) ? '0' : data['item_debit_amount']!.trim(),
          'credit_amount': (data['item_credit_amount']?.trim().isEmpty ?? true) ? '0' : data['item_credit_amount']!.trim(),
        },
      ];
    }
    return payload;
  }

  /// 编辑回填：把后端数字 status 转回下拉选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final s = d['status'];
    if (s is int && s >= 0 && s < _statusLabels.length) {
      d['status'] = '$s - ${_statusLabels[s]}';
    }
    return d;
  }

  String _p2(int v) => v.toString().padLeft(2, '0');

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['凭证号', '凭证日期', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '凭证号': r['code'] ?? '',
    '凭证日期': r['voucher_date'] ?? '',
    '状态': _chip(_statusText(r['status'])),
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(String s) {
    final color = s == '已审核' ? Colors.green : Colors.orange;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(s, style: TextStyle(color: color, fontSize: 12)),
    );
  }

}
