# Sistem ERP Terbuka (open-erp)

Sistem ERP full-stack berbasis webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Maskot gurita open-erp" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | Bahasa Indonesia | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Perbandingan Edisi](EDITIONS.md) | [Diagram Arsitektur](ARCHITECTURE.md) | [Diagram Sistem](#diagram-arsitektur-sistem) | [Dokumen Desain](DESIGN.md) | [Arsitektur Keamanan](SECURITY.md) | [Referensi API](API.md) | [Manual Fitur](FUNCTIONS.md)

## Pengenalan Proyek

open-erp adalah **sistem ERP open source full-stack** untuk UKM, mencakup domain bisnis lengkap seperti pembelian-penjualan-stok (pembelian/penjualan/stok), akuntansi keuangan, manufaktur (BOM/MRP/laporan kerja proses/beban kapasitas), CRM, alur persetujuan, sumber daya manusia, notifikasi pesan, dan laporan kustom. Backend dibangun di atas webman v2 + MySQL 8.0 (prefiks tabel `erp_`, primary key unik global Snowflake), panel admin menyediakan tiga implementasi: Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`), dan Flutter 3.x Web (`apps/flutter/`), sisi seluler dilengkapi klien native HarmonyOS (`apps/harmonyos/`).

Sistem berpusat pada desain **berbasis dokumen, penautan otomatis**: persetujuan dokumen bisnis secara otomatis memicu perubahan stok, pembentukan piutang-hutang, dan pengumpulan biaya; alur persetujuan dan notifikasi pesan menembus seluruh dokumen kunci; MRP menghitung kebutuhan material berdasarkan pesanan penjualan dan BOM lalu menghasilkan saran pembelian/produksi, membentuk siklus bisnis end-to-end dari penerimaan pesanan penjualan hingga penerimaan pembelian, dari penjadwalan produksi hingga penutupan buku keuangan.

## Keterangan Proyek

- **Perhitungan desimal presisi**: nilai bisnis seperti jumlah uang, kuantitas, dan bobot mengikuti aritmetika desimal bcmath; biaya rata-rata bergerak tertimbang, penyelesaian piutang-hutang, dan keluaran berbagai laporan berpresisi string, tanpa galat floating point
- **Baseline keamanan tingkat perusahaan**: token JWT + otorisasi tingkat metode RBAC, pertahanan berlapis (panorama berlapis L0–L12 + 35 jenis detektor serangan + rantai 7 lapis middleware, XSS/injeksi SQL/CSRF/rate limit/CSP, dll.), enkripsi penyimpanan field sensitif dan enkripsi transmisi antarmuka, jejak audit operasi lengkap
- **Kemampuan terkonfigurasi**: alur persetujuan multi-node (termasuk kanvas perancang proses visual), mesin template cetak dokumen (render placeholder + PDF dompdf + label kode QR), pencegat real-time batas kredit pelanggan, penelusuran maju-mundur rantai penuh batch/nomor seri
- **Data dapat dilacak**: setiap transaksi bisnis meninggalkan jejak; batch stok dan nomor seri menembus seluruh siklus hidup masuk → pemakaian → keluar → penelusuran, penghitungan biaya hingga tingkat baris dokumen
- **Ramah deployment**: Docker Compose v2 sekali klik (MySQL/Redis/Elasticsearch), `composer install` lokal juga dapat dijalankan langsung
- **Internasionalisasi**: 13 bahasa (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), pesan backend serta antarmuka dua panel admin Angular/React tercakup penuh, kamus frontend dimuat lambat per bahasa, README juga tersedia dalam 12 bahasa

## Daftar Fitur

| Domain Bisnis | Fitur | Keterangan |
|--------|------|------|
| 🔐 Autentikasi | Login/Registrasi/Refresh Token/Logout | Captcha klik + JWT + daftar hitam |
| | Penguncian akun | 5 kali gagal dikunci 15 menit |
| | Batas sesi bersamaan | Maksimal 3 Token valid per pengguna |
| 📊 Dasbor | Ikhtisar operasional + enam papan penjualan/stok/keuangan/OMS/WMS/TMS | Cache Redis 5 menit |
| 👥 Manajemen Pengguna | CRUD + Hapus massal/Aktif-Nonaktifkan | Soft delete + konfirmasi ulang kata sandi |
| | Impor massal Excel | Validasi per baris + laporan kesalahan |
| 🔒 Peran & Izin | CRUD Peran + Pohon izin | Otorisasi RBAC granular method.path |
| ⚙ Konfigurasi Sistem | CRUD pasangan kunci-nilai | Manajemen berkelompok |
| 📋 Audit Operasi | Kueri log + deteksi asal klien | Identifikasi otomatis 8 platform |
| 📁 Manajemen File | Unggah/Ekspor Excel/Ekspor PDF | Data sensitif otomatis di-masking |
| 🛡 Perlindungan Keamanan | 35 jenis detektor serangan + rantai middleware 7 lapis | XSS/Injeksi SQL/Path traversal/Injeksi perintah/CSRF/Rate limit/CSP... |
| 🏥 Operasi | Health check/metrics/dokumen API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Manajemen Produk | Arsip produk/SKU/banyak spesifikasi/banyak satuan/kategori/merek/strategi harga | Pohon kategori bertingkat + konversi multi-satuan |
| | Gudang & lokasi | Manajemen multi-gudang multi-lokasi |
| | Arsip pemasok/pelanggan | Kontak/akun bank/plafon kredit |
| 📥 Manajemen Pembelian | Permintaan→Pesanan→Penerimaan→Retur→Penyelesaian | Proses pembelian lengkap + persetujuan |
| | Pengadaan sourcing (RFQ → penawaran → pemenang jadi pesanan) | Perbandingan harga multi-pemasok, penawaran wajib mencakup semua baris RFQ, pemenang dikonversi satu klik menjadi pesanan pembelian |
| | Evaluasi pemasok | Skor total 0–100 dengan peringkat otomatis (A ≥ 90 / B ≥ 70 / C) + JSON dimensi penilaian + jejak penilai |
| 📤 Manajemen Penjualan | Penawaran→Pesanan→Pengiriman→Retur→Penyelesaian | Penawaran jadi pesanan + margin kotor penjualan |
| | Kontrol kredit pelanggan | Manajemen plafon/jangka waktu/pembekuan + intersepsi pesanan/pengiriman melebihi batas/lewat jatuh tempo |
| 🏗 Manajemen Stok | Stok real-time/batch/nomor seri/transfer/stok opname/peringatan | Perhitungan biaya rata-rata tertimbang bergerak |
| 💰 Manajemen Keuangan | Piutang-hutang/Penerimaan-pembayaran/Jurnal/Reimbursement/Laporan laba/Aset tetap/Pajak/Multi-mata uang/Anggaran/Pusat biaya & laba | Pembuatan otomatis piutang-hutang + penutupan (write-off) + manajemen keuangan menyeluruh |
| | Multi-organisasi + laporan konsolidasi | Akuntansi multi-perusahaan/pembukuan + jurnal eliminasi (metode ekuitas/biaya) |
| | Akuntansi biaya persediaan/produksi | Pengeluaran bahan produksi→pengumpulan tenaga kerja/biaya overhead pabrik→biaya produk jadi→penutupan selisih biaya |
| | Wesel akseptasi + rekonsiliasi bank | Daftar wesel + impor laporan bank dengan pencocokan otomatis |
| | Kumpulan faktur masukan + e-faktur digital | Manajemen faktur masukan + saluran penerbitan faktur (adaptor + saluran Mock) |
| 🤝 CRM | Pelanggan/Kontak/Catatan tindak lanjut/Kampanye pemasaran/Tiket layanan/Laporan analisis/Coroong penjualan/Kolam bersama/Penawaran/Kontrak | Manajemen siklus hidup pelanggan menyeluruh |
| | Mesin nilai member | Operasional member saldo/poin/kupon |
| ✅ Alur Persetujuan | Definisi alur kerja/Submit persetujuan/Setujui/Tolak/Tarik/Persetujuan saya | Mesin alur persetujuan multi-node |
| | Perancang alur visual | Konfigurasi node/cabang/edge penolakan pada kanvas + penggunaan ulang mesin alur persetujuan |
| 🔔 Notifikasi Pesan | Daftar notifikasi/Tandai dibaca/Jumlah belum dibaca/Semua dibaca | Push pesan real-time & pelacakan status |
| | Notifikasi multi-kanal | Driver kanal SMS/email (saluran Mock + log + retry) |
| 📐 Manajemen Proyek | Proyek/Tugas/Catatan jam kerja | Pelacakan progres proyek & manajemen sumber daya |
| | Biaya proyek & anggaran | Jam kerja×tarif→akumulasi biaya proyek + deviasi anggaran |
| 👤 Sumber Daya Manusia | Departemen/Karyawan/Posisi/Absensi/Cuti/Gaji | Manajemen personalia menyeluruh |
| | Rekrutmen/Kinerja/Pelatihan/Jamsos | Corong rekrutmen + penilaian KPI/360 + kredit kursus + aturan dasar Jamsos & slip gaji |
| 🏭 Manufaktur | BOM/Pesanan produksi/Rute proses/Stasiun kerja/MRP | Perencanaan kebutuhan material & eksekusi produksi |
| | Pelaporan kerja operasi/upah per potong/rekonsiliasi subkontrak | Lapisan eksekusi operasi MES + rekonsiliasi pengeluaran material pesanan subkontrak |
| | Analisis beban kapasitas | Kalender stasiun kerja + laporan beban kapasitas kasar |
| | Penelusuran batch/nomor seri | Rantai pelacakan maju & mundur + peringatan mendekati kedaluwarsa |
| 📈 Laporan Kustom | Template laporan/Set data/Field/Filter/Eksekusi/Jadwal terjadwal | Pembangun laporan visual |
| 📋 Manajemen Pesanan (OMS) | Pesanan multi-kanal/Orkestrasi pemenuhan/Pre-alokasi stok/Alokasi/Pembatalan/Retur RMA | Manajemen siklus hidup pesanan menyeluruh |
| 🏗 Manajemen Gudang (WMS) | Zona lokasi/ASN/Penerimaan/Putaway/Gelombang/Picking/Packing/Pengiriman | Proses operasi gudang lengkap |
| 🚚 Manajemen Transportasi (TMS) | Kurir/Layanan/Tarif/Resi/Lacak logistik/Invoice biaya kirim | Perbandingan tarif multi-kurir + pelacakan |
| 🛠 Manajemen Peralatan (EAM) | Buku besar peralatan/rencana perawatan/work order perbaikan/suku cadang | Manajemen siklus hidup peralatan menyeluruh |
| | Inspeksi scan loop tertutup | Scan kode untuk inspeksi, anomali otomatis membuat work order perbaikan |
| 🌐 Platform & Terbuka | Versioning jalur API | Admin /admin/v1, klien /api/v1, terbuka /open/v1 (tanpa header versi) |
| | Mesin template cetak dokumen | Render placeholder + PDF dompdf + label QR |
| | Bidang kustom formulir | Ekstensi JSON custom_fields di tabel master + validasi |
| | Arsitektur multi-tenant | Tenant erp_tenant + konteks permintaan TenantScope + penagihan kedaluwarsa (seam middleware dicadangkan, belum terdaftar) |

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
| Mesin pencari | Elasticsearch | `webman-scout` menyinkronkan indeks otomatis saat tulis/hapus (komponen opsional) |
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
| `erikwang2013/apidoc-php` | Pembuatan otomatis dokumen API | Dokumen antarmuka berbasis anotasi, dikelompokkan untuk sisi admin/klien |

## Internasionalisasi

| Lapisan | Lokasi kamus | Skala |
|---|---------|------|
| Pesan backend | `resource/translations/{bahasa}/` | 13 direktori bahasa: `zh_CN` 565 entri, 11 bahasa lainnya masing-masing 544, `en` 30 (cakupan: entri daun dari tiga berkas common/modules/validation) |
| Admin Angular | `apps/angular/src/app/core/zh-*.ts` (kamus sumber `zh-en/`, 4 irisan) | Kamus sumber 1456 kunci × 11 bahasa baru (jumlah kunci tiap bahasa 1:1) |
| Admin React | `apps/react/src/lib/i18n/zh*.ts` | Kamus sumber 1451 kunci × 11 bahasa baru |

## Struktur Proyek

```
open-erp/
├── app/
│   ├── admin/controller/       # Controller manajemen sistem (16)
│   ├── api/v1/controller/      # API klien (versi berada di path /api/v1, tanpa header versi)
│   ├── controller/             # Controller modul bisnis (139, 23 domain)
│   │   ├── product/            # Produk/kategori/merek/gudang/lokasi/pemasok/pelanggan (8)
│   │   ├── purchase/           # Permintaan/order/penerimaan/retur/penyelesaian/RFQ/penawaran/evaluasi pemasok (8)
│   │   ├── sales/              # Penawaran/order/pengiriman/retur/penyelesaian penjualan (5)
│   │   ├── inventory/          # Stok/transaksi/transfer/opname/peringatan (6)
│   │   ├── finance/            # Piutang-hutang/voucher/penerimaan-pembayaran/jurnal/buku besar/buku pembantu/laporan/aset/pajak/multi-mata uang/anggaran/pusat biaya & laba/nota/rekonsiliasi/faktur (28)
│   │   ├── crm/                # Peluang/tindak lanjut/corong/kontak/kolam bersama/kontrak/penawaran/pemasaran/tiket/analisis (10)
│   │   ├── workflow/           # Definisi alur kerja/persetujuan/perancang proses (3)
│   │   ├── notification/       # Notifikasi internal/pengiriman kanal (2)
│   │   ├── project/            # Proyek/tugas/jam kerja/biaya (4)
│   │   ├── hr/                 # Departemen/karyawan/posisi/absensi/cuti/gaji/rekrutmen/kinerja/jaminan sosial/pelatihan (9)
│   │   ├── manufacturing/      # BOM/work order/rute proses/stasiun kerja/MRP/laporan kerja/subkontrak/biaya/kapasitas (13)
│   │   ├── report/             # Template laporan/dataset/eksekusi/jadwal terjadwal (2)
│   │   ├── print/              # Mesin template cetak (1)
│   │   ├── retail/             # Member/nilai tersimpan/poin/voucher (2)
│   │   ├── platform/           # Multi-tenant/bidang kustom (2)
│   │   ├── quality/            # Kontrol kualitas (5)
│   │   ├── eam/                # Peralatan/perawatan/repair/suku cadang/inspeksi (5)
│   │   ├── bi/                 # Business intelligence (3)
│   │   ├── dms/                # Manajemen dokumen (2)
│   │   ├── oms/                # Order OMS/pemenuhan/RMA/kanal (4)
│   │   ├── wms/                # Zona/lokasi/ASN/penerimaan/putaway/gelombang/picking/packing (8)
│   │   ├── tms/                # Kurir/layanan/tarif/resi/lacak/invoice biaya kirim (6)
│   │   └── open/               # Antarmuka platform terbuka (1)
│   ├── service/                # Lapisan logika bisnis (64)
│   │   ├── inventory/          # In/out stok + biaya rata-rata tertimbang bergerak + reservasi stok/ATP
│   │   ├── finance/            # Pembuatan otomatis piutang-hutang + write-off
│   │   ├── notification/       # Layanan pengiriman notifikasi
│   │   ├── oms/                # Orkestrasi order/alokasi stok/siklus hidup RMA
│   │   ├── wms/                # Proses inbound (ASN→penerimaan→putaway) / proses outbound (gelombang→picking→packing)
│   │   └── tms/                # Manajemen resi/perbandingan tarif/lacak logistik
│   ├── model/                  # 224 model Eloquent (dipakai bersama antar modul)
│   ├── middleware/             # 11 middleware (ApiVersion dihapus, versi lewat path)
│   ├── common/                 # Layanan Hashids/Snowflake/Encryption
│   └── queue/                  # Tugas antrean
├── apps/
│   ├── angular/                # Admin Angular 22 (halaman resource berbasis config, ng serve :4200)
│   ├── react/                  # Admin React 19 + Vite (Vite :5173)
│   ├── flutter/                # Flutter lintas platform (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Klien native HarmonyOS
├── config/                     # File konfigurasi (berisi komentar 中文)
│   ├── plugin/erikwang2013/apidoc/ # Konfigurasi dokumen API
├── database/
│   ├── install.sql              # SQL instalasi lengkap (227 tabel + data seed)
│   ├── e2e-seed.sql             # Seed minimal E2E/CI
│   └── backup/                 # Skrip backup/restore
├── docs/                       # Dokumentasi arsitektur, desain, keamanan, API
├── tests/                      # Pengujian PHPUnit (<!-- stats:test_files=113 --> file pengujian, <!-- stats:tests=1025 --> metode pengujian, <!-- stats:assertions=4827 --> asersi)
├── resource/
│   └── translations/           # Kamus pesan backend 13 bahasa (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # Terjemahan 中文 (565 entri)
│       ├── en/                 # Inggris sebagai key, hanya 30 entri seperti nama aturan framework
│       └── ja|ko|de|.../       # 11 bahasa lainnya masing-masing 544 entri (generator scripts/gen-be-locales.mjs)
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

