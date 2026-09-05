/**
 * @name createVitePlugins
 * @description 封装plugins数组统一调用
 */
import type { Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import { ConfigSvgIconsPlugin } from './svgIcons'
import { AutoRegistryComponents } from './component'
import { AutoImportDeps } from './autoImport'
import { ConfigVisualizerConfig } from './visualizer'
import { ConfigCompressPlugin } from './compress'
import { ThemePreprocessor } from './theme'
import { themePreprocessorHmrPlugin } from '@zougt/vite-plugin-theme-preprocessor'
import { createHtmlPlugin } from 'vite-plugin-html'

import { version as APP_VERSION } from '../../../package.json'

export function createVitePlugins(isBuild: boolean) {
  const vitePlugins: (Plugin | Plugin[])[] = [
    // vue支持
    vue(),
    // JSX支持
    vueJsx(),
    // 自动按需引入组件
    AutoRegistryComponents(),
    // 自动按需引入依赖
    AutoImportDeps(),
    // 开启.gz/.br压缩
    ConfigCompressPlugin(),
    // 主题
    ThemePreprocessor(),
    // 主题热更新（仅开发模式；其 watcher 会让构建进程无法退出）
    ...(isBuild ? [] : [themePreprocessorHmrPlugin()]),
    createHtmlPlugin({
      inject: {
        data: {
          injectScript: `<script src="./config.js?v=${APP_VERSION}"></script>`,
        },
      },
    }),
  ]

  // vite-plugin-svg-icons
  vitePlugins.push(ConfigSvgIconsPlugin(isBuild))

  // rollup-plugin-visualizer
  vitePlugins.push(ConfigVisualizerConfig())

  return vitePlugins
}
