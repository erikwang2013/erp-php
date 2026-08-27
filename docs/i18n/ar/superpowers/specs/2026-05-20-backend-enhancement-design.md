# المشروع الفرعي A: تعزيز الخلفية — مواصفات التصميم

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## النطاق

هذا تعزيز للخلفية، بإجمالي 15 نقطة وظيفية، تشمل 9 ملفات جديدة + 4 ملفات معدَّلة.

---

## قائمة الملفات الجديدة/المعدَّلة

```
app/middleware/
├── OperationLog.php          # جديد: تسجيل تلقائي لسجلات العمليات
├── Cors.php                  # جديد: عبر النطاقات
└── RateLimit.php             # جديد: تحديد معدل عبر Redis
app/admin/controller/
├── ConfigController.php      # جديد: CRUD لإعدادات النظام
├── LogController.php         # جديد: استعلام سجلات العمليات
├── ProfileController.php     # جديد: المركز الشخصي (يشمل الخروج)
├── UploadController.php      # جديد: رفع الملفات
├── ImportController.php      # جديد: استيراد مستخدمين عبر Excel
└── HealthController.php      # جديد: فحص الصحة
app/model/
├── AdminUser.php             # معدَّل: إضافة SoftDeletes + trait Searchable
└── OperationLog.php          # معدَّل: إضافة public $timestamps = false
app/middleware/
└── AdminAuth.php             # معدَّل: التحقق من القائمة السوداء JWT
app/admin/controller/
├── DashboardController.php   # معدَّل: إحصائيات فورية من قاعدة البيانات
└── UserController.php        # معدَّل: إضافة إجراءات المعالجة الدفعية
config/
└── route.php                 # معدَّل: إضافة مسارات + وسائط
```

---

## 1. الوسائط

### 1.1 وسيط CORS

**الملف**: `app/middleware/Cors.php`

- طلبات OPTIONS للفحص المسبق تُرجع 204 مباشرة
- الطلبات غير الفاحصة تُلحق برأس الاستجابة `Access-Control-Allow-Origin: *`
- الرؤوس المسموحة: `Authorization, Content-Type, API-Version`
- أقصى تخزين مؤقت: 86400 ثانية

التركيب: وسيط عام (`config/middleware.php`)

### 1.2 وسيط تحديد المعدل

**الملف**: `app/middleware/RateLimit.php`

- التخزين: نافذة منزلقة Redis Sorted Set
- الافتراضي: 60 مرة/دقيقة/IP/مسار
- الواجهات الحساسة:
  - `/api/auth/login`: 10 مرات/دقيقة
  - `/api/auth/register`: 5 مرات/دقيقة
- عند التجاوز يُرجع `429 Too Many Requests`

التركيب: وسيط عام (`config/middleware.php`)، بعد Cors وقبل ApiVersion

### 1.3 وسيط سجلات العمليات

**الملف**: `app/middleware/OperationLog.php`

- يسجل فقط POST/PUT/DELETE
- الحقول المسجلة: user_id, action, method, path, ip, input(JSON)
- يُكتب بشكل غير متزامن بعد إرجاع الاستجابة (لا يحجب)

التركيب: مجموعة مسارات `/admin`، بعد AdminPermission

### 1.4 سلسلة تنفيذ الوسائط العامة

```
جميع الطلبات:
  Cors → RateLimit → ApiVersion → {وسائط المسارات} → Controller

طلبات /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 الخروج (القائمة السوداء JWT)

**الملف**: `app/middleware/AdminAuth.php` (معدَّل)

**المبدأ**: JWT بلا حالة بطبيعته؛ عند الخروج يُضاف الرمز إلى القائمة السوداء في Redis، ويفحص AdminAuth القائمة السوداء أولًا عند التحقق.

**تعديل AdminAuth**:
- إضافة في بداية `process()`: فحص ما إذا كان الرمز الحالي في القائمة السوداء لمجموعة `jwt_blacklist` في Redis
- عند الإصابة يُرجع 401

**مسار الخروج** (تحت المركز الشخصي):

| الطريقة | المسار | الوصف |
|------|------|------|
| `POST` | `/admin/profile/logout` | إضافة رمز Bearer الحالي إلى القائمة السوداء في Redis، TTL=المدة المتبقية لصلاحية الرمز |

**منطق الخروج**:
```php
// تحليل المدة المتبقية لصلاحية الرمز
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// إضافة إلى القائمة السوداء
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. وحدات التحكم الجديدة وتعديلات القائمة

### 2.1 CRUD لإعدادات النظام (`ConfigController`)

يرث `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `index()` | GET `/admin/config` | قائمة مقسمة صفحات، قابلة للتصفية حسب `group`، ترقيم صفحات `page`/`limit` |
| `store()` | POST `/admin/config` | إنشاء عنصر إعداد، إلزامي: group, key, value |
| `update()` | PUT `/admin/config/{id}` | تحديث value/type/description لعنصر الإعداد |
| `destroy()` | DELETE `/admin/config/{id}` | حذف عنصر الإعداد، يتطلب `confirmPassword()` |

### 2.2 استعلام سجلات العمليات (`LogController`)

يرث `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `index()` | GET `/admin/log` | قائمة مقسمة صفحات، تدعم التصفية: user_id, action, path, created_at(نطاق) |

