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

`install.sql`은 전체 227개 테이블 구조와 초기 시드 데이터(슈퍼 관리자 역할, 권한 트리, 퍼널 단계, 세율, 통화, 분석 지표, 문서 분류, 서비스 인터페이스 권한)를 포함합니다. schema의 유일한 사실 소스는 database/install.sql입니다.

### 3. 환경 변수 설정

```bash
cd /home/wwwroot/erp-php/service
cp .env.example .env
# 무작위 키를 생성해 .env에 기록(JWT_SECRET_KEY/ENCRYPTION_KEY/HASHIDS_SALT 등, 멱등; 플레이스홀더 값은 env_required가 기동을 거부)
bash scripts/gen-env-keys.sh .env
```

`.env`를 편집하여 다음 주요 설정을 수정합니다:

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

JWT_SECRET_KEY=修改为32位以上随机字符串
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

기본적으로 `http://0.0.0.0:8788`에서 수신합니다(포트는 `.env`의 `APP_HTTP_PORT`, WebSocket은 `APP_WS_PORT`).

### 6. 설치 확인

```bash
curl http://localhost:8788/health
```

브라우저에서 `http://localhost:8788/apidoc`에 접속하여 API 문서를 확인합니다.

---

## 기존 배포 환경 업그레이드

이 저장소에는 마이그레이션 도구가 없습니다: schema와 시드는 `database/install.sql`을 유일한 사실 소스로 삼는데, 이 파일은 **데이터베이스 전체를 한 번에 설치하는 스크립트
(일반 `INSERT`이며 멱등하지 않음)이므로 실서비스 데이터베이스에서 다시 실행할 수 없습니다**. 업그레이드는 차이분을 수작업으로 적용합니다:

```bash
git diff <이전 버전>..<새 버전> -- database/install.sql    # 테이블 구조와 시드의 차이를 추출
```

1. 차이분의 `CREATE TABLE`(`IF NOT EXISTS` 포함, 그대로 실행 가능) / `ALTER TABLE` / 시드 `INSERT`를 순서대로 실서비스 데이터베이스에서 실행합니다.
2. **권한 시드**: 새 엔드포인트에 대응하는 `erp_admin_permission` 행을 추가해야 합니다. 슈퍼 관리자는 예외로, 와일드카드 권한
   (`erp_admin_permission`의 `slug = '*'`인 「전체 권한」 행, `app/middleware/AdminPermission.php:38`에서
   `*`를 보면 전부 통과)을 가지므로 새 엔드포인트가 자동으로 적용됩니다. 커스텀 역할은 연관 관계를 추가로 보완해야 합니다:

   ```sql
   INSERT INTO `erp_admin_role_permission` (`role_id`, `permission_id`)
   SELECT <역할ID>, `id` FROM `erp_admin_permission` WHERE `slug` = '<새 엔드포인트의 권한 slug>';
   ```

3. 업그레이드 후 `curl http://localhost:8788/health`와 관리단 로그인 스모크를 한 번 실행해 서비스 가용성을 확인합니다.

---

## 초기 계정

설치 후 모든 권한을 가진 슈퍼 관리자 역할(`super_admin`)이 미리 만들어집니다. 최초 사용 시 관리자 계정을 직접 생성해야 합니다:

```sql
-- 创建管理员（密码使用 bcrypt 哈希）
INSERT INTO `erp_admin_user` (`id`, `username`, `password`, `real_name`, `status`)
VALUES (90000000000000001, 'admin', '$2y$10$...', '系统管理员', 1);

-- 关联超级管理员角色
INSERT INTO `erp_admin_user_role` (`user_id`, `role_id`)
VALUES (90000000000000001, 10000000000000001);
```

> `id`는 `snowflake-php`가 애플리케이션 계층에서 생성하며, 등록 인터페이스로도 얻을 수 있습니다.

---

## Docker Compose 배포

프로젝트 루트는 5개 서비스로 오케스트레이션됩니다: `nginx`, `app` (PHP 8.3), `mysql` (8.0), `redis` (7), `elasticsearch` (8.x).

