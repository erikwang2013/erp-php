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

`install.sql` में सभी 163 तालिकाओं की संरचना और प्रारंभिक सीड डेटा शामिल है (सुपर एडमिन भूमिका, अनुमति ट्री, फ़नल चरण, कर दरें, मुद्राएँ, विश्लेषण मेट्रिक्स, दस्तावेज़ श्रेणियाँ, सेवा इंटरफ़ेस अनुमतियाँ); schema के लिए database/install.sql ही एकमात्र सत्य स्रोत है।

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

JWT_SECRET=修改为32位以上随机字符串
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

## तालिका सूची (163 तालिकाएँ)

| मॉड्यूल | तालिका संख्या | तालिका नाम |
|------|------|------|
| प्रशासन बैकएंड | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| सिस्टम | 1 | operation_log |
| उत्पाद आधार | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| क्रय | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| विक्रय | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| इन्वेंटरी | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| वित्त आधार | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| वित्त विस्तार | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM आधार | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM विस्तार | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| अनुमोदन वर्कफ़्लो | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| संदेश अधिसूचना | 3 | notification, notification_template, notification_setting |
| परियोजना प्रबंधन | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| मानव संसाधन | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| विनिर्माण | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| कस्टम रिपोर्ट | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS ऑर्डर प्रबंधन | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS वेयरहाउस प्रबंधन | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS परिवहन प्रबंधन | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS गुणवत्ता प्रबंधन | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM उपकरण प्रबंधन | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS दस्तावेज़ प्रबंधन | 3 | dms_category, dms_document, dms_document_version |
| BI बोर्ड | 2 | bi_dashboard, bi_widget |
| चैनल | 1 | channel |

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
