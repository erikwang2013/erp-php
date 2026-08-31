# نظام إدارة موارد المؤسسات المفتوح — معالج التثبيت

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## متطلبات البيئة

| المكوّن | الحد الأدنى للإصدار | الوصف |
|------|---------|------|
| PHP | 8.3+ | يتطلب تفعيل الإضافات: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | مجموعة الأحرف utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | للتخزين المؤقت وتحديد المعدل والجلسات |
| Composer | 2.x | إدارة تبعيات PHP |
| Elasticsearch | 8.x | اختياري، البحث النصي الكامل |

### فحص إضافات PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

عند نقص الإضافات (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## خطوات التثبيت

### 1. إنشاء قاعدة البيانات

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. استيراد قاعدة البيانات (أمر واحد يكفي)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

يحتوي `install.sql` على بنية جميع الجداول الـ 163 وبيانات البذرة الأولية (دور المدير الفائق وشجرة الصلاحيات ومراحل قمع المبيعات ومعدلات الضرائب والعملات ومؤشرات التحليل وتصنيفات المستندات وصلاحيات واجهات الخدمة)؛ schema مصدره الوحيد هو database/install.sql.

### 3. ضبط متغيرات البيئة

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

عدّل `.env` وغير البنود الرئيسية التالية:

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

### 4. تثبيت تبعيات PHP

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. بدء الخدمة

```bash
php start.php start
```

يستمع افتراضيًا على `http://0.0.0.0:8788`.

### 6. التحقق من التثبيت

```bash
curl http://localhost:8788/health
```

افتح `http://localhost:8788/apidoc` في المتصفح لعرض وثائق API.

---

## الحساب الأولي

بعد التثبيت يتم توفير دور المدير الفائق مسبقًا (`super_admin`) بكل الصلاحيات. أول استخدام يتطلب إنشاء حساب مدير يدويًا:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> يُولَّد `id` عبر `snowflake-php` في طبقة التطبيق، ويمكن الحصول عليه أيضًا عبر واجهة التسجيل.

---

## النشر عبر Docker Compose

في جذر المشروع 5 خدمات: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# استبدال المفاتيح المبدئية بقيم عشوائية (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## اتفاقيات قاعدة البيانات

| الاتفاقية | الوصف |
|------|------|
| بادئة الجداول | `erp_` |
| المفتاح الأساسي | `id` BIGINT UNSIGNED NOT NULL، غير تلقائي التزايد، يولَّد عبر snowflake-php |
| مجموعة الأحرف | utf8mb4, utf8mb4_unicode_ci |
| المحرك | InnoDB |
| الحذف الناعم | `deleted_at` DATETIME DEFAULT NULL |
| الطوابع الزمنية | `created_at` / `updated_at` تُدار تلقائيًا |
| الحقول الحساسة | تشفير/فك تشفير تلقائي عبر trait encryptable |

---

## قائمة الجداول (163 جدولًا)

| الوحدة | عدد الجداول | أسماء الجداول |
|------|------|------|
| لوحة الإدارة | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| النظام | 1 | operation_log |
| أساس المنتجات | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| المشتريات | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| المبيعات | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| المخزون | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| أساس المالية | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| توسعات المالية | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| أساس CRM | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| توسعات CRM | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| سير عمل الموافقات | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| إشعارات الرسائل | 3 | notification, notification_template, notification_setting |
| إدارة المشاريع | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| الموارد البشرية | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| التصنيع | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| التقارير المخصصة | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| إدارة الطلبات OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| إدارة المستودعات WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| إدارة النقل TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| إدارة الجودة QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| إدارة المعدات EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| إدارة المستندات DMS | 3 | dms_category, dms_document, dms_document_version |
| لوحات BI | 2 | bi_dashboard, bi_widget |
| القنوات | 1 | channel |

---

## استكشاف الأخطاء وإصلاحها

### فشل الاتصال بقاعدة البيانات
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### فشل الاتصال بـ Redis
```bash
redis-cli ping    # 应返回 PONG
```

### المنفذ مشغول
```bash
ss -tlnp | grep 8788
# 修改监听端口: config/server.php
```

### صلاحيات الملفات
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## النسخ الاحتياطي والاستعادة

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## المراقبة

يُخرج `GET /metrics` بصيغة Prometheus: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## الوثائق ذات الصلة

| الوثيقة | المسار |
|------|------|
| التصميم المعماري | `ARCHITECTURE.md` |
| مرجع API | `API.md` |
| بنية الأمان | `SECURITY.md` |
| التصميم الوظيفي | `FEATURE_DESIGN.md` |
| أمان Nginx | `nginx-security.conf` |
