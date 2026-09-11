// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 列表页表格容器(主文档 §5.2 + 视觉 3.0):整块内容包 surface 圆角卡容器
// (r12 + hairline 描边),卡内自上而下:可选页头行(模块色竖条+标题+「共 N 条」)
// → 搜索+筛选+操作行 → 加载/错误/空/表格(斑马纹) → 分页;
// 移动(<768)行高 56,桌面 44(行高取 AppMetrics.rowDesktop)。
// onRefresh(必填可空):非 null 时工具栏出现刷新按钮(loading 中禁用),
// 且内容区支持下拉刷新(RefreshIndicator)。数据页须显式传入以证明覆盖。
// 视觉 3.0 全部加性:pageTitle/moduleKey/primaryColumnIndex 不传时旧渲染不变。
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';
import '../theme/app_tokens.dart';
import 'data_table_view.dart';
import 'empty_state.dart';
import 'stacked_row_list.dart';

/// 模块→模块色(页头 8px 竖条;与 HOS V0-h moduleAccent 同源同值):
/// system/sales 蓝、hr/tms 紫、oms/wms 青、purchase 橙、mfg 绿、finance 红。
/// 色值零新增:chart_1..4 即语义 token 同值,chart_5/6 沿用 dashboard 注册
/// 图表色字面量(#722ED1/#13C2C2,两主题一致)。未收录模块回退品牌蓝,避免断色。
Color moduleAccent(String moduleKey) {
  if (moduleKey == 'hr' || moduleKey == 'tms') return const Color(0xFF722ED1);
  if (moduleKey == 'oms' || moduleKey == 'wms') return const Color(0xFF13C2C2);
  if (moduleKey == 'purchase') return const Color(0xFFFA8C16);
  if (moduleKey == 'mfg') return const Color(0xFF52C41A);
  if (moduleKey == 'finance') return const Color(0xFFFF4D4F);
  return const Color(0xFF1677FF);
}

