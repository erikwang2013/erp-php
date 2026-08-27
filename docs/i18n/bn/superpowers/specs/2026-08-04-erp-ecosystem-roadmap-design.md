# ERP ইকোসিস্টেম সম্পূর্ণ রোডম্যাপ — ডিজাইন স্পেক

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 2026-08-04 ইকোসিস্টেম অডিট রিপোর্টের ভিত্তিতে তৈরি, P0~P3 চারটি অগ্রাধিকার ফেজ কভার করে

---

## 1. বর্তমান বেসলাইন

| মাত্রা | বর্তমান অবস্থা | স্কোর |
|------|------|------|
| ব্যাকএন্ড API | 14 মডিউল / 80+ কন্ট্রোলার / 120+ মডেল, মাল্টি-মডিউল CRUD কঙ্কাল | 85/100 |
| নিরাপত্তা | 18 স্তরের গভীর প্রতিরক্ষা, CORS/SecurityFilter/RateLimit/JWT/এনক্রিপশন | 95/100 |
| ফ্রন্টএন্ড UI | Flutter 12 পেজ, HarmonyOS 9 পেজ, প্রায় 20% মডিউল কভার; Web ম্যানেজমেন্ট প্যানেল নেই | 20/100 |
| অপারেশন ইকোসিস্টেম | Docker, CI সম্পন্ন, মাইগ্রেশন রোলব্যাক, ব্যাকআপ অটোমেশন, অবজারভেবিলিটি নেই | 70/100 |
| ব্যবসায়িক গভীরতা | ফাইন্যান্স/HR/ম্যানুফ্যাকচারিং মডিউলের টেবিল স্ট্রাকচার সম্পূর্ণ কিন্তু ব্যবসায়িক লজিক মূলত CRUD | 55/100 |
| **সামগ্রিক** | | **65/100** |

---

## 2. সামগ্রিক কৌশল

```
串行瀑布: P0 → P1 → P2 → P3
每个阶段内有独立性的子任务可并行推进
```

### 2.1 ফ্রন্টএন্ড প্রযুক্তি নির্বাচন

- **Web ম্যানেজমেন্ট প্যানেল**: Flutter Web, `apps/flutter`-এর বিদ্যমান কোড পুনরায় ব্যবহার, PC ম্যানেজমেন্ট ব্যাকএন্ড স্টাইল, GetX স্টেট ম্যানেজমেন্ট
- **মোবাইল**: Flutter (iOS/Android), Web-এর সাথে `apps/flutter/lib/app/` ব্যবসায়িক কোড শেয়ার করে
- **HarmonyOS**: ArkTS, Flutter ফিচার সেটের সাথে সামঞ্জস্যপূর্ণ

### 2.2 ব্যাকএন্ড কৌশল

- **ইন্ডাস্ট্রিয়াল-গ্রেড** (A লেভেল): ডাবল-এন্ট্রি বুককিপিং, বেতন গণনা, MRP ইঞ্জিন — অ্যালগরিদম সম্পূর্ণ, এজ কেস হ্যান্ডলিং যথেষ্ট, প্রোডাকশন রেডি
- **কোর-ইউজেবল** (B লেভেল): কোয়ালিটি ম্যানেজমেন্ট, নোটিফিকেশন সিস্টেম, BI ড্যাশবোর্ড — মূল নিয়ম বাস্তবায়ন, পরে প্রয়োজন অনুযায়ী ইটারেশন

---

## 3. P0 — ফ্রন্টএন্ড ইকোসিস্টেম (3-4 সপ্তাহ)

> **লক্ষ্য**: সিস্টেমে একটি ব্যবহারযোগ্য ম্যানেজমেন্ট ইন্টারফেস আনা, সব বাস্তবায়িত ব্যাকএন্ড মডিউল কভার করা

### 3.1 Flutter প্রজেক্ট আর্কিটেকচার রিফ্যাক্টর

