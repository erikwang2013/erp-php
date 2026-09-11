/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  ChangeDetectionStrategy,
  Component,
  OnDestroy,
  OnInit,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs';
import { MENUS, NOTIFICATION_PATH, RESOURCE_ROUTES, SCREENS, SPECIAL_ROUTES } from '../config/menu';
import { http } from '../core/api.service';
import { AuthStore } from '../core/auth.store';
import { currentLocale, setLocale } from '../core/i18n.service';
import { TrPipe } from '../core/tr.pipe';
import { IconComponent } from '../ui/icon';
import { PageTabs } from './page-tabs';

/** 当前路由所属业务屏下标（仪表盘/通知/个人中心等特殊页回退 0）—— 与 React screenOf 一致 */
function screenOf(path: string): number {
  const hit = SCREENS.findIndex((s) =>
    s.groups.some((g) => g.children.some((c) => c.path === path)),
  );
  return hit >= 0 ? hit : 0;
}

/**
 * 应用外壳：左侧边栏 + 顶栏 + 业务屏切换条 + 多标签内容区。逐条对齐 React Shell.tsx。
 *
 * 路由同步（React 里是 effect）在这里收成一条 NavigationEnd 订阅：切屏、展开命中分组、刷新面包屑。
 * 侧边栏菜单搜索只搜「当前屏」（与 React 相同）：命中分组名时该组仍按过滤后的子项渲染（可能为空）。
 *
 * 样式不在此组件：外壳是页面级唯一的结构层，样式上提到 src/styles/app.css 的「外壳」区块
 * （与 React 把 shell 写在全局 styles/app.css 同构）——组件样式要计入 anyComponentStyle 预算，全局层不计。
 */
@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [TrPipe, IconComponent, PageTabs],
  templateUrl: './shell.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Shell implements OnInit, OnDestroy {
  private readonly router = inject(Router);
  private readonly auth = inject(AuthStore);

  readonly SPECIAL_ROUTES = SPECIAL_ROUTES;
  readonly SCREENS = SCREENS;
  readonly NOTIFICATION_PATH = NOTIFICATION_PATH;

  readonly url = signal(this.router.url.split('?')[0]);
  readonly folded = signal(false);
  readonly userOpen = signal(false);
  readonly query = signal('');
  readonly screen = signal(screenOf(this.url()));
  /** 手工展开的分组名；搜索态下所有命中分组强制展开，不写回这里 */
  readonly open = signal<ReadonlySet<string>>(new Set<string>());
  readonly unread = signal(0);

  readonly user = this.auth.user;
  readonly initial = computed(() => {
    const u = this.user();
    return (u?.real_name || u?.username || '?').slice(0, 1);
  });
  readonly searching = computed(() => this.query().trim() !== '');
  readonly crumbs = computed(() => this.crumbOf(this.url()));
  /** 当前屏可见菜单：非搜索态原样；搜索态按组名/子项名过滤 */
  readonly menus = computed(() => {
    const groups = SCREENS[this.screen()].groups;
    if (!this.searching()) return groups;
    const q = this.query().trim().toLowerCase();
    return groups
      .map((g) => {
        const hitGroup = g.label.toLowerCase().includes(q);
        const children = g.children.filter((c) => c.label.toLowerCase().includes(q));
        return hitGroup || children.length > 0 ? { ...g, children } : null;
      })
      .filter((g): g is (typeof groups)[number] => g !== null);
  });

  /** 分组是否展开：搜索态全展开 */
  isOpen(label: string): boolean {
    return this.searching() || this.open().has(label);
  }

  /** 未读数轮询定时器（60s；React 是 useEffect + setInterval，语义相同） */
  private timer?: ReturnType<typeof setInterval>;

  constructor() {
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => {
        const p = this.router.url.split('?')[0];
        this.url.set(p);
        this.screen.set(screenOf(p));
        const g = MENUS.find((m) => m.children.some((c) => c.path === p));
        if (g && !this.open().has(g.label)) this.open.set(new Set([g.label]));
      });
  }

  ngOnInit(): void {
    this.loadUnread();
    this.timer = setInterval(() => this.loadUnread(), 60_000);
  }

  ngOnDestroy(): void {
    clearInterval(this.timer);
  }

  private loadUnread(): void {
    http
      .get<{ count: number }>(`${NOTIFICATION_PATH}/unread-count`)
      .then((d) => this.unread.set(Number(d?.count ?? 0)))
      .catch(() => undefined);
  }

  private crumbOf(path: string): string[] {
    const route = RESOURCE_ROUTES.find((r) => r.path === path);
    if (route) {
      const screen = SCREENS.find((s) =>
        s.groups.some((g) => g.children.some((c) => c.path === path)),
      );
      const group = screen?.groups.find((g) => g.children.some((c) => c.path === path));
      return [screen?.label, group?.label, route.leaf.cfg.title].filter((x): x is string => !!x);
    }
    if (path === '/dashboard') return ['仪表盘'];
    if (path.startsWith(NOTIFICATION_PATH)) return ['通知中心'];
    if (path.startsWith('/profile')) return ['个人中心'];
    return ['管理后台'];
  }

  toggleGroup(label: string): void {
    this.open.update((s) => {
      const n = new Set(s);
      if (n.has(label)) n.delete(label);
      else n.add(label);
      return n;
    });
  }

  onSearch(e: Event): void {
    this.query.set((e.target as HTMLInputElement).value);
  }

  go(path: string): void {
    if (path !== this.url()) void this.router.navigateByUrl(path);
  }

  /** 语言切换：模板里的中文都走 | tr（impure 管道），改完 signal 立即重渲染 */
  toggleLocale(e: Event): void {
    e.stopPropagation();
    setLocale(currentLocale() === 'zh' ? 'en' : 'zh');
    this.userOpen.set(false);
  }

  async doLogout(): Promise<void> {
    this.userOpen.set(false);
    await this.auth.logout();
    void this.router.navigateByUrl('/login', { replaceUrl: true });
  }
}
