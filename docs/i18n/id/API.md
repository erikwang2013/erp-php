# Dokumen Referensi API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Dokumen API

Proyek menggunakan [hg/apidoc](https://github.com/hg-code/apidoc) untuk menghasilkan dokumen API interaktif secara otomatis.

**Cara akses:** Setelah layanan dimulai, akses `http://localhost:8787/apidoc`

**Pengelompokan dokumen:**
| Grup | Keterangan | Jumlah Modul |
|------|------|--------|
| Antarmuka Admin | Seluruh antarmuka sistem manajemen backend | 25 modul |
| Antarmuka Klien (Service API) | Antarmuka ringan untuk dipanggil seluler/Web | 3 modul |

**Header global:**
| Header | Keterangan |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | Nomor versi API (v1) |
| `Accept-Language` | Bahasa internasionalisasi (zh-CN/en) |

**Konvensi anotasi:** Semua metode controller menggunakan seri anotasi `@Apidoc\*` untuk menandai nama antarmuka, deskripsi, URL, metode permintaan, parameter, dan struktur nilai kembali.

## 1. Ikhtisar

Sistem Manajemen Terbuka (open-admin) dibangun di atas webman v2, menyediakan RESTful JSON API. Semua antarmuka admin memerlukan autentikasi JWT dan validasi izin RBAC, antarmuka publik dirutekan ke controller ber-versioning melalui header versi API.

- **URL Dasar**: `http://localhost:8787`
- **Versi API**: dikontrol melalui header `API-Version: v1` (default v1 saat tidak ada)

> **Ikhtisar endpoint**: Autentikasi(5) | Dasbor(1) | Pengguna(7) | Peran(4) | Izin(4) | Konfigurasi(4) | Log(1) | Pusat Pribadi(3) | Impor-Ekspor(3) | Unggah(1) | Operasi(4: health/metrics/docs/security.txt) | Total 37 endpoint
- **Autentikasi**: `Authorization: Bearer <token>` (JWT)
- **Format respons**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint dokumen**: `GET /api/docs` mengembalikan spesifikasi JSON OpenAPI 3.0

### Internasionalisasi

API otomatis mengganti bahasa melalui header `Accept-Language`:

| Nilai Header | Bahasa |
|---------|------|
| `zh-CN`, `zh` | 中文 (default) |
| `en`, `en-US` | English |

```bash
# Respons bahasa Inggris
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# Respons 中文 (default)
curl http://localhost:8787/admin/product
```

Field `message` dalam respons akan dikembalikan dalam bahasa yang sesuai.

### Persyaratan Permintaan

- Hanya mengizinkan metode `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`, penggunaan metode HTTP lain (seperti TRACE, CONNECT, PATCH) mengembalikan 405
- Semua permintaan `POST` / `PUT` harus mengatur `Content-Type: application/json` (kecuali unggah file), jika tidak mengembalikan 415
- Ukuran body permintaan tidak boleh melebihi 10MB, jika tidak mengembalikan 413
- Filter keamanan memindai semua input permintaan untuk XSS, injeksi SQL, path traversal, injeksi perintah, jika terkena mengembalikan 403
- 5 kali gagal login berturut-turut memicu penguncian akun (15 menit), selama periode penguncian permintaan login mengembalikan 429
- Pengguna yang sama maksimal dapat memiliki 3 Token valid secara bersamaan, saat melebihi Token paling lama otomatis masuk daftar hitam

## 2. Kode Kesalahan

| code | Arti | Skenario Pemicu |
|------|------|---------|
| 0 | Sukses | |
| 400 | Kesalahan parameter permintaan | Format permintaan tidak benar |
| 401 | Tidak terautentikasi | Token tidak ada / kedaluwarsa / sudah di daftar hitam |
| 403 | Tanpa izin / pemblokiran keamanan | Izin RBAC tidak cukup / terkena SecurityFilter |
| 404 | Sumber daya tidak ada | Target kueri/perbarui/hapus tidak ada |
| 405 | Metode permintaan tidak diizinkan | Hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD, metode non-standar langsung ditolak |
| 413 | Body permintaan terlalu besar | Content-Length melebihi 10MB |
| 415 | Tipe media tidak didukung | Content-Type permintaan POST/PUT bukan JSON dan bukan unggah file |
| 422 | Validasi parameter gagal | Field wajib hilang, format tidak sesuai, validasi bisnis tidak lolos |
| 429 | Permintaan terlalu sering | Dipicu RateLimit / penguncian akun (5 kali gagal login berturut-turut dikunci 15 menit) |
| 500 | Kesalahan internal server | |

## 3. Endpoint Publik

Semua endpoint publik dipasang di bawah grup `/api`, didistribusikan oleh middleware `ApiVersion` sesuai header `API-Version` ke controller ber-versioning yang sesuai (misalnya `app\api\v1\controller\AuthController`).

### 3.1 Health Check

```
GET /health
```

- **Autentikasi**: Tidak perlu
- **Rate limit**: Tidak ada

**Contoh respons**:
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

Nilai `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` mengembalikan `"unavailable"` saat ES tidak dapat dijangkau, jika status kesehatan cluster bukan green/yellow mengembalikan nilai status aktual (misalnya `"red"`).

### 3.2 Dokumen API

```
GET /api/docs
```

- **Autentikasi**: Tidak perlu
- **Rate limit**: Default global (60 kali/menit)
- **Respons**: Spesifikasi OpenAPI 3.0.3 JSON, berisi semua definisi endpoint, parameter, dan Schema

### 3.3 Membuat Captcha Klik

```
POST /api/captcha/generate
```

- **Autentikasi**: Tidak perlu
- **Header**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "difficulty": "medium"
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| difficulty | string | Tidak | `easy` / `medium` / `hard`, default `medium` |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "Silakan klik A" },
        { "order": 2, "text": "Silakan klik B" }
      ]
    }
  }
}
```

| Field | Tipe | Keterangan |
|------|------|------|
| key | string | Identitas captcha, dikirim kembali saat validasi |
| image | string | Gambar PNG ber-encoding base64 |
| extra.targets[].order | int | Urutan klik |
| extra.targets[].text | string | Teks petunjuk target klik |

### 3.4 Memverifikasi Captcha Klik

```
POST /api/captcha/verify
```

- **Autentikasi**: Tidak perlu
- **Header**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| key | string | Ya | Kunci captcha, dikembalikan oleh generate |
| clicks | array{object} | Ya | Array koordinat klik, setiap elemen berisi `x` (int) dan `y` (int) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Verifikasi berhasil",
  "data": { "valid": true }
}
```

