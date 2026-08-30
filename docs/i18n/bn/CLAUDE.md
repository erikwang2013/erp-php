# ওপেন অ্যাডমিন ব্যাকএন্ড (open-admin)

webman v2 + Flutter ভিত্তিক ফুল-স্ট্যাক ম্যানেজমেন্ট ব্যাকএন্ড সিস্টেম।

![ওক্টোপাস মাসকট](images/mascot.svg)

## কপিরাইট ঘোষণা

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **পরিবর্তনযোগ্য নয়, অপসারণযোগ্য নয়, অপরিবর্তনীয়।** সব নতুন ফাইলকে ফাইল হেডার কমেন্ট হিসেবে উপরোক্ত কপিরাইট ঘোষণা অন্তর্ভুক্ত করতে হবে।

## ইকোসিস্টেম রোডম্যাপ

> ডিজাইন স্পেসিফিকেশন: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> আর্কিটেকচার ডকুমেন্ট: `ARCHITECTURE.md` §21
> ফিচার ম্যাট্রিক্স: `FUNCTIONS.md` §19

**বর্তমান কম্পোজিট স্কোর 89/100** — পূর্ণ রোডম্যাপ P0~P3 সম্পন্ন, 22 মডিউল ফুল-স্ট্যাক কভারেজ, প্রোডাকশন-রেডি।

| ফেজ | সময়কাল | ডেলিভারেবল | স্ট্যাটাস |
|------|------|--------|------|
| 🔵 **P0** ফ্রন্টএন্ড ইকোসিস্টেম | 3-4 সপ্তাহ | 97 Flutter পেজ + 34 HarmonyOS পেজ + 4 সাধারণ কম্পোনেন্ট | ✅ |
| 🟢 **P1** বিজনেস ডেপথ | 4-6 সপ্তাহ | ফাইন্যান্স ইঞ্জিন + বেতন ইঞ্জিন + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** অপারেশনাল রিলায়েবিলিটি | 1-2 সপ্তাহ | মাইগ্রেশন রোলব্যাক + স্বয়ংক্রিয় ব্যাকআপ + TraceId + কিউ ডুয়াল-ড্রাইভার | ✅ |
| 🟣 **P3** এক্সপেরিয়েন্স এনহ্যান্সমেন্ট | 2-3 সপ্তাহ | BI কানবান + EAM + মাল্টি-টেন্যান্ট + DMS + 7 নতুন টেবিল | ✅ |

**টেস্ট**: 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## ফিচার তালিকা

