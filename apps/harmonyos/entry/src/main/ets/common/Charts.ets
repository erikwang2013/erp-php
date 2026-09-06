/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 首页自绘图表（视觉 2.0）— 不引三方库，Canvas 手绘 + 原生条
 *
 * TrendChart   近30日趋势折线+面积（Canvas）
 * DonutChart   占比环形（Canvas 弧 + ArkUI 中心数/图例，图例文字随主题）
 * TopBarsChart Top5 横条（原生比例条：文字省略/主题自适应优于 Canvas 自绘文本）
 *
 * 数据源 /admin/v1/dashboard/sales（见 DashboardController::sales）。
 * Canvas 文本色用中性灰 #999，深浅主题均可读；主题敏感文字一律走 ArkUI 层。
 */
import { BRAND_MID, CHART_COLORS } from './ThemeTokens';
import { L10n } from '../utils/L10n';

/** 图例/数据项 */
export interface DonutItem {
  name: string;
  value: number;
}

export interface BarDatum {
  name: string;
  value: number;
}

const PLOT_TOP = 18;
const PLOT_BOTTOM = 20;
const PLOT_LEFT = 2;
const PLOT_RIGHT = 8;

/**
 * 坐标轴/数值紧凑格式化：zh ≥1万按「万」进位、en ≥1万按「k」进位。
 * 生效语言经 resolved_locale 探针 key 同步获取（资源解析与 UI 同源，免异步
 * resourceManager 配置查询）；进位后缀取语言资源 axis_unit_tenk，代码零字面量。
 */
function fmtAxis(ui: UIContext, v: number): string {
  if (v < 10000) {
    return Math.round(v).toString();
  }
  const zh = L10n.str(ui, 'resolved_locale') === 'zh';
  const n = v / (zh ? 10000 : 1000);
  const text = n >= 100 ? Math.round(n).toString() : (n.toFixed(1).replace(/\.0$/, ''));
  return text + L10n.str(ui, 'axis_unit_tenk');
}

/** 'YYYY-MM-DD' → 'MM-DD' */
function fmtDay(date: string): string {
  return date.length >= 10 ? date.slice(5, 10) : date;
}

/**
 * 近30日趋势折线+面积（单序列，自绘 Canvas）
 */
@Component
export struct TrendChart {
  @Watch('onDataChange') @Prop amounts: number[] = [];
  @Watch('onDataChange') @Prop dates: string[] = [];
  @Prop color: string = BRAND_MID;
  private settings: RenderingContextSettings = new RenderingContextSettings(true);
  private ctx: CanvasRenderingContext2D = new CanvasRenderingContext2D(this.settings);
  private ready: boolean = false;

  onDataChange(): void {
    this.draw();
  }

  private buildPath(xs: number[], ys: number[], bottomY: number, close: boolean): void {
    const n = xs.length;
    const ctx = this.ctx;
    ctx.moveTo(xs[0], ys[0]);
    // 中点二次曲线平滑（折线过中点的圆角走势）
    for (let i = 1; i < n - 1; i++) {
      const xc = (xs[i] + xs[i + 1]) / 2;
      const yc = (ys[i] + ys[i + 1]) / 2;
      ctx.quadraticCurveTo(xs[i], ys[i], xc, yc);
    }
    ctx.lineTo(xs[n - 1], ys[n - 1]);
    if (close) {
      ctx.lineTo(xs[n - 1], bottomY);
      ctx.lineTo(xs[0], bottomY);
      ctx.closePath();
    }
  }

  private draw(): void {
    if (!this.ready) {
      return;
    }
    const ctx = this.ctx;
    const w = ctx.width;
    const h = ctx.height;
    ctx.clearRect(0, 0, w, h);

    const n = this.amounts.length;
    if (n < 2 || this.dates.length !== n) {
      return;
    }
    let max = 0;
    for (let i = 0; i < n; i++) {
      if (this.amounts[i] > max) {
        max = this.amounts[i];
      }
    }
    const pw = w - PLOT_LEFT - PLOT_RIGHT;
    const ph = h - PLOT_TOP - PLOT_BOTTOM;
    const bottomY = PLOT_TOP + ph;
    const xs: number[] = [];
    const ys: number[] = [];
    for (let i = 0; i < n; i++) {
      xs.push(PLOT_LEFT + (i / (n - 1)) * pw);
      ys.push(bottomY - (max > 0 ? this.amounts[i] / max : 0) * ph);
    }

    // 面积（品牌色 12% 透明）
    ctx.globalAlpha = 0.12;
    ctx.beginPath();
    this.buildPath(xs, ys, bottomY, true);
    ctx.fillStyle = this.color;
    ctx.fill();
    ctx.globalAlpha = 1;

    // 折线
    ctx.beginPath();
    this.buildPath(xs, ys, bottomY, false);
    ctx.strokeStyle = this.color;
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();

    // 纵轴峰值（左上角）与底部三个日期刻度（去重，避免贴边截断）
    ctx.fillStyle = '#999999';
    ctx.font = '10px sans-serif';
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    ctx.fillText(fmtAxis(this.getUIContext(), max), PLOT_LEFT, PLOT_TOP - 6);
    const tickIdx: number[] = [0];
    const mid = Math.floor((n - 1) / 2);
    if (mid > 0 && mid < n - 1) {
      tickIdx.push(mid);
    }
    if (n - 1 !== 0) {
      tickIdx.push(n - 1);
    }
    for (let i = 0; i < tickIdx.length; i++) {
      const idx = tickIdx[i];
      ctx.textAlign = idx === 0 ? 'left' : (idx === n - 1 ? 'right' : 'center');
      ctx.fillText(fmtDay(this.dates[idx]), xs[idx], h - 6);
    }
  }