Saat verifikasi gagal, `code` adalah 422, `message` adalah `"Verifikasi gagal, silakan coba lagi"`, `data.valid` adalah `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autentikasi**: Tidak perlu
- **Header**: `API-Version: v1` (wajib)
- **Rate limit**: 10 kali/menit (berdasarkan IP + path)

**Body permintaan**:
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

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna |
| password | string | Ya | min:6, max:32 | Kata sandi |
| captcha_key | string | Ya | | Kunci captcha |
| clicks | array{object} | Ya | min:2 | Array koordinat klik |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Login berhasil",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "Administrator"
    }
  }
}
```

| Field | Tipe | Keterangan |
|------|------|------|
| access_token | string | Token akses JWT |
| refresh_token | string | Token refresh JWT |
| expires_in | int | Masa berlaku token akses (detik), default 7200 |
| user.id | string | ID pengguna terenkripsi hashid |
| user.username | string | Nama pengguna |
| user.real_name | string | Nama asli |

**Kemungkinan kesalahan**:
- 422: Validasi parameter gagal (field wajib hilang, format tidak sesuai)
- 422: Kode captcha salah, silakan coba lagi
- 401: Nama pengguna atau kata sandi salah
- 403: Akun telah dinonaktifkan
- 429: Akun telah dikunci, silakan coba lagi setelah 15 menit (dipicu 5 kali gagal login berturut-turut)

### 3.6 Registrasi

```
POST /api/auth/register
```

- **Autentikasi**: Tidak perlu
- **Header**: `API-Version: v1` (wajib)
- **Rate limit**: 5 kali/menit (berdasarkan IP + path)
- **Sakelar**: default nonaktif (`REGISTRATION_ENABLED=0`), saat nonaktif mengembalikan 403; perlu diaktifkan secara eksplisit di `.env` (`REGISTRATION_ENABLED=1`)

**Body permintaan**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "Pengguna baru",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 | Kata sandi (disimpan hash bcrypt) |
| real_name | string | Ya | max:50 | Nama asli |
| captcha_key | string | Ya | | Kunci captcha |
| clicks | array{object} | Ya | min:2 | Array koordinat klik |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Registrasi berhasil",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "Pengguna baru"
    }
  }
}
```

Setelah registrasi berhasil langsung mengembalikan token JWT, status pengguna default aktif (status=1). Endpoint ini hanya tersedia saat `REGISTRATION_ENABLED=1`.

### 3.7 Refresh Token

```
POST /api/auth/refresh
```

- **Autentikasi**: Tidak perlu
- **Header**: `API-Version: v1` (wajib)
- **Rate limit**: Default global (60 kali/menit)

**Body permintaan**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| refresh_token | string | Ya | refresh_token yang diperoleh saat login/registrasi |

**Contoh respons**:
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

Refresh berhasil mengembalikan access_token dan refresh_token baru secara bersamaan, token lama otomatis tidak berlaku. Saat refresh, waktu login terakhir dan IP pengguna diperbarui.

**Kemungkinan kesalahan**:
- 422: Token refresh hilang
- 401: Token refresh tidak valid atau kedaluwarsa

### 3.8 Metrik Pemantauan Prometheus

```
GET /metrics
```

- **Autentikasi**: Tidak perlu
- **Rate limit**: Tidak ada
- **Format respons**: Prometheus text format (`text/plain; version=0.0.4`)

Mengekspos endpoint metrik pemantauan Prometheus publik, untuk di-scrape Grafana/Prometheus.

**Contoh respons**:
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

| Nama Metrik | Tipe | Keterangan |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Total kumulatif permintaan HTTP |
| `openadmin_active_users` | gauge | Jumlah pengguna aktif saat ini (login dalam 24 jam) |
| `openadmin_db_connection_status` | gauge | Status koneksi database, 1=normal, 0=abnormal |
| `openadmin_redis_connection_status` | gauge | Status koneksi Redis, 1=normal, 0=abnormal |
| `openadmin_memory_usage_bytes` | gauge | Penggunaan memori proses PHP saat ini (bytes) |

## 4. Dasbor

Semua antarmuka admin dipasang di bawah grup `/admin`, melalui tiga middleware `AdminAuth` (autentikasi JWT), `AdminPermission` (validasi izin RBAC), `OperationLog` (pencatatan operasi).

### 4.1 Data Dasbor

```
GET /admin/dashboard
```

- **Autentikasi**: JWT + RBAC
- **Cache**: Redis 5 menit

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "Total Pengguna",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "Baru Hari Ini",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "Pengguna Aktif",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "Log Operasi",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "Total Pengguna", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "Log Operasi", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "Aktif", "value": 1250 },
        { "name": "Nonaktif", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "Login pengguna",
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

| Field stats | Tipe | Keterangan |
|------|------|------|
| label | string | Nama metrik |
| value | string | Nilai metrik (tipe string) |
| icon | string | Nama ikon Material |
| color | string | Nilai warna kartu |
| trend | float? | Tingkat pertumbuhan harian (persen), hanya "Total Pengguna" yang memiliki field ini |

| Field trends | Tipe | Keterangan |
|------|------|------|
| dates | array{string} | Urutan tanggal 30 hari terakhir |
| series | array{object} | Data garis tren, setiap baris berisi name (nama), data (array nilai), color (warna) |

## 5. Manajemen Pengguna

Semua `id` yang dikembalikan antarmuka manajemen pengguna adalah string terenkripsi hashid. Field kata sandi sudah dikecualikan dari respons. Nomor ponsel dan email di-masking di antarmuka daftar, dikembalikan plaintext di antarmuka detail (field enkripsi database didekripsi otomatis oleh trait Encryptable).

### 5.1 Daftar Pengguna

```
GET /admin/user
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| keyword | string | Tidak | | Kata kunci pencarian, cocok dengan nama pengguna dan nama asli |
| status | int | Tidak | | Filter status, 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "Administrator",
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

| Field | Tipe | Keterangan |
|------|------|------|
| id | string | ID pengguna terenkripsi hashid |
| username | string | Nama pengguna |
| real_name | string | Nama asli |
| phone | string | Nomor ponsel ter-masking (format `138****5678`) |
| email | string | Email ter-masking (format `a***@example.com`) |
| status | int | 1=aktif, 0=nonaktif |
| last_login_at | string | Waktu login terakhir (datetime) |
| created_at | string | Waktu pembuatan (datetime) |

### 5.2 Membuat Pengguna

```
POST /admin/user
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "Pengguna baru",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| username | string | Ya | min:3, max:50 | Nama pengguna (unik) |
| password | string | Ya | min:6, max:32 | Kata sandi (disimpan bcrypt) |
| real_name | string | Ya | max:50 | Nama asli |
| phone | string | Tidak | | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | | Email (disimpan terenkripsi Encryptable) |
| status | int | Tidak | in:0,1 | Status, default 1 (aktif) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembuatan berhasil",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "Pengguna baru",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Kemungkinan kesalahan**:
- 422: Nama pengguna sudah ada
- 422: Validasi parameter gagal (field wajib hilang)

### 5.3 Detail Pengguna

```
GET /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Administrator",
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