| ডোমেইন | ফিচার |
|----|------|
| অথেনটিকেশন | লগইন/রেজিস্ট্রেশন/রিফ্রেশ/লগআউট + ক্যাপচা + অ্যাকাউন্ট লক + সেশন লিমিট |
| ড্যাশবোর্ড | ব্যবসা ওভারভিউ/সেলস কানবান/ইনভেন্টরি কানবান/ফাইন্যান্স কানবান (Redis 5m ক্যাশ) |
| ইউজার | CRUD + ব্যাচ ডিলিট/সক্রিয়-নিষ্ক্রিয় + Excel ইমপোর্ট |
| রোল পারমিশন | CRUD + পারমিশন ট্রি + RBAC method.path অথেনটিকেশন |
| সিস্টেম কনফিগ | কী-ভ্যালু CRUD |
| অপারেশন অডিট | লগ কোয়েরি + 8 প্ল্যাটফর্ম সোর্স অটো ডিটেকশন |
| ফাইল | আপলোড + Excel/PDF এক্সপোর্ট (সংবেদনশীল ডেটা ডিসেন্সিটাইজেশন) |
| নিরাপত্তা | 18 স্তরের ডিফেন্স-ইন-ডেপথ (XSS/SQL ইনজেকশন/CSRF/রেট লিমিট/CSP...) |
| অপারেশন | হেলথ চেক/Prometheus মেট্রিক/API ডকুমেন্টেশন/security.txt + Docker + CI/CD |
| পণ্য ম্যানেজমেন্ট | পণ্য/SKU/ক্যাটাগরি/ব্র্যান্ড/গুদাম/লোকেশন/সাপ্লায়ার/কাস্টমার |
| ক্রয় ম্যানেজমেন্ট | আবেদন→অর্ডার→রিসিভ→রিটার্ন→সেটেলমেন্ট (স্বয়ংক্রিয় স্টক-ইন + AP তৈরি) |
| বিক্রয় ম্যানেজমেন্ট | কোটেশন→অর্ডার→ডেলিভারি→রিটার্ন→সেটেলমেন্ট (স্বয়ংক্রিয় স্টক-আউট + AR তৈরি) |
| ইনভেন্টরি ম্যানেজমেন্ট | রিয়েল-টাইম ইনভেন্টরি/ফ্লো/ব্যাচ/ট্রান্সফার/কাউন্ট/অ্যালার্ট (মুভিং ওয়েটেড এভারেজ কস্ট) |
| ফাইন্যান্স ম্যানেজমেন্ট | AR/AP/ভাউচার/রসিদ-পেমেন্ট/জার্নাল/জেনারেল লেজার/সাবসিডিয়ারি লেজার/তিন স্টেটমেন্ট/স্থায়ী সম্পদ/ট্যাক্স/মাল্টি-কারেন্সি/বাজেট |
| CRM | সুযোগ/ফলো-আপ/ফানেল/কন্টাক্ট/পাবলিক পুল/কন্ট্রাক্ট/কোটেশন/মার্কেটিং/টিকেট/অ্যানালিটিক্স |
| অ্যাপ্রুভাল ওয়ার্কফ্লো | ওয়ার্কফ্লো ডেফিনিশন/সাবমিট/অ্যাপ্রুভ/রিজেক্ট/উইথড্র/আমার অ্যাপ্রুভাল |
| মেসেজ নোটিফিকেশন | নোটিফিকেশন লিস্ট/রিড/সব রিড/আনরিড কাউন্ট |
| প্রজেক্ট ম্যানেজমেন্ট | প্রজেক্ট/টাস্ক/টাইমশিট রেকর্ড |
| হিউম্যান রিসোর্স | ডিপার্টমেন্ট/এমপ্লয়ি/পজিশন/অ্যাটেনডেন্স/লিভ/বেতন |
| উৎপাদন ম্যানুফ্যাকচারিং | BOM/প্রোডাকশন অর্ডার/প্রসেস রাউটিং/ওয়ার্কস্টেশন/MRP |
| কাস্টম রিপোর্ট | রিপোর্ট টেমপ্লেট/ডেটাসেট/ফিল্ড/ফিল্টার/এক্সিকিউশন/শিডিউলিং |
| OMS অর্ডার ম্যানেজমেন্ট | মাল্টি-চ্যানেল অর্ডার/ফুলফিলমেন্ট অর্কেস্ট্রেশন/ইনভেন্টরি রিজার্ভেশন(ATP)/RMA রিটার্ন-এক্সচেঞ্জ/চ্যানেল ম্যানেজমেন্ট |
| WMS গুদাম ম্যানেজমেন্ট | জোন লোকেশন(লেভেল+বারকোড)/ইনবাউন্ড(ASN→রিসিভ→পুটঅ্যাওয়ে)/আউটবাউন্ড(ওয়েভ→পিকিং→প্যাকিং) |
| TMS পরিবহন ম্যানেজমেন্ট | ক্যারিয়ার/ফ্রেট কম্পারিজন/শিপমেন্ট লেবেল/লজিস্টিক ট্র্যাকিং(webhook) |
| QMS কোয়ালিটি ম্যানেজমেন্ট | ইনকামিং IQC/প্রসেস IPQC/আউটগোয়িং OQC পরিদর্শন + পরিদর্শন স্ট্যান্ডার্ড + নন-কনফরমিটি হ্যান্ডলিং |
| EAM ইকুইপমেন্ট ম্যানেজমেন্ট | ইকুইপমেন্ট লেজার/মেইনটেন্যান্স প্ল্যান/রিপেয়ার অর্ডার/স্পেয়ার পার্ট ম্যানেজমেন্ট |
| DMS ডকুমেন্ট ম্যানেজমেন্ট | ডকুমেন্ট ক্যাটাগরি/ডকুমেন্ট/ভার্সন ম্যানেজমেন্ট |
| BI কানবান | কানবান লেআউট/চার্ট কম্পোনেন্ট |

