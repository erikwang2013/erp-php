# Laporan Audit Endpoint — Perbandingan Permintaan Frontend × Rute Backend

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Waktu pembuatan: 2026-08-16T01:02:44+08:00　｜　Skrip pembuatan: `scripts/check-endpoints.php` (dapat dijalankan ulang)
> Kasus smoke `/admin/notification/my/read`: pemindaian sumber tidak ditemukan (mungkin sudah diperbaiki di workspace) ｜ self-check logika pencocokan lolos ✅

## Statistik

- Rute backend yang terdaftar (setelah ekspansi): 564
- Panggilan permintaan frontend: Flutter 377 lokasi / HarmonyOS 40 lokasi
- Endpoint mati: 20　｜　Kesenjangan cakupan: 211　｜　Tidak dapat diurai: 0

## I. Daftar Endpoint Mati (dipanggil frontend tetapi tidak ada di backend)

| # | Modul | Metode | Path | Sumber | Lokasi Pemanggilan |
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

## II. Daftar Kesenjangan Cakupan (ada di backend tetapi tidak dipanggil Flutter/HarmonyOS, dikelompokkan per modul)

### Modul: .well-known

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: approval

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | Tidak dipanggil frontend |

### Modul: auth

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | Tidak dipanggil frontend |

### Modul: bi

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | Tidak dipanggil frontend |

### Modul: brand

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | Tidak dipanggil frontend |

### Modul: category

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | Tidak dipanggil frontend |

### Modul: crm

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | Tidak dipanggil frontend |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | Tidak dipanggil frontend |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | Tidak dipanggil frontend |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | Tidak dipanggil frontend |

### Modul: customer

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | Tidak dipanggil frontend |

### Modul: customer-level

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | Tidak dipanggil frontend |

### Modul: dashboard

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | Tidak dipanggil frontend |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | Tidak dipanggil frontend |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | Tidak dipanggil frontend |

### Modul: debug

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: dms

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | Tidak dipanggil frontend |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | Tidak dipanggil frontend |

### Modul: docs

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: eam

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | Tidak dipanggil frontend |

### Modul: finance

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | Tidak dipanggil frontend |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | Tidak dipanggil frontend |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | Tidak dipanggil frontend |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | Tidak dipanggil frontend |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | Tidak dipanggil frontend |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | Tidak dipanggil frontend |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | Tidak dipanggil frontend |

### Modul: health

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: hr

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | Tidak dipanggil frontend |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | Tidak dipanggil frontend |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | Tidak dipanggil frontend |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | Tidak dipanggil frontend |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | Tidak dipanggil frontend |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | Tidak dipanggil frontend |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | Tidak dipanggil frontend |

### Modul: import

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | Tidak dipanggil frontend |

### Modul: install

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: inventory

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | Tidak dipanggil frontend |

### Modul: location

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | Tidak dipanggil frontend |

### Modul: metrics

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: mfg

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | Tidak dipanggil frontend |

### Modul: notification

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | Tidak dipanggil frontend |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | Tidak dipanggil frontend |

### Modul: oms

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | Tidak dipanggil frontend |

### Modul: permission

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | Tidak dipanggil frontend |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | Tidak dipanggil frontend |

### Modul: product

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | Tidak dipanggil frontend |
| ANY | `/api/product` | config/route.php:412（route(any)） | Tidak dipanggil frontend |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | Tidak dipanggil frontend |

### Modul: project

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | Tidak dipanggil frontend |

### Modul: purchase

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | Tidak dipanggil frontend |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | Tidak dipanggil frontend |

### Modul: quality

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | Tidak dipanggil frontend |

### Modul: report

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | Tidak dipanggil frontend |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | Tidak dipanggil frontend |

### Modul: sales

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | Tidak dipanggil frontend |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | Tidak dipanggil frontend |

### Modul: supplier

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | Tidak dipanggil frontend |

### Modul: tms

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | Tidak dipanggil frontend |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | Tidak dipanggil frontend |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | Sistem/bukan panggilan langsung frontend (webhook, health check, wizard instalasi, dll.) |

### Modul: upload

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | Tidak dipanggil frontend |

### Modul: warehouse

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | Tidak dipanggil frontend |

### Modul: wms

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | Tidak dipanggil frontend |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | Tidak dipanggil frontend |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | Tidak dipanggil frontend |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | Tidak dipanggil frontend |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | Tidak dipanggil frontend |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | Tidak dipanggil frontend |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | Tidak dipanggil frontend |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | Tidak dipanggil frontend |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | Tidak dipanggil frontend |

### Modul: workflow

| Metode | Path | Lokasi Rute | Keterangan |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | Tidak dipanggil frontend |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | Tidak dipanggil frontend |

## III. Path yang Tidak Dapat Diurai (perlu review manual)

(Tidak ada)

## IV. Petunjuk Penggunaan Skrip

Skrip: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Output teks (default)
php scripts/check-endpoints.php

# Output JSON (untuk dikonsumsi alat lain)
php scripts/check-endpoints.php --json

# Filter hanya satu modul (mis. finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# Membuat ulang laporan audit ini
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Cara kerja:

1. **Backend**: mengurai `config/route.php`, memulihkan prefiks `Route::group` menjadi path lengkap; `Route::resource` diekspansi sesuai metode yang benar-benar ada di controller (index/store/show/update/destroy, dll.); `Route::any` dianggap metode apa pun.
2. **Frontend**: memindai semua file `.dart` di direktori `apps/flutter/lib` dan semua file `.ets` di direktori `apps/harmonyos/entry/src/main/ets`, mengekstrak literal path dari pemanggilan `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()` dll.; mendukung interpolasi `${...}` / `$var` dan template string (termasuk pemisahan prefiks `${BASE_URL}`).
3. **Pencocokan**: segmen literal frontend hanya cocok dengan segmen literal backend, segmen dinamis frontend hanya cocok dengan segmen `{param}` backend (menjamin `/admin/notification/my/read` tidak salah cocok dengan `/admin/notification/{id}/read`); metode dicocokkan persis berdasarkan metode HTTP, `any` cocok dengan apa pun.
4. **Daftar**: ① Endpoint mati (dipanggil frontend tetapi tidak ada di backend, prioritas tertinggi) → ② Kesenjangan cakupan (ada di backend tetapi tidak dipanggil frontend, dikelompokkan per modul; rute sistem seperti webhook/health check sudah ditandai) → ③ Path yang tidak dapat diurai (path variabel, penggabungan string, interpolasi tidak tertutup, dll., perlu review manual).
