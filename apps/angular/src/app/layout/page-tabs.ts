/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  ChangeDetectionStrategy,
  Component,
  ViewEncapsulation,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { NOTIFICATION_PATH, RESOURCE_ROUTES } from '../config/menu';
import { TrPipe } from '../core/tr.pipe';
import { IconComponent } from '../ui/icon';

/** 首页页签：不可关闭（与 React PageTabs 同约定） */
const HOME = '/dashboard';

interface Tab {
  path: string;
  label: string;
}

/** 路径 → 页签文案；未注册路由返回 null（不建签，由路由的 ** 兜底跳回首页） */
function labelOf(path: string): string | null {
  if (path === HOME) return '仪表盘';
  if (path === NOTIFICATION_PATH) return '通知中心';
  if (path === '/profile') return '个人中心';
  return RESOURCE_ROUTES.find((r) => r.path === path)?.leaf.cfg.title ?? null;
}

/**
 * 多标签页条 + 路由出口 —— 对齐 React PageTabs 的页签语义（建签/关闭/关其他/关全部）。
 *
 * 与 React 的唯一差别：React 把打开的页签全部挂载、用 hidden 切换（保活），
 * Angular 的 router-outlet 只渲染当前路由 → 切签会重建页面（无保活）。
 * 这是刻意取舍：保活要自建 RouteReuseStrategy / 手工挂载组件，代价远超收益。
 *
 * ViewEncapsulation.None：路由目标是动态组件，宿主元素没有本组件的 _ngcontent 属性，
 * 靠 .tabbody > * 撑满高度的规则必须无封装才能命中。
 */
@Component({
  selector: 'app-page-tabs',
  standalone: true,
  imports: [RouterOutlet, TrPipe, IconComponent],
  templateUrl: './page-tabs.html',
  styleUrl: './page-tabs.less',
  changeDetection: ChangeDetectionStrategy.OnPush,
  encapsulation: ViewEncapsulation.None,
  host: { class: 'page-tabs' },
})
export class PageTabs {
  private readonly router = inject(Router);
  /** 当前路径（去查询串）—— 与 React location.pathname 等价 */
  readonly path = signal(this.router.url.split('?')[0]);
  readonly tabs = signal<Tab[]>([{ path: HOME, label: '仪表盘' }]);
  readonly HOME = HOME;

  constructor() {
    this.sync(this.path());
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => {
        const p = this.router.url.split('?')[0];
        this.path.set(p);
        this.sync(p);
      });
  }

  private sync(p: string): void {
    const label = labelOf(p);
    if (label && !this.tabs().some((t) => t.path === p)) {
      this.tabs.update((ts) => [...ts, { path: p, label }]);
    }
  }

  go(p: string): void {
    if (p !== this.path()) void this.router.navigateByUrl(p);
  }

  closeTab(p: string, e: Event): void {
    e.stopPropagation();
    if (p === HOME) return;
    const tabs = this.tabs();
    const i = tabs.findIndex((t) => t.path === p);
    const next = tabs.filter((t) => t.path !== p);
    this.tabs.set(next);
    // 关掉的是当前页 → 退到原位置的下一张，没有则退到末尾
    if (p === this.path()) {
      const target = next[i] ?? next[next.length - 1];
      if (target) void this.router.navigateByUrl(target.path);
    }
  }

  closeOthers(): void {
    const cur = this.tabs().find((t) => t.path === this.path()) ?? this.tabs()[0];
    this.tabs.set(
      cur.path === HOME
        ? [{ path: HOME, label: '仪表盘' }]
        : [
            { path: HOME, label: '仪表盘' },
            { path: cur.path, label: cur.label },
          ],
    );
  }

  closeAll(): void {
    this.tabs.set([{ path: HOME, label: '仪表盘' }]);
    if (this.path() !== HOME) void this.router.navigateByUrl(HOME);
  }
}
