/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { router } from '@kit.ArkUI';
import { ApiService } from '../service/ApiService';

@Entry
@Component
struct InventoryPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
  private api: ApiService = new ApiService();

  aboutToAppear() {
    this.loadData();
  }

  async loadData() {
    this.loading = true;
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>('/admin/inventory/?page=1&limit=20');
      this.items = (res.data as Record<string, Object>)['list'] as Array<Object>;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errMsg = e instanceof Error && e.message ? e.message : '加载失败';
    } finally {
      this.loading = false;
    }
  }

  build() {
    Column() {
      Text('库存管理')
        .fontSize(20)
        .fontWeight(FontWeight.Bold)
        .padding(16)
      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg) {
        Column({ space: 16 }) {
          Text(this.errMsg)
            .fontSize(14)
            .fontColor('#999999')
          Button('重试')
            .fontSize(14)
            .fontColor('#FFFFFF')
            .backgroundColor('#1677FF')
            .onClick(() => this.loadData())
        }
        .justifyContent(FlexAlign.Center)
        .width('100%')
        .layoutWeight(1)
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
