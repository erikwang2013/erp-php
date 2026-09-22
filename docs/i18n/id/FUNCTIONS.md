# Sistem ERP Terbuka — Manual Fitur

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Ringkasan

Sistem ERP Terbuka (open-erp) mencakup 23 domain bisnis <!-- stats:modules=23 -->, 227 tabel data <!-- stats:tables=227 -->, menyediakan sistem manajemen perusahaan full-stack mulai dari pembelian-penjualan-stok hingga manufaktur produksi, dari pembukuan keuangan hingga sumber daya manusia. Internasionalisasi: dukungan 13 bahasa (中文/English/日本語/한국어/Deutsch/Français/Español/Português/Русский/العربية/हिन्दी/বাংলা/Bahasa Indonesia), peralihan otomatis melalui header permintaan Accept-Language.

> Dokumen API: setelah layanan dimulai, akses `http://localhost:8788/apidoc` untuk melihat dokumen antarmuka interaktif (dihasilkan otomatis oleh erikwang2013/apidoc-php)

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
- Pertahanan berlapis 7 lapis: pembatasan metode HTTP, pencegahan XSS/SQL injection/path traversal/command injection/CSRF
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

### 4.6 Kontrol Kredit Pelanggan (dikirim v1.4.0)
- Manajemen batas kredit: memelihara batas kredit, pemakaian, dan aturan pencegatan per pelanggan
- Pencegatan sebelum pemesanan/pengiriman: CreditControlService assertOrderCreate / assertDeliveryCreate / guard menolak pesanan yang melampaui batas dan mengembalikan alasannya

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

### 5.9 Penelusuran Batch/Nomor Seri & Peringatan Masa Berlaku (dikirim v1.4.0)
- Rantai penelusuran: TraceService forward/backward melacak tujuan/sumber batch ke depan dan ke belakang, buku besar nomor seri `serial`
- Peringatan masa berlaku: expiryAlert mengingatkan batch yang mendekati kedaluwarsa dan yang sudah kedaluwarsa

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

### 6.12 Penutupan Periode / Akuntansi Multi-Organisasi / Konsolidasi Laporan (dikirim v1.4.0)
- Penutupan laba-rugi akhir periode: meringkas mutasi akun laba-rugi per periode (pendapatan - beban = laba bersih, status=calculated)
- Penutupan tidak menghasilkan voucher (belum ada konfigurasi akun laba tahun berjalan dan aturan pencegahan penutupan ganda), tanpa endpoint controller —— tidak termasuk pengiriman v1.4.0, tetap sebagai todo
- Akuntansi multi-organisasi: CompanyController / LedgerPeriodController untuk entitas pembukuan dan periode akuntansi independen (dikirim v1.4.0)
- Mesin laporan konsolidasi (dikirim v1.4.0): ConsolidationService rateToBase/translateLedger konversi kurs akhir periode (tanpa kurs langsung ditolak), addElimination eliminasi antar anak perusahaan (validasi keseimbangan debit-kredit), generateDraft penerbitan laporan (periode yang sudah diterbitkan membaca snapshot, tanpa snapshot dihitung ulang real-time dari voucher yang sudah diaudit), issue pencegahan penerbitan ganda, latest/list; hasil disimpan ke FinanceConsolidationReport
- Pengujian: PeriodCloseServiceTest 4 kasus + ConsolidationServiceTest 3 kasus

### 6.13 Akuntansi Biaya Persediaan dan Produksi (dikirim v1.4.0)
- Pengeluaran material produksi dan pengumpulan biaya: MaterialIssueController mencatat pengeluaran material, CostEntryController + MfgCostService mengumpulkan material/tenaga kerja/biaya overhead per order kerja
- Aturan voucher: MfgCostVoucherRule voucher biaya penyelesaian dan konversi selisih (terkait §12 Order Kerja Produksi)

