# 端点审计报告 — 前端请求 × 后端路由 比对

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 生成时间：2026-08-27T23:34:50+08:00　｜　生成脚本：`scripts/check-endpoints.php`（可重复运行）
> 冒烟用例 `/admin/notification/my/read`：源码扫描 未发现（可能已在工作区修复） ｜ 匹配逻辑自检 通过 ✅

## 统计

- 后端已注册路由（展开后）：571 条
- 前端请求调用：Flutter 397 处 / HarmonyOS 88 处
- 死端点：0 个　｜　覆盖缺口：163 个　｜　无法解析：0 个

## 一、死端点清单（前端调用但后端不存在）

（无）

## 二、覆盖缺口清单（后端存在但 Flutter/HarmonyOS 均未调用，按模块分组）

### 模块：.well-known

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:52（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：auth

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:406（route(post)） | 前端未调用 |

### 模块：bi

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:371（resource(show)） | 前端未调用 |
| GET | `/admin/bi/dataset/{id}` | config/route.php:373（resource(show)） | 前端未调用 |
| GET | `/admin/bi/widget` | config/route.php:372（resource(index)） | 前端未调用 |
| POST | `/admin/bi/widget` | config/route.php:372（resource(store)） | 前端未调用 |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:372（resource(destroy)） | 前端未调用 |
| GET | `/admin/bi/widget/{id}` | config/route.php:372（resource(show)） | 前端未调用 |
| PUT | `/admin/bi/widget/{id}` | config/route.php:372（resource(update)） | 前端未调用 |

### 模块：brand

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:112（resource(show)） | 前端未调用 |

### 模块：category

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:111（resource(show)） | 前端未调用 |

### 模块：crm

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:221（route(post)） | 前端未调用 |
| GET | `/admin/crm/analytics/metric` | config/route.php:222（route(get)） | 前端未调用 |
| POST | `/admin/crm/analytics/metric` | config/route.php:223（route(post)） | 前端未调用 |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:220（route(get)） | 前端未调用 |
| GET | `/admin/crm/campaign/{id}` | config/route.php:214（resource(show)） | 前端未调用 |
| GET | `/admin/crm/contact/{id}` | config/route.php:197（resource(show)） | 前端未调用 |
| GET | `/admin/crm/contract/{id}` | config/route.php:206（resource(show)） | 前端未调用 |
| GET | `/admin/crm/follow/{id}` | config/route.php:195（resource(show)） | 前端未调用 |
| GET | `/admin/crm/funnel/{id}` | config/route.php:196（resource(show)） | 前端未调用 |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:194（resource(show)） | 前端未调用 |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:203（route(post)） | 前端未调用 |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:204（route(post)） | 前端未调用 |
| GET | `/admin/crm/pool/rules` | config/route.php:205（route(get)） | 前端未调用 |
| GET | `/admin/crm/quotation/{id}` | config/route.php:208（resource(show)） | 前端未调用 |
| GET | `/admin/crm/ticket/{id}` | config/route.php:215（resource(show)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:216（route(post)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:218（route(post)） | 前端未调用 |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:217（route(post)） | 前端未调用 |

### 模块：customer

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:117（resource(show)） | 前端未调用 |

### 模块：customer-level

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:118（route(any)） | 前端未调用 |

### 模块：dashboard

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:230（route(any)） | 前端未调用 |
| ANY | `/admin/dashboard/inventory` | config/route.php:229（route(any)） | 前端未调用 |
| ANY | `/admin/dashboard/sales` | config/route.php:228（route(any)） | 前端未调用 |

### 模块：dms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:388（route(get)） | 前端未调用 |
| GET | `/admin/dms/document/{id}` | config/route.php:387（resource(show)） | 前端未调用 |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:389（route(get)） | 前端未调用 |

### 模块：docs

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:63（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：eam

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:378（resource(show)） | 前端未调用 |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:379（resource(show)） | 前端未调用 |
| GET | `/admin/eam/repair/{id}` | config/route.php:380（resource(show)） | 前端未调用 |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:382（resource(show)） | 前端未调用 |

