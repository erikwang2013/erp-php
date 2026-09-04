// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

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

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};

      final res = await ApiService.instance.get('/admin/v1/bi/dashboard', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/dashboard', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/bi/dashboard/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/bi/dashboard/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: '看板名称', required: true),
    FormFieldConfig(name: 'layout', label: '布局配置', type: FormFieldType.multiline),
    FormFieldConfig(name: 'user_id', label: '用户ID', type: FormFieldType.number),
    FormFieldConfig(name: 'status', label: '状态', type: FormFieldType.dropdown, options: ['0', '1']),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增')),
    ],
  );

  List<String> _columns() => ['看板名称', '用户ID', '状态', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '看板名称': r['name'] ?? '',
    '用户ID': r['user_id'] ?? '',
    '状态': r['status'] ?? '',
    '操作': Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(icon: const Icon(Icons.dashboard_customize, size: 18), tooltip: '图表管理',
        onPressed: () => _manageWidgets(r)),
      IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
      IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
    ]),
  };

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
    await FormDialog.show(context, title: '新增图表', fields: _widgetFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/bi/widget', data: {...data, 'dashboard_id': widget.dashboardId});
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> w) async {
    await FormDialog.show(context, title: '编辑图表', fields: _widgetFields(), initialData: w, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/bi/widget/${w['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> w) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除图表「${w['name'] ?? ''}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/bi/widget/${w['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _widgetFields() => const [
    FormFieldConfig(name: 'name', label: '图表名称', required: true),
    FormFieldConfig(name: 'type', label: '图表类型', required: true, type: FormFieldType.dropdown, options: ['bar', 'line', 'pie', 'table']),
    FormFieldConfig(name: 'dataset_id', label: '数据集ID', type: FormFieldType.number),
    FormFieldConfig(name: 'config', label: '配置JSON', type: FormFieldType.multiline),
    FormFieldConfig(name: 'position_x', label: 'X坐标', type: FormFieldType.number),
    FormFieldConfig(name: 'position_y', label: 'Y坐标', type: FormFieldType.number),
    FormFieldConfig(name: 'width', label: '宽度', type: FormFieldType.number),
    FormFieldConfig(name: 'height', label: '高度', type: FormFieldType.number),
  ];

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('图表管理 — ${widget.dashboardName}', style: const TextStyle(fontWeight: FontWeight.bold)),
      content: SizedBox(
        width: 480,
        height: 420,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增图表')),
            const Spacer(),
            Text('共 ${_widgets.length} 个', style: const TextStyle(color: Colors.grey)),
          ]),
          const SizedBox(height: 8),
          if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
          Expanded(child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _widgets.isEmpty
              ? const Center(child: Text('暂无图表，点击「新增图表」创建'))
              : ListView.separated(
                  itemCount: _widgets.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final w = _widgets[i];
                    return ListTile(
                      dense: true,
                      title: Text('${w['name'] ?? ''}'),
                      subtitle: Text('类型: ${w['type'] ?? ''}'),
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
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('关闭')),
      ],
    );
  }
}
