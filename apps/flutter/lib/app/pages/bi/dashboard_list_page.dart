// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class DashboardListPage extends StatefulWidget {
  const DashboardListPage({super.key});
  @override
  State<DashboardListPage> createState() => _DashboardListPageState();
}

class _DashboardListPageState extends State<DashboardListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 用户下拉：user_id 是后端 hashid 契约（UserController::index 出口 encodeIds），
  /// 手输数字与编辑态回写的 hashid 直灌 BIGINT 列会以 1366 崩；选项值=行 id(hashid)，
  /// 标签取用户名。该字段可空（空=不指定），预取失败不阻断弹窗。
  Map<String, String> _users = {};

  Future<void> _ensureRefs() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/user', params: {'limit': '500'});
      final options = {
        for (final r in List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []))
          '${r['id']}': '${r['username'] ?? r['real_name'] ?? r['id']}',
      };
      if (mounted) {
        setState(() => _users = options);
      } else {
        _users = options;
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiService.friendlyError(e))));
      }
    }
  }

  @override
  void initState() { super.initState(); _load(); _ensureRefs(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/bi/dashboard', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    await _ensureRefs();
    if (!mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(_users), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/dashboard', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await _ensureRefs();
    if (!mounted) return;
    final options = dropdownOptionsWithCurrent(_users, row['user_id']);
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(options), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/bi/dashboard/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent('${row['name'] ?? row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/bi/dashboard/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields(Map<String, String> userOptions) {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.biDashboardName, required: true),
      FormFieldConfig(name: 'layout', label: l10n.biLayout, type: FormFieldType.multiline),
      FormFieldConfig(name: 'user_id', label: l10n.biUserId,
          type: FormFieldType.dropdown, options: userOptions.keys.toList(), optionLabels: userOptions),
      // 显示文案走 optionLabels（值=存储值不变），词表同本页状态列：启用/停用
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown,
          options: ['0', '1'], optionLabels: {for (final v in const ['0', '1']) v: _statusLabel(v)}),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    pageTitle: AppL10n.current.biDashboardTitle,
    moduleKey: 'bi',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.biDashboardName, l10n.biUserId, l10n.commonStatus, l10n.commonAction];
  }

  /// 状态列 0/1 机读值不上屏（DashboardController::index apidoc：0=停用 1=启用），未知值原样回落。
  static String _statusLabel(Object? v) => switch ('$v') {
        '0' => AppL10n.current.commonDisabled,
        '1' => AppL10n.current.commonEnabled,
        _ => '$v',
      };

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.biDashboardName: r['name'] ?? '',
      l10n.biUserId: _users['${r['user_id']}'] ?? '-',
      l10n.commonStatus: _statusLabel(r['status']),
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.dashboard_customize, size: 18), tooltip: l10n.biChartManage,
          onPressed: () => _manageWidgets(r)),
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }

  /// 图表管理弹窗 — 覆盖 /admin/bi/widget 增删改查
  Future<void> _manageWidgets(Map<String, dynamic> row) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _WidgetManagerDialog(
        dashboardId: '${row['id']}',
        dashboardName: '${row['name'] ?? ''}',
      ),
    );
  }
}

/// 看板图表管理弹窗：列表 + 新增/编辑/删除
class _WidgetManagerDialog extends StatefulWidget {
  final String dashboardId;
  final String dashboardName;
  const _WidgetManagerDialog({required this.dashboardId, required this.dashboardName});
  @override
  State<_WidgetManagerDialog> createState() => _WidgetManagerDialogState();
}

class _WidgetManagerDialogState extends State<_WidgetManagerDialog> {
  List<Map<String, dynamic>> _widgets = [];
  bool _loading = true;
  String? _error;

  /// 数据集下拉：dataset_id 同为用户下拉式 hashid 外键（DatasetController::index 出口
  /// encodeIds），手输数字/回写 hashid 直灌 BIGINT 列会崩；选项值=行 id(hashid)，标签取名称。
  /// 弹窗内预取失败走本弹窗的 _error（SnackBar 会被弹窗遮罩挡住）。
  Map<String, String> _datasets = {};

