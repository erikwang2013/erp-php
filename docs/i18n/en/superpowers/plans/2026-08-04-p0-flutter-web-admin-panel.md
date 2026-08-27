# P0 Flutter Web Admin Panel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Expand the Flutter Web admin panel from the current ~12 pages to 40+ pages covering all 14 backend modules, and build a reusable common component library.

**Architecture:** Refactor into a config-driven dynamic menu system (`menu_config.dart`), PC three-column layout (collapsible sidebar + top bar + content area), and common CRUD components (DataTableWrapper/FormDialog/ConfirmDialog) reused by all module pages.

**Tech Stack:** Flutter 3.x, Dart 3.11+, GetX 4.6, Dio 5.4, data_table_2 2.5, fl_chart 0.68, printing 5.12, excel 4.0, responsive_framework 1.4

---

## File Structure

```
apps/flutter/lib/app/
├── main.dart                          # Modify: centralized route registration
├── config/
│   └── menu_config.dart               # New: navigation menu tree configuration
├── layouts/
│   └── admin_layout.dart              # Rewrite: dynamic menu rendering
├── services/                          # Unchanged
├── widgets/                           # New: 5 common components
│   ├── data_table_wrapper.dart
│   ├── form_dialog.dart
│   ├── confirm_dialog.dart
│   ├── stat_card.dart
│   └── search_bar.dart
└── pages/                             # Reorganized into modular directories
    ├── system/user/..., role/..., config/..., log/...
    ├── product/product_list, product_form, category_list, brand_list
    ├── partner/supplier_list, customer_list, warehouse_list, location_list
    ├── purchase/apply_list, order_list, receive_list, return_list
    ├── sales/quotation_list, order_list, delivery_list, return_list
    ├── inventory/inventory_list, flow_list, transfer_list, check_list, alert_list
    ├── finance/(13 pages)
    ├── crm/(8 pages)
    ├── oms/(4 pages)
    ├── wms/(7 pages)
    ├── tms/(5 pages)
    ├── manufacturing/(5 pages)
    ├── hr/(6 pages)
    ├── project/(3 pages)
    ├── workflow/(2 pages)
    ├── notification/notification_page
    └── report/(2 pages)
```

---

### Task 1: Menu Configuration & Route Refactor

**Files:**
- Create: `apps/flutter/lib/app/config/menu_config.dart`
- Modify: `apps/flutter/lib/app/layouts/admin_layout.dart` (full rewrite)
- Modify: `apps/flutter/lib/main.dart` (route registration)
- Move: migrate existing page files to the new directory structure

- [ ] **Step 1: Create menu_config.dart**

