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

`install.sql` содержит структуру всех 227 таблиц и начальные seed-данные (роль суперадминистратора, дерево прав, стадии воронки, налоговые ставки, валюты, аналитические метрики, категории документов, права сервисных API); schema базы данных — единственный источник истины — `database/install.sql`.

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

JWT_SECRET_KEY=замените_на_случайную_строку_не_короче_32_символов
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

## Обновление развёрнутого окружения

В репозитории нет инструмента миграций: schema и seed-данные имеют единственный источник истины — `database/install.sql`,
а это **одноразовый скрипт установки всей базы (обычные `INSERT`, не идемпотентный) — перезапускать его на рабочей базе нельзя**.
Обновление выполняется вручную по различиям:

```bash
git diff <старая-версия>..<новая-версия> -- database/install.sql    # получить различия структуры таблиц и seed-данных
```

1. Различия — `CREATE TABLE` (с `IF NOT EXISTS`, можно выполнять как есть) / `ALTER TABLE` / seed-`INSERT` — по порядку выполняются на рабочей базе.
2. **Seed-данные прав**: строки `erp_admin_permission`, соответствующие новым эндпоинтам, нужно добавить вручную. Исключение — суперадминистратор: он обладает подстановочным правом
   (строка «все права» с `slug = '*'` в `erp_admin_permission`; `app/middleware/AdminPermission.php:38`
   при виде `*` пропускает всё), поэтому новые эндпоинты для него действуют сразу; пользовательским ролям нужно дополнительно добавить связь:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ID-роли>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug права нового эндпоинта>';
   ```

3. После обновления один раз выполните `curl http://localhost:8788/health` и smoke-проверку входа в панель управления, чтобы убедиться в работоспособности сервиса.

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

## Список таблиц (227 таблиц)

| Модуль | Таблиц | Имена таблиц |
|------|------|------|
| Системное администрирование | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Управление товарами | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Управление закупками | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Управление продажами | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Управление складом | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Управление финансами | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Согласование рабочих процессов | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Уведомления | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Управление проектами | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| HR | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Производство | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Пользовательские отчёты | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM (управление оборудованием) | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS (управление документами) | 3 | dms_category, dms_document, dms_document_version |
| BI-дашборды | 2 | bi_dashboard, bi_widget |
| OMS (управление заказами) | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS (управление складом) | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS (управление транспортом) | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS (управление качеством) | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Центр участников программы лояльности | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Открытая платформа | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Шаблоны печати | 1 | print_template |
| Налоги | 2 | tax_input_invoice, tax_issue_log |
| Базовая платформа | 3 | company, custom_field_definition, tenant |
| Каналы | 1 | channel |

> Данный список механически сгенерирован из `database/install.sql` (2026-09-15, 227 таблиц); принадлежность модулям — по тому же критерию, что и в §20 `docs/ARCHITECTURE.md`: **одна таблица входит только в одну строку**, названия модулей взяты из строк §20 — прежние группы этого списка («Админка + Система», «Основы товаров», «Закупки / Продажи / Склад», «Финансы база + Финансы расшир.», «CRM база + CRM расшир.») соответственно объединены в Системное администрирование / Управление товарами / Управление закупками·Управление продажами·Управление складом / Управление финансами / CRM (деление на «базу / расширение» было следствием поэтапной поставки, в §20 оно уже объединено).
> Последние 10 строк (OMS / WMS / TMS / QMS / Центр участников программы лояльности / Открытая платформа / Шаблоны печати / Налоги / Базовая платформа / Каналы — всего 48 таблиц) — это поздние домены и общие таблицы, **не входящие ни в одну строку §20**; `company` одновременно используется консолидированной отчётностью финансов, `channel` — справочник каналов OMS.
> Самопроверка (① выводит 227 строк; ③ без вывода — ни одна таблица не пропущена и не задублирована):
> ```bash
> # ① полный список имён таблиц из install.sql (единственный источник истины schema)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② столбец имён таблиц этого списка
> sed -n '/^## Список таблиц/,/^---$/p' docs/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ сравнение (без вывода = нет дублей и пропусков)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
