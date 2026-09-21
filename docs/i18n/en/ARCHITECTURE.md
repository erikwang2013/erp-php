# Architecture Diagrams and Business Logic Diagrams

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> The Mermaid diagrams below render automatically in GitHub / GitLab / VS Code. For other environments, use the [Mermaid Live Editor](https://mermaid.live/) to view them.

---

## 1. System Topology Architecture

```mermaid
flowchart TB
    subgraph "Client Layer"
        A1["Flutter Web<br/>PC Admin Console<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Phone/Tablet Client"]
        A3["Angular 22 + ng-zorro<br/>Web Admin Console"]
        A4["React 19 + Vite<br/>Web Admin Console"]
    end

    subgraph "Gateway/Edge Layer (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse Proxy + HTTPS + Gzip<br/>Static File Serving"]
    end

    subgraph "Application Layer (webman v2)"
        C_LOC["I18n::getLocale()<br/>Accept-Language parsing · 13 locales"]
        C0["Path-based versioning<br/>/api/v1 · /admin/v1 (no version header)"]
        C1["AdminAuth Middleware<br/>JWT verification"]
        C2["AdminPermission Middleware<br/>RBAC permission validation"]
        C3["Admin Controllers<br/>Dashboard / User / Role / Permission"]
        C4["Public Controllers v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Storage Layer"
        D1[("MySQL 8.0<br/>Primary storage<br/>Table prefix erp_")]
        D2[("Elasticsearch<br/>Full-text search<br/>Index prefix erp_")]
        D3[("Redis<br/>Session / Cache<br/>Captcha storage")]
    end

    subgraph "External"
        E1["DevEco Studio<br/>HarmonyOS build"]
        E2["Flutter SDK<br/>Web build"]
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

## 2. Backend Layered Architecture

```mermaid
flowchart TD
    subgraph "Route Layer"
        R1["config/route.php<br/>URL → Controller mapping"]
    end

    subgraph "Middleware Layer"
        M_CR["Cors<br/>Cross-origin handling / OPTIONS preflight"]
        M_SF["SecurityFilter<br/>Attack detection and blocking<br/>XSS/SQL injection/path traversal/CSRF"]
        M_RL["RateLimit<br/>Redis sliding-window rate limiting<br/>X-RateLimit response headers"]
        M_TID["TracingId<br/>Generates X-Trace-Id<br/>spans the whole chain"]
        M1["AdminAuth<br/>JWT Token validation<br/>injects adminId"]
        M2["AdminPermission<br/>RBAC authorization<br/>method.path matching<br/>Redis 60s permission cache"]
    end

    subgraph "Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + search + pagination"]
        CT3["RoleController<br/>CRUD + permission sync"]
        CT4["PermissionController<br/>CRUD + tree building"]
        CT5["DashboardController<br/>statistics/trends/distribution"]
        CT6["ExportController<br/>Excel/PDF export"]
        CT7["CaptchaController<br/>Captcha generation/validation"]
        CT8["AuthController<br/>Login/register/refresh"]
    end

    subgraph "Service Layer"
        S1["HashidsService<br/>ID encode/decode"]
        S2["SnowflakeService<br/>Globally unique ID generation"]
        S3["EncryptionService<br/>Encryption + masking"]
        M_LOC["I18n::getLocale()<br/>Accept-Language parsing (not a middleware)<br/>13 locales zh_CN/en/ja/ko/de<br/>fr/es/pt/ru/ar/hi/bn/id"]
    end

    subgraph "Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Driver Layer"
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

### ERP Business Layer Extension

As the system evolves from a pure admin console into a complete ERP system, the following business modules are added to the controller and service layers:

