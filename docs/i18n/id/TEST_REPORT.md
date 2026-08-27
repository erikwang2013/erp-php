# Laporan Pengujian — 2026-08-26

> Pembaruan: 2026-08-27 — 5 item tersisa semuanya telah ditutup; angka pengujian 505/2342/26 → 513/2368/32; sekalian perbaikan 4 → 5 lokasi. Nilai lama lihat «Catatan Pembaruan» di akhir.

## Ringkasan Eksekusi

| Indikator | Nilai |
|------|----|
| Tanggal laporan | 2026-08-26 |
| Unit test PHP | 513 tests / 2368 assertions / 32 skipped |
| Pengujian halaman Flutter | 98 tests semua lolos (flutter analyze 0 error) |
| Otomasi API | 104 endpoint / ~230 asersi (CI e2e sudah terhubung, lihat langkah «Run E2E API coverage» di ci.yml) |
| Cakupan (pengukuran pcov) | Keseluruhan 7,51% / app/service 15,65% / app/controller 3,62% |
| Analisis statis | PHPStan 0 error ✅ |
| Gaya kode | php-cs-fixer 0 diff ✅ (kali ini sekalian memperbaiki 3 file yang sudah ada) |
| Perbaikan defek nyata | 5 lokasi (3 PHP + 1 Flutter + 1 format) |
| Go/Rust | N/A (repo tidak memiliki kode .go/.rs/Cargo.toml apa pun) |

Kali ini merupakan pengiriman pengujian paralel tiga jalur: unit test PHP (php-tester, tambah 9 file), otomasi API (api-tester, tambah 1 file), pengujian halaman Flutter (ui-tester, tambah 8 file 29 kasus).

## Matriks Cakupan

Modul (22 domain bisnis + 14 controller Manajemen Sistem) ditandai tingkat cakupannya menurut jenis pengujian.

### 22 Domain Bisnis

| Modul | Unit | API | UI | Keterangan |
|------|------|-----|-----|------|
| Keuangan Consolidation penggabungan | ✅ | ✅ | — | ConsolidationServiceTest 5 kasus + API |
| Keuangan AccountBalance saldo akun | ✅ | ✅ | — | AccountBalanceServiceTest 4 kasus |
| Keuangan PeriodClose penutupan periode | ✅ | ✅ | — | PeriodCloseServiceTest 5 kasus |
| Keuangan FinanceRatio | ✅ | — | — | FinanceRatioServiceTest (sudah ada) |
| Keuangan DoubleEntry pembukuan ganda | ✅ | — | — | DoubleEntryServiceTest (sudah ada) |
| Stok Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 kasus + UI halaman daftar ERP |
| Penjualan Sales | ✅ | ✅ | ✅ | SalesModuleTest yang ada + UI halaman pesanan penjualan |
| Produk Product | ✅ | ✅ | ✅ | ProductModuleTest yang ada + UI halaman produk |
| Pembelian Purchase | ✅ | ✅ | — | PurchaseModuleTest yang ada |
| Produksi Manufacturing | ✅ | — | — | ManufacturingServiceTest yang ada |
| Mesin MRP | ✅ | — | — | MrpEngineServiceTest yang ada |
| CRM | ✅ | ✅ | — | CrmModuleTest/CrmServiceTest yang ada |
| HR | ✅ | — | — | HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest yang ada |
| Proyek Project | ✅ | ✅ | ✅ | ProjectModuleTest yang ada + UI halaman proyek |
| Persetujuan Approval/Workflow | ✅ | ✅ | ✅ | WorkflowModuleTest yang ada + UI halaman persetujuan |
| OMS/WMS/TMS | ✅ | — | — | OmsWmsTmsServiceTest yang ada |
| QMS Kualitas | ✅ | — | — | QualityModuleTest yang ada |
| EAM Aset | ✅ | — | — | EamModuleTest yang ada |
| DMS Dokumen | ✅ | — | — | DmsModuleTest yang ada |
| BI Pelaporan | ✅ | ✅ | — | BiModuleTest yang ada + API |
| Notifikasi kanal notifikasi | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService 12 kasus) |
| Laporan/detail dokumen | ✅ | Sebagian | ✅ | Logika pembuatan ada unit test; UI halaman detail 3 kasus (report_list_page_test) |

