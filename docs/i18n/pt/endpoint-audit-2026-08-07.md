# Relatório de auditoria de endpoints — comparação entre requisições do frontend × rotas do backend

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Gerado em: 2026-08-16T01:02:44+08:00　｜　Script de geração: `scripts/check-endpoints.php` (pode ser reexecutado)
> Caso de fumaça `/admin/notification/my/read`: varredura do código-fonte não encontrou (pode ter sido corrigido no workspace) ｜ Autoverificação da lógica de correspondência aprovada ✅

## Estatísticas

- Rotas registradas no backend (após expansão): 564
- Chamadas do frontend: Flutter 377 / HarmonyOS 40
- Endpoints mortos: 20　｜　Lacunas de cobertura: 211　｜　Não resolvíveis: 0

## 1. Lista de endpoints mortos (chamados pelo frontend, mas inexistentes no backend)

| # | Módulo | Método | Caminho | Origem | Local de chamada |
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

## 2. Lista de lacunas de cobertura (existem no backend, mas não são chamadas por Flutter/HarmonyOS, agrupadas por módulo)

### Módulo: .well-known

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: approval

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | Não chamado pelo frontend |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | Não chamado pelo frontend |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | Não chamado pelo frontend |

### Módulo: auth

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | Não chamado pelo frontend |

### Módulo: bi

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | Não chamado pelo frontend |

### Módulo: brand

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | Não chamado pelo frontend |

### Módulo: category

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | Não chamado pelo frontend |

### Módulo: crm

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | Não chamado pelo frontend |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | Não chamado pelo frontend |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | Não chamado pelo frontend |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | Não chamado pelo frontend |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | Não chamado pelo frontend |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | Não chamado pelo frontend |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | Não chamado pelo frontend |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | Não chamado pelo frontend |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | Não chamado pelo frontend |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | Não chamado pelo frontend |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | Não chamado pelo frontend |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | Não chamado pelo frontend |

### Módulo: customer

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | Não chamado pelo frontend |

### Módulo: customer-level

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | Não chamado pelo frontend |

### Módulo: dashboard

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | Não chamado pelo frontend |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | Não chamado pelo frontend |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | Não chamado pelo frontend |

### Módulo: debug

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: dms

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | Não chamado pelo frontend |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | Não chamado pelo frontend |

### Módulo: docs

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: eam

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | Não chamado pelo frontend |

### Módulo: finance

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | Não chamado pelo frontend |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | Não chamado pelo frontend |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | Não chamado pelo frontend |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | Não chamado pelo frontend |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | Não chamado pelo frontend |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | Não chamado pelo frontend |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | Não chamado pelo frontend |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | Não chamado pelo frontend |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | Não chamado pelo frontend |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | Não chamado pelo frontend |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | Não chamado pelo frontend |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | Não chamado pelo frontend |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | Não chamado pelo frontend |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | Não chamado pelo frontend |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | Não chamado pelo frontend |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | Não chamado pelo frontend |

### Módulo: health

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: hr

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | Não chamado pelo frontend |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | Não chamado pelo frontend |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | Não chamado pelo frontend |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | Não chamado pelo frontend |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | Não chamado pelo frontend |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | Não chamado pelo frontend |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | Não chamado pelo frontend |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | Não chamado pelo frontend |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | Não chamado pelo frontend |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | Não chamado pelo frontend |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | Não chamado pelo frontend |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | Não chamado pelo frontend |

### Módulo: import

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | Não chamado pelo frontend |

### Módulo: install

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: inventory

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | Não chamado pelo frontend |

### Módulo: location

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | Não chamado pelo frontend |

### Módulo: metrics

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: mfg

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | Não chamado pelo frontend |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | Não chamado pelo frontend |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | Não chamado pelo frontend |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | Não chamado pelo frontend |

### Módulo: notification

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | Não chamado pelo frontend |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | Não chamado pelo frontend |

### Módulo: oms

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | Não chamado pelo frontend |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | Não chamado pelo frontend |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | Não chamado pelo frontend |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | Não chamado pelo frontend |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | Não chamado pelo frontend |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | Não chamado pelo frontend |

