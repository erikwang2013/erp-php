# Peta Jalan Lengkap Ekosistem ERP — Spesifikasi Desain

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Disusun berdasarkan laporan audit ekosistem 2026-08-04, mencakup empat tahap prioritas P0~P3

---

## 1. Baseline Saat Ini

| Dimensi | Kondisi Sekarang | Skor |
|------|------|------|
| API Backend | 14 modul / 80+ controller / 120+ model, kerangka CRUD multi-modul | 85/100 |
| Pertahanan keamanan | 18 lapis pertahanan berlapis, CORS/SecurityFilter/RateLimit/JWT/enkripsi | 95/100 |
| UI Frontend | Flutter 12 halaman, HarmonyOS 9 halaman, mencakup sekitar 20% modul; panel admin Web tidak ada | 20/100 |
| Ekosistem operasional | Dockerisasi, CI selesai, kurang rollback migrasi, otomatisasi backup, observabilitas | 70/100 |
| Kedalaman bisnis | Struktur tabel modul keuangan/HR/manufaktur lengkap tetapi logika bisnis dominan CRUD | 55/100 |
| **Komprehensif** | | **65/100** |

---

## 2. Strategi Keseluruhan

```
Waterfall serial: P0 → P1 → P2 → P3
Sub-tugas yang memiliki independensi dalam setiap tahap dapat berjalan paralel
```

### 2.1 Pemilihan Teknologi Frontend

- **Panel admin Web**: Flutter Web, memakai ulang kode `apps/flutter` yang ada, gaya panel admin PC, manajemen status GetX
- **Mobile**: Flutter (iOS/Android), berbagi kode bisnis `apps/flutter/lib/app/` dengan Web
- **HarmonyOS**: ArkTS, menyelaraskan dengan set fitur Flutter

### 2.2 Strategi Backend

- **Tingkat industri** (kelas A): pembukuan berpasangan, perhitungan gaji, mesin MRP — algoritma lengkap, penanganan batas memadai, siap produksi
- **Inti yang dapat digunakan** (kelas B): manajemen kualitas, sistem notifikasi, papan BI — aturan kunci diimplementasikan, selanjutnya diiterasi sesuai kebutuhan

---

## 3. P0 — Ekosistem Frontend (3-4 minggu)

> **Tujuan**: membuat sistem memiliki antarmuka manajemen yang dapat digunakan, mencakup semua modul backend yang sudah diimplementasikan

### 3.1 Refactor Arsitektur Proyek Flutter

```
apps/flutter/lib/app/
├── main.dart                      # Entri, inisialisasi GetX + Dio
├── routes/
│   └── app_pages.dart             # Registrasi rute lengkap (dikelompokkan per modul)
├── layouts/
│   └── admin_layout.dart          # Tata letak tiga kolom PC (sidebar + topbar + konten)
├── theme/
│   └── app_theme.dart             # Tema Material 3 (warna merek #1677FF)
├── services/
│   ├── api_service.dart           # Singleton Dio + interceptor JWT + refresh otomatis
│   ├── auth_service.dart          # Manajemen status autentikasi
│   ├── captcha_service.dart       # Captcha klik
│   └── export_service.dart        # Unduh ekspor Excel/PDF
├── widgets/
│   ├── data_table_wrapper.dart    # Tabel data umum (paginasi/pencarian/operasi batch)
│   ├── form_dialog.dart           # Dialog form umum
│   ├── confirm_dialog.dart        # Dialog konfirmasi kedua (input kata sandi)
│   └── stat_card.dart             # Kartu statistik
└── pages/
    ├── login/                     # Halaman login
    ├── dashboard/                 # Papan dasbor (6 papan bergantian)
    ├── system/
    │   ├── user/                  # Manajemen pengguna (termasuk batch/impor)
    │   ├── role/                  # Peran + pohon izin
    │   ├── config/                # Konfigurasi sistem
    │   └── log/                   # Log operasi
    ├── product/                   # Produk/kategori/merek/SKU
    ├── partner/                   # Pemasok/pelanggan/gudang/lokasi
    ├── purchase/                  # Permintaan pembelian/pesanan/penerimaan/retur/penyelesaian
    ├── sales/                     # Penawaran penjualan/pesanan/pengiriman/retur/penyelesaian
    ├── inventory/                 # Stok/transaksi/transfer/opname/peringatan
    ├── finance/
    │   ├── voucher/               # Voucher pembukuan
    │   ├── ar_ap/                 # Piutang-hutang
    │   ├── receipt_payment/       # Penerimaan-pembayaran
    │   ├── ledger/                # Buku besar/buku pembantu
    │   ├── report/                # Tiga laporan (laba rugi/neraca/arus kas)
    │   ├── asset/                 # Aset tetap
    │   ├── tax/                   # Pajak
    │   ├── currency/              # Multi-mata uang/nilai tukar
    │   ├── budget/                # Anggaran
    │   └── cost_profit/           # Pusat biaya/laba
    ├── crm/
    │   ├── opportunity/           # Funnel peluang
    │   ├── contact/               # Kontak
    │   ├── pool/                  # Pool bersama
    │   ├── contract/              # Kontrak
    │   ├── quotation/             # Penawaran
    │   ├── campaign/              # Kampanye pemasaran
    │   ├── ticket/                # Tiket layanan
    │   └── analytics/             # Analisis pelanggan
    ├── oms/                       # OMS pesanan/pemenuhan/retur/kanal
    ├── wms/                       # WMS zona lokasi/penerimaan/putaway/gelombang/picking/pengepakan
    ├── tms/                       # TMS kurir/tarif/waybill/trajectory/penyelesaian
    ├── manufacturing/             # BOM/work order produksi/routing/stasiun kerja/MRP
    ├── hr/                        # Departemen/karyawan/posisi/absensi/cuti/gaji
    ├── project/                   # Proyek/tugas/jam kerja
    ├── workflow/                  # Alur kerja persetujuan/persetujuan saya
    ├── notification/              # Pusat notifikasi
    ├── report/                    # Laporan kustom
    └── profile/                   # Pusat personal
```

