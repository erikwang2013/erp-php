# Flutter Multiplattform-PC-Layout — Designspezifikation

Datum: 2026-05-18

## Ziel

Die Desktop-Plattformen macOS und Windows aktivieren, sicherstellen, dass alle Plattformen — iOS (iPhone + iPad), macOS, Windows, Linux — das PC-Verwaltungsstil-Layout (Seitenleiste + Kopfleiste + Inhaltsbereich) verwenden, auf Mobilgeräten ein Drawer-Menü zur Anpassung nutzen.

## Plattformstrategie

| Plattform | Status | Erläuterung |
|------|------|------|
| Linux | bereits aktiviert | keine Aktion nötig |
| macOS | muss aktiviert werden | `flutter config --enable-macos-desktop` |
| Windows | muss aktiviert werden | `flutter config --enable-windows-desktop` |
| iOS | bereits vorhanden | deckt zugleich iPhone (Mobil-Layout) und iPad (Desktop-Layout) ab |
| Web | bereits vorhanden | keine Aktion nötig |

Das iPad hat kein eigenes Plattformziel, es erreicht das Desktop-Layout über den responsiven Breakpoint TABLET.

## Responsive Breakpoints

| Breakpoint | Bereich | Layout-Modus |
|------|------|----------|
| PHONE | 0 - 767 | Drawer-Menü (AppBar + Drawer) |
| TABLET | 768 - 1199 | einklappbare Seitenleiste (Standard eingeklappt 64px) |
| DESKTOP | 1200 - 2460 | Seitenleiste (Standard ausgeklappt 240px) |

Minimale iPad-Hochformatbreite ist 768px, trifft TABLET, erhält das Seitenleisten-Layout.
Die iPhone-Breite liegt immer unter 768px, trifft PHONE, erhält das Drawer-Menü.

## Dateiänderungen

### 1. main.dart — Breakpoint-Konfiguration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- übriger Code unverändert

### 2. admin_layout.dart — responsiver Navigationswechsel

- `_isPhone`: trifft den PHONE-Breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, im Drawer nutzt NavigationDrawer dieselben Menüpunkte wie die Desktop-Seitenleiste
- `_buildDesktopLayout()`: vorhandenes Row-Layout (Seitenleiste + Kopfleiste + Inhaltsbereich)
- Unter TABLET ist die Seitenleiste standardmäßig eingeklappt, unter DESKTOP standardmäßig ausgeklappt

### 3. app_theme.dart — Dark-Theme vervollständigen

- Komponentenstile als private Konstanten extrahieren: `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Helles und dunkles Theme verwenden denselben Satz Komponentenstile
- Dark-Theme ergänzen mit Material 3 + gleichem seed + dark-Brightness
