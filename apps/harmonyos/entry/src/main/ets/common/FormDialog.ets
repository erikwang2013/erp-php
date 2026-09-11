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
  label: ResourceStr = '';
  type: string = 'text'; // 'text' | 'number' | 'password' | 'select'
  required: boolean = false;
  options: Array<ResourceStr> = [];
  placeholder: ResourceStr = '';
  readonly: boolean = false; // 编辑态只读：输入框禁用、下拉不弹选（值仍随 initial 展示）
  optionValues: Array<string> = []; // select 原值表（与 options 下标对齐；缺省=选中值落解析文本，兼容旧语义）

  constructor(key: string, label: ResourceStr, type: string = 'text',
    required: boolean = false, options: Array<ResourceStr> = [], placeholder: ResourceStr = '',
    readonly: boolean = false, optionValues: Array<string> = []) {
    this.key = key;
    this.label = label;
    this.type = type;
    this.required = required;
    this.options = options;
    // placeholder 未显式传入时留空（旧「请输入」拼接属未资源化时期遗留；
    // 字段级全句 hint 资源化后调用方显式传参，本回退已无消费方）
    this.placeholder = placeholder || '';
    this.readonly = readonly;
    this.optionValues = optionValues;
  }
}

/**
 * 危险操作确认弹窗（设计 5.7）：标题 16/600 + 正文 14 次要色 + 取消 / 危险实心主操作。
 * needPassword 通道：onConfirmPwd 返回 Promise，成功才 close；422 等提交级错误由本弹窗
 * toast 后端 message 并保留弹窗（密码留在框内，可改了重试）——不再静默关闭丢输入。
 * 其余通道（onConfirm 同步）仍按 5.4 由页面在 close 后 toast 反馈。
 */
@CustomDialog
export struct ConfirmDialog {
  @Prop title: ResourceStr = '';
  @Prop message: ResourceStr = '';
  @Prop confirmText: ResourceStr = $r('app.string.delete');
  @Prop cancelText: ResourceStr = $r('app.string.cancel');
  @Prop kind: string = 'danger'; // 'danger' 实心危险主操作 | 'primary' 主色
  @Prop needPassword: boolean = false; // 敏感删除的密码二次确认（后端 422 校验）
  @State pwd: string = '';
  @State submitting: boolean = false; // 密码确认往返中（防重复提交；失败复位可重试）
  onConfirm?: () => void;
  onConfirmPwd?: (password: string) => Promise<void>;
  onCancel?: () => void;
  controller?: CustomDialogController;

  private close(): void {
    if (this.controller) {
      this.controller.close();
    }
  }

  /** needPassword 通道：成功才 close；失败 toast 后端 message 并保留弹窗待重试 */
  private async runConfirmPwd(): Promise<void> {
    try {
      if (this.onConfirmPwd) {
        await this.onConfirmPwd(this.pwd);
      }
      this.close();
    } catch (e) {
      this.submitting = false;
      let msg: ResourceStr = $r('app.string.delete_failed');
      if (e instanceof Error && e.message.length > 0 && e.message !== 'UNAUTHORIZED') {
        msg = e.message;
      }
      promptAction.showToast({ message: msg });
    }
  }

