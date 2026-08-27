# Sistem ERP Terbuka — Manual Fitur

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ringkasan

Sistem ERP Terbuka (open-erp) mencakup 19 domain bisnis <!-- stats:modules=19 -->, 163 tabel data <!-- stats:tables=163 -->, menyediakan sistem manajemen perusahaan full-stack mulai dari pembelian-penjualan-stok hingga manufaktur produksi, dari pembukuan keuangan hingga sumber daya manusia. Internasionalisasi: dukungan bilingual 中文/English, peralihan otomatis melalui header permintaan Accept-Language.

> Dokumen API: setelah layanan dimulai, akses `http://localhost:8787/apidoc` untuk melihat dokumen antarmuka interaktif (dihasilkan otomatis oleh hg/apidoc)

---

## 1. Manajemen Sistem

### 1.1 Manajemen Pengguna
- Manajemen siklus hidup penuh akun admin (buat/ubah/hapus/aktif-nonaktif)
- Operasi massal: hapus massal, aktif/nonaktif massal
- Impor massal pengguna melalui Excel, validasi per baris + laporan kesalahan
- Kata sandi disimpan dengan hash bcrypt, mengubah kata sandi memerlukan konfirmasi kata sandi lama
- Operasi sensitif seperti penghapusan memerlukan verifikasi ulang kata sandi pengguna saat ini
- Nomor ponsel/email/nomor KTP disimpan terenkripsi, daftar otomatis di-masking

### 1.2 Peran & Izin (RBAC)
- Manajemen peran: buat/ubah/hapus, slug sebagai identifikasi unik
- Pohon izin: struktur pohon tanpa batas, tiga jenis — menu (terlihat di navigasi), tombol (operasi dalam halaman), API (akses antarmuka)
- Format penanda izin: `{method}.{path}`, contoh `get.admin/product`, `post.admin/user/batch/destroy`
- Relasi banyak-ke-banyak peran-izin, super admin melewati semua pemeriksaan izin
- Middleware AdminPermission meng-cache izin pengguna di Redis (TTL=60s)

### 1.3 Konfigurasi Sistem
- Penyimpanan pasangan kunci-nilai, mendukung manajemen grup
- Tipe nilai: string/integer/boolean/JSON/array

### 1.4 Audit Operasi
- Mencatat otomatis semua operasi POST/PUT/DELETE
- Mencatat operator, aksi, metode, path, IP, parameter (bidang sensitif di-masking), waktu
- Deteksi otomatis 8 platform sumber (Web/Flutter/HarmonyOS/API, dll.)
- Kueri hanya-baca, tidak dapat dihapus atau diubah

### 1.5 Perlindungan Keamanan
- Pertahanan berlapis 18 lapis: pembatasan metode HTTP, pencegahan XSS/SQL injection/path traversal/command injection/CSRF
- Captcha klik (validasi wajib saat login/registrasi)
- Rate limit Redis sliding window (atomik Lua, default 60 kali/menit)
- Penguncian akun: 5 kali gagal terkunci 15 menit
- Pembatasan sesi konkuren: maksimal 3 Token valid per pengguna
- Header CSP, security.txt (RFC 9116)
- Verifikasi ulang acak untuk operasi sensitif (poster-php)

---

## 2. Produk & Data Dasar

### 2.1 Manajemen Produk
- Arsip produk: kode (unik), nama, barcode, spesifikasi, unit dasar, gambar, deskripsi
- SKU multi-spesifikasi: beberapa SKU di bawah produk yang sama, masing-masing dengan kode, barcode, atribut spesifikasi (JSON) independen
- Konversi multi-satuan: rasio konversi unit dasar dan unit pembantu
- Strategi harga: harga beli, harga grosir, harga ecer, harga tingkat pelanggan
- Mendukung pencarian teks lengkap ES

