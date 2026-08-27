# Informe de auditoría de endpoints — Comparación de solicitudes frontend × rutas backend

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Generado: 2026-08-16T01:02:44+08:00　｜　Script de generación: `scripts/check-endpoints.php` (reproducible)
> Caso smoke `/admin/notification/my/read`: escaneo de código fuente no detectado (posiblemente ya corregido en el workspace) ｜ Autocomprobación de lógica de coincidencia superada ✅

## Estadísticas

- Rutas registradas en el backend (tras expansión): 564
- Llamadas de solicitudes frontend: Flutter 377 / HarmonyOS 40
- Endpoints muertos: 20　｜　Brechas de cobertura: 211　｜　Sin resolver: 0

## 1. Lista de endpoints muertos (el frontend llama pero el backend no existe)

| # | Módulo | Método | Ruta | Fuente | Ubicación de la llamada |
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

## 2. Lista de brechas de cobertura (existen en el backend pero ni Flutter ni HarmonyOS las llaman, agrupadas por módulo)

### Módulo: .well-known

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: approval

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245 (route(post)) | El frontend no lo llama |
| POST | `/admin/approval/{id}/reject` | config/route.php:246 (route(post)) | El frontend no lo llama |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247 (route(post)) | El frontend no lo llama |

### Módulo: auth

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408 (route(post)) | El frontend no lo llama |

### Módulo: bi

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373 (resource(show)) | El frontend no lo llama |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375 (resource(show)) | El frontend no lo llama |
| GET | `/admin/bi/widget` | config/route.php:374 (resource(index)) | El frontend no lo llama |
| POST | `/admin/bi/widget` | config/route.php:374 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/bi/widget/{id}` | config/route.php:374 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374 (resource(update)) | El frontend no lo llama |

### Módulo: brand

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117 (resource(show)) | El frontend no lo llama |

### Módulo: category

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116 (resource(show)) | El frontend no lo llama |

### Módulo: crm

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226 (route(post)) | El frontend no lo llama |
| GET | `/admin/crm/analytics/metric` | config/route.php:227 (route(get)) | El frontend no lo llama |
| POST | `/admin/crm/analytics/metric` | config/route.php:228 (route(post)) | El frontend no lo llama |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225 (route(get)) | El frontend no lo llama |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219 (resource(show)) | El frontend no lo llama |
| GET | `/admin/crm/contact/{id}` | config/route.php:202 (resource(show)) | El frontend no lo llama |
| GET | `/admin/crm/contract/{id}` | config/route.php:211 (resource(show)) | El frontend no lo llama |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212 (route(post)) | El frontend no lo llama |
| GET | `/admin/crm/follow` | config/route.php:200 (resource(index)) | El frontend no lo llama |
| POST | `/admin/crm/follow` | config/route.php:200 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/crm/follow/{id}` | config/route.php:200 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200 (resource(update)) | El frontend no lo llama |
| GET | `/admin/crm/funnel` | config/route.php:201 (resource(index)) | El frontend no lo llama |
| POST | `/admin/crm/funnel` | config/route.php:201 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(update)) | El frontend no lo llama |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199 (resource(show)) | El frontend no lo llama |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208 (route(post)) | El frontend no lo llama |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209 (route(post)) | El frontend no lo llama |
| GET | `/admin/crm/pool/rules` | config/route.php:210 (route(get)) | El frontend no lo llama |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213 (resource(show)) | El frontend no lo llama |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214 (route(post)) | El frontend no lo llama |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220 (resource(show)) | El frontend no lo llama |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221 (route(post)) | El frontend no lo llama |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223 (route(post)) | El frontend no lo llama |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222 (route(post)) | El frontend no lo llama |

### Módulo: customer

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122 (resource(show)) | El frontend no lo llama |

### Módulo: customer-level

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123 (route(any)) | El frontend no lo llama |

### Módulo: dashboard

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235 (route(any)) | El frontend no lo llama |
| ANY | `/admin/dashboard/inventory` | config/route.php:234 (route(any)) | El frontend no lo llama |
| ANY | `/admin/dashboard/sales` | config/route.php:233 (route(any)) | El frontend no lo llama |

### Módulo: debug

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: dms

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390 (route(get)) | El frontend no lo llama |
| GET | `/admin/dms/document/{id}` | config/route.php:389 (resource(show)) | El frontend no lo llama |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391 (route(get)) | El frontend no lo llama |

