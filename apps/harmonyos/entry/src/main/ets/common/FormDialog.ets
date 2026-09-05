/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 表单弹窗 + 危险操作确认弹窗（设计 5.4 / 5.7），@CustomDialog 形态，调用方持
// controller 每次打开前重建（保证内部状态干净，与 CaptchaVerifyDialog 同模式）。
//
// ConfirmDialog 用法：
//   this.dialogController = new CustomDialogController({
//     builder: ConfirmDialog({
//       title: '删除商品',
//       message: `删除后不可恢复，确定删除「${item.name}」？`,
//       onConfirm: () => { this.deleteItem(item.id); },
//     }),
//     alignment: DialogAlignment.Center,
//   });
//   this.dialogController.open();
//
// FormDialog 用法（onConfirm 返回收集到的各字段值，页面自行 await/close；
// 服务端失败可带 initial 原值重建弹窗）：
//   const f = [new FormField('name', '商品名称', 'text', true),
//              new FormField('status', '状态', 'select', false, ['启用', '停用'])];
//   this.dialogController = new CustomDialogController({
//     builder: FormDialog({
//       title: '新增商品', fields: f, submitText: '保存',
//       onConfirm: (vals: Array<string>) => { this.submitForm(vals); },
//     }),
//   });
//   this.dialogController.open();
//   // 移动端底部抽屉式：controller 构造加 alignment: DialogAlignment.Bottom
//   // + offsetY 适配,或保持 Center（本组件已做 92% 宽圆角 16 弹卡样式）。

import promptAction from '@ohos.promptAction';

/** 表单字段描述（value 收集在 onConfirm 的 Array<string> 参数中，与 fields 下标对齐） */
export class FormField {
  key: string = '';
  label: string = '';
  type: string = 'text'; // 'text' | 'number' | 'password' | 'select'
  required: boolean = false;
  options: Array<string> = [];
  placeholder: string = '';

  constructor(key: string, label: string, type: string = 'text',
    required: boolean = false, options: Array<string> = [], placeholder: string = '') {
    this.key = key;
    this.label = label;
    this.type = type;
    this.required = required;
    this.options = options;
    this.placeholder = placeholder || `请输入${label}`;
  }
}

/**
 * 危险操作确认弹窗（设计 5.7）：标题 16/600 + 正文 14 次要色 + 取消 / 危险实心主操作。
 * 提交级错误按 5.4 由页面在 close 后用 toast 反馈（对话框状态不跨次提交保留）。
 */
@CustomDialog
export struct ConfirmDialog {
  @Prop title: string = '';
  @Prop message: string = '';
  @Prop confirmText: string = '删除';
  @Prop cancelText: string = '取消';
  @Prop kind: string = 'danger'; // 'danger' 实心危险主操作 | 'primary' 主色
  onConfirm?: () => void;
  onCancel?: () => void;
  controller?: CustomDialogController;

  private close(): void {
    if (this.controller) {
      this.controller.close();
    }
  }

  build() {
    Column() {
      Row() {
        Text(this.title)
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .fontColor($r('app.color.text_primary'))
          .layoutWeight(1)

        Button('✕')
          .fontSize(16)
          .fontColor($r('app.color.text_hint'))
          .backgroundColor($r('app.color.surface'))
          .width(40)
          .height(40)
          .type(ButtonType.Circle)
          .onClick(() => {
            if (this.onCancel) {
              this.onCancel();
            }
            this.close();
          })
      }
      .width('100%')

      Text(this.message)
        .fontSize(14)
        .fontColor($r('app.color.text_secondary'))
        .lineHeight(21)
        .width('100%')
        .margin({ top: 12 })

      Row() {
        Button(this.cancelText)
          .fontSize(14)
          .fontColor($r('app.color.text_primary'))
          .backgroundColor($r('app.color.surface'))
          .border({ width: 1, color: $r('app.color.border') })
          .borderRadius(6)
          .height(40)
          .layoutWeight(1)
          .onClick(() => {
            if (this.onCancel) {
              this.onCancel();
            }
            this.close();
          })

        Button(this.confirmText)
          .fontSize(14)
          .fontWeight(FontWeight.Medium)
          .fontColor($r('app.color.text_on_primary'))
          .backgroundColor(this.kind === 'primary' ? $r('app.color.primary') : $r('app.color.danger'))
          .borderRadius(6)
          .height(40)
          .layoutWeight(1)
          .margin({ left: 12 })
          .onClick(() => {
            if (this.onConfirm) {
              this.onConfirm();
            }
            this.close();
          })
      }
      .width('100%')
      .margin({ top: 20 })
    }
    .padding(20)
    .width('92%')
    .backgroundColor($r('app.color.surface'))
    .borderRadius(16)
  }
}

/**
 * 表单弹窗骨架（设计 5.4）：标题行 + 字段区 + 底部操作条（取消 → 提交）。
 * 字段由 FormField 数组驱动；必填未填时确认按钮内联提示不提交。
 */
@CustomDialog
export struct FormDialog {
  @Prop title: string = '';
  @Prop fields: Array<FormField> = [];
  @Prop submitText: string = '保存';
  @Prop cancelText: string = '取消';
  @Prop initial: Array<string> = [];
  onConfirm?: (values: Array<string>) => void;
  onCancel?: () => void;
  controller?: CustomDialogController;

  @State values: Array<string> = [];
  @State missing: Array<boolean> = [];
  @State submitting: boolean = false;

