# Sistem ERP Terbuka — Dokumen Desain Fitur

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Ringkasan Sistem

Sistem ERP Terbuka (open-erp) adalah sistem perencanaan sumber daya perusahaan full-stack yang dibangun di atas webman v2 + Flutter, mencakup empat belas domain bisnis besar: manajemen sistem, pembelian-penjualan-stok, keuangan, CRM, alur persetujuan, notifikasi pesan, manajemen proyek, sumber daya manusia, manufaktur produksi, pelaporan kustom.

### 1.1 Tujuan Desain
- Deployment monolit, desain modular
- Semua ID dibuat melalui snowflake + dienkripsi hashids saat transmisi
- Enkripsi ganda data sensitif (lapisan transmisi AES-256-CBC + lapisan penyimpanan AES-128-ECB)
- Perhitungan biaya rata-rata tertimbang bergerak
- Linkage otomatis lintas modul (pembelian→hutang, penjualan→piutang, penerimaan-pembayaran→penutupan)

### 1.2 Batasan Teknis
- PHP 8.3+, MySQL 8.0+, Redis 7, Elasticsearch 8
- Prefiks tabel erik_, primary key BIGINT non-auto-increment
- Versi API dikontrol melalui header permintaan API-Version
- Autentikasi JWT + izin RBAC
- Fungsi global tanpa prefix \

---

## 2. Modul Manajemen Sistem

### 2.1 Manajemen Pengguna
- CRUD admin, mendukung aktif/nonaktif massal, soft delete massal
- Impor massal Excel (validasi per baris + laporan kesalahan)
- Hash bcrypt kata sandi, mengubah kata sandi perlu konfirmasi kata sandi lama
- Operasi hapus perlu konfirmasi ulang kata sandi pengguna saat ini
- Nomor ponsel/email/nomor KTP disimpan terenkripsi, daftar otomatis di-masking

### 2.2 Peran & Izin (RBAC)
- CRUD peran, slug identifikasi unik
- Pohon izin (parent_id self-reference tanpa batas), jenis: menu/tombol/API
- Format penanda izin: {method}.{path} (mis. get.admin/product, post.admin/user/batch/destroy)
- Relasi banyak-ke-banyak peran-izin
- Super admin (super_admin) melewati semua pemeriksaan izin
- Middleware AdminPermission cache izin di Redis (TTL=60s)

### 2.3 Konfigurasi Sistem
- Penyimpanan pasangan kunci-nilai, mendukung grup
- Tipe nilai: string|int|bool|json|array

### 2.4 Audit Operasi
- Mencatat otomatis semua operasi POST/PUT/DELETE
- Mencatat: operator, aksi, metode, path, IP, parameter (bidang sensitif di-masking), waktu
- Deteksi otomatis 8 platform sumber (Web/Flutter/HarmonyOS/API, dll.)
- Hanya mendukung kueri, tidak dapat dihapus/diubah

### 2.5 Perlindungan Keamanan
- Pertahanan berlapis 18 lapis (detail lihat SECURITY.md)
- SecurityFilter: pembatasan metode HTTP + pencegahan XSS/SQL injection/path traversal/command injection/CSRF
- RateLimit: rate limit Redis sliding window (atomik Lua, 60 kali/menit)
- Captcha klik (wajib saat login/registrasi)
- Penguncian akun: 5 kali gagal terkunci 15 menit
- Batas sesi konkuren: maksimal 3 Token per pengguna
- Header CSP, security.txt (RFC 9116)
- poster-php verifikasi ulang acak operasi sensitif

---

## 3. Produk & Data Dasar

### 3.1 Manajemen Produk
- Arsip produk: kode (unik), nama, barcode, spesifikasi, unit dasar, gambar, deskripsi
- SKU multi-spesifikasi: beberapa SKU di bawah produk yang sama, masing-masing kode, barcode, atribut spesifikasi (JSON) independen
- Konversi multi-satuan: unit dasar ↔ unit pembantu, rasio konversi
- Strategi harga: harga beli, harga grosir, harga ecer, harga tingkat pelanggan
- Kategori produk: struktur pohon tanpa batas, mendukung pengurutan seret
- Manajemen merek: nama merek, Logo, deskripsi

### 3.2 Gudang & Lokasi
- Manajemen multi-gudang (nama, kode, alamat, penanggung jawab)
- Setiap gudang memiliki banyak lokasi (kode unik dalam gudang)
- Telepon gudang disimpan terenkripsi

