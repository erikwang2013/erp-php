# Perencanaan Tim (Tim Kolaborasi AI)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Dokumen ini mendefinisikan tim kolaborasi AI proyek ini: komposisi peran, batas tanggung jawab, mode kolaborasi, dan routing tugas.
> Aturan koordinasi pendukung (SendMessage-First, penamaan agent, siklus hidup) lihat `CLAUDE.md` di root; definisi peran lihat `.claude/agents/`.

---

## 1. Profil Proyek (Dasar Perencanaan)

| Dimensi | Kondisi saat ini | Makna bagi tim |
|------|------|--------------|
| Backend | webman (Workerman) PHP 8.3+, **22 modul bisnis**, 121+ controller, 24 layanan, 161 model, 163 tabel, 12 middleware (schema dengan database/install.sql sebagai satu-satunya sumber kebenaran) | Monolit besar dan lengkap, dibagi kerja per domain bisnis, mencegah ledakan konteks pada satu agent |
| Frontend | Flutter **97 halaman** (Web/mobile) + HarmonyOS **34 halaman**, mencakup semua modul | Pemeliharaan paralel dua platform, perlu peran frontend khusus |
| Baseline kualitas | PHPUnit 137 test / 805 assertion, PHPStan + baseline, CS-Fixer, matriks multi-versi CI | Disiplin sudah ada, peran pengujian/review langsung tertanam ke pipeline |
| Matriks versi | Tiga cabang `lite` / `standard` / `full` (62/72/163 tabel) | Perubahan perlu mempertimbangkan sinkronisasi lintas cabang, perlu koordinasi versi |
| Roadmap | P0~P3 sudah dikirim (skor komprehensif 89/100), memasuki periode iterasi dan evolusi harian | Ukuran tim mengembang sesuai jenis tugas, bukan formasi besar berbasis proyek |
| Fasilitas yang ada | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, batas 15 agents, koordinasi consensus), hooks + memori | Tim langsung dipasang ke konfigurasi yang ada, tidak membangun dari nol |

---

## 2. Komposisi Tim

### 2.1 Tim Inti (menetap, 5 peran)

| Peran | Korespondensi agent yang ada | Tanggung jawab (untuk proyek ini) |
|------|-----------------|--------------------|
| **Project Manager Lead** | `planner` / `swarm/hierarchical-coordinator` | Pemecahan kebutuhan → routing → penerimaan; memelihara antrean tugas 22 modul; memutuskan mode pipeline / fan-out / supervisor; perantara pesan lintas peran |
| **Arsitek Sistem** | `sparc/architecture` | Desain struktur tabel (163 tabel, schema dengan database/install.sql sebagai satu-satunya sumber kebenaran); alur data lintas modul (penerimaan pembelian→stok→hutang, pengiriman penjualan→piutang→keluar gudang, dll.); keputusan batas pemecahan mikroservice |
| **Developer Backend** | `core` / kustom `backend-dev` | Implementasi controller / service / model; mengikuti lapisan `app/service` dan rantai middleware (Locale→Cors→SecurityFilter→RateLimit→TracingId→middleware bisnis) |
| **Insinyur Pengujian** | `testing/tdd-london-swarm` + `production-validator` | Kasus PHPUnit lebih dulu (pengujian batas mesin); verifikasi regresi tiga cabang; melengkapi celah cakupan `tests/` |
| **Reviewer Kode** | `consensus/security-manager` | PHPStan tanpa penambahan baseline, kepatuhan CS-Fixer, pemeriksaan pola keamanan 18 lapis; menjaga gerbang kualitas sebelum commit |

### 2.2 Tim Spesialis (ditarik sesuai jenis tugas, 4 peran)

| Peran | Korespondensi agent yang ada | Skenario pengaktifan | Tugas tipikal |
|------|-----------------|----------|----------|
| **Ahli Mesin Bisnis** | kustom `business-engineer` | Modul algoritmik seperti keuangan / gaji / MRP | Penguatan algoritma dan penanganan batas mesin pembukuan ganda, mesin perhitungan gaji, mesin MRP (persyaratan kelas A "industri") |
| **Insinyur Frontend (Flutter)** | kustom `frontend-flutter` | Perubahan apa pun yang melibatkan `apps/flutter/` | Halaman panel admin Web, status GetX, kaitan ApiService/ekspor, pemeliharaan 97 halaman |
| **Insinyur Frontend (HarmonyOS)** | kustom `frontend-harmonyos` | Perubahan apa pun yang melibatkan `apps/harmonyos/` | Halaman ArkTS, refresh token tanpa rasa sakit, penyelarasan kumpulan fitur dengan Flutter (pemeliharaan 34 halaman) |
| **Insinyur Keamanan/DevOps** | `consensus/security-manager` + `performance-benchmarker` | Penguatan keamanan, performa, deployment | Regresi proteksi 18 lapis, sub-layanan Docker/gRPC, migrasi rollback, observabilitas, metrik Prometheus |

### 2.3 Peran Sesuai Kebutuhan (dipicu tugas, 2 peran)

| Peran | Korespondensi agent yang ada | Kondisi pengaktifan |
|------|-----------------|----------|
| **Peneliti** | kustom `researcher` | Sebelum desain modul/fitur baru: riset pesaing, bandingkan `API.md`, `FUNCTIONS.md` dengan selisih implementasi, keluarkan input desain |
| **Koordinator Edisi** | kustom `edition-coordinator` | Melibatkan selisih `lite/standard/full`: sinkronisasi tiga cabang, validasi matriks `EDITIONS.md`, regresi antar cabang |