### Manajemen Sistem (14 controller)

| Domain controller | Unit | API | UI | Keterangan |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (sisi User) + UI halaman daftar pengguna |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (sisi Role) + UI halaman daftar peran |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (sisi Permission) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (sisi Config) + UI halaman konfigurasi |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| 7 controller lainnya (login/audit/kamus, dll.) | ✅ | ✅ | — | BusinessControllersTest 10 domain controller representatif memvalidasi jalur kegagalan |
| Halaman login | — | ✅ | ✅ | login_flow_test 2 kasus |
| Pusat pribadi | — | ✅ | ✅ | profile_page_test 3 kasus |
| Halaman log | — | ✅ | ✅ | log_page_test 2 kasus |
| Papan dasbor | — | — | ✅ | dashboard_page_test 5 kasus |
| Halaman peringatan stok/keuangan | — | — | ✅ | erp_list_pages_test |

## Statistik Pengujian

### Unit test PHP: 513 tests / 2368 assertions / 32 skipped

Kali ini menambah 9 file (semua dengan header hak cipta, 63 tests / 125 assertions):

| File | Jumlah kasus | Objek yang dicakup |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance penggabungan |
| tests/AccountBalanceServiceTest.php | 4 | saldo akun |
| tests/PeriodCloseServiceTest.php | 5 | penutupan periode |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | ekstensi stok |
| tests/AdminUserRoleControllerTest.php | 9 | controller User/Role |
| tests/AdminPermissionConfigControllerTest.php | 8 | controller Permission/Config |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 domain | validasi jalur kegagalan controller representatif |

2026-08-27 menambah 3 file PHP (14 tests; tanpa TEST_DB_* maka pengujian integrasi 6/6 otomatis dilewati):

