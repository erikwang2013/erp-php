# open-erp 管理端 (Flutter)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

开放ERP系统（webman v2 + Flutter 全栈 ERP）的管理端前端，使用 Flutter 3.x 构建，支持 Web（PC 管理后台风格）、iOS、Android、macOS、Windows、Linux 多平台。

## 功能页面

| 目录 | 功能 |
|------|------|
| `dashboard` | 仪表盘：经营总览/销售看板/库存看板/财务看板 + 经营看板 Tab（30日销售趋势/热销商品/订单状态/应收应付账龄/库存预警，fl_chart 折线图 + 饼图） |
| `login` / `profile` | 登录（点击验证码 + JWT）/ 个人中心 |
| `system` | 用户管理、角色权限、系统配置、操作日志 |
| `product` / `partner` | 商品/SKU/多规格/多单位/分类/品牌/价格策略、仓库库位、供应商/客户档案 |
| `purchase` / `sales` / `inventory` | 采购（申请→订单→收货→退货→结算）、销售（报价→订单→发货→退货→结算）、库存（批次/序列号/调拨/盘点/预警） |
| `finance` | 财务：应收应付/收付款/凭证/日记账/总账/固定资产/税务/多币种/预算/成本利润中心 |
| `crm` | 客户/商机/跟进/公海池/合同/报价/营销活动/服务工单/分析报表/销售漏斗 |
| `workflow` / `notification` / `project` / `hr` | 审批工作流、消息通知、项目任务工时、人事考勤薪资 |
| `manufacturing` | BOM/生产订单/工艺路线/工作站/MRP |
| `report` | 自定义报表：模板/数据集/字段/筛选器/执行/定时调度 |
| `oms` / `wms` / `tms` | 订单履约/RMA、仓储（ASN→收货→上架→波次→拣货→打包）、运输（承运商/费率/运单/轨迹） |
| `bi` / `dms` / `eam` / `quality` | BI 看板、文档管理、设备管理、质量管理 |

## 运行

### 1. 启动后端服务

按[主 README 快速开始](../../README.md#快速开始)完成安装：`composer install` → 配置 `.env` → 初始化数据库（推荐 Web 安装向导 `http://localhost:8788/install`）→ `php start.php start`。后端默认监听 `http://0.0.0.0:8788`。

### 2. 启动管理端

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome   # Web 端（PC 管理后台风格）
```

后端地址通过编译期常量 `API_BASE_URL` 注入，代码默认值为 `http://localhost:8788`，与后端默认端口一致；如需连接其他环境（如远程服务器）再显式指定：

```bash
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788
flutter build web --dart-define=API_BASE_URL=http://localhost:8788
```

其他平台：`flutter run -d <ios|android|macos|windows|linux>`；真机调试需将 `API_BASE_URL` 指向局域网可访问的后端地址。

### 3. 登录使用

使用 Web 安装向导创建的管理员账号登录（登录需先通过点击验证码校验）。登录后通过侧边栏进入各业务模块；删除用户/角色等敏感操作需在请求中二次确认密码。

## 技术要点

- 状态管理：GetX（`ApiService` 单例 + `AuthService` Token 持久化 `shared_preferences`）
- 网络：Dio + JWT Bearer 拦截器，401 自动刷新 Token
- 主题：Material 3 浅色/深色双主题；响应式布局三断点（手机/平板/桌面）
- 导出：Excel/PDF（PDF 含不可移除版权信息）；批量操作：多选批量删除、批量启禁用

## 相关文档

- [主 README](../../README.md) | [功能手册](../../docs/FUNCTIONS.md) | [API 参考](../../docs/API.md) | [安装向导](../../docs/INSTALL.md)
