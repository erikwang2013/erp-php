# Panel Admin Web Flutter P0 — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Tujuan:** Memperluas panel admin web Flutter dari ~12 halaman yang ada menjadi 40+ halaman yang mencakup seluruh 14 modul backend, membangun pustaka komponen umum yang dapat digunakan kembali.

**Arsitektur:** Direfaktor menjadi sistem menu dinamis berbasis konfigurasi (`menu_config.dart`), tata letak tiga kolom PC (sidebar dapat dilipat + topbar + area konten), komponen CRUD umum (DataTableWrapper/FormDialog/ConfirmDialog) yang dipakai ulang oleh halaman-halaman setiap modul.

**Tumpukan Teknologi:** Flutter 3.x, Dart 3.11+, GetX 4.6, Dio 5.4, data_table_2 2.5, fl_chart 0.68, printing 5.12, excel 4.0, responsive_framework 1.4

---

## Struktur File

```
apps/flutter/lib/app/
├── main.dart                          # Modify: pendaftaran rute terpusat
├── config/
│   └── menu_config.dart               # Create: konfigurasi pohon menu navigasi
├── layouts/
│   └── admin_layout.dart              # Rewrite: render menu dinamis
├── services/                          # Tidak berubah
├── widgets/                           # Create 5 komponen umum
│   ├── data_table_wrapper.dart
│   ├── form_dialog.dart
│   ├── confirm_dialog.dart
│   ├── stat_card.dart
│   └── search_bar.dart
└── pages/                             # Direorganisasi menjadi direktori modular
    ├── system/user/..., role/..., config/..., log/...
    ├── product/product_list, product_form, category_list, brand_list
    ├── partner/supplier_list, customer_list, warehouse_list, location_list
    ├── purchase/apply_list, order_list, receive_list, return_list
    ├── sales/quotation_list, order_list, delivery_list, return_list
    ├── inventory/inventory_list, flow_list, transfer_list, check_list, alert_list
    ├── finance/(13 halaman)
    ├── crm/(8 halaman)
    ├── oms/(4 halaman)
    ├── wms/(7 halaman)
    ├── tms/(5 halaman)
    ├── manufacturing/(5 halaman)
    ├── hr/(6 halaman)
    ├── project/(3 halaman)
    ├── workflow/(2 halaman)
    ├── notification/notification_page
    └── report/(2 halaman)
```

---

### Task 1: Refaktor konfigurasi menu dan rute

**File:**
- Create: `apps/flutter/lib/app/config/menu_config.dart`
- Modify: `apps/flutter/lib/app/layouts/admin_layout.dart` (rewrite penuh)
- Modify: `apps/flutter/lib/main.dart` (pendaftaran rute)
- Move: migrasi file halaman yang ada ke struktur direktori baru

