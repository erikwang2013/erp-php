# 更新日志

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## v1.18.0 (2026-09-15)

文档全量对齐 + CI 发版链打通（顺带修掉 Release 自 v1.9.2 断档的真正根因）。

### 文档

- **README 项目功能与项目结构对齐现状**：`apps/` 补上 Angular 22（`apps/angular/`）与 React 19 + Vite
  （`apps/react/`）两端（此前只列 Flutter / HarmonyOS）；业务控制器 136→139（总 159）、模型 223→224、
  表 226→227、`install.sql` 表清单 163→227；域子计数订正（product 8 / purchase 8 / inventory 6），
  并补上漏列的 `open/` 域（23 域齐）
- **国际化口径统一为 13 语种**（README / CLAUDE / ARCHITECTURE / FUNCTIONS / API / EDITIONS /
  INSTALL / TEAM 八份）：后端词典 11 个语种各 542 条、`zh_CN` 533、`en` 30 —— **口径为「叶子条目」**，
  `validation.php` 的 `attributes` 是分组容器不计入（箭头口径会得 543/535/31，两者都是实测真值）；
  Angular 源词典 1453 键 / React 1447 键，按语种懒加载成独立 chunk
- **纠正三处与代码不符的陈述**：① ARCHITECTURE / TEAM 声称存在「Locale 中间件」——实测全仓无此类
  （`config/middleware.php` 全局链仅 Cors→SecurityFilter→RateLimit→TracingId），语种解析实际在
  `app/common/I18n.php` 的 `getLocale()`，由 `I18n::trans()` 调用，共改写 10 处；② `FUNCTIONS.md`
  首段同行自相矛盾（文字 19 域/163 表 vs 标注 23/227）；③ INSTALL / TEAM 陈旧计数
- EDITIONS 补记 v1.17.0 与 v1.18.0 变更段

### CI 与发版

- **release 作业不再被常红的 php 作业卡死**：`needs` 由 `[php, docs, e2e]` 改为 `[docs, e2e]`
- **修复「只打 tag、不建 Release」的真正根因**（v1.9.2 ~ v1.17.0 期间 GitHub Release 一直断档）：
  `gh` CLI 在 Actions 里必须显式注入 `GH_TOKEN`；`permissions: contents: write` 只是授权，
  不会把 token 放进环境变量，缺它直接 `exit 4`
- **修复「一次 run 打两个 tag」**：Release 步骤重复调用 `bump-version.sh --create`（按「最新 tag +1」
  计算），导致 step3 建 vN.1、step4 又建 vN.2 且只给后者建 Release；改为 step3 产出 `TAG_NAME`
  交 step4 复用，缺则报错退出
- 新增防重守卫：同一 commit 已有版本 tag 即跳过创建，并把该 tag 交给 Release 步骤补建（幂等自愈）
- 自动 Release 标题统一为纯 `x.y.z`（不带 `v` 前缀、不带附加文字）
- 现状：docs / e2e / flutter 三作业绿；php 作业仍红（CI 专属集成测试历史债 —— 建表 `ledger_id`
  重复、测试里 `tenant` 表漏 `erp_` 前缀、发票用例依赖 CI 未提供的 Elasticsearch 等），
  但已不再拦发版

## v1.17.0 (2026-09-15)

**全平台 13 语种**（后端响应消息 + Angular 管理端 + React 管理端），并补齐两端词典漏收文案。

### 国际化

- **后端响应消息 12 个语种词典全量生成**（与 zh_CN 合计 13 语种）：common 428 / modules 84 /
  validation 20 + attributes 块，键集与 zh_CN 严格对齐；新增 `scripts/gen-be-locales.mjs`
  （「英文即 key」与框架规则名两套逻辑并存、截断自愈二分重试、断点续跑）
- **Angular 管理端 13 语种**：11 个新语种词典全量生成（源词典 1453 键），语言切换常驻顶栏 +
  个人中心下拉，`acceptLanguage` 随语种声明
- **React 管理端 13 语种**：11 个新语种词典全量生成（源词典 1447 键），切换器与 Angular 同源
  （同一份 `LOCALES` 清单，label 用各语种自称、不翻译）
