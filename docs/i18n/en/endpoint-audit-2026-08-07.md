# Endpoint Audit Report — Frontend Requests × Backend Routes Comparison

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Generated: 2026-08-16T01:02:44+08:00 | Generation script:`scripts/check-endpoints.php` (repeatable)
Smoke case `/admin/notification/my/read`: source scan not found (possibly fixed in workspace) | matching logic self-check passed ✅

## Statistics

- Backend registered routes (expanded): 564
- Frontend request call sites: Flutter 377 / HarmonyOS 40
- Dead endpoints: 20 | Coverage gaps: 211 | Unresolvable: 0

## 1. Dead Endpoints (called by frontend, nonexistent on backend)

| # | Module | Method | Path | Source | Call Location |
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

## 2. Coverage Gaps (exist on backend, not called by Flutter/HarmonyOS, grouped by module)

### Module: .well-known

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: approval

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | Not called by the frontend |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | Not called by the frontend |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | Not called by the frontend |

### Module: auth

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | Not called by the frontend |

### Module: bi

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | Not called by the frontend |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | Not called by the frontend |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | Not called by the frontend |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | Not called by the frontend |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | Not called by the frontend |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | Not called by the frontend |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | Not called by the frontend |

### Module: brand

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | Not called by the frontend |

### Module: category

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | Not called by the frontend |

### Module: crm

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | Not called by the frontend |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | Not called by the frontend |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | Not called by the frontend |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | Not called by the frontend |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | Not called by the frontend |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | Not called by the frontend |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | Not called by the frontend |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | Not called by the frontend |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | Not called by the frontend |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | Not called by the frontend |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | Not called by the frontend |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | Not called by the frontend |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | Not called by the frontend |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | Not called by the frontend |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | Not called by the frontend |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | Not called by the frontend |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | Not called by the frontend |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | Not called by the frontend |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | Not called by the frontend |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | Not called by the frontend |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | Not called by the frontend |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | Not called by the frontend |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | Not called by the frontend |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | Not called by the frontend |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | Not called by the frontend |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | Not called by the frontend |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | Not called by the frontend |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | Not called by the frontend |

### Module: customer

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | Not called by the frontend |

### Module: customer-level

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | Not called by the frontend |

### Module: dashboard

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | Not called by the frontend |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | Not called by the frontend |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | Not called by the frontend |

### Module: debug

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: dms

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | Not called by the frontend |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | Not called by the frontend |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | Not called by the frontend |

### Module: docs

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: eam

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | Not called by the frontend |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | Not called by the frontend |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | Not called by the frontend |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | Not called by the frontend |

### Module: finance

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | Not called by the frontend |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | Not called by the frontend |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | Not called by the frontend |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | Not called by the frontend |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | Not called by the frontend |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | Not called by the frontend |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | Not called by the frontend |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | Not called by the frontend |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | Not called by the frontend |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | Not called by the frontend |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | Not called by the frontend |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | Not called by the frontend |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | Not called by the frontend |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | Not called by the frontend |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | Not called by the frontend |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | Not called by the frontend |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | Not called by the frontend |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | Not called by the frontend |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | Not called by the frontend |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | Not called by the frontend |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | Not called by the frontend |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | Not called by the frontend |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | Not called by the frontend |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | Not called by the frontend |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | Not called by the frontend |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | Not called by the frontend |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | Not called by the frontend |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | Not called by the frontend |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | Not called by the frontend |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | Not called by the frontend |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | Not called by the frontend |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | Not called by the frontend |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | Not called by the frontend |

### Module: health

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: hr

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | Not called by the frontend |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | Not called by the frontend |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | Not called by the frontend |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | Not called by the frontend |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | Not called by the frontend |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | Not called by the frontend |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | Not called by the frontend |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | Not called by the frontend |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | Not called by the frontend |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | Not called by the frontend |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | Not called by the frontend |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | Not called by the frontend |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | Not called by the frontend |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | Not called by the frontend |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | Not called by the frontend |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | Not called by the frontend |

### Module: import

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | Not called by the frontend |

### Module: install

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: inventory

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | Not called by the frontend |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | Not called by the frontend |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | Not called by the frontend |

### Module: location

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | Not called by the frontend |

### Module: metrics

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: mfg

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | Not called by the frontend |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | Not called by the frontend |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | Not called by the frontend |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | Not called by the frontend |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | Not called by the frontend |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | Not called by the frontend |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | Not called by the frontend |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | Not called by the frontend |