```
apps/flutter/lib/app/
├── main.dart                      # 入口，初始化 GetX + Dio
├── routes/
│   └── app_pages.dart             # 全量路由注册（按模块分组）
├── layouts/
│   └── admin_layout.dart          # PC 三栏布局（侧边栏 + 顶栏 + 内容）
├── theme/
│   └── app_theme.dart             # Material 3 主题（品牌色 #1677FF）
├── services/
│   ├── api_service.dart           # Dio 单例 + JWT 拦截器 + 自动刷新
│   ├── auth_service.dart          # 认证状态管理
│   ├── captcha_service.dart       # 点击验证码
│   └── export_service.dart        # Excel/PDF 导出下载
├── widgets/
│   ├── data_table_wrapper.dart    # 通用数据表格（分页/搜索/批量操作）
│   ├── form_dialog.dart           # 通用表单弹窗
│   ├── confirm_dialog.dart        # 二次确认弹窗（密码输入）
│   └── stat_card.dart             # 统计卡片
└── pages/
    ├── login/                     # 登录页
    ├── dashboard/                 # 仪表盘（6 个看板切换）
    ├── system/
    │   ├── user/                  # 用户管理（含批量/导入）
    │   ├── role/                  # 角色 + 权限树
    │   ├── config/                # 系统配置
    │   └── log/                   # 操作日志
    ├── product/                   # 商品/分类/品牌/SKU
    ├── partner/                   # 供应商/客户/仓库/库位
    ├── purchase/                  # 采购申请/订单/收货/退货/结算
    ├── sales/                     # 销售报价/订单/发货/退货/结算
    ├── inventory/                 # 库存/流水/调拨/盘点/预警
    ├── finance/
    │   ├── voucher/               # 记账凭证
    │   ├── ar_ap/                 # 应收应付
    │   ├── receipt_payment/       # 收付款
    │   ├── ledger/                # 总账/明细账
    │   ├── report/                # 三表（利润/资产负债/现金流）
    │   ├── asset/                 # 固定资产
    │   ├── tax/                   # 税务
    │   ├── currency/              # 多币种/汇率
    │   ├── budget/                # 预算
    │   └── cost_profit/           # 成本/利润中心
    ├── crm/
    │   ├── opportunity/           # 商机漏斗
    │   ├── contact/               # 联系人
    │   ├── pool/                  # 公海池
    │   ├── contract/              # 合同
    │   ├── quotation/             # 报价
    │   ├── campaign/              # 营销活动
    │   ├── ticket/                # 服务工单
    │   └── analytics/             # 客户分析
    ├── oms/                       # OMS 订单/履约/退货/渠道
    ├── wms/                       # WMS 库区库位/收货/上架/波次/拣货/打包
    ├── tms/                       # TMS 承运商/费率/运单/轨迹/结算
    ├── manufacturing/             # BOM/生产订单/工艺/工作站/MRP
    ├── hr/                        # 部门/员工/职位/考勤/请假/薪资
    ├── project/                   # 项目/任务/工时
    ├── workflow/                  # 审批工作流/我的审批
    ├── notification/              # 通知中心
    ├── report/                    # 自定义报表
    └── profile/                   # 个人中心
```

### 3.2 সাধারণ কম্পোনেন্ট ডেভেলপমেন্ট

| কম্পোনেন্ট | ফাংশন | ব্যবহারের দৃশ্য |
|------|------|----------|
| `DataTableWrapper` | পেজিনেশন/সর্টিং/কীওয়ার্ড সার্চ/স্ট্যাটাস ফিল্টার/ব্যাচ সিলেক্ট/কলাম কনফিগ | সব লিস্ট পেজ |
| `FormDialog` | ডাইনামিক ফর্ম রেন্ডার/ফিল্ড ভ্যালিডেশন/সাবমিট/বন্ধ | সব তৈরি/এডিট পপআপ |
| `ConfirmDialog` | পাসওয়ার্ড দ্বিতীয়বার নিশ্চিত ইনপুট | সব ডিলিট অপারেশন |
| `StatCard` | মান/ট্রেন্ড অ্যারো/শিরোনাম | ড্যাশবোর্ড |
| `BreadcrumbNav` | ব্রেডক্রাম্ব নেভিগেশন | গভীর পেজ |
| `FileUploader` | ড্র্যাগ-ড্রপ আপলোড/প্রগ্রেস/প্রিভিউ | ইমপোর্ট/ছবি আপলোড |