| Layer | Directory | Description |
|------|------|------|
| Business controllers | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report,oms,wms,tms,quality,eam,dms,open,platform,print,retail,bi}/` | 139 (23 business domains, plus top-level Install / Index), organized per module, handling business requests |
| Business services | `app/service/{finance,inventory,notification,crm,hr,manufacturing,oms,wms,tms,quality,…}/` | 63 service classes / 64 files / 20 module subdirectories; covering inventory stock in/out + costing, finance AR/AP + write-off, notification sending |

### Internationalization (13 Languages)

Locale resolution lives in `getLocale()` in `app/common/I18n.php` (called by `I18n::trans()`, **not a middleware**): it takes the first tag of the `Accept-Language` request header and maps the primary language subtag (`zh*` → `zh_CN`). Division of labour between the frontend and backend dictionaries:

| End | Dictionary location | Scale | Generator |
|----|----------|------|--------|
| Backend | `resource/translations/<locale>/{common,modules,validation}.php` | 13 locale directories; `zh_CN` 565 entries, each of the other 11 locales 544 entries, `en` 30 entries (leaf-entry scope; the `attributes` field labels in `validation.php` count, their group keys do not) | `scripts/gen-be-locales.mjs` |
| Angular | source `apps/angular/src/app/core/zh-en/part1..4.ts` → artifacts `core/zh-<code>.ts` | 1456 source entries | `scripts/gen-fe-locales.mjs --app angular` |
| React | source `apps/react/src/lib/i18n/zhEn.ts` → artifacts `lib/i18n/zh<Code>.ts` | 1451 source entries | `scripts/gen-fe-locales.mjs --app react` |

- Languages: `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`.
- Backend "English is the key": `en`'s common/modules are left empty; the keys in `validation.php` are framework rule names, so only the values are translated.
- Each of the 11 new frontend locale dictionaries is dynamically `import()`ed into its own chunk; missing entries fall back to the original Chinese text.
- Switching language switches `Accept-Language`, and the backend returns text per locale (`app/common/I18n.php` + `config/translation.php`).

---

## 3. Request Lifecycle

```mermaid
sequenceDiagram
    participant C as Client
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

    C->>N: HTTPS request<br/>path /api/v1 or /admin/v1 (no version header)
    N->>MW_CR: Forward
    MW_CR->>MW_CR: Handle OPTIONS preflight<br/>inject CORS response headers
    MW_CR->>MW_SF: Pass

    alt Non-standard HTTP method (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Valid method (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Method whitelist check passed
    end

    alt Attack detection triggered
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Pass

    alt Rate limit triggered
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW_TID: Pass
    MW_TID->>MW_TID: Generate X-Trace-Id<br/>inject response header
    MW_TID->>MW1: Pass

    alt Token missing or invalid
        MW1-->>C: 401 Unauthorized
    else Valid token
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt No permission
        MW2-->>C: 403 Forbidden
    else Has permission
        MW2->>CTL: Enter controller
    end

    CTL->>CTL: Parameter validation (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Sensitive operation (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Wrong password
            CTL-->>C: 422 Password verification failed
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast auto-decrypt
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Build response JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Record operation log (POST/PUT/DELETE)
```

---

## 4. Authentication and Captcha Flow

```mermaid
sequenceDiagram
    participant U as User
    participant CL as Client
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Step 1: Get captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Generate 300×200 background image
    CAP->>CAP: Randomly place N Chinese targets
    CAP->>CAP: Generate key, store targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Step 2: User clicks ===
    CL->>CL: Render captcha image
    CL->>CL: Prompt "Please click in order: tree → bird → flower"
    U->>CL: Click text positions in the image in sequence
    CL->>CL: Collect clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Step 3: Login ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha error
        CAP-->>SV: false
        SV-->>CL: 422 Captcha error
    else Captcha correct
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Wrong credentials
            SV-->>CL: 401 Wrong username or password
        else Correct credentials
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Subsequent requests ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC Permission Model

```mermaid
flowchart LR
    subgraph "Users"
        U1["admin<br/>(Super Admin)"]
        U2["editor<br/>(Editor)"]
        U3["viewer<br/>(Read-only)"]
    end

    subgraph "Roles"
        R1["super_admin<br/>permission slug: *"]
        R2["editor<br/>permission slug: get.*, post.*"]
        R3["viewer<br/>permission slug: get.*"]
    end

    subgraph "Permissions (Tree)"
        P1["dashboard<br/>type=1 menu"]
        P2["user<br/>type=1 menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 button"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (all permissions)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Permission Types"
        T1["type=1 menu<br/>controls sidebar show/hide"]
        T2["type=2 button<br/>controls page action buttons"]
        T3["type=3 API<br/>controls endpoint access"]
    end

    subgraph "Permission Slug Format"
        F1["{method}.{path}<br/>e.g. get.admin/user<br/>e.g. post.admin/user<br/>e.g. delete.admin/role"]
    end

    subgraph "Decision Flow"
        J1["Extract Token → adminId"]
        J2["Find user roles"]
        J3["Collect all permission slugs"]
        J4["Build method.path"]
        J5{"Match?"}
        J6["Allow"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Yes / slug=*"| J6
        J5 -->|No| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID Full Lifecycle

```mermaid
flowchart LR
    subgraph "1. Generation"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>e.g. 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Storage"
        S1["MySQL erp_* tables<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Sensitive fields<br/>encryptable cast<br/>AES-128-ECB encryption"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transport"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid string<br/>e.g. aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Reverse Decode"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Data Encryption Layers

```mermaid
flowchart TB
    subgraph "Transport Layer Encryption (encryption)"
        E1["Client sends sensitive data"]
        E2["AES-256-CBC encryption"]
        E3["Ciphertext in API transport"]
        E4["Server decrypts and processes"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Storage Layer Encryption (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Write: auto-encrypt"]
        D3["MySQL VARCHAR(500)<br/>stores ciphertext"]
        D4["Read: auto-decrypt"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Display Layer Masking (mask)"
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

## 8. Database ER Relationships

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "encrypted"
        VARCHAR phone "encrypted"
        VARCHAR id_card "encrypted"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "soft delete"
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
        BIGINT parent_id FK "self-reference"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1menu 2button 3API"
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
        VARCHAR source "source client"
        TEXT input "masked"
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

## 9. Export Business Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as File System

    Note over C,FS: === Excel export ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Data
    CTL->>CTL: Decrypt sensitive fields
    CTL->>CTL: Masking (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet build<br/>header blue bg white text<br/>data rows thin borders<br/>freeze first row<br/>auto filter
    CTL->>FS: Write runtime/tmp/export_*.xlsx
    CTL-->>C: File download
    Note over C,FS: === PDF export ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>header: title+copyright+time<br/>content: table or cards<br/>footer: non-removable copyright
    CTL->>CTL: Dompdf renders A4 landscape
    CTL->>FS: Write runtime/tmp/export_*.pdf
    CTL-->>C: File download
```

---

## 10. Flutter Web Component Tree

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Login form<br/>username/password/captcha"]
    LF --> CAPTCHA["Click captcha component<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>click marks Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>collapsible 64px / 240px<br/>Dashboard/Users/Roles/Config/Logs"]
    DB --> HEADER["Top bar 56px<br/>collapse button + user menu<br/>logout AlertDialog"]
    DB --> CONTENT["Content area"]
    CONTENT --> DASH["DashboardPage<br/>stat cards GridView<br/>trend LineChart<br/>distribution PieChart<br/>recent operations ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS Page Routing

```mermaid
flowchart LR
    EA["EntryAbility<br/>startup"]
    EA -->|"no Token"| LP["LoginPage<br/>login page"]
    EA -->|"has Token"| DP["DashboardPage<br/>dashboard"]

    LP -->|"login success<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>user list"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>profile center"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>user detail/create/edit"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"logout<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Defense in Depth Overview

```mermaid
flowchart TB
    subgraph "Layer 1: Human verification"
        L1["Click captcha<br/>Click Captcha<br/>mandatory for login/register"]
    end

    subgraph "Layer 2: Operation confirmation"
        L2["Password second confirmation<br/>confirmPassword()<br/>mandatory for DELETE operations"]
    end

    subgraph "Layer 3: Transport security"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Layer 4: Identity authentication"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Layer 5: Permission authorization"
        L5["RBAC<br/>method.path granularity<br/>super admin *"]
    end

    subgraph "Layer 6: Data protection"
        L6["API IDs: Hashids encryption<br/>request body: Encryption encryption<br/>storage: Encryptable encryption<br/>export: masking+copyright"]
    end

    subgraph "Layer 7: Audit trail"
        L7["OperationLog<br/>records all operations<br/>user/IP/time/source client/params"]
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

## 13. Deployment Topology

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web Server"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Static files<br/>Flutter Web build/"]
    end

    subgraph "Application Servers (horizontally scalable)"
        WM1["webman worker 1<br/>:8788"]
        WM2["webman worker 2<br/>:8788"]
        WM3["webman worker N<br/>:8788"]
    end

    subgraph "Data Layer"
        MYSQL["MySQL 8.0<br/>master-slave replication<br/>erp_ prefix"]
        ES["Elasticsearch 8.x<br/>3-node cluster<br/>erp_ prefix"]
        REDIS["Redis 7.x<br/>sentinel mode<br/>poster:captcha:*"]
    end

    subgraph "Monitoring"
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
## 14. ERP System Overall Architecture

```mermaid
graph TB
    subgraph Client["Client Layer"]
        FW["Flutter Web<br/>PC Admin Panel"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>HarmonyOS Native App"]
        NG["Angular 22 + ng-zorro<br/>Web Admin Console"]
        RC["React 19 + Vite<br/>Web Admin Console"]
    end

    subgraph Gateway["API Gateway Layer"]
        MW["Middleware Chain<br/>Cors→SecurityFilter→RateLimit→TracingId<br/>route groups: AdminAuth→AdminPermission→OperationLog"]
    end

    subgraph Business["Business Module Layer"]
        direction LR
        Admin["System Management<br/>Users/Roles/Permissions/Config/Logs"]
        Product["Product Management<br/>Products/Categories/Brands/Warehouses/Suppliers/Customers"]
        Purchase["Purchase Management<br/>Apply→Order→Receive→Return→Settlement"]
        Sales["Sales Management<br/>Quote→Order→Delivery→Return→Settlement"]
        Inventory["Inventory Management<br/>In/Out/Batches/Counts/Transfers/Alerts"]
        Finance["Finance Management<br/>Accounts/Vouchers/AR-AP/General Ledger/Detail Ledger/Reports/Reimbursement"]
        CRM["CRM<br/>Customers/Contacts/Follow-ups/Funnel/Public Pool/Quotations/Contracts"]
        Workflow["Approval Workflow<br/>Workflow Definition/Submit/Approve/Reject/Withdraw"]
        Notification["Message Notifications<br/>Notification List/Read/Unread Count"]
        Project["Project Management<br/>Projects/Tasks/Timesheets"]
        HR["Human Resources<br/>Departments/Employees/Positions/Attendance/Leave/Payroll"]
        Manufacturing["Manufacturing<br/>BOM/Production Orders/Routings/Workstations/MRP"]
        Report["Custom Reports<br/>Report Templates/Datasets/Fields/Filters/Scheduling"]
    end

    subgraph Service["Business Service Layer"]
        IS["InventoryService<br/>In/Out + Moving Weighted Average Cost"]
        FS["FinanceService<br/>AR-AP Auto-Generation + Reconciliation"]
        NS["NotificationService<br/>Unified Notification Sending"]
    end

    subgraph Data["Data Layer"]
        MySQL["MySQL 8.0<br/>227 Business Tables"]
        Redis["Redis 7<br/>Cache/Rate Limiting/Session"]
        ES["Elasticsearch 8<br/>Full-Text Search"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Cross-Module Data Flow

```mermaid
sequenceDiagram
    participant PO as Purchase Receive
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Inventory Table
    participant COST as Cost Records
    participant ARAP as AR/AP

    PO->>IS: stockIn(product, qty, unit price)
    IS->>INV: Update live inventory (locked)
    IS->>COST: Recompute moving weighted average cost
    IS-->>PO: Return flow record ID
    
    PO->>FS: createAp(supplier, amount)
    FS->>ARAP: Generate AP record
    
    Note over PO,ARAP: Sales delivery likewise: stockOut + createAr
```

---

## 16. Inventory Costing Data Flow

```mermaid
graph LR
    A[Purchase receive 100 CNY x 10] --> B[Inbound flow]
    C[Purchase receive 130 CNY x 20] --> D[Inbound flow]
    B --> E[Inventory: 10 units, cost 100]
    D --> F[Inventory: 30 units, cost 120]
    E --> G[Moving weighted average: 100]
    F --> H[Moving weighted average: 120]
    H --> I[Outbound costs at 120]
```

---

## 17. Approval Workflow Data Flow

```mermaid
sequenceDiagram
    participant Biz as Business Module
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Workflow Engine
    participant NTF as NotificationService

    Biz->>WF: Submit approval (business doc no., module type)
    WF->>WFE: Match workflow definition → create approval instance
    WFE->>APR: Notify first node's approver
    APR->>NTF: Send approval notification
    NTF-->>APR: Notification sent
    APR->>APR: Approver approves/rejects
    alt Approved
        APR->>WFE: Advance to next node
        alt All nodes approved
            WFE->>Biz: Callback: approved, update business doc status
        end
    else Rejected
        WFE->>Biz: Callback: rejected
    end
```

---

## 18. Message Notification Data Flow

```mermaid
sequenceDiagram
    participant Event as Event Trigger Source
    participant NS as NotificationService
    participant DB as Notifications Table
    participant User as User

    Event->>NS: Trigger notification (type, title, content, recipient)
    NS->>DB: Write notification record
    NS-->>User: Push (in-app/WebSocket)
    User->>NS: Mark as read
    NS->>DB: Update read status
    User->>NS: Query unread count
    NS-->>User: Unread count
```

---

## 19. MRP Material Requirements Planning Data Flow

```mermaid
sequenceDiagram
    participant SO as Sales Order
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Purchase Suggestion
    participant MO as Production Suggestion

    SO->>MRP: Sales order demand
    MRP->>BOM: Explode BOM to get material list
    BOM-->>MRP: Materials + standard usage
    MRP->>INV: Query available inventory
    INV-->>MRP: Inventory quantity
    MRP->>MRP: Net requirement = gross requirement - inventory
    alt Raw materials insufficient
        MRP->>PO: Generate purchase suggestion
    else Semi-finished goods insufficient
        MRP->>MO: Generate production suggestion
    end
```

---

## 20. ERP Module Controller-Service-Model Mapping Table

> Service layer notes: the `Core Service` column marks business services already extracted for the module;
> modules marked **⚠ controller queries model directly, known tech debt** still have controllers calling
> model query/write methods directly (`XxxModel::find/where/save` etc.) without an extracted service layer;
> this is known tech debt, to be gradually converged under the P2-F2 lightweight service-layer extraction
> pattern (`app/service/AbstractCrudService` generic CRUD base class + module Service).

| Module | Controllers (Directory) | Core Service | Main Models | Tables |
|------|-------------------|-------------|-----------|------|
| System Management | admin/controller/ (16) | - ⚠ controller queries model directly, known tech debt | AdminUser, AdminRole, AdminPermission | 7 |
| Product Management | controller/product/ (8) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 12 |
| Purchase Management | controller/purchase/ (8) | InventoryService, FinanceService ⚠ CRUD still queries directly, known tech debt | PurchaseOrder, PurchaseReceive | 14 |
| Sales Management | controller/sales/ (5) | InventoryService, FinanceService ⚠ CRUD still queries directly, known tech debt | SalesOrder, SalesDelivery | 9 |
| Inventory Management | controller/inventory/ (6) | InventoryService ⚠ CRUD still queries directly, known tech debt | Inventory, InventoryFlow, CostRecord | 11 |
| Finance Management | controller/finance/ (28) | FinanceService ⚠ CRUD still queries directly, known tech debt | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 38 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Approval Workflow | controller/workflow/ (3) | - ⚠ controller queries model directly, known tech debt | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Message Notifications | controller/notification/ (2) | NotificationService ⚠ CRUD still queries directly, known tech debt | Notification, NotificationSetting, NotificationTemplate | 4 |
| Project Management | controller/project/ (4) | - ⚠ controller queries model directly, known tech debt | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 6 |
| Human Resources | controller/hr/ (9) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 21 |
| Manufacturing | controller/manufacturing/ (13) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 21 |
| Custom Reports | controller/report/ (2) | - ⚠ controller queries model directly, known tech debt | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM Equipment Management | controller/eam/ (5) | - ⚠ controller queries model directly, known tech debt | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart, EamInspectionTask, EamInspectionResult | 6 |
| DMS Document Management | controller/dms/ (2) | - ⚠ controller queries model directly, known tech debt | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI Dashboards | controller/bi/ (3) | - ⚠ controller queries model directly, known tech debt | BiDashboard, BiWidget | 2 |

> This table is an early module mapping (system management + 15 business domains); the 8 domains added later — oms / wms / tms / quality / open / platform / print / retail — are not listed;
> the full list is in the `docs/CLAUDE.md` project structure tree (`app/controller/` has 23 module directories / 139 controllers in total, including the top-level Install and Index).
>
> `Tables` caliber (measured 2026-09-15): take the 227 tables in `database/install.sql` and attribute each to exactly one module by table-name prefix — system management `admin_*`+`system_config`+`operation_log`;
> product management `product*`/`category`/`brand`/`warehouse`/`location`/`supplier`/`customer*`; purchase management `purchase_*`+`supplier_assessment`; inventory management `inventory*`/`transfer*`/`check_*`/`cost_record`;
> the remaining modules use their same-named table prefixes (`sales_*`→sales, `finance_*`→finance, `crm_*`→CRM, `approval_*`→approval, `notification*`→notification, `project*`→project, `hr_*`→HR, `mfg_*`→manufacturing, `report_*`→reports, `eam_*`→EAM, `dms_*`→DMS, `bi_*`→BI).
> One table is attributed to only one column; the domains added later plus shared tables total 48 tables (`oms_`/`wms_`/`tms_`/`quality_`/`openapi_`/`webhook_`/`member_`/`print_template`/`company`/`tenant`/`channel`/`custom_field_definition`/`tax_*`) and are not counted in any row of this table.
> Recompute: ``grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | cut -d_ -f1 | sort | uniq -c | sort -rn``

### 20.1 P2-F2 Lightweight Service-Layer Extraction Record (crm/hr/manufacturing/product extraction completed)

| Module | Direct Controller Queries Before Extraction | After Extraction | New Service | Extracted Content |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | Generic CRUD + contract status transitions, quotation-to-contract conversion, public pool claim/release, ticket assignment/resolve/reply, line-item cascade cleanup, analytics report data assembly |
| Human Resources | 38 | 0 | `app/service/hr/HrService.php` | Generic CRUD + clock-in late/early-leave determination, leave approval (auto-generating leave attendance records), salary uniqueness/net-pay calculation/disbursement/batch generation |
| Manufacturing | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | Generic CRUD + work order start/complete transitions, BOM version copy/activation mutual exclusion, MRP line-item generation |
| Product Management | 29 | 0 | `app/service/product/ProductService.php` | Generic CRUD + transactional product creation (SKU/prices), field-wise original-value-preserving updates, detail relational loading |

Extraction pattern: `app/service/AbstractCrudService.php` provides the generic CRUD methods
`list/all/find/create/update/delete/deleteWhere` and the pure-logic helpers `normalizePageParams/canTransition`;
module Services extend it and settle module-specific business logic.
Controllers inject services via `Container::get(XxxService::class)` (with class_exists fallback), keeping routes,
parameters, and response structures completely unchanged; HTTP concerns such as hashid encode/decode,
password second confirmation, and response wrapping remain in the controllers.
New Services are registered in `config/dependence.php` (a dead config not loaded by addDefinitions; the runtime
container instantiates via the class_exists fallback, so all Services keep no-arg constructors).

Modules not yet extracted (Project Management 18 calls, Custom Reports 18, Purchase 24, Sales 24,
System Management 42, etc.) are marked in the table as "controller queries model directly, known tech debt"
and will be extracted under the same pattern in later iterations.

> ⚠ Re-measurement (2026-09-15): the figures in this section are as measured at **extraction time** (1051d83 / 2026-08-16) — re-checking that commit with the same caliber gives exactly
> CRM 57→0, HR 36→0 (this section records 38), Manufacturing 33→0, Product 29→0; the not-yet-extracted modules at that time were Project 18 / Reports 18 / Purchase 25 / Sales 25 / System Management 44 (recorded as 18/18/24/24/42, the difference of 1–2 being a counting-caliber difference).
> Pages added after extraction were not wired into the Services, so direct queries have crept back: CRM 6 places (relation-name backfill via `pluck`), Manufacturing 39 places (CostEntry/MaterialIssue/WorkReport/Subcontract receiving-issuing and the like — 6 later controllers in total),
> Product Management 2 places (LocationController warehouse locations), HR still 0; the not-yet-extracted modules now stand at Project 24 / Reports 20 / Purchase 58 / Sales 35 / System Management 67.
> Re-measurement caliber and command (`Model::class` not counted): ``grep -rhoE '\b[A-Z][A-Za-z]*::(find|where|whereIn|query|first|all|count|paginate|insert|update|delete|save|create|pluck|exists)\(' app/controller/<module>/ | grep -vE '\b(Service|Container|Validator|Cache|Log)::' | wc -l``

---

## OMS/WMS/TMS Extension Modules (2026-08)

### OMS (Order Management System) — 8 tables
- **Order extension** (`erp_oms_order`): multi-channel aggregation/fulfillment status/payment status/priority
- **Order address** (`erp_oms_order_address`): shipping/billing addresses (multi-country formats)
- **Fulfillment records** (`erp_oms_fulfillment`+`_item`): allocated/picked/packed/shipped quantity tracking
- **RMA** (`erp_oms_rma`+`_item`): return/exchange full lifecycle
- **Inventory reservation** (`erp_oms_inventory_reservation`): ATP = physical - reserved
- **Channels** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tables
- **Zones & locations** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **Inbound** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Outbound** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 tables
- **Carriers** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Shipments** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Invoices** (`erp_tms_freight_invoice`)

### Data Flow
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. Ecosystem Roadmap (2026-08)

> Detailed design spec: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Baseline Assessment (at Roadmap Launch)

> P0~P3 have all been delivered; the current overall score is 89/100 (see docs/CLAUDE.md); the table below is the baseline snapshot before the roadmap launch.

| Dimension | Score | Key Gap |
|------|------|----------|
| Backend APIs | 85/100 | Most modules are CRUD skeletons, missing business calculation engines |
| Security | 95/100 | 7-layer defense in depth (L0–L12 panorama), production-ready |
| Frontend UI | 20/100 | **Biggest shortfall**: Flutter 12 pages cover ~20% of modules, Web admin panel missing |
| Ops ecosystem | 70/100 | Missing migration rollback, auto backup, observability |
| Business depth | 55/100 | Finance/HR/manufacturing core algorithms not implemented |
| **Overall** | **65/100** | |

### 21.2 Four-Phase Serial Roadmap

```
P0(3-4 weeks) → P1(4-6 weeks) → P2(1-2 weeks) → P3(2-3 weeks) = ~13 weeks total
```

| Phase | Name | Core Deliverables |
|------|------|----------|
| **P0** | Frontend ecosystem | Flutter Web full-module admin panel (14 modules 40+ pages), common component library, HarmonyOS alignment |
| **P1** | Business depth | Finance double-entry engine, payroll calculation engine, MRP engine, quality management module, real-time notifications (WebSocket) |
| **P2** | Ops reliability | Database migration rollback, auto backup enhancement, OpenTelemetry tracing, RabbitMQ queue driver |
| **P3** | Experience enhancement | BI drag-and-drop dashboards, equipment management (EAM), multi-tenant isolation, document management (DMS) |

### 21.3 Middleware Chain Evolution

```
Current:  Cors → SecurityFilter → RateLimit → TracingId → {route group}
After P1: Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {route group}
After P2: Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {route group}
After P3: Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {route group}
```

### 21.4 P0 Target Architecture — Flutter Web Admin Panel

| Layer | Additions |
|------|----------|
| Layout layer | `AdminLayout` PC three-column layout (collapsible sidebar + top bar + content area) |
| Component layer | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Page layer | Expand from the existing 12 pages to full coverage of 14 modules 40+ pages |
| Service layer | Reuse existing `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 P1 Target Architecture — Business Calculation Engines

| Engine | Service Classes | Key Rules |
|------|--------|----------|
| Double-entry accounting | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Mandatory debit/credit balance validation, period-end P&L carry-over, multi-currency FX conversion |
| Payroll calculation | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Social insurance base upper/lower limits, housing fund ratio, progressive individual income tax rates, bank payroll disbursement |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | Layer-by-layer BOM explosion with loss rates, low-level code (LLC), safety stock, lot-sizing rules |
| Quality | `QmsInspectionService` | IQC incoming/IPQC in-process/OQC outgoing three-document flow |
| Notifications | `WebSocketService`, `ChannelRouter` | Multi-channel in-app/email/WeCom/DingTalk |

### 21.6 Data Model Change Summary

| Phase | New Tables | Modules Involved |
|------|----------|----------|
| P0 | 0 | Pure frontend, no table changes |
| P1 | 14 | Finance(2) + HR(3) + Manufacturing(2) + Quality(5) + Notifications(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. Multi-Tenancy (Reserved Capability, Not Enabled)

> Copyright notice as in the file header: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Positioning and Decision

Multi-tenancy is positioned in this project as a **reserved capability**, **not wired up or enabled** in this phase (documented downgrade). Consistent with the plan:
a "complete multi-tenant commercialization solution" such as SaaS billing and tenant self-service provisioning is out of scope for this project; this phase keeps only a minimal
code skeleton (middleware + model Trait) and provides enablement steps for later on-demand use.
Note: the "multi-tenant isolation" item in roadmap §21.2 P3 is accordingly adjusted to "reserved capability (documented downgrade)", keeping the skeleton without wiring it up.

Decision basis (2026-08 review):
- Existing deployments are almost entirely single-tenant; wiring it up would introduce unnecessary isolation complexity and regression risk;
- The current skeleton has technical flaws (see 22.4); "wired up equals isolated" does not hold, a design fix must come first;
- Isolation would require adding a column per business table across all 227 tables and enabling per model, a cost far exceeding "minimal wiring".

### 22.2 Current Facts (Code and Config Verification)

| Item | Current State |
|----|------|
| `app/middleware/TenantScope.php` | Exists, not registered; reads the tenant code from the `X-Tenant-Code` header and looks up `erp_tenant` to inject the context, passes through directly when the header is missing |
| `app/model/concerns/TenantScope.php` | Exists; used by 4 finance models (`FinanceLedger` / `FinanceBalanceSheet` / `FinanceCashFlow` / `FinanceProfit`, whose company family `tenantScopeByCompany()` returns true), filtering by `company_id`; because the middleware is not registered and the request context is never injected, the global scope does not take effect at present |
| `config/middleware.php` | Global chain: Cors → SecurityFilter → RateLimit → TracingId, no TenantScope |
| `config/route.php` /admin/v1 group | AdminAuth → AdminPermission → OperationLog, no TenantScope |
| JWT payload | Only `sub` / `username` / `token_type`, **no tenant_id claim** (`app/api/v1/controller/AuthController.php`) |
| Database | **No tenant_id column anywhere in the database** (nor in install.sql) |
| Models | 4 finance models use the `TenantScope` trait (company family, filtering `company_id`) — pilot isolation; when no tenant context is injected, no filtering is applied |

### 22.3 Enablement Steps (Reserved Reference, Not Executed This Phase)

1. Register the middleware: append `app\middleware\TenantScope::class` to the `middleware()` of the /admin/v1 group in `config/route.php` (place it after AdminAuth to ensure authentication).
2. Requesters carry `X-Tenant-Code` (tenant code string) in the request header.
3. Add a `tenant_id` column (BIGINT + index) to business tables requiring isolation and backfill existing data;
   dictionary/system tables (e.g. `erp_admin_user`, `erp_role`, `erp_permission`) are not isolated.
4. Add `use app\model\concerns\TenantScope;` in models requiring isolation for automatic filtering by the current tenant.
5. (Optional) To take the tenant from JWT instead of the header: extend the login issuance payload with a `tenant_id` claim and read from `$payload['tenant_id']` in the middleware.

### 22.4 Known Technical Limitations (Must Be Resolved Before Enablement)

- **Trust boundary (must be resolved before registration)**: the tenant context comes from the `X-Tenant-Code` request header, which is
  forgeable input; enabling the middleware before a binding between `erp_admin_user` and companies/tenants
  (administrator ownership determination) is established would open a privilege-escalation gap in the data plane
  (any authenticated administrator could claim any tenant and read its data).
- **The broken static pass-through chain (measured on PHP 8.3) has been superseded by the P2-4 B5 fix**: the `TenantScope` trait
  now goes through request-context injection (`request()->tenantId` / `companyId`), a path with no static state, so
  cross-request crosstalk within the long-running process is eliminated as a result; the trait's static facade is marked `@deprecated`
  and remains only as a test/CLI fallback.
- **Data-plane gap**: no tenant_id column anywhere in the database; a per-table migration is needed, and cross-tenant shared dictionary tables require an exemption mechanism.

### 22.5 Acceptance Criteria

This phase's acceptance = documentation consistent with code: neither `config/middleware.php` nor `config/route.php` contains a
TenantScope registration; the middleware comment marks it "implemented, not registered by default" and gives the registration point and the trust boundary,
the Trait comment marks the request-context injection chain and the regression line (it takes no part in filtering when there is no tenant context);
each item described in this section corresponds one-to-one with the current code state.