### 6.14 Nota dan Rekonsiliasi Bank-Perusahaan (dikirim v1.4.0)
- Siklus hidup penuh nota: FinanceBillService store/update/endorse (endosemen)/discount (diskon)/collect (inkaso)/cash (pencairan)/reject + dueWarnings peringatan jatuh tempo
- Rekonsiliasi bank-perusahaan: BankReconService importStatement impor mutasi, autoReconcile/manualReconcile/unreconcile penyelesaian, reconReport laporan rekonsiliasi

### 6.15 Kolam Faktur Masukan dan E-Faktur (dikirim v1.4.0)
- Kolam masukan: TaxInvoicePoolService registerOne/registerBatch/verify/check/deduct/deductStats; verifikasi melalui MockTaxVerifier (kanal otoritas pajak nyata sebagai titik adaptasi)
- E-faktur: EInvoiceService issueInvoice/voidInvoice/issueLogs, melalui EInvoiceAdapter/MockEInvoiceAdapter (kanal otoritas pajak nyata sebagai titik adaptasi)

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

### 7.6 Mesin Nilai Member (dikirim v1.4.0)
- Member dan saldo tersimpan: MemberService openMember/recharge/consume/refund
- Poin dan voucher: earnPoints/consumePoints/expirePoints mutasi poin + issueCoupon/redeemCoupon pemakaian voucher (MemberController / CouponController)

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

### 8.3 Perancang Proses Visual (dikirim v1.4.0)
- Penyusunan kanvas: WorkflowDesignerController membaca/menulis definisi node dan koneksi (persistensi canvas_json)
- Sumber yang sama dengan mesin persetujuan: setelah diterbitkan, alur rantai persetujuan digerakkan oleh definisi kanvas

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

### 10.4 Biaya Proyek dan Deviasi Anggaran (dikirim v1.4.0)
- Pencatatan biaya: ProjectCostService createManual pencatatan manual + generateFromTimesheet konversi berdasarkan jam kerja × tarif karyawan
- Tampilan laba-rugi: projectPnl margin kotor proyek dibandingkan dengan anggaran

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

### 11.4 Rekrutmen dan Kinerja (dikirim v1.4.0)
- Rekrutmen: RecruitService publikasi/penutupan lowongan, mendorong corong kandidat, catatan wawancara, pengiriman/penerimaan/penolakan Offer
- Kinerja: PerformanceService template indikator dan rencana penilaian, penilaian 360 (submitScore multi-penilai), ringkasan

### 11.5 Pelatihan, Jaminan Sosial dan Slip Gaji (dikirim v1.4.0)
- Pelatihan: TrainingService kursus/pendaftaran/penyelesaian/buku besar kredit (employeeCredits)
- Jaminan sosial: SocialSecurityService aturan dasar (createRule/setRate), pengikatan karyawan bind/unbind, simulasi calculate dan detail karyawan employeeSocialDetail
- Slip gaji: PayslipService view hanya-baca menjabarkan item gaji (pendapatan/potongan/gaji bersih, ditambah keterangan jaminan sosial)

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

### 12.6 Laporan Kerja Proses dan Upah Satuan (dikirim v1.4.0)
- Laporan kerja: WorkReportService audit mengaudit laporan kerja proses
- Upah satuan: PieceWageService accumulate buku besar upah satuan / periodSummary ringkasan per periode

### 12.7 Subkontrak dan Penyelesaiannya (dikirim v1.4.0)
- SubcontractService: auditIssue audit pengeluaran material subkontrak → auditReceive penerimaan barang menutup siklus penyelesaian

