# এন্ডপয়েন্ট অডিট রিপোর্ট — ফ্রন্টএন্ড রিকোয়েস্ট × ব্যাকএন্ড রাউট তুলনা

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> জেনারেট সময়: 2026-08-16T01:02:44+08:00　｜　জেনারেট স্ক্রিপ্ট: `scripts/check-endpoints.php` (বারবার চালানো যাবে)
> স্মোক কেস `/admin/notification/my/read`：সোর্স স্ক্যান  পাওয়া যায়নি (সম্ভবত ওয়ার্কস্পেসে মেরামত হয়েছে) ｜ ম্যাচিং লজিক সেলফ-চেক  পাস ✅

## পরিসংখ্যান

- ব্যাকএন্ডে রেজিস্টার্ড রাউট (সম্প্রসারিত): 564টি
- ফ্রন্টএন্ড রিকোয়েস্ট কল: Flutter 377 জায়গায় / HarmonyOS 40 জায়গায়
- ডেড এন্ডপয়েন্ট: 20টি ｜ কভারেজ ফাঁক: 211টি ｜ পার্স করা যায়নি: 0টি

## ১. ডেড এন্ডপয়েন্ট তালিকা (ফ্রন্টএন্ড কল করে কিন্তু ব্যাকএন্ডে নেই)

| # | মডিউল | মেথড | পাথ | উৎস | কল অবস্থান |
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

## ২. কভারেজ ফাঁক তালিকা (ব্যাকএন্ডে আছে কিন্তু Flutter/HarmonyOS কোনোটি কল করেনি, মডিউল অনুযায়ী গ্রুপ করা)

### মডিউল: .well-known

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: approval

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/approval/{id}/reject` | config/route.php:246（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: auth

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: bi

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/bi/widget` | config/route.php:374（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/bi/widget` | config/route.php:374（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/bi/widget/{id}` | config/route.php:374（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374（resource(update)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: brand

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: category

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: crm

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/analytics/metric` | config/route.php:227（route(get)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/analytics/metric` | config/route.php:228（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225（route(get)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/contact/{id}` | config/route.php:202（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/contract/{id}` | config/route.php:211（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/follow` | config/route.php:200（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/follow` | config/route.php:200（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/follow/{id}` | config/route.php:200（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/funnel` | config/route.php:201（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/funnel` | config/route.php:201（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/pool/rules` | config/route.php:210（route(get)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: customer

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: customer-level

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: dashboard

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235（route(any)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/dashboard/inventory` | config/route.php:234（route(any)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/dashboard/sales` | config/route.php:233（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: debug

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: dms

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390（route(get)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/dms/document/{id}` | config/route.php:389（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391（route(get)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: docs

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: eam

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/eam/repair/{id}` | config/route.php:382（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: finance

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/asset/{id}` | config/route.php:182（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183（route(post)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184（route(any)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/bank-account` | config/route.php:167（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/bank-account` | config/route.php:167（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/budget/{id}` | config/route.php:191（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192（route(any)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/currency/{id}` | config/route.php:189（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/exchange-rate` | config/route.php:190（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/exchange-rate` | config/route.php:190（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/expense/{id}` | config/route.php:160（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/payment/{id}` | config/route.php:158（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/profit-center` | config/route.php:194（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/profit-center` | config/route.php:194（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/report/account-balance` | config/route.php:166（route(get)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174（route(any)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175（route(post)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176（route(any)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/report/close-period` | config/route.php:162（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/report/consolidate` | config/route.php:163（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/finance/report/ratios` | config/route.php:164（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165（route(get)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173（route(any)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/finance/tax-record` | config/route.php:188（route(any)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: health

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/health` | config/route.php:46（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: hr

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/department/{id}` | config/route.php:268（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/employee/{id}` | config/route.php:269（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/leave/{id}` | config/route.php:276（route(get)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/position/{id}` | config/route.php:270（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/salary-item` | config/route.php:284（route(get)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/salary-item` | config/route.php:285（route(post)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288（route(delete)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286（route(get)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287（route(put)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/salary/calculate` | config/route.php:281（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/hr/salary/{id}` | config/route.php:280（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: import

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: install

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40（route(any)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |
| GET | `/install/test-db` | config/route.php:41（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: inventory

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/inventory/check/{id}` | config/route.php:149（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: location

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: metrics

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49（route(get)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: mfg

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/mfg/production/{id}` | config/route.php:294（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: notification

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256（route(any)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/notification/{id}/read` | config/route.php:254（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: oms

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/oms/order/{id}` | config/route.php:313（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/oms/rma/{id}` | config/route.php:318（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: permission

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/permission/{id}` | config/route.php:86（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/permission/{id}` | config/route.php:86（resource(update)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: product

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/api/product` | config/route.php:412（route(any)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/api/product/{hashid}` | config/route.php:413（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: project

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/project/{id}` | config/route.php:263（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: purchase

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/purchase/order/{id}` | config/route.php:129（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/purchase/return/{id}` | config/route.php:131（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/purchase/settlement` | config/route.php:132（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: quality

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/quality/inspection/record` | config/route.php:367（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/quality/standard/{id}` | config/route.php:362（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: report

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/report/{id}` | config/route.php:308（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/report/{id}/execute` | config/route.php:306（route(post)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/report/{id}/result` | config/route.php:307（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: sales

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/sales/order/{id}` | config/route.php:138（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/sales/return/{id}` | config/route.php:140（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| ANY | `/admin/sales/settlement` | config/route.php:141（route(any)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: supplier

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: tms

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350（route(get)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/service` | config/route.php:347（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/service` | config/route.php:347（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/service/{id}` | config/route.php:347（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/tms/service/{id}` | config/route.php:347（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/api/tms/tracking/callback` | config/route.php:419（route(post)） | সিস্টেম/ফ্রন্টএন্ড নয় সরাসরি কল (webhook, হেলথ চেক, ইনস্টল উইজার্ড ইত্যাদি) |

