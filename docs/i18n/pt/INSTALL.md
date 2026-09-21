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

O `install.sql` contém a estrutura completa das 227 tabelas e os dados de seed iniciais (papel de super administrador, árvore de permissões, estágios de funil, alíquotas de impostos, moedas, métricas de análise, categorias de documentos, permissões de interfaces de serviço); o schema tem `database/install.sql` como única fonte de verdade.

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

JWT_SECRET_KEY=altere para uma string aleatória com mais de 32 caracteres
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

## Atualizar um ambiente já implantado

Este repositório não possui ferramenta de migração: o schema e os dados de seed têm em `database/install.sql` sua única fonte de verdade, e esse arquivo é um **script de instalação de banco inteiro em uma única execução
(`INSERT` comuns, não idempotente) — não pode ser reexecutado sobre um banco em produção**. A atualização é feita manualmente, aplicando os diffs:

```bash
git diff <versão antiga>..<versão nova> -- database/install.sql    # extrai as diferenças de estrutura de tabelas e de seed
```

1. Os `CREATE TABLE` (com `IF NOT EXISTS`, podem rodar como estão) / `ALTER TABLE` / `INSERT` de seed do diff são executados em ordem no banco em produção.
2. **Seed de permissões**: as linhas de `erp_admin_permission` correspondentes aos novos endpoints precisam ser inseridas. O super administrador é exceção — ele detém a permissão curinga
   (a linha de «todas as permissões» com `slug = '*'` em `erp_admin_permission`; `app/middleware/AdminPermission.php:38`
   libera tudo ao ver `*`), portanto novos endpoints passam a valer automaticamente; papéis personalizados precisam ainda da associação:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ID do papel>, `id` FROM `erp_admin_permission` WHERE `slug` = '<slug da permissão do novo endpoint>';
   ```

3. Após a atualização, rode uma vez `curl http://localhost:8788/health` e um smoke test de login no painel administrativo para confirmar que o serviço está disponível.

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

## Lista de tabelas (227 tabelas)

| Módulo | Nº de tabelas | Nomes das tabelas |
|------|------|------|
| Administração do sistema | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| Gestão de produtos | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| Gestão de compras | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| Gestão de vendas | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| Gestão de estoque | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| Gestão financeira | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| Fluxo de aprovação | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| Notificações | 4 | notification, notification_channel_log, notification_setting, notification_template |
| Gestão de projetos | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| Recursos humanos | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| Manufatura | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| Relatórios personalizados | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| Gestão de equipamentos EAM | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| Gestão de documentos DMS | 3 | dms_category, dms_document, dms_document_version |
| Painéis BI | 2 | bi_dashboard, bi_widget |
| Gestão de pedidos OMS | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| Gestão de armazém WMS | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| Gestão de transporte TMS | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| Gestão de qualidade QMS | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| Central de membros | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| Plataforma aberta | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| Modelos de impressão | 1 | print_template |
| Tributação | 2 | tax_input_invoice, tax_issue_log |
| Base da plataforma | 3 | company, custom_field_definition, tenant |
| Canais | 1 | channel |

> Esta lista é gerada mecanicamente a partir de `database/install.sql` (2026-09-15, 227 tabelas) e adota o mesmo critério de atribuição de módulos da §20 de `docs/ARCHITECTURE.md`: **cada tabela pertence a uma única linha**, e os nomes de módulo seguem os nomes de linha da §20 — as antigas «Painel administrativo + Sistema», «Base de produtos», «Compras / Vendas / Estoque», «Base financeira + Extensão financeira» e «Base CRM + Extensão CRM» desta tabela foram respectivamente incorporadas a Administração do sistema / Gestão de produtos / Gestão de compras·Gestão de vendas·Gestão de estoque / Gestão financeira / CRM (as divisões «base / extensão» eram produtos das entregas em lotes e já foram unificadas na §20).
> As últimas 10 linhas (OMS / WMS / TMS / QMS / Central de membros / Plataforma aberta / Modelos de impressão / Tributação / Base da plataforma / Canais, 48 tabelas no total) são domínios posteriores e tabelas compartilhadas que **não entram em nenhuma linha da §20**; `company` também é reutilizada pelos relatórios consolidados financeiros, e `channel` é o dicionário de canais do OMS.
> Autoverificação (① imprime 227 linhas; ③ sem saída significa que nenhuma tabela falta e nenhuma se repete):
> ```bash
> # ① todos os nomes de tabela do install.sql (fonte única de verdade do schema)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② coluna de nomes de tabela desta lista
> sed -n '/^## Lista de tabelas/,/^---$/p' docs/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ comparação (sem saída = sem repetição e sem omissão)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
