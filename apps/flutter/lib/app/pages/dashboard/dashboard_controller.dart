// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import '../../services/api_service.dart';

class DashboardController extends GetxController {
  final isLoading = true.obs;

  final stats = <Map<String, dynamic>>[].obs;
  final trends = <String, dynamic>{}.obs;
  final distribution = <String, dynamic>{}.obs;
  final recentLogs = <Map<String, dynamic>>[].obs;

  /// OMS / WMS / TMS operation stats for the dashboard tabs,
  /// loaded from /admin/dashboard/oms, /admin/dashboard/wms, /admin/dashboard/tms.
  final omsStats = <Map<String, dynamic>>[].obs;
  final wmsStats = <Map<String, dynamic>>[].obs;
  final tmsStats = <Map<String, dynamic>>[].obs;

  List<List<FlSpot>> get trendSpots {
    final allSeries = trends['series'] as List<dynamic>? ?? [];
    return allSeries.map((s) {
      final data = s['data'] as List<dynamic>? ?? [];
      return data.asMap().entries.map((e) => FlSpot(e.key.toDouble(), (e.value as num).toDouble())).toList();
    }).toList();
  }

  List<PieChartSectionData> get pieSections {
    final list = distribution['user_status'] as List<dynamic>? ?? [];
    const colors = [Color(0xFF1677FF), Color(0xFF52C41A)];
    return List.generate(list.length, (i) {
      final item = list[i] as Map<String, dynamic>;
      return PieChartSectionData(
        color: colors[i % colors.length],
        value: ((item['value'] as num?) ?? 0).toDouble(),
        title: '',
        radius: 30,
      );
    });
  }

  @override
  void onInit() {
    super.onInit();
    loadData();
    loadOpsStats();
  }

  /// 总览：/admin/dashboard（用户统计、趋势、分布、最近操作日志）
  Future<void> loadData() async {
    try {
      isLoading.value = true;
      final res = await ApiService.instance.get('/admin/dashboard');
      final data = res['data'] ?? <String, dynamic>{};
      stats.value = List<Map<String, dynamic>>.from(data['stats'] ?? []);
      trends.value = Map<String, dynamic>.from(data['trends'] ?? {});
      distribution.value = Map<String, dynamic>.from(data['distribution'] ?? {});
      recentLogs.value = List<Map<String, dynamic>>.from(data['recent_logs'] ?? []);
    } catch (e) {
      debugPrint('加载仪表盘总览失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  /// OMS / WMS / TMS 三个面板分别请求各自真实端点。
  Future<void> loadOpsStats() async {
    try {
      final results = await Future.wait([
        ApiService.instance.get('/admin/dashboard/oms'),
        ApiService.instance.get('/admin/dashboard/wms'),
        ApiService.instance.get('/admin/dashboard/tms'),
      ]);
      omsStats.value = _omsCards(results[0]['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
      wmsStats.value = _wmsCards(results[1]['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
      tmsStats.value = _tmsCards(results[2]['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    } catch (e) {
      debugPrint('加载 OMS/WMS/TMS 看板失败: $e');
    }
  }

  List<Map<String, dynamic>> _omsCards(Map<String, dynamic> d) => [
    {'label': '待处理订单', 'value': '${d['pending_orders'] ?? 0}', 'icon': Icons.shopping_bag, 'color': const Color(0xFF1677FF)},
    {'label': '拣货中订单', 'value': '${d['picking_orders'] ?? 0}', 'icon': Icons.shopping_basket, 'color': const Color(0xFF52C41A)},
    {'label': '今日发货', 'value': '${d['shipped_today'] ?? 0}', 'icon': Icons.local_shipping, 'color': const Color(0xFFFA8C16)},
    {'label': '待处理 RMA', 'value': '${d['pending_rma'] ?? 0}', 'icon': Icons.replay, 'color': const Color(0xFF722ED1)},
  ];

  List<Map<String, dynamic>> _wmsCards(Map<String, dynamic> d) => [
    {'label': '待收货', 'value': '${d['pending_receiving'] ?? 0}', 'icon': Icons.download, 'color': const Color(0xFF1677FF)},
    {'label': '待上架', 'value': '${d['pending_putaway'] ?? 0}', 'icon': Icons.upload, 'color': const Color(0xFF52C41A)},
    {'label': '待拣货', 'value': '${d['pending_picks'] ?? 0}', 'icon': Icons.shopping_basket, 'color': const Color(0xFFFA8C16)},
    {'label': '待打包', 'value': '${d['pending_packs'] ?? 0}', 'icon': Icons.inventory_2, 'color': const Color(0xFF722ED1)},
  ];

  List<Map<String, dynamic>> _tmsCards(Map<String, dynamic> d) => [
    {'label': '待发运', 'value': '${d['pending_shipments'] ?? 0}', 'icon': Icons.outbox, 'color': const Color(0xFF1677FF)},
    {'label': '在途', 'value': '${d['in_transit'] ?? 0}', 'icon': Icons.local_shipping, 'color': const Color(0xFF52C41A)},
    {'label': '今日送达', 'value': '${d['delivered_today'] ?? 0}', 'icon': Icons.task_alt, 'color': const Color(0xFFFA8C16)},
    {'label': '异常运单', 'value': '${d['exception_shipments'] ?? 0}', 'icon': Icons.warning_amber, 'color': const Color(0xFF722ED1)},
  ];

  Future<void> exportPdf() async {
    final pdf = pw.Document();
    pdf.addPage(pw.MultiPage(
      pageFormat: PdfPageFormat.a4.landscape,
      build: (ctx) => [
        pw.Header(text: '仪表盘数据导出'),
        pw.Paragraph(text: 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz'),
        for (final s in stats)
          pw.Row(children: [
            pw.Text(s['label']),
            pw.Text(s['value'], style: pw.TextStyle(fontWeight: pw.FontWeight.bold)),
          ]),
      ],
    ));
    await Printing.sharePdf(bytes: await pdf.save(), filename: 'dashboard_export.pdf');
  }

  Future<void> exportExcel() async {
    Get.snackbar('导出', 'Excel 导出功能已触发');
  }
}
