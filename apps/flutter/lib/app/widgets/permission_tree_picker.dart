/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';

/// 权限树可见行条目（纯函数 [collectVisibleRows] 产物，渲染与单测共用）。
class PermissionTreeRow {
  const PermissionTreeRow(this.node, this.depth);

  /// 树节点（`{id, name, slug, type, children[]}`，id 为 hashid 字符串）。
  final Map<String, dynamic> node;

  /// 缩进深度（顶层为 0）。
  final int depth;
}

/// 收集可见行（纯函数）：行序=先序 DFS，节点自身恒占一行；
/// 节点 id 在 [collapsed] 中时其整棵子树剪枝（不产出任何行）。
/// 折叠态下数百节点树首屏仅产出少量行，供 ListView.builder 惰性挂载。
List<PermissionTreeRow> collectVisibleRows(
  List<Map<String, dynamic>> nodes,
  Set<String> collapsed,
) {
  final rows = <PermissionTreeRow>[];
  void walk(List<Map<String, dynamic>> list, int depth) {
    for (final node in list) {
      rows.add(PermissionTreeRow(node, depth));
      final children = _childrenOf(node);
      if (children.isNotEmpty && !collapsed.contains(_idOf(node))) {
        walk(children, depth + 1);
      }
    }
  }

  walk(nodes, 0);
  return rows;
}

/// 按 [initiallyExpanded] 三态计算初始折叠 id 集：
/// null（默认）= 仅展开第一级（顶层组展开、孙级组折叠——数百节点树首屏
/// 只渲染「顶层+直接子」几十行）；true = 全展开（空集）；false = 仅顶层可见
/// （所有含子节点组折叠）。
Set<String> initialCollapsedIds(
  List<Map<String, dynamic>> nodes, {
  bool? initiallyExpanded,
}) {
  if (initiallyExpanded == true) return {};
  final collapsed = <String>{};
  void walk(List<Map<String, dynamic>> list, int depth) {
    for (final node in list) {
      final children = _childrenOf(node);
      if (children.isNotEmpty &&
          (initiallyExpanded == false || depth >= 1)) {
        collapsed.add(_idOf(node));
      }
      walk(children, depth + 1);
    }
  }

  walk(nodes, 0);
  return collapsed;
}

/// 预计算每节点「严格后代 id 集」（不含自身），把逐行 O(深度) 递归判定
/// 降为 Set 查询；树不变时结果可跨渲染复用（折叠/勾选不重建）。
Map<String, Set<String>> collectDescendantSets(
  List<Map<String, dynamic>> nodes,
) {
  final out = <String, Set<String>>{};
  void walk(List<Map<String, dynamic>> list) {
    for (final node in list) {
      final children = _childrenOf(node);
      walk(children); // 后序：子节点条目先就绪
      final set = <String>{};
      for (final c in children) {
        set
          ..add(_idOf(c))
          ..addAll(out[_idOf(c)]!);
      }
      out[_idOf(node)] = set;
    }
  }

  walk(nodes);
  return out;
}

String _idOf(Map<String, dynamic> node) => '${node['id']}';

