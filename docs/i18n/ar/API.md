# وثيقة مرجع API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## وثائق API

يستخدم المشروع [hg/apidoc](https://github.com/hg-code/apidoc) لتوليد وثائق API تفاعلية تلقائيًا.

**طريقة الوصول:** بعد بدء الخدمة افتح `http://localhost:8788/apidoc`

**مجموعات الوثائق:**
| المجموعة | الوصف | عدد الوحدات |
|------|------|--------|
| واجهات الإدارة (Admin) | جميع واجهات نظام الإدارة الخلفي | 25 وحدة |
| واجهات العميل (Service API) | واجهات خفيفة يطلقها الجوال/الويب | 3 وحدات |

**الرؤوس العامة:**
| الرأس | الوصف |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | رقم إصدار API (v1) |
| `Accept-Language` | لغة التدويل (zh-CN/en) |

**معيار التعليقات التوضيحية:** تستخدم جميع طرق وحدات التحكم تعليقات `@Apidoc\*` لتوضيح اسم الواجهة ووصفها وعنوان URL وطريقة الطلب والمعاملات وهيكل القيمة المرجعة.

## 1. نظرة عامة

نظام إدارة الخلفية المفتوح (open-admin) مبني على webman v2 ويوفر RESTful JSON API. تتطلب جميع واجهات الإدارة مصادقة JWT وتحقق صلاحيات RBAC، بينما تُوجَّه الواجهات العامة عبر رأس إصدار API إلى وحدات التحكم المصنفة بالإصدار.

- **عنوان URL الأساسي**: `http://localhost:8788`
- **إصدار API**: يُتحكم فيه عبر رأس الطلب `API-Version: v1` (عند غيابه يكون v1 افتراضيًا)

> **نظرة عامة على النقاط**: المصادقة(5) | لوحة المعلومات(1) | المستخدمون(7) | الأدوار(4) | الصلاحيات(4) | الإعدادات(4) | السجلات(1) | الملف الشخصي(3) | الاستيراد والتصدير(3) | الرفع(1) | التشغيل(4: health/metrics/docs/security.txt) | المجموع 37 نقطة
- **المصادقة**: `Authorization: Bearer <token>` (JWT)
- **تنسيق الاستجابة**: `{ "code": 0, "message": "success", "data": {...} }`
- **نقطة الوثائق**: `GET /api/docs` تُرجع مواصفة OpenAPI 3.0 JSON

### التدويل

يبدّل API اللغة تلقائيًا عبر رأس الطلب `Accept-Language`:

| قيمة الرأس | اللغة |
|---------|------|
| `zh-CN`, `zh` | الصينية (الافتراضية) |
| `en`, `en-US` | English |

```bash
# 英文响应
curl -H "Accept-Language: en" http://localhost:8788/admin/product

# 中文响应（默认）
curl http://localhost:8788/admin/product
```

يُرجع حقل `message` في الاستجابة باللغة المقابلة.

### متطلبات الطلب

- يُسمح فقط بطرق `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`، واستخدام طرق HTTP أخرى (مثل TRACE وCONNECT وPATCH) يُرجع 405
- يجب أن تحدد جميع طلبات `POST` / `PUT` رأس `Content-Type: application/json` (باستثناء رفع الملفات)، وإلا تُرجع 415
- يجب ألا يتجاوز حجم جسم الطلب 10MB، وإلا تُرجع 413
- يفحص فلتر الأمان جميع مدخلات الطلب ضد XSS وحقن SQL واجتياز المسار وحقن الأوامر، وعند الإصابة تُرجع 403
- 5 محاولات تسجيل دخول فاشلة متتالية تُفعّل قفل الحساب (15 دقيقة)، وخلال فترة القفل تُرجع طلبات تسجيل الدخول 429
- يملك المستخدم نفسه حتى 3 رموز صالحة في وقت واحد كحد أقصى، وعند التجاوز يُضاف أقدم رمز تلقائيًا إلى القائمة السوداء

## 2. أكواد الأخطاء

| code | المعنى | سيناريو التفعيل |
|------|------|---------|
| 0 | نجاح | |
| 400 | خطأ في معاملات الطلب | تنسيق الطلب غير صحيح |
| 401 | غير مصادق | الرمز مفقود / منتهي / موجود في القائمة السوداء |
| 403 | لا صلاحية / اعتراض أمني | صلاحيات RBAC غير كافية / إصابة SecurityFilter |
| 404 | المورد غير موجود | الهدف غير موجود في الاستعلام/التحديث/الحذف |
| 405 | طريقة الطلب غير مسموحة | يُسمح فقط GET/POST/PUT/DELETE/OPTIONS/HEAD، والطرق غير القياسية تُرفض مباشرة |
| 413 | جسم الطلب كبير جدًا | Content-Length يتجاوز 10MB |
| 415 | نوع الوسائط غير مدعوم | Content-Type في طلبات POST/PUT ليس JSON وليس رفع ملفات |
| 422 | فشل التحقق من المعاملات | حقل إلزامي مفقود، تنسيق غير مطابق، أو فشل تحقق الأعمال |
| 429 | الطلبات كثيرة جدًا | تفعيل RateLimit / قفل الحساب (5 محاولات فاشلة تقفل 15 دقيقة) |
| 500 | خطأ داخلي في الخادم | |

## 3. النقاط العامة

تُركَّب جميع النقاط العامة ضمن مجموعة `/api`، وتُوزَّع عبر وسيط `ApiVersion` حسب رأس `API-Version` إلى وحدات التحكم المصنفة بالإصدار (مثل `app\api\v1\controller\AuthController`).

### 3.1 فحص الصحة

```
GET /health
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: لا يوجد

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

قيم `database` و`redis` و`elasticsearch`: `"ok"` | `"unavailable"`. تُرجع `elasticsearch` القيمة `"unavailable"` عند عدم إمكانية الوصول إلى ES، وتُرجع قيمة status الفعلية (مثل `"red"`) عندما لا تكون حالة صحة الكتلة green/yellow.

### 3.2 وثائق API

```
GET /api/docs
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)
- **الاستجابة**: مواصفة OpenAPI 3.0.3 JSON، تحتوي على جميع تعريفات النقاط والمعاملات والـ Schemas

### 3.3 توليد كابتشا النقر

```
POST /api/captcha/generate
```

- **المصادقة**: غير مطلوبة
- **رأس الطلب**: `API-Version: v1` (إلزامي)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "difficulty": "medium"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| difficulty | string | لا | `easy` / `medium` / `hard`، الافتراضي `medium` |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| key | string | معرّف الكابتشا، يُعاد إرساله عند التحقق |
| image | string | صورة PNG بترميز base64 |
| extra.targets[].order | int | ترتيب النقر |
| extra.targets[].text | string | نص إرشاد هدف النقر |

