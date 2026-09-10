/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { acceptLanguage, tr } from './i18n.service';

/**
 * API 客户端 —— 平移自 React lib/api.ts（语义逐条一致）。
 *
 * 后端信封约定：{code, message, data}，code === 0 视为成功；
 * data 为 {list,total,page,limit} 形 = 分页，裸数组 = 不分页。
 * 会话约定（已核对 AuthController）：
 * - access_token 放 Authorization Bearer；localStorage key `erp_access_token`
 * - refresh_token 存 `erp_refresh_token`，401 后 single-flight POST /api/v1/auth/refresh
 * - 续期也失败 → 派发 window 'erp:unauthorized' 事件（AuthStore 监听回登录页）
 * - 同账号最多 3 并发 token，第 4 次登录踢最旧会话 —— 依赖下方 401 续期兜底
 */

export const UNAUTHORIZED_EVENT = 'erp:unauthorized';

export interface Envelope<T = unknown> {
  code: number;
  message: string;
  data: T;
}

export interface PageData<T = unknown> {
  list: T[];
  total: number;
  page: number;
  limit: number;
}

export interface RequestOptions {
  method?: string;
  body?: unknown;
  /** 401 时不触发刷新续期（防止登出等自身 401 时死循环） */
  noRetry?: boolean;
}

const ACCESS_KEY = 'erp_access_token';
const REFRESH_KEY = 'erp_refresh_token';

function readToken(key: string): string {
  try {
    return localStorage.getItem(key) ?? '';
  } catch {
    return '';
  }
}

let accessToken = readToken(ACCESS_KEY);
let refreshToken = readToken(REFRESH_KEY);
let refreshing: Promise<void> | null = null;

/** 写入新令牌；任一无 → 清理并返回 false（半套状态一律视为登出） */
export function setTokens(access: string, refresh: string): boolean {
  const ok = !!access && !!refresh;
  if (ok) {
    accessToken = access;
    refreshToken = refresh;
    localStorage.setItem(ACCESS_KEY, access);
    localStorage.setItem(REFRESH_KEY, refresh);
  } else {
    clearTokens();
  }
  return ok;
}

export function getAccessToken(): string {
  return accessToken;
}

export function clearTokens(): void {
  accessToken = '';
  refreshToken = '';
  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
}

async function rawFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set('Accept-Language', acceptLanguage());
  if (accessToken) headers.set('Authorization', `Bearer ${accessToken}`);
  if (init.body) headers.set('Content-Type', 'application/json');
  return fetch(path, { ...init, headers });
}

async function doRefresh(): Promise<void> {
  const res = await fetch('/api/v1/auth/refresh', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
  if (!res.ok) {
    clearTokens();
    throw new Error(`refresh failed: ${res.status}`);
  }
  const env = (await res.json().catch(() => null)) as Envelope<{
    access_token?: string;
    refresh_token?: string;
  }> | null;
  if (!env || env.code !== 0 || !env.data?.access_token) {
    clearTokens();
    throw new Error(env?.message || 'refresh failed');
  }
  accessToken = env.data.access_token;
  localStorage.setItem(ACCESS_KEY, accessToken);
  // 后端可能轮换 refresh_token；未轮换则沿用旧值
  if (env.data.refresh_token) {
    refreshToken = env.data.refresh_token;
    localStorage.setItem(REFRESH_KEY, refreshToken);
  }
}

/** 单飞续期：并发 401 共享同一次刷新 */
function ensureRefreshed(): Promise<void> {
  if (!refreshing) {
    refreshing = doRefresh().finally(() => {
      refreshing = null;
    });
  }
  return refreshing;
}

export class ApiError extends Error {
  readonly status: number;

  constructor(status: number, message: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

/**
 * 发送请求并解包信封；失败抛 ApiError。
 * 401（除 noRetry）→ 刷新后续期一次；续期失败 → 派发 erp:unauthorized 并抛「登录已过期」。
 */
export async function api<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const res = await rawFetch(path, {
    method: opts.method ?? 'GET',
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  });

  if (res.status === 401 && !opts.noRetry && refreshToken) {
    try {
      await ensureRefreshed();
    } catch {
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
      throw new ApiError(401, tr('登录已过期，请重新登录'));
    }
    return api<T>(path, { ...opts, noRetry: true });
  }

  const env = (await res.json().catch(() => null)) as Envelope<T> | null;
  if (!res.ok || !env || env.code !== 0) {
    const status = res.ok && env ? env.code : res.status;
    const msg = env?.message || tr('请求失败（{code}）', { code: String(status) });
    throw new ApiError(status, msg);
  }
  return env.data;
}

/** 查询串构造：丢弃 undefined/null/空串 */
export function qs(params: Record<string, string | number | undefined | null>): string {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null || v === '') continue;
    p.set(k, String(v));
  }
  const s = p.toString();
  return s ? `?${s}` : '';
}

export interface Http {
  get<T>(path: string): Promise<T>;
  post<T>(path: string, body?: unknown): Promise<T>;
  put<T>(path: string, body?: unknown): Promise<T>;
  del<T>(path: string, body?: unknown): Promise<T>;
}

export const http: Http = {
  get: (p) => api(p),
  post: (p, b) => api(p, { method: 'POST', body: b }),
  put: (p, b) => api(p, { method: 'PUT', body: b }),
  del: (p, b) => api(p, { method: 'DELETE', body: b }),
};

/** 文件下载：POST 拿 blob 走 <a download>（表格导出等） */
export async function downloadFile(path: string, filename: string, body?: unknown): Promise<void> {
  const res = await rawFetch(path, {
    method: 'POST',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const env = (await res.json().catch(() => null)) as Envelope | null;
    throw new ApiError(
      res.status,
      env?.message || tr('请求失败（{code}）', { code: String(res.status) }),
    );
  }
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
