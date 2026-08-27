# لوحة الإدارة المفتوحة (open-admin)

نظام إدارة خلفية متكامل مبني على webman v2 + Flutter.

![تميمة الأخطبوط](images/mascot.svg)

## إعلان حقوق النشر

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **لا يُعدَّل ولا يُحذف ولا يُلغي.** يجب أن يحتوي كل ملف جديد على إعلان حقوق النشر أعلاه كتعليق رأس الملف.

## خارطة طريق النظام البيئي

> مواصفات التصميم: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> وثيقة البنية: `ARCHITECTURE.md` §21
> مصفوفة الوظائف: `FUNCTIONS.md` §19

**النتيجة الشاملة الحالية 89/100** — اكتملت خارطة الطريق الكاملة P0~P3، تغطية شاملة 22 وحدة من الطرف إلى الطرف، جاهزة للإنتاج.

| المرحلة | المدة | التسليمات | الحالة |
|------|------|--------|------|
| 🔵 **P0** بيئة الواجهة | 3-4 أسابيع | 97 صفحة Flutter + 34 صفحة HarmonyOS + 4 مكونات عامة | ✅ |
| 🟢 **P1** عمق الأعمال | 4-6 أسابيع | محرك المالية + محرك الرواتب + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** موثوقية التشغيل | 1-2 أسبوعين | استرجاع الهجرات + نسخ احتياطي تلقائي + TraceId + محركا قوائم انتظار | ✅ |
| 🟣 **P3** تحسين التجربة | 2-3 أسابيع | لوحات BI + EAM + تعدد المستأجرين + DMS + 7 جداول جديدة | ✅ |

**الاختبارات**: 513 اختبارًا، 2368 تأكيدًا (32 متخطيًا) — جميعها ناجحة. **Flutter**: 0 خطأ، 0 تحذير.

## قائمة الوظائف

| المجال | الوظيفة |
|----|------|
| المصادقة | تسجيل دخول/تسجيل/تحديث/خروج + كابتشا + قفل الحساب + تقييد الجلسات |
| لوحة المعلومات | نظرة عامة تشغيلية/لوحة مبيعات/لوحة مخزون/لوحة مالية (تخزين مؤقت Redis 5m) |
| المستخدمون | CRUD + حذف جماعي/تمكين-تعطيل + استيراد Excel |
| أدوار الصلاحيات | CRUD + شجرة الصلاحيات + مصادقة RBAC method.path |
| إعدادات النظام | CRUD لأزواج المفاتيح والقيم |
| تدقيق العمليات | استعلام السجلات + كشف تلقائي لمصدر 8 منصات |
| الملفات | رفع + تصدير Excel/PDF (إخفاء البيانات الحساسة) |
| الأمان | دفاع متعمق من 18 طبقة (XSS/حقن SQL/CSRF/تحديد المعدل/CSP...) |
| التشغيل | فحص الصحة/مؤشرات Prometheus/وثائق API/security.txt + Docker + CI/CD |
| إدارة المنتجات | المنتج/SKU/التصنيف/العلامة التجارية/المستودع/الموقع/المورد/العميل |
| إدارة المشتريات | طلب←أمر←استلام←إرجاع←تسوية (إدخال تلقائي للمخزون + توليد مستحقات) |
| إدارة المبيعات | عرض سعر←أمر←شحن←إرجاع←تسوية (إخراج تلقائي من المخزون + توليد مستحقات) |
| إدارة المخزون | مخزون فوري/سجلات/دفعات/تحويل/جرد/تنبيهات (تكلفة المتوسط المتحرك المرجح) |
| الإدارة المالية | مستحقات/سندات/مقبوضات ومدفوعات/دفتر يومي/دفتر عام/دفتر فرعي/ثلاثة بيانات/أصول ثابتة/ضرائب/عملات متعددة/ميزانيات |
| CRM | فرص/متابعات/قمع/جهات اتصال/بركة عامة/عقود/عروض أسعار/تسويق/تذاكر/تحليل |
| سير عمل الموافقات | تعريف سير العمل/تقديم/موافقة/رفض/سحب/موافقاتي |
| إشعارات الرسائل | قائمة الإشعارات/مقروء/قراءة الكل/عدد غير المقروء |
| إدارة المشاريع | مشاريع/مهام/سجلات ساعات العمل |
| الموارد البشرية | أقسام/موظفون/مناصب/حضور/إجازات/رواتب |
| التصنيع | BOM/أوامر إنتاج/مسارات/محطات عمل/MRP |
| التقارير المخصصة | قوالب تقارير/مجموعات بيانات/حقول/فلاتر/تنفيذ/جدولة دورية |
| إدارة الطلبات OMS | أوامر متعددة القنوات/تنسيق التنفيذ/حجز المخزون (ATP)/RMA/إدارة القنوات |
| إدارة المستودعات WMS | مناطق ومواقع (هرمية + باركود)/إدخال (ASN←استلام←تخزين)/إخراج (موجات←انتقاء←تغليف) |
| إدارة النقل TMS | ناقلون/مقارنة رسوم الشحن/بوليصة شحن/مسار شحنة (webhook) |
| إدارة الجودة QMS | فحص IQC/IPQC/OQC + معايير الفحص + معالجة غير المطابق |
| إدارة المعدات EAM | سجل المعدات/خطط الصيانة/أوامر الإصلاح/إدارة قطع الغيار |
| إدارة المستندات DMS | تصنيفات المستندات/المستندات/إدارة الإصدارات |
| لوحات BI | تخطيط اللوحات/مكونات الرسوم البيانية |

