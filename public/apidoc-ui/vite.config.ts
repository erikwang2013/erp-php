import { UserConfig, ConfigEnv, loadEnv } from 'vite'
import { createVitePlugins } from './config/vite/plugins'
import { resolve } from 'path'
import { VITE_DROP_CONSOLE, VITE_PORT } from './config/constant'

function pathResolve(dir: string) {
  return resolve(process.cwd(), '.', dir)
}

// https://vitejs.dev/config/
export default ({ command, mode }: ConfigEnv): UserConfig => {
  const isBuild = command === 'build'
  // 加载环境变量到 process.env，供 config/constant.ts 读取
  Object.assign(process.env, loadEnv(mode, process.cwd(), ''))

  return {
    base: './',
    resolve: {
      alias: [
        {
          find: /\/@\//,
          replacement: pathResolve('src') + '/',
        },
        {
          find: /\/#\//,
          replacement: pathResolve('types') + '/',
        },
      ],
    },
    // plugins
    plugins: createVitePlugins(isBuild),

    // css
    css: {
      preprocessorOptions: {
        less: {
          javascriptEnabled: true,
        },
      },
    },

    // server
    server: {
      hmr: { overlay: false }, // 禁用或配置 HMR 连接 设置 server.hmr.overlay 为 false 可以禁用服务器错误遮罩层
      // 服务配置
      port: VITE_PORT, // 类型： number 指定服务器端口;
      open: false, // 类型： boolean | string在服务器启动时自动在浏览器中打开应用程序；
      cors: false, // 类型： boolean | CorsOptions 为开发服务器配置 CORS。默认启用并允许任何源
      host: '0.0.0.0', // IP配置，支持从IP启动
    },

    // esbuild（顶层选项，构建与预构建共用）
    esbuild: {
      drop: VITE_DROP_CONSOLE ? ['console'] : undefined,
    },

    // build
    build: {
      outDir: 'apidoc',
      minify: 'esbuild',
      target: 'es2018',
      rollupOptions: {
        // 确保外部化处理那些不想打包进库的依赖
        external: [],
        output: {
          manualChunks: {
            antd: ['ant-design-vue', '@ant-design/icons-vue'],
            monaco: ['monaco-editor'],
            vendor: [
              'vue',
              'vue-router',
              'pinia',
              'vue-i18n',
              'axios',
              'lodash-es',
              'marked',
              'highlight.js',
              'nprogress',
              'js-md5',
              'mockjs',
            ],
          },
        },
      },
      // Turning off brotliSize display can slightly reduce packaging time
      chunkSizeWarningLimit: 2000,
      cssTarget: 'chrome83',
    },
  }
}
