// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../utils/format.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/filter_chips_bar.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/line_items_editor.dart';

class SalesOrderListPage extends StatefulWidget {
  const SalesOrderListPage({super.key});
  @override
  State<SalesOrderListPage> createState() => _SalesOrderListPageState();
}

class _SalesOrderListPageState extends State<SalesOrderListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
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
      if (_statusFilter != null) params['status'] = _statusFilter!;
      final res = await ApiService.instance.get('/admin/v1/sales/order', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.of(context);
    // 外键选项先就位再弹窗（失败已弹提示，此处直接返回）
    if (!await _ensureRefs()) return;
    if (!mounted) return;
    // 明细经 FormDialog 的 child 插槽接入（表单值 Map<String,String> 装不下数组），
    // 累积结果由下面的 onSubmit 闭包捕获后塞进 payload。
    // 明细仅新建期填写：编辑态不回填 items，避免「空编辑器 + 整表替换」误清明细。
    var items = <Map<String, dynamic>>[];
    await FormDialog.show(context, title: l10n.salesOrderAdd, fields: _formFields(),
      child: LineItemsEditor(onChanged: (rows) => items = rows),
      onSubmit: (data) async {
      if (items.isEmpty) throw Exception(l10n.detailAllocateEmpty);
      final payload = _buildPayload(data);
      payload['items'] = items;
      await ApiService.instance.post('/admin/v1/sales/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureRefs()) return;
    if (!mounted) return;
    await FormDialog.show(context, title: AppL10n.of(context).salesOrderEdit, fields: _formFields(row: row),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/sales/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: AppL10n.of(context).commonDeleteConfirm, content: AppL10n.of(context).commonDeleteMsg(row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/sales/order/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 销售结算：本页不再自建弹窗 —— 旧弹窗上送的 customer_id/received_amount/status/
  /// settled_at 后端 SettlementController::store 一律不读（状态由服务层推导），
  /// 且不收集 required 的 receipt_payment_id，请求恒在 validator 处 422。
  /// 结算页（/sales/settlement）本就是同一契约的表单，直接跳过去。
  Future<void> _settle() async {
    await Get.toNamed('/sales/settlement');
    // 结算页不 pop 回传值，且核销会改订单状态 → 返回后无条件刷新
    if (mounted) _load();
  }

  // 后端 erp_sales_order 字段: code/customer_id/warehouse_id/total_amount/
  // discount_amount/status/remark/ordered_at（无 name 列；客户名 customer_name 由列表 leftJoin 带出）
  static List<String> get _statusLabels => [AppL10n.current.salesOrderPending, AppL10n.current.salesOrderReviewed, AppL10n.current.salesOrderPartShipped, AppL10n.current.salesOrderShipped, AppL10n.current.salesOrderCancelled];

  /// 外键下拉数据源：FormFieldConfig 无 remote source（FormFieldType 只有
  /// text/number/dropdown/password/multiline），故弹窗前自己预取喂静态 options/optionLabels
  /// —— 本工程既有惯用法，模板见 wms/pack_page.dart:68-86。
  /// customer_id 必填；warehouse_id 可空（列 NOT NULL DEFAULT 0，空选下发 '0' 落 0）。
  /// 两者原先都是手输 hashid，用户无从获得。
  Map<String, String> _customers = {}, _warehouses = {};

  Future<bool> _ensureRefs() async {
    try {
      final c = await ApiService.instance.get('/admin/v1/customer', params: {'limit': '500'});
      final w = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      _customers = _options(c, 'name');
      // 可空外键前置空选项：下拉一旦替掉文本框，'0' 就再也填不回去（含清空场景）
      _warehouses = _options(w, 'name', optional: true);
      return true;
    } catch (e) {
      // 必填下拉取不到选项就不弹窗，避免用户面对空下拉无路可走（P1）
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      return false;
    }
  }

  /// 列表响应 → 选项表（值=hashid，标签=名称/单号）。optional=true 时前置空选项
  /// （值 ''，_buildPayload 归一成 '0'）供「不指定」。
  /// 标签走 fmtText 落占位：名称键缺失/为空时贴出的会是 encodeIds 后的雪花码，对用户是噪声。
  Map<String, String> _options(Map<String, dynamic> res, String labelKey, {bool optional = false}) => {
    if (optional) '': AppL10n.of(context).commonUnspecified,
    for (final r in List<Map<String, dynamic>>.from(res['data']?['list'] ?? []))
      '${r['id']}': fmtText(r[labelKey]),
  };

  /// 编辑态：当前 FK 不在预取列表内时前置进选项 —— FormDialog 会把不在 options 里的
  /// 预填值置 null（form_dialog.dart:80-83），提交时该外键就被静默清空了（P2）。
  /// 返回新 map（前置孤儿项），未命中时原样返回 —— 口径同 quality/ipqc_list_page.dart::_primed。
  /// 选项标签同样只出可读名（fmtText），没有名称就出占位短横，绝不回落 hashid 本身。
  Map<String, String> _primed(Map<String, String> m, Map<String, dynamic> row, String idKey, String nameKey) {
    final id = '${row[idKey] ?? ''}';
    if (id.isEmpty || id == '0' || m.containsKey(id)) return m;
    return {id: fmtText(row[nameKey]), ...m};
  }

  List<FormFieldConfig> _formFields({Map<String, dynamic>? row}) {
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultOrderedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    var customers = _customers, warehouses = _warehouses;
    if (row != null) {
      customers = _primed(customers, row, 'customer_id', 'customer_name');
      warehouses = _primed(warehouses, row, 'warehouse_id', 'warehouse_name');
    }
    // 可空外键 length>1 才算「有真选项」（1 是那枚空选项），否则退回文本框
    return [
      FormFieldConfig(name: 'code', label: AppL10n.of(context).salesOrderNo, hint: AppL10n.of(context).salesOrderCodeHint),
      FormFieldConfig(name: 'customer_id', label: AppL10n.of(context).salesCustomerId, required: true,
        type: customers.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
        options: customers.keys.toList(), optionLabels: customers,
        hint: AppL10n.of(context).salesCustomerIdHint),
      FormFieldConfig(name: 'warehouse_id', label: AppL10n.of(context).salesWarehouseId,
        type: warehouses.length > 1 ? FormFieldType.dropdown : FormFieldType.text,
        options: warehouses.keys.toList(), optionLabels: warehouses,
        hint: AppL10n.of(context).salesWarehouseIdHint),
      FormFieldConfig(name: 'total_amount', label: AppL10n.of(context).salesOrderTotalAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonExampleAmount('100.00')),
      FormFieldConfig(name: 'discount_amount', label: AppL10n.of(context).salesDiscountAmount, type: FormFieldType.number, hint: AppL10n.of(context).commonDefaultZero),
      FormFieldConfig(name: 'status', label: AppL10n.of(context).commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) '$i - ${_statusLabels[i]}'], initialValue: '0 - ${_statusLabels[0]}'),
      FormFieldConfig(name: 'ordered_at', label: AppL10n.of(context).salesOrderedAt, initialValue: defaultOrderedAt,
        hint: AppL10n.of(context).commonDateTimeFormat),
      FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    var code = data['code']?.trim() ?? '';
    if (code.isEmpty) {
      final now = DateTime.now();
      code = 'SO${now.year}${_p2(now.month)}${_p2(now.day)}${_p2(now.hour)}${_p2(now.minute)}${_p2(now.second)}';
    }
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      'code': code,
      // customer_id 原样传 hashid 串（后端 store/update 已解码落库）
      'customer_id': data['customer_id']?.trim(),
      'warehouse_id': (data['warehouse_id']?.trim().isEmpty ?? true) ? '0' : data['warehouse_id']!.trim(),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
      'discount_amount': (data['discount_amount']?.trim().isEmpty ?? true) ? '0' : data['discount_amount']!.trim(),
      'status': statusRaw,
      'ordered_at': data['ordered_at']?.trim(),
      'remark': data['remark']?.trim() ?? '',
    };
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

  /// 详情页入口：写操作成功后详情页回传 changed=true → 刷新本列表。
  Future<void> _detail(Map<String, dynamic> row) async {
    final changed = await Get.toNamed('/sales/order/detail', arguments: {
      'id': '${row['id']}',
      'title': '${row['code'] ?? ''}',
    });
    if (changed == true && mounted) _load();
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.salesOrderTitle,
    moduleKey: 'sales',
    primaryColumnIndex: 0,
    filterBar: FilterChips<String>(
      options: [for (var i = 0; i < _statusLabels.length; i++) ('$i', _statusLabels[i])],
      selected: _statusFilter, onChanged: (v) { _statusFilter = v; _page = 1; _load(); },
    ),
    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
    rightAlignColumns: [2],
  );

  List<String> _columns() => [AppL10n.current.salesOrderNo, AppL10n.current.partnerCustomerTitle, AppL10n.current.salesTotalAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.salesOrderNo: r['code'] ?? '',
    // 列表行无 name 列，客户名由 customer_name 带出；取不到留空（不回落 customer_id hashid）
    AppL10n.current.partnerCustomerTitle: r['customer_name'] ?? '',
    AppL10n.current.salesTotalAmount: r['total_amount'] ?? '',
    AppL10n.current.commonStatus: _chip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.visibility_outlined, size: 18),
        tooltip: AppL10n.current.commonDetail, onPressed: () => _detail(r)),
      IconButton(icon: Icon(Icons.paid, size: 18, color: AppColors.of(context).primary),
        tooltip: AppL10n.current.salesSettleTooltip, onPressed: _settle),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };
  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    final label = _statusText(s);
    final c = AppColors.of(context);
    // §2.4：0待审批=待办(warning)，1已审批/3已发货=终态(success)，2部分发货=进行中(primary)，4已取消=失败(danger)
    final (bg, fg) = switch (i) {
      0 => (c.warningBg, c.warningText),
      1 || 3 => (c.successBg, c.successText),
      2 => (c.primaryBg, c.primaryPressed),
      4 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: label, bg: bg, fg: fg);
  }
}
