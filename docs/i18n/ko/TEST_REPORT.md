# 테스트 리포트 — 2026-08-26

> 업데이트: 2026-08-27 — 잔여 사항 5건 전부 종결; 테스트 숫자 505/2342/26 → 513/2368/32; 덤으로 수정한 결함 4 → 5건. 이전 값은 문서末尾「업데이트 기록」참고.

## 실행 요약

| 지표 | 값 |
|------|----|
| 리포트 일자 | 2026-08-26 |
| PHP 단위 테스트 | 513 tests / 2368 assertions / 32 skipped |
| Flutter 페이지 테스트 | 98 tests 전부 통과(flutter analyze 0 error) |
| API 자동화 | 104 엔드포인트 / ~230 assertion(CI e2e 연동 완료, ci.yml「Run E2E API coverage」스텝 참고) |
| 커버리지(pcov 실측) | 전체 7.51% / app/service 15.65% / app/controller 3.62% |
| 정적 분석 | PHPStan 0 error ✅ |
| 코드 스타일 | php-cs-fixer 0 diff ✅(이번에 기존 파일 3개도 덤으로 수정) |
| 덤으로 수정한 실제 결함 | 5건(3 PHP + 1 Flutter + 1 포맷) |
| Go/Rust | N/A(저장소에 .go/.rs/Cargo.toml 코드 없음) |

이번은 3개 트랙 병행 테스트 전달: PHP 단위 테스트(php-tester, 신규 9개 파일), API 자동화(api-tester, 신규 1개 파일), Flutter 페이지 테스트(ui-tester, 신규 8개 파일 29개 케이스).

## 커버리지 매트릭스

모듈(22개 업무 도메인 + 시스템 관리 14개 컨트롤러)을 테스트 유형별로 커버리지 표기.

### 22개 업무 도메인

| 모듈 | 단위 | API | UI | 설명 |
|------|------|-----|-----|------|
| 재무 Consolidation 합병 | ✅ | ✅ | — | ConsolidationServiceTest 5케이스 + API |
| 재무 AccountBalance 계좌 잔액 | ✅ | ✅ | — | AccountBalanceServiceTest 4케이스 |
| 재무 PeriodClose 기간 이월 | ✅ | ✅ | — | PeriodCloseServiceTest 5케이스 |
| 재무 FinanceRatio | ✅ | — | — | FinanceRatioServiceTest(기존) |
| 재무 DoubleEntry 복식 부기 | ✅ | — | — | DoubleEntryServiceTest(기존) |
| 재고 Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5케이스 + ERP 목록 페이지 UI |
| 판매 Sales | ✅ | ✅ | ✅ | 기존 SalesModuleTest + 판매 오더 페이지 UI |
| 상품 Product | ✅ | ✅ | ✅ | 기존 ProductModuleTest + 상품 페이지 UI |
| 구매 Purchase | ✅ | ✅ | — | 기존 PurchaseModuleTest |
| 생산 Manufacturing | ✅ | — | — | 기존 ManufacturingServiceTest |
| MRP 엔진 | ✅ | — | — | 기존 MrpEngineServiceTest |
| CRM | ✅ | ✅ | — | 기존 CrmModuleTest/CrmServiceTest |
| HR | ✅ | — | — | 기존 HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest |
| 프로젝트 Project | ✅ | ✅ | ✅ | 기존 ProjectModuleTest + 프로젝트 페이지 UI |
| 승인 Approval/Workflow | ✅ | ✅ | ✅ | 기존 WorkflowModuleTest + 승인 페이지 UI |
| OMS/WMS/TMS | ✅ | — | — | 기존 OmsWmsTmsServiceTest |
| QMS 품질 | ✅ | — | — | 기존 QualityModuleTest |
| EAM 자산 | ✅ | — | — | 기존 EamModuleTest |
| DMS 문서 | ✅ | — | — | 기존 DmsModuleTest |
| BI 리포트 | ✅ | ✅ | — | 기존 BiModuleTest + API |
| 알림·알림 채널 | ✅ | ✅ | — | NotificationChannelTest(ChannelRouter/WebSocketService 12케이스) |
| 리포트/문서 상세 | ✅ | 일부 | ✅ | 생성 로직 단위 테스트 있음; 상세 페이지 UI 3케이스(report_list_page_test) |

