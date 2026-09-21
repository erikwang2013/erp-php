# আর্কিটেকচার ডিজাইন ডায়াগ্রাম ও বিজনেস লজিক ডায়াগ্রাম

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> নিম্নলিখিত Mermaid চার্ট GitHub / GitLab / VS Code-এ স্বয়ংক্রিয়ভাবে রেন্ডার হয়। অন্যান্য পরিবেশে দেখতে [Mermaid Live Editor](https://mermaid.live/) ব্যবহার করুন।

---

## 1. সিস্টেম টপোলজি আর্কিটেকচার

```mermaid
flowchart TB
    subgraph "ক্লায়েন্ট লেয়ার"
        A1["Flutter Web<br/>PC ম্যানেজমেন্ট ব্যাকএন্ড<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>মোবাইল/ট্যাবলেট ক্লায়েন্ট"]
        A3["Angular 22 + ng-zorro<br/>Web ম্যানেজমেন্ট ব্যাকএন্ড"]
        A4["React 19 + Vite<br/>Web ম্যানেজমেন্ট ব্যাকএন্ড"]
    end

    subgraph "গেটওয়ে/এজ লেয়ার (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>রিভার্স প্রক্সি + HTTPS + Gzip<br/>স্ট্যাটিক ফাইল সার্ভিস"]
    end

    subgraph "অ্যাপ্লিকেশন লেয়ার (webman v2)"
        C_LOC["I18n::getLocale()<br/>Accept-Language পার্স · ১৩টি ভাষা"]
        C0["পাথ-ভিত্তিক ভার্সনিং<br/>/api/v1 · /admin/v1（কোনো ভার্সন হেডার নেই）"]
        C1["AdminAuth মিডলওয়্যার<br/>JWT যাচাই"]
        C2["AdminPermission মিডলওয়্যার<br/>RBAC পারমিশন যাচাই"]
        C3["অ্যাডমিন কন্ট্রোলার<br/>Dashboard / User / Role / Permission"]
        C4["পাবলিক কন্ট্রোলার v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "স্টোরেজ লেয়ার"
        D1[("MySQL 8.0<br/>মূল স্টোরেজ<br/>টেবিল প্রিফিক্স erp_")]
        D2[("Elasticsearch<br/>ফুল-টেক্সট সার্চ<br/>ইনডেক্স প্রিফিক্স erp_")]
        D3[("Redis<br/>Session / ক্যাশ<br/>Captcha স্টোরেজ")]
    end

    subgraph "বাহ্যিক"
        E1["DevEco Studio<br/>HarmonyOS বিল্ড"]
        E2["Flutter SDK<br/>Web বিল্ড"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A3 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A4 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
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
    style A3 fill:#1677FF,color:#fff
    style A4 fill:#1677FF,color:#fff
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

## 2. ব্যাকএন্ড লেয়ারড আর্কিটেকচার

```mermaid
flowchart TD
    subgraph "রাউট লেয়ার Route Layer"
        R1["config/route.php<br/>URL → Controller ম্যাপিং"]
    end

    subgraph "মিডলওয়্যার লেয়ার Middleware Layer"
        M_CR["Cors<br/>ক্রস-অরিজিন হ্যান্ডলিং / OPTIONS প্রি-ফ্লাইট"]
        M_SF["SecurityFilter<br/>অ্যাটাক ডিটেকশন ইন্টারসেপ্ট<br/>XSS/SQL ইনজেকশন/পাথ ট্রাভার্সাল/CSRF"]
        M_RL["RateLimit<br/>Redis স্লাইডিং উইন্ডো রেট লিমিট<br/>X-RateLimit রেসপন্স হেডার"]
        M_TID["TracingId<br/>X-Trace-Id জেনারেট<br/>পুরো চেইন জুড়ে"]
        M1["AdminAuth<br/>JWT Token যাচাই<br/>adminId ইনজেক্ট"]
        M2["AdminPermission<br/>RBAC অনুমোদন<br/>method.path ম্যাচিং<br/>Redis 60s ক্যাশ পারমিশন"]
    end

    subgraph "কন্ট্রোলার লেয়ার Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + সার্চ + পেজিনেশন"]
        CT3["RoleController<br/>CRUD + পারমিশন সিঙ্ক"]
        CT4["PermissionController<br/>CRUD + ট্রি নির্মাণ"]
        CT5["DashboardController<br/>স্ট্যাটিস্টিক্স/ট্রেন্ড/ডিস্ট্রিবিউশন"]
        CT6["ExportController<br/>Excel/PDF এক্সপোর্ট"]
        CT7["CaptchaController<br/>ক্যাপচা জেনারেশন/যাচাই"]
        CT8["AuthController<br/>লগইন/রেজিস্ট্রেশন/রিফ্রেশ"]
    end

    subgraph "সার্ভিস লেয়ার Service Layer"
        S1["HashidsService<br/>ID এনকোড/ডিকোড"]
        S2["SnowflakeService<br/>গ্লোবাল ইউনিক ID জেনারেশন"]
        S3["EncryptionService<br/>এনক্রিপ্ট/ডিক্রিপ্ট + ডিসেনসিটাইজেশন"]
        M_LOC["I18n::getLocale()<br/>Accept-Language পার্স（মিডলওয়্যার নয়）<br/>১৩টি ভাষা zh_CN/en/ja/ko/de<br/>fr/es/pt/ru/ar/hi/bn/id"]
    end

    subgraph "মডেল লেয়ার Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ড্রাইভার লেয়ার Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_CR --> M_SF --> M_RL --> M_TID
    M_TID --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M_TID --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_LOC fill:#13C2C2,color:#fff
    style M_CR fill:#2F54EB,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M_TID fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

### ERP বিজনেস লেয়ার এক্সটেনশন

সিস্টেম বিশুদ্ধ ম্যানেজমেন্ট ব্যাকএন্ড থেকে সম্পূর্ণ ERP সিস্টেমে বিবর্তিত হওয়ায়, কন্ট্রোলার লেয়ার ও সার্ভিস লেয়ারে নিম্নলিখিত বিজনেস মডিউল যুক্ত হয়েছে:

