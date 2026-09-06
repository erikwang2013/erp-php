/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 履约详情（联动批）：GET /admin/oms/fulfillment/{id}
// 头卡 = 仓库/状态/时间；WMS/TMS 任务引用（pick_task_id/pack_task_id/shipment_id 为
// hashid 或 null）经 RefDialog 只读弹层查看（不建页面）；关联订单跳 OMS 订单详情；
// items[] 明细（分配/拣货/打包/发货数量）。

import router from '@ohos.router';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { DetailCard, DetailRow } from '../../common/DetailCard';
import { RowBadge } from '../../common/ListRow';
import { RefDialog, RefField } from '../../common/RefDialog';

interface FulfillItemRow {
  product_name: string;
  product_code: string;
  allocated_quantity: number;
  picked_quantity: number;
  packed_quantity: number;
  shipped_quantity: number;
}

@Entry
@Component
struct FulfillmentDetailPage {
  @State row: Record<string, Object> = {};
  @State items: Array<FulfillItemRow> = [];
  @State loading: boolean = true;
  @State errMsg: string = '';
  private detailId: string = '';
  private api: ApiService = new ApiService();
  private dialogController?: CustomDialogController;

  aboutToAppear(): void {
    const params = router.getParams() as Record<string, string>;
    this.detailId = (params && params['id']) || '';
    this.loadData();
  }

