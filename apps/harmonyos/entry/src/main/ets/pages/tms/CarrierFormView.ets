/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 表单视图（自 CarrierListPage.buildForm 抽出，行为与样式不变）。
// 8 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 用法：CarrierFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formName: this.formName, ...8 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../../utils/L10n';
import { DetailCard } from '../../common/DetailCard';

@Component
export struct CarrierFormView {
  @Link formName: string;
  @Link formCode: string;
  @Link formType: string;
  @Link formWebsite: string;
  @Link formTrackingUrlTemplate: string;
  @Link formApiProvider: string;
  @Link formContactPhone: string;
  @Link formStatus: number;
  @Prop formMode: string = 'create';
  @Prop isSubmitting: boolean = false;
  onSubmit: () => void = () => {};
  onCancel: () => void = () => {};

  build() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'carrier_form_add' : 'carrier_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.carrier_name'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_name_hint'), text: this.formName })
                .fontSize(15)
                .onChange((v: string) => { this.formName = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_code_hint'), text: this.formCode })
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
              Text($r('app.string.carrier_type'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_type_hint'), text: this.formType })
                .fontSize(15)
                .onChange((v: string) => { this.formType = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_website'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_website_hint'), text: this.formWebsite })
                .fontSize(15)
                .onChange((v: string) => { this.formWebsite = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_tracking_url_template'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_tracking_url_template_hint'), text: this.formTrackingUrlTemplate })
                .fontSize(15)
                .onChange((v: string) => { this.formTrackingUrlTemplate = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_api_provider'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_api_provider_hint'), text: this.formApiProvider })
                .fontSize(15)
                .onChange((v: string) => { this.formApiProvider = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_contact_phone'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.carrier_contact_phone_hint'), text: this.formContactPhone })
                .fontSize(15)
                .type(InputType.PhoneNumber)
                .onChange((v: string) => { this.formContactPhone = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.carrier_status'))
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
