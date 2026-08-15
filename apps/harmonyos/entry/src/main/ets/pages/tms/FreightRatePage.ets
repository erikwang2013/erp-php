/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';

@Entry
@Component
struct FreightRatePage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

  aboutToAppear() {
    this.loadData();
  }

  async loadData() {
    this.loading = true;
    try {
      const res = await this.api.get(`/admin/tms/freight-rate?page=${this.page}&limit=${this.limit}`);
      const data = res['data'] as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
      this.total = (data['total'] as number) ?? 0;
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
      } else {
        List() {
          ForEach(this.items, (item: Object) => {
            ListItem() {
              Row() {
                Text((item['name'] as string) ?? (item['code'] as string) ?? '')
                  .fontSize(14)
                  .layoutWeight(1)
                Text((item['code'] as string) ?? '')
                  .fontSize(12)
                  .fontColor('#999')
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