### 2.2 Kategori Produk
- Struktur kategori pohon tanpa batas
- Mendukung pengurutan, aktif/nonaktif
- Pengurutan seret-dan-lepas

### 2.3 Manajemen Merek
- Nama merek, Logo, deskripsi, urutan

### 2.4 Gudang & Lokasi
- Manajemen multi-gudang (nama, kode, alamat, penanggung jawab, telepon)
- Setiap gudang memiliki banyak lokasi (kode unik dalam gudang)

### 2.5 Manajemen Pemasok
- Kode pemasok, nama, kontak, telepon/email (terenkripsi), alamat
- Informasi rekening bank (disimpan terenkripsi), nomor pajak, tarif pajak
- Pencarian teks lengkap ES

### 2.6 Manajemen Pelanggan
- Kode pelanggan, nama, tingkat pelanggan, batas kredit
- Kontak/telepon/email (terenkripsi) / alamat
- Tingkat pelanggan: nama, rasio diskon default
- Pencarian teks lengkap ES

---

## 3. Manajemen Pembelian

### 3.1 Permintaan Pembelian
- Departemen/personel mengajukan kebutuhan pembelian
- Alur persetujuan: menunggu persetujuan → disetujui/ditolak → dikonversi ke pesanan
- Dapat terhubung dengan mesin alur persetujuan

### 3.2 Pesanan Pembelian
- Terkait pemasok, rincian produk (jumlah, harga satuan, jumlah nilai)
- Status: menunggu review → direview → diterima sebagian → diterima → dibatalkan
- Dapat dibuat berdasarkan permintaan, atau dibuat langsung

### 3.3 Penerimaan Pembelian (linkage lintas modul)
- Penerimaan berdasarkan pesanan, mendukung bertahap
- Penerimaan memicu otomatis: ① masuk gudang (perhitungan biaya rata-rata tertimbang bergerak) ② membuat catatan hutang ③ memperbarui jumlah diterima pesanan

### 3.4 Retur Pembelian
- Dikembalikan ke pemasok, membuat penyesuaian (offset) keluar gudang

### 3.5 Penyelesaian Pemasok
- Rekap per pemasok: jumlah pembelian, sudah dibayar, hutang
- Status: belum diselesaikan/sebagian diselesaikan/diselesaikan

---

## 4. Manajemen Penjualan

### 4.1 Surat Penawaran
- Menawarkan harga ke pelanggan, mendukung konversi ke pesanan penjualan
- Status: draf → sudah ditawarkan → dikonversi ke pesanan → kedaluwarsa

### 4.2 Pesanan Penjualan
- Terkait pelanggan, rincian produk (jumlah, harga satuan, diskon)
- Status: menunggu review → direview → dikirim sebagian → dikirim → dibatalkan

### 4.3 Pengiriman Penjualan (linkage lintas modul)
- Pengiriman berdasarkan pesanan, mendukung bertahap
- Pengiriman memicu otomatis: ① keluar gudang (dengan biaya rata-rata tertimbang bergerak) ② membuat catatan piutang ③ memperbarui jumlah terkirim pesanan

### 4.4 Retur Penjualan
- Retur pelanggan, membuat penyesuaian (offset) masuk gudang

### 4.5 Penyelesaian Pelanggan & Margin Kotor
- Rekap per pelanggan: jumlah penjualan, sudah diterima, piutang
- Perhitungan margin kotor per pesanan/produk/dimensi pelanggan

---

## 5. Manajemen Stok

### 5.1 Stok Real-time
- Presisi empat dimensi: gudang + lokasi + batch + SKU
- Mendukung multi-gudang, multi-lokasi
- Kueri stok real-time

### 5.2 Transaksi Masuk/Keluar
- Semua perubahan stok dicatat secara seragam (arah, jumlah, harga pokok, nomor dokumen sumber, waktu)

### 5.3 Pelacakan Batch
- Tanggal produksi, tanggal kedaluwarsa, nomor batch
- Batch dicatat saat masuk/keluar gudang

