// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../l10n/app_l10n.dart';

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
  String? _error;
  int _reqSeq = 0;

  // 审批实例状态: 0审批中 1已通过 2已驳回 3已撤回

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit'};
      final res = await ApiService.instance.get('/admin/v1/approval/my', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; _error = null; });
      if (_rows.isEmpty && _page > 1) { _page--; _load(); return; }
    } catch (e) { if (mounted) setState(() { _loading = false; _error = ApiService.friendlyError(e); }); }
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
                labelText: commentRequired ? AppL10n.of(ctx).workflowCommentRequired : AppL10n.of(ctx).workflowCommentOptional,
                isDense: true,
                errorText: error,
              ),
            );
          }),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(AppL10n.of(ctx).commonCancel)),
          ElevatedButton(
            onPressed: () async {
              final comment = commentCtrl.text.trim();
              if (commentRequired && comment.isEmpty) {
                error = AppL10n.of(ctx).workflowCommentRequiredError;
                refresh?.call(() {});
                return;
              }
              try {
                await onConfirm(comment);
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppL10n.of(context).commonOpSuccess)));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(AppL10n.of(ctx).commonOpFailedMsg('$e'))));
                }
              }
            },
            child: Text(AppL10n.of(ctx).commonConfirm),
          ),
        ],
      ),
    );
    commentCtrl.dispose();
  }

  Future<void> _approve(Map<String, dynamic> row) => _commentDialog(
    title: AppL10n.current.workflowApproveTitle, commentRequired: false,
    onConfirm: (comment) => ApiService.instance.post('/admin/v1/approval/${row['id']}/approve', data: {'comment': comment}),
  );

  Future<void> _reject(Map<String, dynamic> row) => _commentDialog(
    title: AppL10n.current.workflowRejectTitle, commentRequired: true,
    onConfirm: (comment) => ApiService.instance.post('/admin/v1/approval/${row['id']}/reject', data: {'comment': comment}),
  );

  Future<void> _withdraw(Map<String, dynamic> row) async {
    final l10n = AppL10n.current;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.workflowWithdrawTitle),
        content: Text(l10n.workflowWithdrawContent),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: Text(AppL10n.of(ctx).commonCancel)),
          ElevatedButton(
            onPressed: () async {
              try {
                await ApiService.instance.post('/admin/v1/approval/${row['id']}/withdraw');
                if (ctx.mounted) Navigator.of(ctx).pop();
                _load();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppL10n.of(context).workflowWithdrawn)));
                }
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(AppL10n.of(ctx).workflowWithdrawFailedMsg('$e'))));
                }
              }
            },
            child: Text(AppL10n.of(ctx).workflowWithdraw),
          ),
        ],
      ),
    );
  }

  String _statusText(dynamic s) {
    final l10n = AppL10n.current;
    final i = s is int ? s : int.tryParse('$s');
    return switch (i) {
      0 => l10n.workflowStatusApproving,
      1 => l10n.workflowStatusApproved,
      2 => l10n.workflowStatusRejected,
      3 => l10n.workflowStatusWithdrawn,
      _ => l10n.workflowStatusUnknown,
    };
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading, error: _error, onRetry: _load,
    keyword: _keyword,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
  );

  List<String> _columns() {
    final l10n = AppL10n.current;
    return [l10n.fieldDocType, l10n.fieldDocId, l10n.commonStatus, l10n.fieldSubmitTime, l10n.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l10n = AppL10n.current;
    final statusVal = r['status'] is int ? r['status'] as int : int.tryParse('${r['status']}');
    final pending = statusVal == 0;
    return {
      l10n.fieldDocType: r['target_type'] ?? '',
      l10n.fieldDocId: r['target_id'] ?? '',
      l10n.commonStatus: _chip(r['status']),
      l10n.fieldSubmitTime: r['submitted_at'] ?? '',
      l10n.commonAction: Row(mainAxisSize: MainAxisSize.min, children: [
        if (pending) ...[
          IconButton(icon: const Icon(Icons.check_circle, size: 18, color: Colors.green),
            tooltip: l10n.workflowApprove, onPressed: () => _approve(r)),
          IconButton(icon: const Icon(Icons.cancel, size: 18, color: Colors.red),
            tooltip: l10n.workflowReject, onPressed: () => _reject(r)),
          IconButton(icon: const Icon(Icons.undo, size: 18, color: Colors.orange),
            tooltip: l10n.workflowWithdraw, onPressed: () => _withdraw(r)),
        ] else
          const Text('—'),
      ]),
    };
  }

  Widget _chip(dynamic s) {
    final i = s is int ? s : int.tryParse('$s');
    // 颜色按状态枚举匹配，标签走 l10n，避免按译文字符串判色
    final color = switch (i) {
      0 => Colors.orange,
      1 => Colors.green,
      2 || 3 => Colors.red,
      _ => Colors.blue,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(_statusText(s), style: TextStyle(color: color, fontSize: 12)),
    );
  }
}
