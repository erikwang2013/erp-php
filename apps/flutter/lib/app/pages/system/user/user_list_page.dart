/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../widgets/confirm_dialog.dart';
import '../../../l10n/app_l10n.dart';
import 'user_controller.dart';
import 'user_form_page.dart';

class UserListPage extends GetView<UserController> {
  const UserListPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<UserController>()) {
      Get.put(UserController(), permanent: false);
    }
    final ctrl = controller;
    final l10n = AppL10n.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header
        Row(
          children: [
            Text(l10n.systemUserTitle, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            ElevatedButton.icon(
              onPressed: () => Get.to(() => const UserFormPage())?.then((_) => ctrl.loadUsers(reset: true)),
              icon: const Icon(Icons.add),
              label: Text(l10n.systemUserAdd),
            ),
            const SizedBox(width: 8),
            // selectedIds 是响应式的，批量操作按钮需包裹在 Obx 中，否则勾选后不会出现
            Obx(() {
              final l10n = AppL10n.current;
              if (ctrl.selectedIds.isEmpty) return const SizedBox.shrink();
              return Row(mainAxisSize: MainAxisSize.min, children: [
                ElevatedButton.icon(
                  onPressed: () => _confirmBatchDelete(context, ctrl),
                  icon: const Icon(Icons.delete, color: Colors.red),
                  label: Text(l10n.systemUserBatchDelLabel(ctrl.selectedIds.length)),
                  style: ElevatedButton.styleFrom(foregroundColor: Colors.red),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<String>(
                  onSelected: (v) {
                    if (v == 'enable') ctrl.batchSetStatus(1);
                    if (v == 'disable') ctrl.batchSetStatus(0);
                  },
                  itemBuilder: (_) => [
                    PopupMenuItem(value: 'enable', child: Text(l10n.systemUserBatchEnable)),
                    PopupMenuItem(value: 'disable', child: Text(l10n.systemUserBatchDisable)),
                  ],
                ),
              ]);
            }),
          ],
        ),
        const SizedBox(height: 12),
        // Search + Filter
        Row(
          children: [
            SizedBox(
              width: 250,
              child: TextField(
                decoration: InputDecoration(hintText: l10n.systemUserSearchHint, prefixIcon: const Icon(Icons.search), isDense: true),
                onSubmitted: (v) => ctrl.search(v),
              ),
            ),
            const SizedBox(width: 12),
            // 筛选 chips 需随 statusFilter 响应式刷新，否则点击后高亮不更新
            Obx(() {
              final l10n = AppL10n.current;
              return Row(children: [
                ChoiceChip(label: Text(l10n.commonAll), selected: ctrl.statusFilter.value == null, onSelected: (_) => ctrl.filterByStatus(null)),
                const SizedBox(width: 4),
                ChoiceChip(label: Text(l10n.commonEnabled), selected: ctrl.statusFilter.value == 1, onSelected: (_) => ctrl.filterByStatus(1)),
                const SizedBox(width: 4),
                ChoiceChip(label: Text(l10n.commonDisabled), selected: ctrl.statusFilter.value == 0, onSelected: (_) => ctrl.filterByStatus(0)),
              ]);
            }),
          ],
        ),
        const SizedBox(height: 12),
        // Table
        Expanded(
          child: Obx(() {
            final l10n = AppL10n.current;
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.users.isEmpty) return Center(child: Text(l10n.commonNoData));

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Checkbox(value: ctrl.selectedIds.length == ctrl.users.length && ctrl.users.isNotEmpty, onChanged: (_) => ctrl.toggleSelectAll())),
                  DataColumn(label: Text(l10n.fieldUsername)),
                  DataColumn(label: Text(l10n.fieldRealName)),
                  DataColumn(label: Text(l10n.fieldPhone)),
                  DataColumn(label: Text(l10n.fieldEmail)),
                  DataColumn(label: Text(l10n.commonStatus)),
                  DataColumn(label: Text(l10n.fieldLastLogin)),
                  DataColumn(label: Text(l10n.commonAction)),
                ],
                rows: ctrl.users.map((u) {
                  final id = u['id'].toString();
                  return DataRow(
                    selected: ctrl.selectedIds.contains(id),
                    onSelectChanged: (_) => ctrl.toggleSelect(id),
                    cells: [
                      DataCell(Checkbox(value: ctrl.selectedIds.contains(id), onChanged: (_) => ctrl.toggleSelect(id))),
                      DataCell(Text(u['username'] ?? '')),
                      DataCell(Text(u['real_name'] ?? '')),
                      DataCell(Text(u['phone'] ?? '')),
                      DataCell(Text(u['email'] ?? '')),
                      DataCell(u['status'] == null
                          ? Chip(label: Text('-')) // 状态缺失显式占位，避免误判为「禁用」
                          : Chip(label: Text(u['status'] == 1 ? l10n.commonEnabled : l10n.commonDisabled), color: WidgetStatePropertyAll(u['status'] == 1 ? Colors.green.shade50 : Colors.red.shade50))),
                      DataCell(Text(u['last_login_at'] ?? '-')),
                      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => Get.to(() => UserFormPage(userData: u))?.then((_) => ctrl.loadUsers())),
                        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _confirmDelete(context, ctrl, u)),
                      ])),
                    ],
                  );
                }).toList(),
              ),
            );
          }),
        ),
        // Pagination
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

  void _confirmDelete(BuildContext context, UserController ctrl, dynamic user) {
    final l10n = AppL10n.of(context);
    // 复用 ConfirmDialog：密码确认 + loading/失败态，临时 controller 由其自管（dispose）
    ConfirmDialog.show(
      context,
      content: l10n.systemUserDeleteContent('${user['username']}'),
      confirmText: l10n.commonDelete,
      onConfirm: (pwd) => ctrl.deleteUser(user['id'], pwd),
    );
  }

  void _confirmBatchDelete(BuildContext context, UserController ctrl) {
    final l10n = AppL10n.of(context);
    ConfirmDialog.show(
      context,
      title: l10n.systemUserBatchDeleteTitle,
      content: l10n.systemUserBatchDeleteContent(ctrl.selectedIds.length),
      confirmText: l10n.commonDelete,
      onConfirm: (pwd) => ctrl.batchDelete(pwd),
    );
  }
}
