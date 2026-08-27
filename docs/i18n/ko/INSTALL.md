# 오픈ERP 시스템 — 설치 가이드

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 환경 요구사항

| 구성 요소 | 최소 버전 | 설명 |
|------|---------|------|
| PHP | 8.3+ | 확장 필요: `pdo_mysql`, `redis`, `json`, `mbstring`, `openssl`, `fileinfo` |
| MySQL | 8.0+ | 문자셋 utf8mb4 / utf8mb4_unicode_ci |
| Redis | 7.0+ | 캐시, 속도 제한, Session용 |
| Composer | 2.x | PHP 의존성 관리 |
| Elasticsearch | 8.x | 선택 사항, 전문 검색 |

### PHP 확장 확인

```bash
php -m | grep -E 'pdo_mysql|redis|json|mbstring|openssl|fileinfo'
```

확장 누락 시(Ubuntu/Debian):
```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring php8.3-fileinfo
```

---

## 설치 단계

### 1. 데이터베이스 생성

```sql
CREATE DATABASE `erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `erp`.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
```

### 2. 데이터베이스 가져오기(한 번의 명령으로 완료)

```bash
cd /home/wwwroot/erp-php/service
mysql -u root -p erp < database/install.sql
```

`install.sql`은 전체 163개 테이블 구조와 초기 시드 데이터(슈퍼 관리자 역할, 권한 트리, 퍼널 단계, 세율, 통화, 분석 지표, 문서 분류, 서비스 인터페이스 권한)를 포함합니다. schema의 유일한 사실 소스는 database/install.sql입니다.

### 3. 환경 변수 설정

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
```

`.env`를 편집하여 다음 주요 설정을 수정합니다:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD=你的密码
DB_PREFIX=erik_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=修改为32位以上随机字符串
APP_KEY=修改为32位随机字符串

# 开放注册开关（默认 0=关闭，接口返回 403；生产建议保持关闭）
REGISTRATION_ENABLED=0
```

### 4. PHP 의존성 설치

```bash
cd /home/wwwroot/erp-php/service
composer install --no-dev --optimize-autoloader
```

### 5. 서비스 시작

```bash
php start.php start
```

기본적으로 `http://0.0.0.0:8787`에서 수신합니다.

### 6. 설치 확인

```bash
curl http://localhost:8787/health
```

브라우저에서 `http://localhost:8787/apidoc`에 접속하여 API 문서를 확인합니다.

---

## 초기 계정

