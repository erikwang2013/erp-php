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
struct FreightRatePage {
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
      const res = await this.api.get<Record<string, Object>>(`/admin/tms/freight-rate?page=${this.page}&limit=${this.limit}`);
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

  /** 行标题：起讫组合（区域优先，缺则国 ISO 码；双侧缺失兜底单侧/'-'） */
  private routeTitle(item: Record<string, Object>): string {
    const o = ((item['origin_zone'] as string) || (item['origin_country'] as string) || '').trim();
    const d = ((item['dest_zone'] as string) || (item['dest_country'] as string) || '').trim();
    if (o.length > 0 && d.length > 0) {
      return `${o} → ${d}`;
    }
    return o.length > 0 ? o : (d.length > 0 ? d : '-');
  }

  /** 状态徽章：status 0=禁用 1=启用（DDL 注释），复用既有启用/禁用文案 */
  private rateBadge(item: Record<string, Object>): RowBadge {
    if ((item['status'] as number) === 1) {
      return new RowBadge('success', $r('app.string.status_enabled'));
    }
    return new RowBadge('danger', $r('app.string.status_disabled'));
  }

  /** 副行：重量段 · 起步+每公斤单价 · 币种（raw 展示不造键） */
  private rowSubtitle(item: Record<string, Object>): string {
    const from = item['weight_from_kg'] != null ? String(item['weight_from_kg']) : '';
    const to = item['weight_to_kg'] != null ? String(item['weight_to_kg']) : '';
    const base = item['base_rate'] != null ? String(item['base_rate']) : '';
    const per = item['per_kg_rate'] != null ? String(item['per_kg_rate']) : '';
    const ccy = (item['currency'] as string) ?? '';
    const parts: string[] = [];
    if (from.length > 0 || to.length > 0) {
      parts.push(`${from}~${to}kg`);
    }
    if (base.length > 0 && per.length > 0) {
      parts.push(`${base}+${per}/kg`);
    }
    if (ccy.length > 0) {
      parts.push(ccy);
    }
    return parts.join(' · ');
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.freight_rate_list_title'),
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
                      // TmsFreightRate 实列无 name/code：起讫路线 + 费率 + 启用态徽章
                      title: this.routeTitle(item),
                      subtitle: this.rowSubtitle(item),
                      icon: this.routeTitle(item), // ListRow 仅取首字符，同路线同色系圆徽
                      badge: this.rateBadge(item),
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
