# 팀 계획(AI 협업 팀)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 이 문서는 이 프로젝트의 AI 협업 팀을 정의합니다: 역할 구성, 책임 경계, 협업 방식과 태스크 라우팅.
> 협력 조정 규칙(SendMessage-First, agent 명명, 라이프사이클)은 루트 `CLAUDE.md` 참고; 역할 정의는 `.claude/agents/` 참고.

---

## 1. 프로젝트 포트레이트(계획 근거)

| 차원 | 현황 | 팀에 주는 의미 |
|------|------|--------------|
| 백엔드 | webman (Workerman) PHP 8.3+, **22개 업무 모듈**, 121+ 컨트롤러, 24개 서비스, 161개 모델, 163개 테이블, 12개 미들웨어(schema는 database/install.sql이 유일한 사실 소스) | 모놀리스 규모가 크므로 업무 도메인별 분업, 단일 agent 컨텍스트 폭발 방지 |
| 프론트엔드 | Flutter **97페이지**(Web/모바일) + HarmonyOS **34페이지**, 전 모듈 커버 | 양단 병행 유지보수, 전담 프론트엔드 역할 필요 |
| 품질 베이스라인 | PHPUnit 137 테스트 / 805 assertion, PHPStan + baseline, CS-Fixer, CI 다중 버전 매트릭스 | 이미 규율 확보, 테스트/리뷰 역할이 파이프라인에 직접 내장 |
| 버전 매트릭스 | `lite` / `standard` / `full` 3개 브랜치(62/72/163 테이블) | 변경 시 브랜치 간 동기화 고려 필요, 버전 코디네이션 필요 |
| 로드맵 | P0~P3 이미 전달(종합 점수 89/100), 일상 반복과 진화 단계 진입 | 팀 규모는 태스크 유형별로 신축, 프로젝트제 대편성 아님 |
| 기존 시설 | `.claude/agents/`(planner / sparc / testing / swarm / consensus), `.claude-flow`(hierarchical-mesh, 상한 15 agents, consensus 조정), hooks + 메모리 | 팀을 기존 설정에 바로 탑재, 새로 구축하지 않음 |

---

## 2. 팀 구성

### 2.1 코어 팀(상주, 5개 역할)

| 역할 | 기존 agent 대응 | 책임(본 프로젝트 기준) |
|------|-----------------|--------------------|
| **프로젝트 매니저 Lead** | `planner` / `swarm/hierarchical-coordinator` | 요구사항 분해 → 라우팅 → 수락; 22개 모듈 태스크 큐 유지; pipeline / fan-out / supervisor 모드 결정; 역할 간 메시지 중계 |
| **시스템 아키텍트** | `sparc/architecture` | 테이블 구조 설계(163개 테이블, schema는 database/install.sql이 유일한 사실 소스); 모듈 간 데이터 흐름(구매 입고→재고→매입채무, 판매 출고→매출채권→출고 등 체인); 마이크로서비스 분리 경계 결정 |
| **백엔드 개발자** | `core` / 커스텀 `backend-dev` | 컨트롤러 / 서비스 / 모델 구현; `app/service` 계층과 미들웨어 체인(Locale→Cors→SecurityFilter→RateLimit→TracingId→업무 미들웨어) 준수 |
| **테스트 엔지니어** | `testing/tdd-london-swarm` + `production-validator` | PHPUnit 케이스 우선 작성(엔진 경계 테스트); 3개 브랜치 회귀 검증; `tests/` 커버리지 공백 보강 |
| **코드 리뷰어** | `consensus/security-manager` | PHPStan baseline 신규 추가 금지, CS-Fixer 준수, 18계층 보안 패턴 검사; 커밋 전 품질 게이트 수문장 |

### 2.2 전문 팀(태스크 유형별 차출, 4개 역할)

| 역할 | 기존 agent 대응 | 투입 시나리오 | 대표 태스크 |
|------|-----------------|----------|----------|
| **업무 엔진 전문가** | 커스텀 `business-engineer` | 재무 / 급여 / MRP 등 알고리즘형 모듈 | 복식 부기 엔진, 급여 계산 엔진, MRP 엔진의 알고리즘 보강과 경계 처리(A등급 "산업 수준" 요구) |
| **프론트엔드 엔지니어(Flutter)** | 커스텀 `frontend-flutter` | `apps/flutter/` 관련 모든 변경 | Web 관리 패널 페이지, GetX 상태, ApiService/내보내기 연동, 97페이지 유지보수 |
| **프론트엔드 엔지니어(HarmonyOS)** | 커스텀 `frontend-harmonyos` | `apps/harmonyos/` 관련 모든 변경 | ArkTS 페이지, token 무감지 갱신, Flutter 기능 세트와 정렬(34페이지 유지보수) |
| **보안/DevOps 엔지니어** | `consensus/security-manager` + `performance-benchmarker` | 보안 강화, 성능, 배포 | 18계층 방어 회귀, Docker/gRPC 하위 서비스, 마이그레이션 롤백, 관측성, Prometheus 지표 |

### 2.3 주문형 역할(태스크 트리거, 2개 역할)

| 역할 | 기존 agent 대응 | 투입 조건 |
|------|-----------------|----------|
| **리서처** | 커스텀 `researcher` | 신규 모듈/신규 기능 설계 전: 경쟁사 조사, `docs/API.md`·`docs/FUNCTIONS.md`와 구현 차이 비교, 설계 인풋 산출 |
| **버전 코디네이터** | 커스텀 `edition-coordinator` | `lite/standard/full` 차이 관련: 3개 브랜치 동기화, `docs/EDITIONS.md` 매트릭스 검증, 브랜치 간 회귀 |

---

## 3. 협업 방식

### 3.1 통칙(루트 CLAUDE.md 준용)