### 3.3 Pemasok/Pelanggan
- Arsip pemasok: kode, nama, kontak, telepon/email (terenkripsi), alamat, informasi bank
- Arsip pelanggan: kode, nama, tingkat pelanggan, batas kredit
- Tingkat pelanggan: nama, rasio diskon default
- Pemasok/pelanggan mendukung pencarian teks lengkap ES

---

## 4. Modul Pembelian

### 4.1 Alur Pembelian
Permintaan → persetujuan → pesanan → penerimaan → penyelesaian

### 4.2 Permintaan Pembelian
- Departemen/personel mengajukan kebutuhan pembelian
- Status: menunggu persetujuan → disetujui/ditolak → dikonversi ke pesanan
- Mendukung operasi penyetuju

### 4.3 Pesanan Pembelian
- Terkait pemasok, rincian produk (jumlah, harga satuan, jumlah nilai)
- Status: menunggu review → direview → diterima sebagian → diterima → dibatalkan
- Dapat dibuat berdasarkan permintaan pembelian, atau langsung

### 4.4 Penerimaan Pembelian (linkage lintas modul)
- Penerimaan berdasarkan pesanan, mendukung penerimaan bertahap
- Saat penerimaan memicu otomatis:
  1. InventoryService.stockIn() → memperbarui stok real-time + menghitung ulang rata-rata tertimbang bergerak
  2. FinanceService.createAp() → membuat catatan hutang
  3. Memperbarui jumlah diterima dan status pesanan
- Mendukung pencatatan lokasi, nomor batch

### 4.5 Retur Pembelian
- Dikembalikan ke pemasok, membuat penyesuaian keluar gudang
- Terkait dokumen penerimaan

### 4.6 Penyelesaian Pemasok
- Rekap per pemasok: jumlah pembelian, sudah dibayar, hutang
- Status penyelesaian: belum diselesaikan/sebagian diselesaikan/diselesaikan

---

## 5. Modul Penjualan

### 5.1 Alur Penjualan
Penawaran → pesanan → pengiriman → penyelesaian

### 5.2 Surat Penawaran
- Menawarkan ke pelanggan, mendukung konversi ke pesanan penjualan
- Status: draf → sudah ditawarkan → dikonversi ke pesanan → kedaluwarsa

### 5.3 Pesanan Penjualan
- Terkait pelanggan, rincian produk (jumlah, harga satuan, diskon)
- Status: menunggu review → direview → dikirim sebagian → dikirim → dibatalkan
- Mendukung jumlah diskon

### 5.4 Pengiriman Penjualan (linkage lintas modul)
- Pengiriman berdasarkan pesanan, mendukung pengiriman bertahap
- Saat pengiriman memicu otomatis:
  1. InventoryService.stockOut() → mengurangi stok (menggunakan biaya rata-rata tertimbang bergerak)
  2. FinanceService.createAr() → membuat catatan piutang
  3. Memperbarui jumlah terkirim dan status pesanan

### 5.5 Retur Penjualan
- Retur pelanggan, membuat penyesuaian masuk gudang

### 5.6 Penyelesaian Pelanggan & Margin Kotor
- Rekap per pelanggan: jumlah penjualan, sudah diterima, piutang
- Margin kotor penjualan: dihitung per dimensi pesanan/produk/pelanggan

---

## 6. Modul Stok

### 6.1 Manajemen Stok
- Stok real-time: presisi empat dimensi gudang+lokasi+batch+SKU
- Transaksi masuk/keluar: semua perubahan stok dicatat seragam (arah, jumlah, harga pokok, nomor dokumen sumber)
- Pelacakan batch: tanggal produksi, tanggal kedaluwarsa
- Pelacakan nomor seri: nomor seri unik, status dicatat saat masuk/keluar (di gudang/sudah keluar)

### 6.2 Perhitungan Biaya
- Metode rata-rata tertimbang bergerak
- Rumus: harga rata-rata baru = (total nilai stok lama + total nilai masuk kali ini) / (jumlah stok lama + jumlah masuk kali ini)
- Dihitung ulang otomatis setiap masuk gudang, keluar gudang menghitung biaya dengan harga rata-rata saat ini
- Rantai catatan biaya lengkap (harga rata-rata sebelum perubahan → harga rata-rata setelah perubahan)

### 6.3 Transfer Stok
- Transfer antar gudang/antar lokasi
- Status: menunggu transfer → sudah ditransfer keluar → sudah ditransfer masuk → selesai
- Otomatis membuat transaksi transfer keluar/masuk

