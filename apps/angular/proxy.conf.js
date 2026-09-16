/**
 * 开发代理配置 — ng serve 用（angular.json 的 serve.options.proxyConfig）
 *
 * 与 React 端 vite.config.ts 一致：后端地址取仓库根目录 .env 的 APP_HTTP_PORT（默认 8788）。
 * 口令/端口只此一份，前端不再各写各的。
 * 注意：@angular/build 的 dev-server 对非 .json 配置走 require/import，
 * 所以这里能用 Node 自带的 process.loadEnvFile 读 .env（需 Node ≥ 20.12）。
 */
const { existsSync } = require('node:fs');
const { resolve } = require('node:path');

const envFile = resolve(__dirname, '../../.env');
if (existsSync(envFile)) {
  process.loadEnvFile(envFile);
}

const backend = `http://localhost:${process.env.APP_HTTP_PORT || 8788}`;
const prefixes = ['/admin', '/api', '/open', '/health', '/metrics', '/install'];

module.exports = Object.fromEntries(
  prefixes.map((prefix) => [prefix, { target: backend, changeOrigin: true }]),
);
