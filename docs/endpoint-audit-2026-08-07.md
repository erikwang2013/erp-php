# 端点审计报告 — 前端请求 × 后端路由 比对

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 生成时间：2026-08-16T01:02:44+08:00　｜　生成脚本：`scripts/check-endpoints.php`（可重复运行）
> 冒烟用例 `/admin/notification/my/read`：源码扫描 未发现（可能已在工作区修复） ｜ 匹配逻辑自检 通过 ✅

## 统计

- 后端已注册路由（展开后）：564 条
- 前端请求调用：Flutter 377 处 / HarmonyOS 40 处
- 死端点：20 个　｜　覆盖缺口：211 个　｜　无法解析：0 个

## 一、死端点清单（前端调用但后端不存在）

| # | 模块 | 方法 | 路径 | 来源 | 调用位置 |
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

## 二、覆盖缺口清单（后端存在但 Flutter/HarmonyOS 均未调用，按模块分组）

### 模块：.well-known

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：approval

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | 前端未调用 |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | 前端未调用 |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | 前端未调用 |

### 模块：auth

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | 前端未调用 |

### 模块：bi

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | 前端未调用 |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | 前端未调用 |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | 前端未调用 |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | 前端未调用 |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | 前端未调用 |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | 前端未调用 |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | 前端未调用 |

### 模块：brand

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | 前端未调用 |

### 模块：category

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | 前端未调用 |

### 模块：crm

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | 前端未调用 |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | 前端未调用 |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | 前端未调用 |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | 前端未调用 |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | 前端未调用 |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | 前端未调用 |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | 前端未调用 |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | 前端未调用 |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | 前端未调用 |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | 前端未调用 |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | 前端未调用 |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | 前端未调用 |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | 前端未调用 |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | 前端未调用 |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | 前端未调用 |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | 前端未调用 |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | 前端未调用 |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | 前端未调用 |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | 前端未调用 |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | 前端未调用 |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | 前端未调用 |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | 前端未调用 |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | 前端未调用 |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | 前端未调用 |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | 前端未调用 |

### 模块：customer

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | 前端未调用 |

### 模块：customer-level

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | 前端未调用 |

### 模块：dashboard

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | 前端未调用 |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | 前端未调用 |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | 前端未调用 |

### 模块：debug

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：dms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | 前端未调用 |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | 前端未调用 |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | 前端未调用 |

### 模块：docs

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：eam

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | 前端未调用 |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | 前端未调用 |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | 前端未调用 |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | 前端未调用 |

### 模块：finance

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | 前端未调用 |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | 前端未调用 |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | 前端未调用 |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | 前端未调用 |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | 前端未调用 |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | 前端未调用 |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | 前端未调用 |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | 前端未调用 |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | 前端未调用 |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | 前端未调用 |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | 前端未调用 |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | 前端未调用 |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | 前端未调用 |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | 前端未调用 |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | 前端未调用 |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | 前端未调用 |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | 前端未调用 |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | 前端未调用 |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | 前端未调用 |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | 前端未调用 |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | 前端未调用 |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | 前端未调用 |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | 前端未调用 |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | 前端未调用 |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | 前端未调用 |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | 前端未调用 |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | 前端未调用 |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | 前端未调用 |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | 前端未调用 |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | 前端未调用 |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | 前端未调用 |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | 前端未调用 |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | 前端未调用 |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | 前端未调用 |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | 前端未调用 |

### 模块：health

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：hr

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | 前端未调用 |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | 前端未调用 |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | 前端未调用 |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | 前端未调用 |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | 前端未调用 |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | 前端未调用 |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | 前端未调用 |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | 前端未调用 |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | 前端未调用 |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | 前端未调用 |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | 前端未调用 |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | 前端未调用 |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | 前端未调用 |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | 前端未调用 |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | 前端未调用 |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | 前端未调用 |

### 模块：import

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | 前端未调用 |

### 模块：install

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | 系统/非前端直调（webhook、健康检查、安装向导等） |
| GET | `/install/test-db` | config/route.php:41（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：inventory

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | 前端未调用 |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | 前端未调用 |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | 前端未调用 |