### 3.2 Pengembangan Komponen Umum

| Komponen | Fungsi | Skenario Penggunaan |
|------|------|----------|
| `DataTableWrapper` | Paginasi/pengurutan/pencarian kata kunci/filter status/pemilihan batch/konfigurasi kolom | Semua halaman daftar |
| `FormDialog` | Rendering form dinamis/validasi bidang/submit/tutup | Semua dialog buat/edit |
| `ConfirmDialog` | Input konfirmasi kata sandi kedua | Semua operasi hapus |
| `StatCard` | Nilai/panah tren/judul | Papan dasbor |
| `BreadcrumbNav` | Navigasi breadcrumb | Halaman dalam |
| `FileUploader` | Upload tarik-lepas/progres/pratinjau | Impor/upload gambar |

### 3.3 Pelengkapan HarmonyOS

Selaraskan dengan set halaman Flutter, lengkapi: modul halaman OMS/WMS/TMS/manufaktur/HR/persetujuan/notifikasi/laporan.

### 3.4 Standar Penerimaan P0

- [ ] Panel admin Flutter Web mencakup seluruh 14 modul
- [ ] Semua halaman daftar CRUD dapat digunakan (paginasi/pencarian/filter)
- [ ] Semua form buat/edit dapat digunakan (validasi/submit)
- [ ] Operasi hapus dengan konfirmasi kata sandi kedua
- [ ] Refresh otomatis JWT tanpa terasa
- [ ] Adaptasi tata letak responsif PC/tablet/ponsel
- [ ] Jumlah halaman HarmonyOS ≥ 80% dari jumlah halaman Flutter

---

## 4. P1 — Kedalaman Bisnis (4-6 minggu)

> **Tujuan**: meng-upgrade modul inti dari kerangka CRUD menjadi mesin komputasi bisnis yang sebenarnya

### 4.1 Mesin Pembukuan Berpasangan Keuangan (tingkat industri)

```
app/service/finance/
├── DoubleEntryService.php        # Validasi keseimbangan debit-kredit + pembuatan entri otomatis
├── PeriodCloseService.php        # Penutupan akhir periode (peralihan laba rugi/peralihan biaya)
├── AccountBalanceService.php     # Ringkasan saldo akun (per bulan/per kuartal/per tahun)
├── ConsolidationService.php      # Laporan konsolidasi multi-mata uang (konversi nilai tukar)
└── FinancialRatioService.php     # Perhitungan otomatis rasio keuangan

app/controller/finance/
├── PeriodCloseController.php     # Operasi penutupan akhir periode
├── AccountBalanceController.php  # Kueri saldo akun
└── FinancialRatioController.php  # Kueri analisis rasio
```

**Aturan kunci**:
- Saat menyimpan voucher dipaksakan "ada debit pasti ada kredit, debit dan kredit harus sama"
- Voucher yang sudah diaudit tidak dapat dimodifikasi, perlu pembalikan tinta merah
- Penutupan akhir periode: saldo akun laba rugi → laba tahun berjalan, mendukung penutupan multi-langkah
- Multi-mata uang: konversi sesuai nilai tukar akhir periode, selisih kurs dihitung otomatis