```dart
// apps/flutter/lib/app/config/menu_config.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

class MenuItem {
  final String label;
  final IconData icon;
  final String? route;
  final List<MenuItem>? children;
  const MenuItem({required this.label, required this.icon, this.route, this.children});
}

const List<MenuItem> menuConfig = [
  MenuItem(label: '仪表盘', icon: Icons.dashboard, route: '/dashboard'),
  MenuItem(label: '系统管理', icon: Icons.settings, children: [
    MenuItem(label: '用户管理', icon: Icons.people, route: '/system/users'),
    MenuItem(label: '角色权限', icon: Icons.security, route: '/system/roles'),
    MenuItem(label: '系统配置', icon: Icons.tune, route: '/system/config'),
    MenuItem(label: '操作日志', icon: Icons.description, route: '/system/logs'),
  ]),
  MenuItem(label: '商品管理', icon: Icons.inventory_2, children: [
    MenuItem(label: '商品列表', icon: Icons.list_alt, route: '/product/list'),
    MenuItem(label: '商品分类', icon: Icons.category, route: '/product/category'),
    MenuItem(label: '品牌管理', icon: Icons.bookmark, route: '/product/brand'),
  ]),
  MenuItem(label: '往来单位', icon: Icons.business, children: [
    MenuItem(label: '供应商', icon: Icons.local_shipping, route: '/partner/supplier'),
    MenuItem(label: '客户', icon: Icons.people_outline, route: '/partner/customer'),
    MenuItem(label: '仓库', icon: Icons.warehouse, route: '/partner/warehouse'),
    MenuItem(label: '库位', icon: Icons.pin_drop, route: '/partner/location'),
  ]),
  MenuItem(label: '采购管理', icon: Icons.shopping_cart, children: [
    MenuItem(label: '采购申请', icon: Icons.request_quote, route: '/purchase/apply'),
    MenuItem(label: '采购订单', icon: Icons.receipt_long, route: '/purchase/order'),
    MenuItem(label: '采购收货', icon: Icons.move_to_inbox, route: '/purchase/receive'),
    MenuItem(label: '采购退货', icon: Icons.assignment_return, route: '/purchase/return'),
  ]),
  MenuItem(label: '销售管理', icon: Icons.point_of_sale, children: [
    MenuItem(label: '销售报价', icon: Icons.price_check, route: '/sales/quotation'),
    MenuItem(label: '销售订单', icon: Icons.receipt, route: '/sales/order'),
    MenuItem(label: '销售发货', icon: Icons.local_shipping, route: '/sales/delivery'),
    MenuItem(label: '销售退货', icon: Icons.assignment_return, route: '/sales/return'),
  ]),
  MenuItem(label: '库存管理', icon: Icons.inventory, children: [
    MenuItem(label: '实时库存', icon: Icons.storage, route: '/inventory/list'),
    MenuItem(label: '库存流水', icon: Icons.sync_alt, route: '/inventory/flow'),
    MenuItem(label: '库存调拨', icon: Icons.swap_horiz, route: '/inventory/transfer'),
    MenuItem(label: '盘点任务', icon: Icons.fact_check, route: '/inventory/check'),
    MenuItem(label: '库存预警', icon: Icons.warning_amber, route: '/inventory/alert'),
  ]),
  MenuItem(label: '财务管理', icon: Icons.account_balance, children: [
    MenuItem(label: '记账凭证', icon: Icons.description, route: '/finance/voucher'),
    MenuItem(label: '应收应付', icon: Icons.swap_horiz, route: '/finance/ar-ap'),
    MenuItem(label: '收款管理', icon: Icons.payments, route: '/finance/receipt'),
    MenuItem(label: '付款管理', icon: Icons.credit_card, route: '/finance/payment'),
    MenuItem(label: '现金日记账', icon: Icons.book, route: '/finance/cash-journal'),
    MenuItem(label: '费用报销', icon: Icons.receipt_long, route: '/finance/expense'),
    MenuItem(label: '总账/明细账', icon: Icons.menu_book, route: '/finance/ledger'),
    MenuItem(label: '财务报表', icon: Icons.assessment, route: '/finance/report'),
    MenuItem(label: '固定资产', icon: Icons.account_balance_wallet, route: '/finance/asset'),
    MenuItem(label: '税务管理', icon: Icons.gavel, route: '/finance/tax'),
    MenuItem(label: '多币种/汇率', icon: Icons.currency_exchange, route: '/finance/currency'),
    MenuItem(label: '预算管理', icon: Icons.savings, route: '/finance/budget'),
    MenuItem(label: '成本/利润中心', icon: Icons.pie_chart, route: '/finance/cost-profit'),
  ]),
  MenuItem(label: 'CRM', icon: Icons.people_alt, children: [
    MenuItem(label: '商机管理', icon: Icons.lightbulb, route: '/crm/opportunity'),
    MenuItem(label: '联系人', icon: Icons.contacts, route: '/crm/contact'),
    MenuItem(label: '公海池', icon: Icons.water, route: '/crm/pool'),
    MenuItem(label: '合同管理', icon: Icons.handshake, route: '/crm/contract'),
    MenuItem(label: '报价单', icon: Icons.request_quote, route: '/crm/quotation'),
    MenuItem(label: '营销活动', icon: Icons.campaign, route: '/crm/campaign'),
    MenuItem(label: '服务工单', icon: Icons.support_agent, route: '/crm/ticket'),
    MenuItem(label: '客户分析', icon: Icons.analytics, route: '/crm/analytics'),
  ]),
  MenuItem(label: '订单管理', icon: Icons.shopping_bag, children: [
    MenuItem(label: 'OMS 订单', icon: Icons.list_alt, route: '/oms/order'),
    MenuItem(label: '履约管理', icon: Icons.checklist, route: '/oms/fulfillment'),
    MenuItem(label: '退换货(RMA)', icon: Icons.replay, route: '/oms/rma'),
    MenuItem(label: '渠道管理', icon: Icons.hub, route: '/oms/channel'),
  ]),
  MenuItem(label: '仓储管理', icon: Icons.warehouse, children: [
    MenuItem(label: '库区管理', icon: Icons.grid_view, route: '/wms/zone'),
    MenuItem(label: '预到货(ASN)', icon: Icons.note_add, route: '/wms/asn'),
    MenuItem(label: '收货管理', icon: Icons.download, route: '/wms/receiving'),
    MenuItem(label: '上架管理', icon: Icons.upload, route: '/wms/putaway'),
    MenuItem(label: '波次管理', icon: Icons.waves, route: '/wms/wave'),
    MenuItem(label: '拣货管理', icon: Icons.shopping_basket, route: '/wms/pick'),
    MenuItem(label: '打包管理', icon: Icons.inventory_2, route: '/wms/pack'),
  ]),
  MenuItem(label: '运输管理', icon: Icons.local_shipping, children: [
    MenuItem(label: '承运商', icon: Icons.business, route: '/tms/carrier'),
    MenuItem(label: '运费费率', icon: Icons.attach_money, route: '/tms/freight-rate'),
    MenuItem(label: '运单管理', icon: Icons.content_paste, route: '/tms/shipment'),
    MenuItem(label: '物流轨迹', icon: Icons.track_changes, route: '/tms/tracking'),
    MenuItem(label: '运费发票', icon: Icons.receipt, route: '/tms/freight-invoice'),
  ]),
  MenuItem(label: '生产制造', icon: Icons.precision_manufacturing, children: [
    MenuItem(label: 'BOM管理', icon: Icons.account_tree, route: '/mfg/bom'),
    MenuItem(label: '生产工单', icon: Icons.engineering, route: '/mfg/production'),
    MenuItem(label: '工艺路线', icon: Icons.route, route: '/mfg/routing'),
    MenuItem(label: '工作站', icon: Icons.desktop_windows, route: '/mfg/workstation'),
    MenuItem(label: 'MRP计划', icon: Icons.calculate, route: '/mfg/mrp'),
  ]),
  MenuItem(label: '人力资源', icon: Icons.groups, children: [
    MenuItem(label: '部门管理', icon: Icons.org_chart, route: '/hr/department'),
    MenuItem(label: '员工档案', icon: Icons.badge, route: '/hr/employee'),
    MenuItem(label: '职位管理', icon: Icons.work, route: '/hr/position'),
    MenuItem(label: '考勤管理', icon: Icons.access_time, route: '/hr/attendance'),
    MenuItem(label: '请假管理', icon: Icons.event_busy, route: '/hr/leave'),
    MenuItem(label: '薪资管理', icon: Icons.monetization_on, route: '/hr/salary'),
  ]),
  MenuItem(label: '项目管理', icon: Icons.task_alt, children: [
    MenuItem(label: '项目列表', icon: Icons.folder, route: '/project/list'),
    MenuItem(label: '任务管理', icon: Icons.checklist, route: '/project/task'),
    MenuItem(label: '工时记录', icon: Icons.timer, route: '/project/timesheet'),
  ]),
  MenuItem(label: '审批工作流', icon: Icons.account_tree, children: [
    MenuItem(label: '工作流定义', icon: Icons.schema, route: '/workflow/list'),
    MenuItem(label: '我的审批', icon: Icons.how_to_vote, route: '/workflow/my-approval'),
  ]),
  MenuItem(label: '通知中心', icon: Icons.notifications, route: '/notification'),
  MenuItem(label: '自定义报表', icon: Icons.bar_chart, children: [
    MenuItem(label: '报表管理', icon: Icons.auto_graph, route: '/report/list'),
    MenuItem(label: '定时调度', icon: Icons.schedule_send, route: '/report/schedule'),
  ]),
];

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
```

