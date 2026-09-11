/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { RoleService, PermissionService } from '../service/DataService';
import { RoleItem, PermissionNode } from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { TopBar, TopBarAction } from '../common/TopBar';
import { ConfirmDialog, FormDialog, FormField } from '../common/FormDialog';
import { PermissionTreePicker } from '../common/PermissionTreePicker';
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../common/ListRow';
import { L10n } from '../utils/L10n';

/**
 * 角色管理（系统域）：角色 CRUD + 权限树分配。
 * 删除为敏感操作：后端要求当前管理员密码二次确认（422 校验），
 * 经 ConfirmDialog needPassword 通道提交。
 */
@Entry
@Component
struct RoleListPage {
  // 列表状态
  @State roles: Array<RoleItem> = [];
  @State page: number = 1;
  @State total: number = 0;
  @State loading: boolean = true;
  @State hasError: boolean = false;
  @State errorMsg: string = '';
  @State isRefreshing: boolean = false;
  private readonly limit: number = 15;

  // 权限树（弹框权限选区分发；页进入即预取）
  @State perms: Array<PermissionNode> = [];
  @State permLoading: boolean = false;
  @State permFailed: boolean = false;

  // 表单弹框状态
  @State formMode: string = 'create'; // create | edit
  @State editId: string = '';
  // 权限勾选提交集（编辑预选 / onChange 上行累计；提交时快照发送，保存不擅补/不擅删）
  @State permPicked: Array<string> = [];
  // presetIds 开框快照：弹框生命周期内不再变更——勾选只在 picker 内部演进、
  // onChange 仅累计到 permPicked，不回灌本快照（避免每点一次整树 @Prop 重传重建）；
  // 重开（含提交失败带原值重建）时重新快照 permPicked 当前值
  @State permPreset: Array<string> = [];

  private formDialogController?: CustomDialogController;

  aboutToAppear(): void {
    this.loadData();
    this.loadPerms();
  }

  /** 加载权限树：默认走 Service 层静态缓存（首载后复用），手动刷新传 force 重拉 */
  async loadPerms(force: boolean = false): Promise<void> {
    this.permLoading = true;
    this.permFailed = false;
    try {
      this.perms = await PermissionService.getTree(force);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.permFailed = true;
      if (this.perms.length > 0) {
        // 已有数据仅提示，不清空已渲染树
        promptAction.showToast({ message: (e instanceof Error && e.message)
          ? e.message : L10n.str(this.getUIContext(), 'load_failed') });
      }
    } finally {
      this.permLoading = false;
    }
  }

