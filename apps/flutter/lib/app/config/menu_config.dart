// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 导航菜单配置。当前为最小国际化：仅“仪表盘 / 系统管理”两项通过
// i18nKey 接入 arb（key 与后端 app/common/I18n.php 的 nav.* 风格对齐），
// 其余菜单文案暂保留硬编码中文，后续 i18n（全量菜单改造不在 P2 范围）。
import 'package:flutter/material.dart';

class MenuItem {
  final String label;
  final IconData icon;
  final String? route;
  final List<MenuItem>? children;

  /// 可选 i18n key（如 nav.dashboard）；为 null 时直接显示 [label]。
  final String? i18nKey;
  const MenuItem({
    required this.label,
    required this.icon,
    this.route,
    this.children,
    this.i18nKey,
  });
}

const List<MenuItem> menuConfig = [
  MenuItem(
    label: '仪表盘',
    icon: Icons.dashboard,
    route: '/dashboard',
    i18nKey: 'nav.dashboard',
  ),
  MenuItem(
    label: '系统管理',
    icon: Icons.settings,
    i18nKey: 'nav.system',
    children: [
      MenuItem(label: '用户管理', icon: Icons.people, route: '/system/users'),
      MenuItem(label: '角色权限', icon: Icons.security, route: '/system/roles'),
      MenuItem(label: '系统配置', icon: Icons.tune, route: '/system/config'),
      MenuItem(label: '操作日志', icon: Icons.description, route: '/system/logs'),
    ],
  ),
  MenuItem(
    label: '商品管理',
    icon: Icons.inventory_2,
    children: [
      MenuItem(label: '商品列表', icon: Icons.list_alt, route: '/product/list'),
      MenuItem(label: '商品分类', icon: Icons.category, route: '/product/category'),
      MenuItem(label: '品牌管理', icon: Icons.bookmark, route: '/product/brand'),
    ],
  ),
  MenuItem(
    label: '往来单位',
    icon: Icons.business,
    children: [
      MenuItem(
        label: '供应商',
        icon: Icons.local_shipping,
        route: '/partner/supplier',
      ),
      MenuItem(
        label: '客户',
        icon: Icons.people_outline,
        route: '/partner/customer',
      ),
      MenuItem(label: '仓库', icon: Icons.warehouse, route: '/partner/warehouse'),
      MenuItem(label: '库位', icon: Icons.pin_drop, route: '/partner/location'),
    ],
  ),
  MenuItem(
    label: '采购管理',
    icon: Icons.shopping_cart,
    children: [
      MenuItem(
        label: '采购申请',
        icon: Icons.request_quote,
        route: '/purchase/apply',
      ),
      MenuItem(
        label: '采购订单',
        icon: Icons.receipt_long,
        route: '/purchase/order',
      ),
      MenuItem(
        label: '采购收货',
        icon: Icons.move_to_inbox,
        route: '/purchase/receive',
      ),
      MenuItem(
        label: '采购退货',
        icon: Icons.assignment_return,
        route: '/purchase/return',
      ),
      MenuItem(label: '采购结算', icon: Icons.paid, route: '/purchase/settlement'),
    ],
  ),
  MenuItem(
    label: '销售管理',
    icon: Icons.point_of_sale,
    children: [
      MenuItem(
        label: '销售报价',
        icon: Icons.price_check,
        route: '/sales/quotation',
      ),
      MenuItem(label: '销售订单', icon: Icons.receipt, route: '/sales/order'),
      MenuItem(
        label: '销售发货',
        icon: Icons.local_shipping,
        route: '/sales/delivery',
      ),
      MenuItem(
        label: '销售退货',
        icon: Icons.assignment_return,
        route: '/sales/return',
      ),
      MenuItem(label: '销售结算', icon: Icons.paid, route: '/sales/settlement'),
    ],
  ),
  MenuItem(
    label: '库存管理',
    icon: Icons.inventory,
    children: [
      MenuItem(label: '实时库存', icon: Icons.storage, route: '/inventory/list'),
      MenuItem(label: '库存流水', icon: Icons.sync_alt, route: '/inventory/flow'),
      MenuItem(
        label: '库存调拨',
        icon: Icons.swap_horiz,
        route: '/inventory/transfer',
      ),
      MenuItem(
        label: '盘点任务',
        icon: Icons.fact_check,
        route: '/inventory/check',
      ),
      MenuItem(
        label: '库存预警',
        icon: Icons.warning_amber,
        route: '/inventory/alert',
      ),
    ],
  ),
  MenuItem(
    label: '财务管理',
    icon: Icons.account_balance,
    children: [
      MenuItem(
        label: '记账凭证',
        icon: Icons.description,
        route: '/finance/voucher',
      ),
      MenuItem(label: '应收应付', icon: Icons.swap_horiz, route: '/finance/ar-ap'),
      MenuItem(label: '收款管理', icon: Icons.payments, route: '/finance/receipt'),
      MenuItem(
        label: '付款管理',
        icon: Icons.credit_card,
        route: '/finance/payment',
      ),
      MenuItem(
        label: '现金日记账',
        icon: Icons.book,
        route: '/finance/cash-journal',
      ),
      MenuItem(
        label: '费用报销',
        icon: Icons.receipt_long,
        route: '/finance/expense',
      ),
      MenuItem(
        label: '总账/明细账',
        icon: Icons.menu_book,
        route: '/finance/ledger',
      ),
      MenuItem(
        label: '明细分类账',
        icon: Icons.receipt_long,
        route: '/finance/subsidiary-ledger',
      ),
      MenuItem(label: '财务报表', icon: Icons.assessment, route: '/finance/report'),
      MenuItem(
        label: '固定资产',
        icon: Icons.account_balance_wallet,
        route: '/finance/asset',
      ),
      MenuItem(label: '税务管理', icon: Icons.gavel, route: '/finance/tax'),
      MenuItem(
        label: '多币种/汇率',
        icon: Icons.currency_exchange,
        route: '/finance/currency',
      ),
      MenuItem(
        label: '银行账户',
        icon: Icons.account_balance,
        route: '/finance/bank-account',
      ),
      MenuItem(
        label: '汇率管理',
        icon: Icons.swap_horiz,
        route: '/finance/exchange-rate',
      ),
      MenuItem(label: '预算管理', icon: Icons.savings, route: '/finance/budget'),
      MenuItem(
        label: '成本/利润中心',
        icon: Icons.pie_chart,
        route: '/finance/cost-profit',
      ),
    ],
  ),
  MenuItem(
    label: 'CRM',
    icon: Icons.people_alt,
    children: [
      MenuItem(label: '商机管理', icon: Icons.lightbulb, route: '/crm/opportunity'),
      MenuItem(label: '联系人', icon: Icons.contacts, route: '/crm/contact'),
      MenuItem(label: '公海池', icon: Icons.water, route: '/crm/pool'),
      MenuItem(label: '合同管理', icon: Icons.handshake, route: '/crm/contract'),
      MenuItem(
        label: '报价单',
        icon: Icons.request_quote,
        route: '/crm/quotation',
      ),
      MenuItem(label: '营销活动', icon: Icons.campaign, route: '/crm/campaign'),
      MenuItem(label: '服务工单', icon: Icons.support_agent, route: '/crm/ticket'),
      MenuItem(
        label: '跟进记录',
        icon: Icons.follow_the_signs,
        route: '/crm/follow',
      ),
      MenuItem(label: '销售漏斗', icon: Icons.filter_alt, route: '/crm/funnel'),
      MenuItem(label: '客户分析', icon: Icons.analytics, route: '/crm/analytics'),
    ],
  ),
  MenuItem(
    label: '订单管理',
    icon: Icons.shopping_bag,
    children: [
      MenuItem(label: 'OMS 订单', icon: Icons.list_alt, route: '/oms/order'),
      MenuItem(label: '履约管理', icon: Icons.checklist, route: '/oms/fulfillment'),
      MenuItem(label: '退换货(RMA)', icon: Icons.replay, route: '/oms/rma'),
      MenuItem(label: '渠道管理', icon: Icons.hub, route: '/oms/channel'),
    ],
  ),
  MenuItem(
    label: '仓储管理',
    icon: Icons.warehouse,
    children: [
      MenuItem(label: '库区管理', icon: Icons.grid_view, route: '/wms/zone'),
      MenuItem(label: '预到货(ASN)', icon: Icons.note_add, route: '/wms/asn'),
      MenuItem(label: '收货管理', icon: Icons.download, route: '/wms/receiving'),
      MenuItem(label: '上架管理', icon: Icons.upload, route: '/wms/putaway'),
      MenuItem(label: '波次管理', icon: Icons.waves, route: '/wms/wave'),
      MenuItem(label: '拣货管理', icon: Icons.shopping_basket, route: '/wms/pick'),
      MenuItem(label: '打包管理', icon: Icons.inventory_2, route: '/wms/pack'),
    ],
  ),
  MenuItem(
    label: '运输管理',
    icon: Icons.local_shipping,
    children: [
      MenuItem(label: '承运商', icon: Icons.business, route: '/tms/carrier'),
      MenuItem(
        label: '运费费率',
        icon: Icons.attach_money,
        route: '/tms/freight-rate',
      ),
      MenuItem(
        label: '运单管理',
        icon: Icons.content_paste,
        route: '/tms/shipment',
      ),
      MenuItem(
        label: '物流轨迹',
        icon: Icons.track_changes,
        route: '/tms/tracking',
      ),
      MenuItem(
        label: '运费发票',
        icon: Icons.receipt,
        route: '/tms/freight-invoice',
      ),
    ],
  ),
  MenuItem(
    label: '生产制造',
    icon: Icons.precision_manufacturing,
    children: [
      MenuItem(label: 'BOM管理', icon: Icons.account_tree, route: '/mfg/bom'),
      MenuItem(
        label: '生产工单',
        icon: Icons.engineering,
        route: '/mfg/production',
      ),
      MenuItem(label: '工艺路线', icon: Icons.route, route: '/mfg/routing'),
      MenuItem(
        label: '工作站',
        icon: Icons.desktop_windows,
        route: '/mfg/workstation',
      ),
      MenuItem(label: 'MRP计划', icon: Icons.calculate, route: '/mfg/mrp'),
    ],
  ),
  MenuItem(
    label: '质量管理',
    icon: Icons.verified_user,
    children: [
      MenuItem(label: '检验标准', icon: Icons.rule, route: '/quality/standard'),
      MenuItem(label: '来料检验(IQC)', icon: Icons.download, route: '/quality/iqc'),
      MenuItem(
        label: '过程检验(IPQC)',
        icon: Icons.engineering,
        route: '/quality/ipqc',
      ),
      MenuItem(label: '出货检验(OQC)', icon: Icons.upload, route: '/quality/oqc'),
      MenuItem(
        label: '不合格品',
        icon: Icons.report_problem,
        route: '/quality/nonconformity',
      ),
    ],
  ),
  MenuItem(
    label: '人力资源',
    icon: Icons.groups,
    children: [
      MenuItem(label: '部门管理', icon: Icons.apartment, route: '/hr/department'),
      MenuItem(label: '员工档案', icon: Icons.badge, route: '/hr/employee'),
      MenuItem(label: '职位管理', icon: Icons.work, route: '/hr/position'),
      MenuItem(label: '考勤管理', icon: Icons.access_time, route: '/hr/attendance'),
      MenuItem(label: '请假管理', icon: Icons.event_busy, route: '/hr/leave'),
      MenuItem(label: '薪资管理', icon: Icons.monetization_on, route: '/hr/salary'),
      MenuItem(
        label: '薪资项配置',
        icon: Icons.settings_applications,
        route: '/hr/salary-item',
      ),
    ],
  ),
  MenuItem(
    label: '项目管理',
    icon: Icons.task_alt,
    children: [
      MenuItem(label: '项目列表', icon: Icons.folder, route: '/project/list'),
      MenuItem(label: '任务管理', icon: Icons.checklist, route: '/project/task'),
      MenuItem(label: '工时记录', icon: Icons.timer, route: '/project/timesheet'),
    ],
  ),
  MenuItem(
    label: '审批工作流',
    icon: Icons.account_tree,
    children: [
      MenuItem(label: '工作流定义', icon: Icons.schema, route: '/workflow/list'),
      MenuItem(
        label: '我的审批',
        icon: Icons.how_to_vote,
        route: '/workflow/my-approval',
      ),
    ],
  ),
  MenuItem(label: '通知中心', icon: Icons.notifications, route: '/notification'),
  MenuItem(
    label: '自定义报表',
    icon: Icons.bar_chart,
    children: [
      MenuItem(label: '报表管理', icon: Icons.auto_graph, route: '/report/list'),
      MenuItem(
        label: '定时调度',
        icon: Icons.schedule_send,
        route: '/report/schedule',
      ),
    ],
  ),
  MenuItem(
    label: 'BI 看板',
    icon: Icons.dashboard_customize,
    children: [
      MenuItem(label: '仪表盘', icon: Icons.dashboard, route: '/bi/dashboard'),
    ],
  ),
  MenuItem(
    label: '设备管理',
    icon: Icons.build,
    children: [
      MenuItem(label: '设备台账', icon: Icons.list_alt, route: '/eam/equipment'),
      MenuItem(label: '保养计划', icon: Icons.event, route: '/eam/maintenance'),
      MenuItem(label: '维修工单', icon: Icons.construction, route: '/eam/repair'),
    ],
  ),
  MenuItem(
    label: '文档管理',
    icon: Icons.folder,
    children: [
      MenuItem(label: '文档列表', icon: Icons.article, route: '/dms/document'),
    ],
  ),
];

