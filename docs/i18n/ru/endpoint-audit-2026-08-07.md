# Отчёт аудита точек API — сопоставление запросов фронтенда × маршрутов бэкенда

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Сформирован: 2026-08-16T01:02:44+08:00　｜　Скрипт генерации: `scripts/check-endpoints.php` (повторно запускаемый)
> Смоук-кейс `/admin/notification/my/read`: сканирование исходников — не найдено (возможно, уже исправлено в рабочей копии) ｜ Самотест логики сопоставления — пройден ✅

## Статистика

- Зарегистрированных маршрутов бэкенда (после развёртывания): 564
- Вызовов фронтенда: Flutter 377 / HarmonyOS 40
- Мёртвых точек: 20 ｜ Пробелов покрытия: 211 ｜ Нераспознаваемых: 0

## I. Список мёртвых точек (фронтенд вызывает, бэкенд не существует)

| # | Модуль | Метод | Путь | Источник | Место вызова |
|---|--------|-------|------|----------|--------------|
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

## II. Список пробелов покрытия (бэкенд существует, но ни Flutter, ни HarmonyOS не вызывают; сгруппировано по модулям)

### Модуль: .well-known

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: approval

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | Фронтенд не вызывает |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | Фронтенд не вызывает |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | Фронтенд не вызывает |

### Модуль: auth

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | Фронтенд не вызывает |

### Модуль: bi

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | Фронтенд не вызывает |

### Модуль: brand

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | Фронтенд не вызывает |

### Модуль: category

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | Фронтенд не вызывает |

### Модуль: crm

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | Фронтенд не вызывает |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | Фронтенд не вызывает |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | Фронтенд не вызывает |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | Фронтенд не вызывает |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | Фронтенд не вызывает |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | Фронтенд не вызывает |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | Фронтенд не вызывает |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | Фронтенд не вызывает |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | Фронтенд не вызывает |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | Фронтенд не вызывает |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | Фронтенд не вызывает |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | Фронтенд не вызывает |

### Модуль: customer

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | Фронтенд не вызывает |

### Модуль: customer-level

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | Фронтенд не вызывает |

### Модуль: dashboard

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | Фронтенд не вызывает |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | Фронтенд не вызывает |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | Фронтенд не вызывает |

### Модуль: debug

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: dms

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | Фронтенд не вызывает |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | Фронтенд не вызывает |

### Модуль: docs

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/api/docs` | config/route.php:68（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: eam

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | Фронтенд не вызывает |

### Модуль: finance

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | Фронтенд не вызывает |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | Фронтенд не вызывает |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | Фронтенд не вызывает |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | Фронтенд не вызывает |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | Фронтенд не вызывает |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | Фронтенд не вызывает |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | Фронтенд не вызывает |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | Фронтенд не вызывает |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | Фронтенд не вызывает |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | Фронтенд не вызывает |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | Фронтенд не вызывает |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | Фронтенд не вызывает |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | Фронтенд не вызывает |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | Фронтенд не вызывает |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | Фронтенд не вызывает |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | Фронтенд не вызывает |

### Модуль: health

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/health` | config/route.php:46（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: hr

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | Фронтенд не вызывает |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | Фронтенд не вызывает |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | Фронтенд не вызывает |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | Фронтенд не вызывает |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | Фронтенд не вызывает |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | Фронтенд не вызывает |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | Фронтенд не вызывает |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | Фронтенд не вызывает |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | Фронтенд не вызывает |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | Фронтенд не вызывает |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | Фронтенд не вызывает |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | Фронтенд не вызывает |

### Модуль: import

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | Фронтенд не вызывает |

### Модуль: install

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| ANY | `/install` | config/route.php:40（route(any)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: inventory

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | Фронтенд не вызывает |

### Модуль: location

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | Фронтенд не вызывает |