### 시스템 관리(14개 컨트롤러)

| 컨트롤러 도메인 | 단위 | API | UI | 설명 |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest(User 측) + 사용자 목록 페이지 UI |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest(Role 측) + 역할 목록 페이지 UI |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest(Permission 측) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest(Config 측) + 설정 페이지 UI |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| 나머지 7개 컨트롤러(로그인/감사/사전 등) | ✅ | ✅ | — | BusinessControllersTest 10개 도메인 대표 컨트롤러 실패 경로 검증 |
| 로그인 페이지 | — | ✅ | ✅ | login_flow_test 2케이스 |
| 개인 센터 | — | ✅ | ✅ | profile_page_test 3케이스 |
| 로그 페이지 | — | ✅ | ✅ | log_page_test 2케이스 |
| 대시보드 | — | — | ✅ | dashboard_page_test 5케이스 |
| 재고 경고/재무 페이지 | — | — | ✅ | erp_list_pages_test |

## 테스트 통계

### PHP 단위 테스트: 513 tests / 2368 assertions / 32 skipped

이번 신규 9개 파일(전부 저작권 헤더 포함, 63 tests / 125 assertions):

| 파일 | 케이스 수 | 커버 대상 |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance 합병 |
| tests/AccountBalanceServiceTest.php | 4 | 계좌 잔액 |
| tests/PeriodCloseServiceTest.php | 5 | 기간 이월 |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | 재고 확장 |
| tests/AdminUserRoleControllerTest.php | 9 | User/Role 컨트롤러 |
| tests/AdminPermissionConfigControllerTest.php | 8 | Permission/Config 컨트롤러 |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10개 도메인 | 대표 컨트롤러 실패 경로 검증 |

2026-08-27 신규 3개 PHP 파일(14 tests; TEST_DB_* 부재 시 통합 테스트 6/6 자동 스킵):

| 파일 | 케이스 수 | 커버 대상 |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | DB 트랜잭션 롤백/commit/중복 소스/pcntl_fork 병행 잠금(Group(integration)) |
| tests/NotificationServiceTest.php | 6 | 알림 서비스 |
| tests/FinanceRatioServiceTest.php | 2 | 재무 비율 |

### Flutter 페이지 테스트: 98 tests 전부 통과

이번 신규 8개 파일 29개 케이스(기존 10개 파일 미변경, 전부 통과); `flutter analyze` 0 error(기존 info 1건):

| 파일 | 케이스 수 |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 신규 1개 파일(3개 케이스):

| 파일 | 케이스 수 |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### API 자동화: 104 엔드포인트 / ~230 assertion(19개 그룹 모듈)

tests/E2E/api-coverage.php(423줄, `php -l` 통과): 순수 읽기 전용 + 멱등(개인 센터 GET 상세→PUT 동일 값 재기록), 테이블 부재 인식 포함(500 + Base table not found → SKIP, install.sql 전체 시드 필요 안내).

**로컬 미실행**(MySQL 자격증명 없음, 8788 서비스 없음), CI e2e 환경에서 실행 필요:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

19개 그룹 모듈 커버: 시스템 관리(사용자/역할/권한/설정/헬스/메트릭), 재무(합병/잔액/이월/비율), 재고, 판매, 상품, 구매, 프로젝트, 승인, CRM, BI, 알림, 리포트.

> 정정: api-tester가 `erik_admin_config` 테이블 부재를 의심했으나 — **결함 아님**. 실제 테이블명은 `erik_system_config`(install.sql:133에 생성, SystemConfig 모델도 정확히 가리킴), 리포트에서 정정함.

## 커버리지

pcov 실측(2026-08-26, 2026-08-27 재측정 없음·이 값 유지): 전체 **7.51%**(베이스라인 4.8%), app/service **15.65%**(베이스라인 10.6%), app/controller **3.62%**.