### 3.3 HarmonyOS পূরণ

Flutter পেজ সেটের সাথে সামঞ্জস্য রেখে পূরণ: OMS/WMS/TMS/ম্যানুফ্যাকচারিং/HR/অনুমোদন/নোটিফিকেশন/রিপোর্ট মডিউল পেজ।

### 3.4 P0 গ্রহণযোগ্যতার মানদণ্ড

- [ ] Flutter Web ম্যানেজমেন্ট প্যানেল সব 14টি মডিউল কভার করে
- [ ] সব CRUD লিস্ট পেজ কাজ করে (পেজিনেশন/সার্চ/ফিল্টার)
- [ ] সব তৈরি/এডিট ফর্ম কাজ করে (ভ্যালিডেশন/সাবমিট)
- [ ] ডিলিট অপারেশনে দ্বিতীয় পাসওয়ার্ড নিশ্চিতকরণ
- [ ] JWT স্বয়ংক্রিয় রিফ্রেশ নিরবিচ্ছিন্ন
- [ ] PC/ট্যাবলেট/মোবাইল রেসপন্সিভ লেআউট অ্যাডাপ্টেশন
- [ ] HarmonyOS পেজ সংখ্যা ≥ Flutter পেজ সংখ্যার 80%

---

## 4. P1 — ব্যবসায়িক গভীরতা (4-6 সপ্তাহ)

> **লক্ষ্য**: মূল মডিউলগুলো CRUD কঙ্কাল থেকে প্রকৃত ব্যবসায়িক গণনা ইঞ্জিনে আপগ্রেড করা

### 4.1 ফাইন্যান্স ডাবল-এন্ট্রি ইঞ্জিন (ইন্ডাস্ট্রিয়াল-গ্রেড)

```
app/service/finance/
├── DoubleEntryService.php        # 借贷平衡校验 + 自动分录生成
├── PeriodCloseService.php        # 期末结转（损益结转/成本结转）
├── AccountBalanceService.php     # 科目余额汇总（按月/按季/按年）
├── ConsolidationService.php      # 多币种合并报表（汇率折算）
└── FinancialRatioService.php     # 财务比率自动计算

app/controller/finance/
├── PeriodCloseController.php     # 期末结转操作
├── AccountBalanceController.php  # 科目余额查询
└── FinancialRatioController.php  # 比率分析查询
```

**মূল নিয়ম**:
- ভাউচার সেভে বাধ্যতামূলক "ডেবিট থাকলে ক্রেডিট থাকতেই হবে, ডেবিট ও ক্রেডিট সমান হতে হবে"
- অনুমোদিত ভাউচার পরিবর্তন করা যায় না, রেড-লেটার রিভার্সাল প্রয়োজন
- পিরিয়ড-এন্ড ক্যারি-ফরোয়ার্ড: লাভ-ক্ষতি অ্যাকাউন্ট ব্যালেন্স → চলতি বছরের মুনাফা, মাল্টি-স্টেপ ক্যারি-ফরোয়ার্ড সাপোর্ট
- মাল্টি-কারেন্সি: পিরিয়ড-এন্ড এক্সচেঞ্জ রেটে রূপান্তর, এক্সচেঞ্জ লাভ-ক্ষতি স্বয়ংক্রিয় হিসাব

