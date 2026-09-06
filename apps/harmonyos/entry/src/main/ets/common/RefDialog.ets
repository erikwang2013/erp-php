/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 引用只读弹层（跨资源下钻收口设计，docs/mobile-ui-design.md §5.5/联动批）：
// 传入目标 show URL（不含 /admin 前缀）+ 字段对描述 → GET 拉头字段渲染 DetailRow 列表。
// 避免详情页连环无底洞：引用目标一律弹层查看，不建页面。
// 用法（调用方持 controller，每次打开前重建）：
//   this.dialogController = new CustomDialogController({
//     builder: RefDialog({
//       title: '关联订单', url: `oms/order/${id}`,
//       fields: [new RefField('code', '单号'), new RefField('channel', '渠道')],
//     }),
//     alignment: DialogAlignment.Center,
//   });
//   this.dialogController.open();

import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';
import { DetailRow } from './DetailCard';

/** 弹层字段描述：key = 后端下发键，label 为展示文案（已本地化的字符串） */
export class RefField {
  key: string = '';
  label: string = '';

  constructor(key: string, label: string) {
    this.key = key;
    this.label = label;
  }
}

@CustomDialog
export struct RefDialog {
  @Prop title: string = '';
  @Prop url: string = ''; // show 地址（不含 /admin 前缀），如 oms/order/x1y2
  @Prop fields: Array<RefField> = [];
  controller?: CustomDialogController;
  @State loading: boolean = true;
  @State errMsg: string = '';
  @State values: Array<string> = [];
  private api: ApiService = new ApiService();

  aboutToAppear(): void {
    this.fetchRef();
  }

  private async fetchRef(): Promise<void> {
    this.loading = true;
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/${this.url}`);
      const row = res.data as Record<string, Object>;
      const vals: Array<string> = [];
      for (const f of this.fields) {
        const raw = row[f.key];
        vals.push(raw != null ? String(raw) : '-');
      }
      this.values = vals;
    } catch (e) {
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = msg.length > 0 && msg !== 'UNAUTHORIZED' ? msg :
        L10n.str(this.getUIContext(), 'load_detail_failed');
    } finally {
      this.loading = false;
    }
  }

  build() {
    Column() {
      Row() {
        Text(this.title)
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .fontColor($r('app.color.text_primary'))
          .layoutWeight(1)

        Button('✕')
          .fontSize(16)
          .fontColor($r('app.color.text_hint'))
          .backgroundColor($r('app.color.surface'))
          .width(40)
          .height(40)
          .type(ButtonType.Circle)
          .onClick(() => {
            if (this.controller) {
              this.controller.close();
            }
          })
      }
      .width('100%')
      .margin({ bottom: 12 })

      if (this.loading) {
        Row() {
          LoadingProgress().width(24).height(24)
        }
        .width('100%')
        .height(160)
        .justifyContent(FlexAlign.Center)
      } else if (this.errMsg.length > 0) {
        Column({ space: 12 }) {
          Text(this.errMsg)
            .fontSize(14)
            .fontColor($r('app.color.text_secondary'))
            .width('100%')

          Button($r('app.string.refresh'))
            .fontSize(13)
            .fontColor($r('app.color.primary'))
            .backgroundColor($r('app.color.primary_bg'))
            .borderRadius(6)
            .height(32)
            .onClick(() => {
              this.fetchRef();
            })
        }
        .width('100%')
        .alignItems(HorizontalAlign.Start)
        .padding({ top: 8, bottom: 8 })
      } else {
        Scroll() {
          Column({ space: 14 }) {
            ForEach(this.fields, (f: RefField, index: number) => {
              DetailRow({ label: f.label, value: index < this.values.length ? this.values[index] : '-' })
            }, (f: RefField, index: number) => `${index}_${f.key}`)
          }
          .width('100%')
        }
        .layoutWeight(1)
        .scrollBar(BarState.Off)
      }

      Row() {
        Button($r('app.string.cancel'))
          .fontSize(14)
          .fontWeight(FontWeight.Medium)
          .fontColor($r('app.color.text_on_primary'))
          .backgroundColor($r('app.color.primary'))
          .borderRadius(6)
          .height(40)
          .width('100%')
          .onClick(() => {
            if (this.controller) {
              this.controller.close();
            }
          })
      }
      .width('100%')
      .margin({ top: 16 })
    }
    .padding(20)
    .width('92%')
    .height('70%')
    .backgroundColor($r('app.color.surface'))
    .borderRadius(16)
  }
}
