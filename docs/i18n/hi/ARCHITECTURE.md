# वास्तुकला आरेख और व्यावसायिक तर्क आरेख

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> निम्नलिखित Mermaid चार्ट GitHub / GitLab / VS Code में स्वचालित रूप से रेंडर होते हैं। अन्य वातावरणों के लिए [Mermaid Live Editor](https://mermaid.live/) का उपयोग करें।

---

## 1. सिस्टम टोपोलॉजी वास्तुकला

```mermaid
flowchart TB
    subgraph "क्लाइंट परत"
        A1["Flutter Web<br/>PC प्रबंधन बैकएंड<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>मोबाइल/टैबलेट क्लाइंट"]
    end

    subgraph "गेटवे/एज परत (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>रिवर्स प्रॉक्सी + HTTPS + Gzip<br/>स्टैटिक फ़ाइल सेवा"]
    end

    subgraph "एप्लिकेशन परत (webman v2)"
        C_LOC["Locale मिडलवेयर<br/>Accept-Language स्वचालित पहचान"]
        C0["ApiVersion मिडलवेयर<br/>API-Version हेडर सत्यापन"]
        C1["AdminAuth मिडलवेयर<br/>JWT सत्यापन"]
        C2["AdminPermission मिडलवेयर<br/>RBAC अनुमति सत्यापन"]
        C3["प्रबंधन Controller<br/>Dashboard / User / Role / Permission"]
        C4["सार्वजनिक Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "स्टोरेज परत"
        D1[("MySQL 8.0<br/>मुख्य स्टोरेज<br/>तालिका उपसर्ग erp_")]
        D2[("Elasticsearch<br/>फुल-टेक्स्ट खोज<br/>इंडेक्स उपसर्ग erp_")]
        D3[("Redis<br/>Session / कैश<br/>Captcha स्टोरेज")]
    end

    subgraph "बाहरी"
        E1["DevEco Studio<br/>HarmonyOS बिल्ड"]
        E2["Flutter SDK<br/>Web बिल्ड"]
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

## 2. बैकएंड परत वास्तुकला

```mermaid
flowchart TD
    subgraph "रूट परत Route Layer"
        R1["config/route.php<br/>URL → Controller मैपिंग"]
    end

    subgraph "मिडलवेयर परत Middleware Layer"
        M_LOC["Locale<br/>Accept-Language स्वचालित पहचान<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Redis स्लाइडिंग विंडो रेट लिमिट<br/>X-RateLimit रिस्पॉन्स हेडर"]
        M_SF["SecurityFilter<br/>हमले की पहचान और रोकथाम<br/>XSS/SQL इंजेक्शन/पाथ ट्रैवर्सल/CSRF"]
        M0["ApiVersion<br/>API संस्करण सत्यापन<br/>apiVersion इंजेक्शन"]
        M1["AdminAuth<br/>JWT Token सत्यापन<br/>adminId इंजेक्शन"]
        M2["AdminPermission<br/>RBAC प्रमाणीकरण<br/>method.path मिलान<br/>Redis 60s अनुमति कैश"]
    end

    subgraph "कंट्रोलर परत Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + खोज + पेजिनेशन"]
        CT3["RoleController<br/>CRUD + अनुमति सिंक"]
        CT4["PermissionController<br/>CRUD + ट्री निर्माण"]
        CT5["DashboardController<br/>आँकड़े/ट्रेंड/वितरण"]
        CT6["ExportController<br/>Excel/PDF निर्यात"]
        CT7["CaptchaController<br/>कैप्चा जनरेशन/सत्यापन"]
        CT8["AuthController<br/>लॉगिन/रजिस्टर/रिफ्रेश"]
    end

    subgraph "सेवा परत Service Layer"
        S1["HashidsService<br/>ID एन्कोडिंग/डिकोडिंग"]
        S2["SnowflakeService<br/>वैश्विक अद्वितीय ID जनरेशन"]
        S3["EncryptionService<br/>एन्क्रिप्शन/डिक्रिप्शन + मास्किंग"]
    end

    subgraph "मॉडल परत Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ड्राइवर परत Driver Layer"
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

### ERP व्यावसायिक परत विस्तार

जैसे-जैसे सिस्टम शुद्ध प्रबंधन बैकएंड से पूर्ण ERP सिस्टम में विकसित हुआ, कंट्रोलर परत और सेवा परत में निम्नलिखित व्यावसायिक मॉड्यूल जोड़े गए:

| परत | निर्देशिका | विवरण |
|------|------|------|
| व्यावसायिक कंट्रोलर | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70, मॉड्यूल के अनुसार विभाजित, व्यावसायिक अनुरोधों को संभालते हैं |
| व्यावसायिक सेवा | `app/service/{inventory,finance,notification}/` | इन्वेंटरी प्रवेश/निकास + लागत गणना, वित्तीय प्राप्य/देय + निपटान, नोटिफिकेशन भेजना |

---

## 3. अनुरोध जीवनचक्र

