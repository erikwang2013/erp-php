// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_finance_expense 真实列：code/apply_user_id/account_id/amount/status/
// remark/approved_at/approved_by/paid_at。无 name 列（旧 name 字段纯属幻列）：
// 申请人/科目由后端 leftJoin 带回 apply_user_name/account_name；费用科目无独立
// 列表接口，account_id 按文本填（数字ID/hashid 均可，后端双模解码）。
class ExpenseListPage extends StatefulWidget {
  const ExpenseListPage({super.key});
  @override
  State<ExpenseListPage> createState() => _ExpenseListPageState();
}

class _ExpenseListPageState extends State<ExpenseListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  /// 申请人下拉数据源 /admin/v1/hr/employee（行 name/员工名；失败降级空表）。
  Future<List<Map<String, dynamic>>> _loadEmployees() async {
    try {
      final res = await ApiService.instance.get('/admin/v1/hr/employee', params: {'limit': '500'});
      return List<Map<String, dynamic>>.from(res['data']?['list'] ?? []);
    } catch (_) {
      return [];
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/finance/expense', params: {'page': '$_page', 'limit': '$_limit'});
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

  Future<void> _create() async {
    // 表无 name/code 可搜列：不挂搜索框；code 客户端自动生成（uk_code 唯一）。
    final employees = await _loadEmployees();
    if (!mounted) return;
    final options = [
      for (final e in employees) '${e['id']} - ${e['name'] ?? e['employee_no'] ?? e['id']}',
    ];
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonAdd,
      fields: _formFields(options),
      onSubmit: (data) async {
        final payload = _buildPayload(data, isEdit: false);
        await ApiService.instance.post('/admin/v1/finance/expense', data: payload);
        _load();
        return true;
      },
    );
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    final employees = await _loadEmployees();
    if (!mounted) return;
    var options = [
      for (final e in employees) '${e['id']} - ${e['name'] ?? e['employee_no'] ?? e['id']}',
    ];
    // 申请人不在最新列表（离职/超500条）时补一行原值，保证下拉回填不落空
    final cur = '${row['apply_user_id']}';
    if (cur.isNotEmpty && !options.any((o) => o.startsWith('$cur - '))) {
      options = [cur, ...options];
    }
    await FormDialog.show(
      context,
      title: AppL10n.of(context).commonEdit,
      fields: _formFields(options),
      initialData: _toEditData(row),
      onSubmit: (data) async {
        final payload = _buildPayload(data, isEdit: true, code: '${row['code'] ?? ''}');
        await ApiService.instance.put('/admin/v1/finance/expense/${row['id']}', data: payload);
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['code'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/finance/expense/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  List<FormFieldConfig> _formFields(List<String> employeeOptions) => [
    FormFieldConfig(
      name: 'apply_user_id',
      label: AppL10n.of(context).purchaseApplyUserId,
      required: true,
      type: FormFieldType.dropdown,
      options: employeeOptions,
    ),
    FormFieldConfig(
      name: 'account_id',
      label: AppL10n.of(context).financeSubjectId,
      required: true,
    ),
    FormFieldConfig(name: 'amount', label: AppL10n.of(context).financeAmount, type: FormFieldType.number),
    FormFieldConfig(name: 'remark', label: AppL10n.of(context).commonRemark, type: FormFieldType.multiline),
  ];

  /// 编辑回填：申请人 FK 转「id - 名称」选项文案（名称缺失按原值兜底）。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final aid = '${row['apply_user_id'] ?? ''}';
    final an = row['apply_user_name'];
    d['apply_user_id'] = aid.isEmpty ? '' : (an == null || '$an'.isEmpty ? aid : '$aid - $an');
    return d;
  }

  /// 组装后端接收参数：状态由后端审批动作驱动（创建固定 0）；code 新建时客户端
  /// 自动生成 EXP+时间戳（uk_code 唯一），编辑沿用原单号不改动。
  Map<String, dynamic> _buildPayload(Map<String, String> data, {required bool isEdit, String code = ''}) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    final amount = data['amount']?.trim();
    return {
      'code': isEdit ? code : 'EXP${DateTime.now().millisecondsSinceEpoch}',
      'apply_user_id': pick('apply_user_id'),
      'account_id': data['account_id']?.trim(),
      'amount': (amount == null || amount.isEmpty) ? '0' : amount,
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
    onPageChanged: (p) {
      _page = p;
      _load();
    },
    pageTitle: AppL10n.of(context).financeExpenseTitle,
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
    return [l.fieldCode, l.purchaseApplyUserId, l.financeSubjectId, l.financeAmount, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.fieldCode: r['code'] ?? '',
      l.purchaseApplyUserId: r['apply_user_name'] ?? '',
      l.financeSubjectId: r['account_name'] ?? r['account_id'] ?? '',
      l.financeAmount: '${r['amount'] ?? 0}',
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

  // 状态：0=待审批 1=已批准 2=已驳回 3=已打款（0-2 复用审批流文案，3 无 key 待补）
  Widget _statusChip(dynamic s) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final i = s is int ? s : int.tryParse('$s') ?? 0;
    final map = {
      0: (l.purchaseApplyStatusPending, c.warningBg, c.warningText),
      1: (l.purchaseApplyStatusApproved, c.successBg, c.successText),
      2: (l.purchaseApplyStatusRejected, c.dangerBg, c.dangerText),
      3: ('3', c.primaryBg, c.primaryPressed),
    };
    final (text, bg, fg) = map[i] ?? ('$i', c.primaryBg, c.primaryPressed);
    return StatusBadge(label: text, bg: bg, fg: fg);
  }
}
