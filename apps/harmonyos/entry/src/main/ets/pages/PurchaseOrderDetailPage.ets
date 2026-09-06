/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 采购订单详情（只读，联动批）：GET /admin/purchase/order/{id}
// 头卡 = 单号 + 状态胶囊 + 平字段；明细卡 = items[]（后端 show 已嵌）。

import router from '@ohos.router';
import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';
import { LoadingView, ErrorView } from '../common/Components';
import { TopBar } from '../common/TopBar';
import { DetailCard, DetailRow } from '../common/DetailCard';
import { RowBadge, kindTint, kindInk } from '../common/ListRow';
import { RefDialog, RefField } from '../common/RefDialog';

interface PurchaseItemRow {
  product_name: string;
  product_code: string;
  product_id: string; // hashid（后端 show items 编码），行点击 → 商品引用弹层
  sku_id: string;
  quantity: number;
  price: number;
  amount: number;
  unit: string;
}

@Entry
@Component
struct PurchaseOrderDetailPage {
  @State row: Record<string, Object> = {};
  @State items: Array<PurchaseItemRow> = [];
  @State loading: boolean = true;
  @State errMsg: string = '';
  private detailId: string = ''; // 路由收参缓存：失败重试不再依赖 row['id']（失败后行为空）
  private api: ApiService = new ApiService();
  dialogController?: CustomDialogController;

  aboutToAppear(): void {
    const params = router.getParams() as Record<string, string>;
    this.detailId = (params && params['id']) || '';
    this.loadData(this.detailId);
  }

