// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import 'app/theme/app_theme.dart';
import 'app/l10n/app_l10n.dart';
import 'l10n/app_localizations.dart';
import 'app/layouts/admin_layout.dart';
import 'app/config/menu_config.dart';
import 'app/pages/login/login_page.dart';
import 'app/pages/dashboard/dashboard_page.dart';
import 'app/pages/profile/profile_page.dart';

// 系统管理
import 'app/pages/system/user/user_list_page.dart';
import 'app/pages/system/role/role_list_page.dart';
import 'app/pages/system/config/config_page.dart';
import 'app/pages/system/log/log_page.dart';

// 商品管理
import 'app/pages/product/product_list_page.dart';
import 'app/pages/product/category_list_page.dart';
import 'app/pages/product/brand_list_page.dart';

// 往来单位
import 'app/pages/partner/supplier_list_page.dart';
import 'app/pages/partner/customer_list_page.dart';
import 'app/pages/partner/warehouse_list_page.dart';
import 'app/pages/partner/location_list_page.dart';

// 采购管理
import 'app/pages/purchase/apply_list_page.dart';
import 'app/pages/purchase/order_list_page.dart';
import 'app/pages/purchase/receive_list_page.dart';
import 'app/pages/purchase/return_list_page.dart';
import 'app/pages/purchase/settlement_list_page.dart';

// 销售管理
import 'app/pages/sales/quotation_list_page.dart';
import 'app/pages/sales/order_list_page.dart';
import 'app/pages/sales/delivery_list_page.dart';
import 'app/pages/sales/return_list_page.dart';
import 'app/pages/sales/settlement_list_page.dart';

// 库存管理
import 'app/pages/inventory/inventory_list_page.dart';
import 'app/pages/inventory/flow_list_page.dart';
import 'app/pages/inventory/transfer_list_page.dart';
import 'app/pages/inventory/check_list_page.dart';
import 'app/pages/inventory/alert_list_page.dart';

// 财务管理
import 'app/pages/finance/voucher_list_page.dart';
import 'app/pages/finance/ar_ap_list_page.dart';
import 'app/pages/finance/receipt_list_page.dart';
import 'app/pages/finance/payment_list_page.dart';
import 'app/pages/finance/cash_journal_page.dart';
import 'app/pages/finance/expense_list_page.dart';
import 'app/pages/finance/ledger_page.dart';
import 'app/pages/finance/report_page.dart';
import 'app/pages/finance/asset_list_page.dart';
import 'app/pages/finance/tax_page.dart';
import 'app/pages/finance/currency_page.dart';
import 'app/pages/finance/bank_account_page.dart';
import 'app/pages/finance/exchange_rate_page.dart';
import 'app/pages/finance/budget_page.dart';
import 'app/pages/finance/cost_profit_page.dart';
import 'app/pages/finance/subsidiary_ledger_page.dart';

// CRM
import 'app/pages/crm/opportunity_list_page.dart';
import 'app/pages/crm/contact_list_page.dart';
import 'app/pages/crm/pool_page.dart';
import 'app/pages/crm/contract_list_page.dart';
import 'app/pages/crm/quotation_list_page.dart';
import 'app/pages/crm/campaign_list_page.dart';
import 'app/pages/crm/ticket_list_page.dart';
import 'app/pages/crm/follow_list_page.dart';
import 'app/pages/crm/funnel_list_page.dart';
import 'app/pages/crm/analytics_page.dart';

// OMS
import 'app/pages/oms/order_list_page.dart';
import 'app/pages/oms/fulfillment_list_page.dart';
import 'app/pages/oms/rma_list_page.dart';
import 'app/pages/oms/channel_list_page.dart';

// WMS
import 'app/pages/wms/zone_list_page.dart';
import 'app/pages/wms/asn_list_page.dart';
import 'app/pages/wms/receiving_page.dart';
import 'app/pages/wms/putaway_page.dart';
import 'app/pages/wms/wave_page.dart';
import 'app/pages/wms/pick_page.dart';
import 'app/pages/wms/pack_page.dart';

// TMS
import 'app/pages/tms/carrier_list_page.dart';
import 'app/pages/tms/freight_rate_page.dart';
import 'app/pages/tms/shipment_list_page.dart';
import 'app/pages/tms/tracking_page.dart';
import 'app/pages/tms/freight_invoice_page.dart';

// 生产制造
import 'app/pages/manufacturing/bom_list_page.dart';
import 'app/pages/manufacturing/production_order_page.dart';
import 'app/pages/manufacturing/routing_page.dart';
import 'app/pages/manufacturing/workstation_page.dart';
import 'app/pages/manufacturing/mrp_page.dart';

// 质量管理
import 'app/pages/quality/standard_list_page.dart';
import 'app/pages/quality/iqc_list_page.dart';
import 'app/pages/quality/ipqc_list_page.dart';
import 'app/pages/quality/oqc_list_page.dart';
import 'app/pages/quality/nonconformity_list_page.dart';

