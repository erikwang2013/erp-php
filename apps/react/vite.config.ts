import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

/**
 * Web 端构建配置
 *
 * 开发期把 /admin、/api、/open、/health 代理到 webman 后端，
 * 前端代码一律走同源相对路径，生产期由 Nginx 用同样的前缀转发，两端零分叉。
 * 后端 CORS_ALLOWED_ORIGIN 已放行 localhost 任意端口，直连亦可，代理只是省掉跨域头开销。
 */
// 后端端口取 .env 的 APP_HTTP_PORT（默认 8788）。需要改地址时直接改这里。
const backend = 'http://localhost:8788';

const proxyPrefixes = ['/admin', '/api', '/open', '/health', '/metrics', '/install'];

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { '@': new URL('./src', import.meta.url).pathname },
  },
  server: {
    port: 5173,
    proxy: Object.fromEntries(
      proxyPrefixes.map((p) => [p, { target: backend, changeOrigin: true }]),
    ),
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
  },
});
