# 감사 보고서 — 2026-08-07

**프로젝트**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**범위**: 전체 실행 테스트, 심층 점검, P0/P1 문제 수정
**지시**: "전체적으로 테스트하고, 돌려보고, 심층 점검해서 아직 문제나 최적화할 곳이 없는지 봐줘?"
**테스트 결과**: OK (135 tests, 799 assertions) — 전체 통과

---

## 1. 테스트 및 실행 검증 결과

| 항목 | 결과 |
|---|---|
| PHPUnit 전체 | 135 tests / 799 assertions 전체 통과 |
| 서비스 시작 (port 8787→임시 8791) | 정상 시작, 프로세스 크래시 없음 |
| /health 헬스 체크 | code=0, database/redis/elasticsearch 필드 완비 |
| 제한 체인 | /api/auth/login 연속 요청 시 429 반환 |
| JWT 블랙리스트 / 로그인 잠금 | 정상 동작 (Redis 수정 후) |
| CS-Fixer | 31개 파일 포맷 위반 수정 |
| PHPStan | 캐시 손상 수정 후 재실행 (851개 ORM 매직 메서드 오탐, 75개 만료 베이스라인) |

---

## 2. P0 수정 (런타임 장애 — 전체 수정 및 검증 완료)

### 2.1 support\Redis 클래스 부재 — 보안 메커니즘 조용한 무력화

