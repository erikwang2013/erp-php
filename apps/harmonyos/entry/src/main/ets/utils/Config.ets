/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 统一 API 基地址 — 全仓唯一配置源（H2 修复）
// 模拟器: http://10.0.2.2:8788（默认）
// 真机: http://<服务器IP>:8788
// 独立部署域名（如 http://erp.test）: 改此处即可
export const BASE_URL: string = 'http://10.0.2.2:8788'; // 只读常量，禁止运行时改写
