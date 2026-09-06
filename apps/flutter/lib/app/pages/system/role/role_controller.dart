/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../l10n/app_l10n.dart';

/// 权限树会话缓存（模块级内存：非空即用，不进磁盘；P2）。
/// 页面/控制器销毁不清——同会话内二次进页零请求；force 重拉成功后整树
/// 替换；空成功/失败不写缓存（下次仍会拉取）。
List<dynamic>? _permissionTreeCache;

class RoleController extends GetxController {
  final api = ApiService();
  final roles = <dynamic>[].obs;
  final permissions = <dynamic>[].obs;
  final isLoading = false.obs;
  final isPermLoading = false.obs;
  final permLoadFailed = false.obs;
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
      total.value = (resp['data']['total'] as num?)?.toInt() ?? 0;
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

  /// 拉取权限树。会话缓存命中（模块级非空即用，二次进页零请求）；
  /// [force] 才真正重拉——弹框页内刷新/失败重试走 force: true。
  Future<void> loadPermissions({bool force = false}) async {
    if (!force && _permissionTreeCache != null) {
      permissions.value = _permissionTreeCache!;
      permLoadFailed.value = false;
      return;
    }
    isPermLoading.value = true;
    permLoadFailed.value = false;
    try {
      final resp = await api.get('/admin/v1/permission');
      final data = resp['data'] as List<dynamic>? ?? [];
      permissions.value = data;
      if (data.isNotEmpty) _permissionTreeCache = data;
    } catch (e) {
      permLoadFailed.value = true;
      final l10n = AppL10n.current;
      // 权限树是弹框勾选唯一来源，失败静默会导致新建/编辑角色零权限
      Get.snackbar(l10n.commonSnackError, l10n.systemPermLoadFailedMsg('$e'));
    } finally {
      isPermLoading.value = false;
    }
  }

  /// 清空会话缓存：登出换号必须清（同进程新 token 会复用旧账号权限树）；
  /// 正常流程内 force 重拉即覆盖。
  static void clearPermissionCache() => _permissionTreeCache = null;

  Future<bool> createRole(String name, String slug, String desc, List<String> permIds, {int status = 1}) async {
    try {
      await api.post('/admin/v1/role', data: {
        'name': name, 'slug': slug, 'description': desc, 'status': status, 'permission_ids': permIds,
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

  Future<bool> updateRole(String id, {String? name, String? desc, int? status, List<String>? permIds}) async {
    try {
      final data = <String, dynamic>{};
      if (name != null) data['name'] = name;
      if (desc != null) data['description'] = desc;
      if (status != null) data['status'] = status;
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