### Módulo: permission

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | Não chamado pelo frontend |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | Não chamado pelo frontend |

### Módulo: product

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | Não chamado pelo frontend |
| ANY | `/api/product` | config/route.php:412（route(any)） | Não chamado pelo frontend |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | Não chamado pelo frontend |

### Módulo: project

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | Não chamado pelo frontend |

### Módulo: purchase

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | Não chamado pelo frontend |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | Não chamado pelo frontend |

### Módulo: quality

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | Não chamado pelo frontend |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | Não chamado pelo frontend |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | Não chamado pelo frontend |

### Módulo: report

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | Não chamado pelo frontend |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | Não chamado pelo frontend |

### Módulo: sales

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | Não chamado pelo frontend |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | Não chamado pelo frontend |

### Módulo: supplier

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | Não chamado pelo frontend |

### Módulo: tms

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | Não chamado pelo frontend |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | Não chamado pelo frontend |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | Não chamado pelo frontend |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | Não chamado pelo frontend |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | Não chamado pelo frontend |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | Não chamado pelo frontend |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | Não chamado pelo frontend |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | Sistema/chamada direta não-frontend (webhook, health check, assistente de instalação etc.) |

### Módulo: upload

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | Não chamado pelo frontend |

### Módulo: warehouse

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | Não chamado pelo frontend |

### Módulo: wms

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | Não chamado pelo frontend |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | Não chamado pelo frontend |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | Não chamado pelo frontend |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | Não chamado pelo frontend |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | Não chamado pelo frontend |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | Não chamado pelo frontend |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | Não chamado pelo frontend |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | Não chamado pelo frontend |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | Não chamado pelo frontend |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | Não chamado pelo frontend |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | Não chamado pelo frontend |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | Não chamado pelo frontend |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | Não chamado pelo frontend |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | Não chamado pelo frontend |

### Módulo: workflow

| Método | Caminho | Local da rota | Observação |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | Não chamado pelo frontend |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | Não chamado pelo frontend |

## 3. Caminhos não resolvíveis (revisão manual)

(nenhum)

## 4. Instruções de uso do script

Script: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# Saída em texto (padrão)
php scripts/check-endpoints.php

# Saída JSON (para consumo por ferramentas posteriores)
php scripts/check-endpoints.php --json

# Filtrar apenas um módulo (ex.: finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# Regenerar este relatório de auditoria
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

Como funciona:

1. **Backend**: analisa `config/route.php`, restaura o prefixo de `Route::group` no caminho completo; `Route::resource` é expandido de acordo com os métodos realmente existentes no controller (index/store/show/update/destroy etc.); `Route::any` é tratado como método arbitrário.
2. **Frontend**: varre todos os arquivos `.dart` em `apps/flutter/lib` e todos os arquivos `.ets` em `apps/harmonyos/entry/src/main/ets`, extrai literais de caminho de chamadas como `ApiService.instance.*`, `api.*`, `_dio.*`, `apiService.*`, `httpRequest.request()`; suporta interpolação `${...}` / `$var` e strings de modelo (incluindo remoção do prefixo `${BASE_URL}`).
3. **Correspondência**: segmentos literais do frontend só correspondem a segmentos literais do backend; segmentos dinâmicos do frontend só correspondem a segmentos `{param}` do backend (garantindo que `/admin/notification/my/read` não seja erroneamente associado a `/admin/notification/{id}/read`); o método é correspondido exatamente pelo método HTTP, `any` corresponde a tudo.
4. **Listas**: ① endpoints mortos (chamados pelo frontend mas inexistentes no backend, prioridade máxima) → ② lacunas de cobertura (existem no backend mas não são chamados por nenhum frontend, agrupados por módulo; rotas de sistema como webhook/health check já estão anotadas) → ③ caminhos não resolvíveis (caminhos com variáveis, concatenação de strings, interpolação não fechada etc., exigem revisão manual).

