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
import { PasswordConfirmBody } from '../model/DataModels';
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../common/ListRow';
import { PurchaseOrderFormView } from './PurchaseOrderFormView';
import { OrderItemDraft, nonEmptyItems } from '../common/OrderItemsEditor';

/**
 * 采购订单列表项（与后端 /admin/purchase/order 返回字段对齐）
 */
interface PurchaseOrderItem {
  id: string;
  code: string;
  supplier_name: string; // leftJoin 带出；供应商软删后为 null（表无 name 列）
  supplier_id: string; // hashid（后端 encodeIds 编码，保存原样回传）
  apply_id: string; // hashid（后端 encodeIds 编码；原为裸雪花 ID，>2^53 会被 JS 丢精度）
  warehouse_id: string; // hashid（同上）
  total_amount: number;
  status: number;
  ordered_at: string;
  remark: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  supplier_id: string; // hashid 原样回传（后端解码落库 int）
  apply_id: string;
  warehouse_id: string;
  total_amount: string;
  status: number;
  ordered_at: string;
  remark: string;
  // 明细（仅新建时下发）：update 是「下发即整表替换」，编辑态表单不回填既有明细，
  // 故编辑态整个键不送（送空数组会被后端挡回 422）
  items?: Array<OrderItemDraft>;
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

  // 表单字段（与后端 fillable 字段名严格一致；单号由后端 doc_code() 生成，故无 code 项）
  @State formSupplierId: string = '';
  @State formApplyId: string = '';
  @State formWarehouseId: string = '';
  @State formTotalAmount: string = '';
  @State formStatus: number = 0; // 0=待审核（DDL 默认值；原写死 1=已审核，新建即假审核）
  @State formOrderedAt: string = '';
  @State formRemark: string = '';
  // 明细录入（仅新建期下发）：至少一行占位，用户不必先点「添加明细」
  @State formItems: Array<OrderItemDraft> = [new OrderItemDraft()];

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

  /** 行徽章：启用 success / 停用 danger（kind 同源驱动行 icon 圆徽色） */
  /** 行徽章：DDL 0待审核/1已审核/2部分收货/3已收货/4已取消（详情页同款口径） */
  private purchaseBadge(item: PurchaseOrderItem): RowBadge {
    const code: number = item.status;
    let kind: string = 'neutral';
    if (code === 0 || code === 2) {
      kind = 'warning';
    } else if (code === 1 || code === 3) {
      kind = 'success';
    } else if (code === 4) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `purchase_status_${code}`));
  }

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formSupplierId = '';
    this.formApplyId = '';
    this.formWarehouseId = '';
    this.formTotalAmount = '';
    this.formStatus = 0;
    this.formOrderedAt = '';
    this.formRemark = '';
    // 明细回到一行空占位：上次填写（或上次创建成功）的值不能漂到下一单
    this.formItems = [new OrderItemDraft()];
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
      this.formSupplierId = row['supplier_id'] != null ? String(row['supplier_id']) : '';
      this.formApplyId = row['apply_id'] != null ? String(row['apply_id']) : '';
      this.formWarehouseId = row['warehouse_id'] != null ? String(row['warehouse_id']) : '';
      this.formTotalAmount = row['total_amount'] != null ? String(row['total_amount']) : '';
      this.formStatus = (row['status'] as number) ?? 0;
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
   * 保存：新建 POST /admin/purchase/order，编辑 PUT /admin/purchase/order/{id}
   */
  async save(): Promise<void> {
    if (!this.formSupplierId.trim()) {
      promptAction.showToast({ message: $r('app.string.purchase_supplier_id_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      supplier_id: this.formSupplierId, // hashid 原样（后端解码；Number() 强转 hashid 得 NaN）
      // apply_id/warehouse_id 同为 hashid 串（后端 encodeIds 编码），原样回传：
      // 走 Number() 得 NaN → JSON.stringify 变 null → 后端按缺省落 0，关联被静默丢弃
      apply_id: this.formApplyId.trim(), // 留空即未关联（后端按 0 处理）
      warehouse_id: this.formWarehouseId.trim(),
      // 金额原样字符串下发（后端 numeric 校验 + bc_norm 落 DECIMAL(12,2)）：经 Number() 会引入浮点误差
      total_amount: this.formTotalAmount.trim(),
      status: this.formStatus,
      ordered_at: this.formOrderedAt,
      remark: this.formRemark
    };

    // 新建必须先有明细：后端 store 带 items 时以明细汇总回写主表金额，且收货端按明细行
    // 反查商品 ——「有单无明细」的单收货必然 422，故在这里挡回而不是让它落库
    if (this.formMode === 'create') {
      const lines = nonEmptyItems(this.formItems);
      if (lines.length === 0) {
        promptAction.showToast({ message: $r('app.string.order_items_required') });
        this.isSubmitting = false;
        return;
      }
      body.items = lines;
    }

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/purchase/order', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/purchase/order/${this.formId}`, body);
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

  private deleteDialogController?: CustomDialogController;

  /**
   * 删除入口：ConfirmDialog + 管理员密码二次确认（后端 destroy 走 confirmPassword，
   * 不带 password 一律 422「密码验证失败」，故必须走 needPassword 通道）
   */
  confirmDelete(id: string): void {
    this.deleteDialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.purchase_delete_msg'),
        needPassword: true,
        confirmText: $r('app.string.delete'),
        onConfirmPwd: (pwd: string) => this.deleteItem(id, pwd),
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.deleteDialogController.open();
  }

  /**
   * 删除：DELETE /admin/purchase/order/{id}（body 带管理员密码）
   */
  async deleteItem(id: string, password: string): Promise<void> {
    try {
      // ArkTS 严格模式不接受匿名对象字面量（arkts-no-untyped-obj-literals），须用已声明的接口
      const body: PasswordConfirmBody = { password: password };
      await this.api.delete<Object>(`/admin/purchase/order/${id}`, body);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // 422（密码错等）交回 ConfirmDialog：toast 后端 message 且弹窗保留可重试
      throw new Error(e instanceof Error ? e.message : '');
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛试点：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.purchase_list_title'),
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
      EmptyView({ message: $r('app.string.purchase_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'purchase', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: PurchaseOrderItem, index: number) => {
                ListItem() {
                  ListRow({
                    // 表无 name 列：标题取供应商名（leftJoin 带出），副行采购单号；供应商软删后 title 兜 code
                    title: item.supplier_name || item.code || '-',
                    subtitle: item.supplier_name ? item.code : '',
                    icon: item.supplier_name || item.code || '-',
                    badge: this.purchaseBadge(item),
                    actions: [
                      new RowAction($r('app.string.act_view_detail'), 'text', (): void => {
                        router.pushUrl({ url: 'pages/PurchaseOrderDetailPage', params: { id: item.id } });
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
              }, (item: PurchaseOrderItem) => item.id)

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
   * 表单视图（新建/编辑共用）：渲染抽到 PurchaseOrderFormView，父页保留字段状态与保存逻辑
   */
  @Builder
  buildForm() {
    PurchaseOrderFormView({
      formMode: this.formMode,
      isSubmitting: this.isSubmitting,
      formSupplierId: this.formSupplierId,
      formApplyId: this.formApplyId,
      formWarehouseId: this.formWarehouseId,
      formTotalAmount: this.formTotalAmount,
      formStatus: this.formStatus,
      formOrderedAt: this.formOrderedAt,
      formRemark: this.formRemark,
      formItems: this.formItems,
      onSubmit: () => { this.save(); },
      onCancel: () => { this.showForm = false; }
    })
  }
}