- [ ] **Step 2: Rewrite AdminLayout to support dynamic menu rendering**

```dart
// apps/flutter/lib/app/layouts/admin_layout.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../config/menu_config.dart';
import '../services/auth_service.dart';

class AdminLayout extends StatefulWidget {
  final Widget child;
  const AdminLayout({super.key, required this.child});

  @override
  State<AdminLayout> createState() => _AdminLayoutState();
}

class _AdminLayoutState extends State<AdminLayout> {
  bool _sidebarCollapsed = false;
  String? _expandedGroup;
  static const double sidebarW = 240;
  static const double sidebarCollapsedW = 64;
  static const double headerH = 56;

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);

  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  void _checkAuth() async {
    final ok = await AuthService.isLoggedIn();
    if (!ok && mounted) Get.offAllNamed('/login');
  }

  String? _currentGroupLabel() {
    final loc = Get.currentRoute;
    for (final item in menuConfig) {
      if (item.route == loc) return item.label;
      if (item.children != null) {
        for (final child in item.children!) {
          if (child.route == loc) return item.label;
        }
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    if (_isPhone) return _phoneLayout();
    return _desktopLayout();
  }

  Widget _phoneLayout() => Scaffold(
    appBar: AppBar(title: Text(_currentGroupLabel() ?? '管理后台'), actions: [_userMenu()]),
    drawer: Drawer(child: _sidebarContent()),
    body: Container(color: Theme.of(context).colorScheme.surfaceContainerLowest, padding: const EdgeInsets.all(16), child: widget.child),
  );

  Widget _desktopLayout() => Scaffold(body: Row(children: [
    _sidebar(),
    Expanded(child: Column(children: [
      _header(),
      Expanded(child: Container(color: Theme.of(context).colorScheme.surfaceContainerLowest, padding: const EdgeInsets.all(16), child: widget.child)),
    ])),
  ]));

  Widget _sidebar() => AnimatedContainer(
    duration: const Duration(milliseconds: 200),
    width: _sidebarCollapsed ? sidebarCollapsedW : sidebarW,
    child: _sidebarContent(),
  );

  Widget _sidebarContent() => ListView(children: [
    Container(height: headerH, padding: const EdgeInsets.symmetric(horizontal: 16), alignment: Alignment.centerLeft,
      child: _sidebarCollapsed ? const Icon(Icons.admin_panel_settings, size: 28) : const Text('管理后台', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
    const Divider(),
    ...menuConfig.map(_menuItem),
  ]);

  Widget _menuItem(MenuItem item) {
    final hasKids = item.children != null && item.children!.isNotEmpty;
    final isExpanded = _expandedGroup == item.label;
    final current = Get.currentRoute;
    if (!hasKids && item.route != null) {
      final sel = current == item.route;
      return ListTile(leading: Icon(item.icon, size: 20, color: sel ? Theme.of(context).colorScheme.primary : null),
        title: _sidebarCollapsed ? null : Text(item.label), selected: sel,
        onTap: () { if (item.route != current) Get.toNamed(item.route!); if (_isPhone) Navigator.pop(context); });
    }
    final childSel = item.children!.any((c) => c.route == current);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      ListTile(leading: Icon(item.icon, size: 20, color: childSel ? Theme.of(context).colorScheme.primary : null),
        title: _sidebarCollapsed ? null : Text(item.label, style: childSel ? TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w600) : null),
        trailing: _sidebarCollapsed ? null : Icon(isExpanded ? Icons.expand_less : Icons.expand_more, size: 18),
        onTap: () => setState(() => _expandedGroup = isExpanded ? null : item.label)),
      if (isExpanded && !_sidebarCollapsed)
        ...item.children!.map((c) => Padding(padding: const EdgeInsets.only(left: 32), child: ListTile(
          dense: true, leading: Icon(c.icon, size: 18, color: current == c.route ? Theme.of(context).colorScheme.primary : null),
          title: Text(c.label, style: TextStyle(fontSize: 13, color: current == c.route ? Theme.of(context).colorScheme.primary : null)),
          selected: current == c.route,
          onTap: () { if (c.route != current) Get.toNamed(c.route!); if (_isPhone) Navigator.pop(context); },
        ))),
    ]);
  }

  Widget _header() => Container(height: headerH, padding: const EdgeInsets.symmetric(horizontal: 16),
    decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, border: Border(bottom: BorderSide(color: Theme.of(context).dividerColor))),
    child: Row(children: [
      IconButton(icon: Icon(_sidebarCollapsed ? Icons.menu_open : Icons.menu), onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed)),
      const Spacer(), _userMenu(),
    ]));

  Widget _userMenu() => PopupMenuButton<String>(offset: const Offset(0, headerH),
    child: const Row(mainAxisSize: MainAxisSize.min, children: [CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)), SizedBox(width: 8), Text('管理员', style: TextStyle(fontSize: 14)), Icon(Icons.arrow_drop_down, size: 20)]),
    onSelected: (v) {
      if (v == 'profile') Get.toNamed('/profile');
      else if (v == 'logout') showDialog(context: context, builder: (ctx) => AlertDialog(
        title: const Text('确认退出'), content: const Text('确定要退出登录吗？'),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
          TextButton(onPressed: () async { Navigator.pop(ctx); await AuthService.clearToken(); Get.offAllNamed('/login'); }, child: const Text('确定退出', style: TextStyle(color: Colors.red)))]));
    },
    itemBuilder: (_) => const [PopupMenuItem(value: 'profile', child: Text('个人中心')), PopupMenuItem(value: 'logout', child: Text('退出登录'))]);
}
```

