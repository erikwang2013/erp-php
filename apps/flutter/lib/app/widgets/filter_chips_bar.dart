// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 状态筛选 chips(主文档 §5.2):横向滚动,高 32、圆角 16、间距 8、内边距 0 16;
// 首项固定「全部」(值 null,includeAll 控制,文案 allLabel);
// 未选:白底 border 描边,字 13 text_secondary;选中:primary_bg 底、字 13/500 primary_pressed。
// 均为无状态受控组件(selected/onChanged 由页面持有),不订阅任何状态。
// 用法:
//   FilterChipsBar<String>(
//     options: [('pending','待审核'), ('done','已审核')],
//     selected: _filter, onChanged: (v) { setState(() => _filter = v); _load(); },
//     totalCount: _total,
//   )
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';
import '../theme/app_tokens.dart';

/// 状态筛选 chips 行(仅 chips,不自带滚动/计数——嵌页面自有的横向布局)。
/// 注意:内部用 Expanded,须置于横向有界父级(整行/Column 子级);勿放进
/// DataTableWrapper 的 filterBar 槽位——该工具行对子级无宽度约束。
class FilterChips<T> extends StatelessWidget {
  /// (值, 文案);值 null 即「全部」。
  final List<(T?, String)> options;

  /// 是否自动前置「全部」(值 null)。默认 true;文案取 [allLabel]。
  final bool includeAll;
  final String? allLabel;

  /// 当前选中值;null = 全部。选中态由页面控制刷新。
  final T? selected;
  final ValueChanged<T?> onChanged;

  const FilterChips({
    super.key,
    required this.options,
    this.includeAll = true,
    this.allLabel,
    required this.selected,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final list = [
      if (includeAll) (null, allLabel ?? l10n.commonAll),
      ...options,
    ];
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < list.length; i++)
          Padding(
            // 芯片间距 8;首项(「全部」)不前置间隔
            padding: EdgeInsetsDirectional.only(start: i == 0 ? 0 : 8),
            child: _StatusChip(
              label: list[i].$2,
              active: list[i].$1 == selected,
              onTap: () => onChanged(list[i].$1),
            ),
          ),
      ],
    );
  }
}

/// 筛选条:横向滚动 chips 行 + 行尾固定「共 N 条」(totalCount ≥ 0 时显示)。
/// 整条建议放列表容器上方独占一行(§5.2 位置);宽度有界时方可用(见 FilterChips)。
class FilterChipsBar<T> extends StatelessWidget {
  final List<(T?, String)> options;
  final bool includeAll;
  final String? allLabel;
  final T? selected;
  final ValueChanged<T?> onChanged;

  /// 结果计数;非 null 时显示「共 N 条」(§5.2:放 chips 行右端或列表底部,不重复放)。
  final int? totalCount;

  const FilterChipsBar({
    super.key,
    required this.options,
    this.includeAll = true,
    this.allLabel,
    required this.selected,
    required this.onChanged,
    this.totalCount,
  });

  @override
  Widget build(BuildContext context) {
    final chips = FilterChips<T>(
      options: options,
      includeAll: includeAll,
      allLabel: allLabel,
      selected: selected,
      onChanged: onChanged,
    );
    final count = totalCount;
    return Row(children: [
      Expanded(
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: chips,
        ),
      ),
      if (count != null) ...[
        const SizedBox(width: 12),
        Text(AppL10n.of(context).commonTotalPages(count),
            style: TextStyle(fontSize: 12, color: AppColors.of(context).textHint)),
      ],
    ]);
  }
}

/// 单颗 chip(§5.2):h32/r16/padding h16,选中 primary_bg + pressed 字,描边不位移。
class _StatusChip extends StatelessWidget {
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _StatusChip({
    required this.label,
    required this.active,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 32,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: active ? c.primaryBg : c.surface,
          // 选中时描边取 primary_bg(同底色不可见),避免 1px 几何跳动
          border: Border.all(color: active ? c.primaryBg : c.border),
          borderRadius: BorderRadius.circular(AppMetrics.radiusChip),
        ),
        child: Text(
          label,
          maxLines: 1,
          style: TextStyle(
            fontSize: 13,
            color: active ? c.primaryPressed : c.textSecondary,
            fontWeight: active ? FontWeight.w500 : FontWeight.w400,
          ),
        ),
      ),
    );
  }
}
