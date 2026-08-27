# 차기 단계(P4 / 진화기 1.1) 프로젝트 계획

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 작성: 시스템 아키텍트 ｜ 일자: 2026-08-07 ｜ 근거: 3건의 사전 조사(계획과 격차 / 백엔드와 품질 / 프론트엔드) + 현장 표본 점검 재확인
> 상태: 초안(심사 대기) ｜ 목표 버전: 1.1(진화기)

---

## 1. 단계 포지셔닝

P0~P3 로드맵이 모두 납품 완료: 22개 업무 모듈, 163개 테이블, 121개 컨트롤러, 24개 서비스, 161개 모델, 12개 미들웨어;
Flutter 96페이지 + HarmonyOS 34페이지; 종합 점수 89/100. **본 단계에서는 신규 업무 도메인을 추가하지 않습니다.** 대신 「이미 구현했지만 닫히지 않은」
능력을 보완하고, 품질 부채를 정리하며, 문서 표류를 제거하여 장기적으로 유지 가능한 **1.1 진화 버전**을 생산합니다.

세 가지 핵심 판단(모두 표본 점검으로 확인됨):

1. **대량의 능력이 「존재하지만 미작동」**: TenantScope 미들웨어와 모델 trait가 `config/middleware.php`에 등록되지 않음(멀티테넌트는 껍데기);
   큐는 redis/rabbitmq 이중 드라이버를 구성했지만 `config/process.php`에 소비 프로세스가 없음; WebSocket 연결이 JWT를 검증하지 않음;
   Flutter 대시보드 OMS/WMS/TMS 통계는 하드코딩된 가짜 값이며, 백엔드 `/dashboard/oms|wms|tms` 엔드포인트는 이미 존재하는데 호출되지 않음;
   프론트엔드가 존재하지 않는 알림 엔드포인트 `/admin/notification/my/read` 호출(백엔드 실은 `/admin/notification/read-all`).
2. **품질과 보안 미결제**: 11개 업무 모듈 테스트 0건; PHPStan level 5지만 베이스라인이 974개 오류 억제; 137개 테스트가 전부 순수 단위 테스트로 통합/E2E/커버리지 없음;
   `.env.docker`에 약한 키 대량 존재; CI는 PHP 작업뿐, 프론트엔드 품질 게이트 없음.
3. **문서 체계적 표류**: 테스트 수 132/779→135/799→137/805 3개 버전 불일치; FUNCTIONS.md 부록과 실측 차이 큼;
   EDITIONS.md 숫자 자가모순; lite/standard/full 3개 브랜치가 main에 20~41 커밋 뒤처짐.

**원칙**: 먼저 「이미 구현했지만 닫히지 않은」 것(죽은 엔드포인트, 배선 안 된 TenantScope/큐, mock 대시보드)을 보완하고, 그다음 테스트와 품질 게이트, 그다음 구조와 문서 최적화.
모든 작업은 작고 명확하며 단일 agent 세션 내에서 완료 가능; 확신이 없는 것은 「검증 필요」로 표시.

---

## 2. 격차 분석(요약)

3건의 조사 격차를 **6개 작업 그룹**으로 귀결. 각 항목에 증거 경로를 제시.

### 작업 그룹 A: 업무 루프 보완(우선순위 최고)

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| A1 | 알림 「전체 읽음 표시」 프론트엔드가 존재하지 않는 엔드포인트 호출 | `apps/flutter/lib/app/pages/notification/notification_page.dart:43`이 `/admin/notification/my/read` 호출; 백엔드 라우트는 `config/route.php:250`의 `POST /admin/notification/read-all` | 확인됨 |
| A2 | 대시보드 OMS/WMS/TMS 통계가 mock 가짜 값이고 요청에 JWT 없음 | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`(독립 Dio `baseUrl: http://localhost:8787`, 인터셉터 없음; `omsStats/wmsStats/tmsStats` 하드코딩; 주석 "Mock values for now"); 백엔드 실제 엔드포인트 `config/route.php:231-233` | 확인됨 |
| A3 | TenantScope 미들웨어와 모델 trait 배선 안 됨, 멀티테넌트는 껍데기 | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` 존재; `config/middleware.php` 전역 체인은 Locale/Cors/SecurityFilter/RateLimit/TracingId만 등록, route.php 각 그룹에도 참조 없음 | 확인됨 |
| A4 | 큐 이중 드라이버지만 소비 프로세스 없음, 엔드투엔드 미작동 | `config/queue.php`(기본 redis, 선택 rabbitmq); `config/process.php`는 webman/socket/monitor 3개 프로세스뿐 | 확인됨 |
| A5 | WebSocket 무인증 | `app/process/WebSocket.php:23` 주석 "could validate JWT here"; `:47-50` auth 메시지가 바로 success:true 반환, token 미검증 | 확인됨 |
| A6 | HarmonyOS 25개 목록 페이지 페이징 파라미터 무효(단일 인용부호 안 `${this.page}` 미보간) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24`(표본 점검); 추가 24곳 동일 패턴 | 확인됨(목록 전량 대조 필요) |
| A7 | 업무 액션 엔드포인트 다수가 프론트엔드 미연결(정산/3대 재무제표/이행/승인/급여 계산 등) | 커버리지 매트릭스 조사 결론; 예: 구매/판매 정산 페이지 부재, 재무 13개 엔드포인트 부재, CRM follow/funnel/계약 유통 부재 | 검증 필요(모듈별 목록 대조 필요) |
| A8 | 다수 업무 페이지 폼이 공통 name/code 필드뿐 | 조사 결론(판매 오더/전표 생성 시 이름·코드만 입력) | 검증 필요(페이지별 대조 필요) |

