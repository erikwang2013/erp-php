# مخططات البنية ومنطق الأعمال

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> تُعرض رسوم Mermaid التالية تلقائيًا في GitHub / GitLab / VS Code. في البيئات الأخرى استخدم [Mermaid Live Editor](https://mermaid.live/) للعرض.

---

## 1. طوبولوجيا بنية النظام

```mermaid
flowchart TB
    subgraph "طبقة العميل"
        A1["Flutter Web<br/>لوحة إدارة PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>عميل الهاتف/الجهاز اللوحي"]
    end

    subgraph "طبقة البوابة/الحافة (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>وكيل عكسي + HTTPS + Gzip<br/>خدمة الملفات الثابتة"]
    end

    subgraph "طبقة التطبيق (webman v2)"
        C_LOC["وسيط Locale<br/>كشف Accept-Language تلقائيًا"]
        C0["وسيط ApiVersion<br/>التحقق من رأس API-Version"]
        C1["وسيط AdminAuth<br/>التحقق من JWT"]
        C2["وسيط AdminPermission<br/>التحقق من صلاحيات RBAC"]
        C3["وحدات تحكم الإدارة<br/>Dashboard / User / Role / Permission"]
        C4["وحدات تحكم عامة v1<br/>Captcha / Auth"]
        C5["خدمات عامة<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "طبقة التخزين"
        D1[("MySQL 8.0<br/>التخزين الرئيسي<br/>بادئة الجداول erik_")]
        D2[("Elasticsearch<br/>البحث النصي الكامل<br/>بادئة الفهارس erik_")]
        D3[("Redis<br/>الجلسة / التخزين المؤقت<br/>تخزين كابتشا")]
    end

    subgraph "خارجي"
        E1["DevEco Studio<br/>بناء HarmonyOS"]
        E2["Flutter SDK<br/>بناء الويب"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. البنية الطبقية للخلفية

```mermaid
flowchart TD
    subgraph "طبقة التوجيه Route Layer"
        R1["config/route.php<br/>تعيين URL ← وحدة التحكم"]
    end

    subgraph "طبقة الوسائط Middleware Layer"
        M_LOC["Locale<br/>كشف Accept-Language تلقائيًا<br/>zh_CN/en"]
        M_RL["RateLimit<br/>تحديد معدل Redis بنافذة منزلقة<br/>رأس استجابة X-RateLimit"]
        M_SF["SecurityFilter<br/>اعتراض وكشف الهجمات<br/>XSS/حقن SQL/اجتياز المسار/CSRF"]
        M0["ApiVersion<br/>التحقق من إصدار API<br/>حقن apiVersion"]
        M1["AdminAuth<br/>التحقق من JWT Token<br/>حقن adminId"]
        M2["AdminPermission<br/>تفويض RBAC<br/>مطابقة method.path<br/>تخزين مؤقت للصلاحيات 60s في Redis"]
    end

    subgraph "طبقة وحدات التحكم Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + بحث + ترقيم"]
        CT3["RoleController<br/>CRUD + مزامنة الصلاحيات"]
        CT4["PermissionController<br/>CRUD + بناء الشجرة"]
        CT5["DashboardController<br/>إحصائيات/اتجاهات/توزيعات"]
        CT6["ExportController<br/>تصدير Excel/PDF"]
        CT7["CaptchaController<br/>توليد/تحقق الكابتشا"]
        CT8["AuthController<br/>تسجيل دخول/تسجيل/تحديث"]
    end

    subgraph "طبقة الخدمات Service Layer"
        S1["HashidsService<br/>ترميز وفك ترميز المعرفات"]
        S2["SnowflakeService<br/>توليد معرفات فريدة عالمية"]
        S3["EncryptionService<br/>تشفير وفك تشفير + إخفاء"]
    end

    subgraph "طبقة النماذج Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "طبقة المشغلات Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_LOC --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_LOC fill:#13C2C2,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

### توسعة طبقة أعمال ERP

مع تطور النظام من لوحة إدارة خالصة إلى نظام ERP كامل، أضيفت الوحدات التجارية التالية إلى طبقة وحدات التحكم وطبقة الخدمات:

| الطبقة | الدليل | الوصف |
|------|------|------|
| وحدات تحكم الأعمال | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70 وحدة، مقسمة حسب الوحدات، تعالج طلبات الأعمال |
| خدمات الأعمال | `app/service/{inventory,finance,notification}/` | إدخال/إخراج المخزون + احتساب التكلفة، المستحقات/الذمم المالية + المقاصة، إرسال الإشعارات |

---

## 3. دورة حياة الطلب

