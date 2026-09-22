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
//
// 引用**选择**三件套（表单外键下拉用，见文件下半部）：toRefOptions 把列表回包转选项、
// pickRef 走 ActionMenu 选、refLabel 把当前值换回展示名。值一律是行 id 原样串。

import promptAction from '@ohos.promptAction';
import { ApiService } from '../service/ApiService';
import { L10n } from '../utils/L10n';
import { Format } from '../utils/Format';
import { DetailRow } from './DetailCard';

/** 弹层字段描述：key = 后端下发键，label 为展示文案（已本地化的字符串） */
export class RefField {
  key: string = '';
  label: string = '';
  /** true = 时间列：后端下发 ISO-UTC，展示前按本机时区换算（Format.dateTime） */
  isTime: boolean = false;

  constructor(key: string, label: string, isTime: boolean = false) {
    this.key = key;
    this.label = label;
    this.isTime = isTime;
  }
}

/**
 * 引用选择项：value = 后端行 id（encodeIds 后的 hashid 串，**原样回传**——后端
 * decodeFlexibleId 双模解码）；label = 可读名（列表行的 name ?? code）。
 */
export class RefOption {
  value: string = '';
  label: string = '';

  constructor(value: string, label: string) {
    this.value = value;
    this.label = label;
  }
}

/** ActionMenu 按钮上限（promptAction 定长元组 ≤6，同 FormDialog.pickSelect） */
const REF_MENU_MAX: number = 6;
/** 菜单按钮文字色（镜像 color.json text_primary）：本文件 helper 在组件作用域外，取不到 $r */
const MENU_TEXT_COLOR: string = '#333333';

/**
 * 列表回包 → 引用选项：只收 id + 可读名（name ?? code），两缺其一的行丢弃
 * （选项贴一串 hashid 无意义，与契约 rule ④「无可读名称就不出该段」同口径）。
 */
export function toRefOptions(list: Array<Object>): Array<RefOption> {
  const out: Array<RefOption> = [];
  for (const item of list) {
    const row = item as Record<string, Object>;
    const id = row['id'] != null ? String(row['id']) : '';
    const name = (row['name'] as string) ?? '';
    const code = (row['code'] as string) ?? '';
    const label = name.length > 0 ? name : code;
    if (id.length > 0 && label.length > 0) {
      out.push(new RefOption(id, label));
    }
  }
  return out;
}

/**
 * 外键引用选择菜单（照抄本仓 FormDialog.pickSelect 的 ActionMenu 形态）：标题 + 选项名列表，
 * 选中回调该选项的 value。选项为空不开菜单（调用方自行提示）。
 * 上限 REF_MENU_MAX 项——与 FormDialog 下拉同限，超出部分不可达（本仓既有范式，勿在此加长）。
 */
export function pickRef(title: ResourceStr, options: Array<RefOption>, onPick: (value: string) => void): void {
  if (options.length === 0) {
    return;
  }
  const buttons: Array<promptAction.Button> = [];
  for (let i = 0; i < options.length && buttons.length < REF_MENU_MAX; i++) {
    buttons.push({ text: options[i].label, color: MENU_TEXT_COLOR });
  }
  promptAction.showActionMenu({
    title: title,
    buttons: buttons as [promptAction.Button, promptAction.Button?, promptAction.Button?,
      promptAction.Button?, promptAction.Button?, promptAction.Button?],
  }).then((ret: promptAction.ActionMenuSuccessResponse) => {
    const idx: number = ret.index;
    if (idx >= 0 && idx < options.length) {
      onPick(options[idx].value);
    }
  }).catch(() => {
    // 取消菜单等失败路径不处理（同 FormDialog.pickSelect）
  });
}

/** 当前值 → 展示名：选项未加载/未命中时回落原值（编辑回填不因取数失败变成空框） */
export function refLabel(options: Array<RefOption>, raw: string): string {
  for (let i = 0; i < options.length; i++) {
    if (options[i].value === raw) {
      return options[i].label;
    }
  }
  return raw;
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
        vals.push(f.isTime ? (Format.dateTime(raw) || '-') : (raw != null ? String(raw) : '-'));
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