| লেয়ার | ডিরেক্টরি | ব্যাখ্যা |
|------|------|------|
| বিজনেস কন্ট্রোলার | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report,oms,wms,tms,quality,eam,dms,open,platform,print,retail,bi}/` | ১৩৯টি (২৩টি ব্যবসায়িক ডোমেইন, এছাড়া টপ-লেভেল Install / Index), মডিউল অনুযায়ী বিভক্ত, বিজনেস রিকোয়েস্ট প্রসেস করে |
| বিজনেস সার্ভিস | `app/service/{finance,inventory,notification,crm,hr,manufacturing,oms,wms,tms,quality,…}/` | ৬৩টি সার্ভিস ক্লাস / ৬৪টি ফাইল / ২০টি মডিউল সাব-ডিরেক্টরি; ইনভেন্টরি ইন-আউট+খরচ হিসাব、ফাইন্যান্স AR/AP+নিষ্পত্তি、নোটিফিকেশন পাঠানো সহ |

### আন্তর্জাতিকীকরণ (১৩টি ভাষা)

ভাষা নির্ধারণ হয় `app/common/I18n.php`-এর `getLocale()`-এ (`I18n::trans()` থেকে কল হয়, **এটি মিডলওয়্যার নয়**): রিকোয়েস্ট হেডার `Accept-Language`-এর প্রথম ট্যাগ নেওয়া হয়, তারপর প্রধান ভাষার সাব-ট্যাগ ম্যাপ করা হয় (`zh*` → `zh_CN`)। ফ্রন্টএন্ড ও ব্যাকএন্ডের অভিধানের ভূমিকা:

| প্রান্ত | অভিধানের অবস্থান | পরিসর | জেনারেটর |
|----|----------|------|--------|
| ব্যাকএন্ড | `resource/translations/<locale>/{common,modules,validation}.php` | ১৩টি ভাষা ডিরেক্টরি; `zh_CN` ৫৬৫টি, বাকি ১১টি ভাষায় প্রতিটি ৫৪৪টি, `en` ৩০টি (লিফ এন্ট্রির গণনা, `validation.php`-এর `attributes` ফিল্ড লেবেল গণনায় ধরা হয়, তার গ্রুপ কী ধরা হয় না) | `scripts/gen-be-locales.mjs` |
| Angular | সোর্স `apps/angular/src/app/core/zh-en/part1..4.ts` → প্রোডাক্ট `core/zh-<code>.ts` | সোর্স অভিধানে ১৪৫৬টি এন্ট্রি | `scripts/gen-fe-locales.mjs --app angular` |
| React | সোর্স `apps/react/src/lib/i18n/zhEn.ts` → প্রোডাক্ট `lib/i18n/zh<Code>.ts` | সোর্স অভিধানে ১৪৫১টি এন্ট্রি | `scripts/gen-fe-locales.mjs --app react` |

- ভাষা: `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`।
- ব্যাকএন্ডে "ইংরেজিই key": `en`-এর common/modules ফাঁকা; `validation.php`-এর কী হলো ফ্রেমওয়ার্কের রুলের নাম, কেবল ভ্যালু অনূদিত হয়।
- ফ্রন্টএন্ডে ১১টি নতুন ভাষার অভিধান প্রতিটি `import()`-এ ডাইনামিকভাবে আলাদা chunk হিসেবে লোড হয়, এন্ট্রি না থাকলে চীনা মূল টেক্সটে ফিরে যায়।
- ভাষা বদলালেই `Accept-Language` বদলায়, ব্যাকএন্ড ভাষা অনুযায়ী টেক্সট ফেরত দেয় (`app/common/I18n.php` + `config/translation.php`)।

---

## 3. রিকোয়েস্ট লাইফসাইকেল

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
    participant N as Nginx
    participant MW_CR as Cors
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW_TID as TracingId
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: HTTPS রিকোয়েস্ট<br/>পাথ /api/v1 বা /admin/v1（কোনো ভার্সন হেডার নেই）
    N->>MW_CR: ফরোয়ার্ড
    MW_CR->>MW_CR: OPTIONS প্রি-ফ্লাইট প্রসেস<br/>CORS রেসপন্স হেডার ইনজেক্ট
    MW_CR->>MW_SF: পাস

    alt নন-স্ট্যান্ডার্ড HTTP মেথড (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else মেথড বৈধ (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: মেথড হোয়াইটলিস্ট চেক পাস
    end

    alt অ্যাটাক ডিটেকশন ট্রিগার
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: পাস

    alt রেট লিমিট ট্রিগার
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW_TID: পাস
    MW_TID->>MW_TID: X-Trace-Id জেনারেট<br/>রেসপন্স হেডারে ইনজেক্ট
    MW_TID->>MW1: পাস

    alt Token অনুপস্থিত বা অবৈধ
        MW1-->>C: 401 Unauthorized
    else Token বৈধ
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt অনুমতি নেই
        MW2-->>C: 403 Forbidden
    else অনুমতি আছে
        MW2->>CTL: কন্ট্রোলারে প্রবেশ
    end

    CTL->>CTL: প্যারামিটার যাচাই (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt সংবেদনশীল অপারেশন (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt পাসওয়ার্ড ভুল
            CTL-->>C: 422 পাসওয়ার্ড যাচাই ব্যর্থ
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast স্বয়ংক্রিয় ডিক্রিপশন
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: রেসপন্স JSON নির্মাণ
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: অপারেশন লগ রেকর্ড (POST/PUT/DELETE)
```

---

## 4. অথ ও ক্যাপচা ফ্লো

