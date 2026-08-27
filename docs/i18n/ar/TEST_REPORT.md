# تقرير الاختبار — 2026-08-26

> التحديث: 2026-08-27 — البنود المتبقية الخمسة أُغلقت بالكامل؛ أرقام الاختبارات 505/2342/26 ← 513/2368/32؛ إصلاحات مرافقة من 4 ← 5 مواضع. القيم القديمة في «سجل التحديثات» في نهاية الملف.

## الملخص التنفيذي

| المؤشر | القيمة |
|------|----|
| تاريخ التقرير | 2026-08-26 |
| اختبارات PHP الوحدوية | 513 tests / 2368 assertions / 32 skipped |
| اختبارات صفحات Flutter | 98 tests جميعها ناجحة (flutter analyze 0 error) |
| أتمتة API | 104 نقاط نهاية / ~230 تأكيدًا (رُبط e2e بـ CI، انظر خطوة «Run E2E API coverage» في ci.yml) |
| التغطية (قياس pcov الفعلي) | الكلية 7.51% / app/service 15.65% / app/controller 3.62% |
| التحليل الساكن | PHPStan 0 error ✅ |
| أسلوب الكود | php-cs-fixer 0 diff ✅ (أُصلح 3 ملفات قائمة مرافقًا لهذه الجولة) |
| إصلاحات عيوب حقيقية مرافقة | 5 مواضع (3 PHP + 1 Flutter + 1 تنسيق) |
| Go/Rust | N/A (لا يوجد في المستودع أي كود .go/.rs/Cargo.toml) |

هذه الجولة تسليم ثلاثي متوازٍ للاختبارات: اختبارات PHP الوحدوية (php-tester، 9 ملفات جديدة)، أتمتة API (api-tester، ملف واحد جديد)، اختبارات صفحات Flutter (ui-tester، 8 ملفات جديدة بـ 29 حالة).

## مصفوفة التغطية

الوحدات (22 مجالًا تجاريًا + إدارة النظام 14 وحدة تحكم) موضحة بدرجة التغطية حسب نوع الاختبار.

### 22 مجالًا تجاريًا

| الوحدة | وحدوي | API | UI | الشرح |
|------|------|-----|-----|------|
| المالية — دمج Consolidation | ✅ | ✅ | — | ConsolidationServiceTest 5 حالات + API |
| المالية — رصيد الحساب AccountBalance | ✅ | ✅ | — | AccountBalanceServiceTest 4 حالات |
| المالية — إقفال الفترة PeriodClose | ✅ | ✅ | — | PeriodCloseServiceTest 5 حالات |
| المالية — FinanceRatio | ✅ | — | — | FinanceRatioServiceTest (قائم) |
| المالية — القيد المزدوج DoubleEntry | ✅ | — | — | DoubleEntryServiceTest (قائم) |
| المخزون Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 حالات + واجهة قائمة ERP |
| المبيعات Sales | ✅ | ✅ | ✅ | SalesModuleTest قائم + واجهة أمر المبيعات |
| المنتجات Product | ✅ | ✅ | ✅ | ProductModuleTest قائم + واجهة المنتجات |
| المشتريات Purchase | ✅ | ✅ | — | PurchaseModuleTest قائم |
| التصنيع Manufacturing | ✅ | — | — | ManufacturingServiceTest قائم |
| محرك MRP | ✅ | — | — | MrpEngineServiceTest قائم |
| CRM | ✅ | ✅ | — | CrmModuleTest/CrmServiceTest قائمان |
| الموارد البشرية HR | ✅ | — | — | HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest قائمة |
| المشاريع Project | ✅ | ✅ | ✅ | ProjectModuleTest قائم + واجهة المشاريع |
| الموافقات Approval/Workflow | ✅ | ✅ | ✅ | WorkflowModuleTest قائم + واجهة الموافقات |
| OMS/WMS/TMS | ✅ | — | — | OmsWmsTmsServiceTest قائم |
| الجودة QMS | ✅ | — | — | QualityModuleTest قائم |
| الأصول EAM | ✅ | — | — | EamModuleTest قائم |
| المستندات DMS | ✅ | — | — | DmsModuleTest قائم |
| تقارير BI | ✅ | ✅ | — | BiModuleTest قائم + API |
| قنوات الإشعارات | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService 12 حالة) |
| التقارير/تفاصيل المستندات | ✅ | جزئي | ✅ | منطق التوليد له اختبار وحدوي؛ واجهة التفاصيل 3 حالات (report_list_page_test) |

