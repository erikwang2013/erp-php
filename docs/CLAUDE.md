# 开放管理后台 (open-admin)

基于 webman v2 + Flutter 的全栈管理后台系统。

![章鱼吉祥物](mascot.svg)

## 版权声明

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **不可修改、不可移除、不可逆。** 所有新建文件必须包含上述版权声明作为文件头注释。

## 生态系统路线图

> 设计规范: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> 架构文档: `docs/ARCHITECTURE.md` §21
> 功能矩阵: `docs/FUNCTIONS.md` §19

**当前综合评分 89/100** — 全量路线图 P0~P3 已完成，22 模块全栈覆盖，生产可用。

| 阶段 | 工期 | 交付物 | 状态 |
|------|------|--------|------|
| 🔵 **P0** 前端生态 | 3-4 周 | 97 Flutter 页 + 34 HarmonyOS 页 + 4 通用组件 | ✅ |
| 🟢 **P1** 业务深度 | 4-6 周 | 财务引擎 + 薪资引擎 + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** 运维可靠性 | 1-2 周 | 迁移回滚 + 自动备份 + TraceId + 队列双驱动 | ✅ |
| 🟣 **P3** 体验增强 | 2-3 周 | BI看板 + EAM + 多租户 + DMS + 7新表 | ✅ |

**测试**: <!-- stats:tests=500 --> tests, <!-- stats:assertions=2171 --> assertions（51 skipped）— ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## 功能清单

| 域 | 功能 |
|----|------|
| 认证 | 登录/注册/刷新/登出 + 验证码 + 账号锁定 + 会话限制 |
| 仪表盘 | 经营总览/销售看板/库存看板/财务看板（Redis 5m 缓存）|
| 用户 | CRUD + 批量删除/启禁用 + Excel 导入 |
| 角色权限 | CRUD + 权限树 + RBAC method.path 鉴权 |
| 系统配置 | 键值对 CRUD |
| 操作审计 | 日志查询 + 8 平台来源端自动检测 |
| 文件 | 上传 + Excel/PDF 导出（敏感数据脱敏）|
| 安全 | 18 层纵深防御（XSS/SQL注入/CSRF/限流/CSP...）|
| 运维 | 健康检查/Prometheus 指标/API 文档/security.txt + Docker + CI/CD |
| 商品管理 | 商品/SKU/分类/品牌/仓库/库位/供应商/客户 |
| 采购管理 | 申请→订单→收货→退货→结算（自动入库+生成应付）|
| 销售管理 | 报价→订单→发货→退货→结算（自动出库+生成应收）|
| 库存管理 | 实时库存/流水/批次/调拨/盘点/预警（移动加权平均成本）|
| 财务管理 | 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算 |
| CRM | 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 |
| 审批工作流 | 工作流定义/提交/批准/拒绝/撤回/我的审批 |
| 消息通知 | 通知列表/已读/全部已读/未读计数 |
| 项目管理 | 项目/任务/工时记录 |
| 人力资源 | 部门/员工/职位/考勤/请假/薪资 |
| 生产制造 | BOM/生产订单/工艺路线/工作站/MRP |
| 自定义报表 | 报表模板/数据集/字段/筛选器/执行/定时调度 |
| OMS 订单管理 | 多渠道订单/履约编排/库存预占(ATP)/RMA退换货/渠道管理 |
| WMS 仓储管理 | 库区库位(层级+条码)/入库(ASN→收货→上架)/出库(波次→拣货→打包) |
| TMS 运输管理 | 承运商/运费比价/运单面单/物流轨迹(webhook) |
| QMS 质量管理 | 进料IQC/制程IPQC/出货OQC检验 + 检验标准 + 不合格品处理 |
| EAM 设备管理 | 设备台账/保养计划/维修工单/备件管理 |
| DMS 文档管理 | 文档分类/文档/版本管理 |
| BI 看板 | 看板布局/图表组件 |

## 技术栈

### 后端
- PHP 8.3+, webman v2 (workerman/webman)
- 数据库: MySQL 8.0+，表前缀 `erp_`
- 主键: BIGINT 非自增，由 `erikwang2013/snowflake-php` 生成
- API 层 ID 加解密: `erikwang2013/hashids`
- JWT 认证: `erikwang2013/jwt-webman`
- API 敏感数据加解密: `erikwang2013/encryption`
- 数据库敏感字段加解密: `erikwang2013/encryptable`
- ES 同步与查询: `erikwang2013/webman-scout`
- 国家旗帜: `erikwang2013/season`
- API 文档生成: `erikwang2013/apidoc-php` | 注解式，访问 /apidoc

### 前端
- Flutter 3.x，源码目录 `apps/flutter/`
- Web 端按 PC 管理后台风格设计（非移动端 App 风格）
- 支持客户端和管理员端
- HarmonyOS ArkTS，源码目录 `apps/harmonyos/`