/// 菜单英文文案（视觉 C0 双语基座）：key = menuConfig 的 zh label 源真值
/// （menuConfig 保持不变），value = 英文菜单文案。admin_layout._menuLabel
/// 在非 zh 语言下按 zh label 查表；新菜单漏配时回退显示 zh label。
/// 命名用 UI 菜单惯例 Title Case；与 arb 已译 nav.dashboard/nav.system
/// （Dashboard / System）保持一致。覆盖守卫见 test/l10n/ 下 en 测试。
const Map<String, String> menuLabelsEn = {
  // 通用 / 系统
  '仪表盘': 'Dashboard',
  '系统管理': 'System',
  '用户管理': 'Users',
  '角色权限': 'Roles',
  '系统配置': 'System Settings',
  '操作日志': 'Audit Logs',
  // 商品
  '商品管理': 'Products',
  '商品列表': 'Product List',
  '商品分类': 'Categories',
  '品牌管理': 'Brands',
  // 往来单位
  '往来单位': 'Partners',
  '供应商': 'Suppliers',
  '客户': 'Customers',
  '仓库': 'Warehouses',
  '库位': 'Locations',
  // 采购
  '采购管理': 'Purchasing',
  '采购申请': 'Purchase Requisitions',
  '采购订单': 'Purchase Orders',
  '采购收货': 'Purchase Receipts',
  '采购退货': 'Purchase Returns',
  '采购结算': 'Purchase Settlements',
  // 销售
  '销售管理': 'Sales',
  '销售报价': 'Sales Quotations',
  '销售订单': 'Sales Orders',
  '销售发货': 'Deliveries',
  '销售退货': 'Sales Returns',
  '销售结算': 'Sales Settlements',
  // 库存
  '库存管理': 'Inventory',
  '实时库存': 'Stock on Hand',
  '库存流水': 'Stock Movements',
  '库存调拨': 'Transfers',
  '盘点任务': 'Stocktakes',
  '库存预警': 'Stock Alerts',
  // 财务
  '财务管理': 'Finance',
  '记账凭证': 'Vouchers',
  '应收应付': 'AR/AP',
  '收款管理': 'Receipts',
  '付款管理': 'Payments',
  '现金日记账': 'Cash Journals',
  '费用报销': 'Expenses',
  '总账/明细账': 'General / Detail Ledger',
  '明细分类账': 'Subsidiary Ledgers',
  '财务报表': 'Financial Reports',
  '固定资产': 'Fixed Assets',
  '税务管理': 'Taxes',
  '多币种/汇率': 'Multi-Currency / FX Rates',
  '银行账户': 'Bank Accounts',
  '汇率管理': 'Exchange Rates',
  '预算管理': 'Budgets',
  '成本/利润中心': 'Cost / Profit Centers',
  // CRM
  'CRM': 'CRM',
  '商机管理': 'Opportunities',
  '联系人': 'Contacts',
  '公海池': 'Lead Pool',
  '合同管理': 'Contracts',
  '报价单': 'Quotations',
  '营销活动': 'Campaigns',
  '服务工单': 'Service Tickets',
  '跟进记录': 'Follow-ups',
  '销售漏斗': 'Sales Funnel',
  '客户分析': 'Customer Analytics',
  // OMS
  '订单管理': 'Orders',
  'OMS 订单': 'OMS Orders',
  '履约管理': 'Fulfillment',
  '退换货(RMA)': 'Returns (RMA)',
  '渠道管理': 'Channels',
  // WMS
  '仓储管理': 'Warehousing',
  '库区管理': 'Zones',
  '预到货(ASN)': 'Expected Receipts (ASN)',
  '收货管理': 'Receiving',
  '上架管理': 'Putaway',
  '波次管理': 'Waves',
  '拣货管理': 'Picking',
  '打包管理': 'Packing',
  // TMS
  '运输管理': 'Transportation',
  '承运商': 'Carriers',
  '运费费率': 'Freight Rates',
  '运单管理': 'Shipments',
  '物流轨迹': 'Tracking',
  '运费发票': 'Freight Invoices',
  // 生产
  '生产制造': 'Manufacturing',
  'BOM管理': 'BOM Management',
  '生产工单': 'Production Orders',
  '工艺路线': 'Routings',
  '工作站': 'Workstations',
  'MRP计划': 'MRP Planning',
  // 质量
  '质量管理': 'Quality',
  '检验标准': 'Inspection Standards',
  '来料检验(IQC)': 'Incoming Inspection (IQC)',
  '过程检验(IPQC)': 'In-process Inspection (IPQC)',
  '出货检验(OQC)': 'Outgoing Inspection (OQC)',
  '不合格品': 'Nonconforming Products',
  // 人力
  '人力资源': 'HR',
  '部门管理': 'Departments',
  '员工档案': 'Employees',
  '职位管理': 'Positions',
  '考勤管理': 'Attendance',
  '请假管理': 'Leave',
  '薪资管理': 'Payroll',
  '薪资项配置': 'Payroll Items',
  // 项目
  '项目管理': 'Projects',
  '项目列表': 'Project List',
  '任务管理': 'Tasks',
  '工时记录': 'Timesheets',
  // 审批 / 通知 / 报表 / BI
  '审批工作流': 'Approvals',
  '工作流定义': 'Workflows',
  '我的审批': 'My Approvals',
  '通知中心': 'Notifications',
  '自定义报表': 'Custom Reports',
  '报表管理': 'Reports',
  '定时调度': 'Schedules',
  'BI 看板': 'BI Dashboards',
  // 设备 / 文档
  '设备管理': 'Equipment',
  '设备台账': 'Equipment Ledger',
  '保养计划': 'Maintenance Plans',
  '维修工单': 'Repair Orders',
  '文档管理': 'Documents',
  '文档列表': 'Document List',
};

Map<String, String> buildRouteMap() {
  final map = <String, String>{};
  void walk(List<MenuItem> items) {
    for (final item in items) {
      if (item.route != null) map[item.route!] = item.label;
      if (item.children != null) walk(item.children!);
    }
  }

  walk(menuConfig);
  return map;
}