```mermaid
sequenceDiagram
    participant C as العميل
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: طلب HTTPS<br/>Header: API-Version: v1
    N->>MW_LOC: إعادة توجيه
    MW_LOC->>MW_LOC: تحليل Accept-Language<br/>تعيين locale
    MW_LOC->>MW_SF: تمرير

    alt طريقة HTTP غير قياسية (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else طريقة قانونية (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: اجتاز فحص القائمة البيضاء للطرق
    end

    alt تنشيط كشف الهجمات
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: تمرير

    alt تنشيط تحديد المعدل
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: تمرير

    alt إصدار غير مدعوم
        MW0-->>C: 400 إصدار API غير مدعوم
    else إصدار صالح
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token مفقود أو غير صالح
        MW1-->>C: 401 Unauthorized
    else Token صالح
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt لا صلاحية
        MW2-->>C: 403 Forbidden
    else لديه صلاحية
        MW2->>CTL: الدخول إلى وحدة التحكم
    end

    CTL->>CTL: التحقق من المعاملات (validator)
    CTL->>CTL: decodeId(hashid) ← BIGINT

    alt عملية حساسة (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt كلمة مرور خاطئة
            CTL-->>C: 422 فشل التحقق من كلمة المرور
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: فك تشفير تلقائي عبر encryptable cast
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) ← hashid
    SVC-->>CTL: hash string

    CTL->>CTL: بناء استجابة JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: تسجيل سجل العملية (POST/PUT/DELETE)
```

---

## 4. تدفق المصادقة والكابتشا

