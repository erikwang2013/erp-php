// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 统计卡(主文档 §5.3):图标 40×40 r8 + 标签 + 数值(20/24 tabular)+ 趋势。
// 卡容器走全局 cardTheme(elevation 1 / r8 / 无色染);签名保持对外不变。
import 'package:flutter/material.dart';
import '../theme/app_tokens.dart';

class StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;

  /// Trend as a percentage delta (e.g. 12.5 for +12.5%).
  final double? trend;

  /// When false, a rising trend is treated as bad (rendered red) and a
  /// falling trend as good. Defaults to null → up is good, down is bad.
  final bool? trendIsGood;

  const StatCard({
    super.key,
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    this.trend,
    this.trendIsGood,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final compact = MediaQuery.sizeOf(context).width < 768;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant)),
                  const SizedBox(height: 4),
                  Text(value,
                      style: (compact ? AppText.stat20 : AppText.stat24)
                          .copyWith(color: cs.onSurface)),
                ],
              ),
            ),
            if (trend != null) _buildTrend(context),
          ],
        ),
      ),
    );
  }

  Widget _buildTrend(BuildContext context) {
    final tokens = AppColors.of(context);
    final up = trend! >= 0;
    final good = trendIsGood ?? true;
    final positive = good ? up : !up;
    final color = positive ? tokens.successText : tokens.dangerText;
    return Padding(
      padding: const EdgeInsets.only(left: 12),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(up ? Icons.arrow_upward : Icons.arrow_downward,
              size: 12, color: color),
          const SizedBox(width: 2),
          Text('${trend!.abs().toStringAsFixed(1)}%',
              style: TextStyle(
                  fontSize: 12, color: color, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
