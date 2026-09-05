/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 详情分区卡（设计 5.5）：surface 卡 8 圆角，卡标题 14/600 + divider + 内容区。
// 内容由 DetailRow 字段行或任意子组件组成。
// 用法：
//   DetailCard({ title: '基本信息' }) {
//     DetailRow({ label: '单据编号', value: item.code || '-' })
//     DetailRow({ label: '状态', value: ... })
//   }
// 值为空传 '-'（hint 色）由调用方负责；桌面两列栅格待后续骨架收敛统一。

@Component
export struct DetailRow {
  @Prop label: string = '';
  @Prop value: string = '';
  @Prop valueColor: string = ''; // 可选着色（如 danger_text 语义场景），空则 text_primary

  build() {
    Column() {
      Text(this.label)
        .fontSize(12)
        .fontColor($r('app.color.text_hint'))
        .width('100%')

      Text(this.value)
        .fontSize(14)
        .fontColor(this.valueColor.length > 0
          ? this.valueColor
          : $r('app.color.text_primary'))
        .width('100%')
        .margin({ top: 4 })
    }
    .alignItems(HorizontalAlign.Start)
    .width('100%')
  }
}

@Component
export struct DetailCard {
  @Prop title: string = '';
  @BuilderParam content: () => void;

  build() {
    Column() {
      Text(this.title)
        .fontSize(14)
        .fontWeight(FontWeight.Bold)
        .fontColor($r('app.color.text_primary'))
        .width('100%')

      Divider()
        .strokeWidth(1)
        .color($r('app.color.divider'))
        .margin({ top: 8, bottom: 12 })

      Column({ space: 16 }) {
        this.content()
      }
      .alignItems(HorizontalAlign.Start)
      .width('100%')
    }
    .alignItems(HorizontalAlign.Start)
    .padding(16)
    .width('100%')
    .backgroundColor($r('app.color.surface'))
    .borderRadius(8)
  }
}
