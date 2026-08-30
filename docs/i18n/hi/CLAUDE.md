# ओपन एडमिन बैकएंड (open-admin)

webman v2 + Flutter पर आधारित फुल-स्टैक प्रशासन बैकएंड प्रणाली।

![ऑक्टोपस शुभंकर](images/mascot.svg)

## कॉपीराइट घोषणा

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **संशोधनीय नहीं, हटाने योग्य नहीं, अपरिवर्तनीय।** सभी नई फ़ाइलों में फ़ाइल हेडर टिप्पणी के रूप में उपरोक्त कॉपीराइट घोषणा होनी चाहिए।

## पारिस्थितिकी तंत्र रोडमैप

> डिज़ाइन विनिर्देश: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> आर्किटेक्चर दस्तावेज़: `docs/ARCHITECTURE.md` §21
> फ़ीचर मैट्रिक्स: `docs/FUNCTIONS.md` §19

**वर्तमान समग्र स्कोर 89/100** — पूर्ण रोडमैप P0~P3 पूरा, 22 मॉड्यूल फुल-स्टैक कवरेज, उत्पादन उपयोग योग्य।

| चरण | अवधि | वितरण | स्थिति |
|------|------|--------|------|
| 🔵 **P0** फ्रंटएंड पारिस्थितिकी | 3-4 सप्ताह | 97 Flutter पेज + 34 HarmonyOS पेज + 4 सामान्य घटक | ✅ |
| 🟢 **P1** व्यावसायिक गहराई | 4-6 सप्ताह | वित्त इंजन + वेतन इंजन + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** संचालन विश्वसनीयता | 1-2 सप्ताह | माइग्रेशन रोलबैक + स्वचालित बैकअप + TraceId + कतार दोहरा ड्राइवर | ✅ |
| 🟣 **P3** अनुभव संवर्द्धन | 2-3 सप्ताह | BI बोर्ड + EAM + मल्टी-टेनेंट + DMS + 7 नई तालिकाएँ | ✅ |

**परीक्षण**: 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## फ़ीचर सूची

| क्षेत्र | फ़ीचर |
|----|------|
| प्रमाणीकरण | लॉगिन/पंजीकरण/रीफ्रेश/लॉगआउट + कैप्चा + खाता लॉक + सत्र सीमा |
| डैशबोर्ड | व्यवसाय अवलोकन/बिक्री बोर्ड/स्टॉक बोर्ड/वित्त बोर्ड (Redis 5m कैश) |
| उपयोगकर्ता | CRUD + बैच डिलीट/सक्षम-अक्षम + Excel आयात |
| भूमिका-अनुमति | CRUD + अनुमति ट्री + RBAC method.path प्रमाणीकरण |
| सिस्टम कॉन्फ़िगरेशन | कुंजी-मूल्य जोड़ी CRUD |
| ऑपरेशन ऑडिट | लॉग क्वेरी + 8 प्लेटफ़ॉर्म स्रोत एंडपॉइंट स्वतः पहचान |
| फ़ाइल | अपलोड + Excel/PDF निर्यात (संवेदनशील डेटा मास्किंग) |
| सुरक्षा | 18 परत गहन रक्षा (XSS/SQL इंजेक्शन/CSRF/रेट लिमिट/CSP...) |
| संचालन | स्वास्थ्य जांच/Prometheus मेट्रिक्स/API दस्तावेज़/security.txt + Docker + CI/CD |
| उत्पाद प्रबंधन | उत्पाद/SKU/श्रेणी/ब्रांड/गोदाम/स्थान/आपूर्तिकर्ता/ग्राहक |
| क्रय प्रबंधन | अनुरोध→आदेश→प्राप्ति→वापसी→निपटान (स्वतः इनबाउंड + देय उत्पन्न) |
| विक्रय प्रबंधन | कोटेशन→आदेश→डिलीवरी→वापसी→निपटान (स्वतः आउटबाउंड + प्राप्य उत्पन्न) |
| इन्वेंटरी प्रबंधन | रीयल-टाइम स्टॉक/फ्लो/बैच/स्थानांतरण/गणना/अलर्ट (मूविंग वेटेड एवरेज लागत) |
| वित्त प्रबंधन | प्राप्य/देय/वाउचर/प्राप्ति-भुगतान/जर्नल/सामान्य खाता बही/विवरण खाता बही/तीन विवरण/स्थायी संपत्ति/कर/बहु-मुद्रा/बजट |
| CRM | अवसर/फॉलो-अप/फ़नल/संपर्क/पब्लिक पूल/अनुबंध/कोटेशन/मार्केटिंग/टिकट/विश्लेषण |
| अनुमोदन वर्कफ़्लो | वर्कफ़्लो परिभाषा/सबमिट/स्वीकृत/अस्वीकृत/वापस लें/मेरे अनुमोदन |
| संदेश अधिसूचना | अधिसूचना सूची/पठित/सभी पढ़े गए/अपठित गणना |
| परियोजना प्रबंधन | परियोजना/कार्य/कार्य-घंटे रिकॉर्ड |
| मानव संसाधन | विभाग/कर्मचारी/पद/उपस्थिति/अवकाश/वेतन |
| विनिर्माण | BOM/उत्पादन आदेश/प्रक्रिया मार्ग/वर्कस्टेशन/MRP |
| कस्टम रिपोर्ट | रिपोर्ट टेम्पलेट/डेटासेट/फ़ील्ड/फ़िल्टर/निष्पादन/शेड्यूलिंग |
| OMS ऑर्डर प्रबंधन | मल्टी-चैनल ऑर्डर/फ़ुलफ़िलमेंट ऑर्केस्ट्रेशन/स्टॉक रिज़र्वेशन (ATP)/RMA वापसी-विनिमय/चैनल प्रबंधन |
| WMS वेयरहाउस प्रबंधन | ज़ोन-स्थान (स्तर+बारकोड)/इनबाउंड (ASN→प्राप्ति→पुटअवे)/आउटबाउंड (वेव→पिकिंग→पैकिंग) |
| TMS परिवहन प्रबंधन | कैरियर/फ्रेट मूल्य तुलना/शिपमेंट लेबल/लॉजिस्टिक्स ट्रैकिंग (webhook) |
| QMS गुणवत्ता प्रबंधन | इनकमिंग IQC/प्रक्रिया IPQC/शिपिंग OQC निरीक्षण + निरीक्षण मानक + असंगत उत्पाद हैंडलिंग |
| EAM उपकरण प्रबंधन | उपकरण रजिस्टर/रखरखाव योजना/मरम्मत कार्य आदेश/स्पेयर पार्ट्स प्रबंधन |
| DMS दस्तावेज़ प्रबंधन | दस्तावेज़ श्रेणी/दस्तावेज़/संस्करण प्रबंधन |
| BI बोर्ड | बोर्ड लेआउट/चार्ट घटक |