### 5.4 Pelacakan Nomor Seri
- Manajemen nomor seri unik
- Status dicatat saat masuk/keluar gudang (di gudang/sudah keluar)

### 5.5 Perhitungan Biaya
- Metode rata-rata tertimbang bergerak
- Rumus: harga rata-rata baru = (total nilai stok lama + total nilai masuk kali ini) / (jumlah stok lama + jumlah masuk kali ini)
- Dihitung ulang otomatis setiap masuk gudang, keluar gudang menghitung biaya dengan harga rata-rata saat ini

### 5.6 Transfer Stok
- Transfer antar gudang/antar lokasi
- Status: menunggu transfer → sudah ditransfer keluar → sudah ditransfer masuk → selesai
- Otomatis membuat transaksi transfer keluar/masuk

### 5.7 Manajemen Stock Opname
- Opname terencana (per gudang/kategori) + opname dinamis (per SKU)
- Mencatat jumlah buku vs jumlah aktual
- Selisih otomatis membuat transaksi surplus/defisit opname

### 5.8 Peringatan Stok
- Mengatur batas atas/bawah per SKU+gudang
- Di bawah batas bawah/di atas batas atas otomatis mencatat log peringatan

---

## 6. Manajemen Keuangan

### 6.1 Piutang-Hutang
- Dibuat otomatis dari penerimaan pembelian/pengiriman penjualan
- Status: belum ditutup → ditutup sebagian → ditutup
- Perlindungan idempoten untuk dokumen sumber yang sama

### 6.2 Manajemen Penerimaan
- Multi-akun (kas/bank/WeChat/Alipay)
- Setelah direview otomatis memperbarui saldo akun dan jurnal kas
- Mendukung penutupan catatan piutang

### 6.3 Manajemen Pembayaran
- Logika sama dengan penerimaan, arah berlawanan
- Mendukung penutupan catatan hutang

### 6.4 Jurnal Kas & Bank
- Mencatat transaksi masuk/keluar per akun + tanggal
- Saldo rekening bank diperbarui real-time

### 6.5 Reimbursement Biaya
- Alur: submit → persetujuan → transfer
- Setelah transfer otomatis membuat dokumen pembayaran + jurnal

### 6.6 Laporan Laba Rugi
- Rekap bulanan: pendapatan operasional, biaya operasional, biaya, laba
- Penyimpanan snapshot (unik per tahun+bulan)

### 6.7 Aset Tetap
- Siklus hidup aset penuh: perolehan → penggunaan → depresiasi → disposisi
- Depresiasi garis lurus: (nilai perolehan - nilai residu) / jumlah bulan penggunaan
- Pencadangan depresiasi bulanan, otomatis membuat catatan depresiasi
- Mencatat: nilai perolehan, nilai residu, masa manfaat, jumlah depresiasi bulanan, depresiasi kumulatif, nilai bersih

### 6.8 Manajemen Pajak
- Multi jenis pajak: PPN/pajak penghasilan badan/pajak penghasilan pribadi/bea meterai
- Konfigurasi tarif pajak fleksibel (termasuk 4 data seed tarif pajak default)
- Terkait dengan dokumen pembelian/penjualan, otomatis mencatat jumlah pajak

### 6.9 Multi-Mata Uang
- Manajemen mata uang: CNY/USD/EUR/JPY (termasuk 4 data seed mata uang default)
- Penanda mata uang basis (fungsional)
- Kurs dikelola berdasarkan tanggal efektif

### 6.10 Manajemen Anggaran
- Penyusunan anggaran tahunan: per pusat biaya + akun + bulan
- Analisis perbandingan anggaran vs aktual (tingkat eksekusi + selisih)
- Status: draf → disetujui → berjalan → ditutup

### 6.11 Pusat Biaya/Pusat Laba
- Struktur hierarki pohon
- Pengumpulan biaya + alokasi biaya
- Pusat laba dibukukan secara independen

