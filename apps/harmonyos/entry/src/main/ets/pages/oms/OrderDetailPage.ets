/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// OMS 订单详情（联动批）：GET /admin/oms/order/{id}
// 头卡 + 状态胶囊（履约/支付）；操作：状态非 4/5 可取消（POST /cancel，成功即返回列表）；
// 履约子表 GET /admin/oms/fulfillment?oms_order_id={id}（批2 过滤参数收 hashid）→ 行内跳履约详情。
// 下钻约束：oms show 仅编码 id（order_id 等 raw），销售单下钻在批7 后端补编码前不做。

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';
import { DetailCard, DetailRow } from '../../common/DetailCard';
import { ListRow, RowAction, RowBadge, kindTint, kindInk } from '../../common/ListRow';

/** POST body：取消订单零字段（ArkTS 禁裸对象字面量传参） */
class EmptyBody {
}

interface FulfillmentRow {
  id: string;
  warehouse_name: string;
  status: number;
  oms_order_id: string;
}

@Entry
@Component
struct OrderDetailPage {
  @State row: Record<string, Object> = {};
  @State fulfillments: Array<FulfillmentRow> = [];
  @State loading: boolean = true;
  @State errMsg: string = '';
  @State isActing: boolean = false;
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/order/${this.detailId}`);
      this.row = res.data as Record<string, Object>;
      const fres = await this.api.get<Record<string, Object>>(
        `/admin/oms/fulfillment?oms_order_id=${this.detailId}&page=1&limit=20`);
      const fdata = fres.data as Record<string, Object>;
      this.fulfillments = ((fdata['list'] as Array<Object>) ?? [])
        .map((it: Object) => it as FulfillmentRow);
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

  /** 履约状态（oms_order 域 0..5）：0未分配/1已分配/2拣货中/3已打包/4已发货/5已签收；
   *  后端生命周期仅产出 0/1/4/5，其余码直出数字（与列表页同口径） */
  private ffLabel(code: number): string {
    if (code === 0 || code === 1 || code === 4 || code === 5) {
      return L10n.str(this.getUIContext(), `status_ff_${code}`);
    }
    return String(code);
  }

  private ffKind(code: number): string {
    if (code === 1) {
      return 'warning';
    }
    if (code === 4 || code === 5) {
      return 'success';
    }
    return 'neutral';
  }

  private payLabel(code: number): string {
    return L10n.str(this.getUIContext(), `payment_status_${code}`);
  }

  private hint(v: Object): string {
    return v != null && String(v).length > 0 ? String(v) : '-';
  }

  private fmtMoney(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : n.toFixed(2);
  }

  /** 履约子表行徽章：批4 键语义（erp_oms_fulfillment 0..6） */
  private ffRowBadge(code: number): RowBadge {
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

  private canCancel(): boolean {
    const code: number = Number(this.row['fulfillment_status'] ?? 0);
    return code !== 4 && code !== 5;
  }

  /** 取消订单：ConfirmDialog → POST → 成功返回列表（onPageShow 刷新） */
  confirmCancel(): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.oms_cancel_confirm'),
        kind: 'danger',
        onConfirm: () => {
          this.cancelOrder();
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  private async cancelOrder(): Promise<void> {
    if (this.isActing) {
      return;
    }
    this.isActing = true;
    try {
      await this.api.post<Object>(`/admin/oms/order/${this.detailId}/cancel`, new EmptyBody());
      promptAction.showToast({ message: $r('app.string.op_success') });
      const router = this.getUIContext().getRouter();
      router.back();
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg.length > 0 ? msg : $r('app.string.op_failed') });
    } finally {
      this.isActing = false;
    }
  }

  @Builder
  statusCapsule() {
    Row() {
      Row() {
        Text(this.ffLabel(Number(this.row['fulfillment_status'] ?? 0)))
          .fontSize(12)
          .fontColor(kindInk(this.ffKind(Number(this.row['fulfillment_status'] ?? 0))))
      }
      .backgroundColor(kindTint(this.ffKind(Number(this.row['fulfillment_status'] ?? 0))))
      .borderRadius(10)
      .padding({ left: 8, right: 8, top: 3, bottom: 3 })

      Row() {
        Text(this.payLabel(Number(this.row['payment_status'] ?? 0)))
          .fontSize(12)
          .fontColor($r('app.color.text_secondary'))
      }
      .backgroundColor($r('app.color.surface_alt'))
      .borderRadius(10)
      .padding({ left: 8, right: 8, top: 3, bottom: 3 })
      .margin({ left: 8 })
    }
  }

  @Builder
  fulfillmentRow(item: FulfillmentRow, index: number) {
    ListRow({
      title: (item.warehouse_name || item.id || '-') as string,
      subtitle: (item.id || '') as string,
      icon: (item.warehouse_name || item.id || '-') as string,
      badge: this.ffRowBadge(item.status),
      actions: [
        new RowAction($r('app.string.act_view_detail'), 'primary', (): void => {
          router.pushUrl({ url: 'pages/oms/FulfillmentDetailPage', params: { id: item.id } });
        })
      ],
      isLast: index === this.fulfillments.length - 1
    })
  }

  build() {
    Column() {
      TopBar({ title: $r('app.string.oms_order_detail_title') })

      if (this.loading) {
        LoadingView()
      } else if (this.errMsg !== '') {
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
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

                  this.statusCapsule()
                }
                .width('100%')
                .margin({ bottom: 12 })

                Column({ space: 16 }) {
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_code'), value: this.hint(this.row['code']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_order'), value: this.hint(this.row['order_id']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_channel'), value: this.hint(this.row['channel']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_channel_no'), value: this.hint(this.row['channel_order_no']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_channel_store'), value: this.hint(this.row['channel_store']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_shipping_method'), value: this.hint(this.row['shipping_method']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_shipping_fee'), value: this.fmtMoney(this.row['shipping_fee']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_buyer'), value: this.hint(this.row['buyer_message']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_seller'), value: this.hint(this.row['seller_note']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_priority'), value: this.hint(this.row['priority']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_hold'), value: this.hint(this.row['hold_until']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'oms_d_created'), value: this.hint(this.row['created_at']) })
                }
                .width('100%')
              }
              .width('100%')
            }

            // 操作：状态非已发货/已签收可取消（cancel 端点零请求体实证）
            if (this.canCancel()) {
              Row() {
                Button($r('app.string.cancel'))
                  .fontSize(14)
                  .fontWeight(FontWeight.Medium)
                  .fontColor($r('app.color.text_on_primary'))
                  .backgroundColor($r('app.color.danger'))
                  .borderRadius(8)
                  .height(44)
                  .layoutWeight(1)
                  .enabled(!this.isActing)
                  .onClick(() => {
                    this.confirmCancel();
                  })
              }
              .width('100%')
              .padding({ left: 12, right: 12 })
            }

            // 履约子表（oms_order_id 过滤）
            Column() {
              DetailCard({ title: L10n.str(this.getUIContext(), 'fulfillment_title') }) {
                if (this.fulfillments.length === 0) {
                  Text('-')
                    .fontSize(14)
                    .fontColor($r('app.color.text_primary'))
                    .width('100%')
                } else {
                  ForEach(this.fulfillments, (item: FulfillmentRow, index: number) => {
                    this.fulfillmentRow(item, index)
                  }, (item: FulfillmentRow) => item.id)
                }
              }
            }
            .width('100%')
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
