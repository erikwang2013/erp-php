# ওপেন-ইআরপি সিস্টেম — ইনস্টলেশন গাইড

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## পরিবেশ প্রয়োজনীয়তা

| কম্পোনেন্ট | সর্বনিম্ন সংস্করণ | বিবরণ |
|------|---------|------|
| PHP | 8.3+ | এক্সটেনশন প্রয়োজন: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | ক্যারেক্টার সেট utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | ক্যাশ, রেট লিমিট, সেশন এর জন্য |
| Composer | 2.x | PHP নির্ভরতা ম্যানেজমেন্ট |
| Elasticsearch | 8.x | ঐচ্ছিক, ফুল-টেক্সট সার্চ |

### PHP এক্সটেনশন চেক

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

এক্সটেনশন না থাকলে (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## ইনস্টলেশন ধাপ

### 1. ডাটাবেস তৈরি করুন

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. ডাটাবেস ইমপোর্ট করুন (একটি কমান্ডে)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` এ সব ১৬৩টি টেবিলের স্ট্রাকচার ও প্রাথমিক সিড ডেটা অন্তর্ভুক্ত (সুপার অ্যাডমিন রোল, পারমিশন ট্রি, ফানেল স্টেজ, ট্যাক্স রেট, কারেন্সি, অ্যানালিটিক্স মেট্রিক, ডকুমেন্ট ক্যাটাগরি, সার্ভিস ইন্টারফেস পারমিশন); schema এর একমাত্র সত্যের উৎস হলো database/install.sql।

### 3. পরিবেশ ভেরিয়েবল কনফিগার করুন

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

`.env` এডিট করে নিম্নলিখিত মূল কনফিগারেশন পরিবর্তন করুন:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=你的密码
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=修改为32位以上随机字符串
APP_KEY=修改为32位随机字符串

# 开放注册开关（默认 0=关闭，接口返回 403；生产建议保持关闭）
REGISTRATION_ENABLED=0
```

### 4. PHP নির্ভরতা ইনস্টল করুন

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. সার্ভিস চালু করুন

```bash
php start.php start
```

ডিফল্টভাবে `http://0.0.0.0:8787` শোনে।

### 6. ইনস্টলেশন যাচাই করুন

```bash
curl http://localhost:8787/health
```

ব্রাউজারে `http://localhost:8787/apidoc` দেখে API ডকুমেন্টেশন দেখুন।

---

## প্রাথমিক অ্যাকাউন্ট

ইনস্টলের পরে একটি সুপার অ্যাডমিন রোল (`super_admin`) প্রি-সেট থাকে, যার সব অনুমতি আছে। প্রথম ব্যবহারে ম্যানুয়ালি অ্যাডমিন অ্যাকাউন্ট তৈরি করতে হবে:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` অ্যাপ্লিকেশন স্তরে `snowflake-php` দিয়ে তৈরি হয়, রেজিস্ট্রেশন ইন্টারফেসের মাধ্যমেও পাওয়া যায়।

---

## Docker Compose ডিপ্লয়মেন্ট

প্রজেক্ট রুট ডিরেক্টরি ৫টি সার্ভিস অর্কেস্ট্রেট করে: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x)।

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## ডাটাবেস নিয়মাবলী

| নিয়ম | বিবরণ |
|------|------|
| টেবিল প্রিফিক্স | `erp_` |
| প্রাইমারি কী | `id` BIGINT UNSIGNED NOT NULL, নন-অটোইনক্রিমেন্ট, snowflake-php দিয়ে তৈরি |
| ক্যারেক্টার সেট | utf8mb4, utf8mb4_unicode_ci |
| ইঞ্জিন | InnoDB |
| সফট ডিলিট | `deleted_at` DATETIME DEFAULT NULL |
| টাইমস্ট্যাম্প | `created_at` / `updated_at` স্বয়ংক্রিয় রক্ষণাবেক্ষণ |
| সংবেদনশীল ফিল্ড | encryptable trait দিয়ে স্বয়ংক্রিয় এনক্রিপশন/ডিক্রিপশন |

---

## টেবিল তালিকা (১৬৩টি টেবিল)

| মডিউল | টেবিল সংখ্যা | টেবিলের নাম |
|------|------|------|
| অ্যাডমিন ব্যাকএন্ড | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| সিস্টেম | 1 | operation_log |
| পণ্য বেস | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| ক্রয় | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| বিক্রয় | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| ইনভেন্টরি | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| ফাইন্যান্স বেস | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| ফাইন্যান্স এক্সটেনশন | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM বেস | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM এক্সটেনশন | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| অ্যাপ্রুভাল ওয়ার্কফ্লো | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| নোটিফিকেশন | 3 | notification, notification_template, notification_setting |
| প্রজেক্ট ম্যানেজমেন্ট | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| হিউম্যান রিসোর্স | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| ম্যানুফ্যাকচারিং | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| কাস্টম রিপোর্ট | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS অর্ডার ম্যানেজমেন্ট | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS ওয়্যারহাউস ম্যানেজমেন্ট | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS ট্রান্সপোর্ট ম্যানেজমেন্ট | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS কোয়ালিটি ম্যানেজমেন্ট | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM ইকুইপমেন্ট ম্যানেজমেন্ট | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS ডকুমেন্ট ম্যানেজমেন্ট | 3 | dms_category, dms_document, dms_document_version |
| BI ড্যাশবোর্ড | 2 | bi_dashboard, bi_widget |
| চ্যানেল | 1 | channel |

---

## সমস্যা সমাধান

### ডাটাবেস সংযোগ ব্যর্থ

```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis সংযোগ ব্যর্থ

```bash
redis-cli ping    # 应返回 PONG
```

### পোর্ট ব্যবহার হয়ে গেছে

```bash
ss -tlnp | grep 8787
# 修改监听端口: config/server.php
```

### ফাইল পারমিশন

```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## ব্যাকআপ ও রিস্টোর

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## মনিটরিং

`GET /metrics` Prometheus ফরম্যাটে আউটপুট দেয়: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`।

---

## সম্পর্কিত ডকুমেন্টেশন

| ডকুমেন্ট | পাথ |
|------|------|
| আর্কিটেকচার ডিজাইন | `ARCHITECTURE.md` |
| API রেফারেন্স | `API.md` |
| নিরাপত্তা আর্কিটেকচার | `SECURITY.md` |
| ফিচার ডিজাইন | `FEATURE_DESIGN.md` |
| Nginx নিরাপত্তা | `nginx-security.conf` |