```mermaid
sequenceDiagram
    participant U as ইউজার
    participant CL as ক্লায়েন্ট
    participant SV as সার্ভার
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === ধাপ ১: ক্যাপচা প্রাপ্তি ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 ব্যাকগ্রাউন্ড ইমেজ জেনারেট
    CAP->>CAP: Nটি চীনা টার্গেট এলোমেলোভাবে বসানো
    CAP->>CAP: key জেনারেট, targets স্টোর
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === ধাপ ২: ইউজার ক্লিক ===
    CL->>CL: ক্যাপচা ইমেজ রেন্ডার
    CL->>CL: প্রম্পট "অনুগ্রহ করে ক্রমানুসারে ক্লিক করুন: গাছ → পাখি → ফুল"
    U->>CL: ছবিতে লেখার অবস্থানে পর্যায়ক্রমে ক্লিক
    CL->>CL: clicks সংগ্রহ: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === ধাপ ৩: লগইন ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt ক্যাপচা ভুল
        CAP-->>SV: false
        SV-->>CL: 422 ক্যাপচা ভুল
    else ক্যাপচা সঠিক
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt ক্রেডেনশিয়াল ভুল
            SV-->>CL: 401 ইউজারনেম বা পাসওয়ার্ড ভুল
        else ক্রেডেনশিয়াল সঠিক
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === পরবর্তী রিকোয়েস্ট ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC পারমিশন মডেল

```mermaid
flowchart LR
    subgraph "ইউজার User"
        U1["admin<br/>(সুপার অ্যাডমিন)"]
        U2["editor<br/>(সম্পাদক)"]
        U3["viewer<br/>(শুধু-পঠন)"]
    end

    subgraph "রোল Role"
        R1["super_admin<br/>পারমিশন চিহ্ন: *"]
        R2["editor<br/>পারমিশন চিহ্ন: get.*, post.*"]
        R3["viewer<br/>পারমিশন চিহ্ন: get.*"]
    end

    subgraph "পারমিশন Permission (ট্রি)"
        P1["dashboard<br/>type=1 মেনু"]
        P2["user<br/>type=1 মেনু"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 বাটন"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (সম্পূর্ণ পারমিশন)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "পারমিশন টাইপ"
        T1["type=1 মেনু<br/>সাইডবার প্রদর্শন/লুকানো নিয়ন্ত্রণ"]
        T2["type=2 বাটন<br/>পেজ অপারেশন বাটন নিয়ন্ত্রণ"]
        T3["type=3 API<br/>ইন্টারফেস অ্যাক্সেস নিয়ন্ত্রণ"]
    end

    subgraph "পারমিশন চিহ্ন ফরম্যাট"
        F1["{method}.{path}<br/>উদাহরণ: get.admin/user<br/>উদাহরণ: post.admin/user<br/>উদাহরণ: delete.admin/role"]
    end

    subgraph "নির্ণয় ফ্লো"
        J1["Token এক্সট্রাক্ট → adminId"]
        J2["ইউজার রোল খোঁজ"]
        J3["সব পারমিশন slug সংগ্রহ"]
        J4["method.path গঠন"]
        J5{"ম্যাচ?"}
        J6["ছেড়ে দেওয়া"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"হ্যাঁ / slug=*"| J6
        J5 -->|না| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID সম্পূর্ণ লাইফসাইকেল

```mermaid
flowchart LR
    subgraph "1. জেনারেশন"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>উদাহরণ: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. স্টোরেজ"
        S1["MySQL erp_* টেবিল<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["সংবেদনশীল ফিল্ড<br/>encryptable cast<br/>AES-128-ECB এনক্রিপশন"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. ট্রান্সফার"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid স্ট্রিং<br/>উদাহরণ: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. রিভার্স ডিকোডিং"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. ডেটা এনক্রিপশন লেয়ারিং

```mermaid
flowchart TB
    subgraph "ট্রান্সমিশন লেয়ার এনক্রিপশন (encryption)"
        E1["ক্লায়েন্ট সংবেদনশীল ডেটা পাঠায়"]
        E2["AES-256-CBC এনক্রিপশন"]
        E3["API সাইফারটেক্সট ট্রান্সফার"]
        E4["সার্ভার ডিক্রিপ্ট করে প্রসেস"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "স্টোরেজ লেয়ার এনক্রিপশন (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["লেখা: স্বয়ংক্রিয় এনক্রিপশন"]
        D3["MySQL VARCHAR(500)<br/>সাইফারটেক্সট স্টোরেজ"]
        D4["পড়া: স্বয়ংক্রিয় ডিক্রিপশন"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "ডিসপ্লে লেয়ার ডিসেনসিটাইজেশন (mask)"
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

## 8. ডেটাবেস ER সম্পর্ক

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "এনক্রিপ্টেড"
        VARCHAR phone "এনক্রিপ্টেড"
        VARCHAR id_card "এনক্রিপ্টেড"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "সফট ডিলিট"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "সেলফ-রেফারেন্স"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1মেনু2বাটন3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erp_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "সোর্স এন্ড"
        TEXT input "ডিসেনসিটাইজড"
        DATETIME created_at
    }

    erp_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user ||--o{ erp_admin_user_role : "user_id"
    erp_admin_role ||--o{ erp_admin_user_role : "role_id"
    erp_admin_role ||--o{ erp_admin_role_permission : "role_id"
    erp_admin_permission ||--o{ erp_admin_role_permission : "permission_id"
    erp_admin_user ||--o{ erp_operation_log : "user_id"
    erp_admin_permission ||--o{ erp_admin_permission : "parent_id"
```

---

## 9. এক্সপোর্ট বিজনেস ফ্লো

```mermaid
sequenceDiagram
    participant C as ক্লায়েন্ট
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ফাইল সিস্টেম

    Note over C,FS: === Excel এক্সপোর্ট ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: ডেটা
    CTL->>CTL: সংবেদনশীল ফিল্ড ডিক্রিপ্ট
    CTL->>CTL: ডিসেনসিটাইজেশন প্রসেস (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet নির্মাণ<br/>হেডার নীল ব্যাকগ্রাউন্ড সাদা লেখা<br/>ডেটা সারি সরু বর্ডার<br/>প্রথম সারি ফ্রিজ<br/>অটো ফিল্টার
    CTL->>FS: runtime/tmp/export_*.xlsx এ লেখা
    CTL-->>C: ফাইল ডাউনলোড

    Note over C,FS: === PDF এক্সপোর্ট ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>পেজ হেডার: শিরোনাম+কপিরাইট+সময়<br/>কনটেন্ট: টেবিল বা কার্ড<br/>ফুটার: অপসারণযোগ্য নয় কপিরাইট
    CTL->>CTL: Dompdf রেন্ডার A4 অনুভূমিক
    CTL->>FS: runtime/tmp/export_*.pdf এ লেখা
    CTL-->>C: ফাইল ডাউনলোড
```

---

## 10. Flutter Web কম্পোনেন্ট ট্রি

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["লগইন ফর্ম<br/>ইউজারনেম/পাসওয়ার্ড/ক্যাপচা"]
    LF --> CAPTCHA["ক্লিক ক্যাপচা কম্পোনেন্ট<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>ক্লিক চিহ্নিত Circle"]

    DB --> SIDEBAR["সাইডবার NavigationDrawer<br/>কলাপসেবল 64px / 240px<br/>ড্যাশবোর্ড/ইউজার/রোল/কনফিগ/লগ"]
    DB --> HEADER["টপবার 56px<br/>কলাপস বাটন + ইউজার মেনু<br/>লগআউট AlertDialog"]
    DB --> CONTENT["কনটেন্ট এরিয়া"]
    CONTENT --> DASH["DashboardPage<br/>স্ট্যাটিস্টিক কার্ড GridView<br/>ট্রেন্ড লাইন চার্ট LineChart<br/>ডিস্ট্রিবিউশন পাই চার্ট PieChart<br/>সাম্প্রতিক অপারেশন ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS পেজ রাউটিং

```mermaid
flowchart LR
    EA["EntryAbility<br/>স্টার্ট"]
    EA -->|"Token নেই"| LP["LoginPage<br/>লগইন পেজ"]
    EA -->|"Token আছে"| DP["DashboardPage<br/>ড্যাশবোর্ড"]

    LP -->|"লগইন সফল<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>ইউজার তালিকা"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>প্রোফাইল সেন্টার"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>ইউজার বিবরণ/নতুন/সম্পাদনা"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"লগআউট<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. সিকিউরিটি ডিপ ডিফেন্স ওভারভিউ

```mermaid
flowchart TB
    subgraph "স্তর ১: হিউম্যান-মেশিন যাচাই"
        L1["ক্লিক ক্যাপচা<br/>Click Captcha<br/>লগইন/রেজিস্ট্রেশন বাধ্যতামূলক"]
    end

    subgraph "স্তর ২: অপারেশন কনফার্মেশন"
        L2["পাসওয়ার্ড দ্বিতীয় কনফার্মেশন<br/>confirmPassword()<br/>DELETE অপারেশনে আবশ্যক"]
    end

    subgraph "স্তর ৩: ট্রান্সমিশন সিকিউরিটি"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "স্তর ৪: আইডেন্টিটি অথ"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "স্তর ৫: পারমিশন অনুমোদন"
        L5["RBAC<br/>method.path গ্রানুলারিটি<br/>সুপার অ্যাডমিন * "]
    end

    subgraph "স্তর ৬: ডেটা সুরক্ষা"
        L6["ইন্টারফেস ID: Hashids এনক্রিপশন<br/>রিকোয়েস্ট বডি: Encryption এনক্রিপশন<br/>স্টোরেজ লেয়ার: Encryptable এনক্রিপশন<br/>এক্সপোর্ট: ডিসেনসিটাইজেশন+কপিরাইট"]
    end

    subgraph "স্তর ৭: অডিট ট্রেসিং"
        L7["OperationLog<br/>সব অপারেশন রেকর্ড<br/>ইউজার/IP/সময়/সোর্স এন্ড/প্যারামিটার"]
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

## 13. ডিপ্লয়মেন্ট টপোলজি

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web সার্ভার"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["স্ট্যাটিক ফাইল<br/>Flutter Web build/"]
    end

    subgraph "অ্যাপ্লিকেশন সার্ভার (হরাইজন্টালি স্কেলেবল)"
        WM1["webman worker 1<br/>:8788"]
        WM2["webman worker 2<br/>:8788"]
        WM3["webman worker N<br/>:8788"]
    end

    subgraph "ডেটা লেয়ার"
        MYSQL["MySQL 8.0<br/>মাস্টার-স্লেভ রেপ্লিকেশন<br/>erp_ প্রিফিক্স"]
        ES["Elasticsearch 8.x<br/>3 নোড ক্লাস্টার<br/>erp_ প্রিফিক্স"]
        REDIS["Redis 7.x<br/>সেন্টিনেল মোড<br/>poster:captcha:*"]
    end

    subgraph "মনিটরিং"
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

## 14. ERP সিস্টেম সামগ্রিক আর্কিটেকচার

```mermaid
graph TB
    subgraph Client["ক্লায়েন্ট লেয়ার"]
        FW["Flutter Web<br/>PC ম্যানেজমেন্ট ব্যাকএন্ড"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>হারমনি নেটিভ App"]
        NG["Angular 22 + ng-zorro<br/>Web ম্যানেজমেন্ট ব্যাকএন্ড"]
        RC["React 19 + Vite<br/>Web ম্যানেজমেন্ট ব্যাকএন্ড"]
    end

    subgraph Gateway["API গেটওয়ে লেয়ার"]
        MW["মিডলওয়্যার চেইন<br/>Cors→SecurityFilter→RateLimit→TracingId<br/>রাউট গ্রুপ：AdminAuth→AdminPermission→OperationLog"]
    end

    subgraph Business["বিজনেস মডিউল লেয়ার"]
        direction LR
        Admin["সিস্টেম ম্যানেজমেন্ট<br/>ইউজার/রোল/পারমিশন/কনফিগ/লগ"]
        Product["পণ্য ম্যানেজমেন্ট<br/>পণ্য/ক্যাটাগরি/ব্র্যান্ড/ওয়ারহাউস/সাপ্লায়ার/কাস্টমার"]
        Purchase["ক্রয় ম্যানেজমেন্ট<br/>অ্যাপ্লিকেশন→অর্ডার→রিসিভ→রিটার্ন→সেটেলমেন্ট"]
        Sales["সেলস ম্যানেজমেন্ট<br/>কোটেশন→অর্ডার→ডেলিভারি→রিটার্ন→সেটেলমেন্ট"]
        Inventory["ইনভেন্টরি ম্যানেজমেন্ট<br/>ইন-আউট/ব্যাচ/স্টকটেক/ট্রান্সফার/অ্যালার্ট"]
        Finance["ফাইন্যান্স ম্যানেজমেন্ট<br/>অ্যাকাউন্ট/ভাউচার/রিসিভেবল-পেবল/জেনারেল লেজার/সাবসিডিয়ারি লেজার/রিপোর্ট/রিম্বার্সমেন্ট"]
        CRM["CRM<br/>কাস্টমার/কন্টাক্ট/ফলো-আপ/ফানেল/পাবলিক পুল/কোটেশন/কন্ট্রাক্ট"]
        Workflow["অ্যাপ্রুভাল ওয়ার্কফ্লো<br/>ওয়ার্কফ্লো ডেফিনিশন/সাবমিট/অ্যাপ্রুভ/রিজেক্ট/উইথড্র"]
        Notification["মেসেজ নোটিফিকেশন<br/>নোটিফিকেশন লিস্ট/পঠিত/অপঠিত কাউন্ট"]
        Project["প্রজেক্ট ম্যানেজমেন্ট<br/>প্রজেক্ট/টাস্ক/টাইমশিট"]
        HR["হিউম্যান রিসোর্স<br/>ডিপার্টমেন্ট/এমপ্লয়ি/পজিশন/অ্যাটেনডেন্স/লিভ/স্যালারি"]
        Manufacturing["প্রোডাকশন ম্যানুফ্যাকচারিং<br/>BOM/প্রোডাকশন অর্ডার/রাউটিং/ওয়ার্কস্টেশন/MRP"]
        Report["কাস্টম রিপোর্ট<br/>রিপোর্ট টেমপ্লেট/ডেটাসেট/ফিল্ড/ফিল্টার/শিডিউল"]
    end

    subgraph Service["বিজনেস সার্ভিস লেয়ার"]
        IS["InventoryService<br/>ইন-আউট + চলমান ওজনযুক্ত গড় খরচ"]
        FS["FinanceService<br/>রিসিভেবল-পেবল স্বয়ংক্রিয় জেনারেশন + নিষ্পত্তি"]
        NS["NotificationService<br/>নোটিফিকেশন একীভূত পাঠানো"]
    end

    subgraph Data["ডেটা লেয়ার"]
        MySQL["MySQL 8.0<br/>২২৭টি বিজনেস টেবিল"]
        Redis["Redis 7<br/>ক্যাশ/রেট লিমিট/Session"]
        ES["Elasticsearch 8<br/>ফুল-টেক্সট সার্চ"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. ক্রস-মডিউল ডেটা ফ্লো

```mermaid
sequenceDiagram
    participant PO as ক্রয় রিসিভ
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as ইনভেন্টরি টেবিল
    participant COST as খরচ রেকর্ড
    participant ARAP as রিসিভেবল-পেবল

    PO->>IS: stockIn(পণ্য,পরিমাণ,একক দাম)
    IS->>INV: রিয়েল-টাইম ইনভেন্টরি আপডেট (লকসহ)
    IS->>COST: চলমান ওজনযুক্ত গড় খরচ পুনঃগণনা
    IS-->>PO: ফ্লো ID রিটার্ন
    
    PO->>FS: createAp(সাপ্লায়ার,পরিমাণ)
    FS->>ARAP: পেবল রেকর্ড জেনারেট
    
    Note over PO,ARAP: সেলস ডেলিভারিও একইভাবে: stockOut + createAr
```

---

## 16. ইনভেন্টরি খরচ হিসাব ডেটা ফ্লো

```mermaid
graph LR
    A[ক্রয় রিসিভ 100元×10টি] --> B[ইনবাউন্ড ফ্লো]
    C[ক্রয় রিসিভ 130元×20টি] --> D[ইনবাউন্ড ফ্লো]
    B --> E[ইনভেন্টরি: 10টি, খরচ 100]
    D --> F[ইনভেন্টরি: 30টি, খরচ 120]
    E --> G[চলমান ওজনযুক্ত গড়: 100]
    F --> H[চলমান ওজনযুক্ত গড়: 120]
    H --> I[আউটবাউন্ড 120 হিসাবে খরচ]
```

---

## 17. অ্যাপ্রুভাল ওয়ার্কফ্লো ডেটা ফ্লো

```mermaid
sequenceDiagram
    participant Biz as বিজনেস মডিউল
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as ওয়ার্কফ্লো ইঞ্জিন
    participant NTF as NotificationService

    Biz->>WF: অ্যাপ্রুভাল সাবমিট (বিজনেস নম্বর,মডিউল টাইপ)
    WF->>WFE: ওয়ার্কফ্লো ডেফিনিশন ম্যাচ → অ্যাপ্রুভাল ইনস্ট্যান্স তৈরি
    WFE->>APR: প্রথম নোডের অ্যাপ্রুভারকে নোটিফাই
    APR->>NTF: অ্যাপ্রুভাল নোটিফিকেশন পাঠানো
    NTF-->>APR: নোটিফিকেশন পাঠানো হয়েছে
    APR->>APR: অ্যাপ্রুভার অ্যাপ্রুভ/রিজেক্ট
    alt অ্যাপ্রুভ
        APR->>WFE: পরবর্তী নোডে ফ্লো
        alt সব নোড পাস
            WFE->>Biz: কলব্যাক: অ্যাপ্রুভ পাস, বিজনেস ডক স্ট্যাটাস আপডেট
        end
    else রিজেক্ট
        WFE->>Biz: কলব্যাক: অ্যাপ্রুভাল রিজেক্ট
    end
```

---

## 18. মেসেজ নোটিফিকেশন ডেটা ফ্লো

```mermaid
sequenceDiagram
    participant Event as ইভেন্ট ট্রিগার সোর্স
    participant NS as NotificationService
    participant DB as নোটিফিকেশন টেবিল
    participant User as ইউজার

    Event->>NS: নোটিফিকেশন ট্রিগার (টাইপ,শিরোনাম,কনটেন্ট,প্রাপক)
    NS->>DB: নোটিফিকেশন রেকর্ড লেখা
    NS-->>User: পুশ (ইন-সাইট মেসেজ/WebSocket)
    User->>NS: পঠিত চিহ্নিত
    NS->>DB: পঠিত স্ট্যাটাস আপডেট
    User->>NS: অপঠিত কাউন্ট কোয়েরি
    NS-->>User: অপঠিত সংখ্যা
```

---

## 19. MRP ম্যাটেরিয়াল রিকোয়ারমেন্ট প্ল্যানিং ডেটা ফ্লো

```mermaid
sequenceDiagram
    participant SO as সেলস অর্ডার
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as ক্রয় পরামর্শ
    participant MO as প্রোডাকশন পরামর্শ

    SO->>MRP: সেলস অর্ডার ডিমান্ড
    MRP->>BOM: BOM সম্প্রসারণ করে ম্যাটেরিয়াল লিস্ট
    BOM-->>MRP: ম্যাটেরিয়াল + স্ট্যান্ডার্ড ব্যবহার
    MRP->>INV: ইনভেন্টরি উপলব্ধ পরিমাণ কোয়েরি
    INV-->>MRP: ইনভেন্টরি পরিমাণ
    MRP->>MRP: নেট ডিমান্ড = গ্রস ডিমান্ড - ইনভেন্টরি
    alt কাঁচামাল অপর্যাপ্ত
        MRP->>PO: ক্রয় পরামর্শ জেনারেট
    else আধা-সমাপ্ত পণ্য অপর্যাপ্ত
        MRP->>MO: প্রোডাকশন পরামর্শ জেনারেট
    end
```

---

## 20. ERP মডিউল কন্ট্রোলার-সার্ভিস-মডেল ম্যাপিং টেবিল

> সার্ভিস লেয়ার ব্যাখ্যা: `কোর Service` কলাম সেই মডিউলের ডুবিয়ে দেওয়া বিজনেস সার্ভিস চিহ্নিত করে; **⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি করে, পরিচিত টেকনিক্যাল ডেব্ট** চিহ্নিত মডিউলগুলিতে,
> কন্ট্রোলার এখনও সরাসরি মডেল কোয়েরি/রাইট মেথড কল করে (`XxxModel::find/where/save` ইত্যাদি), সার্ভিস লেয়ার এখনও এক্সট্রাক্ট করা হয়নি, পরিচিত টেকনিক্যাল ডেব্ট,
> পরবর্তীতে P2-F2 সার্ভিস লেয়ার লাইটওয়েট এক্সট্রাকশন প্যাটার্ন (`app/service/AbstractCrudService` জেনেরিক CRUD বেস ক্লাস + মডিউল Service) অনুযায়ী ধাপে ধাপে কনভার্জ হবে।

| মডিউল | Controllers (ডিরেক্টরি) | কোর Service | প্রধান Model | টেবিল সংখ্যা |
|------|-------------------|-------------|-----------|------|
| সিস্টেম ম্যানেজমেন্ট | admin/controller/ (16টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | AdminUser, AdminRole, AdminPermission | 7 |
| পণ্য ম্যানেজমেন্ট | controller/product/ (8টি) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 12 |
| ক্রয় ম্যানেজমেন্ট | controller/purchase/ (8টি) | InventoryService, FinanceService ⚠ CRUD এখনও সরাসরি কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | PurchaseOrder, PurchaseReceive | 14 |
| সেলস ম্যানেজমেন্ট | controller/sales/ (5টি) | InventoryService, FinanceService ⚠ CRUD এখনও সরাসরি কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | SalesOrder, SalesDelivery | 9 |
| ইনভেন্টরি ম্যানেজমেন্ট | controller/inventory/ (6টি) | InventoryService ⚠ CRUD এখনও সরাসরি কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | Inventory, InventoryFlow, CostRecord | 11 |
| ফাইন্যান্স ম্যানেজমেন্ট | controller/finance/ (28টি) | FinanceService ⚠ CRUD এখনও সরাসরি কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 38 |
| CRM | controller/crm/ (10টি) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| অ্যাপ্রুভাল ওয়ার্কফ্লো | controller/workflow/ (3টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| মেসেজ নোটিফিকেশন | controller/notification/ (2টি) | NotificationService ⚠ CRUD এখনও সরাসরি কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | Notification, NotificationSetting, NotificationTemplate | 4 |
| প্রজেক্ট ম্যানেজমেন্ট | controller/project/ (4টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 6 |
| হিউম্যান রিসোর্স | controller/hr/ (9টি) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 21 |
| প্রোডাকশন ম্যানুফ্যাকচারিং | controller/manufacturing/ (13টি) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 21 |
| কাস্টম রিপোর্ট | controller/report/ (2টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM ইকুইপমেন্ট ম্যানেজমেন্ট | controller/eam/ (5টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart, EamInspectionTask, EamInspectionResult | 6 |
| DMS ডকুমেন্ট ম্যানেজমেন্ট | controller/dms/ (2টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI ড্যাশবোর্ড | controller/bi/ (3টি) | - ⚠ কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট | BiDashboard, BiWidget | 2 |

> এই টেবিলটি প্রাথমিক মডিউল ম্যাপিং (সিস্টেম ম্যানেজমেন্ট + ১৫টি ব্যবসায়িক ডোমেইন); পরবর্তীতে যোগ হওয়া oms / wms / tms / quality / open / platform / print / retail ৮টি ডোমেইন এতে অন্তর্ভুক্ত নয়,
> সম্পূর্ণ তালিকা দেখুন `docs/CLAUDE.md` প্রজেক্ট স্ট্রাকচার ট্রিতে (`app/controller/` মোট ২৩টি মডিউল ডিরেক্টরি / ১৩৯টি কন্ট্রোলার, টপ-লেভেল Install ও Index সহ)।
>
> `টেবিল সংখ্যা`-র গণনার পদ্ধতি (2026-09-15 পরিমাপ): `database/install.sql`-এর ২২৭টি টেবিল নিয়ে টেবিল-নাম প্রিফিক্স অনুযায়ী একক মডিউলে বরাদ্দ —— সিস্টেম ম্যানেজমেন্ট `admin_*`+`system_config`+`operation_log`;
> পণ্য ম্যানেজমেন্ট `product*`/`category`/`brand`/`warehouse`/`location`/`supplier`/`customer*`; ক্রয় ম্যানেজমেন্ট `purchase_*`+`supplier_assessment`; ইনভেন্টরি ম্যানেজমেন্ট `inventory*`/`transfer*`/`check_*`/`cost_record`;
> বাকি মডিউল একই-নাম প্রিফিক্সের টেবিল (`sales_*`→সেলস, `finance_*`→ফাইন্যান্স, `crm_*`→CRM, `approval_*`→অ্যাপ্রুভাল, `notification*`→নোটিফিকেশন, `project*`→প্রজেক্ট, `hr_*`→হিউম্যান রিসোর্স, `mfg_*`→প্রোডাকশন, `report_*`→রিপোর্ট, `eam_*`→EAM, `dms_*`→DMS, `bi_*`→BI)।
> একটি টেবিল শুধু এক কলামে যায়; পরবর্তীতে যোগ হওয়া ডোমেইন ও শেয়ার্ড টেবিল মোট ৪৮টি (`oms_`/`wms_`/`tms_`/`quality_`/`openapi_`/`webhook_`/`member_`/`print_template`/`company`/`tenant`/`channel`/`custom_field_definition`/`tax_*`) এই টেবিলের কোনো সারিতে গণনা করা হয় না।
> পুনর্গণনা: ``grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | cut -d_ -f1 | sort | uniq -c | sort -rn``

### 20.1 P2-F2 সার্ভিস লেয়ার লাইটওয়েট এক্সট্রাকশন রেকর্ড (crm/hr/manufacturing/product এক্সট্রাকশন সম্পন্ন)

| মডিউল | এক্সট্রাকশনের আগে কন্ট্রোলার সরাসরি কোয়েরি কল সংখ্যা | এক্সট্রাকশনের পরে | নতুন Service | এক্সট্রাকশন কনটেন্ট |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | জেনেরিক CRUD + কন্ট্রাক্ট স্ট্যাটাস ফ্লো、কোটেশন-টু-কন্ট্রাক্ট、পাবলিক পুল ক্লেইম/রিলিজ、টিকিট অ্যাসাইন/সলভ/রিপ্লাই、ডিটেইল ক্যাসকেড ক্লিনআপ、অ্যানালিটিক্স রিপোর্ট ডেটা নির্মাণ |
| হিউম্যান রিসোর্স | 38 | 0 | `app/service/hr/HrService.php` | জেনেরিক CRUD + ক্লক-ইন লেট/আর্লি লিভ ডিটারমিনেশন、লিভ অ্যাপ্রুভাল (স্বয়ংক্রিয় লিভ অ্যাটেনডেন্স জেনারেশন)、স্যালারি ইউনিকনেস/নেট পে হিসাব/পেমেন্ট/ব্যাচ জেনারেশন |
| প্রোডাকশন ম্যানুফ্যাকচারিং | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | জেনেরিক CRUD + ওয়ার্ক অর্ডার স্টার্ট/কমপ্লিট ফ্লো、BOM ভার্সন কপি/এফেক্টিভ মিউচুয়াল এক্সক্লুশন、MRP ডিটেইল জেনারেশন |
| পণ্য ম্যানেজমেন্ট | 29 | 0 | `app/service/product/ProductService.php` | জেনেরিক CRUD + পণ্য ট্রানজেকশন তৈরি (SKU/দাম)、ফিল্ড অনুযায়ী মূল মান রেখে আপডেট、বিবরণ রিলেটেড লোডিং |

এক্সট্রাকশন প্যাটার্ন: `app/service/AbstractCrudService.php` `list/all/find/create/update/delete/deleteWhere` জেনেরিক CRUD
এবং `normalizePageParams/canTransition` পিওর লজিক হেল্পার প্রদান করে; মডিউল Service এটি থেকে ইনহেরিট করে মডিউল-নির্দিষ্ট বিজনেস জমা করে।
কন্ট্রোলার `Container::get(XxxService::class)` (class_exists ফলব্যাক) দিয়ে সার্ভিস ইনজেক্ট করে, রাউট/প্যারামিটার/রিটার্ন স্ট্রাকচার সম্পূর্ণ অপরিবর্তিত রাখে;
hashid এনকোড/ডিকোড、পাসওয়ার্ড দ্বিতীয় কনফার্মেশন、রেসপন্স র্যাপিং ইত্যাদি HTTP ফোকাস পয়েন্ট কন্ট্রোলারেই থাকে।
নতুন Service `config/dependence.php`-এ নিবন্ধিত হয়েছে (ফাইলটি dead config, addDefinitions দিয়ে লোড হয় না, রানটাইম ডিপেন্ডেন্সি কন্টেইনার
class_exists ফলব্যাক ইনস্ট্যান্টিয়েশন, তাই সব Service নো-আর্গুমেন্ট কনস্ট্রাক্টর রাখে)।

এক্সট্রাক্ট না হওয়া মডিউল (প্রজেক্ট ম্যানেজমেন্ট 18 বার、কাস্টম রিপোর্ট 18 বার、ক্রয় 24 বার、সেলস 24 বার、সিস্টেম ম্যানেজমেন্ট 42 বার ইত্যাদি) টেবিলে
"কন্ট্রোলার সরাসরি মডেল কোয়েরি, পরিচিত টেকনিক্যাল ডেব্ট" চিহ্নিত করা হয়েছে, পরবর্তী ইটারেশনে একই প্যাটার্নে এক্সট্রাক্ট হবে।

> ⚠ পুনঃপরীক্ষা (2026-09-15): এই অংশের সংখ্যাগুলো **এক্সট্রাকশনের সময়বিন্দুর** (1051d83 / 2026-08-16) পরিমাপ —— একই পদ্ধতিতে ওই কমিট যাচাই করলে চার মডিউল ঠিক
> CRM 57→0、হিউম্যান রিসোর্স 36→0 (এই অংশে লেখা 38)、প্রোডাকশন 33→0、পণ্য 29→0; তখন এক্সট্রাক্ট না হওয়া মডিউল ছিল প্রজেক্ট 18 / রিপোর্ট 18 / ক্রয় 25 / সেলস 25 / সিস্টেম ম্যানেজমেন্ট 44 (লেখা 18/18/24/24/42, 1~2-এর পার্থক্য গণনার পদ্ধতিগত পার্থক্য)।
> এক্সট্রাকশনের পর যোগ হওয়া পেজগুলো Service-এ যুক্ত হয়নি, সরাসরি কোয়েরি ফিরে এসেছে: CRM ৬ জায়গায় (সম্পর্কিত নাম ব্যাকফিল `pluck`)、প্রোডাকশন ৩৯ জায়গায় (CostEntry/MaterialIssue/WorkReport/Subcontract ইন-আউট ইত্যাদি ৬টি পরবর্তী কন্ট্রোলার)、
> পণ্য ২ জায়গায় (LocationController লকেশন), হিউম্যান রিসোর্স এখনও ০; এক্সট্রাক্ট না হওয়া মডিউল এখন প্রজেক্ট 24 / রিপোর্ট 20 / ক্রয় 58 / সেলস 35 / সিস্টেম ম্যানেজমেন্ট 67।
> পুনঃপরীক্ষার পদ্ধতি ও কমান্ড (`Model::class` গণনায় ধরা হয় না): ``grep -rhoE '\b[A-Z][A-Za-z]*::(find|where|whereIn|query|first|all|count|paginate|insert|update|delete|save|create|pluck|exists)\(' app/controller/<মডিউল>/ | grep -vE '\b(Service|Container|Validator|Cache|Log)::' | wc -l``

---

## OMS/WMS/TMS এক্সটেনশন মডিউল (2026-08)

### OMS (Order Management System) — 8 টেবিল
- **অর্ডার এক্সটেনশন** (`erp_oms_order`)：মাল্টি-চ্যানেল অ্যাগ্রিগেশন/ফুলফিলমেন্ট স্ট্যাটাস/পেমেন্ট স্ট্যাটাস/প্রায়োরিটি
- **অর্ডার ঠিকানা** (`erp_oms_order_address`)：রিসিভ/বিল ঠিকানা (মাল্টি-কান্ট্রি ফরম্যাট)
- **ফুলফিলমেন্ট রেকর্ড** (`erp_oms_fulfillment`+`_item`)：অ্যালোকেটেড/পিকড/প্যাকড/শিপড পরিমাণ ট্র্যাকিং
- **RMA** (`erp_oms_rma`+`_item`)：রিটার্ন/এক্সচেঞ্জ সম্পূর্ণ লাইফসাইকেল
- **ইনভেন্টরি রিজার্ভেশন** (`erp_oms_inventory_reservation`)：ATP = physical - reserved
- **চ্যানেল** (`erp_channel`)：direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 টেবিল
- **জোন ও লোকেশন** (`erp_wms_zone`, `erp_wms_location`)：zone→aisle→rack→level→bin
- **ইনবাউন্ড** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **আউটবাউন্ড** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 টেবিল
- **ক্যারিয়ার** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **শিপমেন্ট** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **ইনভয়েস** (`erp_tms_freight_invoice`)

### Data Flow
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. ইকোসিস্টেম রোডম্যাপ (2026-08)

> বিস্তারিত ডিজাইন স্পেক: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 বেসলাইন অ্যাসেসমেন্ট (রোডম্যাপ শুরুর সময়)

> P0~P3 সব ডেলিভার হয়েছে, বর্তমান সমন্বিত স্কোর 89/100 (CLAUDE.md দেখুন); নিম্নলিখিত টেবিল রোডম্যাপ শুরুর আগের বেসলাইন স্ন্যাপশট।

| মাত্রা | স্কোর | মূল ফাঁক |
|------|------|----------|
| ব্যাকএন্ড API | 85/100 | একাধিক মডিউল CRUD কঙ্কাল, বিজনেস হিসাব ইঞ্জিন নেই |
| সিকিউরিটি সুরক্ষা | 95/100 | ৭ স্তরের ডিপ ডিফেন্স (L0–L12 প্যানোরামা), প্রোডাকশন-রেডি |
| ফ্রন্টএন্ড UI | 20/100 | **সবচেয়ে বড় দুর্বলতা**: Flutter 12 পেজ ~20% মডিউল কভার করে, Web ম্যানেজমেন্ট প্যানেল নেই |
| অপস ইকোসিস্টেম | 70/100 | মাইগ্রেশন রোলব্যাক、অটো ব্যাকআপ、অবজারভেবিলিটি নেই |
| বিজনেস গভীরতা | 55/100 | ফাইন্যান্স/HR/ম্যানুফ্যাকচারিং কোর অ্যালগরিদম ইমপ্লিমেন্ট হয়নি |
| **সমন্বিত** | **65/100** | |

### 21.2 চার-ফেজ সিরিয়াল রোডম্যাপ

```
P0(3-4周) → P1(4-6周) → P2(1-2周) → P3(2-3周) = মোট প্রায় 13 সপ্তাহ
```

| ফেজ | নাম | কোর ডেলিভারি |
|------|------|----------|
| **P0** | ফ্রন্টএন্ড ইকোসিস্টেম | Flutter Web ফুল-মডিউল ম্যানেজমেন্ট প্যানেল (14 মডিউল 40+ পেজ)、জেনেরিক কম্পোনেন্ট লাইব্রেরি、HarmonyOS অ্যালাইনমেন্ট |
| **P1** | বিজনেস গভীরতা | ফাইন্যান্স ডাবল-এন্ট্রি ইঞ্জিন、স্যালারি হিসাব ইঞ্জিন、MRP ইঞ্জিন、কোয়ালিটি ম্যানেজমেন্ট মডিউল、রিয়েল-টাইম নোটিফিকেশন (WebSocket) |
| **P2** | অপস রিলায়েবিলিটি | ডেটাবেস মাইগ্রেশন রোলব্যাক、অটো ব্যাকআপ এনহ্যান্সমেন্ট、OpenTelemetry ট্রেসিং、RabbitMQ কিউ ড্রাইভার |
| **P3** | এক্সপেরিয়েন্স এনহ্যান্সমেন্ট | BI ড্র্যাগ-অ্যান্ড-ড্রপ ড্যাশবোর্ড、ইকুইপমেন্ট ম্যানেজমেন্ট (EAM)、মাল্টি-টেন্যান্ট আইসোলেশন、ডকুমেন্ট ম্যানেজমেন্ট (DMS) |

### 21.3 মিডলওয়্যার চেইন ইভোল্যুশন

```
বর্তমান:  Cors → SecurityFilter → RateLimit → TracingId → {রাউট গ্রুপ}
P1 পরে:   Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {রাউট গ্রুপ}
P2 পরে:   Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {রাউট গ্রুপ}
P3 পরে:   Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {রাউট গ্রুপ}
```

### 21.4 P0 টার্গেট আর্কিটেকচার — Flutter Web ম্যানেজমেন্ট প্যানেল

| লেয়ার | নতুন কনটেন্ট |
|------|----------|
| লেআউট লেয়ার | `AdminLayout` PC থ্রি-কলাম লেআউট (কলাপসেবল সাইডবার + টপবার + কনটেন্ট এরিয়া) |
| কম্পোনেন্ট লেয়ার | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| পেজ লেয়ার | বর্তমান 12 পেজ থেকে 14 মডিউল 40+ পেজ ফুল কভারেজে সম্প্রসারিত |
| সার্ভিস লেয়ার | বিদ্যমান `ApiService`, `AuthService`, `CaptchaService`, `ExportService` রিইউজ |

### 21.5 P1 টার্গেট আর্কিটেকচার — বিজনেস হিসাব ইঞ্জিন

| ইঞ্জিন | সার্ভিস ক্লাস | মূল নিয়ম |
|------|--------|----------|
| ডাবল-এন্ট্রি | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | ডেবিট-ক্রেডিট ব্যালেন্স বাধ্যতামূলক যাচাই、পিরিয়ড-এন্ড লাভ-লস ক্যারি-ফরওয়ার্ড、মাল্টি-কারেন্সি এক্সচেঞ্জ রেট কনভার্সন |
| স্যালারি হিসাব | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | সোশ্যাল ইন্স্যুরেন্স বেস আপার-লোয়ার সীমা、প্রভিডেন্ট ফান্ড রেশিও、ব্যক্তিগত আয়কর প্রগ্রেসিভ রেট、ব্যাংক পে-রোল |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM লেয়ার-বাই-লেয়ার সম্প্রসারণ + লস、লো-লেভেল কোড (LLC)、সেফটি স্টক、ব্যাচ নিয়ম |
| কোয়ালিটি | `QmsInspectionService` | IQC ইনকামিং/IPQC প্রসেস/OQC শিপিং তিন ডক ফ্লো |
| নোটিফিকেশন | `WebSocketService`, `ChannelRouter` | ইন-সাইট/ইমেইল/এন্টারপ্রাইজ WeChat/DingTalk মাল্টি-চ্যানেল |

### 21.6 ডেটা মডেল পরিবর্তন সামারি

| ফেজ | নতুন টেবিল সংখ্যা | সংশ্লিষ্ট মডিউল |
|------|----------|----------|
| P0 | 0 | পিওর ফ্রন্টএন্ড, কোনো টেবিল পরিবর্তন নেই |
| P1 | 14 | ফাইন্যান্স(2) + HR(3) + ম্যানুফ্যাকচারিং(2) + কোয়ালিটি(5) + নোটিফিকেশন(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. মাল্টি-টেন্যান্সি (রিজার্ভড ক্যাপাবিলিটি, সক্রিয় নয়)

> কপিরাইট ডিক্লারেশন ফাইল হেডারের মতো: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 পজিশনিং ও সিদ্ধান্ত

মাল্টি-টেন্যান্সি এই প্রজেক্টে **রিজার্ভড ক্যাপাবিলিটি** হিসেবে পজিশন করা, এই পর্বে **কানেক্ট করা হয় না、সক্রিয় করা হয় না** (ডকুমেন্টেড ডিগ্রেডেশন)। পরিকল্পনার সাথে সামঞ্জস্যপূর্ণ:
SaaS বিলিং、টেন্যান্ট সেলফ-অনবোর্ডিং ইত্যাদি "মাল্টি-টেন্যান্সি সম্পূর্ণ বাণিজ্যিকীকরণ সমাধান" এই প্রজেক্টের নির্মাণ সুযোগের মধ্যে নেই; এই পর্বে শুধু ন্যূনতম
কোড কঙ্কাল (মিডলওয়্যার + মডেল Trait) রাখা হয় এবং সক্রিয়করণ ধাপ দেওয়া হয়, পরবর্তীতে প্রয়োজন অনুযায়ী সক্রিয় করার জন্য।
দ্রষ্টব্য: §21.2 রোডম্যাপ P3-এর "মাল্টি-টেন্যান্ট আইসোলেশন" এর ভিত্তিতে "রিজার্ভড ক্যাপাবিলিটি (ডকুমেন্টেড ডিগ্রেডেশন)" হিসেবে সমন্বয় করা হয়েছে, কঙ্কাল রাখা হয়েছে, কানেক্ট করা হয়নি।

সিদ্ধান্তের ভিত্তি (2026-08 রিভিউ):
- বর্তমান ডিপ্লয়মেন্ট প্রায় সব সিঙ্গেল-টেন্যান্ট, কানেক্ট করলে অপ্রয়োজনীয় আইসোলেশন জটিলতা ও রিগ্রেশন ঝুঁকি আসবে;
- বর্তমান কঙ্কালে টেকনিক্যাল ত্রুটি আছে (22.4 দেখুন), "কানেক্ট মানেই আইসোলেশন" সত্য নয়, প্রথমে ডিজাইন সংশোধন সম্পন্ন করতে হবে;
- আইসোলেশনের জন্য ২২৭টি ব্যবসায়িক টেবিলে একে একে কলাম যোগ করতে হবে、একে একে মডেল সক্রিয় করতে হবে, খরচ "ন্যূনতম কানেক্ট"-এর চেয়ে অনেক বেশি।

### 22.2 বর্তমান অবস্থা (কোড ও কনফিগ মিলিয়ে যাচাই)

| আইটেম | বর্তমান অবস্থা |
|----|------|
| `app/middleware/TenantScope.php` | আছে, রেজিস্টার করা হয়নি; `X-Tenant-Code` হেডার থেকে টেন্যান্ট কোড পড়ে `erp_tenant` টেবিল কোয়েরি করে কনটেক্সট ইনজেক্ট করে, হেডার অনুপস্থিত থাকলে সরাসরি ছেড়ে দেয় |
| `app/model/concerns/TenantScope.php` | আছে; ৪টি ফাইন্যান্স মডেল (`FinanceLedger` / `FinanceBalanceSheet` / `FinanceCashFlow` / `FinanceProfit`, কোম্পানি-পরিবার `tenantScopeByCompany()` true রিটার্ন করে) ব্যবহার করে, `company_id` অনুযায়ী ফিল্টার করে; মিডলওয়্যার রেজিস্টার না থাকায় রিকোয়েস্ট কনটেক্সট ইনজেক্ট হয় না, গ্লোবাল স্কোপ বর্তমানে কার্যকর নয় |
| `config/middleware.php` | গ্লোবাল চেইন: Cors → SecurityFilter → RateLimit → TracingId, TenantScope নেই |
| `config/route.php` /admin/v1 গ্রুপ | AdminAuth → AdminPermission → OperationLog, TenantScope নেই |
| JWT পেলোড | শুধু `sub` / `username` / `token_type`, **tenant_id ডিক্লারেশন নেই** (`app/api/v1/controller/AuthController.php`) |
| ডেটাবেস | **পুরো ডেটাবেসে tenant_id কলাম নেই** (install.sql-এও নেই) |
| মডেল | ৪টি ফাইন্যান্স মডেল `TenantScope` trait ব্যবহার করে (কোম্পানি-পরিবার, `company_id` ফিল্টার) —— পাইলট আইসোলেশন; টেন্যান্ট কনটেক্সট ইনজেক্ট না হলে কোনো ফিল্টার যোগ হয় না |

### 22.3 সক্রিয়করণ ধাপ (রিজার্ভড রেফারেন্স, এই পর্বে এক্সিকিউট নয়)

1. মিডলওয়্যার রেজিস্টার: `config/route.php`-এর /admin/v1 গ্রুপের `middleware()`-এ
   `app\middleware\TenantScope::class` যুক্ত করুন (AdminAuth-এর পরে বসান, অথ সম্পন্ন হয়েছে তা নিশ্চিত করতে)।
2. রিকোয়েস্টকারী রিকোয়েস্ট হেডারে `X-Tenant-Code` (টেন্যান্ট কোড স্ট্রিং) বহন করবে।
3. আইসোলেশন প্রয়োজন বিজনেস টেবিলে `tenant_id` কলাম যোগ (BIGINT + ইনডেক্স) করে বিদ্যমান ডেটা ব্যাকফিল করুন;
   ডিকশনারি/সিস্টেম টেবিল (যেমন `erp_admin_user`、`erp_role`、`erp_permission`) আইসোলেট হয় না।
4. আইসোলেশন প্রয়োজন মডেল ক্লাসে `use app\model\concerns\TenantScope;` যোগ করুন, স্বয়ংক্রিয়ভাবে বর্তমান টেন্যান্ট অনুযায়ী ফিল্টার হবে।
5. (ঐচ্ছিক) JWT থেকে হেডারের বদলে টেন্যান্ট নিতে চাইলে: লগইন ইস্যু পেলোডে `tenant_id` ডিক্লারেশন যোগ করুন,
   এবং মিডলওয়্যারে `$payload['tenant_id']` থেকে পড়ুন।

### 22.4 পরিচিত টেকনিক্যাল সীমাবদ্ধতা (সক্রিয় করার আগে অবশ্যই সমাধান করতে হবে)

- **ট্রাস্ট বাউন্ডারি (রেজিস্টার করার আগে অবশ্যই সমাধান করতে হবে)**：টেন্যান্ট কনটেক্সটের উৎস `X-Tenant-Code` রিকোয়েস্ট হেডার, যা জাল করা যায়
  এমন ইনপুট; `erp_admin_user`-এর সঙ্গে কোম্পানি/টেন্যান্টের বাইন্ডিং (অ্যাডমিনের সদস্যতা নির্ধারণ) প্রতিষ্ঠার আগে মিডলওয়্যার সক্রিয় করলে
  অনধিকার ডেটা-প্লেন ফাঁক তৈরি হবে (যেকোনো অথেনটিকেটেড অ্যাডমিন যেকোনো টেন্যান্ট দাবি করে তার ডেটা পড়তে পারবে)।
- **স্ট্যাটিক ট্রান্সমিশন চেইন ভেঙে যাওয়া (PHP 8.3 পরীক্ষিত) P2-4 B5 ফিক্স সংস্করণে প্রতিস্থাপিত হয়েছে**：`TenantScope` trait
  এখন রিকোয়েস্ট কনটেক্সট ইনজেকশনে চলে (`request()->tenantId` / `companyId`), এই পাথে কোনো স্ট্যাটিক স্টেট নেই,
  রেসিডেন্ট প্রসেসে রিকোয়েস্ট-জুড়ে ক্রস-টকও তাই দূর হয়েছে; trait নামের স্ট্যাটিক ফ্যাসাড `@deprecated` হিসেবে চিহ্নিত, শুধু
  টেস্ট/CLI ফলব্যাকের জন্য।
- **ডেটা প্লেন ফাঁক**：পুরো ডেটাবেসে tenant_id কলাম নেই, টেবিল প্রতি মাইগ্রেশন প্রয়োজন; ক্রস-টেন্যান্ট শেয়ার্ড ডিকশনারি টেবিলে এক্সেম্পশন মেকানিজম ডিজাইন করতে হবে।

### 22.5 অ্যাকসেপটেন্স ক্রাইটেরিয়া

এই পর্বের অ্যাকসেপটেন্স = ডকুমেন্ট ও কোড সামঞ্জস্যপূর্ণ: `config/middleware.php` ও `config/route.php`-এ
TenantScope রেজিস্ট্রেশন নেই; মিডলওয়্যার কমেন্টে "বাস্তবায়িত, ডিফল্টে রেজিস্টার করা হয়নি" চিহ্নিত এবং রেজিস্ট্রেশন পয়েন্ট ও ট্রাস্ট বাউন্ডারি দেওয়া আছে,
Trait কমেন্টে রিকোয়েস্ট কনটেক্সট ইনজেকশন চেইন ও রিগ্রেশন লাইন (টেন্যান্ট কনটেক্সট না থাকলে ফিল্টারে অংশ নেয় না) চিহ্নিত;
এই সেকশনের বর্ণনা কোডের বর্তমান অবস্থার সাথে প্রতিটি পয়েন্ট মিলে।