  aboutToAppear(): void {
    this.resetValues();
  }

  private resetValues(): void {
    this.values = [];
    this.missing = [];
    for (let i = 0; i < this.fields.length; i++) {
      this.values.push(i < this.initial.length ? this.initial[i] : '');
      this.missing.push(false);
    }
  }

  private setValue(index: number, v: string): void {
    if (index >= this.values.length) {
      return;
    }
    this.values[index] = v;
    this.missing[index] = false;
  }

  private validate(): boolean {
    let allOk = true;
    const next: Array<boolean> = [];
    for (let i = 0; i < this.fields.length; i++) {
      const empty = this.fields[i].required && this.values[i].length === 0;
      next.push(empty);
      if (empty) {
        allOk = false;
      }
    }
    this.missing = next;
    return allOk;
  }

  private pickSelect(index: number): void {
    const field = this.fields[index];
    if (!field || field.options.length === 0) {
      return;
    }
    const sheets = field.options.map((opt: string) => ({ title: opt }));
    promptAction.showActionMenu({
      title: field.label,
      sheets: sheets,
      responseCallback: (ret) => {
        const idx = ret.index;
        if (idx >= 0 && idx < field.options.length) {
          this.setValue(index, field.options[idx]);
        }
      },
    });
  }

  build() {
    Column() {
      Row() {
        Text(this.title)
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .fontColor($r('app.color.text_primary'))
          .layoutWeight(1)

        Button('✕')
          .fontSize(16)
          .fontColor($r('app.color.text_hint'))
          .backgroundColor($r('app.color.surface'))
          .width(40)
          .height(40)
          .type(ButtonType.Circle)
          .onClick(() => {
            if (this.onCancel) {
              this.onCancel();
            }
            if (this.controller) {
              this.controller.close();
            }
          })
      }
      .width('100%')
      .margin({ bottom: 16 })

      // 字段区（内部滚动）
      Scroll() {
        Column({ space: 16 }) {
          ForEach(this.fields, (field: FormField, index: number) => {
            Column() {
              Row() {
                Text(field.label)
                  .fontSize(14)
                  .fontWeight(FontWeight.Medium)
                  .fontColor($r('app.color.text_primary'))

                if (field.required) {
                  Text('*')
                    .fontSize(14)
                    .fontColor($r('app.color.danger_text'))
                    .margin({ left: 2 })
                }
              }
              .width('100%')

              if (field.type === 'select') {
                TextInput({ placeholder: field.placeholder, text: this.values[index] })
                  .fontSize(14)
                  .fontColor($r('app.color.text_primary'))
                  .placeholderColor($r('app.color.text_hint'))
                  .height(44)
                  .backgroundColor($r('app.color.surface'))
                  .borderRadius(6)
                  .border({ width: 1, color: $r('app.color.border') })
                  .margin({ top: 6 })
                  .onClick(() => {
                    this.pickSelect(index);
                  })
              } else {
                TextInput({
                  placeholder: field.placeholder,
                  text: this.values[index],
                  type: field.type === 'number' ? InputType.Number :
                    (field.type === 'password' ? InputType.Password : InputType.Normal),
                })
                  .fontSize(14)
                  .fontColor($r('app.color.text_primary'))
                  .placeholderColor($r('app.color.text_hint'))
                  .height(44)
                  .backgroundColor($r('app.color.surface'))
                  .borderRadius(6)
                  .border({ width: 1, color: $r('app.color.border') })
                  .margin({ top: 6 })
                  .onChange((v: string) => {
                    this.setValue(index, v);
                  })
              }

              if (this.missing[index]) {
                Text('此项为必填')
                  .fontSize(12)
                  .fontColor($r('app.color.danger_text'))
                  .width('100%')
                  .margin({ top: 4 })
              }
            }
            .width('100%')
            .alignItems(HorizontalAlign.Start)
          }, (field: FormField) => field.key)
        }
        .width('100%')
      }
      .layoutWeight(1)
      .scrollBar(BarState.Off)

      // 底部操作条
      Row() {
        Button(this.cancelText)
          .fontSize(14)
          .fontColor($r('app.color.text_primary'))
          .backgroundColor($r('app.color.surface'))
          .border({ width: 1, color: $r('app.color.border') })
          .borderRadius(6)
          .height(44)
          .layoutWeight(1)
          .enabled(!this.submitting)
          .onClick(() => {
            if (this.onCancel) {
              this.onCancel();
            }
            if (this.controller) {
              this.controller.close();
            }
          })

        Button(this.submitText)
          .fontSize(14)
          .fontWeight(FontWeight.Medium)
          .fontColor($r('app.color.text_on_primary'))
          .backgroundColor(this.submitting
            ? $r('app.color.primary_disabled')
            : $r('app.color.primary'))
          .borderRadius(6)
          .height(44)
          .layoutWeight(1)
          .margin({ left: 12 })
          .enabled(!this.submitting)
          .onClick(() => {
            if (!this.validate()) {
              return; // 必填内联提示
            }
            this.submitting = true; // 提交中禁用防重复提交
            if (this.onConfirm) {
              this.onConfirm(this.values.slice());
            }
          })
      }
      .width('100%')
      .margin({ top: 16 })
    }
    .padding(20)
    .width('92%')
    .height('85%')
    .backgroundColor($r('app.color.surface'))
    .borderRadius(16)
  }
}
