// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 页面转场(视觉 2.0 动效基线):250ms 淡入 + 4px 上移。
// Get 路由体系统一走 GetPageRoute;在 GetPage 上挂 customTransition +
// transitionDuration 250ms 即全局生效(main.dart getPages 注册处)。
// 位移用高度占比(0.008≈640px 高下 5px),避免依赖像素高。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class FadeUpPageTransition extends CustomTransition {
  FadeUpPageTransition();

  @override
  Widget buildTransition(
    BuildContext context,
    Curve? curve,
    Alignment? alignment,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    // 减少动态效果(无障碍):直接给原始子树,不做淡入/位移。
    if (MediaQuery.disableAnimationsOf(context)) return child;
    final curved = curve == null
        ? animation
        : CurvedAnimation(
            parent: animation,
            curve: curve,
            reverseCurve: Curves.easeInCubic,
          );
    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.008),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}
