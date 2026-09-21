// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/filter_chips_bar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

/// 销售结算管理页 — 覆盖 POST/GET/PUT/DELETE /admin/sales/settlement
/// 列表为应收记录薄视图；新增即收款核销（delivery_id + receipt_payment_id + amount），
/// 编辑仅调应收金额，status/received_amount/settled_at 由服务端推导返回
class SalesSettlementListPage extends StatefulWidget {
  const SalesSettlementListPage({super.key});
  @override
  State<SalesSettlementListPage> createState() => _SalesSettlementListPageState();
}

class _SalesSettlementListPageState extends State<SalesSettlementListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  static List<String> get _statusLabels => [AppL10n.current.salesSettlementUnsettled, AppL10n.current.salesSettlementPartSettled, AppL10n.current.salesSettlementSettled];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/sales/settlement', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  /// 外键下拉数据源。FormFieldConfig 没有 remote source（FormFieldType 只有
  /// text/number/dropdown/password/multiline），故弹窗前自己预取喂静态
  /// options/optionLabels —— 本工程既有惯用法，模板见 wms/pack_page.dart:68-86。
  /// 两个 status=1 都不是装饰：发货单 status=1 即「已发货」，而 AR 记录正是在发货动作里
  /// 建的（DeliveryController 置 status=1 后 createAr），故它恰好等于「有应收记录的发货单」；
  /// 收款单 status=1 即「已审核」，否则 FinanceService::assertReceiptPaymentUsable 必抛。
  /// ponytail: 收款单未再按「与发货单同客户」过滤 —— 弹窗打开时 options 就固定，无法随
  /// 已选发货单联动；归属不一致由服务端兜底报错（文案自解释）。
  Map<String, String> _deliveries = {}, _receipts = {};

  Future<bool> _ensureRefs() async {
    try {
      final d = await ApiService.instance.get('/admin/v1/sales/delivery', params: {'limit': '500', 'status': '1'});
      _deliveries = {
        for (final r in List<Map<String, dynamic>>.from(d['data']?['list'] ?? []))
          '${r['id']}': '${r['code'] ?? r['id']}',
      };
      final p = await ApiService.instance.get('/admin/v1/finance/receipt', params: {'limit': '500', 'status': '1'});
      _receipts = {
        for (final r in List<Map<String, dynamic>>.from(p['data']?['list'] ?? []))
          '${r['id']}': '${r['code'] ?? r['id']}',
      };
      return true;
    } catch (e) {
      // 必填下拉取不到选项就不弹窗，避免用户面对空下拉无路可走
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      return false;
    }
  }

  /// 结算表单: 新增=核销登记（发货单+收款单+金额），编辑=仅调应收金额。
  /// 新增态的 required 外键：**有可选项才转下拉**（值=hashid，标签=单号），列表为空则退回
  /// 文本框 —— 退化成改动前的手输，绝不会出现「必填空下拉、用户既选不了也提交不了」。
  /// 编辑态一律文本框：回填的原值可能不在预取列表内（该收款单已核销完），下拉找不到项会直接断言崩溃。
  /// 下拉分支不渲染 hint（form_dialog.dart 该分支只读 labelText），故 hint 在文本框回退态才可见。
  List<FormFieldConfig> _formFields({bool forCreate = true}) => [
    FormFieldConfig(name: 'delivery_id', label: AppL10n.of(context).salesDeliveryId, required: true,
      type: forCreate && _deliveries.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
      options: _deliveries.keys.toList(), optionLabels: _deliveries),
    if (forCreate) FormFieldConfig(name: 'receipt_payment_id', label: AppL10n.of(context).salesReceiptPaymentId, required: true,
      type: _receipts.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
      options: _receipts.keys.toList(), optionLabels: _receipts,
      hint: AppL10n.of(context).salesReceiptPaymentHint),
    FormFieldConfig(name: 'amount', label: forCreate ? AppL10n.of(context).salesWriteoffAmount : AppL10n.of(context).salesReceivableAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('1000.00')),
  ];

  Map<String, dynamic> _buildPayload(Map<String, String> data, {bool forCreate = true}) {
    return {
      'delivery_id': data['delivery_id']?.trim(),
      if (forCreate) 'receipt_payment_id': data['receipt_payment_id']?.trim(),
      'amount': (data['amount']?.trim().isEmpty ?? true) ? '0' : data['amount']!.trim(),
    };
  }

  Future<void> _create() async {
    // 两个必填外键的选项必须先就位再弹窗（失败已弹提示，此处直接返回）
    if (!await _ensureRefs()) return;
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).salesSettlementAdd, fields: _formFields(forCreate: true), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/sales/settlement', data: _buildPayload(data));
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: AppL10n.of(context).salesSettlementEdit, fields: _formFields(forCreate: false), initialData: _toEditData(row), onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/sales/settlement/${row['id']}', data: _buildPayload(data, forCreate: false));
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).salesSettlementDeleteMsg, onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/settlement/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  Map<String, dynamic> _toEditData(Map<String, dynamic> row) => Map<String, dynamic>.from(row);

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.salesSettlementTitle,
    moduleKey: 'sales',
    primaryColumnIndex: 0,
    filterBar: FilterChips<String>(
      options: [for (var i = 0; i < _statusLabels.length; i++) ('$i', _statusLabels[i])],
      selected: _statusFilter, onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).salesSettlementAddButton)),
    ],
    rightAlignColumns: [2, 3, 5],
  );

  List<String> _columns() => [AppL10n.current.salesCustomerId, AppL10n.current.salesDeliveryId, AppL10n.current.salesReceivableAmount, AppL10n.current.salesReceivedAmount, AppL10n.current.commonStatus, AppL10n.current.salesSettledAt, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesCustomerId: r['customer_id'] ?? '',
    AppL10n.current.salesDeliveryId: r['delivery_id'] ?? '',
    AppL10n.current.salesReceivableAmount: r['amount'] ?? '',
    AppL10n.current.salesReceivedAmount: r['received_amount'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.salesSettledAt: r['settled_at'] ?? '',
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = _statusText(s);
    final c = AppColors.of(context);
    // §2.4：0未结算=待办(warning)，1部分结算=进行中(primary)，2已结算=终态(success)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 => (c.primaryBg, c.primaryPressed),
      2 => (c.successBg, c.successText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: label, bg: bg, fg: fg);
  }
}