### 12.8 Beban Kapasitas (dikirim v1.4.0)
- MfgCapacityService: calendar kalender kapasitas stasiun kerja, setException/removeException pengecualian kapasitas, report analisis beban

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
| Modul bisnis | 23 <!-- stats:modules=23 --> |
| Tabel database | 227 <!-- stats:tables=227 --> |
| Model data | 224 <!-- stats:models=224 --> |
| Controller | 159 <!-- stats:controllers=159 --> |
| Layanan bisnis | 64 <!-- stats:services=64 --> |
| Route API | 837 (dibuat dinamis, lihat `scripts/check-endpoints.php`, tidak ikut validasi doc-stats) |
| Middleware | 11 <!-- stats:middleware=11 --> |
| File sumber PHP | 483 <!-- stats:php_files=483 --> |
| Skrip instalasi database | file tunggal `database/install.sql` (227 tabel, semua migrasi telah digabungkan) |
| Halaman frontend (Flutter) | 119 berkas halaman (2026-09-22, penghitungan berkas rekursif; statistik frontend tidak masuk validasi doc-stats) |
| Halaman frontend (HarmonyOS) | 52 berkas halaman (2026-09-22, penghitungan berkas rekursif; statistik frontend tidak masuk validasi doc-stats) |
| Unit test | 113<!-- stats:test_files=113 --> file pengujian  / 1051<!-- stats:tests=1051 --> kasus pengujian  / 5047<!-- stats:assertions=5047 --> asersi  (hitungan statis: jumlah metode pengujian + titik pemanggilan asersi, tidak bergantung pada lingkungan eksekusi) |

> Angka di atas dihasilkan langsung oleh `bash scripts/doc-stats.sh`; item yang ditandai `<!-- stats:key=value -->` diverifikasi otomatis oleh CI
> (job docs di `.github/workflows/ci.yml`) agar konsisten dengan fakta kode, penyimpangan berarti merah.

---

