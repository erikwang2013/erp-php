# Laporan Audit — 2026-08-07

**Proyek**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**Ruang lingkup**: pengujian keseluruhan, pemeriksaan mendalam, perbaikan masalah P0/P1
**Instruksi**: "Uji semuanya secara menyeluruh, jalankan, periksa lebih dalam apakah masih ada masalah atau hal yang bisa dioptimalkan?"
**Hasil tes**: OK (135 tests, 799 assertions) — semua lolos

---

## 1. Hasil Pengujian dan Verifikasi Runtime

| Item | Hasil |
|---|---|
| PHPUnit lengkap | 135 tests / 799 assertions semua lolos |
| Startup layanan (port 8787→sementara 8791) | Normal, tanpa crash proses |
| Health check /health | code=0, field database/redis/elasticsearch lengkap |
| Rantai rate limit | Permintaan beruntun /api/auth/login mengembalikan 429 |
| Blacklist JWT / kunci login | Berfungsi normal (setelah Redis diperbaiki) |
| CS-Fixer | 31 pelanggaran format file telah diperbaiki |
| PHPStan | Berjalan kembali setelah perbaikan cache rusak (851 false positive magic method ORM, 75 baseline kedaluwarsa) |

---

## 2. Perbaikan P0 (Kegagalan Runtime — semua telah diperbaiki dan diverifikasi)

### 2.1 Kelas support\Redis tidak ada — mekanisme keamanan gagal secara diam-diam

