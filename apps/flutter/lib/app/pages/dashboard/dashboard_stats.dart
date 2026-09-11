// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'dashboard_page.dart';

class _StatsGrid extends StatelessWidget {
  const _StatsGrid(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    // 后端返回的统计卡片（最多展示 4 张）；为空（接口失败/无数据）时给出空态，
    // 避免固定 itemCount: 4 访问空列表导致 RangeError 崩溃。
    final stats = controller.stats;
    if (stats.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 32),
        child: Center(
          child: Text(
            AppL10n.of(context).dashboardNoData,
            style: TextStyle(fontSize: 13, color: c.textHint),
          ),
        ),
      );
    }
    final itemCount = stats.length > 4 ? 4 : stats.length;
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = constraints.maxWidth > 900 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: crossAxisCount,
            mainAxisExtent: 136,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
          ),
          itemCount: itemCount,
          itemBuilder: (context, index) => _KpiCard(controller, stats[index]),
        );
      },
    );
  }
}

/// KPI 卡(视觉 2.0):图标+标签行 → 数值 400ms 滚动 → 迷你 sparkline。
/// sparkline 数据源见 [_seriesDataFor];无对应序列时不画,卡面不塌。
class _KpiCard extends StatelessWidget {
  const _KpiCard(this.controller, this.stat);
  final DashboardController controller;
  final Map<String, dynamic> stat;

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    final color = Color(
      int.parse('0xFF${stat['color'].replaceFirst('#', '')}'),
    );
    final spark = _seriesDataFor(controller, stat);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(_getIcon(stat['icon']), color: color, size: 18),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${stat['label']}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13, color: c.textSecondary),
                  ),
                ),
                if (stat['trend'] != null) _TrendBadge(stat['trend']),
              ],
            ),
            const SizedBox(height: 6),
            _CountUpValue(value: '${stat['value']}'),
            if (spark != null) ...[
              const SizedBox(height: 4),
              SizedBox(
                width: double.infinity,
                height: 24,
                child: CustomPaint(
                  painter: _SparklinePainter(points: spark, color: color),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// KPI 迷你 sparkline 数据映射(批1 契约):趋势序列带 series_key(users/logs)、
/// 统计卡带 icon 字符串(people/description)——按 icon→series_key 推导优先,
/// 断开 label 字符串耦合;无 series_key 的旧缓存/夹具保持原 name==label 与
/// icon=people「累计*」两重 fallback,行为不变。无则 null。
List<double>? _seriesDataFor(
  DashboardController controller,
  Map<String, dynamic> stat,
) {
  final series = <Map<String, dynamic>>[
    for (final s in (controller.trends['series'] as List<dynamic>?) ?? const [])
      Map<String, dynamic>.from(s as Map),
  ];
  final byKey = <String, List<Map<String, dynamic>>>{};
  for (final s in series) {
    final k = s['series_key'];
    if (k is String && k.isNotEmpty) {
      byKey.putIfAbsent(k, () => []).add(s);
    }
  }
  // 统计图标 → 序列语义键(与后端 bi stats/trends 同源)
  final statKey = switch (stat['icon']) {
    'people' => 'users',
    'description' => 'logs',
    _ => null,
  };
  if (statKey != null) {
    for (final s in byKey[statKey] ?? const <Map<String, dynamic>>[]) {
      final d = _numList(s['data']);
      if (d != null) return d;
    }
  }
  for (final s in series) {
    if (s['name'] == stat['label']) {
      return _numList(s['data']);
    }
  }
  if (stat['icon'] == 'people') {
    for (final s in series) {
      if ('${s['name']}'.contains('累计')) return _numList(s['data']);
    }
  }
  return null;
}

List<double>? _numList(dynamic data) {
  final list = data as List<dynamic>? ?? const [];
  final out = [for (final e in list) ((e as num?) ?? 0).toDouble()];
  return out.isEmpty ? null : out;
}