## الحزمة التقنية

### الخلفية
- PHP 8.3+، webman v2 (workerman/webman)
- قاعدة البيانات: MySQL 8.0+، بادئة الجداول `erp_`
- المفتاح الأساسي: BIGINT غير تلقائي التزايد، يولَّد عبر `erikwang2013/snowflake-php`
- تشفير/فك تشفير معرفات طبقة API: `erikwang2013/hashids`
- مصادقة JWT: `erikwang2013/jwt-webman`
- تشفير/فك تشفير بيانات API الحساسة: `erikwang2013/encryption`
- تشفير/فك تشفير حقول قاعدة البيانات الحساسة: `erikwang2013/encryptable`
- مزامنة واستعلام ES: `erikwang2013/webman-scout`
- أعلام الدول: `erikwang2013/season`
- توليد وثائق API: `hg/apidoc` | بأسلوب التعليقات، الوصول عبر /apidoc

### الواجهة
- Flutter 3.x، دليل المصدر `apps/flutter/`
- مصمم الويب بأسلوب لوحة إدارة الكمبيوتر (وليس نمط تطبيقات الموبايل)
- يدعم عميل المستخدمين وعميل المسؤولين
- HarmonyOS ArkTS، دليل المصدر `apps/harmonyos/`

## هيكل المشروع