### 3.4 التحقق من كابتشا النقر

```
POST /api/captcha/verify
```

- **المصادقة**: غير مطلوبة
- **رأس الطلب**: `API-Version: v1` (إلزامي)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| key | string | نعم | مفتاح الكابتشا، يُرجع من generate |
| clicks | array{object} | نعم | مصفوفة إحداثيات النقر، كل عنصر يحتوي `x` (int) و`y` (int) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

عند فشل التحقق يكون `code` هو 422 و`message` هو `"验证失败，请重试"` و`data.valid` هو `false`.

### 3.5 تسجيل الدخول

```
POST /api/auth/login
```

- **المصادقة**: غير مطلوبة
- **رأس الطلب**: `API-Version: v1` (إلزامي)
- **تحديد المعدل**: 10 مرات/دقيقة (حسب IP + المسار)

**جسم الطلب**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم |
| password | string | نعم | min:6, max:32 | كلمة المرور |
| captcha_key | string | نعم | | مفتاح الكابتشا |
| clicks | array{object} | نعم | min:2 | مصفوفة إحداثيات النقر |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| access_token | string | رمز وصول JWT |
| refresh_token | string | رمز تحديث JWT |
| expires_in | int | مدة صلاحية رمز الوصول (بالثواني)، الافتراضي 7200 |
| user.id | string | معرف المستخدم المشفر بـ hashid |
| user.username | string | اسم المستخدم |
| user.real_name | string | الاسم الحقيقي |

**الأخطاء المحتملة**:
- 422: فشل التحقق من المعاملات (حقل إلزامي مفقود، تنسيق غير مطابق)
- 422: خطأ في الكابتشا، يرجى إعادة المحاولة
- 401: اسم المستخدم أو كلمة المرور خاطئة
- 403: الحساب معطل
- 429: الحساب مقفول، يرجى المحاولة بعد 15 دقيقة (يُفعَّل بعد 5 محاولات فاشلة متتالية)

### 3.6 التسجيل

```
POST /api/auth/register
```

- **المصادقة**: غير مطلوبة
- **رأس الطلب**: `API-Version: v1` (إلزامي)
- **تحديد المعدل**: 5 مرات/دقيقة (حسب IP + المسار)
- **المفتاح**: مغلق افتراضيًا (`REGISTRATION_ENABLED=0`)، وعند الإغلاق تُرجع 403؛ يجب تفعيله صراحة في `.env` (`REGISTRATION_ENABLED=1`)

**جسم الطلب**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 | كلمة المرور (تخزين كـ bcrypt hash) |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| captcha_key | string | نعم | | مفتاح الكابتشا |
| clicks | array{object} | نعم | min:2 | مصفوفة إحداثيات النقر |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

بعد نجاح التسجيل يُرجع رمز JWT مباشرة، وحالة المستخدم مفعّلة افتراضيًا (status=1). النقطة متاحة فقط عندما يكون `REGISTRATION_ENABLED=1`.

### 3.7 تحديث الرمز

```
POST /api/auth/refresh
```

- **المصادقة**: غير مطلوبة
- **رأس الطلب**: `API-Version: v1` (إلزامي)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| refresh_token | string | نعم | refresh_token المُحصَّل عند تسجيل الدخول/التسجيل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

عند نجاح التحديث يُرجع access_token وrefresh_token جديدين معًا، ويُبطل الرمز القديم تلقائيًا. ويُحدَّث أثناء التحديث وقت آخر تسجيل دخول وعنوان IP للمستخدم.

**الأخطاء المحتملة**:
- 422: رمز التحديث مفقود
- 401: رمز التحديث غير صالح أو منتهي

### 3.8 مؤشرات مراقبة Prometheus

```
GET /metrics
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: لا يوجد
- **تنسيق الاستجابة**: Prometheus text format (`text/plain; version=0.0.4`)

نقطة عامة لمؤشرات مراقبة Prometheus، لتُلتقط بواسطة Grafana/Prometheus.

**مثال على الاستجابة**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| اسم المؤشر | النوع | الوصف |
|------|------|------|
| `openadmin_http_requests_total` | gauge | إجمالي عدد طلبات HTTP المتراكمة |
| `openadmin_active_users` | gauge | عدد المستخدمين النشطين حاليًا (سجلوا خلال 24 ساعة) |
| `openadmin_db_connection_status` | gauge | حالة اتصال قاعدة البيانات، 1=طبيعي, 0=غير طبيعي |
| `openadmin_redis_connection_status` | gauge | حالة اتصال Redis، 1=طبيعي, 0=غير طبيعي |
| `openadmin_memory_usage_bytes` | gauge | استخدام الذاكرة الحالي لعملية PHP (بالبايت) |

## 4. لوحة المعلومات

تُركَّب جميع واجهات الإدارة ضمن مجموعة `/admin`، وتمر عبر ثلاثة وسائط: `AdminAuth` (مصادقة JWT) و`AdminPermission` (تحقق صلاحيات RBAC) و`OperationLog` (تسجيل العمليات).

### 4.1 بيانات لوحة المعلومات

```
GET /admin/dashboard
```

- **المصادقة**: JWT + RBAC
- **التخزين المؤقت**: Redis 5 دقائق

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| حقول stats | النوع | الوصف |
|------|------|------|
| label | string | اسم المؤشر |
| value | string | قيمة المؤشر (من نوع السلسلة) |
| icon | string | اسم أيقونة Material |
| color | string | قيمة لون البطاقة |
| trend | float? | معدل النمو اليومي (بالنسبة المئوية)، موجود فقط في "إجمالي المستخدمين" |

| حقول trends | النوع | الوصف |
|------|------|------|
| dates | array{string} | تسلسل تواريخ آخر 30 يومًا |
| series | array{object} | بيانات خطوط الاتجاه، كل خط يحتوي name (الاسم) وdata (مصفوفة القيم) وcolor (اللون) |

## 5. إدارة المستخدمين

جميع المعرفات `id` التي تُرجعها واجهات إدارة المستخدمين عبارة عن سلاسل مشفرة بـ hashid. حقل كلمة المرور مستبعد من الاستجابات. تظهر أرقام الهواتف والبريد الإلكتروني بإخفاء في واجهات القائمة، وتُرجع بنص واضح في واجهات التفاصيل (حقول قاعدة البيانات المشفرة تُفك تلقائيًا عبر trait Encryptable).

### 5.1 قائمة المستخدمين

```
GET /admin/user
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر في الصفحة |
| keyword | string | لا | | كلمة البحث، تطابق اسم المستخدم والاسم الحقيقي |
| status | int | لا | | فلتر الحالة، 0=معطل، 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرف المستخدم المشفر بـ hashid |
| username | string | اسم المستخدم |
| real_name | string | الاسم الحقيقي |
| phone | string | رقم الهاتف بإخفاء (بصيغة `138****5678`) |
| email | string | البريد الإلكتروني بإخفاء (بصيغة `a***@example.com`) |
| status | int | 1=مفعّل, 0=معطل |
| last_login_at | string | وقت آخر تسجيل دخول (datetime) |
| created_at | string | وقت الإنشاء (datetime) |

