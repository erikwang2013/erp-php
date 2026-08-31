# Sistema ERP Aberto — Assistente de instalação

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Requisitos de ambiente

| Componente | Versão mínima | Descrição |
|------|---------|------|
| PHP | 8.3+ | Extensões necessárias: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | Charset utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | Usado para cache, rate limit e Session |
| Composer | 2.x | Gerenciamento de dependências PHP |
| Elasticsearch | 8.x | Opcional, busca de texto completo |

### Verificação das extensões PHP

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

Se faltarem extensões (Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## Passos da instalação

### 1. Criar o banco de dados

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'sua_senha';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Importar o banco de dados (um único comando)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

O `install.sql` contém a estrutura completa das 163 tabelas e os dados de seed iniciais (papel de super administrador, árvore de permissões, estágios de funil, alíquotas de impostos, moedas, métricas de análise, categorias de documentos, permissões de interfaces de serviço); o schema tem `database/install.sql` como única fonte de verdade.

### 3. Configurar variáveis de ambiente

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

Edite o `.env` e altere as seguintes configurações principais:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=sua_senha
DB_PREFIX=erp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=altere para uma string aleatória com mais de 32 caracteres
APP_KEY=altere para uma string aleatória de 32 caracteres

# Interruptor de registro aberto (padrão 0=desativado, a interface retorna 403; em produção recomenda-se manter desativado)
REGISTRATION_ENABLED=0
```

### 4. Instalar as dependências PHP

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. Iniciar o serviço

```bash
php start.php start
```

Por padrão, escuta em `http://0.0.0.0:8788`.

### 6. Verificar a instalação

```bash
curl http://localhost:8788/health
```

Acesse `http://localhost:8788/apidoc` no navegador para ver a documentação da API.

---

## Conta inicial

Após a instalação, há um papel de super administrador pré-configurado (`super_admin`) com todas as permissões. No primeiro uso, é necessário criar manualmente a conta de administrador:

```sql
-- Criar administrador (a senha usa hash bcrypt)
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', 'Administrador do sistema', 1);

-- Vincular o papel de super administrador
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> O `id` é gerado pelo `snowflake-php` na camada de aplicação; também é possível obtê-lo pela interface de registro.

---

## Implantação com Docker Compose

A raiz do projeto orquestra 5 serviços: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# Substituir chaves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# Entrar no contêiner e importar o banco de dados
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## Convenções do banco de dados

| Convenção | Descrição |
|------|------|
| Prefixo de tabela | `erp_` |
| Chave primária | `id` BIGINT UNSIGNED NOT NULL, não incremental, gerado por snowflake-php |
| Charset | utf8mb4, utf8mb4_unicode_ci |
| Engine | InnoDB |
| Soft delete | `deleted_at` DATETIME DEFAULT NULL |
| Timestamps | `created_at` / `updated_at` mantidos automaticamente |
| Campos sensíveis | criptografia/descriptografia automática via trait encryptable |

---

## Lista de tabelas (163 tabelas)

| Módulo | Nº de tabelas | Nomes das tabelas |
|------|------|------|
| Painel administrativo | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| Sistema | 1 | operation_log |
| Base de produtos | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| Compras | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| Vendas | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| Estoque | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| Base financeira | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| Extensão financeira | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| Base CRM | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| Extensão CRM | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| Fluxo de aprovação | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| Notificações | 3 | notification, notification_template, notification_setting |
| Gestão de projetos | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| Recursos humanos | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| Manufatura | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| Relatórios personalizados | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| Gestão de pedidos OMS | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| Gestão de armazém WMS | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| Gestão de transporte TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| Gestão de qualidade QMS | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| Gestão de equipamentos EAM | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| Gestão de documentos DMS | 3 | dms_category, dms_document, dms_document_version |
| Painéis BI | 2 | bi_dashboard, bi_widget |
| Canais | 1 | channel |

---

## Solução de problemas

### Falha na conexão com o banco de dados
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Falha na conexão com o Redis
```bash
redis-cli ping    # deve retornar PONG
```

### Porta em uso
```bash
ss -tlnp | grep 8788
# Alterar a porta de escuta: config/server.php
```

### Permissões de arquivo
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## Backup e restauração

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # backup (mysqldump+gzip, retenção de 30 dias)
bash database/backup/restore.sh    # restauração (interativa)
```

---

## Monitoramento

`GET /metrics` gera o formato Prometheus: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## Documentação relacionada

| Documento | Caminho |
|------|------|
| Arquitetura | `ARCHITECTURE.md` |
| Referência da API | `API.md` |
| Arquitetura de segurança | `SECURITY.md` |
| Design de funcionalidades | `FEATURE_DESIGN.md` |
| Segurança do Nginx | `nginx-security.conf` |