```
open-erp/
├── app/
│   ├── admin/controller/       # وحدات تحكم إدارة النظام (14 وحدة)
│   │   ├── BaseController.php      # وحدة التحكم الأساسية
│   │   ├── DashboardController.php # لوحة المعلومات + لوحات المبيعات/المخزون/المالية
│   │   ├── UserController.php      # مستخدمو CRUD + عمليات جماعية
│   │   ├── RoleController.php      # أدوار CRUD
│   │   ├── PermissionController.php# صلاحيات CRUD
│   │   ├── ConfigController.php    # إعدادات النظام CRUD
│   │   ├── LogController.php       # استعلام سجلات العمليات
│   │   ├── ProfileController.php   # المركز الشخصي + الخروج
│   │   ├── ExportController.php    # تصدير Excel/PDF
│   │   ├── ImportController.php    # استيراد مستخدمين عبر Excel
│   │   ├── UploadController.php    # رفع الملفات
│   │   ├── HealthController.php    # فحص الصحة
│   │   ├── DocsController.php      # وثائق OpenAPI
│   │   └── MetricsController.php   # مؤشرات مراقبة Prometheus
│   ├── api/v1/controller/      # واجهات عميل (تحكم إصدار الرأس)
│   │   ├── CaptchaController.php   # كابتشا النقر
│   │   ├── AuthController.php      # تسجيل الدخول/التسجيل/التحديث
│   │   └── ProductController.php   # استعلام المنتجات (بدون سعر الشراء)
│   ├── controller/              # وحدات تحكم الأعمال (104 وحدات، بما فيها InstallController)
│   │   ├── product/             # منتجات/تصنيفات/علامات تجارية/مستودعات/مواقع/موردون/عملاء (7)
│   │   ├── purchase/            # طلبات شراء/أوامر/استلام/إرجاع/تسوية (5)
│   │   ├── sales/               # عروض مبيعات/أوامر/شحن/إرجاع/تسوية (5)
│   │   ├── inventory/           # مخزون/سجلات/تحويل/جرد/تنبيهات (5)
│   │   ├── finance/             # مستحقات/سندات/مقبوضات ومدفوعات/دفتر يومي/دفتر عام/دفتر فرعي/ثلاثة بيانات/أصول ثابتة/ضرائب/عملات متعددة/ميزانيات/مراكز تكلفة وأرباح (20)
│   │   ├── crm/                 # فرص/متابعات/قمع/جهات اتصال/بركة عامة/عروض أسعار/عقود/تسويق/تذاكر/تحليل (10)
│   │   ├── workflow/            # تعريف سير العمل/تقديم الموافقة/موافقة/رفض/سحب (2)
│   │   ├── notification/        # قائمة الإشعارات/مقروء/عدد غير المقروء (1)
│   │   ├── project/             # مشاريع/مهام/سجلات ساعات العمل (3)
│   │   ├── hr/                  # أقسام/موظفون/مناصب/حضور/إجازات/رواتب (5)
│   │   ├── manufacturing/       # BOM/أوامر إنتاج/مسارات/محطات عمل/MRP (5)
│   │   ├── report/              # قوالب تقارير/مجموعات بيانات/تنفيذ/جدولة دورية (2)
│   │   ├── oms/                 # أوامر/تنفيذ/حجز مخزون/RMA/قنوات (4)
│   │   ├── wms/                 # مناطق ومواقع/استلام ASN/تخزين/موجات/انتقاء/تغليف (8)
│   │   ├── tms/                 # ناقلون/أسعار/شحنات/بوليصة/مسار (6)
│   │   ├── quality/             # IQC/IPQC/OQC/معايير فحص/غير مطابق (5)
│   │   ├── eam/                 # معدات/خطط صيانة/أوامر إصلاح/قطع غيار (4)
│   │   ├── dms/                 # تصنيفات مستندات/مستندات/إصدارات (2)
│   │   └── bi/                  # لوحات BI/مكونات رسوم بيانية (3)
│   ├── service/                 # طبقة منطق الأعمال (تسجيل الحاوية، 24 خدمة)
│   │   ├── finance/             # FinanceService: توليد تلقائي للمستحقات + تقييد المقبوضات والمدفوعات + دفتر اليومية
│   │   ├── inventory/           # InventoryService: إدخال وإخراج + احتساب تكلفة المتوسط المتحرك المرجح
│   │   ├── notification/        # NotificationService: إرسال الإشعارات
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # خدمات الأوامر/المستودعات/النقل/الجودة/الموارد البشرية/التصنيع
│   ├── common/                  # فئات أدوات عامة (تسجيل الحاوية، 4)
│   │   ├── HashidsService.php   # ترميز وفك ترميز المعرفات
│   │   ├── SnowflakeService.php # توليد معرفات Snowflake
│   │   ├── EncryptionService.php# تشفير وفك تشفير البيانات + الإخفاء
│   │   └── I18n.php             # الترجمة الدولية
│   ├── middleware/              # الوسائط (12)
│   │   ├── Locale.php           # كشف تلقائي للغة عبر Accept-Language
│   │   ├── Cors.php             # عبر النطاقات
│   │   ├── SecurityFilter.php   # اعتراض XSS/حقن SQL/اجتياز المسارات/حقن الأوامر/CSRF
│   │   ├── RateLimit.php        # نافذة منزلقة لتحديد المعدل عبر Redis
│   │   ├── ApiVersion.php       # التحقق من إصدار API
│   │   ├── AdminAuth.php        # مصادقة JWT + قائمة سوداء
│   │   ├── AdminPermission.php  # التحقق من صلاحيات RBAC
│   │   ├── OperationLog.php     # تسجيل تلقائي لسجلات العمليات
│   │   ├── TenantScope.php      # عزل تعدد المستأجرين (استدعاء ثابت)
│   │   ├── TracingId.php        # TraceId كامل المسار
│   │   ├── TrackingSignature.php# التحقق من توقيع الطلبات
│   │   └── StaticFile.php       # خدمة الملفات الثابتة (مدمجة في webman)
│   ├── model/                   # نماذج البيانات (161)
│   ├── queue/                   # مهام قوائم الانتظار
│   └── process/                 # العمليات (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter لجميع المنصات (ويب/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # صفحات الأعمال (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # تخطيطات متجاوبة
│   │       └── theme/          # سمة Material 3
│   └── harmonyos/              # عميل HarmonyOS
├── config/                     # ملفات التكوين
│   ├── route.php               # المسارات + استراتيجية إصدار API
│   ├── middleware.php           # تسجيل الوسائط العامة
│   ├── translation.php          # تكوين اللغات
│   └── plugin/hg/apidoc/        # تكوين وثائق API (25 وحدة إدارة + 3 وحدات عميل)
├── database/
│   ├── install.sql              # SQL التثبيت الكامل (163 جدولًا + بيانات البذرة، دُمجت جميع الهجرات)
│   ├── e2e-seed.sql             # بذر E2E/CI الأدنى
│   └── backup/                 # سكربتات النسخ الاحتياطي لقاعدة البيانات
│       ├── backup.sh           # mysqldump+gzip، احتفاظ 30 يومًا
│       └── restore.sh          # استعادة تفاعلية
├── docs/                       # الوثائق
│   ├── ARCHITECTURE.md         # مخططات Mermaid
│   ├── DESIGN.md               # وثيقة التصميم
│   ├── FEATURE_DESIGN.md       # وثيقة تصميم الوظائف
│   ├── SECURITY.md             # تصميم البنية الأمنية
│   ├── API.md                  # وثائق مرجعية API
│   ├── nginx-security.conf     # تكوين أمان Nginx المرجعي
│   ├── diagrams/               # مخططات البنية المفككة
│   └── superpowers/            # المواصفات والخطط
│       ├── specs/              # مواصفات التصميم
│       └── plans/              # خطط التنفيذ
├── public/                     # مدخل عام
├── runtime/                    # ملفات وقت التشغيل
├── tests/                      # الاختبارات
├── vendor/                     # تبعيات Composer
├── CLAUDE.md                   # هذا الملف
├── README.md                   # الشرح الصيني
├── README_EN.md                # الشرح الإنجليزي
├── .env                        # متغيرات البيئة (خارج التحكم بالنسخ)
├── .env.example                # قالب متغيرات البيئة
├── .env.docker                 # متغيرات بيئة Docker
├── composer.json               # تبعيات PHP
├── Dockerfile                  # بناء Docker (يشمل إضافات OPcache + event + redis)
├── docker-compose.yml          # تنسيق Docker
└── .github/
    └── workflows/
        └── ci.yml              # خط أنابيب CI/CD (قواعد PHP+PHPStan+CS Fixer+PHPUnit+composer audit، مصفوفة إصدارات متعددة)
```

