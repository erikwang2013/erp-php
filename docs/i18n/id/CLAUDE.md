# Panel Admin Terbuka (open-admin)

Sistem panel admin full-stack berbasis webman v2 + Flutter.

![Maskot gurita](images/mascot.svg)

## Pernyataan Hak Cipta

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Tidak dapat diubah, tidak dapat dihapus, tidak dapat dibatalkan.** Semua file baru harus menyertakan pernyataan hak cipta di atas sebagai komentar header file.

## Roadmap Ekosistem

> Spesifikasi desain: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> Dokumen arsitektur: `ARCHITECTURE.md` §21
> Matriks fitur: `FUNCTIONS.md` §19

**Skor komprehensif saat ini 89/100** — roadmap lengkap P0~P3 selesai, 22 modul cakupan full-stack, siap produksi.

| Tahap | Durasi | Deliverable | Status |
|------|------|--------|------|
| 🔵 **P0** Ekosistem frontend | 3-4 minggu | 97 halaman Flutter + 34 halaman HarmonyOS + 4 komponen umum | ✅ |
| 🟢 **P1** Kedalaman bisnis | 4-6 minggu | Mesin keuangan + mesin gaji + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Keandalan operasional | 1-2 minggu | Migrasi rollback + backup otomatis + TraceId + queue dual-driver | ✅ |
| 🟣 **P3** Peningkatan pengalaman | 2-3 minggu | Papan BI + EAM + multi-tenant + DMS + 7 tabel baru | ✅ |

**Pengujian**: 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## Daftar Fitur

| Domain | Fitur |
|----|------|
| Autentikasi | Login/registrasi/refresh/logout + captcha + penguncian akun + batas sesi |
| Papan dasbor | Ringkasan operasional/papan penjualan/papan stok/papan keuangan (cache Redis 5m) |
| Pengguna | CRUD + hapus massal/aktif-nonaktif + impor Excel |
| Peran & izin | CRUD + pohon izin + otorisasi RBAC method.path |
| Konfigurasi sistem | CRUD pasangan kunci-nilai |
| Audit operasi | Kueri log + deteksi otomatis 8 platform sumber |
| File | Upload + ekspor Excel/PDF (data sensitif di-masking) |
| Keamanan | Pertahanan berlapis 18 lapis (XSS/SQL injection/CSRF/rate limit/CSP...) |
| Operasional | Health check/metrik Prometheus/dokumen API/security.txt + Docker + CI/CD |
| Manajemen produk | Produk/SKU/kategori/merek/gudang/lokasi/pemasok/pelanggan |
| Manajemen pembelian | Permintaan→pesanan→penerimaan→retur→penyelesaian (masuk gudang otomatis + membuat hutang) |
| Manajemen penjualan | Penawaran→pesanan→pengiriman→retur→penyelesaian (keluar gudang otomatis + membuat piutang) |
| Manajemen stok | Stok real-time/transaksi/batch/transfer/opname/peringatan (biaya rata-rata tertimbang bergerak) |
| Manajemen keuangan | Piutang-hutang/voucher/penerimaan-pembayaran/jurnal/buku besar/buku pembantu/tiga laporan/aset tetap/pajak/multi-mata uang/anggaran |
| CRM | Peluang/tindak lanjut/funnel/kontak/pool bersama/kontrak/penawaran/pemasaran/tiket/analisis |
| Alur persetujuan | Definisi alur kerja/submit/approve/tolak/tarik/persetujuan saya |
| Notifikasi pesan | Daftar notifikasi/baca/semua dibaca/jumlah belum dibaca |
| Manajemen proyek | Proyek/tugas/catatan jam kerja |
| Sumber daya manusia | Departemen/karyawan/posisi/absensi/cuti/gaji |
| Manufaktur produksi | BOM/work order produksi/routing proses/stasiun kerja/MRP |
| Pelaporan kustom | Template laporan/dataset/bidang/filter/eksekusi/penjadwalan |
| OMS Manajemen pesanan | Pesanan multi-kanal/orkestrasi pemenuhan/reservasi stok (ATP)/RMA retur tukar/manajemen kanal |
| WMS Manajemen gudang | Zona lokasi (hierarki+barcode)/masuk gudang (ASN→penerimaan→putaway)/keluar gudang (gelombang→picking→pengepakan) |
| TMS Manajemen transportasi | Kurir/perbandingan ongkos kirim/waybill/lacak logistik (webhook) |
| QMS Manajemen kualitas | Inspeksi IQC/IPQC/OQC + standar inspeksi + penanganan produk tidak sesuai |
| EAM Manajemen peralatan | Buku besar peralatan/rencana perawatan/work order perbaikan/manajemen suku cadang |
| DMS Manajemen dokumen | Kategori dokumen/dokumen/manajemen versi |
| Papan BI | Tata letak papan/komponen grafik |

