/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 审批详情（联动批）：GET /admin/approval/{id}
// 头卡 = 单据类型/流程/当前节点/状态 + 提交/完成时间；审批记录时间线卡（records[]，
// operator_name/action/comment/created_at）；动作按状态接线（审批中）：
//   通过 POST approve {comment 选填} / 驳回 POST reject {comment 必填} / 撤回 POST withdraw
// 单据引用：registry 内 target_type（sales_order/purchase_order/purchase_apply/expense）
// target_ref 为 hashid → RefDialog 只读弹层查看单据；registry 外 target_ref=null 不渲染。

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ConfirmDialog, FormDialog, FormField } from '../../common/FormDialog';
import { DetailCard, DetailRow } from '../../common/DetailCard';
import { RowBadge, kindTint, kindInk } from '../../common/ListRow';
import { RefDialog, RefField } from '../../common/RefDialog';

/** 单据类型 → 资源前缀（与后端 TARGET_REGISTRY 键集镜像；registry 外无下钻） */
const TARGET_PREFIX: Record<string, string> = {
  'sales_order': 'sales/order',
  'purchase_apply': 'purchase/apply',
  'purchase_order': 'purchase/order',
  'expense': 'finance/expense'
};

/** 单据类型 → l10n 键（与列表页同源） */
const TARGET_TYPE_KEYS: Record<string, string> = {
  'sales_order': 'target_type_sales_order',
  'purchase_apply': 'target_type_purchase_apply',
  'purchase_order': 'target_type_purchase_order',
  'expense': 'target_type_expense',
  'leave': 'target_type_leave',
  'other': 'target_type_other'
};

/** POST body：审批意见（ArkTS 禁裸对象字面量传参） */
class CommentBody {
  comment: string;
  constructor(comment: string) {
    this.comment = comment;
  }
}

interface RecordRow {
  action: number;
  comment: string;
  operator_name: string;
  node_id: string;
  created_at: string;
}

@Entry
@Component
struct ApprovalDetailPage {
  @State row: Record<string, Object> = {};
  @State records: Array<RecordRow> = [];
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
      const res = await this.api.get<Record<string, Object>>(`/admin/approval/${this.detailId}`);
      this.row = res.data as Record<string, Object>;
      this.records = ((this.row['records'] as Array<Object>) ?? [])
        .map((it: Object) => it as RecordRow);
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

  private typeLabel(): string {
    const raw = String(this.row['target_type'] ?? '').trim();
    if (raw.length === 0) {
      return '-';
    }
    const key = TARGET_TYPE_KEYS[raw];
    return key !== undefined ? L10n.str(this.getUIContext(), key) : raw;
  }

  /** 状态徽章：0 审批中（warning）/1 已通过/2 已驳回/3 已撤回 */
  private statusBadge(code: number): RowBadge {
    let kind: string = 'neutral';
    if (code === 0) {
      kind = 'warning';
    } else if (code === 1) {
      kind = 'success';
    } else if (code === 2 || code === 3) {
      kind = 'danger';
    }
    const key = code === 0 ? 'approval_status_pending'
      : (code === 1 ? 'approval_status_passed'
        : (code === 2 ? 'approval_status_rejected' : 'approval_status_withdrawn'));
    return new RowBadge(kind, L10n.str(this.getUIContext(), key));
  }

  private hint(v: Object): string {
    return v != null && String(v).length > 0 ? String(v) : '-';
  }

  private isPending(): boolean {
    return Number(this.row['status'] ?? -1) === 0;
  }

  private refUrl(): string {
    const prefix = TARGET_PREFIX[String(this.row['target_type'] ?? '')];
    const ref = this.row['target_ref'];
    return (prefix !== undefined && ref != null) ? `${prefix}/${String(ref)}` : '';
  }

  /** 单据弹层字段集：sales/purchase_order 已跳真实详情页；仅余申请/费用等无详情页域走弹层（缺键显 '-'） */
  private refFields(): Array<RefField> {
    return [
      new RefField('code', L10n.str(this.getUIContext(), 'ref_code')),
      new RefField('created_at', L10n.str(this.getUIContext(), 'oms_d_created'))
    ];
  }

