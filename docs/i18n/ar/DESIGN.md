# لوحة الإدارة المفتوحة — وثيقة التصميم

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> لمخططات Mermaid التفصيلية راجع [ARCHITECTURE.md](ARCHITECTURE.md) (يمكن لـ GitHub/GitLab/VS Code عرضها تلقائيًا).

## 1. بنية النظام

> **قائمة الوظائف**: المصادقة (login/register/refresh/logout + قفل الحساب + تقييد الجلسات) | لوحة المعلومات (تخزين مؤقت Redis) | مستخدمو CRUD + جماعي + استيراد | صلاحيات الأدوار (RBAC) | إعدادات النظام | تدقيق العمليات (8 منصات مصدر) | الملفات (رفع + تصدير + إخفاء) | الأمان (دفاع من 18 طبقة) | التشغيل (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. بنية الخلفية

### 2.1 التصميم الطبقي

| الطبقة | الدليل | المسؤولية |
|---|------|------|
| المسارات | `config/route.php` | تعيين URL إلى وحدات التحكم، ربط الوسائط، المسارات المتجهزة |
| الوسائط | `app/middleware/` | اعتراض الهجمات (SecurityFilter)، تحديد المعدل (RateLimit)، المصادقة (JWT)، التفويض (RBAC)، إصدار API (ApiVersion) |
| وحدات التحكم | 14 وحدة: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (جهة الإدارة) + Captcha/Auth (API v1) | التحقق من معاملات الطلب، استدعاء منطق الأعمال، تنسيق الاستجابة |
| خدمات الأعمال | `app/service/` | منطق أعمال قابل لإعادة الاستخدام (محجوز) |
| نماذج البيانات | `app/model/` | تعيين ORM، العلاقات، تشفير وفك تشفير الحقول |
| الأدوات العامة | `app/common/` | خدمات Hashids وSnowflake وEncryption |

### 2.2 دورة حياة الطلب

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 دورة حياة المعرفات

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 منظومة تشفير البيانات

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. تصميم قاعدة البيانات

### 3.1 علاقات ER

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erp_operation_log
             (操作日志)

erp_system_config (系统配置) — 独立表
```

### 3.2 بنية الجداول الأساسية

| اسم الجدول | عدد الحقول | الشرح |
|------|-------|------|
| `erp_admin_user` | 14 | مستخدمو الإدارة، تخزين مشفر لـ phone/email/id_card، دعم الحذف الناعم |
| `erp_admin_role` | 7 | الأدوار، slug فريد |
| `erp_admin_permission` | 10 | شجرة الصلاحيات (parent_id مرجع ذاتي)، type: 1=قائمة 2=زر 3=API |
| `erp_admin_user_role` | 2 | جدول وسيط متعدد-لمتعدد بين المستخدم والدور |
| `erp_admin_role_permission` | 2 | جدول وسيط متعدد-لمتعدد بين الدور والصلاحية |
| `erp_system_config` | 8 | تكوين أزواج مفتاح-قيمة، فريد مشترك group+key |
| `erp_operation_log` | 9 | سجل تدقيق العمليات (يشمل حقل المصدر source) |

### 3.3 معيار المفتاح الأساسي

- النوع: `BIGINT UNSIGNED NOT NULL`
- الخاصية: **غير تلقائي التزايد**، يولَّد في طبقة التطبيق عبر خوارزمية Snowflake
- المزايا: فريد عالميًا، صديق للأنظمة الموزعة، زيادة اتجاهية تساعد على الفهرسة، لا يكشف حجم الأعمال
- التكوين: datacenter_id(0-31) + worker_id(0-31)، يدعم 1024 عقدة بالتزامن

## 4. تصميم API

### 4.1 معيار URL

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 استراتيجية إصدارات API

يتحكم إصدار API عبر رأس الطلب، **ولا يظهر في مسار URL**:

```http
API-Version: v1
```

| الآلية | الشرح |
|------|------|
| الإصدار الافتراضي | عند عدم حمل `API-Version` يكون الافتراضي `v1` |
| التحقق | يتحقق وسيط `ApiVersion`، ويعيد 400 للإصدارات غير المدعومة |
| التوجيه | الدالة المساعدة `v()` تحلل فئة وحدة التحكم ديناميكيًا حسب الإصدار |
| الدليل | وحدات التحكم منظمة حسب الإصدار: `app/api/{version}/controller/` |

مثال التوسعة — إضافة API v2:
1. أنشئ `app/api/v2/controller/AuthController.php`
2. أضف `'v2'` إلى ثابت `SUPPORTED` في وسيط `ApiVersion`
3. تعريفات المسارات لا تحتاج تعديلًا

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 استراتيجية تحديد المعدل

خوارزمية النافذة المنزلقة القائمة على Redis Sorted Set، تُنفَّذ عبر سكربت Lua ذري:

| الواجهة | التحديد |
|------|------|
| الافتراضي | 60 مرة/دقيقة/IP/مسار |
| POST /api/auth/login | 10 مرات/دقيقة |
| POST /api/auth/register | 5 مرات/دقيقة |

عند التجاوز يُعاد 429، وتتضمن رؤوس الاستجابة X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 الاستجابة الموحدة

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | المعنى | سيناريو التفعيل |
|------|------|---------|
| 0 | نجاح | استجابة عادية |
| 400 | خطأ في المعاملات | تنسيق الطلب غير صحيح |
| 401 | غير مصادق | الرمز مفقود/منتهي/غير صالح |
| 403 | بلا صلاحية | دور المستخدم لا يتضمن الصلاحية المطلوبة |
| 404 | غير موجود | المورد غير موجود |
| 422 | فشل التحقق | معاملات النموذج لا تطابق القواعد / فشل تأكيد كلمة المرور |
| 500 | خطأ خادم | استثناء غير متوقع |

### 4.5 تدفق المصادقة (يشمل كابتشا النقر)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 نموذج الصلاحيات (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 التأكيد الثانوي للعمليات الحساسة

تتطلب العمليات الحساسة مثل حذف المستخدمين والأدوار والصلاحيات تمرير كلمة مرور المستخدم الحالي في جسم الطلب لإعادة التحقق من الهوية:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

تعرض الواجهة قبل تنفيذ الحذف مربع حوار تأكيد، وتجمع كلمة مرور المستخدم قبل إرسال الطلب.

## 5. تصميم الواجهة

### 5.1 لوحة إدارة Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

المميزات: شريط جانبي قابل للطي، سمة مزدوجة Material 3، جدول بيانات عالي الكثافة، نوافذ Dialog، تفاعل تمرير الماوس

### 5.2 عميل HarmonyOS للموبايل

توجيه الصفحات:

| الصفحة | المسار | الشرح |
|------|------|------|
| LoginPage | `pages/LoginPage` | تسجيل دخول باسم المستخدم وكلمة المرور + كابتشا النقر |
| DashboardPage | `pages/DashboardPage` | بطاقات الإحصائيات + أحدث العمليات |
| UserListPage | `pages/UserListPage` | قائمة المستخدمين، بحث + سحب للتحديث + تحميل عند التمرير لأعلى |
| UserDetailPage | `pages/UserDetailPage` | إضافة/تعديل/عرض/حذف (تأكيد AlertDialog) |
| ProfilePage | `pages/ProfilePage` | المركز الشخصي، تسجيل الخروج (تأكيد AlertDialog) |

تدفق البيانات: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. تصميم الأمان

### 6.1 الدفاع المتعمق

| الطبقة | الإجراء |
|------|------|
| تقييد الطرق | قائمة بيضاء لطرق HTTP في SecurityFilter، يسمح فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، ويعيد 405 للطرق غير القياسية |
| اعتراض الهجمات | وسيط SecurityFilter، كشف واعتراض XSS/حقن SQL/اجتياز المسارات/حقن الأوامر/CSRF |
| التحقق البشري | كابتشا النقر (Click Captcha)، تحقق إجباري عند تسجيل الدخول/التسجيل |
| قفل الحساب | 5 محاولات تسجيل دخول فاشلة متتالية تقفل الحساب 15 دقيقة، ويعيد 429 أثناء القفل |
| تقييد الجلسات | 3 رموز متزامنة كحد أقصى لنفس المستخدم، ويُضاف الأقدم تلقائيًا إلى القائمة السوداء عند التجاوز |
| تحديد المعدل | وسيط RateLimit، نافذة منزلقة عبر Redis، Lua ذري |
| CSP | رأس Content-Security-Policy يحدد مصادر الموارد، لمنع XSS وحقن البيانات |
| تأكيد العمليات | العمليات الحساسة مثل الحذف تتطلب إدخال كلمة مرور المستخدم الحالي للتأكيد الثانوي |
| النقل | HTTPS + JWT Bearer Token |
| معرفات الواجهات | تشفير Hashids، لا يمكن استنتاج المعرفات الحقيقية خارجيًا |
| جسم الطلب | تشفير AES-256-CBC للحقول الحساسة |
| قاعدة البيانات | مفاتيح BIGINT الأساسية (لا تكشف قيمة التزايد التلقائي) |
| قاعدة البيانات | تشفير AES-128-ECB للحقول الحساسة عند التخزين |
| المصادقة | JWT HS256، انتهاء بعد 2 ساعة + رمز تحديث |
| التفويض | RBAC، تحكم بالصلاحيات بدقة method.path |
| التدقيق | OperationLog يسجل جميع العمليات (يشمل الكشف التلقائي لمصدر source) |

### 6.2 إدارة المفاتيح

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 حماية البيانات الحساسة

| السيناريو | الحقل | الإجراء |
|------|------|------|
| عرض القائمة | phone | إخفاء: 138****1234 |
| عرض القائمة | email | إخفاء: a***@example.com |
| عرض التفاصيل | phone/email | يتطلب واجهة فك تشفير |
| تصدير Excel | phone/email | تصدير بعد الإخفاء |
| تصدير PDF | جميع الحقول | إخفاء + علامة مائية لحقوق النشر غير قابلة للإزالة |
| التخزين | phone/email/id_card | تشفير encryptable إلى نص مشفر |

## 7. تصميم التصدير

### 7.1 تصدير Excel

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 تصدير PDF

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. بنية النشر

### 8.1 الطوبولوجيا الموصى بها

```
Nginx (:443 HTTPS) → webman worker × N (:8788) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (موصى به للإنتاج)

ينسق `docker-compose.yml` في جذر المشروع جميع خدمات الطوبولوجيا أعلاه:

| الخدمة | الصورة/البناء | المنفذ | الشرح |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | وكيل عكسي + ملفات ثابتة + Gzip |
| `app` | بناء محلي عبر `Dockerfile` | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | قاعدة البيانات الرئيسية، استمرار عبر وحدات تخزين البيانات |
| `redis` | redis:7-alpine | 6379 | تخزين مؤقت / تحديد معدل / كابتشا |
| `elasticsearch` | elasticsearch:8.x | 9200 | بحث نصي كامل |

قبل التشغيل استبدل المفاتيح في `docker-compose.yml` مثل `JWT_SECRET` و `HASHIDS_SALT` و `ENCRYPTION_KEY` بسلاسل عشوائية.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

يُعرَّف التكامل المستمر لـ GitHub Actions في `.github/workflows/ci.yml`:
- فحص قواعد PHP (`php -l`)
- اختبارات PHPUnit الوحدوية
- التحليل الساكن لـ Flutter (`flutter analyze`)

### 8.4 النسخ الاحتياطي لقاعدة البيانات

`database/backup/backup.sh` — نسخ احتياطي mysqldump + gzip، تنظيف تلقائي للنسخ الأقدم من 30 يومًا.
`database/backup/restore.sh` — اختيار تفاعلي واستعادة النسخ الاحتياطية.

### 8.5 المراقبة

يكشف نقطة نهاية `GET /metrics` (`MetricsController`) بصيغة Prometheus text format 5 مقاييس gauge: إجمالي طلبات HTTP، عدد المستخدمين النشطين، حالة اتصال قاعدة البيانات/Redis، استخدام الذاكرة.

### 8.6 متطلبات البيئة

| المكوّن | الحد الأدنى للإصدار | التكوين الموصى به |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ مع تفعيل OPcache |
| MySQL | 8.0+ | نسخ رئيسي-تابع 8.0+ |
| Elasticsearch | 7.x | مجموعة من 3 عقد 8.x |
| Redis | 6.x | وضع الحارس 7.x |
| Nginx | 1.20+ | وكيل عكسي + gzip + SSL |
| Flutter SDK | 3.41+ | أحدث إصدار مستقر |
| HarmonyOS | API 12 | DevEco Studio 5.x |