  private async loadData(): Promise<void> {
    this.loading = true;
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/fulfillment/${this.detailId}`);
      this.row = res.data as Record<string, Object>;
      this.items = ((this.row['items'] as Array<Object>) ?? [])
        .map((it: Object) => it as FulfillItemRow);
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

  private statusBadge(code: number): RowBadge {
    let kind: string = 'neutral';
    if (code >= 1 && code <= 4) {
      kind = 'warning';
    } else if (code === 5) {
      kind = 'success';
    } else if (code === 6) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `fulfillment_status_${code}`));
  }

  private hint(v: Object): string {
    return v != null && String(v).length > 0 ? String(v) : '-';
  }

  private num(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : String(n);
  }

  /** 引用弹层：WMS/TMS 任务（pick/pack/shipment show 端点实证） */
  openRef(title: string, url: string, fields: Array<RefField>): void {
    this.dialogController = new CustomDialogController({
      builder: RefDialog({ title: title, url: url, fields: fields }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  openTaskRef(kind: string, id: Object): void {
    const taskId = String(id ?? '');
    if (taskId.length === 0) {
      return;
    }
    if (kind === 'pick') {
      this.openRef(L10n.str(this.getUIContext(), 'pick_list_title'), `wms/pick/${taskId}`,
        [new RefField('code', L10n.str(this.getUIContext(), 'pick_list_title')),
        new RefField('status', L10n.str(this.getUIContext(), 'approval_d_status'))]);
    } else if (kind === 'pack') {
      this.openRef(L10n.str(this.getUIContext(), 'pack_list_title'), `wms/pack/${taskId}`,
        [new RefField('code', L10n.str(this.getUIContext(), 'pack_list_title')),
        new RefField('status', L10n.str(this.getUIContext(), 'approval_d_status'))]);
    } else {
      this.openRef(L10n.str(this.getUIContext(), 'fulfillment_d_shipment'), `tms/shipment/${taskId}`,
        [new RefField('code', L10n.str(this.getUIContext(), 'fulfillment_d_shipment')),
        new RefField('status', L10n.str(this.getUIContext(), 'approval_d_status'))]);
    }
  }

  @Builder
  itemLine(item: FulfillItemRow, isLast: boolean) {
    Row() {
      Column() {
        Text(item.product_name || '-')
          .fontSize(14)
          .fontWeight(600)
          .fontColor($r('app.color.text_primary'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%')

        Text(L10n.str(this.getUIContext(), 'ff_item_qty_fmt',
          this.num(item.allocated_quantity), this.num(item.picked_quantity),
          this.num(item.packed_quantity), this.num(item.shipped_quantity)))
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%')
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      if (item.product_code) {
        Text(item.product_code)
          .fontSize(12)
          .fontColor($r('app.color.text_secondary'))
          .margin({ left: 8 })
      }
    }
    .width('100%')
    .padding({ top: 8, bottom: 8 })
    .border({ width: { bottom: isLast ? 0 : 0.5 }, color: $r('app.color.divider') })
  }

  build() {
    Column() {
      TopBar({ title: $r('app.string.fulfillment_detail_title') })

      if (this.loading) {
        LoadingView()
      } else if (this.errMsg !== '') {
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
      } else {
        Scroll() {
          Column({ space: 12 }) {
            DetailCard({ title: L10n.str(this.getUIContext(), 'sec_basic') }) {
              Column() {
                Column({ space: 16 }) {
                  DetailRow({ label: L10n.str(this.getUIContext(), 'fulfillment_d_warehouse'),
                    value: this.hint(this.row['warehouse_name']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'fulfillment_d_status'),
                    value: this.statusBadge(Number(this.row['status'] ?? 0)).label as string })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_created'),
                    value: this.hint(this.row['created_at']) })
                }
                .width('100%')
              }
              .width('100%')
            }

            // 任务引用卡：跳 OMS 订单详情（oms_order_id 为 hashid）；WMS/TMS 任务弹层
            DetailCard({ title: L10n.str(this.getUIContext(), 'fulfillment_title') }) {
              Column({ space: 16 }) {
                Row() {
                  Text(L10n.str(this.getUIContext(), 'fulfillment_d_oms'))
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .layoutWeight(1)
                    .height(20)

                  Text($r('app.string.act_view_detail'))
                    .fontSize(13)
                    .fontColor($r('app.color.primary'))
                    .height(32)
                    .onClick(() => {
                      router.pushUrl({ url: 'pages/oms/OrderDetailPage',
                        params: { id: String(this.row['oms_order_id'] ?? '') } });
                    })
                }
                .width('100%')

                Row() {
                  Text(L10n.str(this.getUIContext(), 'fulfillment_d_pick'))
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .layoutWeight(1)
                    .height(20)

                  if (this.row['pick_task_id'] != null) {
                    Text($r('app.string.act_view_detail'))
                      .fontSize(13)
                      .fontColor($r('app.color.primary'))
                      .height(32)
                      .onClick(() => {
                        this.openTaskRef('pick', this.row['pick_task_id']);
                      })
                  } else {
                    Text('-')
                      .fontSize(14)
                      .fontColor($r('app.color.text_primary'))
                      .height(20)
                  }
                }
                .width('100%')

                Row() {
                  Text(L10n.str(this.getUIContext(), 'fulfillment_d_pack'))
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .layoutWeight(1)
                    .height(20)

                  if (this.row['pack_task_id'] != null) {
                    Text($r('app.string.act_view_detail'))
                      .fontSize(13)
                      .fontColor($r('app.color.primary'))
                      .height(32)
                      .onClick(() => {
                        this.openTaskRef('pack', this.row['pack_task_id']);
                      })
                  } else {
                    Text('-')
                      .fontSize(14)
                      .fontColor($r('app.color.text_primary'))
                      .height(20)
                  }
                }
                .width('100%')

                Row() {
                  Text(L10n.str(this.getUIContext(), 'fulfillment_d_shipment'))
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                    .layoutWeight(1)
                    .height(20)

                  if (this.row['shipment_id'] != null) {
                    Text($r('app.string.act_view_detail'))
                      .fontSize(13)
                      .fontColor($r('app.color.primary'))
                      .height(32)
                      .onClick(() => {
                        this.openTaskRef('shipment', this.row['shipment_id']);
                      })
                  } else {
                    Text('-')
                      .fontSize(14)
                      .fontColor($r('app.color.text_primary'))
                      .height(20)
                  }
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
                  ForEach(this.items, (item: FulfillItemRow, index: number) => {
                    this.itemLine(item, index === this.items.length - 1)
                  }, (item: FulfillItemRow, index: number) => `${index}_${item.product_code}`)
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
