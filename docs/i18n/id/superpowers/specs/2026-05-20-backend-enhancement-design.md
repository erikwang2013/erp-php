# Sub-proyek A: Peningkatan Backend — Spesifikasi Desain

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ruang Lingkup

Kali ini adalah peningkatan backend, total 15 poin fitur, melibatkan 9 file baru + 4 file modifikasi.

---

## Daftar File Baru/Modifikasi

```
app/middleware/
├── OperationLog.php          # Baru: pencatatan otomatis log operasi
├── Cors.php                  # Baru: lintas domain
└── RateLimit.php             # Baru: rate limit Redis
app/admin/controller/
├── ConfigController.php      # Baru: CRUD konfigurasi sistem
├── LogController.php         # Baru: kueri log operasi
├── ProfileController.php     # Baru: pusat personal (termasuk logout)
├── UploadController.php      # Baru: upload file
├── ImportController.php      # Baru: impor pengguna Excel
└── HealthController.php      # Baru: health check
app/model/
├── AdminUser.php             # Modifikasi: tambah trait SoftDeletes + Searchable
└── OperationLog.php          # Modifikasi: tambah public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifikasi: validasi blacklist JWT
app/admin/controller/
├── DashboardController.php   # Modifikasi: diubah menjadi statistik database real-time
└── UserController.php        # Modifikasi: tambah aksi batch
config/
└── route.php                 # Modifikasi: tambah rute + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**File**: `app/middleware/Cors.php`

- Permintaan preflight OPTIONS langsung mengembalikan 204
- Permintaan non-preflight menambahkan `Access-Control-Allow-Origin: *` pada header respons
- Header yang diizinkan: `Authorization, Content-Type, API-Version`
- Cache maksimum: 86400 detik

Dipasang: middleware global (`config/middleware.php`)

### 1.2 Middleware Rate Limit

**File**: `app/middleware/RateLimit.php`

- Penyimpanan: Redis Sorted Set sliding window
- Default: 60 kali/menit/IP/route
- Antarmuka sensitif:
  - `/api/auth/login`: 10 kali/menit
  - `/api/auth/register`: 5 kali/menit
- Melebihi batas mengembalikan `429 Too Many Requests`

Dipasang: middleware global (`config/middleware.php`), setelah Cors, sebelum ApiVersion

### 1.3 Middleware Log Operasi

**File**: `app/middleware/OperationLog.php`

- Hanya mencatat POST/PUT/DELETE
- Bidang yang dicatat: user_id, action, method, path, ip, input(JSON)
- Ditulis asinkron setelah respons dikembalikan (tidak memblokir)

Dipasang: grup rute `/admin`, setelah AdminPermission

### 1.4 Rantai Eksekusi Middleware Global

```
Semua permintaan:
  Cors → RateLimit → ApiVersion → {Middleware Rute} → Controller

Permintaan /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (Blacklist JWT)

**File**: `app/middleware/AdminAuth.php` (modifikasi)

**Prinsip**: JWT sendiri stateless; saat logout, token ditambahkan ke blacklist Redis, AdminAuth mengecek blacklist terlebih dahulu saat validasi.

**Perubahan AdminAuth**:
- Awal `process()` ditambahkan: memeriksa apakah token saat ini ada di blacklist dari koleksi Redis `jwt_blacklist`
- Mengenai blacklist mengembalikan 401

**Rute logout** (di bawah pusat personal):

| Metode | Rute | Keterangan |
|------|------|------|
| `POST` | `/admin/profile/logout` | Menambahkan token Bearer saat ini ke blacklist Redis, TTL=sisa masa berlaku token |

**Logika Logout**:
```php
// Parsing sisa masa berlaku token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Tambah ke blacklist
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Controller Baru dan Perubahan yang Ada

### 2.1 CRUD Konfigurasi Sistem (`ConfigController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/config` | Daftar berpaginasi, dapat difilter dengan `group`, paginasi `page`/`limit` |
| `store()` | POST `/admin/config` | Membuat item konfigurasi, wajib: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Memperbarui value/type/description item konfigurasi |
| `destroy()` | DELETE `/admin/config/{id}` | Menghapus item konfigurasi, perlu `confirmPassword()` |

### 2.2 Kueri Log Operasi (`LogController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `index()` | GET `/admin/log` | Daftar berpaginasi, mendukung filter: user_id, action, path, created_at (rentang) |

