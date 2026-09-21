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
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'كلمة_المرور';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. استيراد قاعدة البيانات (أمر واحد يكفي)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

يحتوي `install.sql` على بنية جميع الجداول الـ 227 وبيانات البذرة الأولية (دور المدير الفائق وشجرة الصلاحيات ومراحل قمع المبيعات ومعدلات الضرائب والعملات ومؤشرات التحليل وتصنيفات المستندات وصلاحيات واجهات الخدمة)؛ schema مصدره الوحيد هو database/install.sql.

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
DB_PASSWORD=كلمة_المرور
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET_KEY=استبدلها بسلسلة عشوائية من 32 خانة فأكثر
APP_KEY=استبدلها بسلسلة عشوائية من 32 خانة

# مفتاح التسجيل المفتوح (الافتراضي 0=مغلق، وتُرجع الواجهة 403؛ يُنصح بإبقائه مغلقًا في الإنتاج)
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

يستمع افتراضيًا على `http://0.0.0.0:8788` (المنفذ في `APP_HTTP_PORT` بملف `.env`، وWebSocket في `APP_WS_PORT`).

### 6. التحقق من التثبيت

```bash
curl http://localhost:8788/health
```

افتح `http://localhost:8788/apidoc` في المتصفح لعرض وثائق API.

---

## ترقية بيئة منشورة

لا يوجد في هذا المستودع أداة هجرات: schema وبيانات البذرة مصدرهما الوحيد `database/install.sql`، وهو **سكربت تثبيت للمكتبة كاملة بمرة واحدة
(`INSERT` عادي وليس idempotent) — ولا يجوز إعادة تشغيله على قاعدة إنتاج حيّة**. وتُنفَّذ الترقية يدويًا حسب الفروق:

```bash
git diff <الإصدار القديم>..<الإصدار الجديد> -- database/install.sql    # استخراج فروق بنية الجداول والبذور
```

1. تُنفَّذ في قاعدة الإنتاج بالترتيب: عبارات `CREATE TABLE` (مع `IF NOT EXISTS`، ويمكن تشغيلها كما هي) / `ALTER TABLE` / `INSERT` الخاصة بالبذور الواردة في الفروق.
2. **بذور الصلاحيات**: يجب إضافة صفوف `erp_admin_permission` المقابلة للنقاط الجديدة. والمدير الفائق استثناء — فهو يحمل صلاحية شاملة
   (صف «كل الصلاحيات» حيث `slug = '*'` في `erp_admin_permission`، و`app/middleware/AdminPermission.php:38`
   يسمح بكل شيء عند رؤية `*`)، فتصبح النقاط الجديدة نافذة تلقائيًا؛ أما الأدوار المخصصة فتحتاج إلى إضافة الربط:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <معرّف الدور>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug صلاحية النقطة الجديدة>';
   ```

3. بعد الترقية شغّل مرة واحدة `curl http://localhost:8788/health` واختبار دخول دخاني للوحة الإدارة للتأكد من توافر الخدمة.

---

## الحساب الأولي

بعد التثبيت يتم توفير دور المدير الفائق مسبقًا (`super_admin`) بكل الصلاحيات. أول استخدام يتطلب إنشاء حساب مدير يدويًا:

```sql
-- إنشاء مدير (كلمة المرور بتجزئة bcrypt)
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', 'مدير النظام', 1);

-- ربط دور المدير الفائق
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

# الدخول إلى الحاوية واستيراد قاعدة البيانات
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

## قائمة الجداول (227 جدولًا)

| الوحدة | عدد الجداول | أسماء الجداول |
|------|------|------|
| إدارة النظام | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| إدارة المنتجات | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| إدارة المشتريات | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| إدارة المبيعات | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| إدارة المخزون | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| الإدارة المالية | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| إدارة علاقات العملاء (CRM) | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| سير عمل الموافقات | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| إشعارات الرسائل | 4 | notification, notification_channel_log, notification_setting, notification_template |
| إدارة المشاريع | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| الموارد البشرية | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| التصنيع | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| التقارير المخصصة | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| إدارة المعدات EAM | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| إدارة المستندات DMS | 3 | dms_category, dms_document, dms_document_version |
| لوحات BI | 2 | bi_dashboard, bi_widget |
| إدارة الطلبات OMS | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| إدارة المستودعات WMS | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| إدارة النقل TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| إدارة الجودة QMS | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| مركز العضوية | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| المنصة المفتوحة | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| قوالب الطباعة | 1 | print_template |
| الضرائب | 2 | tax_input_invoice, tax_issue_log |
| أساس المنصة | 3 | company, custom_field_definition, tenant |
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
redis-cli ping    # يجب أن تُرجع PONG
```

### المنفذ مشغول
```bash
ss -tlnp | grep 8788
# تعديل منفذ الاستماع: config/server.php
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
bash database/backup/backup.sh     # نسخ احتياطي (mysqldump+gzip، احتفاظ 30 يومًا)
bash database/backup/restore.sh    # استعادة (تفاعلية)
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