### মডিউল: upload

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110（route(post)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: warehouse

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119（route(get)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: wms

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/location` | config/route.php:328（resource(index)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/location` | config/route.php:328（resource(store)） | ফ্রন্টএন্ড কল করেনি |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328（resource(destroy)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/location/{id}` | config/route.php:328（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| PUT | `/admin/wms/location/{id}` | config/route.php:328（resource(update)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/pack/{id}` | config/route.php:339（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/pick/{id}` | config/route.php:336（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338（route(post)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/wave/{id}` | config/route.php:334（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335（route(post)） | ফ্রন্টএন্ড কল করেনি |
| GET | `/admin/wms/zone/{id}` | config/route.php:327（resource(show)） | ফ্রন্টএন্ড কল করেনি |

### মডিউল: workflow

| মেথড | পাথ | রাউট অবস্থান | ব্যাখ্যা |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243（resource(show)） | ফ্রন্টএন্ড কল করেনি |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244（route(post)） | ফ্রন্টএন্ড কল করেনি |

## ৩. পার্স করা যায়নি এমন পাথ (ম্যানুয়াল রিভিউ)

(কোনোটি নেই)

## ৪. স্ক্রিপ্ট ব্যবহারের ব্যাখ্যা

স্ক্রিপ্ট: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# টেক্সট আউটপুট (ডিফল্ট)
php scripts/check-endpoints.php

# JSON আউটপুট (পরবর্তী টুলস ব্যবহারের সুবিধার্থে)
php scripts/check-endpoints.php --json

# শুধু একটি মডিউল ফিল্টার (যেমন finance, wms, notification)
php scripts/check-endpoints.php --module=finance

# এই অডিট রিপোর্ট পুনরায় জেনারেট
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

কাজের নীতি:

1. **ব্যাকএন্ড**: `config/route.php` পার্স করে, `Route::group` প্রিফিক্স সম্পূর্ণ পাথে রূপান্তর করে; `Route::resource` কন্ট্রোলারে প্রকৃতপক্ষে থাকা মেথড অনুযায়ী সম্প্রসারিত হয় (index/store/show/update/destroy ইত্যাদি); `Route::any` যেকোনো মেথড হিসেবে গণ্য হয়।
2. **ফ্রন্টএন্ড**: `apps/flutter/lib` ডিরেক্টরির সব `.dart` এবং `apps/harmonyos/entry/src/main/ets` ডিরেক্টরির সব `.ets` ফাইল স্ক্যান করে, `ApiService.instance.*`、`api.*`、`_dio.*`、`apiService.*`、`httpRequest.request()` ইত্যাদি কলের পাথ লিটারেল এক্সট্রাক্ট করে; `${...}` / `$var` ইন্টারপোলেশন এবং টেমপ্লেট স্ট্রিং সাপোর্ট করে (`${BASE_URL}` প্রিফিক্স স্ট্রিপিং সহ)।
3. **ম্যাচিং**: ফ্রন্টএন্ড লিটারেল সেগমেন্ট শুধু ব্যাকএন্ড লিটারেল সেগমেন্টের সাথে ম্যাচ করে, ফ্রন্টএন্ড ডাইনামিক সেগমেন্ট শুধু ব্যাকএন্ড `{param}` সেগমেন্টের সাথে ম্যাচ করে (নিশ্চিত করে `/admin/notification/my/read` ভুলভাবে `/admin/notification/{id}/read`-এ ম্যাচ না হয়); মেথড HTTP মেথড অনুযায়ী নির্ভুল ম্যাচ, `any` সবকিছু ম্যাচ করে।
4. **তালিকা**: ① ডেড এন্ডপয়েন্ট (ফ্রন্টএন্ড কল করে কিন্তু ব্যাকএন্ডে নেই, সর্বোচ্চ প্রায়োরিটি) → ② কভারেজ ফাঁক (ব্যাকএন্ডে আছে কিন্তু ফ্রন্টএন্ড কোনোটি কল করেনি, মডিউল অনুযায়ী গ্রুপ; webhook/হেলথ চেক ইত্যাদি সিস্টেম রাউট চিহ্নিত) → ③ পার্স করা যায়নি এমন পাথ (ভেরিয়েবল পাথ, স্ট্রিং কনক্যাটেনেশন, অসমাপ্ত ইন্টারপোলেশন ইত্যাদি, ম্যানুয়াল রিভিউ প্রয়োজন)।
