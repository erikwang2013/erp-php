/**
 * @name ConfigCompressPlugin
 * @description 开启 gzip / brotli 压缩
 */
import viteCompression from 'vite-plugin-compression'
import { COMPRESSION } from '../../constant'

export const ConfigCompressPlugin = () => {
  const types = COMPRESSION()
    .split(',')
    .map((t) => t.trim())
  if (!types.length || types.includes('none')) {
    return []
  }
  const plugins: any[] = []
  if (types.includes('gzip')) {
    plugins.push(
      viteCompression({
        ext: '.gz',
        algorithm: 'gzip',
        verbose: true,
        deleteOriginFile: false,
      }),
    )
  }
  if (types.includes('brotli')) {
    plugins.push(
      viteCompression({
        ext: '.br',
        algorithm: 'brotliCompress',
        verbose: true,
        deleteOriginFile: false,
      }),
    )
  }
  return plugins
}
