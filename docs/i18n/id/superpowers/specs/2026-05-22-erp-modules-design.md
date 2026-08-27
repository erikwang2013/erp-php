# Spesifikasi Desain Modul Bisnis ERP

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. Ikhtisar

Di atas basis manajemen sistem `service/` yang ada, perluas tiga domain bisnis: pembelian-penjualan-stok (inventory), keuangan, dan CRM, untuk membangun sistem ERP lengkap.
Semua kode di-deploy monolitik di bawah `service/app/`, modul dibagi per direktori.

### 1.1 Perencanaan Tahap

| Tahap | Modul | Keterangan |
|------|------|------|
| Phase 1 | Data dasar produk + pembelian + penjualan + stok + keuangan + CRM | Loop tertutup bisnis inti |
| Phase 2 | Manajemen manufaktur + manajemen proyek | Perluasan berikutnya |

### 1.2 Tumpukan Teknologi (mengikuti yang ada)

- PHP 8.3+, webman v2, MySQL 8.0+
- Primary key BIGINT dibuat oleh snowflake-php
- ID lapisan API dienkripsi-dekripsi dengan hashids
- Autentikasi JWT, enkripsi data sensitif semuanya menggunakan paket seri erikwang2013/*
- Prefiks tabel `erp_`, soft delete, fungsi global tanpa `\`

---

## 2. Struktur Proyek

```
service/app/
├── admin/controller/          # Controller manajemen sistem (sudah ada, tidak berubah)
├── api/v1/controller/         # API klien (sudah ada + perluasan)
├── common/                    # Utilitas bersama (sudah ada Snowflake/Hashids/Encryption)
├── middleware/                # Middleware global (sudah ada 7)
├── model/                     # Semua model data (berbagi lintas modul)
├── service/                   # Lapisan logika bisnis (dibagi per modul)
│   ├── product/               # Produk dan data dasar
│   ├── purchase/              # Pembelian
│   ├── sales/                 # Penjualan
│   ├── inventory/             # Stok
│   ├── finance/               # Keuangan
│   └── crm/                   # CRM
├── controller/                # Controller modul bisnis
│   ├── product/               # Data dasar produk
│   ├── purchase/              # Pembelian
│   ├── sales/                 # Penjualan
│   ├── inventory/             # Stok
│   ├── finance/               # Keuangan
│   └── crm/                   # CRM
├── queue/                     # Tugas queue (sudah ada + queue bisnis)
├── process/                   # Proses (sudah ada Http, Monitor)
└── functions.php              # Fungsi bantu global (sudah ada)
```

### 2.1 Tanggung Jawab Per Lapisan

| Lapisan | Lokasi File | Tanggung Jawab |
|----|----------|------|
| Controller | `app/controller/{module}/` | Validasi parameter, format respons, memanggil Service |
| Service | `app/service/{module}/` | Logika bisnis, integrasi lintas modul, manajemen transaksi |
| Model | `app/model/` | Model data, relasi, query scope, trait encryptable |

---

## 3. Daftar Fungsi Modul

### 3.1 Produk dan Data Dasar

| Fungsi | Keterangan |
|------|------|
| Arsip produk | Nama produk, kode, barcode, kategori (pohon), merek, atribut spesifikasi |
| SKU multi-spesifikasi | Satu produk multi-spesifikasi, masing-masing SKU, barcode, harga independen |
| Konversi multi-unit | Rasio konversi unit dasar ↔ unit bantu |
| Strategi harga | Harga beli, harga grosir, harga eceran, harga level pelanggan |
| Manajemen kategori | Pohon kategori tak terbatas, mendukung pengurutan drag |
| Manajemen merek | CRUD merek |
| Manajemen gudang | Multi-gudang, setiap gudang memiliki multi-lokasi |
| Manajemen lokasi | Lokasi penyimpanan di bawah gudang, kode unik |
| Arsip pemasok | Nama, kontak, telepon, alamat, rekening bank, tarif pajak |
| Arsip pelanggan | Nama, kontak, telepon, alamat, level pelanggan, batas kredit |

### 3.2 Modul Pembelian

| Fungsi | Keterangan |
|------|------|
| Permintaan pembelian | Departemen/individu mengajukan kebutuhan pembelian, mendukung proses persetujuan |
| Pesanan pembelian | Dibuat berdasarkan permintaan atau langsung, menghubungkan pemasok, produk, jumlah, harga satuan |
| Penerimaan pembelian | Terima barang sesuai pesanan, buat nota masuk gudang, mendukung penerimaan bertahap |
| Retur pembelian | Kembalikan ke pemasok, buat nota keluar gudang sebagai penutup |
| Rekonsiliasi pemasok | Merangkum jumlah pembelian, sudah dibayar, hutang per pemasok + periode waktu |
| Penyelesaian pembelian | Menghapusbukukan penerimaan pembelian dan pembayaran |

### 3.3 Modul Penjualan

| Fungsi | Keterangan |
|------|------|
| Penawaran | Penawaran harga ke pelanggan, mendukung konversi ke pesanan penjualan |
| Pesanan penjualan | Pelanggan memesan, menghubungkan produk, jumlah, harga satuan, diskon |
| Pengiriman penjualan | Kirim sesuai pesanan, buat nota keluar gudang, mendukung pengiriman bertahap |
| Retur penjualan | Retur dari pelanggan, buat nota masuk gudang sebagai penutup |
| Rekonsiliasi pelanggan | Merangkum jumlah penjualan, sudah diterima, piutang per pelanggan + periode waktu |
| Penyelesaian penjualan | Menghapusbukukan pengiriman penjualan dan penerimaan |
| Margin kotor penjualan | Menghitung margin kotor per dimensi pesanan/produk/pelanggan |

### 3.4 Modul Stok

| Fungsi | Keterangan |
|------|------|
| Stok real-time | Jumlah stok dimensi gudang+lokasi+batch+SKU |
| Lacak batch | Tanggal produksi, tanggal kedaluwarsa, nomor batch |
| Lacak seri | Nomor seri unik, dicatat saat masuk/keluar stok |
| Transaksi masuk/keluar | Log terpadu semua perubahan stok (nomor dokumen sumber+tipe+jumlah+arah) |
| Transfer stok | Transfer antar gudang/lokasi, buat nota transfer masuk/keluar |
| Tugas opname | Opname terjadwal (per gudang/kategori) + opname dinamis (per SKU) |
| Selisih opname | Surplus/defisit stok otomatis membuat transaksi masuk/keluar |
| Peringatan stok | Atur batas atas/bawah per SKU+gudang, alert saat di bawah batas bawah atau di atas batas atas |
| Kalkulasi biaya | Metode rata-rata bergerak, hitung ulang harga biaya setiap masuk stok |

### 3.5 Modul Keuangan

| Fungsi | Keterangan |
|------|------|
| Akun akuntansi | Pohon akun (aset/kewajiban/ekuitas/pendapatan/biaya), mendukung kustom |
| Piutang/hutang | Dibuat otomatis dari dokumen penjualan/pembelian, dihapusbukukan manual |
| Nota penerimaan | Penerimaan multi-akun, multi-metode (tunai/bank/WeChat/Alipay) |
| Nota pembayaran | Pembayaran multi-akun, multi-metode |
| Penghapusbukuan | Nota penerimaan menghapusbukukan piutang, nota pembayaran menghapusbukukan hutang |
| Jurnal kas bank | Mencatat transaksi masuk/keluar per akun + tanggal |
| Reimburse biaya | Ajukan→setujui→transfer, menghubungkan akun |
| Laporan laba rugi | Merangkum pendapatan/biaya/laba per bulan |

### 3.6 Modul CRM

| Fungsi | Keterangan |
|------|------|
| Manajemen pelanggan | Arsip pelanggan (terhubung dengan pelanggan data dasar) |
| Manajemen kontak | Banyak kontak di bawah pelanggan |
| Catatan tindak lanjut | Metode, waktu, konten, rencana tindak lanjut berikutnya |
| Funnel penjualan | Konfigurasi tahap + estimasi jumlah peluang + rasio konversi tahap |

---

## 4. Desain Tabel Database

Semua tabel berprefix `erp_`, `id` BIGINT non-auto-increment, berisi `created_at`/`updated_at`/`deleted_at`.

### 4.1 Data Dasar Produk

```
erp_product              Tabel utama produk
erp_product_sku         SKU/spesifikasi produk
erp_product_unit        Konversi multi-unit
erp_product_price       Strategi harga
erp_category            Kategori produk (pohon parent_id)
erp_brand               Merek
erp_warehouse           Gudang
erp_location            Lokasi
erp_supplier            Pemasok
erp_customer            Pelanggan
erp_customer_level      Level pelanggan
```

### 4.2 Modul Pembelian

```
erp_purchase_apply       Permintaan pembelian
erp_purchase_apply_item  Detail permintaan
erp_purchase_order       Pesanan pembelian
erp_purchase_order_item  Detail pesanan
erp_purchase_receive     Tabel utama penerimaan pembelian
erp_purchase_receive_item Detail penerimaan
erp_purchase_return      Tabel utama retur pembelian
erp_purchase_return_item Detail retur
erp_purchase_settlement  Catatan penyelesaian pemasok
```

### 4.3 Modul Penjualan

```
erp_sales_quotation      Tabel utama penawaran
erp_sales_quotation_item Detail penawaran
erp_sales_order          Tabel utama pesanan penjualan
erp_sales_order_item     Detail pesanan
erp_sales_delivery       Tabel utama pengiriman penjualan
erp_sales_delivery_item  Detail pengiriman
erp_sales_return         Tabel utama retur penjualan
erp_sales_return_item    Detail retur
erp_sales_settlement     Catatan penyelesaian pelanggan
```

### 4.4 Modul Stok

```
erp_inventory            Stok real-time
erp_inventory_batch      Informasi batch
erp_inventory_serial     Catatan nomor seri
erp_inventory_flow       Transaksi masuk/keluar
erp_transfer             Tabel utama transfer
erp_transfer_item        Detail transfer
erp_check_task           Tugas opname
erp_check_detail         Detail opname
erp_inventory_alert_rule Aturan peringatan stok
erp_inventory_alert_log  Log peringatan stok
erp_cost_record          Catatan kalkulasi biaya
```

### 4.5 Modul Keuangan

```
erp_finance_account      Akun akuntansi
erp_finance_voucher      Voucher pembukuan
erp_finance_voucher_item Entri voucher
erp_finance_ar_ap        Detail piutang/hutang
erp_finance_receipt      Nota penerimaan
erp_finance_payment      Nota pembayaran
erp_finance_cash_journal Jurnal kas bank
erp_finance_expense      Nota reimburse biaya
erp_finance_expense_item Detail reimburse
erp_finance_profit       Snapshot laporan laba rugi
erp_finance_bank_account Rekening bank
```

### 4.6 Modul CRM

```
erp_crm_funnel_stage     Konfigurasi tahap funnel penjualan
erp_crm_opportunity      Peluang
erp_crm_follow_record    Catatan tindak lanjut
erp_crm_contact          Kontak
```

---

## 5. Rute API

Mengikuti namespace `/admin/*`, rantai middleware lengkap (Auth → Permission → OperationLog).

```
# Data dasar produk
/admin/product/*          CRUD produk/kategori/merek
/admin/warehouse/*        CRUD gudang/lokasi
/admin/supplier/*         CRUD pemasok
/admin/customer/*         CRUD pelanggan/level pelanggan

# Pembelian
/admin/purchase/apply/*      Permintaan pembelian + persetujuan
/admin/purchase/order/*      Pesanan pembelian
/admin/purchase/receive/*    Penerimaan pembelian
/admin/purchase/return/*     Retur pembelian
/admin/purchase/settlement/* Penyelesaian pemasok

# Penjualan
/admin/sales/quotation/*     Penawaran (termasuk konversi ke pesanan)
/admin/sales/order/*         Pesanan penjualan
/admin/sales/delivery/*      Pengiriman penjualan
/admin/sales/return/*        Retur penjualan
/admin/sales/settlement/*    Penyelesaian pelanggan

# Stok
/admin/inventory/*           Kueri stok real-time
/admin/inventory/batch/*     Manajemen batch
/admin/inventory/serial/*    Manajemen nomor seri
/admin/inventory/flow/*      Transaksi masuk/keluar
/admin/inventory/transfer/*  Transfer
/admin/inventory/check/*     Opname
/admin/inventory/alert/*     Aturan peringatan

# Keuangan
/admin/finance/account/*     Akun akuntansi
/admin/finance/voucher/*     Voucher pembukuan
/admin/finance/receipt/*     Nota penerimaan
/admin/finance/payment/*     Nota pembayaran
/admin/finance/cash/*        Jurnal kas bank
/admin/finance/expense/*     Reimburse biaya
/admin/finance/report/*      Laporan keuangan

# CRM
/admin/crm/opportunity/*     Peluang
/admin/crm/follow/*          Catatan tindak lanjut
/admin/crm/funnel/*          Konfigurasi tahap funnel
/admin/crm/contact/*         Kontak

# Dasbor (perluasan)
/admin/dashboard/sales       Panel penjualan
/admin/dashboard/inventory   Panel stok
/admin/dashboard/finance     Panel keuangan
```

API klien `/api/v1/*` menyediakan antarmuka ringan (kueri produk, buat pesanan, status pesanan, dll.), untuk dipanggil Flutter App / HarmonyOS.

---

## 6. Alur Data Lintas Modul

```
Penerimaan pembelian → inventory_flow(masuk) → inventory(+jumlah) → cost_record(hitung ulang rata-rata)
       → finance_ar_ap(hutang)

Pengiriman penjualan → inventory_flow(keluar) → inventory(-jumlah) → cost_record(catat biaya)
       → finance_ar_ap(piutang)

Penghapusbukuan nota penerimaan → finance_ar_ap(pembaruan sudah diterima) → cash_journal(catat pendapatan)
Penghapusbukuan nota pembayaran → finance_ar_ap(pembaruan sudah dibayar) → cash_journal(catat pengeluaran)

Selisih opname → inventory_flow(surplus masuk/defisit keluar) → inventory(penyesuaian)

Reimburse biaya (sudah ditransfer) → finance_payment(dibuat otomatis) → cash_journal(catat pengeluaran)
```

Cara implementasi: setelah setiap operasi bisnis selesai, aksi downstream dipicu melalui event, tidak memanggil Service lintas modul secara langsung.

---

## 7. Ekspor Excel/PDF

- Semua halaman daftar mendukung parameter `?export=excel`, menghasilkan file .xlsx dengan style
- Panel dasbor mendukung `?export=pdf`, mengeluarkan laporan PDF berisi grafik
- Bidang sensitif (jumlah uang, nomor telepon, dll.) di-masking dengan EncryptionService saat diekspor
- Menggunakan ulang kelas dasar ExportController yang ada, controller setiap modul mewarisi dan mengimplementasikan definisi kolom ekspor sendiri

---

## 8. Panel Dasbor

| Panel | Rute | Metrik |
|------|------|------|
| Ringkasan operasional | `/admin/dashboard` | Penjualan hari ini/bulan ini, pembelian, piutang/hutang, total nilai stok, margin kotor |
| Papan stok | `/admin/dashboard/inventory` | Daftar peringatan, tren masuk/keluar, tingkat hunian lokasi |
| Papan penjualan | `/admin/dashboard/sales` | Grafik tren, peringkat pelanggan, produk laris, rasio konversi funnel |
| Papan keuangan | `/admin/dashboard/finance` | Tren pemasukan-pengeluaran, umur piutang/hutang, arus kas |

Data di-cache Redis 5 menit, mendukung peralihan rentang waktu.

---

## 9. Desain Frontend

| Platform | Direktori | Framework | Gaya |
|----|------|------|------|
| Web panel admin | `apps/flutter/` (web) | Flutter + GetX | Panel admin PC (sidebar+topbar+area konten) |
| App klien | `apps/flutter/` (app) | Flutter + GetX | Gaya native mobile |
| HarmonyOS | `apps/harmonyos/` | ArkTS | Native HarmonyOS, gaya App |

Kode Flutter membedakan rendering Web PC dan mobile melalui routing dan penilaian tata letak.

---

## 10. Urutan Implementasi

| Langkah | Konten | Dependensi |
|------|------|------|
| 1 | SQL migrasi database (semua tabel bisnis) | Tidak ada |
| 2 | Lapisan Model (model data semua modul) | Langkah 1 |
| 3 | Modul data dasar produk (CRUD) | Langkah 2 |
| 4 | Modul pembelian | Langkah 3 |
| 5 | Modul penjualan | Langkah 3 |
| 6 | Modul stok + kalkulasi biaya | Langkah 4,5 |
| 7 | Modul keuangan | Langkah 4,5,6 |
| 8 | Modul CRM | Langkah 3 |
| 9 | Panel dasbor | Langkah 4-8 |
| 10 | Ekspor Excel/PDF | Langkah 4-9 |
| 11 | API klien (/api/*) | Langkah 4-8 |
| 12 | Halaman frontend Flutter | Langkah 4-10 |
| 13 | Halaman frontend HarmonyOS | Langkah 11 |
