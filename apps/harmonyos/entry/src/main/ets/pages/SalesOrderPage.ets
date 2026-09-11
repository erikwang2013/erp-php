/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';
import { LoadingView, ErrorView, EmptyView } from '../common/Components';
import { TopBar, TopBarAction } from '../common/TopBar';
import { ConfirmDialog } from '../common/FormDialog';
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../common/ListRow';
import { DetailCard } from '../common/DetailCard';

/**
 * 销售订单列表项（与后端 /admin/sales/order 返回字段对齐）
 */
interface SalesOrderItem {
  id: string;
  code: string;
  customer_name: string; // leftJoin 带出；客户软删后为 null（表无 name 列）
  customer_id: string; // hashid（后端 encodeIds 编码，保存原样回传）
  quotation_id: number;
  warehouse_id: number;
  total_amount: number;
  discount_amount: number;
  status: number;
  ordered_at: string;
  remark: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  customer_id: string; // hashid 原样回传（后端解码落库 int）
  quotation_id: string | number;
  warehouse_id: string | number;
  total_amount: string | number;
  discount_amount: string | number;
  status: string | number;
  ordered_at: string | number;
  remark: string | number;
}

@Entry
@Component
struct SalesOrderPage {
  // 列表状态
  @State items: Array<SalesOrderItem> = [];
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
  @State formCustomerId: string = '';
  @State formQuotationId: string = '';
  @State formWarehouseId: string = '';
  @State formTotalAmount: string = '';
  @State formDiscountAmount: string = '0';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/sales/order?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as SalesOrderItem);
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
      this.errMsg = e instanceof Error && e.message ? e.message : L10n.str(this.getUIContext(), 'load_failed');
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

  /** 行徽章：DDL 0待审核/1已审核/2部分发货/3已发货/4已取消（详情页同款口径） */
  private salesBadge(item: SalesOrderItem): RowBadge {
    const code: number = item.status;
    let kind: string = 'neutral';
    if (code === 0 || code === 2) {
      kind = 'warning';
    } else if (code === 1 || code === 3) {
      kind = 'success';
    } else if (code === 4) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `sales_status_${code}`));
  }

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formCode = '';
    this.formCustomerId = '';
    this.formQuotationId = '';
    this.formWarehouseId = '';
    this.formTotalAmount = '';
    this.formDiscountAmount = '0';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/sales/order/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formCustomerId = row['customer_id'] != null ? String(row['customer_id']) : '';
      this.formQuotationId = row['quotation_id'] != null ? String(row['quotation_id']) : '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formTotalAmount = row['total_amount'] != null ? String(row['total_amount']) : '';
      this.formDiscountAmount = row['discount_amount'] != null ? String(row['discount_amount']) : '0';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formOrderedAt = (row['ordered_at'] as string) ?? '';
      this.formRemark = (row['remark'] as string) ?? '';
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
   * 保存：新建 POST /admin/sales/order，编辑 PUT /admin/sales/order/{id}
   */
  async save(): Promise<void> {
    if (!this.formCustomerId.trim()) {
      promptAction.showToast({ message: $r('app.string.sales_customer_id_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      customer_id: this.formCustomerId, // hashid 原样（后端解码；Number() 强转 hashid 得 NaN）
      quotation_id: this.formQuotationId ? Number(this.formQuotationId) : 0,
      warehouse_id: this.formWarehouseId ? Number(this.formWarehouseId) : 0,
      total_amount: this.formTotalAmount ? Number(this.formTotalAmount) : 0,
      discount_amount: this.formDiscountAmount ? Number(this.formDiscountAmount) : 0,
      status: this.formStatus,
      ordered_at: this.formOrderedAt,
      remark: this.formRemark
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/sales/order', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/sales/order/${this.formId}`, body);
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

  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.sales_delete_msg'),
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
   * 删除：DELETE /admin/sales/order/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/sales/order/${id}`);
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
        title: $r('app.string.sales_list_title'),
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
      EmptyView({ message: $r('app.string.sales_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'sales', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: SalesOrderItem, index: number) => {
                ListItem() {
                  ListRow({
                    // 表无 name 列：标题取客户名（leftJoin 带出），副行订单号；客户软删后 title 兜 code
                    title: item.customer_name || item.code || '-',
                    subtitle: item.customer_name ? item.code : '',
                    icon: item.customer_name || item.code || '-',
                    badge: this.salesBadge(item),
                    actions: [
                      new RowAction($r('app.string.act_view_detail'), 'text', (): void => {
                        router.pushUrl({ url: 'pages/SalesOrderDetailPage', params: { id: item.id } });
                      }),
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
              }, (item: SalesOrderItem) => item.id)

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
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'sales_form_add' : 'sales_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.sales_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_code_hint'), text: this.formCode })
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
              Text($r('app.string.sales_customer_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_customer_id_hint'), text: this.formCustomerId })
                .fontSize(15)
                // customer_id 为 hashid（可含字母）：默认键盘，勿用 Number
                .onChange((v: string) => { this.formCustomerId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_quotation_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_quotation_id_hint'), text: this.formQuotationId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formQuotationId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_warehouse_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_warehouse_id_hint'), text: this.formWarehouseId })
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
              Text($r('app.string.sales_total_amount'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_total_amount_hint'), text: this.formTotalAmount })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formTotalAmount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_discount_amount'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_discount_amount_hint'), text: this.formDiscountAmount })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formDiscountAmount = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_status'))
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
              Text($r('app.string.sales_ordered_at'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_ordered_at_hint'), text: this.formOrderedAt })
                .fontSize(15)
                .onChange((v: string) => { this.formOrderedAt = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.sales_remark'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.sales_remark_hint'), text: this.formRemark })
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
