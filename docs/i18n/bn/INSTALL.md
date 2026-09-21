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

`install.sql` এ সব ২২৭টি টেবিলের স্ট্রাকচার ও প্রাথমিক সিড ডেটা অন্তর্ভুক্ত (সুপার অ্যাডমিন রোল, পারমিশন ট্রি, ফানেল স্টেজ, ট্যাক্স রেট, কারেন্সি, অ্যানালিটিক্স মেট্রিক, ডকুমেন্ট ক্যাটাগরি, সার্ভিস ইন্টারফেস পারমিশন); schema এর একমাত্র সত্যের উৎস হলো database/install.sql।

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

ডিফল্টভাবে `http://0.0.0.0:8788` শোনে।

### 6. ইনস্টলেশন যাচাই করুন

```bash
curl http://localhost:8788/health
```

ব্রাউজারে `http://localhost:8788/apidoc` দেখে API ডকুমেন্টেশন দেখুন।

---

## ডিপ্লয় করা পরিবেশ আপগ্রেড করা

এই রিপোজিটরিতে কোনো মাইগ্রেশন টুল নেই: schema ও সিডের একমাত্র সত্যের উৎস `database/install.sql`,
আর সেটি **সম্পূর্ণ ডাটাবেসের এককালীন ইনস্টল স্ক্রিপ্ট (সাধারণ `INSERT`, idempotent নয়) —
লাইভ ডাটাবেসে পুনরায় চালানো যাবে না**। আপগ্রেড ডিফারেন্স অনুযায়ী ম্যানুয়ালি করতে হবে:

```bash
git diff <পুরনো সংস্করণ>..<নতুন সংস্করণ> -- database/install.sql    # টেবিল স্ট্রাকচার ও সিডের পার্থক্য বের করুন
```

1. ডিফারেন্সের `CREATE TABLE` (`IF NOT EXISTS` সহ, হুবহু চালানো যায়) / `ALTER TABLE` / সিড `INSERT`
   ক্রমানুসারে লাইভ ডাটাবেসে চালান।
2. **পারমিশন সিড**: নতুন এন্ডপয়েন্টের সংশ্লিষ্ট `erp_admin_permission` রেকর্ড যোগ করতে হবে। সুপার
   অ্যাডমিন ব্যতিক্রম —— তার কাছে ওয়াইল্ডকার্ড পারমিশন আছে (`erp_admin_permission`-এ `slug = '*'`
   "সব অনুমতি" রেকর্ড; `app/middleware/AdminPermission.php:38` `*` দেখলেই সব অনুমতি দেয়), তাই নতুন
   এন্ডপয়েন্ট স্বয়ংক্রিয়ভাবে কার্যকর হয়; কাস্টম রোলের জন্য সম্পর্ক যোগ করতে হবে:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <রোল ID>, `id` FROM `erp_admin_permission` WHERE `slug` = '<নতুন এন্ডপয়েন্টের পারমিশন slug>';
   ```

3. আপগ্রেডের পর একবার `curl http://localhost:8788/health` এবং অ্যাডমিন লগইন স্মোক চালিয়ে সার্ভিস সচল আছে কিনা নিশ্চিত করুন।

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
# প্লেসহোল্ডার কীকো র‍্য়াণ্ডম মান দিয্যে প্রতিস্থাপন করুন (idempotent)
bash scripts/gen-env-keys.sh .env
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

## টেবিল তালিকা (২২৭টি টেবিল)