- **SendMessage-First**: agent 간 SendMessage로 직접 통신, 폴링하지 않고 가변 상태를 공유하지 않음;
- **명명 필수**: 모든 agent에 이름 지정(`name: "role"`);
- **1회 spawn**: 독립 하위 태스크는 한 번에 백그라운드로 기동, Lead는 멈추고 결과를 기다리며 상태를 폴링하지 않음;
- **메시지 필수 포함**: 모든 prompt에 "완료 후 SendMessage로 누구에게 무엇을 보낼지" 명시.

### 3.2 세 가지 오케스트레이션 토폴로지

| 모드 | 흐름 | 사용 시나리오 |
|------|------|----------|
| **Pipeline** | Lead → 아키텍트 → 백엔드 → 테스트 → 리뷰 | 순서 의존성이 있는 기능 개발(신규 모듈, 모듈 간 데이터 흐름) |
| **Fan-out** | Lead → A, B, C → Lead 집계 | 상호 독립적인 병행 작업(다중 페이지, 다중 모듈 조사) |
| **Supervisor** | Lead ↔ 멤버 다회 왕복 | 지속 조정이 필요한 복잡 작업(마이크로서비스 분리, 대규모 리팩터링) |

### 3.3 태스크 라우팅 테이블

| 태스크 유형 | 오케스트레이션 | 참여 역할 |
|----------|------|----------|
| 신규 모듈 / 신규 기능(예: DMS, BI 심화) | pipeline | Lead → 아키텍트(테이블 설계) → 백엔드 → 테스트 → 리뷰 |
| 엔진급 알고리즘(복식 부기 / 급여 / MRP) | pipeline + TDD | Lead → 업무 엔진 전문가(설계) → 테스트(경계 케이스 우선) → 리뷰 |
| 프론트엔드 페이지(Flutter / HarmonyOS 병행) | fan-out | Lead → 프론트엔드×2 + 백엔드(API 정렬) 병행 → Lead 집계 |
| 모듈 간 데이터 흐름(구매→재고→매입채무 등) | pipeline | Lead → 아키텍트 → 백엔드 → 테스트 → 리뷰 |
| 마이크로서비스 분리 / 대규모 리팩터링 | supervisor | Lead ↔ 아키텍트 + 백엔드 + 리뷰 다회 |
| 보안 / 성능 특화 | 단일 스레드 심층 분석 | Lead → 보안/DevOps 엔지니어 → 리뷰 |
| Bug 수정(단일 파일 / 1-2줄) | 팀 미투입 | Lead가 직접 처리, 또는 1개 agent로 완료 |
| 3개 브랜치 차이 / 버전 릴리스 | pipeline | Lead → 버전 코디네이터 → 테스트(브랜치 간 회귀) → 리뷰 |

### 3.4 품질 게이트(커밋 전 필수, 리뷰어가 수문장)

```
phpunit            # 137 测试 / 805 断言全绿，新增用例随改动提交
phpstan            # 不允许新增 baseline 之外的问题
php-cs-fixer       # --dry-run 通过
composer audit     # 无高危依赖漏洞
```

데이터베이스 관련 변경은 반드시 아키텍트를 거쳐야 합니다(163개 테이블, schema는 database/install.sql이 유일한 사실 소스); 프론트엔드 관련 변경은 Flutter `flutter analyze` 0 error / 0 warning를 반드시 실행해야 합니다.

---

## 4. 팀 규모 제안

| 작업 형태 | 제안 규모 | 설명 |
|----------|----------|------|
| 일상 유지보수 / 소규모 수정 | 1-2명 | Lead가 직접 처리, 과도한 오케스트레이션 방지 |
| 단일 모듈 반복 | 3명 | Lead + 백엔드 + 테스트 |
| 모듈 간 기능 | 4-5명 | Lead + 아키텍트 + 백엔드 + 테스트 + 리뷰 |
| 프론트엔드 양단 병행 | 4-5명 | Lead + Flutter + HarmonyOS + 백엔드(API) + 테스트 |
| 엔진급 / 복잡 리팩터링 | 5-7명 | 위 전부 + 업무 엔진 전문가 또는 보안/DevOps |

> `.claude-flow/config.yaml`(`maxAgents: 15`, `hierarchical-mesh`, `consensus` 조정 전략)과 호환되며, 단일 태스크 점유는 상한을 넘지 않습니다.

---

## 5. 착수 단계

1. **역할 정의 보강**: `.claude/agents/`에 planner / sparc / testing / swarm / consensus가 이미 있고, `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator` 5개 정의가 부족합니다; 기존 YAML/MD 형식에 따라 각각 파일 하나씩 추가하면 탑재 완료;
2. **라우팅 고정**: §3.3 라우팅 테이블을 `.claude-flow/hooks`의 routing 로직에 기록해 `UserPromptSubmit` 훅이 자동으로 태스크를 해당 역할에 배정하게 함;
3. **메모리 영역 분리**: `.claude-flow`가 이미 `agentScopes`(`defaultScope: project`)를 켜고 있으며, `backend / frontend / ops / security` 4개 영역으로 보관해 재무 엔진 컨텍스트가 프론트엔드 태스크를 오염시키지 않도록 권장;
4. **파일럿 운영**: 모듈 간 태스크 하나(예: DMS 심화 또는 BI 보드 반복)를 골라 §3.3 라우팅대로 전체 한 라운드 실행, 메시지 체인과 게이트 검증 후 확산.

---

## 6. 변경 기록

| 일자 | 변경 |
|------|------|
| 2026-08-07 | 초판: 22개 모듈 현황(P0~P3 전달 완료, 89/100) 기준으로 코어 5 + 전문 4 + 주문형 2 팀 편성 |