  openRef(): void {
    const url = this.refUrl();
    if (url.length === 0) {
      return;
    }
    // registry 单据域 → 跳真实详情页（与批5 Flutter 同构）；申请/费用等无详情页域 → 只读弹层
    const t = String(this.row['target_type'] ?? '');
    if (t === 'sales_order' || t === 'purchase_order') {
      const page = t === 'sales_order' ? 'pages/SalesOrderDetailPage' : 'pages/PurchaseOrderDetailPage';
      router.pushUrl({ url: page, params: { id: String(this.row['target_ref'] ?? '') } });
      return;
    }
    this.dialogController = new CustomDialogController({
      builder: RefDialog({ title: L10n.str(this.getUIContext(), 'act_view_ref'), url: url, fields: this.refFields() }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  /** 通过/驳回共用意见弹层（required = 驳回必填） */
  openCommentDialog(mode: string): void {
    const isReject: boolean = mode === 'reject';
    const fields: Array<FormField> = [
      new FormField('comment', L10n.str(this.getUIContext(), 'approval_comment'), 'text',
        isReject, [], L10n.str(this.getUIContext(), 'approval_comment_hint'))
    ];
    this.dialogController = new CustomDialogController({
      builder: FormDialog({
        title: isReject ? $r('app.string.act_reject') : $r('app.string.act_approve'),
        fields: fields,
        submitText: isReject ? $r('app.string.act_reject') : $r('app.string.act_approve'),
        onConfirm: (values: Array<string>) => {
          this.postComment(isReject ? '/reject' : '/approve', values.length > 0 ? values[0] : '');
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  private async postComment(urlSuffix: string, comment: string): Promise<void> {
    if (this.isActing) {
      return;
    }
    this.isActing = true;
    try {
      await this.api.post<Object>(`/admin/approval/${this.detailId}${urlSuffix}`, new CommentBody(comment));
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

  confirmWithdraw(): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.act_withdraw'),
        message: $r('app.string.approval_confirm_withdraw'),
        kind: 'danger',
        onConfirm: () => {
          this.postComment('/withdraw', '');
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  @Builder
  recordLine(item: RecordRow, isLast: boolean) {
    Column() {
      Row() {
        Text(item.operator_name || '-')
          .fontSize(14)
          .fontWeight(600)
          .fontColor($r('app.color.text_primary'))
          .layoutWeight(1)

        Text(L10n.str(this.getUIContext(), `approval_record_action_${item.action}`))
          .fontSize(12)
          .fontColor(kindInk(item.action === 1 ? 'success' : (item.action === 2 ? 'danger' : 'neutral')))
          .backgroundColor(kindTint(item.action === 1 ? 'success' : (item.action === 2 ? 'danger' : 'neutral')))
          .borderRadius(10)
          .padding({ left: 8, right: 8, top: 3, bottom: 3 })

        Text(item.created_at || '-')
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .margin({ left: 8 })
      }
      .width('100%')

      if (item.comment && item.comment.length > 0) {
        Text(item.comment)
          .fontSize(13)
          .fontColor($r('app.color.text_secondary'))
          .lineHeight(19)
          .width('100%')
          .margin({ top: 4 })
      }
    }
    .alignItems(HorizontalAlign.Start)
    .width('100%')
    .padding({ top: 8, bottom: 8 })
    .border({ width: { bottom: isLast ? 0 : 0.5 }, color: $r('app.color.divider') })
  }

  build() {
    Column() {
      TopBar({ title: $r('app.string.approval_detail_title') })

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
                  Text(this.typeLabel())
                    .fontSize(16)
                    .fontWeight(FontWeight.Bold)
                    .fontColor($r('app.color.text_primary'))
                    .layoutWeight(1)

                  Row() {
                    Text(L10n.str(this.getUIContext(), this.statusBadge(Number(this.row['status'] ?? 0)).label as string))
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
                  DetailRow({ label: L10n.str(this.getUIContext(), 'approval_d_type'), value: this.typeLabel() })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'approval_d_workflow'), value: this.hint(this.row['workflow_name']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'approval_d_node'), value: this.hint(this.row['current_node_name']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'approval_d_submitted'), value: this.hint(this.row['submitted_at']) })
                  DetailRow({ label: L10n.str(this.getUIContext(), 'approval_d_completed'), value: this.hint(this.row['completed_at']) })
                }
                .width('100%')
              }
              .width('100%')
            }

            // 单据引用弹层（registry 内类型 target_ref 非空才渲染；审批中/终态均可查看）
            if (this.refUrl().length > 0) {
              Row() {
                Text($r('app.string.act_view_ref'))
                  .fontSize(14)
                  .fontColor($r('app.color.primary'))
                  .height(44)
                  .backgroundColor($r('app.color.primary_bg'))
                  .borderRadius(8)
                  .textAlign(TextAlign.Center)
                  .layoutWeight(1)
                  .onClick(() => {
                    this.openRef();
                  })
              }
              .width('100%')
              .padding({ left: 12, right: 12 })
            }

            if (this.isPending()) {
              Row({ space: 12 }) {
                Button($r('app.string.act_approve'))
                  .fontSize(14)
                  .fontWeight(FontWeight.Medium)
                  .fontColor($r('app.color.text_on_primary'))
                  .backgroundColor($r('app.color.primary'))
                  .borderRadius(8)
                  .height(44)
                  .layoutWeight(1)
                  .enabled(!this.isActing)
                  .onClick(() => {
                    this.openCommentDialog('approve');
                  })

                Button($r('app.string.act_reject'))
                  .fontSize(14)
                  .fontWeight(FontWeight.Medium)
                  .fontColor($r('app.color.text_on_primary'))
                  .backgroundColor($r('app.color.danger'))
                  .borderRadius(8)
                  .height(44)
                  .layoutWeight(1)
                  .enabled(!this.isActing)
                  .onClick(() => {
                    this.openCommentDialog('reject');
                  })
              }
              .width('100%')
              .padding({ left: 12, right: 12 })

              Row() {
                Button($r('app.string.act_withdraw'))
                  .fontSize(14)
                  .fontColor($r('app.color.text_secondary'))
                  .backgroundColor($r('app.color.surface_alt'))
                  .borderRadius(8)
                  .height(44)
                  .layoutWeight(1)
                  .enabled(!this.isActing)
                  .onClick(() => {
                    this.confirmWithdraw();
                  })
              }
              .width('100%')
              .padding({ left: 12, right: 12 })
            }

            DetailCard({ title: L10n.str(this.getUIContext(), 'approval_records_title') }) {
              Column() {
                if (this.records.length === 0) {
                  Text('-')
                    .fontSize(14)
                    .fontColor($r('app.color.text_primary'))
                    .width('100%')
                } else {
                  ForEach(this.records, (item: RecordRow, index: number) => {
                    this.recordLine(item, index === this.records.length - 1)
                  }, (item: RecordRow, index: number) => `${index}_${item.created_at}`)
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
