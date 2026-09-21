/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 表单视图（自 PurchaseOrderPage.buildForm 抽出，行为与样式不变）。
// 7 个表单字段用 @Link 双向绑定父页状态；保存/取消走回调，业务逻辑仍留在父页。
// 单号（code）不设录入项：由后端 doc_code() 生成（PO+雪花号），列表/详情可见。
// 用法：PurchaseOrderFormView({ formMode: this.formMode, isSubmitting: this.isSubmitting,
//   formSupplierId: this.formSupplierId, ...7 项..., onSubmit: () => { this.save(); },
//   onCancel: () => { this.showForm = false; } })

import { L10n } from '../utils/L10n';
import { DetailCard } from '../common/DetailCard';

/** 订单状态码（与 install.sql 列注释 / 后端 0..4 校验一致），文案取 purchase_status_{code} */
const PURCHASE_STATUS_CODES: Array<number> = [0, 1, 2, 3, 4];

@Component
export struct PurchaseOrderFormView {
  @Link formSupplierId: string;
  @Link formApplyId: string;
  @Link formWarehouseId: string;
  @Link formTotalAmount: string;
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
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'purchase_form_add' : 'purchase_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.purchase_supplier_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_supplier_id_hint'), text: this.formSupplierId })
                .fontSize(15)
                // supplier_id 为 hashid（可含字母）：默认键盘，勿用 Number
                .onChange((v: string) => { this.formSupplierId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.purchase_apply_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_apply_id_hint'), text: this.formApplyId })
                .fontSize(15)
                // apply_id 是 hashid（可含字母）：默认键盘
                .onChange((v: string) => { this.formApplyId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.purchase_warehouse_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_warehouse_id_hint'), text: this.formWarehouseId })
                .fontSize(15)
                // warehouse_id 是 hashid（可含字母）：默认键盘
                .onChange((v: string) => { this.formWarehouseId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.purchase_total_amount'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_total_amount_hint'), text: this.formTotalAmount })
                .fontSize(15)
                // 金额列是 DECIMAL(12,2)：NUMBER_DECIMAL 键盘可输小数点（Number 键盘无小数点）
                .type(InputType.NUMBER_DECIMAL)
                .onChange((v: string) => { this.formTotalAmount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.purchase_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              // 订单状态是 5 档业务状态（0待审核/1已审核/2部分收货/3已收货/4已取消，
              // 见 install.sql 列注释），不是用户模块的「启用/禁用」1/0：
              // 原先复用启用/禁用按钮 → 新建恒写 status=1=已审核，未审即记已审
              Flex({ wrap: FlexWrap.Wrap }) {
                ForEach(PURCHASE_STATUS_CODES, (code: number) => {
                  Button(L10n.str(this.getUIContext(), `purchase_status_${code}`))
                    .fontSize(13)
                    .fontColor(this.formStatus === code ? $r('app.color.text_on_primary') : $r('app.color.text_secondary'))
                    .backgroundColor(this.formStatus === code ? $r('app.color.primary') : $r('app.color.surface_alt'))
                    .borderRadius(4)
                    .height(32)
                    .margin({ left: 8, top: 4, bottom: 4 })
                    .onClick(() => { this.formStatus = code; })
                }, (code: number) => code.toString())
              }
              .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.purchase_ordered_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_ordered_at_hint'), text: this.formOrderedAt })
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
              Text($r('app.string.purchase_remark'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.purchase_remark_hint'), text: this.formRemark })
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
