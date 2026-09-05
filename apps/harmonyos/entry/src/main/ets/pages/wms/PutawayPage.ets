/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';

/**
 * 上架单列表项（与后端 /admin/wms/putaway 返回字段对齐）
 */
interface WmsPutawayItem {
  id: string;
  code: string;
  warehouse_id: number;
  status: number;
  strategy: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  warehouse_id: string | number;
  receiving_id: string | number;
  status: string | number;
  strategy: string | number;
  assigned_to: string | number;
  completed_at: string | number;
}

@Entry
@Component
struct PutawayPage {
  // 列表状态
  @State items: Array<WmsPutawayItem> = [];
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
  @State formReceivingId: string = '';
  @State formStatus: number = 1;
  @State formStrategy: string = 'fifo';
  @State formAssignedTo: string = '';
  @State formCompletedAt: string = '';

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
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/putaway?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as WmsPutawayItem);
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
    this.formReceivingId = '';
    this.formStatus = 1;
    this.formStrategy = 'fifo';
    this.formAssignedTo = '';
    this.formCompletedAt = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/putaway/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formReceivingId = row['receiving_id'] != null ? String(row['receiving_id']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formStrategy = (row['strategy'] as string) ?? 'fifo';
      this.formAssignedTo = row['assigned_to'] != null ? String(row['assigned_to']) : '';
      this.formCompletedAt = (row['completed_at'] as string) ?? '';
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
   * 保存：新建 POST /admin/wms/putaway，编辑 PUT /admin/wms/putaway/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: '请输入上架单号' });
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
      receiving_id: this.formReceivingId ? Number(this.formReceivingId) : 0,
      status: this.formStatus,
      strategy: this.formStrategy,
      assigned_to: this.formAssignedTo ? Number(this.formAssignedTo) : 0,
      completed_at: this.formCompletedAt
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/wms/putaway', body);
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put<Object>(`/admin/wms/putaway/${this.formId}`, body);
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

  /**
   * 删除：确认对话框后 DELETE /admin/wms/putaway/{id}
   */
  deleteItem(id: string): void {
    AlertDialog.show({
      title: '确认删除',
      message: '确定要删除该上架单吗？删除后不可恢复。',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定',
        fontColor: '#FF4D4F',
        action: async () => {
          try {
            await this.api.delete<Object>(`/admin/wms/putaway/${id}`);
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
      },
      secondaryButton: {
        value: '取消',
        action: () => {}
      }
    });
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text('上架管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button('+ 新增')
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .height(32)
          .onClick(() => this.openCreate())
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      if (this.showForm) {
        this.buildForm()
      } else {
        this.buildList()
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
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
      EmptyView({ message: '暂无上架数据' });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: WmsPutawayItem) => {
            ListItem() {
              Row() {
                // 单号与仓库
                Column() {
                  Text(item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`仓库ID: ${item.warehouse_id} · 策略: ${item.strategy || '-'}`)
                    .fontSize(12)
                    .fontColor('#999999')
                    .margin({ top: 2 })
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)

                // 状态
                Text(item.status === 1 ? '启用' : '禁用')
                  .fontSize(10)
                  .fontColor(item.status === 1 ? '#52C41A' : '#FF4D4F')
                  .backgroundColor(item.status === 1 ? '#E6FFF0' : '#FFE6E6')
                  .borderRadius(2)
                  .padding({ left: 6, right: 6, top: 2, bottom: 2 })

                // 操作
                Button('编辑')
                  .fontSize(12)
                  .fontColor('#1677FF')
                  .backgroundColor('#E6F0FF')
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.openEdit(item.id))

                Button('删除')
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .backgroundColor('#FFE6E6')
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.deleteItem(item.id))
              }
              .width('100%')
              .padding({ left: 16, right: 16, top: 10, bottom: 10 })
              .backgroundColor('#FFFFFF')
              .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
            }
          }, (item: WmsPutawayItem) => item.id)

          // 加载更多
          ListItem() {
            Row() {
              if (this.items.length >= this.total) {
                Text('— 没有更多了 —')
                  .fontSize(12)
                  .fontColor('#CCCCCC')
              } else {
                Button('加载更多')
                  .fontSize(13)
                  .fontColor('#1677FF')
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
        Text(this.formMode === 'create' ? '新增上架单' : '编辑上架单')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        Row() {
          Text('上架单号')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入上架单号（必填）', text: this.formCode })
            .fontSize(15)
            .onChange((v: string) => { this.formCode = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('仓库ID')
          .fontSize(15)
          .fontColor('#333333')
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
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('收货单ID')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '收货单数字ID，留空为0', text: this.formReceivingId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formReceivingId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('状态')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          Row() {
            Button('启用')
              .fontSize(13)
              .fontColor(this.formStatus === 1 ? '#FFFFFF' : '#666666')
              .backgroundColor(this.formStatus === 1 ? '#52C41A' : '#F5F5F5')
              .borderRadius(4)
              .height(32)
              .onClick(() => { this.formStatus = 1; })

            Button('禁用')
              .fontSize(13)
              .fontColor(this.formStatus === 0 ? '#FFFFFF' : '#666666')
              .backgroundColor(this.formStatus === 0 ? '#FF4D4F' : '#F5F5F5')
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
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('上架策略')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '如: fifo / lifo / fefo', text: this.formStrategy })
            .fontSize(15)
            .onChange((v: string) => { this.formStrategy = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('指派人员ID')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '指派人员数字ID，留空为0', text: this.formAssignedTo })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formAssignedTo = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('完成时间')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '格式: YYYY-MM-DD HH:mm:ss，可留空', text: this.formCompletedAt })
            .fontSize(15)
            .onChange((v: string) => { this.formCompletedAt = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        // 操作按钮
        Row() {
          Button('取 消')
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor('#666666')
            .backgroundColor('#F0F0F0')
            .borderRadius(8)
            .onClick(() => { this.showForm = false; })

          Button('保 存')
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor('#FFFFFF')
            .backgroundColor(this.isSubmitting ? '#99BBFF' : '#1677FF')
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
    .backgroundColor('#F5F5F5')
  }
}
