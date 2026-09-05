/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 列表页筛选条（设计 5.2 工具栏 + 状态筛选 chips）：
// 第 1 行：搜索框（flex 1,防抖 300ms,带清空 ×）——「新建」主按钮在 TopBar 右侧槽位,不重复放。
// 第 2 行：横向滚动 chips（高 32 圆角 16,首项固定「全部」由页面保证）,行尾「共 N 条」。
// 用法：
//   FilterBar({
//     placeholder: '搜索商品名称/编码',
//     chips: [new FilterChipItem('全部', 'all'), new FilterChipItem('启用', '1')],
//     active: 'all',
//     total: 42,
//     onSearch: (kw) => {...}, onSelect: (key) => {...},
//   })
export class FilterChipItem {
  label: string = '';
  key: string = '';

  constructor(label: string, key: string) {
    this.label = label;
    this.key = key;
  }
}

@Component
export struct FilterBar {
  @Prop chips: Array<FilterChipItem> = [];
  @Prop active: string = '';
  @Prop placeholder: string = '搜索';
  @Prop total: number = -1; // >=0 时在 chips 行尾显示「共 N 条」
  onSearch?: (keyword: string) => void;
  onSelect?: (key: string) => void;

  @State query: string = '';
  private timer: number = -1;

  private emitSearch(kw: string): void {
    if (this.timer !== -1) {
      clearTimeout(this.timer);
    }
    this.timer = setTimeout(() => {
      if (this.onSearch) {
        this.onSearch(kw);
      }
    }, 300);
  }

  build() {
    Column() {
      // 搜索行
      Row() {
        TextInput({ placeholder: this.placeholder, text: this.query })
          .fontSize(14)
          .placeholderColor($r('app.color.text_hint'))
          .height(40)
          .padding({ left: 12, right: 32 })
          .backgroundColor($r('app.color.surface'))
          .borderRadius(6)
          .border({ width: 1, color: $r('app.color.border') })
          .layoutWeight(1)
          .onChange((v: string) => {
            this.query = v;
            this.emitSearch(v);
          })

        // 清空按钮（输入非空时显示）
        if (this.query.length > 0) {
          Button('×')
            .fontSize(18)
            .fontColor($r('app.color.text_hint'))
            .backgroundColor($r('app.color.surface'))
            .width(28)
            .height(28)
            .margin({ left: -30 })
            .type(ButtonType.Circle)
            .onClick(() => {
              this.query = '';
              if (this.onSearch) {
                this.onSearch('');
              }
            })
        }
      }
      .width('100%')

      // chips 行
      Row() {
        Scroll() {
          Row({ space: 8 }) {
            ForEach(this.chips, (chip: FilterChipItem) => {
              Text(chip.label)
                .fontSize(13)
                .fontColor(chip.key === this.active
                  ? $r('app.color.primary_pressed')
                  : $r('app.color.text_secondary'))
                .backgroundColor(chip.key === this.active
                  ? $r('app.color.primary_bg')
                  : $r('app.color.surface'))
                .borderRadius(16)
                .height(32)
                .padding({ left: 16, right: 16 })
                .border({ width: 1, color: chip.key === this.active
                  ? $r('app.color.primary_bg')
                  : $r('app.color.border') })
                .onClick(() => {
                  if (this.onSelect) {
                    this.onSelect(chip.key);
                  }
                })
            }, (chip: FilterChipItem) => chip.key)
          }
        }
        .scrollable(ScrollDirection.Horizontal)
        .scrollBar(BarState.Off)
        .layoutWeight(1)

        if (this.total >= 0) {
          Text(`共 ${this.total} 条`)
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
            .margin({ left: 12 })
        }
      }
      .width('100%')
      .margin({ top: 8 })
    }
    .width('100%')
  }
}
