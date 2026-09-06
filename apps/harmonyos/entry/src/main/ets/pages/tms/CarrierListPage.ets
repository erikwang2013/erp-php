/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';
import { TopBar, TopBarAction } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';

/**
 * 承运商列表项（与后端 /admin/tms/carrier 返回字段对齐）
 */
interface TmsCarrierItem {
  id: string;
  name: string;
  code: string;
  status: number;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  name: string | number;
  code: string | number;
  type: string | number;
  website: string | number;
  tracking_url_template: string | number;
  api_provider: string | number;
  contact_phone: string | number;
  status: string | number;
}

@Entry
@Component
struct CarrierListPage {
  // 列表状态
  @State items: Array<TmsCarrierItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4: 错误展示文案（取后端/网络 message，空则保底）
  @State isRefreshing: boolean = false;
  private readonly limit: number = 20;
  private api: ApiService = new ApiService();

  // 表单状态
  @State showForm: boolean = false;
  @State formMode: string = 'create'; // create | edit
  @State formId: string = '';
  @State isSubmitting: boolean = false;

  // 表单字段（与后端 fillable 字段名严格一致）
  @State formName: string = '';
  @State formCode: string = '';
  @State formType: string = 'express';
  @State formWebsite: string = '';
  @State formTrackingUrlTemplate: string = '';
  @State formApiProvider: string = '';
  @State formContactPhone: string = '';
  @State formStatus: number = 1;