## 19. Matriks Kelengkapan Modul (koreksi 2026-09-05; batch v1.17.0 2026-09-15)

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
| Manajemen Sistem | ✅ | ✅ | ⚠️ 9/14 | ⚠️ 5 halaman | 🔵 P0 |
| Papan Dasbor | ✅ | ✅ | ✅ 2 halaman | ⚠️ 1 halaman | 🔵 P0 |
| Data Dasar Produk | ✅ | ✅ | ✅ 7/7 | ⚠️ 1/7 | 🔵 P0 |
| Manajemen Pembelian | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Manajemen Penjualan | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Manajemen Stok | ✅ | ✅ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Keuangan — Voucher/Piutang-Hutang | ✅ | ⚠️ | ✅ 16 halaman | 🔴 | 🔵 P0 |
| Keuangan — Buku Besar/Tiga Laporan | ⚠️ | 🔴 | ⚠️ 3 halaman (kedalaman aksi menunggu verifikasi) | 🔴 | 🟢 P1 |
| Keuangan — Konsolidasi Laporan | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Keuangan — Akuntansi Multi-Organisasi (F1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Keuangan — Penutupan Periode | 🔴 | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Keuangan — Akuntansi Biaya Persediaan (F3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| CRM Semua Modul | ✅ | ✅ | ✅ 10/10 | 🔴 | 🔵 P0 |
| OMS Manajemen Pesanan | ✅ | ✅ | ✅ 4/4 | ⚠️ 4 halaman | 🔵 P0 |
| WMS Manajemen Gudang | ✅ | ✅ | ⚠️ 7/8 | ⚠️ 7 halaman | 🔵 P0 |
| TMS Manajemen Transportasi | ✅ | ✅ | ⚠️ 5/6 | ⚠️ 5 halaman | 🔵 P0 |
| Alur Persetujuan | ✅ | ✅ | ⚠️ 2/3 | ⚠️ 1 halaman | v1.4.0 |
| Sistem Notifikasi | ✅ | ✅ | ⚠️ 1/2 | 🔴 | v1.4.0 |
| Kontrol Kredit (F7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Rantai Penelusuran/Masa Berlaku Dekat (M6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Beban Kapasitas (M3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Laporan Kerja Proses/Upah Satuan/Penyelesaian Subkontrak (M1+M2) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Perancang Proses (B3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Template Cetak (B1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Rekrutmen/Kinerja/Pelatihan/Jaminan Sosial (H1-H4) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Inspeksi Pemindaian Kode (E1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Biaya Proyek/Anggaran (P1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Nota/Rekonsiliasi Bank-Perusahaan (F6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Kolam Masukan/E-Faktur (F5) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Nilai Member (C1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Multi-Tenant (B5) | ✅ | ⚠️ | 🔴 | 🔴 | v1.4.0 aktif sebagian |
| Notifikasi Kanal/Field Kustom (B4+B7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Manajemen Proyek | ✅ | ✅ | ✅ 3/3 | 🔴 | 🔵 P0 |
| HR — Organisasi/Absensi/Cuti | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 3 halaman | 🔵 P0 |
| HR — Mesin Gaji | ⚠️ | 🔴 | ⚠️ 2 halaman | 🔴 | 🟢 P1 |
| Manufaktur — BOM/Produksi/MRP | ✅ | ✅ | ⚠️ 5/13 | ⚠️ 5 halaman | v1.4.0 |
| Manajemen Kualitas | ✅ | ✅ | ✅ 5/5 | 🔴 | 🟢 P1 |
| Laporan Kustom | ✅ | ⚠️ | ✅ 2/2 | 🔴 | 🔵 P0 |
| Papan BI | ✅ | ✅ | ⚠️ 2/3 | 🔴 | 🟣 P3 |
| Manajemen Peralatan EAM | ✅ | ✅ | ⚠️ 4/5 | 🔴 | v1.4.0 |
| Manajemen Dokumen DMS | ✅ | ✅ | ⚠️ 1/2 | 🔴 | 🟣 P3 |
| Multi-bahasa (i18n) | ✅ | ✅ | ⚠️ hanya 中文/Inggris | ⚠️ hanya 中文/Inggris | v1.17.0 |
| Observabilitas | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Rollback Migrasi/Backup | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Statistik

| Dimensi | ✅ Selesai | ⚠️ Kerangka | 🔴 Hilang | N/A | Tingkat Penyelesaian |
|------|---------|----------|---------|-----|--------|
| Modul (44) | 33 | 11 | 0 | 0 | 75% |
| API Backend | 39 | 4 | 1 | 0 | 89% |
| Logika Bisnis | 33 | 7 | 4 | 0 | 75% |
| Frontend Flutter | 12 | 12 | 18 | 2 | 29% |
| HarmonyOS | 0 | 13 | 29 | 2 | 0% (hitungan ✅; 13 baris sudah punya halaman ⚠️) |

> **Basis statistik (koreksi 2026-09-05)**: baris modul dihitung dengan 「API Backend dan Logika Bisnis keduanya terimplementasi」—— ✅ ganda = selesai,
> yang belum mencapai ✅ ganda dihitung kerangka ⚠️ (termasuk baris 「aktif sebagian」: item tunggakan historis seperti middleware isolasi Multi-Tenant B5 yang belum terdaftar, lihat bukti kode);
> dua baris API Backend / Logika Bisnis dihitung berdasarkan kolom terkait pada matriks, penyebut tingkat penyelesaian dikurangi baris N/A (Observabilitas, Rollback Migrasi tidak punya frontend).
> **Kolom Flutter / HarmonyOS (sejak 2026-08-27 diubah ke cakupan「jangkauan aksi halaman」)**: ✅ = modul memiliki halaman dan jumlah berkas halaman ≥ jumlah controller backend
> (penanda `n/n` atau jumlah halaman); ⚠️ = ada halaman tetapi jumlah berkas halaman < jumlah controller backend (cakupan sebagian); 🔴 = tidak ada halaman; **menunggu verifikasi** = halaman ada tetapi
> kedalaman aksi (siklus tambah-ubah-hapus-cari) belum diverifikasi per halaman. **Jumlah halaman per baris adalah cakupan snapshot 2026-08-27** (`apps/flutter/lib/app/pages/<modul>/`
> dan `apps/harmonyos/entry/src/main/ets/pages/**` jumlah berkas, saat itu Flutter 107 halaman / HarmonyOS 35 halaman);
> pengukuran ulang menyeluruh 2026-09-22 adalah Flutter 119 halaman / HarmonyOS 52 halaman (**jumlah halaman per baris belum dihitung ulang dengan cakupan baru**, penilaian ✅/⚠️ mengikuti snapshot 2026-08-27),
> tidak termasuk validasi doc-stats backend; tingkat penyelesaian HarmonyOS 0% adalah hitungan ✅ (0/42), sebenarnya 13 baris sudah punya halaman (⚠️ cakupan sebagian), bukan seluruh kolom hilang.
> **Deduplikasi 2026-09-15**: matriks semula memuat dua baris 「Multi-Tenant」 (`Multi-Tenant (B5)` dan `Multi-Tenant`, kolom logika bisnis ✅ / ⚠️ saling bertentangan),
> telah digabung sesuai cakupan di atas menjadi satu baris 「Multi-Tenant (B5)」 yang dihitung ⚠️ (middleware isolasi belum terdaftar), baris modul 45 → 44.
> **Batch v1.17.0 (2026-09-15)**: menambah baris 「Multi-bahasa (i18n)」 (1 dari 44 baris ditandai v1.17.0) —— kamus 13 bahasa backend
> (`resource/translations/<locale>/`, 13 direktori) dan negosiasi `Accept-Language` dihitung ✅; sisi Flutter / HarmonyOS masih dua bahasa 中文/Inggris,
> sehingga kedua kolom frontend dihitung ⚠️; sisi Angular / React sudah memiliki kamus 13 bahasa (tidak termasuk kolom matriks ini).
> **Batch v1.4.0 (2026-09-05)**: 21 dari 44 baris ditandai v1.4.0 (termasuk 1 baris 「aktif sebagian」= baris Multi-Tenant (B5)),
> mencakup multi-organisasi/konsolidasi laporan/biaya persediaan (F1-F3), manufaktur M1/M2/M3/M6, kredit F7, nota/rekonsiliasi bank-perusahaan/kolam masukan/e-faktur F6/F5,
> HR H1-H4, member C1, platform B1-B5/B7, inspeksi E1, biaya proyek P1; bukti lihat 「Bukti Kode」 di bawah.

### Bukti Kode (koreksi 2026-09-05; termasuk batch v1.17.0)

Dasar koreksi kelengkapan kali ini (keberadaan file dapat dibuktikan oleh `bash scripts/doc-stats.sh` dan `find`):

| Modul | Koreksi | Bukti Kode |
|------|------|----------|
| Manajemen Kualitas | 🔴 → ✅ | `app/controller/quality/` (5 controller) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Papan BI | 🔴 → ✅ | `app/controller/bi/` (3 controller: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Manajemen Peralatan EAM | 🔴 → ✅ (+E1 inspeksi) | `app/controller/eam/` (5 controller, termasuk `EamInspectionController.php`) + `app/service/eam/EamInspectionService.php` + `tests/EamModuleTest.php` |
| Manajemen Dokumen DMS | 🔴 → ✅ | `app/controller/dms/` (2 controller) + `tests/DmsModuleTest.php` |
| Multi-Tenant | ⚠️ → ⚠️ (v1.4.0 aktif sebagian) | `app/controller/platform/TenantController.php` + `app/service/platform/TenantService.php` (penagihan jatuh tempo provision/suspend/resume/expireMark/renew/expiryWarnings sudah dikirim) + `tests/Integration/TenantScopeIntegrationTest.php`; middleware isolasi `app/middleware/TenantScope.php` masih belum terdaftar (isolasi belum berlaku), sehingga aktif sebagian |
| Konsolidasi Laporan | ⚠️ → ✅ (dikirim v1.4.0) | `app/service/finance/ConsolidationService.php` (rateToBase/translateLedger konversi kurs akhir periode, addElimination eliminasi antar anak perusahaan dengan validasi keseimbangan debit-kredit, generateDraft snapshot diutamakan/perhitungan ulang real-time tanpa snapshot, issue pencegahan ganda, latest/list; tanpa kurs langsung ditolak) + hasil disimpan ke `app/model/FinanceConsolidationReport.php` + `tests/ConsolidationServiceTest.php` (3 kasus) |
| Penutupan Periode | 🔴 → ⚠️ | `app/service/finance/PeriodCloseService.php:21` `closeProfitAndLoss()` (peringkasan akun laba-rugi sudah diimplementasikan, tidak menghasilkan voucher penutupan, tanpa endpoint controller) + `tests/PeriodCloseServiceTest.php` (4 kasus) |
| v1.4.0 — Akuntansi Multi-Organisasi (F1) | Baru | `app/controller/finance/CompanyController.php` + `LedgerPeriodController.php` (entitas pembukuan dan periode akuntansi independen) |
| v1.4.0 — Persediaan dan Biaya Produksi (F3) | Baru | `app/controller/manufacturing/MaterialIssueController.php` + `CostEntryController.php` + `app/service/manufacturing/MfgCostService.php` + `MfgCostVoucherRule.php` |
| v1.4.0 — Eksekusi Manufaktur M1/M2/M3/M6 | Baru | `app/service/manufacturing/` (WorkReportService/PieceWageService/SubcontractService/MfgCapacityService) + `app/service/inventory/TraceService.php` (penelusuran batch/nomor seri dan peringatan masa berlaku) + controller terkait WorkReport/PieceWage/Subcontract/Capacity |
| v1.4.0 — Dana Keuangan/Perpajakan F6/F5 + F7 | Baru | `app/service/finance/FinanceBillService.php` + `BankReconService.php` (impor rekening koran/penyelesaian otomatis dan manual) + `app/service/tax/TaxInvoicePoolService.php` + `EInvoiceService.php` (EInvoiceAdapter/MockEInvoiceAdapter, otoritas pajak nyata sebagai titik adaptasi cadangan) + `app/service/sales/CreditControlService.php` (pencegatan dengan asersi untuk pesanan melampaui batas) |
| v1.4.0 — Member/HR/Proyek C1/H1-H4/P1 | Baru | `app/service/retail/MemberService.php` + `app/controller/retail/` (MemberController/CouponController) + `app/service/hr/` (RecruitService/PerformanceService/TrainingService/SocialSecurityService/PayslipService) + `app/service/project/ProjectCostService.php` |
| v1.4.0 — Platform/Kanal B3/B4/B7/E1 | Baru | `app/controller/workflow/WorkflowDesignerController.php` (persistensi canvas_json) + `app/service/notification/` (ChannelDriver/ChannelService/MockChannelDriver/MailMockChannelDriver, percobaan ulang saat gagal) + `app/controller/notification/NotificationChannelController.php` (`tests/NotificationChannelTest.php` 5 kasus) + `app/controller/platform/CustomFieldController.php` + `app/controller/eam/EamInspectionController.php` (inspeksi pemindaian kode) |
| v1.17.0 — Multi-bahasa (i18n) | Baru | Backend: `resource/translations/` (13 direktori bahasa zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id, setiap bahasa tiga berkas common+modules+validation, 11 bahasa masing-masing 542 entri (zh_CN 533, en 30); en adalah Inggris sebagai key) + `app/common/I18n.php` (`getLocale()` mengurai label pertama `Accept-Language`, memetakan sub-label bahasa utama zh*→zh_CN; `trans()` untuk non-en menempuh `[bahasa permintaan, zh_CN, en]`→key, en tidak jatuh ke 中文) + `config/translation.php` + skrip generator `scripts/gen-be-locales.mjs`; Frontend: `apps/react/src/lib/i18n/` (`index.tsx` + `zhEn` + 11 berkas bahasa) dan `apps/angular/src/app/core/` (`zh-en/` (index + part1..4) + `zh-{ar,bn,de,es,fr,hi,id,ja,ko,pt,ru}.ts`) (kamus 11 bahasa baru dimuat lambat per bahasa, Angular 1453 / React 1447 kunci) + `scripts/gen-fe-locales.mjs`; pintu masuk peralihan = ikon globe bilah atas + dropdown pusat pribadi; Flutter (`apps/flutter/lib/l10n/`) dan HarmonyOS (`entry/src/main/resources/`) masih dua bahasa 中文/Inggris |

> Spesifikasi desain roadmap terperinci: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
