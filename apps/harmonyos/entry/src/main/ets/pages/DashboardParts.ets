/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 仪表盘可复用件（自 DashboardPage 抽出，行为与样式不变）：
//   KpiSpec + KpiCard —— KPI 数字滚动卡（animateTo 免不了逐帧数值，interval + 缓动近似）
//   greetingText / dateLineText —— 问候语与日期行文案（UIContext 由调用方传入，语言相关排版走 L10n）
// 用法：import { KpiSpec, KpiCard, greetingText, dateLineText } from './DashboardParts';

import { L10n } from '../utils/L10n';
import { TokenManager } from '../utils/TokenManager';
import { BRAND_MID, RADIUS_CARD, SHADOW_CARD, MOTION_BASE } from '../common/ThemeTokens';

/** KPI 卡展示规格（由后端 stats 归一，数字滚动所需） */
export interface KpiSpec {
  label: ResourceStr; // 后端固定文案经 kpiLabel 映射为资源；未知文案透传
  raw: string;      // 原始文案（非数字兜底直接展示）
  color: string;    // 后端 hex 直用
  target: number;   // 滚动目标值
  decimals: number; // 小数位
  numeric: boolean; // 值是否可数字滚动
  trend: number;    // 环比 %
  hasTrend: boolean;
}

/**
 * KPI 数字滚动卡（animateTo 免不了逐帧数值，用 interval + 缓动近似；
 * 刷新换数后从当前值续滚，不闪跳）
 */
@Component
export struct KpiCard {
  @Prop label: ResourceStr = '';
  @Prop color: string = BRAND_MID;
  @Prop raw: string = '';
  @Watch('onTargetChange') @Prop target: number = 0;
  @Prop decimals: number = 0;
  @Prop numeric: boolean = true;
  @Prop trend: number = 0;
  @Prop hasTrend: boolean = false;
  @State shown: string = '0';
  private timer: number = -1;

  aboutToAppear(): void {
    if (!this.numeric) {
      this.shown = this.raw;
      return;
    }
    this.shown = '0';
    this.rollTo(this.target);
  }

  aboutToDisappear(): void {
    this.clearTimer();
  }

  onTargetChange(): void {
    if (this.numeric) {
      this.rollTo(this.target);
    }
  }

  private clearTimer(): void {
    if (this.timer >= 0) {
      clearInterval(this.timer);
      this.timer = -1;
    }
  }

  private rollTo(target: number): void {
    this.clearTimer();
    const dur = MOTION_BASE;
    const t0 = Date.now();
    const from = Number(this.shown) || 0;
    if (Math.abs(target - from) < 0.001) {
      this.shown = target.toFixed(this.decimals);
      return;
    }
    this.timer = setInterval(() => {
      const p = Math.min(1, (Date.now() - t0) / dur);
      // easeOutCubic：先快后慢
      const e = 1 - Math.pow(1 - p, 3);
      if (p >= 1) {
        this.clearTimer();
        this.shown = target.toFixed(this.decimals);
      } else {
        this.shown = (from + (target - from) * e).toFixed(this.decimals);
      }
    }, 16);
  }

  build() {
    Column() {
      Row() {
        Text(this.label)
          .fontSize(12)
          .fontColor($r('app.color.text_secondary'))
          .layoutWeight(1)
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })

        // 环比：▲升 ▼降，无上期对比不显示
        if (this.hasTrend) {
          Text(`${this.trend >= 0 ? '▲' : '▼'} ${Math.abs(this.trend).toFixed(1)}%`)
            .fontSize(11)
            .fontColor(this.trend >= 0 ? $r('app.color.success_text') : $r('app.color.danger_text'))
            .margin({ left: 8 })
        }
      }
      .width('100%')

      Text(this.shown)
        .fontSize(25)
        .fontWeight(FontWeight.Bold)
        .fontColor(this.color)
        .margin({ top: 8 })
    }
    .width('100%')
    .height('100%')
    .alignItems(HorizontalAlign.Start)
    .justifyContent(FlexAlign.Center)
    .padding(14)
    .backgroundColor($r('app.color.surface'))
    .borderRadius(RADIUS_CARD)
    .shadow(SHADOW_CARD)
  }
}

  /** 问候语：时段文案 +（有登录名时）拼接；文案经 L10n 按当前语言解析 */
export function greetingText(ui: UIContext): string {
    const h = new Date().getHours();
    const part = greetingPart(ui, h);
    const name = TokenManager.getUsername();
    if (name && name.length > 0) {
      return L10n.str(ui, 'greeting_with_name', part, name);
    }
    return part;
  }

function greetingPart(ui: UIContext, h: number): string {
    if (h < 6) {
      return L10n.str(ui, 'greeting_dawn');
    }
    if (h < 12) {
      return L10n.str(ui, 'greeting_morning');
    }
    if (h < 14) {
      return L10n.str(ui, 'greeting_noon');
    }
    if (h < 18) {
      return L10n.str(ui, 'greeting_afternoon');
    }
    return L10n.str(ui, 'greeting_evening');
  }

  /** 日期行：年月日 + 星期，语言相关排版由 date_line 资源格式串承载 */
export function dateLineText(ui: UIContext): string {
    const d = new Date();
    return L10n.str(ui, 'date_line', d.getFullYear(), d.getMonth() + 1, d.getDate(),
      weekdayText(ui, d.getDay()));
  }

function weekdayText(ui: UIContext, dow: number): string {
    if (dow === 0) {
      return L10n.str(ui, 'weekday_sun');
    }
    if (dow === 1) {
      return L10n.str(ui, 'weekday_mon');
    }
    if (dow === 2) {
      return L10n.str(ui, 'weekday_tue');
    }
    if (dow === 3) {
      return L10n.str(ui, 'weekday_wed');
    }
    if (dow === 4) {
      return L10n.str(ui, 'weekday_thu');
    }
    if (dow === 5) {
      return L10n.str(ui, 'weekday_fri');
    }
    return L10n.str(ui, 'weekday_sat');
  }