### 4.2 বেতন গণনা ইঞ্জিন (ইন্ডাস্ট্রিয়াল-গ্রেড)

```
app/service/hr/
├── SalaryEngineService.php       # 薪资计算主引擎
├── SocialInsuranceService.php    # 社保计算（养老/医疗/失业/工伤/生育）
├── HousingFundService.php        # 公积金计算
├── TaxCalculatorService.php      # 个税累进税率计算
└── BankPayrollService.php        # 银行代发文件导出

app/controller/hr/
└── PayrollController.php         # 薪资计算/发放/查询
```

**মূল নিয়ম**:
- সোশ্যাল ইন্স্যুরেন্স বেস ঊর্ধ্ব-নিম্ন সীমা (প্রতি শহরে বার্ষিক সমন্বয়, কনফিগযোগ্য)
- হাউজিং ফান্ড বেস + জমার হার (5%-12%, কনফিগযোগ্য)
- ব্যক্তিগত কর প্রগ্রেসিভ রেট টেবিল (3%-45%, বার্ষিক সেটেলমেন্ট)
- ব্যাংক পেঅরোল ফরম্যাট: ICBC/BOC/CCB/CMB সহ মূল ব্যাংক সাপোর্ট
- বেতন স্লিপ তৈরি (সব বিবরণসহ)

### 4.3 MRP ইঞ্জিন (ইন্ডাস্ট্রিয়াল-গ্রেড)

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP 运算主引擎
├── DemandForecastService.php      # 需求汇总（订单+预测+安全库存）
├── NetRequirementService.php      # 净需求计算（毛需求-在库-在途）
├── BomExplosionService.php        # BOM 展开（逐层展开到原材料）
└── OrderSuggestionService.php     # 建议订单生成（采购/生产/外协）

app/model/
├── MfgMrpRunLog.php              # MRP 运算日志
└── MfgOrderSuggestion.php        # 建议订单
```

**মূল নিয়ম**:
- BOM স্তরে স্তরে সম্প্রসারণ, ক্ষয়ের হার বিবেচনা
- নিট চাহিদা = মোট চাহিদা - বর্তমান স্টক - ট্রানজিট স্টক + বরাদ্দকৃত পরিমাণ + নিরাপত্তা স্টক
- লো-লেভেল কোড (LLC) নিশ্চিত করে একই ম্যাটেরিয়াল শুধু একবার গণনা হয়
- লিড টাইম দিয়ে প্রস্তাবিত অর্ডার তারিখ পেছন থেকে গণনা
- লট সাইজ নিয়ম: ফিক্সড লট/ইকোনমিক লট/অন-ডিমান্ড

### 4.4 কোয়ালিটি ম্যানেজমেন্ট (কোর-ইউজেবল)

```
app/controller/quality/
├── InspectionStandardController.php  # 检验标准
├── IncomingCheckController.php       # IQC 来料检验
├── ProcessCheckController.php        # IPQC 过程检验
├── FinalCheckController.php          # OQC 出货检验
└── NonconformityController.php       # 不合格品处理

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 রিয়েল-টাইম নোটিফিকেশন সিস্টেম (কোর-ইউজেবল)

```
app/service/notification/
├── WebSocketService.php           # WebSocket 连接管理 + 推送
├── ChannelRouter.php              # 多渠道路由（站内/邮件/企微/钉钉）
├── TemplateRenderer.php           # 通知模板渲染

app/process/
└── WebSocket.php                  # WebSocket 进程

app/controller/notification/
├── WebSocketController.php        # WebSocket 事件处理
└── ChannelConfigController.php    # 通知渠道配置
```

**মূল নিয়ম**:
- WebSocket workerman নেটিভ প্রোটোকল ভিত্তিক
- নোটিফিকেশন টেমপ্লেট: ভেরিয়েবল প্লেসহোল্ডার `{order_code}` রানটাইমে প্রতিস্থাপিত হয়
- চ্যানেল অগ্রাধিকার: ইন-অ্যাপ → ইমেইল → WeCom → DingTalk, কনফিগযোগ্য

