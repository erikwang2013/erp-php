# ApiDoc UI

基于 Vue3 + TypeScript 的 API 接口文档前端，配合 [apidoc-php](https://github.com/erikwang2013/apidoc-php) 后端使用。构建产物为纯静态文件，不依赖任何后端运行环境。

## 项目介绍

[Apidoc](https://github.com/erikwang2013/apidoc-php) 是一套通过解析 PHP 注解自动生成 API 接口文档的解决方案：

- 服务端为 PHP composer 扩展（[erikwang2013/apidoc-php](https://packagist.org/packages/erikwang2013/apidoc-php)），开箱兼容 ThinkPHP、Laravel、Hyperf、Webman 等主流框架，接口代码中写几句注解即可自动产出文档数据；
- 本项目（ApiDoc UI）即该方案的**前端界面**：负责拉取接口配置与文档数据，以应用 / 版本 / 分组的形式展示接口文档，并提供在线调试、接口分享、Swagger 导出、缓存管理等一系列开箱功能。

项目来源于 [HGthecode/apidoc-php](https://github.com/erikwang2013/apidoc-php) 生态，原作者为 HG-CODE，本仓库为独立维护的 UI 前端仓库。

## 项目说明

- **纯静态前端**：构建产物（`apidoc/` 目录）无任何服务端语言依赖，可部署到任意静态 Web 服务器，或后端项目的 public 目录下直接访问；
- **运行时配置**：后端地址、接口前缀等通过与 `index.html` 同级的 `config.js`（`window.apidocFeConfig`）配置，修改后刷新即生效，**无需重新打包**；
- **前后端分工**：后端（apidoc-php）负责解析 PHP 注解 / Markdown，提供文档数据与鉴权接口；前端只做数据拉取、文档渲染与在线调试。替换或升级任一端均可独立进行；
- **多应用支持**：一套后端可管理多个应用 / 版本，前端通过 `appKey` 切换（见下方配置说明）。

## 功能特性

- 应用 / 版本 / 分组结构展示接口与文档，支持全部展开收起
- 接口详情多视图：字段表格、Json、TypeScript、在线调试
- 在线调试：请求前 / 响应后事件、全局参数（Header / Query / Body）、文件上传、Mock 规则扩展
- 接口分享、Swagger JSON 导出、缓存管理（生成 / 清除）
- 多语言、多 Host 切换，菜单自定义宽度，访问密码授权
- 内置 md5、setToken 等调试事件扩展点，可自行注册

## 技术栈

Vue 3.5 · TypeScript · Vite 6 · Ant Design Vue 3 · Pinia · Monaco Editor

## 快速开始

要求 Node.js ≥ 18。

```bash
npm ci          # 安装依赖（锁定版本，建议 ci）
npm run dev     # 本地开发，默认 http://localhost:6969（端口见 config/constant.ts 的 VITE_PORT）
```

## 打包与部署

```bash
npm run build   # vue-tsc 类型检查 + 构建，产物输出到 apidoc/ 目录
```

构建产物使用相对路径，可直接整体复制到任意静态 Web 服务器或后端项目的 public 目录下。

部署时注意：

1. `config.js` 必须与 `index.html` 同目录存放（产物中已自动携带）；
2. 替换线上旧文件前先清空旧目录：新版 `index.html` 不再引用旧版哈希资源（`assets/*`、`utils/*`），残留无用且易被缓存；
3. 改完 `config.js` 后强制刷新一次（Ctrl+F5）。`config.js?v=<版本号>` 为缓存戳，来自 package.json 的 version，发布新版即可自动失效旧缓存。

## 配置说明

### 后端地址（config.js）

运行时配置，与 `index.html` 同级，修改后刷新即生效，无需重新打包：

```js
window.apidocFeConfig = {
  HTTP: {
    HOSTS: ['https://api.example.com'], // 后端地址列表，可多个切换；留空 [] 则请求同源
    API_PREFIX: '/apidoc', // 请求前缀，注释掉时默认 "/apidoc"
    TIMEOUT: 30000,
    WITHCREDENTIALS: false,
  },
}
```

### appKey（多应用）

同一套后端可挂多个应用，前端按以下优先级选择：

1. URL 参数 `?appKey=xxx`
2. 后端 `getConfig` 返回的应用列表第一个
3. 侧边栏应用选择器手动切换

授权 token 按 appKey 分别缓存（`CACHE.PREFIX` + appKey），切换应用互不影响。

### 其他常用项（同文件）

| 配置                     | 说明                                            |
| ------------------------ | ----------------------------------------------- |
| `TITLE`                  | 页面标题                                        |
| `CACHE.PREFIX`           | 本地缓存键前缀                                  |
| `MENU.WIDTH` / `SHOWURL` | 菜单宽度 / 是否显示接口 URL                     |
| `METHOD_COLOR`           | GET / POST / PUT / DELETE 等请求类型颜色        |
| `API_DETAIL_TABS`        | 接口详情页视图顺序（table / json / ts / debug） |
| `DEBUG_EVENTS`           | 调试事件扩展（内置 md5、setToken 示例）         |
| `LANG`                   | 界面文案，可增补语言包                          |

### 构建环境变量（.env）

| 变量                  | 说明                                               |
| --------------------- | -------------------------------------------------- |
| `VITE_APP_TITLE`      | 应用标题                                           |
| `VITE_APP_DEBUG_TOOL` | 移动端调试工具：`eruda` / `vconsole`，留空关闭     |
| `VITE_DROP_CONSOLE`   | 是否在构建产物中剔除 console（config/constant.ts） |
| `VITE_BUILD_COMPRESS` | 产物压缩：默认 `gzip,brotli`，可设 `gzip` / `none` |

## 目录结构

```text
apidoc/                # 构建产物（npm run build 生成，已 gitignore）
config/vite/           # vite 插件与构建配置
public/                # 静态资源入口（config.js 等，构建时拷入产物）
src/                   # 源码（布局、视图、store、api、工具）
types/                 # 全局类型声明
```

## 相关项目

- 后端 / 注解解析引擎（本项目的来源）：[HGthecode/apidoc-php](https://github.com/erikwang2013/apidoc-php)
- composer 扩展包：[hg/apidoc](https://packagist.org/packages/erikwang2013/apidoc-php)

