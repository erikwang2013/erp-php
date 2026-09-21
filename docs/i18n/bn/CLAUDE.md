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

**বর্তমান কম্পোজিট স্কোর 89/100** — পূর্ণ রোডম্যাপ P0~P3 সম্পন্ন, 23 মডিউল ফুল-স্ট্যাক কভারেজ, প্রোডাকশন-রেডি।

| ফেজ | সময়কাল | ডেলিভারেবল | স্ট্যাটাস |
|------|------|--------|------|
| 🔵 **P0** ফ্রন্টএন্ড ইকোসিস্টেম | 3-4 সপ্তাহ | 102 Flutter মেনু রুট + 41 HarmonyOS পেজ + 4 সাধারণ কম্পোনেন্ট | ✅ |
| 🟢 **P1** বিজনেস ডেপথ | 4-6 সপ্তাহ | ফাইন্যান্স ইঞ্জিন + বেতন ইঞ্জিন + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** অপারেশনাল রিলায়েবিলিটি | 1-2 সপ্তাহ | মাইগ্রেশন রোলব্যাক + স্বয়ংক্রিয় ব্যাকআপ + TraceId + কিউ ডুয়াল-ড্রাইভার | ✅ |
| 🟣 **P3** এক্সপেরিয়েন্স এনহ্যান্সমেন্ট | 2-3 সপ্তাহ | BI কানবান + EAM + DMS | ✅ |
(মাল্টি-টেন্যান্সি B5 আগেই P2-এ ডেলিভার হয়েছে: TenantScope রিকোয়েস্ট কনটেক্সট + erp_tenant, আইসোলেশন মিডলওয়্যার seam রেজিস্টার্ড নয়)