## سلسلة تنفيذ الوسائط

```
عام:  Locale → Cors → SecurityFilter(فحص الطرق→405) → RateLimit → TracingId → {وسائط المسارات}
/health:  Locale → Cors → SecurityFilter(فحص الطرق→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(فحص الطرق→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(فحص الطرق→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(فحص الطرق→405) → RateLimit → TracingId → ApiVersion → Controller
```

## التحصين الأمني

- **تقييد طرق HTTP**: يسمح SecurityFilter فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، ويعيد 405 للطرق غير القياسية
- **رؤوس CSP**: حقن Content-Security-Policy + X-Permitted-Cross-Domain-Policies في جميع الاستجابات
- **قفل الحساب**: 5 محاولات تسجيل دخول فاشلة متتالية تقفل الحساب 15 دقيقة
- **تقييد الجلسات المتزامنة**: 3 رموز صالحة كحد أقصى لنفس المستخدم، ويُضاف الأقدم إلى القائمة السوداء عند التجاوز
- **security.txt**: نقطة نهاية `/.well-known/security.txt` وفق RFC 9116
- **تكوين أمان Nginx**: `nginx-security.conf` مرجع تحصين للوكيل العكسي

## استراتيجية إصدارات API

يتحكم الإصدار عبر رأس الطلب `API-Version` (الافتراضي `v1`)، ولا يظهر في عنوان URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

