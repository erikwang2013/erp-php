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

`install.sql` berisi struktur seluruh 163 tabel dan data seed awal (peran super admin, pohon izin, tahap corong, tarif pajak, mata uang, metrik analisis, kategori dokumen, izin antarmuka layanan); schema mengacu pada database/install.sql sebagai satu-satunya sumber kebenaran.

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

Secara default mendengarkan di `http://0.0.0.0:8787`.

### 6. Verifikasi Instalasi

```bash
curl http://localhost:8787/health
```

Akses `http://localhost:8787/apidoc` di browser untuk melihat dokumen API.

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

## Daftar Tabel (163 tabel)

| Modul | Jumlah Tabel | Nama Tabel |
|------|------|------|
| Admin | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| Sistem | 1 | operation_log |
| Dasar Produk | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Pembelian | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Penjualan | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Stok | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Dasar Keuangan | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Ekstensi Keuangan | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| Dasar CRM | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| Ekstensi CRM | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Alur Persetujuan | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Notifikasi Pesan | 3 | notification, notification_template, notification_setting |
| Manajemen Proyek | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| Sumber Daya Manusia | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Manufaktur | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Laporan Kustom | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| Manajemen Pesanan OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| Manajemen Gudang WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| Manajemen Transportasi TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| Manajemen Kualitas QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| Manajemen Peralatan EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| Manajemen Dokumen DMS | 3 | dms_category, dms_document, dms_document_version |
| Papan BI | 2 | bi_dashboard, bi_widget |
| Kanal | 1 | channel |

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
ss -tlnp | grep 8787
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