## 项目结构

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/                  # 插件配置（erikwang2013/* + hg/*）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## 中间件执行链

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## 安全增强

- **HTTP 方法限制**：SecurityFilter 仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD，非标准方法返回 405
- **CSP 头**：Content-Security-Policy + X-Permitted-Cross-Domain-Policies 注入所有响应
- **账号锁定**：连续 5 次登录失败，账号锁定 15 分钟
- **并发会话限制**：同一用户最多 3 个有效 Token，超出时最旧 Token 加入黑名单
- **security.txt**：`/.well-known/security.txt` RFC 9116 端点
- **Nginx 安全配置**：`docs/nginx-security.conf` 反向代理安全加固参考

## API 版本策略

版本通过请求头 `API-Version` 控制（默认 `v1`），不在 URL 中体现：

```bash
curl -H "API-Version: v1" http://localhost:8788/api/auth/login
```

新增版本只需创建 `app/api/{version}/controller/` 目录并注册到 `ApiVersion` 中间件。

## 限流策略

Redis 滑动窗口（Lua 原子化），默认 60 次/分钟/IP/路由：
- 登录: 10 次/分钟
- 注册: 5 次/分钟
- 响应头: `X-RateLimit-Limit/Remaining/Reset`，超限附加 `Retry-After`

## 代码规范

### PHP
- 全局函数/类引用不加前置 `\`，使用 `use` 导入
- 配置文件必须包含中文注释说明每个配置项的含义
- 所有新建 `.php` 文件头必须包含版权声明

### 数据库
- 表前缀: `erp_`
- 主键 `id`: BIGINT 类型，非自增，由 snowflake 生成
- 敏感字段使用 `erikwang2013/encryptable` trait 自动加解密
- schema 以 database/install.sql 为唯一事实源（单文件 SQL）

### Flutter
- Web 端布局使用 PC 管理后台风格（侧边栏 + 顶栏 + 内容区）
- 使用 GetX 状态管理，`ApiService` 单例（Dio + JWT 拦截器）
- Token 持久化使用 `shared_preferences`
- 响应式断点: 移动端 (< 768px) 与桌面端 (>= 768px)

### HarmonyOS
- 使用 `@ohos.net.http` 原生 HTTP 客户端
- Token 无感刷新：401 时自动调用 `/api/auth/refresh`
- 刷新失败自动重定向登录页

## 已知技术债

> 以下清单由 `grep -rn "new .*Service(" app/controller/` 实测（26 处），与代码事实一致。
> **P5 不重构**：控制器直建服务为既有模式，仅在新建代码时改走容器注入（`support\Container`），存量代码维持现状。

| 模块 | 直建服务数 | 说明 |
|------|-----------|------|
| wms | 8 | 收货/上架/波次/拣货/打包等流程服务 |
| finance | 6 | 应收应付/核销/日记账/结转等 |
| tms | 5 | 运单/比价/轨迹/运费发票 |
| oms | 3 | 履约/预占/RMA |
| quality | 2 | 检验/不合格品处理 |
| hr | 2 | 薪资/考勤 |

## 部署

### Docker Compose（推荐生产环境）

项目根目录 `docker-compose.yml` 编排 5 个服务：

| 服务 | 说明 |
|------|------|
| `nginx` | Nginx 反向代理（80/443），静态文件服务 |
| `app` | webman PHP 8.3 应用，`Dockerfile` 构建（含 OPcache + event + redis） |
| `mysql` | MySQL 8.0，数据卷持久化 |
| `redis` | Redis 7 Alpine，缓存/限流/Session |
| `elasticsearch` | Elasticsearch 8.x，全文检索 |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` 定义 GitHub Actions 流水线（PHP 8.2/8.3/8.4 矩阵）：

- PHP 语法检查 (`php -l`)
- PHPStan 静态分析 (`vendor/bin/phpstan analyse`)
- PHP CS Fixer 代码风格检查 (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit 单元测试
- Composer 安全审计 (`composer audit --no-dev`)

### 数据库备份

`database/backup/backup.sh` — mysqldump + gzip，自动清理 30 天前旧备份。
`database/backup/restore.sh` — 交互式恢复，列出可用备份供选择。

### 监控

`GET /metrics` 端点（`MetricsController`）输出 Prometheus text format，包含 5 个 gauge 指标：
- `openadmin_http_requests_total` — 请求总数
- `openadmin_active_users` — 活跃用户数
- `openadmin_db_connection_status` — 数据库连接状态 (0/1)
- `openadmin_redis_connection_status` — Redis 连接状态 (0/1)
- `openadmin_memory_usage_bytes` — 内存使用量