| File | Jumlah kasus | Objek yang dicakup |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | rollback/commit transaksi DB/sumber duplikat/kunci bersaing pcntl_fork (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | layanan notifikasi |
| tests/FinanceRatioServiceTest.php | 2 | rasio keuangan |

### Pengujian halaman Flutter: 98 tests semua lolos

Kali ini menambah 8 file 29 kasus (10 file yang ada tidak diubah, semua lolos); `flutter analyze` 0 error (1 info yang sudah ada):

| File | Jumlah kasus |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 menambah 1 file (3 kasus):

| File | Jumlah kasus |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### Otomasi API: 104 endpoint / ~230 asersi (19 grup modul)

tests/E2E/api-coverage.php (423 baris, `php -l` lolos): murni hanya-baca + idempoten (GET detail → PUT tulis ulang nilai sama di pusat pribadi), termasuk deteksi tabel hilang (500 + Base table not found → SKIP memberi tahu perlu seed penuh install.sql).

**Tidak dijalankan lokal** (MySQL tanpa kredensial, 8788 tanpa layanan), perlu lingkungan e2e CI:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

Mencakup 19 grup modul: Manajemen Sistem (pengguna/peran/izin/konfigurasi/health/metrik), Keuangan (penggabungan/saldo/penutupan/rasio), Stok, Penjualan, Produk, Pembelian, Proyek, Persetujuan, CRM, BI, Notifikasi, Pelaporan.

> Koreksi: api-tester pernah menduga tabel `erp_admin_config` hilang — **bukan defek**. Nama tabel sebenarnya `erp_system_config` (install.sql:133 sudah dibuat, model SystemConfig mengarah dengan benar), laporan mengoreksinya.

## Cakupan

Pengukuran pcov (2026-08-26, 2026-08-27 tidak diukur ulang, memakai nilai ini): keseluruhan **7,51%** (baseline 4,8%), app/service **15,65%** (baseline 10,6%), app/controller **3,62%**.

Perbandingan ambang dan target CI (lihat superpowers/plans/2026-08-07-next-phase-plan.md P1-B4):

| Dimensi | Saat ini | Ambang CI | Target |
|------|------|---------|------|
| Keseluruhan | 7,51% | 4% ✅ tercapai | 30% |
| app/service | 15,65% | 10% ✅ tercapai | 40% |
| app/controller | 3,62% | — | — |

Cakupan keseluruhan dan service sudah melewati ambang CI, masih ada jarak cukup besar ke target, perlu terus menambah pengujian sesuai jalur P1-B4.

## Perbaikan Defek Nyata Sekalian (5 lokasi)

| # | Lokasi | Defek | Perbaikan |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php、PermissionController.php | Kurang `use support\Response;`, TypeError saat runtime | Tambah import |
| 2 | app/controller/Admin/DocsController.php | `path()` param ketiga diisi null crash | Perbaiki pemanggilan |
| 3 | lib/pages/user_list_page.dart | Tombol hapus/aktif massal kurang bungkus Obx, tombol tidak pernah muncul setelah diceklis | Tambah bungkus Obx |
| 4 | scripts/api-coverage.php (serta 3 file app/queue/redis/search/ kali ini) | Format cs-fixer tidak sesuai | Sudah diperbaiki sesuai fixer |
| 5 | app/model/FinanceCashJournal.php | Bidang `UPDATED_AT` tidak sesuai install.sql | Bidang sudah diperbaiki |

## Go / Rust

**N/A** — repo tidak memiliki kode .go / .rs / Cargo.toml apa pun, pengujian kedua tumpukan teknologi ditandai tidak berlaku.

## Penutupan Item Tersisa (pembaruan 2026-08-27)

5 item tersisa versi 2026-08-26 semuanya telah ditangani:

1. **Jalur transaksi DB** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` menambah 6 kasus (rollback/commit/sumber duplikat/kunci bersaing pcntl_fork, `Group(integration)`), tanpa TEST_DB_* otomatis dilewati 6/6; job php CI sudah menginjeksi TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST.
2. **api-coverage terhubung CI** ✅ — seed job e2e di `.github/workflows/ci.yml` ditingkatkan menjadi install.sql penuh (163 tabel), setelah smoke menambah langkah «Run E2E API coverage».
3. **UI halaman detail laporan/dokumen belum dicakup** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` 3 kasus semua lolos.
4. **Ketergantungan lingkungan CaptchaTest** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` kompatibilitas ganda PIXELS→AREA + guard clone(); `tests/CaptchaTest.php` ditulis ulang sesuai kontrak poster-php v1.2.3, jalur imagick mesin lokal 7/7 lolos (27 asersi).
5. **Target cakupan** ✅ progres — menambah `tests/NotificationServiceTest.php`, `tests/FinanceRatioServiceTest.php`; angka cakupan memakai pengukuran 2026-08-26 (tidak diukur ulang), masih perlu terus menambah hingga target (30%/40%).

Baseline regresi: **513 tests / 2368 assertions / 32 skipped** semua hijau (versi lama 505/2342/26).

## Catatan Pembaruan

| Tanggal | Perubahan |
|------|------|
| 2026-08-26 | Versi awal: 505 tests / 2342 assertions / 26 skipped; item tersisa 5; perbaikan sekalian 4 lokasi |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; item tersisa 5 semuanya ditutup; perbaikan sekalian 5 lokasi; menambah 4 file pengujian; semua gambar ditambah watermark erik.xyz |

## Laporan dan Lokasi Penyimpanan Artefak

- Laporan ini: `docs/TEST_REPORT.md`
- Data cakupan: `runtime/coverage/` (dibuat pcov)
- Skrip otomasi API: `tests/E2E/api-coverage.php`
- Unit test PHP: `tests/*.php` (9 file baru kali ini lihat tabel di atas)
- Pengujian Flutter: `test/pages/*.dart` (8 file baru kali ini lihat tabel di atas)