### 模块：finance

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:150（resource(show)） | 前端未调用 |
| GET | `/admin/finance/asset/{id}` | config/route.php:177（resource(show)） | 前端未调用 |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:178（route(post)） | 前端未调用 |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:179（route(any)） | 前端未调用 |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:162（resource(show)） | 前端未调用 |
| GET | `/admin/finance/budget/{id}` | config/route.php:186（resource(show)） | 前端未调用 |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:187（route(any)） | 前端未调用 |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:188（resource(show)） | 前端未调用 |
| GET | `/admin/finance/currency/{id}` | config/route.php:184（resource(show)） | 前端未调用 |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:185（resource(show)） | 前端未调用 |
| GET | `/admin/finance/expense/{id}` | config/route.php:155（resource(show)） | 前端未调用 |
| GET | `/admin/finance/payment/{id}` | config/route.php:153（resource(show)） | 前端未调用 |
| GET | `/admin/finance/profit-center` | config/route.php:189（resource(index)） | 前端未调用 |
| POST | `/admin/finance/profit-center` | config/route.php:189（resource(store)） | 前端未调用 |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:189（resource(destroy)） | 前端未调用 |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:189（resource(show)） | 前端未调用 |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:189（resource(update)） | 前端未调用 |
| GET | `/admin/finance/receipt/{id}` | config/route.php:152（resource(show)） | 前端未调用 |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:170（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:172（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/consolidate` | config/route.php:158（route(post)） | 前端未调用 |
| POST | `/admin/finance/report/ratios` | config/route.php:159（route(post)） | 前端未调用 |
| ANY | `/admin/finance/tax-record` | config/route.php:183（route(any)） | 前端未调用 |
| GET | `/admin/finance/voucher/{id}` | config/route.php:151（resource(show)） | 前端未调用 |

### 模块：health

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：hr

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:267（route(post)） | 前端未调用 |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:268（route(post)） | 前端未调用 |
| GET | `/admin/hr/leave/{id}` | config/route.php:271（route(get)） | 前端未调用 |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:274（route(post)） | 前端未调用 |
| GET | `/admin/hr/position/{id}` | config/route.php:265（resource(show)） | 前端未调用 |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:282（route(get)） | 前端未调用 |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:277（route(post)） | 前端未调用 |
| GET | `/admin/hr/salary/{id}` | config/route.php:278（resource(show)） | 前端未调用 |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:279（route(post)） | 前端未调用 |

### 模块：import

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:102（route(post)） | 前端未调用 |

### 模块：install

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | 系统/非前端直调（webhook、健康检查、安装向导等） |
| GET | `/install/test-db` | config/route.php:41（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：inventory

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:145（resource(show)） | 前端未调用 |
| GET | `/admin/inventory/check/{id}` | config/route.php:144（resource(show)） | 前端未调用 |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:143（resource(show)） | 前端未调用 |

### 模块：location

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:115（resource(show)） | 前端未调用 |

### 模块：metrics

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：mfg

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:289（resource(show)） | 前端未调用 |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:295（resource(show)） | 前端未调用 |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:296（route(post)） | 前端未调用 |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:292（route(post)） | 前端未调用 |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:291（route(post)） | 前端未调用 |
| GET | `/admin/mfg/routing/{id}` | config/route.php:293（resource(show)） | 前端未调用 |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:294（resource(show)） | 前端未调用 |

### 模块：notification

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:251（route(any)） | 前端未调用 |
| POST | `/admin/notification/{id}/read` | config/route.php:249（route(post)） | 前端未调用 |

### 模块：oms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:313（resource(show)） | 前端未调用 |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:310（route(post)） | 前端未调用 |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:312（route(post)） | 前端未调用 |
| GET | `/admin/oms/rma/{id}` | config/route.php:314（resource(show)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:315（route(post)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:316（route(post)） | 前端未调用 |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:317（route(post)） | 前端未调用 |

### 模块：permission

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:81（resource(store)） | 前端未调用 |
| DELETE | `/admin/permission/{id}` | config/route.php:81（resource(destroy)） | 前端未调用 |
| PUT | `/admin/permission/{id}` | config/route.php:81（resource(update)） | 前端未调用 |

### 模块：product

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| ANY | `/api/product` | config/route.php:410（route(any)） | 前端未调用 |
| ANY | `/api/product/{hashid}` | config/route.php:411（route(any)） | 前端未调用 |

### 模块：project

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:256（resource(show)） | 前端未调用 |
| GET | `/admin/project/timesheet/{id}` | config/route.php:257（resource(show)） | 前端未调用 |
| GET | `/admin/project/{id}` | config/route.php:258（resource(show)） | 前端未调用 |

### 模块：purchase

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:123（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/receive/{id}` | config/route.php:125（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/return/{id}` | config/route.php:126（resource(show)） | 前端未调用 |
| GET | `/admin/purchase/settlement/{id}` | config/route.php:127（resource(show)） | 前端未调用 |

### 模块：quality

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:366（route(post)） | 前端未调用 |
| POST | `/admin/quality/inspection/record` | config/route.php:365（route(post)） | 前端未调用 |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:362（resource(show)） | 前端未调用 |
| GET | `/admin/quality/iqc/{id}` | config/route.php:361（resource(show)） | 前端未调用 |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:364（resource(show)） | 前端未调用 |
| GET | `/admin/quality/oqc/{id}` | config/route.php:363（resource(show)） | 前端未调用 |
| GET | `/admin/quality/standard/{id}` | config/route.php:360（resource(show)） | 前端未调用 |

### 模块：report

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:301（resource(show)） | 前端未调用 |
| GET | `/admin/report/{id}` | config/route.php:304（resource(show)） | 前端未调用 |

### 模块：sales

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:134（resource(show)） | 前端未调用 |
| GET | `/admin/sales/quotation/{id}` | config/route.php:132（resource(show)） | 前端未调用 |
| GET | `/admin/sales/return/{id}` | config/route.php:135（resource(show)） | 前端未调用 |
| GET | `/admin/sales/settlement/{id}` | config/route.php:136（resource(show)） | 前端未调用 |

### 模块：supplier

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:116（resource(show)） | 前端未调用 |

### 模块：tms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:353（resource(show)） | 前端未调用 |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:354（route(post)） | 前端未调用 |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:355（route(post)） | 前端未调用 |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:346（route(post)） | 前端未调用 |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:347（route(get)） | 前端未调用 |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | 前端未调用 |
| GET | `/admin/tms/service` | config/route.php:343（resource(index)） | 前端未调用 |
| POST | `/admin/tms/service` | config/route.php:343（resource(store)） | 前端未调用 |
| DELETE | `/admin/tms/service/{id}` | config/route.php:343（resource(destroy)） | 前端未调用 |
| GET | `/admin/tms/service/{id}` | config/route.php:343（resource(show)） | 前端未调用 |
| PUT | `/admin/tms/service/{id}` | config/route.php:343（resource(update)） | 前端未调用 |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:351（route(post)） | 前端未调用 |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:350（route(post)） | 前端未调用 |
| GET | `/admin/tms/tracking/{id}` | config/route.php:352（resource(show)） | 前端未调用 |
| POST | `/api/tms/tracking/callback` | config/route.php:417（route(post)） | 系统/非前端直调（webhook、健康检查、安装向导等） |

### 模块：upload

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:105（route(post)） | 前端未调用 |

### 模块：warehouse

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:113（resource(show)） | 前端未调用 |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:114（route(get)） | 前端未调用 |

### 模块：wms

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:325（resource(show)） | 前端未调用 |
| GET | `/admin/wms/location` | config/route.php:324（resource(index)） | 前端未调用 |
| POST | `/admin/wms/location` | config/route.php:324（resource(store)） | 前端未调用 |
| DELETE | `/admin/wms/location/{id}` | config/route.php:324（resource(destroy)） | 前端未调用 |
| GET | `/admin/wms/location/{id}` | config/route.php:324（resource(show)） | 前端未调用 |
| PUT | `/admin/wms/location/{id}` | config/route.php:324（resource(update)） | 前端未调用 |
| GET | `/admin/wms/pack/{id}` | config/route.php:335（resource(show)） | 前端未调用 |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:337（route(post)） | 前端未调用 |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:336（route(post)） | 前端未调用 |
| GET | `/admin/wms/pick/{id}` | config/route.php:332（resource(show)） | 前端未调用 |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:334（route(post)） | 前端未调用 |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:333（route(post)） | 前端未调用 |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:329（route(post)） | 前端未调用 |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:327（route(post)） | 前端未调用 |
| GET | `/admin/wms/wave/{id}` | config/route.php:330（resource(show)） | 前端未调用 |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:331（route(post)） | 前端未调用 |
| GET | `/admin/wms/zone/{id}` | config/route.php:323（resource(show)） | 前端未调用 |

### 模块：workflow

| 方法 | 路径 | 路由位置 | 说明 |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:238（resource(show)） | 前端未调用 |
| POST | `/admin/workflow/{id}/submit` | config/route.php:239（route(post)） | 前端未调用 |

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