class DataTableWrapper extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final int total, page, limit;
  final bool loading;

  /// 非空时显示错误态(含重试按钮),与空数据态区分。
  final String? error;
  final VoidCallback? onRetry;

  /// 页面重载回调(保持当前页/搜索/筛选);非 null 时启用下拉刷新与工具栏刷新按钮。
  /// 非网络取数的使用方(离线/演示渲染)显式传 null。
  final Future<void> Function()? onRefresh;

  final ValueChanged<int>? onPageChanged;
  final ValueChanged<String>? onSearch;

  /// Current search keyword; the search box is seeded with it so the text
  /// survives reloads.
  final String keyword;

  /// 搜索框占位提示；缺省用通用 commonSearchHint。语义特化的页面
  /// （如库存按商品名/编码/批次号）传后端 keyword 语义一致的文案。
  final String? keywordHint;
  final Widget? filterBar;
  final List<Widget>? actions;

  /// 右对齐列索引(§5.2「时间金额列右对齐」):该列表头与文本单元格右对齐,
  /// 文本单元格追加等宽数字特性(§3 tabular figures);值为 Widget 的单元格原样保留。
  /// 不传则渲染与旧版完全一致。
  final List<int> rightAlignColumns;

  /// 页头行标题(视觉 3.0);非 null 时卡内顶部渲染页头行:
  /// 模块色 8px 竖条 + 18/w600 标题 + 「共 N 条」计数。不传不渲染。
  final String? pageTitle;

  /// 页头竖条模块键,见 [moduleAccent](与 HOS V0-h 同源同色)。
  final String moduleKey;

  /// 主业务列索引(w600 强调,视觉 3.0);-1 不渲染强调。仅作用于纯文本单元格,
  /// 值为 Widget 的单元格原样保留。不传(-1)时渲染与旧版完全一致。
  final int primaryColumnIndex;

  /// 窄屏(<768)行堆叠开关(设计 §5.2);默认 false = 窄屏仍走横向滚动表格,
  /// 与旧渲染完全一致。只在 compact 分支生效,宽屏渲染不读此字段。
  final bool stackOnNarrow;

  /// 堆叠渲染的动作列索引(仅 [stackOnNarrow] 生效);-1 表示不单出动作行
  /// (动作仍按普通列渲染)。动作单元格 Widget 原样渲染在卡片底部右对齐,
  /// 见 [StackedRowList]。
  final int actionColumnIndex;

  const DataTableWrapper({
    super.key,
    required this.columns,
    required this.rows,
    required this.total,
    required this.page,
    required this.limit,
    this.loading = false,
    this.error,
    this.onRetry,
    required this.onRefresh,
    this.onPageChanged,
    this.onSearch,
    this.keyword = '',
    this.keywordHint,
    this.filterBar,
    this.actions,
    this.rightAlignColumns = const [],
    this.pageTitle,
    this.moduleKey = 'system',
    this.primaryColumnIndex = -1,
    this.stackOnNarrow = false,
    this.actionColumnIndex = -1,
  });

  @override
  Widget build(BuildContext context) {
    // limit<=0(如整页取数、无分页调用方传 0)时 0/0=NaN,.ceil() 抛异常 → 兜底为 1
    final tp = limit <= 0 ? 1 : (total / limit).ceil();
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 768;
        final scheme = Theme.of(context).colorScheme;
        final toolbar = <Widget>[
          if (onSearch != null)
            SizedBox(
              width: compact ? double.infinity : 240,
              child: _SearchField(initialText: keyword, onSearch: onSearch, hint: keywordHint),
            ),
          if (filterBar != null) ...[const SizedBox(width: 12), filterBar!],
          const Spacer(),
          // 下拉刷新等价入口:工具栏刷新按钮,loading 中禁用(防重复请求)
          if (onRefresh != null)
            IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: AppL10n.of(context).commonRefresh,
              onPressed: loading ? null : onRefresh,
            ),
          ...?actions,
        ];
        final c = AppColors.of(context);
        // 视觉 3.0:全内容包 surface 圆角卡容器,从 bgPage 上浮起(r12 + hairline)
        return Container(
          decoration: BoxDecoration(
            color: c.surface,
            borderRadius: BorderRadius.circular(AppMetrics.radiusCard),
            border: Border.all(color: c.divider),
          ),
          // 表头/斑马底色铺满行宽,卡内圆角由裁剪保证干净
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (pageTitle != null) ...[
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  child: Row(
                    children: [
                      // 8×16 模块色竖条(圆角 2)
                      Container(
                        width: AppMetrics.headBarW,
                        height: AppMetrics.headBarH,
                        decoration: BoxDecoration(
                          color: moduleAccent(moduleKey),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          pageTitle!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppText.pageTitle.copyWith(
                            color: c.textPrimary,
                          ),
                        ),
                      ),
                      // 单页时分页条不出计数，页头这枚就是唯一；多页时页脚已带
                      // 「共 N 条」，页头再显示会同一屏出现两处（P4①）。
                      if (tp <= 1) ...[
                        const SizedBox(width: 12),
                        Text(
                          AppL10n.of(context).commonTotalPages(total),
                          style: TextStyle(fontSize: 13, color: c.textSecondary),
                        ),
                      ],
                    ],
                  ),
                ),
                const _Hairline(),
              ],
              if (onSearch != null || actions != null || onRefresh != null) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  child: Row(children: toolbar),
                ),
                // 工具栏下缘 hairline：与页头分隔对称（有/无 pageTitle 均铺）
                const _Hairline(),
              ],
              Expanded(
                // onRefresh == null 保持旧结构;非 null 时内容区整体可下拉,四态各自
                // 为可滚动体(表格态用 DataTable2 内部滚动,其余态 AlwaysScrollable
                // 撑满视口居中)——data_table_2 文档禁外层无界滚动包裹。
                child: onRefresh == null
                    ? _stateBody(context, compact, scheme)
                    : LayoutBuilder(
                        builder: (context, c) {
                          final viewportH = c.maxHeight;
                          return RefreshIndicator(
                            onRefresh: () async {
                              // 加载中(骨架态)下拉不重复触发;按钮已在 loading 禁用
                              if (loading) return;
                              await onRefresh?.call();
                            },
                            // DataTable2 内部垂直滚动体在自嵌套 scrollable 之下,
                            // 放宽 depth,只认纵向通知
                            notificationPredicate: (n) =>
                                n.metrics.axis == Axis.vertical,
                            child: _refreshBody(
                              context,
                              compact,
                              scheme,
                              viewportH,
                            ),
                          );
                        },
                      ),
              ),
              if (tp > 1)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Text(
                        AppL10n.of(context).commonTotalPages(total),
                        style: TextStyle(
                          fontSize: 13,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(width: 16),
                      IconButton(
                        icon: const Icon(Icons.chevron_left, size: 20),
                        onPressed: page > 1
                            ? () => onPageChanged?.call(page - 1)
                            : null,
                      ),
                      Text(
                        '$page/${tp > 0 ? tp : 1}',
                        style: TextStyle(fontSize: 13, color: scheme.onSurface),
                      ),
                      IconButton(
                        icon: const Icon(Icons.chevron_right, size: 20),
                        onPressed: page < tp
                            ? () => onPageChanged?.call(page + 1)
                            : null,
                      ),
                    ],
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  /// 无刷新设施时的原版四态(直接交给 Expanded)。
  Widget _stateBody(BuildContext context, bool compact, ColorScheme scheme) {
    return _stateContent(context, compact, scheme);
  }

  /// 下拉刷新版内容:表格态返回 DataTable2(内部滚动,禁外层无界包裹);
  /// 骨架/错误/空态用 AlwaysScrollable 滚动体撑满视口,保持居中语义,
  /// 内容高度不足视口时下拉仍可触发。
  Widget _refreshBody(
    BuildContext context,
    bool compact,
    ColorScheme scheme,
    double viewportH,
  ) {
    final content = _stateContent(context, compact, scheme);
    if (content is DataTableView) {
      // DataTable2 不暴露 physics 参数:其内部滚动体在平台钳制物理下不接受
      // 拖拽(短表内容零滚动范围),下拉无法触发 → 注入 AlwaysScrollable。
      return ScrollConfiguration(
        behavior: const _PullScrollBehavior(),
        child: content,
      );
    }
    // 堆叠态自带 ListView(AlwaysScrollable 物理),直接交给 RefreshIndicator:
    // 落到下面的 SingleChildScrollView 会给 ListView 无界高度 → 布局崩溃。
    if (content is StackedRowList) return content;
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: ConstrainedBox(
        constraints: BoxConstraints(minHeight: viewportH),
        child: content,
      ),
    );
  }

  /// 四态内容本体:加载骨架 / 错误(含重试) / 空数据 / 数据表。
  Widget _stateContent(BuildContext context, bool compact, ColorScheme scheme) {
    if (loading) {
      // 加载态:3 行骨架(行高同数据行,surface_alt 50%),禁整页菊花(§5.2)
      return Column(
        children: [
          for (var i = 0; i < 3; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Container(
                height: compact ? AppMetrics.rowMobile : AppMetrics.rowDesktop,
                decoration: BoxDecoration(
                  color: scheme.surfaceContainerHighest.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(6),
                ),
              ),
            ),
        ],
      );
    }
    if (error != null) {
      // 加载失败与「暂无数据」必须可区分:错误态带重试按钮
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, color: scheme.error, size: 36),
            const SizedBox(height: 8),
            Text(error!, style: TextStyle(fontSize: 14, color: scheme.error)),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: onRetry,
              style: OutlinedButton.styleFrom(minimumSize: const Size(72, 32)),
              icon: const Icon(Icons.refresh, size: 16),
              label: Text(AppL10n.of(context).commonRetry),
            ),
          ],
        ),
      );
    }
    if (rows.isEmpty) {
      // 空数据态:共享 EmptyState(灰阶 mascot + 「暂无数据」),与错误态区分
      return EmptyState();
    }
    // 移动端行堆叠(opt-in,§5.2):窄屏且显式开启时不出表格;
    // 标题列兜底 0——主业务列未声明时取首列(各页首列即单号/编码/名称)。
    if (compact && stackOnNarrow) {
      return StackedRowList(
        columns: columns,
        rows: rows,
        titleColumn: primaryColumnIndex >= 0 ? primaryColumnIndex : 0,
        actionColumn: actionColumnIndex,
      );
    }
    return DataTableView(
      columns: columns,
      rows: rows,
      compact: compact,
      rightAlign: rightAlignColumns,
      primaryColumn: primaryColumnIndex,
    );
  }
}