### 6.4 Manajemen Stock Opname
- Opname terencana (per gudang/kategori) + opname dinamis (per SKU)
- Mencatat jumlah buku vs jumlah aktual
- Selisih opname otomatis membuat transaksi surplus/defisit

### 6.5 Peringatan Stok
- Mengatur batas atas/bawah per SKU+gudang
- Di bawah batas bawah/di atas batas atas otomatis mencatat log peringatan

---

## 7. Modul Keuangan

### 7.1 Akun Akuntansi
- Pohon akun: lima kategori besar aset/kewajiban/ekuitas/pendapatan/biaya
- Kode akun unik
- Arah saldo: debit/kredit

### 7.2 Voucher Pembukuan
- Nomor voucher, tanggal, ringkasan
- Pembukuan ganda: setiap entri berisi jumlah debit dan jumlah kredit (debit dan kredit harus sama)
- Status: draf → direview

### 7.3 Buku Besar
- Rekap per akun akuntansi + periode akuntansi (tahun/bulan)
- Mencatat: saldo awal debit/kredit, jumlah transaksi periode debit/kredit, saldo akhir debit/kredit
- Saldo akhir = saldo awal ± jumlah transaksi periode (sesuai arah saldo akun)
- Setelah voucher direview otomatis diperbarui
- Mendukung filter per tahun/bulan/akun

### 7.4 Buku Pembantu
- Setiap entri voucher akun tertentu dicatat satu per satu
- Berisi: nomor voucher, arah (debit/kredit), jumlah, saldo, ringkasan, tanggal
- Mendukung kueri per akun + rentang tanggal
- Sinkron diperbarui dengan entri voucher

### 7.5 Neraca
- Dibuat per periode akuntansi (bulanan/tahunan)
- Otomatis merangkum saldo buku besar:
  - Akun aset (1) → total aset = aset lancar + aset tidak lancar
  - Akun kewajiban (2) → total kewajiban = kewajiban lancar + kewajiban tidak lancar
  - Akun ekuitas (3) → ekuitas pemilik
  - Identitas: aset = kewajiban + ekuitas pemilik
- Mendukung penyimpanan snapshot (data JSON lengkap)
- Tanpa snapshot otomatis dibuat dari buku besar

### 7.6 Laporan Arus Kas
- Dibuat per periode akuntansi (bulanan/tahunan)
- Tiga klasifikasi:
  - Arus kas aktivitas operasi (penerimaan penjualan - pembayaran pembelian - pengeluaran biaya)
  - Arus kas aktivitas investasi
  - Arus kas aktivitas pendanaan
- Saldo kas awal/akhir = total saldo awal/akhir semua rekening bank
- Otomatis merangkum jurnal kas bank untuk dibuat
- Mendukung penyimpanan snapshot (data JSON lengkap)

### 7.7 Piutang-Hutang
- Dibuat otomatis dari penerimaan pembelian/pengiriman penjualan
- Piutang: jenis=piutang, terkait pelanggan, sumber=dokumen pengiriman penjualan
- Hutang: jenis=hutang, terkait pemasok, sumber=dokumen penerimaan pembelian
- Status: belum ditutup → ditutup sebagian → ditutup
- Dokumen sumber yang sama tidak dapat dibuat ulang (perlindungan idempoten)

### 7.8 Manajemen Penerimaan
- Multi-akun (kas/bank/WeChat/Alipay)
- Setelah direview otomatis memperbarui saldo rekening bank dan jurnal kas
- Penutupan: pilih catatan piutang, input jumlah penutupan (tidak melebihi saldo belum ditutup)
- Status penutupan sebagian mengalir otomatis

### 7.9 Manajemen Pembayaran
- Logika sama dengan penerimaan, arah berlawanan
- Menutup catatan hutang

### 7.10 Jurnal Kas & Bank
- Mencatat setiap transaksi masuk/keluar per akun + tanggal
- Mencatat saldo setelah perubahan
- Saldo rekening bank diperbarui real-time

### 7.11 Reimbursement Biaya
- Alur: submit → persetujuan → transfer
- Terkait akun biaya
- Setelah transfer otomatis membuat dokumen pembayaran + jurnal

### 7.12 Laporan Laba Rugi
- Rekap bulanan: pendapatan operasional, biaya operasional, biaya, laba
- Penyimpanan snapshot data (unik per tahun+bulan)

