/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../theme/app_tokens.dart';
import '../../../widgets/confirm_dialog.dart';
import '../../../widgets/form_dialog.dart';
import '../../../l10n/app_l10n.dart';

class ConfigController extends GetxController {
  final api = ApiService();
  final configs = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;

  @override
  void onInit() { super.onInit(); loadConfigs(); }

  Future<void> loadConfigs() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/config', params: {'page': page.value, 'limit': limit.value});
      configs.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.commonLoadFailedMsg('$e'));
    }
    finally { isLoading.value = false; }
  }

  Future<void> nextPage() async {
    if (page.value * limit.value < total.value) {
      page.value++;
      await loadConfigs();
    }
  }

  Future<void> prevPage() async {
    if (page.value > 1) {
      page.value--;
      await loadConfigs();
    }
  }

  /// 保存成功返回 true(弹框由调用方据此关闭),失败留在弹框内可重试。
  Future<bool> save(dynamic item) async {
    final l10n = AppL10n.current;
    try {
      if (item['id'] != null) {
        await api.put('/admin/v1/config/${item['id']}', data: item);
      } else {
        await api.post('/admin/v1/config', data: item);
      }
      await loadConfigs();
      Get.snackbar(l10n.commonSnackSuccess, l10n.configSaveSuccess);
      return true;
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.configSaveFailedMsg('$e'));
      return false;
    }
  }

  Future<bool> remove(String id, String pwd) async {
    final l10n = AppL10n.current;
    try {
      await api.delete('/admin/v1/config/$id', data: {'password': pwd});
      await loadConfigs();
      Get.snackbar(l10n.commonSnackSuccess, l10n.configDeleteSuccess);
      return true;
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.commonDeleteFailedMsg('$e'));
      return false;
    }
  }
}

class ConfigPage extends GetView<ConfigController> {
  const ConfigPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<ConfigController>()) {
      Get.put(ConfigController(), permanent: false);
    }
    final ctrl = controller;
    final l10n = AppL10n.of(context);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text(l10n.configTitle, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        // 刷新:保持当前页重载;加载中禁用
        Obx(() => IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: AppL10n.current.commonRefresh,
              onPressed: ctrl.isLoading.value ? null : () => ctrl.loadConfigs(),
            )),
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: Text(l10n.configAdd)),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        final l10n = AppL10n.current;
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        // 下拉刷新:保持当前页重载;加载中整页菊花
        return RefreshIndicator(
          onRefresh: () => ctrl.loadConfigs(),
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: ctrl.configs.length,
            itemBuilder: (_, i) {
              final c = ctrl.configs[i];
              return Card(child: ListTile(
                title: Text('${c['group']}.${c['key']}', style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text(c['description'] ?? ''),
                trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                  Chip(label: Text(c['type'] ?? 'string')), // type 为后端存储值，原样展示不翻译
                  const SizedBox(width: 8),
                  Text(c['value'] ?? '', style: TextStyle(color: AppColors.of(context).primary)),
                  IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showDialog(context, ctrl, item: c)),
                  IconButton(icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger), onPressed: () {
                    ConfirmDialog.show(
                      context,
                      content: l10n.systemConfigDeleteContent('${c['group']}.${c['key']}'),
                      confirmText: l10n.commonDelete,
                      passwordLabel: l10n.commonPasswordConfirm,
                      onConfirm: (pwd) => ctrl.remove(c['id'], pwd),
                    );
                  }),
                ]),
              ));
            },
          ),
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
    ]);
  }

  Future<void> _showDialog(BuildContext context, ConfigController ctrl, {dynamic item}) async {
    final l10n = AppL10n.of(context);
    final isEdit = item != null;
    await FormDialog.show(
      context,
      title: isEdit ? l10n.configEdit : l10n.configAdd,
      initialData: isEdit ? item : null,
      submitText: l10n.commonSave,
      fields: [
        // group/key 为 uk_group_key 联合主键:编辑态禁改(与旧交互一致)
        FormFieldConfig(name: 'group', label: l10n.fieldGroup, required: true, enabled: !isEdit),
        FormFieldConfig(name: 'key', label: l10n.fieldKey, required: true, enabled: !isEdit),
        FormFieldConfig(name: 'value', label: l10n.fieldValue, type: FormFieldType.multiline),
        FormFieldConfig(
          name: 'type',
          label: l10n.fieldType,
          type: FormFieldType.dropdown,
          initialValue: 'string',
          // 枚举与 erp_system_config.type 注释集(string|int|bool|json|array)一致;
          // 原样英文展示(存储值),不翻译
          options: const ['string', 'int', 'bool', 'json', 'array'],
        ),
        FormFieldConfig(name: 'description', label: l10n.fieldNote),
      ],
      onSubmit: (data) async => ctrl.save({
        'id': item?['id'],
        'group': data['group'],
        'key': data['key'],
        'value': data['value'],
        'type': data['type'],
        'description': data['description'],
      }),
    );
  }
}