---

## 3. Mode Kolaborasi

### 3.1 Prinsip Umum (mengikuti CLAUDE.md di root)

- **SendMessage-First**: agent berkomunikasi langsung melalui SendMessage, tidak polling, tidak berbagi status mutable;
- **Penamaan wajib**: setiap agent harus diberi nama (`name: "role"`);
- **Satu kali spawn**: sub-tugas independen sekali ditarik di background, Lead berhenti menunggu hasil, tidak polling status;
- **Pesan wajib lengkap**: setiap prompt menuliskan "setelah selesai SendMessage ke siapa, kirim apa".

### 3.2 Tiga Topologi Orkestrasi

| Mode | Alur | Skenario penggunaan |
|------|------|----------|
| **Pipeline** | Lead → Arsitek → Backend → Pengujian → Review | Pengembangan fitur dengan dependensi urutan (modul baru, alur data lintas modul) |
| **Fan-out** | Lead → A, B, C → Lead merangkum | Pekerjaan paralel yang saling independen (riset banyak halaman, banyak modul) |
| **Supervisor** | Lead ↔ anggota banyak putaran bolak-balik | Pekerjaan kompleks dengan koordinasi berkelanjutan (pemecahan mikroservice, refaktor besar) |

### 3.3 Tabel Routing Tugas

| Jenis tugas | Orkestrasi | Peran yang terlibat |
|----------|------|----------|
| Modul / fitur baru (mis. pendalaman DMS, BI) | pipeline | Lead → Arsitek (desain tabel) → Backend → Pengujian → Review |
| Algoritma tingkat mesin (pembukuan ganda / gaji / MRP) | pipeline + TDD | Lead → Ahli Mesin Bisnis (desain) → Pengujian (kasus batas lebih dulu) → Review |
| Halaman frontend (Flutter / HarmonyOS paralel) | fan-out | Lead → Frontend×2 + Backend (penyelarasan API) paralel → Lead merangkum |
| Alur data lintas modul (pembelian→stok→hutang, dll.) | pipeline | Lead → Arsitek → Backend → Pengujian → Review |
| Pemecahan mikroservice / refaktor besar | supervisor | Lead ↔ Arsitek + Backend + Review banyak putaran |
| Keamanan / performa khusus | penggalian single-thread | Lead → Insinyur Keamanan/DevOps → Review |
| Perbaikan bug (satu file / 1-2 baris) | tidak masuk tim | Lead langsung menangani, atau 1 agent selesai |
| Selisih tiga cabang / rilis versi | pipeline | Lead → Koordinator Edisi → Pengujian (regresi lintas cabang) → Review |

### 3.4 Gerbang Kualitas (wajib sebelum commit, dijaga reviewer)

```
phpunit            # 137 test / 805 assertion semua hijau, kasus baru ikut dikirim bersama perubahan
phpstan            # tidak boleh ada masalah baru di luar baseline
php-cs-fixer       # --dry-run lolos
composer audit     # tanpa kerentanan dependensi berisiko tinggi
```

Perubahan yang melibatkan database harus melalui arsitek (163 tabel, schema dengan database/install.sql sebagai satu-satunya sumber kebenaran); perubahan yang melibatkan frontend harus menjalankan Flutter `flutter analyze` 0 error / 0 warning.

---

## 4. Saran Ukuran Tim

| Bentuk kerja | Ukuran saran | Keterangan |
|----------|----------|------|
| Pemeliharaan harian / perbaikan kecil | 1-2 orang | Lead langsung menangani, hindari orkestrasi berlebihan |
| Iterasi satu modul | 3 orang | Lead + Backend + Pengujian |
| Fitur lintas modul | 4-5 orang | Lead + Arsitek + Backend + Pengujian + Review |
| Paralel dua platform frontend | 4-5 orang | Lead + Flutter + HarmonyOS + Backend (API) + Pengujian |
| Tingkat mesin / refaktor kompleks | 5-7 orang | Seluruh di atas + Ahli Mesin Bisnis atau Keamanan/DevOps |

> Kompatibel dengan `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, strategi koordinasi `consensus`), penggunaan sekali tugas tidak melebihi batas.

---

## 5. Langkah Implementasi

1. **Melengkapi definisi peran**: `.claude/agents/` sudah memiliki planner / sparc / testing / swarm / consensus, kurang lima definisi `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator`; menambah satu file masing-masing sesuai format YAML/MD yang ada selesai terpasang;
2. **Memantapkan routing**: tulis tabel routing §3.3 ke logika routing `.claude-flow/hooks`, biarkan hook `UserPromptSubmit` otomatis menugaskan tugas ke peran terkait;
3. **Memisahkan memori per domain**: `.claude-flow` sudah membuka `agentScopes` (`defaultScope: project`), disarankan menyimpan per empat domain `backend / frontend / ops / security`, menghindari konteks mesin keuangan mengotori tugas frontend;
4. **Menjalankan pilot**: pilih satu tugas lintas modul (mis. pendalaman DMS atau iterasi papan BI) dijalankan penuh satu putaran sesuai routing §3.3, verifikasi rantai pesan dan gerbang lalu disebarluaskan.

---

## 6. Catatan Perubahan

| Tanggal | Perubahan |
|------|------|
| 2026-08-07 | Versi awal: berdasarkan kondisi 22 modul (P0~P3 sudah dikirim, 89/100) menyusun tim inti 5 + spesialis 4 + sesuai kebutuhan 2 |
