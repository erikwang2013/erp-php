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

`install.sql` incluye la estructura de las 163 tablas y los datos semilla iniciales (rol de superadministrador, árbol de permisos, etapas del embudo, tasas impositivas, divisas, métricas de análisis, categorías de documentos, permisos de interfaces de servicio); el esquema tiene como única fuente de verdad `database/install.sql`.

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

JWT_SECRET=cambiar_por_cadena_aleatoria_de_mas_de_32_caracteres
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

## Lista de tablas (163 tablas)

| Módulo | N.º de tablas | Nombres de tablas |
|------|------|------|
| Panel de administración | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| Sistema | 1 | operation_log |
| Base de productos | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Compras | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Ventas | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Inventario | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Base financiera | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Extensión financiera | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| Base CRM | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| Extensión CRM | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Flujo de aprobación | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Notificaciones | 3 | notification, notification_template, notification_setting |
| Gestión de proyectos | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| Recursos humanos | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Manufactura | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Informes personalizados | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| Gestión de pedidos OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| Gestión de almacén WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| Gestión de transporte TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| Gestión de calidad QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| Gestión de equipos EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| Gestión de documentos DMS | 3 | dms_category, dms_document, dms_document_version |
| Paneles BI | 2 | bi_dashboard, bi_widget |
| Canales | 1 | channel |

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