- [ ] **Step 3: Update main.dart with centralized route registration**

```dart
// apps/flutter/lib/main.dart (key changes)
// Register all pages via GetPage, wrap all non-public pages with AdminLayout
getPages: [
  GetPage(name: '/login', page: () => const LoginPage()),
  GetPage(name: '/dashboard', page: () => const AdminLayout(child: DashboardPage())),
  GetPage(name: '/profile', page: () => const ProfilePage()),
  GetPage(name: '/system/users', page: () => const AdminLayout(child: UserListPage())),
  // ... remaining 40+ routes added progressively in later tasks
],
```

- [ ] **Step 4: Migrate existing page file directories**

```bash
mkdir -p apps/flutter/lib/app/pages/system/user
mkdir -p apps/flutter/lib/app/pages/system/role
mkdir -p apps/flutter/lib/app/pages/system/config
mkdir -p apps/flutter/lib/app/pages/system/log
git mv apps/flutter/lib/app/pages/user/user_list_page.dart apps/flutter/lib/app/pages/system/user/
git mv apps/flutter/lib/app/pages/user/user_form_page.dart apps/flutter/lib/app/pages/system/user/
git mv apps/flutter/lib/app/pages/user/user_controller.dart apps/flutter/lib/app/pages/system/user/
git mv apps/flutter/lib/app/pages/role/role_list_page.dart apps/flutter/lib/app/pages/system/role/
git mv apps/flutter/lib/app/pages/role/role_controller.dart apps/flutter/lib/app/pages/system/role/
git mv apps/flutter/lib/app/pages/config/config_page.dart apps/flutter/lib/app/pages/system/config/
git mv apps/flutter/lib/app/pages/log/log_page.dart apps/flutter/lib/app/pages/system/log/
```