---

## 7. CRM

### 7.1 Manajemen Pelanggan
- Arsip pelanggan (terkait pelanggan data dasar)
- Manajemen multi-kontak (penanda kontak utama)
- Telepon/email kontak disimpan terenkripsi

### 7.2 Catatan Tindak Lanjut
- Cara tindak lanjut: telepon/kunjungan/email/pesan/lainnya
- Mencatat isi tindak lanjut, rencana tindak lanjut berikutnya, waktu tindak lanjut berikutnya
- Terkait pelanggan, kontak

### 7.3 Kampanye Pemasaran
- Siklus hidup kampanye penuh: direncanakan → berjalan → selesai → dibatalkan
- Multi-kanal: email/SMS/telepon/event/media sosial
- Pelacakan pelanggan peserta, statistik tingkat konversi
- Perbandingan anggaran vs biaya aktual

### 7.4 Tiket Layanan
- Manajemen tiket: menunggu diproses → diproses → diselesaikan → ditutup
- Prioritas: rendah/sedang/tinggi/darurat
- Kategori: dukungan teknis/keluhan/konsultasi/retur tukar/lainnya
- Alokasi penangan + balasan (publik/catatan internal)

### 7.5 Laporan Analisis Pelanggan
- 6 indikator inti: pelanggan baru/pelanggan aktif/tingkat retensi/nilai transaksi rata-rata/CLV/tingkat penyelesaian tiket
- Laporan dibuat otomatis (snapshot data JSON)
- Mendukung bulanan/kuartalan/tahunan

---

## 8. Mesin Alur Persetujuan

### 8.1 Template Alur Kerja
- Rantai persetujuan yang dapat dikonfigurasi: membuat alur persetujuan berbeda per jenis dokumen
- Node persetujuan: persetujuan berurutan, mendukung routing bersyarat (berdasarkan jumlah/departemen, dll.)
- Jenis penyetuju: orang tertentu/peran/kepala departemen/atasan langsung
- Mendukung penolakan, pelimpahan

### 8.2 Operasi Persetujuan
- Submit → persetujuan bertingkat → disetujui/ditolak/ditarik
- Daftar persetujuan saya (menunggu persetujuan + sudah diproses)
- Pelacakan lengkap catatan persetujuan

---

## 9. Sistem Notifikasi Pesan

### 9.1 Manajemen Notifikasi
- Pesan dalam sistem: status belum dibaca/sudah dibaca
- Template notifikasi: mendukung penggantian variabel (mis. "Anda memiliki persetujuan menunggu dari {pemohon}")
- Multi-kanal: notifikasi dalam sistem (sudah terwujud) → email (terwujud berbasis log file, SMTP menunggu integrasi) → WeChat Enterprise/DingTalk (titik adaptasi dicadangkan)
- Preferensi notifikasi pengguna

### 9.2 Notifikasi Otomatis
- Pengingat tugas persetujuan
- Dorongan peringatan stok
- Notifikasi alokasi tiket
- Dikirim terpusat melalui NotificationService

---

## 10. Manajemen Proyek

### 10.1 Proyek
- Siklus hidup proyek penuh: dalam perencanaan → berjalan → tertunda → selesai → dibatalkan
- Prioritas: rendah/sedang/tinggi/darurat
- Perbandingan anggaran proyek vs biaya aktual
- Progres tugas teragregasi otomatis menjadi progres proyek
- Terkait pelanggan, menunjuk manajer proyek

### 10.2 Dekomposisi Tugas WBS
- Struktur tugas pohon (tugas induk-anak tanpa batas)
- Mendukung data Gantt chart (dependensi tugas, garis waktu)
- Status tugas: belum mulai → berjalan → selesai → tertunda
- Estimasi jam vs jam aktual

