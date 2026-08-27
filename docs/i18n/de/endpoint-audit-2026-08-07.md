# Endpunkt-Audit-Bericht — Abgleich Frontend-Anfragen × Backend-Routen

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Erzeugt: 2026-08-16T01:02:44+08:00　｜　Skript: `scripts/check-endpoints.php` (wiederholbar ausführbar)
> Smoke-Testfall `/admin/notification/my/read`: Quellcode-Scan nicht gefunden (möglicherweise bereits im Arbeitsbereich behoben) ｜ Selbsttest der Abgleichlogik bestanden ✅

## Statistiken

- Im Backend registrierte Routen (aufgelöst): 564
- Frontend-Anfrageaufrufe: Flutter 377 Stellen / HarmonyOS 40 Stellen
- Tote Endpunkte: 20　｜　Abdeckungslücken: 211　｜　Nicht auflösbar: 0

## 1. Liste toter Endpunkte (vom Frontend aufgerufen, im Backend nicht vorhanden)

| # | Modul | Methode | Pfad | Quelle | Aufrufstelle |
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

## 2. Liste der Abdeckungslücken (im Backend vorhanden, aber weder von Flutter noch von HarmonyOS aufgerufen, nach Modul gruppiert)

### Modul: .well-known

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: approval

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/approval/{id}/reject` | config/route.php:246 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: auth

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: bi

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/bi/widget` | config/route.php:374 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/bi/widget` | config/route.php:374 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/bi/widget/{id}` | config/route.php:374 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374 (resource(update)) | vom Frontend nicht aufgerufen |

