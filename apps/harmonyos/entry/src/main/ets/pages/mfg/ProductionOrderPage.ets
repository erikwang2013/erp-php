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
 * 生产工单列表项（与后端 /admin/mfg/production 返回字段对齐）
 */
interface MfgProductionItem {
  id: string;
  code: string;
  bom_id: number;
  status: number;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  bom_id: string | number;
  warehouse_id: string | number;
  planned_quantity: string | number;
  status: string | number;
  planned_start: string | number;
  planned_end: string | number;
  remark: string | number;
}

@Entry
@Component
struct ProductionOrderPage {
  // 列表状态
  @State items: Array<MfgProductionItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errorMsg: string = ''; // M4: 失败的真实错误文案（传给 ErrorView/提示）
  @State isRefreshing: boolean = false;
  private readonly limit: number = 20;
  private api: ApiService = new ApiService();

  // 表单状态
  @State showForm: boolean = false;
  @State formMode: string = 'create'; // create | edit
  @State formId: string = '';
  @State isSubmitting: boolean = false;

  // 表单字段（与后端 fillable 字段名严格一致）
  @State formCode: string = '';
  @State formBomId: string = '';
  @State formWarehouseId: string = '';
  @State formPlannedQuantity: string = '';
  @State formStatus: number = 1;
  @State formPlannedStart: string = '';
  @State formPlannedEnd: string = '';
  @State formRemark: string = '';

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
    this.errorMsg = '';

    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/mfg/production?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as MfgProductionItem);
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
      // M4: 展示真实错误信息（替代固定文案）；UNAUTHORIZED 已在上方跳转
      this.errorMsg = (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'load_failed');
      promptAction.showToast({ message: this.errorMsg });
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
    this.formCode = '';
    this.formBomId = '';
    this.formWarehouseId = '';
    this.formPlannedQuantity = '';
    this.formStatus = 1;
    this.formPlannedStart = '';
    this.formPlannedEnd = '';
    this.formRemark = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/mfg/production/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formBomId = row['bom_id'] != null ? String(row['bom_id']) : '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formPlannedQuantity = row['planned_quantity'] != null ? String(row['planned_quantity']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formPlannedStart = (row['planned_start'] as string) ?? '';
      this.formPlannedEnd = (row['planned_end'] as string) ?? '';
      this.formRemark = (row['remark'] as string) ?? '';
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'load_detail_failed') });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/mfg/production，编辑 PUT /admin/mfg/production/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: $r('app.string.production_code_required') });
      return;
    }

    if (!this.formBomId.trim()) {
      promptAction.showToast({ message: $r('app.string.production_bom_id_required') });
      return;
    }

    if (!this.formPlannedQuantity.trim()) {
      promptAction.showToast({ message: $r('app.string.production_planned_quantity_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      bom_id: this.formBomId ? Number(this.formBomId) : 0,
      warehouse_id: this.formWarehouseId ? Number(this.formWarehouseId) : 0,
      planned_quantity: this.formPlannedQuantity ? Number(this.formPlannedQuantity) : 0,
      status: this.formStatus,
      planned_start: this.formPlannedStart,
      planned_end: this.formPlannedEnd,
      remark: this.formRemark
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/mfg/production', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/mfg/production/${this.formId}`, body);
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
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.production_delete_msg'),
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
   * 删除：DELETE /admin/mfg/production/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/mfg/production/${id}`);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'delete_failed') });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.production_list_title'),
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
      ErrorView({ message: this.errorMsg, onRetry: (): void => { this.loadData(true); } });
    } else if (this.items.length === 0) {
      EmptyView({ message: $r('app.string.production_empty') });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: MfgProductionItem) => {
            ListItem() {
              Row() {
                // 工单号与BOM ID
                Column() {
                  Text(item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`BOM ID: ${item.bom_id}`)
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
          }, (item: MfgProductionItem) => item.id)

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
        Text(this.formMode === 'create' ? $r('app.string.production_form_add') : $r('app.string.production_form_edit'))
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        Row() {
          Text($r('app.string.production_code'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_code_hint'), text: this.formCode })
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
          Text($r('app.string.production_bom_id'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_bom_id_hint'), text: this.formBomId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formBomId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text($r('app.string.production_warehouse_id'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_warehouse_id_hint'), text: this.formWarehouseId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formWarehouseId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text($r('app.string.production_planned_quantity'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_planned_quantity_hint'), text: this.formPlannedQuantity })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formPlannedQuantity = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text($r('app.string.production_status'))
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

        Row() {
          Text($r('app.string.production_planned_start'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_planned_start_hint'), text: this.formPlannedStart })
            .fontSize(15)
            .onChange((v: string) => { this.formPlannedStart = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text($r('app.string.production_planned_end'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_planned_end_hint'), text: this.formPlannedEnd })
            .fontSize(15)
            .onChange((v: string) => { this.formPlannedEnd = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text($r('app.string.production_remark'))
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: $r('app.string.production_remark_hint'), text: this.formRemark })
            .fontSize(15)
            .onChange((v: string) => { this.formRemark = v; })
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
