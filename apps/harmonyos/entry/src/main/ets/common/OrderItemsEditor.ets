/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 单据明细行编辑器（订单录入路径）：录入 items[]，调用方保存时过滤空行后下发。
// 后端 OrderController::buildItems 负责 product_id 解码（hashid 双模）与量程校验，
// 主表金额恒以明细汇总为准，故前端不重复收 total_amount。
//
// 为什么用 @Observed + @ObjectLink：@State/@Link 只观测数组增减与顶层赋值，不观测元素内部
// 字段；直接改 item.quantity 不触发刷新，输入框会被旧值顶回（逐字符输入即丢字）。
//
// 用法（父级）：
//   @State formItems: Array<OrderItemDraft> = [new OrderItemDraft()];
//   OrderItemsEditor({ items: this.formItems })

@Observed
export class OrderItemDraft {
  product_id: string = '';
  quantity: string = '';
  price: string = '';
  unit: string = '';
}

/** 过滤掉 product_id 为空的占位行（用户点了「添加明细」但没填） */
export function nonEmptyItems(rows: Array<OrderItemDraft>): Array<OrderItemDraft> {
  return rows.filter((r: OrderItemDraft) => r.product_id.trim().length > 0);
}

@Component
struct OrderItemRowView {
  @ObjectLink item: OrderItemDraft;
  canRemove: boolean = false;
  onRemove: () => void = () => {};

  build() {
    Column() {
      TextInput({ placeholder: $r('app.string.order_item_product_id_hint'), text: this.item.product_id })
        .fontSize(15)
        // 商品 ID 是 hashid（可含字母）：默认键盘，勿用 Number
        .onChange((v: string) => { this.item.product_id = v; })
        .width('100%')
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })

      Row() {
        TextInput({ placeholder: $r('app.string.order_item_qty'), text: this.item.quantity })
          .fontSize(15)
          // 数量/单价落 DECIMAL(12,2)：Number 键盘，非法串由后端 validator 挡回 422
          .type(InputType.Number)
          .layoutWeight(1)
          .onChange((v: string) => { this.item.quantity = v; })

        TextInput({ placeholder: $r('app.string.order_item_price'), text: this.item.price })
          .fontSize(15)
          .type(InputType.Number)
          .layoutWeight(1)
          .onChange((v: string) => { this.item.price = v; })

        TextInput({ placeholder: $r('app.string.order_item_unit'), text: this.item.unit })
          .fontSize(15)
          .layoutWeight(1)
          .onChange((v: string) => { this.item.unit = v; })

        if (this.canRemove) {
          Text($r('app.string.order_item_remove'))
            .fontSize(13)
            .fontColor($r('app.color.danger_text'))
            .onClick(() => { this.onRemove(); })
        }
      }
      .width('100%')
      .alignItems(VerticalAlign.Center)
    }
    .width('100%')
    .padding({ top: 4, bottom: 4 })
  }
}

@Component
export struct OrderItemsEditor {
  @Link items: Array<OrderItemDraft>;

  build() {
    Column() {
      Text($r('app.string.order_items_title'))
        .fontSize(14)
        .fontWeight(FontWeight.Bold)
        .fontColor($r('app.color.text_primary'))
        .width('100%')
        .margin({ bottom: 4 })

      // 首行不可删：删空后编辑器一片空白，用户以为控件坏了
      ForEach(this.items, (it: OrderItemDraft, idx: number) => {
        OrderItemRowView({
          item: it,
          canRemove: this.items.length > 1,
          onRemove: () => { this.items.splice(idx, 1); }
        })
      }, (it: OrderItemDraft, idx: number) => `${idx}`)

      Text($r('app.string.order_item_add'))
        .fontSize(14)
        .fontColor($r('app.color.primary'))
        .width('100%')
        .onClick(() => { this.items.push(new OrderItemDraft()); })
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
  }
}
