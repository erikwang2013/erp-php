# تقرير التدقيق — 2026-08-07

**المشروع**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**النطاق**: تشغيل الاختبارات الكلي، الفحص العميق، إصلاح مشكلات P0/P1
**التعليمات**: «اختبر النظام بالكامل، شغّله، وافحص بعمق لترى إن كانت هناك مشكلات أو مواضع تحسين؟»
**نتيجة الاختبارات**: OK (135 اختبارًا، 799 تأكيدًا) — جميعها ناجحة

---

## 1. نتائج الاختبارات والتحقق من التشغيل

| البند | النتيجة |
|---|---|
| مجموعة PHPUnit الكاملة | 135 اختبارًا / 799 تأكيدًا جميعها ناجحة |
| بدء الخدمة (المنفذ 8787→مؤقت 8791) | بدء طبيعي، دون انهيار عمليات worker |
| فحص الصحة /health | code=0، حقول database/redis/elasticsearch مكتملة |
| سلسلة تحديد المعدل | الطلبات المتتالية لـ /api/auth/login ترجع 429 |
| القائمة السوداء JWT / قفل تسجيل الدخول | تعمل بشكل طبيعي (بعد إصلاح Redis) |
| CS-Fixer | إصلاح مخالفات تنسيق 31 ملفًا |
| PHPStan | استعادة التشغيل بعد إصلاح تلف التخزين المؤقت (851 بلاغًا خاطئًا عن طرق ORM السحرية، 75 عنصر خط أساس منتهي الصلاحية) |

---

## 2. إصلاحات P0 (أعطال وقت التشغيل — جميعها أُصلحت وتحققت)

### 2.1 غياب فئة support\Redis — تعطل آليات الأمان بصمت

