// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_inventory_alert_rule 真实列：product_id/sku_id(0=全部)/warehouse_id(0=全部)/
// min_quantity/max_quantity/enabled。无 name/code/status 列（幻列已删，无文本
// 可搜列故不挂搜索框）；商品/仓库名由后端 leftJoin 带回 product_name/warehouse_name。
class InventoryAlertListPage extends StatefulWidget {
  const InventoryAlertListPage({super.key});
  @override
  State<InventoryAlertListPage> createState() => _InventoryAlertListPageState();
}

class _InventoryAlertListPageState extends State<InventoryAlertListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 商品下拉：/admin/v1/product?limit=500（行 name 为商品名；失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadProducts() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/product', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  /// 仓库下拉：/admin/v1/warehouse?limit=500（行 name 为仓库名，0=全部走缺省；失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadWarehouses() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/inventory/alert', params: {'page': '$_page', 'limit': '$_limit'});
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

  /// 弹窗前预取商品/仓库（编辑时各自补一行原值防回填落空）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    final products = await _loadProducts();
    final warehouses = await _loadWarehouses();
    if (!mounted) return _formFields([], []);
    var productOptions = [for (final p in products) '${p['id']} - ${p['name'] ?? p['id']}'];
    var warehouseOptions = [for (final w in warehouses) '${w['id']} - ${w['name'] ?? w['id']}'];
    if (row != null) {
      final pid = '${row['product_id'] ?? ''}';
      if (pid.isNotEmpty && !productOptions.any((o) => o.startsWith('$pid - '))) {
        productOptions = [pid, ...productOptions];
      }
      final wid = '${row['warehouse_id'] ?? ''}';
      if (wid.isNotEmpty && !warehouseOptions.any((o) => o.startsWith('$wid - '))) {
        warehouseOptions = [wid, ...warehouseOptions];
      }
    }
    return _formFields(productOptions, warehouseOptions);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/inventory/alert', data: _buildPayload(data));
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
      initialData: _toEditData(row),
      onSubmit: (data) async {
        await ApiService.instance.put('/admin/v1/inventory/alert/${row['id']}', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['product_name'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/inventory/alert/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields(List<String> productOptions, List<String> warehouseOptions) => [
    FormFieldConfig(
      name: 'product_id',
      label: AppL10n.of(context).fieldProductName,
      required: true,
      type: FormFieldType.dropdown,
      options: productOptions,
    ),
    FormFieldConfig(
      name: 'warehouse_id',
      label: AppL10n.of(context).fieldWarehouse,
      type: FormFieldType.dropdown,
      options: warehouseOptions,
    ),
    FormFieldConfig(name: 'min_quantity', label: AppL10n.of(context).eamMinStock, type: FormFieldType.number),
    FormFieldConfig(name: 'max_quantity', label: AppL10n.of(context).omsPriorityHigh, type: FormFieldType.number),
    FormFieldConfig(
      name: 'enabled',
      label: AppL10n.of(context).commonStatus,
      type: FormFieldType.dropdown,
      options: _enabledOptions,
      initialValue: '1 - ${AppL10n.of(context).commonEnabled}',
    ),
  ];

  List<String> get _enabledOptions {
    final l = AppL10n.of(context);
    return ['1 - ${l.commonEnabled}', '0 - ${l.commonDisabled}'];
  }

  /// 组装后端接收参数：warehouse 留空=0 全部（可选）；sku_id 未暴露保持 0。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    String num(String key) {
      final v = data[key]?.trim();
      return (v == null || v.isEmpty) ? '0' : v;
    }

    return {
      'product_id': pick('product_id'),
      'warehouse_id': data['warehouse_id'] == null || data['warehouse_id']!.isEmpty
          ? '0'
          : pick('warehouse_id'),
      'min_quantity': num('min_quantity'),
      'max_quantity': num('max_quantity'),
      'enabled': pick('enabled'),
    };
  }

  /// 编辑回填：下拉项转「id - 名称」选项文案（数值阈值原样回填）。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final l = AppL10n.of(context);
    final pid = '${row['product_id'] ?? ''}';
    final wid = '${row['warehouse_id'] ?? ''}';
    // 后端以 product_name/warehouse_name 随行带回；名称缺失（0=全部/已删档）时按原始 id 兜底
    final pn = row['product_name'];
    final wn = row['warehouse_name'];
    d['product_id'] = pid.isEmpty ? '' : (pn == null || '$pn'.isEmpty ? pid : '$pid - $pn');
    d['warehouse_id'] = (wid.isEmpty || wid == '0' || wn == null || '$wn'.isEmpty)
        ? ''
        : '$wid - $wn';
    final enabled = row['enabled'];
    d['enabled'] = (enabled is int && enabled == 1) || '$enabled' == '1'
        ? '1 - ${l.commonEnabled}'
        : '0 - ${l.commonDisabled}';
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
    onPageChanged: (p) {
      _page = p;
      _load();
    },
    pageTitle: AppL10n.of(context).inventoryAlertTitle,
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

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [l.fieldProductName, l.fieldWarehouse, l.eamMinStock, l.inventoryAlertMaxQuantity, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.fieldProductName: r['product_name'] ?? '',
      l.fieldWarehouse: r['warehouse_name'] ?? '',
      l.eamMinStock: '${r['min_quantity'] ?? 0}',
      l.inventoryAlertMaxQuantity: '${r['max_quantity'] ?? 0}',
      l.commonStatus: _chip(r['enabled']),
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

  Widget _chip(dynamic enabled) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final on = enabled is int ? enabled == 1 : '$enabled' == '1';
    return StatusBadge(
      label: on ? l.commonEnabled : l.commonDisabled,
      bg: on ? c.successBg : c.warningBg,
      fg: on ? c.successText : c.warningText,
    );
  }
}
