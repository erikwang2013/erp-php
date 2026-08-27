# Dokumen Desain Arsitektur Keamanan

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama Pertahanan Berlapis

Sistem mengadopsi model pertahanan berlapis 7 lapis, memfilter permintaan berbahaya dari luar ke dalam lapis demi lapis, memastikan ketika satu lapis mana pun gagal, masih ada garis pertahanan berikutnya sebagai cadangan.

Seluruh rantai middleware dieksekusi dalam urutan berikut (lihat `config/middleware.php`):

```
Permintaan → Cors → SecurityFilter → RateLimit → [Middleware grup rute: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Lapis | Middleware/Mekanisme | Target Perlindungan |
|----|--------|---------|
| 1 | SecurityFilter | Intercept serangan XSS / SQL Injection / Path Traversal / Command Injection / CSRF |
| 2 | Cors | Keamanan lintas domain + injeksi header keamanan respons |
| 3 | RateLimit | Rate limit sliding window Redis, mencegah brute force |
| 4 | AdminAuth | Autentikasi JWT + logout blacklist |
| 5 | AdminPermission | Otorisasi granularitas RBAC method.path |
| 6 | OperationLog | Audit operasi + pelacakan sumber |
| 7 | Enkripsi Data | Obfuskasi ID Hashids + Enkripsi DB Encryptable + Enkripsi transmisi EncryptionService |

Tiga lapis frontend (Flutter) memiliki validasi input independen, backend tidak dipercaya; setiap lapis mempertahankan pertahanan independen.

---

## 2. Mesin Deteksi Serangan

### 2.0 Pembatasan Metode HTTP

SecurityFilter memvalidasi metode HTTP sebelum semua deteksi serangan, hanya mengizinkan metode standar berikut:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Metode non-standar (seperti TRACE, CONNECT, PATCH, metode kustom, dll.) langsung mengembalikan **405 Method Not Allowed**, body respons berupa HTML kosong, tidak memasuki deteksi serangan atau logika bisnis selanjutnya.

Ini adalah garis pertahanan pertama pertahanan berlapis, secara efektif memblokir:
- Serangan pelacakan lintas situs TRACE (XST)
- Penyalahgunaan tunnel proxy CONNECT
- Probing metode WebDAV non-standar
- Enumerasi metode HTTP oleh scanner otomatis

### 2.1 XSS Cross-Site Scripting

Semua regex berasal dari `SecurityFilter::PATTERNS['XSS']`, pencocokan case-insensitive.

| Pola Deteksi | Regex | Serangan yang Diblokir |
|----------|------|-----------|
| Tag script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` dan varian dengan spasi |
| Atribut event | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Event inline seperti `onclick="javascript:..."` |
| Protokol pseudo JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` dll. |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` dll. |
| Template injection | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` dan injeksi template server/Angular/Vue lainnya |

### 2.2 SQL Injection

| Pola Deteksi | Regex | Serangan yang Diblokir |
|----------|------|-----------|
| Query UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` eksfiltrasi data |
| Injeksi OR selalu benar | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Perusakan struktur tabel | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Pemanggilan stored procedure | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Eksekusi perintah stored procedure ekstensi MSSQL |
| Probing metadata | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Probing struktur database MySQL/PG/SQLite/MSSQL |
| Bypass komentar | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Bypass komentar `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Path Traversal

| Pola Deteksi | Regex | Serangan yang Diblokir |
|----------|------|-----------|
| Backtracking direktori | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` backtracking direktori bertingkat |
| Probing file sensitif | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` dll. |
| Null byte truncation | `%00` | `../../../etc/passwd%00.jpg` bypass validasi ekstensi |

### 2.4 Command Injection

| Pola Deteksi | Regex | Serangan yang Diblokir |
|----------|------|-----------|
| Perintah pipe/titik koma | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substitusi backtick | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substitusi $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Pipeline unduhan jarak jauh | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF Cross-Site Request Forgery

Logika validasi diimplementasikan di `SecurityFilter::checkCsrf()`:

```php
// Hanya POST/PUT/DELETE yang memicu validasi
// Header Origin dan Referer keduanya kosong → lolos (klien non-browser)
// Origin tidak kosong → parse domain Origin dan bandingkan dengan Host
```

Aturan perbandingan:
- Hapus prefiks `www.` dari Host lalu bandingkan tepat dengan domain Origin
- Jika Host adalah domain induk dari Origin (seperti `Origin: app.example.com`, `Host: example.com` — memicu `str_contains($originHost, '.' . $hostOnly)`), lolos
- Tidak cocok tepat dan bukan subdomain → kembalikan 403, dianggap serangan CSRF

Catatan: klien non-browser (seperti curl tanpa Origin/Referer) langsung lolos, perlindungan CSRF hanya efektif di lingkungan browser.

### 2.6 Upload File Berbahaya

| Pola Deteksi | Regex | Serangan yang Diblokir |
|----------|------|-----------|
| Penyamaran ekstensi ganda | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` bypass daftar putih |
| Ekstensi PHP | `\.php\s*$/m` | Meneruskan path `.php` langsung di parameter permintaan |

