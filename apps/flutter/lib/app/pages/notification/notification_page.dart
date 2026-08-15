// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';

class NotificationPage extends StatefulWidget {
  const NotificationPage({super.key});
  @override
  State<NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<NotificationPage> {
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
      final res = await ApiService.instance.get('/admin/notification/my', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [
      ElevatedButton.icon(
        onPressed: () async {
          try {
            await ApiService.instance.post('/admin/notification/read-all');
          } catch (e) {
            debugPrint('全部标记已读失败: $e');
          }
          _load();
        },
        icon: const Icon(Icons.mark_email_read, size: 18),
        label: const Text('全部标记已读'),
      ),
    ],
  );

  List<String> _columns() => ['标题', '内容', '时间', '状态'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {
    '标题': r['title'] ?? '',
    '内容': r['content'] ?? '',
    '时间': r['created_at'] ?? '',
    '状态': r['status'] ?? '',
  };
}