/// 卡内 1px hairline 分隔线(主题 divider 色)。
class _Hairline extends StatelessWidget {
  const _Hairline();

  @override
  Widget build(BuildContext context) =>
      Container(height: 1, color: AppColors.of(context).divider);
}

/// 给 DataTable2 内部滚动体注入 AlwaysScrollableScrollPhysics:
/// 平台默认物理(Android 钳制)下零滚动范围内容不接受拖拽,下拉无法触发;
/// 叠加在 super 平台物理之上,保留原生惯性/钳制语义。
class _PullScrollBehavior extends MaterialScrollBehavior {
  const _PullScrollBehavior();

  @override
  ScrollPhysics getScrollPhysics(BuildContext context) =>
      AlwaysScrollableScrollPhysics(parent: super.getScrollPhysics(context));
}

/// Search input that keeps its text across wrapper rebuilds.
class _SearchField extends StatefulWidget {
  final String initialText;
  final ValueChanged<String>? onSearch;
  final String? hint;
  const _SearchField({required this.initialText, this.onSearch, this.hint});

  @override
  State<_SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<_SearchField> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialText,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _controller,
      // 边框/圆角/聚焦色走全局 inputDecorationTheme(§4:r6/primary 1.5)
      decoration: InputDecoration(
        hintText: widget.hint ?? AppL10n.of(context).commonSearchHint,
        prefixIcon: const Icon(Icons.search, size: 20),
      ),
      onSubmitted: widget.onSearch,
    );
  }
}
