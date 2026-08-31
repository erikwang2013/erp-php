# Open-ERP-System — Installationsanleitung

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Systemvoraussetzungen

| Komponente | Mindestversion | Beschreibung |
|------|---------|------|
| PHP | 8.3+ | Erforderliche Erweiterungen: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Zeichensatz utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Für Cache, Rate-Limit, Session |
| Composer | 2.x | PHP-Abhängigkeitsverwaltung |
| Elasticsearch | 8.x | Optional, Volltextsuche |

### PHP-Erweiterungen prüfen

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

Fehlende Erweiterungen installieren (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Installationsschritte

### 1. Datenbank erstellen

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Datenbank importieren (mit einem Befehl)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` enthält die Strukturen aller 163 Tabellen sowie die initialen Seed-Daten (Super-Admin-Rolle, Berechtigungsbaum, Funnel-Phasen, Steuersätze, Währungen, Analyse-Kennzahlen, Dokumentkategorien, Dienst-Schnittstellenberechtigungen); das Schema in database/install.sql ist die einzige maßgebliche Quelle.

### 3. Umgebungsvariablen konfigurieren

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

`.env` bearbeiten und folgende Schlüsselkonfigurationen anpassen:

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

### 4. PHP-Abhängigkeiten installieren

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Dienst starten

```bash
php start.php start
```

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8788`.

### 6. Installation verifizieren

```bash
curl http://localhost:8788/health
```

Die API-Dokumentation ist im Browser unter `http://localhost:8788/apidoc` abrufbar.

---

## Initiale Konten

Nach der Installation ist eine Super-Admin-Rolle (`super_admin`) vorab angelegt, die alle Berechtigungen besitzt. Beim ersten Einsatz muss ein Admin-Konto manuell erstellt werden:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> Die `id` wird von `snowflake-php` in der Anwendungsschicht erzeugt; alternativ kann das Konto über die Registrierungsschnittstelle angelegt werden.

---

## Docker-Compose-Deployment

Im Projektstamm werden 5 Dienste orchestriert: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Platzhalter-Schluessel durch Zufallswerte ersetzen (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# In den Container wechseln und Datenbank importieren
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Datenbank-Konventionen

| Konvention | Beschreibung |
|------|------|
| Tabellenpräfix | `erp_` |
| Primärschlüssel | `id` BIGINT UNSIGNED NOT NULL, nicht auto-increment, von snowflake-php erzeugt |
| Zeichensatz | utf8mb4, utf8mb4_unicode_ci |
| Engine | InnoDB |
| Soft Delete | `deleted_at` DATETIME DEFAULT NULL |
| Zeitstempel | `created_at` / `updated_at` werden automatisch gepflegt |
| Sensible Felder | automatische Ver-/Entschlüsselung über das encryptable-Trait |

---

## Tabellenliste (163 Tabellen)

| Modul | Anzahl | Tabellennamen |
|------|------|------|
| Admin-Backend | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| System | 1 | operation_log |
| Produktbasis | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Einkauf | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Vertrieb | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Bestand | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Finanzbasis | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Finanzerweiterung | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM-Basis | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM-Erweiterung | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Genehmigungsworkflow | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Benachrichtigungen | 3 | notification, notification_template, notification_setting |
| Projektmanagement | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| Personalwesen | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Produktion | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Benutzerdefinierte Berichte | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS-Auftragsverwaltung | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS-Lagerverwaltung | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS-Transportverwaltung | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS-Qualitätsmanagement | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM-Anlagenverwaltung | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS-Dokumentenverwaltung | 3 | dms_category, dms_document, dms_document_version |
| BI-Dashboards | 2 | bi_dashboard, bi_widget |
| Kanäle | 1 | channel |

---

## Fehlerbehebung

### Datenbankverbindung fehlgeschlagen
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis-Verbindung fehlgeschlagen
```bash
redis-cli ping    # sollte PONG zurückgeben
```

### Port bereits belegt
```bash
ss -tlnp | grep 8788
# 修改监听端口: config/server.php
```

### Dateiberechtigungen
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Backup und Wiederherstellung

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # Backup (mysqldump+gzip, 30 Tage Aufbewahrung)
bash database/backup/restore.sh    # Wiederherstellung (interaktiv)
```

---

## Monitoring

`GET /metrics` liefert Prometheus-Format: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Weitere Dokumentation

| Dokument | Pfad |
|------|------|
| Architektur | `ARCHITECTURE.md` |
| API-Referenz | `API.md` |
| Sicherheitsarchitektur | `SECURITY.md` |
| Funktionsdesign | `FEATURE_DESIGN.md` |
| Nginx-Sicherheit | `nginx-security.conf` |