Di antarmuka detail, `phone` dan `email` dikembalikan plaintext (di database disimpan terenkripsi, cast Encryptable mendekripsi otomatis), tidak di-masking. `password` dan `id_card` selalu tidak ada dalam respons.

**Kemungkinan kesalahan**:
- 404: Pengguna tidak ada

### 5.4 Memperbarui Pengguna

```
PUT /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid

**Body permintaan**:
```json
{
  "real_name": "Nama baru",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| real_name | string | Tidak | Nama asli, tidak dikirim tetap mempertahankan nilai asli |
| password | string | Tidak | Kata sandi baru, string kosong atau tidak dikirim berarti tidak mengubah |
| phone | string | Tidak | Nomor ponsel |
| email | string | Tidak | Email |
| status | int | Tidak | 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembaruan berhasil",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Nama baru",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Kemungkinan kesalahan**:
- 404: Pengguna tidak ada

### 5.5 Menghapus Pengguna

```
DELETE /admin/user/{id}
```

- **Autentikasi**: JWT + RBAC
- **Parameter path**: `{id}` adalah ID pengguna terenkripsi hashid
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Penghapusan berhasil",
  "data": []
}
```

Melakukan soft delete (Eloquent SoftDeletes), data ditandai deleted_at tanpa penghapusan fisik.

**Kemungkinan kesalahan**:
- 404: Pengguna tidak ada
- 422: Operasi sensitif memerlukan input kata sandi untuk konfirmasi (password kosong)
- 422: Verifikasi kata sandi gagal (kata sandi tidak cocok)

### 5.6 Menghapus Pengguna Massal

```
POST /admin/user/batch/destroy
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| password | string | Ya | Kata sandi pengguna yang sedang login (konfirmasi ulang) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Penghapusan berhasil",
  "data": {
    "count": 2
  }
}
```

Melakukan soft delete, `data.count` adalah jumlah yang benar-benar dihapus.

**Kemungkinan kesalahan**:
- 422: Silakan pilih pengguna yang akan dihapus (ids kosong)
- 422: ID tidak valid (dekode hashid gagal)
- 422: Verifikasi kata sandi gagal

### 5.7 Mengaktifkan/Nonaktifkan Pengguna Massal

```
POST /admin/user/batch/status
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| ids | array{string} | Ya | Array ID pengguna terenkripsi hashid |
| status | int | Ya | 0=nonaktif, 1=aktif |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pengaktifan massal berhasil",
  "data": {
    "count": 2
  }
}
```

message berubah dinamis sesuai nilai status menjadi `"Pengaktifan massal berhasil"` atau `"Penonaktifan massal berhasil"`.

**Kemungkinan kesalahan**:
- 422: Silakan pilih pengguna (ids kosong)
- 422: Nilai status tidak valid (status bukan 0 atau 1)

## 6. Manajemen Peran

### 6.1 Daftar Peran

```
GET /admin/role
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "Super Admin",
        "slug": "super_admin",
        "description": "Memiliki semua izin",
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

| Field | Tipe | Keterangan |
|------|------|------|
| id | string | ID peran terenkripsi hashid |
| name | string | Nama peran |
| slug | string | Identitas peran (unik, digunakan untuk penentuan izin) |
| description | string | Deskripsi peran |
| status | int | 1=aktif, 0=nonaktif |
| users_count | int | Jumlah pengguna yang memiliki peran ini |

### 6.2 Membuat Peran

```
POST /admin/role
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "Editor",
  "slug": "editor",
  "description": "Peran editor konten",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| name | string | Ya | max:50 | Nama peran |
| slug | string | Ya | max:50 | Identitas peran |
| description | string | Tidak | | Deskripsi peran, default string kosong |
| status | int | Tidak | | Status, default 1 |
| permission_ids | array{int} | Tidak | | Array ID izin (ID INT asli, bukan hashid) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembuatan berhasil",
  "data": {
    "id": "r5r6r7r8",
    "name": "Editor",
    "slug": "editor",
    "description": "Peran editor konten",
    "status": 1
  }
}
```

### 6.3 Memperbarui Peran

```
PUT /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "Editor Konten",
  "description": "Deskripsi setelah pembaruan",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| name | string | Tidak | Nama peran |
| description | string | Tidak | Deskripsi |
| status | int | Tidak | 0=nonaktif, 1=aktif |
| permission_ids | array{int} | Tidak | Array ID izin, jika dikirim maka disinkronkan (menimpa) izin peran |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembaruan berhasil",
  "data": {
    "id": "r5r6r7r8",
    "name": "Editor Konten",
    "slug": "editor",
    "description": "Deskripsi setelah pembaruan",
    "status": 1
  }
}
```

### 6.4 Menghapus Peran

```
DELETE /admin/role/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Penghapusan berhasil",
  "data": []
}
```

Saat menghapus, secara otomatis melepas hubungan peran dengan semua izin dan pengguna, lalu menghapus fisik catatan peran.

## 7. Manajemen Izin

Izin menggunakan struktur pohon (parent_id self-referencing), dibagi menjadi tiga jenis. Antarmuka daftar mengembalikan pohon izin lengkap.

### 7.1 Pohon Izin

```
GET /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "Manajemen Pengguna",
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
          "name": "Daftar Pengguna",
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

| Field | Tipe | Keterangan |
|------|------|------|
| id | string | Terenkripsi hashid |
| parent_id | string | hashid izin induk, "0" berarti node akar |
| name | string | Nama izin |
| slug | string | Identitas izin (identitas route/tombol) |
| type | int | 1=menu, 2=tombol, 3=antarmuka |
| icon | string | Ikon menu (nama ikon Material) |
| path | string | Path route frontend |
| sort | int | Nilai urutan (ascending) |
| children | array? | Daftar sub-izin (rekursif), tanpa field ini saat tidak ada node anak |

### 7.2 Membuat Izin

```
POST /admin/permission
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "parent_id": 0,
  "name": "Pengaturan Sistem",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| parent_id | int | Tidak | | ID izin induk (tipe INT asli), default 0 |
| name | string | Ya | max:50 | Nama izin |
| slug | string | Ya | max:100 | Identitas izin |
| type | int | Ya | in:1,2,3 | 1=menu, 2=tombol, 3=antarmuka |
| icon | string | Tidak | | Ikon menu, default kosong |
| path | string | Tidak | | Path route frontend, default kosong |
| sort | int | Tidak | | Nilai urutan, default 0 |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembuatan berhasil",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "Pengaturan Sistem",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Memperbarui Izin

