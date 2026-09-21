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

`install.sql` contient la structure des 227 tables et les données initiales (rôle super administrateur, arbre de permissions, étapes de l'entonnoir, taux de taxe, devises, métriques d'analyse, catégories de documents, permissions des interfaces de service) ; le schéma a `database/install.sql` comme source unique de vérité.

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

## Mise à niveau d'un environnement déjà déployé

Ce dépôt n'a pas d'outil de migration : le schéma et les données initiales ont `database/install.sql` comme
unique source de vérité, et ce fichier est un **script d'installation complet de la base
(`INSERT` ordinaires, non idempotent) — il ne doit pas être réexécuté sur une base en production**. La mise à
niveau s'effectue manuellement, par différences :

```bash
git diff <ancienne version>..<nouvelle version> -- database/install.sql    # extraire les différences de structure et de données initiales
```

1. Exécuter dans l'ordre sur la base en production les `CREATE TABLE` (avec `IF NOT EXISTS`, exécutables tels quels) / `ALTER TABLE` / `INSERT` de données initiales présents dans la différence.
2. **Données initiales de permissions** : les lignes `erp_admin_permission` correspondant aux nouveaux points de terminaison doivent être ajoutées. Le super administrateur fait exception — il détient une permission
   générique (la ligne « toutes les permissions » avec `slug = '*'` dans `erp_admin_permission`, voir
   `app/middleware/AdminPermission.php:38` qui laisse tout passer dès qu'il voit `*`), les nouveaux points de
   terminaison sont donc actifs automatiquement ; les rôles personnalisés nécessitent en revanche d'ajouter les
   associations :

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ID du rôle>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug de permission du nouveau point de terminaison>';
   ```

3. Après la mise à niveau, lancer une fois `curl http://localhost:8788/health` et un test de connexion à la console d'administration pour confirmer que le service est disponible.

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

## Liste des tables (227 tables)

| Module | Nombre de tables | Noms des tables |
|------|------|------|
| Administration système | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Gestion des produits | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Gestion des achats | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Gestion des ventes | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Gestion des stocks | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Gestion financière | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Workflow d'approbation | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Notifications | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Gestion de projets | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| Ressources humaines | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Production | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Rapports personnalisés | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM Gestion des équipements | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS Gestion documentaire | 3 | dms_category, dms_document, dms_document_version |
| Tableaux de bord BI | 2 | bi_dashboard, bi_widget |
| OMS Gestion des commandes | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS Gestion d'entrepôt | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS Gestion du transport | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS Gestion de la qualité | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Centre de membres | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Plateforme ouverte | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Modèles d'impression | 1 | print_template |
| Fiscalité | 2 | tax_input_invoice, tax_issue_log |
| Base de plateforme | 3 | company, custom_field_definition, tenant |
| Canaux | 1 | channel |

> Cette liste est générée mécaniquement à partir de `database/install.sql` (2026-09-15, 227 tables), le rattachement des modules suit la même convention que §20 de `docs/ARCHITECTURE.md` : **une table n'appartient qu'à une seule colonne**, les noms de modules reprennent ceux des lignes de §20 —— les regroupements précédents de cette liste (« Console d'administration + Système », « Base produits », « Achats / Ventes / Stocks », « Base financière + Extension financière », « CRM base + CRM extension ») ont été fusionnés respectivement dans Administration système / Gestion des produits / Gestion des achats·Gestion des ventes·Gestion des stocks / Gestion financière / CRM (« base / extension » était un artefact de livraison par lots, §20 les a fusionnés).
> Les 10 dernières lignes (OMS / WMS / TMS / QMS / Centre de membres / Plateforme ouverte / Modèles d'impression / Fiscalité / Base de plateforme / Canaux, soit 48 tables) sont des domaines tardifs et des tables partagées **non comptés dans une quelconque ligne de §20** ; `company` est également réutilisée par la consolidation financière, `channel` est le dictionnaire de canaux OMS.
> Auto-vérification (① affiche 227 lignes ; ③ sans sortie = aucune table manquante ni en double) :
> ```bash
> # ① tous les noms de tables de install.sql (source unique de vérité du schéma)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② la colonne des noms de tables de cette liste
> sed -n '/^## Liste des tables/,/^---$/p' docs/i18n/fr/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ comparaison (aucune sortie = ni doublon ni omission)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