---

## 3. Eskalasi Serangan dan Blacklist IP

SecurityFilter memiliki mekanisme eskalasi serangan bawaan untuk mencegah IP yang sama terus memindai.

### Alur Eskalasi

```
Hit pemindaian ke-1 → Redis INCR security_escalate:{ip} = 1, TTL=60s
Hit pemindaian ke-2 → INCR → 2
...
Hit pemindaian ke-5 → INCR → 5
    → Memicu ban: SETEX security_ban:{ip} 900 1
    → Menghapus penghitung DEL security_escalate:{ip}
    → Menulis log keamanan: [SECURITY] IP banned 15min
```

### Perilaku Selama Ban

Setiap permintaan memasuki SecurityFilter terlebih dahulu memeriksa `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

IP yang diblokir dalam 15 menit, semua permintaan (termasuk permintaan sah) langsung mengembalikan 403, sepenuhnya melewati logika bisnis selanjutnya.

### Konstanta Konfigurasi

| Konstanta | Nilai | Arti |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Ambang jumlah pemicuan dalam jendela 60 detik |
| ESCALATE_WINDOW | 60 | Jendela penghitung (detik) |
| BAN_DURATION | 900 | Durasi blacklist (detik), yaitu 15 menit |

### Log Keamanan

Lokasi file: `runtime/logs/security.log`

Contoh format log:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Batas Ukuran Body Permintaan

`Content-Length > 10MB` langsung mengembalikan 413 Payload Too Large, mencegah serangan DoS body permintaan sangat besar.

### Validasi Content-Type

Permintaan POST/PUT **harus** mendeklarasikan `Content-Type` sebagai `application/json` atau `application/x-www-form-urlencoded`, jika tidak, mengembalikan 415 Unsupported Media Type. Permintaan upload file (dengan field file) melewati pemeriksaan ini.

---

## 4. Header Keamanan Respons

Semua header diinjeksikan di middleware `Cors`, ditambahkan ke setiap respons melalui `$response->withHeaders()`.

| Header | Nilai | Fungsi |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Mengizinkan lintas domain sumber mana pun (skenario panel admin intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Kumpulan metode yang diizinkan |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Header kustom yang diizinkan |
| Access-Control-Max-Age | `86400` | Cache permintaan preflight 24 jam |
| X-Content-Type-Options | `nosniff` | Melarang browser MIME sniffing |
| X-Frame-Options | `DENY` | Melarang semua embed iframe, mencegah clickjacking |
| X-XSS-Protection | `1; mode=block` | Mengaktifkan filter XSS bawaan browser dan memblokir render halaman |
| Referrer-Policy | `strict-origin-when-cross-origin` | Sumber yang sama mengirim URL lengkap, lintas domain hanya mengirim domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Menonaktifkan API kamera/mikrofon/lokasi di seluruh situs |

Permintaan preflight OPTIONS langsung mengembalikan respons kosong 204, tidak memasuki rantai middleware selanjutnya.

### 4.2 Content-Security-Policy (CSP)

Diinjeksikan bersama header keamanan lain di middleware Cors, memberikan pertahanan berlapis, membatasi sumber daya yang boleh dimuat dan dieksekusi browser.

| Header | Nilai | Fungsi |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Membatasi sumber skrip/gaya/gambar/koneksi/iframe/formulir dll. |
| X-Permitted-Cross-Domain-Policies | `none` | Melarang pemuatan file kebijakan lintas domain Adobe Flash/PDF dll. |

Poin-poin kebijakan CSP:
- `default-src 'self'`: secara default hanya mengizinkan sumber daya sumber yang sama
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: mengizinkan skrip sumber yang sama + skrip inline (diperlukan Flutter Web) + eval (diperlukan debugging Flutter Web)
- `frame-ancestors 'none'`: melarang di-embed iframe oleh halaman mana pun, perlindungan ganda dengan X-Frame-Options: DENY
- `base-uri 'self'`: membatasi tag `<base>` hanya ke sumber yang sama
- `form-action 'self'`: membatasi formulir hanya dapat dikirim ke sumber yang sama

---

## 5. Strategi Rate Limit

### Algoritma

Redis Sorted Set sliding window + skrip Lua atomik, operasi kunci:

```lua
-- 1. Bersihkan catatan lama di luar jendela
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Periksa hitungan jendela saat ini
local count = redis.call('ZCARD', KEYS[1])
-- 3. Jika melebihi batas, kembalikan {0, count}; jika tidak, ZADD dan kembalikan {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- sufiks acak untuk mencegah timpa milidetik yang sama
redis.call('EXPIRE', KEYS[1], window + 10)
```

Skrip Lua dieksekusi single-thread di sisi server Redis, **secara alami atomik**, menghilangkan kondisi balapan TOCTOU (Time-of-check to Time-of-use).

### Konfigurasi Rate Limit

| Rute | Batas | Jendela | Skenario |
|------|------|------|------|
| Default (semua rute) | 60 kali/menit | 60s | API umum |
| `/api/auth/login` | 10 kali/menit | 60s | Login (mencegah brute force) |
| `/api/auth/register` | 5 kali/menit | 60s | Registrasi (mencegah registrasi massal; nonaktif secara default, perlu `REGISTRATION_ENABLED=1` untuk mengaktifkan) |

### Header Respons

Saat rate limit terpicu, mengembalikan HTTP 429 dan body JSON:
```json
{"code": 429, "message": "Permintaan terlalu sering, silakan coba lagi nanti", "data": []}
```

Semua respons (termasuk respons normal) membawa header berikut:

| Header | Keterangan |
|----|------|
| X-RateLimit-Limit | Jumlah maksimum permintaan yang diizinkan di jendela saat ini |
| X-RateLimit-Remaining | Jumlah permintaan tersisa yang tersedia di jendela saat ini |
| X-RateLimit-Reset | Timestamp Unix reset jendela |
| Retry-After | Hanya dibawa saat rate limit, detik yang disarankan untuk menunggu |

### Strategi Degradasi

Saat Redis bermasalah (timeout koneksi, tidak tersedia, dll.), **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, lepaskan semua permintaan
}
```

Lebih baik kehilangan perlindungan rate limit dalam waktu singkat daripada memblokir permintaan bisnis normal.

### 5.4 Mekanisme Kunci Akun

Selain rate limit, endpoint login menambahkan mekanisme **kunci akun** untuk mencegah brute force terarah ke pengguna tertentu.

**Alur penguncian**:

```
Login gagal → Redis INCR account_lockout:{userId} TTL=900s
5 kali gagal berurutan → Redis SETEX account_locked:{userId} 900 1
            → Mengembalikan 429 "Akun telah dikunci, silakan coba lagi dalam 15 menit"
            → Menghapus penghitung DEL account_lockout:{userId}
```

**Perilaku selama terkunci**:

Selama terkunci, semua permintaan login langsung mengembalikan 429, tidak melakukan validasi kata sandi, sepenuhnya memblokir upaya brute force.

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Jumlah maksimum kegagalan berurutan |
| LOCKOUT_DURATION | 900 | Durasi kunci (detik), yaitu 15 menit |

Catatan: kunci akun berbasis `userId` bukan IP, sehingga penyerang yang mengganti IP tidak dapat melewati kunci. Terlapis dengan rate limit IP (10 kali/menit) membentuk perlindungan ganda:
- Level IP: rate limit 10 kali/menit mencegah brute force terdistribusi
- Level akun: kunci 5 kali gagal mencegah brute force terarah

---

## 6. Autentikasi dan Otorisasi

### 6.1 Autentikasi JWT

Diimplementasikan oleh middleware AdminAuth, dipasang pada grup rute yang memerlukan autentikasi.

**Konfigurasi parameter** (`config/plugin/erikwang2013/jwt/jwt`, diinjeksi dari `.env`):

| Parameter | Nilai | Keterangan |
|------|-----|------|
| Algoritma | HS256 | Tanda tangan simetris HMAC-SHA256 |
| Kunci | `JWT_SECRET` | Diinjeksi dari variabel lingkungan, perlu diganti di produksi |
| TTL access_token | 7200s (2h) | `JWT_TTL` |
| TTL refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Ekstraksi Token**: diekstrak dari header `Authorization: Bearer <token>`, hapus prefiks `Bearer ` untuk mendapatkan JWT asli.

**Alur autentikasi**:
1. Token kosong → langsung 401 `{"code": 401, "message": "Belum login"}`
2. Periksa blacklist Redis `jwt_blacklist:{md5(token)}` → terkena → 401 `Token tidak berlaku, silakan login ulang`
3. JWT decode → gagal (kedaluwarsa/tanda tangan tidak cocok) → 401 `Token kedaluwarsa atau tidak valid`
4. Berhasil → injeksi `$request->adminId` dan `$request->adminUsername`

**Mekanisme blacklist**: saat pengguna logout, `md5(token)` ditulis ke Redis, TTL diatur ke sisa masa berlaku JWT. Saat Redis bermasalah, pemeriksaan blacklist dilewati (fail-open), Token yang sudah logout masih dapat digunakan dalam waktu singkat, tetapi masa berlaku pendek JWT itu sendiri (2h) berfungsi sebagai perlindungan cadangan.

### 6.2 Pembatasan Sesi Konkuren

Untuk mencegah penyalahgunaan Token yang bocor di banyak perangkat, sistem membatasi jumlah Token valid yang dimiliki satu pengguna secara bersamaan.

**Logika pembatasan**:

```
Login berhasil → Terbitkan Token baru
         → Query jumlah Token valid pengguna saat ini: Redis SCARD user_tokens:{userId}
         → Jika jumlah >= 3 (MAX_CONCURRENT_SESSIONS):
            → Urutkan naik berdasarkan waktu pembuatan, hapus Token tertua:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → Tambahkan Token baru ke set: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Konstanta konfigurasi**:

| Konstanta | Nilai | Arti |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Jumlah maksimum Token konkuren per pengguna |

**Skenario terdepak**: ketika pengguna login di perangkat ke-4, Token perangkat ke-1 dipaksa masuk blacklist, permintaan berikutnya mengembalikan 401 "Token tidak berlaku, silakan login ulang".

Saat logout, Token saat ini dihapus dari set. Saat Token kedaluwarsa secara alami, key Redis otomatis tidak berlaku, anggota set pun berkurang.

### 6.3 Model Izin RBAC

Diimplementasikan oleh middleware AdminPermission.

**Model data**: relasi tiga lapis User -> Role -> Permission

- `erp_admin_user` (tabel pengguna)
- `erp_admin_user_role` (tabel relasi pengguna-peran)
- `erp_admin_role` (tabel peran)
- `erp_admin_role_permission` (tabel relasi peran-izin)
- `erp_admin_permission` (tabel izin)

**Jenis izin**:
| type | Arti | Contoh |
|------|------|------|
| 1 | Izin menu | Mengontrol visibilitas navigasi kiri |
| 2 | Izin tombol | Mengontrol tombol aksi dalam halaman (tambah/edit/hapus) |
| 3 | Izin API | Mengontrol pemanggilan endpoint backend |

Format identitas izin API: `{method}.{path}`

Contoh:
- `post.admin/user` — membuat pengguna
- `put.admin/user` — mengedit pengguna
- `delete.admin/user` — menghapus pengguna
- `get.admin/user` — melihat daftar pengguna

**Alur otorisasi**:
1. `$request->adminId` kosong → lolos (rute tidak dikonfigurasi autentikasi terlebih dahulu)
2. Ambil pengguna → peran (lewati peran nonaktif `status=0`) → daftar izin
3. Super admin (`slug = '*'`) → langsung lolos
4. Bangun `strtolower(method) . '.' . trim(path, '/')` → bandingkan dengan daftar izin
5. Tidak cocok → 403 `{"code": 403, "message": "Tidak ada izin akses"}`

**Konfirmasi kedua**: BaseController menyediakan metode `confirmPassword()`, operasi sensitif (menghapus pengguna, ekspor data, dll.) memerlukan input kata sandi saat ini di lapisan Controller, mencegah operasi tidak sah setelah session hijacking.

---

## 7. Log Audit

### 7.1 Log Operasi

Middleware OperationLog otomatis mencatat log operasi untuk permintaan POST / PUT / DELETE. Permintaan GET tidak dicatat.

**Bidang yang dicatat**:

| Bidang | Sumber | Keterangan |
|------|------|------|
| id | SnowflakeService::generate() | ID unik global |
| user_id | `$request->adminId` | ID operator, 0 jika belum login |
| action | `$request->method()` | Sama dengan method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Path permintaan |
| ip | `$request->getRealIp()` | IP asli klien |
| source | detectSource() | Platform sumber klien |
| input | Body permintaan (JSON yang dimasking) | Data yang disubmit operasi |
| created_at | `date('Y-m-d H:i:s')` | Waktu operasi |

**Filter bidang sensitif**: rekursif menelusuri body permintaan, nilai bidang berikut diganti dengan `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Deteksi sumber** (`detectSource()`): berdasarkan prioritas:

1. Utamakan membaca header kustom `X-Client-Platform` (deklarasi eksplisit klien native)
2. Degradasi ke inferensi string User-Agent (urutan deteksi metode `detectSource()`):

| Platform | Kata Kunci UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Nilai default fallback |

**Toleransi kesalahan**: pengecualian penulisan log tidak memblokir permintaan bisnis (`catch (\Throwable)` ditelan secara diam-diam).

### 7.2 Log Keamanan

**Lokasi file**: `runtime/logs/security.log`

**Isi yang dicatat**:
- Log intercept serangan: kategori serangan, IP, path, bidang, sumber, fragmen payload (200 karakter pertama)
- Notifikasi ban IP: IP yang diblokir, jumlah pemicuan

Izin log adalah `FILE_APPEND | LOCK_EX`, memastikan penulisan aman konkuren.

---

## 8. Perlindungan Data

Sistem mengadopsi strategi perlindungan data tiga lapis, berkorespondensi dengan tiga tahap aliran data.

### 8.1 Lapisan Transmisi — EncryptionService

`EncryptionService` menggunakan paket `erikwang2013/encryption`, melakukan enkripsi/deskripsi bidang sensitif pada permintaan/respons API.

**Detail teknis**:
- Algoritma: `aes-256-cbc-hmac` (memiliki tanda tangan HMAC anti-tamper)
- Kunci: variabel lingkungan `ENCRYPTION_KEY`, otomatis diselaraskan ke 32 byte
- Digunakan untuk: transmisi nomor ponsel, nomor KTP, dll. antara klien dan API

**Metode utilitas masking**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (username lebih dari 2 karakter) atau `a**@example.com`

### 8.2 Lapisan Penyimpanan — Encryptable Cast

Model `AdminUser` menggunakan Eloquent cast `Erikwang2013\Encryptable\Encryptable`, bidang terkait:

- `email` → cast ke Encryptable, enkripsi/deskripsi otomatis
- `phone` → cast ke Encryptable, enkripsi/deskripsi otomatis
- `id_card` → cast ke Encryptable, enkripsi/deskripsi otomatis

Saat menulis ke database, otomatis dienkripsi menjadi ciphertext; saat membaca, otomatis dideskripsi menjadi plaintext. Jenis kolom penyimpanan database adalah `VARCHAR(500)`, ciphertext disimpan dalam bentuk base64.

**Sistem kunci**: terpisah dari enkripsi lapisan transmisi (`ENCRYPTION_KEY`) menggunakan `ENCRYPTABLE_KEY`; kebocoran satu kunci tidak membuat lapisan lain tidak berfungsi.

Rotasi kunci: variabel lingkungan `ENCRYPTION_PREVIOUS_KEYS` mendukung daftar kunci historis (dipisahkan koma), saat membaca data lama mencoba deskripsi dengan kunci historis, saat menulis kembali menggunakan kunci saat ini untuk mengenkripsi ulang.

### 8.3 Lapisan Tampilan — Obfuskasi dan Masking ID

**Obfuskasi ID Hashids**: `HashidsService` menggunakan paket `erikwang2013/hashids`.

- ID BIGINT database yang dikembalikan API eksternal di-encode menjadi string hash (seperti `xK3mN9qR2pL7wV8b`)
- Klien mengirim string hash saat meminta, backend otomatis mendekode menjadi ID asli
- Nilai salt `HASHIDS_SALT` diinjeksi dari variabel lingkungan, salt berbeda menghasilkan hasil encode/decode yang sepenuhnya berbeda
- Panjang minimum hash 16 digit, menggunakan charset alfanumerik 62 digit
- BaseController menyediakan metode praktis `encodeId()`, `decodeId()`, `encodeIds()`

**Masking ekspor**: saat ekspor Excel/PDF (ExportController), bidang sensitif dimasking secara seragam:
- Nomor ponsel: `138****1234`
- Email: `a***@example.com`
- KTP: ditutupi penuh menjadi `********`

---

## 9. Manajemen Kunci

Semua kunci diinjeksi melalui variabel lingkungan `.env`, file konfigurasi membaca dengan `getenv()` dan memiliki nilai default fallback bawaan (hanya aman di lingkungan pengembangan).

| Variabel Lingkungan | Kegunaan | Paket | Persyaratan Produksi |
|----------|------|-----|---------|
| JWT_SECRET | Kunci tanda tangan JWT | erikwang2013/jwt-webman | String acak 64+ karakter |
| JWT_ALGORITHM | Algoritma tanda tangan JWT | sama di atas | Pertahankan HS256 |
| HASHIDS_SALT | Salt encode ID | erikwang2013/hashids | String acak |
| SNOWFLAKE_DATACENTER_ID | ID pusat data (0-31) | erikwang2013/snowflake-php | Pusat data tunggal tetap default |
| ENCRYPTION_KEY | Kunci enkripsi lapisan transmisi API | erikwang2013/encryption | String acak 32 byte |
| ENCRYPTABLE_KEY | Kunci enkripsi lapisan penyimpanan DB | erikwang2013/encryptable | String acak 32 byte, berbeda dengan kunci transmisi |

**Persyaratan keamanan**:
- File `.env` sudah dimasukkan ke `.gitignore`, dilarang keras dikomit ke repositori
- `.env.example` adalah file template publik, tidak berisi kunci asli
- Lingkungan produksi **wajib** mengganti semua kunci default menjadi string acak
- Disarankan menggunakan `openssl rand -base64 32` untuk membuat kunci

### Isolasi Penyimpanan Kunci

| Lapisan | Kunci Konfigurasi | Variabel Lingkungan Kunci |
|----|--------|-------------|
| Enkripsi transmisi | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Enkripsi penyimpanan | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Obfuskasi ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Tanda tangan JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Sistem menyediakan endpoint informasi kontak keamanan yang sesuai standar RFC 9116 di `/.well-known/security.txt`, memudahkan peneliti keamanan menemukan saluran pelaporan dengan cepat saat menemukan kerentanan.

**Cara akses**:

```
GET /.well-known/security.txt
```

**Isi respons**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Keterangan bidang**:

| Bidang | Keterangan |
|------|------|
| Contact | Kontak pelaporan kerentanan keamanan |
| Expires | Waktu kedaluwarsa file, perlu diperbarui berkala |
| Preferred-Languages | Bahasa komunikasi pilihan |
| Canonical | URL kanonik file ini |
| Policy | Tautan kebijakan keamanan/kebijakan pengungkapan kerentanan |

Endpoint ini tidak dibatasi oleh middleware seperti rate limit, autentikasi, dll., siapa pun dapat mengakses langsung.

---

## 11. Konfigurasi Keamanan Nginx

Proyek menyediakan `nginx-security.conf` sebagai referensi konfigurasi penguatan keamanan reverse proxy Nginx untuk lingkungan produksi.

**Langkah keamanan yang disertakan**:

| Item Konfigurasi | Fungsi |
|--------|------|
| `server_tokens off` | Menyembunyikan nomor versi Nginx |
| `client_max_body_size 10m` | Membatasi ukuran body permintaan, bersinergi dengan SecurityFilter |
| `limit_req_zone` | Pembatasan frekuensi permintaan di level Nginx |
| `limit_conn_zone` | Pembatasan jumlah koneksi konkuren |
| `add_header` header keamanan | Menambahkan header keamanan seperti X-XSS-Protection di level Nginx |
| `if ($request_method)` | Menolak metode HTTP non-standar di level Nginx |
| Konfigurasi SSL/TLS | Konfigurasi modern TLS 1.2/1.3, menonaktifkan cipher lemah |
| Menyembunyikan header backend | `proxy_hide_header` menghapus header sensitif seperti versi webman |

**Cara penggunaan**: gabungkan konfigurasi di `nginx-security.conf` ke blok server Nginx Anda, sesuaikan dengan domain dan path sertifikat aktual.

---

## 12. Model Ancaman

### 12.1 Ancaman yang Dilindungi

| Jenis Ancaman | Vektor Serangan | Lapisan Pertahanan |
|----------|---------|---------|
| Penyalahgunaan metode HTTP | Serangan XST TRACE/TRACK, tunnel proxy CONNECT, probing metode WebDAV | Daftar putih metode SecurityFilter 405 (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Brute force terarah | Mencoba kata sandi berulang untuk pengguna tertentu | Kunci akun (5 kegagalan terkunci 15 menit) + RateLimit (login 10/menit) + Captcha |
| Brute force | Upaya berulang username/kata sandi dari IP terdistribusi | RateLimit (login 10/menit) + Captcha |
| XSS cross-site scripting | `<script>`, onerror, javascript: | SecurityFilter (5 pola) + header respons X-XSS-Protection + CSP |
| SQL injection | UNION SELECT, OR 1=1, bypass komentar | SecurityFilter (6 pola) + query terparameterisasi Eloquent ORM |
| CSRF cross-site request forgery | Situs berbahaya mengirimkan permintaan atas nama | Validasi Origin/Referer SecurityFilter |
| Path traversal | `../../etc/passwd` | Pola path traversal SecurityFilter + daftar putih ekstensi UploadController |
| Command injection | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 pola) |
| Session hijacking | Mencuri Token JWT | Masa berlaku pendek JWT (2h) + logout blacklist + konfirmasi ulang kata sandi operasi sensitif |
| Enumerasi ID | Menelusuri ID numerik untuk menebak jumlah data | Hashids diobfuskan menjadi string acak |
| Kebocoran data | DB ditarik / man-in-the-middle / kebocoran log | Enkripsi/masking tiga lapis + filter bidang sensitif OperationLog |
| Serangan DoS | Body permintaan sangat besar / permintaan frekuensi tinggi | Batas body 10MB + RateLimit 60/menit + blacklist IP |
| Eskalasi izin | Pengguna berizin rendah mengakses endpoint admin | Otorisasi granularitas RBAC method.path |
| Serangan upload file | Ekstensi ganda shell.php.png | Deteksi file berbahaya SecurityFilter |

### 12.2 Keterbatasan yang Diketahui

| Keterbatasan | Rentang Dampak | Langkah Mitigasi |
|------|---------|---------|
| Perlindungan CSRF hanya efektif di browser | Klien non-browser (curl, Postman, App seluler) dapat melewati pemeriksaan Origin/Referer | Klien non-browser secara alami tidak terpengaruh CSRF; bergantung pada autentikasi JWT menggantikan Cookie |
| Saat Redis tidak tersedia, rate limit dan blacklist terdegradasi menjadi fail-open | Penyerang dapat melewati rate limit dan intercept frekuensi tinggi | Memantau ketersediaan Redis dengan alert; masa berlaku pendek JWT sebagai cadangan |
| Tidak ada mesin WAF independen | SecurityFilter menggunakan pencocokan regex `@preg_match`, bukan mesin aturan WAF khusus | Di produksi disarankan memasang Nginx ModSecurity atau Cloudflare WAF |
| JWT tanpa status tidak dapat dinonaktifkan secara proaktif | Sebelum Token kedaluwarsa, tidak dapat dicabut dari sisi server (selain blacklist) | Blacklist + TTL pendek 2h mengurangi jendela risiko |
| Blacklist IP hanya disimpan di memori | Setelah Redis restart, blacklist hilang | Durasi ban hanya 15 menit, dampak terbatas |
| Endpoint admin tanpa rate limit khusus | Endpoint admin berbagi batas default 60/menit dengan endpoint umum | Frekuensi operasi admin secara alami rendah, belum perlu dibedakan |
| `@preg_match` menekan error | Regex input yang cacat gagal secara diam-diam | `preg_last_error()` dapat ditambahkan pemantauan, saat ini belum diimplementasikan |
