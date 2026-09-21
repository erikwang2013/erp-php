# Perbandingan Edisi

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Statistik dikumpulkan secara real-time oleh `bash scripts/doc-stats.sh`, ditandai dalam dokumen dengan `<!-- stats:key=value -->`,
> CI (job docs di `.github/workflows/ci.yml`) secara otomatis memverifikasi konsistensi antara dokumen dan fakta kode, penyimpangan berarti merah.

Sistem ERP Terbuka menyediakan tiga edisi, menyesuaikan kebutuhan perusahaan dari berbagai skala.

---

## Ikhtisar Edisi

| Dimensi | Edisi Ringkas (Lite) | Edisi Standar (Standard) | Edisi Lengkap (Full) |
|------|:---:|:---:|:---:|
| Cabang | `lite` | `standard` | `full` |
| Tabel data | 62 (nilai rencana) | 72 (nilai rencana) | 227 <!-- stats:tables=227 --> |
| Controller | 48 (nilai rencana) | 42 (nilai rencana) | 159 <!-- stats:controllers=159 --> |
| Modul bisnis | 6 (nilai rencana) | 6 (nilai rencana) | 23 <!-- stats:modules=23 --> |

> **Metodologi statistik**: repositori saat ini hanya mengimplementasikan satu set kode edisi Lengkap (Full); kolom Lite/Standard adalah nilai perencanaan produk (tidak ada cabang terkait di codebase),
> tidak ikut validasi doc-stats. Angka kolom Full diukur oleh `scripts/doc-stats.sh` (227 tabel / 159 controller / 23 modul bisnis),
> konsisten dengan metodologi lampiran `FUNCTIONS.md`.
> **Fakta cabang** (terukur 2026-09-22 dengan `git branch -a` + `git ls-remote --heads origin`):
> repositori, baik lokal maupun remote, hanya menyisakan satu cabang `main`; tiga cabang `lite` / `standard` / `full` **sudah dihapus**
> (pada 2026-08-31 sempat terukur ketiga cabang berdampingan, sama-sama berhenti di commit `eea90c0` tanggal 2026-08-17, tidak berbeda satu sama lain dan tertinggal 38 commit dari `main`).
> Commit arsip tersebut masih ada dalam riwayat `main` (`git merge-base --is-ancestor eea90c0 main` bernilai benar),
> artinya perbedaan edisi kini hanya dapat dilacak melalui commit dan tag, tidak ada lagi cabang versi yang dapat di-checkout di repositori.

---

## Perubahan v1.17.0 (2026-09-15)

> Posisi edisi tidak berubah: repositori tetap hanya mengimplementasikan satu set kode edisi Lengkap (Full); kolom Lite/Standard adalah nilai perencanaan produk, cabangnya sudah diarsipkan dan dibekukan.

- **Panel admin dari dua menjadi tiga**: Angular 22 (`apps/angular/`) dan React 19 + Vite (`apps/react/`) bergabung,
  berdampingan dengan Flutter 3.x Web (`apps/flutter/`) yang sudah ada, ketiga ujung berbagi satu set antarmuka `/admin/v1`, `/api/v1`, `/open/v1`.
- **13 bahasa di seluruh platform** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id):
  - Pesan respons backend `resource/translations/<locale>/` —— `zh_CN` 565 entri, 11 bahasa lainnya masing-masing 544 entri, `en` 30 entri
    (cakupan: entri daun dari tiga berkas, label field `attributes` pada `validation.php` dihitung, kunci grupnya tidak —— `zh_CN` lebih 21 entri karena menerjemahkan 21 label field. Baris `en` "Inggris adalah key", kamusnya nyaris kosong)
  - Antarmuka panel admin: kamus sumber Angular 1456 kunci, React 1451 kunci × 11 bahasa baru; **dimuat lambat per bahasa**, setiap bahasa menjadi satu chunk
  - Generator: `scripts/gen-be-locales.mjs` (backend), `scripts/gen-fe-locales.mjs` (frontend, `--app angular|react`)
  - Pintu masuk peralihan: **ikon globe independen** di bilah atas + dropdown di pusat pribadi (sama di kedua ujung)
- **Flutter dan HarmonyOS masih bilingual 中文/Inggris**, tidak termasuk dalam putaran ini.
- **Dampak terhadap tabel di bawah**: kolom Full 163 tabel / 122 controller / 19 modul bisnis → **227 / 159 / 23**;
  matriks kelengkapan menambah baris 「Multi-bahasa (i18n)」 (baris modul 44 → 45, API backend 39 → 40, logika bisnis 33 → 34);
  catatan: setelah baris 「Multi-tenant」 yang duplikat di matriks digabung pada 2026-09-15, baris modul kembali ke 44 (API backend 39, logika bisnis 33),
  kalimat di atas adalah cakupan penambahan saat v1.17.0 dan dipertahankan apa adanya.

## Perubahan v1.4.0 (2026-09-05)

