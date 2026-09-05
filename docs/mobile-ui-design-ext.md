# open-erp UI 规范附录 — token 明细(可直接抄入两端)

配套 `docs/mobile-ui-design.md`。内容:附录 A/B = HarmonyOS color.json 完整内容(浅/深),附录 C = Flutter 令牌样板代码,附录 D = 补遗对照。

## A. HarmonyOS 浅色 `resources/base/element/color.json`(整文件替换)

```json
{
  "color": [
    { "name": "start_window_background", "value": "#FFFFFF" },
    { "name": "primary", "value": "#1677FF" },
    { "name": "primary_pressed", "value": "#0958D9" },
    { "name": "primary_disabled", "value": "#99BBFF" },
    { "name": "primary_bg", "value": "#E6F0FF" },
    { "name": "success", "value": "#52C41A" },
    { "name": "success_text", "value": "#389E0D" },
    { "name": "success_bg", "value": "#E6FFF0" },
    { "name": "warning", "value": "#FA8C16" },
    { "name": "warning_text", "value": "#D46B08" },
    { "name": "warning_bg", "value": "#FFFBE6" },
    { "name": "danger", "value": "#FF4D4F" },
    { "name": "danger_text", "value": "#CF1322" },
    { "name": "danger_bg", "value": "#FFE6E6" },
    { "name": "text_primary", "value": "#333333" },
    { "name": "text_secondary", "value": "#666666" },
    { "name": "text_hint", "value": "#999999" },
    { "name": "text_disabled", "value": "#CCCCCC" },
    { "name": "text_on_primary", "value": "#FFFFFF" },
    { "name": "bg_page", "value": "#F5F5F5" },
    { "name": "surface", "value": "#FFFFFF" },
    { "name": "surface_alt", "value": "#F0F0F0" },
    { "name": "divider", "value": "#EEEEEE" },
    { "name": "border", "value": "#D9D9D9" },
    { "name": "white", "value": "#FFFFFF" },
    { "name": "chart_1", "value": "#1677FF" },
    { "name": "chart_2", "value": "#52C41A" },
    { "name": "chart_3", "value": "#FA8C16" },
    { "name": "chart_4", "value": "#FF4D4F" },
    { "name": "chart_5", "value": "#722ED1" },
    { "name": "chart_6", "value": "#13C2C2" }
  ]
}
```

## B. HarmonyOS 深色 `resources/dark/element/color.json`(新建,同 key 覆写)

```json
{
  "color": [
    { "name": "start_window_background", "value": "#141414" },
    { "name": "primary", "value": "#1677FF" },
    { "name": "primary_pressed", "value": "#4096FF" },
    { "name": "primary_disabled", "value": "#31619B" },
    { "name": "primary_bg", "value": "#1E2A3A" },
    { "name": "success", "value": "#52C41A" },
    { "name": "success_text", "value": "#95DE64" },
    { "name": "success_bg", "value": "#25331E" },
    { "name": "warning", "value": "#FA8C16" },
    { "name": "warning_text", "value": "#FFC53D" },
    { "name": "warning_bg", "value": "#392C1E" },
    { "name": "danger", "value": "#FF4D4F" },
    { "name": "danger_text", "value": "#FF7875" },
    { "name": "danger_bg", "value": "#3A2425" },
    { "name": "text_primary", "value": "#E6E6E6" },
    { "name": "text_secondary", "value": "#A6A6A6" },
    { "name": "text_hint", "value": "#737373" },
    { "name": "text_disabled", "value": "#4D4D4D" },
    { "name": "text_on_primary", "value": "#FFFFFF" },
    { "name": "bg_page", "value": "#141414" },
    { "name": "surface", "value": "#1F1F1F" },
    { "name": "surface_alt", "value": "#262626" },
    { "name": "divider", "value": "#2E2E2E" },
    { "name": "border", "value": "#434343" },
    { "name": "white", "value": "#FFFFFF" },
    { "name": "chart_1", "value": "#1677FF" },
    { "name": "chart_2", "value": "#52C41A" },
    { "name": "chart_3", "value": "#FA8C16" },
    { "name": "chart_4", "value": "#FF4D4F" },
    { "name": "chart_5", "value": "#722ED1" },
    { "name": "chart_6", "value": "#13C2C2" }
  ]
}
```

