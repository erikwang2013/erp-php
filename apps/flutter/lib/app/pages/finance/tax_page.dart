// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';
import '../../widgets/status_badge.dart';

// erp_finance_tax_rate 真实列：name/rate/type(vat/cit/pit/stamp/other)/enabled，
// 无 code/status 列（旧幻列已删）。路由无 PUT：编辑走 POST 携带 body id 的
// upsert（后端按 id 解码：不存在→新建，存在→更新）。
class TaxPage extends StatefulWidget {
  const TaxPage({super.key});
  @override
  State<TaxPage> createState() => _TaxPageState();
}

class _TaxPageState extends State<TaxPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;

  bool _loading = true;
  String? _error;
  int _reqSeq = 0;

  static const List<String> _typeValues = ['vat', 'cit', 'pit', 'stamp', 'other'];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final seq = ++_reqSeq;
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get('/admin/v1/finance/tax-rate', params: {'page': '$_page', 'limit': '$_limit'});
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

  /// 新增/编辑共用 POST upsert：编辑时 data 携带 body id（编辑模式启用密码复核）。
  Future<void> _upsert({Map<String, dynamic>? row}) async {
    final isEdit = row != null;
    await FormDialog.show(
      context,
      title: isEdit ? AppL10n.of(context).commonEdit : AppL10n.of(context).commonAdd,
      fields: _formFields(),
      initialData: isEdit ? _toEditData(row) : null,
      onSubmit: (data) async {
        final payload = _buildPayload(data);
        if (isEdit) {
          // 编辑=POST upsert：body 携带 id，后端按 id 解码命中则更新（路由无 PUT）
          payload['id'] = '${row['id']}';
        }
        await ApiService.instance.post('/admin/v1/finance/tax-rate', data: payload);
        _load();
        return true;
      },
    );
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(
      context,
      title: AppL10n.of(context).commonDeleteConfirm,
      content: AppL10n.of(context).commonDeleteMsg('${row['name'] ?? row['id']}'),
      onConfirm: (password) async {
        await ApiService.instance.delete('/admin/v1/finance/tax-rate/${row['id']}', data: {'password': password});
        _load();
        return true;
      },
    );
  }

  // 编辑弹窗同时带密码框（与删除同一密码确认语义，后端 destroy 要求）。
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'name', label: AppL10n.of(context).fieldName, required: true),
    FormFieldConfig(name: 'rate', label: AppL10n.of(context).financeRate, type: FormFieldType.number),
    FormFieldConfig(
      name: 'type',
      label: AppL10n.of(context).fieldType,
      type: FormFieldType.dropdown,
      options: _typeValues,
      initialValue: 'vat',
    ),
    FormFieldConfig(
      name: 'enabled',
      label: AppL10n.of(context).commonStatus,
      type: FormFieldType.dropdown,
      options: _enabledOptions,
      initialValue: '1 - ${AppL10n.of(context).commonEnabled}',
    ),
  ];

  List<String> get _enabledOptions {
    final l = AppL10n.of(context);
    return ['1 - ${l.commonEnabled}', '0 - ${l.commonDisabled}'];
  }

  /// 组装 POST 载荷（enabled 拆出 0/1；rate 数字串后端 decimal 直存）。
  Map<String, String> _buildPayload(Map<String, String> data) {
    String pick(String key) => (data[key] ?? '').split(' - ').first.trim();
    final rate = data['rate']?.trim();
    return {
      'name': data['name']?.trim() ?? '',
      'rate': (rate == null || rate.isEmpty) ? '0' : rate,
      'type': (data['type']?.trim().isEmpty ?? true) ? 'vat' : data['type']!.trim(),
      'enabled': pick('enabled'),
    };
  }

  /// 编辑回填：type 保持原值；enabled int → 选项文案。
  Map<String, dynamic> _toEditData(Map<String, dynamic> row) {
    final d = Map<String, dynamic>.from(row);
    final enabled = row['enabled'];
    final l = AppL10n.of(context);
    d['enabled'] = (enabled is int && enabled == 1) || '$enabled' == '1'
        ? '1 - ${l.commonEnabled}'
        : '0 - ${l.commonDisabled}';
    return d;
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
    pageTitle: AppL10n.of(context).financeTaxTitle,
    moduleKey: 'finance',
    primaryColumnIndex: 0,

    actions: [
      ElevatedButton.icon(
        onPressed: () => _upsert(),
        icon: const Icon(Icons.add, size: 18),
        label: Text(AppL10n.of(context).commonAdd),
      ),
    ],
  );

  List<String> _columns() {
    final l = AppL10n.of(context);
    return [l.fieldName, l.fieldType, l.commonStatus, l.commonAction];
  }

  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    final l = AppL10n.of(context);
    return {
      l.fieldName: r['name'] ?? '',
      l.fieldType: '${r['type'] ?? ''} / ${r['rate'] ?? 0}',
      l.commonStatus: _chip(r['enabled']),
      l.commonAction: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.edit, size: 18),
            onPressed: () => _upsert(row: r),
          ),
          IconButton(
            icon: Icon(Icons.delete, size: 18, color: AppColors.of(context).danger),
            onPressed: () => _delete(r),
          ),
        ],
      ),
    };
  }

  Widget _chip(dynamic enabled) {
    final l = AppL10n.of(context);
    final c = AppColors.of(context);
    final on = enabled is int ? enabled == 1 : '$enabled' == '1';
    return StatusBadge(
      label: on ? l.commonEnabled : l.commonDisabled,
      bg: on ? c.successBg : c.warningBg,
      fg: on ? c.successText : c.warningText,
    );
  }
}
