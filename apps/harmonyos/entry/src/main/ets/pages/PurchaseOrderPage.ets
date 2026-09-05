/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';

/**
 * 采购订单列表项（与后端 /admin/purchase/order 返回字段对齐）
 */
interface PurchaseOrderItem {
  id: string;
  code: string;
  name: string;
  supplier_id: number;
  apply_id: number;
  warehouse_id: number;
  total_amount: number;
  status: number;
  ordered_at: string;
  remark: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  name: string | number;
  supplier_id: string | number;
  apply_id: string | number;
  warehouse_id: string | number;
  total_amount: string | number;
  status: string | number;
  ordered_at: string | number;
  remark: string | number;
}

@Entry
@Component
struct PurchaseOrderPage {
  // 列表状态
  @State items: Array<PurchaseOrderItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
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
  @State formName: string = '';
  @State formSupplierId: string = '';
  @State formApplyId: string = '';
  @State formWarehouseId: string = '';
  @State formTotalAmount: string = '';
  @State formStatus: number = 1;
  @State formOrderedAt: string = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/purchase/order?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as PurchaseOrderItem);
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
      this.errMsg = e instanceof Error && e.message ? e.message : '加载失败';
      this.hasError = true;
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
    this.formCode = '';
    this.formName = '';
    this.formSupplierId = '';
    this.formApplyId = '';
    this.formWarehouseId = '';
    this.formTotalAmount = '';
    this.formStatus = 1;
    this.formOrderedAt = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/purchase/order/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formName = (row['name'] as string) ?? '';
      this.formSupplierId = row['supplier_id'] != null ? String(row['supplier_id']) : '';
      this.formApplyId = row['apply_id'] != null ? String(row['apply_id']) : '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formTotalAmount = row['total_amount'] != null ? String(row['total_amount']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formOrderedAt = (row['ordered_at'] as string) ?? '';
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
   * 保存：新建 POST /admin/purchase/order，编辑 PUT /admin/purchase/order/{id}
   */
  async save(): Promise<void> {
    if (!this.formName.trim()) {
      promptAction.showToast({ message: '请输入订单名称' });
      return;
    }
    if (!this.formSupplierId.trim()) {
      promptAction.showToast({ message: '请输入供应商ID' });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      name: this.formName,
      supplier_id: this.formSupplierId ? Number(this.formSupplierId) : 0,
      apply_id: this.formApplyId ? Number(this.formApplyId) : 0,
      warehouse_id: this.formWarehouseId ? Number(this.formWarehouseId) : 0,
      total_amount: this.formTotalAmount ? Number(this.formTotalAmount) : 0,
      status: this.formStatus,
      ordered_at: this.formOrderedAt,
      remark: this.formRemark
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/purchase/order', body);
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put<Object>(`/admin/purchase/order/${this.formId}`, body);
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
   * 删除：确认对话框后 DELETE /admin/purchase/order/{id}
   */
  deleteItem(id: string): void {
    AlertDialog.show({
      title: '确认删除',
      message: '确定要删除该采购订单吗？删除后不可恢复。',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定',
        fontColor: $r('app.color.danger_text'),
        action: async () => {
          try {
            await this.api.delete<Object>(`/admin/purchase/order/${id}`);
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
        .backgroundColor($r('app.color.surface'))
        .onClick(() => router.back())

        Text('采购订单')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button('+ 新增')
          .fontSize(13)
          .fontColor($r('app.color.text_on_primary'))
          .backgroundColor($r('app.color.primary'))
          .borderRadius(4)
          .height(32)
          .onClick(() => this.openCreate())
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor($r('app.color.surface'))

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
      EmptyView({ message: '暂无采购订单数据' });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: PurchaseOrderItem) => {
            ListItem() {
              Row() {
                // 名称与供应商
                Column() {
                  Text(item.name || item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`供应商ID: ${item.supplier_id}`)
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
                  .onClick(() => this.deleteItem(item.id))
              }
              .width('100%')
              .padding({ left: 16, right: 16, top: 10, bottom: 10 })
              .backgroundColor($r('app.color.surface'))
              .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            }
          }, (item: PurchaseOrderItem) => item.id)

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
        Text(this.formMode === 'create' ? '新增采购订单' : '编辑采购订单')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        Row() {
          Text('订单名称')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '请输入订单名称（必填）', text: this.formName })
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
          Text('订单编号')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '请输入订单编号', text: this.formCode })
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
          Text('供应商ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '供应商数字ID（必填）', text: this.formSupplierId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formSupplierId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('采购申请ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '采购申请数字ID，留空为0', text: this.formApplyId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formApplyId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor($r('app.color.surface'))
        .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('收货仓库ID')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '收货仓库数字ID，留空为0', text: this.formWarehouseId })
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
          Text('订单总金额')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '订单总金额，留空为0', text: this.formTotalAmount })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formTotalAmount = v; })
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
          Text('下单时间')
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))
          .width(80)

          TextInput({ placeholder: '格式: YYYY-MM-DD HH:mm:ss', text: this.formOrderedAt })
            .fontSize(15)
            .onChange((v: string) => { this.formOrderedAt = v; })
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
