# 更新日志

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

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
