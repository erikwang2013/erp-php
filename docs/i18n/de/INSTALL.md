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

`install.sql` enthält die Strukturen aller 227 Tabellen sowie die initialen Seed-Daten (Super-Admin-Rolle, Berechtigungsbaum, Funnel-Phasen, Steuersätze, Währungen, Analyse-Kennzahlen, Dokumentkategorien, Dienst-Schnittstellenberechtigungen); das Schema in database/install.sql ist die einzige maßgebliche Quelle.

### 3. Umgebungsvariablen konfigurieren

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
# Zufällige Schlüssel erzeugen und in .env schreiben (JWT_SECRET_KEY/ENCRYPTION_KEY/HASHIDS_SALT usw., idempotent; Platzhalterwerte werden von env_required beim Start abgelehnt)
bash scripts/gen-env-keys.sh .env
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

JWT_SECRET_KEY=修改为32位以上随机字符串
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

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8788` (Port siehe `APP_HTTP_PORT` in `.env`, WebSocket über `APP_WS_PORT`).

### 6. Installation verifizieren

```bash
curl http://localhost:8788/health
```

Die API-Dokumentation ist im Browser unter `http://localhost:8788/apidoc` abrufbar.

---

## Upgrade einer bestehenden Umgebung

Dieses Repository hat kein Migrationstool: Schema und Seeds haben `database/install.sql` als einzige maßgebliche Quelle, und dabei handelt es sich um ein **Einmal-Installationsskript für die gesamte Datenbank
(gewöhnliche `INSERT`, nicht idempotent) — es darf nicht auf einer Produktivdatenbank erneut ausgeführt werden**. Ein Upgrade erfolgt manuell anhand des Diffs:

```bash
git diff <alte Version>..<neue Version> -- database/install.sql    # Differenz von Tabellenstruktur und Seeds entnehmen
```

1. Die `CREATE TABLE` (mit `IF NOT EXISTS`, unverändert ausführbar) / `ALTER TABLE` / Seed-`INSERT` aus dem Diff in dieser Reihenfolge auf der Produktivdatenbank ausführen.
2. **Berechtigungs-Seeds**: Für neue Endpunkte müssen die zugehörigen `erp_admin_permission`-Zeilen nachgetragen werden. Der Super-Admin ist die Ausnahme — er hält eine Wildcard-Berechtigung
   (die Zeile „Alle Berechtigungen" mit `slug = '*'` in `erp_admin_permission`; `app/middleware/AdminPermission.php:38`
   lässt bei `*` alles durch), neue Endpunkte greifen dort automatisch; benutzerdefinierte Rollen brauchen zusätzlich eine Zuordnung:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <Rollen-ID>, `id` FROM `erp_admin_permission` WHERE `slug` = '<Berechtigungs-Slug des neuen Endpunkts>';
   ```

3. Nach dem Upgrade einmal `curl http://localhost:8788/health` und einen Login-Smoke-Test im Admin-Panel ausführen, um die Verfügbarkeit des Dienstes zu bestätigen.

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

## Tabellenliste (227 Tabellen)

| Modul | Anzahl | Tabellennamen |
|------|------|------|
| Systemverwaltung | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Artikelverwaltung | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Einkaufsverwaltung | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Vertriebsverwaltung | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Bestandsverwaltung | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Finanzverwaltung | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Genehmigungsworkflow | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Benachrichtigungen | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Projektmanagement | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| Personalwesen | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Produktion und Fertigung | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Benutzerdefinierte Berichte | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM Anlagenverwaltung | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS Dokumentenverwaltung | 3 | dms_category, dms_document, dms_document_version |
| BI-Dashboards | 2 | bi_dashboard, bi_widget |
| OMS Auftragsverwaltung | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS Lagerverwaltung | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS Transportverwaltung | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS Qualitätsmanagement | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Mitgliederzentrum | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Offene Plattform | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Druckvorlagen | 1 | print_template |
| Steuern | 2 | tax_input_invoice, tax_issue_log |
| Plattform-Basis | 3 | company, custom_field_definition, tenant |
| Kanäle | 1 | channel |

> Diese Liste wurde mechanisch aus `database/install.sql` erzeugt (2026-09-15, 227 Tabellen); die Modulzuordnung folgt derselben Zählweise wie §20 in `docs/ARCHITECTURE.md`: **eine Tabelle gehört genau einer Zeile an**, die Modulnamen übernehmen die Zeilennamen aus §20 — die früheren Zeilen dieser Tabelle („Admin-Backend + System", „Produktbasis", „Einkauf / Vertrieb / Bestand", „Finanzbasis + Finanzerweiterung", „CRM-Basis + CRM-Erweiterung") wurden in Systemverwaltung / Artikelverwaltung / Einkaufsverwaltung·Vertriebsverwaltung·Bestandsverwaltung / Finanzverwaltung / CRM zusammengeführt („Basis / Erweiterung" war ein Artefakt der stufenweisen Lieferung, §20 hat bereits zusammengeführt).
> Die letzten 10 Zeilen (OMS / WMS / TMS / QMS / Mitgliederzentrum / Offene Plattform / Druckvorlagen / Steuern / Plattform-Basis / Kanäle, zusammen 48 Tabellen) sind spätere Domänen und gemeinsame Tabellen, die **in keiner Zeile von §20 erfasst sind**; `company` wird zusätzlich von den konsolidierten Finanzberichten genutzt, `channel` ist das OMS-Kanäleverzeichnis.
> Selbstprüfung (① gibt 227 Zeilen aus; ③ ohne Ausgabe = keine Tabelle fehlt, keine ist doppelt):

> ```bash
> # ① Alle Tabellennamen aus install.sql (einzige Schema-Wahrheitsquelle)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② Tabellennamen-Spalte dieser Liste
> sed -n '/^## 表清单/,/^---$/p' docs/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ Abgleich (ohne Ausgabe = weder doppelt noch fehlend)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
| Architektur | `docs/ARCHITECTURE.md` |
| API-Referenz | `docs/API.md` |
| Sicherheitsarchitektur | `docs/SECURITY.md` |
| Funktionsdesign | `docs/FEATURE_DESIGN.md` |
| Nginx-Sicherheit | `docs/nginx-security.conf` |
