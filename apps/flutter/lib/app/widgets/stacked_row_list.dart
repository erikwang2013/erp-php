// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 窄屏(<768)行堆叠渲染(设计 §5.2):移动端列表页不再横向滚动表格,改纵向卡片流。
// 仅当调用方显式开启 DataTableWrapper.stackOnNarrow 时进入此路径,宽屏不经过。
import 'package:flutter/material.dart';
import '../theme/app_tokens.dart';

/// 把「列头 + 行 Map」的数据渲染为纵向卡片列表(移动端,无横向滚动)。
///
/// - 标题列 [titleColumn]:卡片首行(15/w600 主色强调)——主业务列在移动端
///   必须一眼可见,与宽屏 w600 主列强调同一语义;
/// - 其余列:逐行「标签(13/secondary) + 值(13/primary)」,标签定宽 88 保证值左缘对齐;
/// - 动作列 [actionColumn](>=0 时):单元格 Widget 原样放卡片底部右对齐,与内容以
///   hairline 相隔——4 个 48px 图标贴右侧竖排会占掉 375px 屏宽的 51%,底部整行
///   只占一行高,故取底部而非右侧动作栏;
/// - 值为 Widget 的单元格原样保留(状态徽标/动作行),与表格渲染同一规则;
/// - 自带纵向滚动体(AlwaysScrollable):内容不足一屏也能触发下拉刷新。
class StackedRowList extends StatelessWidget {
  const StackedRowList({
    super.key,
    required this.columns,
    required this.rows,
    required this.titleColumn,
    this.actionColumn = -1,
  });

  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final int titleColumn;
  final int actionColumn;

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      itemCount: rows.length,
      itemBuilder: (context, i) => Column(
        children: [
          // 卡片间 hairline(与表格行分隔同色);首张不加,免与外框重复
          if (i > 0) Divider(height: 1, color: c.divider),
          _card(context, rows[i]),
        ],
      ),
    );
  }

  Widget _card(BuildContext context, Map<String, dynamic> row) {
    final c = AppColors.of(context);
    final action = (actionColumn >= 0 && actionColumn < columns.length)
        ? row[columns[actionColumn]]
        : null;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _text(row[columns[titleColumn]], 15, FontWeight.w600, c.textPrimary),
          for (var j = 0; j < columns.length; j++)
            if (j != titleColumn && j != actionColumn)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 88,
                      child: _text(columns[j], 13, null, c.textSecondary),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _text(row[columns[j]], 13, null, c.textPrimary),
                    ),
                  ],
                ),
              ),
          if (action != null) ...[
            const SizedBox(height: 10),
            Divider(height: 1, color: c.divider),
            Align(alignment: Alignment.centerRight, child: _asWidget(action)),
          ],
        ],
      ),
    );
  }

  /// Widget 单元格原样保留(动作行/状态徽标);纯值转 Text。
  Widget _asWidget(dynamic v) => v is Widget ? v : Text('${v ?? ''}');

  /// 统一文本样式(标签/值同尺寸不同色,标题加重)。
  Widget _text(dynamic v, double size, FontWeight? weight, Color color) =>
      DefaultTextStyle.merge(
        style: TextStyle(fontSize: size, fontWeight: weight, color: color),
        child: _asWidget(v),
      );
}