- [ ] **Step 5: Verify compilation** — Run: `cd apps/flutter && flutter analyze`

- [ ] **Step 6: Commit**

---

### Task 2: Common Component — DataTableWrapper

**Files:** Create: `apps/flutter/lib/app/widgets/data_table_wrapper.dart`

- [ ] **Step 1: Create the component**

```dart
// apps/flutter/lib/app/widgets/data_table_wrapper.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:data_table_2/data_table_2.dart';

class DataTableWrapper extends StatelessWidget {
  final List<String> columns;
  final List<Map<String, dynamic>> rows;
  final int total, page, limit;
  final bool loading;
  final ValueChanged<int>? onPageChanged;
  final ValueChanged<String>? onSearch;
  final Widget? filterBar;
  final List<Widget>? actions;

  const DataTableWrapper({super.key, required this.columns, required this.rows, required this.total, required this.page, required this.limit, this.loading = false, this.onPageChanged, this.onSearch, this.filterBar, this.actions});

  @override
  Widget build(BuildContext context) {
    final tp = (total / limit).ceil();
    return Column(children: [
      if (onSearch != null || actions != null)
        Padding(padding: const EdgeInsets.only(bottom: 8), child: Row(children: [
          if (onSearch != null) SizedBox(width: 280, child: TextField(decoration: InputDecoration(hintText: '搜索...', prefixIcon: const Icon(Icons.search, size: 20), isDense: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)), contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8)), onSubmitted: onSearch)),
          if (filterBar != null) ...[const SizedBox(width: 12), filterBar!],
          const Spacer(),
          if (actions != null) ...actions!,
        ])),
      Expanded(child: loading ? const Center(child: CircularProgressIndicator()) : rows.isEmpty ? const Center(child: Text('暂无数据')) : DataTable2(
        columnSpacing: 12, horizontalMargin: 12, minWidth: columns.length * 130.0,
        columns: columns.map((c) => DataColumn2(label: Text(c, style: const TextStyle(fontWeight: FontWeight.w600)))).toList(),
        rows: rows.map((r) => DataRow2(cells: columns.map((c) => DataCell(Text('${r[c] ?? ''}'))).toList())).toList(),
      )),
      if (tp > 1) Padding(padding: const EdgeInsets.only(top: 8), child: Row(mainAxisAlignment: MainAxisAlignment.end, children: [
        Text('共 $total 条', style: const TextStyle(fontSize: 13, color: Colors.grey)), const SizedBox(width: 16),
        IconButton(icon: const Icon(Icons.chevron_left, size: 20), onPressed: page > 1 ? () => onPageChanged?.call(page - 1) : null),
        Text('$page/${tp > 0 ? tp : 1}', style: const TextStyle(fontSize: 13)),
        IconButton(icon: const Icon(Icons.chevron_right, size: 20), onPressed: page < tp ? () => onPageChanged?.call(page + 1) : null),
      ])),
    ]);
  }
}
```

