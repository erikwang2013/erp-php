// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';

/// Confirmation dialog for destructive operations. Requires the operator's
/// password and shows a loading state while [onConfirm] runs.
class ConfirmDialog extends StatefulWidget {
  final String title;
  final String? content;
  final String confirmText;
  final String passwordLabel;
  final Future<bool> Function(String password)? onConfirm;

  const ConfirmDialog({
    super.key,
    this.title = '确认删除',
    this.content,
    this.confirmText = '确认删除',
    this.passwordLabel = '请输入您的密码确认',
    this.onConfirm,
  });

  /// Shows the dialog. Resolves to `true` when confirmed (and [onConfirm]
  /// returned true / was not given), `false` when cancelled.
  static Future<bool> show(
    BuildContext context, {
    String title = '确认删除',
    String? content,
    String confirmText = '确认删除',
    String passwordLabel = '请输入您的密码确认',
    Future<bool> Function(String password)? onConfirm,
  }) {
    return showDialog<bool>(
      context: context,
      builder: (_) => ConfirmDialog(
        title: title,
        content: content,
        confirmText: confirmText,
        passwordLabel: passwordLabel,
        onConfirm: onConfirm,
      ),
    ).then((r) => r ?? false);
  }

  @override
  State<ConfirmDialog> createState() => _ConfirmDialogState();
}

class _ConfirmDialogState extends State<ConfirmDialog> {
  final _passwordCtrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    final pwd = _passwordCtrl.text;
    if (pwd.isEmpty) {
      setState(() => _error = AppL10n.current.commonEnterPassword);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final ok = await widget.onConfirm?.call(pwd) ?? true;
      if (!mounted) return;
      if (ok) {
        Navigator.of(context).pop(true);
      } else {
        setState(() => _error = AppL10n.current.commonOpFailedRetry);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _error = AppL10n.current.commonOpFailedMsg('$e'));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    // 桌面居中卡 360 宽;窄屏(<420)不限制宽度避免溢出(§5.7)
    final fixedWidth = MediaQuery.sizeOf(context).width >= 420;
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: fixedWidth ? 360 : double.infinity,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.content != null) ...[
              Text(widget.content!,
                  style: TextStyle(
                      fontSize: 14, color: scheme.onSurfaceVariant)),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _passwordCtrl,
              obscureText: true,
              enabled: !_loading,
              decoration: InputDecoration(
                labelText: widget.passwordLabel,
                isDense: true,
                errorText: _error,
              ),
              onSubmitted: (_) => _confirm(),
            ),
          ],
        ),
      ),
      actions: [
        OutlinedButton(
          onPressed: _loading ? null : () => Navigator.of(context).pop(false),
          child: Text(AppL10n.of(context).commonCancel),
        ),
        // 危险实心(error 槽=2.2 danger 表):确认弹窗主操作,宽≥88(§5.7)
        ElevatedButton(
          onPressed: _loading ? null : _confirm,
          style: ElevatedButton.styleFrom(
            backgroundColor: scheme.error,
            foregroundColor: scheme.onError,
            minimumSize: const Size(88, 36),
          ),
          child: _loading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : Text(widget.confirmText),
        ),
      ],
    );
  }
}
