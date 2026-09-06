/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';

@Entry
@Component
struct RmaListPage {
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
      const res = await this.api.get<Record<string, Object>>(`/admin/oms/rma?page=${this.page}&limit=${this.limit}`);
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

  build() {
    Column() {
      Row() {
        Text($r('app.string.rma_title'))
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
        // M4: 错误占位（原无错误位，按页面现有简单风格最小补齐）
        Column() {
          Text(this.errMsg)
            .fontSize(14)
            .fontColor($r('app.color.text_hint'))
          Button($r('app.string.retry'))
            .fontSize(14)
            .fontColor($r('app.color.primary'))
            .backgroundColor(Color.Transparent)
            .margin({ top: 12 })
            .onClick(() => { this.loadData(); })
        }
        .width('100%')
        .layoutWeight(1)
        .justifyContent(FlexAlign.Center)
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