لا يوفر إنشاء/تعديل/حذف؛ السجلات تُسجل تلقائيًا بواسطة الوسيط.

### 2.3 المركز الشخصي (`ProfileController`)

يرث `BaseController`. يتعامل مع المستخدم المسجل حاليًا (`$request->adminId`).

| الطريقة | المسار | الوصف |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | تحديث real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | تغيير كلمة المرور، يتطلب old_password, new_password, new_password_confirmation |

### 2.4 رفع الملفات (`UploadController`)

يرث `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `upload()` | POST `/admin/upload` | استقبال الملف، يدعم image/jpeg/png/gif/pdf/xlsx/docx |

- الحد الأقصى 10MB
- مسار التخزين: `public/upload/{date}/{hash}.{ext}`
- الإرجاع: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 بيانات لوحة المعلومات الفعلية

**الملف**: `app/admin/controller/DashboardController.php` (معدَّل)

تحويل البيانات المزيفة المرمزة حاليًا إلى إحصائيات فورية من قاعدة البيانات:

| المؤشر | المصدر | الوصف |
|------|------|------|
| إجمالي المستخدمين | `AdminUser::count()` | بدون الحذف الناعم |
| الجدد اليوم | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| إجمالي الأدوار | `AdminRole::count()` | |
| إجمالي الصلاحيات | `AdminPermission::count()` | |
| بيانات الاتجاه | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | إحصاء يومي للجدد خلال آخر 7 أيام |
| بيانات التوزيع | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | التوزيع حسب الحالة |
| آخر العمليات | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | آخر 10 سجلات عمليات |

### 2.6 العمليات الدفعية للمستخدمين

**الملف**: `app/admin/controller/UserController.php` (معدَّل، إضافة طرق)

| الطريقة | المسار | الوصف |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | حذف جماعي، جسم الطلب `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | تمكين/تعطيل جماعي، جسم الطلب `{ ids: [hashid, ...], status: 1|0 }` |

- كل معرّف يُحوَّل أولًا عبر `decodeId()` إلى BIGINT
- يجب أن يجتاز `batchDestroy()` التحقق من `confirmPassword()`

### 2.7 استيراد البيانات

**الملف**: `app/admin/controller/ImportController.php` (جديد)

| الطريقة | المسار | الوصف |
|------|------|------|
| `users()` | POST `/admin/import/users` | رفع ملف Excel، إنشاء مستخدمين جماعيًا |

التدفق:
1. استقبال ملف `.xlsx`
2. تحليل عبر PhpSpreadsheet، الأعمدة المتوقعة: `username, password, real_name, phone, email, status`
3. التحقق من كل صف + الإنشاء (توليد ID عبر snowflake، كلمات مرور bcrypt، تشفير phone/email عبر encryption)
4. إرجاع النتيجة: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 فحص الصحة

**الملف**: `app/admin/controller/HealthController.php` (جديد)

`GET /health` (بدون مصادقة، لا يُحتسب في سجلات العمليات):

إرجاع حالة اتصال كل مكون:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- عند فشل كشف مكون تكون قيمة الحقل المقابل سلسلة وصف الخطأ
- المسار لا يحمل بادئة `/admin`، يُسجل منفصلًا في النطاق العام

---

## 3. تصحيحات النماذج

### 3.1 الطوابع الزمنية لـ OperationLog

**الملف**: `app/model/OperationLog.php` (معدَّل)

جدول `erik_operation_log` يحوي عمود `created_at` فقط (بدون `updated_at`). يحاول `save()` الافتراضي في Eloquent كتابة `updated_at`، ما يسبب خطأ SQL.

الإصلاح: `public $timestamps = false;` + تحديد `created_at` يدويًا عند الكتابة.

### 3.2 تعديل نموذج AdminUser

- إضافة trait `Searchable`
- تنفيذ `toSearchableArray()`: إرجاع username, real_name
- عند كشف `UserController::index()` كلمة مفتاحية يستخدم `AdminUser::search($kw)->get()` بدل MySQL LIKE

يجب إنشاء فهرس ES أولًا، عبر أوامر Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. تغييرات المسارات

مسارات جديدة في `config/route.php`:

```php
// إضافة داخل مجموعة مسارات /admin:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// فحص الصحة (مسار عام، خارج مجموعة /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// الوسائط:
إلحاق app\middleware\OperationLog::class بوسائط مجموعة /admin
```

تسجيل الوسائط العامة في `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. استكمال رموز الخطأ

| code | المعنى | سيناريو الإثارة |
|------|------|---------|
| 429 | الطلبات كثيرة جدًا | إثارة RateLimit |

---

## 6. خارج نطاق هذه الجولة

- نظام الإشعارات (يتطلب قوائم انتظار رسائل + بنية دفع للواجهة)
- صفحات Flutter للواجهة (المشروع الفرعي B)
- تحديث رمز HarmonyOS (المشروع الفرعي C)
