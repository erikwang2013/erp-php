// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
part of 'dashboard_page.dart';

class _SalesTrendCard extends StatelessWidget {
  const _SalesTrendCard(this.controller);
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
              l10n.dashboardSalesTrend,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 240,
              // 空数据分支:画空态文案,不炸图表
              child: controller.salesTrendSpots.isEmpty
                  ? EmptyState(mascotSize: 56)
                  : LineChart(
                      LineChartData(
                        gridData: FlGridData(
                          show: true,
                          drawVerticalLine: false,
                        ),
                        titlesData: FlTitlesData(
                          bottomTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          leftTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              reservedSize: 50,
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
                          LineChartBarData(
                            spots: controller.salesTrendSpots,
                            color: c.primary,
                            barWidth: 2.4,
                            isCurved: true,
                            dotData: const FlDotData(show: false),
                            belowBarData: BarAreaData(
                              show: true,
                              color: c.primary.withValues(alpha: 0.12),
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

class _TopProductsCard extends StatelessWidget {
  const _TopProductsCard(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    final products =
        controller.bizSales['top_products'] as List<dynamic>? ?? [];
    final maxQty = products.fold<double>(
      0,
      (m, p) => ((p['quantity'] as num?) ?? 0).toDouble() > m
          ? (p['quantity'] as num).toDouble()
          : m,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.dashboardTopProducts,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            ...products.map(
              (p) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${p['name'] ?? '-'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 13),
                          ),
                        ),
                        Text(
                          '${p['quantity'] ?? 0}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: maxQty > 0
                            ? ((p['quantity'] as num?) ?? 0).toDouble() / maxQty
                            : 0,
                        minHeight: 6,
                        backgroundColor: c.divider,
                        color: c.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (products.isEmpty)
              Text(
                l10n.dashboardNoData,
                style: TextStyle(fontSize: 13, color: c.textHint),
              ),
          ],
        ),
      ),
    );
  }
}

class _OrderStatusCard extends StatelessWidget {
  const _OrderStatusCard(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    final list =
        controller.bizSales['status_distribution'] as List<dynamic>? ?? [];
    // 图例与 controller.orderStatusSections 的饼图切片颜色按下标一一对应：前三色为 token
    // 主色/成功/警示（两主题下与注册图表色一致），后两色保持注册图表字面量（chart_5/chart_6）
    final colors = [
      c.primary,
      c.success,
      c.warning,
      const Color(0xFF722ED1),
      const Color(0xFF13C2C2),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.dashboardOrderStatus,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            // 空数据分支:环形图空态文案
            SizedBox(
              height: 160,
              child: list.isEmpty
                  ? EmptyState(mascotSize: 48)
                  : PieChart(
                      PieChartData(
                        sections: controller.orderStatusSections,
                        centerSpaceRadius: 36,
                        sectionsSpace: 2,
                      ),
                    ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 16,
              runSpacing: 8,
              children: [
                for (var i = 0; i < list.length; i++)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 12,
                        height: 12,
                        decoration: BoxDecoration(
                          color: colors[i % colors.length],
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${list[i]['name']} ${list[i]['value']}',
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AgingCard extends StatelessWidget {
  const _AgingCard(this.title, this.aging);
  final String title;
  final dynamic aging;

  @override
  Widget build(BuildContext context) {
    final c = AppColors.of(context);
    final list = aging as List<dynamic>? ?? [];
    final maxValue = list.fold<double>(
      0,
      (m, b) => ((b['value'] as num?) ?? 0).toDouble() > m
          ? (b['value'] as num).toDouble()
          : m,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            ...list.map(
              (b) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${b['name'] ?? '-'}',
                            style: const TextStyle(fontSize: 13),
                          ),
                        ),
                        Text(
                          '${b['value'] ?? 0}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: maxValue > 0
                            ? ((b['value'] as num?) ?? 0).toDouble() / maxValue
                            : 0,
                        minHeight: 6,
                        backgroundColor: c.divider,
                        color: const Color(0xFF722ED1),
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

class _InventoryCard extends StatelessWidget {
  const _InventoryCard(this.controller);
  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    final c = AppColors.of(context);
    final d = controller.bizInventory;
    final items = <Map<String, dynamic>>[
      {
        'label': l10n.dashboardInvValue,
        'value': '${d['total_value'] ?? 0}',
        'icon': Icons.inventory_2,
        'color': c.primary,
      },
      {
        'label': l10n.dashboardInvLowAlert,
        'value': '${d['alert_low'] ?? 0}',
        'icon': Icons.warning_amber,
        'color': c.warning,
      },
      // 原 #EB2F96 未注册；改用注册图表色 chart_6（#13C2C2），页面自持色不受外部同步约束
      {
        'label': l10n.dashboardInvHighAlert,
        'value': '${d['alert_high'] ?? 0}',
        'icon': Icons.trending_up,
        'color': const Color(0xFF13C2C2),
      },
    ];
    return Row(
      children: [
        for (final s in items) ...[
          Expanded(
            child: StatCard(
              title: s['label'],
              value: s['value'],
              icon: s['icon'],
              color: s['color'],
            ),
          ),
          if (s != items.last) const SizedBox(width: 16),
        ],
      ],
    );
  }
}
