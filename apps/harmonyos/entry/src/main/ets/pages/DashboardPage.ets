/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { DashboardService } from '../service/DataService';
import {
  DashboardData,
  SalesDashboard,
  TopProduct,
  OrderStatusItem,
  OperationLogItem
} from '../model/DataModels';
import { LoadingView, ErrorView, EmptyView, CopyrightBar } from '../common/Components';
import { TrendChart, DonutChart, TopBarsChart, DonutItem, BarDatum } from '../common/Charts';
import {
  BRAND_DEEP,
  BRAND_MID,
  ON_GRADIENT,
  ON_GRADIENT_SUB,
  RADIUS_CARD,
  SHADOW_CARD,
  MOTION_BASE
} from '../common/ThemeTokens';
import { TokenManager } from '../utils/TokenManager';

/** KPI 卡展示规格（由后端 stats 归一，数字滚动所需） */
interface KpiSpec {
  label: string;
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
struct KpiCard {
  @Prop label: string = '';
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

@Entry
@Component
struct DashboardPage {
  @State dashboardData: DashboardData | null = null;
  @State kpis: KpiSpec[] = [];
  // 图表输入（sales 归一为展示数组；空则图表区不渲染）
  @State trendDates: string[] = [];
  @State trendAmounts: number[] = [];
  @State donutItems: DonutItem[] = [];
  @State topItems: BarDatum[] = [];
  @State isLoading: boolean = true;
  @State hasError: boolean = false;
  @State errMsg: string = ''; // M4：真实错误文案（catch 不再吞 message）
  @State greeting: string = '';
  @State dateLine: string = '';

  aboutToAppear(): void {
    this.greeting = this.buildGreeting();
    this.dateLine = this.buildDateLine();
    this.loadDashboard();
  }

  private buildGreeting(): string {
    const h = new Date().getHours();
    const part = h < 6 ? '凌晨好' : h < 12 ? '上午好' : h < 14 ? '中午好' : h < 18 ? '下午好' : '晚上好';
    const name = TokenManager.getUsername();
    return name && name.length > 0 ? `${part}，${name}` : part;
  }

