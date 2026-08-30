# オープンERPシステム — インストールガイド

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 環境要件

| コンポーネント | 最低バージョン | 説明 |
|------|---------|------|
| PHP | 8.3+ | 必要な拡張: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | 文字セット utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | キャッシュ、レート制限、Session に使用 |
| Composer | 2.x | PHP 依存関係管理 |
| Elasticsearch | 8.x | 任意、全文検索 |

### PHP 拡張の確認

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

拡張が不足している場合（Ubuntu/Debian）：
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## インストール手順

### 1. データベースの作成

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. データベースのインポート（1 コマンドで完了）

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql` には全 163 テーブルの構造と初期シードデータ（スーパー管理者ロール、権限ツリー、ファネル段階、税率、通貨、分析指標、文書分類、サービスインターフェース権限）が含まれます。schema は database/install.sql が唯一の事実源です。

### 3. 環境変数の設定

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

`.env` を編集し、以下の主要な設定を変更します：

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

### 4. PHP 依存関係のインストール

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. サービスの起動

```bash
php start.php start
```

デフォルトでは `http://0.0.0.0:8788` で待ち受けます。

### 6. インストールの確認

```bash
curl http://localhost:8788/health
```

ブラウザで `http://localhost:8788/apidoc` にアクセスして API ドキュメントを確認します。

---

## 初期アカウント

インストール後にスーパー管理者ロール（`super_admin`）が 1 つプリセットされ、すべての権限を持ちます。初回利用時は管理者アカウントを手動で作成する必要があります：

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id` は `snowflake-php` によりアプリケーション層で生成されます。登録インターフェースから取得することもできます。

---

## Docker Compose デプロイ

プロジェクトルートで 5 サービスを構成: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x)。

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
docker-compose up -d

# 进入容器导入数据库
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## データベース規約

| 規約 | 説明 |
|------|------|
| テーブルプレフィックス | `erp_` |
| 主キー | `id` BIGINT UNSIGNED NOT NULL、非自動採番、snowflake-php で生成 |
| 文字セット | utf8mb4, utf8mb4_unicode_ci |
| エンジン | InnoDB |
| ソフトデリート | `deleted_at` DATETIME DEFAULT NULL |
| タイムスタンプ | `created_at` / `updated_at` 自動管理 |
| 機密フィールド | encryptable trait で自動暗号化・復号 |

---

## テーブル一覧（163 テーブル）

| モジュール | テーブル数 | テーブル名 |
|------|------|------|
| 管理画面 | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| システム | 1 | operation_log |
| 商品基礎 | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| 購買 | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| 販売 | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| 在庫 | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| 財務基礎 | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| 財務拡張 | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM基礎 | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM拡張 | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| 承認ワークフロー | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| メッセージ通知 | 3 | notification, notification_template, notification_setting |
| プロジェクト管理 | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| 人事管理 | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| 生産製造 | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| カスタムレポート | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS 注文管理 | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS 倉庫管理 | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS 輸送管理 | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS 品質管理 | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM 設備管理 | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS 文書管理 | 3 | dms_category, dms_document, dms_document_version |
| BI ダッシュボード | 2 | bi_dashboard, bi_widget |
| チャネル | 1 | channel |

---

## トラブルシューティング

### データベース接続失敗
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis 接続失敗
```bash
redis-cli ping    # 应返回 PONG
```

### ポートが占有されている
```bash
ss -tlnp | grep 8788
# 修改监听端口: config/server.php
```

### ファイル権限
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## バックアップと復元

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 备份（mysqldump+gzip, 30天保留）
bash database/backup/restore.sh    # 恢复（交互式）
```

---

## モニタリング

`GET /metrics` は Prometheus 形式を出力: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`。

---

## 関連ドキュメント

| ドキュメント | パス |
|------|------|
| アーキテクチャ設計 | `ARCHITECTURE.md` |
| API リファレンス | `API.md` |
| セキュリティアーキテクチャ | `SECURITY.md` |
| 機能設計 | `FEATURE_DESIGN.md` |
| Nginx セキュリティ | `docs/nginx-security.conf` |
