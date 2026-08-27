# Tata Letak Gaya PC Multiplatform Flutter — Spesifikasi Desain

Tanggal: 2026-05-18

## Tujuan

Mengaktifkan platform desktop macOS dan Windows, memastikan semua platform iOS (iPhone + iPad), macOS, Windows, Linux menggunakan tata letak gaya panel admin PC (sidebar + topbar + area konten), dan sisi ponsel menggunakan menu drawer sebagai adaptasi.

## Strategi Platform

| Platform | Status | Keterangan |
|------|------|------|
| Linux | Sudah diaktifkan | Tidak perlu tindakan |
| macOS | Perlu diaktifkan | `flutter config --enable-macos-desktop` |
| Windows | Perlu diaktifkan | `flutter config --enable-windows-desktop` |
| iOS | Sudah ada | Mencakup iPhone (tata letak ponsel) dan iPad (tata letak desktop) |
| Web | Sudah ada | Tidak perlu tindakan |

iPad tidak memiliki target platform terpisah, tata letak desktop diperoleh melalui breakpoint responsif yang mengenai tingkat TABLET.

## Breakpoint Responsif

| Breakpoint | Rentang | Mode Tata Letak |
|------|------|----------|
| PHONE | 0 - 767 | Menu drawer (AppBar + Drawer) |
| TABLET | 768 - 1199 | Sidebar dapat dilipat (default terlipat 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (default terbuka 240px) |

Lebar minimum iPad potret 768px, mengenai TABLET, memperoleh tata letak sidebar.
Lebar iPhone semuanya kurang dari 768px, mengenai PHONE, memperoleh menu drawer.

## Perubahan File

### 1. main.dart — Konfigurasi breakpoint

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Kode lainnya tidak berubah

### 2. admin_layout.dart — Peralihan navigasi responsif

- `_isPhone`: mengenai breakpoint PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, NavigationDrawer di dalam Drawer menggunakan ulang item menu yang sama dengan sidebar desktop
- `_buildDesktopLayout()`: tata letak Row yang ada (sidebar + topbar + area konten)
- Pada TABLET sidebar default terlipat, pada DESKTOP default terbuka

### 3. app_theme.dart — Melengkapi tema gelap

- Mengekstrak gaya komponen menjadi konstanta privat `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Tema terang dan gelap menggunakan ulang satu set gaya komponen yang sama
- Tema gelap dilengkapi menggunakan Material 3 + seed yang sama + kecerahan dark
