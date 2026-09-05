// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 语义色/中性色/间距/圆角/字号 token — docs/mobile-ui-design.md §2.2/§3/§4
// 深色值为 dark 列;页面读色用 AppColors.of(context).xxx 随主题自动切换。
import 'package:flutter/material.dart';

abstract final class AppColors {
  static const light = AppPalette(
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
  static const dark = AppPalette(
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

  /// 当前主题对应的调色板(页面/组件统一入口)。
  static AppPalette of(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark ? dark : light;
}

class AppPalette {
  const AppPalette({
    required this.primary, required this.primaryPressed,
    required this.primaryDisabled, required this.primaryBg,
    required this.success, required this.successText, required this.successBg,
    required this.warning, required this.warningText, required this.warningBg,
    required this.danger, required this.dangerText, required this.dangerBg,
    required this.textPrimary, required this.textSecondary,
    required this.textHint, required this.textDisabled,
    required this.textOnPrimary, required this.bgPage, required this.surface,
    required this.surfaceAlt, required this.divider, required this.border,
  });
  final Color primary, primaryPressed, primaryDisabled, primaryBg;
  final Color success, successText, successBg;
  final Color warning, warningText, warningBg;
  final Color danger, dangerText, dangerBg;
  final Color textPrimary, textSecondary, textHint, textDisabled;
  final Color textOnPrimary, bgPage, surface, surfaceAlt, divider, border;
}

/// 间距/圆角/控件高度 token(主文档 §4,基准网格 4px)。
abstract final class AppMetrics {
  static const pageH = 16.0, pageHDesktop = 24.0;   // 页面水平内边距
  static const gap = 12.0, gapDesktop = 16.0;       // 卡片间距
  static const padCard = 12.0, padCardBody = 16.0;  // 卡片内边距
  static const radiusCard = 8.0, radiusControl = 6.0;
  static const radiusChip = 16.0, radiusBadge = 4.0;
  static const controlH = 36.0, controlHMobile = 44.0; // 输入/按钮高
  static const rowMobile = 56.0, rowDesktop = 48.0;    // 列表行高
}

/// 字号 token(主文档 §3)。用法:AppText.base.copyWith(color: ...)。
abstract final class AppText {
  static const xs = TextStyle(fontSize: 10, height: 1.5);
  static const sm = TextStyle(fontSize: 12, height: 1.5);
  static const md = TextStyle(fontSize: 13, height: 1.5);
  static const base = TextStyle(fontSize: 14, height: 1.5);
  static const lg = TextStyle(fontSize: 16, height: 1.5, fontWeight: FontWeight.w600);
  static const xl = TextStyle(fontSize: 20, height: 1.4, fontWeight: FontWeight.w600);

  /// 统计数值(等宽数字,桌面 24 / 移动 20 取用)。
  static const stat24 = TextStyle(
    fontSize: 24, height: 1.3, fontWeight: FontWeight.w600,
    fontFeatures: [FontFeature.tabularFigures()],
  );
  static const stat20 = TextStyle(
    fontSize: 20, height: 1.3, fontWeight: FontWeight.w600,
    fontFeatures: [FontFeature.tabularFigures()],
  );
}