**23 domain bisnis besar, 227 tabel data, 159 controller**: Mencakup keamanan autentikasi, dasbor, manajemen sistem, perlindungan keamanan, pemantauan operasi, manajemen produk, pembelian, penjualan, stok, keuangan (14 submodul), CRM (10 submodul), alur persetujuan, notifikasi pesan, manajemen proyek, sumber daya manusia, manufaktur (MRP), laporan kustom, manajemen pesanan (OMS), manajemen gudang (WMS), manajemen transportasi (TMS), manajemen kualitas (QMS), manajemen peralatan (EAM), manajemen dokumen (DMS), papan BI.

### Siklus Hidup Permintaan

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Jalur permintaan lengkap dari klien ke database**: Klien (Angular/React/Flutter/HarmonyOS) → terminasi SSL Nginx → penanganan CORS → filter keamanan → rate limit → [Admin: autentikasi JWT → izin RBAC → log operasi] → Controller → Lapisan layanan → Lapisan model → Cache/database/mesin pencari → Respons JSON. Diagram mencakup dua jalur: cache hit dan cache miss. (Versi API kini menyatu di path URL, tanpa langkah validasi terpisah; bahasa ditentukan `app/common/I18n.php` dari `Accept-Language`.)

### Arsitektur Pertahanan Berlapis Keamanan

