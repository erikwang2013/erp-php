/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { L10n } from '../utils/L10n';

/**
 * 内容页视觉 3.0 行级共享件（V0-h 基线，docs/mobile-ui-design.md）：
 * - ListRow：列表行（title 15/w600 + badge 胶囊 + subtitle 12/textHint +
 *   icon 首字符圆徽 + actions 文本钮），行高 56、内边距 16、主/副间距 2、
 *   title 与徽章间距 8、行底 hairline（末行无分隔）、按压态 surface_alt；
 * - ListSectionHeader：surface 列表卡的内容头行（模块色 8px 竖条 + 可选
 *   小标题 + 「共 N 条」12/textHint + 操作文本钮）；
 * - RowBadge/RowAction/模块色映射表：kind 色全部取自既有色池（零新 hex）。
 *
 * 颜色约定：badge 与 icon 圆徽共用 kind 色对——tint 底用 *_bg、文字用
 * *_text 深字（neutral 走灰阶 surface_alt/text_secondary）；action 文字色
 * primary/danger/text。模块竖条色见 moduleAccent()（与 V0-f 模块表同源）。
 */
export const BADGE_KINDS: string[] = ['success', 'warning', 'danger', 'neutral'];

/** 行内状态徽章（胶囊）。kind 限 success|warning|danger|neutral；kind 空=不渲染 */
export class RowBadge {
  kind: string = '';
  label: ResourceStr = '';

  constructor(kind: string = '', label: ResourceStr = '') {
    this.kind = kind;
    this.label = label;
  }
}

/** 行动作文本钮配置（13sp 文本钮，点按区高 ≥32）。kind: primary|danger|text */
export class RowAction {
  label: ResourceStr = '';
  kind: string = 'primary';
  onTap: () => void = (): void => {
  };

  constructor(label: ResourceStr, kind: string = 'primary', onTap: () => void = (): void => {
  }) {
    this.label = label;
    this.kind = kind;
    this.onTap = onTap;
  }
}

/**
 * 模块→模块色（8px 竖条/模块身份；色值零新增，全部 chart/语义系既有资源）。
 * 同域同色：system/sales 蓝、hr/tms 紫、oms/wms 青、purchase 橙、mfg 绿、
 * finance 红（域与域用 chart 系区分；未收录模块回退品牌蓝，避免断色）。
 */
export function moduleAccent(moduleKey: string): Resource {
  if (moduleKey === 'hr' || moduleKey === 'tms') {
    return $r('app.color.chart_5');
  }
  if (moduleKey === 'oms' || moduleKey === 'wms') {
    return $r('app.color.chart_6');
  }
  if (moduleKey === 'purchase') {
    return $r('app.color.chart_3');
  }
  if (moduleKey === 'mfg') {
    return $r('app.color.chart_2');
  }
  if (moduleKey === 'finance') {
    return $r('app.color.chart_4');
  }
  return $r('app.color.chart_1');
}

/** badge/icon tint 底色：kind 走 *_bg（neutral 走灰阶 surface_alt；未知 kind 回退）。
 *  导出供详情页状态胶囊等行外组件复用（联动批） */
export function kindTint(kind: string): Resource {
  if (kind === 'success') {
    return $r('app.color.success_bg');
  }
  if (kind === 'warning') {
    return $r('app.color.warning_bg');
  }
  if (kind === 'danger') {
    return $r('app.color.danger_bg');
  }
  return $r('app.color.surface_alt');
}

/** badge/icon 深字色：kind 走 *_text（neutral 走 text_secondary；未知 kind 回退） */
export function kindInk(kind: string): Resource {
  if (kind === 'success') {
    return $r('app.color.success_text');
  }
  if (kind === 'warning') {
    return $r('app.color.warning_text');
  }
  if (kind === 'danger') {
    return $r('app.color.danger_text');
  }
  return $r('app.color.text_secondary');
}

/** action 文本钮文字色：primary/danger/text（未知回退次要文字色） */
function actionInk(kind: string): Resource {
  if (kind === 'danger') {
    return $r('app.color.danger_text');
  }
  if (kind === 'text') {
    return $r('app.color.text_secondary');
  }
  return $r('app.color.primary');
}

const LIST_ROW_HEIGHT: number = 56;

/**
 * 列表行（surface 卡内使用，行自身透明——底色由卡片容器提供）。
 * icon 传任意源串，仅取首字符做 32px 圆形徽；无 icon/无 badge 自动不渲染。
 */
@Component
export struct ListRow {
  @Prop title: ResourceStr = '';
  @Prop subtitle: ResourceStr = '';
  @Prop icon: string = '';
  @Prop badge: RowBadge = new RowBadge();
  @Prop actions: Array<RowAction> = [];
  @Prop isLast: boolean = false;
  @State pressed: boolean = false;

  /** 圆徽字形（仅首字符，零位图素材） */
  private glyph(): string {
    return this.icon.length > 0 ? this.icon.charAt(0) : '';
  }