  Future<void> _ensureRefs() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/bi/dataset', params: {'limit': '500'});
      final options = {
        for (final r in List<Map<String, dynamic>>.from((res['data'] ?? {})['list'] ?? []))
          '${r['id']}': '${r['name'] ?? r['id']}',
      };
      if (mounted) {
        setState(() => _datasets = options);
      } else {
        _datasets = options;
      }
    } catch (e) {
      if (mounted) setState(() => _error = ApiService.friendlyError(e));
    }
  }

  @override
  void initState() { super.initState(); _load(); _ensureRefs(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ApiService.instance.get('/admin/v1/bi/widget', params: {'dashboard_id': widget.dashboardId});
      setState(() { _widgets = List<Map<String, dynamic>>.from(res['data']['list'] ?? []); _loading = false; });
    } catch (e) {
      setState(() { _loading = false; _error = '$e'; });
    }
  }

  Future<void> _create() async {
    await _ensureRefs();
    if (!mounted) return;
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.biChartAdd, fields: _widgetFields(_datasets), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/widget', data: {...data, 'dashboard_id': widget.dashboardId});
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> w) async {
    await _ensureRefs();
    if (!mounted) return;
    final options = dropdownOptionsWithCurrent(_datasets, w['dataset_id']);
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.biChartEdit, fields: _widgetFields(options), initialData: w, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/bi/widget/${w['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> w) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.biChartDeleteContent('${w['name'] ?? ''}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/bi/widget/${w['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  /// 图表类型值域 = erp_report_template.chart_type 的 DDL 注释
  /// `图表类型: table/bar/line/pie/kpi`（install.sql:2933），文案取 Web 端 REPORT_DICTS.chart_type。
  /// kpi 不在本弹窗表单 options 内（无对应图表实现、不新增提交值），但存量行可能带该值，
  /// 故词表仍收全（列表副行照翻），表外值由末臂原样回落。
  static String _chartTypeLabel(Object? v) => switch ('$v') {
        'table' => AppL10n.current.biChartTypeTable,
        'bar' => AppL10n.current.biChartTypeBar,
        'line' => AppL10n.current.biChartTypeLine,
        'pie' => AppL10n.current.biChartTypePie,
        'kpi' => AppL10n.current.biChartTypeKpi,
        _ => '$v',
      };

  List<FormFieldConfig> _widgetFields(Map<String, String> datasetOptions) {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.biChartName, required: true),
      // 图表类型：值=后端存储值原样提交，显示文案走词表（同 erp_report_template.chart_type 词表）
      FormFieldConfig(name: 'type', label: l10n.biChartType, required: true, type: FormFieldType.dropdown,
          options: ['bar', 'line', 'pie', 'table'],
          optionLabels: {for (final v in const ['bar', 'line', 'pie', 'table']) v: _chartTypeLabel(v)}),
      FormFieldConfig(name: 'dataset_id', label: l10n.biDatasetId,
          type: FormFieldType.dropdown, options: datasetOptions.keys.toList(), optionLabels: datasetOptions),
      FormFieldConfig(name: 'config', label: l10n.biChartConfig, type: FormFieldType.multiline),
      FormFieldConfig(name: 'position_x', label: l10n.biPositionX, type: FormFieldType.number),
      FormFieldConfig(name: 'position_y', label: l10n.biPositionY, type: FormFieldType.number),
      FormFieldConfig(name: 'width', label: l10n.biWidth, type: FormFieldType.number),
      FormFieldConfig(name: 'height', label: l10n.biHeight, type: FormFieldType.number),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return AlertDialog(
      title: Text(l10n.biChartManageTitle(widget.dashboardName), style: const TextStyle(fontWeight: FontWeight.bold)),
      content: SizedBox(
        width: 480,
        height: 420,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(l10n.biChartAdd)),
            const Spacer(),
            Text(l10n.biChartCount(_widgets.length), style: TextStyle(color: AppColors.of(context).textHint)),
          ]),
          const SizedBox(height: 8),
          if (_error != null) Text(_error!, style: TextStyle(color: AppColors.of(context).danger)),
          Expanded(child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _widgets.isEmpty
              ? Center(child: Text(l10n.biChartEmpty))
              : ListView.separated(
                  itemCount: _widgets.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final w = _widgets[i];
                    return ListTile(
                      dense: true,
                      title: Text('${w['name'] ?? ''}'),
                      subtitle: Text(l10n.biChartTypeLabel(_chartTypeLabel(w['type']))),
                      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(w)),
                        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(w)),
                      ]),
                    );
                  },
                )),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l10n.commonClose)),
      ],
    );
  }
}
