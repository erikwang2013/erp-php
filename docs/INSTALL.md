# 开放ERP系统 — 安装向导

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 环境要求

| 组件 | 最低版本 | 说明 |
|------|---------|------|
| PHP | 8.3+ | 需启用扩展: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | 字符集 utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | 用于缓存、限流、Session |
| Composer | 2.x | PHP 依赖管理 |
| Elasticsearch | 8.x | 可选，全文检索 |

### PHP 扩展检查

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

缺少扩展时（Ubuntu/Debian）：
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## 安装步骤

### 1. 创建数据库

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. 导入数据库（一条命令完成）

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` 包含全部 163 张表的结构和初始种子数据（超级管理员角色、权限树、漏斗阶段、税率、币种、分析指标、文档分类、服务接口权限）；schema 以 database/install.sql 为唯一事实源。

### 3. 配置环境变量

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
# 生成随机密钥并写入 .env（JWT_SECRET/ENCRYPTION_KEY/HASHIDS_SALT 等，幂等；占位值会被 env_required 拒绝启动）
bash scripts/gen-env-keys.sh .env
```

编辑 `.env`，修改以下关键配置：

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

### 4. 安装PHP依赖

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. 启动服务

```bash
php start.php start
```

默认监听 `http://0.0.0.0:8787`。

### 6. 验证安装

```bash
curl http://localhost:8787/health
```

浏览器访问 `http://localhost:8787/apidoc` 查看 API 文档。

---

## 初始账号

安装后预置一个超级管理员角色（`super_admin`），拥有所有权限。首次使用需手动创建管理员账号：

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` 由 `snowflake-php` 在应用层生成，也可通过注册接口获取。

---

## Docker Compose 部署

项目根目录编排 5 个服务：`nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x)。

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# 替换占位密钥为随机值（幂等）
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## 数据库约定

| 约定 | 说明 |
|------|------|
| 表前缀 | `erp_` |
| 主键 | `id` BIGINT UNSIGNED NOT NULL，非自增，由 snowflake-php 生成 |
| 字符集 | utf8mb4, utf8mb4_unicode_ci |
| 引擎 | InnoDB |
| 软删除 | `deleted_at` DATETIME DEFAULT NULL |
| 时间戳 | `created_at` / `updated_at` 自动维护 |
| 敏感字段 | 使用 encryptable trait 自动加解密 |

---

## 表清单（163 张表）

| 模块 | 表数 | 表名 |
|------|------|------|
| 管理后台 | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| 系统 | 1 | operation_log |
| 产品基础 | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| 采购 | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| 销售 | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| 库存 | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| 财务基础 | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| 财务扩展 | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM基础 | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM扩展 | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| 审批工作流 | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| 消息通知 | 3 | notification, notification_template, notification_setting |
| 项目管理 | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| 人力资源 | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| 生产制造 | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| 自定义报表 | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS 订单管理 | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS 仓储管理 | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS 运输管理 | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS 质量管理 | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM 设备管理 | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS 文档管理 | 3 | dms_category, dms_document, dms_document_version |
| BI 看板 | 2 | bi_dashboard, bi_widget |
| 渠道 | 1 | channel |

---

## 故障排查

### 数据库连接失败
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis 连接失败
```bash
redis-cli ping    # 应返回 PONG
```

### 端口被占用
```bash
ss -tlnp | grep 8787
# 修改监听端口: config/server.php
```

### 文件权限
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## 备份与恢复

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## 监控

`GET /metrics` 输出 Prometheus 格式：`openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`。

---

## 相关文档

| 文档 | 路径 |
|------|------|
| 架构设计 | `docs/ARCHITECTURE.md` |
| API 参考 | `docs/API.md` |
| 安全架构 | `docs/SECURITY.md` |
| 功能设计 | `docs/FEATURE_DESIGN.md` |
| Nginx 安全 | `docs/nginx-security.conf` |
