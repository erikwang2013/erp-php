# ERP पारिस्थितिकी तंत्र संपूर्ण रोडमैप — डिज़ाइन विनिर्देश

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 2026-08-04 पारिस्थितिकी ऑडिट रिपोर्ट के आधार पर निर्मित, P0~P3 चार प्राथमिकता चरणों को कवर करता है

---

## 1. वर्तमान आधार रेखा

| आयाम | वर्तमान स्थिति | स्कोर |
|------|------|------|
| बैकएंड API | 14 मॉड्यूल / 80+ कंट्रोलर / 120+ मॉडल, कई मॉड्यूल CRUD कंकाल | 85/100 |
| सुरक्षा सुरक्षा | 18 परत गहन रक्षा, CORS/SecurityFilter/RateLimit/JWT/एन्क्रिप्शन | 95/100 |
| फ्रंटएंड UI | Flutter 12 पेज, HarmonyOS 9 पेज, लगभग 20% मॉड्यूल कवर; Web प्रशासन पैनल अनुपस्थित | 20/100 |
| संचालन पारिस्थितिकी | Dockerीकरण, CI पूर्ण, माइग्रेशन रोलबैक, बैकअप स्वचालन, अवलोकनीयता की कमी | 70/100 |
| व्यावसायिक गहराई | वित्त/HR/विनिर्माण मॉड्यूल तालिका संरचना पूर्ण परंतु व्यावसायिक लॉजिक मुख्यतः CRUD | 55/100 |
| **समग्र** | | **65/100** |

---

## 2. समग्र रणनीति

```
串行瀑布: P0 → P1 → P2 → P3
每个阶段内有独立性的子任务可并行推进
```

### 2.1 फ्रंटएंड तकनीक चयन

- **Web प्रशासन पैनल**: Flutter Web, `apps/flutter` के मौजूदा कोड का पुन: उपयोग, PC प्रशासन बैकएंड शैली, GetX स्टेट मैनेजमेंट
- **मोबाइल एंड**: Flutter (iOS/Android), Web के साथ `apps/flutter/lib/app/` व्यावसायिक कोड साझा करता है
- **HarmonyOS**: ArkTS, Flutter फ़ीचर सेट के अनुरूप

### 2.2 बैकएंड रणनीति

- **इंडस्ट्रियल-ग्रेड** (A स्तर): डबल-एंट्री बुककीपिंग, वेतन गणना, MRP इंजन — एल्गोरिदम पूर्ण, सीमा हैंडलिंग पर्याप्त, उत्पादन उपयोग योग्य
- **मुख्य उपयोग योग्य** (B स्तर): गुणवत्ता प्रबंधन, नोटिफिकेशन सिस्टम, BI बोर्ड — मुख्य नियम लागू, आगे आवश्यकता अनुसार पुनरावृत्ति

---

## 3. P0 — फ्रंटएंड पारिस्थितिकी (3-4 सप्ताह)

> **लक्ष्य**: सिस्टम के पास उपयोग योग्य प्रशासन इंटरफ़ेस हो, सभी लागू बैकएंड मॉड्यूल कवर हों

### 3.1 Flutter परियोजना आर्किटेक्चर पुनर्गठन

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

### 3.2 सामान्य घटक विकास

| घटक | फ़ंक्शन | उपयोग परिदृश्य |
|------|------|----------|
| `DataTableWrapper` | पेजिनेशन/सॉर्टिंग/कीवर्ड खोज/स्थिति फ़िल्टर/बैच चयन/कॉलम कॉन्फ़िगरेशन | सभी सूची पेज |
| `FormDialog` | डायनामिक फ़ॉर्म रेंडरिंग/फ़ील्ड सत्यापन/सबमिट/बंद | सभी निर्माण/संपादन डायलॉग |
| `ConfirmDialog` | पासवर्ड द्वितीय पुष्टि इनपुट | सभी डिलीट ऑपरेशन |
| `StatCard` | मान/रुझान तीर/शीर्षक | डैशबोर्ड |
| `BreadcrumbNav` | ब्रेडक्रंब नेविगेशन | गहरे स्तर के पेज |
| `FileUploader` | ड्रैग-ड्रॉप अपलोड/प्रगति/पूर्वावलोकन | आयात/छवि अपलोड |

### 3.3 HarmonyOS पूर्ति

Flutter पेज सेट के अनुरूप, पूरा करें: OMS/WMS/TMS/विनिर्माण/HR/अनुमोदन/नोटिफिकेशन/रिपोर्ट मॉड्यूल पेज।

### 3.4 P0 स्वीकृति मानदंड

