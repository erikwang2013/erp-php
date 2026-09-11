// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../widgets/stat_card.dart';
import '../../widgets/empty_state.dart';
import '../../theme/app_tokens.dart';
import '../../l10n/app_l10n.dart';
import 'dashboard_controller.dart';

part 'dashboard_cards.dart';
part 'dashboard_stats.dart';
part 'dashboard_charts.dart';

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
                  Expanded(flex: 2, child: _SalesTrendCard(controller)),
                  const SizedBox(width: 24),
                  Expanded(flex: 1, child: _TopProductsCard(controller)),
                ],
              ),
              const SizedBox(height: 24),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: _OrderStatusCard(controller)),
                  const SizedBox(width: 24),
                  Expanded(
                    child: _AgingCard(
                      l10n.dashboardArAging,
                      controller.bizFinance['ar_aging'],
                    ),
                  ),
                  const SizedBox(width: 24),
                  Expanded(
                    child: _AgingCard(
                      l10n.dashboardApAging,
                      controller.bizFinance['ap_aging'],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              _InventoryCard(controller),
            ],
          ),
        ),
      );
    });
  }

  Widget _overview(BuildContext context) {
    return Obx(() {
      // 下拉刷新会再次置 isLoading=true；只在首屏无数据时给 spinner，
      // 否则整棵树被替换 → KPI 卡重挂载 → 数字从 0 重新滚动。
      if (controller.stats.isEmpty && controller.isLoading.value) {
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
              _StatsGrid(controller),
              const SizedBox(height: 24),
              _TrendChart(controller),
              const SizedBox(height: 24),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 2, child: _DistributionChart(controller)),
                  const SizedBox(width: 24),
                  Expanded(flex: 3, child: _RecentLogs(controller)),
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
}

/// KPI 数值 400ms 滚动(视觉 2.0):可解析为数字时从 0 滚到终值,
/// 非数字(如「—」)原样直出;系统 reduce-motion 时直接显示终值。