**টেস্ট**: 1025<!-- stats:tests=1025 --> tests, 4827<!-- stats:assertions=4827 --> assertions (23 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

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
| নিরাপত্তা | 7 স্তরের ডিফেন্স-ইন-ডেপথ (XSS/SQL ইনজেকশন/CSRF/রেট লিমিট/CSP...) |
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
- API ডকুমেন্টেশন জেনারেশন: `erikwang2013/apidoc-php` | অ্যানোটেশন-ভিত্তিক, /apidoc দেখুন

### ফ্রন্টএন্ড
- Flutter 3.x, সোর্স ডিরেক্টরি `apps/flutter/`
- Web এন্ড PC ম্যানেজমেন্ট ব্যাকএন্ড স্টাইলে ডিজাইন করা (মোবাইল অ্যাপ স্টাইল নয়)
- ক্লায়েন্ট এন্ড ও অ্যাডমিন এন্ড সাপোর্ট
- HarmonyOS ArkTS, সোর্স ডিরেক্টরি `apps/harmonyos/`
- Angular 22 CLI + ng-zorro-antd, সোর্স ডিরেক্টরি `apps/angular/` (Web ম্যানেজমেন্ট ব্যাকএন্ড)
- React 19 + Vite, সোর্স ডিরেক্টরি `apps/react/` (Web ম্যানেজমেন্ট ব্যাকএন্ড)
- চার প্রান্ত একই ব্যাকএন্ড: Angular / React, Flutter-এর মতোই `/admin/v1`、`/api/v1`、`/open/v1` ব্যবহার করে; ডেভেলপমেন্টের সময় নিজ নিজ dev server থেকে webman-এ প্রক্সি হয়

### আন্তর্জাতিকীকরণ (১৩টি ভাষা)
- ভাষার তালিকা: `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`
- ব্যাকএন্ড অভিধান: `resource/translations/<locale>/{common,modules,validation}.php`, ১৩টি ভাষা ডিরেক্টরি; `zh_CN` ৫৬৫টি এন্ট্রি, বাকি ১১টি ভাষায় প্রতিটি ৫৪৪টি, `en` ৩০টি (গণনা: তিন ফাইলের লিফ এন্ট্রি, `validation.php`-এর `attributes` ফিল্ড লেবেল গণনায় ধরা হয়, তার গ্রুপ কী ধরা হয় না)
  - "ইংরেজিই key": `en`-এর common/modules ফাঁকা রাখা হয়; `validation.php`-এর কী হলো ফ্রেমওয়ার্কের রুলের নাম, কেবল ভ্যালু অনূদিত হয়
  - জেনারেটর: `scripts/gen-be-locales.mjs`
- ফ্রন্টএন্ড অভিধান (Angular): সোর্স `apps/angular/src/app/core/zh-en/part1..4.ts` (১৪৫৬টি এন্ট্রি) → প্রোডাক্ট `apps/angular/src/app/core/zh-<code>.ts`
- ফ্রন্টএন্ড অভিধান (React): সোর্স `apps/react/src/lib/i18n/zhEn.ts` (১৪৫১টি এন্ট্রি) → প্রোডাক্ট `apps/react/src/lib/i18n/zh<Code>.ts`
  - ১১টি নতুন ভাষার অভিধান প্রতিটি ডাইনামিক `import()`-এ আলাদা chunk হয়, এন্ট্রি না থাকলে চীনা মূল টেক্সটে ফিরে যায়
  - জেনারেটর: `scripts/gen-fe-locales.mjs --app angular|react`
- রানটাইম: ভাষা বদলালেই রিকোয়েস্ট হেডার `Accept-Language` বদলায়, ব্যাকএন্ড ভাষা অনুযায়ী টেক্সট ফেরত দেয় (`app/common/I18n.php` + `config/translation.php`)

## প্রজেক্ট স্ট্রাকচার

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (16 个)
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
│   ├── api/v1/controller/      # 客户端 API（版本置于路径 /api/v1，无版本请求头）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（139 个，含顶层 InstallController / IndexController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户/规格 (8个)
│   │   ├── purchase/            # 申请/询价/报价/订单/收货/退货/结算/供应商评估 (8个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警/追溯 (6个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心/银行账户对账/费用/发票结算/合并报表/账期 (28个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/设计器/审批提交/批准/拒绝/撤回 (3个)
│   │   ├── notification/        # 通知列表/已读/未读计数/通知渠道 (2个)
│   │   ├── project/             # 项目/任务/工时记录/项目成本 (4个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/薪资/绩效/招聘/社保/培训 (9个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP/产能/领料/报工/计件工资/成本录入/委外及收发 (13个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件/点检 (5个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   ├── open/                # 开放 API (1个)
│   │   ├── platform/            # 自定义字段/租户 (2个)
│   │   ├── print/               # 打印模板 (1个)
│   │   ├── retail/              # 优惠券/会员 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（64 个文件 / 63 个服务类）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/…  # 订单/仓储/运输/质检/人事/制造等（共 20 个模块子目录）
│   ├── common/                  # 公共工具类（6 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   ├── I18n.php             # 国际化翻译
│   │   ├── CorsPolicy.php       # CORS 策略（middleware/Cors 与 route.php 调用）
│   │   └── AddressValidator.php # 地址校验（多国邮编格式 + 表单字段）
│   ├── middleware/              # 中间件（11 个）
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── OpenApiAuth.php      # 开放接口认证（X-API-Key + 签名，仅 /open/v1 分组挂载）
│   │   ├── TenantScope.php      # 多租户隔离（预留未注册，见 ARCHITECTURE.md §22）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（224 个；连 concerns/TenantScope trait 共 225 个文件）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, WebSocket, QueueConsumer, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   ├── angular/                 # Angular 22 CLI + ng-zorro-antd Web 管理后台
│   │   └── src/app/
│   │       ├── core/            # ApiService / AuthStore / I18n 服务 + 语种词典（zh-en/ 源，zh-<code>.ts 产物）
│   │       └── config/ layout/ pages/ ui/
│   ├── react/                   # React 19 + Vite Web 管理后台
│   │   └── src/
│   │       ├── lib/i18n/        # 源词典 zhEn.ts + 11 个语种 zh<Code>.ts（按语种懒加载）
│   │       └── components/ layout/ pages/ state/ config/domains/ styles/
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/                  # 插件配置（erikwang2013/*；apidoc 见 erikwang2013/apidoc/）
├── database/
│   ├── install.sql              # 完整安装SQL（227 张表 + 种子数据，全部迁移已并入）
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
全局:  Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin/v1:   Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api/v1:     Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/open/v1:    Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → OpenApiAuth → Controller
```

## নিরাপত্তা শক্তিশালীকরণ

- **HTTP মেথড রেস্ট্রিকশন**: SecurityFilter শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমতি দেয়, নন-স্ট্যান্ডার্ড মেথডে 405 রিটার্ন
- **CSP হেডার**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies সব রেসপন্সে ইনজেক্ট
- **অ্যাকাউন্ট লক**: টানা ৫ বার লগইন ব্যর্থ হলে, অ্যাকাউন্ট ১৫ মিনিট লক
- **কনকারেন্ট সেশন লিমিট**: একই ইউজারের সর্বোচ্চ ৩টি বৈধ Token, অতিরিক্ত হলে সবচেয়ে পুরনো Token ব্ল্যাকলিস্টে
- **security.txt**: `/.well-known/security.txt` RFC 9116 এন্ডপয়েন্ট
- **Nginx নিরাপত্তা কনফিগ**: `nginx-security.conf` রিভার্স প্রক্সি নিরাপত্তা শক্তিশালীকরণ রেফারেন্স

## API ভার্সন স্ট্র্যাটেজি

ভার্সন URL পাথে রাখা হয় (`/admin/v1`, `/api/v1`, `/open/v1`), কোনো ভার্সন রিকোয়েস্ট হেডার নেই:

```bash
curl http://localhost:8788/api/v1/auth/login
```

নতুন ভার্সন যোগ করতে শুধু `app/api/{version}/controller/` ডিরেক্টরি তৈরি করে `config/route.php`-এ `/api/v{version}` গ্রুপ রেজিস্টার করুন (ভার্সন নম্বর শুধু URL পাথে প্রকাশ পায়, কন্ট্রোলার সরাসরি বাইন্ড, কোনো ভার্সন-হেডার মিডলওয়্যার নেই — পূর্বের `ApiVersion` হেডার মিডলওয়্যার সরানো হয়েছে)।

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
- Token সাইলেন্ট রিফ্রেশ: 401 হলে স্বয়ংক্রিয়ভাবে `/api/v1/auth/refresh` কল
- রিফ্রেশ ব্যর্থ হলে স্বয়ংক্রিয় লগইন পেজে রিডাইরেক্ট

## পরিচিত টেকনিক্যাল ঋণ

> নিচের তালিকা `grep -rn "new .*Service(" app/controller/` দিয়ে মাপা (৪৫টি জায়গা), কোডের বাস্তব অবস্থার সাথে সঙ্গতিপূর্ণ।
> **P5-এ রিফ্যাক্টর করা হবে না**: কন্ট্রোলারে সরাসরি সার্ভিস তৈরি করা বিদ্যমান প্যাটার্ন; কেবল নতুন কোড লেখার সময় কন্টেইনার ইনজেকশন (`support\Container`) ব্যবহার করা হবে, পুরনো কোড আগের মতোই থাকবে।

| মডিউল | সরাসরি তৈরি সার্ভিসের সংখ্যা | বিবরণ |
|------|-----------|------|
| finance | ২২ | AR/AP/সমন্বয়/জার্নাল/ক্লোজিং/কনসোলিডেশন রিপোর্ট |
| wms | ৯ | রিসিভ/পুটঅ্যাওয়ে/ওয়েভ/পিকিং/প্যাকিং ইত্যাদি প্রসেস সার্ভিস |
| tms | ৫ | শিপমেন্ট/কম্পারিজন/ট্র্যাকিং/ফ্রেট ইনভয়েস |
| oms | ৩ | ফুলফিলমেন্ট/রিজার্ভেশন/RMA |
| quality | ২ | ইন্সপেকশন/নন-কনফরমিটি হ্যান্ডলিং |
| hr | ২ | বেতন/অ্যাটেনডেন্স |
| platform | ১ | টেন্যান্ট |
| notification | ১ | নোটিফিকেশন চ্যানেল |

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