لإضافة إصدار جديد يكفي إنشاء دليل `app/api/{version}/controller/` وتسجيله في وسيط `ApiVersion`.

## استراتيجية تحديد المعدل

نافذة منزلقة عبر Redis (Lua ذري)، الافتراضي 60 مرة/دقيقة/IP/مسار:
- تسجيل الدخول: 10 مرات/دقيقة
- التسجيل: 5 مرات/دقيقة
- رؤوس الاستجابة: `X-RateLimit-Limit/Remaining/Reset`، مع `Retry-After` إضافي عند التجاوز

## معايير الكود

### PHP
- مراجع الدوال/الفئات العامة بدون `\` في البداية، تُستورد عبر `use`
- يجب أن تحتوي ملفات التكوين على تعليقات صينية تشرح معنى كل عنصر تكوين
- يجب أن يحتوي رأس كل ملف `.php` جديد على إعلان حقوق النشر

### قاعدة البيانات
- بادئة الجداول: `erp_`
- المفتاح الأساسي `id`: نوع BIGINT، غير تلقائي التزايد، يولَّد عبر snowflake
- الحقول الحساسة تشفَّر/فك تشفيرها تلقائيًا عبر trait `erikwang2013/encryptable`
- schema مصدره الوحيد database/install.sql (SQL في ملف واحد)

### Flutter
- تخطيط الويب بأسلوب لوحة إدارة الكمبيوتر (شريط جانبي + شريط علوي + منطقة محتوى)
- إدارة الحالة عبر GetX، `ApiService` نمط مفرد (Dio + معترض JWT)
- استمرار الرمز عبر `shared_preferences`
- نقاط التوقف المتجاوبة: موبايل (< 768px) وسطح مكتب (>= 768px)

### HarmonyOS
- استخدام عميل HTTP الأصلي `@ohos.net.http`
- تجديد الرمز دون إحساس: عند 401 يستدعي تلقائيًا `/api/auth/refresh`
- عند فشل التحديث يعيد التوجيه تلقائيًا إلى صفحة تسجيل الدخول

## النشر

### Docker Compose (موصى به للإنتاج)

`docker-compose.yml` في جذر المشروع ينسق 5 خدمات:

| الخدمة | الشرح |
|------|------|
| `nginx` | وكيل عكسي Nginx (80/443)، خدمة الملفات الثابتة |
| `app` | تطبيق webman PHP 8.3، مبني عبر `Dockerfile` (يشمل OPcache + event + redis) |
| `mysql` | MySQL 8.0، استمرار البيانات عبر وحدات تخزين |
| `redis` | Redis 7 Alpine، تخزين مؤقت/تحديد معدل/جلسات |
| `elasticsearch` | Elasticsearch 8.x، بحث نصي كامل |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` يحدد خط أنابيب GitHub Actions (مصفوفة PHP 8.2/8.3/8.4):

- فحص قواعد PHP (`php -l`)
- التحليل الساكن PHPStan (`vendor/bin/phpstan analyse`)
- فحص أسلوب الكود PHP CS Fixer (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- اختبارات PHPUnit الوحدوية
- تدقيق أمان Composer (`composer audit --no-dev`)

### النسخ الاحتياطي لقاعدة البيانات

`database/backup/backup.sh` — mysqldump + gzip، تنظيف تلقائي للنسخ الاحتياطية الأقدم من 30 يومًا.
`database/backup/restore.sh` — استعادة تفاعلية، يسرد النسخ الاحتياطية المتاحة للاختيار.

### المراقبة

يُخرج نقطة نهاية `GET /metrics` (`MetricsController`) تنسيق نص Prometheus، ويشمل 5 مقاييس gauge:
- `openadmin_http_requests_total` — إجمالي الطلبات
- `openadmin_active_users` — عدد المستخدمين النشطين
- `openadmin_db_connection_status` — حالة اتصال قاعدة البيانات (0/1)
- `openadmin_redis_connection_status` — حالة اتصال Redis (0/1)
- `openadmin_memory_usage_bytes` — استخدام الذاكرة