```
PUT /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "name": "Konfigurasi Sistem",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| name | string | Tidak | Nama izin |
| icon | string | Tidak | Ikon |
| path | string | Tidak | Path route |
| sort | int | Tidak | Nilai urutan |

### 7.4 Menghapus Izin

```
DELETE /admin/permission/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Penghapusan berhasil",
  "data": []
}
```

Saat menghapus, menghapus kaskade semua sub-izin (catatan dengan `parent_id` = ID izin saat ini), sekaligus melepas hubungan dengan semua peran.

## 8. Konfigurasi Sistem

Konfigurasi sistem unik dengan kombinasi `group` + `key`.

### 8.1 Daftar Konfigurasi

```
GET /admin/config
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| group | string | Tidak | | Filter berdasarkan grup konfigurasi |

**Contoh respons**:
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
        "value": "Sistem Manajemen Terbuka",
        "type": "string",
        "description": "Nama situs",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Field | Tipe | Keterangan |
|------|------|------|
| id | string | hashid |
| group | string | Grup konfigurasi (misalnya `system`, `email`, `storage`) |
| key | string | Kunci konfigurasi |
| value | string | Nilai konfigurasi |
| type | string | Petunjuk tipe nilai (`string`, `integer`, `boolean`, `json`, dll.) |
| description | string | Keterangan konfigurasi |

### 8.2 Membuat Konfigurasi

```
POST /admin/config
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "Alamat server SMTP"
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| group | string | Ya | max:100 | Grup konfigurasi |
| key | string | Ya | max:100 | Kunci konfigurasi (unik dalam grup yang sama) |
| value | string | Ya | | Nilai konfigurasi |
| type | string | Tidak | | Tipe nilai, default `string` |
| description | string | Tidak | | Keterangan konfigurasi, default kosong |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembuatan berhasil",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "Alamat server SMTP"
  }
}
```

**Kemungkinan kesalahan**:
- 422: Item konfigurasi sudah ada (group + key yang sama)

### 8.3 Memperbarui Konfigurasi

```
PUT /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC

**Body permintaan**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "Alamat SMTP setelah pembaruan"
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| value | string | Tidak | Memperbarui nilai konfigurasi |
| type | string | Tidak | Memperbarui tipe nilai |
| description | string | Tidak | Memperbarui teks keterangan |

### 8.4 Menghapus Konfigurasi

```
DELETE /admin/config/{id}
```

- **Autentikasi**: JWT + RBAC
- **Operasi sensitif**: memerlukan konfirmasi ulang kata sandi

**Body permintaan**:
```json
{
  "password": "admin_password"
}
```

Menghapus fisik catatan konfigurasi.

## 9. Log Operasi

Log operasi adalah antarmuka read-only, ditulis otomatis oleh middleware `OperationLog` pada setiap permintaan POST/PUT/DELETE, field penyimpanan meliputi `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Daftar Log Operasi

```
GET /admin/log
```

- **Autentikasi**: JWT + RBAC

**Parameter kueri**:

| Parameter | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| page | int | Tidak | 1 | Nomor halaman |
| limit | int | Tidak | 15 | Jumlah per halaman |
| user_id | int | Tidak | | Filter presisi berdasarkan ID pengguna (tipe INT asli) |
| action | string | Tidak | | Filter presisi berdasarkan aksi operasi |
| path | string | Tidak | | Filter fuzzy berdasarkan path permintaan |
| start_date | string | Tidak | | Tanggal mulai (format Y-m-d) |
| end_date | string | Tidak | | Tanggal selesai (format Y-m-d) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "Login pengguna",
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

| Field | Tipe | Keterangan |
|------|------|------|
| id | string | hashid |
| user_name | string | Nama pengguna operasi (diperoleh melalui relasi user, operasi tanpa login menampilkan "Sistem") |
| action | string | Deskripsi aksi operasi |
| method | string | Metode HTTP (POST/PUT/DELETE) |
| path | string | Path permintaan |
| ip | string | IP klien |
| source | string | Sumber permintaan |
| input | string | String JSON parameter permintaan (tidak termasuk file) |
| created_at | string | Waktu operasi (datetime) |

## 10. Pusat Pribadi

Antarmuka pusat pribadi hanya memerlukan autentikasi JWT (tidak memerlukan validasi izin RBAC — middleware `AdminPermission` harus menambahkannya ke daftar putih).

### 10.1 Memperbarui Informasi Pribadi

```
PUT /admin/profile
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "real_name": "Nama baru",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| real_name | string | Tidak | Nama asli |
| phone | string | Tidak | Nomor ponsel (disimpan terenkripsi Encryptable) |
| email | string | Tidak | Email (disimpan terenkripsi Encryptable) |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Pembaruan berhasil",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Nama baru",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Dalam respons, `phone` dan `email` dikembalikan plaintext, `password` dan `id_card` sudah dihilangkan.

### 10.2 Mengubah Kata Sandi

```
PUT /admin/profile/password
```

- **Autentikasi**: JWT

**Body permintaan**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Field | Tipe | Wajib | Aturan Validasi | Keterangan |
|------|------|------|---------|------|
| old_password | string | Ya | | Kata sandi saat ini |
| new_password | string | Ya | min:6, max:32 | Kata sandi baru |

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Kata sandi berhasil diubah",
  "data": []
}
```

**Kemungkinan kesalahan**:
- 422: Silakan isi kata sandi lama dan kata sandi baru
- 422: Kata sandi lama salah
- 422: Panjang kata sandi baru 6-32 karakter

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autentikasi**: JWT