Tidak menyediakan tambah/hapus/ubah, log dicatat otomatis oleh middleware.

### 2.3 Pusat Personal (`ProfileController`)

Mewarisi `BaseController`. Mengoperasikan pengguna yang sedang login (`$request->adminId`).

| Metode | Rute | Keterangan |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Memperbarui real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Mengubah kata sandi, perlu old_password, new_password, new_password_confirmation |

### 2.4 Upload File (`UploadController`)

Mewarisi `BaseController`.

| Metode | Rute | Keterangan |
|------|------|------|
| `upload()` | POST `/admin/upload` | Menerima file, mendukung image/jpeg/png/gif/pdf/xlsx/docx |

- Maksimum 10MB
- Jalur penyimpanan: `public/upload/{date}/{hash}.{ext}`
- Mengembalikan: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Data Real Dasbor

**File**: `app/admin/controller/DashboardController.php` (modifikasi)

Mengubah data palsu yang saat ini di-hardcode menjadi statistik real-time database:

| Metrik | Sumber | Keterangan |
|------|------|------|
| Total pengguna | `AdminUser::count()` | Tidak termasuk soft delete |
| Baru hari ini | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total peran | `AdminRole::count()` | |
| Total izin | `AdminPermission::count()` | |
| Data tren | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Statistik per hari 7 hari terakhir yang baru ditambahkan |
| Data distribusi | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribusi per status |
| Operasi terbaru | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 10 log operasi terbaru |

### 2.6 Operasi Batch Pengguna

**File**: `app/admin/controller/UserController.php` (modifikasi, tambah metode)

| Metode | Rute | Keterangan |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Hapus batch, body permintaan `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Aktifkan/nonaktifkan batch, body permintaan `{ ids: [hashid, ...], status: 1|0 }` |

- Setiap id dikonversi ke BIGINT dengan `decodeId()` terlebih dahulu
- `batchDestroy()` harus divalidasi dengan `confirmPassword()`

### 2.7 Impor Data

**File**: `app/admin/controller/ImportController.php` (baru)

| Metode | Rute | Keterangan |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload file Excel, membuat pengguna secara batch |

Alur:
1. Menerima file `.xlsx`
2. Parse PhpSpreadsheet, kolom yang diharapkan: `username, password, real_name, phone, email, status`
3. Validasi per baris + buat (ID dibuat snowflake, kata sandi bcrypt, phone/email dienkripsi encryption)
4. Mengembalikan hasil: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health Check

**File**: `app/admin/controller/HealthController.php` (baru)

`GET /health` (tanpa autentikasi, tidak dihitung dalam log operasi):

Mengembalikan status koneksi setiap komponen:
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

- Saat deteksi komponen gagal, nilai bidang terkait adalah string deskripsi error
- Rute tidak memakai prefix `/admin`, didaftarkan terpisah di global

---

## 3. Perbaikan Model

### 3.1 Timestamp OperationLog

**File**: `app/model/OperationLog.php` (modifikasi)

Tabel `erik_operation_log` hanya memiliki kolom `created_at` (tanpa `updated_at`). `save()` default Eloquent akan mencoba menulis `updated_at`, menyebabkan error SQL.

Perbaikan: `public $timestamps = false;` + menetapkan `created_at` secara manual saat menulis.

### 3.2 Perubahan Model AdminUser

- Tambah trait `Searchable`
- Implementasi `toSearchableArray()`: mengembalikan username, real_name
- `UserController::index()` saat mendeteksi kata kunci menggunakan `AdminUser::search($kw)->get()` bukan MySQL LIKE

ES perlu membuat indeks terlebih dahulu, dapat melalui perintah Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Perubahan Rute

`config/route.php` menambahkan rute:

```php
// Tambah di dalam grup rute /admin:
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

// Health check (rute global, bukan di dalam grup /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
middleware grup /admin menambahkan app\middleware\OperationLog::class
```

`config/middleware.php` mendaftarkan middleware global:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Penambahan Kode Error

| code | Arti | Skenario Pemicu |
|------|------|---------|
| 429 | Permintaan terlalu sering | RateLimit terpicu |

---

## 6. Tidak Termasuk dalam Ruang Lingkup Ini

- Sistem notifikasi (membutuhkan message queue + infrastruktur push frontend)
- Halaman frontend Flutter (sub-proyek B)
- Refresh Token HarmonyOS (sub-proyek C)
