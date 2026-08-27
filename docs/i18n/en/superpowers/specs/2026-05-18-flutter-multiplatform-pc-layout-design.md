# Flutter Multi-platform PC-style Layout — Design Spec

Date: 2026-05-18

## Goal

Enable the macOS and Windows desktop platforms, ensuring that iOS (iPhone + iPad), macOS, Windows, and Linux all use the PC admin-panel style layout (sidebar + top bar + content area), with a drawer menu adaptation for phones.

## Platform Strategy

| Platform | Status | Notes |
|----------|--------|-------|
| Linux | Enabled | No action needed |
| macOS | Needs enabling | `flutter config --enable-macos-desktop` |
| Windows | Needs enabling | `flutter config --enable-windows-desktop` |
| iOS | Already exists | Covers both iPhone (phone layout) and iPad (desktop layout) |
| Web | Already exists | No action needed |

The iPad has no dedicated platform target; it hits the TABLET breakpoint through responsive breakpoints to get the desktop layout.

## Responsive Breakpoints

| Breakpoint | Range | Layout Mode |
|------------|-------|-------------|
| PHONE | 0 - 767 | Drawer menu (AppBar + Drawer) |
| TABLET | 768 - 1199 | Collapsible sidebar (default collapsed 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (default expanded 240px) |

The iPad's minimum portrait width is 768px, which hits TABLET and gets the sidebar layout.
All iPhone widths are below 768px, which hit PHONE and get the drawer menu.

## File Changes

### 1. main.dart — Breakpoint configuration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- All other code unchanged

### 2. admin_layout.dart — Responsive navigation switching

- `_isPhone`: hits the PHONE breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, with the NavigationDrawer inside the Drawer reusing the same menu items as the desktop sidebar
- `_buildDesktopLayout()`: existing Row layout (sidebar + top bar + content area)
- The sidebar defaults to collapsed on TABLET and expanded on DESKTOP

### 3. app_theme.dart — Complete dark theme

- Extract component styles into private constants `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Light and dark themes reuse the same component styles
- The dark theme adds Material 3 with the same seed and dark brightness
