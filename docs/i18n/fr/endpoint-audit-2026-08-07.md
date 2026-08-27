# Rapport d'audit des points d'accès — Comparaison requêtes frontend × routes backend

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Généré le : 2026-08-16T01:02:44+08:00　｜　Script de génération : `scripts/check-endpoints.php` (réexécutable)
> Cas de fumée `/admin/notification/my/read` : scan du code source non concluant (peut-être déjà corrigé dans l'espace de travail) ｜ Auto-test de la logique de correspondance réussi ✅

## Statistiques

- Routes backend enregistrées (après expansion) : 564
- Appels des requêtes frontend : Flutter 377 / HarmonyOS 40
- Points d'accès morts : 20　｜　Lacunes de couverture : 211　｜　Chemins non résolus : 0

## I. Liste des points d'accès morts (appelés par le frontend mais inexistants côté backend)

| # | Module | Méthode | Chemin | Source | Emplacement d'appel |
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

## II. Liste des lacunes de couverture (existantes côté backend mais jamais appelées par Flutter/HarmonyOS, groupées par module)

### Module : .well-known

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : approval

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | Non appelé par le frontend |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | Non appelé par le frontend |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | Non appelé par le frontend |

### Module : auth

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | Non appelé par le frontend |

### Module : bi

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | Non appelé par le frontend |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | Non appelé par le frontend |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | Non appelé par le frontend |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | Non appelé par le frontend |

### Module : brand

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | Non appelé par le frontend |

### Module : category

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | Non appelé par le frontend |

### Module : crm

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | Non appelé par le frontend |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | Non appelé par le frontend |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | Non appelé par le frontend |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | Non appelé par le frontend |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | Non appelé par le frontend |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | Non appelé par le frontend |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | Non appelé par le frontend |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | Non appelé par le frontend |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | Non appelé par le frontend |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | Non appelé par le frontend |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | Non appelé par le frontend |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | Non appelé par le frontend |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | Non appelé par le frontend |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | Non appelé par le frontend |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | Non appelé par le frontend |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | Non appelé par le frontend |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | Non appelé par le frontend |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | Non appelé par le frontend |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | Non appelé par le frontend |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | Non appelé par le frontend |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | Non appelé par le frontend |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | Non appelé par le frontend |

### Module : customer

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | Non appelé par le frontend |

### Module : customer-level

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | Non appelé par le frontend |

### Module : dashboard

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | Non appelé par le frontend |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | Non appelé par le frontend |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | Non appelé par le frontend |

### Module : debug

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : dms

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | Non appelé par le frontend |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | Non appelé par le frontend |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | Non appelé par le frontend |

### Module : docs

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : eam

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | Non appelé par le frontend |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | Non appelé par le frontend |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | Non appelé par le frontend |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | Non appelé par le frontend |

### Module : finance

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | Non appelé par le frontend |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | Non appelé par le frontend |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | Non appelé par le frontend |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | Non appelé par le frontend |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | Non appelé par le frontend |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | Non appelé par le frontend |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | Non appelé par le frontend |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | Non appelé par le frontend |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | Non appelé par le frontend |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | Non appelé par le frontend |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | Non appelé par le frontend |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | Non appelé par le frontend |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | Non appelé par le frontend |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | Non appelé par le frontend |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | Non appelé par le frontend |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | Non appelé par le frontend |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | Non appelé par le frontend |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | Non appelé par le frontend |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | Non appelé par le frontend |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | Non appelé par le frontend |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | Non appelé par le frontend |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | Non appelé par le frontend |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | Non appelé par le frontend |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | Non appelé par le frontend |

### Module : health

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : hr

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | Non appelé par le frontend |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | Non appelé par le frontend |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | Non appelé par le frontend |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | Non appelé par le frontend |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | Non appelé par le frontend |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | Non appelé par le frontend |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | Non appelé par le frontend |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | Non appelé par le frontend |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | Non appelé par le frontend |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | Non appelé par le frontend |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | Non appelé par le frontend |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | Non appelé par le frontend |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | Non appelé par le frontend |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | Non appelé par le frontend |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | Non appelé par le frontend |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | Non appelé par le frontend |

### Module : import

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | Non appelé par le frontend |

### Module : install

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : inventory

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | Non appelé par le frontend |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | Non appelé par le frontend |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | Non appelé par le frontend |

### Module : location

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | Non appelé par le frontend |

### Module : metrics

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : mfg

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | Non appelé par le frontend |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | Non appelé par le frontend |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | Non appelé par le frontend |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | Non appelé par le frontend |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | Non appelé par le frontend |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | Non appelé par le frontend |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | Non appelé par le frontend |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | Non appelé par le frontend |

### Module : notification

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | Non appelé par le frontend |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | Non appelé par le frontend |

### Module : oms

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | Non appelé par le frontend |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | Non appelé par le frontend |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | Non appelé par le frontend |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | Non appelé par le frontend |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | Non appelé par le frontend |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | Non appelé par le frontend |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | Non appelé par le frontend |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | Non appelé par le frontend |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | Non appelé par le frontend |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | Non appelé par le frontend |

### Module : permission

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | Non appelé par le frontend |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | Non appelé par le frontend |

### Module : product

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | Non appelé par le frontend |
| ANY | `/api/product` | config/route.php:412（route(any)） | Non appelé par le frontend |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | Non appelé par le frontend |

### Module : project

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | Non appelé par le frontend |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | Non appelé par le frontend |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | Non appelé par le frontend |

### Module : purchase

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | Non appelé par le frontend |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | Non appelé par le frontend |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | Non appelé par le frontend |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | Non appelé par le frontend |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | Non appelé par le frontend |

### Module : quality

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | Non appelé par le frontend |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | Non appelé par le frontend |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | Non appelé par le frontend |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | Non appelé par le frontend |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | Non appelé par le frontend |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | Non appelé par le frontend |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | Non appelé par le frontend |

### Module : report

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | Non appelé par le frontend |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | Non appelé par le frontend |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | Non appelé par le frontend |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | Non appelé par le frontend |

### Module : sales

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | Non appelé par le frontend |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | Non appelé par le frontend |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | Non appelé par le frontend |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | Non appelé par le frontend |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | Non appelé par le frontend |

### Module : supplier

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | Non appelé par le frontend |

### Module : tms

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | Non appelé par le frontend |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | Non appelé par le frontend |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | Non appelé par le frontend |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | Non appelé par le frontend |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | Non appelé par le frontend |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | Non appelé par le frontend |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | Non appelé par le frontend |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | Non appelé par le frontend |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | Non appelé par le frontend |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | Non appelé par le frontend |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | Non appelé par le frontend |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | Non appelé par le frontend |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | Non appelé par le frontend |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | Système/pas d'appel direct frontend (webhook, vérification de santé, assistant d'installation, etc.) |