> 资源层注意:Color 值用于 `.backgroundColor().fontColor().fillColor().borderColor().fontFeature?` 等链式;scrim 遮罩不落 color.json,页面写 `rgba(0,0,0,0.45)`(深色 0.60)但仅限遮罩一处。深色切换无需改代码,资源系统自动命中;StatCard 等组件内如有写死浅色,一并改资源引用。

## C. Flutter 令牌样板

### C.1 `lib/app/theme/app_tokens.dart`(新建)

```dart
import 'package:flutter/material.dart';

/// 语义色与中性色,key 与 docs/mobile-ui-design.md 2.2 表一致;深色值为 2.2 dark 列。
abstract final class AppColors {
  static const light = _Palette(
    primary: Color(0xFF1677FF), primaryPressed: Color(0xFF0958D9),
    primaryDisabled: Color(0xFF99BBFF), primaryBg: Color(0xFFE6F0FF),
    success: Color(0xFF52C41A), successText: Color(0xFF389E0D),
    successBg: Color(0xFFE6FFF0), warning: Color(0xFFFA8C16),
    warningText: Color(0xFFD46B08), warningBg: Color(0xFFFFFBE6),
    danger: Color(0xFFFF4D4F), dangerText: Color(0xFFCF1322),
    dangerBg: Color(0xFFFFE6E6), textPrimary: Color(0xFF333333),
    textSecondary: Color(0xFF666666), textHint: Color(0xFF999999),
    textDisabled: Color(0xFFCCCCCC), textOnPrimary: Color(0xFFFFFFFF),
    bgPage: Color(0xFFF5F5F5), surface: Color(0xFFFFFFFF),
    surfaceAlt: Color(0xFFF0F0F0), divider: Color(0xFFEEEEEE),
    border: Color(0xFFD9D9D9),
  );
  static const dark = _Palette(
    primary: Color(0xFF1677FF), primaryPressed: Color(0xFF4096FF),
    primaryDisabled: Color(0xFF31619B), primaryBg: Color(0xFF1E2A3A),
    success: Color(0xFF52C41A), successText: Color(0xFF95DE64),
    successBg: Color(0xFF25331E), warning: Color(0xFFFA8C16),
    warningText: Color(0xFFFFC53D), warningBg: Color(0xFF392C1E),
    danger: Color(0xFFFF4D4F), dangerText: Color(0xFFFF7875),
    dangerBg: Color(0xFF3A2425), textPrimary: Color(0xFFE6E6E6),
    textSecondary: Color(0xFFA6A6A6), textHint: Color(0xFF737373),
    textDisabled: Color(0xFF4D4D4D), textOnPrimary: Color(0xFFFFFFFF),
    bgPage: Color(0xFF141414), surface: Color(0xFF1F1F1F),
    surfaceAlt: Color(0xFF262626), divider: Color(0xFF2E2E2E),
    border: Color(0xFF434343),
  );
}

class _Palette {
  const _Palette({required this.primary, required this.primaryPressed,
    required this.primaryDisabled, required this.primaryBg,
    required this.success, required this.successText, required this.successBg,
    required this.warning, required this.warningText, required this.warningBg,
    required this.danger, required this.dangerText, required this.dangerBg,
    required this.textPrimary, required this.textSecondary,
    required this.textHint, required this.textDisabled,
    required this.textOnPrimary, required this.bgPage, required this.surface,
    required this.surfaceAlt, required this.divider, required this.border});
  final Color primary, primaryPressed, primaryDisabled, primaryBg;
  final Color success, successText, successBg;
  final Color warning, warningText, warningBg;
  final Color danger, dangerText, dangerBg;
  final Color textPrimary, textSecondary, textHint, textDisabled;
  final Color textOnPrimary, bgPage, surface, surfaceAlt, divider, border;
}

/// 间距/圆角/字号常量(主文档 3、4 节)。
abstract final class AppMetrics {
  static const pageH = 16.0, pageHDesktop = 24.0;      // 页面水平内边距
  static const gap = 12.0, gapDesktop = 16.0;          // 卡片间距
  static const padCard = 12.0, padCardBody = 16.0;     // 卡片内边距
  static const radiusCard = 8.0, radiusControl = 6.0;
  static const radiusChip = 16.0, radiusBadge = 4.0;
  static const controlH = 36.0, controlHMobile = 44.0; // 输入/按钮高
  static const rowMobile = 56.0, rowDesktop = 48.0;    // 列表行高
}
```