  build() {
    Canvas(this.ctx)
      .width('100%')
      .height(150)
      .onReady(() => {
        this.ready = true;
        this.draw();
      })
  }
}

/**
 * 占比环形（Canvas 弧 + 中心总数/图例为 ArkUI 层，文字随主题）
 */
@Component
export struct DonutChart {
  @Watch('onItemsChange') @Prop items: DonutItem[] = [];
  @Prop centerLabel: string = '';
  @State totalText: string = '0';
  private settings: RenderingContextSettings = new RenderingContextSettings(true);
  private ctx: CanvasRenderingContext2D = new CanvasRenderingContext2D(this.settings);
  private ready: boolean = false;

  aboutToAppear(): void {
    this.totalText = this.sumText();
  }

  onItemsChange(): void {
    this.totalText = this.sumText();
    if (this.ready) {
      this.draw();
    }
  }

  private sum(): number {
    let total = 0;
    for (let i = 0; i < this.items.length; i++) {
      total += this.items[i].value;
    }
    return total;
  }

  private sumText(): string {
    return Math.round(this.sum()).toString();
  }

  private draw(): void {
    if (!this.ready) {
      return;
    }
    const ctx = this.ctx;
    const w = ctx.width;
    const h = ctx.height;
    ctx.clearRect(0, 0, w, h);

    const cx = w / 2;
    const cy = h / 2;
    const lineWidth = 16;
    const r = Math.min(w, h) / 2 - lineWidth / 2 - 2;
    const total = this.sum();
    ctx.lineWidth = lineWidth;

    if (total <= 0) {
      ctx.beginPath();
      ctx.arc(cx, cy, r, 0, Math.PI * 2);
      ctx.strokeStyle = '#D9D9D9';
      ctx.stroke();
      return;
    }

    let angle = -Math.PI / 2;
    for (let i = 0; i < this.items.length; i++) {
      const v = this.items[i].value;
      if (v <= 0) {
        continue;
      }
      const sweep = (v / total) * Math.PI * 2;
      // 段间留 0.02rad 缝隙（白底/主题面透出，避免同色相邻难辨）
      ctx.beginPath();
      ctx.arc(cx, cy, r, angle + 0.01, angle + sweep - 0.01);
      ctx.strokeStyle = CHART_COLORS[i % CHART_COLORS.length];
      ctx.stroke();
      angle += sweep;
    }
  }

  private percentOf(v: number): string {
    const total = this.sum();
    if (total <= 0) {
      return '0.0%';
    }
    return (v / total * 100).toFixed(1) + '%';
  }

  build() {
    Column() {
      Stack() {
        Canvas(this.ctx)
          .width('100%')
          .height(150)
          .onReady(() => {
            this.ready = true;
            this.draw();
          })
        Column() {
          Text(this.totalText)
            .fontSize(22)
            .fontWeight(FontWeight.Bold)
            .fontColor($r('app.color.text_primary'))
          if (this.centerLabel.length > 0) {
            Text(this.centerLabel)
              .fontSize(10)
              .fontColor($r('app.color.text_hint'))
              .margin({ top: 2 })
          }
        }
        .alignItems(HorizontalAlign.Center)
      }
      .width('100%')
      .height(150)

      // 图例：色点 + 名称 + 占比
      ForEach(this.items, (it: DonutItem, idx: number) => {
        Row() {
          Circle({ width: 8, height: 8 })
            .fill(CHART_COLORS[idx % CHART_COLORS.length])
          Text(it.name)
            .fontSize(13)
            .fontColor($r('app.color.text_secondary'))
            .margin({ left: 8 })
            .layoutWeight(1)
            .maxLines(1)
            .textOverflow({ overflow: TextOverflow.Ellipsis })
          Text(this.percentOf(it.value))
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
        }
        .width('100%')
        .margin({ top: 10 })
      }, (it: DonutItem, idx: number) => `${it.name}-${idx}`)
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
  }
}

/**
 * Top5 横条（原生比例条，文字省略与主题自适应优于 Canvas 自绘文本）
 */
@Component
export struct TopBarsChart {
  @Prop items: BarDatum[] = [];

  private maxValue(): number {
    let m = 0;
    for (let i = 0; i < this.items.length; i++) {
      if (this.items[i].value > m) {
        m = this.items[i].value;
      }
    }
    return m;
  }

  build() {
    Column() {
      ForEach(this.items, (it: BarDatum) => {
        Column() {
          Row() {
            Text(it.name)
              .fontSize(13)
              .fontColor($r('app.color.text_primary'))
              .maxLines(1)
              .textOverflow({ overflow: TextOverflow.Ellipsis })
              .layoutWeight(1)
            Text(fmtAxis(this.getUIContext(), it.value))
              .fontSize(12)
              .fontColor($r('app.color.text_hint'))
              .margin({ left: 12 })
          }
          .width('100%')

          Stack({ alignContent: Alignment.Start }) {
            Row()
              .width('100%')
              .height(6)
              .borderRadius(3)
              .backgroundColor($r('app.color.surface_alt'))
            Row()
              .width(this.barWidth(it.value))
              .height(6)
              .borderRadius(3)
              .backgroundColor(BRAND_MID)
          }
          .width('100%')
          .height(6)
          .margin({ top: 6 })
        }
        .width('100%')
        .margin({ bottom: 14 })
      }, (it: BarDatum, idx: number) => `${idx}-${it.name}`)
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
  }

  private barWidth(value: number): string {
    const max = this.maxValue();
    if (max <= 0) {
      return '0%';
    }
    // 极小值给 2% 保底，避免零视觉
    const pct = Math.max(2, Math.round(value / max * 100));
    return `${pct}%`;
  }
}
