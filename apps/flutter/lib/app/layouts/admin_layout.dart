// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../services/auth_service.dart';
import '../config/menu_config.dart';
import '../l10n/app_l10n.dart';

class AdminLayout extends StatefulWidget {
  final Widget child;
  const AdminLayout({super.key, required this.child});

  @override
  State<AdminLayout> createState() => _AdminLayoutState();
}

class _AdminLayoutState extends State<AdminLayout> {
  bool _sidebarCollapsed = false;
  String? _previousBreakpoint;
  late String _selectedRoute = Get.currentRoute;
  final Set<String> _expanded = <String>{};

  static const double sidebarWidth = 240;
  static const double sidebarCollapsedWidth = 64;
  static const double headerHeight = 56;

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);
  bool get _isTablet => _bp.equals(TABLET);

  @override
  void initState() {
    super.initState();
    _expandForRoute(_selectedRoute, menuConfig);
    _checkAuth();
  }

  /// Expand every parent group that contains [route] so the active menu
  /// item is visible on first build.
  void _expandForRoute(String route, List<MenuItem> items) {
    for (final item in items) {
      final children = item.children;
      if (children == null) continue;
      for (final c in children) {
        if (c.route == route) _expanded.add(item.label);
      }
      _expandForRoute(route, children);
    }
  }

  Future<void> _checkAuth() async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn && mounted) {
      Get.offAllNamed('/login');
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final current = _bp.breakpoint.name;
    if (_previousBreakpoint != null && _previousBreakpoint != current) {
      _sidebarCollapsed = _isTablet;
    }
    _previousBreakpoint = current;
    // Refresh the sidebar highlight after back navigation / route changes.
    _syncSelectedRoute();
  }

  /// Keeps the sidebar highlight in sync with the current route (e.g. after
  /// Get.back() or browser back, where [_goTo] was never called).
  void _syncSelectedRoute() {
    final current = Get.currentRoute;
    if (_selectedRoute == current) return;
    bool isValid = false;
    _walkItems(menuConfig, (item) {
      if (item.route == current) isValid = true;
    });
    if (isValid) setState(() => _selectedRoute = current);
  }

  /// Depth-first traversal of [items], invoking [visit] for every node.
  void _walkItems(List<MenuItem> items, void Function(MenuItem) visit) {
    for (final item in items) {
      visit(item);
      final children = item.children;
      if (children != null) _walkItems(children, visit);
    }
  }

  void _goTo(String route, {bool closeDrawer = false}) {
    if (closeDrawer) Navigator.of(context).pop(); // close the phone drawer
    // 目标路由已在栈顶（当前页）时不重复压栈，避免反复点击同菜单叠页。
    // 抽屉不是独立路由，先 pop 再比较仍指向上层页面的路由。
    if (Get.currentRoute == route) return;
    setState(() => _selectedRoute = route);
    Get.toNamed(route);
  }

  void _toggleExpanded(String label) {
    setState(() {
      if (!_expanded.remove(label)) _expanded.add(label);
    });
  }

  /// 菜单显示文案：优先取 i18n（i18nKey 非空时），否则回退硬编码 label。
  /// 最小国际化：仅 nav.dashboard / nav.system 两项接入，其余后续 i18n。
  String _menuLabel(MenuItem item) {
    final l10n = AppL10n.of(context);
    switch (item.i18nKey) {
      case 'nav.dashboard':
        return l10n.navDashboard;
      case 'nav.system':
        return l10n.navSystem;
      default:
        return item.label;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isPhone) return _buildPhoneLayout();
    return _buildDesktopLayout();
  }

  // ─── PHONE layout: AppBar + Drawer ────────────────────────────────

  Widget _buildPhoneLayout() {
    final l10n = AppL10n.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.navAdminTitle),
        actions: [_buildLangToggle(), _buildUserMenu()],
      ),
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            Container(
              height: headerHeight,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              alignment: Alignment.centerLeft,
              child: Row(
                children: [
                  Image.asset('assets/mascot.png', width: 28, height: 28),
                  const SizedBox(width: 8),
                  Text(l10n.navAdminTitle,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                ],
              ),
            ),
            const Divider(),
            ..._buildMenuItems(menuConfig, closeDrawer: true),
          ],
        ),
      ),
      body: Container(
        color: Theme.of(context).colorScheme.surfaceContainerLowest,
        padding: const EdgeInsets.all(16),
        child: widget.child,
      ),
    );
  }

  // ─── DESKTOP / TABLET layout: sidebar + header + content ─────────

  Widget _buildDesktopLayout() {
    return Scaffold(
      body: Row(
        children: [
          _buildSidebar(),
          Expanded(
            child: Column(
              children: [
                _buildHeader(),
                Expanded(
                  child: Container(
                    color: Theme.of(context).colorScheme.surfaceContainerLowest,
                    padding: const EdgeInsets.all(16),
                    child: widget.child,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSidebar() {
    final l10n = AppL10n.of(context);
    final width = _sidebarCollapsed ? sidebarCollapsedWidth : sidebarWidth;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: width,
      color: Theme.of(context).colorScheme.surface,
      child: Column(
        children: [
          Container(
            height: headerHeight,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            alignment: Alignment.centerLeft,
            child: _sidebarCollapsed
                ? Image.asset('assets/mascot.png', width: 32, height: 32)
                : Row(
                    children: [
                      Image.asset('assets/mascot.png', width: 28, height: 28),
                      const SizedBox(width: 8),
                      Text(l10n.navAdminTitle,
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    ],
                  ),
          ),
          const Divider(),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 4),
              children: _sidebarCollapsed
                  ? _buildCollapsedItems(menuConfig)
                  : _buildMenuItems(menuConfig),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    final l10n = AppL10n.of(context);
    return Container(
      height: headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(
          bottom: BorderSide(color: Theme.of(context).dividerColor),
        ),
      ),
      child: Row(
        children: [
          IconButton(
            icon: Icon(_sidebarCollapsed ? Icons.menu_open : Icons.menu),
            tooltip: _sidebarCollapsed ? l10n.navExpandMenu : l10n.navCollapseMenu,
            onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed),
          ),
          const Spacer(),
          _buildLangToggle(),
          _buildUserMenu(),
        ],
      ),
    );
  }

  /// 语言切换（en/zh）：AppL10n.setLocale 触发全局重建。
  Widget _buildLangToggle() {
    final isZh = AppL10n.locale.languageCode == 'zh';
    return PopupMenuButton<String>(
      tooltip: 'Language',
      offset: const Offset(0, headerHeight),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Text(isZh ? '中文' : 'EN', style: const TextStyle(fontSize: 13)),
      ),
      onSelected: (code) => AppL10n.setLocale(
          code == 'zh' ? const Locale('zh', 'CN') : const Locale('en')),
      itemBuilder: (_) => const [
        PopupMenuItem(value: 'zh', child: Text('中文')),
        PopupMenuItem(value: 'en', child: Text('English')),
      ],
    );
  }

  Widget _buildUserMenu() {
    final l10n = AppL10n.of(context);
    return PopupMenuButton<String>(
      offset: const Offset(0, headerHeight),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)),
          const SizedBox(width: 8),
          Text(l10n.navAdministrator, style: const TextStyle(fontSize: 14)),
          const Icon(Icons.arrow_drop_down, size: 20),
        ],
      ),
      onSelected: (value) {
        if (value == 'profile') {
          Get.toNamed('/profile');
        } else if (value == 'logout') {
          showDialog(
            context: context,
            builder: (ctx) => AlertDialog(
              title: Text(l10n.navLogoutConfirmTitle),
              content: Text(l10n.navLogoutConfirmMessage),
              actions: [
                TextButton(onPressed: () => Navigator.pop(ctx), child: Text(l10n.commonCancel)),
                TextButton(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    await AuthService.clearToken();
                    Get.offAllNamed('/login');
                  },
                  child: Text(l10n.navLogoutConfirm, style: const TextStyle(color: Colors.red)),
                ),
              ],
            ),
          );
        }
      },
      itemBuilder: (_) => [
        PopupMenuItem(value: 'profile', child: Text(l10n.navProfile)),
        PopupMenuItem(value: 'logout', child: Text(l10n.navLogout)),
      ],
    );
  }

  // ─── Menu rendering (dynamic from menuConfig) ────────────────────

  List<Widget> _buildMenuItems(List<MenuItem> items, {bool closeDrawer = false}) {
    return [
      for (final item in items) _menuTile(item, depth: 0, closeDrawer: closeDrawer),
    ];
  }

  Widget _menuTile(MenuItem item, {required int depth, required bool closeDrawer}) {
    final children = item.children;
    if (children == null) return _leafTile(item, depth, closeDrawer);
    return Column(
      children: [
        _parentTile(item),
        if (_expanded.contains(item.label))
          for (final c in children)
            _menuTile(c, depth: depth + 1, closeDrawer: closeDrawer),
      ],
    );
  }

  Widget _parentTile(MenuItem item) {
    final expanded = _expanded.contains(item.label);
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: InkWell(
        onTap: () => _toggleExpanded(item.label),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          height: 40,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Icon(item.icon, size: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Text(_menuLabel(item),
                    style: const TextStyle(fontSize: 14),
                    overflow: TextOverflow.ellipsis),
              ),
              Icon(expanded ? Icons.expand_less : Icons.expand_more,
                  size: 18, color: scheme.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }

  Widget _leafTile(MenuItem item, int depth, bool closeDrawer) {
    final selected = _selectedRoute == item.route;
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: InkWell(
        onTap: item.route != null ? () => _goTo(item.route!, closeDrawer: closeDrawer) : null,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          height: 40,
          padding: EdgeInsets.only(left: 12.0 + depth * 20, right: 12),
          decoration: BoxDecoration(
            color: selected ? scheme.primaryContainer : null,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              Icon(item.icon,
                  size: 18, color: selected ? scheme.primary : scheme.onSurfaceVariant),
              const SizedBox(width: 12),
              Expanded(
                child: Text(_menuLabel(item),
                    style: TextStyle(
                        fontSize: 14,
                        color: selected ? scheme.primary : scheme.onSurface),
                    overflow: TextOverflow.ellipsis),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Icon-only sidebar (64px collapsed): leaves navigate, parents open a
  /// popup menu with their children.
  List<Widget> _buildCollapsedItems(List<MenuItem> items) {
    return [for (final item in items) _collapsedTile(item)];
  }

  Widget _collapsedTile(MenuItem item) {
    if (item.route != null) {
      return Tooltip(
        message: _menuLabel(item),
        child: _collapsedIconButton(item, () => _goTo(item.route!)),
      );
    }
    return Tooltip(
      message: _menuLabel(item),
      child: PopupMenuButton<String>(
        icon: Icon(item.icon, size: 20),
        onSelected: _goTo,
        itemBuilder: (_) => [
          for (final c in item.children ?? const <MenuItem>[])
            if (c.route != null) PopupMenuItem(value: c.route, child: Text(_menuLabel(c))),
        ],
      ),
    );
  }

  Widget _collapsedIconButton(MenuItem item, VoidCallback onTap) {
    final selected = _selectedRoute == item.route;
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? scheme.primaryContainer : null,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(item.icon,
              size: 20, color: selected ? scheme.primary : scheme.onSurfaceVariant),
        ),
      ),
    );
  }
}
