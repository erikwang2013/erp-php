/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../l10n/app_l10n.dart';

class RoleController extends GetxController {
  final api = ApiService();
  final roles = <dynamic>[].obs;
  final permissions = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;

  @override
  void onInit() {
    super.onInit();
    loadRoles();
    loadPermissions();
  }

  Future<void> loadRoles() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/role', params: {'page': page.value, 'limit': limit.value});
      roles.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.systemRoleLoadFailedMsg('$e'));
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> nextPage() async {
    if (page.value * limit.value < total.value) {
      page.value++;
      await loadRoles();
    }
  }

  Future<void> prevPage() async {
    if (page.value > 1) {
      page.value--;
      await loadRoles();
    }
  }

  Future<void> loadPermissions() async {
    try {
      final resp = await api.get('/admin/v1/permission');
      permissions.value = resp['data'] as List<dynamic>? ?? [];
    } catch (e) {
      final l10n = AppL10n.current;
      // 权限树是弹框勾选唯一来源，失败静默会导致新建/编辑角色零权限
      Get.snackbar(l10n.commonSnackError, l10n.systemPermLoadFailedMsg('$e'));
    }
  }

  Future<bool> createRole(String name, String slug, String desc, List<String> permIds) async {
    try {
      await api.post('/admin/v1/role', data: {
        'name': name, 'slug': slug, 'description': desc, 'permission_ids': permIds,
      });
      await loadRoles();
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackSuccess, l10n.systemRoleCreated);
      return true;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.systemRoleCreateFailedMsg('$e'));
      return false;
    }
  }

  Future<bool> updateRole(String id, {String? name, String? desc, List<String>? permIds}) async {
    try {
      final data = <String, dynamic>{};
      if (name != null) data['name'] = name;
      if (desc != null) data['description'] = desc;
      if (permIds != null) data['permission_ids'] = permIds;
      await api.put('/admin/v1/role/$id', data: data);
      await loadRoles();
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackSuccess, l10n.systemRoleUpdated);
      return true;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.systemRoleUpdateFailedMsg('$e'));
      return false;
    }
  }

  Future<bool> deleteRole(String id, String password) async {
    try {
      await api.delete('/admin/v1/role/$id', data: {'password': password});
      await loadRoles();
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackSuccess, l10n.systemRoleDeleted);
      return true;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.commonDeleteFailedMsg('$e'));
      return false;
    }
  }
}
