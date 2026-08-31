# Открытая ERP-система — Мастер установки

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Требования к окружению

| Компонент | Мин. версия | Описание |
|------|---------|------|
| PHP | 8.3+ | Требуемые расширения: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Кодировка utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Кэш, лимит запросов, Session |
| Composer | 2.x | Управление PHP-зависимостями |
| Elasticsearch | 8.x | Опционально, полнотекстовый поиск |

### Проверка расширений PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

При отсутствии расширений (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Шаги установки

### 1. Создание базы данных

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'ваш_пароль';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Импорт базы данных (одной командой)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` содержит структуру всех 163 таблиц и начальные seed-данные (роль суперадминистратора, дерево прав, стадии воронки, налоговые ставки, валюты, аналитические метрики, категории документов, права сервисных API); schema базы данных — единственный источник истины — `database/install.sql`.

### 3. Настройка переменных окружения

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

Отредактируйте `.env`, изменив ключевые параметры:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=ваш_пароль
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=замените_на_случайную_строку_не_короче_32_символов
APP_KEY=замените_на_случайную_строку_32_символа

# Переключатель открытой регистрации (по умолчанию 0=выкл, API возвращает 403; в продакшене держать выключенным)
REGISTRATION_ENABLED=0
```

### 4. Установка PHP-зависимостей

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Запуск сервиса

```bash
php start.php start
```

По умолчанию слушает `http://0.0.0.0:8788`.

### 6. Проверка установки

```bash
curl http://localhost:8788/health
```

Откройте `http://localhost:8788/apidoc` в браузере для просмотра документации API.

---

## Начальный аккаунт

После установки предустановлена роль суперадминистратора (`super_admin`) со всеми правами. Аккаунт администратора создаётся вручную при первом использовании:

```sql
-- Создание администратора (пароль — bcrypt-хэш)
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', 'Системный администратор', 1);

-- Привязка роли суперадминистратора
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` генерируется на прикладном уровне через `snowflake-php`; также можно получить через эндпоинт регистрации.

---

## Развёртывание Docker Compose

В корне проекта — оркестрация 5 сервисов: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Заменить ключи-заглушки случайными значениями (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# Войти в контейнер и импортировать БД
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Соглашения о базе данных

| Соглашение | Описание |
|------|------|
| Префикс таблиц | `erp_` |
| Первичный ключ | `id` BIGINT UNSIGNED NOT NULL, без автоинкремента, генерируется snowflake-php |
| Кодировка | utf8mb4, utf8mb4_unicode_ci |
| Движок | InnoDB |
| Мягкое удаление | `deleted_at` DATETIME DEFAULT NULL |
| Метки времени | `created_at` / `updated_at` поддерживаются автоматически |
| Чувствительные поля | автошифрование через trait encryptable |

---

## Список таблиц (163 таблицы)

| Модуль | Таблиц | Имена таблиц |
|------|------|------|
| Админка | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| Система | 1 | operation_log |
| Основы товаров | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Закупки | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Продажи | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Склад | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Финансы база | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Финансы расшир. | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM база | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM расшир. | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Workflow | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Уведомления | 3 | notification, notification_template, notification_setting |
| Проекты | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| HR | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Производство | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Пользовательские отчёты | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS | 3 | dms_category, dms_document, dms_document_version |
| BI-панели | 2 | bi_dashboard, bi_widget |
| Каналы | 1 | channel |

---

## Устранение неполадок

### Сбой подключения к БД
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Сбой подключения к Redis
```bash
redis-cli ping    # должно вернуть PONG
```

### Порт занят
```bash
ss -tlnp | grep 8788
# изменить порт: config/server.php
```

### Права на файлы
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Резервное копирование и восстановление

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # резервная копия (mysqldump+gzip, хранение 30 дней)
bash database/backup/restore.sh    # восстановление (интерактивное)
```

---

## Мониторинг

`GET /metrics` выводит формат Prometheus: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Связанная документация

| Документ | Путь |
|------|------|
| Архитектура | `docs/ARCHITECTURE.md` |
| Справочник API | `docs/API.md` |
| Безопасность | `docs/SECURITY.md` |
| Функциональный дизайн | `docs/FEATURE_DESIGN.md` |
| Безопасность Nginx | `docs/nginx-security.conf` |