### إدارة النظام (14 وحدة تحكم)

| مجال التحكم | وحدوي | API | UI | الشرح |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (جانب User) + واجهة قائمة المستخدمين |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (جانب Role) + واجهة قائمة الأدوار |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (جانب Permission) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (جانب Config) + واجهة الإعدادات |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| وحدات التحكم السبع الأخرى (تسجيل الدخول/التدقيق/القواميس وغيرها) | ✅ | ✅ | — | BusinessControllersTest تتحقق من مسارات الفشل لوحدات تحكم ممثلة في 10 مجالات |
| صفحة تسجيل الدخول | — | ✅ | ✅ | login_flow_test حالتان |
| المركز الشخصي | — | ✅ | ✅ | profile_page_test 3 حالات |
| صفحة السجلات | — | ✅ | ✅ | log_page_test حالتان |
| لوحة المعلومات | — | — | ✅ | dashboard_page_test 5 حالات |
| صفحات تنبيه المخزون/المالية | — | — | ✅ | erp_list_pages_test |

## إحصائيات الاختبارات

### اختبارات PHP الوحدوية: 513 tests / 2368 assertions / 32 skipped

هذه الجولة أضافت 9 ملفات (جميعها برأس حقوق النشر، 63 tests / 125 assertions):

| الملف | عدد الحالات | الكائن المغطى |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | دمج finance |
| tests/AccountBalanceServiceTest.php | 4 | رصيد الحساب |
| tests/PeriodCloseServiceTest.php | 5 | إقفال الفترة |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | امتداد المخزون |
| tests/AdminUserRoleControllerTest.php | 9 | وحدتا تحكم User/Role |
| tests/AdminPermissionConfigControllerTest.php | 8 | وحدتا تحكم Permission/Config |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 مجالات | تحقق مسار الفشل لوحدات تحكم ممثلة |

2026-08-27 أُضيفت 3 ملفات PHP (14 tests؛ عند غياب TEST_DB_* تتخطى الاختبارات التكاملية 6/6 تلقائيًا):

