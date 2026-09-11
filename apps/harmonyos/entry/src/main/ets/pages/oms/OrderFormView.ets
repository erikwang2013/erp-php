/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 订单表单视图（自 OrderListPage.buildForm 抽出，行为与样式不变）。
// 13 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 用法：OrderFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formCode: this.formCode, ...13 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../../utils/L10n';
import { DetailCard } from '../../common/DetailCard';

@Component
export struct OrderFormView {
  @Link formCode: string;
  @Link formOrderId: string;
  @Link formChannel: string;
  @Link formChannelOrderNo: string;
  @Link formChannelStore: string;
  @Link formFulfillmentStatus: string;
  @Link formPaymentStatus: string;
  @Link formShippingMethod: string;
  @Link formShippingFee: string;
  @Link formBuyerMessage: string;
  @Link formSellerNote: string;
  @Link formPriority: string;
  @Link formHoldUntil: string;
  @Prop formMode: string = 'create';
  @Prop isSubmitting: boolean = false;
  onSubmit: () => void = () => {};
  onCancel: () => void = () => {};

  build() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'order_form_add' : 'order_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.order_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_code_hint'), text: this.formCode })
                .fontSize(15)
                .onChange((v: string) => { this.formCode = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_sales_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_sales_id_hint'), text: this.formOrderId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formOrderId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_hint'), text: this.formChannel })
                .fontSize(15)
                .onChange((v: string) => { this.formChannel = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel_no'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_no_hint'), text: this.formChannelOrderNo })
                .fontSize(15)
                .onChange((v: string) => { this.formChannelOrderNo = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel_store'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_store_hint'), text: this.formChannelStore })
                .fontSize(15)
                .onChange((v: string) => { this.formChannelStore = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_ff_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_range_0to3_hint'), text: this.formFulfillmentStatus })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formFulfillmentStatus = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_payment_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_range_0to3_hint'), text: this.formPaymentStatus })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formPaymentStatus = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_shipping_method'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_shipping_method_hint'), text: this.formShippingMethod })
                .fontSize(15)
                .onChange((v: string) => { this.formShippingMethod = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_shipping_fee'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_shipping_fee_hint'), text: this.formShippingFee })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formShippingFee = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_buyer_note'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_buyer_note_hint'), text: this.formBuyerMessage })
                .fontSize(15)
                .onChange((v: string) => { this.formBuyerMessage = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_seller_note'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_seller_note_hint'), text: this.formSellerNote })
                .fontSize(15)
                .onChange((v: string) => { this.formSellerNote = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_priority'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_priority_hint'), text: this.formPriority })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formPriority = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_hold_until'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_hold_until_hint'), text: this.formHoldUntil })
                .fontSize(15)
                .onChange((v: string) => { this.formHoldUntil = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .alignItems(VerticalAlign.Center)

          }
          .width('100%')

          // 操作按钮
          Row() {
            Button($r('app.string.cancel'))
              .layoutWeight(1)
              .height(44)
              .fontSize(15)
              .fontColor($r('app.color.text_secondary'))
              .backgroundColor($r('app.color.surface_alt'))
              .borderRadius(8)
              .onClick(() => { this.onCancel(); })

            Button($r('app.string.save'))
              .layoutWeight(1)
              .height(44)
              .fontSize(15)
              .fontColor($r('app.color.text_on_primary'))
              .backgroundColor(this.isSubmitting ? $r('app.color.primary_disabled') : $r('app.color.primary'))
              .borderRadius(8)
              .margin({ left: 16 })
              .enabled(!this.isSubmitting)
              .onClick(() => { this.onSubmit(); })
          }
          .width('100%')
          .margin({ top: 8 })
        }

      }
      .width('100%')
      .padding({ left: 12, right: 12, top: 12, bottom: 12 })
    }
    .width('100%')
    .layoutWeight(1)
    .backgroundColor($r('app.color.bg_page'))
  }
}
