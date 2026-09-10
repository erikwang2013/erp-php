/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Injectable, signal } from '@angular/core';
import { clearTokens, getAccessToken, http, setTokens, UNAUTHORIZED_EVENT } from './api.service';

/**
 * 会话状态 —— 平移自 React state/auth.tsx。
 *
 * 后端约定（已核对 AuthController）：
 * - 登录只返回 user 三字段（id/username/real_name），没有 /me 读接口 → 本地缓存
 * - 登出接口只拉黑 access_token，refresh_token 需前端自行丢弃
 * - 同账号最多 3 个并发 token，第 4 次登录会踢掉最旧会话 → 依赖 api.service 的 401 续期
 */

export interface AuthUser {
  id: string;
  username: string;
  real_name: string;
}

export interface LoginResult {
  access_token: string;
  refresh_token: string;
  user: AuthUser;
}

const USER_KEY = 'erp_user';

function readUser(): AuthUser | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    const u = JSON.parse(raw) as AuthUser;
    return u && u.username ? u : null;
  } catch {
    return null;
  }
}

function saveUser(u: AuthUser | null): void {
  if (u) localStorage.setItem(USER_KEY, JSON.stringify(u));
  else localStorage.removeItem(USER_KEY);
}

@Injectable({ providedIn: 'root' })
export class AuthStore {
  readonly user = signal<AuthUser | null>(readUser());
  /** 有访问令牌即视为可进入应用；权限由后端 AdminPermission 兜底 */
  readonly authed = signal<boolean>(!!getAccessToken());

  constructor() {
    // 后端判定会话失效（续期也失败）时回到登录页，避免用户卡在失败提示里反复重试
    window.addEventListener(UNAUTHORIZED_EVENT, () => {
      clearTokens();
      saveUser(null);
      this.user.set(null);
      this.authed.set(false);
    });
  }

  async login(username: string, password: string, captchaKey: string): Promise<void> {
    const data = await http.post<LoginResult>('/api/v1/auth/login', {
      username,
      password,
      captcha_key: captchaKey,
    });
    setTokens(data.access_token, data.refresh_token);
    saveUser(data.user);
    this.user.set(data.user);
    this.authed.set(true);
  }

  async logout(): Promise<void> {
    try {
      // 登出必须带令牌；失败也不阻断前端清理
      await http.post('/admin/v1/profile/logout', {});
    } finally {
      clearTokens();
      saveUser(null);
      this.user.set(null);
      this.authed.set(false);
    }
  }

  patchUser(p: Partial<AuthUser>): void {
    const u = this.user();
    if (!u) return;
    const next = { ...u, ...p };
    saveUser(next);
    this.user.set(next);
  }
}