**Body permintaan**: Tidak ada (tanpa requestBody, token dibaca dari header Authorization)

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Sudah logout",
  "data": []
}
```

Logika logout: dekode JWT untuk mendapatkan sisa masa berlaku (exp - now), tulis hash md5 token tersebut ke daftar hitam Redis `jwt_blacklist:{md5}`, TTL = sisa masa berlaku. Token dalam daftar hitam di-blokir di middleware `AdminAuth`, mengembalikan 401.

Saat tidak ada token mengembalikan 401. Saat token kedaluwarsa/tidak valid (pengecualian saat dekode) tetap dianggap logout berhasil.

## 11. Impor dan Ekspor

### 11.1 Ekspor Excel

```
POST /admin/export/excel
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Body permintaan**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "Ekspor Daftar Pengguna"
}
```

| Field | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| table | string | Tidak | `admin_user` | Nama tabel ekspor. Mendukung: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Tidak | | Array nama field kolom ekspor, kosong berarti mengekspor semua kolom tabel tersebut |
| conditions | object | Tidak | `{}` | Kondisi filter, pasangan key-value, saat nilai tidak kosong digunakan untuk WHERE |
| title | string | Tidak | `Ekspor Data` | Judul Excel (ditampilkan sebagai nama Sheet) |

**Tabel dan kolom yang didukung**:

| table | Kolom yang tersedia |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Field sensitif `phone`, `email`, `id_card` diproses masking otomatis saat ekspor. Batas data 10000 baris. Baris pertama Excel dibekukan, filter otomatis.

### 11.2 Ekspor PDF

```
POST /admin/export/pdf
```

- **Autentikasi**: JWT + RBAC
- **Tipe respons**: Unduhan file (`application/pdf`, A4 landscape)

**Body permintaan**:
```json
{
  "type": "dashboard",
  "title": "Laporan Dasbor Manajemen",
  "data": {
    "stats": [
      { "label": "Total Pengguna", "value": "1280" }
    ]
  }
}
```

Atau mode tabel:
```json
{
  "type": "table",
  "title": "Daftar Pengguna",
  "data": {
    "columns": ["Nama Pengguna", "Nama Asli", "Status"],
    "rows": [
      ["admin", "Administrator", "Aktif"],
      ["editor", "Editor", "Aktif"]
    ]
  }
}
```

| Field | Tipe | Wajib | Nilai Default | Keterangan |
|------|------|------|------|------|
| type | string | Tidak | `table` | Tipe ekspor: `table` / `dashboard` |
| title | string | Tidak | `Ekspor Data` | Judul PDF |
| data | object | Tidak | `{}` | Data ekspor |

Saat `type=dashboard`, `data` harus berisi array `stats` (dirender sebagai kartu); saat `type=table`, `data` harus berisi array `columns` dan `rows`.

Template PDF berisi informasi hak cipta dan timestamp ekspor.

### 11.3 Impor Pengguna (Excel)

```
POST /admin/import/users
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data` (unggah file)

**Field form**:

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| file | file | Ya | Format `.xlsx` atau `.xls` |

**Persyaratan kolom Excel**:

| Nama Kolom | Wajib | Keterangan |
|------|------|------|
| username | Ya | Nama pengguna (unik) |
| password | Ya | Kata sandi (disimpan hash bcrypt) |
| real_name | Ya | Nama asli |
| phone | Tidak | Nomor ponsel |
| email | Tidak | Email |
| status | Tidak | Status, default 1 |

Baris ke-1 adalah judul kolom (tidak case-sensitive), baris ke-2 dan seterusnya adalah data.

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Impor selesai",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "Nama pengguna kosong" },
      { "row": 7, "reason": "Nama pengguna zhangsan sudah ada" }
    ]
  }
}
```

| Field | Tipe | Keterangan |
|------|------|------|
| total | int | Jumlah total baris (tidak termasuk baris judul) |
| success | int | Jumlah berhasil diimpor |
| failed | int | Jumlah gagal |
| errors | array | Detail kegagalan, setiap baris berisi row (nomor baris Excel) dan reason (alasan kegagalan) |

## 12. Unggah File

```
POST /admin/upload
```

- **Autentikasi**: JWT + RBAC
- **Tipe permintaan**: `multipart/form-data`

**Field form**:

| Field | Tipe | Wajib | Keterangan |
|------|------|------|------|
| file | file | Ya | File yang diunggah |

**Tipe file yang diizinkan**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Ukuran file maksimum**: 10MB

**Contoh respons**:
```json
{
  "code": 0,
  "message": "Unggah berhasil",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

File disimpan di `public/upload/{Y-m-d}/` dalam direktori berdasarkan tanggal, nama file adalah `md5(uniqid) + ekstensi asli`. `url` adalah path relatif terhadap root situs.

**Kemungkinan kesalahan**:
- 422: Silakan pilih file (belum diunggah)
- 422: Tipe file tidak didukung
- 422: Ukuran file tidak boleh melebihi 10MB
- 500: Gagal mengunggah file (file tidak valid)

## 13. Header Respons

Semua antarmuka (diinjeksi di lapisan middleware global) berisi header respons berikut:

| Header | Keterangan |
|----|------|
| `X-RateLimit-Limit` | Batas atas rate limit (jumlah) |
| `X-RateLimit-Remaining` | Sisa jumlah permintaan |
| `X-RateLimit-Reset` | Timestamp reset jendela rate limit |
| `Retry-After` | Hanya dikembalikan saat rate limit terpicu, detik tunggu yang disarankan |
| `X-Content-Type-Options` | `nosniff` (default webman, melarang MIME sniffing) |
| `X-Frame-Options` | `DENY` (disediakan middleware CORS/konfigurasi dasar webman) |

Detail rate limit:
- Batas global default: 60 kali/menit / IP+path
- Endpoint login `/api/auth/login`: 10 kali/menit
- Endpoint registrasi `/api/auth/register`: 5 kali/menit
- Menggunakan algoritma sliding window Redis atomik (Lua ZSET), menghindari race condition TOCTOU
- Saat Redis tidak tersedia, fail open (izinkan), tidak memblokir permintaan

## 14. Alur Autentikasi

Urutan autentikasi lengkap:

```
1. Klien meminta POST /api/captcha/generate
   (Header: API-Version: v1)
    ↓
   Server mengembalikan: key + gambar base64 + petunjuk target klik
   
2. Pengguna mengklik posisi target gambar, frontend/klien mengumpulkan koordinat klik
   
3. Klien meminta POST /api/auth/login
   (Header: API-Version: v1, Content-Type: application/json)
   Body: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Server:
   a. Validasi parameter → 422
   b. Validasi captcha → 422
   c. Validasi kredensial pengguna → 401
   d. Periksa status akun → 403
   e. Terbitkan JWT (access + refresh) → 200
   f. Perbarui last_login_at / last_login_ip
    ↓
   Klien menyimpan: access_token, refresh_token, expires_in

4. Permintaan berikutnya membawa JWT
   Header: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Ekstrak Bearer token
   b. Periksa daftar hitam (Redis jwt_blacklist:{md5}) → 401
   c. Dekode JWT, validasi kedaluwarsa → 401
   d. Atur $request->adminId = field sub
    ↓
   Middleware AdminPermission:
   a. Parse identitas izin untuk route sumber daya
   b. Kueri peran pengguna → izin peran, lakukan pencocokan
   c. Tanpa izin → 403
    ↓
   Controller memproses permintaan
    ↓
   Response + header X-RateLimit-*

5. Refresh sebelum Access Token kedaluwarsa
   Klien meminta POST /api/auth/refresh
   Body: { refresh_token: "..." }
    ↓
   Server mendekode refresh_token → terbitkan access + refresh baru
    ↓
   Klien memperbarui token lokal

6. Logout
   Klien meminta POST /admin/profile/logout
   Header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. Dekode JWT untuk mendapatkan sisa TTL
   b. Tulis daftar hitam Redis: jwt_blacklist:{md5(token)} = 1, TTL = sisa masa berlaku
   c. Kembalikan sukses
