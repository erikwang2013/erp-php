/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';

@Entry
@Component
struct WavePage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  // M4：加载失败时的提示文案（不吞错误，展示可用 message）
  @State errMsg: string = '';
  @State isRefreshing: boolean = false; // 刷新进行中
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
      this.items = (data['list'] as Array<Object>) ?? [];
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4：加载失败不吞错误——有可用 message 直接展示；UNAUTHORIZED/空消息映射为登录过期提示
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = (msg === '' || msg === 'UNAUTHORIZED') ? L10n.str(this.getUIContext(), 'login_expired') : msg;
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  build() {
    Column() {
      Row() {
        Text($r('app.string.wave_list_title'))
          .fontSize(20)
          .fontWeight(FontWeight.Bold)
        Blank()
        Button($r('app.string.refresh'))
          .fontSize(13)
          .fontColor($r('app.color.primary'))
          .backgroundColor($r('app.color.primary_bg'))
          .borderRadius(6)
          .height(32)
          .enabled(!this.loading && !this.isRefreshing)
          .onClick(() => { this.loadData(true); })
      }
      .width('100%')
      .padding(16)
      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // M4：错误展示位（样式随页面现状）
        Text(this.errMsg)
          .fontSize(14)
          .fontColor($r('app.color.text_hint'))
          .textAlign(TextAlign.Center)
          .width('100%')
          .margin({ top: 48 })
      } else {
        Refresh({ refreshing: $$this.isRefreshing }) {
          List() {
            ForEach(this.items, (item: Record<string, Object>) => {
              ListItem() {
                Row() {
                  Text((item['name'] as string) ?? (item['code'] as string) ?? '')
                    .fontSize(14)
                    .layoutWeight(1)
                  Text((item['code'] as string) ?? '')
                    .fontSize(12)
                    .fontColor($r('app.color.text_hint'))
                }
                .width('100%')
                .padding(12)
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
    }
    .width('100%')
    .height('100%')
  }
}