### 작업 그룹 B: 테스트 체계 재구축

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| B1 | 11개 업무 모듈 테스트 0건: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | `tests/` 19개 테스트 파일이 admin/finance/inventory/oms/wms/tms/notification/hr/mrp/보안 기반 클래스만 커버; 위 11개 모듈에는 전용 테스트 파일 없음 — 그중 crm/eam/dms/quality/report/workflow 6개 모듈은 어떤 테스트 파일에서도 **언급 0건**; project/purchase/sales/product/bi는 공통 기반 클래스 테스트나 인접 모듈 테스트에 우연히 참조될 뿐(ControllerPatternTest 패턴 샘플링, bootstrap.php 라우트 목록, InventoryServiceTest의 purchase/product 입고 맥락 언급, DoubleEntryServiceTest에서 "bi"는 debit_amount 부분 문자열), 전용 커버리지 아님 | 확인됨 |
| B2 | 통합/E2E/커버리지 없음; 137 tests / 805 assertions 전부 순수 단위 테스트(실측 1.2초 내 완료, 순수 메모리) | `vendor/bin/phpunit` 실측 "OK (137 tests, 805 assertions)" | 확인됨 |
| B3 | PHPStan level 5지만 베이스라인이 974개 오류 억제 | `phpstan-baseline.neon` 실측 974개 message 노드 | 확인됨 |
| B4 | CI 커버리지 수집 없음, 통합 테스트 작업 없음 | `.github/workflows/ci.yml`(PHP 8.2/8.3/8.4 × mysql8/redis7, composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit뿐) | 확인됨 |
| B5 | purchase/sales 컨트롤러가 서비스 하드코딩 의존 | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143`(두 파일 모두 `use` 선언 :15-16, `new InventoryService()/new FinanceService()` 인스턴스화 :142-143) | 확인됨 |

### 작업 그룹 C: 인프라와 보안 정리

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| C1 | `.env.docker` 약한 키 | `JWT_SECRET_KEY=change-me-...`, `ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`, `DB_PASSWORD=root`, `ES_PASSWORD=changeme`, `RABBITMQ_PASSWORD=guest`(.env.docker:15,32,37,51,67,81) | 확인됨 |
| C2 | 환경 변수 강제 검증 불완전 | 조사: ENCRYPTION_KEY만 env_required 경유 | 검증 필요(config/jwt.php, encryption.php 대조) |
| C3 | fail-open 조용한 오류 삼킴 | 조사 결론; 범위는 감사 필요(빈 try/catch, catch 로그 없음) | 검증 필요(grep 감사 필요) |
| C4 | backup-validator.sh와 마이그레이션별 `_rollback.sql` 부재 | `find` 전체 저장소 매칭 없음; `database/migrations/` 29개 SQL 마이그레이션 모두 대응 롤백 파일 없음 | 확인됨 |
| C5 | 알림 채널 stub(email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | 확인됨 |
| C6 | 모니터링 공백: 큐 적체/WebSocket 연결 수 지표 없음 | `app/admin/controller/MetricsController.php` 기존 gauge 5개 | 부분 확인 |

### 작업 그룹 D: 버전 매트릭스와 문서 정리

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| D1 | lite/standard/full 브랜치가 main에 20~41 커밋 뒤처짐 | `git rev-list --left-right --count main...lite|standard|full` 실측: 41/41/20 behind, lite/standard는 각각 6~7개 ahead 고유 커밋 | 확인됨 |
| D2 | EDITIONS.md 숫자 자가모순 | 개요 표: 컨트롤러 48/42/70, 업무 모듈 6/6/12; 업그레이드 경로 문단에는 12/12/19 모듈, 163 테이블로 표기; 실측 121 컨트롤러와 불일치 | 확인됨 |
| D3 | FUNCTIONS.md 부록 표류 | 부록에 11개 파일/90개 메서드/168개 단언/9개 미들웨어/22개 마이그레이션 표기; 실측 19~20개 파일/137개 테스트/805개 단언/12개 미들웨어/29개 마이그레이션 | 확인됨 |
| D4 | 테스트 수 3개 버전 표류(132/779→135/799→137/805) | 문서 이력과 git 커밋 기록 | 확인됨 |
| D5 | 완료도 매트릭스가 QMS/EAM/DMS/BI를 🔴로 표기하지만 코드는 이미 존재 | `docs/FUNCTIONS.md:555` 부근 매트릭스 vs `app/controller/{quality,eam,dms,bi}/` 이미 구현 | 확인됨 |
| D6 | 컨트롤러 구경 혼란: docs/CLAUDE.md에 "업무 컨트롤러 104개" 표기, 실측 전체 122 | `find app -path '*/controller/*.php' | wc -l` = 122(admin 14 + api 3 + 업무 104 + Index/Install 포함); 조사 구경 121 | 확인됨(구경 차이) |
| D7 | 마이그레이션 수 구경: 조사 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29(000030까지 번호, 000007/000008 부재) | 확인됨(29가 실측) |

### 작업 그룹 E: 프론트엔드 품질과 정렬

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| E1 | CI에 flutter analyze/test/build 없음, hvigor 빌드 없음 | `.github/workflows/ci.yml` PHP 작업뿐 | 확인됨 |
| E2 | README가 CI에 Flutter 정적 분석 포함 주장, 사실과 불일치 | `README.md:635` "Flutter 정적 분석 (flutter analyze)" vs ci.yml에 해당 단계 없음 | 확인됨 |
| E3 | Flutter 테스트 1건 스모크뿐 | `apps/flutter/test/widget_test.dart`가 유일한 테스트 파일 | 확인됨 |
| E4 | HarmonyOS token 미영속화(AppStorage가 메모리뿐, 콜드 스타트 시 로그인 페이지 복귀) | 조사 결론(`apps/harmonyos/entry/src/main/ets/service/ApiService.ets` 대조 필요) | 검증 필요 |
| E5 | HarmonyOS 25개 페이지 템플릿화, name/code 읽기 전용 목록에 증감삭제 없음 | OrderListPage.ets 전체 65줄 표본 점검: name/code 읽기 전용 목록뿐 | 확인됨 |
| E6 | 프론트엔드 커버리지 깊이 부족(A7/A8 참조) | 상동 | 검증 필요 |

### 작업 그룹 F: API 계층화와 아키텍처 정리(낮은 우선순위, 역량 내에서)

| # | 격차 | 증거 경로 | 상태 |
|---|------|----------|------|
| F1 | /api 버전화 컨트롤러 3개뿐, 업무 전체가 /admin 단일 블록 | `app/api/v1/controller/`에 Captcha/Auth/Product 3개뿐 | 확인됨 |
| F2 | 10개 모듈 컨트롤러가 모델 직접 조회로 서비스 계층 없음 | 조사 결론(crm/product 등 컨트롤러가 모델 직접 사용) | 부분 확인(전량 감사 필요) |
| F3 | purchase/sales가 하드코딩 `new` 서비스로 의존성 주입 아님 | B5 증거 | 확인됨 |

---

## 3. 단계별 계획

우선순위에 따라 3개 배치(P0→P1→P2), **각 기간은 독립 출시 가능, 수용 기준 전부 정량화**. 총 공수 약 **8~9주**(병행도 가정: **개발자 2~3명 병행 + agent 팀 협업** 추산; 단일 작업 합계 약 **77인일** — P0 ≈12.5d, P1 ≈29.5d, P2 ≈35d — 1인 순차 실행 시 약 15주 소요. 병행 근거: A1/A4/A5 등 백엔드 소작업이 서로 독립적이라 병행 가능; B1 각 모듈 테스트는 하위 작업으로 분할 병행 가능; B/C 그룹과 E/D 그룹은 기간 겹침 가능; Flutter/HarmonyOS 프론트엔드 작업과 백엔드 작업은 서로 블로킹 없음; 작업 간 명시적 의존성은 §5 참조).

**번호 체계**: 단계 작업 번호는 §2 격차 번호와 1:1 대응(A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3); 그중 D6/D7(컨트롤러와 마이그레이션 구경)은 D3 작업에 통합, E2(README 불실 선언)는 E1 수용에 통합, E6(커버리지 깊이)는 A7-2에 통합, F1(/api 버전화)은 이번 기간에 수행하지 않음(§6 참조); 그 외 i18n 작업은 조사 "Flutter i18n 미완료"에 대응, 격차 표 번호 아님.

### 3.1 첫 번째 배치 P0: 루프 폐쇄 베이스라인(1~2주차)

**목표**: 죽은 엔드포인트와 가짜 데이터 소멸, 이미 존재하는 배선 안 된 능력(TenantScope/큐/WebSocket)을 사용 가능 또는 명시적 다운그레이드로 착지.

| 작업 | 내용 | 범위 | 수용 기준 | 공수 |
|------|------|------|------|------|
| A1 | 알림 "전체 읽음 표시" 수정: 프론트엔드가 `POST /admin/notification/read-all` 호출로 변경(또는 백엔드 별칭 라우트 추가, 둘 중 하나, 프론트엔드 수정 권장) | `notification_page.dart` + `config/route.php` | 수동/자동 호출 통과; 해당 라우트 존재 단언 PHPUnit 1건 추가 | 0.5d |
| A2 | 대시보드 실제 데이터 연결: 독립 Dio 제거, ApiService(JWT 인터셉터)로 변경; OMS/WMS/TMS 3개 Tab이 `/dashboard/oms\|wms\|tms` 호출; 하드코딩 가짜 값 삭제; Redis 5m 캐시 의미 유지 | `dashboard_controller.dart` + 관련 페이지 | 로그인 상태에서 대시보드 3개 Tab이 백엔드 실제 데이터 표시, Network 패널에서 200과 Authorization 헤더 확인; mock 주석 삭제 | 2d |
| A3 | TenantScope 배선: `/admin` 라우트 그룹에 등록; 테넌트 ID는 JWT 선언 또는 `X-Tenant-Id` 헤더에서 획득(**결정 포인트**, §5 참조); 모델 trait는 준비 완료라 대규모 수정 불필요 | `config/route.php`, `app/middleware/TenantScope.php`, `config/middleware.php` | 두 테넌트 데이터 상호 비가시(신규 통합 테스트); 테넌트 헤더 미전달 시 400 반환, 조용한 통과 아님; **대체 다운그레이드**: 시기 판단이 미성숙하면 문서에 "멀티테넌트는 예약 능력" 명시 표기 + 활성화 절차 제공, 수용=문서와 코드 일치 | 2d |
| A4 | 큐 엔드투엔드: config/process.php에 `redis-queue` 소비 프로세스 추가(기본 redis 드라이버); 관찰 가능한 스모크 작업 1건 추가(예: 비동기 작업 로그 작성); 문서에 rabbitmq 전환 절차 명시 | `config/process.php`, `app/queue/` | 기동 후 소비 프로세스 온라인(`php start.php status`); 스모크 작업 투입 후 목표 부수효과 5초 내 발생 | 1d |
| A5 | WebSocket 인증: 연결 수립/`auth` 메시지에서 JWT 검증(AdminAuth 로직 재사용), 불법 token은 auth_result:false 반환 후 끊음; 문서 동기화 | `app/process/WebSocket.php` + 프론트엔드 연결부 | 미전달/위조 token 연결 거부; 유효 token 연결 성공; 신규 테스트 1건 커버 | 1d |
| A6 | HarmonyOS 페이징 수정: 25곳 단일 인용부호 보간을 템플릿 문자열/결합으로 변경; page 자동 증가 + 바닥 도달 로드 + 당겨서 새로고침; 페이징 컴포넌트 공통화 | `apps/harmonyos/entry/src/main/ets/pages/**`(25개 파일) | grep 전체 저장소에 `${this.page}` 단일 인용부호 패턴 잔여 없음; 목록 페이지 전환 요청 파라미터 정상; 빌드 통과 | 2d |
| A7-1 | 죽은 엔드포인트 전량 소멸: 조사 커버리지 매트릭스를 초안으로 "프론트엔드 URL × 백엔드 라우트" 자동 비교 실행(스크립트로 Flutter/HarmonyOS 요청 문자열 vs `config/route.php` 추출), 잔여 차이 목록 출력 | `apps/flutter/lib`, `apps/harmonyos/.../pages`, `config/route.php` | 비교 스크립트 산출물 저장소 입고(docs/); 차이 목록에서 "프론트엔드가 호출했지만 백엔드에 없음" 0건(없는데 합리적인 것은 화이트리스트 표기) | 2d |
| A8-1 | 고가치 폼 필드 보강: 구매/판매 오더, 전표 페이지에 업무 핵심 필드(금액/일자/거래처/명세 행) 보강, 보강만 하고 폼 엔진 만들지 않음 | 해당 Flutter 페이지 | 폼이 업무 필드 포함한 완전한 문서 생성 가능, 인터페이스 200 | 2d |

**P0 수용 요약**: A1~A6 전부 착지; 죽은 엔드포인트 목록 0건; CI 전부 초록; 신규 문서 표류 없음(변경은 docs/CLAUDE.md 기능 목록에 동기화).

### 3.2 두 번째 배치 P1: 테스트와 보안 베이스라인(3~5주차)

**목표**: 테스트 체계를 "순수 단위 테스트"에서 "단위+통합+커버리지"로 업그레이드, 보안 약점 소멸.

| 작업 | 내용 | 범위 | 수용 기준 | 공수 |
|------|------|------|------|------|
| B1 | 11개 업무 모듈 테스트 보강: 모듈별 서비스/모델 계층 테스트 작성, CRUD + 핵심 액션(정산, 승인 플로우, 품질 검사 플로우, 설비 티켓 등) 커버 | `tests/`(신규 crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow 테스트 파일) | 신규 ≥150 tests / ≥500 assertions; 11개 모듈 각 ≥10 tests; `vendor/bin/phpunit` 전부 초록 | 2w |
| B2 | 통합 테스트: CI 기존 mysql8/redis7 services 활용, 신규 통합 테스트 그룹(실 DB CRUD + 트랜잭션 롤백 + TenantScope 격리 검증 + 큐 스모크) | `tests/Integration/` + `phpunit.xml` 그룹 | 통합 그룹 CI 전부 초록; 로컬 `--group=integration` 실행 가능 | 1w |
| B3 | E2E 스모크: 실제 HTTP로 health→login→핵심 CRUD→대시보드 통과, 스크립트화 | `tests/E2E/`(curl/php 스크립트) | CI 신규 작업이 핵심 체인 10건 통과, 실패 시 즉시 레드 | 2d |
| B4 | 커버리지: phpunit --coverage 연동, 임계값 설정(업무 계층 ≥40%, 전체 ≥30%, CI가 xdebug 수집 지원하는지 검증 필요) | `phpunit.xml`, `ci.yml` | CI 커버리지 리포트 산출; 임계값 미달 시 실패 | 1d |
| B5 | 컨트롤러 서비스화(빈도 높은 4개 모듈): finance/inventory/sales/purchase 컨트롤러에서 `new` 제거, 컨테이너에서 서비스 획득(`support\Container`), B1 테스트 기반 마련 | `app/controller/{finance,inventory,sales,purchase}/**` | `new InventoryService/FinanceService` 잔여 없음; 기존 테스트 전부 초록 | 3d |
| C1 | 약한 키 소멸: `.env.docker`/`.env.example`을 랜덤 플레이스홀더 + 기동 강제 검증(부재/플레이스홀더 동일 시 기동 거부)으로 변경; CI에 `env 검증` 단계 추가 | `.env*`, `config/*.php`, `ci.yml` | `change-me`로 기동 시 바로 실패하고 안내 제공; Docker 신규 인스턴스가 랜덤 키 자동 생성 | 1d |
| C2 | 환경 변수 강제 검증 확장: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD를 env_required에 포함(먼저 config/jwt.php 현황 대조, 검증 필요) | `config/*.php` | 핵심 키 하나라도 부재 시 기동 실패, 오류 메시지가 중국어로 명확 | 1d |
| C3 | fail-open 감사: 빈 catch/로그 없는 catch를 grep, fail-closed + 로그(TraceId 포함)로 변경 | 전체 app/ | 감사 목록 저장소 입고; 수정 항목 모두 테스트 또는 로그 증빙 | 2d |
| C4 | 마이그레이션 정리: `database/backup/backup-validator.sh` 보강(백업 후 자동 복원 검증) + 29개 마이그레이션별 `_rollback.sql`(install.sql로 테이블 구조 역산) | `database/` | validator 스크립트가 백업 파일에 통과(백업→복원→테이블 수/행 수 비교); 각 마이그레이션 파일 옆에 동명 `_rollback.sql` 존재 | 2d |
| C5 | 알림 채널 착지(격차 C5 대응): 최소 하나의 사용 가능 채널 확보(권장 email: SMTP 드라이버 또는 파일 로그 드라이버로 발송 구현); 시기 판단 미성숙 시 명시적으로 "스테이션 내 메시지 + email/wecom/dingtalk 어댑터 포인트 예약"으로 문서화 다운그레이드하고 접속 절차 제공(둘 중 하나, 명시적 결정 필요) | `app/service/notification/ChannelRouter.php` + 신규 드라이버 클래스 + docs | 이메일 드라이버: 알림 발송 성공 시 ChannelRouter가 true 반환(테스트는 로그 드라이버로 단언); 다운그레이드 시: ChannelRouter.php:23 주석과 docs에 "예약" 상태 명시, "stub for future implementation" 모호성 제거 | 1.5d |
| C6 | 모니터링 지표 보강: 큐 적체(redis LLEN), WebSocket 온라인 연결 수 | `MetricsController.php` | `/metrics` 출력에 gauge 2개 신규 추가 | 1d |

**P1 수용 요약**: 테스트 총수 ≥287(137+150); 커버리지 리포트 산출 및 임계값 통과; 약한 키/키 부재 기동 실패; validator와 롤백 스크립트 확보; 알림 채널 최소 1개 사용 가능 또는 명시적 다운그레이드 문서화; CI 신규 통합/E2E/커버리지 작업 전부 초록.

### 3.3 세 번째 배치 P2: 문서, 버전 매트릭스와 프론트엔드 심화(6~8주차)

**목표**: 문서 숫자와 코드 사실 완전 정렬(자동 검증), 버전 매트릭스 신뢰 회복, 프론트엔드 고가치 심화 보강.

| 작업 | 내용 | 범위 | 수용 기준 | 공수 |
|------|------|------|------|------|
| D1 | 3개 브랜치 동기화: main을 lite/standard/full에 병합, 충돌 해결, 3개 브랜치 CI 전부 초록; **결정 포인트**: 이후 "main을 유일한 개발 소스로, 버전 브랜치는 릴리즈 시 cherry-pick만" 전략 채택 | git 3개 브랜치 + ci.yml | 3개 브랜치 behind=0; 각 브랜치 CI 초록; 충돌 해결 기록 남김 | 1w |
| D2 | EDITIONS.md 재작성: 실측 기준(테이블/컨트롤러/모듈 수는 코드 카운트 스크립트에서), 자가모순 문단 삭제 | `docs/EDITIONS.md` | 문서 모든 숫자가 스크립트 출력과 일치 | 1d |
| D3 | 문서 통계 자동화: `scripts/doc-stats.sh` 작성(컨트롤러/서비스/모델/마이그레이션/테스트/미들웨어 카운트 + phpunit 출력), FUNCTIONS.md 부록을 그 출력 참조로 변경; 동시에 D6(컨트롤러 구경 104/121/122)과 D7(마이그레이션 구경 22/29/30)을 스크립트 유일 구경으로 통일 | `scripts/doc-stats.sh`, `docs/FUNCTIONS.md`, `docs/CLAUDE.md` | 스크립트 출력과 문서 일치; README/docs 모든 숫자가 스크립트로 재현 가능(컨트롤러/마이그레이션 구경 단일화 포함) | 2d |
| D4 | 완료도 매트릭스 수정: QMS/EAM/DMS/BI 등 실제 구현된 항목을 ✅로 변경, 코드 증거 첨부 | `docs/FUNCTIONS.md` | 매트릭스가 `app/controller/` 디렉토리와 1:1 대응, 🔴/✅ 어긋남 없음 | 1d |
| D5 | CI 문서 검증 작업: doc-stats 실행과 문서 비교, 표류 시 즉시 레드 | `ci.yml` + 스크립트 | 숫자 1곳 변경 후 CI 레드(자체 테스트 데모) | 1d |
| E1 | Flutter CI 작업: flutter analyze + flutter test + build web, ci.yml에 연동 | `ci.yml`, `apps/flutter/` | 3단계 전부 초록; README.md:635 선언과 실제 일치 | 1d |
| E3 | Flutter 테스트 확충: ApiService 인터셉터/401 갱신, AuthService 플로우, 핵심 폼 검증, ≥20개 widget/unit 테스트 | `apps/flutter/test/` | `flutter test` 전부 초록, ≥20 tests | 1w |
| E4 | HarmonyOS token 영속화: AppStorage 영속화 착지 + 콜드 스타트 복원 + 401 갱신 로직(먼저 ApiService 현황 대조, 검증 필요) | `apps/harmonyos/.../service/ApiService.ets` | 프로세스 종료 후 재시작 시 로그인 상태 유지; token 만료 시 자동 갱신 | 2d |
| E5 | HarmonyOS 핵심 페이지 증감삭제 보강: 가치순 정렬(구매/판매/재고/재무/OMS 각각 목록 페이지 2~3개 선택), 페이지별 신규/편집/삭제 액션과 폼 보강 | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | 선택한 ≥10개 목록 페이지가 증감삭제 보유하고 백엔드 연동 통과; hvigor 빌드 통과(HarmonyOS SDK 환경 없으면 "CI 환경 준비 대기" 표기) | 1w |
| i18n | Flutter 최소 i18n(조사 "Flutter i18n 미완료" 대응): ApiService 오류 메시지와 로그인/내비게이션/대시보드 핵심 문구를 i18n에 연동(arb 파일, 백엔드 `app/common/I18n.php` 연동); **최소 실행만, 전량 페이지 문구 개조는 안 함** | `apps/flutter/lib/app/services/`, `apps/flutter/lib/l10n/` | 핵심 오류 메시지와 ≥10곳 페이지 문구가 언어 전환 가능(en/zh); `flutter test` 전부 초록 | 2d |
| A7-2 | 프론트엔드 깊이 커버리지: A7-1 비교 목록 기준으로 구매/판매 정산 페이지, 재무 3대 재무제표/기말 정리/은행 계좌, CRM follow/funnel/계약 유통 등 핵심 엔드포인트 페이지 보강 | `apps/flutter/lib/app/pages/**` | 비교 목록에서 "백엔드 존재하지만 프론트엔드 미커버" 고우선순위 항목(정산/3대 재무제표/이행/승인/급여) 0건 | 1w |
| F2/F3 | 서비스 계층 경량 추출(선택, 역량 내): 모델 직접 조회가 가장 많은 3~5개 모듈에 얇은 서비스 계층 추출 + 의존성 주입; **전량 리팩터링 강제하지 않음** | `app/controller/{crm,product,project,hr,manufacturing}/**` | 추출 모듈 컨트롤러에 모델 직접 조회 없음; 기존 테스트 전부 초록; 미추출 모듈 문서에 "컨트롤러 모델 직접 조회, 알려진 기술 부채" 표기 | 1w |

**P2 수용 요약**: 3개 브랜치 동기화 및 CI 초록; docs 숫자 스크립트 재현 가능; CI에 Flutter 작업과 문서 검증 포함; Flutter ≥20 테스트; HarmonyOS 영속화 + ≥10페이지 증감삭제; 고우선순위 엔드포인트 커버리지 소멸.

---

## 4. 수용 기준(요약, 전부 검증 가능)

- **엔드포인트**: A1 알림 엔드포인트, A2 `/dashboard/oms|wms|tms`, A7 고우선순위 엔드포인트 모두 curl로 JWT 첨부 호출 시 200/업무 데이터 반환.
- **테스트**: `vendor/bin/phpunit` 전부 초록(≥287 tests); `flutter test` 전부 초록(≥20); 통합/E2E 작업 CI 초록.
- **보안**: `change-me` 키로 기동 실패; WebSocket 불법 token 거부; 빈 catch 조용한 오류 삼킴 없음(감사 목록).
- **채널/i18n**: 알림 최소 1개 채널 사용 가능 또는 명시적 다운그레이드 문서화; Flutter 핵심 오류 메시지와 ≥10곳 문구 중영 전환 가능(최소 실행).
- **CI**: `.github/workflows/ci.yml` 전 작업 초록(PHP 매트릭스 + 통합 + 커버리지 + flutter + 문서 검증).
- **문서**: `scripts/doc-stats.sh` 출력과 docs 전체 숫자 일치(표류 시 CI 레드).
- **브랜치**: `git rev-list --left-right --count main...lite|standard|full` 모두 `0 0`.
- **프론트엔드**: HarmonyOS `${this.page}` 단일 인용부호 잔여 없음; 콜드 스타트 로그인 유지; 핵심 페이지 증감삭제 백엔드 연동 통과.

---

## 5. 의존성과 리스크

**의존 관계**:
- A 그룹(루프 폐쇄) → B 그룹(테스트): B1/B2 테스트는 **실제 사용 가능한** 엔드포인트를 대상으로 해야 하므로, P0에서 먼저 죽은 엔드포인트와 배선을 수정하고 P1에서 테스트를 보강.
- B5(컨트롤러 서비스화) → B1(테스트): **커버하는 finance/inventory/sales/purchase 4개 모듈 테스트의 기반만 마련**(`new` 하드코딩 제거 후 서비스를 mock 주입 가능; purchase/sales는 테스트 0건 모듈, finance/inventory는 기존 테스트를 겸해 개선 가능); 나머지 테스트 0건 모듈(crm/eam/dms/quality/project/product/bi/report/workflow) 테스트는 B5에 **의존하지 않으며** B5와 병행 가능.
- D1(브랜치 동기화) → D3/D5(문서 검증): 동기화 후 main이 유일한 사실 소스가 되어야 문서 구경이 유일해짐.
- E1(Flutter CI) → E3(테스트 확충): 게이트가 먼저 있어야 테스트 확충이 보호 의미를 가짐.

**리스크와 완화**:
| 리스크 | 영향 | 완화 |
|------|------|------|
| TenantScope 배선이 전체 /admin 조회에 영향, 데이터 가시성 회귀 가능 | 높음 | 통합 테스트 선행; JWT 선언으로 테넌트 획득(프론트엔드 개조 불필요); 또는 P0 내에서 "문서 예약 표기"로 다운그레이드하고 명확히 결정 |
| 3개 브랜치 동기화 병합 충돌, 회귀 가능 | 중높음 | 먼저 main 전부 초록; 병합 후 3개 브랜치 각각 CI 전부 초록이어야 납품; 충돌 해결 기록 남김 |
| 큐 소비 프로세스가 일부 환경(rabbitmq)에서 사용 불가 | 중간 | 기본 redis 드라이버(CI에 redis7 이미 있음), rabbitmq는 문서화된 전환 절차만 |
| WebSocket 인증 변경이 기존 클라이언트 손상 | 중간 | 프론트·백엔드가 동일 마일스톤 내에서 협조 수정; 불법 token 거부하되 유효 세션은 영향 없음 |
| 커버리지 매트릭스/폼 필드 목록이 조사 결론, 일부 "검증 필요" | 중간 | A7-1에서 먼저 자동 비교 스크립트 작성, 스크립트 결과 기준으로 진행, 감각으로 페이지 보강하지 않음 |
| 서비스 계층 리팩터링 범위 통제 불가 | 중간 | 3~5개 모듈만 추출 명시, 전량 강제 안 함; /api 전량 버전화 안 함(F1 이번 기간 수행 안 함) |
| 커버리지 임계값이 CI 환경에서 사용 불가(xdebug 미설치) | 낮음 | 먼저 로컬에서 리포트 + 문서 임계값, CI 수집 능력 "검증 필요" 후 연동 |
| HarmonyOS CI(hvigor)가 HarmonyOS SDK 필요, 공용 CI 환경에 없을 수 있음 | 중간 | "CI 환경 준비 대기" 표기; 로컬 빌드 검증 기준, 다른 작업 블로킹 안 함 |

---

## 6. 명시적으로 하지 않는 것

로드맵 §12 제외 항목 이어감, 강한 이유가 없으면(별도 심사 입안 필요):
- ❌ 마이크로서비스 분리 / K8s 배포(실험은 `.claude/worktrees/microservices-split/`에 보존, 메인 라인에 병합 안 함)
- ❌ AI/ML 능력(예측, 지능 추천, NLP)
- ❌ 네이티브 App(iOS/Android 네이티브) — Flutter가 전 플랫폼 커버
- ❌ GraphQL 인터페이스
- ❌ 하드웨어 통합(IoT/스캐너/프린터 직접 연결)
- ❌ 멀티테넌트 완전 상업화 방안(SaaS 과금, 테넌트 자가 개통) — 이번 기간은 최소 배선 또는 문서화 예약만
- ❌ /api 전량 버전화(F1) — 업무 단은 여전히 /admin, 아키텍처 부채로만 기록
- ❌ 전량 서비스 계층 리팩터링과 전량 폼 재작업 — 가치순 추출, "대폭발"식 리팩터링 안 함
- ❌ HarmonyOS 전량 페이지 보강 — 고가치 핵심 페이지 증감삭제만 보강
- ❌ Flutter 전량 i18n 문구 개조 — 이번 기간은 최소 실행만(오류 메시지 + ≥10곳 핵심 문구), 전량 페이지 다국어는 이후 버전에

---

## 7. 마일스톤 제안

| 마일스톤 | 시기 | 내용 | 출구 기준 |
|--------|------|------|----------|
| **M1 루프 폐쇄 베이스라인** | 2주차 말 | A 그룹 전체: 죽은 엔드포인트 소멸, 대시보드 실제 데이터, TenantScope/큐/WebSocket 착지, HarmonyOS 페이징 수정 | P0 수용 요약 전부 통과 |
| **M2 품질 베이스라인** | 5주차 말 | B 그룹 전체 + C 그룹 보안 항목: 11개 모듈 테스트, 통합/E2E/커버리지, 약한 키 소멸, fail-open 감사, 마이그레이션 정리, 알림 채널 | P1 수용 요약 전부 통과 |
| **M3 프론트엔드 품질** | 6주차 말 | E 그룹: Flutter CI 작업 + 테스트 확충, HarmonyOS token 영속화와 핵심 페이지 증감삭제 | flutter CI 초록, 영속화 동작, ≥10페이지 증감삭제 |
| **M4 버전과 문서 정리** | 7주차 말 | D 그룹: 3개 브랜치 동기화, EDITIONS/FUNCTIONS 재작성, doc-stats 자동화 + CI 검증 | 브랜치 동기화, 문서 표류 즉시 레드 |
| **M5 깊이 커버리지** | 8주차 말 | A7-2 프론트엔드 심화 + F 그룹 서비스 계층 경량 추출 | 고우선순위 엔드포인트 커버리지 소멸, 추출 모듈 모델 직접 조회 없음 |
| **M6 1.1 릴리즈** | 9주차 말 | 전량 회귀, 릴리즈 노트(CHANGELOG), 문서 최종 검증, 아카이브 | 모든 마일스톤 출구 기준 통과(하드 지표): 테스트 총수 ≥287 및 phpunit 전부 초록, 커버리지 리포트 임계값 통과, ci.yml 전 작업 초록(PHP 매트릭스+통합+커버리지+flutter+문서 검증), 3개 브랜치 동기화 0 0, 죽은 엔드포인트 목록 0건, doc-stats 표류 즉시 레드 메커니즘 동작; CHANGELOG와 문서 최종 검증 통과; 심사 재검토는 참고만, 점수 임계값 없음 |

---

## 부록: 본 계획이 표본 검증한 핵심 파일

- `config/middleware.php`, `config/route.php`(:231-233 dashboard 엔드포인트, :248-251 알림 라우트, :387-415 미들웨어 그룹)
- `config/process.php`, `config/queue.php`
- `app/middleware/TenantScope.php`, `app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php`(:23, :47-50)
- `app/service/notification/ChannelRouter.php`(:23 stub)
- `app/controller/sales/DeliveryController.php`(:142-143), `app/controller/purchase/ReceiveController.php`(:142-143, 두 파일 모두 `new` 인스턴스화가 여기; `use` 선언은 :15-16)
- `app/api/v1/controller/`(컨트롤러 3개뿐)
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`(mock 통계 + 독립 Dio)
- `apps/flutter/lib/app/pages/notification/notification_page.dart`(:43 죽은 엔드포인트)
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets`(:24 보간 버그)
- `tests/`(19개 테스트 파일 목록), `vendor/bin/phpunit` 실측 137/805
- `phpstan-baseline.neon`(974 message)
- `.github/workflows/ci.yml`(PHP 작업뿐), `README.md`(:635 불실 선언)
- `.env.docker`(약한 키), `database/migrations/`(29개, _rollback 없음)
- `docs/EDITIONS.md`(자가모순), `docs/FUNCTIONS.md`(부록 표류), `docs/CLAUDE.md`(104 vs 실측 122 컨트롤러 구경)
- git 브랜치 `lite/standard/full`(behind 41/41/20)

> 구경 설명: 컨트롤러 실측 `find app -path '*/controller/*.php'` = 122(admin 14 + api 3 + 업무 컨트롤러 + Index/Install 포함); 조사 구경 121, docs/CLAUDE.md 업무 구경 104, 셋의 차이는 통계 범위 차이에서 유래, D6에서 통합 항목으로 정리해 구경 통일.