- **Gejala**: `support\Redis` tidak ada (composer.json tidak pernah menambahkan webman/redis), 9 file mereferensikannya.
- **Akar masalah**: banyak desain fail-open `catch (\Throwable)` menelan error kelas tidak ada, menyebabkan rate limit, blacklist JWT, kunci login, dan ban semuanya gagal secara diam-diam, antarmuka "tampak normal" tetapi tanpa perlindungan apa pun.
- **Perbaikan**: `composer require webman/redis`; `config/redis.php` divariabelkan lingkungan (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Verifikasi**: /health mengembalikan `redis: ok`; tes rate limit mengembalikan 429.

### 2.2 Middleware ApiVersion gagal kompilasi — semua rute /api 500

- **Gejala**: `Interface "app\middleware\MiddlewareInterface" not found` — kurang `use Webman\MiddlewareInterface;`.
- **Error kedua setelah perbaikan**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` adalah subkelas `Webman\Http\Request`, melanggar kontrak kontravarian parameter.
- **Perbaikan**: beralih menggunakan import `Webman\Http\Request` / `Webman\Http\Response`.

### 2.3 Kontravarian parameter middleware AdminAuth — worker rute /admin crash

- **Gejala**: /admin/dashboard memicu worker Empty reply (crash kompilasi).
- **Akar masalah**: masalah kontravarian parameter yang sama dengan 2.2.
- **Perbaikan**: beralih menggunakan `Webman\Http\Request` / `Webman\Http\Response` (pertahankan `support\Redis`).
- **Verifikasi**: mengembalikan JSON 401.

### 2.4 Fungsi helper validator() tidak ada — login 500

- **Gejala**: `Call to undefined function validator()`, dipanggil 105 kali di 99 file.
- **Perbaikan**: `composer require illuminate/validation`; `app/functions.php` mengimplementasikan fungsi helper (cache $factory statis).
- **Jebakan**: parameter pertama `Factory::__construct()` harus `Translator`, bukan `ArrayLoader`.
- **Tinggalan (P2)**: pesan error belum diterjemahkan (menampilkan `validation.required` bukan bahasa Indonesia), perlu melengkapi paket bahasa.

### 2.5 CORS di-hardcode + respons preflight kehilangan header CORS

- **Perbaikan**: tambah `app/common/CorsPolicy.php`, membaca whitelist dari variabel lingkungan `CORS_ALLOWED_ORIGIN` (dipisahkan koma), origin di-echo; jika tidak cocok, tidak mengirim header CORS.
- **Poin kunci**: `Route::fallback` tidak melalui rantai middleware global, preflight OPTIONS harus menambahkan sendiri header CORS — sudah ditangani di closure fallback.
- **Header keamanan**: hapus X-XSS-Protection yang sudah ditinggalkan; CSP menambahkan `connect-src 'self'`.

### 2.6 FastRoute BadRouteException — rute terbayangi

- **Gejala**: `Static route "/install" is shadowed by previously defined variable route`.
- **Akar masalah**: rute wildcard OPTIONS `/{path:.+}` menaungi rute statis berikutnya; rute plugin (apidoc) dimuat setelah config/route.php.
- **Perbaikan**: hapus rute wildcard, beralih ke `Route::fallback` (harus diletakkan di akhir file rute); `/crm/pool/rules` diubah dari resource menjadi rute GET eksplisit, `PoolController::rules()` diubah menjadi public.

---

## 3. Perbaikan P1 (Kualitas Teknik)

- **3.1 Cache PHPStan rusak**: /tmp/phpstan/cache berasal dari direktori service/ yang sudah dihapus (sisa pemisahan microservice), berisi path absolut lama yang menyebabkan error phar dan hang CPU 0%. Setelah cache dibersihkan dan dipasang ulang, berjalan normal. 851 error adalah false positive magic method ORM webman; 75 baris baseline menunjuk ke direktori service/ yang tidak ada (P2).
- **3.2 CS-Fixer**: 31 pelanggaran spasi/urutan use file telah diperbaiki.
- **3.3 Sinkronisasi tes**: `test_cors_response_is_assigned_correctly` diperbarui untuk menegaskan implementasi baru (withHeaders + CorsPolicy).

---

## 4. Akar Masalah yang Terlewatkan Audit Sebelumnya (08-04)

- Tes tidak mencakup **ke-muat-an kelas middleware** dan **ke-dapat-dipanggil-an rute** (class_exists / is_subclass_of tidak dapat menangkap use yang hilang dan kontravarian parameter).
- Perbaikan CORS/X-XSS yang diklaim commit b1fe2de tidak sesuai kode aktual — kesimpulan audit terlalu bergantung pada pesan commit daripada verifikasi runtime.

---

## 5. Daftar Perubahan Putaran Ini (git status: 41 modifikasi + 2 tambahan)

| File | Perubahan |
|---|---|
| app/middleware/ApiVersion.php | Tambah use Webman\MiddlewareInterface; tipe parameter diubah ke Webman\Http |
| app/middleware/AdminAuth.php | Tipe parameter diubah ke Webman\Http |
| app/middleware/Cors.php | Direfaktor menggunakan CorsPolicy; pembaruan CSP/header keamanan |
| app/common/CorsPolicy.php | **Baru**: kebijakan whitelist CORS |
| config/route.php | Rute fallback + perbaikan /crm/pool/rules |
| app/controller/crm/PoolController.php | rules() diubah menjadi public |
| app/functions.php | Tambah fungsi helper validator() |
| config/redis.php | **Baru** (divariabelkan lingkungan setelah dibuat composer) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | Sinkronisasi assertion CORS |
| Sekitar 30 file lainnya | Perbaikan format CS-Fixer |

---

## 6. Saran P2 (lingkungan/todo, belum diperbaiki)

1. **DB_PASSWORD .env kosong** — autentikasi root MySQL gagal, `database: unavailable`; perlu mengonfigurasi kata sandi asli.
2. **Konflik port 8787** — digunakan cloud-php/service (proyek berbeda); deployment produksi perlu dibedakan.
3. **Pesan error bahasa Indonesia validator** — perlu memasang paket bahasa atau kustomisasi messages.
4. **Pembangunan ulang baseline PHPStan** — 75 baris path menunjuk ke direktori service/ yang sudah dihapus, disarankan dibersihkan dan dibangun ulang.
5. **Audit fail-open** — disarankan pemeriksaan menyeluruh titik `catch (\Throwable)` yang menelan error secara diam-diam (putaran ini menemukan 1 konsekuensi serius), diubah menjadi fail-closed atau log eksplisit.

---

*Laporan dibuat: 2026-08-07, layanan telah dihentikan, port dikembalikan ke 8787.*