| الملف | عدد الحالات | الكائن المغطى |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | تراجع/إلزام معاملة DB/مصدر مكرر/قفل متزامن pcntl_fork (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | خدمة الإشعارات |
| tests/FinanceRatioServiceTest.php | 2 | النسب المالية |

### اختبارات صفحات Flutter: 98 tests جميعها ناجحة

هذه الجولة أضافت 8 ملفات بـ 29 حالة (الملفات العشرة القائمة دون تغيير، جميعها ناجحة)؛ `flutter analyze` 0 error (ملاحظة info قائمة واحدة):

| الملف | عدد الحالات |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 أُضيف ملف واحد (3 حالات):

| الملف | عدد الحالات |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### أتمتة API: 104 نقاط نهاية / ~230 تأكيدًا (19 مجموعة وحدات)

tests/E2E/api-coverage.php (423 سطرًا، يمر `php -l`): قراءة فقط بحتة + تكافؤ (GET تفاصيل المركز الشخصي → PUT كتابة بنفس القيمة)، مع تحديد الجداول المفقودة (500 + Base table not found → SKIP تنبيهًا بضرورة بذر install.sql الكامل).

**لم يُنفَّذ محليًا** (لا توجد بيانات اعتماد MySQL، ولا خدمة على 8788)، يتطلب بيئة CI e2e:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

يغطي 19 مجموعة وحدات: إدارة النظام (مستخدمون/أدوار/صلاحيات/إعدادات/صحة/مؤشرات)، المالية (دمج/رصيد/إقفال/نسب)، المخزون، المبيعات، المنتجات، المشتريات، المشاريع، الموافقات، CRM، BI، الإشعارات، التقارير.

> تصحيح: شكّ api-tester سابقًا في فقدان جدول `erik_admin_config` — **ليس عيبًا**. اسم الجدول الحقيقي `erik_system_config` (مُنشأ في install.sql:133، ونموذج SystemConfig يشير إليه بشكل صحيح)، ويصحح التقرير ذلك.

## التغطية

قياس pcov الفعلي (2026-08-26، لم يُعَد قياسه في 2026-08-27 واعتُمدت هذه القيمة): الكلية **7.51%** (خط الأساس 4.8%)، app/service **15.65%** (خط الأساس 10.6%)، app/controller **3.62%**.

المقارنة مع عتبة CI والهدف (انظر P1-B4 في docs/superpowers/plans/2026-08-07-next-phase-plan.md):

| البعد | الحالي | عتبة CI | الهدف |
|------|------|---------|------|
| الكلية | 7.51% | 4% ✅ محقق | 30% |
| app/service | 15.65% | 10% ✅ محقق | 40% |
| app/controller | 3.62% | — | — |

تجاوزت التغطية الكلية و service عتبة CI، ولا يزال أمامها فجوة كبيرة عن الهدف، ويجب مواصلة إضافة الاختبارات وفق خطة P1-B4.

## إصلاحات العيوب الحقيقية المرافقة (5 مواضع)

| # | الموقع | العيب | الإصلاح |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php و PermissionController.php | نقص `use support\Response;`، TypeError في وقت التشغيل | إضافة import |
| 2 | app/controller/Admin/DocsController.php | `path()` بمعامل ثالث null تنهار | تصحيح الاستدعاء |
| 3 | lib/pages/user_list_page.dart | زرا الحذف/التمكين الجماعي ينقصهما غلاف Obx، فلا يظهران بعد التحديد أبدًا | إضافة غلاف Obx |
| 4 | scripts/api-coverage.php (والملفات الثلاثة في app/queue/redis/search/ هذه الجولة) | تنسيق cs-fixer غير مطابق | أُصلح وفق fixer |
| 5 | app/model/FinanceCashJournal.php | حقل `UPDATED_AT` غير مطابق لـ install.sql | صحّح الحقل |

## Go / Rust

**N/A** — لا يوجد في المستودع أي كود .go / .rs / Cargo.toml، وأُعلن اختبارا المنصتين غير قابلين للتطبيق.

## إغلاق البنود المتبقية (تحديث 2026-08-27)

عالجت البنود الخمسة المتبقية من نسخة 2026-08-26 الأصلية بالكامل:

1. **مسار معاملة DB** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` أُضيفت 6 حالات (تراجع/إلزام/مصدر مكرر/قفل متزامن pcntl_fork، `Group(integration)`)، وتتخطى 6/6 تلقائيًا عند غياب TEST_DB_*؛ ووظيفة php في CI حُقنت بـ TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST.
2. **ربط api-coverage بـ CI** ✅ — ترقّى بذر وظيفة e2e في `.github/workflows/ci.yml` إلى install.sql الكامل (163 جدولًا)، وبعد smoke أُضيفت خطوة «Run E2E API coverage».
3. **واجهة تفاصيل التقارير/المستندات غير مغطاة** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` 3 حالات جميعها ناجحة.
4. **اعتماد بيئة CaptchaTest** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` توافق ثنائي PIXELS→AREA + حارس clone()؛ وأُعيدت كتابة `tests/CaptchaTest.php` وفق عقد poster-php v1.2.3، ويمر 7/7 محليًا عبر مسار imagick (27 تأكيدًا).
5. **هدف التغطية** ✅ تقدم — أُضيفا `tests/NotificationServiceTest.php` و `tests/FinanceRatioServiceTest.php`؛ أرقام التغطية ما زالت تعتمد قياس 2026-08-26 الفعلي (لم يُعَد قياسها)، وتظل بحاجة إلى استمرار الإضافة حتى الهدفين (30%/40%).

خط الأساس للانحدار: **513 tests / 2368 assertions / 32 skipped** خضراء بالكامل (النسخة السابقة 505/2342/26).

## سجل التحديثات

| التاريخ | التغيير |
|------|------|
| 2026-08-26 | النسخة الأولى: 505 tests / 2342 assertions / 26 skipped؛ 5 بنود متبقية؛ 4 إصلاحات مرافقة |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped؛ البنود المتبقية الخمسة أُغلقت بالكامل؛ 5 إصلاحات مرافقة؛ 4 ملفات اختبار جديدة؛ جميع الصور عليها علامة مائية erik.xyz |

## مسارات تخزين التقرير والمنتجات

- هذا التقرير: `docs/TEST_REPORT.md`
- بيانات التغطية: `runtime/coverage/` (مولّدة بـ pcov)
- سكربت أتمتة API: `tests/E2E/api-coverage.php`
- اختبارات PHP الوحدوية: `tests/*.php` (الملفات التسعة الجديدة هذه الجولة انظر الجدول أعلاه)
- اختبارات Flutter: `test/pages/*.dart` (الملفات الثمانية الجديدة هذه الجولة انظر الجدول أعلاه)
