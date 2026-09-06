// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../widgets/stat_card.dart';
import '../../widgets/empty_state.dart';
import '../../theme/app_tokens.dart';
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
                Text(
                  l10n.dashboardTitle,
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Spacer(),
                // 页面刷新:总览/经营/OMS/WMS/TMS 全部重载
                IconButton(
                  icon: const Icon(Icons.refresh),
                  tooltip: l10n.commonRefresh,
                  onPressed: controller.refreshAll,
                ),
                PopupMenuButton<String>(
                  icon: const Icon(Icons.download),
                  tooltip: l10n.dashboardExport,
                  onSelected: (type) {
                    if (type == 'pdf') controller.exportPdf();
                    if (type == 'excel') controller.exportExcel();
                  },
                  itemBuilder: (_) => [
                    PopupMenuItem(
                      value: 'pdf',
                      child: ListTile(
                        leading: const Icon(Icons.picture_as_pdf),
                        title: Text(l10n.dashboardExportPdf),
                        dense: true,
                      ),
                    ),
                    PopupMenuItem(
                      value: 'excel',
                      child: ListTile(
                        leading: const Icon(Icons.table_chart),
                        title: Text(l10n.dashboardExportExcel),
                        dense: true,
                      ),
                    ),
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
      // 下拉刷新:整页数据重载
      return RefreshIndicator(
        onRefresh: controller.refreshAll,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
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
                  Expanded(
                    child: _buildAgingCard(
                      context,
                      l10n.dashboardArAging,
                      controller.bizFinance['ar_aging'],
                    ),
                  ),
                  const SizedBox(width: 24),
                  Expanded(
                    child: _buildAgingCard(
                      context,
                      l10n.dashboardApAging,
                      controller.bizFinance['ap_aging'],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              _buildInventoryCard(context),
            ],
          ),
        ),
      );
    });
  }

  Widget _buildSalesTrendCard(BuildContext context) {
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

  Widget _buildTopProductsCard(BuildContext context) {
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

  Widget _buildOrderStatusCard(BuildContext context) {
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

  Widget _buildAgingCard(BuildContext context, String title, dynamic aging) {
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

  Widget _buildInventoryCard(BuildContext context) {
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

  Widget _overview(BuildContext context) {
    return Obx(() {
      if (controller.isLoading.value) {
        return const Center(child: CircularProgressIndicator());
      }
      // 下拉刷新:整页数据重载
      return RefreshIndicator(
        onRefresh: controller.refreshAll,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
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
        ),
      );
    });
  }

  Widget _opsTab(List<Map<String, dynamic>> stats) {
    // 下拉刷新:整页数据重载(网格 shrinkWrap 于外层滚动,下拉由外层 SCSV 承接)
    return RefreshIndicator(
      onRefresh: controller.refreshAll,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
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
      ),
    );
  }

  Widget _buildStatsGrid(BuildContext context) {
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
          itemBuilder: (context, index) => _kpiCard(context, stats[index]),
        );
      },
    );
  }

  /// KPI 卡(视觉 2.0):图标+标签行 → 数值 400ms 滚动 → 迷你 sparkline。
  /// sparkline 数据源见 [_seriesDataFor];无对应序列时不画,卡面不塌。
  Widget _kpiCard(BuildContext context, Map<String, dynamic> stat) {
    final c = AppColors.of(context);
    final color = Color(
      int.parse('0xFF${stat['color'].replaceFirst('#', '')}'),
    );
    final spark = _seriesDataFor(stat);
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
                if (stat['trend'] != null)
                  _buildTrendBadge(context, stat['trend']),
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

  /// KPI 迷你 sparkline 数据映射(批1 契约):趋势序列带 series_key(users/logs)、
  /// 统计卡带 icon 字符串(people/description)——按 icon→series_key 推导优先,
  /// 断开 label 字符串耦合;无 series_key 的旧缓存/夹具保持原 name==label 与
  /// icon=people「累计*」两重 fallback,行为不变。无则 null。
  List<double>? _seriesDataFor(Map<String, dynamic> stat) {
    final series = <Map<String, dynamic>>[
      for (final s
          in (controller.trends['series'] as List<dynamic>?) ?? const [])
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

  static List<double>? _numList(dynamic data) {
    final list = data as List<dynamic>? ?? const [];
    final out = [for (final e in list) ((e as num?) ?? 0).toDouble()];
    return out.isEmpty ? null : out;
  }

  Widget _buildTrendChart(BuildContext context) {
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

  Widget _buildDistributionChart(BuildContext context) {
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
                _buildLegend(c.primary, l10n.dashboardEnabled),
                const SizedBox(width: 24),
                _buildLegend(c.success, l10n.dashboardDisabled),
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

  Widget _buildRecentLogs(BuildContext context) {
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

  Widget _buildTrendBadge(BuildContext context, double trend) {
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
}

/// KPI 数值 400ms 滚动(视觉 2.0):可解析为数字时从 0 滚到终值,
/// 非数字(如「—」)原样直出;系统 reduce-motion 时直接显示终值。
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
