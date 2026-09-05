/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../l10n/app_l10n.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final _api = ApiService();
  final _realNameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  // 脏字段跟踪：后端 PUT 为「has→覆盖」语义，未修改的字段不应提交，
  // 否则只改邮箱会把服务器上已有的姓名/手机号覆盖成空串。
  bool _nameDirty = false, _phoneDirty = false, _emailDirty = false;

  @override
  void dispose() {
    _realNameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    super.dispose();
  }

  Future<void> _updateProfile() async {
    final l10n = AppL10n.current;
    final data = <String, dynamic>{
      if (_nameDirty) 'real_name': _realNameCtrl.text.trim(),
      if (_phoneDirty) 'phone': _phoneCtrl.text.trim(),
      if (_emailDirty) 'email': _emailCtrl.text.trim(),
    };
    if (data.isEmpty) {
      Get.snackbar(l10n.commonSnackInfo, l10n.profileNoChanges);
      return;
    }
    try {
      await _api.put('/admin/v1/profile', data: data);
      setState(() { _nameDirty = false; _phoneDirty = false; _emailDirty = false; });
      Get.snackbar(l10n.commonSnackSuccess, l10n.profileUpdateSuccess);
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.profileUpdateFailedMsg('$e'));
    }
  }

  Future<void> _changePassword() async {
    final l10n = AppL10n.current;
    final oldPwdCtrl = TextEditingController();
    final newPwdCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(l10n.profileChangePassword),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: oldPwdCtrl, obscureText: true, decoration: InputDecoration(labelText: l10n.profileOldPassword)),
          TextField(controller: newPwdCtrl, obscureText: true, decoration: InputDecoration(labelText: l10n.profileNewPassword)),
          TextField(controller: confirmCtrl, obscureText: true, decoration: InputDecoration(labelText: l10n.profileConfirmPassword)),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.commonConfirm)),
        ],
      ),
    );

    if (ok != true) return;
    if (newPwdCtrl.text != confirmCtrl.text) {
      Get.snackbar(l10n.commonSnackError, l10n.profilePwdMismatch);
      return;
    }

    try {
      await _api.put('/admin/v1/profile/password', data: {
        'old_password': oldPwdCtrl.text,
        'new_password': newPwdCtrl.text,
      });
      Get.snackbar(l10n.commonSnackSuccess, l10n.profilePwdChanged);
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.profilePwdChangeFailedMsg('$e'));
    }
  }

  Future<void> _logout() async {
    final l10n = AppL10n.current;
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(l10n.navLogoutConfirmTitle),
        content: Text(l10n.navLogoutConfirmMessage),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text(l10n.navLogoutConfirm)),
        ],
      ),
    );
    if (ok != true) return;
    try { await _api.post('/admin/v1/profile/logout'); } catch (_) {}
    await AuthService.clearToken();
    // GetView 挂载的 controller 不随路由销毁（GetX 4.7.3 语义），登出必须清理，
    // 否则下一账号登录会继承上一个管理员的筛选/分页/勾选状态与缓存列表。
    Get.deleteAll(force: true);
    Get.offAllNamed('/login');
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Center(child: SizedBox(width: 500, child: ListView(padding: const EdgeInsets.all(24), children: [
      Text(l10n.navProfile, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
      const SizedBox(height: 24),
      TextField(controller: _realNameCtrl, onChanged: (_) => setState(() => _nameDirty = true),
          decoration: InputDecoration(labelText: l10n.fieldRealName, hintText: l10n.profileLeaveBlank)),
      const SizedBox(height: 12),
      TextField(controller: _phoneCtrl, onChanged: (_) => setState(() => _phoneDirty = true),
          decoration: InputDecoration(labelText: l10n.fieldPhone, hintText: l10n.profileLeaveBlank)),
      const SizedBox(height: 12),
      TextField(controller: _emailCtrl, onChanged: (_) => setState(() => _emailDirty = true),
          decoration: InputDecoration(labelText: l10n.fieldEmail, hintText: l10n.profileLeaveBlank)),
      const SizedBox(height: 24),
      Row(children: [
        ElevatedButton.icon(onPressed: _updateProfile, icon: const Icon(Icons.save), label: Text(l10n.commonSave)),
      ]),
      const SizedBox(height: 32),
      const Divider(),
      ListTile(leading: const Icon(Icons.lock), title: Text(l10n.profileChangePassword), trailing: const Icon(Icons.chevron_right), onTap: _changePassword),
      ListTile(leading: const Icon(Icons.logout, color: Colors.red), title: Text(l10n.navLogout, style: const TextStyle(color: Colors.red)), onTap: _logout),
    ])));
  }
}