| মডিউল | টেবিল সংখ্যা | টেবিলের নাম |
|------|------|------|
| সিস্টেম ম্যানেজমেন্ট | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| পণ্য ম্যানেজমেন্ট | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| ক্রয় ম্যানেজমেন্ট | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| বিক্রয় ম্যানেজমেন্ট | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| ইনভেন্টরি ম্যানেজমেন্ট | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| ফাইন্যান্স ম্যানেজমেন্ট | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| অ্যাপ্রুভাল ওয়ার্কফ্লো | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| মেসেজ নোটিফিকেশন | 4 | notification, notification_channel_log, notification_setting, notification_template |
| প্রজেক্ট ম্যানেজমেন্ট | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| হিউম্যান রিসোর্স | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| উৎপাদন ও ম্যানুফ্যাকচারিং | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| কাস্টম রিপোর্ট | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM ইকুইপমেন্ট ম্যানেজমেন্ট | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS ডকুমেন্ট ম্যানেজমেন্ট | 3 | dms_category, dms_document, dms_document_version |
| BI কানবান | 2 | bi_dashboard, bi_widget |
| OMS অর্ডার ম্যানেজমেন্ট | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS ওয়্যারহাউস ম্যানেজমেন্ট | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS ট্রান্সপোর্ট ম্যানেজমেন্ট | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS কোয়ালিটি ম্যানেজমেন্ট | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| মেম্বার সেন্টার | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| ওপেন প্ল্যাটফর্ম | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| প্রিন্ট টেমপ্লেট | 1 | print_template |
| ট্যাক্স | 2 | tax_input_invoice, tax_issue_log |
| প্ল্যাটফর্ম বেস | 3 | company, custom_field_definition, tenant |
| চ্যানেল | 1 | channel |

> এই তালিকা `database/install.sql` থেকে যান্ত্রিকভাবে তৈরি (২০২৬-০৯-১৫, ২২৭টি টেবিল); মডিউল নির্ধারণ `docs/ARCHITECTURE.md` §20-এর সাথে একই নীতি: **একটি টেবিল কেবল একটি কলামে**, মডিউলের নাম §20-এর সারির নাম অনুসারে —— এই তালিকার পূর্বতন "অ্যাডমিন ব্যাকএন্ড + সিস্টেম"、"পণ্য বেস"、"ক্রয় / বিক্রয় / ইনভেন্টরি"、"ফাইন্যান্স বেস + ফাইন্যান্স এক্সটেনশন"、"CRM বেস + CRM এক্সটেনশন" যথাক্রমে সিস্টেম ম্যানেজমেন্ট / পণ্য ম্যানেজমেন্ট / ক্রয় ম্যানেজমেন্ট·বিক্রয় ম্যানেজমেন্ট·ইনভেন্টরি ম্যানেজমেন্ট / ফাইন্যান্স ম্যানেজমেন্ট / CRM-এ একীভূত হয়েছে ("বেস / এক্সটেনশন" বিভাজন ছিল ধাপে ধাপে ডেলিভারির ফলাফল, §20-এ তা একীভূত করা হয়েছে)।
> শেষ ১০টি সারি (OMS / WMS / TMS / QMS / মেম্বার সেন্টার / ওপেন প্ল্যাটফর্ম / প্রিন্ট টেমপ্লেট / ট্যাক্স / প্ল্যাটফর্ম বেস / চ্যানেল, মোট ৪৮টি টেবিল) হলো **§20-এর কোনো সারিতে অন্তর্ভুক্ত নয়** এমন পরবর্তীকালীন ডোমেইন ও শেয়ার্ড টেবিল; `company` একই সাথে ফাইন্যান্স কনসোলিডেশন রিপোর্টেও ব্যবহৃত হয়, `channel` হলো OMS চ্যানেল ডিকশনারি।
> স্ব-পরীক্ষা (① ২২৭টি সারি আউটপুট দেয়; ③ আউটপুট না থাকলেই কোনো টেবিল বাদ পড়েনি, কোনোটি দুবারও নেই):
> ```bash
> # ① install.sql-এর সম্পূর্ণ টেবিল নাম (schema-এর একমাত্র সত্যের উৎস)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② এই তালিকার টেবিল নাম কলাম
> sed -n '/^## 表清单/,/^---$/p' docs/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ তুলনা (আউটপুট নেই = পুনরাবৃত্তি নেই, বাদও পড়েনি)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
ss -tlnp | grep 8788
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
