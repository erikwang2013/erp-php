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
| Tabel data | 62 (nilai rencana) | 72 (nilai rencana) | 163 <!-- stats:tables=163 --> |
| Controller | 48 (nilai rencana) | 42 (nilai rencana) | 123 <!-- stats:controllers=122 --> |
| Modul bisnis | 6 (nilai rencana) | 6 (nilai rencana) | 19 <!-- stats:modules=19 --> |

> **Metodologi statistik**: repositori saat ini hanya mengimplementasikan satu set kode edisi Lengkap (Full); kolom Lite/Standard adalah nilai perencanaan produk (tidak ada cabang terkait di codebase),
> tidak ikut validasi doc-stats. Angka kolom Full diukur oleh `scripts/doc-stats.sh` (163 tabel / 123 controller / 19 modul bisnis),
> konsisten dengan metodologi lampiran `FUNCTIONS.md`.

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
| Proteksi keamanan 18 lapis | ✔ | ✔ | ✔ |
| Internasionalisasi (i18n) bilingual 中文/English | — | — | ✔ |

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
| Dokumen API (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full (Lengkap) | 163 tabel <!-- stats:tables=163 --> / 19 modul bisnis <!-- stats:modules=19 --> | Kapabilitas platform perusahaan menyeluruh |

---

## Strategi Cabang (mulai 2026-08)

> Dokumen ini sesuai dengan konvensi cabang versi repositori saat ini, berlaku untuk tiga cabang `lite` / `standard` / `full`.

- **`main` adalah satu-satunya sumber pengembangan**: semua pengembangan fitur, perbaikan bug, upgrade dependensi selalu di-merge ke `main`.
- **Cabang versi hanya di-cherry-pick saat rilis**: `lite` / `standard` / `full` tidak lagi menerima commit harian sebagai jalur pengembangan independen,
  hanya saat rilis, engineer versi melakukan cherry-pick fitur terkait dari `main` (atau melakukan satu kali merge keseluruhan sesuai kebutuhan),
  dan mempertahankan niat pemangkasan masing-masing di cabang (perbedaan modul lihat tabel perbandingan fitur di atas).
- **Prinsip pemangkasan**: cabang versi = subset dari main. Saat menggabungkan/memindahkan konten main, jika konflik jatuh pada logika pemangkasan versi
  (misalnya perbedaan modul di EDITIONS.md, pemangkasan route), pertahankan niat pemangkasan cabang; kode yang tidak terkait selalu mengikuti versi main.
- **Verifikasi**: setelah cabang versi di-merge, harus lulus pemeriksaan sintaks penuh `php -l`; pengujian yang tidak berlaku karena pemangkasan diizinkan dilewati dengan mencatat alasannya.
- **Rilis**: merge/pemindahan cabang versi dilakukan oleh engineer versi dan dikirim sebagai merge commit; commit di `main` dieksekusi terpadu oleh Lead.
