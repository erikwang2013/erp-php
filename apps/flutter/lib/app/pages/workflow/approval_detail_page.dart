// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 审批实例详情（批5 ⑥）：GET /admin/v1/approval/{id}（show leftJoin workflow
// + node 带出 workflow_name/current_node_name；records[] 带 operator_name）。
// 动作（0审批中可见，写法同我的审批页）：
//   POST /approval/{id}/approve  {comment}（可选）
//   POST /approval/{id}/reject   {comment}（必填）
//   POST /approval/{id}/withdraw
// 动作成功刷新本页并置脏；PopScope 返回时回传 changed=true。target_ref 命中
// 登记域（sales_order→销售订单、purchase_order→采购订单）→「查看单据」跳转
// 对应详情页；否则 target_id 原样展示。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/detail_page.dart';

/// target_type（后端 TARGET_REGISTRY）→ 详情路由。
const Map<String, String> _targetRoute = {
  'sales_order': '/sales/order/detail',
  'purchase_order': '/purchase/order/detail',
};

class ApprovalDetailPage extends StatefulWidget {
  final String? id;
  final String? title;
  const ApprovalDetailPage({super.key, this.id, this.title});

  @override
  State<ApprovalDetailPage> createState() => _ApprovalDetailPageState();
}

class _ApprovalDetailPageState extends State<ApprovalDetailPage> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _changed = false;
  bool _busy = false;

  String get _id =>
      widget.id ??
      ((Get.arguments is Map) ? '${(Get.arguments as Map)['id'] ?? ''}' : '');
  String get _title =>
      widget.title ??
      ((Get.arguments is Map) ? '${(Get.arguments as Map)['title'] ?? ''}' : '');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/approval/$_id');
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(res['data'] ?? {});
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      debugPrint('[approval/detail] 加载失败: $e');
      setState(() => _error = ApiService.friendlyError(e));
    }
  }

  /// 审批意见输入对话框（驳回 comment 必填；写法同 my_approval_page）。
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
                labelText: commentRequired
                    ? AppL10n.of(ctx).workflowCommentRequired
                    : AppL10n.of(ctx).workflowCommentOptional,
                isDense: true,
                errorText: error,
              ),
            );
          }),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: Text(AppL10n.of(ctx).commonCancel)),
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
                if (!mounted) return;
                setState(() => _changed = true);
                _load();
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                    content: Text(AppL10n.of(context).commonOpSuccess)));
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(
                      content:
                          Text(AppL10n.of(ctx).commonOpFailedMsg('$e'))));
                }
              }
            },
            child: Text(AppL10n.of(ctx).commonConfirm),
          ),
        ],
      ),
    );
    // showDialog future 在 pop 时即完成，但路由退场动画期间 TextField 仍引用
    // 控制器 —— 立即 dispose 触发「used after being disposed」（F4）。
    // ponytail: 固定 350ms > 默认 dialog 转场(~200ms)；若换长转场需随之调整。
    await Future<void>.delayed(const Duration(milliseconds: 350));
    commentCtrl.dispose();
  }

  Future<void> _approve() => _commentDialog(
        title: AppL10n.of(context).workflowApproveTitle,
        commentRequired: false,
        onConfirm: (comment) => ApiService.instance.post(
            '/admin/v1/approval/$_id/approve',
            data: {'comment': comment}),
      );

  Future<void> _reject() => _commentDialog(
        title: AppL10n.of(context).workflowRejectTitle,
        commentRequired: true,
        onConfirm: (comment) => ApiService.instance.post(
            '/admin/v1/approval/$_id/reject',
            data: {'comment': comment}),
      );

  Future<void> _withdraw() async {
    final l10n = AppL10n.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.workflowWithdrawTitle),
        content: Text(l10n.workflowWithdrawContent),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: Text(AppL10n.of(ctx).commonCancel)),
          ElevatedButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: Text(AppL10n.of(ctx).workflowWithdraw)),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await ApiService.instance.post('/admin/v1/approval/$_id/withdraw');
      if (!mounted) return;
      setState(() => _changed = true);
      _load();
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.workflowWithdrawn)));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(l10n.workflowWithdrawFailedMsg('$e'))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// target 单据跳转（target_ref 命中登记域时显示）。
  Widget? _viewDocButton(Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    final route = _targetRoute['${d['target_type'] ?? ''}'];
    final ref = '${d['target_ref'] ?? ''}';
    if (route == null || ref.isEmpty) return null;
    return FilledButton.tonalIcon(
      icon: const Icon(Icons.open_in_new, size: 18),
      label: Text(l.detailViewDoc),
      onPressed: () => Get.toNamed(route,
          arguments: {'id': ref, 'title': '${d['target_id'] ?? ''}'}),
    );
  }

  // ---- 展示 ----
  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.of(context).pop(_changed ? true : null);
      },
      child: Scaffold(
        appBar: AppBar(
            title: Text(_title.isNotEmpty ? _title : l.workflowMyApprovalTitle)),
        body: _error != null
            ? Center(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text('${l.commonLoadFailed}：$_error'),
                  const SizedBox(height: 12),
                  ElevatedButton(onPressed: _load, child: Text(l.commonRetry)),
                ]))
            : _data == null
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      DetailCard(title: l.detailBasicInfo,
                          children: _headerRows(context, _data!)),
                      if (_records(_data!).isNotEmpty)
                        DetailCard(title: l.detailRecords,
                            children: _records(_data!)),
                      if (_actions(_data!).isNotEmpty)
                        DetailCard(title: l.commonAction, children: [
                          Wrap(spacing: 12, children: _actions(_data!)),
                          const SizedBox(height: 12),
                          if (_viewDocButton(_data!) != null)
                            _viewDocButton(_data!)!,
                        ]),
                    ],
                  ),
      ),
    );
  }

  List<Widget> _headerRows(BuildContext context, Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    return [
      detailRow(d, l.detailWorkflowName, 'workflow_name'),
      detailRow(d, l.detailCurrentNode, 'current_node_name'),
      detailRow(d, l.detailSubmittedBy,
          'submitter_name'), // show 未 join submitter 名时回退 hashid
      detailRow(d, l.fieldDocType, 'target_type'),
      detailRow(d, l.fieldDocId, 'target_id'),
      detailStatusRow(context, label: l.commonStatus,
          text: _statusText(_asInt(d['status'])),
          bg: _chipColor(context, _asInt(d['status'])).$1,
          fg: _chipColor(context, _asInt(d['status'])).$2),
      if ('${d['submitted_at'] ?? ''}'.isNotEmpty &&
          '${d['submitted_at']}' != 'null')
        detailRow(d, l.fieldSubmitTime, 'submitted_at'),
    ];
  }

  /// 审批记录时间线：操作人 · 动作（1通过/2驳回）＋时间，comment 单独成行。
  List<Widget> _records(Map<String, dynamic> d) {
    final records = List<Map<String, dynamic>>.from(d['records'] ?? []);
    if (records.isEmpty) return const [Text('-')];
    return [
      for (var i = 0; i < records.length; i++) ...[
        if (i > 0) const Divider(height: 16),
        _recordEntry(context, records[i]),
      ],
    ];
  }

  Widget _recordEntry(BuildContext context, Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    final action = switch (_asInt(r['action'])) {
      1 => l.workflowApprove,
      2 => l.workflowReject,
      _ => '',
    };
    final comment = '${r['comment'] ?? ''}';
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(
            child: Text(
              '${r['operator_name'] ?? ''}${action.isEmpty ? '' : ' · $action'}',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          Text('${r['created_at'] ?? ''}',
              style: TextStyle(
                  fontSize: 12, color: AppColors.of(context).textHint)),
        ]),
        if (comment.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(comment,
                style: const TextStyle(fontSize: 13)),
          ),
      ]),
    );
  }

  List<Widget> _actions(Map<String, dynamic> d) {
    final l = AppL10n.of(context);
    if (_asInt(d['status']) != 0) return const [];
    return [
      FilledButton.icon(
          icon: const Icon(Icons.check_circle, size: 18),
          label: Text(l.workflowApprove),
          onPressed: _busy ? null : _approve),
      OutlinedButton.icon(
          icon: const Icon(Icons.cancel, size: 18),
          label: Text(l.workflowReject),
          onPressed: _busy ? null : _reject),
      OutlinedButton.icon(
          icon: const Icon(Icons.undo, size: 18),
          label: Text(l.workflowWithdraw),
          onPressed: _busy ? null : _withdraw),
    ];
  }

  int _asInt(dynamic v) => v is int ? v : (int.tryParse('$v') ?? -1);

  /// 状态文案走 AppL10n.current（与 my_approval_page 同源）。
  String _statusText(int s) {
    final l10n = AppL10n.current;
    return switch (s) {
      0 => l10n.workflowStatusApproving,
      1 => l10n.workflowStatusApproved,
      2 => l10n.workflowStatusRejected,
      3 => l10n.workflowStatusWithdrawn,
      _ => l10n.workflowStatusUnknown,
    };
  }

  // §2.4：0审批中=待办(warning)，1已通过=终态(success)，2驳回/3撤回=失败(danger)
  (Color, Color) _chipColor(BuildContext context, int s) {
    final c = AppColors.of(context);
    return switch (s) {
      0 => (c.warningBg, c.warningText),
      1 => (c.successBg, c.successText),
      2 || 3 => (c.dangerBg, c.dangerText),
      _ => (c.primaryBg, c.primaryPressed),
    };
  }
}
