/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';
import { ErrorView, EmptyView } from '../common/Components';
import { TopBar } from '../common/TopBar';
import { ListRow, RowBadge, ListSectionHeader } from '../common/ListRow';

/**
 * 库存列表（后端 /admin/inventory 行键：id hashid + product_id/sku_id/warehouse_id/
 * location_id int + batch_code/quantity/cost_price + product_name/product_code/
 * warehouse_name leftJoin 带出；表无 name/code/status 幻列，勿再读旧键）
 */
@Entry
@Component
struct InventoryPage {
  @State items: Array<Record<string, Object>> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
  @State isRefreshing: boolean = false; // 刷新进行中
  @State total: number = 0;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

  aboutToAppear() {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false) {
    if (isRefresh) {
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.errMsg = '';
    try {
      // 无尾斜杠：webman 路由按无尾斜杠注册，'/inventory/' 会 404
      const res = await this.api.get<Record<string, Object>>(`/admin/inventory?page=1&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Record<string, Object>>) ?? [];
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

  /** 数量徽章：neutral 灰胶囊（库存数量字段本身，无单位列可标） */
  private quantityBadge(item: Record<string, Object>): RowBadge {
    return new RowBadge('neutral', item['quantity'] != null ? String(item['quantity']) : '');
  }

  /** 副行：仓库名 · 批次号 · 成本单价（有则拼接，raw 展示不造键） */
  private rowSubtitle(item: Record<string, Object>): string {
    const parts: string[] = [];
    const wh = (item['warehouse_name'] as string) ?? '';
    const batch = (item['batch_code'] as string) ?? '';
    const cost = item['cost_price'] != null ? String(item['cost_price']) : '';
    if (wh.length > 0) parts.push(wh);
    if (batch.length > 0) parts.push(batch);
    if (cost.length > 0) parts.push(cost);
    return parts.join(' · ');
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.inventory_list_title'),
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
            // inventory 未收录于 moduleAccent，走默认模块色（chart_1）
            ListSectionHeader({ count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: Record<string, Object>, index: number) => {
                  ListItem() {
                    ListRow({
                      // product_name 为业务名（真列经 join 带出），软删后兜 product_code
                      title: ((item['product_name'] as string) || (item['product_code'] as string) || '-') as string,
                      subtitle: this.rowSubtitle(item),
                      icon: ((item['product_name'] as string) || (item['product_code'] as string) || '-') as string,
                      badge: this.quantityBadge(item),
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
