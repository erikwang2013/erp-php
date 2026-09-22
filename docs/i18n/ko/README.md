# 오픈ERP 시스템 (open-erp)

webman v2 + Flutter 기반의 풀스택 ERP 시스템.

<div align="center"><img src="images/mascot.svg" alt="open-erp 문어 마스코트" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | 한국어 | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [버전 비교](EDITIONS.md) | [아키텍처 설계도](ARCHITECTURE.md) | [시스템 아키텍처](#시스템-아키텍처) | [설계 문서](DESIGN.md) | [보안 아키텍처](SECURITY.md) | [API 참조](API.md) | [기능 매뉴얼](FUNCTIONS.md)

## 프로젝트 소개

open-erp는 중소기업을 위한 **오픈소스 풀스택 ERP 시스템**으로, 구매·판매·재고 관리, 재무 회계, 생산 제조(BOM/MRP/공정 실적 등록/생산능력 부하), CRM, 승인 워크플로, 인사 관리, 메시지 알림, 커스텀 리포트 등 완전한 업무 도메인을 커버합니다. 백엔드는 webman v2 + MySQL 8.0 기반(테이블 접두사 `erp_`, Snowflake 전역 고유 기본키)이며, 관리단은 Angular 22(`apps/angular/`), React 19 + Vite(`apps/react/`), Flutter 3.x Web(`apps/flutter/`) 세 가지 구현을 제공하고, 모바일은 HarmonyOS 네이티브 클라이언트(`apps/harmonyos/`)를 갖춥니다.

시스템은 **전표 기반·자동 연동**을 핵심 설계로 삼습니다: 업무 전표 승인 시 재고 변동, 매출채권·매입채무 생성, 원가 집계가 자동으로 트리거되고, 승인 워크플로와 메시지 알림이 모든 핵심 전표에 걸쳐 동작합니다. MRP는 판매 오더와 BOM을 근거로 자재 소요량을 계산해 구매/생산 제안을 생성하여, 판매 접수부터 구매 입고까지, 생산 계획에서 재무 결산까지의 종단 간 업무 폐루프를 형성합니다.

## 프로젝트 설명

- **정확한 십진 연산**: 금액·수량·가중치 등 업무 수치는 bcmath 십진 연산을 기준으로 하며, 이동가중평균 원가·매출채권·매입채무 상계·각종 리포트 출력이 문자열 정밀도로 처리되어 부동소수점 오차가 없습니다
- **엔터프라이즈 보안 베이스라인**: JWT 토큰 + RBAC 메서드 단위 인가, 심층 방어(L0–L12 계층 전경 + 35종 공격 탐지기 + 7계층 미들웨어 체인, XSS/SQL 인젝션/CSRF/속도 제한/CSP 등), 민감 필드 저장 암호화와 인터페이스 전송 암호화, 작업 감사 전량 기록
- **구성화 역량**: 다중 노드 승인 워크플로(비주얼 프로세스 디자이너 캔버스 포함), 전표 인쇄 템플릿 엔진(플레이스홀더 렌더링 + dompdf PDF 출력 + QR 라벨), 고객 신용 한도 실시간 차단, 로트/시리얼 번호 전 구간 정·역방향 추적
- **데이터 추적성**: 업무 흐름이 건별로 기록되고, 재고 로트와 시리얼 번호가 입고→사용→출고→추적 전 생애주기를 관통하며, 원가 계산이 전표 행 단위까지 내려갑니다
- **배포 친화**: Docker Compose v2 원클릭 기동(MySQL/Redis/Elasticsearch), 로컬에서 `composer install`만으로도 바로 실행 가능
- **국제화**: 13개 로케일(zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), 백엔드 메시지와 Angular/React 두 관리단 화면 전체를 커버하고, 프론트엔드 사전은 로케일별 지연 로딩, README는 별도로 12개 언어 문서 제공

## 기능 목록

| 업무 도메인 | 기능 | 설명 |
|--------|------|------|
| 🔐 인증 | 로그인/회원가입/토큰 갱신/로그아웃 | 클릭 캡차 + JWT + 블랙리스트 |
| | 계정 잠금 | 5회 실패 시 15분 잠금 |
| | 동시 세션 제한 | 동일 사용자 최대 3개 유효 토큰 |
| 📊 대시보드 | 경영 개요 + 판매/재고/재무/OMS/WMS/TMS 6개 보드 | Redis 캐시 5분 |
| 👥 사용자 관리 | CRUD + 일괄 삭제/활성·비활성화 | 소프트 삭제 + 비밀번호 2차 확인 |
| | Excel 일괄 가져오기 | 행 단위 검증 + 오류 보고서 |
| 🔒 역할 권한 | 역할 CRUD + 권한 트리 | RBAC method.path 단위 인가 |
| ⚙ 시스템 설정 | 키-값 CRUD | 그룹 관리 |
| 📋 감사 로그 | 로그 조회 + 출처 단말 감지 | 8개 플랫폼 자동 인식 |
| 📁 파일 관리 | 업로드/Excel 내보내기/PDF 내보내기 | 민감 데이터 자동 마스킹 |
| 🛡 보안 방어 | 35종 공격 탐지 + 7계층 미들웨어 체인 | XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF/속도 제한/CSP... |
| 🏥 운영 | 헬스 체크/metrics/API 문서/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 상품 관리 | 상품 마스터/SKU/다중 규격/다중 단위/분류/브랜드/가격 정책 | 다단계 분류 트리 + 다중 단위 환산 |
| | 창고·로케이션 | 다중 창고·다중 로케이션 관리 |
| | 공급업체/고객 마스터 | 담당자/은행 계좌/신용 한도 |
| 📥 구매 관리 | 신청→오더→입고→반품→정산 | 완전한 구매 프로세스 + 승인 |
| | 소싱 구매(RFQ → 견적 → 낙찰 후 오더 전환) | 다중 공급업체 견적 비교, 견적은 RFQ 전체 명세를 포함해야 함, 낙찰 건은 클릭 한 번으로 구매 오더 전환 |
| | 공급업체 평가 | 총점 0–100 자동 등급(A ≥ 90 / B ≥ 70 / C) + 평가 항목 JSON + 평가자 기록 |
| 📤 판매 관리 | 견적→오더→출하→반품→정산 | 견적→오더 전환 + 판매 마진 |
| | 고객 신용 통제 | 한도/결제 기간/동결 관리 + 주문·출하 한도 초과·기간 초과 차단 |
| 🏗 재고 관리 | 실시간 재고/로트/시리얼 번호/이동/실사/경고 | 이동가중평균 원가 계산 |
| 💰 재무 관리 | 매출채권·매입채무/수금·지급/일계부/비용 정산/손익계산서/고정자산/세금/다중 통화/예산/원가·이익센터 | 매출채권·매입채무 자동 생성 + 대체 + 종합 재무 관리 |
| | 다중 조직 + 연결 재무제표 | 다중 회사/회계 장부 관리 + 상계 분개(지분법/원가법) |
| | 재고·생산 원가 계산 | 생산 자재 출고 → 노무비·제조경비 집계 → 완성품 원가 → 원가 차이 이월 |
| | 어음 + 은행 거래 대사 | 어음 대장 + 은행 거래내역서 가져오기 자동 대사 |
| | 매입 세금계산서 풀 + 전자발행 | 매입 세금계산서 관리 + 발행 채널(어댑터 + Mock 채널) |
| 🤝 CRM | 고객/담당자/팔로우 기록/마케팅 캠페인/서비스 티켓/분석 리포트/세일즈 퍼널/공해 풀/견적/계약 | 고객 전 생애주기 관리 |
| | 멤버 가치 엔진 | 선불 충전/포인트/카드 쿠폰 멤버십 운영 |
| ✅ 승인 워크플로 | 워크플로 정의/승인 제출/승인/거부/철회/내 승인 | 다중 노드 승인 프로세스 엔진 |
| | 비주얼 워크플로 디자이너 | 캔버스 구성 노드/분기/반려 복귀 연결선 + 승인 엔진 재사용 |
| 🔔 메시지 알림 | 알림 목록/읽음 표시/안읽음 개수/전체 읽음 | 실시간 메시지 푸시와 상태 추적 |
| | 멀티채널 알림 | SMS/이메일 채널 드라이버(Mock 채널 + 로그 + 재시도) |
| 📐 프로젝트 관리 | 프로젝트/작업/공수 기록 | 프로젝트 진행 추적과 리소스 관리 |
| | 프로젝트 원가·예산 | 공수×요율 → 프로젝트 원가 집계 + 예산 편차 |
| 👤 인사 관리 | 부서/사원/직위/근태/휴가/급여 | 종합 인사 관리 |
| | 채용/성과/교육/4대보험 | 채용 퍼널 + KPI/360 평가 + 교육 학점 + 보험 기준 규칙·급여명세서 |
| 🏭 생산 제조 | BOM/생산 오더/공정 라우팅/작업장/MRP | 자재소요계획과 생산 실행 |
| | 공정 실적 등록/도급 임금/외주 정산 | MES 공정 실행 계층 + 외주 오더 출고·정산 |
| | 생산능력 부하 분석 | 작업장 캘린더 + 조능력 부하 리포트 |
| | 로트/시리얼 번호 이력 추적 | 정·역방향 추적 체인 + 유효기간 임박 경고 |
| 📈 커스텀 리포트 | 리포트 템플릿/데이터셋/필드/필터/실행/정기 스케줄 | 시각화 리포트 빌더 |
| 📋 주문 관리(OMS) | 멀티채널 주문/이행 오케스트레이션/재고 예약/할당/취소/RMA 반품·교환 | 주문 전 생애주기 관리 |
| 🏗 창고 관리(WMS) | 구역·로케이션/ASN/입고/상재/웨이브/피킹/패킹/출고 | 완전한 창고 작업 프로세스 |
| 🚚 운송 관리(TMS) | 운송사/서비스/운임/운송장/물류 트래킹/운임 인보이스 | 다중 운송사 운임 비교 + 트래킹 |
| 🛠 설비 관리(EAM) | 설비 마스터/보전 계획/수리 작업 오더/예비 부품 | 설비 전 생애주기 관리 |
| | 스캔 점검 폐루프 | QR 스캔 점검, 이상 발생 시 수리 작업 오더 자동 연동 |
| 🌐 플랫폼·개방 | API 경로 버전화 | 관리자 /admin/v1 · 클라이언트 /api/v1 · 오픈 /open/v1(버전 요청 헤더 없음) |
| | 문서 출력 템플릿 엔진 | 플레이스홀더 렌더링 + dompdf PDF + QR 코드 라벨 |
| | 폼 커스텀 필드 | 마스터 테이블 custom_fields JSON 확장 + 검증 |
| | 다중 테넌트 아키텍처 | erp_tenant 테넌트 + TenantScope 요청 컨텍스트 + 만료 과금(미들웨어 seam 예약·미등록) |

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
| 검색 엔진 | Elasticsearch | `webman-scout`로 쓰기/삭제 시 인덱스 자동 동기화(선택 컴포넌트, 문서 내 "전문 검색 엔진" 절 참조) |
| 관리단 프론트 A | Angular 22 | config 기반 리소스 페이지, `ResourcePage` 렌더링 엔진(`apps/angular/`) |
| 관리단 프론트 B | React 19 + Vite | Angular와 동일한 소스의 config 기반 + 스타일 토큰(`apps/react/`) |
| 관리단 프론트 C | Flutter 3.x | Web은 PC 관리 백오피스 스타일(`apps/flutter/`) |
| 모바일 | HarmonyOS ArkTS | 하모니OS 네이티브 클라이언트(`apps/harmonyos/`), 휴대폰/태블릿/2in1 지원 |

### 전문 검색 엔진(선택 사항)

인덱스 동기화는 `erikwang2013/webman-scout`로 구현합니다(모델에 `Searchable` trait을 추가하면 저장 시 인덱스가 자동 동기화). **Elasticsearch**와 **OpenSearch**를 모두 지원하며 하나를 선택합니다.

- **인덱스 범위**: `app/model/` 아래 224개 모델이 모두 `Searchable`을 사용하며, 쓰기·소프트 삭제 시 `ModelObserver`를 통해 동기화합니다. AdminUser, Customer, Product, Supplier 4개 모델은 `toSearchableArray()`로 화이트리스트 필드를 정의하고, 나머지는 기본(전체 행)으로 인덱싱합니다.
- **엔진을 사용할 수 없어도 업무 쓰기에 영향이 없습니다**(실측: 드라이버를 도달 불가능한 포트로 지정해도 `save()`는 성공하며 연결 타임아웃 1회만큼 시간이 늘어남) — 검색 엔진은 선택 컴포넌트로, 미설치 상태에서도 전체 업무가 동작합니다.
- **범위 설명**: 이 프로젝트는 현재 **인덱스 동기화만** 연결되어 있으며(쓰기/소프트 삭제 시 동기화), 검색 인터페이스나 검색 화면은 제공하지 않습니다. 검색이 필요하면 Scout 쿼리 API를 직접 호출하세요(관리단 목록 페이지 필터는 백엔드 `where` 쿼리로 처리하며 검색 엔진을 거치지 않습니다).

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
| `erikwang2013/apidoc-php` | API 문서 자동 생성 | 어노테이션 방식 인터페이스 문서, 관리단/클라이언트 그룹 분리 |

## 국제화

시스템은 **13개 로케일**을 지원합니다: `zh`(기본), `en`, `ja`, `ko`, `de`, `fr`, `es`, `pt`, `ru`, `ar`, `hi`, `bn`, `id`.

| 계층 | 사전 위치 | 규모 |
|---|---------|------|
| 백엔드 메시지 | `resource/translations/{로케일}/` | 13개 로케일 디렉터리: `zh_CN` 565개, 나머지 11개 로케일 각 544개, `en` 30개(기준: `common`/`modules`/`validation` 세 파일의 리프 항목. `validation.php`의 `attributes` 필드 라벨은 포함하고 그 그룹 키는 미포함) |
| Angular 관리단 | `apps/angular/src/app/core/zh-*.ts`(소스 사전 `zh-en/`, 4개 슬라이스 병합) | 소스 사전 1456키 × 11개 로케일(각 로케일 키 수는 소스 사전과 1:1) |
| React 관리단 | `apps/react/src/lib/i18n/zh*.ts` | 소스 사전 1451키 × 11개 로케일 |

- **백엔드 「영어가 곧 key」**: 백엔드 메시지 키 자체가 영어 문구이므로 `en`은 프레임워크 규칙명 등 소량의 매핑만 유지하며 전체 사전이 필요 없습니다
- **로케일별 지연 로딩**: 프론트엔드 12개 사전이 각각 독립 chunk로 패키징되어 언어 전환 시 필요할 때만 로드되므로 첫 화면 용량을 늘리지 않습니다
- **전환 진입점**: 상단 바의 독립 globe 아이콘 + 개인 센터 드롭다운(Angular/React 양단 동일)
- **인터페이스 계층**: 요청 헤더 `Accept-Language` 자동 감지(zh-CN → 중국어, en → English, 나머지 로케일은 목록 기준 매칭), 기본값 중국어
- **생성기**: `scripts/gen-be-locales.mjs`(백엔드), `scripts/gen-fe-locales.mjs --app angular|react`(프론트엔드), 중단점 재개 지원

## 프로젝트 구조

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (16 个)
│   ├── api/v1/controller/      # 客户端 API（版本置于路径 /api/v1，无版本请求头）
│   ├── controller/             # 业务模块控制器 (139 个，23 域)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (8 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算/询价/报价/供应商评估 (8 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (6 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心/票据/对账/发票 (28 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批/流程设计器 (3 个)
│   │   ├── notification/       # 站内通知/渠道发送 (2 个)
│   │   ├── project/            # 项目/任务/工时/成本 (4 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资/招聘/绩效/社保/培训 (9 个)
│   │   ├── manufacturing/      # BOM/工单/工艺/工作站/MRP/报工/委外/成本/产能 (13 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── print/              # 打印模板引擎 (1 个)
│   │   ├── retail/             # 会员储值/积分/卡券 (2 个)
│   │   ├── platform/           # 多租户/自定义字段 (2 个)
│   │   ├── quality/            # 质检 (5 个)
│   │   ├── eam/                # 设备/保养/维修/备件/点检 (5 个)
│   │   ├── bi/                 # 商业智能 (3 个)
│   │   ├── dms/                # 文档管理 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   ├── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   │   └── open/               # 开放平台接口 (1 个)
│   ├── service/                # 业务逻辑层 (64 个)
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 224 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 11 个中间件（ApiVersion 已移除，版本走路径）
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── angular/                # Angular 22 管理端（config 驱动资源页，ng serve :4200）
│   ├── react/                  # React 19 + Vite 管理端（Vite :5173）
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/erikwang2013/apidoc/ # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（227张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（<!-- stats:test_files=113 --> 个测试文件，<!-- stats:tests=1051 --> 个测试方法，<!-- stats:assertions=5047 --> 条断言）
├── resource/
│   └── translations/           # 13 语种后端消息词典 (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # 中文翻译 (565 条)
│       ├── en/                 # 英文即 key，仅框架规则名等 30 条
│       └── ja|ko|de|.../       # 其余 11 语种各 544 条（生成器 scripts/gen-be-locales.mjs）
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

**23대 업무 도메인, 227개 데이터 테이블, 159개 컨트롤러**: 인증 보안, 대시보드, 시스템 관리, 보안 방어, 운영 모니터링, 상품 관리, 구매, 판매, 재고, 재무(14개 하위 모듈), CRM(10개 하위 모듈), 승인 워크플로, 메시지 알림, 프로젝트 관리, 인사 관리, 생산 제조(MRP), 커스텀 리포트, 주문 관리(OMS), 창고 관리(WMS), 운송 관리(TMS), 품질 관리(QMS), 설비 관리(EAM), 문서 관리(DMS), BI 대시보드를 포함합니다.

### 요청 생애주기

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**클라이언트에서 데이터베이스까지의 완전한 요청 경로**: 클라이언트(Angular/React/Flutter/하모니OS) → Nginx SSL 종료 → CORS 처리 → 보안 필터 → 속도 제한 → [관리단: JWT 인증 → RBAC 권한 → 작업 로그] → 컨트롤러 → 서비스 계층 → 모델 계층 → 캐시/데이터베이스/검색 엔진 → JSON 응답. 그림에는 캐시 히트와 캐시 미스 두 경로가 포함되어 있습니다. (API 버전은 URL 경로에 통합되어 별도의 버전 검증 단계가 없으며, 언어는 `app/common/I18n.php`가 `Accept-Language`에서 파싱합니다.)

### 보안 심층 방어 아키텍처

![Security Architecture](./diagrams/security-architecture-cn.svg)

**심층 방어 전체상(L0–L12)**: L0 물리 네트워크 → L1 전송 보안 → L2 HTTP 보안 헤더 → L3 요청 검증 → L4 입력 정화 → L5 CSRF 방어 → L6 속도 제한 → L7 인증(JWT+캡차+블랙리스트+세션 제어) → L8 RBAC 인가 → L9 데이터 보호(전송 암호화+저장 암호화+ID 난독화+데이터 마스킹) → L10 감사 모니터링 → L11 규정 준수 공개 → L12 관측성(X-Trace-Id 분산 추적+비즈니스 지표+감사 강화). 실행 가능한 7계층 미들웨어 체인은 `docs/SECURITY.md`, 35종 공격 탐지기는 `config/plugin/erikwang2013/security-php/app.php`를 참조하세요.

---

## 환경 요구사항

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41(프론트 개발 시에만 필요)
- Node >= 22.22.3(Angular/React 관리단 개발 시에만 필요, Angular CLI 22의 `engines` 하한)
- Elasticsearch >= 7.x 또는 OpenSearch >= 2.x(선택 사항, 인덱스 동기화에 필요. 미설치 시 업무 읽기/쓰기에 영향 없음)
- DevEco Studio(선택 사항, HarmonyOS 클라이언트 빌드 시에만 필요. 명령줄 대응은 `hvigorw assembleHap`)

## 기본 로컬 도메인

이 프로젝트는 기본적으로 로컬 도메인 **`http://erp.test`** 를 사용합니다(Flutter 클라이언트의 기본 API 주소이자 백엔드 Web 진입점 규약. HarmonyOS 클라이언트 기본값은 에뮬레이터 호스트 머신 `http://10.0.2.2:8788`).

- **로컬 접속**: hosts에 `127.0.0.1 erp.test` 한 줄을 추가하고, 웹 서버/리버스 프록시를 백엔드 수신 포트(기본 `8788`, `.env`의 `APP_HTTP_PORT` 참조, 설치 마법사 또는 `.env`에서 변경 가능. WebSocket 기본값 `8282`는 `APP_WS_PORT`)로 향하게 합니다.
- **배포 도메인 변경**:
  - Flutter 빌드 주입: `flutter build web --dart-define=API_BASE_URL=https://사용할_도메인`
  - HarmonyOS: `apps/harmonyos/entry/src/main/ets/utils/Config.ets`의 `BASE_URL` 편집(읽기 전용 상수, 기본값 `http://10.0.2.2:8788`)
  - 에뮬레이터 디버깅 시 `http://10.0.2.2:8788`(호스트 머신 접속)로 임시 변경 가능
- 모든 인터페이스 버전은 이미 경로에 위치하므로(`/admin/v1`, `/api/v1`, `/open/v1`), 클라이언트는 루트 주소만 설정하면 됩니다.

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
| `JWT_SECRET_KEY` | JWT 서명 키 | `.env.example`에 미리 설정된 48자 랜덤 값 |
| `HASHIDS_SALT` | Hashids 솔트 | `.env.example`에 미리 설정된 48자 랜덤 값 |
| `ENCRYPTION_KEY` | API 암호화 키 | `.env.example`에 미리 설정된 32자 랜덤 값(32바이트는 AES-256의 필수 요건) |
| `SNOWFLAKE_DATACENTER_ID` | 데이터센터 ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | 작업자 노드 ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES 주소 | `http://localhost:9200` |

**운영 환경 주의**: 키가 없거나 비어 있거나 약한 플레이스홀더 값(`change-me` / `xxx` 등)이면 시작 시 `env_required` / `env_crypto_key`가 거부합니다(조용한 폴백 없음). `ENCRYPTION_KEY`는 길이 하드 검증도 있으며 AES-256은 32바이트가 필수이므로 불일치 시 시작 오류가 발생합니다.

### 3. 데이터베이스 초기화

**방식 1: Web 설치 마법사(권장)**

서비스 시작 후 `http://localhost:8788/install`에 접속하여 안내에 따라 4단계 설치를 진행합니다: 환경 점검 → 데이터베이스 설정 → 관리자 계정 → 원클릭 설치. 데이터베이스 설정 단계에서 **데모 데이터 가져오기** 체크박스를 선택할 수 있습니다(상품/규격/SKU/고객/공급업체, ID 범위 41…, 범위별 삭제 가능). 기본값은 꺼짐 — 프로덕션에서는 선택하지 마세요.

**방식 2: 커맨드라인 가져오기**

```bash
mysql -u root -p 데이터베이스명 < database/install.sql
```

`install.sql`은 단일 파일 완전 베이스라인으로, 전체 227개 테이블 구조와 시드 데이터를 포함합니다.

**방식 3: Docker 환경**

```bash
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
# 2. 플레이스홀더 키를 랜덤 값으로 대체 (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. 모든 서비스 시작
docker compose up -d

# 4. 데이터베이스 초기화(app 컨테이너에서 실행)

# 5. 접속
# http://localhost:8788  (webman)
# http://localhost:8080  (Nginx 리버스 프록시)
```

- `Dockerfile`: `php:8.3-cli` 기반의 PHP 8.3 + OPcache + Composer
- `docker-compose.yml`: 5개 서비스 오케스트레이션, 네트워크 격리, 데이터 볼륨 영속화
- `.env.docker`: Docker 환경 전용 환경 변수

## 사용 방법

### 1. 로그인

첫 사용 시 웹 설치 프로그램 `http://localhost:8788/install`을 열어 설치를 완료하고 관리자 계정을 만듭니다. 설치가 완료되면 콘솔을 열고 자격 증명을 입력한 후 클릭 캡차를 통과해 로그인합니다.

### 2. 기능 탐색

로그인 후 사이드바에서 각 모듈로 이동합니다: 대시보드, 상품, 구매, 판매, 재고, 재무, CRM, 승인 워크플로, 알림, 프로젝트, 인사, 제조, 사용자 정의 보고서, OMS/WMS/TMS, BI 대시보드, 시스템 관리(사용자/역할/설정/로그). 사이드바는 데스크톱에서 고정, 모바일에서는 드로어로 접힙니다.

### 3. 권한과 보안

- 기능과 API는 RBAC로 제어되며 권한 없는 메뉴와 인터페이스는 접근 불가(403)
- 사용자/역할 삭제 같은 민감한 작업은 요청 본문에서 현재 비밀번호 확인이 필요
- 로그아웃 후 토큰은 즉시 블랙리스트에 등록됩니다

### 4. 다국어

요청 헤더 `Accept-Language`로 자동 전환되며 13개 로케일을 지원합니다(`zh` 기본, 그 외 `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`). Angular/React 관리단에는 별도로 상단 바 globe 아이콘과 개인 센터 드롭다운 전환이 있습니다. 자세한 내용은 [국제화](#국제화) 참고.

## 데이터베이스 규약

- **테이블 접두사**: `erp_`
- **기본키**: 모든 테이블 기본키는 `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT 금지**
- **ID 생성**: 기본키 ID는 애플리케이션 계층 `SnowflakeService::generate()`가 생성, 분산 고유
- **필수 필드**: 모든 테이블은 `id`, `created_at`, `updated_at`을 반드시 포함
- **소프트 삭제**: 소프트 삭제가 필요한 테이블에 `deleted_at DATETIME DEFAULT NULL` 추가
- **민감 필드**: 휴대폰 번호, 이메일, 주민등록번호 등은 `encryptable` 플러그인으로 자동 암·복호화, DB 필드는 `VARCHAR(500)`에 암호문 저장

## API 규약

### API 문서

프로젝트는 erikwang2013/apidoc-php으로 인터페이스 문서를 자동 생성하며 `/apidoc`에서 확인할 수 있습니다.

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
- **인터페이스 경로**: `GET /admin/v1/user/{hashid}` — 경로의 `{id}`는 hashid 문자열
- **DB 저장**: BIGINT 원값, snowflake로 생성

### API 버전

API 버전은 URL 경로에 위치하며(예: `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`), **클라이언트는 버전용 요청 헤더가 전혀 필요하지 않습니다**:

- 버전화된 공개 인터페이스는 해당 버전의 컨트롤러 클래스에 직접 바인딩됩니다(`app/api/v1/controller/`)
- 새 버전 추가 시 새로운 `/api/vN` 라우트 그룹을 등록하고 컨트롤러를 `app/api/vN/`에 배치합니다
- 기존의 `v()` 동적 해석과 `ApiVersion` 요청 헤더 미들웨어는 모두 제거되었습니다

### 속도 제한

Redis 슬라이딩 윈도우 알고리즘 기반, 기본 60회/분/IP/라우트. 민감 인터페이스는 더 엄격:
- 로그인: 10회/분
- 회원가입: 5회/분(기본 꺼짐, `REGISTRATION_ENABLED=1`로 켬)

응답 헤더에 `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` 포함. 초과 시 429와 함께 `Retry-After` 반환.

### 미들웨어 아키텍처

전역 미들웨어(`config/middleware.php`)는 모든 요청에 순서대로 적용됩니다:

```
Cors(교차 출처 전처리 + 응답 헤더)
  → SecurityFilter(HTTP 메서드 제한/요청 본문 크기/Content-Type 검증/XSS/SQL 인젝션/경로 순회/명령 인젝션/CSRF 공격 차단)
  → RateLimit(Redis 슬라이딩 윈도우 속도 제한 + 계정 잠금: 5회 로그인 실패 시 15분 잠금)
  → TracingId(추적 ID)
```

라우트 그룹 미들웨어: `/admin/v1`에 `AdminAuth(JWT 인증 + 블랙리스트) → AdminPermission(RBAC 인가) → OperationLog(POST/PUT/DELETE 자동 기록, 클라이언트 유형 검출 포함)`; `/open/v1`에 `OpenApiAuth`; TMS 궤적 콜백에 `TrackingSignature`. 언어는 `app/common/I18n.php`가 `Accept-Language`에서 파싱하며 미들웨어가 아닙니다.

`/health`, `/api/docs`, `/install`는 공개 엔드포인트로 `Cors → SecurityFilter → RateLimit → TracingId`만 거칩니다.

보안 강화:
- **계정 잠금**: 연속 5회 로그인 실패 시 계정이 자동으로 15분 잠금, 잠금 기간 중 로그인은 429 반환
- **동시 세션 제한**: 동일 사용자 최대 3개 유효 토큰, 초과 시 가장 오래된 토큰이 자동으로 블랙리스트에 추가
- **security.txt**: `GET /.well-known/security.txt`가 RFC 9116 표준 보안 연락 정보 제공
- **Nginx 보안 설정**: `docs/nginx-security.conf` 참고, 완전한 리버스 프록시 보안 강화 예제 제공

### 인증

로그인과 회원가입은 먼저 **클릭 캡차** 검증을 통과해야 합니다:

1. 클라이언트가 `POST /api/v1/captcha/generate`로 캡차 이미지(base64 PNG)와 텍스트 대상 목록 획득
2. 사용자가 그림의 해당 텍스트 위치를 순서대로 클릭, 클릭 좌표 `[{x, y}, ...]` 수집
3. 로그인 시 `captcha_key`와 `clicks`를 함께 제출, 서버는 캡차 검증 후 자격 증명 검증

```http
POST /api/v1/auth/login
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

로그아웃 시 토큰이 Redis 블랙리스트에 추가되어 유효 기간 내 재사용 불가. POST /admin/v1/profile/logout

### 민감 작업 2차 확인

사용자, 역할, 권한 삭제 등 민감 작업은 요청 본문에 현재 로그인 사용자의 `password`를 전달하여 신원을 2차 확인해야 합니다:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 목록

전체 인터페이스 목록(공개 인터페이스 / 관리단 인터페이스 / 업무 인터페이스 / 클라이언트 인터페이스)은 별도 문서로 이동했습니다:

→ [API 참조 문서](API.md)

## 프론트엔드 설명

### Angular 관리단(`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200(포트는 .env의 ANGULAR_DEV_PORT)
npm run build      # tsc --noEmit + ng build, 산출물 dist/angular
npm run typecheck  # 타입 검사만 수행
```

- **Node 버전 요구사항**: Angular CLI 22의 `engines`가 **Node ≥ 22.22.3**을 요구합니다(하위 버전에서는 `ng build`가 즉시 거부됩니다).
  로컬 Node가 낮을 때는 npx로 임시 지정(이 저장소에서 가장 흔한 빌드 방식이며, CI 외에는 모두 이 방식):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  `npx`가 없는 환경(예: 이 저장소의 오프라인 검증 머신)은 CLI 내장 tsc로 타입 검사:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **개발 프록시**: `proxy.conf.js`가 `/admin` `/api` `/open` `/health` `/metrics` `/install`을
  `.env`의 `APP_HTTP_PORT`(기본 8788)로 프록시하므로 `ng serve` 시 **백엔드 주소를 따로 설정할 필요가 없습니다**
- **아키텍처**: config 기반 — `src/app/config/domains/*.ts`가 메뉴와 리소스 페이지를 선언하고, **하나의 `ResourcePage`가 모든 업무 페이지를 렌더링**(리소스 페이지 추가 ≈ 설정 객체 하나 추가, 컴포넌트 작성 불필요)
- **다국어**: 13개 로케일, 사전은 로케일별 지연 로딩(각각 하나의 chunk); 상단 바 globe 아이콘으로 전환
- **자체 점검**(모두 브라우저 불필요, `node`로 직접 실행): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### React 관리단(`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173(포트는 .env의 REACT_DEV_PORT)
npm run build      # tsc --noEmit + vite build, 산출물 dist/
```

- Angular와 동일하게 **config 기반**: `src/config/domains/*.ts`가 메뉴와 리소스 페이지를 선언하고,
  렌더링 엔진은 `src/components/ResourcePage.tsx`; 스타일 토큰은 `src/styles/tokens.css`
  (Angular 쪽 `styles/theme.less`와 동일 값)
- 언어 전환 진입점은 **개인 센터** 페이지(Angular 쪽은 상단 바 globe 아이콘 추가 제공)

### Flutter 관리 백오피스(PC 스타일, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web(PC 관리 백오피스 스타일), iOS/Android/macOS/Windows/Linux도 지원
flutter analyze          # 정적 분석(CI와 동일)
```

- **레이아웃**: 사이드바(접이식 64px/240px) + 상단 바 + 콘텐츠 영역, 반응형 3개 중단점(모바일/태블릿/데스크톱)
- **커버리지**: 22개 최상위 메뉴 항목(21개 그룹 + 독립 대시보드), 102개 라우팅 가능 페이지, 119개 페이지 파일(메뉴는 `lib/app/config/menu_config.dart`에 선언, 페이지는 `lib/app/pages/`) — 대시보드, 시스템 관리, 상품 관리, 거래처 관리, 구매 관리, 판매 관리, 재고 관리, 재무 관리, CRM, 주문 관리, 창고 관리, 운송 관리, 생산 제조, 품질 관리, 인사 관리, 프로젝트 관리, 승인 워크플로, 알림 센터, 커스텀 리포트, BI 보드, 설비 관리, 문서 관리
- **상태 관리**: GetX(`ApiService` 싱글턴 + `AuthService` 토큰 영속화)
- **대시보드**: 통계 카드, 판매 추세 꺾은선, Top 상품, 주문 상태 분포, 매출채권·매입채무 에이징, 재고 개요(fl_chart)
- **내보내기**: Excel/PDF 내보내기(`ExportService`), PDF는 제거 불가한 저작권 정보 포함
- **일괄 작업**: 다중 선택 일괄 삭제, 일괄 활성/비활성화
- **테마**: Material 3 라이트/다크 이중 테마
- **국제화**: 중국어/영어 이중 언어(`lib/l10n/app_zh.arb`가 템플릿, `flutter gen-l10n`으로 생성)

### HarmonyOS 모바일(`apps/harmonyos/`)

- **빌드**: DevEco Studio로 `apps/harmonyos/`를 엽니다. 명령줄 대응은
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (HarmonyOS SDK + command-line-tools 필요, 산출물은 `entry/build/default/outputs/default/*.hap`)
- **페이지**: `entry/src/main/resources/base/profile/main_pages.json`에 **41개 페이지가 등록되어 있으며 모두 화면에서 도달 가능**(로그인, 대시보드(KPI 카드 + 업무 그리드), 사용자 목록/상세, 역할 권한, 개인 센터, 그리고 상품/재고/구매/판매/OMS/WMS/TMS/생산/HR/승인 등 하위 시스템 페이지). 대시보드 업무 그리드가 **32개 직접 진입점**을 제공하며, 하위 시스템 상세 페이지는 목록 행 작업으로 진입합니다.
- **인증**: JWT Bearer + 401 시 자동 무감지 토큰 갱신, 갱신 실패 시 로그인 페이지 자동 리다이렉트
- **저장**: 토큰은 AppStorage로 관리
- **국제화**: 중국어/영어 이중 언어(`resources/base/element/string.json` 및 `resources/en_US/element/string.json`)
- **네트워크**: `BASE_URL`은 `entry/src/main/ets/utils/Config.ets`(읽기 전용 상수)에 정의되어 있으며 기본값은 `http://10.0.2.2:8788`(에뮬레이터에서 호스트 머신 접속)

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
# 플레이스홀더 키를 랜덤 값으로 대체 (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

GitHub Actions 지속적 통합 파이프라인: `.github/workflows/ci.yml`, 5개 작업:

| 작업 | 내용 |
|------|------|
| `php`(PHP 8.3 / 8.4 매트릭스, MySQL 8 + Redis 7 서비스 포함) | composer 검증과 보안 감사 → `php -l` → **PHPStan**(level 5 + baseline) → **PHP CS Fixer**(dry-run) → 전체 `install.sql` 가져오기 → **PHPUnit**(통합 케이스 포함) → pcov 커버리지 수집 → 커버리지 기준(전체 ≥ 4%, `app/service` ≥ 10%, 단계적 강화) |
| `flutter` | `flutter analyze` + `flutter test`(`continue-on-error: true`, 환경이 안정되면 강화 예정) |
| `docs` | `bash scripts/doc-stats.sh --check`: README와 docs의 `stats` 주석이 소스 실측 카운트(컨트롤러/서비스/모델/테이블/테스트 수 등)와 일치하는지 검증, 드리프트 시 실패 |
| `e2e` | webman 실제 서비스 기동 → 헬스 체크 → HTTP 핵심 경로 스모크 테스트 + 관리단 API 커버리지 |
| `release` | `main` push 후 위 작업이 통과하면 patch+1로 태그하고 Release 생성(아래 참조) |

> 프론트엔드 정적 검사 범위: CI는 현재 Flutter만 실행합니다. Angular/React(`tsc --noEmit`)와 HarmonyOS(`hvigorw assembleHap`)는 로컬 또는 후속 작업 추가가 필요합니다.

### 릴리스 프로세스(버전 증분)

`main`에 push하고 php / docs / e2e 검사가 모두 통과하면, `ci.yml`의 `release` 작업이 최신 tag의 **patch+1**로 새 버전 tag를 생성해 push하고(`v1.1.4` → `v1.1.5`), 이어서 같은 이름의 GitHub Release를 생성합니다(`--generate-notes`로 변경 설명 자동 생성).

- **트리거**: `main` push만 해당(PR은 트리거되지 않으며, tag push는 브랜치 필터에 맞지 않아 이 워크플로가 재귀 실행되지 않습니다)
- **멱등성**: 원격에 같은 이름의 tag 또는 release가 이미 있으면(동시 CI / 수동 tag) 자동으로 건너뛰며 오류를 내지 않습니다
- **로컬 예행**: `bash scripts/bump-version.sh --check`가 다음 버전 번호를 출력(읽기 전용, 원격에 쓰지 않음)

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