### 7.13 Depresiasi Aset Tetap
- Manajemen siklus hidup aset penuh: perolehan → penggunaan → depresiasi → disposisi
- Metode depresiasi: garis lurus ((nilai perolehan - nilai residu) / jumlah bulan penggunaan)
- Pencadangan depresiasi bulanan, otomatis membuat catatan depresiasi
- Mencatat: nilai perolehan, nilai residu, masa manfaat, jumlah depresiasi bulanan, depresiasi kumulatif, nilai bersih

### 7.14 Manajemen Pajak
- Mendukung multi jenis pajak: PPN/pajak penghasilan badan/pajak penghasilan pribadi/bea meterai
- Tarif pajak dapat dikonfigurasi fleksibel
- Terkait dengan dokumen pembelian/penjualan, otomatis mencatat jumlah pajak

### 7.15 Multi-Mata Uang
- Manajemen mata uang: CNY/USD/EUR/JPY, dll.
- Penanda mata uang basis (fungsional)
- Kurs dikelola berdasarkan tanggal efektif
- Mendukung konversi mata uang asing

### 7.16 Manajemen Anggaran
- Penyusunan anggaran tahunan: per pusat biaya+akun+bulan
- Analisis perbandingan anggaran vs aktual
- Perhitungan tingkat eksekusi + analisis selisih
- Status: draf → disetujui → berjalan → ditutup

### 7.17 Pusat Biaya/Pusat Laba
- Struktur hierarki pohon
- Pengumpulan biaya + alokasi biaya
- Pusat laba dibukukan secara independen

---

## 8. Modul CRM

### 8.1 Manajemen Pelanggan
- Arsip pelanggan terkait pelanggan data dasar
- Banyak kontak di bawah pelanggan (penanda kontak utama)
- Telepon/email kontak disimpan terenkripsi

### 8.2 Catatan Tindak Lanjut
- Cara tindak lanjut: telepon/kunjungan/email/pesan/lainnya
- Mencatat isi tindak lanjut, rencana tindak lanjut berikutnya, waktu tindak lanjut berikutnya
- Terkait pelanggan, kontak, peluang

### 8.3 Kampanye Pemasaran
- Siklus hidup kampanye penuh: direncanakan → berjalan → selesai → dibatalkan
- Multi-kanal: email/SMS/telepon/event/media sosial
- Pelacakan pelanggan peserta, statistik tingkat konversi
- Perbandingan anggaran vs biaya aktual

### 8.4 Tiket Layanan
- Manajemen tiket: menunggu diproses → diproses → diselesaikan → ditutup
- Prioritas: rendah/sedang/tinggi/darurat
- Kategori: dukungan teknis/keluhan/konsultasi/retur tukar/lainnya
- Alokasi penangan + balasan (publik/catatan internal)
- Statistik tingkat penyelesaian

### 8.5 Laporan Analisis Pelanggan
- 6 indikator inti: pelanggan baru/pelanggan aktif/tingkat retensi/nilai transaksi rata-rata/CLV/tingkat penyelesaian tiket
- Laporan dibuat otomatis (snapshot data JSON)
- Mendukung bulanan/kuartalan/tahunan

### 8.6 Funnel Penjualan
- Konfigurasi tahap: kontak awal (10%) → konfirmasi kebutuhan (30%) → skema penawaran (50%) → negosiasi bisnis (70%) → deal (100%) → kalah (0%)
- Peluang: pelanggan, tahap saat ini, perkiraan jumlah, probabilitas deal, perkiraan tanggal deal, penanggung jawab
- Status peluang: kalah/berjalan/sudah deal
- Pelacakan perpindahan tahap

### 8.7 Pool Bersama Pelanggan
- Pool bersama pelanggan: pelanggan tanpa kepemilikan atau terlambat ditindaklanjuti otomatis masuk pool bersama
- Aturan penarikan kembali: atur hari penarikan otomatis tanpa tindak lanjut per tingkat pelanggan
- Batas jumlah ambil maksimal per orang, mencegah pengendapan sumber daya pelanggan
- Operasi ambil/lepas/penarikan kembali semua memiliki catatan transaksi
- Mendorong aktivitas tim penjualan, menghindari pengendapan pelanggan

### 8.8 Manajemen Penawaran CRM
- Alur penawaran internal CRM yang independen dari modul penjualan
- Status: draf → sudah dikirim → konfirmasi pelanggan → dikonversi ke kontrak → kedaluwarsa
- Mendukung masa berlaku penawaran
- Mendukung konversi langsung ke kontrak (`to-contract`)
- Terkait pelanggan dan peluang

