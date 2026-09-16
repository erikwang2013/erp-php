import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

/**
 * Web 端构建配置
 *
 * 开发期把 /admin、/api、/open、/health 代理到 webman 后端，
 * 前端代码一律走同源相对路径，生产期由 Nginx 用同样的前缀转发，两端零分叉。
 * 后端 CORS_ALLOWED_ORIGIN 已放行 localhost 任意端口，直连亦可，代理只是省掉跨域头开销。
 */
// 端口统一读仓库根目录 .env（REACT_DEV_PORT / APP_HTTP_PORT），与后端同一份配置
const rootDir = new URL('../../', import.meta.url).pathname;

const proxyPrefixes = ['/admin', '/api', '/open', '/health', '/metrics', '/install'];

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, rootDir, '');
  const backend = `http://localhost:${env.APP_HTTP_PORT || 8788}`;

  return {
    plugins: [react()],
    resolve: {
      alias: { '@': new URL('./src', import.meta.url).pathname },
    },
    server: {
      port: Number(env.REACT_DEV_PORT || 5173),
      proxy: Object.fromEntries(
        proxyPrefixes.map((p) => [p, { target: backend, changeOrigin: true }]),
      ),
    },
    build: {
      outDir: 'dist',
      sourcemap: false,
    },
  };
});
