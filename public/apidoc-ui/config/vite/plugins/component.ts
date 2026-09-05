/**
 * @name  AutoRegistryComponents
 * @description 按需加载，自动引入组件
 */
import Components from 'unplugin-vue-components/vite'
import { AntDesignVueResolver, VueUseComponentsResolver } from 'unplugin-vue-components/resolvers'
export const AutoRegistryComponents = () => {
  return Components({
    // dirs: ['src/components'],
    extensions: ['vue', 'md'],
    deep: true,
    dts: 'types/components.d.ts',
    directoryAsNamespace: false,
    globalNamespaces: [],
    directives: true,
    include: [/\.vue$/, /\.vue\?vue/, /\.md$/],
    exclude: [/[\\/]node_modules[\\/]/, /[\\/]\.git[\\/]/, /[\\/]\.nuxt[\\/]/],
    // importStyle: 'less'：antd 组件样式改走 less 主题管线（否则默认的预编译 css 永不随
    // theme-dark 作用域变色，弹窗/下拉/表格等模块在深色主题下保持白色）
    resolvers: [AntDesignVueResolver({ importStyle: 'less' }), VueUseComponentsResolver()],
  })
}
