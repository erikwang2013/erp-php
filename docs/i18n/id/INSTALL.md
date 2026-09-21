# Sistem ERP Terbuka — Wizard Instalasi

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Persyaratan Lingkungan

| Komponen | Versi Minimum | Keterangan |
|------|---------|------|
| PHP | 8.3+ | Ekstensi yang diperlukan: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Charset utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Untuk cache, rate limit, Session |
| Composer | 2.x | Manajemen dependensi PHP |
| Elasticsearch | 8.x | Opsional, pencarian teks lengkap |

### Pemeriksaan Ekstensi PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

Jika ekstensi kurang (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Langkah Instalasi

### 1. Buat Database

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'kata_sandi_anda';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Impor Database (selesai dengan satu perintah)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` berisi struktur seluruh 227 tabel dan data seed awal (peran super admin, pohon izin, tahap corong, tarif pajak, mata uang, metrik analisis, kategori dokumen, izin antarmuka layanan); schema mengacu pada database/install.sql sebagai satu-satunya sumber kebenaran.

### 3. Konfigurasi Variabel Lingkungan

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

Edit `.env`, ubah konfigurasi kunci berikut:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=kata_sandi_anda
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=ubah menjadi string acak minimal 32 karakter
APP_KEY=ubah menjadi string acak 32 karakter

# Sakelar pendaftaran terbuka (default 0=nonaktif, antarmuka mengembalikan 403; produksi disarankan tetap nonaktif)
REGISTRATION_ENABLED=0
```

### 4. Instal Dependensi PHP

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Mulai Layanan

```bash
php start.php start
```

Secara default mendengarkan di `http://0.0.0.0:8788`.

### 6. Verifikasi Instalasi

```bash
curl http://localhost:8788/health
```

Akses `http://localhost:8788/apidoc` di browser untuk melihat dokumen API.

---

## Upgrade Lingkungan yang Sudah Diterapkan

Repositori ini tidak memiliki alat migrasi: schema dan seed berpedoman pada `database/install.sql` sebagai satu-satunya sumber kebenaran, sedangkan berkas tersebut adalah **skrip instalasi seluruh basis data sekali jalan
(`INSERT` biasa, bukan idempoten) —— tidak boleh dijalankan ulang pada basis data produksi**. Upgrade dijalankan manual berdasarkan selisih:

```bash
git diff <versi-lama>..<versi-baru> -- database/install.sql    # mengambil selisih struktur tabel dan seed
```

1. `CREATE TABLE` (dengan `IF NOT EXISTS`, dapat dijalankan apa adanya) / `ALTER TABLE` / `INSERT` seed pada selisih dijalankan berurutan di basis data produksi.
2. **Seed izin**: baris `erp_admin_permission` yang bersesuaian dengan endpoint baru perlu ditambahkan. Pengecualian super admin —— ia memegang izin wildcard
   (baris "semua izin" dengan `slug = '*'` di `erp_admin_permission`, `app/middleware/AdminPermission.php:38`
   melihat `*` langsung meloloskan semuanya), sehingga endpoint baru otomatis berlaku; peran kustom perlu menambahkan relasi lagi:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ID peran>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug izin endpoint baru>';
   ```

3. Setelah upgrade jalankan sekali `curl http://localhost:8788/health` dan smoke login panel admin untuk memastikan layanan tersedia.

---

## Akun Awal

Setelah instalasi, peran super admin (`super_admin`) sudah tersedia, memiliki semua izin. Untuk penggunaan pertama, perlu membuat akun admin secara manual:

```sql
-- Buat admin (kata sandi menggunakan hash bcrypt)
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', 'System Administrator', 1);

-- Tautkan peran super admin
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` dibuat oleh `snowflake-php` di lapisan aplikasi, juga dapat diperoleh melalui antarmuka registrasi.

---

## Deployment Docker Compose

Direktori root proyek mengorkestrasi 5 layanan: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Ganti kunci placeholder dengan nilai acak (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# Masuk ke container untuk impor database
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Konvensi Database

| Konvensi | Keterangan |
|------|------|
| Prefiks tabel | `erp_` |
| Primary key | `id` BIGINT UNSIGNED NOT NULL, non-auto-increment, dibuat oleh snowflake-php |
| Charset | utf8mb4, utf8mb4_unicode_ci |
| Mesin | InnoDB |
| Soft delete | `deleted_at` DATETIME DEFAULT NULL |
| Timestamp | `created_at` / `updated_at` dipelihara otomatis |
| Field sensitif | menggunakan trait encryptable untuk enkripsi/dekripsi otomatis |

---

## Daftar Tabel (227 tabel)

| Modul | Jumlah Tabel | Nama Tabel |
|------|------|------|
| Manajemen Sistem | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Manajemen Produk | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Manajemen Pembelian | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Manajemen Penjualan | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Manajemen Stok | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Manajemen Keuangan | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Alur Persetujuan | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Notifikasi Pesan | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Manajemen Proyek | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| Sumber Daya Manusia | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Manufaktur Produksi | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Laporan Kustom | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM Manajemen Peralatan | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS Manajemen Dokumen | 3 | dms_category, dms_document, dms_document_version |
| Papan BI | 2 | bi_dashboard, bi_widget |
| OMS Manajemen Pesanan | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS Manajemen Gudang | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS Manajemen Transportasi | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS Manajemen Kualitas | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Pusat Member | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Platform Terbuka | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Template Cetak | 1 | print_template |
| Perpajakan | 2 | tax_input_invoice, tax_issue_log |
| Dasar Platform | 3 | company, custom_field_definition, tenant |
| Kanal | 1 | channel |

> Daftar ini dihasilkan secara mekanis dari `database/install.sql` (2026-09-15, 227 tabel); atribusi modul memakai kalibrasi yang sama dengan §20 `docs/ARCHITECTURE.md`: **satu tabel hanya masuk satu kolom**, nama modul mengikuti nama baris §20 —— 「Panel Admin + Sistem」/「Dasar Produk」/「Pembelian / Penjualan / Stok」/「Dasar Keuangan + Ekstensi Keuangan」/「Dasar CRM + Ekstensi CRM」 pada tabel ini sebelumnya telah masing-masing digabung ke Manajemen Sistem / Manajemen Produk / Manajemen Pembelian·Manajemen Penjualan·Manajemen Stok / Manajemen Keuangan / CRM (「dasar / ekstensi」adalah hasil pendaratan bertahap, §20 sudah menggabungkannya).
> Sepuluh baris terakhir (OMS / WMS / TMS / QMS / Pusat Member / Platform Terbuka / Template Cetak / Perpajakan / Dasar Platform / Kanal, total 48 tabel) adalah domain lanjutan dan tabel bersama yang **tidak dihitung pada baris mana pun di §20**; `company` juga dipakai ulang oleh laporan konsolidasi keuangan, `channel` adalah kamus kanal OMS.
> Pemeriksaan mandiri (① mengeluarkan 227 baris; ③ tanpa keluaran berarti tidak ada yang terlewat dan tidak ada yang ganda):
> ```bash
> # ① seluruh nama tabel install.sql (satu-satunya sumber kebenaran schema)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② kolom nama tabel daftar ini
> sed -n '/^## Daftar Tabel/,/^---$/p' docs/i18n/id/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ perbandingan (tanpa keluaran = tidak ada yang ganda atau terlewat)
> LC_ALL=C comm -3 <(①) <(②)
> ```

---

## Pemecahan Masalah

### Koneksi database gagal
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Koneksi Redis gagal
```bash
redis-cli ping    # harus mengembalikan PONG
```

### Port digunakan
```bash
ss -tlnp | grep 8788
# Ubah port listen: config/server.php
```

### Izin file
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Backup dan Restore

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # Backup (mysqldump+gzip, retensi 30 hari)
bash database/backup/restore.sh    # Restore (interaktif)
```

---

## Pemantauan

`GET /metrics` mengeluarkan format Prometheus: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Dokumentasi Terkait

| Dokumen | Path |
|------|------|
| Desain Arsitektur | `ARCHITECTURE.md` |
| Referensi API | `API.md` |
| Arsitektur Keamanan | `SECURITY.md` |
| Desain Fitur | `FEATURE_DESIGN.md` |
| Keamanan Nginx | `nginx-security.conf` |
