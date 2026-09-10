// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 语义色/中性色/间距/圆角/字号 token — docs/mobile-ui-design.md §2.2/§3/§4
// 深色值为 dark 列;页面读色用 AppColors.of(context).xxx 随主题自动切换。
// 2026-09-10 「经营台账 V3」跨端统一：light 全面镜像 Web tokens.css 值
// （墨青 #0E7A6F + 暖纸面 + 三阶柔影）；dark 仅主色族/圆角跟随（V3 设计为亮色，
// dark 语义色与中性面保留既有深色变体）。
import 'package:flutter/material.dart';

abstract final class AppColors {
  static const light = AppPalette(
    primary: Color(0xFF0E7A6F),
    primaryPressed: Color(0xFF0A5A51),
    primaryDisabled: Color(0xFF9DC9C2),
    primaryBg: Color(0xFFE4F2EF),
    success: Color(0xFF2F7D3F),
    successText: Color(0xFF256633),
    successBg: Color(0xFFEAF6EC),
    warning: Color(0xFFA76B1B),
    warningText: Color(0xFF8C5715),
    warningBg: Color(0xFFFBF3E4),
    danger: Color(0xFFB02E2E),
    dangerText: Color(0xFF942525),
    dangerBg: Color(0xFFFBEAEA),
    textPrimary: Color(0xFF1F2320),
    textSecondary: Color(0xFF4B524D),
    textHint: Color(0xFF7A837C),
    textDisabled: Color(0xFFA7AEA8),
    textOnPrimary: Color(0xFFFFFFFF),
    bgPage: Color(0xFFF5F6F4),
    surface: Color(0xFFFFFFFF),
    surfaceAlt: Color(0xFFEFF1EE),
    divider: Color(0xFFE1E4E0),
    border: Color(0xFFC9CEC9),
  );
  static const dark = AppPalette(
    primary: Color(0xFF0E7A6F),
    primaryPressed: Color(0xFF4CC7B9),
    primaryDisabled: Color(0xFF2E5F58),
    primaryBg: Color(0xFF142E2B),
    success: Color(0xFF52C41A),
    successText: Color(0xFF95DE64),
    successBg: Color(0xFF25331E),
    warning: Color(0xFFFA8C16),
    warningText: Color(0xFFFFC53D),
    warningBg: Color(0xFF392C1E),
    danger: Color(0xFFFF4D4F),
    dangerText: Color(0xFFFF7875),
    dangerBg: Color(0xFF3A2425),
    textPrimary: Color(0xFFE6E6E6),
    textSecondary: Color(0xFFA6A6A6),
    textHint: Color(0xFF737373),
    textDisabled: Color(0xFF4D4D4D),
    textOnPrimary: Color(0xFFFFFFFF),
    bgPage: Color(0xFF141414),
    surface: Color(0xFF1F1F1F),
    surfaceAlt: Color(0xFF262626),
    divider: Color(0xFF2E2E2E),
    border: Color(0xFF434343),
  );

  /// 当前主题对应的调色板(页面/组件统一入口)。
  static AppPalette of(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark ? dark : light;

  /// 品牌渐变(视觉 3.0 门面专用:登录 hero/移动端品牌带;两主题一致,深底白字)。
  static const brandGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0E7A6F), Color(0xFF17A08F)],
  );
}

class AppPalette {
  const AppPalette({
    required this.primary,
    required this.primaryPressed,
    required this.primaryDisabled,
    required this.primaryBg,
    required this.success,
    required this.successText,
    required this.successBg,
    required this.warning,
    required this.warningText,
    required this.warningBg,
    required this.danger,
    required this.dangerText,
    required this.dangerBg,
    required this.textPrimary,
    required this.textSecondary,
    required this.textHint,
    required this.textDisabled,
    required this.textOnPrimary,
    required this.bgPage,
    required this.surface,
    required this.surfaceAlt,
    required this.divider,
    required this.border,
  });
  final Color primary, primaryPressed, primaryDisabled, primaryBg;
  final Color success, successText, successBg;
  final Color warning, warningText, warningBg;
  final Color danger, dangerText, dangerBg;
  final Color textPrimary, textSecondary, textHint, textDisabled;
  final Color textOnPrimary, bgPage, surface, surfaceAlt, divider, border;
}

/// 间距/圆角/控件高度 token(主文档 §4,基准网格 4px)。
/// 圆角分级(视觉 3.0):门面大卡 r14 > 数据卡 r10 > 控件 r6。
abstract final class AppMetrics {
  static const pageH = 16.0, pageHDesktop = 24.0; // 页面水平内边距
  static const gap = 12.0, gapDesktop = 16.0; // 卡片间距
  static const padCard = 12.0, padCardBody = 16.0; // 卡片内边距
  static const radiusCard = 10.0; // 数据卡(视觉 3.0:原 r12 降 r10)
  static const radiusFacade = 14.0; // 门面大卡(登录玻璃卡/hero 等)
  static const radiusControl = 6.0;
  static const radiusChip = 999.0; // 胶囊徽章/chips
  static const headBarW = 8.0, headBarH = 16.0; // 页头模块色竖条(8×16)
  static const controlH = 36.0, controlHMobile = 44.0; // 输入/按钮高
  static const rowMobile = 56.0, rowDesktop = 44.0; // 列表行高
}

/// 阴影 token(视觉 3.0):三阶柔影——卡片静息/卡片抬起/门面大卡(暖中性色)。
abstract final class AppShadows {
  /// 卡片静息(≈Web --shadow-1):1px 微投影。
  static const card = BoxShadow(
    color: Color(0x0D1C241F),
    blurRadius: 2,
    offset: Offset(0, 1),
  );

  /// 卡片抬起/hover(≈Web --shadow-2):8px 中影。
  static const cardLifted = BoxShadow(
    color: Color(0x121C241F),
    blurRadius: 8,
    offset: Offset(0, 3),
  );

  /// 门面大卡(登录/hero,≈Web --shadow-facade):30px 柔影。
  static const facadeCard = BoxShadow(
    color: Color(0x1F1C241F),
    blurRadius: 30,
    offset: Offset(0, 10),
  );
}

/// 字号 token(主文档 §3)。用法:AppText.base.copyWith(color: ...)。
abstract final class AppText {
  static const xs = TextStyle(fontSize: 11, height: 1.5);
  static const sm = TextStyle(fontSize: 12, height: 1.5);
  static const md = TextStyle(fontSize: 13, height: 1.5);
  static const base = TextStyle(fontSize: 14, height: 1.5);
  static const lg = TextStyle(
    fontSize: 16,
    height: 1.5,
    fontWeight: FontWeight.w600,
  );

  /// 内容页卡内页头标题(18/w600,视觉 3.0 页头行)。
  static const pageTitle = TextStyle(
    fontSize: 18,
    height: 1.4,
    fontWeight: FontWeight.w600,
  );
  static const xl = TextStyle(
    fontSize: 20,
    height: 1.4,
    fontWeight: FontWeight.w600,
  );

  /// 统计数值(等宽数字,桌面 24 / 移动 20 取用)。
  static const stat24 = TextStyle(
    fontSize: 24,
    height: 1.3,
    fontWeight: FontWeight.w600,
    fontFeatures: [FontFeature.tabularFigures()],
  );
  static const stat20 = TextStyle(
    fontSize: 20,
    height: 1.3,
    fontWeight: FontWeight.w600,
    fontFeatures: [FontFeature.tabularFigures()],
  );
}
