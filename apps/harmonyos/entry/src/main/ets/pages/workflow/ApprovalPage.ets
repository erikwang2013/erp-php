/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowAction, RowBadge, ListSectionHeader } from '../../common/ListRow';

/** 单据类型 → l10n 键（registry 注释：sales_order/purchase_apply/purchase_order/expense/leave/other） */
const TARGET_TYPE_KEYS: Record<string, string> = {
  'sales_order': 'target_type_sales_order',
  'purchase_apply': 'target_type_purchase_apply',
  'purchase_order': 'target_type_purchase_order',
  'expense': 'target_type_expense',
  'leave': 'target_type_leave',
  'other': 'target_type_other'
};

@Entry
@Component
struct ApprovalPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
  @State isRefreshing: boolean = false; // 刷新进行中
  @State total: number = 0;
  @State page: number = 1;
  private pageShown: boolean = false; // 首次 onPageShow 不重复加载
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

  aboutToAppear() {
    this.loadData();
  }

  onPageShow(): void {
    // 详情页动作（通过/驳回/撤回）返回后静默刷新待办
    if (this.pageShown) {
      this.loadData(true);
    }
    this.pageShown = true;
  }

  async loadData(isRefresh: boolean = false) {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/approval/my?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4: 错误不再吞掉——真实 message 优先展示，空信息/UNAUTHORIZED 给友好提示
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = (msg === '' || msg === 'UNAUTHORIZED') ? L10n.str(this.getUIContext(), 'login_expired') : msg;
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  /** 标题：target_type 语义文案；表外值直显原文（不'-'，真实数据优先） */
  private typeLabel(item: Record<string, Object>): string {
    const raw = ((item['target_type'] as string) ?? '').trim();
    if (raw.length === 0) {
      return '-';
    }
    const key = TARGET_TYPE_KEYS[raw];
    if (key !== undefined) {
      return L10n.str(this.getUIContext(), key);
    }
    return raw;
  }

  /** 徽章：/approval/my 仅返回审批中实例（status=0），label 审批中 */
  private pendingBadge(): RowBadge {
    return new RowBadge('warning', $r('app.string.approval_status_pending'));
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.approval_list_title'),
        onRefresh: (): void => { this.loadData(true); },
        refreshing: this.loading || this.isRefreshing,
      })

      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // 失败错误位：真实文案 + 重试（ErrorView 内建）
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
      } else {
        // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
        Column() {
          Column() {
            ListSectionHeader({ moduleKey: 'workflow', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: Record<string, Object>, index: number) => {
                  ListItem() {
                    ListRow({
                      // ApprovalInstance 实列无 name/code：标题=单据类型，副行=提交时间
                      title: this.typeLabel(item),
                      subtitle: ((item['submitted_at'] as string) ?? '') as string,
                      icon: this.typeLabel(item),
                      badge: this.pendingBadge(),
                      actions: [
                        new RowAction($r('app.string.act_view_detail'), 'primary', (): void => {
                          router.pushUrl({ url: 'pages/workflow/ApprovalDetailPage',
                            params: { id: String(item['id'] ?? '') } });
                        })
                      ],
                      isLast: index === this.items.length - 1
                    })
                  }
                })
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
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }
}