- **词典按语种懒加载**：每个语种各自成一个 chunk，只有真正切到该语种才下载 —— 否则 12 份词典
  会把首屏包撑爆（实测主包 343KB 未增长，12 个语种 chunk 独立产出）
- **新增 `scripts/gen-fe-locales.mjs`**：`--app angular|react` 双端一套生成器，译文**经英文中转**
  （英文词典已在手，作中间语质量更好）。缓存是「中文原文 → 译文」映射、**与端无关**，所以换产物
  路径这类改动零 API 调用即可重出；只有新增中文键才真正请求网关。断点续跑
- **质量口径（实测）**：键集与源词典缺 0 / 多 0、57 条带 `{占位符}` 的词条占位符全保留、
  除日语汉字外语种**零残留中文**、两端 config 中文覆盖率 **100%**
- **补齐漏收文案**：React 6 条、Angular 10 条（含本端独有的权限树父级 help、JSON 属性帮助串等）；
  另有 2 条斜杠空格漂移（`搜索用户名 / 姓名`）——config 里带空格仅 5 处、不带 933 处，按多数风格
  对齐为无空格，沿用既有译文零新增翻译

### 界面调整

- **语言切换改为顶栏独立图标入口**（两端）：原先藏在用户菜单里 —— 功能页面上看不见；现在
  globe 图标点开即是 13 语种清单（当前项打勾），与用户菜单互斥打开、路由变化自动收起。
  用户菜单回归「个人中心 / 退出登录」两项

### 文档

- README 增加 Angular / React 管理端启动方式（含 Node ≥ 22.22.3 的 npx 绕法、dev 代理说明）
  与 apidoc 访问/标注用法
- 统计标注按实测对齐（tests 943→960、assertions 4585→4608）

## v1.16.0 (2026-09-14)

安装可选演示数据 + CI php 作业去红 + 三处用户可见缺陷修复。

### 平台与架构

- **安装向导新增「带测试数据」选项**：勾选后追加执行 `database/install-demo.sql`；默认**不勾选**（生产安全）。
  该文件**只有数据、没有 DDL** —— schema 的唯一事实源仍是 `install.sql`，避免两份 DDL 漂移；
  演示数据 ID 统一在 `41xxxxxxxxxxxxxxx` 段，可按段整段清理。覆盖 **227 张表**（9 段手工精写 +
  207 段生成）
- **`scripts/gen-demo-data.mjs` 生成器**：**以 `information_schema` 为结构源**（不解析建表语句文本，
  早期正则版本反复漏列）；按列类型/列名/唯一键/列长出值，保证 NOT NULL 列有值、唯一键列逐行互异、
  字符串按列上限截断。**两段式**：只写 `/tmp` → 导入验证通过 → `--install` 才并入正式文件
  （曾直接覆盖，既删了手工段又因唯一键撞车导不进去）
- **CI php 作业 PHPUnit 大幅去红**：集成测试**只在 CI 真跑**（本地无 `TEST_DB_*` 时跳过），
  长期 **349 errors / 14 failures**。根因：**五处自建 Eloquent Capsule 都写着 `'prefix' => ''`**，
  注释理由是「各模型已显式声明 `$table = 'erp_xxx'`」——**实测 224 个模型无一例外都是无前缀表名**。
  前缀与 app 对齐后 → **9 errors / 4 failures**

### 其他

- **数据破坏性缺陷修复（重要）**：测试 tearDown 原先直接 `dropTableIfExists`。前缀对齐后，
  脚手架声明的 `hr_job` 会解析成**真实表 `erp_hr_job`** 并被 DROP —— 实际删除了 13 张 HR 表
  （此前该调用是空操作，因为 `hr_job` 这个无前缀表名根本不存在）。现改为 `dropTableIfCreated`：
  **建表时记账、删表时核对**，只删本次用例自己建的表，本来就存在的表永不删除
- **Angular 资源页「刷新」按钮无效**：`effect` **只跟踪同步执行期间被读取的 signal**，而 `refresh()`
  写入的 `tick` signal **从未被读取** → Angular 不会因此重跑 effect，点击毫无反应。改为 `refresh()`
  直接调 `reload()`，并删除多余的 `tick` signal