```mermaid
sequenceDiagram
    participant C as क्लाइंट
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

    C->>N: HTTPS अनुरोध<br/>Header: API-Version: v1
    N->>MW_LOC: फॉरवर्ड
    MW_LOC->>MW_LOC: Accept-Language पार्स करें<br/>locale सेट करें
    MW_LOC->>MW_SF: पास

    alt गैर-मानक HTTP विधि (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else विधि मान्य है (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: विधि व्हाइटलिस्ट जांच पास
    end

    alt हमले की पहचान ट्रिगर
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: पास

    alt रेट लिमिट ट्रिगर
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: पास

    alt असमर्थित संस्करण
        MW0-->>C: 400 असमर्थित API संस्करण
    else संस्करण मान्य
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token अनुपलब्ध या अमान्य
        MW1-->>C: 401 Unauthorized
    else Token मान्य
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt कोई अनुमति नहीं
        MW2-->>C: 403 Forbidden
    else अनुमति है
        MW2->>CTL: कंट्रोलर में प्रवेश
    end

    CTL->>CTL: पैरामीटर सत्यापन (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt संवेदनशील ऑपरेशन (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt पासवर्ड गलत
            CTL-->>C: 422 पासवर्ड सत्यापन विफल
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast स्वचालित डिक्रिप्शन
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: रिस्पॉन्स JSON बनाएँ
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: ऑपरेशन लॉग रिकॉर्ड करें (POST/PUT/DELETE)
```

---

## 4. प्रमाणीकरण और कैप्चा प्रवाह

