/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView, EmptyView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowAction, RowBadge, ListSectionHeader } from '../../common/ListRow';

@Entry
@Component
struct FulfillmentListPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 非空表示加载失败，展示真实错误信息
  @State isRefreshing: boolean = false; // 刷新进行中
  @State total: number = 0;
  @State page: number = 1;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

  aboutToAppear() {
    this.loadData();
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/fulfillment?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
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

  /** 状态徽章：DDL 注释 0=待处理 1=分配中 2=拣货中 3=打包中 4=待发货 5=已发货 6=已取消；
   *  进行态（1-4）warning、终态已发货 success/已取消 danger、未开始 neutral */
  private ffBadge(item: Record<string, Object>): RowBadge {
    const code: number = (item['status'] as number) ?? 0;
    let kind: string = 'neutral';
    if (code >= 1 && code <= 4) {
      kind = 'warning';
    } else if (code === 5) {
      kind = 'success';
    } else if (code === 6) {
      kind = 'danger';
    }
    // 动态资源名（$r 仅支持字面量）：经 L10n 按当前语言解析，缺键回退 key 不崩
    return new RowBadge(kind, L10n.str(this.getUIContext(), `fulfillment_status_${code}`));
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.fulfillment_title'),
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
                ForEach(this.items, (item: Record<string, Object>, index: number) => {
                  ListItem() {
                    ListRow({
                      // OmsFulfillment 实列无 name/code：标题=发货仓库（join 带出），副行=oms 订单 hashid
                      title: ((item['warehouse_name'] as string) || (item['oms_order_id'] as string) || '-') as string,
                      subtitle: item['warehouse_name'] != null ? ((item['oms_order_id'] as string) ?? '') : '',
                      icon: ((item['warehouse_name'] as string) || (item['oms_order_id'] as string) || '-') as string,
                      badge: this.ffBadge(item),
                      actions: [
                        new RowAction($r('app.string.act_view_detail'), 'primary', (): void => {
                          router.pushUrl({ url: 'pages/oms/FulfillmentDetailPage',
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
