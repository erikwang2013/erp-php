/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowBadge, ListSectionHeader } from '../../common/ListRow';

@Entry
@Component
struct TrackingPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
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
      const res = await this.api.get<Record<string, Object>>(`/admin/tms/tracking?page=${this.page}&limit=${this.limit}`);
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

  /** 行徽章：status_code 语义 kind（picked_up/in_transit/out_for_delivery 途中 warning、
   *  delivered success、exception danger、未知 neutral）——label 直显原始码，不造码表文案 */
  private trackingBadge(item: Record<string, Object>): RowBadge {
    const code = (item['status_code'] as string) ?? '';
    let kind: string = 'neutral';
    if (code === 'delivered') {
      kind = 'success';
    } else if (code === 'exception') {
      kind = 'danger';
    } else if (code === 'picked_up' || code === 'in_transit' || code === 'out_for_delivery') {
      kind = 'warning';
    }
    return new RowBadge(kind, code);
  }

  /** 副行：发生地点 · 事件时间（raw 展示不造键） */
  private rowSubtitle(item: Record<string, Object>): string {
    const parts: string[] = [];
    const loc = (item['location'] as string) ?? '';
    const evt = (item['event_time'] as string) ?? '';
    if (loc.length > 0) parts.push(loc);
    if (evt.length > 0) parts.push(evt);
    return parts.join(' · ');
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.tracking_list_title'),
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
            ListSectionHeader({ moduleKey: 'tms', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: Record<string, Object>, index: number) => {
                  ListItem() {
                    ListRow({
                      // TmsTrackingEvent 实列无 name/code：标题=事件描述，徽章=status_code，
                      // 副行=地点 · 时间（shipment_id 有 show 可跳留待批6）
                      title: ((item['description'] as string) || (item['status_code'] as string) || '-') as string,
                      subtitle: this.rowSubtitle(item),
                      icon: (item['status_code'] as string) || '-',
                      badge: this.trackingBadge(item),
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
