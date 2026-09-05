/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../l10n/app_l10n.dart';

class UserFormPage extends StatefulWidget {
  final Map<String, dynamic>? userData;
  const UserFormPage({super.key, this.userData});

  @override
  State<UserFormPage> createState() => _UserFormPageState();
}

class _UserFormPageState extends State<UserFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _realNameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  int _status = 1;
  bool _isLoading = false;

  bool get isEdit => widget.userData != null;

  @override
  void initState() {
    super.initState();
    if (isEdit) {
      _usernameCtrl.text = widget.userData!['username'] ?? '';
      _realNameCtrl.text = widget.userData!['real_name'] ?? '';
      _phoneCtrl.text = widget.userData!['phone'] ?? '';
      _emailCtrl.text = widget.userData!['email'] ?? '';
      _status = widget.userData!['status'] ?? 1;
    }
  }

  Future<void> _submit() async {
    final l10n = AppL10n.current;
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _isLoading = true);

    final data = {
      'real_name': _realNameCtrl.text.trim(),
      'status': _status,
      'phone': _phoneCtrl.text.trim(),
      'email': _emailCtrl.text.trim(),
    };
    if (!isEdit) {
      data['username'] = _usernameCtrl.text.trim();
      data['password'] = _passwordCtrl.text;
    } else if (_passwordCtrl.text.isNotEmpty) {
      data['password'] = _passwordCtrl.text;
    }

    try {
      final api = ApiService();
      if (isEdit) {
        await api.put('/admin/v1/user/${widget.userData!['id']}', data: data);
      } else {
        await api.post('/admin/v1/user', data: data);
      }
      Get.snackbar(l10n.commonSnackSuccess, isEdit ? l10n.systemUserUpdated : l10n.systemUserCreated);
      Get.back(result: true);
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.commonOpFailedMsg('$e'));
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(isEdit ? l10n.systemUserEdit : l10n.systemUserAdd)),
      body: Center(
        child: SizedBox(
          width: 500,
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                TextFormField(controller: _usernameCtrl, enabled: !isEdit, decoration: InputDecoration(labelText: l10n.fieldUsername), validator: (v) => (v == null || v.isEmpty) ? l10n.commonInputRequired(l10n.fieldUsername) : null),
                const SizedBox(height: 16),
                TextFormField(controller: _passwordCtrl, obscureText: true, decoration: InputDecoration(labelText: isEdit ? l10n.userPwdEditHint : l10n.userPwdNewLabel), validator: (v) => !isEdit && (v == null || v.isEmpty) ? l10n.commonEnterPassword : null),
                const SizedBox(height: 16),
                TextFormField(controller: _realNameCtrl, decoration: InputDecoration(labelText: l10n.fieldRealNameFull), validator: (v) => (v == null || v.isEmpty) ? l10n.commonInputRequired(l10n.fieldRealNameFull) : null),
                const SizedBox(height: 16),
                TextFormField(controller: _phoneCtrl, decoration: InputDecoration(labelText: l10n.fieldPhone)),
                const SizedBox(height: 16),
                TextFormField(controller: _emailCtrl, decoration: InputDecoration(labelText: l10n.fieldEmail)),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(initialValue: _status, decoration: InputDecoration(labelText: l10n.commonStatus), items: [
                  DropdownMenuItem(value: 1, child: Text(l10n.commonEnabled)),
                  DropdownMenuItem(value: 0, child: Text(l10n.commonDisabled)),
                ], onChanged: (v) => setState(() => _status = v ?? 1)),
                const SizedBox(height: 24),
                ElevatedButton(onPressed: _isLoading ? null : _submit, child: Text(_isLoading ? l10n.commonSubmitting : l10n.commonSubmit)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
