/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 后端 API 客户端
 *
 * 统一约定（与 app/admin/controller/BaseController 对齐）：
 * - 信封：{ code, message, data }，code === 0 为成功
 * - 分页：data = { list, total, page, limit }
 * - ID：URL 路径与载荷中的 id 均为 hashid 加密串（非数字）
 * - 鉴权：Authorization: Bearer <access_token>，401 时用 refresh_token 续期一次
 */

import { acceptLanguage, tr } from '@/lib/i18n';

export interface Envelope<T = unknown> {
  code: number;
  message: string;
  data: T;
}

export interface PageData<T = Record<string, unknown>> {
  list: T[];
  total: number;
  page: number;
  limit: number;
}

export class ApiError extends Error {
  readonly code: number;

  constructor(code: number, message: string) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
  }
}

const ACCESS_KEY = 'erp_access_token';
const REFRESH_KEY = 'erp_refresh_token';

/** 会话彻底失效（续期也失败）时广播，外壳据此清理状态并跳回登录页 */
export const UNAUTHORIZED_EVENT = 'erp:unauthorized';

let accessToken: string | null = localStorage.getItem(ACCESS_KEY);
let refreshToken: string | null = localStorage.getItem(REFRESH_KEY);
let refreshing: Promise<boolean> | null = null;

export function getAccessToken(): string | null {
  return accessToken;
}

/** 登录成功后写入令牌对；返回 false 表示刷新令牌无效 */
export function setTokens(access: string | null, refresh: string | null): boolean {
  accessToken = access;
  refreshToken = refresh;
  if (access) localStorage.setItem(ACCESS_KEY, access);
  else localStorage.removeItem(ACCESS_KEY);
  if (refresh) localStorage.setItem(REFRESH_KEY, refresh);
  else localStorage.removeItem(REFRESH_KEY);
  return !!refresh;
}

export function clearTokens(): void {
  accessToken = null;
  refreshToken = null;
  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: unknown;
  /** 为 true 时跳过 401 续期（登录/刷新接口自身） */
  noRetry?: boolean;
}

async function rawFetch(path: string, init: RequestInit): Promise<Response> {
  const headers = new Headers(init.headers);
  if (accessToken) headers.set('Authorization', `Bearer ${accessToken}`);
  // 语言跟随前端偏好：webman I18n 据此返回对应语言文案
  headers.set('Accept-Language', acceptLanguage());
  return fetch(path, { ...init, headers });
}

async function doRefresh(): Promise<boolean> {
  if (!refreshToken) return false;
  try {
    const res = await rawFetch('/api/v1/auth/refresh', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refreshToken }),
    });
    const env = (await res.json()) as Envelope<{
      access_token?: string;
      refresh_token?: string;
    }>;
    if (env.code === 0 && env.data.access_token) {
      accessToken = env.data.access_token;
      localStorage.setItem(ACCESS_KEY, accessToken);
      if (env.data.refresh_token) {
        refreshToken = env.data.refresh_token;
        localStorage.setItem(REFRESH_KEY, refreshToken);
      }
      return true;
    }
  } catch {
    // 网络异常：视为续期失败，由调用方走登出
  }
  clearTokens();
  return false;
}

/** 并发 401 共享同一次续期请求，避免多标签/多请求各自刷新互踢 */
export async function ensureRefreshed(): Promise<boolean> {
  if (refreshing) return refreshing;
  refreshing = doRefresh().finally(() => {
    refreshing = null;
  });
  return refreshing;
}

/** 发送请求并解包信封；失败抛 ApiError */
export async function api<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const res = await rawFetch(path, {
    method: opts.method ?? 'GET',
    headers: opts.body !== undefined ? { 'Content-Type': 'application/json' } : undefined,
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  });

  const env = (await res.json().catch(() => null)) as Envelope<T> | null;
  if (!env) throw new ApiError(res.status, tr('请求失败（{code}）', { code: res.status }));

  if (env.code !== 0) {
    // 令牌过期：续期一次后重放原请求
    if (env.code === 401 && !opts.noRetry) {
      if (await ensureRefreshed()) return api(path, opts);
      // 续期失败 = 会话真的没了：广播让外壳清理并回登录页，否则用户只会反复看到失败提示
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
      throw new ApiError(401, tr('登录已过期，请重新登录'));
    }
    throw new ApiError(env.code, env.message);
  }

  return env.data;
}

export const http = {
  get: <T>(path: string) => api<T>(path),
  post: <T>(path: string, body?: unknown) => api<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => api<T>(path, { method: 'PUT', body }),
  del: <T>(path: string) => api<T>(path, { method: 'DELETE' }),
};

/** 分页查询参数序列化（丢弃空值） */
export function qs(params: Record<string, string | number | undefined | null>): string {
  const sp = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') sp.set(k, String(v));
  }
  const s = sp.toString();
  return s ? `?${s}` : '';
}

/** 下载二进制接口（导出 Excel/PDF）：带鉴权头，成功后触发浏览器保存 */
export async function downloadFile(path: string, filename: string, body?: unknown): Promise<void> {
  const headers = new Headers();
  headers.set('Content-Type', 'application/json');
  if (accessToken) headers.set('Authorization', `Bearer ${accessToken}`);

  const res = await fetch(path, { method: 'POST', headers, body: JSON.stringify(body ?? {}) });
  if (!res.ok) {
    const env = (await res.json().catch(() => null)) as Envelope | null;
    throw new ApiError(env?.code ?? res.status, env?.message ?? tr('下载失败（{code}）', { code: res.status }));
  }

  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