### 4.6 P1 গ্রহণযোগ্যতার মানদণ্ড

- [ ] ভাউচার সেভে ডেবিট-ক্রেডিট অসম হলে → এরর ফেরত
- [ ] বেতন ইঞ্জিনের আউটপুট ম্যানুয়াল হিসাবের সাথে মিলে যায় (10 জনের মাসিক বেতন ডেটা নমুনা)
- [ ] MRP নিট চাহিদা গণনা Excel ম্যানুয়াল হিসাবের সাথে মিলে যায়
- [ ] কোয়ালিটি পরীক্ষার তিন ফর্ম (IQC/IPQC/OQC) সম্পূর্ণ ফ্লো
- [ ] WebSocket নোটিফিকেশন লেটেন্সি < 2 সেকেন্ড
- [ ] সব নতুন সার্ভিসে PHPUnit টেস্ট কভারেজ (মূল অ্যালগরিদম ≥ 95%)

---

## 5. P2 — অপারেশন নির্ভরযোগ্যতা (1-2 সপ্তাহ)

> **লক্ষ্য**: প্রোডাকশন-গ্রেড অপারেশন সক্ষমতা

### 5.1 ডাটাবেস মাইগ্রেশন রোলব্যাক

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

প্রতিটি মাইগ্রেশন ফাইলের জন্য সংশ্লিষ্ট `_rollback.sql` ফাইল যোগ করুন।

### 5.2 ব্যাকআপ/রিস্টোর এনহ্যান্সমেন্ট

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 অবজারভেবিলিটি

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- রিকোয়েস্ট-লেভেল trace ID (রেসপন্স হেডার `X-Trace-Id` দিয়ে প্রকাশ)
- মূল ব্যবসায়িক মেট্রিক: অর্ডার সংখ্যা, ফুলফিলমেন্ট রেট, স্টক টার্নওভার দিন

### 5.4 মেসেজ কিউ আপগ্রেড

বর্তমান Redis কিউ → RabbitMQ অপশনাল ড্রাইভার হিসেবে সাপোর্ট:

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 P2 গ্রহণযোগ্যতার মানদণ্ড

- [ ] মাইগ্রেশন রোলব্যাক স্ক্রিপ্ট চলবে এবং ডেটা অখণ্ডতা যাচাই পাস
- [ ] অটো ব্যাকআপ cron সঠিকভাবে ট্রিগার হয়
- [ ] Trace ID রিকোয়েস্টের পুরো চেইন জুড়ে থাকে
- [ ] RabbitMQ ড্রাইভার সুইচ করা যায় এবং মেসেজ হারায় না

---

## 6. P3 — অভিজ্ঞতা উন্নয়ন (2-3 সপ্তাহ)

> **লক্ষ্য**: উন্নত ফিচার ও ভালো ইউজার অভিজ্ঞতা

### 6.1 BI ডেটা ড্যাশবোর্ড