### 8.9 Manajemen Kontrak
- Siklus hidup kontrak penuh: draf → menunggu persetujuan → disetujui → berjalan → selesai/dihentikan
- Terkait pelanggan, peluang, penawaran
- Rincian kontrak: produk/jumlah/harga satuan/jumlah nilai
- Mencatat tanggal penandatanganan, tanggal mulai/selesai
- Isi klausul kontrak (bidang besar TEXT)
- Alokasi penanggung jawab

---

## 9. Modul Alur Persetujuan

### 9.1 Definisi Alur Kerja
- Nama alur kerja, deskripsi, modul yang berlaku
- Konfigurasi rantai persetujuan multi-node
- Setiap node menentukan penyetuju/peran persetujuan, strategi persetujuan (paraf bersama/paraf atau)

### 9.2 Alur Persetujuan
- Dokumen bisnis mengajukan persetujuan → otomatis membuat instance persetujuan
- Mengalir sesuai node yang telah ditentukan, penyetuju setiap node menangani berurutan
- Operasi persetujuan: submit (dimulai dari modul bisnis), approve, tolak, tarik
- Hasil persetujuan memanggil balik modul bisnis untuk memperbarui status dokumen
- Daftar persetujuan saya: menunggu persetujuan/sudah diproses

### 9.3 Catatan Persetujuan
- Pelacakan rantai persetujuan lengkap: setiap langkah mencatat penyetuju, operasi, pendapat, waktu
- Instance persetujuan terkait nomor dokumen bisnis

---

## 10. Modul Notifikasi Pesan

### 10.1 Manajemen Notifikasi
- Daftar notifikasi: urutan waktu menurun, tampilan paginasi
- Jenis notifikasi: notifikasi persetujuan, pengumuman sistem, peringatan bisnis
- Tandai dibaca: tandai satu per satu / semua dibaca
- Jumlah belum dibaca: jumlah pesan belum dibaca real-time

### 10.2 Template Notifikasi
- Template notifikasi yang telah ditentukan (judul + placeholder konten)
- Kategori template: persetujuan/peringatan/sistem
- Pengaturan notifikasi: konfigurasi preferensi kanal notifikasi per pengguna

### 10.3 Layanan Notifikasi
- NotificationService antarmuka pengiriman seragam
- Mendukung ekstensi multi-kanal (pesan internal/email/SMS/WebSocket)

---

## 11. Modul Manajemen Proyek

### 11.1 Manajemen Proyek
- CRUD proyek: nama, deskripsi, status, tanggal mulai-selesai, penanggung jawab
- Status proyek: dalam perencanaan → berjalan → selesai → diarsipkan
- Manajemen anggota proyek: menambah/menghapus anggota proyek

### 11.2 Manajemen Tugas
- CRUD tugas: judul, deskripsi, prioritas, status, tanggal tenggat
- Terkait proyek, mendukung tugas induk-anak
- Status tugas: belum mulai → berjalan → selesai → ditutup
- Alokasi tugas: menentukan penanggung jawab

### 11.3 Catatan Jam Kerja
- Input jam kerja per tugas: tanggal, durasi, deskripsi
- Statistik jam kerja rekap per proyek

---

## 12. Modul Sumber Daya Manusia

### 12.1 Struktur Organisasi
- Manajemen departemen: struktur pohon, nama departemen, kode, penanggung jawab, departemen induk
- Manajemen posisi: nama posisi, kode, departemen milik, status

### 12.2 Manajemen Karyawan
- Arsip karyawan: kode, nama, jenis kelamin, nomor ponsel (terenkripsi), email (terenkripsi), tanggal masuk, departemen, posisi
- Status: aktif/keluar
- Terkait akun pengguna sistem

### 12.3 Manajemen Absensi
- Check-in: check-in kerja, check-out kerja, mencatat waktu
- Kueri absensi: per karyawan + rentang tanggal
- Aturan absensi: jam kerja, ambang terlambat/pulang awal

### 12.4 Manajemen Cuti
- CRUD cuti: jenis (cuti alasan pribadi/cuti sakit/cuti tahunan, dll.), waktu mulai-selesai, alasan
- Alur persetujuan: submit → persetujuan kepala departemen → disetujui/ditolak
- Status: menunggu persetujuan → disetujui → ditolak

### 12.5 Manajemen Gaji
- Item gaji: gaji pokok/kinerja/tunjangan/item potongan, dll., cara perhitungan
- Pembayaran gaji: membuat slip gaji bulanan, terkait karyawan
- Status pembayaran: menunggu dibayar → sudah dibayar