```

### Struktur JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, default TTL 7200 detik (dikontrol `default_expire` konfigurasi JWT)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, default TTL 1209600 detik (dikontrol `refresh_expire` konfigurasi JWT, yaitu 14 hari)

### Manajemen Keamanan

- Kata sandi disimpan dengan hash `PASSWORD_BCRYPT`
- Field sensitif (phone, email, id_card) menggunakan `erikwang2013/encryptable` untuk enkripsi/dekripsi transparan di lapisan database
- ID di lapisan API dienkripsi untuk transmisi menggunakan `erikwang2013/hashids`, menghindari ekspos urutan ID snowflake asli
- SecurityFilter memindai global XSS, injeksi SQL, path traversal, injeksi perintah, IP yang sama 5 kali/60 detik masuk daftar hitam sementara 15 menit
- Operasi sensitif (menghapus pengguna, peran, izin, konfigurasi) memerlukan konfirmasi ulang kata sandi pengguna yang sedang login
- Batas sesi bersamaan: pengguna yang sama maksimal 3 Token valid, saat perangkat ke-4 login Token paling lama dipaksa masuk daftar hitam
- Penguncian akun: 5 kali gagal login berturut-turut memicu penguncian akun 15 menit, selama penguncian mengembalikan 429

## 15. Deployment dan Operasi

### Docker Compose

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan (Nginx, aplikasi webman, MySQL, Redis, Elasticsearch). PHP dibangun melalui `Dockerfile` (berbasis `php:8.3-cli`, dengan OPcache diaktifkan).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` mendefinisikan pipeline integrasi berkelanjutan GitHub Actions:
- Pemeriksaan sintaks `php -l`
- Pengujian unit PHPUnit
- Analisis statis `flutter analyze`

### Backup Database

Direktori `database/backup/` menyediakan skrip backup dan restore:
- `backup.sh` — backup kompresi mysqldump + gzip, otomatis membersihkan file backup lama lebih dari 30 hari
- `restore.sh` — restore interaktif, menampilkan backup yang ada untuk dipilih pengguna

### Konfigurasi Keamanan Nginx

Untuk deployment lingkungan produksi, lihat `nginx-security.conf` untuk konfigurasi penguatan keamanan reverse proxy.

## 16. Endpoint API Bisnis (ERP)

Semua endpoint bisnis berada di bawah grup `/admin`, melalui tiga middleware `AdminAuth` (autentikasi JWT), `AdminPermission` (validasi izin RBAC), `OperationLog` (pencatatan operasi).

> Total endpoint: Produk(17) | Pembelian(8) | Penjualan(6) | Stok(6) | Keuangan(17) | CRM(13) | Alur kerja(6) | Notifikasi(4) | Proyek(3) | HR(9) | Manufaktur(7) | Laporan(4) | Dasbor(3) | Klien(2) | Total 105 endpoint

Endpoint linkage lintas modul ditandai dengan 🔗.

### 16.1 Manajemen Produk (Product Management)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/product | Daftar produk (paginasi+penelusuran+filter kategori/status) |
| POST | /admin/product | Membuat produk (termasuk SKU dan harga) |
| GET | /admin/product/{id} | Detail produk (termasuk kategori/merek/SKU/harga/satuan) |
| PUT | /admin/product/{id} | Memperbarui produk |
| DELETE | /admin/product/{id} | Menghapus produk (soft delete, perlu konfirmasi kata sandi) |
| GET | /admin/category | Daftar kategori (pohon) |
| POST | /admin/category | Membuat kategori |
| PUT | /admin/category/{id} | Memperbarui kategori |
| DELETE | /admin/category/{id} | Menghapus kategori |
| GET | /admin/brand | Daftar merek |
| POST | /admin/brand | Membuat merek |
| GET | /admin/warehouse | Daftar gudang |
| POST | /admin/warehouse | Membuat gudang |
| GET | /admin/location | Daftar lokasi |
| GET | /admin/warehouse/{id}/locations | Daftar lokasi di bawah gudang |
| GET | /admin/supplier | Daftar pemasok (pencarian ES) |
| POST | /admin/supplier | Membuat pemasok |
| GET | /admin/customer | Daftar pelanggan (pencarian ES) |
| POST | /admin/customer | Membuat pelanggan |

### 16.2 Manajemen Pembelian (Purchase)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/purchase/apply | Daftar permintaan pembelian |
| POST | /admin/purchase/apply | Membuat permintaan pembelian |
| GET | /admin/purchase/order | Daftar pesanan pembelian |
| POST | /admin/purchase/order | Membuat pesanan pembelian |
| 🔗 POST | /admin/purchase/receive | Membuat surat penerimaan (otomatis masuk gudang + membuat utang) |
| GET | /admin/purchase/receive | Daftar surat penerimaan |
| GET | /admin/purchase/receive/{id} | Detail surat penerimaan |
| POST | /admin/purchase/return | Membuat surat retur |
| GET | /admin/purchase/settlement | Daftar penyelesaian pemasok |

### 16.3 Manajemen Penjualan (Sales)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/sales/quotation | Daftar surat penawaran |
| POST | /admin/sales/quotation | Membuat surat penawaran |
| GET | /admin/sales/order | Daftar pesanan penjualan |
| POST | /admin/sales/order | Membuat pesanan penjualan |
| 🔗 POST | /admin/sales/delivery | Membuat surat pengiriman (otomatis keluar gudang + membuat piutang) |
| GET | /admin/sales/delivery | Daftar surat pengiriman |
| GET | /admin/sales/settlement | Daftar penyelesaian pelanggan |

### 16.4 Manajemen Stok (Inventory)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/inventory | Stok real-time (dimensi gudang/lokasi/batch/SKU) |
| GET | /admin/inventory/flow | Transaksi in/out stok |
| GET | /admin/inventory/transfer | Daftar surat transfer |
| POST | /admin/inventory/transfer | Membuat surat transfer |
| GET | /admin/inventory/check | Daftar tugas stok opname |
| POST | /admin/inventory/check | Membuat tugas stok opname |
| GET | /admin/inventory/alert | Aturan peringatan stok |

### 16.5 Manajemen Keuangan (Finance)

