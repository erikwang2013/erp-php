# 엔드포인트 감사 보고서 — 프론트엔드 요청 × 백엔드 라우트 대조

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 생성 시간: 2026-08-16T01:02:44+08:00　｜　생성 스크립트: `scripts/check-endpoints.php`(반복 실행 가능)
> 스모크 케이스 `/admin/notification/my/read`: 소스 스캔 미발견(워크스페이스에서 이미 수정되었을 수 있음) ｜ 매칭 로직 자체 점검 통과 ✅

## 통계

- 백엔드 등록 라우트(전개 후): 564개
- 프론트엔드 요청 호출: Flutter 377곳 / HarmonyOS 40곳
- 데드 엔드포인트: 20개　｜　커버리지 격차: 211개　｜　해석 불가: 0개

## 1. 데드 엔드포인트 목록 (프론트엔드가 호출하지만 백엔드에 없음)

| # | 모듈 | 메서드 | 경로 | 출처 | 호출 위치 |
|---|------|------|------|------|----------|
| 1 | approval | DELETE | `/admin/approval/my/{param}` | flutter `app/pages/workflow/my_approval_page.dart:52` |
| 2 | crm | DELETE | `/admin/crm/analytics/report/{param}` | flutter `app/pages/crm/analytics_page.dart:52` |
| 3 | crm | DELETE | `/admin/crm/pool/{param}` | flutter `app/pages/crm/pool_page.dart:52` |
| 4 | finance | DELETE | `/admin/finance/cash-journal/{param}` | flutter `app/pages/finance/cash_journal_page.dart:52` |
| 5 | finance | DELETE | `/admin/finance/general-ledger/{param}` | flutter `app/pages/finance/ledger_page.dart:52` |
| 6 | finance | DELETE | `/admin/finance/report/profit/{param}` | flutter `app/pages/finance/report_page.dart:52` |
| 7 | inventory | DELETE | `/admin/inventory/flow/{param}` | flutter `app/pages/inventory/flow_list_page.dart:52` |
| 8 | inventory | DELETE | `/admin/inventory/{param}` | flutter `app/pages/inventory/inventory_list_page.dart:52` |
| 9 | productlist | GET | `/admin/productlist` | harmonyos `pages/ProductListPage.ets:22` |
| 10 | purchaseorder | GET | `/admin/purchaseorder` | harmonyos `pages/PurchaseOrderPage.ets:22` |
| 11 | salesorder | GET | `/admin/salesorder` | harmonyos `pages/SalesOrderPage.ets:22` |
| 12 | approval | PUT | `/admin/approval/my/{param}` | flutter `app/pages/workflow/my_approval_page.dart:45` |
| 13 | crm | PUT | `/admin/crm/analytics/report/{param}` | flutter `app/pages/crm/analytics_page.dart:45` |
| 14 | crm | PUT | `/admin/crm/pool/{param}` | flutter `app/pages/crm/pool_page.dart:45` |
| 15 | finance | PUT | `/admin/finance/cash-journal/{param}` | flutter `app/pages/finance/cash_journal_page.dart:45` |
| 16 | finance | PUT | `/admin/finance/general-ledger/{param}` | flutter `app/pages/finance/ledger_page.dart:45` |
| 17 | finance | PUT | `/admin/finance/report/profit/{param}` | flutter `app/pages/finance/report_page.dart:45` |
| 18 | finance | PUT | `/admin/finance/tax-rate/{param}` | flutter `app/pages/finance/tax_page.dart:45` |
| 19 | inventory | PUT | `/admin/inventory/flow/{param}` | flutter `app/pages/inventory/flow_list_page.dart:45` |
| 20 | inventory | PUT | `/admin/inventory/{param}` | flutter `app/pages/inventory/inventory_list_page.dart:45` |

## 2. 커버리지 격차 목록 (백엔드에 존재하지만 Flutter/HarmonyOS 모두 미호출, 모듈별 그룹)

### 모듈: .well-known

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: approval

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | 프론트엔드에서 미호출 |

### 모듈: auth

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | 프론트엔드에서 미호출 |

### 모듈: bi

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | 프론트엔드에서 미호출 |

### 모듈: brand

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: category

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: crm

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | 프론트엔드에서 미호출 |