설치 후 모든 권한을 가진 슈퍼 관리자 역할(`super_admin`)이 미리 만들어집니다. 최초 사용 시 관리자 계정을 직접 생성해야 합니다:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erik_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erik_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id`는 `snowflake-php`가 애플리케이션 계층에서 생성하며, 등록 인터페이스로도 얻을 수 있습니다.

---

## Docker Compose 배포

프로젝트 루트는 5개 서비스로 오케스트레이션됩니다: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
docker-compose up -d

# 컨테이너에 들어가 데이터베이스 가져오기
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## 데이터베이스 규약

| 규약 | 설명 |
|------|------|
| 테이블 접두사 | `erik_` |
| 기본키 | `id` BIGINT UNSIGNED NOT NULL, 비자동증가, snowflake-php가 생성 |
| 문자셋 | utf8mb4, utf8mb4_unicode_ci |
| 엔진 | InnoDB |
| 소프트 삭제 | `deleted_at` DATETIME DEFAULT NULL |
| 타임스탬프 | `created_at` / `updated_at` 자동 유지 |
| 민감 필드 | encryptable trait로 자동 암·복호화 |

---

## 테이블 목록(163개 테이블)

| 모듈 | 테이블 수 | 테이블명 |
|------|------|------|
| 관리 백오피스 | 6 | admin_user, admin_role, admin_permission, admin_user_role, admin_role_permission, system_config |
| 시스템 | 1 | operation_log |
| 상품 기초 | 11 | category, brand, product, product_sku, product_unit, product_price, warehouse, location, supplier, customer_level, customer |
| 구매 | 9 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_settlement |
| 판매 | 9 | sales_quotation, sales_quotation_item, sales_order, sales_order_item, sales_delivery, sales_delivery_item, sales_return, sales_return_item, sales_settlement |
| 재고 | 11 | inventory, inventory_batch, inventory_serial, inventory_flow, transfer, transfer_item, check_task, check_detail, inventory_alert_rule, inventory_alert_log, cost_record |
| 재무 기초 | 11 | finance_account, finance_voucher, finance_voucher_item, finance_ar_ap, finance_bank_account, finance_receipt, finance_payment, finance_settlement, finance_cash_journal, finance_expense, finance_profit |
| 재무 확장 | 15 | finance_general_ledger, finance_subsidiary_ledger, finance_balance_sheet, finance_cash_flow, finance_asset, finance_asset_depreciation, finance_tax_rate, finance_tax_record, finance_currency, finance_exchange_rate, finance_budget, finance_budget_item, finance_cost_center, finance_profit_center, finance_allocation |
| CRM 기초 | 4 | crm_funnel_stage, crm_opportunity, crm_follow_record, crm_contact |
| CRM 확장 | 12 | crm_customer_pool_rule, crm_pool_record, crm_contract, crm_contract_item, crm_quotation, crm_quotation_item, crm_campaign, crm_campaign_participant, crm_ticket, crm_ticket_reply, crm_analytics_report, crm_analytics_metric |
| 승인 워크플로 | 4 | approval_workflow, approval_node, approval_instance, approval_record |
| 메시지 알림 | 3 | notification, notification_template, notification_setting |
| 프로젝트 관리 | 5 | project, project_task, project_member, project_timesheet, project_gantt |
| 인사 관리 | 8 | hr_department, hr_position, hr_employee, hr_attendance_rule, hr_attendance, hr_leave, hr_salary, hr_salary_item |
| 생산 제조 | 8 | mfg_bom, mfg_bom_item, mfg_production_order, mfg_production_item, mfg_routing, mfg_workstation, mfg_mrp_plan, mfg_mrp_item |
| 커스텀 리포트 | 5 | report_template, report_field, report_filter, report_dataset, report_schedule |
| OMS 주문 관리 | 7 | oms_order, oms_order_address, oms_fulfillment, oms_fulfillment_item, oms_rma, oms_rma_item, oms_inventory_reservation |
| WMS 창고 관리 | 12 | wms_asn, wms_asn_item, wms_receiving, wms_putaway_task, wms_putaway_item, wms_wave, wms_wave_order, wms_pick_task, wms_pick_item, wms_pack_task, wms_zone, wms_location |
| TMS 운송 관리 | 7 | tms_carrier, tms_carrier_service, tms_freight_rate, tms_freight_invoice, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS 품질 관리 | 5 | quality_iqc_record, quality_ipqc_record, quality_oqc_record, quality_inspection_standard, quality_nonconformity |
| EAM 설비 관리 | 4 | eam_equipment, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS 문서 관리 | 3 | dms_category, dms_document, dms_document_version |
| BI 대시보드 | 2 | bi_dashboard, bi_widget |
| 채널 | 1 | channel |

---

## 문제 해결

### 데이터베이스 연결 실패
```bash
systemctl status mysql
cat service/.env | grep DB_
```

### Redis 연결 실패
```bash
redis-cli ping    # PONG이 반환되어야 함
```

### 포트 점유
```bash
ss -tlnp | grep 8787
# 수신 포트 수정: config/server.php
```

### 파일 권한
```bash
chmod -R 755 service/runtime
chown -R www-data:www-data service/runtime
```

---

## 백업과 복원

```bash
cd /home/wwwroot/erp-php/service
bash database/backup/backup.sh     # 백업(mysqldump+gzip, 30일 보존)
bash database/backup/restore.sh    # 복원(인터랙티브)
```

---

## 모니터링

`GET /metrics`가 Prometheus 형식으로 출력: `openadmin_http_requests_total`, `openadmin_active_users`, `openadmin_db_connection_status`, `openadmin_redis_connection_status`, `openadmin_memory_usage_bytes`.

---

## 관련 문서

| 문서 | 경로 |
|------|------|
| 아키텍처 설계 | `docs/ARCHITECTURE.md` |
| API 참조 | `docs/API.md` |
| 보안 아키텍처 | `docs/SECURITY.md` |
| 기능 설계 | `docs/FEATURE_DESIGN.md` |
| Nginx 보안 | `docs/nginx-security.conf` |