### Module : upload

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | Non appelé par le frontend |

### Module : warehouse

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | Non appelé par le frontend |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | Non appelé par le frontend |

### Module : wms

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | Non appelé par le frontend |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | Non appelé par le frontend |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | Non appelé par le frontend |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | Non appelé par le frontend |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | Non appelé par le frontend |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | Non appelé par le frontend |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | Non appelé par le frontend |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | Non appelé par le frontend |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | Non appelé par le frontend |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | Non appelé par le frontend |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | Non appelé par le frontend |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | Non appelé par le frontend |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | Non appelé par le frontend |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | Non appelé par le frontend |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | Non appelé par le frontend |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | Non appelé par le frontend |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | Non appelé par le frontend |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | Non appelé par le frontend |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | Non appelé par le frontend |

### Module : workflow

| Méthode | Chemin | Emplacement de route | Description |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | Non appelé par le frontend |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | Non appelé par le frontend |

## III. Chemins non résolus (revue manuelle)

（Aucun）

## IV. Instructions d'utilisation du script

Script : `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Sortie texte (par défaut)
php scripts/check-endpoints.php

# Sortie JSON (pour consommation par d'autres outils)
php scripts/check-endpoints.php --json

# Filtrage sur un seul module (ex. finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# Régénération du présent rapport d'audit
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Principe de fonctionnement :

1. **Backend** : analyse `config/route.php`, rétablit le préfixe `Route::group` en chemin complet ; `Route::resource` est développé selon les méthodes réellement existantes du contrôleur (index/store/show/update/destroy etc.) ; `Route::any` est considéré comme acceptant toute méthode.
2. **Frontend** : scanne tous les fichiers `.dart` sous `apps/flutter/lib` et tous les fichiers `.ets` sous `apps/harmonyos/entry/src/main/ets`, extrait les littéraux de chemin des appels `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()` etc. ; prend en charge l'interpolation `${...}` / `$var` et les chaînes de gabarit (avec suppression du préfixe `${BASE_URL}`).
3. **Correspondance** : les segments littéraux du frontend ne correspondent qu'aux segments littéraux du backend, les segments dynamiques du frontend ne correspondent qu'aux segments `{param}` du backend (garantissant que `/admin/notification/my/read` n'est pas associé à tort à `/admin/notification/{id}/read`) ; les méthodes correspondent exactement par méthode HTTP, `any` correspond à tout.
4. **Listes** : ① points d'accès morts (appelés par le frontend mais inexistants côté backend, priorité maximale) → ② lacunes de couverture (existantes côté backend mais jamais appelées par le frontend, groupées par module ; les routes système telles que webhook/vérification de santé sont annotées) → ③ chemins non résolus (chemins variables, concaténation de chaînes, interpolation non fermée etc., nécessitant une revue manuelle).