| Metode | Path | Keterangan |
|------|------|------|
| POST | /admin/finance/voucher | Membuat voucher pembukuan |
| GET | /admin/finance/ar-ap | Daftar piutang-hutang |
| POST | /admin/finance/receipt | Membuat surat penerimaan |
| POST | /admin/finance/payment | Membuat surat pembayaran |
| GET | /admin/finance/cash-journal | Jurnal kas & bank |
| GET | /admin/finance/expense | Daftar reimbursement biaya |
| POST | /admin/finance/expense | Submit permohonan reimbursement |
| GET | /admin/finance/report/profit | Laporan laba rugi |
| GET | /admin/finance/general-ledger | Buku besar (rekap berdasarkan akun+periode) |
| GET | /admin/finance/subsidiary-ledger | Buku pembantu (detail per transaksi akun) |
| GET | /admin/finance/report/balance-sheet | Neraca (termasuk pembuatan otomatis) |
| GET | /admin/finance/report/cash-flow | Laporan arus kas (operasi/investasi/pendanaan) |
| GET | /admin/finance/bank-account | Daftar akun bank |
| GET/POST/PUT/DELETE | /admin/finance/asset | CRUD aset tetap + penyusutan |
| GET/POST | /admin/finance/tax-rate | Konfigurasi tarif pajak |
| GET | /admin/finance/tax-record | Catatan pajak |
| GET/POST/PUT/DELETE | /admin/finance/currency | Manajemen mata uang |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | Manajemen kurs |
| GET/POST/PUT/DELETE | /admin/finance/budget | Manajemen anggaran (termasuk perbandingan anggaran vs aktual) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | Pusat biaya (struktur pohon) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | Pusat laba (struktur pohon) |

### 16.6 CRM

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/crm/opportunity | Daftar peluang |
| POST | /admin/crm/opportunity | Membuat peluang |
| GET | /admin/crm/follow | Daftar catatan tindak lanjut |
| POST | /admin/crm/follow | Membuat catatan tindak lanjut |
| GET | /admin/crm/funnel | Konfigurasi tahap corong |
| GET | /admin/crm/contact | Daftar kontak |
| POST | /admin/crm/contact | Membuat kontak |
| GET | /admin/crm/pool | Daftar pelanggan kolam bersama |
| POST | /admin/crm/pool/claim/{id} | Mengambil pelanggan kolam bersama |
| POST | /admin/crm/pool/release/{id} | Melepas pelanggan ke kolam bersama |
| GET/POST | /admin/crm/pool/rules | CRUD aturan kolam bersama |
| GET | /admin/crm/contract | Daftar kontrak |
| POST | /admin/crm/contract | Membuat kontrak |
| GET | /admin/crm/contract/{id} | Detail kontrak |
| PUT | /admin/crm/contract/{id} | Memperbarui kontrak |
| DELETE | /admin/crm/contract/{id} | Menghapus kontrak |
| GET | /admin/crm/quotation | Daftar penawaran CRM |
| POST | /admin/crm/quotation | Membuat penawaran CRM |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 Penawaran jadi kontrak |
| GET/POST/PUT/DELETE | /admin/crm/campaign | Kampanye pemasaran |
| GET/POST/PUT/DELETE | /admin/crm/ticket | Tiket layanan |
| POST | /admin/crm/ticket/{id}/assign | Mengalokasikan tiket |
| POST | /admin/crm/ticket/{id}/resolve | Menyelesaikan tiket |
| GET/POST | /admin/crm/analytics/report | Laporan analisis pelanggan |
| GET/POST | /admin/crm/analytics/metric | Metrik analisis |

### 16.7 Alur Persetujuan (Workflow)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/workflow | Daftar definisi alur kerja |
| POST | /admin/workflow | Membuat definisi alur kerja |
| GET | /admin/workflow/{id} | Detail alur kerja |
| PUT | /admin/workflow/{id} | Memperbarui alur kerja |
| DELETE | /admin/workflow/{id} | Menghapus alur kerja |
| POST | /admin/workflow/{id}/submit | 🔗 Submit persetujuan (membuat instansi persetujuan) |
| POST | /admin/approval/{id}/approve | Menyetujui |
| POST | /admin/approval/{id}/reject | Menolak |
| POST | /admin/approval/{id}/withdraw | Menarik |
| ANY | /admin/approval/my | Daftar persetujuan saya (menunggu/disetujui) |

### 16.8 Notifikasi Pesan (Notification)

| Metode | Path | Keterangan |
|------|------|------|
| ANY | /admin/notification/my | Daftar notifikasi saya (paginasi, urutan waktu terbalik) |
| POST | /admin/notification/{id}/read | Menandai satu sudah dibaca |
| POST | /admin/notification/read-all | Menandai semua sudah dibaca |
| ANY | /admin/notification/unread-count | Jumlah pesan belum dibaca |

### 16.9 Manajemen Proyek (Project)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/project | Daftar proyek |
| POST | /admin/project | Membuat proyek |
| GET | /admin/project/{id} | Detail proyek |
| PUT | /admin/project/{id} | Memperbarui proyek |
| DELETE | /admin/project/{id} | Menghapus proyek |
| GET | /admin/project/task | Daftar tugas |
| POST | /admin/project/task | Membuat tugas |
| PUT | /admin/project/task/{id} | Memperbarui tugas |
| DELETE | /admin/project/task/{id} | Menghapus tugas |
| GET | /admin/project/timesheet | Daftar catatan jam kerja |
| POST | /admin/project/timesheet | Mencatat jam kerja |
| PUT | /admin/project/timesheet/{id} | Memperbarui jam kerja |
| DELETE | /admin/project/timesheet/{id} | Menghapus jam kerja |

### 16.10 Manajemen Sumber Daya Manusia (HR)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/hr/department | Daftar departemen (pohon) |
| POST | /admin/hr/department | Membuat departemen |
| PUT | /admin/hr/department/{id} | Memperbarui departemen |
| DELETE | /admin/hr/department/{id} | Menghapus departemen |
| GET | /admin/hr/employee | Daftar karyawan |
| POST | /admin/hr/employee | Membuat karyawan |
| PUT | /admin/hr/employee/{id} | Memperbarui karyawan |
| DELETE | /admin/hr/employee/{id} | Menghapus karyawan |
| GET | /admin/hr/position | Daftar posisi |
| POST | /admin/hr/position | Membuat posisi |
| PUT | /admin/hr/position/{id} | Memperbarui posisi |
| DELETE | /admin/hr/position/{id} | Menghapus posisi |
| ANY | /admin/hr/attendance | Kueri catatan absensi |
| POST | /admin/hr/attendance/clock-in | Clock-in |
| POST | /admin/hr/attendance/clock-out | Clock-out |
| ANY | /admin/hr/leave | Daftar cuti |
| POST | /admin/hr/leave | Submit permohonan cuti |
| GET | /admin/hr/leave/{id} | Detail cuti |
| PUT | /admin/hr/leave/{id} | Memperbarui cuti |
| DELETE | /admin/hr/leave/{id} | Menghapus cuti |
| POST | /admin/hr/leave/{id}/approve | 🔗 Menyetujui cuti |
| GET | /admin/hr/salary | Daftar gaji |
| POST | /admin/hr/salary | Membuat slip gaji |
| PUT | /admin/hr/salary/{id} | Memperbarui gaji |
| DELETE | /admin/hr/salary/{id} | Menghapus gaji |
| POST | /admin/hr/salary/{id}/pay | Membayarkan gaji |
| ANY | /admin/hr/salary-item | Daftar item gaji |
| POST | /admin/hr/salary-item | Membuat item gaji |
| GET | /admin/hr/salary-item/{id} | Detail item gaji |
| PUT | /admin/hr/salary-item/{id} | Memperbarui item gaji |
| DELETE | /admin/hr/salary-item/{id} | Menghapus item gaji |

