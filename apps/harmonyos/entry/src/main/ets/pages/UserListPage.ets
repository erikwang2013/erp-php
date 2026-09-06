/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { UserService } from '../service/DataService';
import { User, UserCreateForm, UserUpdateForm } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { TopBar, TopBarAction } from '../common/TopBar';
import { ConfirmDialog, FormDialog, FormField } from '../common/FormDialog';
import { L10n } from '../utils/L10n';

/**
 * 用户管理：列表 + 新建/编辑弹框（FormDialog）。
 * 原 UserDetailPage 整页形态已并入：用户名仅新建可填（编辑只读）、
 * 密码仅新建必填 / 编辑留空=不改（后端 has&&!empty 才改密）、状态下拉。
 * 删除经 ConfirmDialog needPassword（后端 422 校验，顺带修复旧版无条件删的缺陷）。
 */
@Entry
@Component
struct UserListPage {
  @State users: User[] = [];
  @State page: number = 1;
  @State total: number = 0;
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
  @State keyword: string = '';
  @State isRefreshing: boolean = false;
  private readonly limit: number = 15;

  // 表单弹框状态
  @State formMode: string = 'create'; // create | edit
  @State editUserId: string = '';

  private formDialogController?: CustomDialogController;
  private deleteDialogController?: CustomDialogController;

  aboutToAppear(): void {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false): Promise<void> {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.isLoading = true;
    }
    this.hasError = false;