### 5.2 إنشاء مستخدم

```
POST /admin/user
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 | كلمة المرور (تخزين bcrypt) |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| phone | string | لا | | رقم الهاتف (تخزين مشفر عبر Encryptable) |
| email | string | لا | | البريد الإلكتروني (تخزين مشفر عبر Encryptable) |
| status | int | لا | in:0,1 | الحالة، الافتراضي 1 (مفعّل) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**الأخطاء المحتملة**:
- 422: اسم المستخدم موجود بالفعل
- 422: فشل التحقق من المعاملات (حقل إلزامي مفقود)

### 5.3 تفاصيل المستخدم

```
GET /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفر بـ hashid

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

في واجهة التفاصيل يُرجع `phone` و`email` بنص واضح (في قاعدة البيانات تخزين مشفر، ويفك cast Encryptable تلقائيًا)، دون إخفاء. `password` و`id_card` لا يظهران أبدًا في الاستجابة.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.4 تحديث المستخدم

```
PUT /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفر بـ hashid

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي، عند عدم الإرسال تُحفظ القيمة الأصلية |
| password | string | لا | كلمة المرور الجديدة، عند كونها سلسلة فارغة أو عدم الإرسال لا تتغير |
| phone | string | لا | رقم الهاتف |
| email | string | لا | البريد الإلكتروني |
| status | int | لا | 0=معطل, 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.5 حذف مستخدم

```
DELETE /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفر بـ hashid
- **عملية حساسة**: تتطلب تأكيدًا ثانويًا بكلمة المرور

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| password | string | نعم | كلمة مرور المستخدم المسجل حاليًا (تأكيد ثانوي) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ينفَّذ حذف ناعم (Eloquent SoftDeletes)، وتُعلَّم البيانات بـ deleted_at دون حذف فيزيائي.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود
- 422: تتطلب العملية الحساسة إدخال كلمة المرور للتأكيد (password فارغ)
- 422: فشل التحقق من كلمة المرور (كلمة المرور غير متطابقة)

### 5.6 حذف مستخدمين جماعيًا

```
POST /admin/user/batch/destroy
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيدًا ثانويًا بكلمة المرور

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرفات المستخدمين المشفرة بـ hashid |
| password | string | نعم | كلمة مرور المستخدم المسجل حاليًا (تأكيد ثانوي) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

يُنفَّذ حذف ناعم، و`data.count` هو عدد الحذف الفعلي.

**الأخطاء المحتملة**:
- 422: يرجى تحديد المستخدمين المطلوب حذفهم (ids فارغ)
- 422: معرف غير صالح (فشل فك تشفير hashid)
- 422: فشل التحقق من كلمة المرور

### 5.7 تفعيل/تعطيل مستخدمين جماعيًا

```
POST /admin/user/batch/status
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرفات المستخدمين المشفرة بـ hashid |
| status | int | نعم | 0=معطل, 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

تتغير message ديناميكيًا حسب قيمة status إلى `"批量启用成功"` أو `"批量禁用成功"`.

**الأخطاء المحتملة**:
- 422: يرجى تحديد المستخدمين (ids فارغ)
- 422: قيمة الحالة غير صالحة (status ليس 0 أو 1)

## 6. إدارة الأدوار

### 6.1 قائمة الأدوار

```
GET /admin/role
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر في الصفحة |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرف الدور المشفر بـ hashid |
| name | string | اسم الدور |
| slug | string | معرّف الدور (فريد، يُستخدم للحكم على الصلاحيات) |
| description | string | وصف الدور |
| status | int | 1=مفعّل, 0=معطل |
| users_count | int | عدد المستخدمين الحائزين على هذا الدور |

### 6.2 إنشاء دور

```
POST /admin/role
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| name | string | نعم | max:50 | اسم الدور |
| slug | string | نعم | max:50 | معرّف الدور |
| description | string | لا | | وصف الدور، الافتراضي سلسلة فارغة |
| status | int | لا | | الحالة، الافتراضي 1 |
| permission_ids | array{int} | لا | | مصفوفة معرفات الصلاحيات (معرفات INT أصلية، وليست hashid) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 تحديث دور

```
PUT /admin/role/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الدور |
| description | string | لا | الوصف |
| status | int | لا | 0=معطل, 1=مفعّل |
| permission_ids | array{int} | لا | مصفوفة معرفات الصلاحيات، عند إرسالها تُزامَن (تستبدل) صلاحيات الدور |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 حذف دور

```
DELETE /admin/role/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيدًا ثانويًا بكلمة المرور

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف تُفك العلاقات بين الدور وجميع الصلاحيات والمستخدمين تلقائيًا، ثم يُحذف سجل الدور فيزيائيًا.

## 7. إدارة الصلاحيات

تُعتمد الصلاحيات بنية شجرية (parent_id يرتبط ذاتيًا)، وتنقسم إلى ثلاثة أنواع. تُرجع واجهة القائمة شجرة الصلاحيات الكاملة.

### 7.1 شجرة الصلاحيات

```
GET /admin/permission
```

- **المصادقة**: JWT + RBAC

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | مشفر بـ hashid |
| parent_id | string | hashid الصلاحية الأب، "0" تعني العقدة الجذرية |
| name | string | اسم الصلاحية |
| slug | string | معرّف الصلاحية (معرّف المسار/الزر) |
| type | int | 1=قائمة، 2=زر، 3=واجهة |
| icon | string | أيقونة القائمة (اسم أيقونة Material) |
| path | string | مسار التوجيه في الواجهة الأمامية |
| sort | int | قيمة الترتيب (تصاعديًا) |
| children | array? | قائمة الصلاحيات الفرعية (تكراري)، لا يحتوي هذا الحقل عند عدم وجود عقد فرعية |