> Posisi edisi tidak berubah: repositori tetap hanya mengimplementasikan satu set kode edisi Lengkap (Full); kolom Lite/Standard adalah nilai perencanaan produk, cabangnya sudah diarsipkan dan dibekukan.

- **Versioning path seluruh situs**: `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*`;
  pengecualian hanya `GET /api/docs` (dokumen OpenAPI) dan webhook TMS; titik izin RBAC diautentikasi berdasarkan `method.path` tanpa segmen versi,
  data peran lama tanpa migrasi (commit `3ee1430`; kontrol header permintaan `API-Version` sudah dihapus lebih dulu, commit `8276a1b`).
- **P0 multi-organisasi dan akuntansi biaya**: akuntansi independen multi-organisasi (Company/LedgerPeriod), mesin laporan konsolidasi (konversi kurs akhir periode + eliminasi antar anak perusahaan,
  snapshot diutamakan disimpan ke FinanceConsolidationReport), akuntansi biaya stok/produksi (pengeluaran material + pengumpulan biaya).
- **P1 eksekusi manufaktur dan kolaborasi**: laporan kerja proses/upah satuan/serah-terima subkontrak/beban kapasitas/penelusuran batch-nomor seri (M1/M2/M6/M3), kontrol kredit (F7),
  kanvas alur persetujuan (B3), template cetak (B1), penggajian HR (H1/H2), inspeksi peralatan dengan pemindaian kode (E1), biaya proyek (P1).
- **P2 diferensiasi dan ekosistem**: sistem member (C1), buku besar nota dan rekonsiliasi bank-perusahaan (F6), kolam faktur masukan dan e-faktur (F5, otoritas pajak nyata sebagai titik adaptasi),
  kanal multi-driver dengan percobaan ulang saat gagal (B4), field kustom (B7), penagihan jatuh tempo multi-tenant (B5 —— middleware isolasi tenant masih belum terdaftar, aktif sebagian),
  pelatihan dan jaminan sosial (H3/H4).
- **Matriks fitur**: dari 44 baris modul, 33 baris ✅ ganda; 21 baris ditandai v1.4.0 (termasuk 1 baris aktif sebagian), lihat `docs/FUNCTIONS.md` §19.

> Rincian perubahan lihat `CHANGELOG.md` di akar repositori.

---

## Perbandingan Fitur

### Manajemen Sistem

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen pengguna (CRUD + massal + impor) | ✔ | ✔ | ✔ |
| Izin peran (pohon izin RBAC tiga tingkat) | ✔ | ✔ | ✔ |
| Konfigurasi sistem (pasangan kunci-nilai) | ✔ | ✔ | ✔ |
| Audit operasi (deteksi asal 8 platform) | ✔ | ✔ | ✔ |
| Unggah file / Ekspor Excel / Ekspor PDF | ✔ | ✔ | ✔ |
| Health check / metrik Prometheus | ✔ | ✔ | ✔ |
| Autentikasi JWT + captcha klik | ✔ | ✔ | ✔ |
| Proteksi keamanan 7 lapis | ✔ | ✔ | ✔ |
| Internasionalisasi (i18n) 13 bahasa (Angular/React; Flutter/HarmonyOS masih 中文/Inggris) | — | — | ✔ |

### Produk dan Data Dasar

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Arsip produk + SKU multi-spesifikasi | ✔ | ✔ | ✔ |
| Konversi multi-satuan + strategi harga | ✔ | ✔ | ✔ |
| Kategori produk (pohon) + merek | ✔ | ✔ | ✔ |
| Multi-gudang + multi-lokasi | ✔ | ✔ | ✔ |
| Arsip pemasok / pelanggan | ✔ | ✔ | ✔ |

### Manajemen Pembelian

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Permintaan pembelian + persetujuan | ✔ | ✔ | ✔ |
| Pesanan pembelian | ✔ | ✔ | ✔ |
| Penerimaan pembelian (otomatis masuk gudang + membuat utang) | ✔ | ✔ | ✔ |
| Retur pembelian | ✔ | ✔ | ✔ |
| Penyelesaian pemasok | ✔ | ✔ | ✔ |

### Manajemen Penjualan

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Surat penawaran (mendukung konversi ke pesanan) | ✔ | ✔ | ✔ |
| Pesanan penjualan | ✔ | ✔ | ✔ |
| Pengiriman penjualan (otomatis keluar gudang + membuat piutang) | ✔ | ✔ | ✔ |
| Retur penjualan | ✔ | ✔ | ✔ |
| Penyelesaian pelanggan + analisis margin kotor | ✔ | ✔ | ✔ |

### Manajemen Stok

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Stok real-time (presisi empat dimensi) | ✔ | ✔ | ✔ |
| Transaksi in/out stok | ✔ | ✔ | ✔ |
| Pelacakan batch + pelacakan nomor seri | ✔ | ✔ | ✔ |
| Transfer stok | ✔ | ✔ | ✔ |
| Manajemen stok opname (terencana + dinamis) | ✔ | ✔ | ✔ |
| Peringatan stok (alert batas bawah/atas) | ✔ | ✔ | ✔ |
| Perhitungan biaya rata-rata tertimbang bergerak | ✔ | ✔ | ✔ |

