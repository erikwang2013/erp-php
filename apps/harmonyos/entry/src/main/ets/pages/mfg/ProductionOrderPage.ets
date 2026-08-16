/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';

/**
 * 生产工单列表项（与后端 /admin/mfg/production 返回字段对齐）
 */
interface MfgProductionItem {
  id: string;
  code: string;
  bom_id: number;
  status: number;
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

    try {
      const res = await this.api.get(`/admin/mfg/production?page=${this.page}&limit=${this.limit}`);
      const data = res['data'] as Record<string, Object>;
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
      promptAction.showToast({ message: '加载失败' });
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
      const res = await this.api.get(`/admin/mfg/production/${id}`);
      const row = res['data'] as Record<string, Object>;
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
      promptAction.showToast({ message: '加载详情失败' });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/mfg/production，编辑 PUT /admin/mfg/production/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: '请输入工单号' });
      return;
    }

    if (!this.formBomId.trim()) {
      promptAction.showToast({ message: '请输入BOM ID' });
      return;
    }

    if (!this.formPlannedQuantity.trim()) {
      promptAction.showToast({ message: '请输入计划数量' });
      return;
    }

    this.isSubmitting = true;
    const body: Record<string, Object> = {
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
        await this.api.post('/admin/mfg/production', body);
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put(`/admin/mfg/production/${this.formId}`, body);
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
      promptAction.showToast({ message: '保存失败' });
    } finally {
      this.isSubmitting = false;
    }
  }

  /**
   * 删除：确认对话框后 DELETE /admin/mfg/production/{id}
   */
  deleteItem(id: string): void {
    AlertDialog.show({
      title: '确认删除',
      message: '确定要删除该生产工单吗？删除后不可恢复。',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定',
        fontColor: '#FF4D4F',
        action: async () => {
          try {
            await this.api.delete(`/admin/mfg/production/${id}`);
            promptAction.showToast({ message: '删除成功' });
            this.loadData(true);
          } catch (e) {
            if (e instanceof Error && e.message === 'UNAUTHORIZED') {
              router.replaceUrl({ url: 'pages/LoginPage' });
              return;
            }
            promptAction.showToast({ message: '删除失败' });
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

        Text('生产工单')
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
      ErrorView({ onRetry: () => this.loadData(true) });
    } else if (this.items.length === 0) {
      EmptyView({ message: '暂无生产工单数据' });
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
          }, (item: MfgProductionItem) => item.id)

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
        Text(this.formMode === 'create' ? '新增生产工单' : '编辑生产工单')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        this.buildFormItem('工单号', () => {
          TextInput({ placeholder: '请输入工单号（必填）', text: this.formCode })
            .fontSize(15)
            .onChange((v: string) => { this.formCode = v; })
        });

        this.buildFormItem('BOM ID', () => {
          TextInput({ placeholder: '请输入BOM数字ID（必填）', text: this.formBomId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formBomId = v; })
        });

        this.buildFormItem('仓库ID', () => {
          TextInput({ placeholder: '仓库数字ID，留空为0', text: this.formWarehouseId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formWarehouseId = v; })
        });

        this.buildFormItem('计划数量', () => {
          TextInput({ placeholder: '请输入计划生产数量（必填）', text: this.formPlannedQuantity })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formPlannedQuantity = v; })
        });

        this.buildFormItem('状态', () => {
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
        });

        this.buildFormItem('计划开始日期', () => {
          TextInput({ placeholder: '格式: YYYY-MM-DD', text: this.formPlannedStart })
            .fontSize(15)
            .onChange((v: string) => { this.formPlannedStart = v; })
        });

        this.buildFormItem('计划结束日期', () => {
          TextInput({ placeholder: '格式: YYYY-MM-DD', text: this.formPlannedEnd })
            .fontSize(15)
            .onChange((v: string) => { this.formPlannedEnd = v; })
        });

        this.buildFormItem('备注', () => {
          TextInput({ placeholder: '请输入备注', text: this.formRemark })
            .fontSize(15)
            .onChange((v: string) => { this.formRemark = v; })
        });

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

  /**
   * 表单项：标签 + 输入控件
   */
  @Builder
  buildFormItem(label: string, content: () => void) {
    Row() {
      Text(label)
        .fontSize(15)
        .fontColor('#333333')
        .width(80)

      content()
        .layoutWeight(1)
    }
    .width('100%')
    .minHeight(56)
    .padding({ left: 16, right: 16 })
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
    .alignItems(VerticalAlign.Center)
  }
}