- **الظاهرة**: `support\Redis` غير موجودة (لم يُدخل composer.json webman/redis قط)، و9 ملفات تشير إليها.
- **السبب الجذري**: صامتت تصميمات `catch (\Throwable)` fail-open في مواضع متعددة أخطاء غياب الفئة، ما جعل تحديد المعدل والقائمة السوداء JWT وقفل تسجيل الدخول والحظر كلها معطلة بصمت، و«تبدو الواجهة طبيعية» لكن بلا أي حماية.
- **الإصلاح**: `composer require webman/redis`؛ ترقية `config/redis.php` إلى متغيرات بيئة (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **التحقق**: /health يرجع `redis: ok`؛ اختبار تحديد المعدل يرجع 429.

### 2.2 فشل ترجمة وسيط ApiVersion — جميع مسارات /api ترجع 500

- **الظاهرة**: `Interface "app\middleware\MiddlewareInterface" not found` — نقص `use Webman\MiddlewareInterface;`.
- **الخطأ الثاني بعد الإصلاح**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` فئة فرعية من `Webman\Http\Request`، ما يخالف عقد انعكاس المعاملات.
- **الإصلاح**: التبديل إلى استيراد `Webman\Http\Request` / `Webman\Http\Response`.

### 2.3 انعكاس معاملات وسيط AdminAuth — انهيار worker لمسارات /admin

- **الظاهرة**: /admin/dashboard يثير Empty reply في worker (انهيار الترجمة).
- **السبب الجذري**: مشكلة انعكاس المعاملات نفسها كما في 2.2.
- **الإصلاح**: التبديل إلى `Webman\Http\Request` / `Webman\Http\Response` (مع الإبقاء على `support\Redis`).
- **التحقق**: إرجاع 401 JSON.

### 2.4 عدم وجود الدالة المساعدة validator() — تسجيل الدخول يرجع 500

- **الظاهرة**: `Call to undefined function validator()`، مع 105 استدعاءً في 99 ملفًا.
- **الإصلاح**: `composer require illuminate/validation`؛ تنفيذ الدالة المساعدة في `app/functions.php` (مع تخزين $factory ثابت).
- **عقبة**: المعامل الأول في `Factory::__construct()` يجب أن يكون `Translator` وليس `ArrayLoader`.
- **متبقٍ (P2)**: رسائل الأخطاء غير مترجمة (تعرض `validation.required` بدل الصينية)، يتطلب إضافة حزمة لغة zh_CN.

### 2.5 CORS مكتوب ثابتًا + استجابات ما قبل الفحص تفقد رؤوس CORS

- **الإصلاح**: إضافة `app/common/CorsPolicy.php`، يقرأ القائمة البيضاء من متغير البيئة `CORS_ALLOWED_ORIGIN` (مفصولة بفواصل)، مع إعادة صدى origin؛ وعدم إرسال رؤوس CORS عند عدم التطابق.
- **النقطة المفتاحية**: `Route::fallback` لا يمر بسلسلة الوسائط العامة، لذا يجب أن يضيف استجابة OPTIONS لما قبل الفحص رؤوس CORS بنفسها — عولج ذلك في إغلاق fallback.
- **الرؤوس الأمنية**: إزالة X-XSS-Protection المهجور؛ إضافة `connect-src 'self'` إلى CSP.

### 2.6 FastRoute BadRouteException — تظليل المسارات

- **الظاهرة**: `Static route "/install" is shadowed by previously defined variable route`.
- **السبب الجذري**: مسار البدل OPTIONS `/{path:.+}` يظلل المسارات الثابتة اللاحقة؛ مسارات الإضافات (apidoc) تُحمَّل بعد config/route.php.
- **الإصلاح**: إزالة مسار البدل، والتبديل إلى `Route::fallback` (يجب وضعه في نهاية ملف المسارات)؛ تحويل `/crm/pool/rules` من resource إلى مسار GET صريح، وجعل `PoolController::rules()` عامًا.

---

## 3. إصلاحات P1 (جودة المشروع)

- **3.1 تلف تخزين PHPStan المؤقت**: /tmp/phpstan/cache من دليل service/ المحذوف (بقايا تقسيم الخدمات المصغرة)، يحوي مسارات مطلقة قديمة تسبب أخطاء phar وتعليقًا بـ CPU 0%. بعد مسح التخزين المؤقت وإعادة التثبيت عاد للعمل. الأخطاء الـ 851 بلاغات خاطئة عن طرق ORM السحرية في webman؛ و75 عنصر خط أساس تشير إلى دليل service/ غير الموجود (P2).
- **3.2 CS-Fixer**: إصلاح مخالفات المسافات/ترتيب use في 31 ملفًا.
- **3.3 مزامنة الاختبارات**: تحديث `test_cors_response_is_assigned_correctly` لتأكيد التنفيذ الجديد (withHeaders + CorsPolicy).

---

## 4. الأسباب الجذرية الفائتة في تدقيق الجولة السابقة (08-04)

- الاختبارات لم تغطِ **قابلية تحميل فئات الوسائط** و**قابلية استدعاء المسارات** (class_exists / is_subclass_of لا تلتقط نقص use وانعكاس المعاملات).
- إصلاحات CORS/X-XSS المزعومة في الإرسالية b1fe2de لا تطابق الكود الفعلي — اعتمدت خلاصة التدقيق على معلومات الإرساليات أكثر من التحقق بالتشغيل.

---

## 5. قائمة تغييرات هذه الجولة (git status: 41 تعديلًا + إضافتان)

| الملف | التغيير |
|---|---|
| app/middleware/ApiVersion.php | إضافة use Webman\MiddlewareInterface؛ تغيير أنواع المعاملات إلى Webman\Http |
| app/middleware/AdminAuth.php | تغيير أنواع المعاملات إلى Webman\Http |
| app/middleware/Cors.php | إعادة البناء لاستخدام CorsPolicy؛ تحديث CSP/الرؤوس الأمنية |
| app/common/CorsPolicy.php | **جديد**: سياسة القائمة البيضاء CORS |
| config/route.php | مسار fallback + تصحيح /crm/pool/rules |
| app/controller/crm/PoolController.php | جعل rules() عامًا |
| app/functions.php | إضافة الدالة المساعدة validator() |
| config/redis.php | **جديد** (ترقية إلى متغيرات بيئة بعد توليد composer) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | مزامنة تأكيدات CORS |
| نحو 30 ملفًا آخر | إصلاحات تنسيق CS-Fixer |

---

## 6. اقتراحات P2 (بيئة/مهام معلقة، غير مُصلحة)

1. **DB_PASSWORD فارغ في .env** — فشل مصادقة جذر MySQL، `database: unavailable`؛ يتطلب تكوين كلمة مرور حقيقية.
2. **تعارض المنفذ 8787** — يحتله cloud-php/service (مشروع مختلف)؛ يتطلب التمييز عند النشر في الإنتاج.
3. **رسائل validator الصينية** — يتطلب تثبيت حزمة لغة أو رسائل مخصصة.
4. **إعادة بناء خط أساس PHPStan** — 75 مسارًا تشير إلى دليل service/ المحذوف، يُنصح بتنظيفه وإعادة بنائه.
5. **تدقيق fail-open** — يُنصح بالفحص الشامل لمواضع ابتلاع الأخطاء بصمت في `catch (\Throwable)` (اكتُشف موضع واحد بعواقب وخيمة هذه المرة)، مع التحويل إلى fail-closed أو تسجيل صريح.

---

*توليد التقرير: 2026-08-07، توقفت الخدمة، وأعيد المنفذ إلى 8787.*
