// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 状态徽标(主文档 §5.6 + §2.4 + 视觉 3.0):胶囊形(StadiumBorder)、
// 高 22、内边距 0 8、字 12/500。
// 调用方按 §2.4 四类映射给 bg/fg(如 warningBg+warningText);
// 纯展示组件,不读 context,可 const 构造。默认浅底式;
// 实心版(桌面表格列 danger/success 用):单色底白字,高 18 字 10。
// 用法:StatusBadge(label: '已审核', bg: c.successBg, fg: c.successText)
//       StatusBadge.solid(label: '作废', color: c.danger)
import 'package:flutter/material.dart';

class StatusBadge extends StatelessWidget {
  /// 文案(单行)。
  final String label;

  /// 徽标底色(浅底式 = §2.4 语义 *_bg)。
  final Color bg;

  /// 文字色(浅底式 = §2.4 语义 *_text / primary_pressed)。
  final Color fg;

  /// 高度(§5.6 默认 22;实心版 18)。
  final double height;

  /// 字号(默认 12;实心版 10)。
  final double fontSize;

  const StatusBadge({
    super.key,
    required this.label,
    required this.bg,
    required this.fg,
    this.height = 22,
    this.fontSize = 12,
  });

  /// 实心变体:bg+fg 退化为单一 `color` 实底、白字(§5.6「高 18 字 10,白字,
  /// 仅 danger/success 于桌面表格列」可选)。两端默认仍是上面的浅底式。
  const StatusBadge.solid({
    super.key,
    required this.label,
    required Color color,
  }) : bg = color,
       fg = Colors.white,
       height = 18,
       fontSize = 10;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      alignment: Alignment.center,
      // 胶囊(视觉 3.0:原 r4 方角升胶囊,与 chips 同语义)
      decoration: ShapeDecoration(color: bg, shape: const StadiumBorder()),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: fg,
          fontSize: fontSize,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }
}
