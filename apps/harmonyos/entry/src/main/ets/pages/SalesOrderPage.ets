/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { router } from '@kit.ArkUI';
import { ApiService } from '../../service/ApiService';

@Entry
@Component
struct SalesOrderPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  private api: ApiService = new ApiService();

  aboutToAppear() {
    this.loadData();
  }

  async loadData() {
    this.loading = true;
    try {
      const res = await this.api.get('/admin/sales/order/?page=1&limit=20', { 'API-Version': 'v1' });
      this.items = (res['data'] as Record<string, Object>)['list'] as Array<Object>;
    } finally {
      this.loading = false;
    }
  }

  build() {
    Column() {
      Text('销售管理')
        .fontSize(20)
        .fontWeight(FontWeight.Bold)
        .padding(16)
      if (this.loading) {
        LoadingProgress()
      } else {
        List() {
          ForEach(this.items, (item: Object) => {
            ListItem() {
              Text(JSON.stringify(item))
                .padding(12)
                .fontSize(14)
            }
          })
        }
      }
    }
    .width('100%')
    .height('100%')
  }
}
