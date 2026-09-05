// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
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

  @override
  void initState() { super.initState(); _load(); }

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
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/dashboard', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
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

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.biDashboardName, required: true),
      FormFieldConfig(name: 'layout', label: l10n.biLayout, type: FormFieldType.multiline),
      FormFieldConfig(name: 'user_id', label: l10n.biUserId, type: FormFieldType.number),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown, options: ['0', '1']),
    ];
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.biDashboardName, l10n.biUserId, l10n.commonStatus, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.biDashboardName: r['name'] ?? '',
      l10n.biUserId: r['user_id'] ?? '',
      l10n.commonStatus: r['status'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.dashboard_customize, size: 18), tooltip: l10n.biChartManage,
          onPressed: () => _manageWidgets(r)),
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
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

  @override
  void initState() { super.initState(); _load(); }

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
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.biChartAdd, fields: _widgetFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/widget', data: {...data, 'dashboard_id': widget.dashboardId});
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> w) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.biChartEdit, fields: _widgetFields(), initialData: w, onSubmit: (data) async {
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

  List<FormFieldConfig> _widgetFields() {
    final l10n = AppL10n.current;
    return [
      FormFieldConfig(name: 'name', label: l10n.biChartName, required: true),
      // 图表类型 options 为后端存储值（bar/line/pie/table 枚举），不参与翻译
      FormFieldConfig(name: 'type', label: l10n.biChartType, required: true, type: FormFieldType.dropdown, options: ['bar', 'line', 'pie', 'table']),
      FormFieldConfig(name: 'dataset_id', label: l10n.biDatasetId, type: FormFieldType.number),
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
            Text(l10n.biChartCount(_widgets.length), style: const TextStyle(color: Colors.grey)),
          ]),
          const SizedBox(height: 8),
          if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
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
                      subtitle: Text(l10n.biChartTypeLabel('${w['type'] ?? ''}')),
                      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(w)),
                        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(w)),
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
