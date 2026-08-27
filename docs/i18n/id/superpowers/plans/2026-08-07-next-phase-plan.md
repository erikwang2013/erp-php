# Perencanaan Proyek Fase Berikutnya (P4 / Periode Evolusi 1.1)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Penyusun: System Architect ｜ Tanggal: 2026-08-07 ｜ Dasar: tiga riset pendahuluan (perencanaan & kesenjangan / backend & kualitas / frontend) + pengecekan ulang sampel di lapangan
> Status: draf (menunggu review) ｜ Versi target: 1.1 (periode evolusi)

---

## 1. Posisi Fase

Roadmap P0~P3 telah seluruhnya diserahkan: 22 modul bisnis, 163 tabel, 121 controller, 24 service, 161 model, 12 middleware;
Flutter 96 halaman + HarmonyOS 34 halaman; skor komprehensif 89/100. **Fase ini tidak menambah domain bisnis baru**, melainkan melengkapi kemampuan yang "sudah diimplementasikan tetapi belum tertutup (closed-loop)",
mengelola utang kualitas, menghilangkan drift dokumen, menghasilkan **Versi Evolusi 1.1** yang dapat dipelihara jangka panjang.

Tiga penilaian inti (semuanya dikonfirmasi melalui pengecekan sampel):

1. **Banyak kemampuan "ada tetapi tidak efektif"**: middleware TenantScope dan trait model tidak didaftarkan di `config/middleware.php` (multi-tenant hanyalah cangkang kosong);
   queue dikonfigurasi dual-driver redis/rabbitmq tetapi `config/process.php` tidak memiliki proses konsumen; koneksi WebSocket tidak memvalidasi JWT;
   statistik OMS/WMS/TMS dashboard Flutter berupa nilai palsu hardcoded, sedangkan endpoint backend `/dashboard/oms|wms|tms` sudah ada tetapi tidak pernah dipanggil;
   frontend memanggil endpoint notifikasi yang tidak ada `/admin/notification/my/read` (backend sebenarnya `/admin/notification/read-all`).
2. **Utang kualitas dan keamanan**: 11 modul bisnis nol pengujian; PHPStan level 5 tetapi baseline menekan 974 error; 137 tes semuanya unit test murni, tanpa integrasi/E2E/coverage;
   `.env.docker` banyak kunci lemah; CI hanya job PHP, tanpa quality gate frontend apa pun.
3. **Drift dokumen sistematis**: jumlah tes 132/779→135/799→137/805 tiga versi tidak konsisten; lampiran FUNCTIONS.md jauh dari hasil pengukuran;
   angka EDITIONS.md saling bertentangan; tiga cabang lite/standard/full tertinggal 20~41 commits dari main.

**Prinsip**: lengkapi dulu "sudah diimplementasikan tetapi belum tertutup" (dead endpoint, TenantScope/queue yang belum dihubungkan, dashboard mock), lalu lengkapi tes dan quality gate,
lalu optimalkan struktur dan dokumen. Semua tugas kecil dan jelas, dapat diselesaikan dalam satu sesi agent; yang belum pasti ditandai "待验证" (perlu diverifikasi).

---

## 2. Analisis Kesenjangan (Ringkasan)

Kesenjangan dari tiga riset dirangkum menjadi **6 kelompok kerja**. Setiap item diberi jalur bukti.