### Manajemen Keuangan

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Piutang-hutang (pembuatan otomatis + write-off) | ✔ | ✔ | ✔ |
| Surat penerimaan / surat pembayaran | ✔ | ✔ | ✔ |
| Jurnal kas & bank | ✔ | ✔ | ✔ |
| Reimbursement biaya (submit → setujui → transfer) | ✔ | ✔ | ✔ |
| Laporan laba rugi | ✔ | ✔ | ✔ |
| Depresiasi aset tetap | — | — | ✔ |
| Manajemen pajak (konfigurasi multi-jenis pajak) | — | — | ✔ |
| Multi-mata uang + manajemen kurs | — | — | ✔ |
| Manajemen anggaran (perbandingan anggaran vs aktual) | — | — | ✔ |
| Pusat biaya / pusat laba (perhitungan pohon) | — | — | ✔ |

### CRM

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen kontak pelanggan | ✔ | ✔ | ✔ |
| Catatan tindak lanjut | ✔ | ✔ | ✔ |
| Manajemen kampanye pemasaran | — | — | ✔ |
| Tiket layanan (prioritas + alokasi + proses penyelesaian) | — | — | ✔ |
| Laporan analisis pelanggan | — | — | ✔ |

### Kemampuan Platform

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Mesin alur persetujuan | — | — | ✔ |
| Sistem notifikasi pesan | — | — | ✔ |
| Dokumen API (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Modul Ekstensi

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen proyek (WBS/Gantt/jam kerja) | — | — | ✔ |
| Sumber daya manusia (organisasi/absensi/gaji) | — | — | ✔ |
| Manufaktur (BOM/MRP/order kerja/proses) | — | — | ✔ |
| Pembangun laporan kustom | — | — | ✔ |

---

## Skenario Penggunaan

| Edisi | Skenario yang Disarankan |
|------|---------|
| **Lite** | Perusahaan dagang skala kecil-menengah, berfokus pada pembelian-penjualan-stok + keuangan dasar, tidak memerlukan proses persetujuan dan modul ekstensi |
| **Standard** | Skala fitur setara, desain tabel data lebih ringkas, cocok sebagai dasar pengembangan kustomisasi |
| **Full** | Perusahaan skala menengah-besar, membutuhkan platform full-stack pembelian-penjualan-stok + keuangan + CRM + HR + manufaktur + manajemen proyek yang lengkap |

---

## Jalur Upgrade

| Edisi | Skala (tabel data / modul bisnis) | Keterangan |
|------|--------------------------|------|
| Lite (Ringkas) | 62 tabel / 6 modul bisnis (nilai rencana) | Tanpa persetujuan/notifikasi/HR/manufaktur/laporan |
| Standard (Standar) | 72 tabel / 6 modul bisnis (nilai rencana) | Model data lebih ringkas |
| Full (Lengkap) | 227 tabel <!-- stats:tables=227 --> / 23 modul bisnis <!-- stats:modules=23 --> | Kapabilitas platform perusahaan menyeluruh |

---

## Strategi Cabang (mulai 2026-08-27)

> Berlaku untuk tiga cabang versi `lite` / `standard` / `full`, selaras dengan job release CI (tag versi idempoten).
> **Tambahan kondisi terkini (terukur 2026-09-22)**: ketiga cabang sudah dihapus, sisa butir di bagian ini dipahami sebagai「arsip = commit dan tag」,
> tidak ada lagi cabang versi yang dapat di-checkout.

- **`main` adalah satu-satunya sumber pengembangan**: semua pengembangan fitur, perbaikan bug, dan upgrade dependensi selalu di-merge ke `main`, commit dieksekusi terpadu oleh Lead.
- **Cabang versi hanya diarsipkan, tidak dipelihara**: `lite` / `standard` / `full` dibekukan sebagai cabang arsip historis, tidak lagi menerima commit baru,
  tidak lagi menyinkronkan penambahan dari `main`, dan tidak dilakukan pembaruan paksa atau push (menghindari pemeliharaan tiga jalur kode); **setelah masa pembekuan berakhir ketiga cabang sudah dihapus**,
  konten arsipnya tersimpan pada commit `eea90c0` di riwayat `main`.
- **Perbedaan versi dicatat melalui tag versi**: rilis dibuat secara idempoten oleh job release CI berdasarkan tag terbaru menjadi `vX.Y.Z`
  (lihat `scripts/bump-version.sh`); perbedaan fitur antar edisi berpedoman pada tag dan tabel perbandingan fitur di atas, bukan pada pemeliharaan jalur kode cabang.
- **Verifikasi**: CI pada `main` adalah verifikasi rilis versi; cabang arsip tidak lagi menjalankan CI tersendiri. (Sejak 2026-09-15 dependensi job release adalah `docs` + `e2e`; job php tetap berjalan tetapi tidak menghalangi rilis —— titik merahnya adalah utang historis pengujian integrasi khusus CI, lihat komentar di `.github/workflows/ci.yml`.)