![Security Architecture](./diagrams/security-architecture-cn.svg)

**Panorama pertahanan berlapis (L0–L12)**: L0 jaringan fisik → L1 keamanan transportasi → L2 header keamanan HTTP → L3 validasi permintaan → L4 sanitasi input → L5 proteksi CSRF → L6 rate limit → L7 autentikasi (JWT+Captcha+daftar hitam+kontrol sesi) → L8 otorisasi RBAC → L9 proteksi data (enkripsi transportasi + enkripsi penyimpanan + obfuskasi ID + masking data) → L10 pemantauan audit → L11 pengungkapan kepatuhan → L12 observabilitas (tracing terdistribusi X-Trace-Id + metrik bisnis + audit yang diperkuat). Untuk 7 lapis middleware pada rantai yang dapat dieksekusi lihat `docs/SECURITY.md`; untuk 35 jenis detektor serangan lihat `config/plugin/erikwang2013/security-php/app.php`.

---

## Persyaratan Lingkungan

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (hanya diperlukan untuk pengembangan frontend)
- Node >= 22.22.3 (hanya untuk pengembangan frontend admin Angular/React; batas bawah `engines` Angular CLI 22)
- Elasticsearch >= 7.x atau OpenSearch >= 2.x (opsional, diperlukan untuk sinkronisasi indeks; tidak dipasang pun tidak memengaruhi baca/tulis bisnis)
- DevEco Studio (opsional, hanya untuk build klien HarmonyOS; padanan baris perintah `hvigorw assembleHap`)

