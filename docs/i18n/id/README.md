# Sistem ERP Terbuka (open-erp)

Sistem ERP full-stack berbasis webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Maskot gurita open-erp" width="150"></div>

<div align="center">🌐 [中文](../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | Bahasa Indonesia | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Perbandingan Edisi](EDITIONS.md) | [Diagram Arsitektur](ARCHITECTURE.md) | [Diagram Sistem](#diagram-arsitektur-sistem) | [Dokumen Desain](DESIGN.md) | [Arsitektur Keamanan](SECURITY.md) | [Referensi API](API.md) | [Manual Fitur](FUNCTIONS.md)

## Daftar Fitur

| Domain Bisnis | Fitur | Keterangan |
|--------|------|------|
| 🔐 Autentikasi | Login/Registrasi/Refresh Token/Logout | Captcha klik + JWT + daftar hitam |
| | Penguncian akun | 5 kali gagal dikunci 15 menit |
| | Batas sesi bersamaan | Maksimal 3 Token valid per pengguna |
| 📊 Dasbor | Ikhtisar operasional/Papan penjualan/Papan stok/Papan keuangan | Cache Redis 5 menit |
| 👥 Manajemen Pengguna | CRUD + Hapus massal/Aktif-Nonaktifkan | Soft delete + konfirmasi ulang kata sandi |
| | Impor massal Excel | Validasi per baris + laporan kesalahan |
| 🔒 Peran & Izin | CRUD Peran + Pohon izin | Otorisasi RBAC granular method.path |
| ⚙ Konfigurasi Sistem | CRUD pasangan kunci-nilai | Manajemen berkelompok |
| 📋 Audit Operasi | Kueri log + deteksi asal klien | Identifikasi otomatis 8 platform |
| 📁 Manajemen File | Unggah/Ekspor Excel/Ekspor PDF | Data sensitif otomatis di-masking |
| 🛡 Perlindungan Keamanan | Pertahanan berlapis 18 lapis | XSS/Injeksi SQL/Path traversal/Injeksi perintah/CSRF/Rate limit/CSP... |
| 🏥 Operasi | Health check/metrics/dokumen API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Manajemen Produk | Arsip produk/SKU/banyak spesifikasi/banyak satuan/kategori/merek/strategi harga | Pohon kategori bertingkat + konversi multi-satuan |
| | Gudang & lokasi | Manajemen multi-gudang multi-lokasi |
| | Arsip pemasok/pelanggan | Kontak/akun bank/plafon kredit |
| 📥 Manajemen Pembelian | Permintaan→Pesanan→Penerimaan→Retur→Penyelesaian | Proses pembelian lengkap + persetujuan |
| 📤 Manajemen Penjualan | Penawaran→Pesanan→Pengiriman→Retur→Penyelesaian | Penawaran jadi pesanan + margin kotor penjualan |
| 🏗 Manajemen Stok | Stok real-time/batch/nomor seri/transfer/stok opname/peringatan | Perhitungan biaya rata-rata tertimbang bergerak |
| 💰 Manajemen Keuangan | Piutang-hutang/Penerimaan-pembayaran/Jurnal/Reimbursement/Laporan laba/Aset tetap/Pajak/Multi-mata uang/Anggaran/Pusat biaya & laba | Pembuatan otomatis piutang-hutang + penutupan (write-off) + manajemen keuangan menyeluruh |
| 🤝 CRM | Pelanggan/Kontak/Catatan tindak lanjut/Kampanye pemasaran/Tiket layanan/Laporan analisis/Coroong penjualan/Kolam bersama/Penawaran/Kontrak | Manajemen siklus hidup pelanggan menyeluruh |
| ✅ Alur Persetujuan | Definisi alur kerja/Submit persetujuan/Setujui/Tolak/Tarik/Persetujuan saya | Mesin alur persetujuan multi-node |
| 🔔 Notifikasi Pesan | Daftar notifikasi/Tandai dibaca/Jumlah belum dibaca/Semua dibaca | Push pesan real-time & pelacakan status |
| 📐 Manajemen Proyek | Proyek/Tugas/Catatan jam kerja | Pelacakan progres proyek & manajemen sumber daya |
| 👤 Sumber Daya Manusia | Departemen/Karyawan/Posisi/Absensi/Cuti/Gaji | Manajemen personalia menyeluruh |
| 🏭 Manufaktur | BOM/Pesanan produksi/Rute proses/Stasiun kerja/MRP | Perencanaan kebutuhan material & eksekusi produksi |
| 📈 Laporan Kustom | Template laporan/Set data/Field/Filter/Eksekusi/Jadwal terjadwal | Pembangun laporan visual |
| 📋 Manajemen Pesanan (OMS) | Pesanan multi-kanal/Orkestrasi pemenuhan/Pre-alokasi stok/Alokasi/Pembatalan/Retur RMA | Manajemen siklus hidup pesanan menyeluruh |
| 🏗 Manajemen Gudang (WMS) | Zona lokasi/ASN/Penerimaan/Putaway/Gelombang/Picking/Packing/Pengiriman | Proses operasi gudang lengkap |
| 🚚 Manajemen Transportasi (TMS) | Kurir/Layanan/Tarif/Resi/Lacak logistik/Invoice biaya kirim | Perbandingan tarif multi-kurir + pelacakan |

## Modul ERP

Aliran data antar modul bisnis:

- Penerimaan pembelian → otomatis masuk gudang (perhitungan biaya rata-rata tertimbang bergerak) → otomatis membuat utang
- Pengiriman penjualan → otomatis keluar gudang → otomatis membuat piutang
- Penerimaan/pembayaran → menutup piutang-hutang → memperbarui jurnal
- Verifikasi voucher → otomatis memperbarui buku besar (rekapitulasi akun) + buku pembantu (pencatatan per transaksi)
- Neraca → otomatis dihasilkan dari rekapitulasi saldo akhir buku besar
- Laporan arus kas → otomatis dihasilkan dari rekap jurnal kas & bank (tiga klasifikasi: operasi/investasi/pendanaan)
- Alur persetujuan → dokumen bisnis disubmit untuk persetujuan → alur multi-node → hasil persetujuan di-*callback* ke modul bisnis
- Notifikasi pesan → dipicu oleh persetujuan/peringatan/event sistem → push real-time → pengguna menandai dibaca
- MRP → berdasarkan pesanan penjualan + BOM → menghitung kebutuhan material → menghasilkan saran pembelian/produksi
- OMS → impor pesanan multi-kanal → pre-alokasi stok (ATP) → membuat pemenuhan → mengirimkan tugas picking/packing WMS
- WMS → agregasi gelombang → tugas picking → konfirmasi picking → selesai packing → memicu pembuatan resi TMS
- TMS → perbandingan tarif → membuat resi → konfirmasi pengiriman (stockOut+AR) → pelacakan logistik → tanda terima
- WMS inbound → ASN pra-kedatangan → penerimaan → inspeksi kualitas → konfirmasi putaway (stockIn+AP) → pembaruan stok
- RMA → permintaan retur → persetujuan → retur masuk gudang → refund

## Tumpukan Teknologi

| Lapisan | Teknologi | Keterangan |
|---|------|------|
| Framework Backend | webman v2 (workerman) | Framework PHP resident proses berkinerja ultra-tinggi |
| Versi PHP | 8.3+ | |
| Database | MySQL 8.0+ | Prefiks tabel `erp_`, primary key BIGINT non-auto-increment |
| Mesin pencari | Elasticsearch | Sinkronisasi & kueri melalui `webman-scout` |
| Frontend Admin | Flutter 3.x | Sisi Web bergaya dashboard admin PC (`apps/flutter/`) |
| Seluler | HarmonyOS ArkTS | Klien asli HarmonyOS (`apps/harmonyos/`), mendukung ponsel/tablet/2in1 |

## Dependensi Inti

| Paket | Fungsi |
|---|------|
| `erikwang2013/snowflake-php` | Algoritma Snowflake menghasilkan primary key BIGINT unik global |
| `erikwang2013/hashids` | Enkripsi/dekripsi ID di lapisan API, menyembunyikan ID database asli |
| `erikwang2013/jwt-webman` | Penerbitan & verifikasi token autentikasi JWT |
| `erikwang2013/encryption` | Enkripsi/dekripsi data sensitif di lapisan transfer antarmuka |
| `erikwang2013/encryptable` | Enkripsi/dekripsi otomatis field sensitif di lapisan penyimpanan database |
| `erikwang2013/webman-scout` | Sinkronisasi data Elasticsearch & pencarian teks lengkap |
| `erikwang2013/season` | Data bendera negara |
| `erikwang2013/poster-php` | Pembuatan & verifikasi captcha klik + pembuatan poster |
| `erikwang2013/security-php` | Pemeriksaan alat keamanan |
| `phpoffice/phpspreadsheet` | Ekspor Excel |
| `barryvdh/laravel-dompdf` | Ekspor PDF (berbasis Dompdf) |
| `hg/apidoc` | Pembuatan otomatis dokumen API | Dokumen antarmuka berbasis anotasi, dikelompokkan untuk sisi admin/klien |

## Internasionalisasi

Internasionalisasi | Deteksi otomatis header Accept-Language | Dukungan bilingual 中文/English

## Struktur Proyek

```
open-erp/
├── app/
│   ├── admin/controller/       # Controller manajemen sistem (14)
│   ├── api/v1/controller/      # API klien (versi dikontrol header API-Version)
│   ├── controller/             # Controller modul bisnis (88)
│   │   ├── product/            # Produk/kategori/merek/gudang/lokasi/pemasok/pelanggan (7)
│   │   ├── purchase/           # Permintaan/order/penerimaan/retur/penyelesaian pembelian (5)
│   │   ├── sales/              # Penawaran/order/pengiriman/retur/penyelesaian penjualan (5)
│   │   ├── inventory/          # Stok/transaksi/transfer/stok opname/peringatan (5)
│   │   ├── finance/            # Piutang-hutang/voucher/penerimaan-pembayaran/jurnal/buku besar/buku pembantu/laporan/aset/pajak/multi-mata uang/anggaran/pusat biaya & laba (20)
│   │   ├── crm/                # Peluang/tindak lanjut/corong/kontak/kolam bersama/kontrak/penawaran/pemasaran/tiket/analisis (10)
│   │   ├── workflow/           # Definisi alur kerja/submit persetujuan/setujui/tolak/tarik (2)
│   │   ├── notification/       # Daftar notifikasi/dibaca/jumlah belum dibaca (1)
│   │   ├── project/            # Proyek/tugas/catatan jam kerja (3)
│   │   ├── hr/                 # Departemen/karyawan/posisi/absensi/cuti/gaji (5)
│   │   ├── manufacturing/      # BOM/pesanan produksi/rute proses/stasiun kerja/MRP (5)
│   │   ├── report/             # Template laporan/set data/eksekusi/jadwal terjadwal (2)
│   │   ├── oms/                # Pesanan OMS/pemenuhan/RMA/kanal (4)
│   │   ├── wms/                # Zona/lokasi/ASN/penerimaan/putaway/gelombang/picking/packing (8)
│   │   └── tms/                # Kurir/layanan/tarif/resi/lacak/invoice biaya kirim (6)
│   ├── service/                # Lapisan logika bisnis
│   │   ├── inventory/          # In/out stok + perhitungan biaya rata-rata tertimbang bergerak + pre-alokasi/ATP stok
│   │   ├── finance/            # Pembuatan otomatis piutang-hutang + write-off
│   │   ├── notification/       # Layanan pengiriman notifikasi
│   │   ├── oms/                # Orkestrasi pesanan/alokasi stok/siklus hidup RMA
│   │   ├── wms/                # Proses inbound (ASN→penerimaan→putaway) / proses outbound (gelombang→picking→packing)
│   │   └── tms/                # Manajemen resi/perbandingan tarif/lacak logistik
│   ├── model/                  # 161 model Eloquent (dipakai bersama antar modul)
│   ├── middleware/             # 12 middleware
│   ├── common/                 # Layanan Hashids/Snowflake/Encryption
│   └── queue/                  # Tugas antrean
├── apps/
│   ├── flutter/                # Flutter lintas platform (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Klien asli HarmonyOS
├── config/                     # File konfigurasi (berisi komentar 中文)
│   ├── plugin/hg/apidoc/        # Konfigurasi dokumen API
├── database/
│   ├── install.sql              # SQL instalasi lengkap (163 tabel + data seed)
│   ├── e2e-seed.sql             # Seed minimal E2E/CI
│   └── backup/                 # Skrip backup/restore
├── docs/                       # Dokumentasi arsitektur, desain, keamanan, API
├── tests/                      # Pengujian PHPUnit (20 file pengujian, 137 metode pengujian, 805 asersi)
├── resource/
│   └── translations/           # File terjemahan (zh_CN, en)
│       ├── zh_CN/              # Terjemahan 中文 (127 kunci)
│       └── en/                 # Terjemahan English (127 kunci)
├── public/                     # Entry point publik
├── runtime/                    # File runtime
└── vendor/                     # Dependensi Composer
```

## Diagram Arsitektur Sistem

> Klik gambar untuk melihat SVG asli. Diagram menggunakan penamaan bahasa Inggris, menampilkan secara lengkap dan jelas desain arsitektur di setiap lapisan sistem.

### Topologi Arsitektur Sistem

![System Architecture](./diagrams/system-architecture-cn.svg)

**Arsitektur lima lapis**: Lapisan klien → Lapisan edge gateway (reverse proxy Nginx) → Lapisan aplikasi (webman v2 + rantai middleware + autentikasi & otorisasi + logika bisnis + layanan publik) → Lapisan penyimpanan data (MySQL + Redis + Elasticsearch) → Lapisan operasi (CI/CD + Docker + Prometheus)

### Diagram Alur Data Bisnis

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**Tujuh domain bisnis saling terhubung**: Pembelian → Stok → Penjualan → Keuangan membentuk loop tertutup rantai pasok inti; manajemen hubungan pelanggan mendorong penjualan; MRP manufaktur berbasis pesanan penjualan + bill of materials mendorong rencana pembelian dan rencana produksi; alur persetujuan, notifikasi pesan, manajemen proyek, sumber daya manusia sebagai modul pendukung yang menembus seluruh proses.

### Ikhtisar Modul Fungsi

![Functional Modules](./diagrams/functional-modules-cn.svg)

**19 domain bisnis besar, 163 tabel data, 121 controller**: Mencakup keamanan autentikasi, dasbor, manajemen sistem, perlindungan keamanan, pemantauan operasi, manajemen produk, pembelian, penjualan, stok, keuangan (14 submodul), CRM (10 submodul), alur persetujuan, notifikasi pesan, manajemen proyek, sumber daya manusia, manufaktur (MRP), laporan kustom, manajemen pesanan (OMS), manajemen gudang (WMS), manajemen transportasi (TMS), manajemen kualitas (QMS), manajemen peralatan (EAM), manajemen dokumen (DMS), papan BI.

### Siklus Hidup Permintaan

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Jalur permintaan lengkap dari klien ke database**: Klien (Flutter/HarmonyOS) → terminasi SSL Nginx → deteksi bahasa → penanganan CORS → filter keamanan → rate limit → validasi versi API → [Admin: autentikasi JWT → izin RBAC → log operasi] → Controller → Lapisan layanan → Lapisan model → Cache/database/mesin pencari → Respons JSON. Diagram mencakup dua jalur: cache hit dan cache miss.

### Arsitektur Pertahanan Berlapis Keamanan

![Security Architecture](./diagrams/security-architecture-cn.svg)

**Pertahanan berlapis 18 lapis**: L0 jaringan fisik → L1 keamanan transportasi → L2 header keamanan HTTP → L3 validasi permintaan → L4 sanitasi input → L5 proteksi CSRF → L6 rate limit → L7 autentikasi (JWT+Captcha+daftar hitam+kontrol sesi) → L8 otorisasi RBAC → L9 proteksi data (enkripsi transportasi + enkripsi penyimpanan + obfuskasi ID + masking data) → L10 pemantauan audit → L11 pengungkapan kepatuhan.

---

## Persyaratan Lingkungan

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (hanya diperlukan untuk pengembangan frontend)
- Elasticsearch >= 7.x (opsional, diperlukan untuk fitur pencarian)

## Memulai Cepat

### 1. Instal Dependensi

```bash
composer install
```

### 2. Konfigurasi Variabel Lingkungan

Salin dan ubah variabel lingkungan (opsional; jika tidak dikonfigurasi, nilai default di `config/*.php` yang digunakan):

```bash
cp .env.example .env
```

Item konfigurasi kunci:

| Variabel Lingkungan | Keterangan | Nilai Default |
|---------|------|--------|
| `JWT_SECRET` | Kunci penandatanganan JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Salt Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Kunci enkripsi API | Nilai default 32 byte |
| `SNOWFLAKE_DATACENTER_ID` | ID pusat data (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID node pekerja (0-31) | `1` |
| `SCOUT_HOSTS` | Alamat ES | `http://localhost:9200` |

**Di lingkungan produksi, wajib mengubah semua kunci menjadi string acak.**

### 3. Inisialisasi Database

**Cara 1: Wizard instalasi Web (disarankan)**

Setelah layanan dimulai, akses `http://localhost:8788/install` dan ikuti panduan untuk menyelesaikan instalasi 4 langkah: pemeriksaan lingkungan → konfigurasi database → akun admin → instalasi satu-klik.

**Cara 2: Impor baris perintah**

```bash
mysql -u root -p nama_database < database/install.sql
```

`install.sql` digabungkan dari 29 file migrasi, berisi struktur seluruh 163 tabel dan data seed.

**Cara 3: Lingkungan Docker**

```bash
docker-compose exec app mysql -h mysql -u root -p < database/install.sql
```

### 4. Mulai Layanan

```bash
php start.php start
```

Secara default mendengarkan di `http://0.0.0.0:8788`.

### 5. Mulai Frontend (opsional)

**Flutter Admin (sisi Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Sisi Web (bergaya dashboard admin PC)
```

**Klien HarmonyOS (sisi ponsel):**

Buka direktori `apps/harmonyos/` dengan DevEco Studio, jalankan dengan perangkat asli atau emulator.

### 6. Deploy Satu-Klik Docker Compose (disarankan untuk produksi)

Proyek menyediakan solusi orkestrasi Docker lengkap, berisi 5 layanan: Nginx, PHP (aplikasi webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Konfigurasi variabel lingkungan Docker
cp .env.docker .env

# 2. Mulai semua layanan
docker-compose up -d

# 3. Inisialisasi database (jalankan di dalam container app)
docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Akses
# http://localhost:8788  (webman)
# http://localhost:8080  (reverse proxy Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, berbasis `php:8.3-cli`
- `docker-compose.yml`: orkestrasi 5 layanan, isolasi jaringan, persistensi volume data
- `.env.docker`: variabel lingkungan khusus lingkungan Docker
## Konvensi Database

- **Prefiks tabel**: `erp_`
- **Primary key**: semua tabel ber-primary key `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT dilarang**
- **Pembuatan ID**: ID primary key dibuat oleh `SnowflakeService::generate()` di lapisan aplikasi, unik terdistribusi
- **Field wajib**: setiap tabel harus berisi `id`, `created_at`, `updated_at`
- **Soft delete**: tabel yang memerlukan soft delete menambahkan `deleted_at DATETIME DEFAULT NULL`
- **Field sensitif**: nomor ponsel, email, nomor KTP, dll. menggunakan plugin `encryptable` untuk enkripsi/dekripsi otomatis, field database menggunakan `VARCHAR(500)` untuk menyimpan ciphertext

## Konvensi API

### Dokumen API

Proyek menggunakan hg/apidoc untuk menghasilkan dokumen antarmuka secara otomatis, akses `/apidoc` untuk melihat.

- Antarmuka admin (Admin): 25 grup modul, berisi parameter permintaan lengkap dan struktur respons
- Antarmuka klien (Service API): 3 grup autentikasi/kaptcha/produk
- Semua antarmuka ditandai dengan header global seperti autentikasi JWT, versi API, internasionalisasi

### Format Respons Terpadu

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Kode Kesalahan Bisnis

| Kode Kesalahan | Arti | Keterangan |
|-------|------|------|
| `0` | Sukses | |
| `400` | Kesalahan parameter permintaan | |
| `401` | Belum login (Token tidak valid atau kedaluwarsa) | |
| `403` | Tanpa izin / pemblokiran keamanan | Gagal otorisasi RBAC / deteksi serangan SecurityFilter |
| `404` | Sumber daya tidak ada | |
| `422` | Validasi parameter gagal | |
| `413` | Body permintaan terlalu besar | Dipicu SecurityFilter, melebihi 10MB |
| `405` | Metode permintaan tidak diizinkan | Dipicu SecurityFilter, hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipe media tidak didukung | Dipicu SecurityFilter, Content-Type bukan JSON |
| `429` | Permintaan terlalu sering | Dipicu RateLimit / penguncian akun (5 kali gagal login dikunci 15 menit) |
| `500` | Kesalahan internal server | |

### Internasionalisasi

Header permintaan `Accept-Language` otomatis mengganti bahasa (zh-CN → 中文, en → English), default 中文.

### Penanganan ID

- **ID dalam permintaan/respons**: dienkripsi sebagai string menggunakan hashids, tidak mengekspos ID database asli
- **Path antarmuka**: `GET /admin/user/{hashid}` — `{id}` dalam path adalah string hashid
- **Penyimpanan database**: nilai asli BIGINT, dibuat oleh snowflake

### Versi API

Versi API dikontrol melalui header permintaan, **tidak tercermin di URL**:

```http
API-Version: v1
```

- Saat tidak membawa nomor versi, default menggunakan `v1`
- Versi yang tidak didukung mengembalikan `400 Bad Request`
- Menambahkan versi baru cukup membuat direktori `app/api/{version}/controller/`, middleware mendaftarkan versi baru

### Rate Limit

Berbasis algoritma sliding window Redis, default 60 kali/menit/IP/route. Antarmuka sensitif lebih ketat:
- Login: 10 kali/menit
- Registrasi: 5 kali/menit (default nonaktif, perlu `REGISTRATION_ENABLED=1` untuk mengaktifkan)

Header respons berisi `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Melebihi batas mengembalikan 429 disertai `Retry-After`.

### Arsitektur Middleware

Middleware global berlaku untuk semua permintaan, dieksekusi berurutan:

```
Locale (deteksi otomatis Accept-Language, atur lingkungan bahasa)
  → Cors (praproses CORS + header respons)
  → SecurityFilter (batasan metode HTTP/ukuran body/validasi Content-Type/XSS/injeksi SQL/path traversal/injeksi perintah/intercept serangan CSRF)
  → RateLimit (rate limit sliding window Redis + penguncian akun: 5 kali gagal login dikunci 15 menit)
  → ApiVersion (validasi versi API, grup route /api)
  → AdminAuth (autentikasi JWT + daftar hitam, grup route /admin)
  → AdminPermission (otorisasi RBAC, grup route /admin)
  → OperationLog (pencatatan otomatis POST/PUT/DELETE, termasuk deteksi asal klien, grup route /admin)
```

`/health`, `/api/docs` dan `/install` adalah endpoint publik, hanya melalui `Locale → Cors → SecurityFilter → RateLimit`.

Peningkatan keamanan:
- **Penguncian akun**: 5 kali gagal login berturut-turut, akun otomatis dikunci 15 menit, selama periode tersebut login mengembalikan 429
- **Batas sesi bersamaan**: maksimal 3 Token valid per pengguna, saat melebihi Token paling lama otomatis masuk daftar hitam
- **security.txt**: `GET /.well-known/security.txt` menyediakan informasi kontak keamanan standar RFC 9116
- **Konfigurasi keamanan Nginx**: lihat `nginx-security.conf` untuk contoh penguatan keamanan reverse proxy lengkap

### Autentikasi

Login dan registrasi harus melalui validasi **captcha klik** terlebih dahulu:

1. Klien meminta `POST /api/captcha/generate` untuk mendapatkan gambar captcha (base64 PNG) dan daftar target teks
2. Pengguna mengklik posisi teks yang sesuai di gambar secara berurutan, mengumpulkan koordinat klik `[{x, y}, ...]`
3. Saat login, kirim `captcha_key` dan `clicks` bersamaan, server memvalidasi captcha terlebih dahulu lalu memvalidasi kredensial

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Antarmuka selanjutnya di sisi admin memerlukan autentikasi JWT:

```http
Authorization: Bearer <token>
```

Setelah login berhasil, mengembalikan access_token, berlaku 2 jam; juga mengembalikan refresh_token, berlaku 14 hari.

Saat logout, Token dimasukkan ke daftar hitam Redis, tidak dapat digunakan kembali selama masa berlaku. POST /admin/profile/logout

### Konfirmasi Ulang Operasi Sensitif

Operasi sensitif seperti menghapus pengguna, peran, izin memerlukan pengiriman `password` pengguna yang sedang login di body permintaan untuk konfirmasi ulang identitas:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Daftar API

Daftar lengkap antarmuka (antarmuka publik / antarmuka admin / antarmuka bisnis / antarmuka klien) telah dipindahkan ke dokumen terpisah:

→ [Dokumen Referensi API](API.md)

## Keterangan Frontend

### Flutter Admin (gaya PC)

- **Tata letak**: sidebar (dapat dilipat 64px/240px) + top bar + area konten, tiga breakpoint responsif (ponsel/tablet/desktop)
- **Halaman**: login, dasbor, manajemen pengguna, izin peran, konfigurasi sistem, log operasi, pusat pribadi
- **Manajemen status**: GetX (`ApiService` singleton + persistensi Token `AuthService`)
- **Dasbor**: kartu statistik, grafik garis tren (fl_chart), pie chart, log operasi terbaru
- **Ekspor**: ekspor Excel/PDF, PDF berisi informasi hak cipta yang tidak dapat dihapus
- **Operasi massal**: hapus massal multi-pilih, aktif/nonaktifkan massal
- **Tema**: Material 3 tema terang/gelap ganda

### Seluler HarmonyOS

- **Halaman**: login, dasbor, daftar/detail pengguna, pusat pribadi
- **Autentikasi**: JWT Bearer + 401 refresh Token otomatis tanpa terasa, gagal refresh otomatis redirect ke halaman login
- **Penyimpanan**: Token dikelola melalui AppStorage

## Konvensi Pengembangan

- Referensi fungsi/kelas global tanpa awalan `\`, gunakan `use` untuk impor
- Semua file PHP harus berisi deklarasi hak cipta di bagian atas
- Semua file konfigurasi harus berisi komentar 中文
- Primary key database harus dibuat oleh snowflake di lapisan aplikasi, dilarang auto-increment
- Semua ID dalam parameter dan respons di lapisan API harus melalui enkripsi/dekripsi hashids
- Middleware AdminPermission menggunakan cache Redis untuk izin pengguna (TTL=60s), menghilangkan bottleneck kueri N+1

## Deployment

### Docker Compose (disarankan)

Direktori root proyek menyediakan `docker-compose.yml`, mengorkestrasi 5 layanan:

| Layanan | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | dibangun dari `Dockerfile` lokal | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Image PHP dibangun melalui `Dockerfile`, base image `php:8.3-cli`, dengan OPcache diaktifkan.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

Pipeline integrasi berkelanjutan GitHub Actions: `.github/workflows/ci.yml`

- Pemeriksaan sintaks PHP (`php -l`)
- Pengujian unit PHPUnit
- Analisis statis Flutter (`flutter analyze`, sudah termasuk di CI, aktif — lihat job flutter di `.github/workflows/ci.yml`)

### Backup Database

Direktori `database/backup/`:

- `backup.sh` — backup mysqldump + gzip, otomatis membersihkan backup lama lebih dari 30 hari
- `restore.sh` — restore interaktif, menampilkan backup yang tersedia untuk dipilih

### Konfigurasi Keamanan Nginx

Untuk deployment produksi, lihat `nginx-security.conf` untuk konfigurasi penguatan keamanan reverse proxy.

## Open Source Tidak Mudah, Dukungan Anda Disambut

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./images/weixinpay.png "微信") | ![支付宝](./images/alipay.png "支付宝") |

### Transfer Bank Global (Global Bank Transfer)

**Informasi Penerima**

- Nama penerima: WANG KEXUN
- Nomor rekening penerima: 881015918251

**Bank Penerima**

- Kode SWIFT ZA Bank: AABLHKHHXXX
- Nama bank: ZA Bank Limited
- Nomor bank: 387
- Alamat bank: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Bank Perantara Transfer Lintas Batas (jika diperlukan)**

> Ini adalah informasi bank perantara (bank penerusan), bukan informasi bank penerima. Tanyakan ke bank pengirim apakah perlu disediakan.

- Untuk transfer dalam Dolar Hong Kong, Yuan Tiongkok, dan Dolar AS: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, nomor bank 006, cabang Hong Kong Branch, nomor cabang 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Untuk transfer mata uang lain: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Donasi Kripto (Crypto Donation)

Jika proyek ini membantu Anda, silakan pindai kode QR untuk berdonasi, terima kasih!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## Lisensi

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