## Tumpukan Teknologi

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Database: MySQL 8.0+, prefiks tabel `erik_`
- Primary key: BIGINT non-auto-increment, dibuat oleh `erikwang2013/snowflake-php`
- Enkripsi-dekripsi ID lapisan API: `erikwang2013/hashids`
- Autentikasi JWT: `erikwang2013/jwt-webman`
- Enkripsi-dekripsi data sensitif API: `erikwang2013/encryption`
- Enkripsi-dekripsi bidang sensitif database: `erikwang2013/encryptable`
- Sinkronisasi dan kueri ES: `erikwang2013/webman-scout`
- Bendera negara: `erikwang2013/season`
- Pembuatan dokumen API: `hg/apidoc` | berbasis anotasi, akses /apidoc

### Frontend
- Flutter 3.x, direktori sumber `apps/flutter/`
- Sisi Web didesain bergaya panel admin PC (bukan gaya App mobile)
- Mendukung klien dan admin
- HarmonyOS ArkTS, direktori sumber `apps/harmonyos/`

## Struktur Proyek

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## Rantai Eksekusi Middleware

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## Peningkatan Keamanan

- **Pembatasan metode HTTP**: SecurityFilter hanya mengizinkan GET/POST/PUT/DELETE/OPTIONS/HEAD, metode non-standar mengembalikan 405
- **Header CSP**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies diinjeksi ke semua respons
- **Penguncian akun**: 5 kali kegagalan login beruntun, akun terkunci 15 menit
- **Pembatasan sesi konkuren**: maksimal 3 Token valid per pengguna, Token terlama masuk blacklist saat terlampaui
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Konfigurasi keamanan Nginx**: `nginx-security.conf` referensi penguatan keamanan reverse proxy

## Strategi Versi API

Versi dikontrol melalui header permintaan `API-Version` (default `v1`), tidak tercermin di URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Menambah versi baru cukup membuat direktori `app/api/{version}/controller/` dan mendaftarkan ke middleware `ApiVersion`.

## Strategi Rate Limit

Redis sliding window (atomik Lua), default 60 kali/menit/IP/route:
- Login: 10 kali/menit
- Registrasi: 5 kali/menit
- Header respons: `X-RateLimit-Limit/Remaining/Reset`, terlampaui menambah `Retry-After`

## Standar Kode

### PHP
- Referensi fungsi/kelas global tidak menambah `\` di depan, gunakan `use` untuk import
- File konfigurasi harus memuat komentar bahasa Mandarin yang menjelaskan makna setiap item konfigurasi
- Semua file `.php` baru harus memiliki pernyataan hak cipta di header

### Database
- Prefiks tabel: `erik_`
- Primary key `id`: tipe BIGINT, non-auto-increment, dibuat snowflake
- Bidang sensitif menggunakan trait `erikwang2013/encryptable` untuk enkripsi-dekripsi otomatis
- schema mengacu database/install.sql sebagai satu-satunya sumber kebenaran (SQL file tunggal)

### Flutter
- Tata letak Web menggunakan gaya panel admin PC (sidebar + topbar + area konten)
- Menggunakan manajemen status GetX, singleton `ApiService` (Dio + interceptor JWT)
- Persistensi Token menggunakan `shared_preferences`
- Breakpoint responsif: mobile (< 768px) dan desktop (>= 768px)

### HarmonyOS
- Menggunakan klien HTTP native `@ohos.net.http`
- Refresh token tanpa rasa sakit: 401 otomatis memanggil `/api/auth/refresh`
- Gagal refresh otomatis redirect ke halaman login

## Deployment

### Docker Compose (direkomendasikan untuk produksi)

`docker-compose.yml` di root proyek mengorkestrasi 5 layanan:

| Layanan | Keterangan |
|------|------|
| `nginx` | Nginx reverse proxy (80/443), layanan file statis |
| `app` | Aplikasi webman PHP 8.3, dibangun dengan `Dockerfile` (termasuk OPcache + event + redis) |
| `mysql` | MySQL 8.0, persistensi volume data |
| `redis` | Redis 7 Alpine, cache/rate limit/Session |
| `elasticsearch` | Elasticsearch 8.x, pencarian teks lengkap |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` mendefinisikan pipeline GitHub Actions (matriks PHP 8.2/8.3/8.4):

- Pemeriksaan sintaks PHP (`php -l`)
- Analisis statis PHPStan (`vendor/bin/phpstan analyse`)
- Pemeriksaan gaya kode PHP CS Fixer (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- Unit test PHPUnit
- Audit keamanan Composer (`composer audit --no-dev`)

### Backup Database

`database/backup/backup.sh` — mysqldump + gzip, otomatis membersihkan backup lama sebelum 30 hari.
`database/backup/restore.sh` — pemulihan interaktif, menampilkan daftar backup yang tersedia untuk dipilih.

### Monitoring

Endpoint `GET /metrics` (`MetricsController`) mengeluarkan format text Prometheus, berisi 5 metrik gauge:
- `openadmin_http_requests_total` — total permintaan
- `openadmin_active_users` — jumlah pengguna aktif
- `openadmin_db_connection_status` — status koneksi database (0/1)
- `openadmin_redis_connection_status` — status koneksi Redis (0/1)
- `openadmin_memory_usage_bytes` — penggunaan memori
