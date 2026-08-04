// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

enum FormFieldType { text, number, dropdown, multiline }

/// Declarative description of a single form field rendered by [FormDialog].
class FormFieldConfig {
  final String name;
  final String label;
  final String? initialValue;
  final bool required;
  final FormFieldType type;
  final List<String> options;
  final String? hint;

  const FormFieldConfig({
    required this.name,
    required this.label,
    this.initialValue,
    this.required = false,
    this.type = FormFieldType.text,
    this.options = const [],
    this.hint,
  });
}

/// Reusable form dialog: renders fields dynamically from [FormFieldConfig]
/// list, validates required fields and returns true on successful submit.
class FormDialog extends StatefulWidget {
  final String title;
  final List<FormFieldConfig> fields;
  final Future<bool> Function(Map<String, String> values)? onSubmit;
  final String submitText;

  const FormDialog({
    super.key,
    required this.title,
    required this.fields,
    this.onSubmit,
    this.submitText = '提交',
  });

  /// Shows the dialog. Resolves to `true` when the form was submitted
  /// successfully (or no [onSubmit] was given), `false` when cancelled.
  /// When [initialData] is given, matching field values are used to prefill
  /// the dialog (used for edit forms).
  static Future<bool> show(
    BuildContext context, {
    required String title,
    required List<FormFieldConfig> fields,
    Map<String, dynamic>? initialData,
    Future<bool> Function(Map<String, String> values)? onSubmit,
    String submitText = '提交',
  }) {
    final effective = initialData == null
        ? fields
        : [
            for (final f in fields)
              FormFieldConfig(
                name: f.name,
                label: f.label,
                initialValue:
                    '${initialData[f.name] ?? f.initialValue ?? ''}',
                required: f.required,
                type: f.type,
                options: f.options,
                hint: f.hint,
              ),
          ];
    return showDialog<bool>(
      context: context,
      builder: (_) => FormDialog(
        title: title,
        fields: effective,
        onSubmit: onSubmit,
        submitText: submitText,
      ),
    ).then((r) => r ?? false);
  }

  @override
  State<FormDialog> createState() => _FormDialogState();
}

class _FormDialogState extends State<FormDialog> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _controllers;
  final Map<String, String?> _dropdownValues = {};
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _controllers = {
      for (final f in widget.fields) f.name: TextEditingController(text: f.initialValue),
    };
    for (final f in widget.fields) {
      if (f.type == FormFieldType.dropdown) {
        _dropdownValues[f.name] = f.initialValue;
      }
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _loading = true);
    final values = <String, String>{
      for (final f in widget.fields)
        f.name: (f.type == FormFieldType.dropdown
                ? _dropdownValues[f.name]
                : _controllers[f.name]?.text)
            ?.trim() ??
            '',
    };
    try {
      final ok = await widget.onSubmit?.call(values) ?? true;
      if (ok && mounted) Navigator.of(context).pop(true);
    } catch (e) {
      // Keep the dialog open so the user can retry, but surface the error.
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('提交失败：$e')),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.bold)),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final f in widget.fields) ...[
                  _buildField(f),
                  const SizedBox(height: 14),
                ],
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _loading ? null : () => Navigator.of(context).pop(false),
          child: const Text('取消'),
        ),
        ElevatedButton(
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(widget.submitText),
        ),
      ],
    );
  }

  Widget _buildField(FormFieldConfig f) {
    final validator = f.required
        ? (v) => (v == null || v.toString().trim().isEmpty) ? '请输入${f.label}' : null
        : null;
    final label = f.required ? '${f.label} *' : f.label;

    switch (f.type) {
      case FormFieldType.dropdown:
        return DropdownButtonFormField<String>(
          initialValue: _dropdownValues[f.name],
          decoration: InputDecoration(labelText: label, isDense: true),
          items: [
            for (final o in f.options) DropdownMenuItem(value: o, child: Text(o)),
          ],
          onChanged: _loading
              ? null
              : (v) => setState(() => _dropdownValues[f.name] = v),
          validator: validator,
        );
      case FormFieldType.multiline:
        return TextFormField(
          controller: _controllers[f.name],
          enabled: !_loading,
          maxLines: 3,
          decoration: InputDecoration(labelText: label, hintText: f.hint, isDense: true),
          validator: validator,
        );
      case FormFieldType.number:
        return TextFormField(
          controller: _controllers[f.name],
          enabled: !_loading,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: label, hintText: f.hint, isDense: true),
          validator: validator,
        );
      case FormFieldType.text:
        return TextFormField(
          controller: _controllers[f.name],
          enabled: !_loading,
          decoration: InputDecoration(labelText: label, hintText: f.hint, isDense: true),
          validator: validator,
        );
    }
  }
}
