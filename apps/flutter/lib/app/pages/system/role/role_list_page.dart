/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../widgets/confirm_dialog.dart';
import '../../../l10n/app_l10n.dart';
import 'role_controller.dart';

class RoleListPage extends GetView<RoleController> {
  const RoleListPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RoleController>()) {
      Get.put(RoleController(), permanent: false);
    }
    final ctrl = controller;
    final l10n = AppL10n.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(children: [
          Text(l10n.systemRoleTitle, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const Spacer(),
          ElevatedButton.icon(
            onPressed: () => _showRoleDialog(context, ctrl),
            icon: const Icon(Icons.add),
            label: Text(l10n.systemRoleAdd),
          ),
        ]),
        const SizedBox(height: 12),
        Expanded(child: Obx(() {
          final l10n = AppL10n.current;
          if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
          if (ctrl.roles.isEmpty) return Center(child: Text(l10n.systemRoleEmpty));

          return ListView.builder(
            itemCount: ctrl.roles.length,
            itemBuilder: (_, i) {
              final r = ctrl.roles[i];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.shield, size: 36),
                  title: Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text(l10n.systemRoleSubtitle('${r['slug']}', int.tryParse('${r['users_count'] ?? 0}') ?? 0, '${r['description'] ?? ''}')),
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    Chip(label: Text(r['status'] == 1 ? l10n.commonEnabled : l10n.commonDisabled)),
                    IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showRoleDialog(context, ctrl, role: r)),
                    IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () {
                      // 复用 ConfirmDialog（密码确认 + 内部 loading/失败态，controller 自管生命周期）
                      ConfirmDialog.show(
                        context,
                        content: l10n.systemRoleDeleteContent('${r['name']}'),
                        confirmText: l10n.commonDelete,
                        passwordLabel: l10n.commonPasswordConfirm,
                        onConfirm: (pwd) => ctrl.deleteRole(r['id'], pwd),
                      );
                    }),
                  ]),
                ),
              );
            },
          );
        })),
        // 分页（默认 limit=15，超出首屏的数据需翻页可达）
        const SizedBox(height: 8),
        Obx(() {
          final l10n = AppL10n.current;
          return Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(onPressed: ctrl.prevPage, icon: const Icon(Icons.chevron_left)),
              Text(l10n.commonPageInfo(ctrl.page.value, (ctrl.total.value / ctrl.limit.value).ceil(), ctrl.total.value)),
              IconButton(onPressed: ctrl.nextPage, icon: const Icon(Icons.chevron_right)),
            ],
          );
        }),
      ],
    );
  }

  void _showRoleDialog(BuildContext context, RoleController ctrl, {dynamic role}) {
    final l10n = AppL10n.of(context);
    final nameCtrl = TextEditingController(text: role?['name'] ?? '');
    final slugCtrl = TextEditingController(text: role?['slug'] ?? '');
    final descCtrl = TextEditingController(text: role?['description'] ?? '');
    final selectedPerms = (role?['permissions'] as List<dynamic>?)?.map((p) => p['id'].toString()).toSet() ?? <String>{};

    showDialog(
      context: context,
      builder: (_) => StatefulBuilder(
        builder: (_, setDialogState) => AlertDialog(
          title: Text(role != null ? l10n.systemRoleEdit : l10n.systemRoleAdd, style: const TextStyle(fontWeight: FontWeight.bold)),
          content: SizedBox(width: 450, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: nameCtrl, decoration: InputDecoration(labelText: l10n.fieldName), enabled: role == null),
            TextField(controller: slugCtrl, decoration: InputDecoration(labelText: l10n.fieldSlug), enabled: role == null),
            TextField(controller: descCtrl, decoration: InputDecoration(labelText: l10n.fieldDescription)),
            const SizedBox(height: 12),
            Text(l10n.systemRolePermSection, style: const TextStyle(fontWeight: FontWeight.bold)),
            ...ctrl.permissions.map((perm) => CheckboxListTile(
              title: Text(perm['name'] ?? ''),
              subtitle: Text(perm['slug'] ?? ''),
              value: selectedPerms.contains(perm['id'].toString()),
              onChanged: (v) {
                setDialogState(() { if (v == true) { selectedPerms.add(perm['id'].toString()); } else { selectedPerms.remove(perm['id'].toString()); } });
              },
            )),
          ]))),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: Text(l10n.commonCancel)),
            ElevatedButton(onPressed: () {
              if (role != null) {
                ctrl.updateRole(role['id'], name: nameCtrl.text, desc: descCtrl.text, permIds: selectedPerms.toList());
              } else {
                ctrl.createRole(nameCtrl.text, slugCtrl.text, descCtrl.text, selectedPerms.toList());
              }
              Navigator.pop(context);
            }, child: Text(l10n.commonSave)),
          ],
        ),
      ),
    );
  }
}