- [ ] Flutter Web प्रशासन पैनल सभी 14 मॉड्यूल कवर करता है
- [ ] सभी CRUD सूची पेज उपयोग योग्य (पेजिनेशन/खोज/फ़िल्टर)
- [ ] सभी निर्माण/संपादन फ़ॉर्म उपयोग योग्य (सत्यापन/सबमिट)
- [ ] डिलीट ऑपरेशन पर द्वितीय पासवर्ड पुष्टि
- [ ] JWT स्वतः रीफ्रेश निर्बाध
- [ ] PC/टैबलेट/फ़ोन रिस्पॉन्सिव लेआउट अनुकूलन
- [ ] HarmonyOS पेज संख्या ≥ Flutter पेज संख्या का 80%

---

## 4. P1 — व्यावसायिक गहराई (4-6 सप्ताह)

> **लक्ष्य**: मुख्य मॉड्यूल को CRUD कंकाल से वास्तविक व्यावसायिक गणना इंजन तक उन्नत करना

### 4.1 वित्त डबल-एंट्री बुककीपिंग इंजन (इंडस्ट्रियल-ग्रेड)

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

**मुख्य नियम**:
- वाउचर सहेजते समय "डेबिट हो तो क्रेडिट होना चाहिए, डेबिट और क्रेडिट बराबर होना चाहिए" अनिवार्य रूप से लागू होता है
- अनुमोदित वाउचर संशोधित नहीं हो सकते, रेड-इंक राइट-ऑफ आवश्यक
- अवधि अंत हस्तांतरण: लाभ-हानि खाता शेष → वर्ष का लाभ, बहु-चरण हस्तांतरण समर्थित
- बहु-मुद्रा: अवधि अंत विनिमय दर से रूपांतरण, विनिमय लाभ-हानि स्वतः गणना

### 4.2 वेतन गणना इंजन (इंडस्ट्रियल-ग्रेड)

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

**मुख्य नियम**:
- सामाजिक सुरक्षा आधार ऊपरी/निचली सीमा (प्रत्येक शहर वार्षिक समायोजन, कॉन्फ़िगर करने योग्य)
- हाउसिंग फंड आधार + अंशदान दर (5%-12%, कॉन्फ़िगर करने योग्य)
- व्यक्तिगत कर प्रगतिशील दर तालिका (3%-45%, वार्षिक निपटान)
- बैंक वेतन फ़ाइल प्रारूप: ICBC/BOC/CCB/CMB जैसे प्रमुख बैंक समर्थित
- वेतन पर्ची निर्माण (सभी विवरण सहित)

### 4.3 MRP इंजन (इंडस्ट्रियल-ग्रेड)

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

**मुख्य नियम**:
- BOM परत-दर-परत विस्तार, हानि दर पर विचार
- शुद्ध आवश्यकता = कुल आवश्यकता - मौजूदा स्टॉक - पारगमन स्टॉक + आवंटित मात्रा + सुरक्षा स्टॉक
- लो-लेवल कोड (LLC) सुनिश्चित करता है कि एक ही सामग्री की गणना केवल एक बार हो
- लीड टाइम से सुझावित ऑर्डर दिनांक पीछे की ओर निकालें
- बैच नियम: निश्चित बैच/आर्थिक बैच/आवश्यकता अनुसार

### 4.4 गुणवत्ता प्रबंधन (मुख्य उपयोग योग्य)

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

### 4.5 रीयल-टाइम नोटिफिकेशन सिस्टम (मुख्य उपयोग योग्य)

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

**मुख्य नियम**:
- WebSocket workerman नेटिव प्रोटोकॉल पर आधारित
- नोटिफिकेशन टेम्पलेट: वेरिएबल प्लेसहोल्डर `{order_code}` रनटाइम पर प्रतिस्थापित
- चैनल प्राथमिकता: इन-साइट → ईमेल → वेकॉम → डिंगटॉक, कॉन्फ़िगर करने योग्य

### 4.6 P1 स्वीकृति मानदंड

- [ ] वाउचर सहेजते समय डेबिट-क्रेडिट असमान → त्रुटि लौटाएँ
- [ ] वेतन इंजन आउटपुट मैनुअल गणना से मेल खाता है (10 व्यक्तियों के मासिक वेतन डेटा का नमूना)
- [ ] MRP शुद्ध आवश्यकता गणना Excel मैनुअल गणना से मेल खाती है
- [ ] गुणवत्ता निरीक्षण तीन दस्तावेज़ (IQC/IPQC/OQC) पूर्ण प्रवाह
- [ ] WebSocket नोटिफिकेशन विलंब < 2 सेकंड
- [ ] सभी नई सेवाओं में PHPUnit टेस्ट कवरेज (मुख्य एल्गोरिदम ≥ 95%)

