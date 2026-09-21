// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class PutawayPage extends StatefulWidget {
  const PutawayPage({super.key});
  @override
  State<PutawayPage> createState() => _PutawayPageState();
}

class _PutawayPageState extends State<PutawayPage> {
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
        '/admin/v1/wms/putaway',
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

  /// 仓库下拉：后端建单必填外键（install.sql erp_wms_putaway_task `warehouse_id` NOT NULL
  /// 无默认，控制器只校验 code）。选项值=行 id（hashid，符合对外 hashid 契约）。
  /// 加载失败返回 false（弹提示）而不是静默空下拉，避免必填项无处可选。
  Map<String, String> _warehouses = {};

  Future<bool> _ensureRefs() async {
    try {
      final wh = await ApiService.instance.get('/admin/v1/warehouse', params: {'limit': '500'});
      _warehouses = {
        for (final r in List<Map<String, dynamic>>.from((wh['data'] ?? {})['list'] ?? []))
          '${r['id']}': '${r['name'] ?? r['code'] ?? r['id']}',
      };
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
      return false;
    }
  }

  /// 作业动作（开始/完成类）：二次确认后 POST，成功后刷新列表。
  /// 按钮由行 status 门控（后端状态机：0=待上架 1=上架中 2=已完成，complete 要求 1）。
  Future<void> _runAction(String label, String path) async {
    final l10n = AppL10n.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(label),
        content: Text(l10n.detailConfirmOp(label)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: Text(l10n.commonCancel)),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: Text(l10n.commonConfirm)),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await ApiService.instance.post(path);
      _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.commonOpSuccess)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
    }
  }

  Future<void> _create() async {
    if (!await _ensureRefs() || !mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: _formFields(),
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/wms/putaway', data: data);
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    if (!await _ensureRefs() || !mounted) return;
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: _formFields(),
      initialData: row,
      onSubmit: (data) async {
        await ApiService.instance.put(
          '/admin/v1/wms/putaway/${row['id']}',
          data: data,
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
      ).commonDeleteMsg('${row['name'] ?? row['code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete(
          '/admin/v1/wms/putaway/${row['id']}',
          data: {'password': password},
        );
        _load();
        return true;
      },
    );
  }

  /// erp_wms_putaway_task 无 `name` 列（原 name 字段提交后被后端丢弃）：只留真实列与必填外键。
  /// code 后端 store() 校验 required（自生成分支因此不可达）。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'code', label: AppL10n.of(context).commonCode, required: true),
    FormFieldConfig(
      name: 'warehouse_id',
      label: AppL10n.of(context).fieldWarehouse,
      required: true,
      type: FormFieldType.dropdown,
      options: _warehouses.keys.toList(),
      optionLabels: _warehouses,
    ),
  ];

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
    pageTitle: AppL10n.of(context).wmsPutawayTitle,
    moduleKey: 'wms',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  /// erp_wms_putaway_task 无 `name` 列 → 原「名称」列恒空，只留真实列：单号 + 操作。
  List<String> _columns() => [
    AppL10n.of(context).wmsDocNo,
    AppL10n.of(context).commonAction,
  ];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.wmsDocNo: r['code'] ?? '',
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if ('${r['status']}' == '0')
            IconButton(
              icon: Icon(Icons.play_arrow, size: 18, color: AppColors.of(context).success),
              tooltip: l.wmsPutawayStart,
              onPressed: () => _runAction(l.wmsPutawayStart, '/admin/v1/wms/putaway/${r['id']}/start'),
            ),
          if ('${r['status']}' == '1')
            IconButton(
              icon: Icon(Icons.check_circle, size: 18, color: AppColors.of(context).success),
              tooltip: l.wmsPutawayComplete,
              onPressed: () => _runAction(l.wmsPutawayComplete, '/admin/v1/wms/putaway/${r['id']}/complete'),
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
