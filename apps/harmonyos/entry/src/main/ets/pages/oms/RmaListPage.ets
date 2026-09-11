/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView, EmptyView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowAction, RowBadge, ListSectionHeader } from '../../common/ListRow';

interface RmaRow {
  id: string;
  code: string;
  order_id: number;
  type: number;
  status: number;
  reason: string;
  refund_amount: number;
  created_at: string;
}

@Entry
@Component
struct RmaListPage {
  @State items: Array<RmaRow> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 非空表示加载失败，展示真实错误信息
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
    // 详情页动作（批准/拒绝/收货/退款）返回后静默刷新
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/rma?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = ((data['list'] as Array<Object>) ?? []).map((it: Object) => it as RmaRow);
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4: 原无错误处理（失败会静默成空列表）；此处展示真实错误不吞，UNAUTHORIZED/空 message 用友好文案（§0.8）
      const raw: string = e instanceof Error ? e.message : '';
      // 服务端 message 直通；空/UNAUTHORIZED 用本地化友好文案兜底
      this.errMsg = raw.length > 0 && raw !== 'UNAUTHORIZED' ? raw : L10n.str(this.getUIContext(), 'login_expired');
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  /** 行徽章：0 待审核/2 已退回/3 已收货 warning、1 已批准/4 已退款 success、5 已拒绝 danger */
  private badge(item: RmaRow): RowBadge {
    const code: number = item.status;
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

  private typeLabel(item: RmaRow): string {
    return L10n.str(this.getUIContext(), `rma_type_${item.type}`);
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.rma_title'),
        onRefresh: (): void => { this.loadData(true); },
        refreshing: this.loading || this.isRefreshing,
      })

      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // 失败错误位：真实文案 + 重试（ErrorView 内建）
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
      } else if (this.items.length === 0) {
        EmptyView();
      } else {
        // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
        Column() {
          Column() {
            ListSectionHeader({ moduleKey: 'oms', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: RmaRow, index: number) => {
                  ListItem() {
                    ListRow({
                      title: item.code || item.id || '-',
                      subtitle: this.rowSub(item),
                      icon: item.code || item.id || '-',
                      badge: this.badge(item),
                      actions: [
                        new RowAction($r('app.string.act_view_detail'), 'primary', (): void => {
                          router.pushUrl({ url: 'pages/oms/RmaDetailPage', params: { id: item.id } });
                        })
                      ],
                      isLast: index === this.items.length - 1
                    })
                  }
                }, (item: RmaRow) => item.id)
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

  /** 副行：类型 + 原因（reason 可能为空） */
  private rowSub(item: RmaRow): string {
    const reason: string = item.reason ? ` · ${item.reason}` : '';
    return `${this.typeLabel(item)}${reason}`;
  }
}
