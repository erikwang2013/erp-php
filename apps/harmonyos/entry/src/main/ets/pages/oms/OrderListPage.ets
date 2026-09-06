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
 * OMS 订单列表项（与后端 /admin/oms/order 返回字段对齐）
 */
interface OmsOrderItem {
  id: string;
  code: string;
  order_id: number;
  channel: string;
  channel_order_no: string;
  channel_store: string;
  fulfillment_status: number;
  payment_status: number;
  shipping_method: string;
  shipping_fee: number;
  buyer_message: string;
  seller_note: string;
  priority: number;
  hold_until: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  order_id: string | number;
  channel: string | number;
  channel_order_no: string | number;
  channel_store: string | number;
  fulfillment_status: string | number;
  payment_status: string | number;
  shipping_method: string | number;
  shipping_fee: string | number;
  buyer_message: string | number;
  seller_note: string | number;
  priority: string | number;
  hold_until: string | number;
}

@Entry
@Component
struct OrderListPage {
  // 列表状态
  @State items: Array<OmsOrderItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4: 错误展示文案（取后端/网络 message，空则保底）
  @State isRefreshing: boolean = false;
  private pageShown: boolean = false; // 首次 onPageShow 不重复加载
  private readonly limit: number = 20;
  private api: ApiService = new ApiService();

  // 表单状态
  @State showForm: boolean = false;
  @State formMode: string = 'create'; // create | edit
  @State formId: string = '';
  @State isSubmitting: boolean = false;

  // 表单字段（与后端 fillable 字段名严格一致）
  @State formCode: string = '';
  @State formOrderId: string = '';
  @State formChannel: string = 'manual';
  @State formChannelOrderNo: string = '';
  @State formChannelStore: string = '';
  @State formFulfillmentStatus: string = '';
  @State formPaymentStatus: string = '';
  @State formShippingMethod: string = '';
  @State formShippingFee: string = '';
  @State formBuyerMessage: string = '';
  @State formSellerNote: string = '';
  @State formPriority: string = '5';
  @State formHoldUntil: string = '';

  aboutToAppear(): void {
    this.loadData();
  }

