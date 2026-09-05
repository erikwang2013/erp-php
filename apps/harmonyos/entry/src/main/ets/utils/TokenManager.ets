/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import preferences from '@ohos.data.preferences';
import { common } from '@kit.AbilityKit';

/**
 * JWT Token 管理 — 内存缓存 + Preferences 磁盘持久化
 *
 * 双写策略：
 * - 内存缓存使用 AppStorage（进程内共享，ApiService / EntryAbility / 页面零延迟读取）
 * - 磁盘持久化使用 @ohos.data.preferences（应用冷启动时由 EntryAbility 调用 init 恢复登录态）
 *
 * 运行时各模块读取内存缓存；init 只在冷启动执行一次，从磁盘恢复并回填内存缓存，
 * 保证杀掉进程后重启仍能保持登录态。
 */
export class TokenManager {
  // Preferences 存储文件名
  private static readonly STORE_NAME = 'open_admin_token_store';
  // 与历史 AppStorage key 保持一致，避免旧版本缓存失效
  private static readonly ACCESS_TOKEN_KEY = 'access_token';
  private static readonly REFRESH_TOKEN_KEY = 'refresh_token';
  private static readonly USERNAME_KEY = 'adminUsername';
  // token 过期时刻（毫秒时间戳，存 string）— 全新 key，无旧版本兼容负担
  private static readonly EXPIRES_AT_KEY = 'token_expires_at';

  // Preferences 实例（init 成功后可用）
  private static prefs: preferences.Preferences | null = null;

  /**
   * 初始化：获取 Preferences 实例并从磁盘恢复内存缓存。
   * 由 EntryAbility 冷启动时调用，幂等可重复执行。
   *
   * @param context Ability 上下文，用于定位应用私有存储目录
   */
  static async init(context: common.UIAbilityContext): Promise<void> {
    const options: preferences.Options = { name: TokenManager.STORE_NAME };
    TokenManager.prefs = await preferences.getPreferences(context, options);

    // 从磁盘读取并回填内存缓存（AppStorage），恢复登录态
    const accessToken = await TokenManager.prefs.get(TokenManager.ACCESS_TOKEN_KEY, '') as string;
    const refreshToken = await TokenManager.prefs.get(TokenManager.REFRESH_TOKEN_KEY, '') as string;
    const username = await TokenManager.prefs.get(TokenManager.USERNAME_KEY, '') as string;
    const expiresAt = await TokenManager.prefs.get(TokenManager.EXPIRES_AT_KEY, '') as string;

    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, username);
    AppStorage.setOrCreate(TokenManager.EXPIRES_AT_KEY, expiresAt);
  }

  /**
   * 保存 token：内存缓存 + 磁盘持久化双写
   * @param expiresInSeconds 登录/刷新响应里的过期时长（秒）；省略或 <=0 表示未知，
   *   过期判定全靠 401 兜底，不做预判
   */
  static async saveTokens(accessToken: string, refreshToken: string, expiresInSeconds?: number): Promise<void> {
    // 先更新内存缓存，保证后续请求立即读到新 token
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
    // 过期时刻：有明确时长才预判；未知则清空（存储介质限制见类注释，明文属已知边界）
    const expiresAt = (expiresInSeconds !== undefined && expiresInSeconds > 0)
      ? String(Date.now() + expiresInSeconds * 1000)
      : '';
    AppStorage.setOrCreate(TokenManager.EXPIRES_AT_KEY, expiresAt);
    // 再异步落盘（顺序执行，确保磁盘与内存最终一致）
    await TokenManager.putToDisk(TokenManager.ACCESS_TOKEN_KEY, accessToken);
    await TokenManager.putToDisk(TokenManager.REFRESH_TOKEN_KEY, refreshToken);
    await TokenManager.putToDisk(TokenManager.EXPIRES_AT_KEY, expiresAt);
  }

  /**
   * 保存登录用户名：内存缓存 + 磁盘持久化双写
   */
  static async saveUsername(username: string): Promise<void> {
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, username);
    await TokenManager.putToDisk(TokenManager.USERNAME_KEY, username);
  }

  static getAccessToken(): string {
    return AppStorage.get(TokenManager.ACCESS_TOKEN_KEY) as string ?? '';
  }

  static getRefreshToken(): string {
    return AppStorage.get(TokenManager.REFRESH_TOKEN_KEY) as string ?? '';
  }

  static getUsername(): string {
    return AppStorage.get(TokenManager.USERNAME_KEY) as string ?? '';
  }

  /**
   * 清除 token 与登录信息：内存缓存 + 磁盘持久化双写
   */
  static async clearTokens(): Promise<void> {
    AppStorage.setOrCreate(TokenManager.ACCESS_TOKEN_KEY, '');
    AppStorage.setOrCreate(TokenManager.REFRESH_TOKEN_KEY, '');
    AppStorage.setOrCreate(TokenManager.USERNAME_KEY, '');
    AppStorage.setOrCreate(TokenManager.EXPIRES_AT_KEY, '');
    await TokenManager.putToDisk(TokenManager.ACCESS_TOKEN_KEY, '');
    await TokenManager.putToDisk(TokenManager.REFRESH_TOKEN_KEY, '');
    await TokenManager.putToDisk(TokenManager.USERNAME_KEY, '');
    await TokenManager.putToDisk(TokenManager.EXPIRES_AT_KEY, '');
  }

  /**
   * 是否已登录：以 access_token 是否非空判断
   */
  static isLoggedIn(): boolean {
    return (TokenManager.getAccessToken() ?? '').length > 0;
  }

  /**
   * 是否已过有效期（含 30s 宽容差，避免边界 401）。
   * 无过期时刻（如旧版本登录态）返回 false，交由 401 通道兜底。
   */
  static isAccessTokenExpired(): boolean {
    const expiresAt = Number(AppStorage.get(TokenManager.EXPIRES_AT_KEY) as string ?? '');
    if (expiresAt <= 0) {
      return false;
    }
    return Date.now() > expiresAt - 30000;
  }

  /**
   * 写入磁盘：Preferences 的 put 仅更新内存态，需显式 flush 才会真正落盘
   */
  private static async putToDisk(key: string, value: string): Promise<void> {
    const prefs = TokenManager.prefs;
    // init 尚未完成时跳过磁盘写入（内存缓存仍保证运行期一致性）
    if (!prefs) {
      return;
    }
    try {
      await prefs.put(key, value);
      await prefs.flush();
    } catch (err) {
      // 落盘失败不静默：内存缓存仍可用，但需暴露问题便于排查（安全存储属后续设计）
      console.error('TokenManager 持久化失败', key, err);
    }
  }
}