## तकनीकी स्टैक

### बैकएंड
- PHP 8.3+, webman v2 (workerman/webman)
- डेटाबेस: MySQL 8.0+, टेबल उपसर्ग `erp_`
- प्राथमिक कुंजी: BIGINT गैर-ऑटोइंक्रीमेंट, `erikwang2013/snowflake-php` से उत्पन्न
- API परत ID एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/hashids`
- JWT प्रमाणीकरण: `erikwang2013/jwt-webman`
- API संवेदनशील डेटा एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/encryption`
- डेटाबेस संवेदनशील फ़ील्ड एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/encryptable`
- ES सिंक और क्वेरी: `erikwang2013/webman-scout`
- देश ध्वज: `erikwang2013/season`
- API दस्तावेज़ जनरेशन: `hg/apidoc` | एनोटेशन-आधारित, /apidoc पर जाएँ

### फ्रंटएंड
- Flutter 3.x, स्रोत निर्देशिका `apps/flutter/`
- वेब एंड PC प्रशासन बैकएंड शैली में डिज़ाइन किया गया (मोबाइल App शैली नहीं)
- क्लाइंट और व्यवस्थापक एंड समर्थित
- HarmonyOS ArkTS, स्रोत निर्देशिका `apps/harmonyos/`

## परियोजना संरचना

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

## मिडलवेयर निष्पादन श्रृंखला

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## सुरक्षा संवर्द्धन

- **HTTP विधि सीमा**: SecurityFilter केवल GET/POST/PUT/DELETE/OPTIONS/HEAD की अनुमति देता है, गैर-मानक विधि पर 405 लौटता है
- **CSP हेडर**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies सभी प्रतिक्रियाओं में इंजेक्ट होता है
- **खाता लॉक**: लगातार 5 बार लॉगिन विफल होने पर खाता 15 मिनट लॉक हो जाता है
- **समवर्ती सत्र सीमा**: एक उपयोगकर्ता के अधिकतम 3 वैध टोकन, अधिक होने पर सबसे पुराना टोकन ब्लैकलिस्ट में जाता है
- **security.txt**: `/.well-known/security.txt` RFC 9116 एंडपॉइंट
- **Nginx सुरक्षा कॉन्फ़िगरेशन**: `docs/nginx-security.conf` रिवर्स प्रॉक्सी सुरक्षा सुदृढ़ीकरण संदर्भ

## API संस्करण रणनीति

संस्करण अनुरोध हेडर `API-Version` से नियंत्रित होता है (डिफ़ॉल्ट `v1`), URL में दिखाई नहीं देता:

```bash
curl -H "API-Version: v1" http://localhost:8788/api/auth/login
```

नया संस्करण जोड़ने के लिए केवल `app/api/{version}/controller/` निर्देशिका बनाकर `ApiVersion` मिडलवेयर में पंजीकृत करें।

## रेट लिमिट रणनीति

Redis स्लाइडिंग विंडो (Lua परमाणु), डिफ़ॉल्ट 60 बार/मिनट/IP/रूट:
- लॉगिन: 10 बार/मिनट
- पंजीकरण: 5 बार/मिनट
- प्रतिक्रिया हेडर: `X-RateLimit-Limit/Remaining/Reset`, सीमा पार पर `Retry-After` जुड़ता है

## कोड मानदंड

### PHP
- वैश्विक फ़ंक्शन/क्लास संदर्भों में अग्रणी `\` नहीं, `use` आयात उपयोग
- कॉन्फ़िगरेशन फ़ाइलों में प्रत्येक कॉन्फ़िगरेशन आइटम के अर्थ की चीनी टिप्पणी होनी चाहिए
- सभी नई `.php` फ़ाइलों के हेडर में कॉपीराइट घोषणा होनी चाहिए

### डेटाबेस
- टेबल उपसर्ग: `erp_`
- प्राथमिक कुंजी `id`: BIGINT प्रकार, गैर-ऑटोइंक्रीमेंट, snowflake से उत्पन्न
- संवेदनशील फ़ील्ड `erikwang2013/encryptable` trait से स्वतः एन्क्रिप्ट/डिक्रिप्ट
- schema के लिए database/install.sql एकमात्र सत्य स्रोत (एकल फ़ाइल SQL)

### Flutter
- वेब एंड लेआउट PC प्रशासन बैकएंड शैली (साइडबार + टॉपबार + सामग्री क्षेत्र)
- GetX स्टेट मैनेजमेंट, `ApiService` सिंगलटन (Dio + JWT इंटरसेप्टर)
- टोकन पर्सिस्टेंस `shared_preferences` से
- रेस्पॉन्सिव ब्रेकपॉइंट: मोबाइल (< 768px) और डेस्कटॉप (>= 768px)

### HarmonyOS
- `@ohos.net.http` नेटिव HTTP क्लाइंट उपयोग
- टोकन निर्बाध रीफ्रेश: 401 पर स्वतः `/api/auth/refresh` कॉल
- रीफ्रेश विफल होने पर स्वतः लॉगिन पेज पर पुनर्निर्देशन

## परिनियोजन

### Docker Compose (उत्पादन के लिए अनुशंसित)

प्रोजेक्ट रूट `docker-compose.yml` में 5 सेवाओं का ऑर्केस्ट्रेशन:

| सेवा | विवरण |
|------|------|
| `nginx` | Nginx रिवर्स प्रॉक्सी (80/443), स्टैटिक फ़ाइल सेवा |
| `app` | webman PHP 8.3 एप्लिकेशन, `Dockerfile` से निर्माण (OPcache + event + redis सहित) |
| `mysql` | MySQL 8.0, डेटा वॉल्यूम पर्सिस्टेंस |
| `redis` | Redis 7 Alpine, कैश/रेट लिमिट/सत्र |
| `elasticsearch` | Elasticsearch 8.x, फुल-टेक्स्ट खोज |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions पाइपलाइन परिभाषित करता है (PHP 8.2/8.3/8.4 मैट्रिक्स):

- PHP सिंटैक्स जांच (`php -l`)
- PHPStan स्टैटिक विश्लेषण (`vendor/bin/phpstan analyse`)
- PHP CS Fixer कोड शैली जांच (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit यूनिट टेस्ट
- Composer सुरक्षा ऑडिट (`composer audit --no-dev`)

### डेटाबेस बैकअप

`database/backup/backup.sh` — mysqldump + gzip, 30 दिन पुराने बैकअप स्वतः साफ़।
`database/backup/restore.sh` — इंटरैक्टिव पुनर्स्थापना, उपलब्ध बैकअप सूचीबद्ध करता है।

### निगरानी

`GET /metrics` एंडपॉइंट (`MetricsController`) Prometheus text format में 5 gauge मेट्रिक्स आउटपुट करता है:
- `openadmin_http_requests_total` — कुल अनुरोध संख्या
- `openadmin_active_users` — सक्रिय उपयोगकर्ता संख्या
- `openadmin_db_connection_status` — डेटाबेस कनेक्शन स्थिति (0/1)
- `openadmin_redis_connection_status` — Redis कनेक्शन स्थिति (0/1)
- `openadmin_memory_usage_bytes` — मेमोरी उपयोग