  onPageShow(): void {
    // 详情页动作（取消订单后返回）静默刷新列表
    if (this.pageShown) {
      this.loadData(true);
    }
    this.pageShown = true;
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/order?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as OmsOrderItem);
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
      // 服务端 message 直通；空/UNAUTHORIZED 用本地化友好文案兜底
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
    this.formCode = '';
    this.formOrderId = '';
    this.formChannel = 'manual';
    this.formChannelOrderNo = '';
    this.formChannelStore = '';
    this.formFulfillmentStatus = '';
    this.formPaymentStatus = '';
    this.formShippingMethod = '';
    this.formShippingFee = '';
    this.formBuyerMessage = '';
    this.formSellerNote = '';
    this.formPriority = '5';
    this.formHoldUntil = '';
    this.showForm = true;
  }

  /**
   * 履约状态码 → 本地化文案；后端生命周期仅产出 0/1/4/5，
   * 其余码值（含表单手工 2/3）直出数字，后端新语义时补映射
   */
  private ffLabel(ui: UIContext, code: number): string {
    if (code === 0) {
      return L10n.str(ui, 'status_ff_0');
    }
    if (code === 1) {
      return L10n.str(ui, 'status_ff_1');
    }
    if (code === 4) {
      return L10n.str(ui, 'status_ff_4');
    }
    if (code === 5) {
      return L10n.str(ui, 'status_ff_5');
    }
    return String(code);
  }

  /** 履约徽章：码→kind（0 待分配 neutral、1 履约中 warning、4/5 已发货/已签收 success；表外码回退 neutral） */
  private ffBadge(code: number): RowBadge {
    let kind: string = 'neutral';
    if (code === 1) {
      kind = 'warning';
    } else if (code === 4 || code === 5) {
      kind = 'success';
    }
    const label = L10n.str(this.getUIContext(), 'order_fulfillment_fmt',
      this.ffLabel(this.getUIContext(), code));
    return new RowBadge(kind, label);
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/order/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formOrderId = row['order_id'] != null ? String(row['order_id']) : '';
      this.formChannel = (row['channel'] as string) ?? 'manual';
      this.formChannelOrderNo = (row['channel_order_no'] as string) ?? '';
      this.formChannelStore = (row['channel_store'] as string) ?? '';
      this.formFulfillmentStatus = row['fulfillment_status'] != null ? String(row['fulfillment_status']) : '';
      this.formPaymentStatus = row['payment_status'] != null ? String(row['payment_status']) : '';
      this.formShippingMethod = (row['shipping_method'] as string) ?? '';
      this.formShippingFee = row['shipping_fee'] != null ? String(row['shipping_fee']) : '';
      this.formBuyerMessage = (row['buyer_message'] as string) ?? '';
      this.formSellerNote = (row['seller_note'] as string) ?? '';
      this.formPriority = row['priority'] != null ? String(row['priority']) : '5';
      this.formHoldUntil = (row['hold_until'] as string) ?? '';
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
   * 保存：新建 POST /admin/oms/order，编辑 PUT /admin/oms/order/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: $r('app.string.order_code_required') });
      return;
    }
    if (!this.formOrderId.trim()) {
      promptAction.showToast({ message: $r('app.string.order_sales_id_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      order_id: this.formOrderId ? Number(this.formOrderId) : 0,
      channel: this.formChannel,
      channel_order_no: this.formChannelOrderNo,
      channel_store: this.formChannelStore,
      fulfillment_status: this.formFulfillmentStatus ? Number(this.formFulfillmentStatus) : 0,
      payment_status: this.formPaymentStatus ? Number(this.formPaymentStatus) : 0,
      shipping_method: this.formShippingMethod,
      shipping_fee: this.formShippingFee ? Number(this.formShippingFee) : 0,
      buyer_message: this.formBuyerMessage,
      seller_note: this.formSellerNote,
      priority: this.formPriority ? Number(this.formPriority) : 5,
      hold_until: this.formHoldUntil
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/oms/order', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/oms/order/${this.formId}`, body);
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
   * 删除：确认对话框后 DELETE /admin/oms/order/{id}
   */
  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.order_delete_msg'),
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
   * 删除：DELETE /admin/oms/order/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/oms/order/${id}`);
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
        title: $r('app.string.order_list_title'),
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
      EmptyView({ message: $r('app.string.order_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'oms', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: OmsOrderItem, index: number) => {
                ListItem() {
                  ListRow({
                    title: item.channel_order_no || item.code
                      || (item.order_id != null ? String(item.order_id) : '') || '-',
                    subtitle: L10n.str(this.getUIContext(), 'order_channel_fmt', item.channel || '-'),
                    icon: item.channel_order_no || item.code
                      || (item.order_id != null ? String(item.order_id) : '') || '-',
                    badge: this.ffBadge(item.fulfillment_status),
                    actions: [
                      new RowAction($r('app.string.act_view_detail'), 'text', (): void => {
                        router.pushUrl({ url: 'pages/oms/OrderDetailPage', params: { id: item.id } });
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
              }, (item: OmsOrderItem) => item.id)

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
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'order_form_add' : 'order_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.order_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_code_hint'), text: this.formCode })
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
              Text($r('app.string.order_sales_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_sales_id_hint'), text: this.formOrderId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formOrderId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_hint'), text: this.formChannel })
                .fontSize(15)
                .onChange((v: string) => { this.formChannel = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel_no'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_no_hint'), text: this.formChannelOrderNo })
                .fontSize(15)
                .onChange((v: string) => { this.formChannelOrderNo = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_channel_store'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_channel_store_hint'), text: this.formChannelStore })
                .fontSize(15)
                .onChange((v: string) => { this.formChannelStore = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_ff_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_range_0to3_hint'), text: this.formFulfillmentStatus })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formFulfillmentStatus = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_payment_status'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_range_0to3_hint'), text: this.formPaymentStatus })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formPaymentStatus = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_shipping_method'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_shipping_method_hint'), text: this.formShippingMethod })
                .fontSize(15)
                .onChange((v: string) => { this.formShippingMethod = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_shipping_fee'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_shipping_fee_hint'), text: this.formShippingFee })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formShippingFee = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_buyer_note'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_buyer_note_hint'), text: this.formBuyerMessage })
                .fontSize(15)
                .onChange((v: string) => { this.formBuyerMessage = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_seller_note'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_seller_note_hint'), text: this.formSellerNote })
                .fontSize(15)
                .onChange((v: string) => { this.formSellerNote = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_priority'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_priority_hint'), text: this.formPriority })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formPriority = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.order_hold_until'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.order_hold_until_hint'), text: this.formHoldUntil })
                .fontSize(15)
                .onChange((v: string) => { this.formHoldUntil = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
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
        .padding({ left: 12, right: 12, top: 24, bottom: 32 })
      }
      .width('100%')
      .padding({ left: 12, right: 12, top: 12, bottom: 12 })
    }
    .width('100%')
    .layoutWeight(1)
    .backgroundColor($r('app.color.bg_page'))
  }
}