### 10.3 Catatan Jam Kerja
- Mencatat jam kerja per proyek/tugas/personel/tanggal
- Otomatis mengagregasi jam aktual tugas
- Mendukung perhitungan biaya proyek

---

## 11. Manajemen Sumber Daya Manusia

### 11.1 Struktur Organisasi
- Departemen: struktur hierarki pohon
- Posisi: dibagi per departemen, mendukung pengurutan
- Arsip karyawan: kode, nama, jenis kelamin, tanggal lahir, tanggal masuk, status
- Bidang sensitif terenkripsi: nomor ponsel, email, nomor KTP, nomor rekening bank

### 11.2 Manajemen Absensi
- Aturan absensi: jam masuk-pulang, kelonggaran terlambat, kelonggaran pulang lebih awal
- Catatan check-in: check-in/check-out kerja, otomatis menghitung menit terlambat/pulang lebih awal
- Status: normal/terlambat/pulang lebih awal/tanpa check-in/cuti/dinas luar
- Manajemen cuti: cuti tahunan/cuti alasan pribadi/cuti sakit/cuti nikah/cuti melahirkan/cuti pengganti

### 11.3 Manajemen Gaji
- Konfigurasi item gaji: item pendapatan/item potongan, dikenakan pajak atau tidak, jumlah default
- Perhitungan gaji: gaji pokok + kinerja + lembur - potongan - pajak penghasilan = gaji bersih
- Mendukung pembuatan massal gaji bulanan
- Konfirmasi pembayaran gaji

---

## 12. Manufaktur Produksi

### 12.1 BOM (Bill of Materials)
- BOM produk: produk jadi → komponen → bahan baku, struktur pohon multi-level
- Manajemen versi: draf → efektif → nonaktif
- Rincian komponen: jumlah penggunaan, unit, tingkat susut

### 12.2 Work Order Produksi
- Membuat work order produksi berdasarkan BOM
- Status: menunggu produksi → dalam produksi → selesai → dibatalkan
- Rencana produksi vs produksi aktual
- Tanggal mulai/selesai rencana vs waktu mulai/selesai aktual

### 12.3 Routing Proses
- Mendefinisikan alur proses per produk
- Setiap proses terkait stasiun kerja, jam standar
- Pengurutan proses

### 12.4 Stasiun Kerja
- Kode stasiun kerja, nama, kapasitas (per jam)
- Aktif/nonaktif

### 12.5 MRP (Material Requirements Planning)
- Perhitungan kebutuhan bersih: total kebutuhan - penerimaan terencana - stok saat ini = kebutuhan bersih
- Membuat rencana per periode (tahun+bulan)
- Status: draf → sudah dibuat → dikonfirmasi

---

## 13. Pembangun Laporan Kustom

### 13.1 Template Laporan
- Bidang kustom: pilih bidang tabel data, metode agregasi (penjumlahan/penghitungan/rata-rata/maksimum/minimum)
- Filter kustom: teks/dropdown/rentang tanggal/rentang angka
- Jenis grafik: tabel/bar chart/line chart/pie chart/kartu indikator KPI
- Dikelompokkan per modul (produk/pembelian/penjualan/stok/keuangan/CRM/HR/manufaktur/proyek)

### 13.2 Eksekusi Laporan
- Pembuatan SQL dinamis (berdasarkan konfigurasi bidang dan filter)
- Perlindungan whitelist nama tabel (diparse dari install.sql)
- Snapshot dataset hasil (disimpan JSON)

### 13.3 Laporan Terjadwal
- Frekuensi penjadwalan: setiap hari/setiap minggu/setiap bulan
- Konfigurasi penerima
- Eksekusi otomatis + penyimpanan hasil

---

## 14. Papan Dasbor

### 14.1 Ringkasan Operasional
- Penjualan/pembelian hari ini/bulan ini
- Total piutang/hutang, total nilai stok, margin kotor
- Cache Redis 5 menit