### Kelompok Kerja A: Pelengkapan loop bisnis (prioritas tertinggi)

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| A1 | "Tandai semua sebagai dibaca" notifikasi: frontend memanggil endpoint yang tidak ada | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` memanggil `/admin/notification/my/read`; rute backend adalah `POST /admin/notification/read-all` di `config/route.php:250` | Terkonfirmasi |
| A2 | Statistik OMS/WMS/TMS dashboard berupa nilai mock palsu, permintaan tidak membawa JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (Dio independen `baseUrl: http://localhost:8787`, tanpa interceptor; `omsStats/wmsStats/tmsStats` hardcoded; komentar "Mock values for now"); endpoint backend asli `config/route.php:231-233` | Terkonfirmasi |
| A3 | Middleware TenantScope dan trait model belum dihubungkan, multi-tenant hanyalah cangkang kosong | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` ada; rantai global `config/middleware.php` hanya mendaftarkan Locale/Cors/SecurityFilter/RateLimit/TracingId, grup route.php juga tidak mereferensikannya | Terkonfirmasi |
| A4 | Queue dual-driver tetapi tanpa proses konsumen, end-to-end tidak efektif | `config/queue.php` (default redis, opsi rabbitmq); `config/process.php` hanya tiga proses webman/socket/monitor | Terkonfirmasi |
| A5 | WebSocket tanpa autentikasi | `app/process/WebSocket.php:23` komentar "could validate JWT here"; `:47-50` pesan auth langsung mengembalikan success:true, tanpa validasi token | Terkonfirmasi |
| A6 | Parameter paginasi 25 halaman daftar HarmonyOS tidak berfungsi (`${this.page}` dalam tanda kutip tunggal tidak diinterpolasi) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (sudah dicek sampel); 24 lokasi lain pola yang sama | Terkonfirmasi (daftar menunggu verifikasi penuh) |
| A7 | Endpoint aksi bisnis sebagian besar belum terhubung ke frontend (penyelesaian/tiga laporan/pemenuhan/persetujuan/perhitungan gaji dll.) | Kesimpulan riset matriks cakupan; contoh: pembelian/penjualan kurang halaman penyelesaian, keuangan kurang 13 endpoint, CRM kurang follow/funnel/alur kontrak | Perlu diverifikasi (perlu pengecekan daftar per modul) |
| A8 | Form pada banyak halaman bisnis hanya berisi kolom umum name/code | Kesimpulan riset (membuat pesanan penjualan/voucher pembukuan hanya mengisi nama kode) | Perlu diverifikasi (perlu pengecekan per halaman) |

### Kelompok Kerja B: Rekonstruksi sistem pengujian

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| B1 | 11 modul bisnis nol pengujian: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | 19 file tes di `tests/` hanya mencakup admin/finance/inventory/oms/wms/tms/notification/hr/mrp/kelas dasar keamanan; 11 modul di atas tidak memiliki file tes khusus — enam modul crm/eam/dms/quality/report/workflow **nol penyebutan** di file tes mana pun; project/purchase/sales/product/bi hanya dirujuk secara tidak sengaja oleh tes kelas dasar atau modul tetangga (sampling pola ControllerPatternTest, daftar rute bootstrap.php, konteks masuk gudang purchase/product yang disebut InventoryServiceTest, "bi" sebagai substring debit_amount di DoubleEntryServiceTest), semuanya bukan cakupan khusus | Terkonfirmasi |
| B2 | Tidak ada integrasi/E2E/coverage; 137 tests / 805 assertions semuanya unit test murni (terukur selesai dalam 1.2s, murni in-memory) | `vendor/bin/phpunit` terukur "OK (137 tests, 805 assertions)" | Terkonfirmasi |
| B3 | PHPStan level 5 tetapi baseline menekan 974 error | `phpstan-baseline.neon` terukur 974 node message | Terkonfirmasi |
| B4 | CI tanpa pengumpulan coverage, tanpa job tes integrasi | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, hanya composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Terkonfirmasi |
| B5 | Controller purchase/sales meng-hardcode dependensi service | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143` (kedua file `use` dideklarasikan di :15-16, `new InventoryService()/new FinanceService()` dibuat di :142-143) | Terkonfirmasi |

