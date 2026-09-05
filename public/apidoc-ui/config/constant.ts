/**
 * @name Config
 * @description 项目配置
 */

// 应用名
export const APP_TITLE = 'Apidoc'

// 本地服务端口
export const VITE_PORT = 6969

// 包依赖分析（环境变量 VITE_ANALYSIS=true 时开启，默认关闭）
export const ANALYSIS = (): boolean => process.env.VITE_ANALYSIS === 'true'

// 代码压缩（默认开启 gzip + brotli，可用 VITE_BUILD_COMPRESS 覆盖，如 'gzip'、'none'）
export const COMPRESSION = (): string => process.env.VITE_BUILD_COMPRESS || 'gzip,brotli'

// 打包时删除 console
export const VITE_DROP_CONSOLE = true