### Módulo: docs

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: eam

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380 (resource(show)) | El frontend no lo llama |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381 (resource(show)) | El frontend no lo llama |
| GET | `/admin/eam/repair/{id}` | config/route.php:382 (resource(show)) | El frontend no lo llama |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384 (resource(show)) | El frontend no lo llama |

### Módulo: finance

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/asset/{id}` | config/route.php:182 (resource(show)) | El frontend no lo llama |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183 (route(post)) | El frontend no lo llama |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184 (route(any)) | El frontend no lo llama |
| GET | `/admin/finance/bank-account` | config/route.php:167 (resource(index)) | El frontend no lo llama |
| POST | `/admin/finance/bank-account` | config/route.php:167 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(update)) | El frontend no lo llama |
| GET | `/admin/finance/budget/{id}` | config/route.php:191 (resource(show)) | El frontend no lo llama |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192 (route(any)) | El frontend no lo llama |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/currency/{id}` | config/route.php:189 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/exchange-rate` | config/route.php:190 (resource(index)) | El frontend no lo llama |
| POST | `/admin/finance/exchange-rate` | config/route.php:190 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(update)) | El frontend no lo llama |
| GET | `/admin/finance/expense/{id}` | config/route.php:160 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/payment/{id}` | config/route.php:158 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/profit-center` | config/route.php:194 (resource(index)) | El frontend no lo llama |
| POST | `/admin/finance/profit-center` | config/route.php:194 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(update)) | El frontend no lo llama |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157 (resource(show)) | El frontend no lo llama |
| GET | `/admin/finance/report/account-balance` | config/route.php:166 (route(get)) | El frontend no lo llama |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174 (route(any)) | El frontend no lo llama |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175 (route(post)) | El frontend no lo llama |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176 (route(any)) | El frontend no lo llama |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177 (route(post)) | El frontend no lo llama |
| POST | `/admin/finance/report/close-period` | config/route.php:162 (route(post)) | El frontend no lo llama |
| POST | `/admin/finance/report/consolidate` | config/route.php:163 (route(post)) | El frontend no lo llama |
| POST | `/admin/finance/report/ratios` | config/route.php:164 (route(post)) | El frontend no lo llama |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165 (route(get)) | El frontend no lo llama |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173 (route(any)) | El frontend no lo llama |
| ANY | `/admin/finance/tax-record` | config/route.php:188 (route(any)) | El frontend no lo llama |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156 (resource(show)) | El frontend no lo llama |

### Módulo: health

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/health` | config/route.php:46 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: hr

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272 (route(post)) | El frontend no lo llama |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273 (route(post)) | El frontend no lo llama |
| GET | `/admin/hr/department/{id}` | config/route.php:268 (resource(show)) | El frontend no lo llama |
| GET | `/admin/hr/employee/{id}` | config/route.php:269 (resource(show)) | El frontend no lo llama |
| GET | `/admin/hr/leave/{id}` | config/route.php:276 (route(get)) | El frontend no lo llama |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279 (route(post)) | El frontend no lo llama |
| GET | `/admin/hr/position/{id}` | config/route.php:270 (resource(show)) | El frontend no lo llama |
| GET | `/admin/hr/salary-item` | config/route.php:284 (route(get)) | El frontend no lo llama |
| POST | `/admin/hr/salary-item` | config/route.php:285 (route(post)) | El frontend no lo llama |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288 (route(delete)) | El frontend no lo llama |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286 (route(get)) | El frontend no lo llama |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287 (route(put)) | El frontend no lo llama |
| POST | `/admin/hr/salary/calculate` | config/route.php:281 (route(post)) | El frontend no lo llama |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282 (route(post)) | El frontend no lo llama |
| GET | `/admin/hr/salary/{id}` | config/route.php:280 (resource(show)) | El frontend no lo llama |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283 (route(post)) | El frontend no lo llama |

### Módulo: import

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107 (route(post)) | El frontend no lo llama |

