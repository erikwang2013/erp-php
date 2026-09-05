/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';

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
  @State errMsg: string = '加载失败'; // M4: 错误展示文案（取后端/网络 message，空则保底）
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
      this.errMsg = e instanceof Error && e.message.length > 0 && e.message !== 'UNAUTHORIZED' ? e.message : '登录已过期，请重新登录';
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
      promptAction.showToast({ message: '加载详情失败' });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/oms/order，编辑 PUT /admin/oms/order/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: '请输入订单编码' });
      return;
    }
    if (!this.formOrderId.trim()) {
      promptAction.showToast({ message: '请输入销售订单ID' });
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
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put<Object>(`/admin/oms/order/${this.formId}`, body);
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
   * 删除：确认对话框后 DELETE /admin/oms/order/{id}
   */
  deleteItem(id: string): void {
    AlertDialog.show({
      title: '确认删除',
      message: '确定要删除该订单吗？删除后不可恢复。',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定',
        fontColor: '#FF4D4F',
        action: async () => {
          try {
            await this.api.delete<Object>(`/admin/oms/order/${id}`);
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

        Text('OMS订单管理')
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
      EmptyView({ message: '暂无订单数据' });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: OmsOrderItem) => {
            ListItem() {
              Row() {
                // 订单号与渠道
                Column() {
                  Text(item.channel_order_no || item.code || (item.order_id != null ? String(item.order_id) : '') || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`渠道: ${item.channel || '-'}`)
                    .fontSize(12)
                    .fontColor('#999999')
                    .margin({ top: 2 })
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)

                // 履约状态
                Text(`履约: ${item.fulfillment_status}`)
                  .fontSize(10)
                  .fontColor('#1677FF')
                  .backgroundColor('#E6F0FF')
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
          }, (item: OmsOrderItem) => item.id)

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
        Text(this.formMode === 'create' ? '新增订单' : '编辑订单')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        Row() {
          Text('订单编码')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '订单编码，如 OM+时间戳（必填）', text: this.formCode })
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
          Text('销售订单ID')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入销售订单ID（必填）', text: this.formOrderId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formOrderId = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('渠道')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '渠道，默认 manual', text: this.formChannel })
            .fontSize(15)
            .onChange((v: string) => { this.formChannel = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('渠道订单号')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入渠道订单号', text: this.formChannelOrderNo })
            .fontSize(15)
            .onChange((v: string) => { this.formChannelOrderNo = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('渠道店铺')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入渠道店铺', text: this.formChannelStore })
            .fontSize(15)
            .onChange((v: string) => { this.formChannelStore = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('履约状态')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '0-3，留空为0', text: this.formFulfillmentStatus })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formFulfillmentStatus = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('支付状态')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '0-3，留空为0', text: this.formPaymentStatus })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formPaymentStatus = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('配送方式')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入配送方式', text: this.formShippingMethod })
            .fontSize(15)
            .onChange((v: string) => { this.formShippingMethod = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('运费')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '数字，留空为0', text: this.formShippingFee })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formShippingFee = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('买家备注')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入买家备注', text: this.formBuyerMessage })
            .fontSize(15)
            .onChange((v: string) => { this.formBuyerMessage = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('卖家备注')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '请输入卖家备注', text: this.formSellerNote })
            .fontSize(15)
            .onChange((v: string) => { this.formSellerNote = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('优先级')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: '默认5', text: this.formPriority })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formPriority = v; })
            .layoutWeight(1)
        }
        .width('100%')
        .constraintSize({ minHeight: 56 })
        .padding({ left: 16, right: 16 })
        .backgroundColor('#FFFFFF')
        .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
        .alignItems(VerticalAlign.Center)

        Row() {
          Text('冻结时间')
          .fontSize(15)
          .fontColor('#333333')
          .width(80)

          TextInput({ placeholder: 'YYYY-MM-DD HH:mm:ss，可留空', text: this.formHoldUntil })
            .fontSize(15)
            .onChange((v: string) => { this.formHoldUntil = v; })
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
