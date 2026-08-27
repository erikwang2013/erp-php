# Layout multiplataforma estilo PC en Flutter — Especificación de diseño

Fecha: 2026-05-18

## Objetivo

Habilitar las plataformas de escritorio macOS y Windows, garantizando que todas las plataformas — iOS (iPhone + iPad), macOS, Windows, Linux — usen el layout de panel de administración estilo PC (barra lateral + barra superior + área de contenido), y que el móvil use menú de cajón (drawer) como adaptación.

## Estrategia de plataformas

| Plataforma | Estado | Descripción |
|------|------|------|
| Linux | Habilitada | Sin acciones necesarias |
| macOS | Por habilitar | `flutter config --enable-macos-desktop` |
| Windows | Por habilitar | `flutter config --enable-windows-desktop` |
| iOS | Ya existe | Cubre tanto iPhone (layout móvil) como iPad (layout de escritorio) |
| Web | Ya existe | Sin acciones necesarias |

El iPad no tiene un objetivo de plataforma independiente; logra el layout de escritorio alcanzando el tramo TABLET mediante los breakpoints responsivos.

## Breakpoints responsivos

| Breakpoint | Rango | Modo de layout |
|------|------|----------|
| PHONE | 0 - 767 | Menú de cajón (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barra lateral plegable (plegada por defecto, 64px) |
| DESKTOP | 1200 - 2460 | Barra lateral (expandida por defecto, 240px) |

El ancho mínimo del iPad en vertical es 768px, por lo que alcanza TABLET y obtiene el layout de barra lateral.
El iPhone siempre es menor de 768px, por lo que alcanza PHONE y obtiene el menú de cajón.

## Cambios de archivos

### 1. main.dart — Configuración de breakpoints

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- El resto del código no cambia

### 2. admin_layout.dart — Conmutación de navegación responsiva

- `_isPhone`: alcanza el breakpoint PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; el NavigationDrawer dentro del Drawer reutiliza los mismos ítems de menú que la barra lateral de escritorio
- `_buildDesktopLayout()`: el layout Row existente (barra lateral + barra superior + área de contenido)
- En TABLET la barra lateral está plegada por defecto; en DESKTOP, expandida por defecto

### 3. app_theme.dart — Completar el tema oscuro

- Extraer los estilos de componentes como constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Los temas claro y oscuro reutilizan el mismo conjunto de estilos de componentes
- El tema oscuro se completa usando Material 3 + el mismo seed + luminosidad dark
