/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 售后单详情（联动批）：GET /admin/oms/rma/{id}
// 头卡 = 单号 + 类型/状态胶囊 + 平字段；明细卡 items[]（product_name join）；
// 动作按状态接线（route.php/控制器实证）：
//   0 待审核 → 批准 POST approve {approved:true} / 拒绝 POST approve {approved:false}
//   1 已批准 → 退款 POST refund（1|3 → 4）
//   2 已退回 → 收货 POST receive（2 → 3）
//   3 已收货 → 退款 POST refund
// 终态（4 已退款/5 已拒绝）无动作；动作成功原地刷新。

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';
import { DetailCard, DetailRow } from '../../common/DetailCard';
import { RowBadge, kindTint, kindInk } from '../../common/ListRow';

interface RmaItemRow {
  product_name: string;
  product_code: string;
  quantity: number;
  price: number;
  amount: number;
  unit: string;
}

/** 状态驱动动作条描述（ArkTS 禁内联对象字面量作类型，必须显式声明） */
interface RmaAct {
  label: string;
  confirm: string;
  kind: string;
  suffix: string;
  approved: boolean;
}

/** POST body：approved=false 为拒绝；其余动作 true 与缺省同义（服务端容缺） */
class RmaActBody {
  approved: boolean;
  constructor(approved: boolean) {
    this.approved = approved;
  }
}

@Entry
@Component
struct RmaDetailPage {
  @State row: Record<string, Object> = {};
  @State items: Array<RmaItemRow> = [];
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/rma/${this.detailId}`);
      this.row = res.data as Record<string, Object>;
      this.items = ((this.row['items'] as Array<Object>) ?? [])
        .map((it: Object) => it as RmaItemRow);
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

  /** 状态胶囊 kind：0 待审核 warning、1/4 已批准/已退款 success、2/3 进行 warning、5 已拒绝 danger */
  private statusBadge(code: number): RowBadge {
    let kind: string = 'neutral';
    if (code === 0 || code === 2 || code === 3) {
      kind = 'warning';
    } else if (code === 1 || code === 4) {
      kind = 'success';
    } else if (code === 5) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `rma_status_${code}`));
  }

  private hint(v: Object): string {
    return v != null && String(v).length > 0 ? String(v) : '-';
  }

  private fmtMoney(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : n.toFixed(2);
  }

  private num(v: Object): string {
    const n = Number(v);
    return (v == null || Number.isNaN(n)) ? '-' : String(n);
  }

  /** 带确认的入口：确认 → POST（approve 带 approved 布尔） */
  confirmAct(title: string, message: string, kind: string, urlSuffix: string, approved: boolean): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: title,
        message: message,
        kind: kind,
        confirmText: title,
        onConfirm: () => {
          this.postAct(urlSuffix, new RmaActBody(approved));
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  private async postAct(urlSuffix: string, body: Object): Promise<void> {
    if (this.isActing) {
      return;
    }
    this.isActing = true;
    try {
      await this.api.post<Object>(`/admin/oms/rma/${this.detailId}${urlSuffix}`, body);
      promptAction.showToast({ message: $r('app.string.op_success') });
      await this.loadData();
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

  /** 状态驱动动作条 */
  private currentActions(): Array<RmaAct> {
    const code: number = Number(this.row['status'] ?? 0);
    const list: Array<RmaAct> = [];
    if (code === 0) {
      list.push({ label: L10n.str(this.getUIContext(), 'rma_approve'),
        confirm: L10n.str(this.getUIContext(), 'rma_confirm_approve'), kind: 'primary', suffix: '/approve', approved: true });
      list.push({ label: L10n.str(this.getUIContext(), 'rma_reject'),
        confirm: L10n.str(this.getUIContext(), 'rma_confirm_reject'), kind: 'danger', suffix: '/approve', approved: false });
    } else if (code === 1) {
      list.push({ label: L10n.str(this.getUIContext(), 'rma_refund'),
        confirm: L10n.str(this.getUIContext(), 'rma_confirm_refund'), kind: 'primary', suffix: '/refund', approved: true });
    } else if (code === 2) {
      list.push({ label: L10n.str(this.getUIContext(), 'rma_receive'),
        confirm: L10n.str(this.getUIContext(), 'rma_confirm_receive'), kind: 'primary', suffix: '/receive', approved: true });
    } else if (code === 3) {
      list.push({ label: L10n.str(this.getUIContext(), 'rma_refund'),
        confirm: L10n.str(this.getUIContext(), 'rma_confirm_refund'), kind: 'primary', suffix: '/refund', approved: true });
    }
    return list;
  }

  @Builder
  itemLine(item: RmaItemRow, isLast: boolean) {
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
  }

  private itemSpec(item: RmaItemRow): string {
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
      TopBar({ title: $r('app.string.rma_detail_title') })

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

                  Text(L10n.str(this.getUIContext(), `rma_type_${Number(this.row['type'] ?? 1)}`))
                    .fontSize(12)
                    .fontColor($r('app.color.text_secondary'))
                    .backgroundColor($r('app.color.surface_alt'))
                    .borderRadius(10)
                    .padding({ left: 8, right: 8, top: 3, bottom: 3 })
                    .margin({ left: 8 })

                  Row() {
                    Text(L10n.str(this.getUIContext(), `rma_status_${Number(this.row['status'] ?? 0)}`))
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
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_order'), value: this.hint(this.row['order_id']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_reason'), value: this.hint(this.row['reason']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_refund'), value: this.fmtMoney(this.row['refund_amount']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_fee'), value: this.fmtMoney(this.row['return_shipping_fee']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_created'), value: this.hint(this.row['created_at']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_approved'), value: this.hint(this.row['approved_at']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_returned'), value: this.hint(this.row['returned_at']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'rma_d_received'), value: this.hint(this.row['received_at']) })
                }
                .width('100%')
              }
              .width('100%')
            }

            if (this.currentActions().length > 0) {
              Row({ space: 12 }) {
                ForEach(this.currentActions(), (a: RmaAct) => {
                  Button(a.label)
                    .fontSize(14)
                    .fontWeight(FontWeight.Medium)
                    .fontColor(a.kind === 'danger' ? $r('app.color.text_on_primary') : $r('app.color.text_on_primary'))
                    .backgroundColor(a.kind === 'danger' ? $r('app.color.danger') : $r('app.color.primary'))
                    .borderRadius(8)
                    .height(44)
                    .layoutWeight(1)
                    .enabled(!this.isActing)
                    .onClick(() => {
                      // 资金/拒绝类动作走确认弹层（approve false=拒绝、refund=退款）；其余直发
                      if (a.suffix === '/approve' || a.suffix === '/refund') {
                        this.confirmAct(a.label, a.confirm, a.kind, a.suffix, a.approved);
                      } else {
                        this.postAct(a.suffix, new RmaActBody(a.approved));
                      }
                    })
                }, (a: RmaAct) => a.suffix + a.label)
              }
              .width('100%')
              .padding({ left: 12, right: 12 })
            }

            DetailCard({ title: L10n.str(this.getUIContext(), 'sec_items') }) {
              Column() {
                if (this.items.length === 0) {
                  Text('-')
                    .fontSize(14)
                    .fontColor($r('app.color.text_primary'))
                    .width('100%')
                } else {
                  ForEach(this.items, (item: RmaItemRow, index: number) => {
                    this.itemLine(item, index === this.items.length - 1)
                  }, (item: RmaItemRow, index: number) => `${index}_${item.product_code}`)
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