```mermaid
sequenceDiagram
    participant U as उपयोगकर्ता
    participant CL as क्लाइंट
    participant SV as सर्वर
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === चरण 1: कैप्चा प्राप्त करें ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 बैकग्राउंड छवि बनाएँ
    CAP->>CAP: N चीनी लक्ष्य यादृच्छिक रूप से रखें
    CAP->>CAP: key बनाएँ, targets संग्रहीत करें
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === चरण 2: उपयोगकर्ता क्लिक करता है ===
    CL->>CL: कैप्चा छवि रेंडर करें
    CL->>CL: संकेत "कृपया क्रम से क्लिक करें: पेड़ → पक्षी → फूल"
    U->>CL: चित्र में पाठ की स्थिति पर क्रम से क्लिक करें
    CL->>CL: clicks एकत्र करें: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === चरण 3: लॉगिन ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt कैप्चा गलत
        CAP-->>SV: false
        SV-->>CL: 422 कैप्चा गलत
    else कैप्चा सही
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt क्रेडेंशियल गलत
            SV-->>CL: 401 उपयोगकर्ता नाम या पासवर्ड गलत
        else क्रेडेंशियल सही
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === बाद के अनुरोध ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC अनुमति मॉडल

```mermaid
flowchart LR
    subgraph "उपयोगकर्ता User"
        U1["admin<br/>(सुपर एडमिन)"]
        U2["editor<br/>(संपादक)"]
        U3["viewer<br/>(केवल-पठन)"]
    end

    subgraph "भूमिका Role"
        R1["super_admin<br/>अनुमति पहचानकर्ता: *"]
        R2["editor<br/>अनुमति पहचानकर्ता: get.*, post.*"]
        R3["viewer<br/>अनुमति पहचानकर्ता: get.*"]
    end

    subgraph "अनुमति Permission (ट्री)"
        P1["dashboard<br/>type=1 मेनू"]
        P2["user<br/>type=1 मेनू"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 बटन"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (सभी अनुमतियाँ)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "अनुमति प्रकार"
        T1["type=1 मेनू<br/>साइडबार दिखाना/छिपाना नियंत्रित करता है"]
        T2["type=2 बटन<br/>पेज ऑपरेशन बटन नियंत्रित करता है"]
        T3["type=3 API<br/>इंटरफ़ेस पहुंच नियंत्रित करता है"]
    end

    subgraph "अनुमति पहचानकर्ता प्रारूप"
        F1["{method}.{path}<br/>उदा: get.admin/user<br/>उदा: post.admin/user<br/>उदा: delete.admin/role"]
    end

    subgraph "निर्णय प्रवाह"
        J1["Token निकालें → adminId"]
        J2["उपयोगकर्ता की भूमिकाएँ खोजें"]
        J3["सभी अनुमति slug एकत्र करें"]
        J4["method.path बनाएँ"]
        J5{"मिलान?"}
        J6["अनुमति दें"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"हाँ / slug=*"| J6
        J5 -->|नहीं| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID का पूर्ण जीवनचक्र

```mermaid
flowchart LR
    subgraph "1. जनरेशन"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>उदा: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. स्टोरेज"
        S1["MySQL erp_* तालिकाएँ<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["संवेदनशील फ़ील्ड<br/>encryptable cast<br/>AES-128-ECB एन्क्रिप्शन"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. ट्रांसमिशन"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid स्ट्रिंग<br/>उदा: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. रिवर्स डिकोडिंग"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. डेटा एन्क्रिप्शन परतें

```mermaid
flowchart TB
    subgraph "ट्रांसमिशन परत एन्क्रिप्शन (encryption)"
        E1["क्लाइंट संवेदनशील डेटा भेजता है"]
        E2["AES-256-CBC एन्क्रिप्शन"]
        E3["API सिफरटेक्स्ट ट्रांसमिशन"]
        E4["सर्वर डिक्रिप्शन और प्रोसेसिंग"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "स्टोरेज परत एन्क्रिप्शन (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["लिखना: स्वचालित एन्क्रिप्शन"]
        D3["MySQL VARCHAR(500)<br/>सिफरटेक्स्ट स्टोरेज"]
        D4["पढ़ना: स्वचालित डिक्रिप्शन"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "डिस्प्ले परत मास्किंग (mask)"
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

## 8. डेटाबेस ER संबंध

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "एन्क्रिप्टेड"
        VARCHAR phone "एन्क्रिप्टेड"
        VARCHAR id_card "एन्क्रिप्टेड"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "सॉफ्ट डिलीट"
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
        BIGINT parent_id FK "सेल्फ-रेफरेंस"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1मेनू 2बटन 3API"
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
        VARCHAR source "स्रोत एंड"
        TEXT input "मास्क किया गया"
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

## 9. निर्यात व्यावसायिक प्रक्रिया

```mermaid
sequenceDiagram
    participant C as क्लाइंट
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as फ़ाइल सिस्टम

    Note over C,FS: === Excel निर्यात ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: डेटा
    CTL->>CTL: संवेदनशील फ़ील्ड डिक्रिप्ट करें
    CTL->>CTL: मास्किंग (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet निर्माण<br/>हेडर नीली पृष्ठभूमि सफेद पाठ<br/>डेटा पंक्तियाँ पतली बॉर्डर<br/>पहली पंक्ति फ़्रीज़<br/>ऑटो फ़िल्टर
    CTL->>FS: runtime/tmp/export_*.xlsx लिखें
    CTL-->>C: फ़ाइल डाउनलोड

    Note over C,FS: === PDF निर्यात ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>पेज हेडर: शीर्षक+कॉपीराइट+समय<br/>सामग्री: तालिका या कार्ड<br/>फुटर: हटाने योग्य नहीं कॉपीराइट
    CTL->>CTL: Dompdf रेंडर A4 लैंडस्केप
    CTL->>FS: runtime/tmp/export_*.pdf लिखें
    CTL-->>C: फ़ाइल डाउनलोड
```

---

## 10. Flutter Web कंपोनेंट ट्री

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["लॉगिन फ़ॉर्म<br/>उपयोगकर्ता नाम/पासवर्ड/कैप्चा"]
    LF --> CAPTCHA["क्लिक कैप्चा कंपोनेंट<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>क्लिक मार्क Circle"]

    DB --> SIDEBAR["साइडबार NavigationDrawer<br/>कोलेप्सिबल 64px / 240px<br/>डैशबोर्ड/उपयोगकर्ता/भूमिका/कॉन्फ़िग/लॉग"]
    DB --> HEADER["टॉप बार 56px<br/>कोलेप्स बटन + उपयोगकर्ता मेनू<br/>लॉगआउट AlertDialog"]
    DB --> CONTENT["कंटेंट क्षेत्र"]
    CONTENT --> DASH["DashboardPage<br/>स्टैट कार्ड GridView<br/>ट्रेंड लाइन चार्ट LineChart<br/>वितरण पाई चार्ट PieChart<br/>हालिया ऑपरेशन ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS पेज रूटिंग

```mermaid
flowchart LR
    EA["EntryAbility<br/>स्टार्टअप"]
    EA -->|"कोई Token नहीं"| LP["LoginPage<br/>लॉगिन पेज"]
    EA -->|"Token है"| DP["DashboardPage<br/>डैशबोर्ड"]

    LP -->|"लॉगिन सफल<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>उपयोगकर्ता सूची"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>प्रोफ़ाइल केंद्र"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>उपयोगकर्ता विवरण/नया/संपादित"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"लॉगआउट<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. सुरक्षा गहन रक्षा परिदृश्य

```mermaid
flowchart TB
    subgraph "परत 1: मानव सत्यापन"
        L1["क्लिक कैप्चा<br/>Click Captcha<br/>लॉगिन/रजिस्टर अनिवार्य"]
    end

    subgraph "परत 2: ऑपरेशन पुष्टि"
        L2["पासवर्ड द्वितीय पुष्टि<br/>confirmPassword()<br/>DELETE ऑपरेशन अनिवार्य"]
    end

    subgraph "परत 3: ट्रांसमिशन सुरक्षा"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "परत 4: पहचान प्रमाणीकरण"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "परत 5: अनुमति प्रमाणीकरण"
        L5["RBAC<br/>method.path ग्रैन्युलैरिटी<br/>सुपर एडमिन * "]
    end

    subgraph "परत 6: डेटा सुरक्षा"
        L6["इंटरफ़ेस ID: Hashids एन्क्रिप्शन<br/>अनुरोध बॉडी: Encryption एन्क्रिप्शन<br/>स्टोरेज परत: Encryptable एन्क्रिप्शन<br/>निर्यात: मास्किंग+कॉपीराइट"]
    end

    subgraph "परत 7: ऑडिट ट्रेसेबिलिटी"
        L7["OperationLog<br/>सभी ऑपरेशन रिकॉर्ड करता है<br/>उपयोगकर्ता/IP/समय/स्रोत एंड/पैरामीटर"]
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

## 13. डिप्लॉयमेंट टोपोलॉजी

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web सर्वर"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["स्टैटिक फ़ाइलें<br/>Flutter Web build/"]
    end

    subgraph "एप्लिकेशन सर्वर (क्षैतिज रूप से स्केलेबल)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "डेटा परत"
        MYSQL["MySQL 8.0<br/>मास्टर-स्लेव रेप्लिकेशन<br/>erp_ उपसर्ग"]
        ES["Elasticsearch 8.x<br/>3 नोड क्लस्टर<br/>erp_ उपसर्ग"]
        REDIS["Redis 7.x<br/>सेंटिनल मोड<br/>poster:captcha:*"]
    end

    subgraph "मॉनिटरिंग"
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

## 14. ERP सिस्टम समग्र वास्तुकला

```mermaid
graph TB
    subgraph Client["क्लाइंट परत"]
        FW["Flutter Web<br/>PC प्रबंधन बैकएंड"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>हार्मनी नेटिव App"]
    end

    subgraph Gateway["API गेटवे परत"]
        MW["मिडलवेयर चेन<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["व्यावसायिक मॉड्यूल परत"]
        direction LR
        Admin["सिस्टम प्रबंधन<br/>उपयोगकर्ता/भूमिका/अनुमति/कॉन्फ़िग/लॉग"]
        Product["उत्पाद प्रबंधन<br/>उत्पाद/श्रेणी/ब्रांड/वेयरहाउस/सप्लायर/ग्राहक"]
        Purchase["क्रय प्रबंधन<br/>अनुरोध→ऑर्डर→प्राप्ति→वापसी→निपटान"]
        Sales["बिक्री प्रबंधन<br/>कोटेशन→ऑर्डर→शिपमेंट→वापसी→निपटान"]
        Inventory["इन्वेंटरी प्रबंधन<br/>प्रवेश/निकास/बैच/स्टॉक ले/ट्रांसफर/अलर्ट"]
        Finance["वित्त प्रबंधन<br/>खाता/वाउचर/प्राप्य देय/लेजर/विवरण/रिपोर्ट/रिइम्बर्समेंट"]
        CRM["CRM<br/>ग्राहक/संपर्क/फॉलोअप/फ़नल/पब्लिक पूल/कोटेशन/कॉन्ट्रैक्ट"]
        Workflow["अनुमोदन वर्कफ़्लो<br/>वर्कफ़्लो परिभाषा/सबमिट/स्वीकृत/अस्वीकृत/वापस लें"]
        Notification["संदेश नोटिफिकेशन<br/>नोटिफिकेशन सूची/पढ़ा/अपठित गणना"]
        Project["प्रोजेक्ट प्रबंधन<br/>प्रोजेक्ट/कार्य/कार्य-घंटे रिकॉर्ड"]
        HR["मानव संसाधन<br/>विभाग/कर्मचारी/पद/उपस्थिति/अवकाश/वेतन"]
        Manufacturing["उत्पादन निर्माण<br/>BOM/उत्पादन ऑर्डर/प्रक्रिया मार्ग/वर्कस्टेशन/MRP"]
        Report["कस्टम रिपोर्ट<br/>रिपोर्ट टेम्पलेट/डेटासेट/फ़ील्ड/फ़िल्टर/शेड्यूल"]
    end

    subgraph Service["व्यावसायिक सेवा परत"]
        IS["InventoryService<br/>प्रवेश/निकास + मूविंग वेटेड एवरेज लागत"]
        FS["FinanceService<br/>प्राप्य/देय स्वचालित जनरेशन + निपटान"]
        NS["NotificationService<br/>नोटिफिकेशन एकीकृत भेजना"]
    end

    subgraph Data["डेटा परत"]
        MySQL["MySQL 8.0<br/>163 व्यावसायिक तालिकाएँ"]
        Redis["Redis 7<br/>कैश/रेट लिमिट/Session"]
        ES["Elasticsearch 8<br/>फुल-टेक्स्ट खोज"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. क्रॉस-मॉड्यूल डेटा प्रवाह

```mermaid
sequenceDiagram
    participant PO as क्रय प्राप्ति
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as इन्वेंटरी तालिका
    participant COST as लागत रिकॉर्ड
    participant ARAP as प्राप्य/देय

    PO->>IS: stockIn(उत्पाद,मात्रा,इकाई मूल्य)
    IS->>INV: रीयल-टाइम इन्वेंटरी अपडेट करें(लॉक के साथ)
    IS->>COST: मूविंग वेटेड एवरेज लागत पुनर्गणना
    IS-->>PO: ट्रांज़ैक्शन ID लौटाएँ
    
    PO->>FS: createAp(सप्लायर,राशि)
    FS->>ARAP: देय रिकॉर्ड बनाएँ
    
    Note over PO,ARAP: बिक्री शिपमेंट समान: stockOut + createAr
```

---

## 16. इन्वेंटरी लागत गणना डेटा प्रवाह

```mermaid
graph LR
    A[क्रय प्राप्ति 100₹×10] --> B[प्रवेश ट्रांज़ैक्शन]
    C[क्रय प्राप्ति 130₹×20] --> D[प्रवेश ट्रांज़ैक्शन]
    B --> E[इन्वेंटरी: 10, लागत 100]
    D --> F[इन्वेंटरी: 30, लागत 120]
    E --> G[मूविंग वेटेड एवरेज: 100]
    F --> H[मूविंग वेटेड एवरेज: 120]
    H --> I[निकास पर लागत 120 पर गणना]
```

---

## 17. अनुमोदन वर्कफ़्लो डेटा प्रवाह

```mermaid
sequenceDiagram
    participant Biz as व्यावसायिक मॉड्यूल
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as वर्कफ़्लो इंजन
    participant NTF as NotificationService

    Biz->>WF: अनुमोदन सबमिट करें(व्यावसायिक नंबर,मॉड्यूल प्रकार)
    WF->>WFE: वर्कफ़्लो परिभाषा मिलाएँ→अनुमोदन इंस्टेंस बनाएँ
    WFE->>APR: पहले नोड के अनुमोदक को सूचित करें
    APR->>NTF: अनुमोदन नोटिफिकेशन भेजें
    NTF-->>APR: नोटिफिकेशन भेजा गया
    APR->>APR: अनुमोदक स्वीकृत/अस्वीकृत
    alt स्वीकृत
        APR->>WFE: अगले नोड पर आगे बढ़ें
        alt सभी नोड पास
            WFE->>Biz: कॉलबैक: अनुमोदन पारित, व्यावसायिक दस्तावेज़ स्थिति अपडेट
        end
    else अस्वीकृत
        WFE->>Biz: कॉलबैक: अनुमोदन अस्वीकृत
    end
```

---

## 18. संदेश नोटिफिकेशन डेटा प्रवाह

```mermaid
sequenceDiagram
    participant Event as इवेंट ट्रिगर स्रोत
    participant NS as NotificationService
    participant DB as नोटिफिकेशन तालिका
    participant User as उपयोगकर्ता

    Event->>NS: नोटिफिकेशन ट्रिगर करें(प्रकार,शीर्षक,सामग्री,प्राप्तकर्ता)
    NS->>DB: नोटिफिकेशन रिकॉर्ड लिखें
    NS-->>User: पुश(इन-साइट संदेश/WebSocket)
    User->>NS: पढ़ा हुआ चिह्नित करें
    NS->>DB: पढ़ा गया स्थिति अपडेट करें
    User->>NS: अपठित गणना पूछें
    NS-->>User: अपठित संख्या
```

---

## 19. MRP सामग्री आवश्यकता योजना डेटा प्रवाह

```mermaid
sequenceDiagram
    participant SO as बिक्री ऑर्डर
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as क्रय सुझाव
    participant MO as उत्पादन सुझाव

    SO->>MRP: बिक्री ऑर्डर मांग
    MRP->>BOM: BOM विस्तार कर सामग्री सूची प्राप्त करें
    BOM-->>MRP: सामग्री+मानक उपयोग दर
    MRP->>INV: इन्वेंटरी उपलब्धता पूछें
    INV-->>MRP: इन्वेंटरी मात्रा
    MRP->>MRP: शुद्ध आवश्यकता गणना = सकल आवश्यकता - इन्वेंटरी
    alt कच्चा माल अपर्याप्त
        MRP->>PO: क्रय सुझाव बनाएँ
    else अर्ध-तैयार उत्पाद अपर्याप्त
        MRP->>MO: उत्पादन सुझाव बनाएँ
    end
```

---

## 20. ERP मॉड्यूल कंट्रोलर-सेवा-मॉडल मैपिंग तालिका

> सेवा परत विवरण: `कोर सेवा` कॉलम उस मॉड्यूल को चिह्नित करता है जिसके व्यावसायिक सेवाएँ पहले से स्थानांतरित हो चुकी हैं; **⚠ कंट्रोलर सीधे मॉडल से क्वेरी करता है, ज्ञात तकनीकी ऋण** चिह्नित मॉड्यूल में,
> कंट्रोलर अभी भी सीधे मॉडल क्वेरी/राइट विधियों (`XxxModel::find/where/save` आदि) को कॉल करते हैं, सेवा परत अभी तक नहीं निकाली गई है, यह ज्ञात तकनीकी ऋण है,
> भविष्य में P2-F2 सेवा परत हल्के निष्कर्षण पैटर्न (`app/service/AbstractCrudService` सामान्य CRUD आधार वर्ग + मॉड्यूल सेवा) के अनुसार धीरे-धीरे समेकित किया जाएगा।

| मॉड्यूल | Controllers (निर्देशिका) | कोर सेवा | मुख्य Model | तालिका संख्या |
|------|-------------------|-------------|-----------|------|
| सिस्टम प्रबंधन | admin/controller/ (14) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | AdminUser, AdminRole, AdminPermission | 7 |
| उत्पाद प्रबंधन | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| क्रय प्रबंधन | controller/purchase/ (5) | InventoryService, FinanceService ⚠CRUD अभी भी सीधे क्वेरी, ज्ञात तकनीकी ऋण | PurchaseOrder, PurchaseReceive | 9 |
| बिक्री प्रबंधन | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD अभी भी सीधे क्वेरी, ज्ञात तकनीकी ऋण | SalesOrder, SalesDelivery | 9 |
| इन्वेंटरी प्रबंधन | controller/inventory/ (5) | InventoryService ⚠CRUD अभी भी सीधे क्वेरी, ज्ञात तकनीकी ऋण | Inventory, InventoryFlow, CostRecord | 11 |
| वित्त प्रबंधन | controller/finance/ (20) | FinanceService ⚠CRUD अभी भी सीधे क्वेरी, ज्ञात तकनीकी ऋण | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| अनुमोदन वर्कफ़्लो | controller/workflow/ (2) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| संदेश नोटिफिकेशन | controller/notification/ (1) | NotificationService ⚠CRUD अभी भी सीधे क्वेरी, ज्ञात तकनीकी ऋण | Notification, NotificationSetting, NotificationTemplate | 3 |
| प्रोजेक्ट प्रबंधन | controller/project/ (3) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| मानव संसाधन | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| उत्पादन निर्माण | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| कस्टम रिपोर्ट | controller/report/ (2) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM उपकरण प्रबंधन | controller/eam/ (4) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS दस्तावेज़ प्रबंधन | controller/dms/ (2) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI डैशबोर्ड | controller/bi/ (3) | - ⚠कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण | BiDashboard, BiWidget | 2 |

### 20.1 P2-F2 सेवा परत हल्के निष्कर्षण रिकॉर्ड (crm/hr/manufacturing/product निष्कर्षण पूर्ण)

| मॉड्यूल | निष्कर्षण से पहले कंट्रोलर सीधे क्वेरी कॉल | निष्कर्षण के बाद | नई सेवा | निष्कर्षण सामग्री |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | सामान्य CRUD + कॉन्ट्रैक्ट स्थिति संक्रमण, कोटेशन से कॉन्ट्रैक्ट, पब्लिक पूल क्लेम/रिलीज़, टिकट असाइन/समाधान/उत्तर, विवरण कैस्केड क्लीनअप, विश्लेषण रिपोर्ट डेटा निर्माण |
| मानव संसाधन | 38 | 0 | `app/service/hr/HrService.php` | सामान्य CRUD + अटेंडेंस लेट/जल्दी निकलना निर्धारण, अवकाश अनुमोदन (स्वचालित अवकाश उपस्थिति जनरेशन), वेतन अद्वितीयता/शुद्ध वेतन गणना/भुगतान/बैच जनरेशन |
| उत्पादन निर्माण | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | सामान्य CRUD + कार्य ऑर्डर प्रारंभ/समाप्त संक्रमण, BOM संस्करण प्रतिलिपि/प्रभावी पारस्परिक बहिष्करण, MRP विवरण जनरेशन |
| उत्पाद प्रबंधन | 29 | 0 | `app/service/product/ProductService.php` | सामान्य CRUD + उत्पाद ट्रांज़ैक्शन निर्माण (SKU/मूल्य), फ़ील्ड द्वारा मूल मान संरक्षित अपडेट, विवरण संबंधित लोडिंग |

निष्कर्षण पैटर्न: `app/service/AbstractCrudService.php` `list/all/find/create/update/delete/deleteWhere` सामान्य CRUD
और `normalizePageParams/canTransition` शुद्ध तर्क सहायक प्रदान करता है; मॉड्यूल सेवा इसे विरासत में लेती है और मॉड्यूल-विशिष्ट व्यवसाय जमा करती है।
कंट्रोलर `Container::get(XxxService::class)` (class_exists फॉलबैक) के माध्यम से सेवा इंजेक्ट करते हैं, रूट/पैरामीटर/रिटर्न संरचना पूरी तरह अपरिवर्तित रहती है;
hashid एन्कोडिंग/डिकोडिंग, पासवर्ड द्वितीय पुष्टि, रिस्पॉन्स रैपिंग जैसे HTTP संबंधी पहलू कंट्रोलर में ही रहते हैं।
नई सेवाएँ `config/dependence.php` में पंजीकृत हैं (यह फ़ाइल dead config है, addDefinitions द्वारा लोड नहीं होती, रनटाइम कंटेनर
class_exists फॉलबैक इंस्टैंशिएशन पर निर्भर करता है, इसलिए सभी सेवाएँ बिना-पैरामीटर कंस्ट्रक्टर रखती हैं)।

निष्कर्षण न होने वाले मॉड्यूल (प्रोजेक्ट प्रबंधन 18 बार, कस्टम रिपोर्ट 18 बार, क्रय 24 बार, बिक्री 24 बार, सिस्टम प्रबंधन 42 बार आदि) तालिका में
"कंट्रोलर सीधे मॉडल क्वेरी, ज्ञात तकनीकी ऋण" के रूप में चिह्नित हैं, अगले इटरेशन में उसी पैटर्न के अनुसार निष्कर्षण किया जाएगा।

---

## OMS/WMS/TMS विस्तार मॉड्यूल (2026-08)

### OMS (Order Management System) — 8 तालिकाएँ
- **ऑर्डर विस्तार** (`erp_oms_order`): मल्टी-चैनल एग्रीगेशन/फ़ुलफ़िलमेंट स्थिति/भुगतान स्थिति/प्राथमिकता
- **ऑर्डर पता** (`erp_oms_order_address`): डिलीवरी/बिलिंग पता (बहु-देश प्रारूप)
- **फ़ुलफ़िलमेंट रिकॉर्ड** (`erp_oms_fulfillment`+`_item`): आवंटित/पिक किया/पैक किया/भेजा गया मात्रा ट्रैकिंग
- **RMA** (`erp_oms_rma`+`_item`): वापसी/अदला-बदली पूर्ण जीवनचक्र
- **इन्वेंटरी प्री-रिज़र्वेशन** (`erp_oms_inventory_reservation`): ATP = physical - reserved
- **चैनल** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 तालिकाएँ
- **वेयरहाउस ज़ोन/लोकेशन** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **इनबाउंड** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **आउटबाउंड** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 तालिकाएँ
- **कैरियर** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **शिपमेंट** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **इनवॉइस** (`erp_tms_freight_invoice`)

### डेटा प्रवाह
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. इकोसिस्टम रोडमैप (2026-08)

> विस्तृत डिज़ाइन स्पेक: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 बेसलाइन मूल्यांकन (रोडमैप प्रारंभ के समय)

> P0~P3 सभी डिलीवर हो चुके हैं, वर्तमान समग्र स्कोर 89/100 (CLAUDE.md देखें); निम्न तालिका रोडमैप प्रारंभ से पहले की बेसलाइन स्नैपशॉट है।

| आयाम | स्कोर | प्रमुख अंतर |
|------|------|----------|
| बैकएंड API | 85/100 | कई मॉड्यूल CRUD कंकाल हैं, व्यावसायिक गणना इंजन की कमी |
| सुरक्षा सुरक्षा | 95/100 | 18 परत गहन रक्षा, उत्पादन-तैयार |
| फ्रंटएंड UI | 20/100 | **सबसे बड़ी कमी**: Flutter 12 पेज ~20% मॉड्यूल कवर करते हैं, Web प्रबंधन पैनल अनुपलब्ध |
| ऑप्स इकोसिस्टम | 70/100 | माइग्रेशन रोलबैक, स्वचालित बैकअप, ऑब्ज़र्वेबिलिटी की कमी |
| व्यावसायिक गहराई | 55/100 | वित्त/HR/निर्माण मुख्य एल्गोरिदम लागू नहीं |
| **समग्र** | **65/100** | |

### 21.2 चार-चरण अनुक्रमिक रोडमैप

```
P0(3-4 सप्ताह) → P1(4-6 सप्ताह) → P2(1-2 सप्ताह) → P3(2-3 सप्ताह) = कुल लगभग 13 सप्ताह
```

| चरण | नाम | मुख्य डिलीवरी |
|------|------|----------|
| **P0** | फ्रंटएंड इकोसिस्टम | Flutter Web पूर्ण-मॉड्यूल प्रबंधन पैनल (14 मॉड्यूल 40+ पेज), सामान्य कंपोनेंट लाइब्रेरी, HarmonyOS संरेखण |
| **P1** | व्यावसायिक गहराई | वित्तीय डबल-एंट्री इंजन, वेतन गणना इंजन, MRP इंजन, गुणवत्ता प्रबंधन मॉड्यूल, रीयल-टाइम नोटिफिकेशन (WebSocket) |
| **P2** | ऑप्स विश्वसनीयता | डेटाबेस माइग्रेशन रोलबैक, स्वचालित बैकअप वृद्धि, OpenTelemetry ट्रेसिंग, RabbitMQ क्यू ड्राइवर |
| **P3** | अनुभव वृद्धि | BI ड्रैगेबल डैशबोर्ड, उपकरण प्रबंधन (EAM), मल्टी-टेनेंट आइसोलेशन, दस्तावेज़ प्रबंधन (DMS) |

### 21.3 मिडलवेयर चेन विकास

```
वर्तमान:   Locale → Cors → SecurityFilter → RateLimit → TracingId → {रूट समूह}
P1 के बाद:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {रूट समूह}
P2 के बाद:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {रूट समूह}
P3 के बाद:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {रूट समूह}
```

### 21.4 P0 लक्ष्य वास्तुकला — Flutter Web प्रबंधन पैनल

| परत | नई सामग्री |
|------|----------|
| लेआउट परत | `AdminLayout` PC तीन-कॉलम लेआउट (कोलेप्सिबल साइडबार + टॉप बार + कंटेंट क्षेत्र) |
| कंपोनेंट परत | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| पेज परत | मौजूदा 12 पेजों से 14 मॉड्यूल 40+ पेज पूर्ण कवरेज तक विस्तार |
| सेवा परत | मौजूदा `ApiService`, `AuthService`, `CaptchaService`, `ExportService` का पुन: उपयोग |

### 21.5 P1 लक्ष्य वास्तुकला — व्यावसायिक गणना इंजन

| इंजन | सेवा वर्ग | प्रमुख नियम |
|------|--------|----------|
| डबल-एंट्री बुककीपिंग | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | डेबिट/क्रेडिट संतुलन अनिवार्य सत्यापन, अवधि अंत लाभ/हानि कैरी-फॉरवर्ड, मल्टी-करेंसी विनिमय दर रूपांतरण |
| वेतन गणना | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | सामाजिक बीमा आधार ऊपरी/निचली सीमा, आवास निधि अनुपात, व्यक्तिगत कर प्रगतिशील दर, बैंक बल्क भुगतान |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM परत-दर-परत विस्तार + अपशिष्ट, लो-लेवल कोड (LLC), सुरक्षा स्टॉक, बैच नियम |
| गुणवत्ता | `QmsInspectionService` | IQC आगमन/IPQC प्रक्रिया/OQC शिपमेंट तीन-दस्तावेज़ संक्रमण |
| नोटिफिकेशन | `WebSocketService`, `ChannelRouter` | इन-साइट/ईमेल/वीचैट-वर्क/डिंगटॉक मल्टी-चैनल |

### 21.6 डेटा मॉडल परिवर्तन सारांश

| चरण | नई तालिका संख्या | संबंधित मॉड्यूल |
|------|----------|----------|
| P0 | 0 | शुद्ध फ्रंटएंड, कोई तालिका परिवर्तन नहीं |
| P1 | 14 | वित्त(2) + HR(3) + निर्माण(2) + गुणवत्ता(5) + नोटिफिकेशन(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. मल्टी-टेनेंट (आरक्षित क्षमता, सक्षम नहीं)

> कॉपीराइट घोषणा फ़ाइल शीर्षक के समान: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 स्थिति और निर्णय

मल्टी-टेनेंट इस प्रोजेक्ट में **आरक्षित क्षमता** के रूप में स्थित है, इस चरण में **वायर-अप नहीं, सक्षम नहीं** (दस्तावेज़ित डाउनग्रेड)। योजना के अनुरूप:
SaaS बिलिंग, टेनेंट सेल्फ-सर्विस ऑनबोर्डिंग जैसे "मल्टी-टेनेंट पूर्ण व्यावसायीकरण समाधान" इस प्रोजेक्ट के निर्माण दायरे में नहीं हैं; इस चरण में केवल न्यूनतम
कोड कंकाल (मिडलवेयर + मॉडल Trait) रखा गया है और सक्षम करने के चरण दिए गए हैं, ताकि भविष्य में आवश्यकता अनुसार सक्षम किया जा सके।
नोट: §21.2 रोडमैप P3 में "मल्टी-टेनेंट आइसोलेशन" को तदनुसार "आरक्षित क्षमता (दस्तावेज़ित डाउनग्रेड)" में समायोजित किया गया है, कंकाल रखा गया है, वायर-अप नहीं।

निर्णय आधार (2026-08 समीक्षा):
- मौजूदा डिप्लॉयमेंट लगभग सभी सिंगल-टेनेंट हैं, वायर-अप अनावश्यक आइसोलेशन जटिलता और रिग्रेशन जोखिम लाएगा;
- वर्तमान कंकाल में तकनीकी खामियाँ हैं (22.4 देखें), "वायर-अप = आइसोलेशन" सही नहीं है, पहले डिज़ाइन सुधार पूरा करना आवश्यक है;
- आइसोलेशन के लिए 163 तालिकाओं में से व्यावसायिक तालिकाओं में एक-एक करके कॉलम जोड़ने और एक-एक मॉडल सक्षम करने की आवश्यकता है, लागत "न्यूनतम वायर-अप" से कहीं अधिक है।

### 22.2 वर्तमान तथ्य (कोड और कॉन्फ़िग सत्यापन)

| आइटम | वर्तमान स्थिति |
|----|------|
| `app/middleware/TenantScope.php` | मौजूद है, पंजीकृत नहीं; `X-Tenant-Id` हेडर से टेनेंट पढ़ता है, हेडर अनुपलब्ध होने पर सीधे पास करता है |
| `app/model/concerns/TenantScope.php` | मौजूद है, कोई मॉडल उपयोग नहीं करता; `bootTenantScope()` वैश्विक स्कोप केवल टेनेंट सेट होने पर फ़िल्टर करता है |
| `config/middleware.php` | वैश्विक चेन: Locale → Cors → SecurityFilter → RateLimit → TracingId, कोई TenantScope नहीं |
| `config/route.php` /admin समूह | AdminAuth → AdminPermission → OperationLog, कोई TenantScope नहीं |
| JWT पेलोड | केवल `sub` / `username` / `token_type`, **कोई tenant_id दावा नहीं** (`app/api/v1/controller/AuthController.php`) |
| डेटाबेस | **पूरे डेटाबेस में कोई tenant_id कॉलम नहीं** (install.sql में भी नहीं) |
| मॉडल | **कोई भी मॉडल TenantScope trait का उपयोग नहीं करता** |

### 22.3 सक्षम करने के चरण (आरक्षित संदर्भ, इस चरण में निष्पादित नहीं)

1. मिडलवेयर पंजीकृत करें: `config/route.php` के /admin समूह में `middleware()` में जोड़ें
   `app\middleware\TenantScope::class` (AdminAuth के बाद रखें, सुनिश्चित करें कि प्रमाणीकृत है)।
2. अनुरोधकर्ता अनुरोध हेडर में `X-Tenant-Id` (int टेनेंट ID) भेजे।
3. आइसोलेशन आवश्यक व्यावसायिक तालिकाओं में `tenant_id` कॉलम (BIGINT + इंडेक्स) जोड़ें और मौजूदा डेटा बैकफ़िल करें;
   डिक्शनरी/सिस्टम तालिकाएँ (जैसे `erp_admin_user`, `erp_role`, `erp_permission`) आइसोलेट नहीं होतीं।
4. आइसोलेशन आवश्यक मॉडल वर्गों में `use app\model\concerns\TenantScope;` जोड़ें, स्वचालित रूप से वर्तमान टेनेंट द्वारा फ़िल्टर होगा।
5. (वैकल्पिक) यदि JWT से टेनेंट लेना हो (अनुरोध हेडर के बजाय): लॉगिन टोकन पेलोड में `tenant_id` दावा जोड़ें,
   और मिडलवेयर में `$payload['tenant_id']` से पढ़ें।

### 22.4 ज्ञात तकनीकी सीमाएँ (सक्षम करने से पहले हल करना अनिवार्य)

- **स्टैटिक ट्रांसमिशन चेन टूटना (PHP 8.3 पर परीक्षित)**: मिडलवेयर trait नाम के माध्यम से `setCurrentTenantId()` कॉल करता है,
  जो trait की स्वयं की स्टैटिक प्रतिलिपि में लिखता है, trait का उपयोग करने वाला मॉडल वर्ग इसे पढ़ नहीं पाता, क्वेरी फ़िल्टर नहीं होती।
  सक्षम करते समय अनुरोध संदर्भ-आधारित इंजेक्शन (जैसे `request()->tenantId`) में बदलना होगा।
- **स्टैटिक वैश्विक स्थिति क्रॉस-टॉक**: Workerman रेसिडेंट प्रोसेस है, स्टैटिक प्रॉपर्टी अनुरोधों के बीच साझा होती हैं; यदि कोरुटीन मोड
  (Swoole/Swow) सक्षम हो तो क्रॉस-टेनेंट डेटा क्रॉस-टॉक होगा, अनुरोध-स्तर बाइंडिंग (`context()` / अनुरोध ऑब्जेक्ट) में बदलना होगा।
- **डेटा प्लेन गैप**: पूरे डेटाबेस में tenant_id कॉलम नहीं है, तालिका-दर-तालिका माइग्रेशन आवश्यक है; क्रॉस-टेनेंट साझा डिक्शनरी तालिकाओं के लिए छूट तंत्र डिज़ाइन करना होगा।

### 22.5 स्वीकृति मानदंड

इस चरण की स्वीकृति = दस्तावेज़ और कोड का समान होना: `config/middleware.php` और `config/route.php` में
TenantScope पंजीकरण नहीं होना चाहिए; मिडलवेयर और Trait टिप्पणियों में स्पष्ट रूप से "आरक्षित क्षमता, सक्षम नहीं" चिह्नित और सक्षम करने के चरण दिए होने चाहिए;
यह अनुभाग कोड की वर्तमान स्थिति के अनुरूप बिंदु-दर-बिंदु मेल खाना चाहिए।
