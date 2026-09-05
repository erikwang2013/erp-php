/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 通用三态/展示组件(视觉 token 版,见 docs/mobile-ui-design.md 2.2/5.x)。
// 图标均为 rawfile 单色图,fillColor 着色;未命中映射的 icon 留空位待设计资源。
const STAT_ICON_MAP: Record<string, string> = {
  'people': 'avatar.png',
  'person_add': 'avatar.png',
};

/**
 * 统计卡片组件
 */
@Component
export struct StatCard {
  @Prop label: string = '';
  @Prop value: string = '';
  @Prop icon: string = 'people';
  @Prop color: string = '#1677FF';
  @Prop trend: string = '';
  @Prop trendUp: boolean = true;

  build() {
    Column() {
      Row() {
        if (STAT_ICON_MAP[this.icon]) {
          Image($rawfile(STAT_ICON_MAP[this.icon]))
            .width(20)
            .height(20)
            .fillColor(this.color)
        }
        // 债务：未命中映射（bolt/description 等）暂无设计资源，图标留空，待补充后加映射
      }
      .justifyContent(FlexAlign.Start)

      Blank()

      Text(this.label)
        .fontSize(12)
        .fontColor($r('app.color.text_secondary'))
        .margin({ top: 4 })

      Text(this.value)
        .fontSize(24)
        .fontWeight(FontWeight.Bold)
        .fontColor(this.color)
        .margin({ top: 2 })

      // 趋势行：▲ 升 / ▼ 降,有上期对比才显示
      if (this.trend.length > 0) {
        Text(`${this.trendUp ? '▲' : '▼'} ${this.trend}`)
          .fontSize(12)
          .fontColor(this.trendUp ? $r('app.color.success_text') : $r('app.color.danger_text'))
          .margin({ top: 4 })
      }
    }
    .width('100%')
    .height(110)
    .padding(16)
    .backgroundColor($r('app.color.surface'))
    .borderRadius(8)
    .shadow({ radius: 4, color: '#00000010', offsetY: 2 })
  }
}

/**
 * 加载状态组件（列表骨架屏接入前的过渡态；禁止整页菊花属列表三态规范，见设计文档 5.2）
 */
@Component
export struct LoadingView {
  @Prop message: string = '加载中...';

  build() {
    Column() {
      LoadingProgress()
        .width(40)
        .height(40)
        .color($r('app.color.primary'))

      Text(this.message)
        .fontSize(14)
        .fontColor($r('app.color.text_hint'))
        .margin({ top: 12 })
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .height('100%')
  }
}

/**
 * 空状态组件：图标 48 + 主文案 14/500 + 可选副文案 12（设计 5.2 空态模板）
 */
@Component
export struct EmptyView {
  @Prop message: string = '暂无数据';
  @Prop description: string = '';

  build() {
    Column() {
      Image($rawfile('empty.png'))
        .width(48)
        .height(48)
        .objectFit(ImageFit.Contain)

      Text(this.message)
        .fontSize(14)
        .fontWeight(FontWeight.Medium)
        .fontColor($r('app.color.text_secondary'))
        .margin({ top: 12 })

      if (this.description.length > 0) {
        Text(this.description)
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .margin({ top: 4 })
      }
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .height('100%')
    .padding(24)
  }
}

/**
 * 错误状态组件：文案 + 重试次按钮（高 32,主色描边；设计 5.2 错误态）
 */
@Component
export struct ErrorView {
  @Prop message: string = '加载失败';
  onRetry?: () => void;

  build() {
    Column() {
      Text(this.message)
        .fontSize(14)
        .fontColor($r('app.color.text_secondary'))
        .textAlign(TextAlign.Center)

      Button('重试')
        .fontSize(13)
        .fontWeight(FontWeight.Medium)
        .fontColor($r('app.color.primary'))
        .backgroundColor($r('app.color.surface'))
        .border({ width: 1, color: $r('app.color.primary') })
        .borderRadius(6)
        .height(32)
        .margin({ top: 12 })
        .onClick(() => {
          if (this.onRetry) {
            this.onRetry();
          }
        })
    }
    .justifyContent(FlexAlign.Center)
    .width('100%')
    .height('100%')
    .padding(24)
  }
}

/**
 * 底部版权栏
 */
@Component
export struct CopyrightBar {
  build() {
    Text('Copyright (c) 2026 erik — https://erik.xyz')
      .fontSize(12)
      .fontColor($r('app.color.text_hint'))
      .textAlign(TextAlign.Center)
      .width('100%')
      .padding({ top: 8, bottom: 8 })
  }
}