---

## 5. P2 — संचालन विश्वसनीयता (1-2 सप्ताह)

> **लक्ष्य**: प्रोडक्शन-ग्रेड संचालन क्षमता

### 5.1 डेटाबेस माइग्रेशन रोलबैक

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

प्रत्येक माइग्रेशन फ़ाइल के लिए संबंधित `_rollback.sql` फ़ाइल जोड़ें।

### 5.2 बैकअप-रीस्टोर संवर्द्धन

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 अवलोकनीयता

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- अनुरोध-स्तरीय trace ID (प्रतिक्रिया हेडर `X-Trace-Id` के माध्यम से उजागर)
- मुख्य व्यावसायिक मेट्रिक्स: ऑर्डर मात्रा, पूर्ति दर, इन्वेंटरी टर्नओवर दिन

### 5.4 मैसेज कतार अपग्रेड

मौजूदा Redis कतार → वैकल्पिक ड्राइवर के रूप में RabbitMQ समर्थन:

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 P2 स्वीकृति मानदंड

- [ ] माइग्रेशन रोलबैक स्क्रिप्ट निष्पादन योग्य और डेटा अखंडता सत्यापन पास
- [ ] स्वचालित बैकअप cron सामान्य रूप से ट्रिगर होता है
- [ ] Trace ID अनुरोध की पूरी श्रृंखला में व्याप्त
- [ ] RabbitMQ ड्राइवर स्विच करने योग्य और संदेश नष्ट नहीं होते

---

## 6. P3 — अनुभव संवर्द्धन (2-3 सप्ताह)

> **लक्ष्य**: उन्नत सुविधाएँ और बेहतर उपयोगकर्ता अनुभव

### 6.1 BI डेटा बोर्ड

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

- ड्रैग-ड्रॉप लेआउट वाला डैशबोर्ड
- विजेट: बार चार्ट/लाइन चार्ट/पाई चार्ट/डेटा कार्ड/टेबल
- `app/controller/report/` की डेटासेट तंत्र का पुन: उपयोग

### 6.2 उपकरण प्रबंधन (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 मल्टी-टेनेंट

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- साझा डेटाबेस + `tenant_id` अलगाव
- सुपर एडमिन क्रॉस-टेनेंट दृश्य

### 6.4 दस्तावेज़ प्रबंधन (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 P3 स्वीकृति मानदंड

- [ ] BI डैशबोर्ड ड्रैग-ड्रॉप कस्टम लेआउट
- [ ] उपकरण रजिस्टर → रखरखाव योजना → मरम्मत कार्य आदेश बंद-लूप
- [ ] टेनेंट A टेनेंट B का डेटा एक्सेस नहीं कर सकता
- [ ] दस्तावेज़ संस्करण इतिहास ट्रेसेबल

---

## 7. डेटा मॉडल परिवर्तन सारांश

### P0 नई तालिकाएँ

कोई नई तालिका नहीं, फ्रंटएंड पारिस्थितिकी बैकएंड तालिका संरचना परिवर्तन से संबंधित नहीं।

### P1 नई तालिकाएँ

| तालिका नाम | उपयोग | चरण |
|------|------|------|
| `erp_finance_period_close` | अवधि अंत हस्तांतरण रिकॉर्ड | P1 |
| `erp_finance_account_balance` | खाता शेष स्नैपशॉट | P1 |
| `erp_hr_salary_config` | वेतन गणना कॉन्फ़िगरेशन | P1 |
| `erp_hr_social_insurance_config` | सामाजिक सुरक्षा आधार कॉन्फ़िगरेशन | P1 |
| `erp_hr_housing_fund_config` | हाउसिंग फंड कॉन्फ़िगरेशन | P1 |
| `erp_mfg_mrp_run_log` | MRP गणना लॉग | P1 |
| `erp_mfg_order_suggestion` | सुझावित ऑर्डर | P1 |
| `erp_quality_inspection_standard` | निरीक्षण मानक | P1 |
| `erp_quality_iqc_record` | IQC इनकमिंग निरीक्षण | P1 |
| `erp_quality_ipqc_record` | IPQC प्रक्रिया निरीक्षण | P1 |
| `erp_quality_oqc_record` | OQC शिपिंग निरीक्षण | P1 |
| `erp_quality_nonconformity` | असंगत उत्पाद | P1 |
| `erp_notification_channel_config` | नोटिफिकेशन चैनल कॉन्फ़िगरेशन | P1 |
| `erp_notification_template` | नोटिफिकेशन टेम्पलेट | P1 |