- [ ] **Step 2: Commit**

---

### Task 3: Common Components — FormDialog + ConfirmDialog + StatCard

**Files:**
- Create: `apps/flutter/lib/app/widgets/form_dialog.dart`
- Create: `apps/flutter/lib/app/widgets/confirm_dialog.dart`
- Create: `apps/flutter/lib/app/widgets/stat_card.dart`

- [ ] **Step 1: Create FormDialog**

```dart
// apps/flutter/lib/app/widgets/form_dialog.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

class FormFieldConfig {
  final String name, label;
  final String? initialValue, hint;
  final bool required;
  final FormFieldType type;
  final List<String>? options;
  const FormFieldConfig({required this.name, required this.label, this.initialValue, this.required = false, this.type = FormFieldType.text, this.options, this.hint});
}

enum FormFieldType { text, number, dropdown, multiline }

class FormDialog extends StatefulWidget {
  final String title;
  final List<FormFieldConfig> fields;
  final Map<String, dynamic>? initialData;
  final Future<bool> Function(Map<String, dynamic>)? onSubmit;
  const FormDialog({super.key, required this.title, required this.fields, this.initialData, this.onSubmit});

  static Future<T?> show<T>(BuildContext context, {required String title, required List<FormFieldConfig> fields, Map<String, dynamic>? initialData, required Future<bool> Function(Map<String, dynamic>) onSubmit}) {
    return showDialog<T>(context: context, builder: (ctx) => FormDialog(title: title, fields: fields, initialData: initialData, onSubmit: onSubmit));
  }

  @override
  State<FormDialog> createState() => _FormDialogState();
}

class _FormDialogState extends State<FormDialog> {
  final _fk = GlobalKey<FormState>();
  final _ctls = <String, TextEditingController>{};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) { _ctls[f.name] = TextEditingController(text: widget.initialData?[f.name]?.toString() ?? f.initialValue ?? ''); }
  }

  @override
  void dispose() { for (final c in _ctls.values) { c.dispose(); } super.dispose(); }

  @override
  Widget build(BuildContext ctx) => AlertDialog(title: Text(widget.title), content: SizedBox(width: 480, child: Form(key: _fk, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: widget.fields.map(_field).toList())))), actions: [
    TextButton(onPressed: _saving ? null : () => Navigator.pop(ctx), child: const Text('取消')),
    ElevatedButton(onPressed: _saving ? null : _submit, child: _saving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('保存')),
  ]);

  Widget _field(FormFieldConfig f) {
    final ctl = _ctls[f.name]!;
    return Padding(padding: const EdgeInsets.only(bottom: 12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text.rich(TextSpan(children: [TextSpan(text: f.label), if (f.required) const TextSpan(text: ' *', style: TextStyle(color: Colors.red))])),
      const SizedBox(height: 4),
      switch (f.type) {
        FormFieldType.multiline => TextFormField(controller: ctl, maxLines: 3, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
        FormFieldType.number => TextFormField(controller: ctl, keyboardType: TextInputType.number, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
        FormFieldType.dropdown => DropdownButtonFormField<String>(value: ctl.text.isEmpty ? null : ctl.text, items: (f.options ?? []).map((o) => DropdownMenuItem(value: o, child: Text(o))).toList(), onChanged: (v) => ctl.text = v ?? '', decoration: _dec(f.hint), validator: f.required ? (v) => v == null ? '请选择${f.label}' : null : null),
        _ => TextFormField(controller: ctl, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
      },
    ]));
  }

  InputDecoration _dec(String? hint) => InputDecoration(hintText: hint, border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)), isDense: true);
  FormFieldValidator<String> _req(String label) => (v) => (v == null || v.isEmpty) ? '请输入$label' : null;

  Future<void> _submit() async {
    if (!_fk.currentState!.validate()) return;
    setState(() => _saving = true);
    final data = <String, dynamic>{};
    for (final f in widget.fields) { data[f.name] = _ctls[f.name]!.text; }
    try { final ok = await widget.onSubmit?.call(data) ?? false; if (ok && mounted) Navigator.pop(ctx, true); }
    finally { if (mounted) setState(() => _saving = false); }
  }
}
```

