/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Routes } from '@angular/router';
import { MENUS } from './config/menu';
import { authGuard } from './core/auth.guard';
import { Shell } from './layout/shell';

/**
 * 资源页路由：由菜单表展开，一条叶子一条路由。
 * 配置里的 path 形如 '/system/user'（带前导斜杠，Flutter/React 共用同一份配置），
 * Angular 的子路由 path 不能以 '/' 开头，故去掉；查询串仍由 URL 携带。
 * moduleKey 挂在分组上（叶子没有），随 data 一起带给 ResourcePage 做页头竖条配色。
 */
const resourceRoutes: Routes = MENUS.flatMap((group) =>
  group.children.map((leaf) => ({
    path: leaf.path.replace(/^\//, ''),
    loadComponent: () => import('./pages/resource-page/resource-page').then((m) => m.ResourcePage),
    data: { cfg: leaf.cfg, label: leaf.label, moduleKey: group.moduleKey },
  })),
);

/** 登录页不套外壳；其余全部挂在 Shell 下（经 authGuard）；未匹配路径回首页由 ** 兜底 */
export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login-page').then((m) => m.LoginPage),
  },
  {
    path: '',
    canActivate: [authGuard],
    component: Shell,
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./pages/dashboard/dashboard-page').then((m) => m.DashboardPage),
      },
      {
        path: 'profile',
        loadComponent: () => import('./pages/profile/profile-page').then((m) => m.ProfilePage),
      },
      {
        path: 'notification',
        loadComponent: () =>
          import('./pages/notification/notification-page').then((m) => m.NotificationPage),
      },
      ...resourceRoutes,
    ],
  },
  { path: '**', redirectTo: '/' },
];