### 16.11 Manufaktur (Manufacturing)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/mfg/bom | Daftar BOM |
| POST | /admin/mfg/bom | Membuat BOM |
| PUT | /admin/mfg/bom/{id} | Memperbarui BOM |
| DELETE | /admin/mfg/bom/{id} | Menghapus BOM |
| GET | /admin/mfg/production | Daftar pesanan produksi |
| POST | /admin/mfg/production | Membuat pesanan produksi |
| PUT | /admin/mfg/production/{id} | Memperbarui pesanan produksi |
| DELETE | /admin/mfg/production/{id} | Menghapus pesanan produksi |
| POST | /admin/mfg/production/{id}/start | Mulai produksi |
| POST | /admin/mfg/production/{id}/complete | Selesai produksi |
| GET | /admin/mfg/routing | Daftar rute proses |
| POST | /admin/mfg/routing | Membuat rute proses |
| PUT | /admin/mfg/routing/{id} | Memperbarui rute proses |
| DELETE | /admin/mfg/routing/{id} | Menghapus rute proses |
| GET | /admin/mfg/workstation | Daftar stasiun kerja |
| POST | /admin/mfg/workstation | Membuat stasiun kerja |
| PUT | /admin/mfg/workstation/{id} | Memperbarui stasiun kerja |
| DELETE | /admin/mfg/workstation/{id} | Menghapus stasiun kerja |
| GET | /admin/mfg/mrp | Daftar rencana MRP |
| POST | /admin/mfg/mrp | Membuat rencana MRP |
| PUT | /admin/mfg/mrp/{id} | Memperbarui rencana MRP |
| DELETE | /admin/mfg/mrp/{id} | Menghapus rencana MRP |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 Menjalankan MRP untuk menghasilkan saran pembelian/produksi |

### 16.12 Laporan Kustom (Report Builder)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/report | Daftar template laporan |
| POST | /admin/report | Membuat template laporan |
| GET | /admin/report/{id} | Detail template laporan |
| PUT | /admin/report/{id} | Memperbarui template laporan |
| DELETE | /admin/report/{id} | Menghapus template laporan |
| POST | /admin/report/{id}/execute | Mengeksekusi laporan untuk menghasilkan data |
| ANY | /admin/report/{id}/result | Hasil eksekusi laporan |
| GET | /admin/report/schedule | Daftar jadwal terjadwal |
| POST | /admin/report/schedule | Membuat jadwal terjadwal |
| PUT | /admin/report/schedule/{id} | Memperbarui jadwal terjadwal |
| DELETE | /admin/report/schedule/{id} | Menghapus jadwal terjadwal |

### 16.13 Dasbor (Dashboard)

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/dashboard/sales | Papan penjualan |
| GET | /admin/dashboard/inventory | Papan stok |
| GET | /admin/dashboard/finance | Papan keuangan |

### 16.14 API Klien (Client API)

Antarmuka klien dipasang di bawah grup `/api`, memerlukan header `API-Version`. Informasi produk tidak menyertakan harga beli.

| Metode | Path | Keterangan |
|------|------|------|
| GET | /api/product | Daftar produk (tanpa harga beli) |
| GET | /api/product/{hashid} | Detail produk (termasuk harga eceran/grosir, tanpa harga beli) |

### 16.15 Manajemen Pesanan OMS

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/oms/order | Daftar pesanan OMS |
| POST | /admin/oms/order | Membuat pesanan OMS |
| 🔗 POST | /admin/oms/order/{id}/allocate | Alokasi stok (pre-alokasi) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | Membuat pemenuhan |
| POST | /admin/oms/order/{id}/cancel | Membatalkan pesanan (melepas reservasi) |
| POST | /admin/oms/rma/{id}/approve | Menyetujui RMA |
| POST | /admin/oms/rma/{id}/refund | Refund RMA |

### 16.16 Manajemen Gudang WMS

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/wms/zone | Daftar zona (CURD) |
| GET | /admin/wms/location | Daftar lokasi WMS (CRUD) |
| GET | /admin/wms/asn | Daftar ASN (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | Menyelesaikan penerimaan → otomatis membuat tugas putaway |
| POST | /admin/wms/putaway/{id}/complete | Konfirmasi putaway → memicu stockIn |
| POST | /admin/wms/wave/{id}/release | Melepas gelombang → membuat tugas picking |
| POST | /admin/wms/pick/{id}/start | Mulai picking |
| POST | /admin/wms/pick/{id}/confirm | Konfirmasi picking |
| POST | /admin/wms/pack/{id}/complete | Selesai packing |

### 16.17 Manajemen Transportasi TMS

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/tms/carrier | Daftar kurir (CRUD) |
| GET | /admin/tms/service | Layanan kurir (CRUD) |
| GET | /admin/tms/freight-rate | Tarif ongkos kirim (CRUD) |
| GET | /admin/tms/shipment | Daftar resi (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | Konfirmasi pengiriman (stockOut+AR) |
| POST | /admin/tms/tracking/callback | Webhook lacak kurir |
| POST | /admin/tms/freight-invoice/{id}/pay | Pembayaran invoice ongkos kirim (membuat AP) |

### 16.18 Ekstensi Dasbor

| Metode | Path | Keterangan |
|------|------|------|
| GET | /admin/dashboard/oms | KPI OMS (menunggu diproses/dalam picking/pengiriman hari ini/RMA) |
| GET | /admin/dashboard/wms | KPI WMS (menunggu penerimaan/menunggu putaway/menunggu picking/menunggu packing) |
| GET | /admin/dashboard/tms | KPI TMS (menunggu pengiriman/dalam transportasi/sudah diterima/abnormal) |

### 16.19 Keterangan Linkage Lintas Modul

Endpoint berikut memicu linkage otomatis lintas modul, ditandai dengan 🔗:

| Endpoint | Aksi Linkage |
|------|---------|
| 🔗 POST /admin/purchase/receive | Otomatis memanggil InventoryService.stockIn() memperbarui stok + menghitung ulang biaya rata-rata tertimbang bergerak; memanggil FinanceService.createAp() membuat catatan utang |
| 🔗 POST /admin/sales/delivery | Otomatis memanggil InventoryService.stockOut() mengurangi stok (berdasarkan biaya rata-rata tertimbang bergerak); memanggil FinanceService.createAr() membuat catatan piutang |