## Domain Lokal Default

Proyek secara default menggunakan domain lokal **`http://erp.test`** (alamat API default klien Flutter dan konvensi entri Web backend; klien HarmonyOS secara default mengarah ke host emulator `http://10.0.2.2:8788`).

- **Akses lokal**: tambahkan satu baris `127.0.0.1 erp.test` ke hosts, lalu arahkan server Web/reverse proxy ke port backend (default `8788`, lihat `APP_HTTP_PORT` di `.env`, dapat diubah di wizard instalasi atau `.env`; WebSocket default `8282` sesuai `APP_WS_PORT`).
- **Mengubah domain deployment**:
  - Injeksi saat build Flutter: `flutter build web --dart-define=API_BASE_URL=https://domain-anda`
  - HarmonyOS: edit `BASE_URL` di `apps/harmonyos/entry/src/main/ets/utils/Config.ets` (konstanta read-only, default `http://10.0.2.2:8788`)
  - Debug emulator dapat sementara dikembalikan ke `http://10.0.2.2:8788` (mengakses host)
- Semua versi API sudah diletakkan di path (`/admin/v1`, `/api/v1`, `/open/v1`), klien hanya perlu mengonfigurasi alamat akar.

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
| `JWT_SECRET_KEY` | Kunci penandatanganan JWT | `.env.example` sudah berisi nilai acak 48 karakter |
| `HASHIDS_SALT` | Salt Hashids | `.env.example` sudah berisi nilai acak 48 karakter |
| `ENCRYPTION_KEY` | Kunci enkripsi API | `.env.example` sudah berisi nilai acak 32 karakter (syarat keras AES-256) |
| `SNOWFLAKE_DATACENTER_ID` | ID pusat data (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID node pekerja (0-31) | `1` |
| `SCOUT_HOSTS` | Alamat ES | `http://localhost:9200` |

**Hilang, kosong, atau masih berupa nilai placeholder lemah seperti `change-me`/`xxx` → langsung ditolak saat startup oleh `env_required` / `env_crypto_key` (tanpa degradasi diam-diam); `ENCRYPTION_KEY` punya pemeriksaan panjang terpisah (AES-256 wajib 32 byte, tidak sesuai = error saat startup).**

### 3. Inisialisasi Database

**Cara 1: Wizard instalasi Web (disarankan)**

Setelah layanan dimulai, akses `http://localhost:8788/install` dan ikuti panduan untuk menyelesaikan instalasi 4 langkah: pemeriksaan lingkungan → konfigurasi database → akun admin → instalasi satu-klik. Langkah konfigurasi database menyediakan kotak centang **impor data demo** (produk/spesifikasi/SKU/pelanggan/pemasok, rentang ID 41…, dapat dihapus per rentang); nonaktif secara default — jangan centang di produksi.

**Cara 2: Impor baris perintah**

```bash
mysql -u root -p nama_database < database/install.sql
```

`install.sql` adalah baseline lengkap satu berkas, berisi struktur seluruh 227 tabel dan data seed.

**Cara 3: Lingkungan Docker**

```bash
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
# 2. Ganti kunci placeholder dengan nilai acak (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Mulai semua layanan
docker compose up -d

# 4. Inisialisasi database (jalankan di dalam container app)

# 5. Akses
# http://localhost:8788  (webman)
# http://localhost:8080  (reverse proxy Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, berbasis `php:8.3-cli`
- `docker-compose.yml`: orkestrasi 5 layanan, isolasi jaringan, persistensi volume data
- `.env.docker`: variabel lingkungan khusus lingkungan Docker
## Penggunaan

### 1. Login

Pada penggunaan pertama, buka penginstal web `http://localhost:8788/install` untuk menyelesaikan instalasi dan membuat akun admin. Jika sudah terinstal, buka konsol, masukkan kredensial, dan lewati captcha klik untuk masuk.

### 2. Navigasi

Setelah login, masuk ke setiap modul dari sidebar: dashboard, produk, pembelian, penjualan, inventaris, keuangan, CRM, alur persetujuan, notifikasi, proyek, SDM, manufaktur, laporan kustom, OMS/WMS/TMS, dashboard BI, dan administrasi sistem (pengguna/peran/konfigurasi/log). Sidebar tetap di desktop dan terlipat menjadi laci di ponsel.

### 3. Izin dan keamanan

- Fitur dan API dikendalikan oleh RBAC; menu dan antarmuka tanpa izin tidak dapat diakses (403)
- Operasi sensitif seperti menghapus pengguna/peran memerlukan konfirmasi kata sandi saat ini di badan permintaan
- Setelah logout, token langsung masuk daftar hitam

### 4. Mesin Pencari Teks Lengkap (opsional)

Sinkronisasi indeks diimplementasikan melalui `erikwang2013/webman-scout` (setelah model ditambahi trait `Searchable`, indeks tersinkron otomatis saat penyimpanan). Mendukung dua mesin: **Elasticsearch** dan **OpenSearch**, pilih salah satu:

**① Pasang klien yang sesuai (paket Composer dan driver harus cocok; salah pasang akan muncul error "Please install the ... client")**

| Mesin | Klien Composer |
|---|---|
| Elasticsearch | `composer require elasticsearch/elasticsearch:^9.5` |
| OpenSearch | `composer require opensearch-project/opensearch-php:^2.0` |

**② Konfigurasi `.env` untuk memilih driver**

```ini
# elasticsearch | opensearch (sesuai klien yang dipasang di atas)
SCOUT_DRIVER=opensearch
# Prefiks nama indeks / shard / replika / ukuran blok batch / soft delete (berlaku untuk kedua mesin)
SCOUT_PREFIX=erp_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true
```

**③ Konfigurasi koneksi (lokasi pembacaan kedua mesin berbeda)**

- **Elasticsearch**: `SCOUT_HOSTS` di `.env` (multi-node dipisah koma, mis. `http://localhost:9200`), koneksi langsung tanpa autentikasi;
- **OpenSearch**: image resmi mengaktifkan plugin keamanan secara default (TLS self-signed + autentikasi akun), melalui bagian `opensearch` di `config/scout.php`, tidak membaca `SCOUT_HOSTS`:

  ```ini
  # .env
  SCOUT_OPENSEARCH_HOST=https://localhost:9200
  SCOUT_OPENSEARCH_USERNAME=admin
  SCOUT_OPENSEARCH_PASSWORD=kata-sandi-anda
  ```

  Bagian `opensearch` di `config/scout.php` secara default `ssl_verification=false` (sertifikat self-signed lokal); lingkungan produksi harus mengubahnya menjadi `true` dan mengonfigurasi sertifikat, jangan pernah memakai kata sandi lemah.

> Proyek ini menyertakan Elasticsearch di Docker Compose (layanan `open-admin-es`): untuk deployment Docker pilih **driver elasticsearch + klien ES**; untuk kontainer OpenSearch eksternal/mandiri pilih **driver opensearch + opensearch-php**.
>
> **Cakupan indeks**: seluruh 224 model di `app/model/` membawa `Searchable`, penulisan/pengsoftdeletan langsung menyinkronkan indeks melalui `ModelObserver`; di antaranya AdminUser, Customer, Product, Supplier 4 model menyesuaikan `toSearchableArray()` dengan daftar field putih, model lainnya masuk indeks sesuai default (seluruh baris).
>
> **Mesin tidak tersedia tidak memengaruhi penulisan bisnis** (terbukti: setelah driver diarahkan ke port yang tidak dapat dijangkau, `save()` tetap berhasil, hanya menambah satu kali waktu tunggu timeout koneksi) —— mesin pencari adalah komponen opsional, tanpa dipasang pun seluruh bisnis tetap berjalan.
>
> **Catatan cakupan**: proyek ini saat ini hanya mengintegrasikan **sinkronisasi indeks** (penulisan/pengsoftdeletan langsung sinkron), belum menyediakan antarmuka atau UI pencarian; bila bisnis memerlukan pencarian, panggil API kueri Scout sendiri (filter halaman daftar panel admin memakai kueri `where` backend, tidak melewati mesin pencari).

### 5. Multibahasa

Peralihan otomatis melalui header `Accept-Language`, mendukung 13 bahasa (`zh` default, serta `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`); panel admin Angular/React juga memiliki ikon globe di bilah atas dan dropdown di pusat pribadi. Lihat [Internasionalisasi](#internasionalisasi).

## Konvensi Database

- **Prefiks tabel**: `erp_`
- **Primary key**: semua tabel ber-primary key `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT dilarang**
- **Pembuatan ID**: ID primary key dibuat oleh `SnowflakeService::generate()` di lapisan aplikasi, unik terdistribusi
- **Field wajib**: setiap tabel harus berisi `id`, `created_at`, `updated_at`
- **Soft delete**: tabel yang memerlukan soft delete menambahkan `deleted_at DATETIME DEFAULT NULL`
- **Field sensitif**: nomor ponsel, email, nomor KTP, dll. menggunakan plugin `encryptable` untuk enkripsi/dekripsi otomatis, field database menggunakan `VARCHAR(500)` untuk menyimpan ciphertext

## Konvensi API

### Dokumen API

Proyek menggunakan erikwang2013/apidoc-php untuk menghasilkan dokumen antarmuka secara otomatis, akses `/apidoc` untuk melihat.

- Antarmuka admin (Admin): 25 grup modul, berisi parameter permintaan lengkap dan struktur respons
- Antarmuka klien (Service API): 3 grup autentikasi/kaptcha/produk
- Semua antarmuka ditandai dengan header global seperti autentikasi JWT, internasionalisasi

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
- **Path antarmuka**: `GET /admin/v1/user/{hashid}` — `{id}` dalam path adalah string hashid
- **Penyimpanan database**: nilai asli BIGINT, dibuat oleh snowflake

### Versi API

Versi API berada di path URL (mis. `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`), **klien tidak memerlukan header versi apa pun**:

- Antarmuka publik berversi langsung terikat ke kelas controller versi terkait (`app/api/v1/controller/`)
- Menambah versi baru berarti mendaftarkan grup route `/api/vN` baru, controller disimpan per versi di `app/api/vN/`
- Parsing dinamis `v()` lama dan middleware header `ApiVersion` keduanya sudah dihapus

### Rate Limit

Berbasis algoritma sliding window Redis, default 60 kali/menit/IP/route. Antarmuka sensitif lebih ketat:
- Login: 10 kali/menit
- Registrasi: 5 kali/menit (default nonaktif, perlu `REGISTRATION_ENABLED=1` untuk mengaktifkan)

Header respons berisi `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Melebihi batas mengembalikan 429 disertai `Retry-After`.

### Arsitektur Middleware

Middleware global berlaku untuk semua permintaan, dieksekusi berurutan:

```
Cors (praproses CORS + header respons)
  → SecurityFilter (batasan metode HTTP/ukuran body/validasi Content-Type/XSS/injeksi SQL/path traversal/injeksi perintah/intercept serangan CSRF)
  → RateLimit (rate limit sliding window Redis + penguncian akun: 5 kali gagal login dikunci 15 menit)
  → TracingId (ID pelacakan rantai)
```

Middleware grup route: `/admin/v1` memakai `AdminAuth` (autentikasi JWT + daftar hitam) → `AdminPermission` (otorisasi RBAC) → `OperationLog` (pencatatan otomatis POST/PUT/DELETE, termasuk deteksi asal klien); `/open/v1` memakai `OpenApiAuth`; callback pelacakan TMS memakai `TrackingSignature`. Bahasa ditentukan `app/common/I18n.php` dari `Accept-Language`, bukan middleware.

`/health`, `/api/docs` dan `/install` adalah endpoint publik, hanya melalui `Cors → SecurityFilter → RateLimit → TracingId`.

Peningkatan keamanan:
- **Penguncian akun**: 5 kali gagal login berturut-turut, akun otomatis dikunci 15 menit, selama periode tersebut login mengembalikan 429
- **Batas sesi bersamaan**: maksimal 3 Token valid per pengguna, saat melebihi Token paling lama otomatis masuk daftar hitam
- **security.txt**: `GET /.well-known/security.txt` menyediakan informasi kontak keamanan standar RFC 9116
- **Konfigurasi keamanan Nginx**: lihat `nginx-security.conf` untuk contoh penguatan keamanan reverse proxy lengkap

### Autentikasi

Login dan registrasi harus melalui validasi **captcha klik** terlebih dahulu:

1. Klien meminta `POST /api/v1/captcha/generate` untuk mendapatkan gambar captcha (base64 PNG) dan daftar target teks
2. Pengguna mengklik posisi teks yang sesuai di gambar secara berurutan, mengumpulkan koordinat klik `[{x, y}, ...]`
3. Saat login, kirim `captcha_key` dan `clicks` bersamaan, server memvalidasi captcha terlebih dahulu lalu memvalidasi kredensial

```http
POST /api/v1/auth/login
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

Saat logout, Token dimasukkan ke daftar hitam Redis, tidak dapat digunakan kembali selama masa berlaku. POST /admin/v1/profile/logout

### Konfirmasi Ulang Operasi Sensitif

Operasi sensitif seperti menghapus pengguna, peran, izin memerlukan pengiriman `password` pengguna yang sedang login di body permintaan untuk konfirmasi ulang identitas:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Daftar API

Daftar lengkap antarmuka (antarmuka publik / antarmuka admin / antarmuka bisnis / antarmuka klien) telah dipindahkan ke dokumen terpisah:

→ [Dokumen Referensi API](API.md)

## Keterangan Frontend

### Panel Admin Angular (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (port lihat ANGULAR_DEV_PORT di .env)
npm run build      # tsc --noEmit + ng build，keluaran dist/angular
npm run typecheck  # hanya pemeriksaan tipe
```

- **Syarat versi Node**: `engines` pada Angular CLI 22 mensyaratkan **Node ≥ 22.22.3** (versi lebih rendah akan langsung menolak `ng build`).
  Bila Node lokal lebih rendah, tentukan sementara lewat npx (cara build paling umum di repositori ini, dipakai di luar CI):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  Untuk lingkungan tanpa `npx` (seperti mesin verifikasi offline repositori ini), gunakan tsc bawaan CLI untuk pemeriksaan tipe:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Proxy pengembangan**: `proxy.conf.js` telah mem-proxy `/admin` `/api` `/open` `/health` `/metrics` `/install`
  ke `APP_HTTP_PORT` di `.env` (default 8788), sehingga saat `ng serve` **tidak perlu** mengonfigurasi alamat backend lagi
- **Arsitektur**: digerakkan konfigurasi —— `src/app/config/domains/*.ts` mendeklarasikan menu dan halaman resource, **satu `ResourcePage`
  merender seluruh halaman bisnis** (menambah halaman resource ≈ menambah satu objek konfigurasi, tidak perlu menulis komponen)
- **Multibahasa**: 13 bahasa, kamus dimuat lambat per bahasa (masing-masing menjadi satu chunk); ikon globe di bilah atas untuk beralih
- **Pemeriksaan mandiri** (semuanya tanpa browser, dijalankan langsung dengan `node`): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### Panel Admin React (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (port lihat REACT_DEV_PORT di .env)
npm run build      # tsc --noEmit + vite build，keluaran dist/
```

- Sama seperti Angular, **digerakkan konfigurasi**: `src/config/domains/*.ts` mendeklarasikan menu dan halaman resource,
  mesin render di `src/components/ResourcePage.tsx`; token gaya di `src/styles/tokens.css`
  (nilainya sama dengan `styles/theme.less` di sisi Angular)
- Pintu masuk peralihan bahasa ada di halaman **pusat pribadi** (sisi Angular juga memiliki ikon globe di bilah atas)

### Flutter Admin (gaya PC, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Sisi Web (bergaya panel admin PC), juga mendukung iOS/Android/macOS/Windows/Linux
flutter analyze          # Pemeriksaan statis (sama seperti CI)
```

- **Tata letak**: sidebar (dapat dilipat 64px/240px) + top bar + area konten, tiga breakpoint responsif (ponsel/tablet/desktop)
- **Cakupan**: 22 grup menu, 102 halaman dapat dirutekan, 119 file halaman (menu dideklarasikan di `lib/app/config/menu_config.dart`, halaman di `lib/app/pages/`) —— Dasbor, Manajemen Sistem, Manajemen Produk, Mitra Bisnis, Manajemen Pembelian, Manajemen Penjualan, Manajemen Stok, Manajemen Keuangan, CRM, Manajemen Pesanan, Manajemen Gudang, Manajemen Transportasi, Manufaktur, Manajemen Kualitas, Sumber Daya Manusia, Manajemen Proyek, Alur Persetujuan, Pusat Notifikasi, Laporan Kustom, Papan BI, Manajemen Peralatan, Manajemen Dokumen
- **Manajemen status**: GetX (`ApiService` singleton + persistensi Token `AuthService`)
- **Dasbor**: kartu statistik, garis tren penjualan, Top produk, distribusi status pesanan, umur piutang-hutang, ikhtisar stok (fl_chart)
- **Ekspor**: ekspor Excel/PDF (`ExportService`), PDF berisi informasi hak cipta yang tidak dapat dihapus
- **Operasi massal**: hapus massal multi-pilih, aktif/nonaktifkan massal
- **Tema**: Material 3 tema terang/gelap ganda
- **Internasionalisasi**: bilingual 中文/Inggris (template `lib/l10n/app_zh.arb`, dibuat dengan `flutter gen-l10n`)

### Seluler HarmonyOS (`apps/harmonyos/`)

- **Build**: buka `apps/harmonyos/` dengan DevEco Studio; padanan baris perintah
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (perlu HarmonyOS SDK + command-line-tools, keluaran `entry/build/default/outputs/default/*.hap`)
- **Halaman**: registri `entry/src/main/resources/base/profile/main_pages.json` memuat **41 halaman terdaftar, semuanya dapat dijangkau dari UI** (login, dasbor, daftar/detail pengguna, izin peran, pusat pribadi, serta halaman subsistem produk/stok/pembelian/penjualan/OMS/WMS/TMS/produksi/HR/persetujuan); grid bisnis dasbor menyediakan **32 pintu masuk langsung**, halaman detail subsistem dibuka dari aksi baris daftar
- **Autentikasi**: JWT Bearer + 401 refresh Token otomatis tanpa terasa, gagal refresh otomatis redirect ke halaman login
- **Penyimpanan**: Token dikelola melalui AppStorage
- **Internasionalisasi**: bilingual 中文/Inggris (`resources/base/element/string.json` dan `resources/en_US/element/string.json`)
- **Jaringan**: `BASE_URL` adalah konstanta read-only yang didefinisikan di `entry/src/main/ets/utils/Config.ets`, nilai default `http://10.0.2.2:8788` (emulator ke mesin host); tempat mengubah alamat ada di file ini

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
# Ganti kunci placeholder dengan nilai acak (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

Pipeline integrasi berkelanjutan GitHub Actions: `.github/workflows/ci.yml`, lima job:

| Job | Isi |
|------|------|
| `php` (matriks PHP 8.3 / 8.4, dengan layanan MySQL 8 + Redis 7) | verifikasi & audit keamanan composer → `php -l` → **PHPStan** (level 5 + baseline) → **PHP CS Fixer** (dry-run) → impor `install.sql` lengkap → **PHPUnit** (termasuk kasus integrasi) → pengumpulan cakupan pcov → ambang cakupan (keseluruhan ≥ 4%, `app/service` ≥ 10%, diperketat bertahap) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, diperketat setelah lingkungan stabil) |
| `docs` | `bash scripts/doc-stats.sh --check`: memeriksa anotasi `stats:key=value` di README dan docs cocok dengan hitungan nyata kode sumber (jumlah controller/service/model/tabel/tes, dll.), menyimpang berarti merah |
| `e2e` | menjalankan layanan webman sungguhan → health check → smoke jalur inti HTTP + cakupan API admin |
| `release` | setelah push ke `main` dan job di atas lulus, menandai tag sesuai patch+1 dan menerbitkan Release (lihat di bawah) |

> Cakupan pemeriksaan statis frontend: CI saat ini hanya menjalankan Flutter; Angular/React (`tsc --noEmit`) dan HarmonyOS (`hvigorw assembleHap`) harus dijalankan secara lokal atau di job tambahan nanti.

### Proses Rilis (Increment Versi)

Setelah push ke `main` dan pemeriksaan php / docs / e2e semuanya lulus, job `release` di `ci.yml` otomatis membuat tag versi baru dengan **patch+1** dari tag terbaru lalu mendorongnya (`v1.1.4` → `v1.1.5`), kemudian membuat GitHub Release bernama sama (catatan perubahan dibuat otomatis oleh `--generate-notes`).

- **Pemicu**: hanya push ke `main` (PR tidak memicu; push tag tidak cocok dengan filter cabang, sehingga alur kerja ini tidak terpicu secara rekursif)
- **Idempoten**: bila tag atau release dengan nama sama sudah ada di remote (CI bersamaan / sudah ditandai manual), otomatis dilewati tanpa error
- **Uji coba lokal**: `bash scripts/bump-version.sh --check` mencetak nomor versi berikutnya (hanya baca, tidak menulis ke remote)

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
