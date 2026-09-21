# ओपन-ईआरपी प्रणाली — इंस्टॉलेशन विज़ार्ड

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## पर्यावरण आवश्यकताएँ

| घटक | न्यूनतम संस्करण | विवरण |
|------|---------|------|
| PHP | 8.3+ | आवश्यक एक्सटेंशन: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | वर्ण सेट utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | कैश, रेट लिमिट, सत्र के लिए |
| Composer | 2.x | PHP निर्भरता प्रबंधन |
| Elasticsearch | 8.x | वैकल्पिक, फुल-टेक्स्ट खोज |

### PHP एक्सटेंशन जांच

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

एक्सटेंशन कम होने पर (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## इंस्टॉलेशन चरण

### 1. डेटाबेस बनाएँ

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. डेटाबेस आयात करें (एक कमांड में)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` में सभी 227 तालिकाओं की संरचना और प्रारंभिक सीड डेटा शामिल है (सुपर एडमिन भूमिका, अनुमति ट्री, फ़नल चरण, कर दरें, मुद्राएँ, विश्लेषण मेट्रिक्स, दस्तावेज़ श्रेणियाँ, सेवा इंटरफ़ेस अनुमतियाँ); schema के लिए database/install.sql ही एकमात्र सत्य स्रोत है।

### 3. पर्यावरण चर कॉन्फ़िगर करें

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

`.env` संपादित करें, निम्न मुख्य कॉन्फ़िगरेशन बदलें:

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

JWT_SECRET_KEY=修改为32位以上随机字符串
APP_KEY=修改为32位随机字符串

# 开放注册开关（默认 0=关闭，接口返回 403；生产建议保持关闭）
REGISTRATION_ENABLED=0
```

### 4. PHP निर्भरताएँ स्थापित करें

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. सेवा शुरू करें

```bash
php start.php start
```

डिफ़ॉल्ट रूप से `http://0.0.0.0:8788` पर सुनता है।

### 6. इंस्टॉलेशन सत्यापित करें

```bash
curl http://localhost:8788/health
```

ब्राउज़र में `http://localhost:8788/apidoc` खोलकर API दस्तावेज़ देखें।

---

## तैनात वातावरण का उन्नयन

इस रिपॉजिटरी में कोई माइग्रेशन टूल नहीं है: schema और सीड के लिए `database/install.sql` एकमात्र सत्य स्रोत है, और वह **पूरी डेटाबेस की एक-बार इंस्टॉल स्क्रिप्ट
(सामान्य `INSERT`, इडेम्पोटेंट नहीं) है——मौजूदा प्रोडक्शन डेटाबेस पर दोबारा नहीं चलाई जा सकती**। उन्नयन अंतर के अनुसार मैन्युअल रूप से किया जाता है:

```bash
git diff <旧版本>..<新版本> -- database/install.sql    # 取出表结构与种子的差异
```

1. अंतर में आए `CREATE TABLE` (`IF NOT EXISTS` सहित, यथावत चलाई जा सकती है) / `ALTER TABLE` / सीड `INSERT` क्रम से मौजूदा डेटाबेस पर निष्पादित करें।
2. **अनुमति सीड**: नए एंडपॉइंट के अनुरूप `erp_admin_permission` पंक्तियाँ जोड़नी होंगी। सुपर एडमिन अपवाद है——उसके पास वाइल्डकार्ड अनुमति
   (`erp_admin_permission` में `slug = '*'` वाली 「सभी अनुमतियाँ」 पंक्ति, `app/middleware/AdminPermission.php:38`
   में `*` दिखते ही पूर्ण अनुमति), अतः नए एंडपॉइंट स्वतः प्रभावी हो जाते हैं; कस्टम भूमिकाओं के लिए संबंध अलग से जोड़ना होगा:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <角色ID>, `id` FROM `erp_admin_permission` WHERE `slug` = '<新端点的权限 slug>';
   ```

3. उन्नयन के बाद एक बार `curl http://localhost:8788/health` और प्रशासन लॉगिन स्मोक चलाएँ, सेवा उपलब्ध होने की पुष्टि करें।

---

## प्रारंभिक खाता

इंस्टॉलेशन के बाद एक सुपर एडमिन भूमिका (`super_admin`) पूर्व-स्थापित होती है, जिसके पास सभी अनुमतियाँ होती हैं। पहली बार उपयोग में व्यवस्थापक खाता मैन्युअल रूप से बनाना होता है:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` एप्लिकेशन परत में `snowflake-php` से उत्पन्न होता है, या पंजीकरण इंटरफ़ेस से प्राप्त किया जा सकता है।

---

## Docker Compose परिनियोजन

प्रोजेक्ट रूट निर्देशिका में 5 सेवाओं का ऑर्केस्ट्रेशन है: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x)।

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# स्ररूप कूंजियों को यदृचछिक मानों से बदलें (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## डेटाबेस अनुबंध

| अनुबंध | विवरण |
|------|------|
| टेबल उपसर्ग | `erp_` |
| प्राथमिक कुंजी | `id` BIGINT UNSIGNED NOT NULL, गैर-ऑटोइंक्रीमेंट, snowflake-php से उत्पन्न |
| वर्ण सेट | utf8mb4, utf8mb4_unicode_ci |
| इंजन | InnoDB |
| सॉफ्ट डिलीट | `deleted_at` DATETIME DEFAULT NULL |
| टाइमस्टैम्प | `created_at` / `updated_at` स्वतः बनाए रखा |
| संवेदनशील फ़ील्ड | encryptable trait से स्वतः एन्क्रिप्ट/डिक्रिप्ट |

---

## तालिका सूची (227 तालिकाएँ)

| मॉड्यूल | तालिका संख्या | तालिका नाम |
|------|------|------|
| सिस्टम प्रबंधन | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| उत्पाद प्रबंधन | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| क्रय प्रबंधन | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| विक्रय प्रबंधन | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| इन्वेंटरी प्रबंधन | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| वित्त प्रबंधन | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| अनुमोदन वर्कफ़्लो | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| संदेश अधिसूचना | 4 | notification, notification_channel_log, notification_setting, notification_template |
| परियोजना प्रबंधन | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| मानव संसाधन | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| विनिर्माण | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| कस्टम रिपोर्ट | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM उपकरण प्रबंधन | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS दस्तावेज़ प्रबंधन | 3 | dms_category, dms_document, dms_document_version |
| BI बोर्ड | 2 | bi_dashboard, bi_widget |
| OMS ऑर्डर प्रबंधन | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS वेयरहाउस प्रबंधन | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS परिवहन प्रबंधन | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS गुणवत्ता प्रबंधन | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| सदस्यता केंद्र | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| ओपन प्लेटफ़ॉर्म | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| प्रिंट टेम्पलेट | 1 | print_template |
| कर | 2 | tax_input_invoice, tax_issue_log |
| प्लेटफ़ॉर्म आधार | 3 | company, custom_field_definition, tenant |
| चैनल | 1 | channel |

> यह सूची `database/install.sql` से यंत्रवत रूप से उत्पन्न है (2026-09-15, 227 तालिकाएँ), मॉड्यूल वर्गीकरण `docs/ARCHITECTURE.md` §20 के समान मानदंड पर: **एक तालिका केवल एक कॉलम में**, मॉड्यूल नाम §20 की पंक्ति नामों के अनुसार —— इस तालिका के पूर्व के 「प्रशासन बैकएंड + सिस्टम」「उत्पाद आधार」「क्रय / विक्रय / इन्वेंटरी」「वित्त आधार + वित्त विस्तार」「CRM आधार + CRM विस्तार」 अब क्रमशः सिस्टम प्रबंधन / उत्पाद प्रबंधन / क्रय प्रबंधन·विक्रय प्रबंधन·इन्वेंटरी प्रबंधन / वित्त प्रबंधन / CRM में समाहित कर दिए गए हैं (「आधार / विस्तार」 बैचों में लागू होने का परिणाम था, §20 में विलय हो चुका है)।
> अंतिम 10 पंक्तियाँ (OMS / WMS / TMS / QMS / सदस्यता केंद्र / ओपन प्लेटफ़ॉर्म / प्रिंट टेम्पलेट / कर / प्लेटफ़ॉर्म आधार / चैनल, कुल 48 तालिकाएँ) **§20 की किसी भी पंक्ति में नहीं गिनी गईं**, ये परवर्ती क्षेत्र और साझा तालिकाएँ हैं; `company` को वित्तीय समेकित विवरण भी पुनः उपयोग करता है, `channel` OMS का चैनल शब्दकोश है।
> स्व-जाँच (① में 227 पंक्तियाँ आनी चाहिए; ③ का कोई आउटपुट न हो तो एक भी तालिका न छूटी, न दोहराई गई):
> ```bash
> # ① install.sql 全量表名（schema 唯一事实源）
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② 本清单表名列
> sed -n '/^## 表清单/,/^---$/p' docs/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ 比对（无输出 = 无重无漏）
> LC_ALL=C comm -3 <(①) <(②)
> ```

---

## समस्या निवारण

### डेटाबेस कनेक्शन विफल
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis कनेक्शन विफल
```bash
redis-cli ping    # 应返回 PONG
```

### पोर्ट व्यस्त
```bash
ss -tlnp | grep 8788
# 修改监听端口: config/server.php
```

### फ़ाइल अनुमतियाँ
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## बैकअप और पुनर्स्थापना

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## निगरानी

`GET /metrics` Prometheus प्रारूप में आउटपुट देता है: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`।

---

## संबंधित दस्तावेज़

| दस्तावेज़ | पथ |
|------|------|
| आर्किटेक्चर डिज़ाइन | `docs/ARCHITECTURE.md` |
| API संदर्भ | `docs/API.md` |
| सुरक्षा आर्किटेक्चर | `docs/SECURITY.md` |
| फ़ीचर डिज़ाइन | `docs/FEATURE_DESIGN.md` |
| Nginx सुरक्षा | `docs/nginx-security.conf` |