- **Webhook 订阅创建必 422「event 不能为空」**：后端要求 `event`（数组，必填），而 **Angular 与 React
  两端的 Webhook 表单都没有该字段**（同一份平行副本，两端一起缺）→ 从界面创建必然失败。后端放宽为
  也接受逗号/分号/换行分隔的字符串（**事件名白名单校验一字未动**），两端表单补上 `event` 字段
- 质量门：Angular `ng build` 退出 0 且 **0 warning**；React `tsc --noEmit` 干净；
  `phpunit 971 tests / 2728 assertions / 0 failure`；`phpstan [OK]`；`php-cs-fixer 0/644`
- 遗留：CI php 作业仍红在 PHPUnit 的 **9 errors + 4 failures**（`ledger_id` 的 ALTER 不幂等、
  `member`/`tenant` 两表在全新库中缺），另需给该作业加 `SCOUT_DRIVER=null` 清 ES 连接错误

## v1.15.0 (2026-09-12)

CI 由四点红转三绿 + 后端 i18n 英文化 + 商品规格属性/SKU 关联。

### 平台与架构

- **install.sql 语法错误修复（从零安装必失败）**：权限种子段首条 INSERT 末行 `,` 未终止、DMS 域菜单成孤儿行，
  任何全新部署都会在 `ERROR 1064` 处中断；E2E 作业自引入起一直死在 `Initialize database`。修复后实测
  导入建出 **227 张表**、权限种子 364 行
- **E2E 全链路修复（两处）**：① DB 初始化（同上）② 脚本硬编码**无版本前缀**的 API 路径，`8276a1b` 版本进路径后
  全部落入 `Route::fallback`（HTTP 200 + 业务码 404）；分两轮补齐 `/api/v1`（12 处）与 `/admin/v1`（65 处 + 矩阵 20 项）。
  作业由**长期红转绿**
- **Docs 作业改为确定性测量**：原 `tests`/`assertions` 靠真跑 phpunit 解析，属**环境函数**（CI 965/2478 vs
  本地 951/2689，两种环境不可能同时满足）；改为纯静态计数，docs 作业精简为两步
- **Flutter 作业转绿**：验证码弹框首帧 `_data!.targets.length` 空指针（`9721129` 起潜伏）导致 9 个测试派生失败；
  修实现而非改断言，`+169` 全绿；另补 refresh 路径 2 条常驻测试（原 16 处 `onRefresh` 全为 null，该分支零覆盖）
- **PHPStan 归零**：170 处 → 0（12 处**必 fatal** 的真缺陷已修：`$this->fail()` 用在无该方法/无基类的控制器、
  `$request->merge()` 在 `support\Request` 上根本不存在；其余为框架盲区），**未降 level、未加宽泛 ignore**；
  基线由官方生成器重算（净减 412 行）
- **PHP CS Fixer 全量格式化 228 文件**：解开被它挡在 PHPUnit 之前的死结——此前 CI 的
  `Initialize database`/`PHPUnit`/`Coverage` 三步**一直被 skipped、从未真跑**
- **i18n 英文化（全量）**：① `getLocale()` 经 `request()` 取 `Accept-Language`，**193 调用点零改动**即让
  `resource/translations/` 13 个语种目录首次真正生效（原地区段剥离 bug 致 11 个目录永不可达）
  ② 词典改为**英文即 key**（13 语种重键、`en` 目录留空由引擎回键），`file.key` 前缀改为白名单以容纳英文句中的点号
  ③ **1084 处**响应消息（`fail`/`success`）脱裸中文，`zh_CN` 补 **428 条**词条；中文路径逐字不变（176 条键逐字回解验证）
- **商品规格属性（`erp_product_spec.attrs` JSON）**：后端全栈（归一化 + 422 边界 + 16 用例）、Angular `kind:'tags'`
  胶囊渲染与规格页编辑；**SKU 关联规格**（`erp_product_sku.spec_id`，`update` 支持 SKU 差量同步含增/改/删与
  价格引用守卫）；商品表「规格型号」改为规格下拉

### 其他

