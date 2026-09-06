/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { router } from '@kit.ArkUI';
import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';

@Entry
@Component
struct InventoryPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
  @State isRefreshing: boolean = false; // 刷新进行中
  private api: ApiService = new ApiService();

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
      const res = await this.api.get<Record<string, Object>>('/admin/inventory/?page=1&limit=20');
      this.items = (res.data as Record<string, Object>)['list'] as Array<Object>;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errMsg = e instanceof Error && e.message ? e.message : L10n.str(this.getUIContext(), 'load_failed');
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  build() {
    Column() {
      Row() {
        Text($r('app.string.inventory_list_title'))
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
      } else if (this.errMsg) {
        Column({ space: 16 }) {
          Text(this.errMsg)
            .fontSize(14)
            .fontColor($r('app.color.text_hint'))
          Button($r('app.string.retry'))
            .fontSize(14)
            .fontColor($r('app.color.text_on_primary'))
            .backgroundColor($r('app.color.primary'))
            .onClick(() => this.loadData())
        }
        .justifyContent(FlexAlign.Center)
        .width('100%')
        .layoutWeight(1)
      } else {
        Refresh({ refreshing: $$this.isRefreshing }) {
          List() {
            ForEach(this.items, (item: Object) => {
              ListItem() {
                Text(JSON.stringify(item))
                  .padding(12)
                  .fontSize(14)
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