```bash
cd /home/wwwroot/erp-php
cp .env.docker .env
# 플레이스홀더 키를 랜덤 값으로 대체 (idempotent)
bash scripts/gen-env-keys.sh .env
docker-compose up -d

# 컨테이너에 들어가 데이터베이스 가져오기
docker-compose exec app bash
mysql -h mysql -u root -p erp < database/install.sql
```

---

## 데이터베이스 규약

| 규약 | 설명 |
|------|------|
| 테이블 접두사 | `erp_` |
| 기본키 | `id` BIGINT UNSIGNED NOT NULL, 비자동증가, snowflake-php가 생성 |
| 문자셋 | utf8mb4, utf8mb4_unicode_ci |
| 엔진 | InnoDB |
| 소프트 삭제 | `deleted_at` DATETIME DEFAULT NULL |
| 타임스탬프 | `created_at` / `updated_at` 자동 유지 |
| 민감 필드 | encryptable trait로 자동 암·복호화 |

---

## 테이블 목록(227개 테이블)

| 모듈 | 테이블 수 | 테이블명 |
|------|------|------|
| 시스템 관리 | 7 | admin_permission, admin_role, admin_role_permission, admin_user, admin_user_role, operation_log, system_config |
| 상품 관리 | 12 | brand, category, customer, customer_level, location, product, product_price, product_sku, product_spec, product_unit, supplier, warehouse |
| 구매 관리 | 14 | purchase_apply, purchase_apply_item, purchase_order, purchase_order_item, purchase_receive, purchase_receive_item, purchase_return, purchase_return_item, purchase_rfq, purchase_rfq_item, purchase_rfq_quote, purchase_rfq_quote_item, purchase_settlement, supplier_assessment |
| 판매 관리 | 9 | sales_delivery, sales_delivery_item, sales_order, sales_order_item, sales_quotation, sales_quotation_item, sales_return, sales_return_item, sales_settlement |
| 재고 관리 | 11 | check_detail, check_task, cost_record, inventory, inventory_alert_log, inventory_alert_rule, inventory_batch, inventory_flow, inventory_serial, transfer, transfer_item |
| 재무 관리 | 38 | finance_account, finance_allocation, finance_ar_ap, finance_asset, finance_asset_depreciation, finance_balance_sheet, finance_bank_account, finance_bank_recon_match, finance_bank_statement, finance_bill, finance_budget, finance_budget_item, finance_cash_flow, finance_cash_journal, finance_consolidation_report, finance_cost_account_config, finance_cost_center, finance_currency, finance_elimination_item, finance_exchange_rate, finance_expense, finance_general_ledger, finance_invoice, finance_invoice_item, finance_invoice_match_log, finance_ledger, finance_payment, finance_period, finance_profit, finance_profit_center, finance_receipt, finance_settlement, finance_subsidiary_ledger, finance_tax_rate, finance_tax_record, finance_voucher, finance_voucher_item, finance_voucher_source |
| CRM | 16 | crm_analytics_metric, crm_analytics_report, crm_campaign, crm_campaign_participant, crm_contact, crm_contract, crm_contract_item, crm_customer_pool_rule, crm_follow_record, crm_funnel_stage, crm_opportunity, crm_pool_record, crm_quotation, crm_quotation_item, crm_ticket, crm_ticket_reply |
| 승인 워크플로 | 4 | approval_instance, approval_node, approval_record, approval_workflow |
| 메시지 알림 | 4 | notification, notification_channel_log, notification_setting, notification_template |
| 프로젝트 관리 | 6 | project, project_cost, project_gantt, project_member, project_task, project_timesheet |
| 인사 관리 | 21 | hr_attendance, hr_attendance_rule, hr_candidate, hr_course, hr_course_enrollment, hr_department, hr_employee, hr_employee_social, hr_interview, hr_job, hr_kpi_template, hr_kpi_template_item, hr_leave, hr_offer, hr_perf_plan, hr_perf_score, hr_position, hr_salary, hr_salary_item, hr_social_rate, hr_social_rule |
| 생산 제조 | 21 | mfg_bom, mfg_bom_item, mfg_capacity_calendar, mfg_cost_entry, mfg_material_issue, mfg_material_issue_item, mfg_mrp_item, mfg_mrp_plan, mfg_order_cost, mfg_piece_wage, mfg_production_item, mfg_production_order, mfg_routing, mfg_subcontract, mfg_subcontract_issue, mfg_subcontract_issue_item, mfg_subcontract_receive, mfg_wip, mfg_wip_flow, mfg_work_report, mfg_workstation |
| 커스텀 리포트 | 5 | report_dataset, report_field, report_filter, report_schedule, report_template |
| EAM 설비 관리 | 6 | eam_equipment, eam_inspection_result, eam_inspection_task, eam_maintenance_plan, eam_repair_order, eam_spare_part |
| DMS 문서 관리 | 3 | dms_category, dms_document, dms_document_version |
| BI 대시보드 | 2 | bi_dashboard, bi_widget |
| OMS 주문 관리 | 7 | oms_fulfillment, oms_fulfillment_item, oms_inventory_reservation, oms_order, oms_order_address, oms_rma, oms_rma_item |
| WMS 창고 관리 | 12 | wms_asn, wms_asn_item, wms_location, wms_pack_task, wms_pick_item, wms_pick_task, wms_putaway_item, wms_putaway_task, wms_receiving, wms_wave, wms_wave_order, wms_zone |
| TMS 운송 관리 | 7 | tms_carrier, tms_carrier_service, tms_freight_invoice, tms_freight_rate, tms_shipment, tms_shipment_package, tms_tracking_event |
| QMS 품질 관리 | 5 | quality_inspection_standard, quality_ipqc_record, quality_iqc_record, quality_nonconformity, quality_oqc_record |
| 멤버 센터 | 7 | member, member_balance_account, member_balance_log, member_coupon, member_coupon_template, member_point_account, member_point_log |
| 오픈 플랫폼 | 3 | openapi_app, webhook_delivery_log, webhook_subscription |
| 인쇄 템플릿 | 1 | print_template |
| 세무 | 2 | tax_input_invoice, tax_issue_log |
| 플랫폼 기초 | 3 | company, custom_field_definition, tenant |
| 채널 | 1 | channel |