- **真缺陷修复**：库存新增接口校验**不存在的 `name` 列**（正常 payload 必 422 / 绕过则插全零行）改为经
  `InventoryService::stockIn()` 入库；`update` 禁止直接改 `quantity`（绕开流水与成本重算）；
  RateLimit 三处失效（版本段未归一、放行分支缺前导斜杠、`/admin/user/batch` 被短键抢先命中致 10/min 从未生效）；
  内容区 `.content` 为 `overflow:hidden` 且各页面自身无滚动区 → 权限管理/操作日志/仪表盘滚不动
- **测试基建**：`scripts/check-fe-endpoints.mjs` 前端端点契约探测（两端 config 声明的 API 路径逐个打真实后端，
  401/422=已接上、404=未接上，**无需凭据**）—— 实测 **147/147 命中**；`check-ng-spec-attrs.mjs` 扩至 49 例
- **HOS**：表单操作按钮移入 `DetailCard`（12 页）+ 8 个超 500 行页拆分（590/589/587/567/563/561/551/541 → 391–376）
- 质量门：Angular `ng build` 退出 0 且 0 warning；React `tsc --noEmit` + `vite build` 干净；
  HOS `BUILD SUCCESSFUL`；`phpunit 971 tests / 2728 assertions / 0 failure`；`doc-stats --check` 202/202
- 遗留：CI 的 php 作业仍红在 `PHPUnit Tests`（**首次真正执行**后暴露的环境类红点：ES 无服务 + 集成用例表名缺
  `erp_` 前缀 + 14 个契约失败）；`InvoiceService` 有 service 层中文返回未走 `trans()`；
  `erp_product.spec` 存规格名而非 ID（无 FK，改规格名不回溯）

## v1.14.0 (2026-09-12)

五端并行批：Angular 规格属性可视化、HOS/Flutter 超长文件拆分、后端 hashid 解码修正、CI E2E 根因修复。

### 平台与架构

- **Angular 商品详情展示 SKU 规格属性**：`erp_product_sku.spec_attrs`（JSON 字符串）解析为只读胶囊，
  渲染于商品详情弹层；引擎新增 `detailFetch?: boolean` opt-in（不配 = 零请求零回归），详情请求序号
  与列表分离（共用会让"打开详情"把在飞的列表刷新判过期，骨架屏永久卡住）；
  `scripts/check-ng-spec-attrs.mjs` 18 例自检（抽真身跑，非复制副本）
- **Angular 结构 CSS 上提收官**：`shell.less`（347 行）整块上提并拆出新 `styles/shell.css`，
  `anyComponentStyle` 超预算 warning 清零（v1.13.0 遗留的 406 B 就此消解）；`mask`/`modal`
  弹窗骨架去重；**预算阈值未动**（4kB/8kB 原样），主包 423.6→418.0 kB
- **Angular 翻译词典按域拆分**：`core/zh-en.ts`（1463 行）→ `core/zh-en/{index,part1..4}`，
  1442 词条合并前后 deep-equal，导入路径 `'./zh-en'` 未变（目录 index 解析）；
  `scripts/check-ng-i18n-dict.mjs` 守跨切片重名（重名会静默覆盖，TS1117 只拦同一字面量）
- **HOS 超长页拆分**：`OrderListPage` 705→457、`ShipmentListPage` 642→398、`DashboardPage` 645→474，
  抽出三个子组件（ArkTS 无 `part`，用 `@Link` 逐项绑定 + `onSubmit/onCancel` 回调）；17 个列表页补空态；
  subtitle `String()` 三处 null 兜底；`check_hos_l10n.sh` 修复三元兄弟 key 漏收
  （392 static refs / 158 dynamic keys / 605 双语 key 一致）
- **Flutter 超长页拆分**：`dashboard_page` 992→223、`report_page` 847→56（`part` 拆分，对外导入路径不变）；
  KPI 下拉刷新重滚（闸门改挂数据空而非 loading）、路由转场补 reduce-motion 分支、语义重复 key 收敛
  （`hrName`→`commonName` 68 调用点）、oms chip 改按枚举 index 匹配
