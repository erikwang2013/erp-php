/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { ErrorView } from '../../common/Components';

@Entry
@Component
struct MrpPage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
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
      const res = await this.api.get<Record<string, Object>>(`/admin/mfg/mrp?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4: 错误不再吞掉——真实 message 优先展示，空信息/UNAUTHORIZED 给友好提示
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = (msg === '' || msg === 'UNAUTHORIZED') ? '登录已过期，请重新登录' : msg;
    } finally {
      this.loading = false;
    }
  }

  build() {
    Column() {
      Text('MRP计划')
        .fontSize(20)
        .fontWeight(FontWeight.Bold)
        .padding(16)
      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // 失败错误位：真实文案 + 重试（ErrorView 内建）
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
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