  private async loadData(id: string): Promise<void> {
    this.loading = true;
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/purchase/order/${id}`);
      this.row = res.data as Record<string, Object>;
      this.items = ((this.row['items'] as Array<Object>) ?? [])
        .map((it: Object) => it as PurchaseItemRow);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = msg.length > 0 ? msg : L10n.str(this.getUIContext(), 'load_detail_failed');
    } finally {
      this.loading = false;
    }
  }

  /** 状态胶囊：DDL 0待审核/1已审核/2部分收货/3已收货/4已取消 */
  private statusBadge(code: number): RowBadge {
    let kind: string = 'neutral';
    if (code === 0) {
      kind = 'warning';
    } else if (code === 1 || code === 3) {
      kind = 'success';
    } else if (code === 2) {
      kind = 'warning';
    } else if (code === 4) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `purchase_status_${code}`));
  }

  private fmtMoney(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : n.toFixed(2);
  }

  private num(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : String(n);
  }

  private hint(v: Object): string {
    return v != null && String(v).length > 0 ? String(v) : '-';
  }

  /** 引用弹层：供应商/商品只读卡（key 白名单与后端 show 下发键对齐，缺键显 '-'） */
  private openRef(title: string, url: string, fields: Array<RefField>): void {
    this.dialogController = new CustomDialogController({
      builder: RefDialog({ title: title, url: url, fields: fields }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  private openSupplierRef(): void {
    const id = String(this.row['supplier_id'] ?? '');
    if (id.length === 0) {
      return;
    }
    this.openRef(L10n.str(this.getUIContext(), 'ref_supplier_title'), `supplier/${id}`, [
      new RefField('code', L10n.str(this.getUIContext(), 'ref_code')),
      new RefField('name', L10n.str(this.getUIContext(), 'ref_name')),
      new RefField('contact_person', L10n.str(this.getUIContext(), 'ref_contact')),
      new RefField('address', L10n.str(this.getUIContext(), 'ref_address')),
      new RefField('tax_number', L10n.str(this.getUIContext(), 'ref_tax_no')),
      new RefField('remark', L10n.str(this.getUIContext(), 'ref_remark'))
    ]);
  }

  private openProductRef(id: Object): void {
    const pid = String(id ?? '');
    if (pid.length === 0) {
      return;
    }
    this.openRef(L10n.str(this.getUIContext(), 'ref_product_title'), `product/${pid}`, [
      new RefField('code', L10n.str(this.getUIContext(), 'product_code')),
      new RefField('name', L10n.str(this.getUIContext(), 'product_name')),
      new RefField('spec', L10n.str(this.getUIContext(), 'product_spec')),
      new RefField('unit', L10n.str(this.getUIContext(), 'product_unit')),
      new RefField('description', L10n.str(this.getUIContext(), 'product_description'))
    ]);
  }

  @Builder
  itemLine(item: PurchaseItemRow, isLast: boolean) {
    Row() {
      Column() {
        Text(item.product_name || '-')
          .fontSize(14)
          .fontWeight(600)
          .fontColor($r('app.color.text_primary'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%')

        Text(this.itemSpec(item))
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      Text(this.fmtMoney(item.amount))
        .fontSize(14)
        .fontWeight(600)
        .fontColor($r('app.color.text_primary'))
        .margin({ left: 12 })
    }
    .width('100%')
    .padding({ top: 8, bottom: 8 })
    .border({ width: { bottom: isLast ? 0 : 0.5 }, color: $r('app.color.divider') })
    .onClick(() => {
      // 行点击 → 商品引用弹层（product_id 为 hashid；商品缺失为 null 时不可点）
      if (item.product_id != null && String(item.product_id).length > 0) {
        this.openProductRef(item.product_id);
      }
    })
  }

  private itemSpec(item: PurchaseItemRow): string {
    const parts: Array<string> = [];
    if (item.product_code) {
      parts.push(item.product_code);
    }
    const qty = this.num(item.quantity);
    const price = this.num(item.price);
    parts.push(`${qty} × ${price}${item.unit ? ' ' + item.unit : ''}`);
    return parts.join(' · ');
  }

  build() {
    Column() {
      TopBar({ title: $r('app.string.purchase_order_detail_title') })

      if (this.loading) {
        LoadingView()
      } else if (this.errMsg !== '') {
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(this.detailId); } })
      } else {
        Scroll() {
          Column({ space: 12 }) {
            DetailCard({ title: L10n.str(this.getUIContext(), 'sec_basic') }) {
              Column() {
                Row() {
                  Text(this.hint(this.row['code']))
                    .fontSize(16)
                    .fontWeight(FontWeight.Bold)
                    .fontColor($r('app.color.text_primary'))
                    .layoutWeight(1)

                  Row() {
                    Text(L10n.str(this.getUIContext(), `purchase_status_${Number(this.row['status'] ?? 0)}`))
                      .fontSize(12)
                      .fontColor(kindInk(this.statusBadge(Number(this.row['status'] ?? 0)).kind))
                  }
                  .backgroundColor(kindTint(this.statusBadge(Number(this.row['status'] ?? 0)).kind))
                  .borderRadius(10)
                  .padding({ left: 8, right: 8, top: 3, bottom: 3 })
                  .margin({ left: 8 })
                }
                .width('100%')
                .margin({ bottom: 12 })

                Column({ space: 16 }) {
                  Row() {
                    Text(L10n.str(this.getUIContext(), 'purchase_d_supplier'))
                      .fontSize(12)
                      .fontColor($r('app.color.text_hint'))
                      .layoutWeight(1)
                      .height(20)

                    if (this.row['supplier_id'] != null) {
                      Text(this.hint(this.row['supplier_name']))
                        .fontSize(14)
                        .fontColor($r('app.color.primary'))
                        .height(32)
                        .onClick(() => {
                          this.openSupplierRef();
                        })
                    } else {
                      Text(this.hint(this.row['supplier_name']))
                        .fontSize(14)
                        .fontColor($r('app.color.text_primary'))
                        .height(32)
                    }
                  }
                  .width('100%')

                  DetailRow({ label: L10n.str(this.getUIContext(), 'purchase_d_apply'), value: this.num(this.row['apply_id']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'purchase_d_warehouse'), value: this.num(this.row['warehouse_id']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'purchase_d_total'), value: this.fmtMoney(this.row['total_amount']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'purchase_d_ordered_at'), value: this.hint(this.row['ordered_at']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'purchase_d_remark'), value: this.hint(this.row['remark']) })
                }
                .width('100%')
              }
              .width('100%')
            }

            DetailCard({ title: L10n.str(this.getUIContext(), 'sec_items') }) {
              Column() {
                if (this.items.length === 0) {
                  Text('-')
                    .fontSize(14)
                    .fontColor($r('app.color.text_primary'))
                    .width('100%')
                } else {
                  ForEach(this.items, (item: PurchaseItemRow, index: number) => {
                    this.itemLine(item, index === this.items.length - 1)
                  }, (item: PurchaseItemRow, index: number) => `${index}_${item.product_code}`)
                }
              }
              .width('100%')
            }
          }
          .width('100%')
        }
        .layoutWeight(1)
        .scrollBar(BarState.Off)
        .padding({ left: 12, right: 12, top: 12, bottom: 12 })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }
}
