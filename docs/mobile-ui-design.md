# open-erp 移动/桌面响应式管理端 — 视觉与交互规范

适用:Flutter `apps/flutter`(Material 3)+ HarmonyOS `apps/harmonyos`(ArkUI)。两端同 token、同骨架、平台原生实现。本文件为总纲(≤480 行);token 明细/可抄代码在 `docs/mobile-ui-design-ext.md`(附录 A/B/C)。

## 1. 设计原则与信息架构

原则(6 条):
1. **数据密度优先**:列表/表格是骨,卡片瀑布仅用于首页与详情;一屏信息量优先于留白。
2. **弱装饰、强层级**:不渐变、不重阴影(卡片阴影仅 1 级);层级靠字号/字重/灰阶,不靠色块堆叠。
3. **主色只做一件事**:品牌蓝 `#1677FF` 用于可点(按钮/链接/选中/焦点);状态用语义色;内容文字永不染色。
4. **每屏一个主操作**:工具栏「新建」是列表页唯一主按钮;行内操作为文字链接,不设按钮。
5. **动效克制**:页面转场 250–300ms,弹窗 200ms,仅透明度+位移动画;无弹跳无循环强调。
6. **触控与鼠标双优**:移动端命中区 ≥44px,桌面 ≥32px;同一页桌面密度可高于移动。

信息架构层级(自上而下,任何页面不得颠倒):
`页面标题/返回` → `工具栏(搜索 + 筛选 + 新建/批量操作)` → `状态筛选 chips(可横滚)` → `主列表/表格` → `底部计数与分页`。
弹层权重:操作确认(危险操作必须二次确认)→ 表单弹窗 → 成功/失败 toast(不打断流程)。

## 2. 色彩系统

### 2.1 品牌主色族(10 级,浅色;深色只用 6/5/4 号,见 2.2)

| 级 | hex | 用途 |
|---|---|---|
| 1 | #E6F4FF | 选中 chip 底、行 hover 极浅填充 |
| 2 | #BAE0FF | 搜索框聚焦边框候选、描边弱 |
| 3 | #91CAFF | 选中控件边框、进度条底 |
| 4 | #69B1FF | 链接 hover(桌面)、深色主题强调文字 |
| 5 | #4096FF | 主按钮 hover(桌面)、图标强调 |
| 6 | **#1677FF** | **主色:主按钮/链接/选中/开关/焦点环** |
| 7 | #0958D9 | 按压态(active)、主色深字(浅底徽标文字) |
| 8 | #003EB3 | 强调标题深蓝(极少用) |
| 9 | #002C8C | 渐变/图表深端(极少用) |
| 10 | #001D66 | 最深端(不用在 UI,图表轴深端) |

### 2.2 语义色与中性 token(light/dark 两套 hex,直抄)

状态含义:success=已完成终态, warning=待办提醒, danger=失败/删除/驳回, info=进行中(用主蓝)。

| token | light | dark | 典型用途 |
|---|---|---|---|
| primary | #1677FF | #1677FF | 主按钮/链接/焦点 |
| primary_pressed | #0958D9 | #4096FF | 按压反馈(深色模式按浅变) |
| primary_disabled | #99BBFF | #31619B | 提交中/禁用的主按钮填充 |
| primary_bg | #E6F0FF | #1E2A3A | 主色浅底:选中 chip、徽标底 |
| success | #52C41A | #52C41A | 成功图标/实心填充 |
| success_text | #389E0D | #95DE64 | 浅底上的成功文字/徽标字 |
| success_bg | #E6FFF0 | #25331E | 成功徽标底 |
| warning | #FA8C16 | #FA8C16 | 警告图标/实心填充 |
| warning_text | #D46B08 | #FFC53D | 浅底上的警告文字 |
| warning_bg | #FFFBE6 | #392C1E | 警告徽标底 |
| danger | #FF4D4F | #FF4D4F | 删除/失败图标、危险实心按钮 |
| danger_text | #CF1322 | #FF7875 | 浅底上的危险文字(行内「删除」链接用) |
| danger_bg | #FFE6E6 | #3A2425 | 危险徽标底 |
| text_primary | #333333 | #E6E6E6 | 正文/标题(标题靠字重不加黑) |
| text_secondary | #666666 | #A6A6A6 | 次要文字:标签值、表头、卡片标签 |
| text_hint | #999999 | #737373 | 占位符、说明、时间戳 |
| text_disabled | #CCCCCC | #4D4D4D | 禁用文字、禁用控件填充 |
| text_on_primary | #FFFFFF | #FFFFFF | 主/语义实心按钮上的文字 |
| bg_page | #F5F5F5 | #141414 | 页面背景、禁用按钮底 |
| surface | #FFFFFF | #1F1F1F | 卡片/弹窗/输入框/列表底 |
| surface_alt | #F0F0F0 | #262626 | 表头底、行 hover、填充式控件底 |
| divider | #EEEEEE | #2E2E2E | 分割线、卡片细描边 |
| border | #D9D9D9 | #434343 | 控件描边(输入框/未选 chip/次按钮) |
| scrim | #000000@45% | #000000@60% | 弹窗遮罩 |

