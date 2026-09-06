/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 启动页 — 冷启动 loading 状态页
 *
 * EntryAbility.onWindowStageCreate 先加载本页，随后异步调用 TokenManager.init
 * 从 Preferences（磁盘）恢复登录态，再跳转 Dashboard / Login，
 * 避免冷启动时闪登录页。
 *
 * 视觉 2.0：品牌渐变底 + mascot 淡入上浮 + 品牌字（文字恒白，随渐变底）。
 */
import {
  BRAND_DEEP,
  BRAND_MID,
  BRAND_LIGHT,
  ON_GRADIENT,
  ON_GRADIENT_SUB,
  ON_GRADIENT_FAINT
} from '../common/ThemeTokens';

@Entry
@Component
struct SplashPage {
  @State mascotAlpha: number = 0;
  @State mascotScale: number = 0.9;
  @State mascotDy: number = 16;
  @State textAlpha: number = 0;

  aboutToAppear(): void {
    // mascot 淡入+上浮（约 350ms），品牌字同组淡入
    setTimeout(() => {
      this.getUIContext().animateTo({ duration: 350, curve: Curve.EaseOut }, () => {
        this.mascotAlpha = 1;
        this.mascotScale = 1;
        this.mascotDy = 0;
        this.textAlpha = 1;
      });
    }, 30);
  }

  build() {
    Column() {
      Blank()

      Image($r('app.media.mascot'))
        .width(120)
        .height(120)
        .opacity(this.mascotAlpha)
        .scale({ x: this.mascotScale, y: this.mascotScale })
        .translate({ y: this.mascotDy })
        .margin({ bottom: 16 })

      Text('开放管理后台')
        .fontSize(24)
        .fontWeight(FontWeight.Bold)
        .fontColor(ON_GRADIENT)
        .opacity(this.textAlpha)

      Text('HarmonyOS 客户端')
        .fontSize(13)
        .fontColor(ON_GRADIENT_SUB)
        .margin({ top: 6 })
        .opacity(this.textAlpha)

      LoadingProgress()
        .width(40)
        .height(40)
        .color(ON_GRADIENT)
        .margin({ top: 32 })

      Text('正在恢复登录状态...')
        .fontSize(12)
        .fontColor(ON_GRADIENT_FAINT)
        .margin({ top: 12 })

      Blank()

      Text('Copyright (c) 2026 erik — https://erik.xyz')
        .fontSize(10)
        .fontColor(ON_GRADIENT_FAINT)
        .margin({ bottom: 20 })
    }
    .width('100%')
    .height('100%')
    .justifyContent(FlexAlign.Center)
    .linearGradient({ angle: 180, colors: [[BRAND_DEEP, 0], [BRAND_MID, 0.58], [BRAND_LIGHT, 1]] })
  }

  pageTransition() {
    PageTransitionEnter({ duration: 250, curve: Curve.EaseOut })
      .opacity(0)
    PageTransitionExit({ duration: 250, curve: Curve.EaseIn })
      .opacity(0)
  }
}