    try {
      const result = await UserService.getList({
        page: this.page,
        limit: this.limit,
        keyword: this.keyword || undefined
      });
      if (isRefresh || this.page === 1) {
        this.users = result.list;
      } else {
        this.users = this.users.concat(result.list);
      }
      this.total = result.total;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errMsg = e instanceof Error && e.message ? e.message :
        L10n.str(this.getUIContext(), 'load_failed');
      this.hasError = true;
      promptAction.showToast({ message: this.errMsg });
    } finally {
      this.isLoading = false;
      this.isRefreshing = false;
    }
  }

  loadMore(): void {
    if (this.users.length >= this.total) return;
    this.page++;
    this.loadData();
  }

  onSearch(): void {
    this.page = 1;
    this.loadData(true);
  }

  /** 新建表单（用户名/密码均必填） */
  openCreate(): void {
    this.formMode = 'create';
    this.editUserId = '';
    this.openUserDialog(['', '', '', '', '', '1']);
  }

  /**
   * 编辑弹框入口：先拉服务端详情（列表行不保证含全字段），
   * 成功再以详情值开框（原 UserDetailPage 同源行为）
   */
  async openEdit(user: User): Promise<void> {
    this.isLoading = true;
    try {
      const detail = await UserService.getDetail(user.id);
      this.formMode = 'edit';
      this.editUserId = detail.id;
      this.openUserDialog([
        detail.username,
        '', // 密码：编辑留空=不改
        detail.real_name ?? '',
        detail.phone ?? '',
        detail.email ?? '',
        String(detail.status)
      ]);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: (e instanceof Error && e.message)
        ? e.message : L10n.str(this.getUIContext(), 'load_failed') });
    } finally {
      this.isLoading = false;
    }
  }

  private userFormFields(mode: string): Array<FormField> {
    const isEdit = mode === 'edit';
    // 六行字段与旧 UserDetailPage 表单一致
    const fields: Array<FormField> = [];
    fields.push(new FormField('username', $r('app.string.user_username'), 'text', true,
      [], $r('app.string.user_username_hint'), isEdit));
    if (isEdit) {
      fields.push(new FormField('password', $r('app.string.user_password'), 'password', false,
        [], $r('app.string.user_password_edit_hint')));
    } else {
      fields.push(new FormField('password', $r('app.string.user_password'), 'password', true,
        [], $r('app.string.user_password_hint')));
    }
    fields.push(new FormField('real_name', $r('app.string.user_name'), 'text', false,
      [], $r('app.string.user_name_hint')));
    fields.push(new FormField('phone', $r('app.string.user_phone'), 'text', false,
      [], $r('app.string.user_phone_hint')));
    fields.push(new FormField('email', $r('app.string.user_email'), 'text', false,
      [], $r('app.string.user_email_hint')));
    fields.push(new FormField('status', $r('app.string.user_status'), 'select', false,
      [$r('app.string.status_enabled'), $r('app.string.status_disabled')], '',
      false, ['1', '0']));
    return fields;
  }

  private openUserDialog(initial: Array<string>): void {
    const mode = this.formMode;
    this.formDialogController = new CustomDialogController({
      builder: FormDialog({
        title: mode === 'create' ? $r('app.string.user_form_add') : $r('app.string.user_form_edit'),
        fields: this.userFormFields(mode),
        initial: initial,
        onCancel: (): void => {
          this.formDialogController?.close();
        },
        onConfirm: (vals: Array<string>): void => {
          this.submitUser(vals);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: false,
    });
    this.formDialogController.open();
  }

  async submitUser(vals: Array<string>): Promise<void> {
    const username = (vals[0] ?? '').trim();
    const password = vals[1] ?? '';
    const realName = vals[2] ?? '';
    const phone = vals[3] ?? '';
    const email = vals[4] ?? '';
    const status = vals[5] === '1' ? 1 : 0;
    const isEdit = this.formMode === 'edit';

    try {
      if (isEdit) {
        const body: UserUpdateForm = {
          real_name: realName,
          phone: phone,
          email: email,
          status: status
        };
        if (password.length > 0) {
          body.password = password;
        }
        await UserService.update(this.editUserId, body);
        promptAction.showToast({ message: $r('app.string.update_success') });
      } else {
        const body: UserCreateForm = {
          username: username,
          real_name: realName,
          password: password,
          phone: phone,
          email: email,
          status: status
        };
        await UserService.create(body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      }
      this.formDialogController?.close();
      this.onSearch();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: (e instanceof Error && e.message)
        ? e.message : L10n.str(this.getUIContext(), 'save_failed') });
      // 失败保留输入：关掉后带原值重建（FormDialog 无跨实例值回写通道）
      this.formDialogController?.close();
      setTimeout(() => {
        this.openUserDialog(vals);
      }, 300);
    }
  }

  confirmDelete(user: User): void {
    this.deleteDialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.user_delete_msg'),
        needPassword: true,
        confirmText: $r('app.string.delete'),
        onConfirmPwd: (pwd: string) => {
          this.deleteUser(user.id, pwd);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.deleteDialogController.open();
  }

  async deleteUser(id: string, password: string): Promise<void> {
    try {
      await UserService.delete(id, password);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.onSearch();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: (e instanceof Error && e.message)
        ? e.message : L10n.str(this.getUIContext(), 'delete_failed') });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.user_list_title'),
        actions: [new TopBarAction($r('app.string.user_add'))],
        onAction: () => {
          this.openCreate();
        },
        onRefresh: () => this.loadData(true),
        refreshing: this.isRefreshing || this.isLoading,
      })

      // 搜索栏
      Row() {
        TextInput({ placeholder: $r('app.string.user_search_hint'), text: this.keyword })
          .height(40)
          .fontSize(14)
          .backgroundColor($r('app.color.surface_alt'))
          .borderRadius(8)
          .layoutWeight(1)
          .padding({ left: 12, right: 12 })
          .onChange((value: string) => {
            this.keyword = value;
          })
          .onSubmit(() => {
            this.onSearch();
          })

        Button($r('app.string.search'))
          .fontSize(13)
          .fontColor($r('app.color.text_on_primary'))
          .backgroundColor($r('app.color.primary'))
          .borderRadius(4)
          .width(60)
          .height(40)
          .margin({ left: 8 })
          .onClick(() => {
            this.onSearch();
          })
      }
      .width('100%')
      .padding({ left: 16, right: 16, top: 8, bottom: 8 })
      .backgroundColor($r('app.color.surface'))

      // 内容
      if (this.isLoading && this.users.length === 0) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ message: this.errMsg, onRetry: () => this.loadData(true) });
      } else if (this.users.length === 0) {
        EmptyView({ message: $r('app.string.user_empty') });
      } else {
        // 刷新组件
        Refresh({ refreshing: $$this.isRefreshing }) {
          List() {
            ForEach(this.users, (user: User) => {
              ListItem() {
                Row() {
                  // 头像
                  Circle({ width: 40, height: 40 })
                    .fill($r('app.color.primary_bg'))

                  // 信息
                  Column() {
                    Row() {
                      Text(user.username)
                        .fontSize(15)
                        .fontWeight(FontWeight.Medium)

                      Text(user.status === 1 ? $r('app.string.status_enabled') : $r('app.string.status_disabled'))
                        .fontSize(10)
                        .fontColor(user.status === 1 ? $r('app.color.success_text') : $r('app.color.danger_text'))
                        .backgroundColor(user.status === 1 ? $r('app.color.success_bg') : $r('app.color.danger_bg'))
                        .borderRadius(2)
                        .padding({ left: 6, right: 6, top: 2, bottom: 2 })
                        .margin({ left: 8 })
                    }

                    Text(user.real_name)
                      .fontSize(12)
                      .fontColor($r('app.color.text_hint'))
                      .maxLines(1)
                      .textOverflow({ overflow: TextOverflow.Ellipsis })
                      .margin({ top: 2 })
                  }
                  .alignItems(HorizontalAlign.Start)
                  .layoutWeight(1)
                  .margin({ left: 12 })

                  Button($r('app.string.edit'))
                    .fontSize(12)
                    .fontColor($r('app.color.primary'))
                    .backgroundColor($r('app.color.primary_bg'))
                    .borderRadius(4)
                    .height(28)
                    .margin({ left: 8 })
                    .onClick(() => {
                      this.openEdit(user);
                    })

                  Button($r('app.string.delete'))
                    .fontSize(12)
                    .fontColor($r('app.color.danger_text'))
                    .backgroundColor($r('app.color.danger_bg'))
                    .borderRadius(4)
                    .height(28)
                    .margin({ left: 8 })
                    .onClick(() => {
                      this.confirmDelete(user);
                    })
                }
                .width('100%')
                .padding({ left: 16, right: 16, top: 10, bottom: 10 })
                .backgroundColor($r('app.color.surface'))
                .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
              }
            }, (user: User) => user.id)

            // 加载更多
            ListItem() {
              Row() {
                if (this.users.length >= this.total) {
                  Text('— ' + L10n.str(this.getUIContext(), 'no_more') + ' —')
                    .fontSize(12)
                    .fontColor($r('app.color.text_disabled'))
                } else {
                  Button($r('app.string.load_more'))
                    .fontSize(13)
                    .fontColor($r('app.color.primary'))
                    .backgroundColor(Color.Transparent)
                    .onClick(() => this.loadMore())
                }
              }
              .justifyContent(FlexAlign.Center)
              .width('100%')
              .height(48)
            }
          }
          .width('100%')
          .layoutWeight(1)
          .edgeEffect(EdgeEffect.Spring)
        }
        .onRefreshing(() => {
          this.loadData(true);
        })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }
}
