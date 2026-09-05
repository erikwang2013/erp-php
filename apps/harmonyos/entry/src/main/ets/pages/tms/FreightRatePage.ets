/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';

@Entry
@Component
struct FreightRatePage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 非空表示加载失败，展示真实错误信息
  @State total: number = 0;
  @State page: number = 1;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

  aboutToAppear() {
    this.loadData();
  }

  async loadData() {
    this.loading = true;
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/tms/freight-rate?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4: 原无错误处理（失败会静默成空列表）；此处展示真实错误不吞，UNAUTHORIZED/空 message 用友好文案（§0.8）
      const raw: string = e instanceof Error ? e.message : '';
      this.errMsg = raw.length > 0 && raw !== 'UNAUTHORIZED' ? raw : '登录已过期，请重新登录';
    } finally {
      this.loading = false;
    }
  }

  build() {
    Column() {
      Text('运费费率')
        .fontSize(20)
        .fontWeight(FontWeight.Bold)
        .padding(16)
      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // M4: 错误占位（原无错误位，按页面现有简单风格最小补齐）
        Column() {
          Text(this.errMsg)
            .fontSize(14)
            .fontColor($r('app.color.text_hint'))
          Button('重试')
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
      }
    }
    .width('100%')
    .height('100%')
  }
}