  async loadData(isRefresh: boolean = false): Promise<void> {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.hasError = false;
    this.errorMsg = '';
    try {
      const res = await RoleService.getList(this.page, this.limit);
      if (isRefresh || this.page === 1) {
        this.roles = res.list;
      } else {
        this.roles = this.roles.concat(res.list);
      }
      this.total = res.total;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
      this.errorMsg = (e instanceof Error && e.message) ? e.message :
        L10n.str(this.getUIContext(), 'load_failed');
      promptAction.showToast({ message: this.errorMsg });
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  loadMore(): void {
    if (this.roles.length >= this.total) {
      return;
    }
    this.page++;
    this.loadData();
  }

  private findRole(id: string): RoleItem | undefined {
    for (let i = 0; i < this.roles.length; i++) {
      if (this.roles[i].id === id) {
        return this.roles[i];
      }
    }
    return undefined;
  }

  /** 编辑预选：角色已授权 id 数组（P3 起后端下发 hashid 串数组，含中间目录），递归标记交给树组件 */
  private grantedIds(role: RoleItem): Array<string> {
    const list: Array<string> = [];
    if (role.permissions) {
      role.permissions.forEach((id: string) => {
        list.push(id);
      });
    }
    return list;
  }

  /** 行徽章：启用 success / 停用 danger（kind 同源驱动行 icon 圆徽色） */
  private roleBadge(role: RoleItem): RowBadge {
    if (role.status === 1) {
      return new RowBadge('success', $r('app.string.status_enabled'));
    }
    return new RowBadge('danger', $r('app.string.status_disabled'));
  }

  /** 副行文案：slug · 用户数（· description，有值并入单行——与 Flutter 副行同构） */
  private roleSubtitle(role: RoleItem): string {
    const base = L10n.str(this.getUIContext(), 'role_subtitle_fmt',
      role.slug || '-', String(role.users_count ?? 0));
    const description = (role.description ?? '').trim();
    return description.length > 0 ? base + ' · ' + description : base;
  }

  private formFields(mode: string): Array<FormField> {
    const readonly = mode === 'edit';
    return [
      new FormField('name', $r('app.string.role_name'), 'text', true, [], $r('app.string.role_name_hint'), readonly),
      new FormField('slug', $r('app.string.role_slug'), 'text', true, [], $r('app.string.role_slug_hint'), readonly),
      new FormField('description', $r('app.string.role_description'), 'text', false, [], $r('app.string.role_description_hint')),
      new FormField('status', $r('app.string.role_status'), 'select', false,
        [$r('app.string.status_enabled'), $r('app.string.status_disabled')], '',
        false, ['1', '0']),
    ];
  }

  openCreate(): void {
    this.formMode = 'create';
    this.editId = '';
    this.permPicked = [];
    this.openRoleDialog(['', '', '', '1']);
  }

  openEdit(id: string): void {
    const role = this.findRole(id);
    if (!role) {
      return;
    }
    this.formMode = 'edit';
    this.editId = id;
    // 无自动闭包：预选=已授平铺全集，保存直传同集（未点改不丢深叶子）
    this.permPicked = this.grantedIds(role);
    this.openRoleDialog([role.name, role.slug, role.description ?? '', String(role.status)]);
  }

  /** 打开角色表单弹框（每次重建 controller/弹框实例，保证内部状态干净） */
  private openRoleDialog(initial: Array<string>): void {
    // 开框快照勾选集（含提交失败重建路径：此时 permPicked 为失败前用户点选，原样保留）
    this.permPreset = this.permPicked.slice();
    const mode = this.formMode;
    this.formDialogController = new CustomDialogController({
      builder: FormDialog({
        title: mode === 'create' ? $r('app.string.role_form_add') : $r('app.string.role_form_edit'),
        fields: this.formFields(mode),
        initial: initial,
        withSlot: true,
        slotContent: (): void => {
          this.permSlot();
        },
        onCancel: (): void => {
          this.formDialogController?.close();
        },
        onConfirm: (vals: Array<string>): void => {
          this.submitRole(vals);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: false,
    });
    this.formDialogController.open();
  }

  /**
   * 保存：创建 POST /admin/role，编辑 PUT /admin/role/{id}。
   * 编辑态 name 只读回传冗余值、slug 后端不可改不提交；permission_ids=全勾集合原样。
   */
  async submitRole(vals: Array<string>): Promise<void> {
    const name = (vals[0] ?? '').trim();
    const slug = (vals[1] ?? '').trim();
    const description = vals[2] ?? '';
    const status = vals[3] === '1' ? 1 : 0;
    const permIds = this.permPicked.slice();
    const isEdit = this.formMode === 'edit';

    if (!isEdit && name.length === 0) {
      promptAction.showToast({ message: $r('app.string.role_name_required') });
      return;
    }
    if (!isEdit && slug.length === 0) {
      promptAction.showToast({ message: $r('app.string.role_slug_required') });
      return;
    }

    try {
      if (isEdit) {
        await RoleService.update(this.editId, {
          name: name, description: description, status: status, permission_ids: permIds
        });
        promptAction.showToast({ message: $r('app.string.update_success') });
      } else {
        await RoleService.create({
          name: name, slug: slug, description: description, status: status, permission_ids: permIds
        });
        promptAction.showToast({ message: $r('app.string.create_success') });
      }
      this.formDialogController?.close();
      this.loadData(true);
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
        this.openRoleDialog(vals);
      }, 300);
    }
  }

  private deleteDialogController?: CustomDialogController;

  /** 删除入口：ConfirmDialog + 管理员密码二次确认（后端 422 校验） */
  confirmDelete(role: RoleItem): void {
    this.deleteDialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: L10n.str(this.getUIContext(), 'role_delete_msg_fmt', role.name || '-'),
        needPassword: true,
        confirmText: $r('app.string.delete'),
        onConfirmPwd: (pwd: string) => this.deleteRole(role.id, pwd),
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.deleteDialogController.open();
  }

  async deleteRole(id: string, password: string): Promise<void> {
    try {
      await RoleService.delete(id, password);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // 422（密码错 / 关联约束等）交回 ConfirmDialog：toast 后端 message 且弹窗保留可重试
      throw new Error(e instanceof Error ? e.message : '');
    }
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.role_list_title'),
        actions: [new TopBarAction($r('app.string.user_add'))],
        onAction: () => this.openCreate(),
        onRefresh: () => this.loadData(true),
        refreshing: this.isRefreshing || this.loading,
      })

      this.buildList()
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }

  @Builder
  buildList() {
    if (this.loading && this.roles.length === 0) {
      LoadingView();
    } else if (this.hasError) {
      ErrorView({ message: this.errorMsg, onRetry: (): void => { this.loadData(true); } });
    } else if (this.roles.length === 0) {
      EmptyView({ message: $r('app.string.role_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'system', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.roles, (role: RoleItem, index: number) => {
                ListItem() {
                  ListRow({
                    title: role.name || '-',
                    subtitle: this.roleSubtitle(role),
                    icon: role.name || '-',
                    badge: this.roleBadge(role),
                    actions: [
                      new RowAction($r('app.string.edit'), 'primary', (): void => {
                        this.openEdit(role.id);
                      }),
                      new RowAction($r('app.string.delete'), 'danger', (): void => {
                        this.confirmDelete(role);
                      })
                    ],
                    isLast: index === this.roles.length - 1
                  })
                }
              }, (role: RoleItem) => role.id)

              ListItem() {
                Row() {
                  if (this.roles.length >= this.total) {
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
        .width('100%')
        .layoutWeight(1)
        .borderRadius(8)
        .clip(true)
        .backgroundColor($r('app.color.surface'))
        .border({ width: 1, color: $r('app.color.border') })
      }
      .width('100%')
      .layoutWeight(1)
      .padding({ left: 12, right: 12, top: 12, bottom: 12 })
    }
  }

  /** 弹框内权限分配区（FormDialog 字段下方插槽）：段标题 + 刷新/加载态 + 三态树 */
  @Builder
  permSlot() {
    Column() {
      Row() {
        Text($r('app.string.role_perm_section'))
          .fontSize(14)
          .fontWeight(FontWeight.Medium)
          .fontColor($r('app.color.text_primary'))

        Blank()

        Button($r('app.string.refresh'))
          .fontSize(11)
          .fontColor($r('app.color.primary'))
          .backgroundColor($r('app.color.primary_bg'))
          .borderRadius(4)
          .height(24)
          .enabled(!this.permLoading)
          .onClick(() => {
            this.loadPerms(true); // 手动刷新：force 绕过 Service 层缓存重拉
          })
      }
      .width('100%')
      .margin({ bottom: 8 })

      if (this.permLoading && this.perms.length === 0) {
        Row({ space: 8 }) {
          LoadingProgress()
            .width(16)
            .height(16)
            .color($r('app.color.primary'))
          Text($r('app.string.role_perm_loading'))
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
        }
        .width('100%')
        .height(40)
        .justifyContent(FlexAlign.Center)
      } else if (this.permFailed && this.perms.length === 0) {
        Text($r('app.string.role_perm_load_failed'))
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })
      } else if (this.perms.length === 0) {
        Text($r('app.string.role_perm_empty'))
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })
      } else {
        PermissionTreePicker({
          nodes: this.perms,
          presetIds: this.permPreset,
          onChange: (ids: Array<string>): void => {
            this.permPicked = ids;
          }
        })
      }
    }
    .width('100%')
  }
}