  private buildDateLine(): string {
    const d = new Date();
    const week = ['日', '一', '二', '三', '四', '五', '六'][d.getDay()];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日 星期${week}`;
  }

  private toKpis(data: DashboardData): KpiSpec[] {
    const list: KpiSpec[] = [];
    data.stats.forEach((s) => {
      const n = Number(s.value);
      const numeric = s.value.trim().length > 0 && Number.isFinite(n);
      const dot = s.value.indexOf('.');
      const decimals = dot >= 0 && numeric ? s.value.length - dot - 1 : 0;
      list.push({
        label: s.label,
        raw: s.value,
        color: s.color,
        target: numeric ? n : 0,
        decimals: decimals,
        numeric: numeric,
        trend: s.trend !== undefined ? s.trend : 0,
        hasTrend: s.trend !== undefined && Number.isFinite(s.trend)
      });
    });
    return list;
  }

  private mapCharts(s: SalesDashboard): void {
    this.trendDates = s.trend && s.trend.dates ? s.trend.dates.slice() : [];
    this.trendAmounts = s.trend && s.trend.amounts ? s.trend.amounts.slice() : [];
    const donuts: DonutItem[] = [];
    if (s.status_distribution) {
      s.status_distribution.forEach((it: OrderStatusItem) => {
        if (it.value > 0) {
          donuts.push({ name: it.name, value: it.value });
        }
      });
    }
    this.donutItems = donuts;
    const tops: BarDatum[] = [];
    if (s.top_products) {
      s.top_products.forEach((it: TopProduct) => {
        if (it.quantity > 0) {
          tops.push({ name: it.name, value: it.quantity });
        }
      });
    }
    this.topItems = tops;
  }

  async loadDashboard(): Promise<void> {
    this.isLoading = true;
    this.hasError = false;
    // 图表数据独立加载：失败只隐藏图表区，不阻塞 KPI/日志（刷新会再试）
    try {
      const s = await DashboardService.getSales();
      this.mapCharts(s);
    } catch (e) {
      this.trendDates = [];
      this.trendAmounts = [];
      this.donutItems = [];
      this.topItems = [];
    }
    try {
      this.dashboardData = await DashboardService.getDashboard();
      this.kpis = this.toKpis(this.dashboardData);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.errMsg = e instanceof Error && e.message ? e.message : '加载失败';
      this.hasError = true;
    } finally {
      this.isLoading = false;
    }
  }

  build() {
    Column() {
      // 品牌渐变 hero：欢迎/日期 + 顶栏操作（刷新/导出/头像，行为与旧顶栏一致）
      Column() {
        Row() {
          Text('仪表盘')
            .fontSize(20)
            .fontWeight(FontWeight.Bold)
            .fontColor(ON_GRADIENT)

          Blank()

          Button('刷新')
            .fontSize(13)
            .fontColor(ON_GRADIENT)
            .backgroundColor('#26FFFFFF')
            .borderRadius(6)
            .height(32)
            .enabled(!this.isLoading)
            .onClick(() => { this.loadDashboard(); })
            .margin({ right: 8 })

          Button('导出')
            .fontSize(13)
            .fontColor(ON_GRADIENT)
            .backgroundColor('#26FFFFFF')
            .borderRadius(6)
            .height(32)
            .onClick(() => {
              promptAction.showToast({ message: '导出功能已触发，查看系统通知' });
            })

          Image($rawfile('avatar.png'))
            .width(36)
            .height(36)
            .borderRadius(18)
            .backgroundColor($r('app.color.surface'))
            .margin({ left: 12 })
            .onClick(() => {
              router.pushUrl({ url: 'pages/ProfilePage' });
            })
        }
        .width('100%')
        .height(52)
        .padding({ left: 16, right: 16 })

        Column() {
          Text(this.greeting)
            .fontSize(22)
            .fontWeight(FontWeight.Bold)
            .fontColor(ON_GRADIENT)

          Text(this.dateLine)
            .fontSize(12)
            .fontColor(ON_GRADIENT_SUB)
            .margin({ top: 6 })
        }
        .width('100%')
        .alignItems(HorizontalAlign.Start)
        .padding({ left: 16, right: 16, bottom: 16 })
      }
      .width('100%')
      .linearGradient({ angle: 180, colors: [[BRAND_DEEP, 0], [BRAND_MID, 1]] })

      // 内容区
      if (this.isLoading) {
        LoadingView();
      } else if (this.hasError) {
        ErrorView({ message: this.errMsg, onRetry: () => this.loadDashboard() });
      } else if (!this.dashboardData) {
        EmptyView();
      } else {
        Scroll() {
          Column() {
            // KPI 数字滚动卡 2×2
            if (this.kpis.length > 0) {
              Grid() {
                ForEach(this.kpis, (k: KpiSpec) => {
                  GridItem() {
                    KpiCard({
                      label: k.label,
                      color: k.color,
                      raw: k.raw,
                      target: k.target,
                      decimals: k.decimals,
                      numeric: k.numeric,
                      trend: k.trend,
                      hasTrend: k.hasTrend
                    })
                  }
                  .height(96)
                }, (k: KpiSpec) => k.label)
              }
              .columnsTemplate('1fr 1fr')
              .rowsGap(12)
              .columnsGap(12)
              .width('100%')
              .margin({ top: 16 })
              .padding({ left: 16, right: 16 })
            }

            // 自绘图表区（视觉 2.0，数据来自销售看板端点；失败则整区隐藏）
            if (this.trendDates.length > 1 && this.trendAmounts.length === this.trendDates.length) {
              Column() {
                Text('近30日销售趋势')
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .width('100%')
                  .margin({ bottom: 4 })

                TrendChart({
                  amounts: this.trendAmounts,
                  dates: this.trendDates,
                  color: BRAND_MID
                })
              }
              .width('100%')
              .alignItems(HorizontalAlign.Start)
              .padding(16)
              .backgroundColor($r('app.color.surface'))
              .borderRadius(RADIUS_CARD)
              .shadow(SHADOW_CARD)
              .margin({ left: 16, right: 16, top: 12 })
            }

            if (this.donutItems.length > 0) {
              Column() {
                Text('订单状态分布')
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .width('100%')
                  .margin({ bottom: 12 })

                DonutChart({ items: this.donutItems, centerLabel: '订单' })
              }
              .width('100%')
              .alignItems(HorizontalAlign.Start)
              .padding(16)
              .backgroundColor($r('app.color.surface'))
              .borderRadius(RADIUS_CARD)
              .shadow(SHADOW_CARD)
              .margin({ left: 16, right: 16, top: 12 })
            }

            if (this.topItems.length > 0) {
              Column() {
                Text('热销商品 Top5')
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .width('100%')
                  .margin({ bottom: 14 })

                TopBarsChart({ items: this.topItems })
              }
              .width('100%')
              .alignItems(HorizontalAlign.Start)
              .padding(16)
              .backgroundColor($r('app.color.surface'))
              .borderRadius(RADIUS_CARD)
              .shadow(SHADOW_CARD)
              .margin({ left: 16, right: 16, top: 12 })
            }

            // 最近操作日志
            Column() {
              Row() {
                Text('最近操作')
                  .fontSize(16)
                  .fontWeight(FontWeight.Medium)

                Blank()

                Text('查看全部 >')
                  .fontSize(12)
                  .fontColor($r('app.color.primary'))
              }
              .width('100%')
              .margin({ bottom: 12 })

              if (this.dashboardData!.recent_logs.length === 0) {
                Text('暂无操作记录')
                  .fontSize(13)
                  .fontColor($r('app.color.text_hint'))
                  .width('100%')
                  .textAlign(TextAlign.Center)
                  .padding({ top: 24, bottom: 24 })
              } else {
                ForEach(this.dashboardData!.recent_logs.slice(0, 8), (log: OperationLogItem) => {
                  Row() {
                    Circle({ width: 32, height: 32 })
                      .fill($r('app.color.primary_bg'))

                    Column() {
                      Text(log.action)
                        .fontSize(13)
                        .fontColor($r('app.color.text_primary'))

                      Text(log.created_at ?? '')
                        .fontSize(11)
                        .fontColor($r('app.color.text_hint'))
                        .margin({ top: 2 })
                    }
                    .alignItems(HorizontalAlign.Start)
                    .margin({ left: 8 })
                    .layoutWeight(1)

                    Text(log.ip ?? '')
                      .fontSize(11)
                      .fontColor($r('app.color.text_hint'))
                  }
                  .width('100%')
                  .height(48)
                  .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
                }, (log: OperationLogItem) => `${log.id}-${log.created_at}`)
              }
            }
            .width('100%')
            .backgroundColor($r('app.color.surface'))
            .borderRadius(RADIUS_CARD)
            .shadow(SHADOW_CARD)
            .padding(16)
            .margin({ left: 16, right: 16, top: 12 })

            // 底部版权
            CopyrightBar();
          }
          .width('100%')
        }
        .width('100%')
        .layoutWeight(1)
        .scrollable(ScrollDirection.Vertical)
        .backgroundColor($r('app.color.bg_page'))
      }
    }
    .width('100%')
    .height('100%')
  }

  pageTransition() {
    PageTransitionEnter({ duration: 300, curve: Curve.EaseOut })
      .opacity(0)
      .translate({ y: 24 })
    PageTransitionExit({ duration: 250, curve: Curve.EaseIn })
      .opacity(0)
      .translate({ y: -12 })
  }
}
