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

`install.sql` には全 227 テーブルの構造と初期シードデータ（スーパー管理者ロール、権限ツリー、ファネル段階、税率、通貨、分析指標、文書分類、サービスインターフェース権限）が含まれます。schema は database/install.sql が唯一の事実源です。

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

## 既にデプロイ済みの環境のアップグレード

本リポジトリにマイグレーションツールはありません：schema とシードは `database/install.sql` が唯一の事実源ですが、これは**データベース全体を一括インストールするスクリプト（通常の `INSERT` で、冪等ではありません）——稼働中のデータベースに対して再実行してはいけません**。アップグレードは差分を手作業で適用します：

```bash
git diff <旧バージョン>..<新バージョン> -- database/install.sql    # テーブル構造とシードの差分を取り出す
```

1. 差分のうち `CREATE TABLE`（`IF NOT EXISTS` 付きなのでそのまま実行可能）/ `ALTER TABLE` / シードの `INSERT` を、順に稼働中のデータベースへ実行します。
2. **権限シード**：新しいエンドポイントに対応する `erp_admin_permission` の行を追加登録する必要があります。スーパー管理者は例外です——ワイルドカード権限
   （`erp_admin_permission` の `slug = '*'` の「全権限」行。`app/middleware/AdminPermission.php:38` で `*` を見れば全許可）
   を保持しているため、新しいエンドポイントは自動的に有効になります。カスタムロールでは関連付けを追加登録してください：

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <ロールID>, `id` FROM `erp_admin_permission` WHERE `slug` = '<新エンドポイントの権限 slug>';
   ```

3. アップグレード後に `curl http://localhost:8788/health` と管理画面のログインスモークを一度実行し、サービスが利用可能であることを確認します。

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
# プレースホルダ鍵をランダム値に置換（idempotent）
bash scripts/gen-env-keys.sh .env
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

## テーブル一覧（227 テーブル）

| モジュール | テーブル数 | テーブル名 |
|------|------|------|
| システム管理 | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| 商品管理 | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| 購買管理 | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| 販売 | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| 在庫 | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| 財務管理 | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| 承認ワークフロー | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| メッセージ通知 | 4 | notification, notification_channel_log, notification_setting, notification_template |
| プロジェクト管理 | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| 人事管理 | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| 生産製造 | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| カスタムレポート | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS 注文管理 | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS 倉庫管理 | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS 輸送管理 | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS 品質管理 | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM 設備管理 | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS 文書管理 | 3 | dms_category, dms_document, dms_document_version |
| BI ダッシュボード | 2 | bi_dashboard, bi_widget |
| 会員センター | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| オープンプラットフォーム | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| 印刷テンプレート | 1 | print_template |
| 税務 | 2 | tax_input_invoice, tax_issue_log |
| プラットフォーム基礎 | 3 | company, custom_field_definition, tenant |
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