  /** 徽章生效 kind（空串=不渲染）；表外 kind 一并视为 neutral 兜底 */
  private badgeKind(): string {
    const k = this.badge.kind;
    return BADGE_KINDS.indexOf(k) >= 0 ? k : '';
  }

  private tint(): Resource {
    const k = this.badgeKind();
    return kindTint(k.length > 0 ? k : 'neutral');
  }

  private ink(): Resource {
    const k = this.badgeKind();
    return kindInk(k.length > 0 ? k : 'neutral');
  }

  private showSubtitle(): boolean {
    return typeof this.subtitle !== 'string' || (this.subtitle as string).length > 0;
  }

  build() {
    Row() {
      // 首字符圆徽：32px tint 圆底 + 14/w600 字（kind 同源色，同 badge 语义）
      if (this.glyph().length > 0) {
        Row() {
          Text(this.glyph())
            .fontSize(14)
            .fontWeight(600)
            .fontColor(this.ink())
        }
        .width(32)
        .height(32)
        .borderRadius(16)
        .justifyContent(FlexAlign.Center)
        .backgroundColor(this.tint())
      }

      // 标题 + 徽章行 / 副行
      Column() {
        Row() {
          Text(this.title)
            .fontSize(15)
            .fontWeight(600)
            .fontColor($r('app.color.text_primary'))
            .maxLines(1)
            .textOverflow({ overflow: TextOverflow.Ellipsis })
            .layoutWeight(1)

          if (this.badgeKind().length > 0) {
            Text(this.badge.label)
              .fontSize(12)
              .fontColor(this.ink())
              .backgroundColor(this.tint())
              .borderRadius(10)
              .padding({ left: 8, right: 8, top: 3, bottom: 3 })
              .margin({ left: 8 })
              .maxLines(1)
          }
        }
        .width('100%')

        if (this.showSubtitle()) {
          Text(this.subtitle)
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
            .maxLines(1)
            .textOverflow({ overflow: TextOverflow.Ellipsis })
            .margin({ top: 2 })
            .width('100%')
        }
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)
      .margin({ left: this.glyph().length > 0 ? 12 : 0 })

      // 动作文本钮（点按区高 32）
      ForEach(this.actions, (action: RowAction, index: number) => {
        Text(action.label)
          .fontSize(13)
          .fontColor(actionInk(action.kind))
          .height(32)
          .padding({ left: 4, right: 4 })
          .margin({ left: index === 0 ? 4 : 0 })
          .onClick(() => {
            action.onTap();
          })
      }, (action: RowAction, index: number) => String(index))
    }
    .width('100%')
    .height(LIST_ROW_HEIGHT)
    .padding({ left: 16, right: 16 })
    .backgroundColor(this.pressed ? $r('app.color.surface_alt') : Color.Transparent)
    .border({
      width: { bottom: this.isLast ? 0 : 0.5 },
      color: $r('app.color.divider')
    })
    .onTouch((event: TouchEvent) => {
      // 按压态：Down 亮 surface_alt，Up/Cancel 还原（列表滚动 Up 会先 Cancel）
      this.pressed = event.type === TouchType.Down;
    })
  }
}

/**
 * 内容头行：模块色 8px 竖条 + 可选小标题 + 「共 N 条」计数（12/textHint）
 * + 可选操作文本钮。置于 surface 列表卡顶部（下接 hairline）。
 */
@Component
export struct ListSectionHeader {
  /** 模块键（见 moduleAccent()；页面级 TopBar 之外的模块身份标记） */
  @Prop moduleKey: string = 'system';
  /** 可选小标题（TopBar 已有主标题时通常留空） */
  @Prop title: ResourceStr = '';
  /** 总条数；<0 不渲染计数 */
  @Prop count: number = -1;
  /** 头行右侧操作（一般放页级动作，TopBar 已放时留空避免重复） */
  @Prop actions: Array<RowAction> = [];

  private showTitle(): boolean {
    return typeof this.title !== 'string' || (this.title as string).length > 0;
  }

  build() {
    Row() {
      // 8px 模块色竖条
      Row()
        .width(8)
        .height(16)
        .borderRadius(2)
        .backgroundColor(moduleAccent(this.moduleKey))

      if (this.showTitle()) {
        Text(this.title)
          .fontSize(14)
          .fontWeight(600)
          .fontColor($r('app.color.text_primary'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .margin({ left: 8 })
      }

      if (this.count >= 0) {
        Text(L10n.str(this.getUIContext(), 'total_count_fmt', String(this.count)))
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .margin({ left: 8 })
      }

      Blank()

      ForEach(this.actions, (action: RowAction, index: number) => {
        Text(action.label)
          .fontSize(13)
          .fontColor(actionInk(action.kind))
          .height(32)
          .padding({ left: 4, right: 4 })
          .margin({ left: index === 0 ? 0 : 8 })
          .onClick(() => {
            action.onTap();
          })
      }, (action: RowAction, index: number) => String(index))
    }
    .width('100%')
    .height(44)
    .padding({ left: 16, right: 16 })
    .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
  }
}