```
app/controller/bi/
├── DashboardController.php       # 可配置仪表盘
├── WidgetController.php          # 图表小组件 CRUD
└── DatasetController.php         # 数据集管理

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- ড্র্যাগেবল লেআউট ড্যাশবোর্ড
- উইজেট: বার চার্ট/লাইন চার্ট/পাই চার্ট/ডেটা কার্ড/টেবিল
- `app/controller/report/`-এর ডেটাসেট মেকানিজম পুনরায় ব্যবহার

### 6.2 ইকুইপমেন্ট ম্যানেজমেন্ট (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 মাল্টি-টেন্যান্ট

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- শেয়ার্ড ডাটাবেস + `tenant_id` আইসোলেশন
- সুপার অ্যাডমিন ক্রস-টেন্যান্ট ভিউ

### 6.4 ডকুমেন্ট ম্যানেজমেন্ট (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 P3 গ্রহণযোগ্যতার মানদণ্ড

- [ ] BI ড্যাশবোর্ডে ড্র্যাগযোগ্য কাস্টম লেআউট
- [ ] ইকুইপমেন্ট রেজিস্টার → রক্ষণাবেক্ষণ পরিকল্পনা → মেরামত অর্ডার ক্লোজড-লুপ
- [ ] টেন্যান্ট A টেন্যান্ট B-এর ডেটা অ্যাক্সেস করতে পারে না
- [ ] ডকুমেন্ট ভার্সন ইতিহাস ট্রেসযোগ্য

---

## 7. ডেটা মডেল পরিবর্তন সারসংক্ষেপ

### P0 নতুন টেবিল

নতুন টেবিল নেই, ফ্রন্টএন্ড ইকোসিস্টেম ব্যাকএন্ড টেবিল স্ট্রাকচার পরিবর্তন জড়িত নয়।

### P1 নতুন টেবিল

| টেবিল নাম | উদ্দেশ্য | ফেজ |
|------|------|------|
| `erik_finance_period_close` | পিরিয়ড-এন্ড ক্যারি-ফরোয়ার্ড রেকর্ড | P1 |
| `erik_finance_account_balance` | অ্যাকাউন্ট ব্যালেন্স স্ন্যাপশট | P1 |
| `erik_hr_salary_config` | বেতন গণনা কনফিগ | P1 |
| `erik_hr_social_insurance_config` | সোশ্যাল ইন্স্যুরেন্স বেস কনফিগ | P1 |
| `erik_hr_housing_fund_config` | হাউজিং ফান্ড কনফিগ | P1 |
| `erik_mfg_mrp_run_log` | MRP গণনা লগ | P1 |
| `erik_mfg_order_suggestion` | প্রস্তাবিত অর্ডার | P1 |
| `erik_quality_inspection_standard` | পরীক্ষার মান | P1 |
| `erik_quality_iqc_record` | IQC ইনকামিং পরীক্ষা | P1 |
| `erik_quality_ipqc_record` | IPQC প্রসেস পরীক্ষা | P1 |
| `erik_quality_oqc_record` | OQC শিপমেন্ট পরীক্ষা | P1 |
| `erik_quality_nonconformity` | অ-সম্মত পণ্য | P1 |
| `erik_notification_channel_config` | নোটিফিকেশন চ্যানেল কনফিগ | P1 |
| `erik_notification_template` | নোটিফিকেশন টেমপ্লেট | P1 |

### P3 নতুন টেবিল

| টেবিল নাম | উদ্দেশ্য | ফেজ |
|------|------|------|
| `erik_bi_dashboard` | BI ড্যাশবোর্ড | P3 |
| `erik_bi_widget` | BI উইজেট | P3 |
| `erik_eam_equipment` | ইকুইপমেন্ট রেজিস্টার | P3 |
| `erik_eam_maintenance_plan` | রক্ষণাবেক্ষণ পরিকল্পনা | P3 |
| `erik_eam_repair_order` | মেরামত অর্ডার | P3 |
| `erik_dms_document` | নিয়ন্ত্রিত ডকুমেন্ট | P3 |
| `erik_dms_document_version` | ডকুমেন্ট ভার্সন | P3 |

---

## 8. সার্ভিস লেয়ার পরিবর্তন সারসংক্ষেপ

| সার্ভিস | বর্তমান | P1 পরিবর্তন | P2 পরিবর্তন | P3 পরিবর্তন |
|------|------|---------|---------|---------|
| FinanceService | CRUD | নতুন DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| বেতন | নেই | নতুন SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| ম্যানুফ্যাকচারিং | CRUD | নতুন MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| কোয়ালিটি | নেই | নতুন QmsInspectionService | — | — |
| নোটিফিকেশন | বেসিক | নতুন WebSocketService, ChannelRouter | — | — |
| অবজারভেবিলিটি | Monitor প্রসেস | — | নতুন TracerService, MetricCollector | — |
| BI | নেই | — | — | নতুন BiDashboardService |
| ইকুইপমেন্ট | নেই | — | — | নতুন EamService |

---

## 9. মিডলওয়্যার চেইন পরিবর্তন

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. মাইলস্টোন ও ডেলিভারেবল

| মাইলস্টোন | সময় | ডেলিভারেবল |
|--------|------|--------|
| M0 — বর্তমান বেসলাইন | 2026-08-04 | অডিট রিপোর্ট `audit-report-2026-08-04.md` |
| M1 — P0 সম্পন্ন | +3 সপ্তাহ | Flutter Web পূর্ণ-মডিউল ম্যানেজমেন্ট প্যানেল |
| M2 — P1 সম্পন্ন | +8 সপ্তাহ | ফাইন্যান্স ইঞ্জিন + বেতন ইঞ্জিন + MRP ইঞ্জিন + কোয়ালিটি + নোটিফিকেশন |
| M3 — P2 সম্পন্ন | +10 সপ্তাহ | মাইগ্রেশন রোলব্যাক + অটো ব্যাকআপ + Trace + কিউ আপগ্রেড |
| M4 — P3 সম্পন্ন | +13 সপ্তাহ | BI ড্যাশবোর্ড + ইকুইপমেন্ট ম্যানেজমেন্ট + মাল্টি-টেন্যান্ট + ডকুমেন্ট ম্যানেজমেন্ট |

---

## 11. ঝুঁকি ও প্রশমন

| ঝুঁকি | প্রভাব | প্রশমন ব্যবস্থা |
|------|------|----------|
| Flutter Web পারফরম্যান্স নেটিভ JS-এর চেয়ে কম | বড় ডেটা টেবিলে ল্যাগ | ক্লায়েন্ট পেজিনেশন + ভার্চুয়াল স্ক্রলিং + Web Worker |
| বেতন ইঞ্জিনের নিয়ম পরিবর্তন | গণনার ফলাফল অ-সম্মত | সোশ্যাল ইন্স্যুরেন্স/ট্যাক্স রেট কনফিগযোগ্য, হার্ডকোড নয় |
| MRP গণনায় বড় ডেটা টাইমআউট | গণনা বাধাগ্রস্ত | ব্যাচ প্রসেসিং + প্রগ্রেস কলব্যাক |
| WebSocket দীর্ঘ-সংযোগ সংখ্যা বেশি | সার্ভার মেমরি চাপ | workerman প্রাকৃতিক উচ্চ কনকারেন্সি + সংযোগ সীমা |
| মাল্টি-টেন্যান্ট ডেটা আইসোলেশন ঘাটতি | ডেটা লিক | TenantScope গ্লোবাল মিডলওয়্যার + টেস্ট কভারেজ |

---

## 12. যা করা হবে না (স্পষ্টভাবে বাদ)

- ❌ মাইক্রোসার্ভিস স্প্লিটিং নেই — বর্তমান মনোলিথ আর্কিটেকচার যথেষ্ট, জটিল লজিক Service লেয়ারে সমন্বিত
- ❌ Kubernetes নেই — Docker Compose বর্তমান স্কেলের জন্য যথেষ্ট
- ❌ AI/ML ফিচার নেই — MVP রোডম্যাপে নেই
- ❌ নেটিভ iOS/Android আলাদা App নেই — Flutter ক্রস-প্ল্যাটফর্ম ইতিমধ্যে কভার করেছে
- ❌ GraphQL নেই — RESTful API যথেষ্ট, API ভার্সন কৌশল পরিণত
- ❌ ইলেকট্রনিক সিগনেচার/WMS হার্ডওয়্যার ইন্টিগ্রেশন নেই (PDA/স্ক্যানার) — শুধু সফটওয়্যার স্তর
