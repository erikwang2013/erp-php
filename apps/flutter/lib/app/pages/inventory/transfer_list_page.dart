// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class InventoryTransferListPage extends StatefulWidget {
  const InventoryTransferListPage({super.key});
  @override
  State<InventoryTransferListPage> createState() =>
      _InventoryTransferListPageState();
}

class _InventoryTransferListPageState extends State<InventoryTransferListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() {
    super.initState();
    _load();
    _ensureWarehouseNames();
  }

  /// 列表调出/调入列的名称来源：与下拉同一端点（rule ③）。
  Map<String, String> _warehouseNames = {};

  Future<void> _ensureWarehouseNames() async {
    if (_warehouseNames.isNotEmpty) return;
    try {
      final res = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      final rows = List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
      final m = {for (final w in rows) '${w['id']}': '${w['name'] ?? ''}'};
      if (mounted) setState(() => _warehouseNames = m);
    } catch (_) {
      // 失败降级：列显示「-」，页面其余部分照常
    }
  }

  /// 仓库列取关联名：TransferController::index 只下发 from/to_warehouse_id（rule ③）；
  /// 取不到落「-」（rule ④），裸 hashid 不上屏。
  String _warehouseName(dynamic id) {
    final v = _warehouseNames['$id'];
    return (v == null || v.isEmpty) ? '-' : v;
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{
        'page': '$_page',
        'limit': '$_limit',
        'keyword': _keyword,
      };

      final res = await ApiService.instance.get(
        '/admin/v1/inventory/transfer',
        params: params,
      );
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() {
        _rows = List<Map<String, dynamic>>.from(d['list'] ?? []);
        _total = d['total'] ?? 0;
        _loading = false;
        _error = null;
      });
      if (_rows.isEmpty && _page > 1) {
        _page--;
        _load();
        return;
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = ApiService.friendlyError(e);
        });
      }
    }
  }

  /// 仓库下拉：/admin/v1/warehouse?limit=500（失败降级空表）。
  Future<List<String>> _loadWarehouseOptions() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      final rows = List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
      return [for (final w in rows) '${w['id']} - ${w['name'] ?? w['id']}'];
    } catch (_) {
      return [];
    }
  }

  /// 弹窗前预取仓库（编辑时补一行原值防回填落空）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    var options = await _loadWarehouseOptions();
    if (!mounted) return _formFields([]);
    for (final key in ['from_warehouse_id', 'to_warehouse_id']) {
      final wid = '${row?[key] ?? ''}';
      if (wid.isNotEmpty && !options.any((o) => o.startsWith('$wid - '))) {
        options = [wid, ...options];
      }
    }
    return _formFields(options);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post(
          '/admin/v1/inventory/transfer',
          data: _buildPayload(data),
        );
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final fields = await _fieldsFor(row: row);
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: fields,
      initialData: _toEditData(row, fields),
      onSubmit: (data) async {
        await ApiService.instance.put(
          '/admin/v1/inventory/transfer/${row['id']}',
          data: _buildPayload(data),
        );
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(
        context,
      ).commonDeleteMsg('${row['code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete(
          '/admin/v1/inventory/transfer/${row['id']}',
          data: {'password': password},
        );
        _load();
        return true;
      },
    );
  }

  // erp_inventory_transfer 无 name 列；store 的 validator 为 from_warehouse_id /
  // to_warehouse_id 双 'required'（decodeFlexibleId 失败或两边相同均 422）——
  // 原 'name' 是幻字段，真正必填的是调出/调入仓库。字段对齐 Web 两端
  // （Angular/React 库存域：from_warehouse_id/to_warehouse_id/code/remark）。
  List<FormFieldConfig> _formFields(List<String> warehouseOptions) => [
    FormFieldConfig(
      name: 'from_warehouse_id',
      label: AppL10n.of(context).inventoryTransferFrom,
      required: true,
      type: FormFieldType.dropdown,
      options: warehouseOptions,
    ),
    FormFieldConfig(
      name: 'to_warehouse_id',
      label: AppL10n.of(context).inventoryTransferTo,
      required: true,
      type: FormFieldType.dropdown,
      options: warehouseOptions,
    ),
    FormFieldConfig(name: 'code', label: AppL10n.of(context).commonCode),
    FormFieldConfig(
      name: 'remark',
      label: AppL10n.of(context).commonRemark,
      type: FormFieldType.multiline,
    ),
  ];

  /// 组装后端接收参数：下拉项 'id - 名称' 取回 hashid。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    return {
      'from_warehouse_id': pick('from_warehouse_id'),
      'to_warehouse_id': pick('to_warehouse_id'),
      'code': data['code']?.trim() ?? '',
      'remark': data['remark']?.trim() ?? '',
    };
  }

  /// 编辑回填：下拉值必须与 options 字符串完全一致（FormDialog 匹配不上会置空），
  /// 而列表接口不带仓库名，故按 id 前缀在选项里找 'id - 名称'、否则退回裸 id。
  Map<String, dynamic> _toEditData(
    Map<String, dynamic> row,
    List<FormFieldConfig> fields,
  ) {
    final d = Map<String, dynamic>.from(row);
    final options = fields.firstWhere((f) => f.name == 'from_warehouse_id').options;
    for (final key in ['from_warehouse_id', 'to_warehouse_id']) {
      final wid = '${row[key] ?? ''}';
      d[key] = wid.isEmpty
          ? ''
          : options.firstWhere((o) => o.startsWith('$wid - '), orElse: () => wid);
    }
    return d;
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total,
    page: _page,
    limit: _limit,
    loading: _loading,
    error: _error,
    onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) {
      _keyword = v;
      _page = 1;
      _load();
    },
    onPageChanged: (p) {
      _page = p;
      _load();
    },
    pageTitle: AppL10n.of(context).inventoryTransferTitle,
    moduleKey: 'inventory',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  List<String> _columns() => [
    AppL10n.of(context).commonCode,
    AppL10n.of(context).inventoryTransferFrom,
    AppL10n.of(context).inventoryTransferTo,
    AppL10n.of(context).commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.commonCode: r['code'] ?? '',
      l.inventoryTransferFrom: _warehouseName(r['from_warehouse_id']),
      l.inventoryTransferTo: _warehouseName(r['to_warehouse_id']),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.edit, size: 18),
            onPressed: () => _edit(r),
          ),
          IconButton(
            icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger),
            onPressed: () => _delete(r),
          ),
        ],
      ),
    };
  }
}
