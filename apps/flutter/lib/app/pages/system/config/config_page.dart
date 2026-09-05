/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../widgets/confirm_dialog.dart';
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

  Future<void> save(dynamic item) async {
    final l10n = AppL10n.current;
    try {
      if (item['id'] != null) {
        await api.put('/admin/v1/config/${item['id']}', data: item);
      } else {
        await api.post('/admin/v1/config', data: item);
      }
      await loadConfigs();
      Get.snackbar(l10n.commonSnackSuccess, l10n.configSaveSuccess);
    } catch (e) { Get.snackbar(l10n.commonSnackError, l10n.configSaveFailedMsg('$e')); }
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
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: Text(l10n.configAdd)),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        final l10n = AppL10n.current;
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        return ListView.builder(
          itemCount: ctrl.configs.length,
          itemBuilder: (_, i) {
            final c = ctrl.configs[i];
            return Card(child: ListTile(
              title: Text('${c['group']}.${c['key']}', style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text(c['description'] ?? ''),
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                Chip(label: Text(c['type'] ?? 'string')), // type 为后端存储值，原样展示不翻译
                const SizedBox(width: 8),
                Text(c['value'] ?? '', style: const TextStyle(color: Colors.blue)),
                IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showDialog(context, ctrl, item: c)),
                IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () {
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

  void _showDialog(BuildContext context, ConfigController ctrl, {dynamic item}) {
    final l10n = AppL10n.of(context);
    final gCtrl = TextEditingController(text: item?['group'] ?? '');
    final kCtrl = TextEditingController(text: item?['key'] ?? '');
    final vCtrl = TextEditingController(text: item?['value'] ?? '');
    final tCtrl = TextEditingController(text: item?['type'] ?? 'string');
    final dCtrl = TextEditingController(text: item?['description'] ?? '');
    showDialog(context: context, builder: (_) => AlertDialog(
      title: Text(item != null ? l10n.configEdit : l10n.configAdd),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: gCtrl, decoration: InputDecoration(labelText: l10n.fieldGroup), enabled: item == null),
        TextField(controller: kCtrl, decoration: InputDecoration(labelText: l10n.fieldKey), enabled: item == null),
        TextField(controller: vCtrl, decoration: InputDecoration(labelText: l10n.fieldValue), maxLines: 3),
        TextField(controller: tCtrl, decoration: InputDecoration(labelText: l10n.fieldType)),
        TextField(controller: dCtrl, decoration: InputDecoration(labelText: l10n.fieldNote)),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l10n.commonCancel)),
        ElevatedButton(onPressed: () {
          ctrl.save({'id': item?['id'], 'group': gCtrl.text, 'key': kCtrl.text, 'value': vCtrl.text, 'type': tCtrl.text, 'description': dCtrl.text});
          Navigator.pop(context);
        }, child: Text(l10n.commonSave)),
      ],
    ));
  }
}