### 4.2 Mesin Perhitungan Gaji (tingkat industri)

```
app/service/hr/
├── SalaryEngineService.php       # Mesin utama perhitungan gaji
├── SocialInsuranceService.php    # Perhitungan jaminan sosial (pensiun/kesehatan/pengangguran/cidera kerja/kelahiran)
├── HousingFundService.php        # Perhitungan dana perumahan
├── TaxCalculatorService.php      # Perhitungan tarif pajak penghasilan pribadi progresif
└── BankPayrollService.php        # Ekspor file pembayaran massal bank

app/controller/hr/
└── PayrollController.php         # Perhitungan/pembayaran/kueri gaji
```

**Aturan kunci**:
- Batas atas-bawah dasar jaminan sosial (disesuaikan setiap tahun per kota, dapat dikonfigurasi)
- Dasar dana perumahan + rasio iuran (5%-12%, dapat dikonfigurasi)
- Tabel tarif pajak penghasilan pribadi progresif (3%-45%, settlement tahunan)
- Format pembayaran massal bank: mendukung bank utama seperti ICBC/BOC/CCB/CMB
- Pembuatan slip gaji (termasuk semua rincian)

### 4.3 Mesin MRP (tingkat industri)

```
app/service/manufacturing/
├── MrpEngineService.php           # Mesin utama kalkulasi MRP
├── DemandForecastService.php      # Agregasi permintaan (pesanan + perkiraan + stok pengaman)
├── NetRequirementService.php      # Perhitungan kebutuhan bersih (kebutuhan kotor - dalam stok - dalam perjalanan)
├── BomExplosionService.php        # Ekspansi BOM (diperluas lapis demi lapis hingga bahan baku)
└── OrderSuggestionService.php     # Pembuatan pesanan yang disarankan (pembelian/produksi/subkontrak)

app/model/
├── MfgMrpRunLog.php              # Log kalkulasi MRP
└── MfgOrderSuggestion.php        # Pesanan yang disarankan
```

**Aturan kunci**:
- Ekspansi BOM lapis demi lapis, mempertimbangkan tingkat susut
- Kebutuhan bersih = kebutuhan kotor - stok yang ada - stok dalam perjalanan + jumlah yang dialokasikan + stok pengaman
- Kode lapisan rendah (LLC) memastikan material yang sama hanya dihitung sekali
- Lead time menghitung mundur tanggal pesanan yang disarankan
- Aturan lot: lot tetap/lot ekonomis/sesuai kebutuhan

### 4.4 Manajemen Kualitas (inti yang dapat digunakan)

```
app/controller/quality/
├── InspectionStandardController.php  # Standar inspeksi
├── IncomingCheckController.php       # IQC inspeksi bahan masuk
├── ProcessCheckController.php        # IPQC inspeksi proses
├── FinalCheckController.php          # OQC inspeksi pengiriman
└── NonconformityController.php       # Penanganan produk tidak sesuai

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 Sistem Notifikasi Real-time (inti yang dapat digunakan)

```
app/service/notification/
├── WebSocketService.php           # Manajemen koneksi WebSocket + push
├── ChannelRouter.php              # Routing multi-kanal (dalam aplikasi/email/WeCom/DingTalk)
├── TemplateRenderer.php           # Rendering template notifikasi

app/process/
└── WebSocket.php                  # Proses WebSocket

