# V3 内容区设计 —「经营台账」(Business Ledger)

## 设计主张
把 ERP 内容区做成一本**精细的「经营台账」**：暖纸地面 + 发丝分隔线 + 三阶柔影，用一枚替代默认蓝的「墨青」主色 `#0E7A6F` 贯穿 KPI、按钮、图表渐变；视觉只保留 **1 个同源渐变** 与 **1 档卡片 hover 抬升**，层级靠 1px 描边与留白建立——克制、高密度而不拥挤，天然贴合「财务可信」的产品气质。

## 令牌表（全部沿用现有变量名，只改值；新增 4 组）
落地时改 `apps/react/src/styles/tokens.css`、`apps/angular/src/styles/app.css` 的 `:root`（双轨同值）+ `apps/angular/src/styles/theme.less`。

| 类别 | 变量 | 新值 |
|---|---|---|
| 主色 | `--primary` / `--primary-pressed` / `--primary-disabled` / `--primary-bg` | `#0E7A6F` / `#0A5A51` / `#9DC9C2` / `#E4F2EF` |
| 品牌渐变 | `--brand-grad` | `linear-gradient(135deg,#0E7A6F,#17A08F)` |
| 语义 | `--success`(+`-text`/`-bg`) `--warning`(+…) `--danger`(+…) | `#2F7D3F/#256633/#EAF6EC`  `#A76B1B/#8C5715/#FBF3E4`  `#B02E2E/#942525/#FBEAEA` |
| 文字 | `--text-1..4` | `#1F2320` `#4B524D` `#7A837C` `#A7AEA8` |
| 面 | `--bg-page` `--surface` `--surface-alt` `--divider` `--border` | `#F5F6F4` `#fff` `#EFF1EE` `#E1E4E0` `#C9CEC9` |
| 圆角 | `--r-card` `--r-ctrl` `--r-chip` `--r-facade` | `10px` `6px` `999px` `14px` |
| 间距 | `--sp-1..6`(新) `--page-pad` `--gap` `--pad-card` | `4/8/12/16/20/24px` `16` `16` `16` |
| 字号 | `--fs-xs..--fs-stat` + `--fs-hero`(新) | `11/12/13/14/16/18/20/24px` + `30px` |
| 阴影(新) | `--shadow-1` `--shadow-2` `--shadow-facade` `--ring` | `0 1px 2px rgba(28,36,31,.05),0 1px 1px rgba(28,36,31,.03)` / `0 2px 6px rgba(28,36,31,.07),0 6px 16px rgba(28,36,31,.06)` / `0 10px 30px rgba(28,36,31,.12)` / `0 0 0 3px rgba(14,122,111,.18)` |
| 动效(新) | `--dur-1/2/3` `--ease` `--ease-out` | `120/180/260ms` `cubic-bezier(.2,.6,.3,1)` `cubic-bezier(.16,1,.3,1)` |

> 同源色推导：`--primary-bg`=主色 10% 淡底（替代 Angular 里 `color+'1F'` 十六进制 alpha 的写法差异）；禁用色取主色 40% 灰阶。

## 两页区块与交互清单
**仪表盘**：页题（渐变 accent 条 + 刷新 + 时间范围 select）→ 4 KPI 卡（图标瓦片+计数滚动+hover 抬升+趋势 pill；首卡 hero 渐变瓦片/3px 左键线）→ 销售趋势（SVG 折线 draw-in + 面积渐变 + hover 十字线 tooltip）+ 渠道分布（hover 图例联动段高亮）→ 待办/预警（hover 露出「处理」按钮，点击收起并减计数）+ 快捷入口（hover 瓦片缩放）→ 最近操作日志。
**资源列表**：页题（标题+总数+刷新+主按钮）→ 工具栏（搜索+每页条数+导出）→ 状态 Chips（带计数，点击筛选）→ 表格（发丝分隔、行 hover 淡底 + 行内动作右滑露出、缺货单元格红色加粗、浅底状态徽标）→ 分页（第 X 页/共 Y 页 + 计数）→ 副卡待处理采购订单（审核按钮改状态即更新徽标）。