### Modul: brand

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: category

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: crm

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/analytics/metric` | config/route.php:227 (route(get)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/analytics/metric` | config/route.php:228 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225 (route(get)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/contact/{id}` | config/route.php:202 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/contract/{id}` | config/route.php:211 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/follow` | config/route.php:200 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/follow` | config/route.php:200 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/follow/{id}` | config/route.php:200 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/funnel` | config/route.php:201 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/funnel` | config/route.php:201 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/pool/rules` | config/route.php:210 (route(get)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: customer

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: customer-level

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: dashboard

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235 (route(any)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/dashboard/inventory` | config/route.php:234 (route(any)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/dashboard/sales` | config/route.php:233 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: debug

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: dms

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390 (route(get)) | vom Frontend nicht aufgerufen |
| GET | `/admin/dms/document/{id}` | config/route.php:389 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391 (route(get)) | vom Frontend nicht aufgerufen |

### Modul: docs

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: eam

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/eam/repair/{id}` | config/route.php:382 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: finance

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/asset/{id}` | config/route.php:182 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183 (route(post)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184 (route(any)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/bank-account` | config/route.php:167 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/bank-account` | config/route.php:167 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/budget/{id}` | config/route.php:191 (resource(show)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192 (route(any)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/currency/{id}` | config/route.php:189 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/exchange-rate` | config/route.php:190 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/exchange-rate` | config/route.php:190 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/expense/{id}` | config/route.php:160 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/payment/{id}` | config/route.php:158 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/profit-center` | config/route.php:194 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/profit-center` | config/route.php:194 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/report/account-balance` | config/route.php:166 (route(get)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174 (route(any)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175 (route(post)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176 (route(any)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/report/close-period` | config/route.php:162 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/report/consolidate` | config/route.php:163 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/finance/report/ratios` | config/route.php:164 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165 (route(get)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173 (route(any)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/finance/tax-record` | config/route.php:188 (route(any)) | vom Frontend nicht aufgerufen |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: health

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/health` | config/route.php:46 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: hr

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/department/{id}` | config/route.php:268 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/employee/{id}` | config/route.php:269 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/leave/{id}` | config/route.php:276 (route(get)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/position/{id}` | config/route.php:270 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/salary-item` | config/route.php:284 (route(get)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/salary-item` | config/route.php:285 (route(post)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288 (route(delete)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286 (route(get)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287 (route(put)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/salary/calculate` | config/route.php:281 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/hr/salary/{id}` | config/route.php:280 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: import

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: install

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40 (route(any)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |
| GET | `/install/test-db` | config/route.php:41 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: inventory

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/inventory/check/{id}` | config/route.php:149 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: location

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: metrics

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49 (route(get)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: mfg

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/mfg/production/{id}` | config/route.php:294 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: notification

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256 (route(any)) | vom Frontend nicht aufgerufen |
| POST | `/admin/notification/{id}/read` | config/route.php:254 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: oms

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/oms/order/{id}` | config/route.php:313 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/oms/rma/{id}` | config/route.php:318 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: permission

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/permission/{id}` | config/route.php:86 (resource(destroy)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/permission/{id}` | config/route.php:86 (resource(update)) | vom Frontend nicht aufgerufen |

### Modul: product

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115 (resource(show)) | vom Frontend nicht aufgerufen |
| ANY | `/api/product` | config/route.php:412 (route(any)) | vom Frontend nicht aufgerufen |
| ANY | `/api/product/{hashid}` | config/route.php:413 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: project

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/project/{id}` | config/route.php:263 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: purchase

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/purchase/order/{id}` | config/route.php:129 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/purchase/return/{id}` | config/route.php:131 (resource(show)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/purchase/settlement` | config/route.php:132 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: quality

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/quality/inspection/record` | config/route.php:367 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/quality/standard/{id}` | config/route.php:362 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: report

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/report/{id}` | config/route.php:308 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/report/{id}/execute` | config/route.php:306 (route(post)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/report/{id}/result` | config/route.php:307 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: sales

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/sales/order/{id}` | config/route.php:138 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/sales/return/{id}` | config/route.php:140 (resource(show)) | vom Frontend nicht aufgerufen |
| ANY | `/admin/sales/settlement` | config/route.php:141 (route(any)) | vom Frontend nicht aufgerufen |

### Modul: supplier

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: tms

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350 (route(get)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/service` | config/route.php:347 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/service` | config/route.php:347 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/service/{id}` | config/route.php:347 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/tms/service/{id}` | config/route.php:347 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/api/tms/tracking/callback` | config/route.php:419 (route(post)) | System-/kein-Frontend-Direktaufruf (Webhook, Healthcheck, Installationsassistent usw.) |

### Modul: upload

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110 (route(post)) | vom Frontend nicht aufgerufen |

### Modul: warehouse

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119 (route(get)) | vom Frontend nicht aufgerufen |

### Modul: wms

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329 (resource(show)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/location` | config/route.php:328 (resource(index)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/location` | config/route.php:328 (resource(store)) | vom Frontend nicht aufgerufen |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328 (resource(destroy)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/location/{id}` | config/route.php:328 (resource(show)) | vom Frontend nicht aufgerufen |
| PUT | `/admin/wms/location/{id}` | config/route.php:328 (resource(update)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/pack/{id}` | config/route.php:339 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/pick/{id}` | config/route.php:336 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338 (route(post)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/wave/{id}` | config/route.php:334 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335 (route(post)) | vom Frontend nicht aufgerufen |
| GET | `/admin/wms/zone/{id}` | config/route.php:327 (resource(show)) | vom Frontend nicht aufgerufen |

### Modul: workflow

| Methode | Pfad | Routenposition | Beschreibung |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243 (resource(show)) | vom Frontend nicht aufgerufen |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244 (route(post)) | vom Frontend nicht aufgerufen |

## 3. Nicht auflösbare Pfade (manuelle Prüfung)

(keine)

## 4. Hinweise zur Skriptverwendung

Skript: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Textausgabe (Standard)
php scripts/check-endpoints.php

# JSON-Ausgabe (für die Weiterverarbeitung durch nachgelagerte Tools)
php scripts/check-endpoints.php --json

# Nur ein einzelnes Modul filtern (z. B. finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# Diesen Audit-Bericht neu erzeugen
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Funktionsweise:

1. **Backend**: `config/route.php` parsen, `Route::group`-Präfixe zu vollständigen Pfaden rekonstruieren; `Route::resource` wird anhand der tatsächlich im Controller vorhandenen Methoden aufgelöst (index/store/show/update/destroy usw.); `Route::any` wird als beliebige Methode behandelt.
2. **Frontend**: Alle `.dart`-Dateien unter `apps/flutter/lib` und alle `.ets`-Dateien unter `apps/harmonyos/entry/src/main/ets` scannen und Pfadliterale aus Aufrufen wie `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()` extrahieren; `${...}` / `$var`-Interpolationen und Template-Strings werden unterstützt (einschließlich Entfernen des `${BASE_URL}`-Präfixes).
3. **Abgleich**: Frontend-Literalsegmente matchen nur Backend-Literalsegmente, Frontend-Dynamiksegmente matchen nur Backend-`{param}`-Segmente (dadurch wird `/admin/notification/my/read` nicht fälschlich `/admin/notification/{id}/read` zugeordnet); die Methode wird exakt nach HTTP-Methode abgeglichen, `any` matcht alles.
4. **Listen**: ① tote Endpunkte (vom Frontend aufgerufen, im Backend nicht vorhanden — höchste Priorität) → ② Abdeckungslücken (im Backend vorhanden, aber von keinem Frontend aufgerufen, nach Modul gruppiert; Systemrouten wie Webhook/Healthcheck sind gekennzeichnet) → ③ nicht auflösbare Pfade (variable Pfade, String-Verkettung, ungeschlossene Interpolationen usw., manuelle Prüfung erforderlich).
