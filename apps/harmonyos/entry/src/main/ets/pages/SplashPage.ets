/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 启动页 — 冷启动 loading 状态页
 *
 * EntryAbility.onWindowStageCreate 先加载本页，随后异步调用 TokenManager.init
 * 从 Preferences（磁盘）恢复登录态，再跳转 Dashboard / Login，
 * 避免冷启动时闪登录页。
 */
@Entry
@Component
struct SplashPage {
  build() {
    Column() {
      Blank()

      Text('开放管理后台')
        .fontSize(24)
        .fontWeight(FontWeight.Bold)
        .fontColor('#1677FF')

      Text('HarmonyOS 客户端')
        .fontSize(13)
        .fontColor('#999999')
        .margin({ top: 6 })

      LoadingProgress()
        .width(40)
        .height(40)
        .color('#1677FF')
        .margin({ top: 32 })

      Text('正在恢复登录状态...')
        .fontSize(12)
        .fontColor('#999999')
        .margin({ top: 12 })

      Blank()

      Text('Copyright (c) 2026 erik — https://erik.xyz')
        .fontSize(10)
        .fontColor('#CCCCCC')
        .margin({ bottom: 20 })
    }
    .width('100%')
    .height('100%')
    .justifyContent(FlexAlign.Center)
    .backgroundColor('#FFFFFF')
  }
}
