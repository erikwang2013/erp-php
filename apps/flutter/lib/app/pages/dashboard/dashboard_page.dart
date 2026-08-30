// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../widgets/stat_card.dart';
import '../../l10n/app_l10n.dart';
import 'dashboard_controller.dart';

class DashboardPage extends GetView<DashboardController> {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppL10n.of(context);
    Get.put(DashboardController());
    return DefaultTabController(
      length: 5,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 0),
            child: Row(
              children: [
                Text(l10n.dashboardTitle,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
                const Spacer(),
                PopupMenuButton<String>(
                  icon: const Icon(Icons.download),
                  tooltip: l10n.dashboardExport,
                  onSelected: (type) {
                    if (type == 'pdf') controller.exportPdf();
                    if (type == 'excel') controller.exportExcel();
                  },
                  itemBuilder: (_) => [
                    PopupMenuItem(value: 'pdf', child: ListTile(leading: const Icon(Icons.picture_as_pdf), title: Text(l10n.dashboardExportPdf), dense: true)),
                    PopupMenuItem(value: 'excel', child: ListTile(leading: const Icon(Icons.table_chart), title: Text(l10n.dashboardExportExcel), dense: true)),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 0),
            child: TabBar(
              tabs: [
                Tab(text: l10n.dashboardOverview),
                Tab(text: l10n.dashboardBiz),
                const Tab(text: 'OMS'),
                const Tab(text: 'WMS'),
                const Tab(text: 'TMS'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              children: [
                _overview(context),
                _bizTab(context),
                Obx(() => _opsTab(controller.omsStats)),
                Obx(() => _opsTab(controller.wmsStats)),
                Obx(() => _opsTab(controller.tmsStats)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// 经营看板：销售趋势/热销商品/订单状态 + 应收应付账龄 + 库存预警。
  Widget _bizTab(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Obx(() {
      if (controller.bizSales.isEmpty) {
        return const Center(child: CircularProgressIndicator());
      }
      return SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 2, child: _buildSalesTrendCard(context)),
                const SizedBox(width: 24),
                Expanded(flex: 1, child: _buildTopProductsCard(context)),
              ],
            ),
            const SizedBox(height: 24),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: _buildOrderStatusCard(context)),
                const SizedBox(width: 24),
                Expanded(child: _buildAgingCard(context, l10n.dashboardArAging, controller.bizFinance['ar_aging'])),
                const SizedBox(width: 24),
                Expanded(child: _buildAgingCard(context, l10n.dashboardApAging, controller.bizFinance['ap_aging'])),
              ],
            ),
            const SizedBox(height: 24),
            _buildInventoryCard(context),
          ],
        ),
      );
    });
  }

