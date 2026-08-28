# 오픈ERP 시스템 (open-erp)

webman v2 + Flutter 기반의 풀스택 ERP 시스템.

<div align="center"><img src="images/mascot.svg" alt="open-erp 문어 마스코트" width="150"></div>

<div align="center">🌐 [中文](../../README.md) | [English](../en/README.md) | 한국어 | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [버전 비교](EDITIONS.md) | [아키텍처 설계도](ARCHITECTURE.md) | [시스템 아키텍처](#시스템-아키텍처) | [설계 문서](DESIGN.md) | [보안 아키텍처](SECURITY.md) | [API 참조](API.md) | [기능 매뉴얼](FUNCTIONS.md)

## 기능 목록

| 업무 도메인 | 기능 | 설명 |
|--------|------|------|
| 🔐 인증 | 로그인/회원가입/토큰 갱신/로그아웃 | 클릭 캡차 + JWT + 블랙리스트 |
| | 계정 잠금 | 5회 실패 시 15분 잠금 |
| | 동시 세션 제한 | 동일 사용자 최대 3개 유효 토큰 |
| 📊 대시보드 | 경영 개요/판매 보드/재고 보드/재무 보드 | Redis 캐시 5분 |
| 👥 사용자 관리 | CRUD + 일괄 삭제/활성·비활성화 | 소프트 삭제 + 비밀번호 2차 확인 |
| | Excel 일괄 가져오기 | 행 단위 검증 + 오류 보고서 |
| 🔒 역할 권한 | 역할 CRUD + 권한 트리 | RBAC method.path 단위 인가 |
| ⚙ 시스템 설정 | 키-값 CRUD | 그룹 관리 |
| 📋 감사 로그 | 로그 조회 + 출처 단말 감지 | 8개 플랫폼 자동 인식 |
| 📁 파일 관리 | 업로드/Excel 내보내기/PDF 내보내기 | 민감 데이터 자동 마스킹 |
| 🛡 보안 방어 | 18계층 심층 방어 | XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF/속도 제한/CSP... |
| 🏥 운영 | 헬스 체크/metrics/API 문서/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 상품 관리 | 상품 마스터/SKU/다중 규격/다중 단위/분류/브랜드/가격 정책 | 다단계 분류 트리 + 다중 단위 환산 |
| | 창고·로케이션 | 다중 창고·다중 로케이션 관리 |
| | 공급업체/고객 마스터 | 담당자/은행 계좌/신용 한도 |
| 📥 구매 관리 | 신청→오더→입고→반품→정산 | 완전한 구매 프로세스 + 승인 |
| 📤 판매 관리 | 견적→오더→출하→반품→정산 | 견적→오더 전환 + 판매 마진 |
| 🏗 재고 관리 | 실시간 재고/로트/시리얼 번호/이동/실사/경고 | 이동가중평균 원가 계산 |
| 💰 재무 관리 | 매출채권·매입채무/수금·지급/일계부/비용 정산/손익계산서/고정자산/세금/다중 통화/예산/원가·이익센터 | 매출채권·매입채무 자동 생성 + 대체 + 종합 재무 관리 |
| 🤝 CRM | 고객/담당자/팔로우 기록/마케팅 캠페인/서비스 티켓/분석 리포트/세일즈 퍼널/공해 풀/견적/계약 | 고객 전 생애주기 관리 |
| ✅ 승인 워크플로 | 워크플로 정의/승인 제출/승인/거부/철회/내 승인 | 다중 노드 승인 프로세스 엔진 |
| 🔔 메시지 알림 | 알림 목록/읽음 표시/안읽음 개수/전체 읽음 | 실시간 메시지 푸시와 상태 추적 |
| 📐 프로젝트 관리 | 프로젝트/작업/공수 기록 | 프로젝트 진행 추적과 리소스 관리 |
| 👤 인사 관리 | 부서/사원/직위/근태/휴가/급여 | 종합 인사 관리 |
| 🏭 생산 제조 | BOM/생산 오더/공정 라우팅/작업장/MRP | 자재소요계획과 생산 실행 |
| 📈 커스텀 리포트 | 리포트 템플릿/데이터셋/필드/필터/실행/정기 스케줄 | 시각화 리포트 빌더 |
| 📋 주문 관리(OMS) | 멀티채널 주문/이행 오케스트레이션/재고 예약/할당/취소/RMA 반품·교환 | 주문 전 생애주기 관리 |
| 🏗 창고 관리(WMS) | 구역·로케이션/ASN/입고/상재/웨이브/피킹/패킹/출고 | 완전한 창고 작업 프로세스 |
| 🚚 운송 관리(TMS) | 운송사/서비스/운임/운송장/물류 트래킹/운임 인보이스 | 다중 운송사 운임 비교 + 트래킹 |

## ERP 모듈

각 업무 모듈 간의 데이터 흐름:

- 구매 입고 → 자동 입창(이동가중평균 원가 계산) → 매입채무 자동 생성
- 판매 출고 → 자동 출창 → 매출채권 자동 생성
- 수금·지급 → 매출채권·매입채무 대체 → 일계부 갱신
- 전표 승인 → 총계정원장(계정 집계) + 명세장(건별 기록) 자동 갱신
- 대차대조표 → 총계정원장 기말 잔액 집계로 자동 생성
- 현금흐름표 → 현금·은행 일계부 집계로 자동 생성(영업/투자/재무 3분류)
- 승인 워크플로 → 업무 문서 승인 제출 → 다중 노드 흐름 → 승인 결과 업무 모듈 콜백
- 메시지 알림 → 승인/경고/시스템 이벤트 트리거 → 실시간 푸시 → 사용자 읽음 표시
- MRP → 판매 오더+BOM 기반 → 자재 수요 계산 → 구매 제안/생산 제안 생성
- OMS → 멀티채널 주문 가져오기 → 재고 예약(ATP) → 이행 생성 → WMS 피킹/패킹 지시
- WMS → 웨이브 집계 → 피킹 작업 → 피킹 확정 → 패킹 완료 → TMS 운송장 생성 트리거
- TMS → 운임 비교 → 운송장 생성 → 출고 확정(stockOut+AR) → 물류 트래킹 → 수령 확인
- WMS 입고 → ASN 예정 도착 → 입고 → 품질 검사 → 상재 확정(stockIn+AP) → 재고 갱신
- RMA → 반품 신청 → 승인 → 반품 입고 → 환불

## 기술 스택

| 계층 | 기술 | 설명 |
|---|------|------|
| 백엔드 프레임워크 | webman v2 (workerman) | 초고성능 PHP 상주 프로세스 프레임워크 |
| PHP 버전 | 8.3+ | |
| 데이터베이스 | MySQL 8.0+ | 테이블 접두사 `erp_`, BIGINT 비자동증가 기본키 |
| 검색 엔진 | Elasticsearch | `webman-scout`로 동기화와 조회 |
| 관리단 프론트 | Flutter 3.x | Web은 PC 관리 백오피스 스타일(`apps/flutter/`) |
| 모바일 | HarmonyOS ArkTS | 하모니OS 네이티브 클라이언트(`apps/harmonyos/`), 휴대폰/태블릿/2in1 지원 |

## 핵심 의존성

| 패키지 | 용도 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake 알고리즘으로 전역 고유 BIGINT 기본키 생성 |
| `erikwang2013/hashids` | API 계층 ID 암·복호화, 실제 DB ID 숨김 |
| `erikwang2013/jwt-webman` | JWT 인증 토큰 발급과 검증 |
| `erikwang2013/encryption` | 인터페이스 전송 계층 민감 데이터 암·복호화 |
| `erikwang2013/encryptable` | DB 저장 계층 민감 필드 자동 암·복호화 |
| `erikwang2013/webman-scout` | Elasticsearch 데이터 동기화와 전문 검색 |
| `erikwang2013/season` | 국가 국기 데이터 |
| `erikwang2013/poster-php` | 클릭 캡차 생성·검증 + 포스터 생성 |
| `erikwang2013/security-php` | 보안 도구 검사 |
| `phpoffice/phpspreadsheet` | Excel 내보내기 |
| `barryvdh/laravel-dompdf` | PDF 내보내기(Dompdf 기반) |
| `hg/apidoc` | API 문서 자동 생성 | 어노테이션 방식 인터페이스 문서, 관리단/클라이언트 그룹 분리 |

## 국제화

국제화 | Accept-Language 헤더 자동 감지 | 중국어/English 이중 언어 지원

## 프로젝트 구조

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   ├── api/v1/controller/      # 客户端 API（版本由 API-Version 请求头控制）
│   ├── controller/             # 业务模块控制器 (88 个)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (7 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算 (5 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (5 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心 (20 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批提交/批准/拒绝/撤回 (2 个)
│   │   ├── notification/       # 通知列表/已读/未读计数 (1 个)
│   │   ├── project/            # 项目/任务/工时记录 (3 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资 (5 个)
│   │   ├── manufacturing/      # BOM/生产订单/工艺路线/工作站/MRP (5 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   └── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   ├── service/                # 业务逻辑层
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 161 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 12 个中间件
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/hg/apidoc/        # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（20 个测试文件，137 个测试方法，805 条断言）
├── resource/
│   └── translations/           # 翻译文件 (zh_CN, en)
│       ├── zh_CN/              # 中文翻译 (127 键)
│       └── en/                 # English translations (127 keys)
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
└── vendor/                     # Composer 依赖
```

## 시스템 아키텍처

> 이미지를 클릭하면 원본 SVG를 볼 수 있습니다. 다이어그램은 영어로 명명되었으며, 시스템 각 계층의 아키텍처 설계를 완전하고 명확하게 보여줍니다.

### 시스템 토폴로지 아키텍처

![System Architecture](./diagrams/system-architecture-cn.svg)

**5계층 아키텍처**: 클라이언트 계층 → 게이트웨이 엣지 계층(Nginx 리버스 프록시) → 애플리케이션 계층(webman v2 + 미들웨어 체인 + 인증·인가 + 비즈니스 로직 + 공통 서비스) → 데이터 저장 계층(MySQL + Redis + Elasticsearch) → 운영 계층(CI/CD + Docker + Prometheus)

### 업무 데이터 흐름도

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**7대 업무 도메인 연동**: 구매 → 재고 → 판매 → 재무가 핵심 공급망 루프를 형성합니다. 고객 관계 관리가 판매를 견인하고, 생산 제조 MRP가 판매 오더+자재명세서(BOM)를 기반으로 구매 계획과 생산 계획을 구동합니다. 승인 워크플로, 메시지 알림, 프로젝트 관리, 인사 관리가 지원 모듈로 전 프로세스에 걸쳐 있습니다.

### 기능 모듈 총람

![Functional Modules](./diagrams/functional-modules-cn.svg)

**19대 업무 도메인, 163개 데이터 테이블, 121개 컨트롤러**: 인증 보안, 대시보드, 시스템 관리, 보안 방어, 운영 모니터링, 상품 관리, 구매, 판매, 재고, 재무(14개 하위 모듈), CRM(10개 하위 모듈), 승인 워크플로, 메시지 알림, 프로젝트 관리, 인사 관리, 생산 제조(MRP), 커스텀 리포트, 주문 관리(OMS), 창고 관리(WMS), 운송 관리(TMS), 품질 관리(QMS), 설비 관리(EAM), 문서 관리(DMS), BI 대시보드를 포함합니다.

### 요청 생애주기

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**클라이언트에서 데이터베이스까지의 완전한 요청 경로**: 클라이언트(Flutter/하모니OS) → Nginx SSL 종료 → 언어 감지 → CORS 처리 → 보안 필터 → 속도 제한 → API 버전 검증 → [관리단: JWT 인증 → RBAC 권한 → 작업 로그] → 컨트롤러 → 서비스 계층 → 모델 계층 → 캐시/데이터베이스/검색 엔진 → JSON 응답. 그림에는 캐시 히트와 캐시 미스 두 경로가 포함되어 있습니다.

### 보안 심층 방어 아키텍처

![Security Architecture](./diagrams/security-architecture-cn.svg)

**18계층 심층 방어**: L0 물리 네트워크 → L1 전송 보안 → L2 HTTP 보안 헤더 → L3 요청 검증 → L4 입력 정화 → L5 CSRF 방어 → L6 속도 제한 → L7 인증(JWT+캡차+블랙리스트+세션 제어) → L8 RBAC 인가 → L9 데이터 보호(전송 암호화+저장 암호화+ID 난독화+데이터 마스킹) → L10 감사 모니터링 → L11 규정 준수 공개.

---

## 환경 요구사항

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41(프론트 개발 시에만 필요)
- Elasticsearch >= 7.x(선택 사항, 검색 기능에 필요)

## 빠른 시작

### 1. 의존성 설치

```bash
composer install
```

### 2. 환경 변수 설정

환경 변수를 복사하고 수정합니다(선택 사항, 설정하지 않으면 `config/*.php`의 기본값 사용):

```bash
cp .env.example .env
```

주요 설정 항목:

| 환경 변수 | 설명 | 기본값 |
|---------|------|--------|
| `JWT_SECRET` | JWT 서명 키 | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids 솔트 | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 암호화 키 | 32바이트 기본값 |
| `SNOWFLAKE_DATACENTER_ID` | 데이터센터 ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | 작업자 노드 ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES 주소 | `http://localhost:9200` |

**운영 환경에서는 모든 키를 랜덤 문자열로 반드시 변경하세요.**

### 3. 데이터베이스 초기화

**방식 1: Web 설치 마법사(권장)**

서비스 시작 후 `http://localhost:8788/install`에 접속하여 안내에 따라 4단계 설치를 진행합니다: 환경 점검 → 데이터베이스 설정 → 관리자 계정 → 원클릭 설치.

**방식 2: 커맨드라인 가져오기**

```bash
mysql -u root -p 데이터베이스명 < database/install.sql
```

`install.sql`은 29개의 마이그레이션 파일이 병합된 것으로, 전체 163개 테이블 구조와 시드 데이터를 포함합니다.

**방식 3: Docker 환경**

```bash
docker-compose exec app mysql -h mysql -u root -p < database/install.sql
```

### 4. 서비스 시작

```bash
php start.php start
```

기본적으로 `http://0.0.0.0:8788`에서 수신합니다.

### 5. 프론트엔드 시작(선택 사항)

**Flutter 관리 백오피스(Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web(PC 관리 백오피스 스타일)
```

**HarmonyOS 클라이언트(모바일):**

DevEco Studio로 `apps/harmonyos/` 디렉터리를 열고 실기기 또는 에뮬레이터에 연결해 실행합니다.

### 6. Docker Compose 원클릭 배포(운영 환경 권장)

프로젝트는 Nginx, PHP (webman app), MySQL, Redis, Elasticsearch 5개 서비스를 포함한 완전한 Docker 오케스트레이션을 제공합니다.

```bash
# 1. Docker 환경 변수 설정
cp .env.docker .env

# 2. 모든 서비스 시작
docker-compose up -d

# 3. 데이터베이스 초기화(app 컨테이너에서 실행)
docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. 접속
# http://localhost:8788  (webman)
# http://localhost:8080  (Nginx 리버스 프록시)
```

- `Dockerfile`: `php:8.3-cli` 기반의 PHP 8.3 + OPcache + Composer
- `docker-compose.yml`: 5개 서비스 오케스트레이션, 네트워크 격리, 데이터 볼륨 영속화
- `.env.docker`: Docker 환경 전용 환경 변수

## 데이터베이스 규약

- **테이블 접두사**: `erp_`
- **기본키**: 모든 테이블 기본키는 `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT 금지**
- **ID 생성**: 기본키 ID는 애플리케이션 계층 `SnowflakeService::generate()`가 생성, 분산 고유
- **필수 필드**: 모든 테이블은 `id`, `created_at`, `updated_at`을 반드시 포함
- **소프트 삭제**: 소프트 삭제가 필요한 테이블에 `deleted_at DATETIME DEFAULT NULL` 추가
- **민감 필드**: 휴대폰 번호, 이메일, 주민등록번호 등은 `encryptable` 플러그인으로 자동 암·복호화, DB 필드는 `VARCHAR(500)`에 암호문 저장

## API 규약

### API 문서

프로젝트는 hg/apidoc으로 인터페이스 문서를 자동 생성하며 `/apidoc`에서 확인할 수 있습니다.

- 관리단 인터페이스 (Admin): 25개 모듈 그룹, 완전한 요청 파라미터와 응답 구조 포함
- 클라이언트 인터페이스 (Service API): 인증/캡차/상품 3개 그룹
- 모든 인터페이스에 JWT 인증, API 버전, 국제화 등 전역 요청 헤더 표기

### 통일 응답 형식

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### 업무 오류 코드

| 오류 코드 | 의미 | 설명 |
|-------|------|------|
| `0` | 성공 | |
| `400` | 요청 파라미터 오류 | |
| `401` | 미로그인(Token 무효 또는 만료) | |
| `403` | 권한 없음 / 보안 차단 | RBAC 인가 실패 / SecurityFilter 공격 탐지 |
| `404` | 리소스 없음 | |
| `422` | 파라미터 검증 실패 | |
| `413` | 요청 본문 과다 | SecurityFilter 트리거, 10MB 초과 |
| `405` | 요청 메서드 허용 안 됨 | SecurityFilter 트리거, GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용 |
| `415` | 지원하지 않는 미디어 타입 | SecurityFilter 트리거, Content-Type이 JSON 아님 |
| `429` | 요청이 너무 빈번함 | RateLimit 트리거 / 계정 잠금(5회 로그인 실패 시 15분 잠금) |
| `500` | 서버 내부 오류 | |

### 국제화

요청 헤더 `Accept-Language`로 언어 자동 전환(zh-CN → 중국어, en → English), 기본값은 중국어.

### ID 처리

- **요청/응답의 ID**: hashids로 암호화된 문자열, 실제 DB ID 노출 안 함
- **인터페이스 경로**: `GET /admin/user/{hashid}` — 경로의 `{id}`는 hashid 문자열
- **DB 저장**: BIGINT 원값, snowflake로 생성

### API 버전

API 버전은 요청 헤더로 제어되며 **URL에 표시되지 않습니다**:

```http
API-Version: v1
```

- 버전 번호 미포함 시 기본 `v1` 사용
- 지원하지 않는 버전은 `400 Bad Request` 반환
- 새 버전 추가 시 `app/api/{version}/controller/` 디렉터리 생성과 미들웨어 등록만 하면 됨

### 속도 제한

Redis 슬라이딩 윈도우 알고리즘 기반, 기본 60회/분/IP/라우트. 민감 인터페이스는 더 엄격:
- 로그인: 10회/분
- 회원가입: 5회/분(기본 꺼짐, `REGISTRATION_ENABLED=1`로 켬)

응답 헤더에 `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` 포함. 초과 시 429와 함께 `Retry-After` 반환.

### 미들웨어 아키텍처

전역 미들웨어는 모든 요청에 순서대로 적용됩니다:

```
Locale（Accept-Language 自动检测，设置语言环境）
  → Cors（跨域预处理 + 响应头）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ApiVersion（API 版本校验，/api 路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health`, `/api/docs`, `/install`는 공개 엔드포인트로 `Locale → Cors → SecurityFilter → RateLimit`만 거칩니다.

보안 강화:
- **계정 잠금**: 연속 5회 로그인 실패 시 계정이 자동으로 15분 잠금, 잠금 기간 중 로그인은 429 반환
- **동시 세션 제한**: 동일 사용자 최대 3개 유효 토큰, 초과 시 가장 오래된 토큰이 자동으로 블랙리스트에 추가
- **security.txt**: `GET /.well-known/security.txt`가 RFC 9116 표준 보안 연락 정보 제공
- **Nginx 보안 설정**: `docs/nginx-security.conf` 참고, 완전한 리버스 프록시 보안 강화 예제 제공

### 인증

로그인과 회원가입은 먼저 **클릭 캡차** 검증을 통과해야 합니다:

1. 클라이언트가 `POST /api/captcha/generate`로 캡차 이미지(base64 PNG)와 텍스트 대상 목록 획득
2. 사용자가 그림의 해당 텍스트 위치를 순서대로 클릭, 클릭 좌표 `[{x, y}, ...]` 수집
3. 로그인 시 `captcha_key`와 `clicks`를 함께 제출, 서버는 캡차 검증 후 자격 증명 검증

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

관리단 이후 인터페이스는 JWT 인증 필요:

```http
Authorization: Bearer <token>
```

로그인 성공 후 access_token 반환(유효 기간 2시간); refresh_token도 반환(유효 기간 14일).

로그아웃 시 토큰이 Redis 블랙리스트에 추가되어 유효 기간 내 재사용 불가. POST /admin/profile/logout

### 민감 작업 2차 확인

사용자, 역할, 권한 삭제 등 민감 작업은 요청 본문에 현재 로그인 사용자의 `password`를 전달하여 신원을 2차 확인해야 합니다:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 목록

전체 인터페이스 목록(공개 인터페이스 / 관리단 인터페이스 / 업무 인터페이스 / 클라이언트 인터페이스)은 별도 문서로 이동했습니다:

→ [API 참조 문서](API.md)

## 프론트엔드 설명

### Flutter 관리 백오피스(PC 스타일)

- **레이아웃**: 사이드바(접이식 64px/240px) + 상단 바 + 콘텐츠 영역, 반응형 3개 중단점(모바일/태블릿/데스크톱)
- **페이지**: 로그인, 대시보드, 사용자 관리, 역할 권한, 시스템 설정, 작업 로그, 개인 센터
- **상태 관리**: GetX(`ApiService` 싱글턴 + `AuthService` 토큰 영속화)
- **대시보드**: 통계 카드, 추세 꺾은선 그래프(fl_chart), 파이 차트, 최근 작업 로그
- **내보내기**: Excel/PDF 내보내기, PDF는 제거 불가한 저작권 정보 포함
- **일괄 작업**: 다중 선택 일괄 삭제, 일괄 활성/비활성화
- **테마**: Material 3 라이트/다크 이중 테마

### HarmonyOS 모바일

- **페이지**: 로그인, 대시보드, 사용자 목록/상세, 개인 센터
- **인증**: JWT Bearer + 401 시 자동 무감지 토큰 갱신, 갱신 실패 시 로그인 페이지 자동 리다이렉트
- **저장**: 토큰은 AppStorage로 관리

## 개발 규약

- 전역 함수/클래스 참조에 앞 `\`를 붙이지 않고 통일적으로 `use`로 임포트
- 모든 PHP 파일 헤더는 저작권 선언 필수 포함
- 모든 설정 파일은 중국어 주석 설명 필수 포함
- DB 기본키는 애플리케이션 계층 snowflake가 생성해야 하며 자동증가 금지
- API 계층의 모든 파라미터와 응답의 ID는 hashids로 암·복호화해야 함
- AdminPermission 미들웨어는 Redis로 사용자 권한을 캐시(TTL=60s), N+1 조회 병목 제거

## 배포

### Docker Compose(권장)

프로젝트 루트에 `docker-compose.yml` 제공, 5개 서비스 오케스트레이션:

| 서비스 | 이미지 | 포트 |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | 로컬 `Dockerfile` 빌드 | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP 이미지는 `Dockerfile`로 빌드, 기본 이미지 `php:8.3-cli`, OPcache 활성화.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions 지속적 통합 파이프라인: `.github/workflows/ci.yml`

- PHP 문법 검사 (`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석 (`flutter analyze`, CI 포함, 활성화 중 — `.github/workflows/ci.yml`의 flutter job 참고)

### 데이터베이스 백업

`database/backup/` 디렉터리:

- `backup.sh` — mysqldump + gzip 백업, 30일 전 백업 자동 정리
- `restore.sh` — 인터랙티브 복원, 사용 가능한 백업 목록 제공

### Nginx 보안 설정

운영 배포 시 `docs/nginx-security.conf`를 참고하여 리버스 프록시 보안 강화를 구성하세요.

## 오픈소스는 쉽지 않습니다. 응원해 주세요

| 위챗 | 알리페이 |
|:---:|:---:|
| ![위챗](./images/weixinpay.png "위챗") | ![알리페이](./images/alipay.png "알리페이") |

### 해외 송금(Global Bank Transfer)

**수취인 정보**

- 수취인 이름: WANG KEXUN
- 수취 계좌 번호: 881015918251

**수취 은행**

- ZA Bank SWIFT Code: AABLHKHHXXX
- 은행 이름: ZA Bank Limited
- 은행 번호: 387
- 은행 주소: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**해외 송금 중계 은행(필요 시)**

> 이는 중계(intermediary) 은행 정보로 수취 은행 정보가 아닙니다. 송금 은행에 제공 필요 여부를 문의하세요.

- 홍콩달러, 위안화, 미국달러 송금: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, 은행 번호 006, Hong Kong Branch 지점, 지점 번호 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- 기타 통화 송금: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### 암호화폐 후원 (Crypto Donation)

이 프로젝트가 도움이 되셨다면, QR 코드를 스캔하여 후원해 주세요. 감사합니다!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