### 14.2 Papan Penjualan
- Tren penjualan, peringkat pelanggan Top10
- Mendukung peralihan rentang waktu

### 14.3 Papan Stok
- Total nilai stok, statistik peringatan (di bawah batas bawah/di atas batas atas)
- Tren masuk/keluar (per hari/arah)

### 14.4 Papan Keuangan
- Total piutang/hutang, penerimaan/pembayaran bulan ini
- Ringkasan saldo kas dan bank

---

## Alur Data Lintas Modul

```
采购收货 → 自动入库(移动加权平均成本) → 生成应付记录
销售发货 → 自动出库 → 生成应收记录
收付款 → 核销应收应付 → 更新日记账
盘点差异 → 自动生成盈亏出入库流水
审批提交 → 工作流引擎路由 → 逐级审批 → 通知推送
费用报销打款 → 自动生成付款单 + 日记账
资产折旧 → 按月计提 → 成本分摊到成本中心
MRP 运算 → BOM 展开 → 净需求计算 → 生成采购/生产建议
请假审批 → 通过后更新考勤状态
生产完工 → 自动入库(产成品) + 扣减原材料库存
工时记录 → 汇总到任务 → 聚合到项目成本
```

---

## 15. Fungsi Ekspor

### 15.1 Ekspor Excel
- Semua halaman daftar mendukung ?export=excel
- PhpSpreadsheet membuat .xlsx, header teks putih latar biru + baris pertama dibekukan + filter otomatis
- Bidang sensitif otomatis di-masking

### 15.2 Ekspor PDF
- Panel data dasbor mendukung ?export=pdf
- Dirender dengan Dompdf, A4 lanskap
- Informasi hak cipta tidak dapat dihapus

---

## 16. Manajemen Pesanan (OMS)

### 16.1 Manajemen Pesanan
- **Impor pesanan multi-kanal**: mendukung manual/web/mobile/api/marketplace/edi/pos
- **Informasi ekstensi pesanan**: nomor pesanan kanal, toko, status pemenuhan, status pembayaran, prioritas
- **Alokasi stok**: perhitungan ATP (available-to-promise) → reservasi stok (kunci pesimistis mencegah overselling)
- **Orkestrasi pemenuhan**: alokasi → buat pemenuhan → kirim ke WMS → picking/pengepakan → pengiriman TMS
- **Pembatalan pesanan**: otomatis melepaskan reservasi stok

### 16.2 RMA (Retur/Tukar)
- Pembuatan RMA (retur/tukar/perbaikan) → persetujuan → pengembalian → penerimaan masuk gudang (stockIn) → pengembalian dana
- Mendukung pengelolaan ongkos kirim retur, jumlah pengembalian dana

### 16.3 Manajemen Kanal
- Kode kanal/nama/jenis (direct/marketplace/edi/pos)
- Konfigurasi kanal (JSON), status aktif/nonaktif

---

## 17. Manajemen Gudang (WMS)

### 17.1 Zona & Lokasi
- **Zona**: zona penerimaan/zona penyimpanan/zona picking/zona pengepakan/zona pengiriman/zona retur/zona inspeksi kualitas
- **Ekstensi lokasi**: lorong → rak → tingkat → slot, hierarki + barcode/kapasitas/daya dukung/urutan picking

### 17.2 Alur Masuk Gudang
- **ASN (Advance Shipping Notice)**: pemasok → perkiraan kedatangan → kurir → nomor pelacakan
- **Tugas penerimaan**: penerimaan di dermaga → input jumlah aktual diterima → inspeksi kualitas
- **Tugas putaway**: dibuat otomatis → ditugaskan → strategi (fifo/zone_fixed/abc) → konfirmasi putaway (stockIn)

