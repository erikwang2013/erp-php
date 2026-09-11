/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 表单视图（自 ReceivingPage.buildForm 抽出，行为与样式不变）。
// 8 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 用法：ReceivingFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formCode: this.formCode, ...8 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../../utils/L10n';
import { DetailCard } from '../../common/DetailCard';

@Component
export struct ReceivingFormView {
  @Link formCode: string;
  @Link formWarehouseId: string;
  @Link formAsnId: string;
  @Link formDockLocationId: string;
  @Link formStatus: number;
  @Link formReceiverId: string;
  @Link formReceivedAt: string;
  @Link formRemark: string;
  @Prop formMode: string = 'create';
  @Prop isSubmitting: boolean = false;
  onSubmit: () => void = () => {};
  onCancel: () => void = () => {};

  build() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'receiving_form_add' : 'receiving_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.receiving_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_code_hint'), text: this.formCode })
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
              Text($r('app.string.receiving_warehouse_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_warehouse_id_hint'), text: this.formWarehouseId })
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
              Text($r('app.string.receiving_asn_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_asn_id_hint'), text: this.formAsnId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formAsnId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_dock_location_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_dock_location_id_hint'), text: this.formDockLocationId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formDockLocationId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_status'))
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
              Text($r('app.string.receiving_receiver_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_receiver_id_hint'), text: this.formReceiverId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formReceiverId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_received_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_received_at_hint'), text: this.formReceivedAt })
                .fontSize(15)
                .onChange((v: string) => { this.formReceivedAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_remark'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_remark_hint'), text: this.formRemark })
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