List<Map<String, dynamic>> _childrenOf(Map<String, dynamic> node) =>
    (node['children'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList();

/// 收集节点自身 + 全部后代 id（勾选子树操作用，不受折叠影响）。
void _collectSubtreeIds(Map<String, dynamic> node, Set<String> out) {
  out.add(_idOf(node));
  for (final c in _childrenOf(node)) {
    _collectSubtreeIds(c, out);
  }
}

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
/// 性能（P1-f）：行渲染惰性化——可见行=未折叠节点先序 DFS 收集存 State，
/// ListView.builder 承接；默认仅展开第一级，数百节点树首屏几十行，孙级按需
/// 展开；后代判定用预计算 descendant 集合（Set 查询），无逐行递归。
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

  /// 组行初始展开态：null（默认）= 仅展开第一级（性能默认）；true = 全展开
  /// （历史默认，依赖全展开的调用方显式传 true 保持原样）；false = 仅顶层行。
  final bool? initiallyExpanded;

  const PermissionTreePicker({
    super.key,
    required this.nodes,
    this.initialSelectedIds = const {},
    this.onChanged,
    this.initiallyExpanded,
  });

  @override
  State<PermissionTreePicker> createState() => _PermissionTreePickerState();
}

class _PermissionTreePickerState extends State<PermissionTreePicker> {
  late Set<String> _selected;
  late Set<String> _collapsed;
  late List<PermissionTreeRow> _visibleRows;
  Map<String, Set<String>> _descendantSets = const {};

  @override
  void initState() {
    super.initState();
    _selected = widget.initialSelectedIds.toSet();
    _collapsed =
        initialCollapsedIds(widget.nodes, initiallyExpanded: widget.initiallyExpanded);
    _descendantSets = collectDescendantSets(widget.nodes);
    _visibleRows = collectVisibleRows(widget.nodes, _collapsed);
  }

  @override
  void didUpdateWidget(PermissionTreePicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.nodes, widget.nodes)) {
      // 树变了：后代集与折叠集（按初始展开三态）随新树重建；
      // 仅勾选集 _selected 保留（授权上下文不变，只随 initialSelectedIds 换）
      _descendantSets = collectDescendantSets(widget.nodes);
      _collapsed =
          initialCollapsedIds(widget.nodes, initiallyExpanded: widget.initiallyExpanded);
    } else if (oldWidget.initiallyExpanded != widget.initiallyExpanded) {
      _collapsed =
          initialCollapsedIds(widget.nodes, initiallyExpanded: widget.initiallyExpanded);
    }
    if (oldWidget.initialSelectedIds != widget.initialSelectedIds) {
      _selected = widget.initialSelectedIds.toSet();
    }
    _visibleRows = collectVisibleRows(widget.nodes, _collapsed);
  }

  /// 切换节点勾选：父节点整棵子树全勾/全清（含折叠中不可见后代），
  /// 叶子只动自身。依据点选前状态决定，indeterminate 也走「全勾」分支
  /// （与规格一致）。
  void _toggle(Map<String, dynamic> node) {
    setState(() {
      final subtree = <String>{};
      _collectSubtreeIds(node, subtree);
      if (_selected.contains(_idOf(node))) {
        _selected.removeAll(subtree); // 已勾父 → 全清
      } else {
        _selected.addAll(subtree); // 未勾/indeterminate 父 → 全勾
      }
    });
    widget.onChanged?.call(_selected.toSet());
  }

  void _toggleCollapse(Map<String, dynamic> node) {
    setState(() {
      final id = _idOf(node);
      if (!_collapsed.remove(id)) _collapsed.add(id);
      _visibleRows = collectVisibleRows(widget.nodes, _collapsed);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_visibleRows.isEmpty) return const SizedBox.shrink();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // 惰性容器：shrinkWrap 承接弹框内容区约束（外层 SingleChildScrollView
        // 负责滚动，故本层禁自身滚动）；行数已被折叠剪枝（默认态几十行）。
        ListView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          itemCount: _visibleRows.length,
          itemBuilder: (context, index) {
            final row = _visibleRows[index];
            return _buildRow(context, row.node, row.depth);
          },
        ),
      ],
    );
  }

  Widget _buildRow(
    BuildContext context,
    Map<String, dynamic> node,
    int depth,
  ) {
    final id = _idOf(node);
    final children = _childrenOf(node);
    final hasChildren = children.isNotEmpty;
    final collapsed = _collapsed.contains(id);
    final checked = _selected.contains(id);
    // 自身未勾但有后代已勾 → 三态虚线（不把父自动计入输出）。
    // 后代判定走预计算 descendant 集合：每行 O(1) 起步的 Set 查询。
    final indeterminate =
        !checked && (_descendantSets[id]?.any(_selected.contains) ?? false);

    return Padding(
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
    );
  }
}