- [ ] **Step 2: Create ConfirmDialog**

```dart
// apps/flutter/lib/app/widgets/confirm_dialog.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

class ConfirmDialog extends StatefulWidget {
  final String title, content;
  final Future<void> Function(String password)? onConfirm;
  const ConfirmDialog({super.key, required this.title, required this.content, this.onConfirm});

  static Future<bool> show(BuildContext context, {required String title, required String content, required Future<bool> Function(String) onConfirm}) async {
    final r = await showDialog<bool>(context: context, builder: (ctx) => ConfirmDialog(title: title, content: content, onConfirm: (pwd) async {
      final ok = await onConfirm(pwd);
      if (ok && ctx.mounted) Navigator.pop(ctx, true);
    }));
    return r ?? false;
  }

  @override
  State<ConfirmDialog> createState() => _ConfirmDialogState();
}

class _ConfirmDialogState extends State<ConfirmDialog> {
  final _pwd = TextEditingController();
  bool _loading = false;
  @override
  void dispose() { _pwd.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext ctx) => AlertDialog(title: Text(widget.title), content: Column(mainAxisSize: MainAxisSize.min, children: [
    Text(widget.content), const SizedBox(height: 16),
    TextField(controller: _pwd, obscureText: true, decoration: InputDecoration(labelText: '请输入管理员密码确认', border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)))),
  ]), actions: [
    TextButton(onPressed: _loading ? null : () => Navigator.pop(ctx), child: const Text('取消')),
    ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: Colors.red), onPressed: _loading ? null : () {
      if (_pwd.text.isEmpty) return;
      setState(() => _loading = true);
      widget.onConfirm?.call(_pwd.text);
      setState(() => _loading = false);
    }, child: const Text('确认删除', style: TextStyle(color: Colors.white))),
  ]);
}
```

- [ ] **Step 3: Create StatCard**

```dart
// apps/flutter/lib/app/widgets/stat_card.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';

class StatCard extends StatelessWidget {
  final String title, value;
  final IconData icon;
  final Color color;
  final String? trend;
  final bool trendUp;
  const StatCard({super.key, required this.title, required this.value, required this.icon, required this.color, this.trend, this.trendUp = true});

  @override
  Widget build(BuildContext ctx) => Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(title, style: TextStyle(fontSize: 13, color: Colors.grey[600])), Icon(icon, color: color, size: 28)]),
    const SizedBox(height: 8), Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
    if (trend != null) ...[const SizedBox(height: 4), Row(children: [
      Icon(trendUp ? Icons.arrow_upward : Icons.arrow_downward, size: 14, color: trendUp ? Colors.green : Colors.red),
      Text(' $trend', style: TextStyle(fontSize: 12, color: trendUp ? Colors.green : Colors.red)),
    ])],
  ])));
}
```

- [ ] **Step 4: Commit**

---

### Tasks 4-20: Batch Module Page Creation

> Each task covers one module; all list pages follow a unified CRUD pattern.

**Standard CRUD list page template** (~70 lines per page):

