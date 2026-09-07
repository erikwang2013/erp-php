// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_finance_budget 真实列：code/name/period_year/cost_center_id/status/remark。
// code 为真实列（默认 ''）保留编辑；cost_center_id 无独立列表接口且默认 0，
// 页内不暴露（保持 0 全局）；status 0草稿/1已审批/2执行中/3已关闭 由后端驱动。
class BudgetPage extends StatefulWidget {
  const BudgetPage({super.key});
  @override
  State<BudgetPage> createState() => _BudgetPageState();
}

class _BudgetPageState extends State<BudgetPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
      final res = await ApiService.instance.get('/admin/v1/finance/budget', params: params);
      final d = res['data'];
      if (seq != _reqSeq || !mounted) return;
      setState(() {
        _rows = List<Map<String, dynamic>>.from(d['list'] ?? []);
        _total = d['total'] ?? 0;
        _loading = false;
        _error = null;
      });
      if (_rows.isEmpty && _page > 1) {
        _page--;
        _load();
        return;
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = ApiService.friendlyError(e);
        });
      }
    }
  }

  String _keyword = '';

  Future<void> _create() async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: _formFields(),
      onSubmit: (data) async {
        await ApiService.instance.post('/admin/v1/finance/budget', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: _formFields(),
      initialData: row,
      onSubmit: (data) async {
        await ApiService.instance.put('/admin/v1/finance/budget/${row['id']}', data: _buildPayload(data));
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['name'] ?? row['code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/finance/budget/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.of(context).commonName, required: true),
    FormFieldConfig(name: 'code', label: AppL10n.of(context).fieldCode),
    FormFieldConfig(name: 'period_year', label: AppL10n.of(context).financeAnnual, type: FormFieldType.number),
    FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
  ];

  /// 组装后端接收参数（status 由后端固定 0 草稿，不经客户端）。
  Map<String, dynamic> _buildPayload(Map<String, String> data) {
    final year = data['period_year']?.trim();
    return {
      'name': data['name']?.trim() ?? '',
      'code': data['code']?.trim() ?? '',
      'period_year': (year == null || year.isEmpty) ? '${DateTime.now().year}' : year,
      'remark': data['remark']?.trim() ?? '',
    };
  }

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total,
    page: _page,
    limit: _limit,
    loading: _loading,
    error: _error,
    onRetry: _load, onRefresh: _load,
    keyword: _keyword,
    onSearch: (v) {
      _keyword = v;
      _page = 1;
      _load();
    },
    onPageChanged: (p) {
      _page = p;
      _load();
    },
    pageTitle: AppL10n.of(context).financeBudgetTitle,
    moduleKey: 'finance',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [l.fieldCode, l.commonName, l.financeAnnual, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.fieldCode: r['code'] ?? '',
      l.commonName: r['name'] ?? '',
      l.financeAnnual: '${r['period_year'] ?? ''}',
      l.commonStatus: _statusChip(r['status']),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.edit, size: 18),
            onPressed: () => _edit(r),
          ),
          IconButton(
            icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger),
            onPressed: () => _delete(r),
          ),
        ],
      ),
    };
  }

  // 状态：0=草稿 1=已审批 2=执行中 3=已关闭（3 无 key 待补）
  Widget _statusChip(dynamic s) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final map = {
      0: (l.financeVoucherDraft, c.warningBg, c.warningText),
      1: (l.purchaseApplyStatusApproved, c.successBg, c.successText),
      2: (l.crmContractStatusActive, c.primaryBg, c.primaryPressed),
    };
    final (text, bg, fg) = map[i] ?? ('$i', c.primaryBg, c.primaryPressed);
    return StatusBadge(label: text, bg: bg, fg: fg);
  }
}