> 이 목록은 `database/install.sql`에서 기계 생성되었으며(2026-09-15, 227개 테이블), 모듈 귀속은 `docs/ARCHITECTURE.md` §20과 같은 기준입니다: **한 테이블은 한 행에만 귀속**되고 모듈명은 §20의 행 이름을 따릅니다 —— 이 표의 이전 「관리 백오피스 + 시스템」「상품 기초」「구매 / 판매 / 재고」「재무 기초 + 재무 확장」「CRM 기초 + CRM 확장」은 각각 시스템 관리 / 상품 관리 / 구매 관리·판매 관리·재고 관리 / 재무 관리 / CRM으로 병합되었습니다(「기초 / 확장」은 배치별 산출물이며 §20에서 이미 병합됨).
> 마지막 10개 행(OMS / WMS / TMS / QMS / 멤버 센터 / 오픈 플랫폼 / 인쇄 템플릿 / 세무 / 플랫폼 기초 / 채널, 총 48개)은 **§20 어느 행에도 포함되지 않은** 후기 도메인과 공용 테이블입니다. `company`는 재무 연결 재무제표에서도 재사용되며, `channel`은 OMS 채널 사전입니다.
> 자체 점검(① 227행 출력; ③ 출력 없음 = 누락도 중복도 없음):
> ```bash
> # ① install.sql의 전체 테이블명(schema 유일 사실 소스)
> grep -o 'CREATE TABLE IF NOT EXISTS `erp_[a-z_]*`' database/install.sql | sed 's/.*`erp_\([a-z_]*\)`/\1/' | LC_ALL=C sort
> # ② 이 목록의 테이블명 열
> sed -n '/^## 테이블 목록/,/^---$/p' docs/i18n/ko/INSTALL.md | grep '^| ' | awk -F'|' '$4 ~ /[a-z]/ {print $4}' | tr ',' '\n' | tr -d ' ' | grep . | LC_ALL=C sort
> # ③ 비교(출력 없음 = 중복·누락 없음)
> LC_ALL=C comm -3 <(①) <(②)
> ```

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
ss -tlnp | grep 8788
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
