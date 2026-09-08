/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import {
  clearTokens,
  getAccessToken,
  http,
  setTokens,
  UNAUTHORIZED_EVENT,
} from '@/lib/api';

/**
 * 会话状态。
 *
 * 后端约定（已核对 AuthController）：
 * - 登录只返回 user 三字段（id/username/real_name），没有 /me 读接口 → 本地缓存
 * - 登出接口只拉黑 access_token，refresh_token 需前端自行丢弃
 * - 同账号最多 3 个并发 token，第 4 次登录会踢掉最旧会话 → 依赖 api.ts 的 401 续期
 */

export interface AuthUser {
  id: string;
  username: string;
  real_name: string;
}

interface AuthState {
  user: AuthUser | null;
  /** 有访问令牌即视为可进入应用；权限由后端 AdminPermission 兜底 */
  authed: boolean;
  login: (username: string, password: string, captchaKey: string) => Promise<void>;
  logout: () => Promise<void>;
  patchUser: (p: Partial<AuthUser>) => void;
}

const USER_KEY = 'erp_user';
const Ctx = createContext<AuthState | null>(null);

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

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(readUser);

  // 后端判定会话失效（续期也失败）时回到登录页，避免用户卡在失败提示里反复重试
  useEffect(() => {
    const onUnauth = () => {
      clearTokens();
      saveUser(null);
      setUser(null);
    };
    window.addEventListener(UNAUTHORIZED_EVENT, onUnauth);
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, onUnauth);
  }, []);

  const patchUser = useCallback((p: Partial<AuthUser>) => {
    setUser((u) => {
      if (!u) return u;
      const next = { ...u, ...p };
      saveUser(next);
      return next;
    });
  }, []);

  const login = useCallback(
    async (username: string, password: string, captchaKey: string) => {
    const data = await http.post<{
      access_token: string;
      refresh_token: string;
      user: AuthUser;
    }>('/api/v1/auth/login', {
      username,
      password,
      captcha_key: captchaKey,
    });
    setTokens(data.access_token, data.refresh_token);
    saveUser(data.user);
    setUser(data.user);
    },
    [],
  );

  const logout = useCallback(async () => {
    try {
      // 登出必须带令牌；失败也不阻断前端清理
      await http.post('/admin/v1/profile/logout', {});
    } finally {
      clearTokens();
      saveUser(null);
      setUser(null);
    }
  }, []);

  const value = useMemo<AuthState>(
    () => ({ user, authed: !!getAccessToken(), login, logout, patchUser }),
    [user, login, logout, patchUser],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error('useAuth 必须在 AuthProvider 内使用');
  return ctx;
}
