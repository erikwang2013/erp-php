// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';

/// 我的审批页 — 覆盖 GET /admin/approval/my 及动作端点：
/// POST /admin/approval/{id}/approve
/// POST /admin/approval/{id}/reject
/// POST /admin/approval/{id}/withdraw
class MyApprovalPage extends StatefulWidget {
  const MyApprovalPage({super.key});
  @override
  State<MyApprovalPage> createState() => _MyApprovalPageState();
}

class _MyApprovalPageState extends State<MyApprovalPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  bool _loading = true;

  // 审批实例状态: 0审批中 1已通过 2已驳回 3已撤回
  static const List<String> _statusLabels = ['审批中', '已通过', '已驳回', '已撤回'];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/v1/approval/my', params: params);
      final d = res['data'];
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
    } catch (e) { setState(() => _loading = false); }
  }

  /// 审批意见输入对话框（驳回时 comment 必填）。
  Future<void> _commentDialog({
    required String title,
    required bool commentRequired,
    required Future<void> Function(String comment) onConfirm,
  }) async {
    final commentCtrl = TextEditingController();
    String? error;
    void Function(void Function())? refresh;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: SizedBox(
          width: 360,
          child: StatefulBuilder(builder: (ctx2, setLocal) {
            refresh = setLocal;
            return TextField(
              controller: commentCtrl,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: commentRequired ? '审批意见（必填）' : '审批意见（可选）',
                isDense: true,
                errorText: error,
              ),
            );
          }),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('取消')),
          ElevatedButton(
            onPressed: () async {
              final comment = commentCtrl.text.trim();
              if (commentRequired && comment.isEmpty) {
                error = '驳回意见不能为空';
                refresh?.call(() {});
                return;
              }
              try {
                await onConfirm(comment);
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('操作成功')));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('操作失败：$e')));
                }
              }
            },
            child: const Text('确认'),
          ),
        ],
      ),
    );
    commentCtrl.dispose();
  }

  Future<void> _approve(Map<String, dynamic> row) => _commentDialog(
    title: '审批通过', commentRequired: false,
    onConfirm: (comment) => ApiService.instance.post('/admin/v1/approval/${row['id']}/approve', data: {'comment': comment}),
  );

  Future<void> _reject(Map<String, dynamic> row) => _commentDialog(
    title: '驳回审批', commentRequired: true,
    onConfirm: (comment) => ApiService.instance.post('/admin/v1/approval/${row['id']}/reject', data: {'comment': comment}),
  );

  Future<void> _withdraw(Map<String, dynamic> row) async {
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('撤回审批'),
        content: const Text('确定要撤回该审批吗？仅提交人可操作。'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('取消')),
          ElevatedButton(
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/v1/approval/${row['id']}/withdraw');
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('已撤回')));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('撤回失败：$e')));
                }
              }
            },
            child: const Text('撤回'),
          ),
        ],
      ),
    );
  }

  static String _statusText(dynamic s) {
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    return (i >= 0 && i < _statusLabels.length) ? _statusLabels[i] : '$s';
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
  );

  List<String> _columns() => ['单据类型', '单据ID', '状态', '提交时间', '操作'];

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final pending = (r['status'] is int ? r['status'] as int : int.tryParse('${r['status']}') ?? 0) == 0;
    return {
      '单据类型': r['target_type'] ?? '',
      '单据ID': r['target_id'] ?? '',
      '状态': _chip(_statusText(r['status'])),
      '提交时间': r['submitted_at'] ?? '',
      '操作': Row(mainAxisSize: MainAxisSize.min, children: [
        if (pending) ...[
          IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green),
            tooltip: '通过', onPressed: () => _approve(r)),
          IconButton(icon: const Icon(Icons.cancel, size: 18, color: Colors.red),
            tooltip: '驳回', onPressed: () => _reject(r)),
          IconButton(icon: const Icon(Icons.undo, size: 18, color: Colors.orange),
            tooltip: '撤回', onPressed: () => _withdraw(r)),
        ] else
          const Text('—'),
      ]),
    };
  }

  Widget _chip(String? s) {
    final color = switch (s) {
      '审批中' => Colors.orange,
      '已通过' => Colors.green,
      '已驳回' || '已撤回' => Colors.red,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(s ?? '', style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