  aboutToAppear(): void {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false): Promise<void> {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.hasError = false;

    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/tms/carrier?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as TmsCarrierItem);
      if (isRefresh || this.page === 1) {
        this.items = mapped;
      } else {
        this.items = this.items.concat(mapped);
      }
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
      // M4: 不吞错误，把 message 传给错误位与 toast；UNAUTHORIZED/空 message 显示友好文案（§0.8）
      this.errMsg = e instanceof Error && e.message.length > 0 && e.message !== 'UNAUTHORIZED' ? e.message :
        L10n.str(this.getUIContext(), 'login_expired');
      promptAction.showToast({ message: this.errMsg });
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  loadMore(): void {
    if (this.items.length >= this.total) return;
    this.page++;
    this.loadData();
  }

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formName = '';
    this.formCode = '';
    this.formType = 'express';
    this.formWebsite = '';
    this.formTrackingUrlTemplate = '';
    this.formApiProvider = '';
    this.formContactPhone = '';
    this.formStatus = 1;
    this.showForm = true;
  }

  /**
   * 打开编辑表单：先 GET 详情回填
   */
  async openEdit(id: string): Promise<void> {
    this.formMode = 'edit';
    this.formId = id;
    this.showForm = true;
    this.loading = true;
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/tms/carrier/${id}`);
      const row = res.data as Record<string, Object>;
      this.formName = (row['name'] as string) ?? '';
      this.formCode = (row['code'] as string) ?? '';
      this.formType = (row['type'] as string) ?? 'express';
      this.formWebsite = (row['website'] as string) ?? '';
      this.formTrackingUrlTemplate = (row['tracking_url_template'] as string) ?? '';
      this.formApiProvider = (row['api_provider'] as string) ?? '';
      this.formContactPhone = (row['contact_phone'] as string) ?? '';
      this.formStatus = (row['status'] as number) ?? 1;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: $r('app.string.load_detail_failed') });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/tms/carrier，编辑 PUT /admin/tms/carrier/{id}
   */
  async save(): Promise<void> {
    if (!this.formName.trim()) {
      promptAction.showToast({ message: $r('app.string.carrier_name_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      name: this.formName,
      code: this.formCode,
      type: this.formType,
      website: this.formWebsite,
      tracking_url_template: this.formTrackingUrlTemplate,
      api_provider: this.formApiProvider,
      contact_phone: this.formContactPhone,
      status: this.formStatus
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/tms/carrier', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/tms/carrier/${this.formId}`, body);
        promptAction.showToast({ message: $r('app.string.update_success') });
      }
      this.showForm = false;
      // 刷新列表（回到第一页）
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: $r('app.string.save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  /**
   * 删除：确认对话框后 DELETE /admin/tms/carrier/{id}
   */
  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.carrier_delete_msg'),
        onConfirm: () => {
          this.deleteItem(id);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  /**
   * 删除：DELETE /admin/tms/carrier/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/tms/carrier/${id}`);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
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
        title: $r('app.string.carrier_list_title'),
        actions: [new TopBarAction($r('app.string.user_add'))],
        onAction: () => this.openCreate(),
        onRefresh: () => this.loadData(true),
        refreshing: this.isRefreshing || this.loading,
      })

      if (this.showForm) {
        this.buildForm()
      } else {
        this.buildList()
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }

  /**
   * 列表视图（保留原有分页逻辑）
   */
  @Builder
  buildList() {
    if (this.loading && this.items.length === 0) {
      LoadingView();
    } else if (this.hasError) {
      ErrorView({ message: this.errMsg, onRetry: () => this.loadData(true) });
    } else if (this.items.length === 0) {
      EmptyView({ message: $r('app.string.carrier_empty') });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: TmsCarrierItem) => {
            ListItem() {
              Row() {
                // 名称与编码
                Column() {
                  Text(item.name || item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(L10n.str(this.getUIContext(), 'carrier_code_fmt', item.code || '-'))
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .margin({ top: 2 })
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)

                // 状态
                Text(item.status === 1 ? $r('app.string.status_enabled') : $r('app.string.status_disabled'))
                  .fontSize(10)
                  .fontColor(item.status === 1 ? $r('app.color.success_text') : $r('app.color.danger_text'))
                  .backgroundColor(item.status === 1 ? $r('app.color.success_bg') : $r('app.color.danger_bg'))
                  .borderRadius(2)
                  .padding({ left: 6, right: 6, top: 2, bottom: 2 })

                // 操作
                Button($r('app.string.edit'))
                  .fontSize(12)
                  .fontColor($r('app.color.primary'))
                  .backgroundColor($r('app.color.primary_bg'))
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.openEdit(item.id))

                Button($r('app.string.delete'))
                  .fontSize(12)
                  .fontColor($r('app.color.danger_text'))
                  .backgroundColor($r('app.color.danger_bg'))
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.confirmDelete(item.id))
              }
              .width('100%')
              .padding({ left: 16, right: 16, top: 10, bottom: 10 })
              .backgroundColor($r('app.color.surface'))
              .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            }
          }, (item: TmsCarrierItem) => item.id)

          // 加载更多
          ListItem() {
            Row() {
              if (this.items.length >= this.total) {
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

  /**
   * 表单视图（新建/编辑共用）
   */
  @Builder
  buildForm() {
    Scroll() {
      Column() {
        Text(this.formMode === 'create' ? $r('app.string.carrier_form_add') : $r('app.string.carrier_form_edit'))
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
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
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        // 操作按钮
        Row() {
          Button($r('app.string.cancel'))
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor($r('app.color.text_secondary'))
            .backgroundColor($r('app.color.surface_alt'))
            .borderRadius(8)
            .onClick(() => { this.showForm = false; })

          Button($r('app.string.save'))
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor($r('app.color.text_on_primary'))
            .backgroundColor(this.isSubmitting ? $r('app.color.primary_disabled') : $r('app.color.primary'))
            .borderRadius(8)
            .margin({ left: 16 })
            .enabled(!this.isSubmitting)
            .onClick(() => { this.save(); })
        }
        .width('100%')
        .padding({ left: 16, right: 16, top: 32, bottom: 32 })
      }
      .width('100%')
    }
    .width('100%')
    .layoutWeight(1)
    .backgroundColor($r('app.color.bg_page'))
  }
}