### P3 नई तालिकाएँ

| तालिका नाम | उपयोग | चरण |
|------|------|------|
| `erp_bi_dashboard` | BI डैशबोर्ड | P3 |
| `erp_bi_widget` | BI विजेट | P3 |
| `erp_eam_equipment` | उपकरण रजिस्टर | P3 |
| `erp_eam_maintenance_plan` | रखरखाव योजना | P3 |
| `erp_eam_repair_order` | मरम्मत कार्य आदेश | P3 |
| `erp_dms_document` | नियंत्रित दस्तावेज़ | P3 |
| `erp_dms_document_version` | दस्तावेज़ संस्करण | P3 |

---

## 8. सेवा परत परिवर्तन सारांश

| सेवा | वर्तमान | P1 परिवर्तन | P2 परिवर्तन | P3 परिवर्तन |
|------|------|---------|---------|---------|
| FinanceService | CRUD | नया DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| वेतन | कोई नहीं | नया SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| विनिर्माण | CRUD | नया MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| गुणवत्ता | कोई नहीं | नया QmsInspectionService | — | — |
| नोटिफिकेशन | आधार | नया WebSocketService, ChannelRouter | — | — |
| अवलोकनीयता | Monitor प्रक्रिया | — | नया TracerService, MetricCollector | — |
| BI | कोई नहीं | — | — | नया BiDashboardService |
| उपकरण | कोई नहीं | — | — | नया EamService |

---

## 9. मिडलवेयर श्रृंखला परिवर्तन

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. माइलस्टोन और वितरण

| माइलस्टोन | समय | वितरण |
|--------|------|--------|
| M0 — वर्तमान आधार रेखा | 2026-08-04 | ऑडिट रिपोर्ट `audit-report-2026-08-04.md` |
| M1 — P0 पूर्ण | +3 सप्ताह | Flutter Web सभी मॉड्यूल प्रशासन पैनल |
| M2 — P1 पूर्ण | +8 सप्ताह | वित्त इंजन + वेतन इंजन + MRP इंजन + गुणवत्ता + नोटिफिकेशन |
| M3 — P2 पूर्ण | +10 सप्ताह | माइग्रेशन रोलबैक + स्वचालित बैकअप + Trace + कतार अपग्रेड |
| M4 — P3 पूर्ण | +13 सप्ताह | BI बोर्ड + उपकरण प्रबंधन + मल्टी-टेनेंट + दस्तावेज़ प्रबंधन |

---

## 11. जोखिम और शमन

| जोखिम | प्रभाव | शमन उपाय |
|------|------|----------|
| Flutter Web प्रदर्शन नेटिव JS से कम | बड़े डेटा टेबल में लैग | क्लाइंट पेजिनेशन + वर्चुअल स्क्रॉलिंग + Web Worker |
| वेतन इंजन नियम परिवर्तन | गणना परिणाम गैर-अनुपालन | सामाजिक सुरक्षा/कर दर कॉन्फ़िगर करने योग्य, हार्डकोड नहीं |
| MRP गणना बड़े डेटा पर टाइमआउट | गणना बाधित | बैच प्रोसेसिंग + प्रगति कॉलबैक |
| WebSocket लंबे कनेक्शन अत्यधिक | सर्वर मेमोरी दबाव | workerman स्वाभाविक उच्च समवर्ती + कनेक्शन सीमा |
| मल्टी-टेनेंट डेटा अलगाव चूक | डेटा रिसाव | TenantScope वैश्विक मिडलवेयर + टेस्ट कवरेज |

---

## 12. नहीं किए जाने वाले कार्य (स्पष्ट बहिष्करण)

- ❌ माइक्रोसर्विस विभाजन नहीं — वर्तमान मोनोलिथ आर्किटेक्चर पर्याप्त, जटिल लॉजिक Service परत में एकीकृत
- ❌ Kubernetes नहीं — Docker Compose वर्तमान पैमाने के लिए पर्याप्त
- ❌ AI/ML फ़ीचर नहीं — MVP रोडमैप में नहीं
- ❌ नेटिव iOS/Android स्वतंत्र App नहीं — Flutter क्रॉस-प्लेटफ़ॉर्म पहले से कवर करता है
- ❌ GraphQL नहीं — RESTful API पर्याप्त, API संस्करण रणनीति परिपक्व
- ❌ इलेक्ट्रॉनिक सिग्नेचर/WMS हार्डवेयर एकीकरण (PDA/स्कैनर) नहीं — विशुद्ध सॉफ़्टवेयर स्तर