- [ ] **Step 1: Buat menu_config.dart**

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
  MenuItem(label: 'Dasbor', icon: Icons.dashboard, route: '/dashboard'),
  MenuItem(label: 'Manajemen sistem', icon: Icons.settings, children: [
    MenuItem(label: 'Manajemen pengguna', icon: Icons.people, route: '/system/users'),
    MenuItem(label: 'Peran & izin', icon: Icons.security, route: '/system/roles'),
    MenuItem(label: 'Konfigurasi sistem', icon: Icons.tune, route: '/system/config'),
    MenuItem(label: 'Log operasi', icon: Icons.description, route: '/system/logs'),
  ]),
  MenuItem(label: 'Manajemen produk', icon: Icons.inventory_2, children: [
    MenuItem(label: 'Daftar produk', icon: Icons.list_alt, route: '/product/list'),
    MenuItem(label: 'Kategori produk', icon: Icons.category, route: '/product/category'),
    MenuItem(label: 'Manajemen merek', icon: Icons.bookmark, route: '/product/brand'),
  ]),
  MenuItem(label: 'Unit bisnis', icon: Icons.business, children: [
    MenuItem(label: 'Pemasok', icon: Icons.local_shipping, route: '/partner/supplier'),
    MenuItem(label: 'Pelanggan', icon: Icons.people_outline, route: '/partner/customer'),
    MenuItem(label: 'Gudang', icon: Icons.warehouse, route: '/partner/warehouse'),
    MenuItem(label: 'Lokasi', icon: Icons.pin_drop, route: '/partner/location'),
  ]),
  MenuItem(label: 'Manajemen pembelian', icon: Icons.shopping_cart, children: [
    MenuItem(label: 'Permintaan pembelian', icon: Icons.request_quote, route: '/purchase/apply'),
    MenuItem(label: 'Pesanan pembelian', icon: Icons.receipt_long, route: '/purchase/order'),
    MenuItem(label: 'Penerimaan pembelian', icon: Icons.move_to_inbox, route: '/purchase/receive'),
    MenuItem(label: 'Retur pembelian', icon: Icons.assignment_return, route: '/purchase/return'),
  ]),
  MenuItem(label: 'Manajemen penjualan', icon: Icons.point_of_sale, children: [
    MenuItem(label: 'Penawaran penjualan', icon: Icons.price_check, route: '/sales/quotation'),
    MenuItem(label: 'Pesanan penjualan', icon: Icons.receipt, route: '/sales/order'),
    MenuItem(label: 'Pengiriman penjualan', icon: Icons.local_shipping, route: '/sales/delivery'),
    MenuItem(label: 'Retur penjualan', icon: Icons.assignment_return, route: '/sales/return'),
  ]),
  MenuItem(label: 'Manajemen stok', icon: Icons.inventory, children: [
    MenuItem(label: 'Stok real-time', icon: Icons.storage, route: '/inventory/list'),
    MenuItem(label: 'Transaksi stok', icon: Icons.sync_alt, route: '/inventory/flow'),
    MenuItem(label: 'Transfer stok', icon: Icons.swap_horiz, route: '/inventory/transfer'),
    MenuItem(label: 'Tugas opname', icon: Icons.fact_check, route: '/inventory/check'),
    MenuItem(label: 'Peringatan stok', icon: Icons.warning_amber, route: '/inventory/alert'),
  ]),
  MenuItem(label: 'Manajemen keuangan', icon: Icons.account_balance, children: [
    MenuItem(label: 'Voucher pembukuan', icon: Icons.description, route: '/finance/voucher'),
    MenuItem(label: 'Piutang/hutang', icon: Icons.swap_horiz, route: '/finance/ar-ap'),
    MenuItem(label: 'Manajemen penerimaan', icon: Icons.payments, route: '/finance/receipt'),
    MenuItem(label: 'Manajemen pembayaran', icon: Icons.credit_card, route: '/finance/payment'),
    MenuItem(label: 'Jurnal kas', icon: Icons.book, route: '/finance/cash-journal'),
    MenuItem(label: 'Reimburse biaya', icon: Icons.receipt_long, route: '/finance/expense'),
    MenuItem(label: 'Buku besar/buku pembantu', icon: Icons.menu_book, route: '/finance/ledger'),
    MenuItem(label: 'Laporan keuangan', icon: Icons.assessment, route: '/finance/report'),
    MenuItem(label: 'Aset tetap', icon: Icons.account_balance_wallet, route: '/finance/asset'),
    MenuItem(label: 'Manajemen pajak', icon: Icons.gavel, route: '/finance/tax'),
    MenuItem(label: 'Multi-mata uang/nilai tukar', icon: Icons.currency_exchange, route: '/finance/currency'),
    MenuItem(label: 'Manajemen anggaran', icon: Icons.savings, route: '/finance/budget'),
    MenuItem(label: 'Pusat biaya/laba', icon: Icons.pie_chart, route: '/finance/cost-profit'),
  ]),
  MenuItem(label: 'CRM', icon: Icons.people_alt, children: [
    MenuItem(label: 'Manajemen peluang', icon: Icons.lightbulb, route: '/crm/opportunity'),
    MenuItem(label: 'Kontak', icon: Icons.contacts, route: '/crm/contact'),
    MenuItem(label: 'Pool bersama', icon: Icons.water, route: '/crm/pool'),
    MenuItem(label: 'Manajemen kontrak', icon: Icons.handshake, route: '/crm/contract'),
    MenuItem(label: 'Penawaran', icon: Icons.request_quote, route: '/crm/quotation'),
    MenuItem(label: 'Kampanye pemasaran', icon: Icons.campaign, route: '/crm/campaign'),
    MenuItem(label: 'Tiket layanan', icon: Icons.support_agent, route: '/crm/ticket'),
    MenuItem(label: 'Analisis pelanggan', icon: Icons.analytics, route: '/crm/analytics'),
  ]),
  MenuItem(label: 'Manajemen pesanan', icon: Icons.shopping_bag, children: [
    MenuItem(label: 'Pesanan OMS', icon: Icons.list_alt, route: '/oms/order'),
    MenuItem(label: 'Manajemen pemenuhan', icon: Icons.checklist, route: '/oms/fulfillment'),
    MenuItem(label: 'Retur tukar (RMA)', icon: Icons.replay, route: '/oms/rma'),
    MenuItem(label: 'Manajemen kanal', icon: Icons.hub, route: '/oms/channel'),
  ]),
  MenuItem(label: 'Manajemen gudang', icon: Icons.warehouse, children: [
    MenuItem(label: 'Manajemen zona', icon: Icons.grid_view, route: '/wms/zone'),
    MenuItem(label: 'Kedatangan terantisipasi (ASN)', icon: Icons.note_add, route: '/wms/asn'),
    MenuItem(label: 'Manajemen penerimaan', icon: Icons.download, route: '/wms/receiving'),
    MenuItem(label: 'Manajemen putaway', icon: Icons.upload, route: '/wms/putaway'),
    MenuItem(label: 'Manajemen gelombang', icon: Icons.waves, route: '/wms/wave'),
    MenuItem(label: 'Manajemen picking', icon: Icons.shopping_basket, route: '/wms/pick'),
    MenuItem(label: 'Manajemen pengepakan', icon: Icons.inventory_2, route: '/wms/pack'),
  ]),
  MenuItem(label: 'Manajemen transportasi', icon: Icons.local_shipping, children: [
    MenuItem(label: 'Kurir', icon: Icons.business, route: '/tms/carrier'),
    MenuItem(label: 'Tarif ongkos kirim', icon: Icons.attach_money, route: '/tms/freight-rate'),
    MenuItem(label: 'Manajemen waybill', icon: Icons.content_paste, route: '/tms/shipment'),
    MenuItem(label: 'Lacak logistik', icon: Icons.track_changes, route: '/tms/tracking'),
    MenuItem(label: 'Faktur ongkos kirim', icon: Icons.receipt, route: '/tms/freight-invoice'),
  ]),
  MenuItem(label: 'Manufaktur produksi', icon: Icons.precision_manufacturing, children: [
    MenuItem(label: 'Manajemen BOM', icon: Icons.account_tree, route: '/mfg/bom'),
    MenuItem(label: 'Work order produksi', icon: Icons.engineering, route: '/mfg/production'),
    MenuItem(label: 'Routing proses', icon: Icons.route, route: '/mfg/routing'),
    MenuItem(label: 'Stasiun kerja', icon: Icons.desktop_windows, route: '/mfg/workstation'),
    MenuItem(label: 'Perencanaan MRP', icon: Icons.calculate, route: '/mfg/mrp'),
  ]),
  MenuItem(label: 'Sumber daya manusia', icon: Icons.groups, children: [
    MenuItem(label: 'Manajemen departemen', icon: Icons.org_chart, route: '/hr/department'),
    MenuItem(label: 'Arsip karyawan', icon: Icons.badge, route: '/hr/employee'),
    MenuItem(label: 'Manajemen posisi', icon: Icons.work, route: '/hr/position'),
    MenuItem(label: 'Manajemen absensi', icon: Icons.access_time, route: '/hr/attendance'),
    MenuItem(label: 'Manajemen cuti', icon: Icons.event_busy, route: '/hr/leave'),
    MenuItem(label: 'Manajemen gaji', icon: Icons.monetization_on, route: '/hr/salary'),
  ]),
  MenuItem(label: 'Manajemen proyek', icon: Icons.task_alt, children: [
    MenuItem(label: 'Daftar proyek', icon: Icons.folder, route: '/project/list'),
    MenuItem(label: 'Manajemen tugas', icon: Icons.checklist, route: '/project/task'),
    MenuItem(label: 'Catatan jam kerja', icon: Icons.timer, route: '/project/timesheet'),
  ]),
  MenuItem(label: 'Alur kerja persetujuan', icon: Icons.account_tree, children: [
    MenuItem(label: 'Definisi alur kerja', icon: Icons.schema, route: '/workflow/list'),
    MenuItem(label: 'Persetujuan saya', icon: Icons.how_to_vote, route: '/workflow/my-approval'),
  ]),
  MenuItem(label: 'Pusat notifikasi', icon: Icons.notifications, route: '/notification'),
  MenuItem(label: 'Pelaporan kustom', icon: Icons.bar_chart, children: [
    MenuItem(label: 'Manajemen laporan', icon: Icons.auto_graph, route: '/report/list'),
    MenuItem(label: 'Penjadwalan', icon: Icons.schedule_send, route: '/report/schedule'),
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

- [ ] **Step 2: Tulis ulang AdminLayout untuk mendukung render menu dinamis**

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
    appBar: AppBar(title: Text(_currentGroupLabel() ?? 'Panel admin'), actions: [_userMenu()]),
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
      child: _sidebarCollapsed ? const Icon(Icons.admin_panel_settings, size: 28) : const Text('Panel admin', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
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
    child: const Row(mainAxisSize: MainAxisSize.min, children: [CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)), SizedBox(width: 8), Text('Administrator', style: TextStyle(fontSize: 14)), Icon(Icons.arrow_drop_down, size: 20)]),
    onSelected: (v) {
      if (v == 'profile') Get.toNamed('/profile');
      else if (v == 'logout') showDialog(context: context, builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi keluar'), content: const Text('Yakin ingin keluar dari login?'),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          TextButton(onPressed: () async { Navigator.pop(ctx); await AuthService.clearToken(); Get.offAllNamed('/login'); }, child: const Text('Ya, keluar', style: TextStyle(color: Colors.red)))]));
    },
    itemBuilder: (_) => const [PopupMenuItem(value: 'profile', child: Text('Pusat personal')), PopupMenuItem(value: 'logout', child: Text('Keluar dari login'))]);
}
```

- [ ] **Step 3: Perbarui main.dart — pendaftaran rute terpusat**

```dart
// apps/flutter/lib/main.dart (perubahan kunci)
// Daftarkan semua halaman melalui GetPage, semua halaman non-publik dibungkus AdminLayout
getPages: [
  GetPage(name: '/login', page: () => const LoginPage()),
  GetPage(name: '/dashboard', page: () => const AdminLayout(child: DashboardPage())),
  GetPage(name: '/profile', page: () => const ProfilePage()),
  GetPage(name: '/system/users', page: () => const AdminLayout(child: UserListPage())),
  // ... ditambahkan bertahap pada task-task berikutnya untuk 40+ rute lainnya
],
```

- [ ] **Step 4: Migrasi direktori file halaman yang ada**

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

- [ ] **Step 5: Verifikasi kompilasi** — Run: `cd apps/flutter && flutter analyze`

- [ ] **Step 6: Commit**

---

### Task 2: Komponen umum — DataTableWrapper

**Files:** Create: `apps/flutter/lib/app/widgets/data_table_wrapper.dart`

- [ ] **Step 1: Buat komponen**

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
          if (onSearch != null) SizedBox(width: 280, child: TextField(decoration: InputDecoration(hintText: 'Cari...', prefixIcon: const Icon(Icons.search, size: 20), isDense: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)), contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8)), onSubmitted: onSearch)),
          if (filterBar != null) ...[const SizedBox(width: 12), filterBar!],
          const Spacer(),
          if (actions != null) ...actions!,
        ])),
      Expanded(child: loading ? const Center(child: CircularProgressIndicator()) : rows.isEmpty ? const Center(child: Text('Tidak ada data')) : DataTable2(
        columnSpacing: 12, horizontalMargin: 12, minWidth: columns.length * 130.0,
        columns: columns.map((c) => DataColumn2(label: Text(c, style: const TextStyle(fontWeight: FontWeight.w600)))).toList(),
        rows: rows.map((r) => DataRow2(cells: columns.map((c) => DataCell(Text('${r[c] ?? ''}'))).toList())).toList(),
      )),
      if (tp > 1) Padding(padding: const EdgeInsets.only(top: 8), child: Row(mainAxisAlignment: MainAxisAlignment.end, children: [
        Text('Total $total data', style: const TextStyle(fontSize: 13, color: Colors.grey)), const SizedBox(width: 16),
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

### Task 3: Komponen umum — FormDialog + ConfirmDialog + StatCard

**File:**
- Create: `apps/flutter/lib/app/widgets/form_dialog.dart`
- Create: `apps/flutter/lib/app/widgets/confirm_dialog.dart`
- Create: `apps/flutter/lib/app/widgets/stat_card.dart`

- [ ] **Step 1: Buat FormDialog**

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
    TextButton(onPressed: _saving ? null : () => Navigator.pop(ctx), child: const Text('Batal')),
    ElevatedButton(onPressed: _saving ? null : _submit, child: _saving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Simpan')),
  ]);

  Widget _field(FormFieldConfig f) {
    final ctl = _ctls[f.name]!;
    return Padding(padding: const EdgeInsets.only(bottom: 12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text.rich(TextSpan(children: [TextSpan(text: f.label), if (f.required) const TextSpan(text: ' *', style: TextStyle(color: Colors.red))])),
      const SizedBox(height: 4),
      switch (f.type) {
        FormFieldType.multiline => TextFormField(controller: ctl, maxLines: 3, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
        FormFieldType.number => TextFormField(controller: ctl, keyboardType: TextInputType.number, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
        FormFieldType.dropdown => DropdownButtonFormField<String>(value: ctl.text.isEmpty ? null : ctl.text, items: (f.options ?? []).map((o) => DropdownMenuItem(value: o, child: Text(o))).toList(), onChanged: (v) => ctl.text = v ?? '', decoration: _dec(f.hint), validator: f.required ? (v) => v == null ? 'Silakan pilih ${f.label}' : null : null),
        _ => TextFormField(controller: ctl, decoration: _dec(f.hint), validator: f.required ? _req(f.label) : null),
      },
    ]));
  }

  InputDecoration _dec(String? hint) => InputDecoration(hintText: hint, border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)), isDense: true);
  FormFieldValidator<String> _req(String label) => (v) => (v == null || v.isEmpty) ? 'Silakan isi $label' : null;

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

- [ ] **Step 2: Buat ConfirmDialog**

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
    TextField(controller: _pwd, obscureText: true, decoration: InputDecoration(labelText: 'Silakan masukkan kata sandi administrator untuk konfirmasi', border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)))),
  ]), actions: [
    TextButton(onPressed: _loading ? null : () => Navigator.pop(ctx), child: const Text('Batal')),
    ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: Colors.red), onPressed: _loading ? null : () {
      if (_pwd.text.isEmpty) return;
      setState(() => _loading = true);
      widget.onConfirm?.call(_pwd.text);
      setState(() => _loading = false);
    }, child: const Text('Konfirmasi hapus', style: TextStyle(color: Colors.white))),
  ]);
}
```

