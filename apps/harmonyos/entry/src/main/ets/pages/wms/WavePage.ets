/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView, EmptyView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowBadge, ListSectionHeader } from '../../common/ListRow';

/** 波次行（与 /admin/wms/wave 列表字段对齐：无 join，仅波次表自身列） */
interface WaveRow {
  id: string;
  code: string;
  status: number;
  scheduled_at: string;
}

@Entry
@Component
struct WavePage {
  @State items: Array<WaveRow> = [];
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
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/wave?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = ((data['list'] as Array<Object>) ?? []).map((it: Object) => it as WaveRow);
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

  /** 行徽章：0 待处理 neutral、1 处理中 warning、2 已完成 success；
   *  发布波次需 items 明细（无行内读取端点）→ 行内不接线（债务登记） */
  private badge(item: WaveRow): RowBadge {
    const code: number = item.status;
    let kind: string = 'neutral';
    if (code === 1) {
      kind = 'warning';
    } else if (code === 2) {
      kind = 'success';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `wave_status_${code}`));
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.wave_list_title'),
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
            ListSectionHeader({ moduleKey: 'wms', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: WaveRow, index: number) => {
                  ListItem() {
                    ListRow({
                      title: item.code || item.id || '-',
                      subtitle: item.scheduled_at || '',
                      icon: item.code || item.id || '-',
                      badge: this.badge(item),
                      isLast: index === this.items.length - 1
                    })
                  }
                }, (item: WaveRow) => item.id)
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
