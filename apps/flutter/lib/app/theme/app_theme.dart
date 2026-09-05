// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// M3 主题接线:seed 0xFF1677FF + ColorScheme.copyWith 语义槽(附录 C.2),
// 组件主题对齐 docs/mobile-ui-design.md §5;深色同规则。
import 'package:flutter/material.dart';
import 'app_tokens.dart';

ThemeData _build(Brightness brightness) {
  final c = brightness == Brightness.light ? AppColors.light : AppColors.dark;
  final scheme = ColorScheme.fromSeed(
    seedColor: const Color(0xFF1677FF),
    brightness: brightness,
  ).copyWith(
    primary: c.primary, onPrimary: c.textOnPrimary,
    primaryContainer: c.primaryBg, onPrimaryContainer: c.primaryPressed,
    error: c.danger, onError: c.textOnPrimary,
    errorContainer: c.dangerBg, onErrorContainer: c.dangerText,
    surface: c.surface, onSurface: c.textPrimary,
    onSurfaceVariant: c.textSecondary,
    outline: c.border, outlineVariant: c.divider,
    surfaceContainerHighest: c.surfaceAlt,
  );

  // 主按钮:primary 底白字,禁用 primaryDisabled 白字,按压 primaryPressed(§5.6)
  final primaryBtn = ButtonStyle(
    minimumSize: const WidgetStatePropertyAll(Size(64, AppMetrics.controlH)),
    textStyle: const WidgetStatePropertyAll(
        TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
    foregroundColor: WidgetStatePropertyAll(c.textOnPrimary),
    backgroundColor: WidgetStateProperty.resolveWith((s) {
      if (s.contains(WidgetState.disabled)) return c.primaryDisabled;
      if (s.contains(WidgetState.pressed)) return c.primaryPressed;
      return c.primary;
    }),
    shape: WidgetStatePropertyAll(RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppMetrics.radiusControl))),
  );

  // 次按钮:白底 border 描边,按压 surface_alt(§5.6)
  final secondaryBtn = ButtonStyle(
    minimumSize: const WidgetStatePropertyAll(Size(64, AppMetrics.controlH)),
    textStyle: const WidgetStatePropertyAll(
        TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
    foregroundColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.disabled)
            ? c.textDisabled
            : c.textPrimary),
    backgroundColor: const WidgetStatePropertyAll(Colors.transparent),
    side: WidgetStateProperty.resolveWith((s) =>
        BorderSide(color: s.contains(WidgetState.disabled) ? c.divider : c.border)),
    overlayColor: WidgetStatePropertyAll(c.surfaceAlt),
    shape: WidgetStatePropertyAll(RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppMetrics.radiusControl))),
  );

  // 文字按钮/行内链接:热区 ≥32,默认主蓝
  final textBtn = ButtonStyle(
    minimumSize: const WidgetStatePropertyAll(Size(48, 32)),
    padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 8)),
    textStyle: const WidgetStatePropertyAll(
        TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
    foregroundColor: WidgetStateProperty.resolveWith(
        (s) => s.contains(WidgetState.disabled) ? c.textDisabled : c.primary),
    shape: WidgetStatePropertyAll(RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppMetrics.radiusControl))),
  );

  final radius6 = BorderRadius.circular(AppMetrics.radiusControl);
  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: c.bgPage,
    focusColor: c.primary,
    dividerColor: c.divider,

    appBarTheme: AppBarThemeData(
      backgroundColor: c.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: AppText.xl.copyWith(color: c.textPrimary),
      iconTheme: IconThemeData(color: c.textPrimary),
    ),

    // 卡片/弹窗:surface 实底,消除 M3 surfaceTint 染色(elevation 1 / r8)
    cardTheme: CardThemeData(
      elevation: 1,
      color: c.surface,
      surfaceTintColor: Colors.transparent,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppMetrics.radiusCard)),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: c.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppMetrics.radiusCard)),
      titleTextStyle: AppText.lg.copyWith(color: c.textPrimary),
      contentTextStyle: AppText.base.copyWith(color: c.textPrimary),
    ),

    // 表格:桌面行 48/表头 40,表头字 secondary(移动行高 56 由 wrapper 断点覆盖)
    dataTableTheme: DataTableThemeData(
      dataRowMinHeight: AppMetrics.rowDesktop,
      dataRowMaxHeight: AppMetrics.rowDesktop,
      headingRowHeight: 40,
      headingTextStyle: TextStyle(
          fontSize: 13, fontWeight: FontWeight.w600, color: c.textSecondary),
      dataTextStyle: TextStyle(fontSize: 13, color: c.textPrimary),
    ),

    // 输入/选择器:r6 描边,聚焦主色 1.5,禁用填 surface_alt(§5.6/§4)
    inputDecorationTheme: InputDecorationTheme(
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      hintStyle: TextStyle(fontSize: 13, color: c.textHint),
      labelStyle: TextStyle(fontSize: 14, color: c.textSecondary),
      errorStyle: TextStyle(fontSize: 12, color: c.dangerText),
      prefixIconColor: c.textHint,
      border: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.border)),
      enabledBorder: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.border)),
      focusedBorder: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.primary, width: 1.5)),
      errorBorder: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.danger)),
      focusedErrorBorder: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.danger, width: 1.5)),
      disabledBorder: OutlineInputBorder(
          borderRadius: radius6, borderSide: BorderSide(color: c.divider)),
    ),

    // 状态筛选 chip:胶囊 16,未选白底描边 / 选中 primary_bg + pressed 色字(§5.2)
    chipTheme: ChipThemeData(
      backgroundColor: c.surface,
      selectedColor: c.primaryBg,
      checkmarkColor: c.primaryPressed,
      disabledColor: c.bgPage,
      side: BorderSide(color: c.border),
      labelStyle: TextStyle(fontSize: 13, color: c.textSecondary),
      secondaryLabelStyle: TextStyle(
          fontSize: 13, color: c.primaryPressed, fontWeight: FontWeight.w500),
      shape: StadiumBorder(side: BorderSide(color: c.border)),
    ),

    elevatedButtonTheme: ElevatedButtonThemeData(style: primaryBtn),
    filledButtonTheme: FilledButtonThemeData(style: primaryBtn),
    outlinedButtonTheme: OutlinedButtonThemeData(style: secondaryBtn),
    textButtonTheme: TextButtonThemeData(style: textBtn),

    dividerTheme: DividerThemeData(color: c.divider, thickness: 1, space: 0),

    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.surface,
      surfaceTintColor: Colors.transparent,
      indicatorColor: c.primaryBg,
    ),
    navigationRailTheme: NavigationRailThemeData(
      backgroundColor: c.surface,
      indicatorColor: c.primaryBg,
      selectedIconTheme: IconThemeData(color: c.primaryPressed),
      unselectedIconTheme: IconThemeData(color: c.textSecondary),
    ),
  );
}

class AppTheme {
  static final ThemeData light = _build(Brightness.light);
  static final ThemeData dark = _build(Brightness.dark);
}