```dart
// apps/flutter/lib/app/pages/{module}/{entity}_list_page.dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../widgets/data_table_wrapper.dart';
import '../../widgets/form_dialog.dart';
import '../../widgets/confirm_dialog.dart';

class {Entity}ListPage extends StatefulWidget {
  const {Entity}ListPage({super.key});
  @override
  State<{Entity}ListPage> createState() => _{Entity}ListPageState();
}

class _{Entity}ListPageState extends State<{Entity}ListPage> {
  List<Map<String, dynamic>> _rows = [];
  int _total = 0, _page = 1;
  final int _limit = 20;
  String _keyword = '';
  String? _statusFilter;
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    final params = <String, String>{'page': '$_page', 'limit': '$_limit', 'keyword': _keyword};
    if (_statusFilter != null) params['status'] = _statusFilter!;
    final res = await ApiService.instance.get('/admin/{module}/{entity}', params: params);
    final d = res['data'];
    setState(() { _rows = List<Map<String, dynamic>>.from(d['list'] ?? []); _total = d['total'] ?? 0; _loading = false; });
  }

  Future<void> _create() async {
    await FormDialog.show(context, title: '新增', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/{module}/{entity}', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: '编辑', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/{module}/{entity}/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: '确认删除', content: '确定要删除「${row['name']}」吗？', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/{module}/{entity}/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: '名称', required: true),
    FormFieldConfig(name: 'code', label: '编码'),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('新增'))],
  );

  List<String> _columns() => const ['名称', '编码', '状态', '操作'];  // adjust per module
  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {'名称': r['name'], '编码': r['code'], '操作': Row(children: [
    IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
    IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
  ])};  // adjust per module
}
```

**Page creation checklist**:

| Task | Module | Directory | Page Count |
|------|--------|-----------|------------|
| 4 | Product | `pages/product/` | 4 |
| 5 | Partners | `pages/partner/` | 4 |
| 6 | Purchase | `pages/purchase/` | 4 |
| 7 | Sales | `pages/sales/` | 4 |
| 8 | Inventory | `pages/inventory/` | 5 |
| 9 | Finance 1 (voucher/AR-AP/receipts/payments/journal/expense) | `pages/finance/` | 6 |
| 10 | Finance 2 (ledger/reports/assets/tax/currency/budget/cost) | `pages/finance/` | 7 |
| 11 | CRM | `pages/crm/` | 8 |
| 12 | OMS | `pages/oms/` | 4 |
| 13 | WMS | `pages/wms/` | 7 |
| 14 | TMS | `pages/tms/` | 5 |
| 15 | Manufacturing | `pages/manufacturing/` | 5 |
| 16 | HR | `pages/hr/` | 6 |
| 17 | Project | `pages/project/` | 3 |
| 18 | Approval workflow | `pages/workflow/` | 2 |
| 19 | Notification | `pages/notification/` | 1 |
| 20 | Custom reports | `pages/report/` | 2 |

After each task completes: update `main.dart` to add the corresponding routes + verify with `flutter analyze`.

---

### Task 21: Dashboard Expansion

**Files:**
- Modify: `apps/flutter/lib/app/pages/dashboard/dashboard_page.dart`
- Modify: `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`

- [ ] **Step 1: Add OMS/WMS/TMS dashboards on top of the existing 4 tabs**

Add to TabBar: `const Tab(text: 'OMS')`, `const Tab(text: 'WMS')`, `const Tab(text: 'TMS')`
Add corresponding `_omsTab()`, `_wmsTab()`, `_tmsTab()` methods to TabBarView, each containing 3-4 StatCards.

- [ ] **Step 2: Commit**

---

### Task 22: Full Route Registration + Final Verification

- [ ] **Step 1: Complete all GetPage registrations in main.dart**
- [ ] **Step 2: `flutter analyze` → No issues found**
- [ ] **Step 3: `flutter run -d chrome` manual acceptance of all 14 module menus and pages**
- [ ] **Step 4: Commit**

---

### Task 23: HarmonyOS Alignment

**Files:** create new pages under `apps/harmonyos/entry/src/main/ets/pages/`

- [ ] Create ArkTS pages for 8 modules: OMS/WMS/TMS/Manufacturing/HR/Approval/Notification/Report
- [ ] Update `main_pages.json` to register routes
- [ ] Verify compilation

---

**Acceptance Criteria (P0 complete):**
- [ ] All 14 modules in the Flutter Web sidebar are navigable
- [ ] All CRUD list pages load, paginate, and search correctly
- [ ] Create/Edit dialogs + delete password confirmation work
- [ ] PC/tablet/phone responsive layout works correctly
- [ ] JWT expiration auto-refreshes
- [ ] HarmonyOS page count ≥ 80% of the Flutter page count