- **현상**: `support\Redis`가 존재하지 않음 (composer.json에 webman/redis를 도입한 적 없음), 9개 파일이 이를 참조.
- **근본 원인**: 여러 곳의 `catch (\Throwable)` fail-open 설계가 클래스 부재 오류를 삼켜 제한, JWT 블랙리스트, 로그인 잠금, 차단이 모두 조용히 무력화되어 인터페이스가 "정상처럼 보이지만" 어떤 방어도 없음.
- **수정**: `composer require webman/redis`; `config/redis.php` 환경 변수화 (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **검증**: /health가 `redis: ok` 반환; 제한 테스트가 429 반환.

### 2.2 ApiVersion 미들웨어 컴파일 실패 — 전체 /api 라우트 500

- **현상**: `Interface "app\middleware\MiddlewareInterface" not found` — `use Webman\MiddlewareInterface;` 부재.
- **수정 후 2차 오류**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request`는 `Webman\Http\Request`의 서브클래스로 파라미터 반공변성 계약을 위반.
- **수정**: `Webman\Http\Request` / `Webman\Http\Response` 임포트로 변경.

### 2.3 AdminAuth 미들웨어 파라미터 반공변성 — /admin 라우트 worker 크래시

- **현상**: /admin/dashboard가 worker Empty reply (컴파일 크래시) 트리거.
- **근본 원인**: 2.2와 동일한 파라미터 반공변성 문제.
- **수정**: `Webman\Http\Request` / `Webman\Http\Response`로 변경 (`support\Redis` 유지).
- **검증**: 401 JSON 반환.

### 2.4 validator() 헬퍼 함수 부재 — 로그인 500

- **현상**: `Call to undefined function validator()`, 99개 파일 105곳에서 호출.
- **수정**: `composer require illuminate/validation`; `app/functions.php`에 헬퍼 함수 구현 (정적 $factory 캐시).
- **함정**: `Factory::__construct()`의 첫 번째 파라미터는 `Translator`여야 하며 `ArrayLoader`가 아님.
- **잔여 (P2)**: 오류 메시지가 번역되지 않음 (`validation.required` 표시, 중국어 아님), zh_CN 언어 팩 보강 필요.

### 2.5 CORS 하드코딩 + 프리플라이트 응답의 CORS 헤더 누락

- **수정**: `app/common/CorsPolicy.php` 신규 추가, `CORS_ALLOWED_ORIGIN` 환경 변수에서 화이트리스트(쉼표 구분)를 읽고 origin 에코; 미적중 시 CORS 헤더 미발송.
- **핵심**: `Route::fallback`은 전역 미들웨어 체인을 거치지 않으므로 OPTIONS 프리플라이트가 자체적으로 CORS 헤더를 부착해야 함 — fallback 클로저에서 처리.
- **보안 헤더**: 폐기된 X-XSS-Protection 제거; CSP에 `connect-src 'self'` 추가.

### 2.6 FastRoute BadRouteException — 라우트 가림

- **현상**: `Static route "/install" is shadowed by previously defined variable route`.
- **근본 원인**: OPTIONS 와일드카드 라우트 `/{path:.+}`가 후속 정적 라우트를 가림; 플러그인 라우트(apidoc)는 config/route.php 이후에 로드.
- **수정**: 와일드카드 라우트 제거, `Route::fallback`으로 변경(라우트 파일 맨 끝에 둬야 함); `/crm/pool/rules`를 resource에서 명시적 GET 라우트로 변경, `PoolController::rules()`를 public으로 변경.

---

## 3. P1 수정 (공정 품질)

- **3.1 PHPStan 캐시 손상**: /tmp/phpstan/cache가 삭제된 service/ 디렉토리(마이크로서비스 분리 잔여물)에서 유래, 옛 절대 경로 포함으로 phar 오류, CPU 0% 멈춤. 캐시 삭제 및 재설치 후 복구. 851개 오류는 webman ORM 매직 메서드 오탐; 75개 베이스라인 경로가 존재하지 않는 service/ 디렉토리를 가리킴 (P2).
- **3.2 CS-Fixer**: 31개 파일 공백/use 정렬 위반 수정.
- **3.3 테스트 동기화**: `test_cors_response_is_assigned_correctly`를 새 구현(withHeaders + CorsPolicy)을 단언하도록 갱신.

---

## 4. 이전 감사(08-04)에서 놓친 근본 원인

- 테스트가 **미들웨어 클래스 로드 가능성**과 **라우트 호출 가능성**을 커버하지 않음 (class_exists / is_subclass_of는 use 부재와 반공변성을 포착할 수 없음).
- 커밋 b1fe2de가 주장한 CORS/X-XSS 수정이 실제 코드와 불일치 — 감사 결론이 커밋 정보에 과도하게 의존했고 실행 검증이 부족.

---

## 5. 이번 라운드 변경 목록 (git status: 41 수정 + 2 추가)

| 파일 | 변경 |
|---|---|
| app/middleware/ApiVersion.php | use Webman\MiddlewareInterface 추가; 파라미터 타입 Webman\Http로 변경 |
| app/middleware/AdminAuth.php | 파라미터 타입 Webman\Http로 변경 |
| app/middleware/Cors.php | CorsPolicy 사용으로 리팩터링; CSP/보안 헤더 갱신 |
| app/common/CorsPolicy.php | **신규**: CORS 화이트리스트 정책 |
| config/route.php | fallback 라우트 + /crm/pool/rules 수정 |
| app/controller/crm/PoolController.php | rules()를 public으로 변경 |
| app/functions.php | validator() 헬퍼 함수 신규 추가 |
| config/redis.php | **신규** (composer 생성 후 환경 변수화) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS 단언 동기화 |
| 나머지 ~30 파일 | CS-Fixer 포맷 수정 |

---

## 6. P2 제안 (환경/대기, 미수정)

1. **.env DB_PASSWORD 비어 있음** — MySQL root 인증 실패, `database: unavailable`; 실제 비밀번호 설정 필요.
2. **포트 8787 충돌** — cloud-php/service가 점유(다른 프로젝트); 운영 배포 시 구분 필요.
3. **validator 중국어 오류 메시지** — 언어 팩 설치 또는 커스텀 messages 필요.
4. **PHPStan 베이스라인 재구축** — 75개 경로가 삭제된 service/ 디렉토리를 가리키므로 정리·재구축 권장.
5. **fail-open 감사** — `catch (\Throwable)` 조용한 오류 삼킴 지점을 전역 점검 권장 (이번에 심각한 결과 1건 발견), fail-closed 또는 명시적 로그로 변경.

---

*보고서 생성: 2026-08-07, 서비스 중지, 포트 8787 복원.*