// 人力资源
import 'app/pages/hr/department_page.dart';
import 'app/pages/hr/employee_list_page.dart';
import 'app/pages/hr/position_page.dart';
import 'app/pages/hr/attendance_page.dart';
import 'app/pages/hr/leave_page.dart';
import 'app/pages/hr/salary_page.dart';
import 'app/pages/hr/salary_item_page.dart';

// 项目管理
import 'app/pages/project/project_list_page.dart';
import 'app/pages/project/task_list_page.dart';
import 'app/pages/project/timesheet_page.dart';

// 审批工作流 / 通知中心 / 自定义报表
import 'app/pages/workflow/workflow_list_page.dart';
import 'app/pages/workflow/my_approval_page.dart';
import 'app/pages/notification/notification_page.dart';
import 'app/pages/report/report_list_page.dart';
import 'app/pages/report/report_schedule_page.dart';

// BI 看板 / 设备管理 / 文档管理
import 'app/pages/bi/dashboard_list_page.dart';
import 'app/pages/bi/dataset_list_page.dart';
import 'app/pages/eam/equipment_list_page.dart';
import 'app/pages/eam/maintenance_plan_page.dart';
import 'app/pages/eam/repair_order_page.dart';
import 'app/pages/eam/spare_part_page.dart';
import 'app/pages/dms/document_list_page.dart';

void main() {
  runApp(const AdminApp());
}

/// Pages with real implementations, keyed by menu route.
final Map<String, Widget Function()> _pageBuilders = {
  '/dashboard': () => const DashboardPage(),
  '/system/users': () => const UserListPage(),
  '/system/roles': () => const RoleListPage(),
  '/system/config': () => const ConfigPage(),
  '/system/logs': () => const LogPage(),
  // 商品管理
  '/product/list': () => const ProductListPage(),
  '/product/category': () => const CategoryListPage(),
  '/product/brand': () => const BrandListPage(),
  // 往来单位
  '/partner/supplier': () => const SupplierListPage(),
  '/partner/customer': () => const CustomerListPage(),
  '/partner/warehouse': () => const WarehouseListPage(),
  '/partner/location': () => const LocationListPage(),
  // 采购管理
  '/purchase/apply': () => const PurchaseApplyListPage(),
  '/purchase/order': () => const PurchaseOrderListPage(),
  '/purchase/receive': () => const PurchaseReceiveListPage(),
  '/purchase/return': () => const PurchaseReturnListPage(),
  '/purchase/settlement': () => const PurchaseSettlementListPage(),
  // 销售管理
  '/sales/quotation': () => const SalesQuotationListPage(),
  '/sales/order': () => const SalesOrderListPage(),
  '/sales/delivery': () => const SalesDeliveryListPage(),
  '/sales/return': () => const SalesReturnListPage(),
  '/sales/settlement': () => const SalesSettlementListPage(),
  // 库存管理
  '/inventory/list': () => const InventoryListPage(),
  '/inventory/flow': () => const InventoryFlowListPage(),
  '/inventory/transfer': () => const InventoryTransferListPage(),
  '/inventory/check': () => const InventoryCheckListPage(),
  '/inventory/alert': () => const InventoryAlertListPage(),
  // 财务管理
  '/finance/voucher': () => const VoucherListPage(),
  '/finance/ar-ap': () => const ArApListPage(),
  '/finance/receipt': () => const ReceiptListPage(),
  '/finance/payment': () => const PaymentListPage(),
  '/finance/cash-journal': () => const CashJournalPage(),
  '/finance/expense': () => const ExpenseListPage(),
  '/finance/ledger': () => const LedgerPage(),
  '/finance/report': () => const FinanceReportPage(),
  '/finance/asset': () => const AssetListPage(),
  '/finance/tax': () => const TaxPage(),
  '/finance/currency': () => const CurrencyPage(),
  '/finance/bank-account': () => const BankAccountPage(),
  '/finance/exchange-rate': () => const ExchangeRatePage(),
  '/finance/budget': () => const BudgetPage(),
  '/finance/cost-profit': () => const CostProfitPage(),
  '/finance/subsidiary-ledger': () => const SubsidiaryLedgerPage(),
  // CRM
  '/crm/opportunity': () => const OpportunityListPage(),
  '/crm/contact': () => const ContactListPage(),
  '/crm/pool': () => const PoolPage(),
  '/crm/contract': () => const ContractListPage(),
  '/crm/quotation': () => const CrmQuotationListPage(),
  '/crm/campaign': () => const CampaignListPage(),
  '/crm/ticket': () => const TicketListPage(),
  '/crm/follow': () => const FollowListPage(),
  '/crm/funnel': () => const FunnelListPage(),
  '/crm/analytics': () => const CrmAnalyticsPage(),
  // OMS
  '/oms/order': () => const OmsOrderListPage(),
  '/oms/fulfillment': () => const FulfillmentListPage(),
  '/oms/rma': () => const RmaListPage(),
  '/oms/channel': () => const ChannelListPage(),
  // WMS
  '/wms/zone': () => const WmsZoneListPage(),
  '/wms/asn': () => const AsnListPage(),
  '/wms/receiving': () => const ReceivingPage(),
  '/wms/putaway': () => const PutawayPage(),
  '/wms/wave': () => const WavePage(),
  '/wms/pick': () => const PickPage(),
  '/wms/pack': () => const PackPage(),
  // TMS
  '/tms/carrier': () => const CarrierListPage(),
  '/tms/freight-rate': () => const FreightRatePage(),
  '/tms/shipment': () => const ShipmentListPage(),
  '/tms/tracking': () => const TrackingPage(),
  '/tms/freight-invoice': () => const FreightInvoicePage(),
  // 生产制造
  '/mfg/bom': () => const BomListPage(),
  '/mfg/production': () => const ProductionOrderPage(),
  '/mfg/routing': () => const RoutingPage(),
  '/mfg/workstation': () => const WorkstationPage(),
  '/mfg/mrp': () => const MrpPage(),
  // 质量管理
  '/quality/standard': () => const StandardListPage(),
  '/quality/iqc': () => const IqcListPage(),
  '/quality/ipqc': () => const IpqcListPage(),
  '/quality/oqc': () => const OqcListPage(),
  '/quality/nonconformity': () => const NonconformityListPage(),
  // 人力资源
  '/hr/department': () => const DepartmentPage(),
  '/hr/employee': () => const EmployeeListPage(),
  '/hr/position': () => const PositionPage(),
  '/hr/attendance': () => const AttendancePage(),
  '/hr/leave': () => const LeavePage(),
  '/hr/salary': () => const SalaryPage(),
  '/hr/salary-item': () => const SalaryItemPage(),
  // 项目管理
  '/project/list': () => const ProjectListPage(),
  '/project/task': () => const ProjectTaskListPage(),
  '/project/timesheet': () => const TimesheetPage(),
  // 审批工作流 / 通知中心 / 自定义报表
  '/workflow/list': () => const WorkflowListPage(),
  '/workflow/my-approval': () => const MyApprovalPage(),
  '/notification': () => const NotificationPage(),
  '/report/list': () => const ReportListPage(),
  '/report/schedule': () => const ReportSchedulePage(),
  // BI 看板 / 设备管理 / 文档管理
  '/bi/dashboard': () => const DashboardListPage(),
  '/bi/dataset': () => const DatasetListPage(),
  '/eam/equipment': () => const EquipmentListPage(),
  '/eam/maintenance': () => const MaintenancePlanPage(),
  '/eam/repair': () => const RepairOrderPage(),
  '/eam/spare-part': () => const SparePartPage(),
  '/dms/document': () => const DocumentListPage(),
};