### 17.3 Alur Keluar Gudang
- **Manajemen gelombang**: agregasi multi-pesanan → gelombang picking/gelombang pengiriman → prioritas
- **Tugas picking**: per pesanan/batch/zona/gelombang → ditugaskan → konfirmasi (jumlah aktual dipetik)
- **Tugas pengepakan**: jenis kemasan (box/bag/pallet) → berat/ukuran

---

## 18. Manajemen Transportasi (TMS)

### 18.1 Kurir
- Kode kurir/jenis (ekspres/LTL (muatan parsial)/FTL (muatan penuh)/udara/laut/kereta api)
- Layanan kurir: standard/express/overnight/2day/economy + waktu tempuh
- Konfigurasi API: abstraksi custom/shippo/afterShip/17track

### 18.2 Manajemen Ongkos Kirim
- **Kartu tarif**: asal/tujuan → rentang berat → biaya dasar/biaya per kg/biaya tambahan bahan bakar
- **Multi-mata uang**: CNY/USD/EUR, dll., terkait exchange_rate
- **Perbandingan harga ongkos kirim**: kueri semua tarif tersedia per negara tujuan + berat, diurutkan menaik

### 18.3 Waybill & Lacak
- **Waybill**: layanan kurir → nomor pelacakan → status (menunggu kirim → diambil → dalam perjalanan → terkirim/abnormal/dikembalikan)
- **Lacak logistik**: callback webhook → sinkronisasi otomatis status waybill
- **Faktur ongkos kirim**: buat → konfirmasi → bayar → buat AP (hutang)

---

## Lampiran: Skala Proyek

| Dimensi | Jumlah |
|------|------|
| Modul bisnis | 19 <!-- stats:modules=19 --> |
| Tabel database | 163 <!-- stats:tables=163 --> |
| Model data | 161 <!-- stats:models=161 --> |
| Controller | 123 <!-- stats:controllers=122 --> |
| Layanan bisnis | 29 <!-- stats:services=29 --> |
| Route API | 198 (dibuat dinamis, lihat `scripts/check-endpoints.php`, tidak ikut validasi doc-stats) |
| Middleware | 12 <!-- stats:middleware=12 --> |
| File sumber PHP | 343 <!-- stats:php_files=342 --> |
| Skrip instalasi database | file tunggal `database/install.sql` (163 tabel, semua migrasi telah digabungkan) |
| Halaman frontend (Flutter) | 7 (statistik frontend, tidak masuk validasi doc-stats) |
| Halaman frontend (HarmonyOS) | 4 (statistik frontend, tidak masuk validasi doc-stats) |
| Unit test | 50 file pengujian <!-- stats:test_files=60 --> / 442 kasus pengujian / 2238 asersi (tests/assertions mengambang mengikuti versi patch dan ekstensi PHP, tidak ikut validasi akurat stats) |

> Angka di atas diukur langsung oleh `bash scripts/doc-stats.sh`; item yang ditandai `<!-- stats:key=value -->` diverifikasi otomatis oleh CI
> (job docs di `.github/workflows/ci.yml`) agar konsisten dengan fakta kode, penyimpangan berarti merah.

---

## 19. Matriks Kelengkapan Modul (koreksi 2026-08-16)

### Legenda Status

| Penanda | Makna |
|------|------|
| ✅ | Selesai — siap produksi |
| ⚠️ | Kerangka — CRUD selesai, kurang mesin bisnis/frontend |
| 🔴 | Hilang — belum diimplementasikan |
| 🔵 P0 | Fase ekosistem frontend |
| 🟢 P1 | Fase kedalaman bisnis |
| 🟡 P2 | Fase keandalan operasional |
| 🟣 P3 | Fase peningkatan pengalaman |

### Matriks