### 모듈: customer

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: customer-level

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | 프론트엔드에서 미호출 |

### 모듈: dashboard

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | 프론트엔드에서 미호출 |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | 프론트엔드에서 미호출 |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | 프론트엔드에서 미호출 |

### 모듈: debug

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: dms

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | 프론트엔드에서 미호출 |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | 프론트엔드에서 미호출 |

### 모듈: docs

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: eam

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: finance

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | 프론트엔드에서 미호출 |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | 프론트엔드에서 미호출 |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: health

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: hr

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | 프론트엔드에서 미호출 |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | 프론트엔드에서 미호출 |

### 모듈: import

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | 프론트엔드에서 미호출 |

### 모듈: install

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: inventory

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: location

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: metrics

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: mfg

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: notification

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | 프론트엔드에서 미호출 |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | 프론트엔드에서 미호출 |

### 모듈: oms

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | 프론트엔드에서 미호출 |

### 모듈: permission

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | 프론트엔드에서 미호출 |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | 프론트엔드에서 미호출 |

### 모듈: product

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | 프론트엔드에서 미호출 |
| ANY | `/api/product` | config/route.php:412（route(any)） | 프론트엔드에서 미호출 |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | 프론트엔드에서 미호출 |

### 모듈: project

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: purchase

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | 프론트엔드에서 미호출 |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | 프론트엔드에서 미호출 |

### 모듈: quality

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: report

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | 프론트엔드에서 미호출 |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | 프론트엔드에서 미호출 |

### 모듈: sales

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | 프론트엔드에서 미호출 |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | 프론트엔드에서 미호출 |

### 모듈: supplier

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: tms

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | 시스템/비프론트엔드 직접 호출 (webhook, 헬스 체크, 설치 마법사 등) |

### 모듈: upload

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | 프론트엔드에서 미호출 |

### 모듈: warehouse

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | 프론트엔드에서 미호출 |

### 모듈: wms

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | 프론트엔드에서 미호출 |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | 프론트엔드에서 미호출 |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | 프론트엔드에서 미호출 |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | 프론트엔드에서 미호출 |

### 모듈: workflow

| 메서드 | 경로 | 라우트 위치 | 설명 |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | 프론트엔드에서 미호출 |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | 프론트엔드에서 미호출 |

## 3. 해석 불가 경로 (수동 재검토)

(없음)

## 4. 스크립트 사용법

스크립트: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# 텍스트 출력 (기본)
php scripts/check-endpoints.php

# JSON 출력 (후속 도구에서 소비하기 용이)
php scripts/check-endpoints.php --json

# 단일 모듈만 필터링 (예: finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# 본 감사 보고서 재생성
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

작동 원리:

1. **백엔드**: `config/route.php`를 파싱하고 `Route::group` 프리픽스를 완전한 경로로 복원; `Route::resource`는 컨트롤러에 실제로 존재하는 메서드에 따라 전개(index/store/show/update/destroy 등); `Route::any`는 임의 메서드로 간주.
2. **프론트엔드**: `apps/flutter/lib` 디렉토리의 모든 `.dart`와 `apps/harmonyos/entry/src/main/ets` 디렉토리의 모든 `.ets` 파일을 스캔하여 `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()` 등 호출의 경로 리터럴을 추출; `${...}` / `$var` 인터폴레이션과 템플릿 문자열 지원( `${BASE_URL}` 프리픽스 제거 포함).
3. **매칭**: 프론트엔드 리터럴 세그먼트는 백엔드 리터럴 세그먼트와만 매칭하고, 프론트엔드 동적 세그먼트는 백엔드 `{param}` 세그먼트와만 매칭(`/admin/notification/my/read`가 `/admin/notification/{id}/read`에 오매칭되지 않도록 보장); 메서드는 HTTP 메서드로 정확히 매칭하고 `any`는 모두 매칭.
4. **목록**: ① 데드 엔드포인트(프론트엔드 호출이지만 백엔드에 없음, 최우선) → ② 커버리지 격차(백엔드에 존재하지만 프론트엔드 모두 미호출, 모듈별 그룹; webhook/헬스 체크 등 시스템 라우트는 표기됨) → ③ 해석 불가 경로(변수 경로, 문자열 연결, 닫히지 않은 인터폴레이션 등, 수동 재검토 필요).