- **后端 hashid 解码顺序统一**：`RoleController::normalizePermissionIds` 原为 `is_numeric` 先行，
  纯数字 hashid 会被当原生 id 直通（枚举 1..200000 实测 279 个命中，如 `9→'69'`）→ **授错权限**；
  统一为 `decodeFlexibleId` 同序（hashid 优先、数字兜底）；`store/update` 归一失败改 422 拒绝
  （原为退化原值静默写脏值，致"角色已建、权限未同步"半成品）
- **后端 FK 编码下发补齐**：CRM 合同 / OMS 退货 / OMS 履约三控制器补 `encodeIds` 字段
- **CI E2E 根因修复**：`SCOUT_DRIVER` 的 sed 用字面量 `=elasticsearch`，而 `.env.example` 自 `93a20f0`
  起默认值是 `opensearch` —— 字面量静默不匹配（exit 0 无输出）→ 驱动原样带入 → 登录 `save()` 触发
  scout 同步 → 打向 CI 里不存在的 OpenSearch（127.0.0.1:6205）连接失败 500。改通配替换，订正两处与
  事实不符的注释，清理三处 `CHANGE_ME_` 死步骤（`.env.example` 已无该占位值）

### 其他

- 质量门：Angular `ng build` exit 0 / WARNING 0；Flutter `analyze` 0 issue、`test` 与基线同数（+158 −9，
  9 红均为登录/验证码既有红）；HOS `BUILD SUCCESSFUL` ERROR 0；phpunit 951 tests / 2689 assertions
  0 failure；`phpstan` 175→170（本批只清回归 5 处，**配置未动**）；`doc-stats --check` 202/202 归零
- 自检脚本矩阵：`check-ng-tree-semantics.mjs`、`check-ng-i18n-dict.mjs`、`check-ng-spec-attrs.mjs`、
  `tools/check_hos_l10n.sh`
- 遗留：PHPStan 历史基线 **170 处**（多为模型魔法属性的 `property.notFound`）仍在，CI php 作业继续红；
  后端 i18n 链路 `I18n::getLocale()` 零调用方 + 1065 处裸中文未走 `trans()`（多语从未生效）；
  `perm:{adminId}` 缓存全仓无失效路径（改权限最长 60s 生效）；HOS 九个表单页按钮行在 `DetailCard` 卡外
  （九页一致的统一惯例，未擅自改动）

## v1.13.0 (2026-09-10)

技术债清偿批：文档统计对齐、Angular 结构 CSS 去重、Flutter 令牌三端统一。

### 平台与架构

- **doc-stats 文档统计对齐**：`docs/**` 与 README 共 **28 个文件 202 处** `<!-- stats:key=value -->`
  标注按实测值刷新（php_files 339→479、controllers 122→158、tables 163→226、models 161→223、
  services 27→63、modules 19→23、test_files 59→107、tests 500→948、assertions 2226→2063），
  覆盖全部 12 种语言翻译档；`scripts/doc-stats.sh --check` 归零，CI 的 Docs 作业由红转绿
- **Angular 结构 CSS 上提去重**：`dashboard-page.less` 与 `resource-page.less` 逐字节重复的
  React 镜像结构类（`.page-head`/`.head-bar`/`.page-title`/`.card`/`.table`/`.center-block` 等）
  上提全局 `app.css` 单一来源；`resource-page.less` 组件样式预算警告清零
- **Flutter 令牌三端统一**：`apps/flutter/lib/app/theme/app_tokens.dart` 的 light 调色板全面镜像
  Web `tokens.css`（墨青主色族、暖纸面、暖灰文字、语义色三件套、r-card 10/r-facade 14/r-chip 999），
  品牌渐变与圆角同步；dark 仅主色族与圆角跟随（V3 设计为亮色，dark 语义色保留既有深色变体）

### 其他

- 质量门：React `tsc` + `vite build`；Angular `tsc` + `ngc` + `ng build`；Flutter `flutter analyze`
  无告警 + 令牌组件测试全过
- 顺手修复 `captcha_verify_dialog.dart` 既有 `curly_braces_in_flow_control_structures` lint
- 遗留：`shell.less` 超 4 kB 预算 406 B（纯布局 CSS，无共享结构类可上提，未剥注释凑预算）；
  profile/notification 两页结构类因细微差异未纳入本轮上提；CI 的 PHPStan 静态分析、E2E 数据库种子
  两项为环境类红点，与本次改动无关