### Módulo: install

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40 (route(any)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |
| GET | `/install/test-db` | config/route.php:41 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: inventory

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150 (resource(show)) | El frontend no lo llama |
| GET | `/admin/inventory/check/{id}` | config/route.php:149 (resource(show)) | El frontend no lo llama |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148 (resource(show)) | El frontend no lo llama |

### Módulo: location

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120 (resource(show)) | El frontend no lo llama |

### Módulo: metrics

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49 (route(get)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: mfg

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293 (resource(show)) | El frontend no lo llama |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299 (resource(show)) | El frontend no lo llama |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300 (route(post)) | El frontend no lo llama |
| GET | `/admin/mfg/production/{id}` | config/route.php:294 (resource(show)) | El frontend no lo llama |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296 (route(post)) | El frontend no lo llama |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295 (route(post)) | El frontend no lo llama |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297 (resource(show)) | El frontend no lo llama |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298 (resource(show)) | El frontend no lo llama |

### Módulo: notification

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256 (route(any)) | El frontend no lo llama |
| POST | `/admin/notification/{id}/read` | config/route.php:254 (route(post)) | El frontend no lo llama |

### Módulo: oms

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322 (resource(show)) | El frontend no lo llama |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317 (resource(show)) | El frontend no lo llama |
| GET | `/admin/oms/order/{id}` | config/route.php:313 (resource(show)) | El frontend no lo llama |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314 (route(post)) | El frontend no lo llama |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316 (route(post)) | El frontend no lo llama |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315 (route(post)) | El frontend no lo llama |
| GET | `/admin/oms/rma/{id}` | config/route.php:318 (resource(show)) | El frontend no lo llama |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319 (route(post)) | El frontend no lo llama |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320 (route(post)) | El frontend no lo llama |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321 (route(post)) | El frontend no lo llama |

### Módulo: permission

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/permission/{id}` | config/route.php:86 (resource(destroy)) | El frontend no lo llama |
| PUT | `/admin/permission/{id}` | config/route.php:86 (resource(update)) | El frontend no lo llama |

### Módulo: product

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115 (resource(show)) | El frontend no lo llama |
| ANY | `/api/product` | config/route.php:412 (route(any)) | El frontend no lo llama |
| ANY | `/api/product/{hashid}` | config/route.php:413 (route(any)) | El frontend no lo llama |

### Módulo: project

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261 (resource(show)) | El frontend no lo llama |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262 (resource(show)) | El frontend no lo llama |
| GET | `/admin/project/{id}` | config/route.php:263 (resource(show)) | El frontend no lo llama |

### Módulo: purchase

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128 (resource(show)) | El frontend no lo llama |
| GET | `/admin/purchase/order/{id}` | config/route.php:129 (resource(show)) | El frontend no lo llama |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130 (resource(show)) | El frontend no lo llama |
| GET | `/admin/purchase/return/{id}` | config/route.php:131 (resource(show)) | El frontend no lo llama |
| ANY | `/admin/purchase/settlement` | config/route.php:132 (route(any)) | El frontend no lo llama |

### Módulo: quality

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368 (route(post)) | El frontend no lo llama |
| POST | `/admin/quality/inspection/record` | config/route.php:367 (route(post)) | El frontend no lo llama |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364 (resource(show)) | El frontend no lo llama |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363 (resource(show)) | El frontend no lo llama |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366 (resource(show)) | El frontend no lo llama |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365 (resource(show)) | El frontend no lo llama |
| GET | `/admin/quality/standard/{id}` | config/route.php:362 (resource(show)) | El frontend no lo llama |

### Módulo: report

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305 (resource(show)) | El frontend no lo llama |
| GET | `/admin/report/{id}` | config/route.php:308 (resource(show)) | El frontend no lo llama |
| POST | `/admin/report/{id}/execute` | config/route.php:306 (route(post)) | El frontend no lo llama |
| ANY | `/admin/report/{id}/result` | config/route.php:307 (route(any)) | El frontend no lo llama |

### Módulo: sales

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139 (resource(show)) | El frontend no lo llama |
| GET | `/admin/sales/order/{id}` | config/route.php:138 (resource(show)) | El frontend no lo llama |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137 (resource(show)) | El frontend no lo llama |
| GET | `/admin/sales/return/{id}` | config/route.php:140 (resource(show)) | El frontend no lo llama |
| ANY | `/admin/sales/settlement` | config/route.php:141 (route(any)) | El frontend no lo llama |

### Módulo: supplier

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121 (resource(show)) | El frontend no lo llama |

### Módulo: tms

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346 (resource(show)) | El frontend no lo llama |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355 (resource(show)) | El frontend no lo llama |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356 (route(post)) | El frontend no lo llama |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357 (route(post)) | El frontend no lo llama |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349 (route(post)) | El frontend no lo llama |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350 (route(get)) | El frontend no lo llama |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348 (resource(show)) | El frontend no lo llama |
| GET | `/admin/tms/service` | config/route.php:347 (resource(index)) | El frontend no lo llama |
| POST | `/admin/tms/service` | config/route.php:347 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/tms/service/{id}` | config/route.php:347 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/tms/service/{id}` | config/route.php:347 (resource(update)) | El frontend no lo llama |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351 (resource(show)) | El frontend no lo llama |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353 (route(post)) | El frontend no lo llama |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352 (route(post)) | El frontend no lo llama |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354 (resource(show)) | El frontend no lo llama |
| POST | `/api/tms/tracking/callback` | config/route.php:419 (route(post)) | Sistema/no invocado directamente por el frontend (webhook, healthcheck, asistente de instalación, etc.) |

### Módulo: upload

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110 (route(post)) | El frontend no lo llama |

### Módulo: warehouse

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118 (resource(show)) | El frontend no lo llama |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119 (route(get)) | El frontend no lo llama |

### Módulo: wms

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329 (resource(show)) | El frontend no lo llama |
| GET | `/admin/wms/location` | config/route.php:328 (resource(index)) | El frontend no lo llama |
| POST | `/admin/wms/location` | config/route.php:328 (resource(store)) | El frontend no lo llama |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328 (resource(destroy)) | El frontend no lo llama |
| GET | `/admin/wms/location/{id}` | config/route.php:328 (resource(show)) | El frontend no lo llama |
| PUT | `/admin/wms/location/{id}` | config/route.php:328 (resource(update)) | El frontend no lo llama |
| GET | `/admin/wms/pack/{id}` | config/route.php:339 (resource(show)) | El frontend no lo llama |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341 (route(post)) | El frontend no lo llama |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340 (route(post)) | El frontend no lo llama |
| GET | `/admin/wms/pick/{id}` | config/route.php:336 (resource(show)) | El frontend no lo llama |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338 (route(post)) | El frontend no lo llama |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337 (route(post)) | El frontend no lo llama |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332 (resource(show)) | El frontend no lo llama |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333 (route(post)) | El frontend no lo llama |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330 (resource(show)) | El frontend no lo llama |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331 (route(post)) | El frontend no lo llama |
| GET | `/admin/wms/wave/{id}` | config/route.php:334 (resource(show)) | El frontend no lo llama |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335 (route(post)) | El frontend no lo llama |
| GET | `/admin/wms/zone/{id}` | config/route.php:327 (resource(show)) | El frontend no lo llama |

### Módulo: workflow

| Método | Ruta | Ubicación de la ruta | Descripción |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243 (resource(show)) | El frontend no lo llama |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244 (route(post)) | El frontend no lo llama |

## 3. Rutas sin resolver (revisión manual)

(ninguna)

## 4. Instrucciones de uso del script

Script: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Salida de texto (por defecto)
php scripts/check-endpoints.php

# Salida JSON (para consumo por otras herramientas)
php scripts/check-endpoints.php --json

# Filtrar solo un módulo (p. ej. finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# Regenerar este informe de auditoría
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Cómo funciona:

1. **Backend**: analiza `config/route.php`, reconstruye el prefijo de `Route::group` en la ruta completa; `Route::resource` se expande según los métodos realmente existentes del controlador (index/store/show/update/destroy, etc.); `Route::any` se considera cualquier método.
2. **Frontend**: escanea todos los archivos `.dart` del directorio `apps/flutter/lib` y todos los `.ets` del directorio `apps/harmonyos/entry/src/main/ets`, extrayendo los literales de ruta de llamadas como `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()`, etc.; admite interpolación `${...}` / `$var` y cadenas de plantilla (incluida la eliminación del prefijo `${BASE_URL}`).
3. **Coincidencia**: los segmentos literales del frontend solo coinciden con segmentos literales del backend; los segmentos dinámicos del frontend solo coinciden con segmentos `{param}` del backend (garantizando que `/admin/notification/my/read` no se empareje erróneamente con `/admin/notification/{id}/read`); los métodos coinciden exactamente por método HTTP y `any` coincide con todo.
4. **Listados**: ① endpoints muertos (el frontend llama pero el backend no existe, máxima prioridad) → ② brechas de cobertura (existen en el backend pero el frontend no las llama, agrupadas por módulo; las rutas de sistema como webhooks/healthchecks ya están marcadas) → ③ rutas sin resolver (rutas variables, concatenación de cadenas, interpolaciones sin cerrar, etc., requieren revisión manual).