### 模块：location

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | 前端未调用 |

### 模块：metrics

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：mfg

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | 前端未调用 |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | 前端未调用 |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | 前端未调用 |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | 前端未调用 |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | 前端未调用 |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | 前端未调用 |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | 前端未调用 |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | 前端未调用 |

### 模块：notification

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | 前端未调用 |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | 前端未调用 |

### 模块：oms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | 前端未调用 |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | 前端未调用 |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | 前端未调用 |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | 前端未调用 |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | 前端未调用 |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | 前端未调用 |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | 前端未调用 |

### 模块：permission

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | 前端未调用 |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | 前端未调用 |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | 前端未调用 |

### 模块：product

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | 前端未调用 |
| ANY | `/api/product` | config/route.php:412（route(any)） | 前端未调用 |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | 前端未调用 |

### 模块：project

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | 前端未调用 |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | 前端未调用 |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | 前端未调用 |

### 模块：purchase

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | 前端未调用 |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | 前端未调用 |

### 模块：quality

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | 前端未调用 |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | 前端未调用 |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | 前端未调用 |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | 前端未调用 |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | 前端未调用 |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | 前端未调用 |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | 前端未调用 |

### 模块：report

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | 前端未调用 |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | 前端未调用 |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | 前端未调用 |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | 前端未调用 |

### 模块：sales

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | 前端未调用 |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | 前端未调用 |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | 前端未调用 |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | 前端未调用 |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | 前端未调用 |

### 模块：supplier

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | 前端未调用 |

### 模块：tms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | 前端未调用 |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | 前端未调用 |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | 前端未调用 |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | 前端未调用 |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | 前端未调用 |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | 前端未调用 |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | 前端未调用 |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | 前端未调用 |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | 前端未调用 |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | 前端未调用 |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | 前端未调用 |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | 前端未调用 |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | 前端未调用 |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | 前端未调用 |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | 前端未调用 |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | 前端未调用 |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：upload

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | 前端未调用 |

### 模块：warehouse

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | 前端未调用 |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | 前端未调用 |

### 模块：wms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | 前端未调用 |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | 前端未调用 |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | 前端未调用 |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | 前端未调用 |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | 前端未调用 |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | 前端未调用 |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | 前端未调用 |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | 前端未调用 |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | 前端未调用 |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | 前端未调用 |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | 前端未调用 |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | 前端未调用 |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | 前端未调用 |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | 前端未调用 |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | 前端未调用 |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | 前端未调用 |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | 前端未调用 |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | 前端未调用 |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | 前端未调用 |

### 模块：workflow

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | 前端未调用 |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | 前端未调用 |

## 三、无法解析的路径（人工复核）

（无）

## 四、脚本用法说明

脚本：`scripts/check-endpoints.php`（PHP CLI ≥ 8.0）

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

工作原理：

1. **后端**：解析 `config/route.php`，还原 `Route::group` 前缀为完整路径；`Route::resource` 按控制器实际存在的方法展开（index/store/show/update/destroy 等）；`Route::any` 视为任意方法。
2. **前端**：扫描 `apps/flutter/lib` 目录下所有 `.dart` 与 `apps/harmonyos/entry/src/main/ets` 目录下所有 `.ets` 文件，提取 `ApiService.instance.*`、`api.*`、`_dio.*`、`apiService.*`、`httpRequest.request()` 等调用的路径字面量；支持 `${...}` / `$var` 插值与模板串（含 `${BASE_URL}` 前缀剥离）。
3. **匹配**：前端字面量段仅匹配后端字面量段，前端动态段仅匹配后端 `{param}` 段（保证 `/admin/notification/my/read` 不会被误配到 `/admin/notification/{id}/read`）；方法按 HTTP 方法精确匹配，`any` 匹配一切。
4. **清单**：① 死端点（前端调用但后端不存在，最优先）→ ② 覆盖缺口（后端存在但前端均未调用，按模块分组；webhook/健康检查等系统路由已标注）→ ③ 无法解析的路径（变量路径、字符串拼接、未闭合插值等，需人工复核）。

