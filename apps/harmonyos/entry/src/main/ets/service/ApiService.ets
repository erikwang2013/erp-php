/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import http from '@ohos.net.http';
import { ApiResponse, LoginResponse } from '../model/DataModels';
import { TokenManager } from '../utils/TokenManager';
import { BASE_URL } from '../utils/Config';

/**
 * HTTP API 服务 — 支持 JWT 无感刷新
 *
 * token 双通道刷新：
 * - 预判通道：本地已知过期时刻（expires_in）时先行刷新，避免必败请求
 * - 401 通道：服务端明确返回 401 时刷新后重放原请求
 */
export class ApiService {
  // 共享 http 实例（M1 根修）：30+ 页面各自 new ApiService()，
  // 实例级 httpRequest 每页泄漏一个原生客户端；改共享后所有实例零泄漏，页面零改动。
  // 单例随 App 生命周期存活，不 destroy（destroy 会伤其他共享调用方）。
  private static sharedHttp: http.HttpRequest | null = null;
  // 单飞刷新（M2）：并发 401 共享同一刷新 Promise，等待者拿到结果后自行重放原请求
  private static refreshInFlight: Promise<boolean> | null = null;

  private static getHttp(): http.HttpRequest {
    if (!ApiService.sharedHttp) {
      ApiService.sharedHttp = http.createHttp();
    }
    return ApiService.sharedHttp;
  }

  /**
   * 发送请求（自动处理 401 和 token 刷新）
   */
  private async request<T>(method: http.RequestMethod, path: string, body?: object, retryCount: number = 0): Promise<ApiResponse<T>> {
    // 预判通道（M3）：本地判定已过期且有 refresh_token → 先刷新再带新 token 重发
    if (retryCount === 0 && TokenManager.isAccessTokenExpired() && TokenManager.getRefreshToken().length > 0) {
      const refreshed = await ApiService.doRefresh();
      if (refreshed) {
        return this.request<T>(method, path, body, retryCount + 1);
      }
      // 预判刷新失败（如网络抖动）不清 token，放行旧 token 交由下方 401 分支兜底
    }

    const token = TokenManager.getAccessToken();

    const options: http.HttpRequestOptions = {
      method: method,
      header: {
        'Content-Type': 'application/json',
        'API-Version': 'v1',
        'X-Client-Platform': 'harmonyos',
        'Authorization': (token && token.length > 0) ? `Bearer ${token}` : '',
      },
      connectTimeout: 15000,
      readTimeout: 15000,
    };

    if (body) {
      options.extraData = JSON.stringify(body);
    }

    // 后端版本段置于 URL（/api/v1/*、/admin/v1/*），与 Flutter 端一致；
    // 页面层统一写不带 v1 的业务路径，在此注入
    const versionedPath = path.replace(/^\/(admin|api)\//, '/$1/v1/');
    const url = `${BASE_URL}${versionedPath}`;
    const response = await ApiService.getHttp().request(url, options);

    // 401 通道：首轮 401 → 单飞刷新 → 成功后重放原请求
    if (response.responseCode === 401 && retryCount === 0) {
      const refreshed = await ApiService.doRefresh();
      if (refreshed) {
        // 刷新成功，用新 token 重试原始请求
        return this.request<T>(method, path, body, retryCount + 1);
      }
      // 刷新失败：清除语义已内聚在 doRefresh 的"服务器明确拒绝"路径，
      // 此处不再 clear（网络抖动不误登出），仅抛 UNAUTHORIZED 由页面映射友好文案
      throw new Error('UNAUTHORIZED');
    }

    // 二轮 401：服务器拒绝了新 token，确凿登出
    if (response.responseCode === 401) {
      await TokenManager.clearTokens();
      throw new Error('UNAUTHORIZED');
    }

    // L3：解析失败抛业务错误，不透传原生异常导致崩溃
    return ApiService.parseResult<T>(response.result as string);
  }

  /**
   * 响应 JSON 解析防护（L3）：字符串异常/非 JSON 时给友好文案
   */
  private static parseResult<T>(raw: string): ApiResponse<T> {
    try {
      return JSON.parse(raw) as ApiResponse<T>;
    } catch (_) {
      throw new Error('服务响应格式错误');
    }
  }

  /**
   * 单飞刷新（M2）：并发 401 共享同一 Promise，避免多次并发刷新互相覆盖
   */
  private static doRefresh(): Promise<boolean> {
    if (!ApiService.refreshInFlight) {
      ApiService.refreshInFlight = ApiService.performRefresh().finally(() => {
        ApiService.refreshInFlight = null;
      });
    }
    return ApiService.refreshInFlight;
  }

  /**
   * 真实刷新逻辑（仅供 doRefresh 单飞调用）
   * 清除语义只发生在"服务器明确拒绝"（refresh 响应 401）：
   * 网络异常 / 业务码异常 / 解析失败一律不清 token——现状缺陷是网络抖动即被登出
   */
  private static async performRefresh(): Promise<boolean> {
    const refreshToken = TokenManager.getRefreshToken();
    if (!refreshToken || refreshToken.length === 0) {
      return false;
    }

    try {
      const refreshResponse = await ApiService.getHttp().request(
        `${BASE_URL}/api/v1/auth/refresh`,
        {
          method: http.RequestMethod.POST,
          header: {
            'Content-Type': 'application/json',
            'API-Version': 'v1',
          },
          extraData: JSON.stringify({ refresh_token: refreshToken }),
          connectTimeout: 10000,
          readTimeout: 10000,
        }
      );

      if (refreshResponse.responseCode === 401) {
        // refresh_token 已失效 = 确凿登出：清除本地凭据
        await TokenManager.clearTokens();
        return false;
      }

      if (refreshResponse.responseCode === 200) {
        const result = JSON.parse(refreshResponse.result as string) as ApiResponse<LoginResponse>;
        if (result.code === 0 && result.data) {
          // M3：补存 expires_in，供后续预判刷新使用
          await TokenManager.saveTokens(
            result.data.access_token,
            result.data.refresh_token,
            result.data.expires_in
          );
          return true;
        }
      }
      // 其他（5xx/业务码≠0）→ false，不清 token
    } catch (_) {
      // 刷新请求本身失败（网络错误等）→ 不清 token，交由上层决定重试或提示
    }

    return false;
  }

  async get<T>(path: string): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.GET, path);
  }

  async post<T>(path: string, body?: object): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.POST, path, body);
  }

  async put<T>(path: string, body?: object): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.PUT, path, body);
  }

  async delete<T>(path: string): Promise<ApiResponse<T>> {
    return this.request<T>(http.RequestMethod.DELETE, path);
  }
}

export const apiService = new ApiService();
