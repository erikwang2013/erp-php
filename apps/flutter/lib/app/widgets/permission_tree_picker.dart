/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';

/// 权限树勾选器（纯组件，无 controller 依赖）。
///
/// 输入：后端嵌套权限树 [nodes]（节点 `{id, name, slug, type, children[]}`，
/// id 为 hashid 字符串，children 仅在有子节点时下发）+ 已授 id 集合
/// [initialSelectedIds]。
///
/// 三态级联：父勾选=全子勾、父清除=全子清、子部分选=父 indeterminate；
/// 点击 indeterminate/未勾父 = 全勾（父+全部后代），点击已勾父 = 全清；
/// 叶子勾选只影响自身。组行可展开/收起，深度任意自适应。
///
/// 输出：全勾节点 id 集合（含父子，与原授权逐 id 一致，不做自动闭包）
/// 经 [onChanged] 每次变更全量回调。
class PermissionTreePicker extends StatefulWidget {
  /// 后端权限树顶层节点数组（任意深度嵌套）。
  final List<Map<String, dynamic>> nodes;

  /// 已授/已勾 id 集合（hashid 字符串，原样比较）。
  final Set<String> initialSelectedIds;

  /// 每次勾选变更后的全量已勾 id 集合回调。
  final ValueChanged<Set<String>>? onChanged;

  /// 组行初始展开态，默认全展开（数百行时随弹框整体滚动）。
  final bool initiallyExpanded;

  const PermissionTreePicker({
    super.key,
    required this.nodes,
    this.initialSelectedIds = const {},
    this.onChanged,
    this.initiallyExpanded = true,
  });

  @override
  State<PermissionTreePicker> createState() => _PermissionTreePickerState();
}

class _PermissionTreePickerState extends State<PermissionTreePicker> {
  late Set<String> _selected;
  late Set<String> _collapsed;

  @override
  void initState() {
    super.initState();
    _selected = widget.initialSelectedIds.toSet();
    _collapsed = {};
    if (!widget.initiallyExpanded) {
      _collapseAll(widget.nodes);
    }
  }

  @override
  void didUpdateWidget(PermissionTreePicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialSelectedIds != widget.initialSelectedIds) {
      _selected = widget.initialSelectedIds.toSet();
    }
  }

  void _collapseAll(List<Map<String, dynamic>> nodes) {
    for (final n in nodes) {
      final children = _childrenOf(n);
      if (children.isNotEmpty) {
        _collapsed.add(_id(n));
        _collapseAll(children);
      }
    }
  }

  static String _id(Map<String, dynamic> node) => '${node['id']}';

  static List<Map<String, dynamic>> _childrenOf(Map<String, dynamic> node) =>
      (node['children'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .toList();

  /// 收集节点自身 + 全部后代 id（子树操作/判定用）。
  void _collectIds(Map<String, dynamic> node, Set<String> out) {
    out.add(_id(node));
    for (final c in _childrenOf(node)) {
      _collectIds(c, out);
    }
  }

  bool _hasDescendantSelected(Map<String, dynamic> node) {
    for (final c in _childrenOf(node)) {
      if (_selected.contains(_id(c)) || _hasDescendantSelected(c)) return true;
    }
    return false;
  }

  /// 切换节点勾选：父节点整棵子树全勾/全清，叶子只动自身。
  /// 依据点选前状态决定，indeterminate 也走「全勾」分支（与规格一致）。
  void _toggle(Map<String, dynamic> node) {
    setState(() {
      final subtree = <String>{};
      _collectIds(node, subtree);
      if (_selected.contains(_id(node))) {
        _selected.removeAll(subtree); // 已勾父 → 全清
      } else {
        _selected.addAll(subtree); // 未勾/indeterminate 父 → 全勾
      }
    });
    widget.onChanged?.call(_selected.toSet());
  }

  void _toggleCollapse(Map<String, dynamic> node) {
    setState(() {
      final id = _id(node);
      if (!_collapsed.remove(id)) _collapsed.add(id);
    });
  }

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    _buildRows(widget.nodes, rows, depth: 0);
    if (rows.isEmpty) return const SizedBox.shrink();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: rows,
    );
  }

  void _buildRows(
    List<Map<String, dynamic>> nodes,
    List<Widget> rows, {
    required int depth,
  }) {
    for (final node in nodes) {
      final id = _id(node);
      final children = _childrenOf(node);
      final hasChildren = children.isNotEmpty;
      final collapsed = _collapsed.contains(id);
      final checked = _selected.contains(id);
      // 自身未勾但有后代已勾 → 三态虚线（不把父自动计入输出）
      final indeterminate = !checked && _hasDescendantSelected(node);

      rows.add(
        Padding(
          key: ValueKey(id),
          padding: EdgeInsets.only(left: 8.0 + depth * 20),
          child: InkWell(
            onTap: () => _toggle(node),
            child: SizedBox(
              height: 44,
              child: Row(
                children: [
                  hasChildren
                      ? InkWell(
                          onTap: () => _toggleCollapse(node),
                          child: SizedBox(
                            width: 24,
                            child: Icon(
                              collapsed
                                  ? Icons.chevron_right
                                  : Icons.expand_more,
                              size: 20,
                              color: Theme.of(context).colorScheme.outline,
                            ),
                          ),
                        )
                      : const SizedBox(width: 24),
                  Checkbox(
                    // 三态值：indeterminate 渲染虚线框；onChanged 语义在 _toggle
                    // 内按「点选前状态」决定（tristate 点击 null 态产物不可依赖）。
                    value: checked ? true : (indeterminate ? null : false),
                    tristate: true,
                    onChanged: (_) => _toggle(node),
                  ),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${node['name'] ?? ''}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight:
                                hasChildren ? FontWeight.w600 : FontWeight.w400,
                          ),
                        ),
                        if ('${node['slug'] ?? ''}'.isNotEmpty)
                          Text(
                            '${node['slug']}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 12,
                              color: Theme.of(context).colorScheme.outline,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
      if (hasChildren && !collapsed) {
        _buildRows(children, rows, depth: depth + 1);
      }
    }
  }
}
