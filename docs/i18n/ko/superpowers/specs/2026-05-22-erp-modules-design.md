# ERP 업무 모듈 설계 규격

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. 개요

기존 `service/` 시스템 관리 기반 위에 진입·출고·재고, 재무, CRM 3대 업무 도메인을 확장하여 완전한 ERP 시스템을 구축합니다.
모든 코드는 `service/app/` 아래 단일 배포, 모듈별 디렉토리 계층화.

### 1.1 단계 계획

| 단계 | 모듈 | 설명 |
|------|------|------|
| Phase 1 | 상품 기초 데이터 + 구매 + 판매 + 재고 + 재무 + CRM | 핵심 업무 루프 |
| Phase 2 | 제조 관리 + 프로젝트 관리 | 후속 확장 |

### 1.2 기술 스택(기존 유지)

- PHP 8.3+, webman v2, MySQL 8.0+
- 기본키 BIGINT는 snowflake-php가 생성
- API 계층 ID는 hashids로 암·복호화
- JWT 인증, 민감 데이터 암호화 모두 erikwang2013/* 시리즈 패키지 사용
- 테이블 접두사 `erp_`, 소프트 삭제, 전역 함수에 `\` 미사용

---

## 2. 프로젝트 구조

```
service/app/
├── admin/controller/          # 系统管理控制器（已有，保持不变）
├── api/v1/controller/         # 客户端API（已有 + 扩展）
├── common/                    # 共享工具（已有 Snowflake/Hashids/Encryption）
├── middleware/                # 全局中间件（已有7个）
├── model/                     # 所有数据模型（跨模块共享）
├── service/                   # 业务逻辑层（按模块分目录）
│   ├── product/               # 商品与基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── controller/                # 业务模块控制器
│   ├── product/               # 商品基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── queue/                     # 队列任务（已有 + 业务队列）
├── process/                   # 进程（已有 Http, Monitor）
└── functions.php              # 全局辅助函数（已有）
```

### 2.1 계층별 책임

| 계층 | 파일 위치 | 책임 |
|----|----------|------|
| Controller | `app/controller/{module}/` | 파라미터 검증, 응답 포맷, Service 호출 |
| Service | `app/service/{module}/` | 업무 로직, 모듈 간 연동, 트랜잭션 관리 |
| Model | `app/model/` | 데이터 모델, 연관 관계, 쿼리 스코프, encryptable trait |

---

## 3. 모듈 기능 목록

### 3.1 상품과 기초 데이터

| 기능 | 설명 |
|------|------|
| 상품 마스터 | 상품명, 코드, 바코드, 분류(트리), 브랜드, 스펙 속성 |
| 다중 스펙 SKU | 동일 상품 다중 스펙, 각각 독립 SKU, 바코드, 가격 |
| 다중 단위 환산 | 기본 단위 ↔ 보조 단위 환산율 |
| 가격 전략 | 매입가, 도매가, 소매가, 고객 등급가 |
| 분류 관리 | 무한 레벨 분류 트리, 드래그 정렬 지원 |
| 브랜드 관리 | 브랜드 CRUD |
| 창고 관리 | 다중 창고, 각 창고에 다중 로케이션 |
| 로케이션 관리 | 창고 하위 저장 위치, 코드 고유 |
| 공급업체 마스터 | 이름, 담당자, 전화, 주소, 은행 계좌, 세율 |
| 고객 마스터 | 이름, 담당자, 전화, 주소, 고객 등급, 신용 한도 |

### 3.2 구매 모듈

| 기능 | 설명 |
|------|------|
| 구매 신청 | 부서/담당자 구매 수요 제출, 승인 프로세스 지원 |
| 구매 오더 | 신청 기반 또는 직접 생성, 공급업체·상품·수량·단가 연결 |
| 구매 입고 | 오더 기준 입고, 입고 전표 생성, 분할 입고 지원 |
| 구매 반품 | 공급업체에 반품, 출고 전표 생성으로 차감 |
| 공급업체 대사 | 공급업체+기간 기준 구매 금액, 지급액, 미지급액 집계 |
| 구매 정산 | 구매 입고와 지급 핵심 대사 |

### 3.3 판매 모듈

| 기능 | 설명 |
|------|------|
| 견적서 | 고객에 견적, 판매 오더 전환 지원 |
| 판매 오더 | 고객 주문, 상품·수량·단가·할인 연결 |
| 판매 출고 | 오더 기준 출고, 출고 전표 생성, 분할 출고 지원 |
| 판매 반품 | 고객 반품, 입고 전표 생성으로 차감 |
| 고객 대사 | 고객+기간 기준 판매 금액, 수금액, 미수금액 집계 |
| 판매 정산 | 판매 출고와 수금 핵심 대사 |
| 판매 총이익 | 오더/상품/고객 차원 총이익 계산 |

### 3.4 재고 모듈

| 기능 | 설명 |
|------|------|
| 실시간 재고 | 창고+로케이션+로트+SKU 차원 재고량 |
| 로트 추적 | 생산일, 유통기한, 로트 번호 |
| 시리얼 추적 | 고유 시리얼 번호, 입출고 시 기록 |
| 입출고 이력 | 모든 재고 변동의 통일 로그(출처 전표번호+유형+수량+방향) |
| 재고 이관 | 창고 간/로케이션 간 이관, 이관 입출고 전표 생성 |
| 실사 태스크 | 계획 실사(창고/분류 기준)+ 동적 실사(SKU 기준) |
| 실사 차이 | 실사 이익/손실 자동으로 입출고 이력 생성 |
| 재고 경고 | SKU+창고 기준 상하한 설정, 하한 미달 또는 상한 초과 시 경고 |
| 원가 계산 | 이동가중평균법, 입고마다 원가 재계산 |

### 3.5 재무 모듈

| 기능 | 설명 |
|------|------|
| 회계 과목 | 과목 트리(자산/부채/자본/수익/비용), 커스텀 지원 |
| 매출채권·매입채무 | 판매/구매 전표에서 자동 생성, 수동 핵심 대사 |
| 수금 전표 | 다중 계좌, 다중 방식(현금/은행/위챗/알리페이) 수금 |
| 지급 전표 | 다중 계좌, 다중 방식 지급 |
| 핵심 대사 | 수금 전표로 매출채권 대사, 지급 전표로 매입채무 대사 |
| 현금은행 일계부 | 계좌+일자 기준 수지 내역 기록 |
| 비용 정산 | 제출→승인→지급, 과목 연결 |
| 손익계산서 | 월별 수익/원가/비용/이익 집계 |

### 3.6 CRM 모듈

| 기능 | 설명 |
|------|------|
| 고객 관리 | 고객 마스터(기초 데이터 고객과 연결) |
| 담당자 관리 | 고객 하위의 여러 담당자 |
| 팔로우 기록 | 팔로우 방식, 시간, 내용, 다음 팔로우 계획 |
| 판매 퍼널 | 단계 설정 + 영업기회 금액 예상 + 단계 전환율 |

---

## 4. 데이터베이스 테이블 설계

모든 테이블 `erp_` 접두사, `id` BIGINT 비자동증가, `created_at`/`updated_at`/`deleted_at` 포함.

### 4.1 상품 기초 데이터

```
erp_product             商品主表
erp_product_sku         商品SKU/规格
erp_product_unit        多单位换算
erp_product_price       价格策略
erp_category            商品分类（树形 parent_id）
erp_brand               品牌
erp_warehouse           仓库
erp_location            库位
erp_supplier            供应商
erp_customer            客户
erp_customer_level      客户等级
```

### 4.2 구매 모듈

```
erp_purchase_apply       采购申请
erp_purchase_apply_item  申请明细
erp_purchase_order       采购订单
erp_purchase_order_item  订单明细
erp_purchase_receive     采购收货主表
erp_purchase_receive_item 收货明细
erp_purchase_return      采购退货主表
erp_purchase_return_item 退货明细
erp_purchase_settlement  供应商结算记录
```

### 4.3 판매 모듈

```
erp_sales_quotation      报价单主表
erp_sales_quotation_item 报价明细
erp_sales_order          销售订单主表
erp_sales_order_item     订单明细
erp_sales_delivery       销售发货主表
erp_sales_delivery_item  发货明细
erp_sales_return         销售退货主表
erp_sales_return_item    退货明细
erp_sales_settlement     客户结算记录
```

### 4.4 재고 모듈

```
erp_inventory            实时库存
erp_inventory_batch      批次信息
erp_inventory_serial     序列号记录
erp_inventory_flow       出入库流水
erp_transfer             调拨单主表
erp_transfer_item        调拨明细
erp_check_task           盘点任务
erp_check_detail         盘点明细
erp_inventory_alert_rule 库存预警规则
erp_inventory_alert_log  库存预警日志
erp_cost_record          成本计算记录
```

### 4.5 재무 모듈

```
erp_finance_account      会计科目
erp_finance_voucher      记账凭证
erp_finance_voucher_item 凭证分录
erp_finance_ar_ap        应收应付明细
erp_finance_receipt      收款单
erp_finance_payment      付款单
erp_finance_cash_journal 现金银行日记账
erp_finance_expense      费用报销单
erp_finance_expense_item 报销明细
erp_finance_profit       利润表快照
erp_finance_bank_account 银行账户
```

### 4.6 CRM 모듈

```
erp_crm_funnel_stage     销售漏斗阶段配置
erp_crm_opportunity      商机
erp_crm_follow_record    跟进记录
erp_crm_contact          联系人
```

---

## 5. API 라우트

`/admin/*` 네임스페이스 유지, 전체 미들웨어 체인(Auth → Permission → OperationLog).

```
# 商品基础数据
/admin/product/*          商品/分类/品牌 CRUD
/admin/warehouse/*        仓库/库位 CRUD
/admin/supplier/*         供应商 CRUD
/admin/customer/*         客户/客户等级 CRUD

# 采购
/admin/purchase/apply/*      采购申请 + 审批
/admin/purchase/order/*      采购订单
/admin/purchase/receive/*    采购收货
/admin/purchase/return/*     采购退货
/admin/purchase/settlement/* 供应商结算

# 销售
/admin/sales/quotation/*     报价单（含转订单）
/admin/sales/order/*         销售订单
/admin/sales/delivery/*      销售发货
/admin/sales/return/*        销售退货
/admin/sales/settlement/*    客户结算

# 库存
/admin/inventory/*           实时库存查询
/admin/inventory/batch/*     批次管理
/admin/inventory/serial/*    序列号管理
/admin/inventory/flow/*      出入库流水
/admin/inventory/transfer/*  调拨
/admin/inventory/check/*     盘点
/admin/inventory/alert/*     预警规则

# 财务
/admin/finance/account/*     会计科目
/admin/finance/voucher/*     记账凭证
/admin/finance/receipt/*     收款单
/admin/finance/payment/*     付款单
/admin/finance/cash/*        现金银行日记账
/admin/finance/expense/*     费用报销
/admin/finance/report/*      财务报表

# CRM
/admin/crm/opportunity/*     商机
/admin/crm/follow/*          跟进记录
/admin/crm/funnel/*          漏斗阶段配置
/admin/crm/contact/*         联系人

# 仪表盘（扩展）
/admin/dashboard/sales       销售面板
/admin/dashboard/inventory   库存面板
/admin/dashboard/finance     财务面板
```

클라이언트 API `/api/v1/*`는 경량 인터페이스 제공(상품 조회, 주문, 주문 상태 등), Flutter App / HarmonyOS에서 호출.

---

## 6. 모듈 간 데이터 흐름

```
采购收货 → inventory_flow(入库) → inventory(+数量) → cost_record(重算均价)
       → finance_ar_ap(应付)

销售发货 → inventory_flow(出库) → inventory(-数量) → cost_record(记录成本)
       → finance_ar_ap(应收)

收款单核销 → finance_ar_ap(已收更新) → cash_journal(收入记录)
付款单核销 → finance_ar_ap(已付更新) → cash_journal(支出记录)

盘点差异 → inventory_flow(盘盈入库/盘亏出库) → inventory(调整)

费用报销(已打款) → finance_payment(自动生成) → cash_journal(支出记录)
```

구현 방식: 각 업무 작업 완료 후 이벤트로 다운스트림 작업 트리거, 모듈 간 Service 직접 호출하지 않음.

---

## 7. Excel/PDF 내보내기

- 모든 목록 페이지가 `?export=excel` 파라미터 지원, 스타일 포함 .xlsx 파일 생성
- 대시보드 패널이 `?export=pdf` 지원, 차트 포함 PDF 리포트 출력
- 민감 필드(금액, 휴대폰 번호 등) 내보내기 시 EncryptionService로 마스킹
- 기존 ExportController 기반 클래스 재사용, 모듈 컨트롤러가 상속하고 자체 내보내기 열 정의 구현

---

## 8. 대시보드 패널

| 패널 | 라우트 | 지표 |
|------|------|------|
| 경영 개요 | `/admin/dashboard` | 오늘/이번 달 판매액, 구매액, 매출채권·매입채무, 재고 총액, 총이익 |
| 재고 보드 | `/admin/dashboard/inventory` | 경고 목록, 입출고 추세, 로케이션 점유율 |
| 판매 보드 | `/admin/dashboard/sales` | 추세 차트, 고객 랭킹, 상품 인기, 퍼널 전환율 |
| 재무 보드 | `/admin/dashboard/finance` | 수지 추세, 매출채권·매입채무 회전 연령, 현금 흐름 |

데이터는 Redis 5분 캐시, 시간 범위 전환 지원.

---

## 9. 프론트엔드 설계

| 단말 | 디렉토리 | 프레임워크 | 스타일 |
|----|------|------|------|
| Web 관리 백오피스 | `apps/flutter/` (web) | Flutter + GetX | PC 관리 백오피스(사이드바+탑 바+콘텐츠 영역) |
| 클라이언트 App | `apps/flutter/` (app) | Flutter + GetX | 모바일 네이티브 스타일 |
| HarmonyOS | `apps/harmonyos/` | ArkTS | HarmonyOS 네이티브, App 스타일 |

Flutter 코드는 라우트와 레이아웃 판단으로 Web PC 단과 모바일 단 렌더링 구분.

---

## 10. 구현 순서

| 단계 | 내용 | 의존 |
|------|------|------|
| 1 | 데이터베이스 마이그레이션 SQL(모든 업무 테이블) | 없음 |
| 2 | Model 계층(모든 모듈 데이터 모델) | 1단계 |
| 3 | 상품 기초 데이터 모듈(CRUD) | 2단계 |
| 4 | 구매 모듈 | 3단계 |
| 5 | 판매 모듈 | 3단계 |
| 6 | 재고 모듈 + 원가 계산 | 4,5단계 |
| 7 | 재무 모듈 | 4,5,6단계 |
| 8 | CRM 모듈 | 3단계 |
| 9 | 대시보드 패널 | 4-8단계 |
| 10 | Excel/PDF 내보내기 | 4-9단계 |
| 11 | 클라이언트 API(/api/*) | 4-8단계 |
| 12 | Flutter 프론트엔드 페이지 | 4-10단계 |
| 13 | HarmonyOS 프론트엔드 페이지 | 11단계 |