## v1.12.0 (2026-09-10)

设计系统「经营台账 V3」落地 Web 两端（React + Angular），含设计稿产物与页面交互升级。

### 平台与架构

- **设计语言「经营台账 V3」**：暖纸地面 + 发丝分隔线 + 三阶柔影；墨青 `#0E7A6F` 替代默认蓝作唯一主色，
  层级靠 1px 描边与留白建立。设计稿三件套入档 `docs/design/v3/`（dashboard.html / resource-page.html /
  DESIGN.md，零外部依赖，浏览器可直接打开）
- **令牌双轨落地**：React `tokens.css` 与 Angular `app.css :root` + `theme.less` 同值；
  新增 `--shadow-1/2`、`--ring`、`--sp-*` 间距刻度、`--dur-*`/`--ease` 动效、`--fs-hero` 字号阶
- **Flutter 未跟随**：`app_tokens.dart` 与 Web 端令牌分叉（有意为之，三端统一待单独跨端批）

### 业务覆盖

- **仪表盘**：KPI 计数滚动（rAF/signal，货币等非纯数字原样渲染）、首卡 hero 渐变键线、
  SVG 折线 draw-in + 面积淡入、分布/图表色吃令牌、图标瓦片改 `color-mix` 浅底（替代 hex-alpha 拼串）
- **资源列表页**（139 页共用模板）：状态胶囊实底选中 + 计数位 `.n`、行内动作 hover 右滑露出
  （`@media(hover:none)` 触屏恒显兜底）、表格暖色发丝、焦点环 `--ring`、卡片三阶柔影
- 两端硬编码 `#1677FF` 残留清零（含 accent 回退、焦点环、验证码标记）

### 其他

- 质量门：React `tsc --noEmit` + `vite build`；Angular `tsc --noEmit` + `ngc` + `ng build` 全绿
- 已知取舍：芯片分类计数需后端返回计数（CSS 就位、接口未动）；`resource-page.less` 超 4 kB 预算 503 B
  （交互样式所致，按债务政策未调阈值，待结构 CSS 上提消解）

## v1.11.0 (2026-09-10)

新增 Angular 22 管理端页面层（第 4 个前端端），图形验证码升级为 click/rotate/slider 三型并跨端对齐。

### 平台与架构

- **Angular 22 管理端页面层**：`apps/angular/` 从脚手架基线补齐页面层（73 文件，提交 `47a3a7a`）——
  壳层 Shell + PageTabs（八业务屏切换 + 标签页）、登录（图形验证码）/仪表盘/通知中心/个人中心；
  **config 驱动资源页引擎**：菜单=路由=资源配置单一事实源，一个 `ResourcePage` 渲染全部资源页，
  配 `resource-form` 新建/编辑；核心 api（envelope `code===0` 解包 + 401 单飞刷新）、auth、
  i18n（`TrPipe` 非纯，词典反查）、format、toast
- **跨端人机验证（click / rotate / slider 三型）**：`CaptchaController` 生成时定类型，三型共用一次性
  `captchaKey` 校验后即焚；Flutter / HarmonyOS / React 三端弹窗与本地化词条同步对齐（提交 `9721129`）
- **poster 图像驱动改回 `auto`**：`config/poster.php` 重写为分区注释版，`driver` 恢复自动检测
  （随 poster-php 升至 `v1.2.10`）

### 业务覆盖

- React 端配套：`CaptchaDialog` 三型交互、`zhEn` 词典补齐，登录 / Shell / PageTabs / 仪表盘 /
  通知中心 / 个人中心随动

### 其他

- 质量门：Angular 侧 `tsc --noEmit` + `ngc` + `ng build` 全绿（初始 1.24 MB，懒加载分包）；
  后端 `tests/CaptchaTest.php` 增补三型断言
- 遗留：Angular 结构 CSS 待上提全局层（`shell.less` 4.41 kB 超 4 kB 预算 406 B，warning 非 error）、
  弹窗外壳双实现（手写 `cap-*` vs `nz-modal`）待统一、PageTabs 无 keep-alive、`core/zh-en.ts` 1461 行待拆