### 7.2 إنشاء صلاحية

```
POST /admin/permission
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| parent_id | int | لا | | معرف الصلاحية الأب (نوع INT أصلي)، الافتراضي 0 |
| name | string | نعم | max:50 | اسم الصلاحية |
| slug | string | نعم | max:100 | معرّف الصلاحية |
| type | int | نعم | in:1,2,3 | 1=قائمة، 2=زر، 3=واجهة |
| icon | string | لا | | أيقونة القائمة، الافتراضي فارغ |
| path | string | لا | | مسار التوجيه في الواجهة الأمامية، الافتراضي فارغ |
| sort | int | لا | | قيمة الترتيب، الافتراضي 0 |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 تحديث صلاحية

```
PUT /admin/permission/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الصلاحية |
| icon | string | لا | الأيقونة |
| path | string | لا | مسار التوجيه |
| sort | int | لا | قيمة الترتيب |

### 7.4 حذف صلاحية

```
DELETE /admin/permission/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيدًا ثانويًا بكلمة المرور

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف تُحذف جميع الصلاحيات الفرعية تتابعيًا (`parent_id` = معرف الصلاحية الحالية)، وتُفك الارتباطات مع جميع الأدوار في الوقت نفسه.

## 8. إعدادات النظام

تتفرد إعدادات النظام بمزيج `group` + `key`.

### 8.1 قائمة الإعدادات

```
GET /admin/config
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر في الصفحة |
| group | string | لا | | الفلترة حسب مجموعة الإعدادات |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| group | string | مجموعة الإعدادات (مثل `system` و`email` و`storage`) |
| key | string | مفتاح الإعداد |
| value | string | قيمة الإعداد |
| type | string | تلميح نوع القيمة (`string` و`integer` و`boolean` و`json` وغيرها) |
| description | string | وصف الإعداد |

### 8.2 إنشاء إعداد

```
POST /admin/config
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| group | string | نعم | max:100 | مجموعة الإعدادات |
| key | string | نعم | max:100 | مفتاح الإعداد (فريد داخل المجموعة) |
| value | string | نعم | | قيمة الإعداد |
| type | string | لا | | نوع القيمة، الافتراضي `string` |
| description | string | لا | | وصف الإعداد، الافتراضي فارغ |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**الأخطاء المحتملة**:
- 422: بند الإعداد موجود بالفعل (نفس group + key)

### 8.3 تحديث إعداد

```
PUT /admin/config/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| value | string | لا | تحديث قيمة الإعداد |
| type | string | لا | تحديث نوع القيمة |
| description | string | لا | تحديث نص الوصف |

### 8.4 حذف إعداد

```
DELETE /admin/config/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيدًا ثانويًا بكلمة المرور

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

يُحذف سجل الإعداد فيزيائيًا.

## 9. سجل العمليات

سجل العمليات واجهة للقراءة فقط، يكتبه وسيط `OperationLog` تلقائيًا عند كل طلب POST/PUT/DELETE، والحقول المخزنة تشمل `user_id` و`action` و`method` و`path` و`ip` و`source` و`input`.

### 9.1 قائمة سجل العمليات

```
GET /admin/log
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر في الصفحة |
| user_id | int | لا | | الفلترة الدقيقة حسب معرف المستخدم (نوع INT أصلي) |
| action | string | لا | | الفلترة الدقيقة حسب إجراء العملية |
| path | string | لا | | الفلترة الضبابية حسب مسار الطلب |
| start_date | string | لا | | تاريخ البداية (بصيغة Y-m-d) |
| end_date | string | لا | | تاريخ النهاية (بصيغة Y-m-d) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| user_name | string | اسم مستخدم العملية (يُحصل عبر ربط user، وعمليات غير المسجلين تظهر "النظام") |
| action | string | وصف إجراء العملية |
| method | string | طريقة HTTP (POST/PUT/DELETE) |
| path | string | مسار الطلب |
| ip | string | عنوان IP للعميل |
| source | string | مصدر الطلب |
| input | string | سلسلة JSON لمعاملات الطلب (لا تشمل الملفات) |
| created_at | string | وقت العملية (datetime) |

## 10. الملف الشخصي

تتطلب واجهات الملف الشخصي مصادقة JWT فقط (لا يلزم تحقق صلاحيات RBAC — يجب أن يضعها وسيط `AdminPermission` في القائمة البيضاء).

### 10.1 تحديث المعلومات الشخصية

```
PUT /admin/profile
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي |
| phone | string | لا | رقم الهاتف (تخزين مشفر عبر Encryptable) |
| email | string | لا | البريد الإلكتروني (تخزين مشفر عبر Encryptable) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

في الاستجابة يُرجع `phone` و`email` بنص واضح، و`password` و`id_card` مستبعدان.

### 10.2 تغيير كلمة المرور

```
PUT /admin/profile/password
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| الحقل | النوع | إلزامي | قاعدة التحقق | الوصف |
|------|------|------|---------|------|
| old_password | string | نعم | | كلمة المرور الحالية |
| new_password | string | نعم | min:6, max:32 | كلمة المرور الجديدة |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**الأخطاء المحتملة**:
- 422: يرجى إدخال كلمة المرور القديمة والجديدة
- 422: كلمة المرور القديمة خاطئة
- 422: طول كلمة المرور الجديدة 6-32 حرفًا

### 10.3 تسجيل الخروج

```
POST /admin/profile/logout
```

- **المصادقة**: JWT

**جسم الطلب**: لا يوجد (بدون requestBody، يُقرأ الرمز من رأس Authorization)

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

منطق تسجيل الخروج: يفك JWT للحصول على الصلاحية المتبقية (exp - now)، ويكتب بصمة md5 لهذا الرمز في قائمة Redis السوداء `jwt_blacklist:{md5}`، وTTL = الصلاحية المتبقية. الرموز في القائمة السوداء يُعترضها وسيط `AdminAuth` وتُرجع 401.

عند عدم وجود رمز تُرجع 401. وعندما يكون الرمز منتهيًا/غير صالح (رمي استثناء في فك التشفير) ما زال يُعتبر تسجيل خروج ناجحًا.

## 11. الاستيراد والتصدير

### 11.1 تصدير Excel