/// Routes without a dedicated page yet render a placeholder inside the
/// admin layout so the menu navigation works end-to-end.
final List<GetPage> _menuRoutes = () {
  final routes = <GetPage>[];
  for (final entry in buildRouteMap().entries) {
    final builder = _pageBuilders[entry.key] ?? () => PlaceholderPage(label: entry.value, route: entry.key);
    routes.add(GetPage(
      name: entry.key,
      page: () => AdminLayout(child: builder()),
    ));
  }
  return routes;
}();

class AdminApp extends StatelessWidget {
  const AdminApp({super.key});

  @override
  Widget build(BuildContext context) {
    // 监听 AppL10n.setLocale 以支持运行时切换语言（en/zh）。
    return ValueListenableBuilder<Locale>(
      valueListenable: AppL10n.localeNotifier,
      builder: (context, locale, _) => GetMaterialApp(
        title: '开放管理后台',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        // 国际化（最小可行）：中英双语，默认中文。
        // 与后端 app/common/I18n.php 的键名风格对齐（login.* / nav.* / common.*），
        // locale 可运行时切换（AppL10n.setLocale），后续可接系统语言或用户偏好。
        locale: locale,
        supportedLocales: AppL10n.supportedLocales,
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
          AppLocalizations.delegate,
        ],
        builder: (context, child) => ResponsiveBreakpoints.builder(
          child: child!,
          breakpoints: [
            const Breakpoint(start: 0, end: 767, name: PHONE),
            const Breakpoint(start: 768, end: 1199, name: TABLET),
            const Breakpoint(start: 1200, end: 4500, name: DESKTOP),
          ],
        ),
        getPages: [
          GetPage(name: '/login', page: () => const LoginPage()),
          GetPage(name: '/profile', page: () => const ProfilePage()),
          ..._menuRoutes,
        ],
        initialRoute: '/login',
      ),
    );
  }
}

/// Fallback page for menu routes that have not been implemented yet.
class PlaceholderPage extends StatelessWidget {
  final String label;
  final String route;
  const PlaceholderPage({super.key, required this.label, required this.route});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.construction,
              size: 56, color: Theme.of(context).colorScheme.outline),
          const SizedBox(height: 12),
          Text(label,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text(route,
              style: TextStyle(color: Theme.of(context).colorScheme.outline)),
          const SizedBox(height: 4),
          // 后续 i18n：占位页文案暂保留硬编码中文
          const Text('页面开发中，敬请期待'),
        ],
      ),
    );
  }
}