### Kelompok Kerja C: Infrastruktur dan tata kelola keamanan

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| C1 | Kunci lemah di `.env.docker` | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest`（.env.docker:15,32,37,51,67,81） | Terkonfirmasi |
| C2 | Validasi ketat variabel lingkungan tidak lengkap | Riset: hanya ENCRYPTION_KEY yang melalui env_required | Perlu diverifikasi (periksa config/jwt.php, encryption.php) |
| C3 | fail-open menelan error secara diam-diam | Kesimpulan riset; cakupan menunggu audit (try/catch kosong, catch tanpa log) | Perlu diverifikasi (perlu audit grep) |
| C4 | backup-validator.sh dan `_rollback.sql` per migrasi tidak ada | `find` seluruh repo tanpa kecocokan; 29 migrasi SQL di `database/migrations/` semuanya tanpa file rollback terkait | Terkonfirmasi |
| C5 | Stub kanal notifikasi (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Terkonfirmasi |
| C6 | Celah monitoring: tidak ada metrik penumpukan queue/jumlah koneksi WebSocket | `app/admin/controller/MetricsController.php` yang ada memiliki 5 gauge | Sebagian terkonfirmasi |

### Kelompok Kerja D: Matriks versi dan tata kelola dokumen

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| D1 | Cabang lite/standard/full tertinggal 20~41 commits dari main | `git rev-list --left-right --count main...lite|standard|full` terukur: 41/41/20 behind, dan lite/standard masing-masing memiliki 6~7 ahead commit unik | Terkonfirmasi |
| D2 | Angka EDITIONS.md saling bertentangan | Tabel ringkasan: controller 48/42/70, modul bisnis 6/6/12; bagian jalur upgrade malah menulis 12/12/19 modul, 163 tabel; tidak cocok dengan 121 controller terukur | Terkonfirmasi |
| D3 | Drift lampiran FUNCTIONS.md | Lampiran menulis 11 file/90 metode/168 assertion/9 middleware/22 migrasi; terukur 19~20 file/137 tes/805 assertion/12 middleware/29 migrasi | Terkonfirmasi |
| D4 | Jumlah tes drift tiga versi (132/779→135/799→137/805) | Riwayat dokumen dan catatan commit git | Terkonfirmasi |
| D5 | Matriks kelengkapan menandai QMS/EAM/DMS/BI 🔴 tetapi kode sudah ada | Matriks dekat `docs/FUNCTIONS.md:555` vs `app/controller/{quality,eam,dms,bi}/` yang sudah diimplementasikan | Terkonfirmasi |
| D6 | Terminologi controller kacau: docs/CLAUDE.md menulis "104 controller bisnis", terukur total 122 | `find app -path '*/controller/*.php' | wc -l` = 122 (termasuk admin 14 + api 3 + bisnis 104 + Index/Install); terminologi riset 121 | Terkonfirmasi (perbedaan terminologi) |
| D7 | Terminologi jumlah migrasi: riset 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (penomoran hingga 000030, kurang 000007/000008) | Terkonfirmasi (29 adalah hasil terukur) |

### Kelompok Kerja E: Kualitas dan penyelarasan frontend

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| E1 | CI tanpa flutter analyze/test/build, tanpa build hvigor | `.github/workflows/ci.yml` hanya job PHP | Terkonfirmasi |
| E2 | README mengklaim CI berisi analisis statis Flutter, tidak sesuai fakta | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml tanpa langkah tersebut | Terkonfirmasi |
| E3 | Flutter hanya 1 smoke test | `apps/flutter/test/widget_test.dart` satu-satunya file tes | Terkonfirmasi |
| E4 | Token HarmonyOS tidak dipersistensikan (AppStorage hanya in-memory, cold start kembali ke halaman login) | Kesimpulan riset (perlu periksa `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`) | Perlu diverifikasi |
| E5 | 25 halaman HarmonyOS templated, hanya daftar read-only name/code tanpa tambah/ubah/hapus | Sudah dicek OrderListPage.ets seluruh 65 baris: hanya daftar read-only name/code | Terkonfirmasi |
| E6 | Kedalaman cakupan frontend kurang (lihat A7/A8) | Sama seperti di atas | Perlu diverifikasi |

### Kelompok Kerja F: Pelapisan API dan tata kelola arsitektur (prioritas rendah, sesuai kemampuan)

| # | Kesenjangan | Jalur bukti | Status |
|---|------|----------|------|
| F1 | /api berversi hanya 3 controller, bisnis semuanya di blok tunggal /admin | `app/api/v1/controller/` hanya Captcha/Auth/Product tiga | Terkonfirmasi |
| F2 | 10 modul controller langsung query model tanpa lapisan service | Kesimpulan riset (controller crm/product dll. langsung menggunakan query model) | Sebagian terkonfirmasi (menunggu audit penuh) |
| F3 | purchase/sales meng-hardcode `new` service alih-alih dependency injection | Bukti B5 | Terkonfirmasi |

---

## 3. Perencanaan Bertahap

Dibagi tiga batch berdasarkan prioritas (P0→P1→P2), **setiap periode dapat dirilis independen, semua kriteria penerimaan dapat dikuantifikasi**. Total durasi sekitar **8~9 minggu** (asumsi paralelisme: estimasi dengan **2~3 pengembang paralel + kolaborasi tim agent**; total tugas tunggal sekitar **77 hari kerja (person-day)** — P0 ≈12.5d、P1 ≈29.5d、P2 ≈35d — jika dieksekusi serial satu orang dibutuhkan sekitar 15 minggu. Dasar paralelisme: tugas backend kecil A1/A4/A5 saling independen dapat paralel; tes B1 per modul dapat dipecah menjadi subtugas paralel; kelompok B/C dan E/D dapat tumpang tindih lintas periode; tugas frontend Flutter/HarmonyOS tidak saling memblokir dengan tugas backend; dependensi eksplisit antar tugas lihat §5).

**Sistem penomoran**: nomor tugas bertahap berkorespondensi satu-ke-satu dengan nomor kesenjangan §2 (A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3); di antaranya D6/D7 (terminologi controller dan migrasi) digabung ke tugas D3, E2 (pernyataan README yang tidak sesuai fakta) digabung ke penerimaan E1, E6 (kedalaman cakupan) digabung ke A7-2, F1 (/api berversi) secara eksplisit tidak dikerjakan periode ini (lihat §6); selain itu ada tugas i18n yang berkorespondensi dengan riset "Flutter i18n belum selesai", bukan nomor tabel kesenjangan.

### 3.1 Batch pertama P0: Baseline loop tertutup (minggu 1~2)

**Tujuan**: menghilangkan dead endpoint dan data palsu, menjadikan kemampuan yang ada tetapi belum terhubung (TenantScope/queue/WebSocket) dapat digunakan atau secara eksplisit diturunkan.

| Tugas | Isi | Cakupan | Kriteria penerimaan | Durasi |
|------|------|----------|----------|------|
| A1 | Perbaiki notifikasi "tandai semua sebagai dibaca": frontend ganti memanggil `POST /admin/notification/read-all` (atau backend menambah rute alias, pilih salah satu, rekomendasi ganti frontend) | `notification_page.dart` + `config/route.php` | Panggilan manual/otomatis berhasil; tambah 1 assertion PHPUnit bahwa rute tersebut ada | 0.5d |
| A2 | Dashboard hubungkan data nyata: hapus Dio independen, ganti melalui ApiService (interceptor JWT); tiga Tab OMS/WMS/TMS panggil `/dashboard/oms\|wms\|tms`; hapus nilai palsu hardcoded; pertahankan semantik cache Redis 5m | `dashboard_controller.dart` + halaman terkait | Dalam status login, tiga Tab dashboard menampilkan data nyata backend, Network panel terlihat 200 dan membawa header Authorization; hapus komentar mock | 2d |
| A3 | Hubungkan TenantScope: daftarkan ke grup rute `/admin`; ID tenant diambil dari klaim JWT atau header `X-Tenant-Id` (**titik keputusan**, lihat §5); trait model sudah siap tanpa perubahan besar | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | Data dua tenant saling tidak terlihat (tambah tes integrasi); saat header tenant tidak dikirim mengembalikan 400 alih-alih membiarkan lewat diam-diam; **opsi penurunan**: jika dinilai waktunya belum tepat, ubah menjadi dokumentasi eksplisit "multi-tenant adalah kemampuan cadangan" dan berikan langkah pengaktifan, penerimaan = dokumen konsisten dengan kode | 2d |
| A4 | Queue end-to-end: config/process.php tambah proses konsumen `redis-queue` (driver default redis); tambah satu smoke task yang dapat diamati (mis. menulis log operasi asinkron); dokumentasikan langkah beralih ke rabbitmq | `config/process.php`、`app/queue/` | Setelah start proses konsumen online (`php start.php status`); setelah mengirim smoke task efek samping target muncul dalam 5s | 1d |
| A5 | Autentikasi WebSocket: validasi JWT pada pembentukan koneksi/pesan `auth` (gunakan kembali logika AdminAuth), token ilegal mengembalikan auth_result:false dan putus; sinkronkan dokumentasi | `app/process/WebSocket.php` + titik koneksi frontend | Koneksi tanpa/palsu token ditolak; koneksi token sah berhasil; tambah 1 tes yang mencakup | 1d |
| A6 | Perbaiki paginasi HarmonyOS: 25 interpolasi tanda kutip tunggal ganti template string/penggabungan; page increment + load saat scroll bawah + pull-to-refresh; ekstrak komponen paginasi terpadu | `apps/harmonyos/entry/src/main/ets/pages/**`（25 file） | grep seluruh repo tanpa sisa pola `${this.page}` tanda kutip tunggal; parameter permintaan pergantian halaman benar; build lolos | 2d |
| A7-1 | Dead endpoint seluruhnya bersih: gunakan matriks cakupan riset sebagai dasar, jalankan sekali perbandingan otomatis "URL frontend × rute backend" (skrip mengekstrak string permintaan Flutter/HarmonyOS vs `config/route.php`), keluarkan daftar sisa perbedaan | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | Artefak skrip perbandingan masuk repo (docs/); dalam daftar perbedaan "frontend memanggil tetapi backend tidak ada" menjadi nol (yang tidak ada tetapi wajar ditandai whitelist) | 2d |
| A8-1 | Lengkapi kolom form bernilai tinggi: halaman pesanan pembelian/penjualan, voucher pembukuan lengkapi kolom kunci bisnis (jumlah/tanggal/unit bisnis/baris detail), hanya melengkapi, tanpa form engine | Halaman Flutter terkait | Form dapat membuat dokumen lengkap dengan kolom bisnis, interface 200 | 2d |

**Ringkasan penerimaan P0**: A1~A6 seluruhnya terimplementasi; daftar dead endpoint menjadi nol; CI semua hijau; tanpa drift dokumen baru (perubahan disinkronkan memperbarui daftar fitur docs/CLAUDE.md).

### 3.2 Batch kedua P1: Baseline tes dan keamanan (minggu 3~5)

**Tujuan**: sistem pengujian naik dari "unit test murni" menjadi "unit test + integrasi + coverage", kelemahan keamanan menjadi nol.

| Tugas | Isi | Cakupan | Kriteria penerimaan | Durasi |
|------|------|----------|----------|------|
| B1 | 11 modul bisnis lengkapi tes: tulis tes lapisan service/model per modul, mencakup CRUD + aksi inti (penyelesaian, alur persetujuan, proses QC, work order peralatan dll.) | `tests/`（tambah file tes crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow） | Tambah ≥150 tests / ≥500 assertions; 11 modul masing-masing ≥10 tests; `vendor/bin/phpunit` semua hijau | 2w |
| B2 | Tes integrasi: manfaatkan services mysql8/redis7 yang sudah ada di CI, tambah kelompok tes integrasi (CRUD database nyata + rollback transaksi + verifikasi isolasi TenantScope + smoke queue) | `tests/Integration/` + pengelompokan `phpunit.xml` | Kelompok integrasi hijau di CI; lokal dapat dijalankan dengan `--group=integration` | 1w |
| B3 | Smoke E2E: HTTP nyata menembus health→login→CRUD inti→dashboard, dibuat skrip | `tests/E2E/`（skrip curl/php） | Job CI baru menjalankan 10 jalur inti, gagal langsung merah | 2d |
| B4 | Coverage: pasang phpunit --coverage, tetapkan ambang (lapisan bisnis ≥40%, keseluruhan ≥30%, perlu diverifikasi apakah CI mendukung pengumpulan xdebug) | `phpunit.xml`、`ci.yml` | CI menghasilkan laporan coverage; di bawah ambang gagal | 1d |
| B5 | Controller layanan (4 modul frekuensi tinggi): controller finance/inventory/sales/purchase hapus `new`, ganti ambil service dari container (`support\Container`), membuka jalan untuk tes B1 | `app/controller/{finance,inventory,sales,purchase}/**` | Tanpa sisa `new InventoryService/FinanceService`; tes yang ada semua hijau | 3d |
| C1 | Kunci lemah menjadi nol: `.env.docker`/`.env.example` ganti placeholder acak + validasi ketat saat start (hilang/sama dengan placeholder tolak start); CI tambah langkah `env 校验` | `.env*`、`config/*.php`、`ci.yml` | Start dengan `change-me` langsung gagal dan memberi petunjuk; instance Docker baru otomatis menghasilkan kunci acak | 1d |
| C2 | Perluasan validasi ketat variabel lingkungan: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD masuk env_required (periksa dulu status config/jwt.php, perlu diverifikasi) | `config/*.php` | Kurang salah satu kunci kritis start gagal, pesan error bahasa Mandarin jelas | 1d |
| C3 | Audit fail-open: grep catch kosong/catch tanpa log, ubah menjadi fail-closed + log (termasuk TraceId) | seluruh app/ | Daftar audit masuk repo; item perbaikan semuanya didukung tes atau log | 2d |
| C4 | Tata kelola migrasi: lengkapi `database/backup/backup-validator.sh` (verifikasi pemulihan otomatis setelah backup) + 29 `_rollback.sql` per migrasi (susun mundur struktur tabel sesuai install.sql) | `database/` | Skrip validator berjalan pada file backup (backup→restore→bandingkan jumlah tabel/baris); setiap file migrasi memiliki `_rollback.sql` bernama sama di sebelahnya | 2d |
| C5 | Wujudkan kanal notifikasi (sesuai kesenjangan C5): setidaknya buka satu kanal yang dapat digunakan (rekomendasi email: implementasi pengiriman driver SMTP atau driver log file); jika dinilai waktunya belum tepat, secara eksplisit dokumentasikan penurunan menjadi "hanya pesan internal + titik adaptasi email/wecom/dingtalk cadangan" dan berikan langkah integrasi (pilih salah satu, harus ada keputusan eksplisit) | `app/service/notification/ChannelRouter.php` + kelas driver baru + docs | Driver email: setelah pengiriman notifikasi berhasil ChannelRouter mengembalikan true (tes menggunakan driver log untuk assertion); jika diturunkan: komentar ChannelRouter.php:23 dan docs secara eksplisit menandai status "cadangan", menghilangkan ambiguitas "stub for future implementation" | 1.5d |
| C6 | Metrik monitoring tambahan: penumpukan queue (redis LLEN), jumlah koneksi WebSocket online | `MetricsController.php` | `/metrics` mengeluarkan 2 gauge baru | 1d |

**Ringkasan penerimaan P1**: total tes ≥287 (137+150); laporan coverage dihasilkan dan melewati ambang; start gagal dengan kunci lemah/kurang; validator dan skrip rollback tersedia; setidaknya satu kanal notifikasi dapat digunakan atau penurunan didokumentasikan eksplisit; job integrasi/E2E/coverage baru di CI semua hijau.

### 3.3 Batch ketiga P2: Dokumen, matriks versi, dan kedalaman frontend (minggu 6~8)

**Tujuan**: angka dokumen sepenuhnya selaras dengan fakta kode (validasi otomatis), matriks versi kembali dapat dipercaya, frontend melengkapi kedalaman bernilai tinggi.

| Tugas | Isi | Cakupan | Kriteria penerimaan | Durasi |
|------|------|----------|----------|------|
| D1 | Sinkronisasi tiga cabang: main digabung ke lite/standard/full, selesaikan konflik, CI tiga cabang semua hijau; **titik keputusan**: setelah ini gunakan strategi "main sebagai satu-satunya sumber pengembangan, cabang versi hanya cherry-pick saat rilis" | git tiga cabang + ci.yml | behind tiga cabang = 0; CI masing-masing tiga cabang hijau; catatan penyelesaian konflik tersimpan | 1w |
| D2 | Tulis ulang EDITIONS.md: berdasarkan hasil terukur (jumlah tabel/controller/modul diambil dari skrip hitung kode), hapus bagian yang saling bertentangan | `docs/EDITIONS.md` | Semua angka dokumen konsisten dengan output skrip | 1d |
| D3 | Otomatisasi statistik dokumen: tulis `scripts/doc-stats.sh` (hitung controller/service/model/migrasi/tes/middleware + output phpunit), lampiran FUNCTIONS.md ganti merujuk outputnya; sekaligus samakan D6 (terminologi controller 104/121/122) dan D7 (terminologi migrasi 22/29/30) menjadi satu-satunya terminologi skrip | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | Output skrip konsisten dengan dokumen; semua angka di README/docs dapat direproduksi skrip (termasuk penyatuan terminologi controller/migrasi) | 2d |
| D4 | Koreksi matriks kelengkapan: item yang benar-benar sudah diimplementasikan seperti QMS/EAM/DMS/BI ubah ✅, sertakan bukti kode | `docs/FUNCTIONS.md` | Matriks berkorespondensi satu-ke-satu dengan direktori `app/controller/`, tanpa salah posisi 🔴/✅ | 1d |
| D5 | Job validasi dokumen CI: jalankan doc-stats dan bandingkan dengan dokumen, drift langsung merah | `ci.yml` + skrip | Ubah satu angka lalu CI menjadi merah (demonstrasi uji sendiri) | 1d |
| E1 | Job Flutter CI: flutter analyze + flutter test + build web, masukkan ke ci.yml | `ci.yml`、`apps/flutter/` | Tiga langkah semua hijau; pernyataan README.md:635 konsisten dengan fakta | 1d |
| E3 | Perluasan tes Flutter: interceptor ApiService/refresh 401, alur AuthService, validasi form kunci, ≥20 tes widget/unit | `apps/flutter/test/` | `flutter test` semua hijau, ≥20 tests | 1w |
| E4 | Persistensi token HarmonyOS: AppStorage diwujudkan persisten + pemulihan cold start + logika refresh 401 (periksa dulu status ApiService, perlu diverifikasi) | `apps/harmonyos/.../service/ApiService.ets` | Bunuh proses lalu restart tetap mempertahankan status login; token kedaluwarsa otomatis refresh | 2d |
| E5 | Halaman inti HarmonyOS lengkapi tambah/ubah/hapus: urutkan berdasarkan nilai (pembelian/penjualan/stok/keuangan/OMS masing-masing ambil 2~3 halaman daftar), setiap halaman lengkapi aksi 新建/编辑/删除 dan form | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | ≥10 halaman daftar terpilih memiliki tambah/ubah/hapus dan terhubung ke backend; build hvigor lolos (tanpa lingkungan SDK HarmonyOS tandai "menunggu lingkungan CI siap") | 1w |
| i18n | i18n minimal Flutter (sesuai riset "Flutter i18n belum selesai"): pesan error ApiService dan teks kunci login/navigasi/dashboard masuk i18n (file arb, terhubung dengan `app/common/I18n.php` backend); **hanya minimum viable, tanpa pengubahan teks semua halaman** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | Pesan error kunci dan ≥10 teks halaman dapat berpindah bahasa (en/zh); `flutter test` semua hijau | 2d |
| A7-2 | Cakupan kedalaman frontend: sesuai daftar perbandingan A7-1, lengkapi halaman endpoint kunci seperti penyelesaian pembelian/penjualan, tiga laporan keuangan/pelunasan akhir periode/rekening bank, CRM follow/funnel/alur kontrak | `apps/flutter/lib/app/pages/**` | Item prioritas tinggi dalam daftar perbandingan "backend ada tetapi frontend belum mencakup" (penyelesaian/tiga laporan/pemenuhan/persetujuan/gaji) menjadi nol | 1w |
| F2/F3 | Ekstraksi ringan lapisan service (opsional, sesuai kemampuan): untuk 3~5 modul terberat query model langsung, ekstrak lapisan service tipis + dependency injection; **eksplisit tidak memaksa refactor penuh** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Controller modul terekstrak tanpa query model langsung; tes yang ada semua hijau; modul tidak terekstrak didokumentasikan "controller query model langsung, utang teknis diketahui" | 1w |

**Ringkasan penerimaan P2**: tiga cabang sinkron dan CI hijau; angka docs dapat direproduksi skrip; CI berisi job Flutter dan validasi dokumen; Flutter ≥20 tes; HarmonyOS persistensi + ≥10 halaman tambah/ubah/hapus; cakupan endpoint prioritas tinggi menjadi nol.

---

## 4. Kriteria Penerimaan (Ringkasan, semuanya dapat diverifikasi)

- **Endpoint**: A1 endpoint notifikasi, A2 `/dashboard/oms|wms|tms`, A7 endpoint prioritas tinggi semuanya dapat di-curl dengan JWT dan mengembalikan 200/data bisnis.
- **Tes**: `vendor/bin/phpunit` semua hijau (≥287 tests); `flutter test` semua hijau (≥20); job integrasi/E2E hijau di CI.
- **Keamanan**: start gagal dengan kunci `change-me`; token ilegal WebSocket ditolak; tanpa catch kosong menelan error diam-diam (daftar audit).
- **Kanal/i18n**: setidaknya satu kanal notifikasi dapat digunakan atau penurunan didokumentasikan eksplisit; pesan error kunci Flutter dan ≥10 teks dapat beralih Tiongkok-Inggris (minimum viable).
- **CI**: semua job `.github/workflows/ci.yml` hijau (matriks PHP + integrasi + coverage + flutter + validasi dokumen).
- **Dokumen**: output `scripts/doc-stats.sh` konsisten dengan semua angka docs (drift langsung CI merah).
- **Cabang**: `git rev-list --left-right --count main...lite|standard|full` semuanya `0 0`.
- **Frontend**: HarmonyOS tanpa sisa `${this.page}` tanda kutip tunggal; cold start mempertahankan login; halaman inti tambah/ubah/hapus terhubung ke backend.

---

## 5. Dependensi dan Risiko

**Hubungan dependensi**:
- Kelompok A (loop tertutup) → Kelompok B (tes): tes B1/B2 harus mengarah ke endpoint yang **benar-benar dapat digunakan**, maka P0 memperbaiki dead endpoint dan penghubungan dulu, P1 baru melengkapi tes.
- B5 (controller layanan) → B1 (tes): **hanya membuka jalan untuk tes empat modul yang dicakup finance/inventory/sales/purchase** (setelah `new` hardcoded dihapus service dapat diinjeksi mock; purchase/sales termasuk modul nol tes, finance/inventory sudah ada tes dapat sekaligus diperbaiki); tes modul nol tes lainnya (crm/eam/dms/quality/project/product/bi/report/workflow) **tidak bergantung** pada B5, dapat berjalan paralel dengan B5.
- D1 (sinkronisasi cabang) → D3/D5 (validasi dokumen): setelah sinkron, main sebagai satu-satunya sumber kebenaran, terminologi dokumen baru dapat unik.
- E1 (Flutter CI) → E3 (perluasan tes): dulu ada quality gate, baru perluasan tes memiliki makna perlindungan.

**Risiko dan mitigasi**:
| Risiko | Dampak | Mitigasi |
|------|------|------|
| Penghubungan TenantScope memengaruhi semua query /admin, mungkin menimbulkan regresi visibilitas data | Tinggi | Tes integrasi didahulukan; ambil tenant dari klaim JWT (tanpa perubahan frontend); atau dalam P0 turunkan menjadi "dokumentasi tandai cadangan" dengan keputusan eksplisit |
| Konflik merge sinkronisasi tiga cabang, mungkin menimbulkan regresi | Sedang-tinggi | Main hijau dulu; setelah merge CI tiga cabang masing-masing hijau baru dapat diserahkan; catatan penyelesaian konflik tersimpan |
| Proses konsumen queue tidak tersedia di sebagian lingkungan (rabbitmq) | Sedang | Default driver redis (CI sudah punya redis7), rabbitmq hanya dokumentasikan langkah beralih |
| Perubahan autentikasi WebSocket merusak klien yang ada | Sedang | Frontend-backend dimodifikasi bersama dalam satu milestone; token ilegal ditolak tetapi tidak memengaruhi sesi sah |
| Matriks cakupan/daftar kolom form adalah kesimpulan riset, sebagian "perlu diverifikasi" | Sedang | A7-1 kerjakan skrip perbandingan otomatis dulu, hasil skrip sebagai patokan, jangan menambah halaman berdasarkan kesan |
| Cakupan refactor lapisan service tidak terkendali | Sedang | Eksplisit hanya ekstrak 3~5 modul, tidak memaksa penuh; tidak melakukan /api berversi penuh (F1 tidak dikerjakan periode ini) |
| Ambang coverage tidak tersedia di lingkungan CI (xdebug belum terpasang) | Rendah | Dulu hasilkan laporan lokal + ambang dokumentasi, kemampuan pengumpulan CI "perlu diverifikasi" setelahnya baru dihubungkan |
| CI HarmonyOS (hvigor) memerlukan SDK HarmonyOS, lingkungan CI publik mungkin tidak memilikinya | Sedang | Tandai "menunggu lingkungan CI siap"; verifikasi build lokal sebagai patokan, tidak memblokir tugas lain |

---

## 6. Eksplisit Tidak Dikerjakan

Melanjutkan item pengecualian roadmap §12, kecuali muncul alasan kuat (perlu review dan pendirian proyek terpisah):
- ❌ Pemisahan microservice / deployment K8s (eksperimen tetap di `.claude/worktrees/microservices-split/`, tidak digabung ke main)
- ❌ Kemampuan AI/ML (prediksi, rekomendasi cerdas, NLP)
- ❌ App native (iOS/Android native) — Flutter sudah mencakup semua platform
- ❌ Interface GraphQL
- ❌ Integrasi perangkat keras (IoT/scan barcode/print langsung)
- ❌ Solusi komersialisasi multi-tenant lengkap (penagihan SaaS, aktivasi mandiri tenant) — periode ini hanya penghubungan minimal atau dokumentasi cadangan
- ❌ /api berversi penuh (F1) — sisi bisnis tetap di /admin, hanya dicatat sebagai utang arsitektur
- ❌ Refactor penuh lapisan service dan pengerjaan ulang penuh form — ekstraksi berdasarkan nilai, tanpa refactor "big bang"
- ❌ Pelengkapan penuh halaman HarmonyOS — hanya melengkapi tambah/ubah/hapus halaman inti bernilai tinggi
- ❌ Perubahan penuh teks i18n Flutter — periode ini hanya minimum viable (pesan error + ≥10 teks kunci), multi-bahasa semua halaman diserahkan ke versi berikutnya

---

## 7. Saran Milestone

| Milestone | Waktu | Isi | Kriteria keluar |
|--------|------|------|----------|
| **M1 Baseline loop tertutup** | Akhir minggu 2 | Kelompok A seluruhnya: dead endpoint nol, dashboard data nyata, TenantScope/queue/WebSocket terimplementasi, perbaikan paginasi HarmonyOS | Ringkasan penerimaan P0 semua lolos |
| **M2 Baseline kualitas** | Akhir minggu 5 | Kelompok B seluruhnya + item keamanan kelompok C: tes 11 modul, integrasi/E2E/coverage, kunci lemah nol, audit fail-open, tata kelola migrasi, kanal notifikasi | Ringkasan penerimaan P1 semua lolos |
| **M3 Kualitas frontend** | Akhir minggu 6 | Kelompok E: job Flutter CI + perluasan tes, persistensi token HarmonyOS dan tambah/ubah/hapus halaman inti | CI flutter hijau, persistensi efektif, ≥10 halaman tambah/ubah/hapus |
| **M4 Tata kelola versi dan dokumen** | Akhir minggu 7 | Kelompok D: sinkronisasi tiga cabang, penulisan ulang EDITIONS/FUNCTIONS, otomatisasi doc-stats + validasi CI | Cabang sinkron, drift dokumen langsung merah |
| **M5 Cakupan kedalaman** | Akhir minggu 8 | A7-2 kedalaman frontend + ekstraksi ringan lapisan service kelompok F | Cakupan endpoint prioritas tinggi nol, modul terekstrak tanpa query model langsung |
| **M6 Rilis 1.1** | Akhir minggu 9 | Regresi penuh, catatan rilis (CHANGELOG), verifikasi akhir dokumen, arsip | Semua kriteria keluar milestone lolos (indikator keras): total tes ≥287 dan phpunit semua hijau, laporan coverage melewati ambang, semua job ci.yml hijau (matriks PHP+integrasi+coverage+flutter+validasi dokumen), sinkronisasi tiga cabang 0 0, daftar dead endpoint nol, mekanisme drift doc-stats merah efektif; verifikasi akhir CHANGELOG dan dokumen lolos; review ulang peninjau hanya sebagai referensi, tanpa ambang skor |

---

## Lampiran: File kunci yang sudah diverifikasi melalui pengecekan sampel dalam perencanaan ini

- `config/middleware.php`、`config/route.php`（:231-233 endpoint dashboard、:248-251 rute notifikasi、:387-415 pengelompokan middleware）
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php`（:23、:47-50）
- `app/service/notification/ChannelRouter.php`（:23 stub）
- `app/controller/sales/DeliveryController.php`（:142-143）、`app/controller/purchase/ReceiveController.php`（:142-143，dua file instansiasi `new` ada di sini; deklarasi `use` di :15-16）
- `app/api/v1/controller/`（hanya 3 controller）
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（statistik mock + Dio independen）
- `apps/flutter/lib/app/pages/notification/notification_page.dart`（:43 dead endpoint）
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets`（:24 bug interpolasi）
- `tests/`（daftar 19 file tes）、`vendor/bin/phpunit` terukur 137/805
- `phpstan-baseline.neon`（974 message）
- `.github/workflows/ci.yml`（hanya job PHP）、`README.md`（:635 pernyataan tidak sesuai fakta）
- `.env.docker`（kunci lemah）、`database/migrations/`（29 file，tanpa _rollback）
- `docs/EDITIONS.md`（saling bertentangan）、`docs/FUNCTIONS.md`（drift lampiran）、`docs/CLAUDE.md`（terminologi 104 vs 122 controller terukur）
- cabang git `lite/standard/full`（behind 41/41/20）

> Penjelasan terminologi: controller terukur `find app -path '*/controller/*.php'` = 122 (termasuk admin 14 + api 3 + controller bisnis + Index/Install); terminologi riset 121, terminologi bisnis docs/CLAUDE.md 104, perbedaan ketiganya berasal dari cakupan statistik berbeda, sudah didaftarkan di D6 sebagai item tata kelola untuk menyatukan terminologi.
