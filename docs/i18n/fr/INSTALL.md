# Système ERP Open — Guide d'installation

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Prérequis

| Composant | Version minimale | Description |
|------|---------|------|
| PHP | 8.3+ | Extensions requises : `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Jeu de caractères utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Cache, limitation de débit, Session |
| Composer | 2.x | Gestion des dépendances PHP |
| Elasticsearch | 8.x | Optionnel, recherche plein texte |

### Vérification des extensions PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

Si des extensions manquent (Ubuntu/Debian) :
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Étapes d'installation

### 1. Créer la base de données

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Importer la base de données (une seule commande)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` contient la structure des 163 tables et les données initiales (rôle super administrateur, arbre de permissions, étapes de l'entonnoir, taux de taxe, devises, métriques d'analyse, catégories de documents, permissions des interfaces de service) ; le schéma a `database/install.sql` comme source unique de vérité.

### 3. Configurer les variables d'environnement

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

Modifiez `.env` avec les paramètres clés suivants :

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

### 4. Installer les dépendances PHP

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Démarrer le service

```bash
php start.php start
```

Écoute par défaut sur `http://0.0.0.0:8788`.

### 6. Vérifier l'installation

```bash
curl http://localhost:8788/health
```

Accédez à `http://localhost:8788/apidoc` dans le navigateur pour consulter la documentation API.

---

## Compte initial

Après l'installation, un rôle super administrateur (`super_admin`) est préconfiguré avec toutes les permissions. Pour la première utilisation, un compte administrateur doit être créé manuellement :

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> L'`id` est généré par `snowflake-php` au niveau applicatif ; il peut aussi être obtenu via l'interface d'inscription.

---

## Déploiement Docker Compose

La racine du projet orchestre 5 services : `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Remplacer les cles par des valeurs aleatoires (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Conventions de base de données

| Convention | Description |
|------|------|
| Préfixe de table | `erp_` |
| Clé primaire | `id` BIGINT UNSIGNED NOT NULL, non auto-incrémentée, générée par snowflake-php |
| Jeu de caractères | utf8mb4, utf8mb4_unicode_ci |
| Moteur | InnoDB |
| Suppression logique | `deleted_at` DATETIME DEFAULT NULL |
| Horodatage | `created_at` / `updated_at` maintenus automatiquement |
| Champs sensibles | chiffrement / déchiffrement automatiques via le trait encryptable |

---

## Liste des tables (163 tables)

| Module | Nombre de tables | Noms des tables |
|------|------|------|
| Administration | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| Système | 1 | operation_log |
| Base produits | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Achats | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Ventes | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Stocks | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Base financière | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Extension financière | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| Base CRM | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| Extension CRM | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Workflow d'approbation | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Notifications | 3 | notification, notification_template, notification_setting |
| Gestion de projets | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| Ressources humaines | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Production | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Rapports personnalisés | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| Gestion des commandes OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| Gestion d'entrepôt WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| Gestion du transport TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| Gestion de la qualité QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| Gestion des équipements EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| Gestion documentaire DMS | 3 | dms_category, dms_document, dms_document_version |
| Tableaux de bord BI | 2 | bi_dashboard, bi_widget |
| Canaux | 1 | channel |

---

## Dépannage

### Échec de connexion à la base de données
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Échec de connexion Redis
```bash
redis-cli ping    # 应返回 PONG
```

### Port déjà occupé
```bash
ss -tlnp | grep 8788
# 修改监听端口: config/server.php
```

### Permissions des fichiers
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Sauvegarde et restauration

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## Supervision

`GET /metrics` génère le format Prometheus : `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Documentation associée

| Document | Chemin |
|------|------|
| Architecture | `docs/ARCHITECTURE.md` |
| Référence API | `docs/API.md` |
| Architecture de sécurité | `docs/SECURITY.md` |
| Conception des fonctionnalités | `docs/FEATURE_DESIGN.md` |
| Sécurité Nginx | `docs/nginx-security.conf` |