## v1.10.0 (2026-09-08)

开放管理后台新增 Web 端（React + Vite）。

### 平台与架构

- **配置驱动 CRUD 引擎**：菜单=路由=资源配置单一事实源；`ResourcePage` 通用列表/搜索/状态筛选/
  分页/详情/新增编辑/删除/业务动作；列与表单字段可从行样本推断，任意后端资源两行配置即可上线
- **Web 端 i18n**：zh/en 词典反查（缺词条回退中文原文），localStorage 持久化，随 Accept-Language 与后端联动
- **布局与基础页**：登录 + 点击验证码 + 鉴权路由守卫 + 顶部业务屏/侧边栏、个人中心、仪表盘、通知中心

### 业务覆盖

- 8 业务屏 139 个资源页：系统管理 / 商品资料 / 采购销售 / 财务管理 / 客户管理 / 订单履约 /
  生产制造 / 协同管理（含委外收发、招聘绩效、培训社保、项目成本、租户管理等 P0-P2 模块）
- 行内业务动作按后端语义接线（审核/结账/启停/发布/归档/启停租户等，敏感操作二次密码）
- 提交：`0f27eb3`（基线 121 页）→ `6778051`/`fed83c7`/`686c37d`/`2ea552f`（+18 页补齐）

### 其他

- 质量门：每批 tsc --noEmit + vite build 全绿
- 遗留：需参数化查询/下拉弹层的专用流程（银企对账、招聘漏斗、租户开通等）待专用页面批；
  en 词典 236/664 词条覆盖待补齐

## v1.4.0 (2026-09-05)

路线图 FEATURE_ROADMAP-2026-09 P0+P1+P2 全项交付。

### 平台与架构

- **全站接口路径版本化**：管理端 `/admin/v1/*`（RBAC 权限匹配自动剥离版本段，权限数据零迁移）、客户端 `/api/v1/*`（API-Version 请求头机制已移除）、开放接口 `/open/v1/*`
- **单据打印模板引擎 (B1)**：模板模型 + 占位符渲染（扁平/点路径/date/datetime/二维码）+ dompdf PDF
- **可视化流程设计器 (B3)**：画布数据模型（节点坐标/边/分支条件/驳回回边）+ 图校验 + 路由求解
- **多租户 (B5)**：租户开通/停用/续费/到期计费 + TenantScope 请求上下文隔离（中间件 seam 预留）
- **渠道通知 (B4)**：短信/邮件渠道驱动抽象 + 发送日志 + 幂等 + 失败重试
- **表单自定义字段 (B7)**：主档表 custom_fields JSON 扩展 + 类型化校验 + 动态表单 schema

### P0 财务/组织

- 多组织 + 合并报表（抵消分录，权益法/成本法）
- 存货/生产成本核算（领料/人工/制费归集 → 完工结转 → 差异结转）

### P1 业务深化

- 工序报工 + 计件工资 + 委外订单核销 (M1+M2)
- 客户信用控制：额度/账期/冻结/超限超期拦截 (F7)
- 产能负荷：工作站日历例外 + 粗能力负荷报表 (M3)
- 批次/序列号追溯链 + 近效期预警 (M6)
- 招聘漏斗 + 绩效考核 KPI/360 (H1+H2)
- 设备点检扫码闭环，异常自动联动维修单 (E1)
- 项目成本归集（工时×费率）+ 预算偏差损益 (P1)

### P2 差异化

- 承兑汇票台账 + 银企对账单导入自动核销 (F6)
- 进项发票池 + 数电票开票出口（适配器 + Mock）(F5)
- 会员价值引擎：储值/积分/卡券 (C1)
- 培训课程学分 + 社保基数规则与工资条视图 (H3+H4)

### 其他

- 质量基线：phpunit 916+ 用例 / 6322 断言、phpstan 0 错误；全部金额/比例运算 bcmath
- 消息目录多语言：韩/俄/德/法/西/葡/印地/阿拉伯/孟加拉/印尼/日（随目录批次）

## v1.3.0

- 全量 bcmath 金额/成本改造（移动加权、核销、报表字符串精度）
