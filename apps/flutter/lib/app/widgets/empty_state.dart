// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 共享空态组件(视觉 2.0 质感轴):灰阶去饱和 mascot + 文案 + 可选操作,
// 替换各页直接 Center(Icons.*) 的空态写法;默认文案 commonNoData。
// ponytail: 无 flutter_svg 无法矢量去色,mascot「线稿化」以灰阶 PNG 近似
// (grayscale + 低透明度);真线稿需设计源稿,列入债务由设计补图。
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';

class EmptyState extends StatelessWidget {
  final String? title;
  final String? subtitle;
  final Widget? action;
  final double mascotSize;

  const EmptyState({
    super.key,
    this.title,
    this.subtitle,
    this.action,
    this.mascotSize = 96,
  });

  static const _grey = <double>[
    0.2126, 0.7152, 0.0722, 0, 0, //
    0.2126, 0.7152, 0.0722, 0, 0, //
    0.2126, 0.7152, 0.0722, 0, 0, //
    0, 0, 0, 0.45, 0,
  ];

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ColorFiltered(
            colorFilter: const ColorFilter.matrix(_grey),
            child: Image.asset(
              'assets/mascot.png',
              width: mascotSize,
              height: mascotSize,
            ),
          ),
          if (title != null || subtitle == null) ...[
            const SizedBox(height: 12),
            Text(
              title ?? AppL10n.of(context).commonNoData,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: scheme.onSurfaceVariant,
              ),
            ),
          ],
          if (subtitle != null) ...[
            const SizedBox(height: 6),
            Text(
              subtitle!,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: scheme.outline),
            ),
          ],
          if (action != null) ...[const SizedBox(height: 12), action!],
        ],
      ),
    );
  }
}
