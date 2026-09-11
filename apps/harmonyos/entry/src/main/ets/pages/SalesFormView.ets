/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 表单视图（自 SalesOrderPage.buildForm 抽出，行为与样式不变）。
// 9 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 用法：SalesFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formCode: this.formCode, ...9 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../utils/L10n';
import { DetailCard } from '../common/DetailCard';

@Component
export struct SalesFormView {
  @Link formCode: string;
  @Link formCustomerId: string;
  @Link formQuotationId: string;
  @Link formWarehouseId: string;
  @Link formTotalAmount: string;
  @Link formDiscountAmount: string;
  @Link formStatus: number;
  @Link formOrderedAt: string;
  @Link formRemark: string;
  @Prop formMode: string = 'create';
  @Prop isSubmitting: boolean = false;
  onSubmit: () => void = () => {};
  onCancel: () => void = () => {};

  build() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'sales_form_add' : 'sales_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.sales_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_code_hint'), text: this.formCode })
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
              Text($r('app.string.sales_customer_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_customer_id_hint'), text: this.formCustomerId })
                .fontSize(15)
                // customer_id 为 hashid（可含字母）：默认键盘，勿用 Number
                .onChange((v: string) => { this.formCustomerId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_quotation_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_quotation_id_hint'), text: this.formQuotationId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formQuotationId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_warehouse_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_warehouse_id_hint'), text: this.formWarehouseId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formWarehouseId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_total_amount'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_total_amount_hint'), text: this.formTotalAmount })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formTotalAmount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_discount_amount'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_discount_amount_hint'), text: this.formDiscountAmount })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formDiscountAmount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              Row() {
                Button($r('app.string.status_enabled'))
                  .fontSize(13)
                  .fontColor(this.formStatus === 1 ? $r('app.color.text_on_primary') : $r('app.color.text_secondary'))
                  .backgroundColor(this.formStatus === 1 ? $r('app.color.success') : $r('app.color.surface_alt'))
                  .borderRadius(4)
                  .height(32)
                  .onClick(() => { this.formStatus = 1; })

                Button($r('app.string.status_disabled'))
                  .fontSize(13)
                  .fontColor(this.formStatus === 0 ? $r('app.color.text_on_primary') : $r('app.color.text_secondary'))
                  .backgroundColor(this.formStatus === 0 ? $r('app.color.danger') : $r('app.color.surface_alt'))
                  .borderRadius(4)
                  .height(32)
                  .margin({ left: 12 })
                  .onClick(() => { this.formStatus = 0; })
              }
              .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_ordered_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_ordered_at_hint'), text: this.formOrderedAt })
                .fontSize(15)
                .onChange((v: string) => { this.formOrderedAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_remark'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_remark_hint'), text: this.formRemark })
                .fontSize(15)
                .onChange((v: string) => { this.formRemark = v; })
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
