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

class PurchaseOrderListPage extends StatefulWidget {
  const PurchaseOrderListPage({super.key});
  @override
  State<PurchaseOrderListPage> createState() => _PurchaseOrderListPageState();
}

class _PurchaseOrderListPageState extends State<PurchaseOrderListPage> {
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
      final res = await ApiService.instance.get('/admin/v1/purchase/order', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      final list = List<Map<String, dynamic>>.from(d['list'] ?? []);
      setState(() { _rows = list; _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (list.isEmpty && _page > 1) { _page--; _load(); }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    // 外键选项先就位再弹窗（失败已弹提示，此处直接返回）
    if (!await _ensureRefs()) return;
    if (!mounted) return;
    // 明细经 FormDialog 的 child 插槽接入（表单值 Map<String,String> 装不下数组），
    // 累积结果由下面的 onSubmit 闭包捕获后塞进 payload。
    // 明细仅新建期填写：编辑态不回填 items，避免「空编辑器 + 整表替换」误清明细。
    var items = <Map<String, dynamic>>[];
    await FormDialog.show(context, title: l10n.purchaseOrderAddTitle, fields: _formFields(),
      child: LineItemsEditor(onChanged: (rows) => items = rows),
      onSubmit: (data) async {
      if (items.isEmpty) throw Exception(l10n.detailAllocateEmpty);
      final payload = _buildPayload(data);
      payload['items'] = items;
      await ApiService.instance.post('/admin/v1/purchase/order', data: payload);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    if (!await _ensureRefs()) return;
    if (!mounted) return;
    await FormDialog.show(context, title: l10n.purchaseOrderEditTitle, fields: _formFields(row: row),
      initialData: _toEditData(row), onSubmit: (data) async {
      final payload = _buildPayload(data);
      await ApiService.instance.put('/admin/v1/purchase/order/${row['id']}', data: payload);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm,
        content: l10n.purchaseDeleteConfirmMsg('${row['code'] ?? row['id']}'),
        onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/purchase/order/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  // 后端 erp_purchase_order 字段: code/apply_id/supplier_id/warehouse_id/
  // total_amount/status/remark/ordered_at（无 name 列；供应商名 supplier_name 由列表 leftJoin 带出）
  List<String> get _statusLabels => [
    AppL10n.current.purchaseOrderStatusPending,
    AppL10n.current.purchaseOrderStatusApproved,
    AppL10n.current.purchaseOrderStatusPartReceived,
    AppL10n.current.purchaseOrderStatusReceived,
    AppL10n.current.purchaseOrderStatusCancelled,
  ];

  /// 下拉选项文案与后端 status 数字一一对应（提交时取 ' - ' 前缀）。
  String _statusOption(int i) => '$i - ${_statusLabels[i]}';

  /// 外键下拉数据源：FormFieldConfig 无 remote source（FormFieldType 只有
  /// text/number/dropdown/password/multiline），故弹窗前自己预取喂静态 options/optionLabels
  /// —— 本工程既有惯用法，模板见 wms/pack_page.dart:68-86。
  /// supplier_id 必填（NOT NULL）；apply_id/warehouse_id 可空（后端 decodeFlexibleId ?? 0 落 0，
  /// 列 NOT NULL DEFAULT 0）。三者原先都是手输 hashid，用户无从获得。
  Map<String, String> _suppliers = {}, _applies = {}, _warehouses = {};

  Future<bool> _ensureRefs() async {
    try {
      final s = await ApiService.instance.get('/admin/v1/supplier', params: {'limit': '500'});
      final a = await ApiService.instance.get('/admin/v1/purchase/apply', params: {'limit': '500'});
      final w = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      _suppliers = _options(s, 'name');
      // 可空外键前置空选项：下拉一旦替掉文本框，'0' 就再也填不回去（含清空场景）
      _applies = _options(a, 'code', optional: true);
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
    final l10n = AppL10n.current;
    final now = DateTime.now();
    String pad(int v) => v.toString().padLeft(2, '0');
    final defaultOrderedAt =
        '${now.year}-${pad(now.month)}-${pad(now.day)} ${pad(now.hour)}:${pad(now.minute)}:${pad(now.second)}';
    var suppliers = _suppliers, applies = _applies, warehouses = _warehouses;
    if (row != null) {
      suppliers = _primed(suppliers, row, 'supplier_id', 'supplier_name');
      applies = _primed(applies, row, 'apply_id', 'apply_code');
      warehouses = _primed(warehouses, row, 'warehouse_id', 'warehouse_name');
    }
    // 可空外键 length>1 才算「有真选项」（1 是那枚空选项），否则退回文本框
    return [
      // 表无「订单编号」录入项：单号由后端生成，列表/详情可见（见 _buildPayload 注释）
      // supplier_id 下发 hashid 串（后端 store/update 已解码落库）
      FormFieldConfig(name: 'supplier_id', label: l10n.purchaseSupplierId, required: true,
        type: suppliers.isNotEmpty ? FormFieldType.dropdown : FormFieldType.text,
        options: suppliers.keys.toList(), optionLabels: suppliers,
        hint: l10n.purchaseSupplierIdHint),
      FormFieldConfig(name: 'apply_id', label: l10n.purchaseApplyId,
        type: applies.length > 1 ? FormFieldType.dropdown : FormFieldType.text,
        options: applies.keys.toList(), optionLabels: applies,
        hint: l10n.purchaseZeroHint),
      FormFieldConfig(name: 'warehouse_id', label: l10n.purchaseWarehouseId,
        type: warehouses.length > 1 ? FormFieldType.dropdown : FormFieldType.text,
        options: warehouses.keys.toList(), optionLabels: warehouses,
        hint: l10n.purchaseZeroHint),
      FormFieldConfig(name: 'total_amount', label: l10n.purchaseOrderTotalAmount, type: FormFieldType.number, hint: l10n.purchaseOrderTotalHint),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
        options: [for (var i = 0; i < _statusLabels.length; i++) _statusOption(i)], initialValue: _statusOption(0)),
      FormFieldConfig(name: 'ordered_at', label: l10n.purchaseOrderTimeLabel, initialValue: defaultOrderedAt,
        hint: l10n.purchaseDateTimeHint),
      FormFieldConfig(name: 'remark', label: l10n.purchaseRemark, type: FormFieldType.multiline),
    ];
  }

  /// 把表单提交值转换为后端 store()/update() 接收的参数（status 拆出数字）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    // 不下发 code：订单编号由后端 doc_code() 生成（PO+雪花号）。
    // 前端自造（曾用 PO+秒级时间戳）与后端重复，同秒两次提交必撞 uk_code。
    final statusRaw = (data['status'] ?? '').split(' - ').first.trim();
    return {
      // supplier_id 原样传 hashid 串（后端 store/update 已解码落库）
      'supplier_id': data['supplier_id']?.trim(),
      'apply_id': (data['apply_id']?.trim().isEmpty ?? true) ? '0' : data['apply_id']!.trim(),
      'warehouse_id': (data['warehouse_id']?.trim().isEmpty ?? true) ? '0' : data['warehouse_id']!.trim(),
      'total_amount': (data['total_amount']?.trim().isEmpty ?? true) ? '0' : data['total_amount']!.trim(),
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
      d['status'] = _statusOption(s);
    }
    return d;
  }

  /// 详情页入口：写操作成功后详情页回传 changed=true → 刷新本列表。
  Future<void> _detail(Map<String, dynamic> row) async {
    final changed = await Get.toNamed('/purchase/order/detail', arguments: {
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
    pageTitle: AppL10n.current.purchaseOrderTitle,
    moduleKey: 'purchase',
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

  List<String> _columns() => [AppL10n.current.purchaseOrderCode, AppL10n.current.partnerSupplierTitle, AppL10n.current.purchaseTotalAmount, AppL10n.current.commonStatus, AppL10n.current.commonAction];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    AppL10n.current.purchaseOrderCode: r['code'] ?? '',
    // 列表行无 name 列，供应商名由 supplier_name 带出；取不到留空（不回落 supplier_id hashid）
    AppL10n.current.partnerSupplierTitle: r['supplier_name'] ?? '',
    AppL10n.current.purchaseTotalAmount: r['total_amount'] ?? '',
    AppL10n.current.commonStatus: _statusChip(r['status']),
    AppL10n.current.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.visibility_outlined, size: 18),
        tooltip: AppL10n.current.commonDetail, onPressed: () => _detail(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
    ]),
  };

  /// 状态徽标（§2.4）：0待审核=待办(warning)，1已审核/3已收货=终态(success)，
  /// 2部分收货=进行中(primary)，4已取消=失败(danger)。
  Widget _statusChip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final labels = _statusLabels;
    final text = (i >= 0 && i < labels.length) ? labels[i] : '$s';
    final c = AppColors.of(context);
    // §2.4: 0待审核=待办(warning)，1已审核/3已收货=终态(success)，2部分收货=进行中(primary)，
    // 4已取消=失败(danger)
    final (bg, fg) = switch (i) {
      1 || 3 => (c.successBg, c.successText),
      4 => (c.dangerBg, c.dangerText),
      2 => (c.primaryBg, c.primaryPressed),
      0 => (c.warningBg, c.warningText),
      _ => (c.primaryBg, c.primaryPressed),
    };
    return StatusBadge(label: text, bg: bg, fg: fg);
  }
}
