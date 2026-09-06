// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 收款单页 — 覆盖 GET/POST/PUT/DELETE /admin/v1/finance/receipt
/// 契约（erp_finance_receipt，无 name 列）：code/customer_id/bank_account_id/
/// amount/method(cash|bank|wechat|alipay)/status(0待审核|1已审核)/remark/received_at
class ReceiptListPage extends StatefulWidget {
  const ReceiptListPage({super.key});
  @override
  State<ReceiptListPage> createState() => _ReceiptListPageState();
}

class _ReceiptListPageState extends State<ReceiptListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 客户选项（id→名称，id 为客户列表行 hashid），打开新增/编辑弹窗前懒加载。
  Map<String, String> _customerOptions = {};
  bool _customersLoaded = false;
  /// 收款账户下拉（可选字段；后端缺省 0），加载失败仅空选项不阻断提交。
  Map<String, String> _bankOptions = {};

  static List<String> get _statusLabels =>
      [AppL10n.current.financeStatusPending, AppL10n.current.financeStatusApproved];
  static List<String> get _methodValues => ['cash', 'bank', 'wechat', 'alipay'];
  List<String> get _methodLabels => [
        AppL10n.current.financeMethodCash,
        AppL10n.current.financeMethodBank,
        AppL10n.current.financeMethodWechat,
        AppL10n.current.financeMethodAlipay,
      ];
  Map<String, String> get _methodOptionLabels => {
        for (var i = 0; i < _methodValues.length; i++) _methodValues[i]: _methodLabels[i],
      };

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/finance/receipt', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 加载客户下拉选项（customer_id 必填且为 hashid，取自 /admin/v1/customer 列表行 id）。
  Future<bool> _ensureCustomers() async {
    if (_customersLoaded) return true;
    try {
      final res = await ApiService.instance.get('/admin/v1/customer', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _customerOptions = { for (final c in list) '${c['id']}': '${c['name'] ?? c['code'] ?? ''}' };
      _customersLoaded = true;
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  Future<void> _ensureBanks() async {
    if (_bankOptions.isNotEmpty) return;
    try {
      final res = await ApiService.instance.get('/admin/v1/finance/bank-account', params: {'limit': '500'});
      final list = List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []);
      _bankOptions = { for (final b in list) '${b['id']}': '${b['name'] ?? ''}' };
    } catch (_) {/* 可选下拉失败仅空选项 */}
  }

  Future<void> _create() async {
    if (!await _ensureCustomers() || !mounted) return;
    await _ensureBanks();
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/finance/receipt', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureCustomers() || !mounted) return;
    await _ensureBanks();
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/finance/receipt/${row['id']}', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm,
        content: AppL10n.of(context).commonDeleteMsg(row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/finance/receipt/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 与后端 ReceiptController::store 契约对齐：code 留空自动生成 RCV+时间戳
  // （uk_code 唯一）；customer 必填下拉；method 枚举下拉缺省 bank；银行账户可选
  List<FormFieldConfig> _formFields() {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    return [
      FormFieldConfig(name: 'code', label: AppL10n.of(context).financeReceiptCode, hint: AppL10n.of(context).financeReceiptCodeHint),
      FormFieldConfig(
        name: 'customer_id',
        label: AppL10n.of(context).fieldCustomer,
        required: true,
        type: FormFieldType.dropdown,
        options: _customerOptions.keys.toList(),
        optionLabels: _customerOptions,
      ),
      FormFieldConfig(name: 'amount', label: AppL10n.of(context).financeAmount, type: FormFieldType.number, required: true,
        hint: AppL10n.of(context).commonExampleAmount('1000.00')),
      FormFieldConfig(name: 'method', label: AppL10n.of(context).financeMethod, type: FormFieldType.dropdown,
        options: _methodValues, optionLabels: _methodOptionLabels, initialValue: 'bank'),
      FormFieldConfig(name: 'bank_account_id', label: AppL10n.of(context).financeBankAccountTitle, type: FormFieldType.dropdown,
        options: _bankOptions.keys.toList(), optionLabels: _bankOptions),
      FormFieldConfig(name: 'received_at',
        label: AppL10n.of(context).financeReceivedAt,
        initialValue: '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}',
        hint: AppL10n.of(context).commonDateTimeFormat),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 组装后端 store()/update() 接收的参数（仅真实表列；code 留空自动生成）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'RCV${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final payload = <String, dynamic>{
      'code': code,
      'customer_id': data['customer_id']?.trim(),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
      'method': (data['method']?.trim().isEmpty ?? true) ? 'bank' : data['method']!.trim(),
      'received_at': data['received_at']?.trim(),
      'remark': data['remark']?.trim() ?? '',
    };
    // 空银行账户不上送：创建走后端 0 缺省，编辑保持原账户（杜绝 '' → 0 静默清空）
    final bank = data['bank_account_id']?.trim() ?? '';
    if (bank.isNotEmpty) payload['bank_account_id'] = bank;
    return payload;
  }

  String _p2(int v) => v.toString().padLeft(2, '0');

  String _methodText(dynamic m) {
    final s = '$m';
    final i = _methodValues.indexOf(s);
    return (i >= 0) ? _methodLabels[i] : s;
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
    pageTitle: AppL10n.current.financeReceiptTitle,
    moduleKey: 'finance',
    primaryColumnIndex: 0,
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
    rightAlignColumns: [2],
  );

  List<String> _columns() => [AppL10n.current.financeReceiptCode, AppL10n.current.fieldCustomer,
    AppL10n.current.financeAmount, AppL10n.current.financeMethod, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.financeReceiptCode: r['code'] ?? '',
    AppL10n.current.fieldCustomer: r['customer_name'] ?? '',
    AppL10n.current.financeAmount: '${r['amount'] ?? ''}',
    AppL10n.current.financeMethod: _methodText(r['method']),
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0待审核=待办(warning)，1已审核=终态(success)。
  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = (i != null && i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
    final c = AppColors.of(context);
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: label, bg: bg, fg: fg);
  }
}