| Modul | API Backend | Logika Bisnis | Flutter | HarmonyOS | Tahap Berikutnya |
|------|----------|----------|---------|-----------|----------|
| Manajemen Sistem | ✅ | ✅ | ⚠️ 7/10 | ⚠️ 4/10 | 🔵 P0 |
| Papan Dasbor | ✅ | ✅ | ⚠️ Dasar | ⚠️ Dasar | 🔵 P0 |
| Data Dasar Produk | ✅ | ✅ | ⚠️ 3/7 | ⚠️ 1/7 | 🔵 P0 |
| Manajemen Pembelian | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Manajemen Penjualan | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Manajemen Stok | ✅ | ✅ | ⚠️ Dasar | ⚠️ Dasar | 🔵 P0 |
| Keuangan — Voucher/Piutang-Hutang | ✅ | ⚠️ | ⚠️ 2/10 | 🔴 | 🔵 P0 |
| Keuangan — Buku Besar/Tiga Laporan | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Keuangan — Penutupan Akhir Periode/Konsolidasi | 🔴 | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| CRM Semua Modul | ✅ | ✅ | ⚠️ 1/8 | 🔴 | 🔵 P0 |
| OMS Manajemen Pesanan | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| WMS Manajemen Gudang | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| TMS Manajemen Transportasi | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Alur Persetujuan | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Sistem Notifikasi | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Manajemen Proyek | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| HR — Organisasi/Absensi/Cuti | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| HR — Mesin Gaji | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Manufaktur — BOM/Produksi/MRP | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Manajemen Kualitas | ✅ | ✅ | 🔴 | 🔴 | 🟢 P1 |
| Pelaporan Kustom | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| Papan BI | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Manajemen Peralatan EAM | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Multi-tenant | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟣 P3 |
| Manajemen Dokumen DMS | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Observabilitas | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Migrasi Rollback/Cadangan | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Statistik

| Dimensi | ✅ Selesai | ⚠️ Kerangka | 🔴 Hilang | N/A | Tingkat Penyelesaian |
|------|---------|----------|---------|-----|--------|
| Modul (27) | 14 | 12 | 1 | 0 | 52% |
| API Backend | 19 | 7 | 1 | 0 | 70% |
| Logika Bisnis | 14 | 7 | 6 | 0 | 52% |
| Frontend Flutter | 0 | 8 | 17 | 2 | 0% |
| HarmonyOS | 0 | 6 | 19 | 2 | 0% |

> **Basis statistik (koreksi 2026-08-16)**: baris modul dihitung dengan «API Backend dan Logika Bisnis keduanya terimplementasi»;
> dua baris API Backend / Logika Bisnis dihitung berdasarkan kolom terkait pada matriks (kali ini QMS/EAM/DMS/BI telah dikoreksi menjadi ✅ sesuai kondisi kode saat ini,
> multi-tenant dikoreksi menjadi ⚠️, bukti lihat «Bukti Kode» di bawah); Flutter / HarmonyOS adalah statistik beban kerja halaman frontend
> (2 baris Observabilitas, Migrasi Rollback ditandai N/A), tidak masuk validasi doc-stats backend.

### Bukti Kode (koreksi 2026-08-16)

Dasar koreksi kelengkapan kali ini (keberadaan file dapat dibuktikan oleh `bash scripts/doc-stats.sh` dan `find`):

| Modul | Koreksi | Bukti Kode |
|------|------|----------|
| Manajemen Kualitas | 🔴 → ✅ | `app/controller/quality/` (5 controller) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Papan BI | 🔴 → ✅ | `app/controller/bi/` (3 controller: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Manajemen Peralatan EAM | 🔴 → ✅ | `app/controller/eam/` (4 controller) + `tests/EamModuleTest.php` |
| Manajemen Dokumen DMS | 🔴 → ✅ | `app/controller/dms/` (2 controller) + `tests/DmsModuleTest.php` |
| Multi-tenant | 🔴 → ⚠️ | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` + `tests/Integration/TenantScopeIntegrationTest.php` (kekurangan yang diketahui: ID tenant statis tidak menyebar melalui model, sehingga masih kerangka bukan selesai) |

> Spesifikasi desain roadmap terperinci: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