```
POST /admin/export/excel
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**جسم الطلب**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| table | string | لا | `admin_user` | اسم الجدول المراد تصديره. يدعم: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | لا | | مصفوفة أسماء أعمدة التصدير، عند فارغة يُصدَّر كل أعمدة الجدول |
| conditions | object | لا | `{}` | شروط الفلترة، أزواج مفتاح-قيمة، عند قيمة غير فارغة تُستخدم في WHERE |
| title | string | لا | `数据导出` | عنوان Excel (يظهر كاسم الورقة) |

**الجداول والأعمدة المدعومة**:

| table | الأعمدة المتاحة |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

تُخفى الحقول الحساسة `phone` و`email` و`id_card` تلقائيًا عند التصدير. حد البيانات الأقصى 10000 صف. الصف الأول من Excel مجمد مع فلترة تلقائية.

### 11.2 تصدير PDF

```
POST /admin/export/pdf
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/pdf`، A4 عرضي)

**جسم الطلب**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

أو بنمط الجدول:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| type | string | لا | `table` | نوع التصدير: `table` / `dashboard` |
| title | string | لا | `数据导出` | عنوان PDF |
| data | object | لا | `{}` | بيانات التصدير |

عند `type=dashboard` يجب أن يحتوي `data` على مصفوفة `stats` (تُعرض كبطاقات)؛ وعند `type=table` يجب أن يحتوي `data` على مصفوفتي `columns` و`rows`.

يتضمن قالب PDF معلومات حقوق النشر وطابع وقت التصدير.

### 11.3 استيراد المستخدمين (Excel)

```
POST /admin/import/users
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data` (رفع ملف)

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | بصيغة `.xlsx` أو `.xls` |

**متطلبات أعمدة Excel**:

| اسم العمود | إلزامي | الوصف |
|------|------|------|
| username | نعم | اسم المستخدم (فريد) |
| password | نعم | كلمة المرور (تخزين كـ bcrypt hash) |
| real_name | نعم | الاسم الحقيقي |
| phone | لا | رقم الهاتف |
| email | لا | البريد الإلكتروني |
| status | لا | الحالة، الافتراضي 1 |

الصف الأول هو عناوين الأعمدة (غير حساس لحالة الأحرف)، ومن الصف الثاني تبدأ البيانات.

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| total | int | إجمالي عدد الصفوف (بدون صف العنوان) |
| success | int | عدد الاستيراد الناجح |
| failed | int | عدد حالات الفشل |
| errors | array | تفاصيل الفشل، كل عنصر يحتوي row (رقم صف Excel) وreason (سبب الفشل) |

## 12. رفع الملفات

```
POST /admin/upload
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data`

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | الملف المراد رفعه |

**أنواع الملفات المسموحة**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**الحد الأقصى لحجم الملف**: 10MB

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

تُخزَّن الملفات في مجلدات مصنفة حسب التاريخ `public/upload/{Y-m-d}/`، واسم الملف هو `md5(uniqid) + الامتداد الأصلي`. و`url` مسار نسبي بالنسبة لجذر الموقع.

**الأخطاء المحتملة**:
- 422: يرجى اختيار ملف (لم يُرفع)
- 422: نوع الملف غير مدعوم
- 422: لا يمكن أن يتجاوز حجم الملف 10MB
- 500: فشل رفع الملف (ملف غير صالح)

## 13. رؤوس الاستجابة

تحتوي جميع الواجهات (المحقونة في طبقة الوسائط العامة) على الرؤوس التالية:

| الرأس | الوصف |
|----|------|
| `X-RateLimit-Limit` | حد تحديد المعدل (عدد المرات) |
| `X-RateLimit-Remaining` | عدد الطلبات المتبقية |
| `X-RateLimit-Reset` | طابع زمني لإعادة تعيين نافذة تحديد المعدل |
| `Retry-After` | يُرجع فقط عند تفعيل تحديد المعدل، عدد الثواني الموصى بانتظارها |
| `X-Content-Type-Options` | `nosniff` (افتراضي من webman، يمنع استكشاف MIME) |
| `X-Frame-Options` | `DENY` (يوفره وسيط CORS/الإعدادات الأساسية لـ webman) |

تفاصيل تحديد المعدل:
- الحد العام الافتراضي: 60 مرة/دقيقة / IP+مسار
- نقطة تسجيل الدخول `/api/auth/login`: 10 مرات/دقيقة
- نقطة التسجيل `/api/auth/register`: 5 مرات/دقيقة
- تستخدم خوارزمية نافذة منزلقة ذرية من Redis (Lua ZSET) لتجنب سباقات TOCTOU
- عند تعذر استخدام Redis يُفشَل التساهل (تُمرَّر الطلبات)، ولا تُحجب

## 14. تدفق المصادقة

التسلسل الزمني الكامل للمصادقة:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### هيكل JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`، TTL الافتراضي 7200 ثانية (يُتحكم فيه عبر `default_expire` في إعداد JWT)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`، TTL الافتراضي 1209600 ثانية (يُتحكم فيه عبر `refresh_expire` في إعداد JWT، أي 14 يومًا)

### إدارة الأمان

- تُخزَّن كلمات المرور كـ `PASSWORD_BCRYPT` hash
- تُشفَّر/تُفك الحقول الحساسة (phone, email, id_card) بشفافية في طبقة قاعدة البيانات عبر `erikwang2013/encryptable`
- تُشفَّر معرفات طبقة API عبر `erikwang2013/hashids` أثناء النقل، لتجنب كشف تسلسل معرفات snowflake الأصلية
- يفحص SecurityFilter عالميًا XSS وحقن SQL واجتياز المسار وحقن الأوامر، و5 مرات/60 ثانية لنفس IP تُدرج في القائمة السوداء المؤقتة 15 دقيقة
- تتطلب العمليات الحساسة (حذف المستخدمين والأدوار والصلاحيات والإعدادات) تأكيدًا ثانويًا بكلمة مرور المستخدم المسجل حاليًا
- حد الجلسات المتزامنة: حتى 3 رموز صالحة لنفس المستخدم، وعند تسجيل دخول الجهاز الرابع يُضاف أقدم رمز قسريًا إلى القائمة السوداء
- قفل الحساب: 5 محاولات تسجيل دخول فاشلة متتالية تُفعّل قفل الحساب 15 دقيقة، وتُرجع 429 خلال فترة القفل

## 15. النشر والتشغيل

### Docker Compose

في جذر المشروع `docker-compose.yml` ينسق 5 خدمات (Nginx وتطبيق webman وMySQL وRedis وElasticsearch). يُبنى PHP عبر `Dockerfile` (مبني على `php:8.3-cli` مع تفعيل OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

يُعرّف `.github/workflows/ci.yml` خط أنابيب التكامل المستمر في GitHub Actions:
- فحص بناء الجملة `php -l`
- اختبارات الوحدة PHPUnit
- التحليل الثابت `flutter analyze`

### النسخ الاحتياطي لقاعدة البيانات

يوفر مجلد `database/backup/` سكربتي النسخ والاستعادة:
- `backup.sh` — ضغط نسخ mysqldump + gzip، تنظيف تلقائي لملفات النسخ الأقدم من 30 يومًا
- `restore.sh` — استعادة تفاعلية، تعرض النسخ الاحتياطية الموجودة للاختيار

### إعداد Nginx الأمني

في نشر بيئة الإنتاج يرجى الرجوع إلى `nginx-security.conf` لإعداد تعزيز أمان الوكيل العكسي.

## 16. نقاط API الخاصة بالأعمال (ERP)

جميع نقاط الأعمال ضمن مجموعة `/admin`، وتمر عبر ثلاثة وسائط: `AdminAuth` (مصادقة JWT) و`AdminPermission` (تحقق صلاحيات RBAC) و`OperationLog` (تسجيل العمليات).

> إجمالي النقاط: المنتجات(17) | المشتريات(8) | المبيعات(6) | المخزون(6) | المالية(17) | CRM(13) | سير العمل(6) | الإشعارات(4) | المشاريع(3) | الموارد البشرية(9) | التصنيع(7) | التقارير(4) | لوحة المعلومات(3) | العميل(2) | المجموع 105 نقاط

نقاط الترابط عبر الوحدات مُعلَّمة بـ 🔗.

### 16.1 إدارة المنتجات (Product Management)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/product | قائمة المنتجات (ترقيم صفحات + بحث + فلترة تصنيف/حالة) |
| POST | /admin/product | إنشاء منتج (يشمل SKU والأسعار) |
| GET | /admin/product/{id} | تفاصيل المنتج (يشمل التصنيف/العلامة التجارية/SKU/الأسعار/الوحدة) |
| PUT | /admin/product/{id} | تحديث منتج |
| DELETE | /admin/product/{id} | حذف منتج (حذف ناعم، يتطلب تأكيد كلمة المرور) |
| GET | /admin/category | قائمة التصنيفات (شجري) |
| POST | /admin/category | إنشاء تصنيف |
| PUT | /admin/category/{id} | تحديث تصنيف |
| DELETE | /admin/category/{id} | حذف تصنيف |
| GET | /admin/brand | قائمة العلامات التجارية |
| POST | /admin/brand | إنشاء علامة تجارية |
| GET | /admin/warehouse | قائمة المستودعات |
| POST | /admin/warehouse | إنشاء مستودع |
| GET | /admin/location | قائمة مواقع التخزين |
| GET | /admin/warehouse/{id}/locations | قائمة مواقع تخزين المستودع |
| GET | /admin/supplier | قائمة الموردين (بحث ES) |
| POST | /admin/supplier | إنشاء مورد |
| GET | /admin/customer | قائمة العملاء (بحث ES) |
| POST | /admin/customer | إنشاء عميل |

### 16.2 إدارة المشتريات (Purchase)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/purchase/apply | قائمة طلبات الشراء |
| POST | /admin/purchase/apply | إنشاء طلب شراء |
| GET | /admin/purchase/order | قائمة أوامر الشراء |
| POST | /admin/purchase/order | إنشاء أمر شراء |
| 🔗 POST | /admin/purchase/receive | إنشاء سند استلام (إدخال تلقائي للمخزون + توليد ذمم دائنة) |
| GET | /admin/purchase/receive | قائمة سندات الاستلام |
| GET | /admin/purchase/receive/{id} | تفاصيل سند الاستلام |
| POST | /admin/purchase/return | إنشاء سند إرجاع |
| GET | /admin/purchase/settlement | قائمة تسويات الموردين |

### 16.3 إدارة المبيعات (Sales)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/sales/quotation | قائمة عروض الأسعار |
| POST | /admin/sales/quotation | إنشاء عرض سعر |
| GET | /admin/sales/order | قائمة أوامر المبيعات |
| POST | /admin/sales/order | إنشاء أمر مبيعات |
| 🔗 POST | /admin/sales/delivery | إنشاء سند شحن (إخراج تلقائي من المخزون + توليد ذمم مدينة) |
| GET | /admin/sales/delivery | قائمة سندات الشحن |
| GET | /admin/sales/settlement | قائمة تسويات العملاء |

### 16.4 إدارة المخزون (Inventory)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/inventory | مخزون فوري (بأبعاد المستودع/موقع التخزين/الدفعة/SKU) |
| GET | /admin/inventory/flow | سجلات الإدخال والإخراج |
| GET | /admin/inventory/transfer | قائمة سندات التحويل |
| POST | /admin/inventory/transfer | إنشاء سند تحويل |
| GET | /admin/inventory/check | قائمة مهام الجرد |
| POST | /admin/inventory/check | إنشاء مهمة جرد |
| GET | /admin/inventory/alert | قواعد تنبيه المخزون |

### 16.5 الإدارة المالية (Finance)

| الطريقة | المسار | الوصف |
|------|------|------|
| POST | /admin/finance/voucher | إنشاء قيد محاسبي |
| GET | /admin/finance/ar-ap | قائمة الذمم المدينة/الدائنة |
| POST | /admin/finance/receipt | إنشاء سند مقبوضات |
| POST | /admin/finance/payment | إنشاء سند مدفوعات |
| GET | /admin/finance/cash-journal | دفتر اليومية النقدية والبنكية |
| GET | /admin/finance/expense | قائمة تسديد المصاريف |
| POST | /admin/finance/expense | تقديم طلب تسديد مصاريف |
| GET | /admin/finance/report/profit | بيان الأرباح |
| GET | /admin/finance/general-ledger | دفتر الأستاذ العام (ملخص حسب الحساب + الفترة) |
| GET | /admin/finance/subsidiary-ledger | دفتر الأستاذ التفصيلي (تفاصيل كل معاملة للحساب) |
| GET | /admin/finance/report/balance-sheet | الميزانية العمومية (تشمل التوليد التلقائي) |
| GET | /admin/finance/report/cash-flow | قائمة التدفق النقدي (تشغيلي/استثماري/تمويلي) |
| GET | /admin/finance/bank-account | قائمة الحسابات البنكية |
| GET/POST/PUT/DELETE | /admin/finance/asset | CRUD الأصول الثابتة + احتساب الإهلاك |
| GET/POST | /admin/finance/tax-rate | إعداد معدلات الضرائب |
| GET | /admin/finance/tax-record | السجلات الضريبية |
| GET/POST/PUT/DELETE | /admin/finance/currency | إدارة العملات |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | إدارة أسعار الصرف |
| GET/POST/PUT/DELETE | /admin/finance/budget | إدارة الميزانيات (تشمل مقارنة الميزانية مقابل الفعلي) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | مراكز التكلفة (بنية شجرية) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | مراكز الأرباح (بنية شجرية) |

### 16.6 إدارة علاقات العملاء (CRM)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/crm/opportunity | قائمة الفرص |
| POST | /admin/crm/opportunity | إنشاء فرصة |
| GET | /admin/crm/follow | قائمة سجلات المتابعة |
| POST | /admin/crm/follow | إنشاء سجل متابعة |
| GET | /admin/crm/funnel | إعداد مراحل القمع |
| GET | /admin/crm/contact | قائمة جهات الاتصال |
| POST | /admin/crm/contact | إنشاء جهة اتصال |
| GET | /admin/crm/pool | قائمة عملاء التجمع العام |
| POST | /admin/crm/pool/claim/{id} | استلام عميل من التجمع |
| POST | /admin/crm/pool/release/{id} | إطلاق عميل إلى التجمع |
| GET/POST | /admin/crm/pool/rules | CRUD قواعد التجمع العام |
| GET | /admin/crm/contract | قائمة العقود |
| POST | /admin/crm/contract | إنشاء عقد |
| GET | /admin/crm/contract/{id} | تفاصيل العقد |
| PUT | /admin/crm/contract/{id} | تحديث عقد |
| DELETE | /admin/crm/contract/{id} | حذف عقد |
| GET | /admin/crm/quotation | قائمة عروض أسعار CRM |
| POST | /admin/crm/quotation | إنشاء عرض سعر CRM |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 تحويل عرض السعر إلى عقد |
| GET/POST/PUT/DELETE | /admin/crm/campaign | الحملات التسويقية |
| GET/POST/PUT/DELETE | /admin/crm/ticket | تذاكر الخدمة |
| POST | /admin/crm/ticket/{id}/assign | توزيع تذكرة |
| POST | /admin/crm/ticket/{id}/resolve | حل تذكرة |
| GET/POST | /admin/crm/analytics/report | تقارير تحليل العملاء |
| GET/POST | /admin/crm/analytics/metric | مؤشرات التحليل |

### 16.7 سير عمل الموافقات (Workflow)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/workflow | قائمة تعريفات سير العمل |
| POST | /admin/workflow | إنشاء تعريف سير عمل |
| GET | /admin/workflow/{id} | تفاصيل سير العمل |
| PUT | /admin/workflow/{id} | تحديث سير العمل |
| DELETE | /admin/workflow/{id} | حذف سير العمل |
| POST | /admin/workflow/{id}/submit | 🔗 تقديم للموافقة (إنشاء مثيل موافقة) |
| POST | /admin/approval/{id}/approve | موافقة |
| POST | /admin/approval/{id}/reject | رفض |
| POST | /admin/approval/{id}/withdraw | سحب |
| ANY | /admin/approval/my | قائمة موافقاتي (قيد الانتظار/تمت الموافقة) |

### 16.8 إشعارات الرسائل (Notification)

| الطريقة | المسار | الوصف |
|------|------|------|
| ANY | /admin/notification/my | قائمة إشعاراتي (ترقيم صفحات، بترتيب زمني عكسي) |
| POST | /admin/notification/{id}/read | تحديد إشعار كمقروء |
| POST | /admin/notification/read-all | تحديد الكل كمقروء |
| ANY | /admin/notification/unread-count | عدد الرسائل غير المقروءة |

### 16.9 إدارة المشاريع (Project)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/project | قائمة المشاريع |
| POST | /admin/project | إنشاء مشروع |
| GET | /admin/project/{id} | تفاصيل المشروع |
| PUT | /admin/project/{id} | تحديث مشروع |
| DELETE | /admin/project/{id} | حذف مشروع |
| GET | /admin/project/task | قائمة المهام |
| POST | /admin/project/task | إنشاء مهمة |
| PUT | /admin/project/task/{id} | تحديث مهمة |
| DELETE | /admin/project/task/{id} | حذف مهمة |
| GET | /admin/project/timesheet | قائمة سجلات ساعات العمل |
| POST | /admin/project/timesheet | إدخال ساعات عمل |
| PUT | /admin/project/timesheet/{id} | تحديث ساعات عمل |
| DELETE | /admin/project/timesheet/{id} | حذف ساعات عمل |

### 16.10 إدارة الموارد البشرية (HR)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/hr/department | قائمة الأقسام (شجري) |
| POST | /admin/hr/department | إنشاء قسم |
| PUT | /admin/hr/department/{id} | تحديث قسم |
| DELETE | /admin/hr/department/{id} | حذف قسم |
| GET | /admin/hr/employee | قائمة الموظفين |
| POST | /admin/hr/employee | إنشاء موظف |
| PUT | /admin/hr/employee/{id} | تحديث موظف |
| DELETE | /admin/hr/employee/{id} | حذف موظف |
| GET | /admin/hr/position | قائمة المناصب |
| POST | /admin/hr/position | إنشاء منصب |
| PUT | /admin/hr/position/{id} | تحديث منصب |
| DELETE | /admin/hr/position/{id} | حذف منصب |
| ANY | /admin/hr/attendance | الاستعلام عن سجلات الحضور |
| POST | /admin/hr/attendance/clock-in | تسجيل دخول العمل |
| POST | /admin/hr/attendance/clock-out | تسجيل خروج العمل |
| ANY | /admin/hr/leave | قائمة الإجازات |
| POST | /admin/hr/leave | تقديم طلب إجازة |
| GET | /admin/hr/leave/{id} | تفاصيل الإجازة |
| PUT | /admin/hr/leave/{id} | تحديث إجازة |
| DELETE | /admin/hr/leave/{id} | حذف إجازة |
| POST | /admin/hr/leave/{id}/approve | 🔗 الموافقة على الإجازة |
| GET | /admin/hr/salary | قائمة الرواتب |
| POST | /admin/hr/salary | توليد سند رواتب |
| PUT | /admin/hr/salary/{id} | تحديث راتب |
| DELETE | /admin/hr/salary/{id} | حذف راتب |
| POST | /admin/hr/salary/{id}/pay | صرف الراتب |
| ANY | /admin/hr/salary-item | قائمة بنود الرواتب |
| POST | /admin/hr/salary-item | إنشاء بند راتب |
| GET | /admin/hr/salary-item/{id} | تفاصيل بند الراتب |
| PUT | /admin/hr/salary-item/{id} | تحديث بند راتب |
| DELETE | /admin/hr/salary-item/{id} | حذف بند راتب |

### 16.11 التصنيع (Manufacturing)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/mfg/bom | قائمة BOM |
| POST | /admin/mfg/bom | إنشاء BOM |
| PUT | /admin/mfg/bom/{id} | تحديث BOM |
| DELETE | /admin/mfg/bom/{id} | حذف BOM |
| GET | /admin/mfg/production | قائمة أوامر الإنتاج |
| POST | /admin/mfg/production | إنشاء أمر إنتاج |
| PUT | /admin/mfg/production/{id} | تحديث أمر إنتاج |
| DELETE | /admin/mfg/production/{id} | حذف أمر إنتاج |
| POST | /admin/mfg/production/{id}/start | بدء التشغيل |
| POST | /admin/mfg/production/{id}/complete | اكتمال الإنتاج |
| GET | /admin/mfg/routing | قائمة مسارات التشغيل |
| POST | /admin/mfg/routing | إنشاء مسار تشغيل |
| PUT | /admin/mfg/routing/{id} | تحديث مسار تشغيل |
| DELETE | /admin/mfg/routing/{id} | حذف مسار تشغيل |
| GET | /admin/mfg/workstation | قائمة محطات العمل |
| POST | /admin/mfg/workstation | إنشاء محطة عمل |
| PUT | /admin/mfg/workstation/{id} | تحديث محطة عمل |
| DELETE | /admin/mfg/workstation/{id} | حذف محطة عمل |
| GET | /admin/mfg/mrp | قائمة خطط MRP |
| POST | /admin/mfg/mrp | إنشاء خطة MRP |
| PUT | /admin/mfg/mrp/{id} | تحديث خطة MRP |
| DELETE | /admin/mfg/mrp/{id} | حذف خطة MRP |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 تشغيل MRP لتوليد اقتراحات شراء/إنتاج |

### 16.12 التقارير المخصصة (Report Builder)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/report | قائمة قوالب التقارير |
| POST | /admin/report | إنشاء قالب تقرير |
| GET | /admin/report/{id} | تفاصيل قالب التقرير |
| PUT | /admin/report/{id} | تحديث قالب تقرير |
| DELETE | /admin/report/{id} | حذف قالب تقرير |
| POST | /admin/report/{id}/execute | تنفيذ التقرير لتوليد البيانات |
| ANY | /admin/report/{id}/result | نتيجة تنفيذ التقرير |
| GET | /admin/report/schedule | قائمة الجدولة الدورية |
| POST | /admin/report/schedule | إنشاء جدولة دورية |
| PUT | /admin/report/schedule/{id} | تحديث جدولة دورية |
| DELETE | /admin/report/schedule/{id} | حذف جدولة دورية |

### 16.13 لوحة المعلومات (Dashboard)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/dashboard/sales | لوحة المبيعات |
| GET | /admin/dashboard/inventory | لوحة المخزون |
| GET | /admin/dashboard/finance | لوحة المالية |

### 16.14 واجهات العميل (Client API)

تُركَّب واجهات العميل ضمن مجموعة `/api` وتتطلب رأس `API-Version`. معلومات المنتج لا تتضمن سعر الشراء.

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /api/product | قائمة المنتجات (بدون سعر الشراء) |
| GET | /api/product/{hashid} | تفاصيل المنتج (تشمل سعر التجزئة/الجملة، بدون سعر الشراء) |

### 16.15 إدارة الطلبات OMS

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/oms/order | قائمة أوامر OMS |
| POST | /admin/oms/order | إنشاء أمر OMS |
| 🔗 POST | /admin/oms/order/{id}/allocate | توزيع المخزون (حجز) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | إنشاء التنفيذ |
| POST | /admin/oms/order/{id}/cancel | إلغاء الطلب (تحرير الحجز) |
| POST | /admin/oms/rma/{id}/approve | الموافقة على RMA |
| POST | /admin/oms/rma/{id}/refund | استرداد RMA |

### 16.16 إدارة المستودعات WMS

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/wms/zone | قائمة المناطق (CRUD) |
| GET | /admin/wms/location | قائمة مواقع WMS (CRUD) |
| GET | /admin/wms/asn | قائمة ASN (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | إكمال الاستلام ← توليد مهمة رفع تلقائيًا |
| POST | /admin/wms/putaway/{id}/complete | تأكيد الرفع ← تفعيل stockIn |
| POST | /admin/wms/wave/{id}/release | إطلاق الموجة ← توليد مهمة انتقاء |
| POST | /admin/wms/pick/{id}/start | بدء الانتقاء |
| POST | /admin/wms/pick/{id}/confirm | تأكيد الانتقاء |
| POST | /admin/wms/pack/{id}/complete | اكتمال التغليف |

### 16.17 إدارة النقل TMS

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/tms/carrier | قائمة الناقلين (CRUD) |
| GET | /admin/tms/service | خدمات الناقلين (CRUD) |
| GET | /admin/tms/freight-rate | معدلات الشحن (CRUD) |
| GET | /admin/tms/shipment | قائمة الشحنات (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | تأكيد الشحن (stockOut+AR) |
| POST | /admin/tms/tracking/callback | webhook تتبع الناقل |
| POST | /admin/tms/freight-invoice/{id}/pay | دفع فاتورة الشحن (توليد AP) |

### 16.18 توسعات لوحة المعلومات

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/dashboard/oms | مؤشرات OMS (قيد المعالجة/قيد الانتقاء/شحن اليوم/RMA) |
| GET | /admin/dashboard/wms | مؤشرات WMS (قيد الاستلام/قيد الرفع/قيد الانتقاء/قيد التغليف) |
| GET | /admin/dashboard/tms | مؤشرات TMS (قيد الشحن/قيد النقل/تم الاستلام/غير طبيعي) |

### 16.19 شرح الترابط عبر الوحدات

تُفعّل النقاط التالية ترابطًا تلقائيًا عبر الوحدات، ومعلَّمة بـ 🔗:

| النقطة | إجراء الترابط |
|------|---------|
| 🔗 POST /admin/purchase/receive | استدعاء تلقائي لـ InventoryService.stockIn() لتحديث المخزون + إعادة حساب تكلفة المتوسط المتحرك المرجح؛ واستدعاء FinanceService.createAp() لتوليد سجل ذمم دائنة |
| 🔗 POST /admin/sales/delivery | استدعاء تلقائي لـ InventoryService.stockOut() لخصم المخزون (بتكلفة المتوسط المتحرك المرجح)؛ واستدعاء FinanceService.createAr() لتوليد سجل ذمم مدينة |