> 深色语义浅底 token(#1E2A3A 等)= 语义主色 12% 透明度叠在 #1F1F1F 上的结果;两端直接抄 hex,不必算。信息色=主蓝,不单列。图表情态色仅 BI 图表用:序列 #1677FF/#52C41A/#FA8C16/#FF4D4F/#722ED1/#13C2C2(两模式同 hex),网格线 divider、轴文字 text_hint。

### 2.3 中性灰阶 8 级(编号 g-1…g-8;surface 白另列)

| 级 | hex | 用途 |
|---|---|---|
| g-1 | #F0F0F0 | surface_alt:表头底/悬停行/禁用底 |
| g-2 | #F5F5F5 | bg_page |
| g-3 | #EEEEEE | divider |
| g-4 | #D9D9D9 | border |
| g-5 | #CCCCCC | text_disabled |
| g-6 | #999999 | text_hint |
| g-7 | #666666 | text_secondary |
| g-8 | #333333 | text_primary |

### 2.4 状态着色约定(通用词 → 徽标语义色)

| 业务状态 | 徽标 | 深浅底文字规则 |
|---|---|---|
| 草稿/未开始/关闭 | 中性(灰) | bg #F0F0F0,字 text_secondary |
| 待办提醒:待审核/待入库/待上架/待收货/待付款/待打包/待发运 | warning | warning_bg + warning_text |
| 进行中:审核中/执行中/拣货中/入库中/出库中/生产中/拣货波次中 | primary(info) | primary_bg + #0958D9(深色 #4096FF) |
| 终态成功:已审核/已入库/已上架/已完成/已出库/已付款/已收款/已发运/生效/启用 | success | success_bg + success_text |
| 终态失败:已驳回/已作废/已取消/停用/禁用/已删除/失败 | danger | danger_bg + danger_text |

具体模块把自身状态值归入上表四类即可,不许自定义新色。启用/停用(配置类实体)用 success/danger 的实心点+文字,不用徽标底:实体名后 8px 直径圆点(6px HOS)+12px 文字。

## 3. 排版

字号单位:Flutter px == HarmonyOS fp == vp,数字同值直抄。中文行高 1.5,数字行高 1.3。

| token | 字号 | 字重 | 用途 |
|---|---|---|---|
| text-xs | 10 | 400 | 徽标小注、统计卡角标、角标计数数字 |
| text-sm | 12 | 400 | 说明/时间戳/行副文本/表单错误/徽标文字(12 可 500) |
| text-md | 13 | 400 | 桌面表格正文、表头(表头 600)、筛选 chip |
| text-base | 14 | 400 | 移动行主文本、按钮字、输入字、表单 label 用 500 |
| text-lg | 16 | 500/600 | 卡片标题、详情区块标题(600)、弹窗标题 600 |
| text-xl | 20 | 600 | 页面标题(AppBar/页头)、登录标题 |
| stat-lg | 24 | 600 | 统计卡核心数值(桌面);移动用 20 |
| stat-num | 20 | 600 | 统计卡数值(移动)、次要统计 |

字重规则:正文 400,列表主文本 500,标题/按钮 600,强调数字 600(700 禁用于普通 UI)。数字一律 `fontFeatures: tabularFigures`(Flutter)/ `fontFeature: 'tnum'`? 不可用则不加——两端数值列用等宽退格对齐即可,不做花体。行高:桌面表格 48px 行内 13px 字 1 行截断,ellipsis;移动 56px 行允许主文本 1 行 + 副文本 1 行(共 2 行内)。

## 4. 间距与圆角

基准网格 4px,全部间距取 4 的倍数。两端同值。

| 场景 | 值 |
|---|---|
| 页面水平内边距 | 移动 16 / 桌面 24(Flutter 用 responsive padding 常量,不在每页手写) |
| 区块垂直节奏 | 页面内卡片之间 12(移动)/16(桌面) |
| 卡片内边距 | 12(紧凑:表头行/表单)/16(普通内容)/20(详情正文大块) |
| 卡片间距网格 | 列表/网格容器 gap:移动 12 / 桌面 16 |
| 圆角 | 卡片/弹窗 8(现状不改)、输入/按钮/选择器 6(现状不改)、chip 16(胶囊,高 32 时)、徽标 4、图标按钮圆形 |
| 分割线 | 卡片内 section 之间:1px divider + 上下留白 12;卡片之间不留线只留白 |
| 阴影 | 卡片 elevation 1 / HOS shadow(color #000000@6%, radius 8, offsetY 2);弹窗 elevation 3~4 或 HOS radius 16 @12%;禁用阴影做层次 |
| 控件高度 | 输入/按钮/选择器:桌面 36、移动 44(触控命中);小号 32(仅桌面表格筛选行内);图标按钮 40×40(移动)/36×36(桌面) |

## 5. 组件规范

### 5.1 顶部导航栏(页面级 TopBar/AppBar)
- 结构:左(移动)返回箭头 40×40 热区,chevron 20px,距左边框 4;中部/左标题 text-xl 600 text_primary,单行 ellipsis;右操作位:主操作按钮优先,至多 2 个图标操作 + 1 主按钮,超出收进「…」菜单。
- 高度:移动 56 / 桌面 64(Flutter AppBar toolbarHeight 随断点;HOS 同高 Column header)。
- 桌面 app 内嵌页(admin_layout 内容区)不复用整栏:显示页面标题 + 面包屑位,标题 20 600。
- 实现:Flutter 统一 `PageHeader` 或直接 AppBar(先统一 contentPadding 与标题样式常量,再逐页收敛);HOS 新建 `common/TopBar.ets`(参数 title/back:boolean/actions:Array<Object>)替换各页手写标题行。滚动后标题不下沉、无毛玻璃,简单纯底(surface)。

### 5.2 列表页骨架(两端最核心,所有列表页同构)
布局(自上而下,间距 12):
1. **工具栏**:左搜索框(桌面宽 240 可扩、移动 flex:1)+ 右侧「新建」主按钮(移动 44 高,文案恒显「新建」不缩为图标,<360 宽时可缩为「+」)。搜索:防抖 300ms、清空按钮、`搜索 ${keyword}`。
2. **状态筛选 chips**:横向滚动,高 32,间距 8;首项固定「全部」;chip 圆角 16,内边距 0 16;未选:白底 border 描边,字 13 #666;选中:primary_bg 底、字 #0958D9(深色 #4096FF)500。禁用态灰。附带结果计数「共 N 条」放 chips 行右端或列表底部,不重复放。
3. **列表主体**:
   - 移动(<768):行高 56;行=左主文本(14/500)+ 副文本(12/hint,1 行)堆叠,右端操作位 ≤3 个:图标 18(编辑 pencil/删除 trash 等),危险操作 danger_text;行点击进详情。左侧可选状态圆点(8px)表示实体启停。
   - 桌面(≥768,含平板横向):DataTable 行高 48(现状保留),表头 40/13/600/text_secondary,斑马纹不用、行 hover surface_alt;末列「操作」文字链接 13:常规 #1677FF、删除 danger_text;操作 >3 收进省略号菜单;列按宽度权重:名称/编号列可截断 1 行 ellipsis,时间金额列右对齐。
   - 两端列表横向滑动:桌面表格列超宽用横向滚动(冻结首列可选,默认不冻结);移动禁止左右滑动表格,必须用行堆叠式。
4. **分页**:桌面:右下分页器(共 N 条 · 每页 20,上一页/下一页 + 页码);移动:底部条「共 N 条」+ 上一页/下一页(每页 10)。接口 pageSize 参数默认值两端自洽。加载更多不在本期。
5. **状态文案模板**(占位符实施时代入资源名,走 i18n):
   - 空态:标题「暂无数据」副文案「暂无{n}记录,点击右上角新建」;搜索空:「未找到与"{q}"相关的{n}」。
   - 错误态:「加载失败,请检查网络后重试」+ 按钮「重试」(高 32)。
   - 加载态:列表骨架屏 3 行(行高同数据行,底 surface_alt 透明度 50%),禁止整页菊花。
   - 空态插图:轻量线框 icon 48px + 12 间距 + 标题 14/500 text_secondary + 副文案 12 hint;禁用重色块插图。

### 5.3 统计卡片(StatCard,Dashboard 用)
- 尺寸:卡内边距 16;标签 12/400 text_secondary,间距 4;数值 stat 20/24 600 tabular,右/下趋势行:「▲ 12.4%」(12,success_text)/「▼ 3.2%」(danger_text),与上期比较,无上期则不显示。
- 可选左侧图标 40×40 圆角 8,底用语义 primary_bg(品类各配语义色之一,不新增色),图标 20。
- 整体可点 → 跳模块列表页(hover/按压 20% 变 surface_alt)。
- Flutter 扩展现有 `stat_card.dart`(加 trend 字段/语义色映射);HOS 扩展 `common/Components.ets` 中 StatCard(加同参数)。

### 5.4 表单弹窗
- 容器:移动底部抽屉式全宽(或 92% 宽圆角 16 上滑)、桌面居中弹窗宽 480、复杂表单 640,高 <90vh 内部滚动。
- 结构:标题 16/600 左上,关闭 X 右上(20px 热区 40);body 字段区;底部操作条右对齐:取消(次按钮,白底 border 描边)→ 提交(主按钮,高 36/44),间距 8,提交宽 ≥88。提交中:主按钮 primary_disabled 填充 + 文案不变/或加小 spinner,禁用再次点击(两段现有 #99BBFF 模式升为规范)。
- 字段间距:垂直 16;label 14/500 居上,距输入 6;必填星号 danger_text 12,可输入区才标。
- 错误:字段下 4px 起 danger_text 12;提交级错误顶部红条(白底 danger_bg,danger_text 13)或 toast,不许弹 alert。
- 键盘:移动端弹窗内输入,底部按钮条不遮挡:用 viewInsets 抬升(Flutter) / `expandSafeArea` + 键盘避让(HOS `bindKeyboard`?用默认 avoid 策略即可,实施时验证)。日期/下拉类字段触发原生选择器,输入框 readOnly。
- Flutter 收敛到现有 `form_dialog.dart`(加宽度断点/按钮禁用态);HOS 新建 `common/FormDialog.ets`(title/content/confirmText/cancelText/loading),各页删手写 dialog 模板。

### 5.5 详情页
- 结构:页头(返回+标题 20 600 + 右侧操作:审核/编辑等,主按钮 1 个,其余次按钮)+ 状态徽标与单据号放首卡标题行(状态徽标左,编号 text_hint 13)。
- 分区卡片:每卡一个区块(基本信息/明细/备注/日志),卡标题 14/600 + divider 下 12;字段:label 12/400 hint 居上(或左 96 固定列),值 14/400 text_primary;桌面 ≥900 两列,移动单列;值为空显示「-」(hint)。
- 明细子表(行项目):桌面 DataTable(行 40 密集档、表头 36/12),移动只显关键 3 字段行 48 + 点进弹出项目详情(或横向滚动,二选一,实施统一)。
- Flutter:现有 `detail_page.dart` 收敛字段栅格;HOS 新建 `common/DetailCard.ets`(section 卡 + 字段行)。

### 5.6 徽标 / 按钮 / chip / 选择器
- **徽标 badge**:高 22、内边距 0 8、圆角 4、字 12/500;配色见 2.4;实心版本(高 18 字 10,白字,仅 danger/success 于桌面表格列)可选,两端默认浅底式。
- **按钮**:主按钮:primary 底白字 14/500?按钮字 14 用 500(600 略重,取 500),高 36/44,圆角 6,按压 primary_pressed(无 hover 的移动端直接按态);次按钮:白底 border 描边字 text_primary,按压底 surface_alt;危险实心(danger 底白字,用于确认弹窗主操作,宽≥88);文字按钮/行内链接:13/14 文字色、danger_text 危险,热区 ≥32;禁用:主按钮 primary_disabled 白字,次按钮灰底(bg_page)灰字(text_disabled)。加载按钮:primary_disabled + spinner。
- **chip**:状态筛选 5.2;实体标签(品类/属性):高 24 圆角 4,底 surface_alt 字 12 text_secondary,允许多标签横排 wrap,间距 8。
- **选择器**:统一触发外观 = 输入框(高 36/44 圆角 6 border)+ 右 chevron;桌面点开下拉(面板白底 shadow,选项高 32,选中项 primary 底浅?选中项文本 primary 500 + 左 ✓ 16px);移动用系统底部选择器/日期盘。面板宽度 = 触发框宽,最小 160。

### 5.7 确认弹窗(危险操作)
- 统一轻量 confirm:标题 16/600(「删除商品」),正文 14 text_secondary 两行内(「删除后不可恢复,确定删除"{name}"?」),操作:取消/危险实心「删除」;遮罩 scrim,桌面居中卡 360 宽,移动同 5.4 底部式。Flutter 收敛现有 `confirm_dialog.dart`;HOS 加入 `common/FormDialog.ets` 同文件导出 ConfirmDialog,替换各页手写 AlertDialog(alert 仅用于纯提示不可用场景)。

## 6. Dashboard 布局(业务域首页)

- 结构:页头(「工作台」/域名称 + 右「设置」图标进卡片配置)→ 统计卡区 → 图表卡(BI 折线,整行或半行)→ 待办列表卡(最近 N 条待审核/预警,行高 44,点击进列表)。
- **可配置卡片网格**:移动 2 列 × gap12;桌面(≥768)每行按 span 排:span 1 卡宽 = (屏宽 − 24×2 − 16×(cols−1))/cols,cols 桌面 4 / 移动 2;单卡默认 span 1,图表/待办 span 2(桌面整行卡)。统计卡高度 96(移动)/112(桌面)。
- 默认注册表(新项目可配,顺序可调,禁用则隐藏):今日销售额、待审核单据、库存预警、待收货、待发货、应收合计、应付合计、生产在制;待办卡:最近 10 条。卡片点击跳对应列表页(带状态过滤参数)。
- 图表规范:折线/柱,序列色 2.2 末注 6 色;轴线删除(网格即可),label 12 hint;无数据给空态文案非空图表;tooltip 白底 shadow 10 字号。
- 数据来源复用现有统计接口;卡片字段:key/label/icon/color/route/order/enabled,存 dashboard_config,两端同构(Flutter 已有 dashboard 页改配置驱动;HOS 未建的工作台页按此新建)。

## 7. 登录页 / 个人中心

- **登录**:移动整屏:品牌区(Logo 40 + 产品名 20/600 居中,上 25% 处)+ 表单卡(surface,圆角 8,内边距 24,宽限 400 居中,移动去卡直接铺底):账号/密码输入 44 高间距 16,密码可见切换(眼睛 20px),登录按钮主色高 44 全宽;底部「无法登录?联系管理员」12 hint + CopyrightBar 12 hint。深色同构图。两端构图比例同:上 logo 区高 ≈ 屏高 30%。
- **个人中心**:头像 56 圆角 50%,右侧用户名 16/600 + 岗位/部门 12 hint 一行;菜单分组卡(账号安全/偏好/关于),行高 56、图标 20 text_secondary、箭头 16 hint、行间 divider 缩进 16;危险区独立卡:退出登录行 danger_text 13(需 confirm 弹窗);头像可点换图。

## 8. 两端落地清单

### 8.1 Flutter(`apps/flutter`)
1. `lib/app/theme/app_tokens.dart` 新建:AppColors 常量类(2.2 全表 light/dark 两组) + AppSpacing/AppRadius/AppTextStyle 常量(3、4 节),文字样式用 const TextStyle 组合。
2. `lib/app/theme/app_theme.dart`:保留 `colorSchemeSeed` 与三处现状(Card r8/e1、Input r6、DataTable 行 48/表头 40),新增:light/dark 各补 `scaffoldBackgroundColor: bg_page`、DataTable 表头字色 text_secondary、focusColor primary、dividerColor 等;从 ColorScheme.fromSeed 结果 `copyWith` 语义槽(primary/onPrimary/error 等对齐 2.2);把 AppTokens 暴露为 `ThemeExtension`,页面读 `context.tokens`。表头 hover/选中行色 surface_alt 放 dataTableTheme?DataTable2 无行 hover 时在 wrapper 内自己处理。
3. 组件收敛:`data_table_wrapper.dart`(统一空/错/加载三态+分页,行高随断点:DataTableThemeData 外包一层 Builder,<768 用 56——wrapper 内 `LayoutBuilder` 提供)、`form_dialog.dart`(宽度 480/640 + 底部移动式 + 提交 loading 态)、`confirm_dialog.dart`、`stat_card.dart`(trend/语义色)、`detail_page.dart`(字段栅格),新增 `filter_bar.dart`(搜索+chips+计数,收编散落实现)。
4. 新页面按 5.2 骨架;旧页按批次替换:断点判定用现有 PHONE<768/TABLET768-1199/DESKTOP≥1200(responsive_framework 已配,勿新增断点)。
5. 深色:沿用现有 dark 开关;AppTokens.dark 生效后核对每页硬编码 Color(0xFF…) 清零(除 image 占位)。

### 8.2 HarmonyOS(`apps/harmonyos`)
1. `resources/base/element/color.json` 扩为 2.2 全表(light 值);新建 `resources/dark/element/color.json` 同 key 填 dark 值(系统深色自动切换);string.json 不动。
2. 页面硬编码替换映射(旧 hex → 新 token;token 未建则按本表新增):

| 旧 hex(次数) | 新 token | 说明 |
|---|---|---|
| #FFFFFF(213) | surface / text_on_primary(文字场景) | 底色与按钮白字分开改 |
| #F5F5F5(55) | bg_page / surface_alt | 页面底 |
| #F0F0F0(12) | surface_alt | hover/填充 |
| #EEEEEE(124) | divider | 分割线/描边 |
| #DCDCDC(0 现有) | border | 输入/控件描边(设计引入) |
| #CCCCCC(19) | text_disabled | 禁用字/填充 |
| #999999(35) | text_hint | 占位/说明 |
| #666666(38) | text_secondary | 次要文字 |
| #333333(111) | text_primary | 正文/标题 |
| #000000(1) | text_primary(或 scrim 场景判) | — |
| #1677FF(74) | primary | 主按钮/链接(按压 #0958D9→primary_pressed) |
| #99BBFF(14) | primary_disabled | 提交中按钮 |
| #E6F0FF(18) | primary_bg | 选中/徽标底 |
| #FF4D4F(53) | danger | 危险/删除(文字浅底场景换 danger_text #CF1322) |
| #FFE6E6(25) | danger_bg | 危险浅底 |
| #52C41A(24) | success | 成功(浅底文字场景 success_text) |
| #E6FFF0(12) | success_bg | 成功浅底 |

   替换工具:`rg '#[0-9A-Fa-f]{6}' pages -l`,逐文件以 token 引用替换,禁止新增未登记 hex;16 色之外新色一律补登记再使用。
3. 公共组件(新建,命名与 Flutter 侧对齐):`common/TopBar.ets`、`common/FormDialog.ets`(含 ConfirmDialog 导出)、`common/DetailCard.ets`、`common/FilterBar.ets`(搜索+chips)、`common/EmptyView/ErrorView/LoadingView` 提升为带 token 三态容器(现有 Components.ets 内改造,接受插槽/重试回调)。改造目标:29 页复制的 @State 模板收敛为每域一个页面骨架。
4. 统计卡/图表配色等 2.2 末注;图标 rawfile 静态映射补齐 8 个核心图标:search/plus/edit/trash/back/arrow-right/eye/eye-off(其余按需)。

### 8.3 两端一致性执行顺序
P1 色彩 token(两端改完,截图对 2.2 表)→ P2 公共组件收敛(先 Flutter,HOS 同步同参)→ P3 列表页骨架逐域替换(两端同域同周)→ P4 表单/详情/统计卡 → P5 Dashboard 配置化 + 深色核对。

## 9. 验收自检清单

- [ ] 两端口径同一文件的列表页:工具栏/chips/行高(56/48)/分页/三态 5 项与 5.2 逐条一致。
- [ ] 全仓 UI 层无未登记 hex(Flutter `Color(0xFF…` 除占位图、HOS rg 全扫),新旧映射按 8.2 表可回溯。
- [ ] 语义色只出自 2.2 表;状态文字/徽标色匹配 2.4 四类。
- [ ] 行内删除、作废、退出登录均过确认弹窗;审核/收款等动作成功失败有 toast,无 alert。
- [ ] 主按钮提交中禁用不重复提交;必填错误字段下内联提示非 toast。
- [ ] 字号只出自第 3 节;标题/正文/次要三级字重与色正确。
- [ ] 移动端所有可点元素 ≥44 命中;桌面 ≥32;输入/按钮高 36/44 分档正确。
- [ ] 深浅色两套:切暗色后无「亮白卡片」残留、无对比度崩坏(hint 级 ≥2.5:1、正文 ≥4.5:1)。
- [ ] 卡片 elevation 1、圆角 8/6/16/4 分档全对,无渐变装饰。
- [ ] i18n:新文案全部走 arb(Flutter)/resources 字符串(HOS),无页面写死文案。
- [ ] Dashboard 卡片可按配置显隐排序;点击带状态参数进列表。
- [ ] HOS 36 页 + Flutter 110 页全部可用,无回归:列表/详情/表单三主流程跑通,桌面窄到手机宽度无横向溢出。