## 落地映射
| 手法 | Angular（仅 ng-zorro + 现有工程） | React（零新依赖） |
|---|---|---|
| 墨青主色 | theme.less `@primary-color` 系列 + app.css `:root` 镜像 | tokens.css `--primary*` |
| 暖纸地面/发丝线 | app.css `:root` `--bg-page/--divider/--surface-alt` | tokens.css 同值 |
| 卡片柔影+hover 抬升 | dashboard/resource `*.less` 自有 `.card`（非 nz-card）：加 `box-shadow:var(--shadow-1)`、`:hover{transform:translateY(-2px);box-shadow:var(--shadow-2)}` | app.css `.card`(406 行) 加 shadow + transition |
| KPI 计数滚动 | dashboard-page.ts 组件内 rAF（已有 signal 状态） | Dashboard.tsx `useEffect`+`requestAnimationFrame` |
| 折线 draw-in / hover 十字 | dashboard-page.html `stroke-dasharray` 绑定 + TS 几何；组件 `.less` | LineChart.tsx 同 SVG 手法 |
| 图例 hover 联动 | dashboard-page.ts 加 `hoverIdx` signal，模板 `(mouseenter)` | Dashboard.tsx `useState`+`onMouseEnter` |
| 待办行 hover 动作 | dashboard-page.less `li:hover .act` 纯 CSS | app.css 同类 |
| 快捷入口瓦片 | dashboard-page.less 纯 CSS transform | app.css 同类 |
| 状态 Chips 筛选 | resource-page.less `.chip.on` 用 `--primary`；筛选逻辑已有 | app.css `.chip.on` 换 token |
| 行 hover 动作右滑 | resource-page.less `.row-actions{opacity:0}` + `tr:hover{opacity:1}` + `@media(hover:none)` 恒显 | app.css 同 + media query |
| 状态徽标 | columns.ts `TONE_COLOR` 换新语义值；`.nz-tag`→自有 `.b-*` | config tone → `.badge-*` 换 token |
| 焦点环/圆角/字号 | app.css `:focus-visible` + `--ring` | app.css `:focus-visible` + `--ring` |

## 壳集成
- 容器：沿用 `.content` `padding:var(--page-pad)`(16px) 与 `--gap` 16px，通栏自适应（可选 `max-width:1200px` 居中，仅建议）。
- 页题区：沿用 `.page-head`（8×16px accent 条，本次换 `--brand-grad`；`.page-title` 18px；右侧 `.head-actions`）。仪表盘页题右侧加「刷新+时间范围」；资源页标题旁加 `.page-total`(12px/`--text-3`)。
- 卡片：沿用 `.card`（border-radius/px border 改新 token），仪表盘 KPI 用新的 `.kpi-grid/.kpi` 自写样式（Angular 侧已有同构，React 已有 `.stat-grid`，仅换值）。
- 新增业务文案（刷新/导出/审核/处理…）需同步进两端 i18n 词典（React `zhEn.ts`、Angular l10n）。

## 已知取舍
1. **行内动作 hover 露出**伤害发现性/触屏：用 `@media(hover:none)` 恒显兜底；若产品不接受，退化为「0.35 透明常显」而非全隐。
2. **墨青替代默认蓝**：与品牌资产冲突时可直接改 `--primary` 单点回滚，语义色/阴影不受影响。
3. **阴影换描边**：卡片在 `#F5F6F4` 上需保证 `--shadow-1` 生效，Angular 侧若复用 ng-zorro 卡片默认描边需去 border；本设计 `.card` 均为自有类，纯 CSS 可覆盖。
4. **动效预算**：计数滚动/折线 draw-in 用 rAF 或 CSS keyframes，勿引入动画库；Angular 模板中需避免每次 signal 变更重启动画（用一次性标志位）。
5. **双轨令牌**：改 Angular 必须 app.css `:root` 与 theme.less 同改，否则组件 `var(--primary)` 与 ng-zorro 内部色不一致（现有债务）。
