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
import { L10n } from '../utils/L10n';

/** KPI 卡展示规格（由后端 stats 归一，数字滚动所需） */
interface KpiSpec {
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
struct KpiCard {
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
  // 系统管理宫格按压动效（one-shot 缩放，非交互状态恒 1）
  @State mgmtZoom: number = 1;

  aboutToAppear(): void {
    this.loadDashboard();
  }

  /** 问候语：时段文案 +（有登录名时）拼接；文案经 L10n 按当前语言解析 */
  private greetingText(): string {
    const ui = this.getUIContext();
    const h = new Date().getHours();
    const part = this.greetingPart(ui, h);
    const name = TokenManager.getUsername();
    if (name && name.length > 0) {
      return L10n.str(ui, 'greeting_with_name', part, name);
    }
    return part;
  }

  private greetingPart(ui: UIContext, h: number): string {
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
  private dateLineText(): string {
    const ui = this.getUIContext();
    const d = new Date();
    return L10n.str(ui, 'date_line', d.getFullYear(), d.getMonth() + 1, d.getDate(),
      this.weekdayText(ui, d.getDay()));
  }

  private weekdayText(ui: UIContext, dow: number): string {
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

  /**
   * KPI 标签：后端固定四指标 icon 键 → 本地资源（MetricsController 契约四 icon 稳定）；
   * 其余 icon 透传后端 label 原样（后端下发 label 保持展示原样，属两端双语
   * 后端字段债务，未知文案不做猜测映射）
   */
  /** 宫格按压动效（one-shot）：缩放沉底即回弹，页面跳转不等待动画 */
  private pressMgmt(url: string): void {
    this.mgmtZoom = 0.96;
    setTimeout(() => {
      this.mgmtZoom = 1;
    }, 120);
    router.pushUrl({ url: url });
  }

  /** 系统管理宫格卡：符号字色图标（免 rawfile）+ 文案 + 右箭；素色内容卡外框 */
  @Builder
  mgmtTile(label: ResourceStr, glyph: string, url: string) {
    Row() {
      Stack() {
        Circle({ width: 36, height: 36 })
          .fill($r('app.color.primary_bg'))
        Text(glyph)
          .fontSize(glyph === '◆' ? 14 : 16)
          .fontColor(BRAND_MID)
          .fontWeight(FontWeight.Medium)
      }
      .width(36)
      .height(36)

      Text(label)
        .fontSize(14)
        .fontWeight(FontWeight.Medium)
        .fontColor($r('app.color.text_primary'))
        .margin({ left: 10 })
        .layoutWeight(1)
        .maxLines(1)
        .textOverflow({ overflow: TextOverflow.Ellipsis })

      Text('›')
        .fontSize(20)
        .fontColor($r('app.color.text_disabled'))
    }
    .width('100%')
    .height(64)
    .padding({ left: 12, right: 12 })
    .backgroundColor($r('app.color.surface'))
    .borderRadius(RADIUS_CARD)
    .shadow(SHADOW_CARD)
    .onClick(() => {
      this.pressMgmt(url);
    })
  }

  private kpiLabel(icon: string, label: string): ResourceStr {
    if (icon === 'people') {
      return $r('app.string.total_users');
    }
    if (icon === 'person_add') {
      return $r('app.string.today_new');
    }
    if (icon === 'bolt') {
      return $r('app.string.active_users');
    }
    if (icon === 'description') {
      return $r('app.string.operation_logs');
    }
    return label;
  }

  private toKpis(data: DashboardData): KpiSpec[] {
    const list: KpiSpec[] = [];
    data.stats.forEach((s) => {
      const n = Number(s.value);
      const numeric = s.value.trim().length > 0 && Number.isFinite(n);
      const dot = s.value.indexOf('.');
      const decimals = dot >= 0 && numeric ? s.value.length - dot - 1 : 0;
      list.push({
        label: this.kpiLabel(s.icon, s.label),
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
      this.errMsg = e instanceof Error && e.message ? e.message :
        L10n.str(this.getUIContext(), 'load_failed');
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
          Text($r('app.string.dashboard_title'))
            .fontSize(20)
            .fontWeight(FontWeight.Bold)
            .fontColor(ON_GRADIENT)

          Blank()

          Button($r('app.string.refresh'))
            .fontSize(13)
            .fontColor(ON_GRADIENT)
            .backgroundColor('#26FFFFFF')
            .borderRadius(6)
            .height(32)
            .enabled(!this.isLoading)
            .onClick(() => { this.loadDashboard(); })
            .margin({ right: 8 })

          Button($r('app.string.export'))
            .fontSize(13)
            .fontColor(ON_GRADIENT)
            .backgroundColor('#26FFFFFF')
            .borderRadius(6)
            .height(32)
            .onClick(() => {
              promptAction.showToast({ message: $r('app.string.dashboard_export_toast') });
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
          Text(this.greetingText())
            .fontSize(22)
            .fontWeight(FontWeight.Bold)
            .fontColor(ON_GRADIENT)

          Text(this.dateLineText())
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
            // 系统管理宫格（2 项：用户管理/角色管理；UserListPage 此前全树无入口，
            // 随此宫格一并接通。素色走主题 token，零渐变——渐变只归登录/splash/hero）
            Column() {
              Text($r('app.string.dashboard_sys_title'))
                .fontSize(16)
                .fontWeight(FontWeight.Medium)
                .width('100%')
                .margin({ bottom: 12 })

              Grid() {
                GridItem() {
                  this.mgmtTile($r('app.string.user_list_title'), '◉', 'pages/UserListPage')
                }

                GridItem() {
                  this.mgmtTile($r('app.string.role_list_title'), '◆', 'pages/RoleListPage')
                }
              }
              .columnsTemplate('1fr 1fr')
              .columnsGap(12)
              .rowsGap(12)
              .width('100%')
              .scale({ x: this.mgmtZoom, y: this.mgmtZoom })
              .animation({ duration: 120, curve: Curve.EaseOut })
            }
            .width('100%')
            .padding({ left: 16, right: 16, top: 16 })
            .alignItems(HorizontalAlign.Start)

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
                }, (_k: KpiSpec, index: number) => index.toString())
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
                Text($r('app.string.dashboard_trend_title'))
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
                Text($r('app.string.dashboard_status_title'))
                  .fontSize(15)
                  .fontWeight(FontWeight.Medium)
                  .width('100%')
                  .margin({ bottom: 12 })

                DonutChart({
                  items: this.donutItems,
                  // 环心标签走 L10n（Charts.ets 保持 string 类型不变，此处即时解析）
                  centerLabel: L10n.str(this.getUIContext(), 'dashboard_orders_center')
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

            if (this.topItems.length > 0) {
              Column() {
                Text($r('app.string.dashboard_top_title'))
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
                Text($r('app.string.dashboard_recent_title'))
                  .fontSize(16)
                  .fontWeight(FontWeight.Medium)

                Blank()

                Text($r('app.string.dashboard_view_all'))
                  .fontSize(12)
                  .fontColor($r('app.color.primary'))
              }
              .width('100%')
              .margin({ bottom: 12 })

              if (this.dashboardData!.recent_logs.length === 0) {
                Text($r('app.string.dashboard_no_logs'))
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
