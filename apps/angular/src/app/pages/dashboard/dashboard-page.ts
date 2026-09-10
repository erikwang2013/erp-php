/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Component, Input, OnChanges, OnInit, computed, signal } from '@angular/core';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzIconModule } from 'ng-zorro-antd/icon';
import { http } from '../../core/api.service';
import { dateTime, int, text } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';

/**
 * 经营总览 —— 平移自 React pages/Dashboard.tsx。
 * GET /admin/v1/dashboard（后端 Redis 缓存 5 分钟）
 * → { stats, trends:{dates,series}, distribution:{user_status}, recent_logs }
 *
 * 折线图是手写 SVG：几何常量与 React 端 LineChart 逐点一致，不引入图表库。
 */

interface DashStat {
  label: string;
  value: string;
  icon: string;
  color: string;
  trend?: number | null;
}

interface DashSeries {
  series_key: string;
  name: string;
  data: number[];
  color: string;
}

interface DashData {
  stats: DashStat[];
  trends: { dates: string[]; series: DashSeries[] };
  distribution: { user_status: { name: string; value: number }[] };
  recent_logs: Record<string, unknown>[];
}

/** 后端统计项图标名 → app-icon 图标名（与 React ICON_MAP 同表） */
const ICON_MAP: Record<string, string> = {
  people: 'users',
  person_add: 'user',
  bolt: 'activity',
  description: 'file',
  dollar: 'dollar',
  cart: 'cart',
};

/** 分布条 / 图例交替双色（吃 token：主色 墨青 + 语义红） */
const DIST_COLORS = ['var(--chart-1)', 'var(--chart-4)'];

/** 折线图几何（与 React LineChart 常量同值） */
const W = 640;
const H = 200;
const PAD_L = 36;
const PAD_B = 22;
const PAD_T = 8;

interface ChartModel {
  w: number;
  h: number;
  padL: number;
  padB: number;
  grid: { y: number; label: number }[];
  xLabels: { x: number; text: string }[];
  series: { key: string; color: string; line: string; area: string }[];
}

/**
 * KPI 数值滚动：仅纯数字（可选千分位/小数）动画，货币等其余文本原样渲染。
 * signal 每次 rAF 更新驱动模板；一次变更只起一轮动画，不引动画库。
 */
@Component({
  selector: 'count-up',
  standalone: true,
  template: `{{ display() }}`,
})
export class CountUpComponent implements OnChanges {
  @Input() text = '';
  private readonly val = signal<number | null>(null);
  private readonly dec = computed(() =>
    this.text.includes('.') ? (this.text.split('.')[1] ?? '').length : 0,
  );
  private readonly grouping = computed(() => this.text.includes(','));

  protected readonly display = computed(() => {
    const v = this.val();
    if (v === null) return this.text;
    return v.toLocaleString('en-US', {
      minimumFractionDigits: this.dec(),
      maximumFractionDigits: this.dec(),
      useGrouping: this.grouping(),
    });
  });

  ngOnChanges(): void {
    const trimmed = this.text.trim();
    const animatable = /^[\d.,]+$/.test(trimmed);
    const target = parseFloat(trimmed.replace(/[^0-9.-]/g, ''));
    if (!animatable || Number.isNaN(target)) {
      this.val.set(null);
      return;
    }
    this.val.set(0);
    const start = performance.now();
    const step = (now: number): void => {
      const p = Math.min(1, (now - start) / 640);
      this.val.set(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
}

@Component({
  imports: [TrPipe, NzButtonModule, NzIconModule, IconComponent, CountUpComponent],
  selector: 'app-dashboard-page',
  styleUrl: './dashboard-page.less',
  templateUrl: './dashboard-page.html',
})
export class DashboardPage implements OnInit {
  private readonly data = signal<DashData | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly loading = signal(true);

  protected readonly hasData = computed(() => this.data() !== null);
  protected readonly stats = computed(() => this.data()?.stats ?? []);
  protected readonly trend = computed(() => this.data()?.trends ?? null);
  protected readonly logs = computed(() => this.data()?.recent_logs ?? []);
  protected readonly dist = computed(() => this.data()?.distribution?.user_status ?? []);
  protected readonly distTotal = computed(() =>
    this.dist().reduce((s, d) => s + Number(d.value || 0), 0),
  );
  /** 无 dates 时不画图（React：trend && trend.dates.length > 0） */
  protected readonly chart = computed<ChartModel | null>(() => buildChart(this.trend()));

  /** 模板 helper：格式化函数直出（React 侧同一批函数 import 进组件） */
  protected readonly int = int;
  protected readonly text = text;
  protected readonly dateTime = dateTime;
  protected readonly abs = (n: number): number => Math.abs(n);
  protected readonly iconOf = (icon: string): string => ICON_MAP[icon] ?? 'box';
  protected readonly distColor = (i: number): string => DIST_COLORS[i % DIST_COLORS.length];
  /** 图标瓦片浅底：后端色 14% 混入纸面（替代 hex+alpha 拼串，任意颜色值都可用） */
  protected readonly iconBg = (color: string): string =>
    `color-mix(in srgb, ${color} 14%, var(--surface))`;

  ngOnInit(): void {
    void this.load();
  }

  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      this.data.set(await http.get<DashData>('/admin/v1/dashboard'));
    } catch (e) {
      this.error.set(e instanceof Error ? e.message : tr('加载失败'));
    } finally {
      this.loading.set(false);
    }
  }
}

/** 折线/面积点串在 TS 侧算好，模板只做 attr 绑定（模板内没法写 toFixed 表达式） */
function buildChart(trend: { dates: string[]; series: DashSeries[] } | null): ChartModel | null {
  const dates = trend?.dates ?? [];
  const n = dates.length;
  if (!n) return null;

  const series = trend?.series ?? [];
  const max = Math.max(1, ...series.flatMap((s) => s.data));
  const x = (i: number): number => PAD_L + (i / Math.max(1, n - 1)) * (W - PAD_L - 8);
  const y = (v: number): number => PAD_T + (1 - v / max) * (H - PAD_T - PAD_B);
  const baseY = H - PAD_B;
  const step = Math.ceil(n / 6);

  return {
    w: W,
    h: H,
    padL: PAD_L,
    padB: PAD_B,
    grid: [0, 0.25, 0.5, 0.75, 1].map((r) => ({
      y: PAD_T + r * (H - PAD_T - PAD_B),
      label: Math.round(max * (1 - r)),
    })),
    xLabels: dates
      .map((d, i) => ({ i, d }))
      .filter(({ i }) => i % step === 0 || i === n - 1)
      .map(({ i, d }) => ({ x: x(i), text: d.slice(5) })),
    series: series.map((s) => {
      const pts = s.data.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
      return {
        key: s.series_key,
        color: s.color,
        line: pts,
        area: `${PAD_L},${baseY} ${pts} ${x(n - 1).toFixed(1)},${baseY}`,
      };
    }),
  };
}
