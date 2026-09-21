# Diagram Arsitektur dan Diagram Alur Bisnis

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Diagram Mermaid berikut dapat dirender otomatis di GitHub / GitLab / VS Code. Untuk lingkungan lain, gunakan [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Arsitektur Topologi Sistem

```mermaid
flowchart TB
    subgraph "Lapisan Klien"
        A1["Flutter Web<br/>Panel Admin PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Klien Ponsel/Tablet"]
        A3["Angular 22 + ng-zorro<br/>Panel Admin Web"]
        A4["React 19 + Vite<br/>Panel Admin Web"]
    end

    subgraph "Lapisan Gateway/Edge (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse Proxy + HTTPS + Gzip<br/>Layanan File Statis"]
    end

    subgraph "Lapisan Aplikasi (webman v2)"
        C_LOC["I18n::getLocale()<br/>Parsing Accept-Language · 13 bahasa"]
        C0["Versi berbasis path<br/>/api/v1 · /admin/v1 (tanpa header versi)"]
        C1["Middleware AdminAuth<br/>Verifikasi JWT"]
        C2["Middleware AdminPermission<br/>Validasi Izin RBAC"]
        C3["Controller Admin<br/>Dashboard / User / Role / Permission"]
        C4["Controller Publik v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Lapisan Penyimpanan"
        D1[("MySQL 8.0<br/>Penyimpanan Utama<br/>Prefiks Tabel erp_")]
        D2[("Elasticsearch<br/>Pencarian Teks Lengkap<br/>Prefiks Indeks erp_")]
        D3[("Redis<br/>Session / Cache<br/>Penyimpanan Captcha")]
    end

    subgraph "Eksternal"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
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

## 2. Arsitektur Berlapis Backend

```mermaid
flowchart TD
    subgraph "Lapisan Routing"
        R1["config/route.php<br/>URL → Pemetaan Controller"]
    end

    subgraph "Lapisan Middleware"
        M_CR["Cors<br/>Penanganan lintas domain / preflight OPTIONS"]
        M_SF["SecurityFilter<br/>Intercept Deteksi Serangan<br/>XSS/SQL Injection/Path Traversal/CSRF"]
        M_RL["RateLimit<br/>Rate Limit Sliding Window Redis<br/>Header Respons X-RateLimit"]
        M_TID["TracingId<br/>Menghasilkan X-Trace-Id<br/>Menembus seluruh rantai"]
        M1["AdminAuth<br/>Validasi Token JWT<br/>Injeksi adminId"]
        M2["AdminPermission<br/>Otorisasi RBAC<br/>Pencocokan method.path<br/>Cache izin Redis 60s"]
    end

    subgraph "Lapisan Controller"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Pencarian + Paginasi"]
        CT3["RoleController<br/>CRUD + Sinkronisasi Izin"]
        CT4["PermissionController<br/>CRUD + Pembangunan Pohon"]
        CT5["DashboardController<br/>Statistik/Tren/Distribusi"]
        CT6["ExportController<br/>Ekspor Excel/PDF"]
        CT7["CaptchaController<br/>Pembuatan/Validasi Captcha"]
        CT8["AuthController<br/>Login/Registrasi/Refresh"]
    end

    subgraph "Lapisan Service"
        S1["HashidsService<br/>Encode/Decode ID"]
        S2["SnowflakeService<br/>Pembuatan ID Unik Global"]
        S3["EncryptionService<br/>Enkripsi/Deskripsi + Masking"]
        M_LOC["I18n::getLocale()<br/>Parsing Accept-Language (bukan middleware)<br/>13 bahasa zh_CN/en/ja/ko/de<br/>fr/es/pt/ru/ar/hi/bn/id"]
    end

    subgraph "Lapisan Model"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Lapisan Driver"
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

### Perluasan Lapisan Bisnis ERP

Seiring evolusi sistem dari panel admin murni menjadi sistem ERP lengkap, lapisan controller dan service menambahkan modul bisnis berikut:

| Lapisan | Direktori | Keterangan |
|------|------|------|
| Controller Bisnis | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report,oms,wms,tms,quality,eam,dms,open,platform,print,retail,bi}/` | 139 buah (23 domain bisnis, plus Install / Index di tingkat atas), dibagi per modul, menangani permintaan bisnis |
| Service Bisnis | `app/service/{finance,inventory,notification,crm,hr,manufacturing,oms,wms,tms,quality,…}/` | 63 kelas service / 64 berkas / 20 subdirektori modul; mencakup stok masuk-keluar + kalkulasi biaya, piutang-hutang keuangan + penyelesaian, pengiriman notifikasi |

### Internasionalisasi (13 bahasa)

Resolusi bahasa ada di `getLocale()` pada `app/common/I18n.php` (dipanggil oleh `I18n::trans()`, **bukan middleware**): mengambil label pertama header permintaan `Accept-Language` lalu memetakan sub-label bahasa utama (`zh*` → `zh_CN`). Pembagian kamus frontend-backend:

| Ujung | Lokasi kamus | Skala | Generator |
|----|----------|------|--------|
| Backend | `resource/translations/<locale>/{common,modules,validation}.php` | 13 direktori bahasa; `zh_CN` 565 entri, 11 bahasa lainnya masing-masing 544 entri, `en` 30 entri (cakupan entri daun, label field `attributes` pada `validation.php` dihitung, kunci grupnya tidak) | `scripts/gen-be-locales.mjs` |
| Angular | sumber `apps/angular/src/app/core/zh-en/part1..4.ts` → produk `core/zh-<code>.ts` | kamus sumber 1456 entri | `scripts/gen-fe-locales.mjs --app angular` |
| React | sumber `apps/react/src/lib/i18n/zhEn.ts` → produk `lib/i18n/zh<Code>.ts` | kamus sumber 1451 entri | `scripts/gen-fe-locales.mjs --app react` |

- Daftar bahasa: `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`.
- Backend 「Inggris adalah key」: `en` pada common/modules dibiarkan kosong; kunci `validation.php` adalah nama aturan framework, hanya nilainya diterjemahkan.
- Kamus 11 bahasa baru di frontend masing-masing dimuat dinamis dengan `import()` menjadi chunk mandiri; entri yang hilang kembali ke teks asli 中文.
- Mengganti bahasa berarti mengganti `Accept-Language`, backend mengembalikan teks sesuai bahasa (`app/common/I18n.php` + `config/translation.php`).

---

## 3. Siklus Hidup Permintaan

```mermaid
sequenceDiagram
    participant C as Klien
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

    C->>N: Permintaan HTTPS<br/>Path /api/v1 atau /admin/v1 (tanpa header versi)
    N->>MW_CR: Meneruskan
    MW_CR->>MW_CR: Menangani preflight OPTIONS<br/>Menyuntik header respons CORS
    MW_CR->>MW_SF: Lolos

    alt Metode HTTP non-standar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Metode valid (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Pemeriksaan daftar putih metode lolos
    end

    alt Deteksi serangan terpicu
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Lolos

    alt Rate limit terpicu
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW_TID: Lolos
    MW_TID->>MW_TID: Menghasilkan X-Trace-Id<br/>Menyuntik header respons
    MW_TID->>MW1: Lolos

    alt Token hilang atau tidak valid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Tanpa izin
        MW2-->>C: 403 Forbidden
    else Memiliki izin
        MW2->>CTL: Masuk ke controller
    end

    CTL->>CTL: Validasi parameter (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operasi sensitif (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Kata sandi salah
            CTL-->>C: 422 Verifikasi kata sandi gagal
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Cast encryptable dekripsi otomatis
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Membangun respons JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Mencatat log operasi (POST/PUT/DELETE)
```

---

## 4. Alur Autentikasi dan Captcha

```mermaid
sequenceDiagram
    participant U as Pengguna
    participant CL as Klien
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Langkah 1: Mendapatkan Captcha ===
    CL->>SV: POST /api/v1/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Menghasilkan gambar latar 300×200
    CAP->>CAP: Menempatkan N target karakter acak
    CAP->>CAP: Membuat key, menyimpan targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Langkah 2: Klik Pengguna ===
    CL->>CL: Render gambar captcha
    CL->>CL: Menampilkan "Silakan klik secara berurutan: pohon → burung → bunga"
    U->>CL: Klik posisi karakter pada gambar secara berurutan
    CL->>CL: Mengumpulkan clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Langkah 3: Login ===
    CL->>SV: POST /api/v1/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha salah
        CAP-->>SV: false
        SV-->>CL: 422 Captcha salah
    else Captcha benar
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Kredensial salah
            SV-->>CL: 401 Nama pengguna atau kata sandi salah
        else Kredensial benar
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Permintaan Berikutnya ===
    CL->>SV: GET /admin/v1/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { data dashboard }
```

---

## 5. Model Izin RBAC

```mermaid
flowchart LR
    subgraph "Pengguna User"
        U1["admin<br/>(Super Admin)"]
        U2["editor<br/>(Editor)"]
        U3["viewer<br/>(Hanya Baca)"]
    end

    subgraph "Peran Role"
        R1["super_admin<br/>Identitas izin: *"]
        R2["editor<br/>Identitas izin: get.*, post.*"]
        R3["viewer<br/>Identitas izin: get.*"]
    end

    subgraph "Izin Permission (Pohon)"
        P1["dashboard<br/>type=1 Menu"]
        P2["user<br/>type=1 Menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Tombol"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (Semua Izin)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Jenis Izin"
        T1["type=1 Menu<br/>Mengontrol tampil/sembunyi sidebar"]
        T2["type=2 Tombol<br/>Mengontrol tombol aksi halaman"]
        T3["type=3 API<br/>Mengontrol akses endpoint"]
    end

    subgraph "Format Identitas Izin"
        F1["{method}.{path}<br/>Contoh: get.admin/user<br/>Contoh: post.admin/user<br/>Contoh: delete.admin/role"]
    end

    subgraph "Alur Penilaian"
        J1["Ekstrak Token → adminId"]
        J2["Cari peran pengguna"]
        J3["Kumpulkan semua slug izin"]
        J4["Bangun method.path"]
        J5{"Cocok?"}
        J6["Lolos"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Ya / slug=*"| J6
        J5 -->|Tidak| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Siklus Hidup Lengkap ID

```mermaid
flowchart LR
    subgraph "1. Pembuatan"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Contoh: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Penyimpanan"
        S1["Tabel MySQL erp_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Bidang sensitif<br/>cast encryptable<br/>Enkripsi AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmisi"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["String hashid<br/>Contoh: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decode Terbalik"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Lapisan Enkripsi Data

```mermaid
flowchart TB
    subgraph "Enkripsi Lapisan Transmisi (encryption)"
        E1["Klien mengirim data sensitif"]
        E2["Enkripsi AES-256-CBC"]
        E3["API mentransmisikan ciphertext"]
        E4["Server mendekripsi dan memproses"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Enkripsi Lapisan Penyimpanan (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Menulis: enkripsi otomatis"]
        D3["MySQL VARCHAR(500)<br/>Menyimpan ciphertext"]
        D4["Membaca: dekripsi otomatis"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Masking Lapisan Tampilan (mask)"
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

## 8. Relasi ER Basis Data

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Terenkripsi"
        VARCHAR phone "Terenkripsi"
        VARCHAR id_card "Terenkripsi"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft Delete"
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
        BIGINT parent_id FK "Referensi diri"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1menu 2tombol 3API"
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
        VARCHAR source "Sumber"
        TEXT input "Dimasking"
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

## 9. Alur Bisnis Ekspor

```mermaid
sequenceDiagram
    participant C as Klien
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistem File

    Note over C,FS: === Ekspor Excel ===
    C->>CTL: POST /admin/v1/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Data
    CTL->>CTL: Mendekripsi bidang sensitif
    CTL->>CTL: Masking (maskPhone/maskEmail)
    CTL->>CTL: Membangun PhpSpreadsheet<br/>Header biru dengan teks putih<br/>Baris data dengan border tipis<br/>Baris pertama terkunci<br/>Filter otomatis
    CTL->>FS: Menulis runtime/tmp/export_*.xlsx
    CTL-->>C: Unduh file

    Note over C,FS: === Ekspor PDF ===
    C->>CTL: POST /admin/v1/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Header: judul + hak cipta + waktu<br/>Isi: tabel atau kartu<br/>Footer: hak cipta yang tidak dapat dihapus
    CTL->>CTL: Render Dompdf A4 lanskap
    CTL->>FS: Menulis runtime/tmp/export_*.pdf
    CTL-->>C: Unduh file
```

---

## 10. Pohon Komponen Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulir login<br/>Nama pengguna/kata sandi/captcha"]
    LF --> CAPTCHA["Komponen click captcha<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Penanda klik Circle"]

    DB --> SIDEBAR["Sidebar NavigationDrawer<br/>Dapat dilipat 64px / 240px<br/>Dashboard/Pengguna/Peran/Konfigurasi/Log"]
    DB --> HEADER["Top bar 56px<br/>Tombol lipat + menu pengguna<br/>AlertDialog keluar"]
    DB --> CONTENT["Area Konten"]
    CONTENT --> DASH["DashboardPage<br/>Kartu statistik GridView<br/>Garis tren LineChart<br/>Pie chart distribusi PieChart<br/>ListTile operasi terbaru"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Routing Halaman HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Memulai"]
    EA -->|"Tanpa Token"| LP["LoginPage<br/>Halaman Login"]
    EA -->|"Ada Token"| DP["DashboardPage<br/>Dashboard"]

    LP -->|"Login berhasil<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Daftar Pengguna"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Pusat Akun"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detail/Tambah/Edit Pengguna"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Keluar<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama Pertahanan Berlapis

```mermaid
flowchart TB
    subgraph "Lapisan 1: Verifikasi Manusia"
        L1["Click Captcha<br/>Click Captcha<br/>Wajib saat login/registrasi"]
    end

    subgraph "Lapisan 2: Konfirmasi Operasi"
        L2["Konfirmasi ulang kata sandi<br/>confirmPassword()<br/>Wajib untuk operasi DELETE"]
    end

    subgraph "Lapisan 3: Keamanan Transmisi"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Lapisan 4: Autentikasi Identitas"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Lapisan 5: Otorisasi Izin"
        L5["RBAC<br/>Granularitas method.path<br/>Super admin *"]
    end

    subgraph "Lapisan 6: Perlindungan Data"
        L6["ID antarmuka: Enkripsi Hashids<br/>Body permintaan: Enkripsi Encryption<br/>Lapisan penyimpanan: Enkripsi Encryptable<br/>Ekspor: masking + hak cipta"]
    end

    subgraph "Lapisan 7: Audit dan Penelusuran"
        L7["OperationLog<br/>Mencatat semua operasi<br/>Pengguna/IP/Waktu/Sumber/Parameter"]
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

## 13. Topologi Deployment

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web Server"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["File statis<br/>Flutter Web build/"]
    end

    subgraph "Server Aplikasi (dapat diskalakan horizontal)"
        WM1["webman worker 1<br/>:8788"]
        WM2["webman worker 2<br/>:8788"]
        WM3["webman worker N<br/>:8788"]
    end

    subgraph "Lapisan Data"
        MYSQL["MySQL 8.0<br/>Replikasi master-slave<br/>Prefiks erp_"]
        ES["Elasticsearch 8.x<br/>Klaster 3 node<br/>Prefiks erp_"]
        REDIS["Redis 7.x<br/>Mode sentinel<br/>poster:captcha:*"]
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

---

## 14. Arsitektur Keseluruhan Sistem ERP

```mermaid
graph TB
    subgraph Client["Lapisan Klien"]
        FW["Flutter Web<br/>Panel Admin PC"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>App Native HarmonyOS"]
        NG["Angular 22 + ng-zorro<br/>Panel Admin Web"]
        RC["React 19 + Vite<br/>Panel Admin Web"]
    end

    subgraph Gateway["Lapisan API Gateway"]
        MW["Rantai middleware<br/>Cors→SecurityFilter→RateLimit→TracingId<br/>Grup rute: AdminAuth→AdminPermission→OperationLog"]
    end

    subgraph Business["Lapisan Modul Bisnis"]
        direction LR
        Admin["Manajemen Sistem<br/>Pengguna/Peran/Izin/Konfigurasi/Log"]
        Product["Manajemen Produk<br/>Produk/Kategori/Merek/Gudang/Pemasok/Pelanggan"]
        Purchase["Manajemen Pembelian<br/>Permintaan→Pesanan→Penerimaan→Retur→Settlement"]
        Sales["Manajemen Penjualan<br/>Penawaran→Pesanan→Pengiriman→Retur→Settlement"]
        Inventory["Manajemen Inventaris<br/>Masuk/keluar stok/Batch/Stock opname/Transfer/Peringatan"]
        Finance["Manajemen Keuangan<br/>Akun/Voucher/Piutang-Hutang/Buku Besar/Jurnal Detail/Laporan/Reimbursement"]
        CRM["CRM<br/>Pelanggan/Kontak/Tindak lanjut/Funnel/Gudang umum/Penawaran/Kontrak"]
        Workflow["Alur Kerja Persetujuan<br/>Definisi alur kerja/Submit/Setujui/Tolak/Tarik"]
        Notification["Notifikasi Pesan<br/>Daftar notifikasi/Dibaca/Perhitungan belum dibaca"]
        Project["Manajemen Proyek<br/>Proyek/Tugas/Catatan jam kerja"]
        HR["Sumber Daya Manusia<br/>Departemen/Karyawan/Jabatan/Absensi/Cuti/Gaji"]
        Manufacturing["Manufaktur<br/>BOM/Pesanan produksi/Rute produksi/Workstation/MRP"]
        Report["Laporan Kustom<br/>Template laporan/Dataset/Bidang/Filter/Jadwal"]
    end

    subgraph Service["Lapisan Layanan Bisnis"]
        IS["InventoryService<br/>Masuk/keluar stok + biaya rata-rata bergerak"]
        FS["FinanceService<br/>Pembuatan piutang/hutang otomatis + penghapusan"]
        NS["NotificationService<br/>Pengiriman notifikasi terpadu"]
    end

    subgraph Data["Lapisan Data"]
        MySQL["MySQL 8.0<br/>227 tabel bisnis"]
        Redis["Redis 7<br/>Cache/Rate limit/Session"]
        ES["Elasticsearch 8<br/>Pencarian teks lengkap"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Alur Data Lintas Modul

```mermaid
sequenceDiagram
    participant PO as Penerimaan Pembelian
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Tabel Inventaris
    participant COST as Catatan Biaya
    participant ARAP as Piutang/Hutang

    PO->>IS: stockIn(produk, jumlah, harga)
    IS->>INV: Memperbarui stok real-time (dengan kunci)
    IS->>COST: Menghitung ulang biaya rata-rata bergerak
    IS-->>PO: Mengembalikan ID transaksi

    PO->>FS: createAp(pemasok, jumlah)
    FS->>ARAP: Membuat catatan hutang

    Note over PO,ARAP: Pengiriman penjualan sama: stockOut + createAr
```

---

## 16. Alur Data Kalkulasi Biaya Inventaris

```mermaid
graph LR
    A[Penerimaan pembelian 100 yuan × 10 pcs] --> B[Transaksi masuk stok]
    C[Penerimaan pembelian 130 yuan × 20 pcs] --> D[Transaksi masuk stok]
    B --> E[Stok: 10 pcs, Biaya 100]
    D --> F[Stok: 30 pcs, Biaya 120]
    E --> G[Rata-rata bergerak: 100]
    F --> H[Rata-rata bergerak: 120]
    H --> I[Keluar stok dihitung biaya 120]
```

---

## 17. Alur Data Alur Kerja Persetujuan

```mermaid
sequenceDiagram
    participant Biz as Modul Bisnis
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Mesin Alur Kerja
    participant NTF as NotificationService

    Biz->>WF: Submit persetujuan(nomor transaksi, jenis modul)
    WF->>WFE: Mencocokkan definisi alur kerja → membuat instance persetujuan
    WFE->>APR: Memberi tahu approver node pertama
    APR->>NTF: Mengirim notifikasi persetujuan
    NTF-->>APR: Notifikasi terkirim
    APR->>APR: Approver menyetujui/menolak
    alt Disetujui
        APR->>WFE: Melanjutkan ke node berikutnya
        alt Semua node lolos
            WFE->>Biz: Callback: persetujuan lolos, memperbarui status dokumen bisnis
        end
    else Ditolak
        WFE->>Biz: Callback: persetujuan ditolak
    end
```

---

## 18. Alur Data Notifikasi Pesan

```mermaid
sequenceDiagram
    participant Event as Sumber Pemicu Acara
    participant NS as NotificationService
    participant DB as Tabel Notifikasi
    participant User as Pengguna

    Event->>NS: Memicu notifikasi(jenis, judul, konten, penerima)
    NS->>DB: Menulis catatan notifikasi
    NS-->>User: Push (pesan internal/WebSocket)
    User->>NS: Menandai dibaca
    NS->>DB: Memperbarui status dibaca
    User->>NS: Menanyakan jumlah belum dibaca
    NS-->>User: Jumlah belum dibaca
```

---

## 19. Alur Data MRP (Material Requirements Planning)

```mermaid
sequenceDiagram
    participant SO as Pesanan Penjualan
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Saran Pembelian
    participant MO as Saran Produksi

    SO->>MRP: Kebutuhan pesanan penjualan
    MRP->>BOM: Mengurai BOM untuk mendapatkan daftar material
    BOM-->>MRP: Material + penggunaan standar
    MRP->>INV: Menanyakan ketersediaan stok
    INV-->>MRP: Jumlah stok
    MRP->>MRP: Menghitung kebutuhan bersih = kebutuhan kotor - stok
    alt Bahan baku tidak mencukupi
        MRP->>PO: Membuat saran pembelian
    else Produk setengah jadi tidak mencukupi
        MRP->>MO: Membuat saran produksi
    end
```

---

## 20. Tabel Pemetaan Controller-Service-Model Modul ERP

> Keterangan lapisan service: kolom `Service Inti` menandai layanan bisnis yang telah diturunkan ke modul tersebut; modul yang ditandai **⚠ Controller langsung query model, technical debt diketahui**,
> controller masih memanggil langsung metode query/tulis model (`XxxModel::find/where/save` dll.), belum diekstrak ke lapisan service, merupakan technical debt yang diketahui,
> akan dikonvergensikan bertahap sesuai pola ekstraksi ringan lapisan service P2-F2 (`app/service/AbstractCrudService` kelas dasar CRUD umum + Service modul).

| Modul | Controllers (Direktori) | Service Inti | Model Utama | Jumlah Tabel |
|------|-------------------|-------------|-----------|------|
| Manajemen Sistem | admin/controller/ (16) | - ⚠Controller langsung query model, technical debt diketahui | AdminUser, AdminRole, AdminPermission | 7 |
| Manajemen Produk | controller/product/ (8) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 12 |
| Manajemen Pembelian | controller/purchase/ (8) | InventoryService, FinanceService ⚠CRUD masih langsung query, technical debt diketahui | PurchaseOrder, PurchaseReceive | 14 |
| Manajemen Penjualan | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD masih langsung query, technical debt diketahui | SalesOrder, SalesDelivery | 9 |
| Manajemen Inventaris | controller/inventory/ (6) | InventoryService ⚠CRUD masih langsung query, technical debt diketahui | Inventory, InventoryFlow, CostRecord | 11 |
| Manajemen Keuangan | controller/finance/ (28) | FinanceService ⚠CRUD masih langsung query, technical debt diketahui | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 38 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Alur Kerja Persetujuan | controller/workflow/ (3) | - ⚠Controller langsung query model, technical debt diketahui | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Notifikasi Pesan | controller/notification/ (2) | NotificationService ⚠CRUD masih langsung query, technical debt diketahui | Notification, NotificationSetting, NotificationTemplate | 4 |
| Manajemen Proyek | controller/project/ (4) | - ⚠Controller langsung query model, technical debt diketahui | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 6 |
| Sumber Daya Manusia | controller/hr/ (9) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 21 |
| Manufaktur | controller/manufacturing/ (13) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 21 |
| Laporan Kustom | controller/report/ (2) | - ⚠Controller langsung query model, technical debt diketahui | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| Manajemen Peralatan EAM | controller/eam/ (5) | - ⚠Controller langsung query model, technical debt diketahui | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart, EamInspectionTask, EamInspectionResult | 6 |
| Manajemen Dokumen DMS | controller/dms/ (2) | - ⚠Controller langsung query model, technical debt diketahui | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| Dashboard BI | controller/bi/ (3) | - ⚠Controller langsung query model, technical debt diketahui | BiDashboard, BiWidget | 2 |

> Tabel ini adalah pemetaan modul awal (Manajemen Sistem + 15 domain bisnis); 8 domain yang ditambahkan kemudian —— oms / wms / tms / quality / open / platform / print / retail —— tidak dicantumkan,
> daftar lengkapnya lihat pohon struktur proyek di `docs/CLAUDE.md` (`app/controller/` total 23 direktori modul / 139 controller, termasuk Install dan Index di tingkat atas).
>
> Cakupan `Jumlah Tabel` (terukur 2026-09-15): mengambil 227 tabel dari `database/install.sql`, dipetakan berdasarkan prefiks nama tabel ke satu modul saja —— Manajemen Sistem `admin_*`+`system_config`+`operation_log`;
> Manajemen Produk `product*`/`category`/`brand`/`warehouse`/`location`/`supplier`/`customer*`; Manajemen Pembelian `purchase_*`+`supplier_assessment`; Manajemen Inventaris `inventory*`/`transfer*`/`check_*`/`cost_record`;
> modul lain memakai prefiks tabel bernama sama (`sales_*`→Penjualan, `finance_*`→Keuangan, `crm_*`→CRM, `approval_*`→Persetujuan, `notification*`→Notifikasi, `project*`→Proyek, `hr_*`→SDM, `mfg_*`→Produksi, `report_*`→Laporan, `eam_*`→EAM, `dms_*`→DMS, `bi_*`→BI).
> Satu tabel hanya masuk satu kolom; domain baru dan tabel bersama yang ditambahkan kemudian berjumlah 48 tabel (`oms_`/`wms_`/`tms_`/`quality_`/`openapi_`/`webhook_`/`member_`/`print_template`/`company`/`tenant`/`channel`/`custom_field_definition`/`tax_*`) tidak dihitung pada baris mana pun tabel ini.
> Hitung ulang: ``grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | cut -d_ -f1 | sort | uniq -c | sort -rn``

### 20.1 Catatan Ekstraksi Ringan Lapisan Service P2-F2 (crm/hr/manufacturing/product telah selesai diekstrak)

| Modul | Jumlah pemanggilan langsung controller sebelum ekstraksi | Setelah ekstraksi | Service Baru | Isi Ekstraksi |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | CRUD umum + transisi status kontrak, penawaran jadi kontrak, klaim/rilis gudang umum, penugasan/penyelesaian/balasan tiket, pembersihan kaskade detail, pembangunan data laporan analisis |
| Sumber Daya Manusia | 38 | 0 | `app/service/hr/HrService.php` | CRUD umum + penentuan keterlambatan/pulang cepat, persetujuan cuti (menghasilkan absensi cuti otomatis), keunikan gaji/perhitungan gaji bersih/pembayaran/pembuatan massal |
| Manufaktur | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | CRUD umum + transisi mulai/selesai work order, salinan versi BOM/eksklusivitas efektif, pembuatan detail MRP |
| Manajemen Produk | 29 | 0 | `app/service/product/ProductService.php` | CRUD umum + pembuatan transaksi produk (SKU/harga), pembaruan mempertahankan nilai asli per bidang, pemuatan relasi detail |

Pola ekstraksi: `app/service/AbstractCrudService.php` menyediakan CRUD umum `list/all/find/create/update/delete/deleteWhere`
dan helper logika murni `normalizePageParams/canTransition`; Service modul mewarisinya dan mengendapkan bisnis spesifik modul.
Controller menginjeksi service melalui `Container::get(XxxService::class)` (fallback class_exists), menjaga struktur rute/parameter/return sepenuhnya tidak berubah;
kodek/hashid, konfirmasi ulang kata sandi, pembungkusan respons dan concern HTTP lainnya tetap berada di controller.
Service baru telah didaftarkan di `config/dependence.php` (file tersebut merupakan dead config, tidak dimuat oleh addDefinitions, kontainer dependensi runtime
dipakai dengan fallback class_exists, sehingga semua Service mempertahankan konstruktor tanpa parameter).

Modul yang belum diekstrak (Manajemen Proyek 18 kali, Laporan Kustom 18 kali, Pembelian 24 kali, Penjualan 24 kali, Manajemen Sistem 42 kali, dll.) telah ditandai di tabel
"Controller langsung query model, technical debt diketahui", akan diekstrak bertahap dengan pola yang sama pada iterasi berikutnya.

> ⚠ Pengujian ulang (2026-09-15): nilai pada bagian ini adalah hasil pengukuran **pada titik ekstraksi** (1051d83 / 2026-08-16) —— dengan cakupan yang sama pada commit tersebut, keempat modul tepat
> CRM 57→0, SDM 36→0 (bagian ini menandai 38), Produksi 33→0, Produk 29→0; modul yang belum diekstrak saat itu adalah Proyek 18 / Laporan 18 / Pembelian 25 / Penjualan 25 / Manajemen Sistem 44 (penandaan 18/18/24/24/42, selisih 1~2 berasal dari perbedaan cakupan penghitungan).
> Setelah ekstraksi, halaman baru yang ditambahkan belum terhubung ke Service, query langsung sudah mengalir kembali: CRM 6 tempat (pengisian nama relasi `pluck`), Produksi 39 tempat (CostEntry/MaterialIssue/WorkReport/Subkontrak penerimaan-pengiriman dan 6 controller periode berikutnya),
> Manajemen Produk 2 tempat (LocationController lokasi), SDM masih 0; modul yang belum diekstrak kini Proyek 24 / Laporan 20 / Pembelian 58 / Penjualan 35 / Manajemen Sistem 67.
> Cakupan pengujian ulang dan perintahnya (`Model::class` tidak dihitung): ``grep -rhoE '\b[A-Z][A-Za-z]*::(find|where|whereIn|query|first|all|count|paginate|insert|update|delete|save|create|pluck|exists)\(' app/controller/<modul>/ | grep -vE '\b(Service|Container|Validator|Cache|Log)::' | wc -l``

---

## Modul Ekstensi OMS/WMS/TMS (2026-08)

### OMS (Order Management System) — 8 tabel
- **Ekstensi Pesanan** (`erp_oms_order`): Agregasi multi-kanal/status pemenuhan/status pembayaran/prioritas
- **Alamat Pesanan** (`erp_oms_order_address`): Alamat pengiriman/penagihan (format multinegara)
- **Catatan Pemenuhan** (`erp_oms_fulfillment`+`_item`): Pelacakan jumlah dialokasikan/dipetik/dikemas/dikirim
- **RMA** (`erp_oms_rma`+`_item`): Siklus hidup lengkap retur/tukar
- **Pre-reservasi Stok** (`erp_oms_inventory_reservation`): ATP = fisik - reserved
- **Kanal** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tabel
- **Zona dan Lokasi Gudang** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **Inbound** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Outbound** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 tabel
- **Operator Pengiriman** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Waybill** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Invoice** (`erp_tms_freight_invoice`)

### Alur Data
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. Roadmap Ekosistem (2026-08)

> Spesifikasi desain terperinci: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Penilaian Baseline (saat roadmap dimulai)

> P0~P3 semuanya telah selesai, skor komprehensif saat ini 89/100 (lihat CLAUDE.md); tabel di bawah adalah snapshot baseline sebelum roadmap dimulai.

| Dimensi | Skor | Kesenjangan Kunci |
|------|------|----------|
| API Backend | 85/100 | Banyak modul merupakan kerangka CRUD, kekurangan mesin kalkulasi bisnis |
| Keamanan | 95/100 | Pertahanan berlapis 7 lapis (panorama L0–L12), siap produksi |
| UI Frontend | 20/100 | **Kelemahan terbesar**: Flutter 12 halaman mencakup ~20% modul, panel admin Web belum ada |
| Ekosistem Operasional | 70/100 | Kekurangan rollback migrasi, backup otomatis, observabilitas |
| Kedalaman Bisnis | 55/100 | Algoritma inti keuangan/HR/manufaktur belum diimplementasikan |
| **Komprehensif** | **65/100** | |

### 21.2 Roadmap Empat Fase Berurutan

```
P0(3-4 minggu) → P1(4-6 minggu) → P2(1-2 minggu) → P3(2-3 minggu) = Total sekitar 13 minggu
```

| Fase | Nama | Deliverable Inti |
|------|------|----------|
| **P0** | Ekosistem Frontend | Panel admin Flutter Web semua modul (14 modul 40+ halaman), pustaka komponen umum, penyelarasan HarmonyOS |
| **P1** | Kedalaman Bisnis | Mesin pembukuan berpasangan keuangan, mesin kalkulasi gaji, mesin MRP, modul manajemen kualitas, notifikasi real-time (WebSocket) |
| **P2** | Keandalan Operasional | Rollback migrasi database, penguatan backup otomatis, tracing OpenTelemetry, driver antrean RabbitMQ |
| **P3** | Peningkatan Pengalaman | Dashboard BI drag-and-drop, manajemen peralatan (EAM), isolasi multi-tenant, manajemen dokumen (DMS) |

### 21.3 Evolusi Rantai Middleware

```
Saat ini:   Cors → SecurityFilter → RateLimit → TracingId → {Grup rute}
Setelah P1:  Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {Grup rute}
Setelah P2:  Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {Grup rute}
Setelah P3:  Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {Grup rute}
```

### 21.4 Arsitektur Target P0 — Panel Admin Flutter Web

| Lapisan | Konten Baru |
|------|----------|
| Lapisan Layout | Layout tiga kolom PC `AdminLayout` (sidebar dapat dilipat + top bar + area konten) |
| Lapisan Komponen | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Lapisan Halaman | Diperluas dari 12 halaman yang ada menjadi cakupan penuh 14 modul 40+ halaman |
| Lapisan Service | Menggunakan kembali `ApiService`, `AuthService`, `CaptchaService`, `ExportService` yang ada |

### 21.5 Arsitektur Target P1 — Mesin Kalkulasi Bisnis

| Mesin | Kelas Service | Aturan Kunci |
|------|--------|----------|
| Pembukuan berpasangan | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Validasi wajib keseimbangan debit/kredit, pemindahan laba rugi akhir periode, konversi nilai tukar multivaluta |
| Kalkulasi gaji | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Batas atas/bawah basis jaminan sosial, rasio dana perumahan, tarif pajak penghasilan progresif, pembayaran via bank |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | Ekspansi BOM per lapisan + susut, low level code (LLC), stok pengaman, aturan batch |
| Kualitas | `QmsInspectionService` | Alur tiga dokumen IQC bahan masuk/IPQC proses/OQC pengiriman |
| Notifikasi | `WebSocketService`, `ChannelRouter` | Multi-kanal internal/email/WeCom/DingTalk |

### 21.6 Ringkasan Perubahan Model Data

| Fase | Jumlah Tabel Baru | Modul Terkait |
|------|----------|----------|
| P0 | 0 | Murni frontend, tanpa perubahan tabel |
| P1 | 14 | Keuangan(2) + HR(3) + Manufaktur(2) + Kualitas(5) + Notifikasi(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. Multi-Tenant (kapabilitas dicadangkan, tidak diaktifkan)

> Pernyataan hak cipta sama dengan header file: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Posisi dan Keputusan

Multi-tenant diposisikan sebagai **kapabilitas cadangan** di proyek ini, periode ini **tidak dihubungkan, tidak diaktifkan** (degradasi terdokumentasi). Sesuai dengan perencanaan:
Penagihan SaaS, aktivasi mandiri tenant, dan "skema komersialisasi multi-tenant lengkap" lainnya tidak termasuk dalam ruang lingkup pembangunan proyek ini; periode ini hanya mempertahankan
kerangka kode minimal (middleware + model Trait) dan memberikan langkah aktivasi, untuk diaktifkan sesuai kebutuhan di masa mendatang.
Catatan: "Isolasi multi-tenant" di roadmap §21.2 P3 disesuaikan menjadi "kapabilitas cadangan (degradasi terdokumentasi)", mempertahankan kerangka, tidak dihubungkan.

Dasar keputusan (tinjauan 2026-08):
- Deployment yang ada hampir semuanya single-tenant, menghubungkan akan membawa kompleksitas isolasi dan risiko regresi yang tidak perlu;
- Kerangka saat ini memiliki cacat teknis (lihat 22.4), "terhubung berarti terisolasi" tidak berlaku, perlu menyelesaikan perbaikan desain terlebih dahulu;
- Isolasi memerlukan penambahan kolom per tabel pada 227 tabel bisnis dan pengaktifan per model, biayanya jauh melebihi "koneksi minimal".

### 22.2 Fakta Saat Ini (verifikasi kode dan konfigurasi)

| Item | Kondisi Saat Ini |
|----|------|
| `app/middleware/TenantScope.php` | Ada, tidak didaftarkan; membaca kode tenant dari header `X-Tenant-Code` lalu mencari `erp_tenant` dan menyuntikkan konteks, langsung meloloskan jika header tidak ada |
| `app/model/concerns/TenantScope.php` | Ada; dipakai 4 model keuangan (`FinanceLedger` / `FinanceBalanceSheet` / `FinanceCashFlow` / `FinanceProfit`, keluarga perusahaan `tenantScopeByCompany()` mengembalikan true), memfilter berdasarkan `company_id`; karena middleware tidak terdaftar, konteks permintaan tidak akan disuntikkan, sehingga global scope saat ini tidak berlaku |
| `config/middleware.php` | Rantai global: Cors → SecurityFilter → RateLimit → TracingId, tanpa TenantScope |
| `config/route.php` grup /admin/v1 | AdminAuth → AdminPermission → OperationLog, tanpa TenantScope |
| Payload JWT | Hanya `sub` / `username` / `token_type`, **tanpa klaim tenant_id** (`app/api/v1/controller/AuthController.php`) |
| Database | **Seluruh database tanpa kolom tenant_id** (install.sql juga tidak) |
| Model | 4 model keuangan memakai trait `TenantScope` (keluarga perusahaan, memfilter `company_id`) —— isolasi percontohan; saat konteks tenant tidak disuntikkan, tidak ada filter yang diterapkan |

### 22.3 Langkah Aktivasi (referensi cadangan, tidak dieksekusi periode ini)

1. Daftarkan middleware: pada grup /admin/v1 di `config/route.php`, tambahkan
   `app\middleware\TenantScope::class` pada `middleware()` (ditempatkan setelah AdminAuth, memastikan telah terautentikasi).
2. Peminta membawa `X-Tenant-Code` (string kode tenant) pada header permintaan.
3. Tambahkan kolom `tenant_id` (BIGINT + indeks) pada tabel bisnis yang perlu diisolasi dan isi ulang data yang ada;
   tabel kamus/sistem (seperti `erp_admin_user`, `erp_role`, `erp_permission`) tidak diisolasi.
4. Gunakan `use app\model\concerns\TenantScope;` pada kelas model yang perlu diisolasi, otomatis memfilter sesuai tenant saat ini.
5. (Opsional) Jika ingin mengambil tenant dari JWT daripada header permintaan: perluas payload penandatanganan login dengan klaim `tenant_id`,
   dan baca dari `$payload['tenant_id']` di middleware.

### 22.4 Keterbatasan Teknis yang Diketahui (harus diselesaikan sebelum aktivasi)

- **Batas kepercayaan (harus diselesaikan sebelum pendaftaran)**: sumber konteks tenant adalah header permintaan `X-Tenant-Code`,
   yaitu input yang dapat dipalsukan; jika middleware diaktifkan sebelum `erp_admin_user` diikatkan ke perusahaan/tenant
   (penentuan kepemilikan admin), akan timbul celah bidang data lintas kewenangan (setiap admin terautentikasi dapat
   mengklaim tenant mana pun dan membaca datanya).
- **Rantai transmisi statis putus (teruji PHP 8.3) sudah digantikan oleh versi perbaikan P2-4 B5**: trait `TenantScope`
   kini melalui injeksi konteks permintaan (`request()->tenantId` / `companyId`), jalur ini tanpa status statis,
   sehingga interferensi lintas permintaan dalam proses menetap hilang; fasad statis bernama trait telah ditandai
   `@deprecated`, hanya untuk cadangan pengujian/CLI.
- **Kesenjangan data plane**: seluruh database tidak memiliki kolom tenant_id, perlu migrasi per tabel; tabel kamus yang dibagikan lintas tenant perlu mekanisme pengecualian desain.

### 22.5 Kriteria Penerimaan

Penerimaan periode ini = dokumen dan kode konsisten: `config/middleware.php` dan `config/route.php` tidak mengandung
registrasi TenantScope; komentar middleware menandai "sudah diimplementasikan, default tidak didaftarkan" dan memberikan titik pendaftaran serta batas kepercayaan,
komentar trait menandai jalur injeksi konteks permintaan dan garis regresi (tanpa konteks tenant tidak ikut memfilter);
deskripsi bagian ini berkorespondensi satu per satu dengan kondisi kode saat ini.