### C.2 `app_theme.dart` 接线(保留 seed,叠加 token)

```dart
// light/dark 各加:
final cs = ColorScheme.fromSeed(seedColor: const Color(0xFF1677FF),
    brightness: Brightness.light).copyWith(
  primary: c.primary, onPrimary: c.textOnPrimary,
  primaryContainer: c.primaryBg, onPrimaryContainer: c.primaryPressed,
  error: c.danger, onError: Colors.white, errorContainer: c.dangerBg,
  onErrorContainer: c.dangerText, surface: c.surface,
  onSurface: c.textPrimary, onSurfaceVariant: c.textSecondary,
  outline: c.border, outlineVariant: c.divider,
  surfaceContainerHighest: c.surfaceAlt,
);
// ThemeData(...).copyWith / 直接参数:
scaffoldBackgroundColor: c.bgPage,
cardTheme: _cardTheme.copyWith(color: c.surface, surfaceTintColor: Colors.transparent),
dataTableTheme: _dataTableTheme,      // 行高 48 保留,表头/正文色加 c.textSecondary/c.textPrimary
dividerTheme: DividerThemeData(color: c.divider, thickness: 1, space: 0),
inputDecorationTheme: _inputDecorationTheme.copyWith(
  enabledBorder: OutlineInputBorder(borderRadius: Radius6, borderSide: BorderSide(color: c.border)),
  focusedBorder: ..., // primary 1.5 宽,圆角 6
  disabledBorder: ... border c.divider, fillColor: c.surfaceAlt),
// 列表行高:列表页外层按 width<768 包一层 DataTableTheme(dataRowMin/MaxHeight: 56)
```

### C.3 文字样式(替换页面内联 TextStyle 的入口)

```dart
abstract final class AppText {
  // 用法:AppText.sm.copyWith(color: AppColors.light.textHint)
  static const xs = TextStyle(fontSize: 10, height: 1.5);
  static const sm = TextStyle(fontSize: 12, height: 1.5);
  static const md = TextStyle(fontSize: 13, height: 1.5);
  static const base = TextStyle(fontSize: 14, height: 1.5);
  static const lg = TextStyle(fontSize: 16, height: 1.5, fontWeight: FontWeight.w600);
  static const xl = TextStyle(fontSize: 20, height: 1.4, fontWeight: FontWeight.w600);
}
// 表格/统计数值加 tabularFigures:
TextStyle(fontSize: 24, fontWeight: FontWeight.w600,
    fontFeatures: const [FontFeature.tabularFigures()]);
```

## D. 补遗对照

- 深色语义浅底 token 生成式(如需调色板工具重算):`#1F1F1F 卡底 + 主色 12% 透明`;#E6F0FF 系浅色背景在深色不可用,必须换深底 token。
- Flutter dark 模式既有开关不动;`surfaceTintColor: transparent` 是 M3 表面染色消除关键一步,勿漏。
- 圆角分档:控件 6、卡片 8、chip 16、徽标 4;全仓不允许第四个近似值(如 10/12)新出现。
- HarmonyOS 旧 hex → token 全映射与次数统计见主文档 8.2.2 表;替换完成标志:pages 与 common 内 `rg -i '#[0-9a-f]{6}'` 仅剩遮罩 rgba 与占位图文件名。
- HOS 图标建议(rawfile):search/plus/edit/trash/arrow-left/arrow-right/eye/eye-off/close/chevron-down/refresh/empty-state,尺寸 16-24,fill 单色 #000 由 `.fillColor` 着色;彩色图标禁止进列表 UI。
