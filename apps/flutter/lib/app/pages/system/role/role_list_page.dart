/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../theme/app_tokens.dart';
import '../../../widgets/confirm_dialog.dart';
import '../../../widgets/form_dialog.dart';
import '../../../widgets/permission_tree_picker.dart';
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
          // 刷新:保持当前页重载;加载中禁用
          Obx(() => IconButton(
                icon: const Icon(Icons.refresh),
                tooltip: AppL10n.current.commonRefresh,
                onPressed: ctrl.isLoading.value ? null : () => ctrl.loadRoles(),
              )),
          ElevatedButton.icon(
            onPressed: () => _showRoleDialog(context, ctrl),
            icon: const Icon(Icons.add),
            label: Text(l10n.systemRoleAdd),
          ),
        ]),
        const SizedBox(height: 12),
        Expanded(child: Obx(() {
          final l10n = AppL10n.current;
          if (ctrl.isLoading.value) {
            return const Center(child: CircularProgressIndicator());
          }
          // 下拉刷新:保持当前页重载;加载中整页菊花
          return RefreshIndicator(
            onRefresh: () => ctrl.loadRoles(),
            child: LayoutBuilder(builder: (context, c) {
              if (ctrl.roles.isEmpty) {
                // 空态撑满视口:内容不足一屏时下拉仍可触发
                return SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(minHeight: c.maxHeight),
                    child: Center(child: Text(l10n.systemRoleEmpty)),
                  ),
                );
              }
              return ListView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                itemCount: ctrl.roles.length,
                itemBuilder: (_, i) {
                  final r = ctrl.roles[i];
                  return Card(
                    child: ListTile(
                      leading: const Icon(Icons.shield, size: 36),
                      title: Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text(l10n.systemRoleSubtitle('${r['slug']}', int.tryParse('${r['users_count'] ?? 0}') ?? 0, '${r['description'] ?? ''}')),
                      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                        Chip(label: Text(r['status'] == 1 ? l10n.commonEnabled : l10n.commonDisabled), // §2.4：启用 success/停用 danger
                            color: WidgetStatePropertyAll(r['status'] == 1 ? AppColors.of(context).successBg : AppColors.of(context).dangerBg),
                            labelStyle: TextStyle(color: r['status'] == 1 ? AppColors.of(context).successText : AppColors.of(context).dangerText, fontSize: 12),
                            side: BorderSide.none),
                        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showRoleDialog(context, ctrl, role: r)),
                        IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () {
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
            }),
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

  Future<void> _showRoleDialog(BuildContext context, RoleController ctrl, {dynamic role}) async {
    final l10n = AppL10n.of(context);
    final isEdit = role != null;
    // 预选:role['permissions'] 为后端下发 hashid id 数组(含中间目录;整树经
    // GET /permission 拉取),直接作 id 集合交给树组件递归标记 —— 修复旧实现
    // 「只比顶层、叶子权限保存即静默清空」的问题。id 同为 hashid 字符串,原样比较。
    final grantedIds = (role?['permissions'] as List<dynamic>?)
            ?.map((p) => '$p')
            .toSet() ??
        <String>{};
    var permIds = grantedIds.toSet();

    final fields = <FormFieldConfig>[
      FormFieldConfig(
        name: 'name',
        label: l10n.fieldName,
        required: true,
        enabled: !isEdit, // 名称/slug:slug 后端不可改;名称编辑沿用旧交互(只读)
      ),
      FormFieldConfig(name: 'slug', label: l10n.fieldSlug, required: true, enabled: !isEdit),
      FormFieldConfig(name: 'description', label: l10n.fieldDescription),
      FormFieldConfig(
        name: 'status',
        label: l10n.commonStatus,
        type: FormFieldType.dropdown,
        initialValue: '1',
        options: const ['1', '0'],
        optionLabels: {'1': l10n.commonEnabled, '0': l10n.commonDisabled},
      ),
    ];

    await FormDialog.show(
      context,
      title: isEdit ? l10n.systemRoleEdit : l10n.systemRoleAdd,
      fields: fields,
      initialData: isEdit ? role : null,
      submitText: l10n.commonSave,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(l10n.systemRolePermSection,
                  style: const TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(width: 4),
              // 页内刷新/失败重试：force 重拉（会话缓存命中时普通调用不触网）
              InkWell(
                onTap: () => ctrl.loadPermissions(force: true),
                child: Icon(Icons.refresh,
                    size: 16, color: Theme.of(context).colorScheme.outline),
              ),
            ],
          ),
          const SizedBox(height: 4),
          // P2 响应态：Obx 订阅 controller，数据晚到自动从 loading 转树
          // （消除直读快照造成的假空态）；失败态含重试钮(force 重拉)。
          Obx(() {
            final l10n = AppL10n.current;
            if (ctrl.permLoadFailed.value) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.commonLoadFailed,
                        style: TextStyle(
                            fontSize: 13,
                            color: Theme.of(context).colorScheme.error),
                      ),
                      TextButton.icon(
                        onPressed: () => ctrl.loadPermissions(force: true),
                        icon: const Icon(Icons.refresh, size: 16),
                        label: Text(l10n.commonRetry),
                      ),
                    ],
                  ),
                ),
              );
            }
            if (ctrl.permissions.isEmpty) {
              if (ctrl.isPermLoading.value) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Center(
                    child: SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.5),
                    ),
                  ),
                );
              }
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Center(
                  child: Text(l10n.commonNoData,
                      style: TextStyle(
                          fontSize: 13,
                          color: Theme.of(context).colorScheme.outline)),
                ),
              );
            }
            return PermissionTreePicker(
              nodes: ctrl.permissions
                  .map((p) => p as Map<String, dynamic>)
                  .toList(),
              initialSelectedIds: grantedIds,
              onChanged: (s) => permIds = s,
            );
          }),
        ],
      ),
      onSubmit: (data) async {
        final status = int.tryParse(data['status'] ?? '') ?? 1;
        return isEdit
            ? ctrl.updateRole(role['id'],
                name: data['name'] ?? '',
                desc: data['description'] ?? '',
                status: status,
                permIds: permIds.toList())
            : ctrl.createRole(data['name'] ?? '', data['slug'] ?? '',
                data['description'] ?? '',
                permIds.toList(),
                status: status);
      },
    );
  }
}
