# एंडपॉइंट ऑडिट रिपोर्ट — फ्रंटएंड अनुरोध × बैकएंड रूट तुलना

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> जनरेशन समय: 2026-08-16T01:02:44+08:00　｜　जनरेशन स्क्रिप्ट: `scripts/check-endpoints.php` (दोबारा चलाने योग्य)
> स्मोक केस `/admin/notification/my/read`: सोर्स स्कैन में नहीं मिला (संभवतः वर्कस्पेस में पहले ही ठीक कर दिया गया) ｜ मैचिंग लॉजिक स्व-परीक्षण पास ✅

## आँकड़े

- बैकएंड पर पंजीकृत रूट (विस्तार के बाद): 564
- फ्रंटएंड अनुरोध कॉल: Flutter 377 / HarmonyOS 40
- डेड एंडपॉइंट: 20 ｜ कवरेज गैप: 211 ｜ हल नहीं हो सके: 0

## 一、डेड एंडपॉइंट सूची (फ्रंटएंड कॉल करता है लेकिन बैकएंड मौजूद नहीं)

| # | मॉड्यूल | विधि | पथ | स्रोत | कॉल स्थान |
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

## 二、कवरेज गैप सूची (बैकएंड मौजूद है लेकिन Flutter/HarmonyOS दोनों ने कॉल नहीं किया, मॉड्यूल के अनुसार समूहीकृत)

### मॉड्यूल: .well-known

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: approval

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: auth

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: bi

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: brand

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: category

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: crm

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: customer

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: customer-level

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: dashboard

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: debug

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: dms

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: docs

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: eam

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: finance

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: health

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: hr

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: import

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: install

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: inventory

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: location

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: metrics

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: mfg

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: notification

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: oms

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: permission

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: product

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/api/product` | config/route.php:412（route(any)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: project

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: purchase

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: quality

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: report

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: sales

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: supplier

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: tms

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | सिस्टम/फ्रंटएंड सीधे कॉल नहीं (webhook, स्वास्थ्य जांच, इंस्टॉल विज़ार्ड आदि) |

### मॉड्यूल: upload

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: warehouse

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: wms

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | फ्रंटएंड ने कॉल नहीं किया |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | फ्रंटएंड ने कॉल नहीं किया |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |

### मॉड्यूल: workflow

| विधि | पथ | रूट स्थान | विवरण |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | फ्रंटएंड ने कॉल नहीं किया |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | फ्रंटएंड ने कॉल नहीं किया |

## 三、हल नहीं हो सके पथ (मानव पुनः समीक्षा)

（कोई नहीं）

## 四、स्क्रिप्ट उपयोग निर्देश

स्क्रिप्ट: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# टेक्स्ट आउटपुट (डिफ़ॉल्ट)
php scripts/check-endpoints.php

# JSON आउटपुट (आगे के टूल उपभोग के लिए)
php scripts/check-endpoints.php --json

# केवल एक मॉड्यूल फ़िल्टर करें (जैसे finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# यह ऑडिट रिपोर्ट दोबारा जनरेट करें
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

कार्य सिद्धांत:

1. **बैकएंड**: `config/route.php` पार्स करता है, `Route::group` उपसर्गों को पूर्ण पथ में बदलता है; `Route::resource` को कंट्रोलर में वास्तव में मौजूद विधियों के अनुसार विस्तारित करता है (index/store/show/update/destroy आदि); `Route::any` को किसी भी विधि के रूप में मानता है।
2. **फ्रंटएंड**: `apps/flutter/lib` निर्देशिका के सभी `.dart` और `apps/harmonyos/entry/src/main/ets` निर्देशिका के सभी `.ets` फ़ाइलों को स्कैन करता है, `ApiService.instance.*`、`api.*`、`_dio.*`、`apiService.*`、`httpRequest.request()` आदि कॉल के पथ लिटरल निकालता है; `${...}` / `$var` इंटरपोलेशन और टेम्पलेट स्ट्रिंग्स समर्थित (${BASE_URL} उपसर्ग हटाने सहित)।
3. **मिलान**: फ्रंटएंड लिटरल सेगमेंट केवल बैकएंड लिटरल सेगमेंट से मेल खाते हैं, फ्रंटएंड डायनामिक सेगमेंट केवल बैकएंड `{param}` सेगमेंट से मेल खाते हैं (गारंटी कि `/admin/notification/my/read` गलती से `/admin/notification/{id}/read` से मेल नहीं खाएगा); विधि HTTP विधि से सटीक मेल, `any` सब कुछ मेल खाता है।
4. **सूचियाँ**: ① डेड एंडपॉइंट (फ्रंटएंड कॉल करता है लेकिन बैकएंड मौजूद नहीं, सबसे उच्च प्राथमिकता) → ② कवरेज गैप (बैकएंड मौजूद है लेकिन फ्रंटएंड ने कॉल नहीं किया, मॉड्यूल के अनुसार समूहीकृत; webhook/स्वास्थ्य जांच आदि सिस्टम रूट चिह्नित) → ③ हल नहीं हो सके पथ (वेरिएबल पथ, स्ट्रिंग कॉन्कैटनेशन, बंद न हुआ इंटरपोलेशन आदि, मानव पुनः समीक्षा आवश्यक)।
