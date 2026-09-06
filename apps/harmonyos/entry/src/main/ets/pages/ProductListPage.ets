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
 * 商品列表项（与后端 /admin/product 返回字段对齐）
 */
interface ProductItem {
  id: string;
  code: string;
  name: string;
  barcode: string;
  spec: string;
  unit: string;
  category_id: number;
  brand_id: number;
  status: number;
  description: string;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  code: string | number;
  name: string | number;
  barcode: string | number;
  spec: string | number;
  unit: string | number;
  category_id: string | number;
  brand_id: string | number;
  status: string | number;
  description: string | number;
}

@Entry
@Component
struct ProductListPage {
  // 列表状态
  @State items: Array<ProductItem> = [];
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
  @State formBarcode: string = '';
  @State formSpec: string = '';
  @State formUnit: string = '';
  @State formCategoryId: string = '';
  @State formBrandId: string = '';
  @State formStatus: number = 1;
  @State formDescription: string = '';

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
      const res = await this.api.get<Record<string, Object>>(`/admin/product?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as ProductItem);
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
  private productBadge(item: ProductItem): RowBadge {
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
    this.formName = '';
    this.formBarcode = '';
    this.formSpec = '';
    this.formUnit = '';
    this.formCategoryId = '';
    this.formBrandId = '';
    this.formStatus = 1;
    this.formDescription = '';
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
      const res = await this.api.get<Record<string, Object>>(`/admin/product/${id}`);
      const row = res.data as Record<string, Object>;
      this.formCode = (row['code'] as string) ?? '';
      this.formName = (row['name'] as string) ?? '';
      this.formBarcode = (row['barcode'] as string) ?? '';
      this.formSpec = (row['spec'] as string) ?? '';
      this.formUnit = (row['unit'] as string) ?? '';
      this.formCategoryId = row['category_id'] != null ? String(row['category_id']) : '';
      this.formBrandId = row['brand_id'] != null ? String(row['brand_id']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
      this.formDescription = (row['description'] as string) ?? '';
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: L10n.str(this.getUIContext(), 'load_detail_failed') });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/product，编辑 PUT /admin/product/{id}
   * 必填校验与后端 ProductController::store 规则对齐（name/code/category_id/unit）
   */
  async save(): Promise<void> {
    if (!this.formName.trim()) {
      promptAction.showToast({ message: $r('app.string.product_name_required') });
      return;
    }
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: $r('app.string.product_code_required') });
      return;
    }
    if (!this.formCategoryId.trim()) {
      promptAction.showToast({ message: $r('app.string.product_category_id_required') });
      return;
    }
    if (!this.formUnit.trim()) {
      promptAction.showToast({ message: $r('app.string.product_unit_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      code: this.formCode,
      name: this.formName,
      barcode: this.formBarcode,
      spec: this.formSpec,
      unit: this.formUnit,
      category_id: this.formCategoryId ? Number(this.formCategoryId) : 0,
      brand_id: this.formBrandId ? Number(this.formBrandId) : 0,
      status: this.formStatus,
      description: this.formDescription
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/product', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/product/${this.formId}`, body);
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
      promptAction.showToast({ message: L10n.str(this.getUIContext(), 'save_failed') });
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
        message: $r('app.string.product_delete_msg'),
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
   * 删除：DELETE /admin/product/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/product/${id}`);
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
        title: $r('app.string.product_list_title'),
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
      EmptyView({ message: $r('app.string.product_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'product', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: ProductItem, index: number) => {
                ListItem() {
                  ListRow({
                    title: item.name || item.code || '-',
                    subtitle: L10n.str(this.getUIContext(), 'product_code_fmt', item.code || '-'),
                    icon: item.name || item.code || '-',
                    badge: this.productBadge(item),
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
              }, (item: ProductItem) => item.id)

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
        DetailCard({ title: L10n.str(this.getUIContext(), this.formMode === 'create' ? 'product_form_add' : 'product_form_edit') }) {
          Column() {

            Row() {
              Text($r('app.string.product_name'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_name_hint'), text: this.formName })
                .fontSize(15)
                .onChange((v: string) => { this.formName = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_code'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_code_hint'), text: this.formCode })
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
              Text($r('app.string.product_category_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_category_id_hint'), text: this.formCategoryId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formCategoryId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_brand_id'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_brand_id_hint'), text: this.formBrandId })
                .fontSize(15)
                .type(InputType.Number)
                .onChange((v: string) => { this.formBrandId = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_barcode'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_barcode_hint'), text: this.formBarcode })
                .fontSize(15)
                .onChange((v: string) => { this.formBarcode = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_spec'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_spec_hint'), text: this.formSpec })
                .fontSize(15)
                .onChange((v: string) => { this.formSpec = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_unit'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_unit_hint'), text: this.formUnit })
                .fontSize(15)
                .onChange((v: string) => { this.formUnit = v; })
                .layoutWeight(1)
            }
            .width('100%')
            .constraintSize({ minHeight: 56 })
            .backgroundColor($r('app.color.surface'))
            .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
            .alignItems(VerticalAlign.Center)

            Row() {
              Text($r('app.string.product_status'))
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
              Text($r('app.string.product_description'))
              .fontSize(15)
              .fontColor($r('app.color.text_primary'))
              .width(80)

              TextInput({ placeholder: $r('app.string.product_description_hint'), text: this.formDescription })
                .fontSize(15)
                .onChange((v: string) => { this.formDescription = v; })
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
