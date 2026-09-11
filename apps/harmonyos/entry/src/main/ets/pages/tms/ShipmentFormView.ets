/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 运单表单视图（自 ShipmentListPage.buildForm 抽出，行为与样式不变）。
// 12 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 用法：ShipmentFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formCode: this.formCode, ...12 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../../utils/L10n';
import { DetailCard } from '../../common/DetailCard';

@Component
export struct ShipmentFormView {
  @Link formCode: string;
  @Link formCarrierServiceId: string;
  @Link formTrackingNo: string;
  @Link formStatus: number;
  @Link formShippingLabelUrl: string;
  @Link formEstimatedDeliveryAt: string;
  @Link formActualDeliveryAt: string;
  @Link formTotalWeightKg: string;
  @Link formPackageCount: string;
  @Link formFreightCharge: string;
  @Link formInsuranceCharge: string;
  @Link formCurrency: string;
  @Prop formMode: string = 'create';
  @Prop isSubmitting: boolean = false;
  onSubmit: () => void = () => {};
  onCancel: () => void = () => {};

  build() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'shipment_form_add' : 'shipment_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.shipment_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_code_hint'), text: this.formCode })
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
              Text($r('app.string.shipment_carrier_service_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_carrier_service_id_hint'), text: this.formCarrierServiceId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formCarrierServiceId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_tracking_no'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_tracking_no_hint'), text: this.formTrackingNo })
                .fontSize(15)
                .onChange((v: string) => { this.formTrackingNo = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_status'))
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
              Text($r('app.string.shipment_shipping_label_url'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_shipping_label_url_hint'), text: this.formShippingLabelUrl })
                .fontSize(15)
                .onChange((v: string) => { this.formShippingLabelUrl = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_estimated_delivery_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_estimated_delivery_at_hint'), text: this.formEstimatedDeliveryAt })
                .fontSize(15)
                .onChange((v: string) => { this.formEstimatedDeliveryAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_actual_delivery_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_actual_delivery_at_hint'), text: this.formActualDeliveryAt })
                .fontSize(15)
                .onChange((v: string) => { this.formActualDeliveryAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_total_weight_kg'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_total_weight_kg_hint'), text: this.formTotalWeightKg })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formTotalWeightKg = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_package_count'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_package_count_hint'), text: this.formPackageCount })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formPackageCount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_freight_charge'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_freight_charge_hint'), text: this.formFreightCharge })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formFreightCharge = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_insurance_charge'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_insurance_charge_hint'), text: this.formInsuranceCharge })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formInsuranceCharge = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.shipment_currency'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.shipment_currency_hint'), text: this.formCurrency })
                .fontSize(15)
                .onChange((v: string) => { this.formCurrency = v; })
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