  /** 确认分支：needPassword 时空密码内联提示，不提交 */
  private submit(): void {
    if (this.needPassword) {
      if (this.pwd.length === 0) {
        promptAction.showToast({ message: $r('app.string.confirm_password_required') });
        return;
      }
      if (this.onConfirmPwd) {
        this.submitting = true;
        this.runConfirmPwd();
        return;
      }
    } else if (this.onConfirm) {
      this.onConfirm();
    }
    this.close();
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

      if (this.needPassword) {
        TextInput({ placeholder: $r('app.string.confirm_password_hint'), text: this.pwd })
          .fontSize(14)
          .type(InputType.Password)
          .fontColor($r('app.color.text_primary'))
          .placeholderColor($r('app.color.text_hint'))
          .height(40)
          .width('100%')
          .backgroundColor($r('app.color.surface_alt'))
          .borderRadius(6)
          .padding({ left: 12, right: 12 })
          .margin({ top: 12 })
          .onChange((v: string) => { this.pwd = v; })
      }

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
          .enabled(!this.submitting)
          .onClick(() => {
            this.submit();
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
  @Prop title: ResourceStr = '';
  @Prop fields: Array<FormField> = [];
  @Prop submitText: ResourceStr = $r('app.string.save');
  @Prop cancelText: ResourceStr = $r('app.string.cancel');
  @Prop initial: Array<string> = [];
  onConfirm?: (values: Array<string>) => void;
  onCancel?: () => void;
  controller?: CustomDialogController;

  @State values: Array<string> = [];
  @State missing: Array<boolean> = [];
  @State submitting: boolean = false;
  // 可选内容插槽（字段区下方随整体滚动渲染，如角色弹框的权限树选区）；
  // 惰性组件语义：调用方仅当需要时传 withSlot=true + slotContent
  @Prop withSlot: boolean = false;
  @BuilderParam slotContent: () => void = this.slotEmpty;

  @Builder
  slotEmpty() {
    Text('')
  }

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
    if (!field || field.options.length === 0 || field.readonly) {
      return;
    }
    // 现代 ActionMenu API：buttons 定长元组 ≤6 + Promise（旧 sheets/responseCallback 为 @ohos.prompt 遗留形态）
    const menuButtons: Array<promptAction.Button> = [];
    for (let i = 0; i < field.options.length && menuButtons.length < 6; i++) {
      menuButtons.push({ text: field.options[i], color: $r('app.color.text_primary') });
    }
    promptAction.showActionMenu({
      title: field.label,
      buttons: menuButtons as [promptAction.Button, promptAction.Button?, promptAction.Button?,
        promptAction.Button?, promptAction.Button?, promptAction.Button?],
    }).then((ret: promptAction.ActionMenuSuccessResponse) => {
      const idx: number = ret.index;
      if (idx >= 0 && idx < field.options.length) {
        this.setValue(index, this.selectRaw(field, idx));
      }
    }).catch(() => {
      // 取消菜单等失败路径不处理
    });
  }

  /** select 选中项落值：声明了 optionValues 存原值，否则回退解析文本（旧语义） */
  private selectRaw(field: FormField, idx: number): string {
    if (idx < field.optionValues.length) {
      return String(field.optionValues[idx]);
    }
    return this.optionText(field.options[idx]);
  }

  /** 渲染值：select 存原值时换算回选项展示文案；其余类型原样 */
  private displayValue(index: number): string {
    const raw = index < this.values.length ? this.values[index] : '';
    if (index < this.fields.length && this.fields[index].type === 'select') {
      const vi = this.fields[index].optionValues.indexOf(raw);
      if (vi >= 0 && vi < this.fields[index].options.length) {
        return this.optionText(this.fields[index].options[vi]);
      }
    }
    return raw;
  }

  /** 选项选中值落 values 前转纯字符串（选项为 $r 资源时按当前语言解析，兜底空串） */
  private optionText(option: ResourceStr): string {
    if (typeof option === 'string') {
      return option;
    }
    try {
      const rm = this.getUIContext().getHostContext()?.resourceManager;
      if (rm) {
        return rm.getStringSync(option);
      }
    } catch (err) {
      // 资源解析失败：回退空串，不崩
    }
    return '';
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
                // select 用禁用输入框纯展示 + 透明覆盖层拾取（禁键盘不弹起），
                // 只读态不加覆盖层（值仍展示、不可再选）
                Stack() {
                  TextInput({ placeholder: field.placeholder, text: this.displayValue(index) })
                    .fontSize(14)
                    .fontColor($r('app.color.text_primary'))
                    .placeholderColor($r('app.color.text_hint'))
                    .height(44)
                    .width('100%')
                    .backgroundColor($r('app.color.surface'))
                    .borderRadius(6)
                    .border({ width: 1, color: $r('app.color.border') })
                    .enabled(false)
                  if (!field.readonly) {
                    Row() {
                      Text('▾')
                        .fontSize(12)
                        .fontColor($r('app.color.text_hint'))
                    }
                    .width('100%')
                    .height(44)
                    .padding({ right: 12 })
                    .justifyContent(FlexAlign.End)
                    .onClick(() => {
                      this.pickSelect(index);
                    })
                  }
                }
                .width('100%')
                .margin({ top: 6 })
              } else {
                TextInput({
                  placeholder: field.placeholder,
                  text: this.values[index],
                })
                  .type(field.type === 'number' ? InputType.Number :
                    (field.type === 'password' ? InputType.Password : InputType.Normal))
                  .fontSize(14)
                  .fontColor($r('app.color.text_primary'))
                  .placeholderColor($r('app.color.text_hint'))
                  .height(44)
                  .backgroundColor($r('app.color.surface'))
                  .borderRadius(6)
                  .border({ width: 1, color: $r('app.color.border') })
                  .margin({ top: 6 })
                  .enabled(!field.readonly)
                  .onChange((v: string) => {
                    this.setValue(index, v);
                  })
              }

              if (this.missing[index]) {
                Text($r('app.string.form_required'))
                  .fontSize(12)
                  .fontColor($r('app.color.danger_text'))
                  .width('100%')
                  .margin({ top: 4 })
              }
            }
            .width('100%')
            .alignItems(HorizontalAlign.Start)
          }, (field: FormField) => field.key)

          if (this.withSlot) {
            Column() {
              this.slotContent()
            }
            .width('100%')
            .margin({ top: 8 })
          }
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
