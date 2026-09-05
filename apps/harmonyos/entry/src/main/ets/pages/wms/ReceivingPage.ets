/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';
import { TopBar, TopBarAction } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';

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
      this.errMsg = msg !== '' ? msg : '加载失败';
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
      promptAction.showToast({ message: msg !== '' ? msg : '加载详情失败' });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/wms/receiving，编辑 PUT /admin/wms/receiving/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: '请输入收货单号' });
      return;
    }
    if (!this.formWarehouseId.trim()) {
      promptAction.showToast({ message: '请输入仓库ID' });
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
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put<Object>(`/admin/wms/receiving/${this.formId}`, body);
        promptAction.showToast({ message: '更新成功' });
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
      promptAction.showToast({ message: msg !== '' ? msg : '保存失败' });
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
        title: '确认删除',
        message: '确定要删除该收货单吗？删除后不可恢复。',
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
      promptAction.showToast({ message: '删除成功' });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4：删除失败展示可用 message，空消息回退原文案
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg !== '' ? msg : '删除失败' });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛试点：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: '收货管理',
        actions: [new TopBarAction('+ 新增')],
        onAction: () => this.openCreate(),
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
      EmptyView({ message: '暂无收货数据' });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: WmsReceivingItem) => {
            ListItem() {
              Row() {
                // 单号与仓库
                Column() {
                  Text(item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`仓库ID: ${item.warehouse_id}`)
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .margin({ top: 2 })
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)

                // 状态
                Text(item.status === 1 ? '启用' : '禁用')
                  .fontSize(10)
                  .fontColor(item.status === 1 ? $r('app.color.success_text') : $r('app.color.danger_text'))
                  .backgroundColor(item.status === 1 ? $r('app.color.success_bg') : $r('app.color.danger_bg'))
                  .borderRadius(2)
                  .padding({ left: 6, right: 6, top: 2, bottom: 2 })

                // 操作
                Button('编辑')
                  .fontSize(12)
                  .fontColor($r('app.color.primary'))
                  .backgroundColor($r('app.color.primary_bg'))
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.openEdit(item.id))

                Button('删除')
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
          }, (item: WmsReceivingItem) => item.id)

          // 加载更多
          ListItem() {
            Row() {
              if (this.items.length >= this.total) {
                Text('— 没有更多了 —')
                  .fontSize(12)
                  .fontColor($r('app.color.text_disabled'))
              } else {
                Button('加载更多')
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
        Text(this.formMode === 'create' ? '新增收货单' : '编辑收货单')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        Row() {
          Text('收货单号')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '请输入收货单号（必填）', text: this.formCode })
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
          Text('仓库ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '仓库数字ID（必填）', text: this.formWarehouseId })
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
          Text('ASN单ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: 'ASN单数字ID，留空为0', text: this.formAsnId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formAsnId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('收货月台ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '收货月台数字ID，留空为0', text: this.formDockLocationId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formDockLocationId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('状态')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          Row() {
            Button('启用')
              .fontSize(13)
              .fontColor(this.formStatus === 1 ? $r('app.color.text_on_primary') : $r('app.color.text_secondary'))
              .backgroundColor(this.formStatus === 1 ? $r('app.color.success') : $r('app.color.surface_alt'))
              .borderRadius(4)
              .height(32)
              .onClick(() => { this.formStatus = 1; })

            Button('禁用')
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
          Text('收货人ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '收货人数字ID，留空为0', text: this.formReceiverId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formReceiverId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('收货时间')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '格式: YYYY-MM-DD HH:mm:ss，可留空', text: this.formReceivedAt })
            .fontSize(15)
            .onChange((v: string) => { this.formReceivedAt = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('备注')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '请输入备注', text: this.formRemark })
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
          Button('取 消')
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor($r('app.color.text_secondary'))
            .backgroundColor($r('app.color.surface_alt'))
            .borderRadius(8)
            .onClick(() => { this.showForm = false; })

          Button('保 存')
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