  Widget _buildSalesTrendCard(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardSalesTrend, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 240,
              child: LineChart(
                LineChartData(
                  gridData: FlGridData(show: true, drawVerticalLine: false),
                  titlesData: FlTitlesData(
                    bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: true, reservedSize: 50, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
                    ),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  ),
                  borderData: FlBorderData(show: false),
                  lineBarsData: [
                    LineChartBarData(
                      spots: controller.salesTrendSpots,
                      color: const Color(0xFFFA8C16),
                      barWidth: 2,
                      dotData: const FlDotData(show: false),
                      belowBarData: BarAreaData(show: true, color: const Color(0xFFFA8C16).withValues(alpha: 0.1)),
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

  Widget _buildTopProductsCard(BuildContext context) {
    final l10n = AppL10n.of(context);
    final products = controller.bizSales['top_products'] as List<dynamic>? ?? [];
    final maxQty = products.fold<double>(0, (m, p) => ((p['quantity'] as num?) ?? 0).toDouble() > m ? (p['quantity'] as num).toDouble() : m);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardTopProducts, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            ...products.map((p) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Expanded(child: Text('${p['name'] ?? '-'}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13))),
                    Text('${p['quantity'] ?? 0}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                  ]),
                  const SizedBox(height: 6),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: maxQty > 0 ? ((p['quantity'] as num?) ?? 0).toDouble() / maxQty : 0,
                      minHeight: 6,
                      backgroundColor: Colors.grey[200],
                      color: const Color(0xFF1677FF),
                    ),
                  ),
                ],
              ),
            )),
            if (products.isEmpty) Text(l10n.dashboardNoData, style: TextStyle(fontSize: 13, color: Colors.grey[500])),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderStatusCard(BuildContext context) {
    final l10n = AppL10n.of(context);
    final list = controller.bizSales['status_distribution'] as List<dynamic>? ?? [];
    const colors = [Color(0xFF1677FF), Color(0xFF52C41A), Color(0xFFFA8C16), Color(0xFF722ED1), Color(0xFFEB2F96)];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardOrderStatus, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 160,
              child: PieChart(
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
                  Row(mainAxisSize: MainAxisSize.min, children: [
                    Container(width: 12, height: 12, decoration: BoxDecoration(color: colors[i % colors.length], borderRadius: BorderRadius.circular(2))),
                    const SizedBox(width: 4),
                    Text('${list[i]['name']} ${list[i]['value']}', style: const TextStyle(fontSize: 12)),
                  ]),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAgingCard(BuildContext context, String title, dynamic aging) {
    final list = aging as List<dynamic>? ?? [];
    final maxValue = list.fold<double>(0, (m, b) => ((b['value'] as num?) ?? 0).toDouble() > m ? (b['value'] as num).toDouble() : m);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            ...list.map((b) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Expanded(child: Text('${b['name'] ?? '-'}', style: const TextStyle(fontSize: 13))),
                    Text('${b['value'] ?? 0}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                  ]),
                  const SizedBox(height: 4),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: maxValue > 0 ? ((b['value'] as num?) ?? 0).toDouble() / maxValue : 0,
                      minHeight: 6,
                      backgroundColor: Colors.grey[200],
                      color: const Color(0xFF722ED1),
                    ),
                  ),
                ],
              ),
            )),
          ],
        ),
      ),
    );
  }

  Widget _buildInventoryCard(BuildContext context) {
    final l10n = AppL10n.of(context);
    final d = controller.bizInventory;
    final items = <Map<String, dynamic>>[
      {'label': l10n.dashboardInvValue, 'value': '${d['total_value'] ?? 0}', 'icon': Icons.inventory_2, 'color': const Color(0xFF1677FF)},
      {'label': l10n.dashboardInvLowAlert, 'value': '${d['alert_low'] ?? 0}', 'icon': Icons.warning_amber, 'color': const Color(0xFFFA8C16)},
      {'label': l10n.dashboardInvHighAlert, 'value': '${d['alert_high'] ?? 0}', 'icon': Icons.trending_up, 'color': const Color(0xFFEB2F96)},
    ];
    return Row(
      children: [
        for (final s in items) ...[
          Expanded(child: StatCard(title: s['label'], value: s['value'], icon: s['icon'], color: s['color'])),
          if (s != items.last) const SizedBox(width: 16),
        ],
      ],
    );
  }

  Widget _overview(BuildContext context) {
    return Obx(() {
      if (controller.isLoading.value) {
        return const Center(child: CircularProgressIndicator());
      }
      return SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildStatsGrid(context),
            const SizedBox(height: 24),
            _buildTrendChart(context),
            const SizedBox(height: 24),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 2, child: _buildDistributionChart(context)),
                const SizedBox(width: 24),
                Expanded(flex: 3, child: _buildRecentLogs(context)),
              ],
            ),
          ],
        ),
      );
    });
  }

  Widget _opsTab(List<Map<String, dynamic>> stats) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
          maxCrossAxisExtent: 340,
          mainAxisExtent: 96,
          crossAxisSpacing: 16,
          mainAxisSpacing: 16,
        ),
        itemCount: stats.length,
        itemBuilder: (context, i) {
          final s = stats[i];
          return StatCard(
            title: s['label'],
            value: s['value'],
            icon: s['icon'],
            color: s['color'],
          );
        },
      ),
    );
  }

  Widget _buildStatsGrid(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = constraints.maxWidth > 900 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: crossAxisCount,
            mainAxisExtent: 120,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
          ),
          itemCount: 4,
          itemBuilder: (context, index) {
            final stat = controller.stats[index];
            final color = Color(int.parse('0xFF${stat['color'].replaceFirst('#', '')}'));
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Icon(_getIcon(stat['icon']), color: color, size: 20),
                      const Spacer(),
                      if (stat['trend'] != null) _buildTrendBadge(stat['trend']),
                    ]),
                    const Spacer(),
                    Text(stat['label'], style: TextStyle(fontSize: 13, color: Colors.grey[600])),
                    const SizedBox(height: 4),
                    Text(stat['value'], style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildTrendChart(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardTrend, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 300,
              child: LineChart(
                LineChartData(
                  gridData: FlGridData(
                    show: true,
                    drawVerticalLine: false,
                    horizontalInterval: 10,
                  ),
                  titlesData: FlTitlesData(
                    bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: true, reservedSize: 40, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
                    ),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  ),
                  borderData: FlBorderData(show: false),
              lineBarsData: controller.trendSpots.map((spots) {
                    return LineChartBarData(
                      spots: spots,
                      color: const Color(0xFF1677FF),
                      barWidth: 2,
                      dotData: const FlDotData(show: false),
                      belowBarData: BarAreaData(
                        show: true,
                        color: const Color(0xFF1677FF).withValues(alpha: 0.1),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDistributionChart(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardUserStatus, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 200,
              child: PieChart(
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
                _buildLegend(const Color(0xFF1677FF), l10n.dashboardEnabled),
                const SizedBox(width: 24),
                _buildLegend(const Color(0xFF52C41A), l10n.dashboardDisabled),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLegend(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }

  Widget _buildRecentLogs(BuildContext context) {
    final l10n = AppL10n.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.dashboardRecentOps, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            ...controller.recentLogs.take(8).map((log) => ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(radius: 14, backgroundColor: const Color(0xFF1677FF).withValues(alpha: 0.1),
                  child: Text(log['user_name'][0].toUpperCase(), style: const TextStyle(fontSize: 12, color: Color(0xFF1677FF)))),
              title: Text(log['action'], style: const TextStyle(fontSize: 13)),
              subtitle: Text(log['created_at'] ?? '', style: const TextStyle(fontSize: 11)),
              trailing: Text(log['ip'] ?? '', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
            )),
          ],
        ),
      ),
    );
  }

  Widget _buildTrendBadge(double trend) {
    final isUp = trend >= 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: isUp ? Colors.green[50] : Colors.red[50],
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(isUp ? Icons.arrow_upward : Icons.arrow_downward, size: 12,
              color: isUp ? Colors.green : Colors.red),
          Text('${trend.abs()}%',
              style: TextStyle(fontSize: 11, color: isUp ? Colors.green : Colors.red)),
        ],
      ),
    );
  }

  IconData _getIcon(String name) {
    switch (name) {
      case 'people': return Icons.people;
      case 'person_add': return Icons.person_add;
      case 'bolt': return Icons.bolt;
      default: return Icons.description;
    }
  }
}