app/controller/notification/
├── WebSocketController.php        # Penanganan event WebSocket
└── ChannelConfigController.php    # Konfigurasi kanal notifikasi
```

**Aturan kunci**:
- WebSocket berbasis protokol native workerman
- Template notifikasi: placeholder variabel `{order_code}` diganti saat runtime
- Prioritas kanal: dalam aplikasi → email → WeCom → DingTalk, dapat dikonfigurasi

### 4.6 Standar Penerimaan P1

- [ ] Saat menyimpan voucher debit dan kredit tidak sama → mengembalikan error
- [ ] Hasil output mesin gaji konsisten dengan perhitungan manual (periksa sampel data gaji bulanan 10 orang)
- [ ] Perhitungan kebutuhan bersih MRP konsisten dengan penghitungan manual Excel
- [ ] Tiga dokumen inspeksi kualitas (IQC/IPQC/OQC) berjalan lengkap
- [ ] Latensi notifikasi WebSocket < 2 detik
- [ ] Semua layanan baru memiliki cakupan pengujian PHPUnit (algoritma kunci ≥ 95%)

---

## 5. P2 — Keandalan Operasional (1-2 minggu)

> **Tujuan**: kemampuan operasional tingkat produksi

### 5.1 Rollback Migrasi Database

```
database/migrations/
├── migrate.sh                    # Skrip maju (forward)
└── rollback.sh                   # Skrip rollback (dijalankan urutan terbalik dari file migrasi)
```

Setiap file migrasi menambahkan file `_rollback.sql` yang sesuai.

### 5.2 Penguatan Pemulihan Backup

```
database/backup/
├── backup.sh                     # Sudah ada
├── restore.sh                    # Sudah ada
├── auto-backup.sh                # Baru: backup terjadwal cron + peringatan
└── backup-validator.sh           # Baru: validasi integritas file backup
```

### 5.3 Observabilitas

```
app/service/observability/
├── TracerService.php             # Tracing OpenTelemetry
└── MetricCollector.php           # Koleksi metrik bisnis
```

- Trace ID tingkat permintaan (dipancarkan melalui header respons `X-Trace-Id`)
- Metrik bisnis kunci: volume pesanan, tingkat pemenuhan, hari perputaran stok

### 5.4 Upgrade Message Queue

Queue Redis yang ada → mendukung RabbitMQ sebagai driver opsional:

```
config/queue.php                  # Konfigurasi driver queue (redis/rabbitmq)
```

### 5.5 Standar Penerimaan P2

- [ ] Skrip rollback migrasi dapat dijalankan dan verifikasi integritas data lolos
- [ ] Cron backup otomatis terpicu dengan normal
- [ ] Trace ID menembus seluruh rantai permintaan
- [ ] Driver RabbitMQ dapat dialihkan dan pesan tidak hilang

---

## 6. P3 — Peningkatan Pengalaman (2-3 minggu)

> **Tujuan**: fitur lanjutan dan pengalaman pengguna yang lebih baik

### 6.1 Papan Data BI

```
app/controller/bi/
├── DashboardController.php       # Papan dasbor yang dapat dikonfigurasi
├── WidgetController.php          # CRUD widget grafik
└── DatasetController.php         # Manajemen dataset

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- Papan dasbor dengan tata letak yang dapat diseret
- Widget: grafik batang/grafik garis/grafik pai/kartu data/tabel
- Memakai ulang mekanisme dataset `app/controller/report/`

### 6.2 Manajemen Peralatan (EAM)

```
app/controller/eam/
├── EquipmentController.php       # Buku besar peralatan
├── MaintenancePlanController.php # Rencana perawatan
├── RepairOrderController.php     # Work order perbaikan
└── SparePartController.php       # Manajemen suku cadang
```

### 6.3 Multi-Tenant

```
app/middleware/TenantScope.php    # Middleware isolasi tenant
app/model/concerns/TenantScope.php # Trait scope Eloquent tenant
```

- Database bersama + isolasi `tenant_id`
- Tampilan lintas tenant untuk super admin

### 6.4 Manajemen Dokumen (DMS)

```
app/controller/dms/
├── DocumentController.php        # CRUD dokumen + manajemen versi
├── CategoryController.php        # Kategori dokumen
└── ApprovalController.php        # Persetujuan dan publikasi dokumen
```

### 6.5 Standar Penerimaan P3

- [ ] Tata letak papan BI dapat disesuaikan dengan drag
- [ ] Buku besar peralatan → rencana perawatan → work order perbaikan loop tertutup
- [ ] Tenant A tidak dapat mengakses data tenant B
- [ ] Riwayat versi dokumen dapat ditelusuri

---

## 7. Ringkasan Perubahan Model Data

### Tabel Baru P0

Tidak ada tabel baru, ekosistem frontend tidak melibatkan perubahan struktur tabel backend.

### Tabel Baru P1

| Nama tabel | Kegunaan | Tahap |
|------|------|------|
| `erik_finance_period_close` | Catatan penutupan akhir periode | P1 |
| `erik_finance_account_balance` | Snapshot saldo akun | P1 |
| `erik_hr_salary_config` | Konfigurasi perhitungan gaji | P1 |
| `erik_hr_social_insurance_config` | Konfigurasi dasar jaminan sosial | P1 |
| `erik_hr_housing_fund_config` | Konfigurasi dana perumahan | P1 |
| `erik_mfg_mrp_run_log` | Log kalkulasi MRP | P1 |
| `erik_mfg_order_suggestion` | Pesanan yang disarankan | P1 |
| `erik_quality_inspection_standard` | Standar inspeksi | P1 |
| `erik_quality_iqc_record` | IQC inspeksi bahan masuk | P1 |
| `erik_quality_ipqc_record` | IPQC inspeksi proses | P1 |
| `erik_quality_oqc_record` | OQC inspeksi pengiriman | P1 |
| `erik_quality_nonconformity` | Produk tidak sesuai | P1 |
| `erik_notification_channel_config` | Konfigurasi kanal notifikasi | P1 |
| `erik_notification_template` | Template notifikasi | P1 |