## টেক স্ট্যাক

### ব্যাকএন্ড
- PHP 8.3+, webman v2 (workerman/webman)
- ডেটাবেস: MySQL 8.0+，টেবিল প্রিফিক্স `erp_`
- প্রাইমারি কী: BIGINT নন-অটোইনক্রিমেন্ট, `erikwang2013/snowflake-php` দিয়ে তৈরি
- API লেয়ার ID এনক্রিপশন/ডিক্রিপশন: `erikwang2013/hashids`
- JWT অথেনটিকেশন: `erikwang2013/jwt-webman`
- API সংবেদনশীল ডেটা এনক্রিপশন/ডিক্রিপশন: `erikwang2013/encryption`
- ডেটাবেস সংবেদনশীল ফিল্ড এনক্রিপশন/ডিক্রিপশন: `erikwang2013/encryptable`
- ES সিঙ্ক ও কোয়েরি: `erikwang2013/webman-scout`
- দেশের পতাকা: `erikwang2013/season`
- API ডকুমেন্টেশন জেনারেশন: `hg/apidoc` | অ্যানোটেশন-ভিত্তিক, /apidoc দেখুন

### ফ্রন্টএন্ড
- Flutter 3.x, সোর্স ডিরেক্টরি `apps/flutter/`
- Web এন্ড PC ম্যানেজমেন্ট ব্যাকএন্ড স্টাইলে ডিজাইন করা (মোবাইল অ্যাপ স্টাইল নয়)
- ক্লায়েন্ট এন্ড ও অ্যাডমিন এন্ড সাপোর্ট
- HarmonyOS ArkTS, সোর্স ডিরেক্টরি `apps/harmonyos/`

## প্রজেক্ট স্ট্রাকচার

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
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
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

## মিডলওয়্যার এক্সিকিউশন চেইন

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## নিরাপত্তা শক্তিশালীকরণ

- **HTTP মেথড রেস্ট্রিকশন**: SecurityFilter শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমতি দেয়, নন-স্ট্যান্ডার্ড মেথডে 405 রিটার্ন
- **CSP হেডার**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies সব রেসপন্সে ইনজেক্ট
- **অ্যাকাউন্ট লক**: টানা ৫ বার লগইন ব্যর্থ হলে, অ্যাকাউন্ট ১৫ মিনিট লক
- **কনকারেন্ট সেশন লিমিট**: একই ইউজারের সর্বোচ্চ ৩টি বৈধ Token, অতিরিক্ত হলে সবচেয়ে পুরনো Token ব্ল্যাকলিস্টে
- **security.txt**: `/.well-known/security.txt` RFC 9116 এন্ডপয়েন্ট
- **Nginx নিরাপত্তা কনফিগ**: `nginx-security.conf` রিভার্স প্রক্সি নিরাপত্তা শক্তিশালীকরণ রেফারেন্স

## API ভার্সন স্ট্র্যাটেজি

ভার্সন রিকোয়েস্ট হেডার `API-Version` দিয়ে নিয়ন্ত্রিত (ডিফল্ট `v1`), URL-এ প্রকাশিত হয় না:

```bash
curl -H "API-Version: v1" http://localhost:8788/api/auth/login
```

নতুন ভার্সন যোগ করতে শুধু `app/api/{version}/controller/` ডিরেক্টরি তৈরি করে `ApiVersion` মিডলওয়্যারে রেজিস্টার করুন।

## রেট লিমিট স্ট্র্যাটেজি

Redis স্লাইডিং উইন্ডো (Lua অ্যাটমিক), ডিফল্ট 60 বার/মিনিট/IP/রাউট:
- লগইন: 10 বার/মিনিট
- রেজিস্ট্রেশন: 5 বার/মিনিট
- রেসপন্স হেডার: `X-RateLimit-Limit/Remaining/Reset`, সীমা অতিক্রম করলে `Retry-After` যুক্ত

## কোড কনভেনশন

