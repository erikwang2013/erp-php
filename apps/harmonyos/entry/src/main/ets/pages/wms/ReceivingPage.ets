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
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../../common/ListRow';
import { DetailCard } from '../../common/DetailCard';

/**
 * 收货单列表项（与后端 /admin/wms/receiving 返回字段对齐）
 */
interface WmsReceivingItem {
  id: string;
  code: string;
  warehouse_id: number;
  status: number;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  warehouse_id: string | number;
  asn_id: string | number;
  dock_location_id: string | number;
  status: string | number;
  receiver_id: string | number;
  received_at: string | number;
  remark: string | number;
}

@Entry
@Component
struct ReceivingPage {
  // 列表状态
  @State items: Array<WmsReceivingItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4：加载失败时的可用 message（交 ErrorView 展示）
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
  @State formWarehouseId: string = '';
  @State formAsnId: string = '';
  @State formDockLocationId: string = '';
  @State formStatus: number = 1;
  @State formReceiverId: string = '';
  @State formReceivedAt: string = '';
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

    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/receiving?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as WmsReceivingItem);
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
      // M4：错误不吞——可用 message 交 ErrorView 展示（toast 与其重复故移除）；空消息回退原文案
      const msg = e instanceof Error ? e.message : '';
      this.hasError = true;
      this.errMsg = msg !== '' ? msg : L10n.str(this.getUIContext(), 'load_failed');
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

  /** 行徽章：启用 success / 停用 danger（kind 同源驱动行 icon 圆徽色） */
  private receivingBadge(item: WmsReceivingItem): RowBadge {
    if (item.status === 1) {
      return new RowBadge('success', $r('app.string.status_enabled'));
    }
    return new RowBadge('danger', $r('app.string.status_disabled'));
  }

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formCode = '';
    this.formWarehouseId = '';
    this.formAsnId = '';
    this.formDockLocationId = '';
    this.formStatus = 1;
    this.formReceiverId = '';
    this.formReceivedAt = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/receiving/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formAsnId = row['asn_id'] != null ? String(row['asn_id']) : '';
      this.formDockLocationId = row['dock_location_id'] != null ? String(row['dock_location_id']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formReceiverId = row['receiver_id'] != null ? String(row['receiver_id']) : '';
      this.formReceivedAt = (row['received_at'] as string) ?? '';
      this.formRemark = (row['remark'] as string) ?? '';
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4：详情加载失败展示可用 message，空消息回退原文案
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg !== '' ? msg : L10n.str(this.getUIContext(), 'load_detail_failed') });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/wms/receiving，编辑 PUT /admin/wms/receiving/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: $r('app.string.receiving_code_required') });
      return;
    }
    if (!this.formWarehouseId.trim()) {
      promptAction.showToast({ message: $r('app.string.receiving_warehouse_id_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      warehouse_id: this.formWarehouseId ? Number(this.formWarehouseId) : 0,
      asn_id: this.formAsnId ? Number(this.formAsnId) : 0,
      dock_location_id: this.formDockLocationId ? Number(this.formDockLocationId) : 0,
      status: this.formStatus,
      receiver_id: this.formReceiverId ? Number(this.formReceiverId) : 0,
      received_at: this.formReceivedAt,
      remark: this.formRemark
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/wms/receiving', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/wms/receiving/${this.formId}`, body);
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
      // M4：保存失败展示可用 message，空消息回退原文案
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg !== '' ? msg : L10n.str(this.getUIContext(), 'save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛试点）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.receiving_delete_msg'),
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
   * 删除：DELETE /admin/wms/receiving/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/wms/receiving/${id}`);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4：删除失败展示可用 message，空消息回退原文案
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg !== '' ? msg : L10n.str(this.getUIContext(), 'delete_failed') });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛试点：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.receiving_list_title'),
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
      EmptyView({ message: $r('app.string.receiving_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'wms', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: WmsReceivingItem, index: number) => {
                ListItem() {
                  ListRow({
                    title: item.code || '-',
                    subtitle: L10n.str(this.getUIContext(), 'receiving_wh_fmt', String(item.warehouse_id ?? '-')),
                    icon: item.code || '-',
                    badge: this.receivingBadge(item),
                    actions: [
                      new RowAction($r('app.string.edit'), 'primary', (): void => {
                        this.openEdit(item.id);
                      }),
                      new RowAction($r('app.string.delete'), 'danger', (): void => {
                        this.confirmDelete(item.id);
                      })
                    ],
                    isLast: index === this.items.length - 1
                  })
                }
              }, (item: WmsReceivingItem) => item.id)

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

  /**
   * 表单视图（新建/编辑共用）
   */
  @Builder
  buildForm() {
    Scroll() {
      Column() {
        // F 批：inline 表单分组卡——单 DetailCard 套列表卡同款卡容器（标题复用既有表单 key）
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'receiving_form_add' : 'receiving_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.receiving_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_code_hint'), text: this.formCode })
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
              Text($r('app.string.receiving_warehouse_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_warehouse_id_hint'), text: this.formWarehouseId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formWarehouseId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_asn_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_asn_id_hint'), text: this.formAsnId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formAsnId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_dock_location_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_dock_location_id_hint'), text: this.formDockLocationId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formDockLocationId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_status'))
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
              Text($r('app.string.receiving_receiver_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_receiver_id_hint'), text: this.formReceiverId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formReceiverId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_received_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_received_at_hint'), text: this.formReceivedAt })
                .fontSize(15)
                .onChange((v: string) => { this.formReceivedAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.receiving_remark'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.receiving_remark_hint'), text: this.formRemark })
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
        }

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
        .margin({ top: 24, bottom: 32 })
      }
      .width('100%')
      .padding({ left: 12, right: 12, top: 12, bottom: 12 })
    }
    .width('100%')
    .layoutWeight(1)
    .backgroundColor($r('app.color.bg_page'))
  }
}