```mermaid
sequenceDiagram
    participant U as المستخدم
    participant CL as العميل
    participant SV as الخادم
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === الخطوة الأولى: الحصول على الكابتشا ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: توليد صورة خلفية 300×200
    CAP->>CAP: وضع N من أهداف النصوص الصينية عشوائيًا
    CAP->>CAP: توليد key وتخزين targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === الخطوة الثانية: نقر المستخدم ===
    CL->>CL: عرض صورة الكابتشا
    CL->>CL: تلميح "يرجى النقر بالترتيب: شجرة ← طائر ← زهرة"
    U->>CL: النقر على مواضع النصوص في الصورة بالترتيب
    CL->>CL: تجميع clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === الخطوة الثالثة: تسجيل الدخول ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt كابتشا خاطئة
        CAP-->>SV: false
        SV-->>CL: 422 خطأ في الكابتشا
    else كابتشا صحيحة
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt بيانات اعتماد خاطئة
            SV-->>CL: 401 اسم المستخدم أو كلمة المرور خاطئة
        else بيانات اعتماد صحيحة
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === الطلبات اللاحقة ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. نموذج صلاحيات RBAC

```mermaid
flowchart LR
    subgraph "المستخدم User"
        U1["admin<br/>(مسؤول فائق)"]
        U2["editor<br/>(محرر)"]
        U3["viewer<br/>(قراءة فقط)"]
    end

    subgraph "الدور Role"
        R1["super_admin<br/>معرف الصلاحية: *"]
        R2["editor<br/>معرف الصلاحية: get.*, post.*"]
        R3["viewer<br/>معرف الصلاحية: get.*"]
    end

    subgraph "الصلاحية Permission (شجرة)"
        P1["dashboard<br/>type=1 قائمة"]
        P2["user<br/>type=1 قائمة"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 زر"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (كل الصلاحيات)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "أنواع الصلاحيات"
        T1["type=1 قائمة<br/>التحكم في إظهار/إخفاء الشريط الجانبي"]
        T2["type=2 زر<br/>التحكم في أزرار عمليات الصفحة"]
        T3["type=3 API<br/>التحكم في الوصول إلى الواجهات"]
    end

    subgraph "تنسيق معرف الصلاحية"
        F1["{method}.{path}<br/>مثال: get.admin/user<br/>مثال: post.admin/user<br/>مثال: delete.admin/role"]
    end

    subgraph "مسار الحكم"
        J1["استخراج Token ← adminId"]
        J2["البحث عن أدوار المستخدم"]
        J3["تجميع كل معرفات الصلاحيات"]
        J4["بناء method.path"]
        J5{"مطابقة؟"}
        J6["السماح"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"نعم / slug=*"| J6
        J5 -->|لا| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. دورة حياة المعرّف الكاملة

```mermaid
flowchart LR
    subgraph "1. التوليد"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>مثال: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. التخزين"
        S1["جداول MySQL erik_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["الحقول الحساسة<br/>encryptable cast<br/>تشفير AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. النقل"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["سلسلة hashid<br/>مثال: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. فك الترميز العكسي"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. طبقات تشفير البيانات

```mermaid
flowchart TB
    subgraph "تشفير طبقة النقل (encryption)"
        E1["العميل يرسل بيانات حساسة"]
        E2["تشفير AES-256-CBC"]
        E3["نقل النص المشفر عبر API"]
        E4["فك التشفير والمعالجة على الخادم"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "تشفير طبقة التخزين (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["الكتابة: تشفير تلقائي"]
        D3["MySQL VARCHAR(500)<br/>تخزين النص المشفر"]
        D4["القراءة: فك تشفير تلقائي"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "إخفاء طبقة العرض (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. علاقات ER لقاعدة البيانات

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "مشفر"
        VARCHAR phone "مشفر"
        VARCHAR id_card "مشفر"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "حذف ناعم"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "مرجع ذاتي"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1قائمة 2زر 3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erik_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "جهة المصدر"
        TEXT input "إخفاء"
        DATETIME created_at
    }

    erik_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : "user_id"
    erik_admin_role ||--o{ erik_admin_user_role : "role_id"
    erik_admin_role ||--o{ erik_admin_role_permission : "role_id"
    erik_admin_permission ||--o{ erik_admin_role_permission : "permission_id"
    erik_admin_user ||--o{ erik_operation_log : "user_id"
    erik_admin_permission ||--o{ erik_admin_permission : "parent_id"
```

---

## 9. عملية تصدير الأعمال

```mermaid
sequenceDiagram
    participant C as العميل
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as نظام الملفات

    Note over C,FS: === تصدير Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: البيانات
    CTL->>CTL: فك تشفير الحقول الحساسة
    CTL->>CTL: معالجة الإخفاء (maskPhone/maskEmail)
    CTL->>CTL: بناء PhpSpreadsheet<br/>رأس أزرق بخلفية بيضاء<br/>حدود رفيعة لصفوف البيانات<br/>تجميد الصف الأول<br/>تصفية تلقائية
    CTL->>FS: كتابة runtime/tmp/export_*.xlsx
    CTL-->>C: تنزيل الملف

    Note over C,FS: === تصدير PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>الترويسة: عنوان + حقوق + وقت<br/>المحتوى: جدول أو بطاقات<br/>التذييل: حقوق غير قابلة للإزالة
    CTL->>CTL: عرض Dompdf A4 أفقي
    CTL->>FS: كتابة runtime/tmp/export_*.pdf
    CTL-->>C: تنزيل الملف
```

---

## 10. شجرة مكونات Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["نموذج تسجيل الدخول<br/>اسم المستخدم/كلمة المرور/الكابتشا"]
    LF --> CAPTCHA["مكون كابتشا النقر<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>وضع علامات النقر Circle"]

    DB --> SIDEBAR["الشريط الجانبي NavigationDrawer<br/>قابل للطي 64px / 240px<br/>لوحة المعلومات/المستخدمون/الأدوار/الإعدادات/السجلات"]
    DB --> HEADER["الشريط العلوي 56px<br/>زر الطي + قائمة المستخدم<br/>تسجيل الخروج AlertDialog"]
    DB --> CONTENT["منطقة المحتوى"]
    CONTENT --> DASH["DashboardPage<br/>بطاقات إحصائية GridView<br/>مخطط خطي للاتجاه LineChart<br/>مخطط دائري للتوزيع PieChart<br/>آخر العمليات ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. توجيه صفحات HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>الإقلاع"]
    EA -->|"بدون Token"| LP["LoginPage<br/>صفحة تسجيل الدخول"]
    EA -->|"مع Token"| DP["DashboardPage<br/>لوحة المعلومات"]

    LP -->|"نجاح تسجيل الدخول<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>قائمة المستخدمين"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>الصفحة الشخصية"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>تفاصيل المستخدم/إضافة/تعديل"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"تسجيل الخروج<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. بانوراما الدفاع الأمني المتعمق

```mermaid
flowchart TB
    subgraph "الطبقة 1: التحقق بين الإنسان والآلة"
        L1["كابتشا النقر<br/>Click Captcha<br/>إلزامية لتسجيل الدخول/التسجيل"]
    end

    subgraph "الطبقة 2: تأكيد العملية"
        L2["تأكيد كلمة المرور ثانية<br/>confirmPassword()<br/>إلزامية لعمليات DELETE"]
    end

    subgraph "الطبقة 3: أمان النقل"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "الطبقة 4: مصادقة الهوية"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "الطبقة 5: تفويض الصلاحيات"
        L5["RBAC<br/>دقة method.path<br/>المسؤول الفائق * "]
    end

    subgraph "الطبقة 6: حماية البيانات"
        L6["معرّفات الواجهة: تشفير Hashids<br/>جسم الطلب: تشفير Encryption<br/>طبقة التخزين: تشفير Encryptable<br/>التصدير: إخفاء + حقوق"]
    end

    subgraph "الطبقة 7: التدقيق والتتبع"
        L7["OperationLog<br/>تسجيل كل العمليات<br/>المستخدم/IP/الوقت/جهة المصدر/المعاملات"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. طوبولوجيا النشر

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "خادم الويب"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 ← 443 إعادة توجيه<br/>gzip on"]
        STA["الملفات الثابتة<br/>Flutter Web build/"]
    end

    subgraph "خوادم التطبيق (قابلة للتوسع أفقيًا)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "طبقة البيانات"
        MYSQL["MySQL 8.0<br/>نسخ رئيسي-تبعي<br/>بادئة erik_"]
        ES["Elasticsearch 8.x<br/>عنقود من 3 عقد<br/>بادئة erik_"]
        REDIS["Redis 7.x<br/>وضع الحارس<br/>poster:captcha:*"]
    end

    subgraph "المراقبة"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```

---

## 14. البنية الشاملة لنظام ERP

```mermaid
graph TB
    subgraph Client["طبقة العميل"]
        FW["Flutter Web<br/>لوحة إدارة PC"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>تطبيق هارموني أصلي"]
    end

    subgraph Gateway["طبقة بوابة API"]
        MW["سلسلة الوسائط<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["طبقة الوحدات التجارية"]
        direction LR
        Admin["إدارة النظام<br/>المستخدمون/الأدوار/الصلاحيات/الإعدادات/السجلات"]
        Product["إدارة المنتجات<br/>منتج/تصنيف/علامة تجارية/مستودع/مورد/عميل"]
        Purchase["إدارة المشتريات<br/>طلب←أمر←استلام←إرجاع←تسوية"]
        Sales["إدارة المبيعات<br/>عرض سعر←أمر←شحن←إرجاع←تسوية"]
        Inventory["إدارة المخزون<br/>إدخال/إخراج/دفعات/جرد/تحويل/تنبيهات"]
        Finance["الإدارة المالية<br/>حسابات/سندات/مستحقات/دفتر عام/دفتر فرعي/تقارير/مصاريف"]
        CRM["CRM<br/>عميل/جهة اتصال/متابعة/قمع/بركة عامة/عرض سعر/عقد"]
        Workflow["سير عمل الموافقات<br/>تعريف سير العمل/تقديم/موافقة/رفض/سحب"]
        Notification["إشعارات الرسائل<br/>قائمة الإشعارات/مقروء/عدد غير المقروء"]
        Project["إدارة المشاريع<br/>مشروع/مهمة/تسجيل ساعات العمل"]
        HR["الموارد البشرية<br/>قسم/موظف/منصب/حضور/إجازة/راتب"]
        Manufacturing["التصنيع<br/>BOM/أمر إنتاج/مسار تشغيلي/محطة عمل/MRP"]
        Report["تقارير مخصصة<br/>قالب تقرير/مجموعة بيانات/حقل/فلتر/جدولة"]
    end

    subgraph Service["طبقة خدمات الأعمال"]
        IS["InventoryService<br/>إدخال/إخراج + تكلفة المتوسط المتحرك المرجح"]
        FS["FinanceService<br/>توليد تلقائي للمستحقات + مقاصة"]
        NS["NotificationService<br/>إرسال موحد للإشعارات"]
    end

    subgraph Data["طبقة البيانات"]
        MySQL["MySQL 8.0<br/>163 جدول أعمال"]
        Redis["Redis 7<br/>تخزين مؤقت/تحديد معدل/جلسة"]
        ES["Elasticsearch 8<br/>بحث نصي كامل"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. تدفق البيانات عبر الوحدات

```mermaid
sequenceDiagram
    participant PO as استلام المشتريات
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as جدول المخزون
    participant COST as سجلات التكلفة
    participant ARAP as المستحقات/الذمم

    PO->>IS: stockIn(منتج,كمية,سعر وحدة)
    IS->>INV: تحديث المخزون الفوري (مع قفل)
    IS->>COST: إعادة حساب تكلفة المتوسط المتحرك المرجح
    IS-->>PO: إرجاع معرّف السجل

    PO->>FS: createAp(مورد,مبلغ)
    FS->>ARAP: توليد سجل ذمم

    Note over PO,ARAP: شحن المبيعات بالمثل: stockOut + createAr
```

---

## 16. تدفق بيانات احتساب تكلفة المخزون

```mermaid
graph LR
    A[استلام مشتريات 100يوان×10] --> B[سجل الإدخال]
    C[استلام مشتريات 130يوان×20] --> D[سجل الإدخال]
    B --> E[المخزون: 10, التكلفة 100]
    D --> F[المخزون: 30, التكلفة 120]
    E --> G[المتوسط المتحرك المرجح: 100]
    F --> H[المتوسط المتحرك المرجح: 120]
    H --> I[الإخراج يُحتسب بتكلفة 120]
```

---

## 17. تدفق بيانات سير عمل الموافقات

```mermaid
sequenceDiagram
    participant Biz as وحدة الأعمال
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as محرك سير العمل
    participant NTF as NotificationService

    Biz->>WF: تقديم للموافقة (رقم مستند الأعمال, نوع الوحدة)
    WF->>WFE: مطابقة تعريف سير العمل ← إنشاء مثيل موافقة
    WFE->>APR: إخطار معتمد العقدة الأولى
    APR->>NTF: إرسال إشعار الموافقة
    NTF-->>APR: تم إرسال الإشعار
    APR->>APR: موافقة/رفض المعتمد
    alt موافقة
        APR->>WFE: الانتقال إلى العقدة التالية
        alt مرور كل العقد
            WFE->>Biz: استدعاء رجعي: الموافقة ناجحة, تحديث حالة مستند الأعمال
        end
    else رفض
        WFE->>Biz: استدعاء رجعي: تم رفض الموافقة
    end
```

---

## 18. تدفق بيانات إشعارات الرسائل

```mermaid
sequenceDiagram
    participant Event as مصدر تنشيط الحدث
    participant NS as NotificationService
    participant DB as جدول الإشعارات
    participant User as المستخدم

    Event->>NS: تنشيط إشعار (نوع,عنوان,محتوى,مستلم)
    NS->>DB: كتابة سجل الإشعار
    NS-->>User: دفع (رسالة داخلية/WebSocket)
    User->>NS: وضع علامة مقروء
    NS->>DB: تحديث حالة القراءة
    User->>NS: الاستعلام عن عدد غير المقروء
    NS-->>User: عدد غير المقروء
```

---

## 19. تدفق بيانات تخطيط متطلبات المواد MRP

```mermaid
sequenceDiagram
    participant SO as أمر المبيعات
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as اقتراح شراء
    participant MO as اقتراح إنتاج

    SO->>MRP: متطلبات أمر المبيعات
    MRP->>BOM: توسيع BOM للحصول على قائمة المواد
    BOM-->>MRP: المواد + الاستهلاك القياسي
    MRP->>INV: الاستعلام عن الكمية المتاحة بالمخزون
    INV-->>MRP: كمية المخزون
    MRP->>MRP: حساب صافي الاحتياج = الاحتياج الإجمالي - المخزون
    alt نقص المواد الخام
        MRP->>PO: توليد اقتراح شراء
    else نقص المنتجات نصف المصنعة
        MRP->>MO: توليد اقتراح إنتاج
    end
```

---

## 20. جدول تعيين وحدات التحكم-الخدمات-النماذج في ERP

> ملاحظة طبقة الخدمات: عمود `الخدمة الأساسية` يوضح الخدمات التجارية التي تم إنزالها لهذه الوحدة؛ الوحدات المعلمة بـ **⚠ وحدة التحكم تستعلم النماذج مباشرة، دين تقني معروف**،
> لا تزال وحدات التحكم تستدعي طرق الاستعلام/الكتابة في النماذج مباشرة (`XxxModel::find/where/save` وغيرها)، ولم يتم استخراج طبقة الخدمات بعد، وهي دين تقني معروف،
> وسيتم تقليصها تدريجيًا لاحقًا وفق نمط الاستخراج الخفيف لطبقة الخدمات P2-F2 (`app/service/AbstractCrudService` فئة أساسية عامة للـ CRUD + خدمة الوحدة).

| الوحدة | وحدات التحكم (الدليل) | الخدمة الأساسية | النماذج الرئيسية | عدد الجداول |
|------|-------------------|-------------|-----------|------|
| إدارة النظام | admin/controller/ (14) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | AdminUser, AdminRole, AdminPermission | 7 |
| إدارة المنتجات | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| إدارة المشتريات | controller/purchase/ (5) | InventoryService, FinanceService ⚠CRUD ما زال استعلامًا مباشرًا، دين تقني معروف | PurchaseOrder, PurchaseReceive | 9 |
| إدارة المبيعات | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD ما زال استعلامًا مباشرًا، دين تقني معروف | SalesOrder, SalesDelivery | 9 |
| إدارة المخزون | controller/inventory/ (5) | InventoryService ⚠CRUD ما زال استعلامًا مباشرًا، دين تقني معروف | Inventory, InventoryFlow, CostRecord | 11 |
| الإدارة المالية | controller/finance/ (20) | FinanceService ⚠CRUD ما زال استعلامًا مباشرًا، دين تقني معروف | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| سير عمل الموافقات | controller/workflow/ (2) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| إشعارات الرسائل | controller/notification/ (1) | NotificationService ⚠CRUD ما زال استعلامًا مباشرًا، دين تقني معروف | Notification, NotificationSetting, NotificationTemplate | 3 |
| إدارة المشاريع | controller/project/ (3) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| الموارد البشرية | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| التصنيع | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| التقارير المخصصة | controller/report/ (2) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| إدارة المعدات EAM | controller/eam/ (4) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| إدارة المستندات DMS | controller/dms/ (2) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| لوحات BI | controller/bi/ (3) | - ⚠استعلام مباشر للنماذج، دين تقني معروف | BiDashboard, BiWidget | 2 |

### 20.1 سجل الاستخراج الخفيف لطبقة الخدمات P2-F2 (اكتمل الاستخراج لـ crm/hr/manufacturing/product)

| الوحدة | عدد استدعاءات الاستعلام المباشر قبل الاستخراج | بعد الاستخراج | الخدمة الجديدة | محتوى الاستخراج |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | CRUD عام + انتقال حالة العقد، عرض السعر ← عقد، استلام/تحرير البركة العامة، إسناد/حل/رد التذاكر، تنظيف متتالي للتفاصيل، بناء بيانات التقارير التحليلية |
| الموارد البشرية | 38 | 0 | `app/service/hr/HrService.php` | CRUD عام + تحديد تأخر الحضور/الخروج المبكر، موافقات الإجازات (توليد حضور/إجازات تلقائيًا)، تفرد الرواتب/احتساب صافي الراتب/الصرف/التوليد الجماعي |
| التصنيع | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | CRUD عام + انتقال بدء/إكمال أوامر العمل، نسخ إصدار BOM/استبعاد التفعيل، توليد تفاصيل MRP |
| إدارة المنتجات | 29 | 0 | `app/service/product/ProductService.php` | CRUD عام + إنشاء معاملات المنتج (SKU/السعر)، تحديث محتفظ بقيمة الحقل الأصلية، تحميل الارتباطات التفصيلية |

نمط الاستخراج: يوفر `app/service/AbstractCrudService.php` عمليات CRUD العامة `list/all/find/create/update/delete/deleteWhere`
ومساعدي المنطق الخالص `normalizePageParams/canTransition`؛ خدمات الوحدات ترث منه وترسّب منطق الأعمال الخاص بالوحدة.
تقوم وحدات التحكم بحقن الخدمات عبر `Container::get(XxxService::class)` (مع تراجع class_exists)، مع الحفاظ التام على بنية المسارات/المعاملات/الاستجابات؛
تبقى اهتمامات HTTP مثل ترميز وفك ترميز hashid وتأكيد كلمة المرور الثانية وتغليف الاستجابة في وحدات التحكم.
الخدمات الجديدة مسجلة في `config/dependence.php` (ملف تكوين ميت، لا يُحمَّل عبر addDefinitions، ويعتمد حاوية التشغيل على
تراجع class_exists للإنشاء، لذا تبقى جميع الخدمات بمنشئ بلا معاملات).

الوحدات غير المستخرجة (إدارة المشاريع 18 مرة، التقارير المخصصة 18 مرة، المشتريات 24 مرة، المبيعات 24 مرة، إدارة النظام 42 مرة وغيرها) معلمة في الجدول
"استعلام مباشر للنماذج، دين تقني معروف"، وسيتم استخراجها في التكرارات اللاحقة وفق النمط نفسه.

---

## وحدات التوسعة OMS/WMS/TMS (2026-08)

### OMS (نظام إدارة الطلبات) — 8 جداول
- **توسعة الطلبات** (`erik_oms_order`): تجميع متعدد القنوات/حالة التنفيذ/حالة الدفع/الأولوية
- **عناوين الطلبات** (`erik_oms_order_address`): عناوين الاستلام/الفوترة (تنسيقات متعددة الدول)
- **سجلات التنفيذ** (`erik_oms_fulfillment`+`_item`): تتبع كميات التخصيص/الانتقاء/التغليف/الشحن
- **RMA** (`erik_oms_rma`+`_item`): دورة حياة كاملة للإرجاع والاستبدال
- **حجز المخزون** (`erik_oms_inventory_reservation`): ATP = physical - reserved
- **القنوات** (`erik_channel`): direct/marketplace/edi/pos

### WMS (نظام إدارة المستودعات) — 12 جدولًا
- **المناطق والمواقع** (`erik_wms_zone`, `erik_wms_location`): zone→aisle→rack→level→bin
- **الإدخال** (`erik_wms_asn`+`_item`, `erik_wms_receiving`, `erik_wms_putaway_task`+`_item`)
- **الإخراج** (`erik_wms_wave`+`wave_order`, `erik_wms_pick_task`+`_item`, `erik_wms_pack_task`)

### TMS (نظام إدارة النقل) — 7 جداول
- **الناقلون** (`erik_tms_carrier`+`carrier_service`, `erik_tms_freight_rate`)
- **بوليصة الشحن** (`erik_tms_shipment`+`_package`, `erik_tms_tracking_event`)
- **الفواتير** (`erik_tms_freight_invoice`)

### تدفق البيانات
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. خارطة طريق النظام البيئي (2026-08)

> مواصفات التصميم التفصيلية: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 التقييم الأساسي (عند إطلاق خارطة الطريق)

> تم تسليم P0~P3 بالكامل، والنتيجة الشاملة الحالية 89/100 (انظر CLAUDE.md)؛ الجدول التالي هو لقطة الأساس قبل إطلاق خارطة الطريق.

| البعد | النتيجة | الفجوة الرئيسية |
|------|------|----------|
| API الخلفية | 85/100 | وحدات متعددة هي هياكل CRUD، تفتقر إلى محركات احتساب الأعمال |
| الحماية الأمنية | 95/100 | دفاع متعمق من 18 طبقة، جاهز للإنتاج |
| واجهة المستخدم | 20/100 | **أكبر قصور**: 12 صفحة Flutter تغطي ~20% من الوحدات، لوحة إدارة الويب مفقودة |
| بيئة التشغيل | 70/100 | ينقصها تراجع الهجرات والنسخ الاحتياطي التلقائي وقابلية المراقبة |
| عمق الأعمال | 55/100 | الخوارزميات الأساسية للمالية/HR/التصنيع غير منفذة |
| **الإجمالي** | **65/100** | |

### 21.2 خارطة طريق متسلسلة بأربع مراحل

```
P0(3-4 أسابيع) → P1(4-6 أسابيع) → P2(1-2 أسبوعين) → P3(2-3 أسابيع) = إجمالي حوالي 13 أسبوعًا
```

| المرحلة | الاسم | التسليمات الأساسية |
|------|------|----------|
| **P0** | بيئة الواجهة | لوحة إدارة Flutter Web لجميع الوحدات (14 وحدة 40+ صفحة)، مكتبة مكونات عامة، محاذاة HarmonyOS |
| **P1** | عمق الأعمال | محرك القيد المزدوج، محرك احتساب الرواتب، محرك MRP، وحدة إدارة الجودة، إشعارات فورية (WebSocket) |
| **P2** | موثوقية التشغيل | تراجع هجرات قاعدة البيانات، تعزيز النسخ الاحتياطي التلقائي، تتبع OpenTelemetry، قيادة قوائم انتظار RabbitMQ |
| **P3** | تحسين التجربة | لوحات BI بالسحب والإفلات، إدارة المعدات (EAM)، عزل متعدد المستأجرين، إدارة المستندات (DMS) |

### 21.3 تطور سلسلة الوسائط

```
الوضع الحالي: Locale → Cors → SecurityFilter → RateLimit → TracingId → {مجموعة التوجيه}
بعد P1:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {مجموعة التوجيه}
بعد P2:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {مجموعة التوجيه}
بعد P3:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {مجموعة التوجيه}
```

### 21.4 البنية المستهدفة لـ P0 — لوحة إدارة Flutter Web

| الطبقة | المحتوى الجديد |
|------|----------|
| طبقة التخطيط | `AdminLayout` تخطيط PC ثلاثي الأعمدة (شريط جانبي قابل للطي + شريط علوي + منطقة محتوى) |
| طبقة المكونات | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| طبقة الصفحات | التوسع من 12 صفحة حاليًا إلى تغطية كاملة لـ 14 وحدة 40+ صفحة |
| طبقة الخدمات | إعادة استخدام `ApiService`, `AuthService`, `CaptchaService`, `ExportService` الحالية |

### 21.5 البنية المستهدفة لـ P1 — محركات احتساب الأعمال

| المحرك | فئة الخدمة | القواعد الرئيسية |
|------|--------|----------|
| القيد المزدوج | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | تحقق إلزامي من توازن المدين/الدائن، ترحيل أرباح وخسائر نهاية الفترة، تحويل أسعار الصرف متعدد العملات |
| احتساب الرواتب | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | حدود أساس التأمين الاجتماعي، نسب صندوق الإسكان، الضريبة التصاعدية على الدخل، الصرف عبر البنك |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | توسيع BOM طبقة بطبقة + الهالك، رمز الطبقة الدنيا (LLC)، مخزون الأمان، قواعد الدفعات |
| الجودة | `QmsInspectionService` | انتقال ثلاثي لـ IQC للمواد/IPQC للعمليات/OQC للشحن |
| الإشعارات | `WebSocketService`, `ChannelRouter` | قنوات متعددة: داخلية/بريد/وي تشات الأعمال/دينغ توك |

### 21.6 ملخص تغييرات نموذج البيانات

| المرحلة | عدد الجداول الجديدة | الوحدات المعنية |
|------|----------|------|
| P0 | 0 | واجهة فقط، بدون تغييرات على الجداول |
| P1 | 14 | مالية(2) + HR(3) + تصنيع(2) + جودة(5) + إشعارات(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. تعدد المستأجرين (قدرة محجوزة، غير مفعلة)

> إعلان حقوق النشر كما في ترويسة الملف: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 التموضع والقرار

تعدد المستأجرين في هذا المشروع هو **قدرة محجوزة**، **غير موصَّل وغير مفعَّل** في هذه المرحلة (تخفيض موثق). بما يتسق مع التخطيط:
الحلول التجارية الكاملة لتعدد المستأجرين مثل فوترة SaaS والتفعيل الذاتي للمستأجرين ليست ضمن نطاق هذا المشروع؛ في هذه المرحلة لا يُحتفظ إلا
بهيكل كود أدنى (وسيط + trait نموذج) مع خطوات التفعيل، للتفعيل حسب الحاجة لاحقًا.
ملاحظة: «عزل تعدد المستأجرين» في P3 من §21.2 يُعدَّل بناءً على ذلك إلى «قدرة محجوزة (تخفيض موثق)»، مع الاحتفاظ بالهيكل دون توصيل.

أساس القرار (مراجعة 2026-08):
- جميع عمليات النشر الحالية تقريبًا أحادية المستأجر، والتوصيل سيُدخل تعقيد عزل غير ضروري ومخاطر انحدار؛
- الهيكل الحالي يعاني عيوبًا تقنية (انظر 22.4)، و«التوصيل يعني العزل» غير صحيح، يجب إصلاح التصميم أولًا؛
- يتطلب العزل إضافة أعمدة لكل جدول أعمال من الجداول الـ 163 وتفعيلها لكل نموذج، والتكلفة تتجاوز بكثير «الحد الأدنى من التوصيل».

### 22.2 الوقائع الحالية (التحقق من الكود والإعدادات)

| البند | الوضع الحالي |
|----|------|
| `app/middleware/TenantScope.php` | موجود، غير مسجل؛ يقرأ المستأجر من رأس `X-Tenant-Id`، ويمرر مباشرة عند غياب الرأس |
| `app/model/concerns/TenantScope.php` | موجود، لا تستخدمه أي نماذج؛ `bootTenantScope()` نطاق عالمي لا يفلتر إلا بعد تعيين المستأجر |
| `config/middleware.php` | السلسلة العامة: Locale → Cors → SecurityFilter → RateLimit → TracingId، بدون TenantScope |
| `config/route.php` مجموعة /admin | AdminAuth → AdminPermission → OperationLog، بدون TenantScope |
| حمولة JWT | `sub` / `username` / `token_type` فقط، **بدون ادعاء tenant_id** (`app/api/v1/controller/AuthController.php`) |
| قاعدة البيانات | **لا توجد أي أعمدة tenant_id في القاعدة** (ولا في install.sql) |
| النماذج | **لا يستخدم أي نموذج trait TenantScope** |

### 22.3 خطوات التفعيل (مرجع محجوز، لا تنفَّذ في هذه المرحلة)

1. تسجيل الوسيط: إضافة `app\middleware\TenantScope::class` في `middleware()` لمجموعة /admin في `config/route.php`
   (وضعه بعد AdminAuth، لضمان المصادقة).
2. يحمل الطالب رأس `X-Tenant-Id` (int معرف المستأجر) في ترويسة الطلب.
3. إضافة عمود `tenant_id` (BIGINT + فهرس) لجداول الأعمال التي تحتاج العزل وإعادة ملء البيانات القائمة؛
   جداول القواميس/النظام (مثل `erik_admin_user` و`erik_role` و`erik_permission`) لا تُعزل.
4. في فئات النماذج التي تحتاج العزل: `use app\model\concerns\TenantScope;`، فيتم الفلترة تلقائيًا حسب المستأجر الحالي.
5. (اختياري) إذا أردت أخذ المستأجر من JWT بدل الترويسة: توسيع حمولة إصدار تسجيل الدخول بإضافة ادعاء `tenant_id`،
   والقراءة من `$payload['tenant_id']` في الوسيط.

### 22.4 القيود التقنية المعروفة (يجب حلها قبل التفعيل)

- **انقطاع سلسلة النقل الثابتة (مُختبَر فعليًا على PHP 8.3)**: الوسيط يستدعي `setCurrentTenantId()` عبر اسم trait
   ويكتب نسخة ثابتة خاصة بالـ trait نفسه، ولا تستطيع فئات النماذج التي تستخدم الـ trait قراءتها، ولن تُفلتر الاستعلامات.
   عند التفعيل يجب التحول إلى الحقن المستند إلى سياق الطلب (مثل `request()->tenantId`).
- **تداخل الحالة العامة الثابتة**: Workerman عملية مقيمة، الخصائص الثابتة مشتركة عبر الطلبات؛ إذا فُعِّل وضع coroutine
   (Swoole/Swow) سيحدث تداخل بيانات عبر المستأجرين، يجب التحول إلى الربط على مستوى الطلب (`context()` / كائن الطلب).
- **فجوة مستوى البيانات**: لا توجد أعمدة tenant_id في القاعدة، يتطلب ترحيلًا جدولًا بجدول؛ جدول القواميس المشتركة عبر المستأجرين يحتاج آلية استثناء مصممة.

### 22.5 معايير القبول

قبول هذه المرحلة = توافق المستندات مع الكود: `config/middleware.php` و`config/route.php` لا يحتويان على
تسجيل TenantScope؛ التعليقات في الوسيط والـ trait تشير بوضوح إلى «قدرة محجوزة، غير مفعلة» مع خطوات التفعيل؛
هذا القسم يتطابق بندًا ببند مع الوضع الحالي للكود.
