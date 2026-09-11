// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'dashboard_page.dart';

class _TrendChart extends StatelessWidget {
  const _TrendChart(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.dashboardTrend,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 300,
              // 空数据分支:无任何有效序列时画空态文案
              child: !controller.trendSpots.any((s) => s.isNotEmpty)
                  ? EmptyState(mascotSize: 56)
                  : LineChart(
                      LineChartData(
                        gridData: FlGridData(
                          show: true,
                          drawVerticalLine: false,
                          horizontalInterval: 10,
                        ),
                        titlesData: FlTitlesData(
                          bottomTitles: AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          leftTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              reservedSize: 40,
                              getTitlesWidget: (v, _) => Text('${v.toInt()}'),
                            ),
                          ),
                          topTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          rightTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                        ),
                        borderData: FlBorderData(show: false),
                        lineBarsData: [
                          for (var i = 0; i < controller.trendSpots.length; i++)
                            LineChartBarData(
                              spots: controller.trendSpots[i],
                              color: _chartColors(
                                c,
                              )[i % _chartColors(c).length],
                              barWidth: 2.4,
                              isCurved: true,
                              dotData: const FlDotData(show: false),
                              belowBarData: BarAreaData(
                                show: true,
                                color:
                                    _chartColors(c)[i % _chartColors(c).length]
                                        .withValues(alpha: 0.12),
                              ),
                            ),
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DistributionChart extends StatelessWidget {
  const _DistributionChart(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.dashboardUserStatus,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            // 空数据分支:无用户分布时画空态文案
            SizedBox(
              height: 200,
              child: controller.pieSections.isEmpty
                  ? EmptyState(mascotSize: 48)
                  : PieChart(
                      PieChartData(
                        sections: controller.pieSections,
                        centerSpaceRadius: 40,
                        sectionsSpace: 2,
                      ),
                    ),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // 图例与 controller.pieSections 切片同源（token 主色/成功色 = 注册图表色 #1677FF/#52C41A）
                _Legend(c.primary, l10n.dashboardEnabled),
                const SizedBox(width: 24),
                _Legend(c.success, l10n.dashboardDisabled),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend(this.color, this.label);
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }
}

class _RecentLogs extends StatelessWidget {
  const _RecentLogs(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.dashboardRecentOps,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            ...controller.recentLogs
                .take(8)
                .map(
                  (log) => ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      radius: 14,
                      backgroundColor: c.primaryBg,
                      child: Text(
                        log['user_name'][0].toUpperCase(),
                        style: TextStyle(fontSize: 12, color: c.primary),
                      ),
                    ),
                    title: Text(
                      log['action'],
                      style: const TextStyle(fontSize: 13),
                    ),
                    subtitle: Text(
                      log['created_at'] ?? '',
                      style: const TextStyle(fontSize: 11),
                    ),
                    trailing: Text(
                      log['ip'] ?? '',
                      style: TextStyle(fontSize: 11, color: c.textHint),
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

class _TrendBadge extends StatelessWidget {
  const _TrendBadge(this.trend);
  final double trend;

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    final isUp = trend >= 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: isUp ? c.successBg : c.dangerBg,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isUp ? Icons.arrow_upward : Icons.arrow_downward,
            size: 12,
            color: isUp ? c.success : c.danger,
          ),
          Text(
            '${trend.abs()}%',
            style: TextStyle(fontSize: 11, color: isUp ? c.success : c.danger),
          ),
        ],
      ),
    );
  }
}

IconData _getIcon(String name) {
  switch (name) {
    case 'people':
      return Icons.people;
    case 'person_add':
      return Icons.person_add;
    case 'bolt':
      return Icons.bolt;
    default:
      return Icons.description;
  }
}

/// 图表序列调色板:主色/成功/警示 + 注册图表色(chart_5/chart_6),两主题一致。
List<Color> _chartColors(AppPalette c) => [
  c.primary,
  c.success,
  c.warning,
  const Color(0xFF722ED1),
  const Color(0xFF13C2C2),
];

class _CountUpValue extends StatelessWidget {
  final String value;
  const _CountUpValue({required this.value});

  @override
  Widget build(BuildContext context) {
    final target = double.tryParse(value.trim());
    if (target == null) return _text(value);
    if (MediaQuery.disableAnimationsOf(context)) {
      return _text('${target.round()}');
    }
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: target),
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeOutCubic,
      builder: (_, v, _) => _text('${v.round()}'),
    );
  }

  Widget _text(String v) => Text(
    v,
    style: const TextStyle(
      fontSize: 24,
      fontWeight: FontWeight.w700,
      height: 1.3,
      fontFeatures: [FontFeature.tabularFigures()],
    ),
  );
}

/// 迷你 sparkline:折线 + 渐变面积(仅绘制,零新依赖)。
class _SparklinePainter extends CustomPainter {
  final List<double> points;
  final Color color;
  const _SparklinePainter({required this.points, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    if (points.length < 2 || size.width <= 0 || size.height <= 0) return;
    var min = points.first, max = points.first;
    for (final p in points) {
      if (p < min) min = p;
      if (p > max) max = p;
    }
    final span = (max - min) == 0 ? 1.0 : (max - min);
    final top = 3.0, bottom = size.height - 3.0;
    Offset at(int i) {
      final x = size.width * i / (points.length - 1);
      final y = bottom - (points[i] - min) / span * (bottom - top);
      return Offset(x, y);
    }

    final line = Path()..moveTo(at(0).dx, at(0).dy);
    for (var i = 1; i < points.length; i++) {
      line.lineTo(at(i).dx, at(i).dy);
    }
    final fill = Path.from(line)
      ..lineTo(size.width, bottom)
      ..lineTo(0, bottom)
      ..close();
    canvas.drawPath(
      fill,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            color.withValues(alpha: 0.18),
            color.withValues(alpha: 0.02),
          ],
        ).createShader(Offset.zero & size),
    );
    canvas.drawPath(
      line,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.6
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(covariant _SparklinePainter old) =>
      old.points != points || old.color != color;
}
