// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../l10n/app_l10n.dart';

class DocumentListPage extends StatefulWidget {
  const DocumentListPage({super.key});
  @override
  State<DocumentListPage> createState() => _DocumentListPageState();
}

class _DocumentListPageState extends State<DocumentListPage> {
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

      final res = await ApiService.instance.get('/admin/v1/dms/document', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
  }

  Future<void> _create() async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonAdd, fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/v1/dms/document', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await FormDialog.show(context, title: l10n.commonEdit, fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/v1/dms/document/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await ConfirmDialog.show(context, title: l10n.commonDeleteConfirm, content: l10n.commonDeleteContent(row['title'] ?? row['code'] ?? '${row['id']}'), onConfirm: (password) async {
      await ApiService.instance.delete('/admin/v1/dms/document/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() {
    final l10n = AppL10n.current;
    return [
      // 分类不是枚举：后端 validate 为 required|string|max:50、列表按 where 精确匹配，
      // Web 两端同为文本输入。原下拉只列 6 个中文值，非选项内的历史值会被表单预填成
      // 空并被 _submit 以 '' 原样提交（'category' => 'string' 放行）→ 编辑即覆盖掉原分类。
      FormFieldConfig(name: 'title', label: l10n.fieldDocTitle, required: true),
      FormFieldConfig(name: 'category', label: l10n.fieldCategory, required: true),
      FormFieldConfig(name: 'content', label: l10n.fieldContent, type: FormFieldType.multiline),
      FormFieldConfig(name: 'tags', label: l10n.fieldTags),
      FormFieldConfig(name: 'change_note', label: l10n.fieldChangeNote),
      FormFieldConfig(name: 'status', label: l10n.commonStatus, type: FormFieldType.dropdown, options: ['0', '1']),
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
    pageTitle: AppL10n.current.dmsDocumentTitle,
    moduleKey: 'dms',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: Text(AppL10n.of(context).commonAdd)),
    ],
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldDocCode, l10n.fieldTitle, l10n.fieldCategory, l10n.fieldVersion, l10n.commonStatus, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    return {
      l10n.fieldDocCode: r['code'] ?? '',
      l10n.fieldTitle: r['title'] ?? '',
      l10n.fieldCategory: r['category'] ?? '', // 展示后端类别值，与 options 同源，不翻译
      l10n.fieldVersion: r['version'] ?? '',
      l10n.commonStatus: r['status'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () => _delete(r)),
      ]),
    };
  }
}
