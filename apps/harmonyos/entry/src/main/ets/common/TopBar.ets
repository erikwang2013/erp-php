/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 页面级顶部导航栏（设计 5.1）：左返回 40×40 热区(可选) + 标题 20/600 单行截断 +
// 右操作位至多 2 个(主按钮 + 危险钮)。高 56,纯 surface 底。
// 用法：
//   TopBar({ title: '商品管理' })                                    // 仅返回+标题
//   TopBar({ title: '商品管理', actions: [new TopBarAction('+ 新增', 'primary')] })
//   .onAction? -> 见 onAction 回调：
//   TopBar({ title: 'x', actions: [...], onAction: (i) => { ... } })

/** 右上操作按钮描述（label + kind: primary 主按钮 / danger 危险浅底钮） */
export class TopBarAction {
  label: string = '';
  kind: string = 'primary'; // 'primary' | 'danger'

  constructor(label: string, kind: string = 'primary') {
    this.label = label;
    this.kind = kind;
  }
}

@Component
export struct TopBar {
  @Prop title: string = '';
  @Prop back: boolean = true;
  @Prop actions: Array<TopBarAction> = [];
  onBack?: () => void;
  onAction?: (index: number) => void;

  build() {
    Row() {
      if (this.back) {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(20).height(20)
        }
        .width(40)
        .height(40)
        .backgroundColor($r('app.color.surface'))
        .onClick(() => {
          if (this.onBack) {
            this.onBack();
          } else {
            // 未注入回调时回退到路由返回（与旧页手写行一致）
            const router = this.getUIContext().getRouter();
            router.back();
          }
        })
      }

      Text(this.title)
        .fontSize(20)
        .fontWeight(FontWeight.Bold)
        .fontColor($r('app.color.text_primary'))
        .maxLines(1)
        .textOverflow({ overflow: TextOverflow.Ellipsis })
        .margin({ left: this.back ? 8 : 0 })

      Blank()

      ForEach(this.actions, (action: TopBarAction, index: number) => {
        if (action.kind === 'danger') {
          Button(action.label)
            .fontSize(13)
            .fontColor($r('app.color.danger_text'))
            .backgroundColor($r('app.color.danger_bg'))
            .borderRadius(6)
            .height(32)
            .margin({ left: 8 })
            .onClick(() => {
              if (this.onAction) {
                this.onAction(index);
              }
            })
        } else {
          Button(action.label)
            .fontSize(14)
            .fontWeight(FontWeight.Medium)
            .fontColor($r('app.color.text_on_primary'))
            .backgroundColor($r('app.color.primary'))
            .borderRadius(6)
            .height(36)
            .margin({ left: 8 })
            .onClick(() => {
              if (this.onAction) {
                this.onAction(index);
              }
            })
        }
      }, (action: TopBarAction, index: number) => `${index}-${action.label}`)
    }
    .width('100%')
    .height(56)
    .padding({ left: 16, right: 16 })
    .backgroundColor($r('app.color.surface'))
  }
}
