/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { UserService } from '../service/DataService';
import { L10n } from '../utils/L10n';
import { User } from '../model/DataModels';
import { LoadingView, ErrorView } from '../common/Components';
import { TopBar, TopBarAction } from '../common/TopBar';
import { ConfirmDialog } from '../common/FormDialog';

@Entry
@Component
struct UserDetailPage {
  @State user: User | null = null;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
  @State isSubmitting: boolean = false;
  @State mode: string = 'view';
  @State userId: string = '';

  // 表单状态
  @State formUsername: string = '';
  @State formRealName: string = '';
  @State formPhone: string = '';
  @State formEmail: string = '';
  @State formPassword: string = '';
  @State formStatus: number = 1;

  aboutToAppear(): void {
    const params = (router.getParams() ?? {}) as Record<string, Object>;
    this.mode = (params?.['mode'] as string) ?? 'view';
    this.userId = (params?.['userId'] as string) ?? '';

    if (this.mode !== 'create') {
      this.loadDetail();
    } else {
      this.isLoading = false;
    }
  }

  async loadDetail(): Promise<void> {
    this.isLoading = true;
    try {
      this.user = await UserService.getDetail(this.userId);
      this.formUsername = this.user.username;
      this.formRealName = this.user.real_name;
      this.formPhone = this.user.phone ?? '';
      this.formEmail = this.user.email ?? '';
      this.formStatus = this.user.status;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errMsg = e instanceof Error && e.message ? e.message : L10n.str(this.getUIContext(), 'load_failed');
      this.hasError = true;
    } finally {
      this.isLoading = false;
    }
  }

  async save(): Promise<void> {
    if (!this.formUsername.trim()) {
      promptAction.showToast({ message: $r('app.string.user_username_required') });
      return;
    }
    if (this.mode === 'create' && !this.formPassword) {
      promptAction.showToast({ message: $r('app.string.user_password_required') });
      return;
    }

    this.isSubmitting = true;
    try {
      if (this.mode === 'create') {
        await UserService.create({
          username: this.formUsername,
          real_name: this.formRealName,
          password: this.formPassword,
          phone: this.formPhone,
          email: this.formEmail,
          status: this.formStatus
        });
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await UserService.update(this.userId, {
          real_name: this.formRealName,
          phone: this.formPhone,
          email: this.formEmail,
          status: this.formStatus
        });
        promptAction.showToast({ message: $r('app.string.update_success') });
      }
      router.back();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: L10n.str(this.getUIContext(), 'save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  private dialogController?: CustomDialogController;

  /**
   * 删除入口：顶栏删除钮先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.user_delete_msg'),
        onConfirm: () => {
          this.deleteUser();
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  /**
   * 删除：DELETE 用户后返回列表
   */
  async deleteUser(): Promise<void> {
    try {
      await UserService.delete(this.userId);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      router.back();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: $r('app.string.delete_failed') });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: this.mode === 'create' ? $r('app.string.user_form_add') : $r('app.string.user_detail_title'),
        actions: this.mode !== 'create' ? [new TopBarAction($r('app.string.delete'), 'danger')] : [],
        onAction: () => this.confirmDelete(),
        // 详情/编辑态可刷新重载服务端详情；新增态（纯表单）无远端数据，注入仅守卫态回调
        onRefresh: () => {
          if (this.mode !== 'create') {
            this.loadDetail();
          }
        },
        refreshing: this.isLoading,
      })

      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ message: this.errMsg, onRetry: () => this.loadDetail() });
      } else {
        Scroll() {
          Column() {
            // 头像
            Circle({ width: 72, height: 72 })
              .fill($r('app.color.primary_bg'))
              .margin({ top: 24, bottom: 24 })

            // 表单
            Row() {
              Text($r('app.string.user_username'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.user_username_hint'), text: this.formUsername })
                .fontSize(15)
                .enabled(this.mode === 'create')
                .onChange((v: string) => { this.formUsername = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .height(56)
            .padding({ left: 16, right: 16 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            if (this.mode === 'create') {
              Row() {
                Text($r('app.string.user_password'))
                .fontSize(15)
                .fontColor($r('app.color.text_primary'))
                .width(80)

                TextInput({ placeholder: $r('app.string.user_password_hint'), text: this.formPassword })
                  .fontSize(15)
                  .type(InputType.Password)
                  .onChange((v: string) => { this.formPassword = v; })
                  .layoutWeight(1)
              }
              .width('100%')
              .height(56)
              .padding({ left: 16, right: 16 })
              .backgroundColor($r('app.color.surface'))
              .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
              .alignItems(VerticalAlign.Center)
            }

            Row() {
              Text($r('app.string.user_name'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.user_name_hint'), text: this.formRealName })
                .fontSize(15)
                .onChange((v: string) => { this.formRealName = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .height(56)
            .padding({ left: 16, right: 16 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.user_phone'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.user_phone_hint'), text: this.formPhone })
                .fontSize(15)
                .type(InputType.PhoneNumber)
                .onChange((v: string) => { this.formPhone = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .height(56)
            .padding({ left: 16, right: 16 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.user_email'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.user_email_hint'), text: this.formEmail })
                .fontSize(15)
                .type(InputType.Email)
                .onChange((v: string) => { this.formEmail = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .height(56)
            .padding({ left: 16, right: 16 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.user_status'))
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
            .height(56)
            .padding({ left: 16, right: 16 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            // 提交按钮
            Button($r('app.string.save'))
              .width('100%')
              .height(48)
              .fontSize(16)
              .fontColor($r('app.color.text_on_primary'))
              .backgroundColor(this.isSubmitting ? $r('app.color.primary_disabled') : $r('app.color.primary'))
              .borderRadius(8)
              .margin({ top: 32, left: 16, right: 16, bottom: 32 })
              .enabled(!this.isSubmitting)
              .onClick(() => { this.save(); })
          }
          .width('100%')
        }
        .width('100%')
        .layoutWeight(1)
        .backgroundColor($r('app.color.bg_page'))
      }
    }
    .width('100%')
    .height('100%')
  }
}
