/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:get/get.dart';
import '../../../services/api_service.dart';
import '../../../l10n/app_l10n.dart';

class UserController extends GetxController {
  final api = ApiService();

  final users = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;
  final keyword = ''.obs;
  final statusFilter = Rx<int?>(null);
  final selectedIds = <String>{}.obs;

  @override
  void onInit() {
    super.onInit();
    loadUsers();
  }

  Future<void> loadUsers({bool reset = false}) async {
    if (reset) page.value = 1;
    isLoading.value = true;
    try {
      final params = <String, dynamic>{
        'page': page.value,
        'limit': limit.value,
      };
      if (keyword.value.isNotEmpty) params['keyword'] = keyword.value;
      if (statusFilter.value != null) params['status'] = statusFilter.value;

      final resp = await api.get('/admin/v1/user', params: params);
      users.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.systemUserLoadFailedMsg('$e'));
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> search(String kw) async {
    keyword.value = kw;
    await loadUsers(reset: true);
  }

  Future<void> filterByStatus(int? status) async {
    statusFilter.value = status;
    await loadUsers(reset: true);
  }

  Future<void> nextPage() async {
    if (page.value * limit.value < total.value) {
      page.value++;
      await loadUsers();
    }
  }

  Future<void> prevPage() async {
    if (page.value > 1) {
      page.value--;
      await loadUsers();
    }
  }

  Future<bool> deleteUser(String id, String password) async {
    try {
      await api.delete('/admin/v1/user/$id', data: {'password': password});
      await loadUsers();
      return true;
    } catch (e) {
      final l10n = AppL10n.current;
      Get.snackbar(l10n.commonSnackError, l10n.commonDeleteFailedMsg('$e'));
      return false;
    }
  }

  Future<bool> batchDelete(String password) async {
    final l10n = AppL10n.current;
    if (selectedIds.isEmpty) {
      Get.snackbar(l10n.commonSnackInfo, l10n.systemUserSelectFirst);
      return false;
    }
    try {
      await api.post('/admin/v1/user/batch/destroy', data: {
        'ids': selectedIds.toList(),
        'password': password,
      });
      selectedIds.clear();
      await loadUsers();
      Get.snackbar(l10n.commonSnackSuccess, l10n.systemUserBatchDeleteDone);
      return true;
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.systemUserBatchDeleteFailedMsg('$e'));
      return false;
    }
  }

  Future<bool> batchSetStatus(int status) async {
    final l10n = AppL10n.current;
    if (selectedIds.isEmpty) {
      Get.snackbar(l10n.commonSnackInfo, l10n.systemUserSelectFirst);
      return false;
    }
    try {
      await api.post('/admin/v1/user/batch/status', data: {
        'ids': selectedIds.toList(),
        'status': status,
      });
      selectedIds.clear();
      await loadUsers();
      Get.snackbar(l10n.commonSnackSuccess, status == 1 ? l10n.systemUserBatchEnabled : l10n.systemUserBatchDisabled);
      return true;
    } catch (e) {
      Get.snackbar(l10n.commonSnackError, l10n.commonOpFailedMsg('$e'));
      return false;
    }
  }

  void toggleSelect(String id) {
    if (selectedIds.contains(id)) {
      selectedIds.remove(id);
    } else {
      selectedIds.add(id);
    }
  }

  void toggleSelectAll() {
    if (selectedIds.length == users.length) {
      selectedIds.clear();
    } else {
      selectedIds.addAll(users.map((u) => u['id'].toString()));
    }
  }
}