### Tabel Baru P3

| Nama tabel | Kegunaan | Tahap |
|------|------|------|
| `erik_bi_dashboard` | Papan BI | P3 |
| `erik_bi_widget` | Widget BI | P3 |
| `erik_eam_equipment` | Buku besar peralatan | P3 |
| `erik_eam_maintenance_plan` | Rencana perawatan | P3 |
| `erik_eam_repair_order` | Work order perbaikan | P3 |
| `erik_dms_document` | Dokumen terkendali | P3 |
| `erik_dms_document_version` | Versi dokumen | P3 |

---

## 8. Ringkasan Perubahan Lapisan Layanan

| Layanan | Saat Ini | Perubahan P1 | Perubahan P2 | Perubahan P3 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | Baru DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Gaji | Tidak ada | Baru SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Manufaktur | CRUD | Baru MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Kualitas | Tidak ada | Baru QmsInspectionService | — | — |
| Notifikasi | Dasar | Baru WebSocketService, ChannelRouter | — | — |
| Observabilitas | Proses Monitor | — | Baru TracerService, MetricCollector | — |
| BI | Tidak ada | — | — | Baru BiDashboardService |
| Peralatan | Tidak ada | — | — | Baru EamService |

---

## 9. Perubahan Rantai Middleware

```
Saat ini: Locale → Cors → SecurityFilter → RateLimit → {Grup rute}

P0: Tanpa perubahan
P1: + WebSocketUpgrade (upgrade koneksi WebSocket di jalur /ws)
P2: + TracingId (injeksi X-Trace-Id)
P3: + TenantScope (isolasi multi-tenant)
```

---

## 10. Tonggak dan Deliverable

| Tonggak | Waktu | Deliverable |
|--------|------|--------|
| M0 — Baseline saat ini | 2026-08-04 | Laporan audit `audit-report-2026-08-04.md` |
| M1 — P0 selesai | +3 minggu | Panel admin Flutter Web semua modul |
| M2 — P1 selesai | +8 minggu | Mesin keuangan + mesin gaji + mesin MRP + kualitas + notifikasi |
| M3 — P2 selesai | +10 minggu | Rollback migrasi + backup otomatis + Trace + upgrade queue |
| M4 — P3 selesai | +13 minggu | Papan BI + manajemen peralatan + multi-tenant + manajemen dokumen |

---

## 11. Risiko dan Mitigasi

| Risiko | Dampak | Langkah Mitigasi |
|------|------|----------|
| Performa Flutter Web tidak sebaik JS native | Tabel data besar lag | Paginasi sisi klien + scroll virtual + Web Worker |
| Perubahan regulasi mesin gaji | Hasil perhitungan tidak sesuai | Konfigurasi jaminan sosial/tarif pajak, bukan hardcode |
| Timeout kalkulasi MRP data besar | Kalkulasi terputus | Pemrosesan batch + callback progres |
| Terlalu banyak koneksi panjang WebSocket | Tekanan memori server | workerman secara alami high concurrency + batas jumlah koneksi |
| Kelalaian isolasi data multi-tenant | Kebocoran data | Middleware global TenantScope + cakupan pengujian |

---

## 12. Hal yang Tidak Dilakukan (Dikecualikan Secara Eksplisit)

- ❌ Tidak memperkenalkan pemecahan microservice — arsitektur monolitik saat ini cukup, logika kompleks dikohesikan melalui lapisan Service
- ❌ Tidak memperkenalkan Kubernetes — Docker Compose memenuhi skala saat ini
- ❌ Tidak membuat fitur AI/ML — tidak ada dalam roadmap MVP
- ❌ Tidak mengembangkan App iOS/Android native terpisah — Flutter cross-platform sudah mencakup
- ❌ Tidak memperkenalkan GraphQL — RESTful API cukup, strategi versi API sudah matang
- ❌ Tidak melakukan stempel elektronik/integrasi perangkat keras WMS (PDA/scanner) — murni tingkat perangkat lunak
