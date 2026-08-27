# エンドポイント監査報告書 — フロントエンドリクエスト × バックエンドルート 突合

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 生成時刻：2026-08-16T01:02:44+08:00　｜　生成スクリプト：`scripts/check-endpoints.php`（再実行可能）
> スモークケース `/admin/notification/my/read`：ソーススキャン 未検出（ワークスペースで修正済みの可能性）｜ マッチングロジック自己検証 通過 ✅

## 統計

- バックエンド登録済みルート（展開後）：564 件
- フロントエンドのリクエスト呼び出し：Flutter 377 箇所 / HarmonyOS 40 箇所
- デッドエンドポイント：20 個　｜　カバレッジギャップ：211 個　｜　解析不能：0 個

## 一、デッドエンドポイント一覧（フロントエンドが呼び出しているがバックエンドに存在しない）

| # | モジュール | メソッド | パス | ソース | 呼び出し箇所 |
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

## 二、カバレッジギャップ一覧（バックエンドに存在するが Flutter/HarmonyOS のいずれも未呼び出し、モジュール別グループ）

### モジュール：.well-known

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：approval

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | フロントエンド未呼び出し |

### モジュール：auth

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | フロントエンド未呼び出し |

### モジュール：bi

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | フロントエンド未呼び出し |

### モジュール：brand

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | フロントエンド未呼び出し |

### モジュール：category

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | フロントエンド未呼び出し |

### モジュール：crm

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | フロントエンド未呼び出し |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | フロントエンド未呼び出し |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | フロントエンド未呼び出し |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | フロントエンド未呼び出し |

### モジュール：customer

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | フロントエンド未呼び出し |

### モジュール：customer-level

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | フロントエンド未呼び出し |

### モジュール：dashboard

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | フロントエンド未呼び出し |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | フロントエンド未呼び出し |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | フロントエンド未呼び出し |

### モジュール：debug

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：dms

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | フロントエンド未呼び出し |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | フロントエンド未呼び出し |

### モジュール：docs

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：eam

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | フロントエンド未呼び出し |

### モジュール：finance

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | フロントエンド未呼び出し |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | フロントエンド未呼び出し |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | フロントエンド未呼び出し |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | フロントエンド未呼び出し |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | フロントエンド未呼び出し |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | フロントエンド未呼び出し |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | フロントエンド未呼び出し |

### モジュール：health

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：hr

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | フロントエンド未呼び出し |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | フロントエンド未呼び出し |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | フロントエンド未呼び出し |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | フロントエンド未呼び出し |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | フロントエンド未呼び出し |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | フロントエンド未呼び出し |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | フロントエンド未呼び出し |

### モジュール：import

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | フロントエンド未呼び出し |

### モジュール：install

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |
| GET | `/install/test-db` | config/route.php:41（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：inventory

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | フロントエンド未呼び出し |

### モジュール：location

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | フロントエンド未呼び出し |

### モジュール：metrics

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：mfg

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | フロントエンド未呼び出し |

### モジュール：notification

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | フロントエンド未呼び出し |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | フロントエンド未呼び出し |

### モジュール：oms

| メソッド | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | フロントエンド未呼び出し |

### モジュール：permission

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | フロントエンド未呼び出し |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | フロントエンド未呼び出し |

### モジュール：product

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | フロントエンド未呼び出し |
| ANY | `/api/product` | config/route.php:412（route(any)） | フロントエンド未呼び出し |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | フロントエンド未呼び出し |

### モジュール：project

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | フロントエンド未呼び出し |

### モジュール：purchase

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | フロントエンド未呼び出し |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | フロントエンド未呼び出し |

### モジュール：quality

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | フロントエンド未呼び出し |

### モジュール：report

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | フロントエンド未呼び出し |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | フロントエンド未呼び出し |

### モジュール：sales

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | フロントエンド未呼び出し |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | フロントエンド未呼び出し |

### モジュール：supplier

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | フロントエンド未呼び出し |

### モジュール：tms

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | フロントエンド未呼び出し |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | フロントエンド未呼び出し |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | システム/フロントエンド外からの直接呼び出し（webhook、ヘルスチェック、インストールウィザードなど） |

### モジュール：upload

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | フロントエンド未呼び出し |

### モジュール：warehouse

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | フロントエンド未呼び出し |

### モジュール：wms

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | フロントエンド未呼び出し |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | フロントエンド未呼び出し |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | フロントエンド未呼び出し |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | フロントエンド未呼び出し |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | フロントエンド未呼び出し |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | フロントエンド未呼び出し |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | フロントエンド未呼び出し |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | フロントエンド未呼び出し |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | フロントエンド未呼び出し |

### モジュール：workflow

| 方法 | パス | ルート位置 | 説明 |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | フロントエンド未呼び出し |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | フロントエンド未呼び出し |

## 三、解析できないパス（手動確認）

（なし）

## 四、スクリプトの使い方

スクリプト：`scripts/check-endpoints.php`（PHP CLI ≥ 8.0）

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

仕組み：

1. **バックエンド**：`config/route.php` を解析し、`Route::group` のプレフィックスを完全なパスに復元；`Route::resource` はコントローラーに実際に存在するメソッドに展開（index/store/show/update/destroy など）；`Route::any` は任意のメソッドとみなす。
2. **フロントエンド**：`apps/flutter/lib` ディレクトリ配下のすべての `.dart` ファイルと、`apps/harmonyos/entry/src/main/ets` ディレクトリ配下のすべての `.ets` ファイルをスキャンし、`ApiService.instance.*`、`api.*`、`_dio.*`、`apiService.*`、`httpRequest.request()` などの呼び出しからパスリテラルを抽出；`${...}` / `$var` 補間とテンプレート文字列をサポート（`${BASE_URL}` プレフィックスの除去を含む）。
3. **マッチング**：フロントエンドのリテラルセグメントはバックエンドのリテラルセグメントのみにマッチし、フロントエンドの動的セグメントはバックエンドの `{param}` セグメントのみにマッチ（`/admin/notification/my/read` が `/admin/notification/{id}/read` に誤マッチしないことを保証）；メソッドは HTTP メソッドで完全一致、`any` はすべてにマッチ。
4. **一覧**：① 死エンドポイント（フロントエンドが呼び出しているがバックエンドに存在しない、最優先）→ ② カバレッジギャップ（バックエンドに存在するがフロントエンドが呼び出していない、モジュールごとにグループ化；webhook/ヘルスチェックなどのシステムルートは注記済み）→ ③ 解析できないパス（変数パス、文字列連結、未クローズの補間など、手動確認が必要）。