### Модуль: metrics

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/metrics` | config/route.php:49（route(get)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: mfg

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | Фронтенд не вызывает |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | Фронтенд не вызывает |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | Фронтенд не вызывает |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | Фронтенд не вызывает |

### Модуль: notification

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | Фронтенд не вызывает |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | Фронтенд не вызывает |

### Модуль: oms

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | Фронтенд не вызывает |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | Фронтенд не вызывает |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | Фронтенд не вызывает |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | Фронтенд не вызывает |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | Фронтенд не вызывает |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | Фронтенд не вызывает |

### Модуль: permission

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | Фронтенд не вызывает |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | Фронтенд не вызывает |

### Модуль: product

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | Фронтенд не вызывает |
| ANY | `/api/product` | config/route.php:412（route(any)） | Фронтенд не вызывает |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | Фронтенд не вызывает |

### Модуль: project

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | Фронтенд не вызывает |

### Модуль: purchase

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | Фронтенд не вызывает |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | Фронтенд не вызывает |

### Модуль: quality

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | Фронтенд не вызывает |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | Фронтенд не вызывает |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | Фронтенд не вызывает |

### Модуль: report

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | Фронтенд не вызывает |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | Фронтенд не вызывает |

### Модуль: sales

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | Фронтенд не вызывает |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | Фронтенд не вызывает |

### Модуль: supplier

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | Фронтенд не вызывает |

### Модуль: tms

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | Фронтенд не вызывает |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | Фронтенд не вызывает |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | Фронтенд не вызывает |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | Фронтенд не вызывает |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | Фронтенд не вызывает |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | Фронтенд не вызывает |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | Фронтенд не вызывает |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | Системный / не прямой вызов фронтенда (webhook, healthcheck, мастер установки и т. п.) |

### Модуль: upload

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | Фронтенд не вызывает |

### Модуль: warehouse

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | Фронтенд не вызывает |

### Модуль: wms

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | Фронтенд не вызывает |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | Фронтенд не вызывает |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | Фронтенд не вызывает |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | Фронтенд не вызывает |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | Фронтенд не вызывает |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | Фронтенд не вызывает |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | Фронтенд не вызывает |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | Фронтенд не вызывает |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | Фронтенд не вызывает |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | Фронтенд не вызывает |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | Фронтенд не вызывает |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | Фронтенд не вызывает |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | Фронтенд не вызывает |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | Фронтенд не вызывает |

### Модуль: workflow

| Метод | Путь | Расположение маршрута | Описание |
|-------|------|-----------------------|----------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | Фронтенд не вызывает |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | Фронтенд не вызывает |

## III. Нераспознаваемые пути (ручная проверка)

(нет)

## IV. Инструкция по использованию скрипта

Скрипт: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Текстовый вывод (по умолчанию)
php scripts/check-endpoints.php

# JSON-вывод (для последующего потребления инструментами)
php scripts/check-endpoints.php --json

# Фильтр по одному модулю (например, finance、wms、notification)
php scripts/check-endpoints.php --module=finance

# Повторная генерация данного отчёта аудита
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Принцип работы:

1. **Бэкенд**: разбор `config/route.php`; восстановление префикса `Route::group` в полные пути; `Route::resource` раскрывается по фактически существующим методам контроллера (index/store/show/update/destroy и т. д.); `Route::any` считается любым методом.
2. **Фронтенд**: сканирование всех `.dart` в `apps/flutter/lib` и всех `.ets` в `apps/harmonyos/entry/src/main/ets`; извлечение литералов путей из вызовов `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()` и т. п.; поддержка интерполяции `${...}` / `$var` и шаблонных строк (включая снятие префикса `${BASE_URL}`).
3. **Сопоставление**: литеральные сегменты фронтенда сопоставляются только с литеральными сегментами бэкенда, динамические сегменты фронтенда — только с сегментами `{param}` бэкенда (гарантирует, что `/admin/notification/my/read` не будет ошибочно сопоставлен с `/admin/notification/{id}/read`); метод сопоставляется точно по HTTP-методу, `any` подходит всему.
4. **Списки**: ① мёртвые точки (фронтенд вызывает, бэкенд не существует; высший приоритет) → ② пробелы покрытия (бэкенд существует, фронтенд не вызывает; сгруппировано по модулям; системные маршруты webhook/healthcheck и т. п. отмечены) → ③ нераспознаваемые пути (переменные пути, конкатенация строк, незакрытая интерполяция и т. п. — требуют ручной проверки).
