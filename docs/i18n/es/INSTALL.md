# Sistema ERP Abierto — Asistente de instalación

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Requisitos del entorno

| Componente | Versión mínima | Descripción |
|------|---------|------|
| PHP | 8.3+ | Extensiones requeridas: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Juego de caracteres utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Para caché, limitación de velocidad y sesiones |
| Composer | 2.x | Gestión de dependencias PHP |
| Elasticsearch | 8.x | Opcional, búsqueda de texto completo |

### Comprobación de extensiones PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

Si faltan extensiones (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Pasos de instalación

### 1. Crear la base de datos

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'tu_contraseña';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Importar la base de datos (se completa con un solo comando)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` incluye la estructura de las 227 tablas y los datos semilla iniciales (rol de superadministrador, árbol de permisos, etapas del embudo, tasas impositivas, divisas, métricas de análisis, categorías de documentos, permisos de interfaces de servicio); el esquema tiene como única fuente de verdad `database/install.sql`.

### 3. Configurar variables de entorno

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

Edite `.env` y modifique las siguientes configuraciones clave:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=tu_contraseña
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET_KEY=cambiar_por_cadena_aleatoria_de_mas_de_32_caracteres
APP_KEY=cambiar_por_cadena_aleatoria_de_32_caracteres

# Interruptor de registro abierto (por defecto 0=desactivado, la interfaz devuelve 403; en producción se recomienda mantenerlo desactivado)
REGISTRATION_ENABLED=0
```

### 4. Instalar dependencias de PHP

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Iniciar el servicio

```bash
php start.php start
```

Por defecto escucha en `http://0.0.0.0:8788`.

### 6. Verificar la instalación

```bash
curl http://localhost:8788/health
```

Visite `http://localhost:8788/apidoc` en el navegador para ver la documentación de API.

---

## Actualizar un entorno ya desplegado

Este repositorio no tiene herramienta de migración: el schema y los datos semilla tienen en `database/install.sql` su única fuente de verdad, y ese archivo es un **script de instalación de la base de datos completa
(`INSERT` normales, no idempotente) — no se puede volver a ejecutar sobre una base de datos en producción**. La actualización se hace a mano, por diferencias:

```bash
git diff <versión-antigua>..<versión-nueva> -- database/install.sql    # extrae las diferencias de estructura de tablas y datos semilla
```

1. Los `CREATE TABLE` (con `IF NOT EXISTS`, ejecutables tal cual) / `ALTER TABLE` / `INSERT` de datos semilla que aparezcan en el diff se ejecutan en orden sobre la base de datos de producción.
2. **Semillas de permisos**: hay que añadir las filas correspondientes a los nuevos endpoints en `erp_admin_permission`. El superadministrador es la excepción — posee el permiso comodín
   (la fila «todos los permisos» con `slug = '*'` en `erp_admin_permission`; `app/middleware/AdminPermission.php:38` deja pasar todo al ver `*`), por lo que los nuevos endpoints le afectan automáticamente; los roles personalizados sí necesitan añadir la relación:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ID de rol>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug del permiso del nuevo endpoint>';
   ```

3. Tras la actualización, ejecute una vez `curl http://localhost:8788/health` y una prueba de humo del inicio de sesión en la consola de administración para confirmar que el servicio está disponible.

---

## Cuenta inicial

Tras la instalación se incluye un rol de superadministrador (`super_admin`) con todos los permisos. En el primer uso debe crear manualmente la cuenta de administrador:

```sql
-- Crear administrador (la contraseña usa hash bcrypt)
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', 'Administrador del sistema', 1);

-- Vincular el rol de superadministrador
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` lo genera `snowflake-php` en la capa de aplicación; también puede obtenerse a través de la interfaz de registro.

---

## Despliegue con Docker Compose

La raíz del proyecto orquesta 5 servicios: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Reemplazar claves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# Entrar al contenedor para importar la base de datos
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Convenciones de base de datos

| Convención | Descripción |
|------|------|
| Prefijo de tablas | `erp_` |
| Clave primaria | `id` BIGINT UNSIGNED NOT NULL, no autoincremental, generada por snowflake-php |
| Juego de caracteres | utf8mb4, utf8mb4_unicode_ci |
| Motor | InnoDB |
| Borrado suave | `deleted_at` DATETIME DEFAULT NULL |
| Marcas de tiempo | `created_at` / `updated_at` mantenidas automáticamente |
| Campos sensibles | Cifrado/descifrado automático con el trait encryptable |

---

## Lista de tablas (227 tablas)

| Módulo | N.º de tablas | Nombres de tablas |
|------|------|------|
| Administración del sistema | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Gestión de productos | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Gestión de compras | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Gestión de ventas | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Gestión de inventario | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Gestión financiera | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Flujo de aprobación | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Notificaciones de mensajes | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Gestión de proyectos | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| Recursos humanos | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Producción y fabricación | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Informes personalizados | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM Gestión de equipos | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS Gestión de documentos | 3 | dms_category, dms_document, dms_document_version |
| Paneles BI | 2 | bi_dashboard, bi_widget |
| OMS Gestión de pedidos | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS Gestión de almacenes | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS Gestión de transporte | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS Gestión de calidad | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Centro de miembros | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Plataforma abierta | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Plantillas de impresión | 1 | print_template |
| Fiscalidad | 2 | tax_input_invoice, tax_issue_log |
| Base de plataforma | 3 | company, custom_field_definition, tenant |
| Canales | 1 | channel |

> Esta lista se genera mecánicamente a partir de `database/install.sql` (2026-09-15, 227 tablas); la asignación de módulos sigue el mismo criterio que §20 de `docs/ARCHITECTURE.md`: **una tabla pertenece a una sola columna**, y los nombres de módulo reutilizan los de las filas de §20 —— las agrupaciones anteriores de esta lista («Panel de administración + Sistema», «Base de productos», «Compras / Ventas / Inventario», «Base financiera + Extensión financiera», «CRM base + CRM extensión») se han fusionado respectivamente en Administración del sistema / Gestión de productos / Gestión de compras·Gestión de ventas·Gestión de inventario / Gestión financiera / CRM («base / extensión» era un artefacto de entrega por lotes; §20 ya las fusionó).
> Las 10 últimas filas (OMS / WMS / TMS / QMS / Centro de miembros / Plataforma abierta / Plantillas de impresión / Fiscalidad / Base de plataforma / Canales, 48 tablas en total) son dominios posteriores y tablas compartidas **no contabilizadas en ninguna fila de §20**; `company` también la reutiliza la consolidación financiera, y `channel` es el diccionario de canales de OMS.
> Autocomprobación (① imprime 227 líneas; ③ sin salida = ninguna tabla falta ni se repite):
> ```bash
> # ① todos los nombres de tabla de install.sql (fuente única de verdad del schema)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② la columna de nombres de tabla de esta lista
> sed -n '/^## Lista de tablas/,/^---$/p' docs/i18n/es/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ comparación (sin salida = ni duplicados ni omisiones)
> LC_ALL=C comm -3 <(①) <(②)
> ```

---

## Solución de problemas

### Fallo de conexión a la base de datos
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Fallo de conexión a Redis
```bash
redis-cli ping    # Debería devolver PONG
```

### Puerto ocupado
```bash
ss -tlnp | grep 8788
# Modificar el puerto de escucha: config/server.php
```

### Permisos de archivos
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Copia de seguridad y restauración

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # Copia de seguridad (mysqldump+gzip, retención de 30 días)
bash database/backup/restore.sh    # Restauración (interactiva)
```

---

## Monitoreo

`GET /metrics` genera formato Prometheus: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Documentación relacionada

| Documento | Ruta |
|------|------|
| Diseño de arquitectura | `docs/ARCHITECTURE.md` |
| Referencia de API | `docs/API.md` |
| Arquitectura de seguridad | `docs/SECURITY.md` |
| Diseño de funciones | `docs/FEATURE_DESIGN.md` |
| Seguridad Nginx | `docs/nginx-security.conf` |