---

## 13. Modul Manufaktur Produksi

### 13.1 BOM (Bill of Materials)
- Definisi BOM: produk induk, material anak, jumlah standar, unit, proses
- Level BOM: mendukung ekspansi BOM multi-level
- Manajemen versi: catatan revisi BOM

### 13.2 Work Order Produksi
- CRUD work order produksi: produk, jumlah rencana, tanggal mulai/selesai rencana
- Status: menunggu produksi → dalam produksi → selesai → ditutup
- Operasi mulai/selesai: mencatat waktu mulai/selesai aktual
- Rincian produksi: daftar ambil material (berdasarkan ekspansi BOM)

### 13.3 Routing Proses
- Definisi routing proses: produk, urutan proses, jam standar setiap proses
- Terkait BOM dan stasiun kerja

### 13.4 Stasiun Kerja
- CRUD stasiun kerja: nama, kode, jenis, kapasitas, status
- Terkait proses routing proses

### 13.5 MRP (Material Requirements Planning)
- Rencana MRP: menghitung kebutuhan material berdasarkan pesanan penjualan/rencana produksi + BOM
- Otomatis membuat saran pembelian (saat bahan baku kurang) dan saran produksi (saat setengah jadi kurang)
- Rincian MRP: material, kebutuhan bruto, stok tersedia, kebutuhan bersih, jumlah pesanan saran
- Status rencana: draf → sudah dibuat → sudah diterbitkan saran pembelian/produksi

---

## 14. Modul Pelaporan Kustom

### 14.1 Definisi Laporan
- CRUD template laporan: nama, deskripsi, dataset, bidang, kondisi filter, jenis grafik
- Dataset: kueri SQL yang telah ditentukan atau metode model
- Bidang laporan: nama kolom, nama tampilan, tipe data, urutan
- Filter: bidang, operator, nilai default

### 14.2 Eksekusi Laporan
- Menjalankan laporan menghasilkan data: menerapkan kondisi filter, urutan, paginasi
- Tampilan hasil: tabel atau grafik (dirender melalui frontend)
- Mendukung ekspor

### 14.3 Penjadwalan Terjadwal
- Tugas terjadwal laporan: menentukan laporan, frekuensi eksekusi (cron), penerima
- Status penjadwalan: aktif/nonaktif
- Kueri riwayat eksekusi

---

## 15. Papan Dasbor

### 15.1 Ringkasan Operasional
- Penjualan/pembelian hari ini/bulan ini
- Total piutang/hutang, total nilai stok, margin kotor
- Data cache Redis 5 menit

### 15.2 Papan Penjualan
- Tren penjualan, peringkat pelanggan Top10
- Analisis konversi funnel CRM

### 15.3 Papan Stok
- Total nilai stok, statistik peringatan
- Tren masuk/keluar (per hari/arah)

### 15.4 Papan Keuangan
- Total piutang/hutang, penerimaan/pembayaran bulan ini
- Ringkasan saldo kas dan bank

---

## 16. Internasionalisasi (i18n)

### 16.1 Deteksi Bahasa Otomatis
- Header permintaan `Accept-Language` dikenali otomatis (zh-CN → Mandarin, en → English)
- Middleware Locale dieksekusi di posisi pertama rantai middleware global
- Rantai fallback: bahasa saat ini → fallback_locale yang dikonfigurasi → mengembalikan kunci asli

### 16.2 File Terjemahan
- Direktori: `resource/translations/{locale}/`
- Pesan umum: `common.php` (41 kunci: sukses/gagal/buat/ubah/hapus/validasi, dll.)
- Nama modul: `modules.php` (69 kunci: produk/pembelian/penjualan/stok/keuangan/CRM, dll.)
- Aturan validasi: `validation.php` (11 aturan + 10 label bidang)

### 16.3 Cara Penggunaan
- Di dalam controller: `$this->trans('created')`
- Fungsi global: `__('modules.product')`, `__m('finance')`
- Nama modul: `__('modules.product')` → 商品 / Product

---

## 17. Fungsi Ekspor

### 17.1 Ekspor Excel
- Semua halaman daftar mendukung ?export=excel
- PhpSpreadsheet membuat .xlsx
- Header teks putih latar biru + baris pertama dibekukan + lebar kolom otomatis
- Bidang sensitif otomatis di-masking

### 17.2 Ekspor PDF
- Panel data dasbor mendukung ?export=pdf
- Dirender dengan Dompdf, A4 lanskap
- Informasi hak cipta tidak dapat dihapus
