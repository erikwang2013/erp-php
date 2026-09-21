// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class RmaListPage extends StatefulWidget {
  const RmaListPage({super.key});
  @override
  State<RmaListPage> createState() => _RmaListPageState();
}

class _RmaListPageState extends State<RmaListPage> {
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
        '/admin/v1/oms/rma',
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

  /// 客户/订单下拉：各取 limit=500（失败降级空表）。
  Future<List<String>> _optionList(String endpoint, String Function(Map<String, dynamic>) label) async {
    try {
      final res = await ApiService.instance.get(endpoint, params: {'limit': '500'});
      final rows = List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
      return [for (final r in rows) '${r['id']} - ${label(r)}'];
    } catch (_) {
      return [];
    }
  }

  /// 弹窗前预取客户/订单（编辑时补一行原值防回填落空）。
  Future<List<FormFieldConfig>> _fieldsFor({Map<String, dynamic>? row}) async {
    var customers = await _optionList('/admin/v1/customer', (r) => '${r['name'] ?? r['id']}');
    var orders = await _optionList('/admin/v1/sales/order', (r) => '${r['code'] ?? r['id']}');
    if (!mounted) return _formFields([], []);
    final cid = '${row?['customer_id'] ?? ''}';
    if (cid.isNotEmpty && !customers.any((o) => o.startsWith('$cid - '))) {
      customers = [cid, ...customers];
    }
    final oid = '${row?['order_id'] ?? ''}';
    if (oid.isNotEmpty && !orders.any((o) => o.startsWith('$oid - '))) {
      orders = [oid, ...orders];
    }
    return _formFields(customers, orders);
  }

  Future<void> _create() async {
    final fields = await _fieldsFor();
    if (!mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: fields,
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/oms/rma', data: _buildPayload(data));
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
          '/admin/v1/oms/rma/${row['id']}',
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
          '/admin/v1/oms/rma/${row['id']}',
          data: {'password': password},
        );
        _load();
        return true;
      },
    );
  }

  // erp_oms_rma 无 name 列，且 order_id/customer_id 为 NOT NULL 无默认（install.sql）；
  // store 对两者的 decodeFlexibleId 失败即 422 "Invalid customer/order" —— 原 'name'
  // 幻字段之外，真正必填的是客户 + 关联订单。字段对齐 Web 两端（React fulfill 域
  // 已把 order_id 标 required；Angular 本次同步补上）。code 留空由后端生成。
  List<FormFieldConfig> _formFields(List<String> customerOptions, List<String> orderOptions) => [
    FormFieldConfig(name: 'code', label: AppL10n.of(context).commonCode),
    FormFieldConfig(
      name: 'customer_id',
      label: AppL10n.of(context).fieldCustomer,
      required: true,
      type: FormFieldType.dropdown,
      options: customerOptions,
    ),
    FormFieldConfig(
      name: 'order_id',
      label: AppL10n.of(context).detailOrderRef,
      required: true,
      type: FormFieldType.dropdown,
      options: orderOptions,
    ),
    FormFieldConfig(
      name: 'refund_amount',
      label: AppL10n.of(context).omsRmaRefundAmount,
      type: FormFieldType.number,
    ),
  ];

  /// 组装后端接收参数：下拉项 'id - 名称' 取回 hashid。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    return {
      'code': data['code']?.trim() ?? '',
      'customer_id': pick('customer_id'),
      'order_id': pick('order_id'),
      'refund_amount': data['refund_amount']?.trim() ?? '',
    };
  }

  /// 编辑回填：下拉值必须与 options 字符串完全一致（FormDialog 匹配不上会置空），
  /// 而列表接口不带客户/订单号，故按 id 前缀在选项里找 'id - 名称'、否则退回裸 id。
  Map<String, dynamic> _toEditData(
    Map<String, dynamic> row,
    List<FormFieldConfig> fields,
  ) {
    final d = Map<String, dynamic>.from(row);
    for (final key in ['customer_id', 'order_id']) {
      final options = fields.firstWhere((f) => f.name == key).options;
      final id = '${row[key] ?? ''}';
      d[key] = id.isEmpty
          ? ''
          : options.firstWhere((o) => o.startsWith('$id - '), orElse: () => id);
    }
    return d;
  }

  /// 详情页入口：动作成功后详情页回传 changed=true → 刷新本列表。
  Future<void> _detail(Map<String, dynamic> row) async {
    final changed = await Get.toNamed('/oms/rma/detail', arguments: {
      'id': '${row['id']}',
      'title': '${row['code'] ?? ''}',
    });
    if (changed == true && mounted) _load();
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
    pageTitle: AppL10n.of(context).omsRmaTitle,
    moduleKey: 'oms',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  // 列表接口不 join 客户名：客户列按原值（hashid）展示，与 Web 两端列集
  // （编号/退款金额/状态）保持一致取向。
  List<String> _columns() => [
    AppL10n.of(context).commonCode,
    AppL10n.of(context).fieldCustomer,
    AppL10n.of(context).omsRmaRefundAmount,
    AppL10n.of(context).commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.commonCode: r['code'] ?? '',
      l.fieldCustomer: r['customer_id'] ?? '',
      l.omsRmaRefundAmount: r['refund_amount'] ?? '',
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.visibility_outlined, size: 18),
            tooltip: l.commonDetail,
            onPressed: () => _detail(r),
          ),
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