### PHP
- গ্লোবাল ফাংশন/ক্লাস রেফারেন্সে সামনে `\` যোগ করবেন না, `use` ইমপোর্ট ব্যবহার করুন
- কনফিগ ফাইলে প্রতিটি কনফিগ আইটেমের অর্থ ব্যাখ্যা করা চীনা কমেন্ট থাকতে হবে
- সব নতুন `.php` ফাইলের হেডারে কপিরাইট ঘোষণা থাকতে হবে

### ডেটাবেস
- টেবিল প্রিফিক্স: `erp_`
- প্রাইমারি কী `id`: BIGINT টাইপ, নন-অটোইনক্রিমেন্ট, snowflake দিয়ে তৈরি
- সংবেদনশীল ফিল্ড `erikwang2013/encryptable` trait দিয়ে স্বয়ংক্রিয় এনক্রিপশন/ডিক্রিপশন
- schema-এর একমাত্র সত্যের উৎস database/install.sql (একক ফাইল SQL)

### Flutter
- Web এন্ড লেআউট PC ম্যানেজমেন্ট ব্যাকএন্ড স্টাইল (সাইডবার + টপবার + কনটেন্ট এরিয়া)
- GetX স্টেট ম্যানেজমেন্ট, `ApiService` সিঙ্গেলটন (Dio + JWT ইন্টারসেপ্টর)
- Token পার্সিস্টেন্স `shared_preferences` দিয়ে
- রেসপন্সিভ ব্রেকপয়েন্ট: মোবাইল (< 768px) ও ডেস্কটপ (>= 768px)

### HarmonyOS
- `@ohos.net.http` নেটিভ HTTP ক্লায়েন্ট ব্যবহার
- Token সাইলেন্ট রিফ্রেশ: 401 হলে স্বয়ংক্রিয়ভাবে `/api/auth/refresh` কল
- রিফ্রেশ ব্যর্থ হলে স্বয়ংক্রিয় লগইন পেজে রিডাইরেক্ট

## ডিপ্লয়মেন্ট

### Docker Compose (প্রোডাকশন পরিবেশের জন্য প্রস্তাবিত)

প্রজেক্ট রুট `docker-compose.yml` ৫টি সার্ভিস অর্কেস্ট্রেট করে:

| সার্ভিস | ব্যাখ্যা |
|------|------|
| `nginx` | Nginx রিভার্স প্রক্সি (80/443)，স্ট্যাটিক ফাইল সার্ভিস |
| `app` | webman PHP 8.3 অ্যাপ, `Dockerfile` দিয়ে বিল্ড (OPcache + event + redis সহ) |
| `mysql` | MySQL 8.0，ডেটা ভলিউম পার্সিস্টেন্স |
| `redis` | Redis 7 Alpine，ক্যাশ/রেট লিমিট/Session |
| `elasticsearch` | Elasticsearch 8.x，ফুল-টেক্সট রিট্রিভাল |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions পাইপলাইন সংজ্ঞায়িত করে (PHP 8.2/8.3/8.4 ম্যাট্রিক্স):

- PHP সিনট্যাক্স চেক (`php -l`)
- PHPStan স্ট্যাটিক অ্যানালাইসিস (`vendor/bin/phpstan analyse`)
- PHP CS Fixer কোড স্টাইল চেক (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit ইউনিট টেস্ট
- Composer সিকিউরিটি অডিট (`composer audit --no-dev`)

### ডেটাবেস ব্যাকআপ

`database/backup/backup.sh` — mysqldump + gzip, ৩০ দিন আগের পুরনো ব্যাকআপ স্বয়ংক্রিয় ক্লিনআপ।
`database/backup/restore.sh` — ইন্টারঅ্যাকটিভ রিস্টোর, উপলব্ধ ব্যাকআপের তালিকা দেখিয়ে নির্বাচন করানো।

### মনিটরিং

`GET /metrics` এন্ডপয়েন্ট (`MetricsController`) Prometheus text format আউটপুট করে, ৫টি gauge মেট্রিক:
- `openadmin_http_requests_total` — রিকোয়েস্ট মোট সংখ্যা
- `openadmin_active_users` — সক্রিয় ইউজার সংখ্যা
- `openadmin_db_connection_status` — ডেটাবেস কানেকশন স্ট্যাটাস (0/1)
- `openadmin_redis_connection_status` — Redis কানেকশন স্ট্যাটাস (0/1)
- `openadmin_memory_usage_bytes` — মেমোরি ব্যবহারের পরিমাণ