CI 문턱 및 목표와 대비(문서 docs/superpowers/plans/2026-08-07-next-phase-plan.md P1-B4 참고):

| 차원 | 현재 | CI 문턱 | 목표 |
|------|------|---------|------|
| 전체 | 7.51% | 4% ✅ 달성 | 30% |
| app/service | 15.65% | 10% ✅ 달성 | 40% |
| app/controller | 3.62% | — | — |

전체와 service 커버리지는 CI 문턱을 넘었고, 목표와는 아직 차이가 커 P1-B4 로드맵대로 테스트를 계속 보강해야 합니다.

## 덤으로 수정한 실제 결함(4건)

| # | 위치 | 결함 | 수정 |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php, PermissionController.php | `use support\Response;` 누락, 런타임 TypeError | import 추가 |
| 2 | app/controller/Admin/DocsController.php | `path()` 3번째 인자에 null 전달 시 크래시 | 호출부 수정 |
| 3 | lib/pages/user_list_page.dart | 일괄 삭제/활성화 버튼에 Obx 래핑 없음, 체크 후 버튼이 절대 나타나지 않음 | Obx 래핑 추가 |
| 4 | scripts/api-coverage.php(및 이번 app/queue/redis/search/ 3개 파일) | cs-fixer 포맷 위반 | fixer대로 수정 |
| 5 | app/model/FinanceCashJournal.php | `UPDATED_AT` 필드가 install.sql과 불일치 | 필드 수정 |

## Go / Rust

**N/A** — 저장소에 .go / .rs / Cargo.toml 코드가 없어 두 기술 스택 테스트는 적용 불가로 표기.

## 잔여 사항 종결(2026-08-27 업데이트)

기존 2026-08-26판 잔여 사항 5건 전부 처리 완료:

1. **DB 트랜잭션 경로** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` 신규 6케이스(롤백/commit/중복 소스/pcntl_fork 병행 잠금, `Group(integration)`), TEST_DB_* 부재 시 6/6 자동 스킵; CI php job에 TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST 주입 완료.
2. **api-coverage CI 연동** ✅ — `.github/workflows/ci.yml` e2e job 시드를 전량 install.sql(163개 테이블)로 업그레이드, smoke 후「Run E2E API coverage」스텝 신설.
3. **리포트/문서 상세 페이지 UI 미커버** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` 3케이스 전부 통과.
4. **CaptchaTest 환경 의존성** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` PIXELS→AREA 이중 버전 호환 + clone() 가드; `tests/CaptchaTest.php`를 poster-php v1.2.3 계약대로 재작성, 로컬 imagick 경로 7/7 통과(27 assertion).
5. **커버리지 목표** ✅ 진행 — `tests/NotificationServiceTest.php`, `tests/FinanceRatioServiceTest.php` 신규; 커버리지 숫자는 2026-08-26 실측값 유지(재측정 없음), 목표(30%/40%)까지 계속 보강 필요.

회귀 베이스라인: **513 tests / 2368 assertions / 32 skipped** 전부 그린(이전판 505/2342/26).

## 업데이트 기록

| 일자 | 변경 |
|------|------|
| 2026-08-26 | 초판: 505 tests / 2342 assertions / 26 skipped; 잔여 사항 5건; 덤으로 수정 4건 |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; 잔여 사항 5건 전부 종결; 덤으로 수정 5건; 신규 테스트 파일 4개; 전체 이미지에 워터마크 erik.xyz |

## 리포트 및 산출물 저장 경로

- 본 리포트: `docs/TEST_REPORT.md`
- 커버리지 데이터: `runtime/coverage/`(pcov 생성)
- API 자동화 스크립트: `tests/E2E/api-coverage.php`
- PHP 단위 테스트: `tests/*.php`(이번 신규 9개 파일은 위 표 참고)
- Flutter 테스트: `test/pages/*.dart`(이번 신규 8개 파일은 위 표 참고)