- [ ] **Step 3: Buat StatCard**

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

### Task 4-20: Pembuatan massal halaman modul

> Setiap task mencakup satu modul, semua halaman daftar mengikuti pola CRUD terpadu.

**Template standar halaman daftar CRUD** (setiap halaman ~70 baris):

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
    await FormDialog.show(context, title: 'Tambah', fields: _formFields(), onSubmit: (data) async {
      await ApiService.instance.post('/admin/{module}/{entity}', data: data);
      _load(); return true;
    });
  }

  Future<void> _edit(Map<String, dynamic> row) async {
    await FormDialog.show(context, title: 'Edit', fields: _formFields(), initialData: row, onSubmit: (data) async {
      await ApiService.instance.put('/admin/{module}/{entity}/${row['id']}', data: data);
      _load(); return true;
    });
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    await ConfirmDialog.show(context, title: 'Konfirmasi hapus', content: 'Yakin ingin menghapus «${row['name']}»?', onConfirm: (password) async {
      await ApiService.instance.delete('/admin/{module}/{entity}/${row['id']}', data: {'password': password});
      _load(); return true;
    });
  }

  List<FormFieldConfig> _formFields() => const [
    FormFieldConfig(name: 'name', label: 'Nama', required: true),
    FormFieldConfig(name: 'code', label: 'Kode'),
  ];

  @override
  Widget build(BuildContext context) => DataTableWrapper(
    columns: _columns(),
    rows: _rows.map((r) => _rowToMap(r)).toList(),
    total: _total, page: _page, limit: _limit, loading: _loading,
    onSearch: (v) { _keyword = v; _page = 1; _load(); },
    onPageChanged: (p) { _page = p; _load(); },
    actions: [ElevatedButton.icon(onPressed: _create, icon: const Icon(Icons.add, size: 18), label: const Text('Tambah'))],
  );

  List<String> _columns() => const ['Nama', 'Kode', 'Status', 'Aksi'];  // sesuaikan per modul
  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) => {'Nama': r['name'], 'Kode': r['code'], 'Aksi': Row(children: [
    IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _edit(r)),
    IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _delete(r)),
  ])};  // sesuaikan per modul
}
```

**Daftar pembuatan halaman**:

| Task | Modul | Direktori | Jumlah halaman |
|------|------|------|--------|
| 4 | Produk | `pages/product/` | 4 |
| 5 | Unit bisnis | `pages/partner/` | 4 |
| 6 | Pembelian | `pages/purchase/` | 4 |
| 7 | Penjualan | `pages/sales/` | 4 |
| 8 | Stok | `pages/inventory/` | 5 |
| 9 | Keuangan 1 (voucher/piutang hutang/penerimaan-pembayaran/jurnal/reimburse) | `pages/finance/` | 6 |
| 10 | Keuangan 2 (buku besar/laporan/aset/pajak/mata uang/anggaran/biaya) | `pages/finance/` | 7 |
| 11 | CRM | `pages/crm/` | 8 |
| 12 | OMS | `pages/oms/` | 4 |
| 13 | WMS | `pages/wms/` | 7 |
| 14 | TMS | `pages/tms/` | 5 |
| 15 | Manufaktur | `pages/manufacturing/` | 5 |
| 16 | SDM | `pages/hr/` | 6 |
| 17 | Proyek | `pages/project/` | 3 |
| 18 | Alur kerja persetujuan | `pages/workflow/` | 2 |
| 19 | Notifikasi | `pages/notification/` | 1 |
| 20 | Pelaporan kustom | `pages/report/` | 2 |

Setelah setiap task selesai: perbarui `main.dart` tambahkan rute terkait + verifikasi `flutter analyze`.

---

### Task 21: Perluasan Dashboard

**File:**
- Modify: `apps/flutter/lib/app/pages/dashboard/dashboard_page.dart`
- Modify: `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`

- [ ] **Step 1: Tambahkan papan OMS/WMS/TMS di atas 4 Tab yang ada**

TabBar tambahkan: `const Tab(text: 'OMS')`, `const Tab(text: 'WMS')`, `const Tab(text: 'TMS')`
TabBarView tambahkan metode `_omsTab()`, `_wmsTab()`, `_tmsTab()` yang bersesuaian, masing-masing berisi 3-4 StatCard.

- [ ] **Step 2: Commit**

---

### Task 22: Pendaftaran semua rute + verifikasi akhir

- [ ] **Step 1: Lengkapi semua pendaftaran GetPage di main.dart**
- [ ] **Step 2: `flutter analyze` → No issues found**
- [ ] **Step 3: `flutter run -d chrome` terima manual seluruh menu dan halaman 14 modul**
- [ ] **Step 4: Commit**

---

### Task 23: Penyelarasan HarmonyOS

**Files:** Buat halaman baru di bawah `apps/harmonyos/entry/src/main/ets/pages/`

- [ ] Buat halaman ArkTS untuk 8 modul: OMS/WMS/TMS/manufaktur/SDM/persetujuan/notifikasi/laporan
- [ ] Perbarui `main_pages.json` untuk mendaftarkan rute
- [ ] Verifikasi kompilasi

---

**Kriteria penerimaan (P0 selesai):**
- [ ] Sidebar Flutter Web 14 modul semuanya dapat dinavigasi
- [ ] Semua halaman daftar CRUD memuat normal, paginasi, pencarian
- [ ] Dialog buat/edit + konfirmasi kata sandi hapus berfungsi
- [ ] Tata letak responsif PC/tablet/ponsel normal
- [ ] JWT kedaluwarsa otomatis refresh
- [ ] Jumlah halaman HarmonyOS ≥ 80% halaman Flutter