### Module: notification

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | Not called by the frontend |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | Not called by the frontend |

### Module: oms

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | Not called by the frontend |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | Not called by the frontend |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | Not called by the frontend |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | Not called by the frontend |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | Not called by the frontend |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | Not called by the frontend |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | Not called by the frontend |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | Not called by the frontend |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | Not called by the frontend |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | Not called by the frontend |

### Module: permission

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | Not called by the frontend |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | Not called by the frontend |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | Not called by the frontend |

### Module: product

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | Not called by the frontend |
| ANY | `/api/product` | config/route.php:412（route(any)） | Not called by the frontend |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | Not called by the frontend |

### Module: project

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | Not called by the frontend |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | Not called by the frontend |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | Not called by the frontend |

### Module: purchase

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | Not called by the frontend |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | Not called by the frontend |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | Not called by the frontend |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | Not called by the frontend |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | Not called by the frontend |

### Module: quality

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | Not called by the frontend |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | Not called by the frontend |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | Not called by the frontend |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | Not called by the frontend |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | Not called by the frontend |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | Not called by the frontend |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | Not called by the frontend |

### Module: report

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | Not called by the frontend |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | Not called by the frontend |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | Not called by the frontend |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | Not called by the frontend |

### Module: sales

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | Not called by the frontend |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | Not called by the frontend |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | Not called by the frontend |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | Not called by the frontend |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | Not called by the frontend |

### Module: supplier

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | Not called by the frontend |

### Module: tms

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | Not called by the frontend |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | Not called by the frontend |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | Not called by the frontend |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | Not called by the frontend |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | Not called by the frontend |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | Not called by the frontend |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | Not called by the frontend |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | Not called by the frontend |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | Not called by the frontend |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | Not called by the frontend |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | Not called by the frontend |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | Not called by the frontend |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | Not called by the frontend |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | Not called by the frontend |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | Not called by the frontend |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | Not called by the frontend |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | System / not called directly by the frontend (webhooks, health checks, install wizard, etc.) |

### Module: upload

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | Not called by the frontend |

### Module: warehouse

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | Not called by the frontend |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | Not called by the frontend |

### Module: wms

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | Not called by the frontend |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | Not called by the frontend |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | Not called by the frontend |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | Not called by the frontend |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | Not called by the frontend |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | Not called by the frontend |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | Not called by the frontend |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | Not called by the frontend |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | Not called by the frontend |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | Not called by the frontend |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | Not called by the frontend |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | Not called by the frontend |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | Not called by the frontend |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | Not called by the frontend |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | Not called by the frontend |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | Not called by the frontend |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | Not called by the frontend |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | Not called by the frontend |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | Not called by the frontend |

### Module: workflow

| Method | Path | Route Location | Notes |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | Not called by the frontend |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | Not called by the frontend |

## 3. Unresolvable Paths (manual review)

(none)

## 4. Script Usage

Script: `scripts/check-endpoints.php` (PHP CLI >= 8.0)

```bash
# 文本输出（默认）
php scripts/check-endpoints.php

# JSON 输出（便于后续工具消费）
php scripts/check-endpoints.php --json

# 仅过滤单个模块（如 finance、wms、notification）
php scripts/check-endpoints.php --module=finance

# 重新生成本审计报告
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

How it works:

1. **Backend**: parses `config/route.php`, reconstructing `Route::group` prefixes into full paths; `Route::resource` expands by the methods actually present on the controller (index/store/show/update/destroy etc.); `Route::any` counts as any method.
2. **Frontend**: scans all `.dart` files under `apps/flutter/lib` and all `.ets` files under `apps/harmonyos/entry/src/main/ets`, extracting path literals from calls such as `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()`; supports `${...}` / `$var` interpolation and template strings (including `${BASE_URL}` prefix stripping).
3. **Matching**: frontend literal segments only match backend literal segments, frontend dynamic segments only match backend `{param}` segments (ensuring `/admin/notification/my/read` is not mis-matched to `/admin/notification/{id}/read`); methods match exactly by HTTP method, `any` matches everything.
4. **Report**: ① dead endpoints (called by the frontend but nonexistent on the backend, highest priority) → ② coverage gaps (exist on the backend but never called by the frontend, grouped by module; system routes such as webhooks/health checks are annotated) → ③ unresolvable paths (variable paths, string concatenation, unclosed interpolation, etc., requiring manual review).

