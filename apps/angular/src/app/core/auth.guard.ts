/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthStore } from './auth.store';

/**
 * 登录守卫 —— 对齐 React RequireAuth：无访问令牌即回登录页。
 * 返回 UrlTree 而非命令式 navigate：能直接中断本次导航，且不用订阅/竞态处理。
 */
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  const router = inject(Router);
  return auth.authed() ? true : router.createUrlTree(['/login']);
};
