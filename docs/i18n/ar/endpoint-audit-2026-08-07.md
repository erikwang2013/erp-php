# تقرير تدقيق نقاط النهاية — مقارنة طلبات الواجهة الأمامية × مسارات الخلفية

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> وقت التوليد: 2026-08-16T01:02:44+08:00　｜　سكربت التوليد: `scripts/check-endpoints.php` (قابل لإعادة التشغيل)
> حالة استخدام الدخان `/admin/notification/my/read`: فحص المصدر لم يعثر عليه (ربما أُصلح في مساحة العمل) ｜ فحص منطق المطابقة ناجح ✅

## الإحصائيات

- مسارات الخلفية المسجلة (بعد التوسيع): 564 مسارًا
- استدعاءات الواجهة الأمامية: Flutter 377 موضعًا / HarmonyOS 40 موضعًا
- نقاط نهاية ميتة: 20 ｜ فجوات التغطية: 211 ｜ غير قابلة للتحليل: 0

## أولًا: قائمة نقاط النهاية الميتة (تستدعيها الواجهة الأمامية لكنها غير موجودة في الخلفية)

| # | الوحدة | الطريقة | المسار | المصدر | موضع الاستدعاء |
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

## ثانيًا: قائمة فجوات التغطية (موجودة في الخلفية لكن Flutter/HarmonyOS لا تستدعيها، مجمعة حسب الوحدة)

### الوحدة: .well-known

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/.well-known/security.txt` | config/route.php:57 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: approval

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/approval/{id}/approve` | config/route.php:245 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/approval/{id}/reject` | config/route.php:246 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/approval/{id}/withdraw` | config/route.php:247 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: auth

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/api/auth/register` | config/route.php:408 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: bi

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/bi/dashboard/{id}` | config/route.php:373 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/bi/dataset/{id}` | config/route.php:375 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/bi/widget` | config/route.php:374 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/bi/widget` | config/route.php:374 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/bi/widget/{id}` | config/route.php:374 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/bi/widget/{id}` | config/route.php:374 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/bi/widget/{id}` | config/route.php:374 (resource(update)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: brand

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/brand/{id}` | config/route.php:117 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: category

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/category/{id}` | config/route.php:116 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: crm

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/crm/analytics/generate` | config/route.php:226 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/analytics/metric` | config/route.php:227 (route(get)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/analytics/metric` | config/route.php:228 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/analytics/report/{id}` | config/route.php:225 (route(get)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/campaign/{id}` | config/route.php:219 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/contact/{id}` | config/route.php:202 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/contract/{id}` | config/route.php:211 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/contract/{id}/transition` | config/route.php:212 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/follow` | config/route.php:200 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/follow` | config/route.php:200 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/crm/follow/{id}` | config/route.php:200 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/follow/{id}` | config/route.php:200 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/crm/follow/{id}` | config/route.php:200 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/funnel` | config/route.php:201 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/funnel` | config/route.php:201 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/crm/funnel/{id}` | config/route.php:201 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/opportunity/{id}` | config/route.php:199 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/pool/claim/{id}` | config/route.php:208 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/pool/release/{id}` | config/route.php:209 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/pool/rules` | config/route.php:210 (route(get)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/quotation/{id}` | config/route.php:213 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/quotation/{id}/to-contract` | config/route.php:214 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/crm/ticket/{id}` | config/route.php:220 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/ticket/{id}/assign` | config/route.php:221 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/ticket/{id}/reply` | config/route.php:223 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/crm/ticket/{id}/resolve` | config/route.php:222 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: customer

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/customer/{id}` | config/route.php:122 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: customer-level

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| ANY | `/admin/customer-level` | config/route.php:123 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: dashboard

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| ANY | `/admin/dashboard/finance` | config/route.php:235 (route(any)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/dashboard/inventory` | config/route.php:234 (route(any)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/dashboard/sales` | config/route.php:233 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: debug

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/debug/queue-smoke` | config/route.php:54 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: dms

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/dms/categories` | config/route.php:390 (route(get)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/dms/document/{id}` | config/route.php:389 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/dms/document/{id}/versions` | config/route.php:391 (route(get)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: docs

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/api/docs` | config/route.php:68 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: eam

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/eam/equipment/{id}` | config/route.php:380 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/eam/maintenance/{id}` | config/route.php:381 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/eam/repair/{id}` | config/route.php:382 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/eam/spare-part/{id}` | config/route.php:384 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: finance

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/finance/ar-ap/{id}` | config/route.php:155 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/asset/{id}` | config/route.php:182 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/asset/{id}/depreciate` | config/route.php:183 (route(post)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/asset/{id}/depreciation` | config/route.php:184 (route(any)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/bank-account` | config/route.php:167 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/bank-account` | config/route.php:167 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/finance/bank-account/{id}` | config/route.php:167 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/budget/{id}` | config/route.php:191 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/budget/{id}/comparison` | config/route.php:192 (route(any)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/cost-center/{id}` | config/route.php:193 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/currency/{id}` | config/route.php:189 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/exchange-rate` | config/route.php:190 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/exchange-rate` | config/route.php:190 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/finance/exchange-rate/{id}` | config/route.php:190 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/expense/{id}` | config/route.php:160 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/payment/{id}` | config/route.php:158 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/profit-center` | config/route.php:194 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/profit-center` | config/route.php:194 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/finance/profit-center/{id}` | config/route.php:194 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/receipt/{id}` | config/route.php:157 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/report/account-balance` | config/route.php:166 (route(get)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/report/balance-sheet` | config/route.php:174 (route(any)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/report/balance-sheet/save` | config/route.php:175 (route(post)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/report/cash-flow` | config/route.php:176 (route(any)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/report/cash-flow/save` | config/route.php:177 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/report/close-period` | config/route.php:162 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/report/consolidate` | config/route.php:163 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/finance/report/ratios` | config/route.php:164 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/report/trial-balance` | config/route.php:165 (route(get)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/subsidiary-ledger` | config/route.php:173 (route(any)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/finance/tax-record` | config/route.php:188 (route(any)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/finance/voucher/{id}` | config/route.php:156 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: health

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/health` | config/route.php:46 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: hr

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/hr/attendance/clock-in` | config/route.php:272 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/attendance/clock-out` | config/route.php:273 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/department/{id}` | config/route.php:268 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/employee/{id}` | config/route.php:269 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/leave/{id}` | config/route.php:276 (route(get)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/leave/{id}/approve` | config/route.php:279 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/position/{id}` | config/route.php:270 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/salary-item` | config/route.php:284 (route(get)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/salary-item` | config/route.php:285 (route(post)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/hr/salary-item/{id}` | config/route.php:288 (route(delete)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/salary-item/{id}` | config/route.php:286 (route(get)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/hr/salary-item/{id}` | config/route.php:287 (route(put)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/salary/calculate` | config/route.php:281 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/salary/payroll-file` | config/route.php:282 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/hr/salary/{id}` | config/route.php:280 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/hr/salary/{id}/pay` | config/route.php:283 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: import

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/import/users` | config/route.php:107 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: install

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| ANY | `/install` | config/route.php:40 (route(any)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |
| GET | `/install/test-db` | config/route.php:41 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: inventory

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/inventory/alert/{id}` | config/route.php:150 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/inventory/check/{id}` | config/route.php:149 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/inventory/transfer/{id}` | config/route.php:148 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: location

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/location/{id}` | config/route.php:120 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: metrics

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/metrics` | config/route.php:49 (route(get)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: mfg

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/mfg/bom/{id}` | config/route.php:293 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/mfg/mrp/{id}` | config/route.php:299 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/mfg/mrp/{id}/generate` | config/route.php:300 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/mfg/production/{id}` | config/route.php:294 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/mfg/production/{id}/complete` | config/route.php:296 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/mfg/production/{id}/start` | config/route.php:295 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/mfg/routing/{id}` | config/route.php:297 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/mfg/workstation/{id}` | config/route.php:298 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: notification

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| ANY | `/admin/notification/unread-count` | config/route.php:256 (route(any)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/notification/{id}/read` | config/route.php:254 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: oms

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/oms/channel/{id}` | config/route.php:322 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/oms/fulfillment/{id}` | config/route.php:317 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/oms/order/{id}` | config/route.php:313 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/order/{id}/allocate` | config/route.php:314 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/order/{id}/cancel` | config/route.php:316 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/order/{id}/fulfill` | config/route.php:315 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/oms/rma/{id}` | config/route.php:318 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/rma/{id}/approve` | config/route.php:319 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/rma/{id}/receive` | config/route.php:320 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/oms/rma/{id}/refund` | config/route.php:321 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: permission

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/permission` | config/route.php:86 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/permission/{id}` | config/route.php:86 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/permission/{id}` | config/route.php:86 (resource(update)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: product

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/product/{id}` | config/route.php:115 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/api/product` | config/route.php:412 (route(any)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/api/product/{hashid}` | config/route.php:413 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: project

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/project/task/{id}` | config/route.php:261 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/project/timesheet/{id}` | config/route.php:262 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/project/{id}` | config/route.php:263 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: purchase

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/purchase/apply/{id}` | config/route.php:128 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/purchase/order/{id}` | config/route.php:129 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/purchase/receive/{id}` | config/route.php:130 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/purchase/return/{id}` | config/route.php:131 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/purchase/settlement` | config/route.php:132 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: quality

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/quality/inspection/pass-rate` | config/route.php:368 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/quality/inspection/record` | config/route.php:367 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/quality/ipqc/{id}` | config/route.php:364 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/quality/iqc/{id}` | config/route.php:363 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/quality/nonconformity/{id}` | config/route.php:366 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/quality/oqc/{id}` | config/route.php:365 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/quality/standard/{id}` | config/route.php:362 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: report

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/report/schedule/{id}` | config/route.php:305 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/report/{id}` | config/route.php:308 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/report/{id}/execute` | config/route.php:306 (route(post)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/report/{id}/result` | config/route.php:307 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: sales

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/sales/delivery/{id}` | config/route.php:139 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/sales/order/{id}` | config/route.php:138 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/sales/quotation/{id}` | config/route.php:137 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/sales/return/{id}` | config/route.php:140 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| ANY | `/admin/sales/settlement` | config/route.php:141 (route(any)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: supplier

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/supplier/{id}` | config/route.php:121 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: tms

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/tms/carrier/{id}` | config/route.php:346 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/freight-invoice/{id}` | config/route.php:355 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/freight-invoice/{id}/confirm` | config/route.php:356 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/freight-invoice/{id}/pay` | config/route.php:357 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/freight-rate/calculate` | config/route.php:349 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/freight-rate/rate-shop` | config/route.php:350 (route(get)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/freight-rate/{id}` | config/route.php:348 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/service` | config/route.php:347 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/service` | config/route.php:347 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/tms/service/{id}` | config/route.php:347 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/service/{id}` | config/route.php:347 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/tms/service/{id}` | config/route.php:347 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/shipment/{id}` | config/route.php:351 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/shipment/{id}/get-label` | config/route.php:353 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/tms/shipment/{id}/ship` | config/route.php:352 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/tms/tracking/{id}` | config/route.php:354 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/api/tms/tracking/callback` | config/route.php:419 (route(post)) | نظامي/لا يُستدعى مباشرة من الواجهة الأمامية (webhook، فحص الصحة، معالج التثبيت وغيرها) |

### الوحدة: upload

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| POST | `/admin/upload` | config/route.php:110 (route(post)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: warehouse

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/warehouse/{id}` | config/route.php:118 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/warehouse/{id}/locations` | config/route.php:119 (route(get)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: wms

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/wms/asn/{id}` | config/route.php:329 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/location` | config/route.php:328 (resource(index)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/location` | config/route.php:328 (resource(store)) | غير مستدعى من الواجهة الأمامية |
| DELETE | `/admin/wms/location/{id}` | config/route.php:328 (resource(destroy)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/location/{id}` | config/route.php:328 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| PUT | `/admin/wms/location/{id}` | config/route.php:328 (resource(update)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/pack/{id}` | config/route.php:339 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/pack/{id}/complete` | config/route.php:341 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/pack/{id}/start` | config/route.php:340 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/pick/{id}` | config/route.php:336 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/pick/{id}/confirm` | config/route.php:338 (route(post)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/pick/{id}/start` | config/route.php:337 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/putaway/{id}` | config/route.php:332 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/putaway/{id}/complete` | config/route.php:333 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/receiving/{id}` | config/route.php:330 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/receiving/{id}/complete` | config/route.php:331 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/wave/{id}` | config/route.php:334 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/wms/wave/{id}/release` | config/route.php:335 (route(post)) | غير مستدعى من الواجهة الأمامية |
| GET | `/admin/wms/zone/{id}` | config/route.php:327 (resource(show)) | غير مستدعى من الواجهة الأمامية |

### الوحدة: workflow

| الطريقة | المسار | موضع المسار | الوصف |
|------|------|----------|------|
| GET | `/admin/workflow/{id}` | config/route.php:243 (resource(show)) | غير مستدعى من الواجهة الأمامية |
| POST | `/admin/workflow/{id}/submit` | config/route.php:244 (route(post)) | غير مستدعى من الواجهة الأمامية |

## ثالثًا: المسارات غير القابلة للتحليل (مراجعة يدوية)

(لا يوجد)

## رابعًا: شرح استخدام السكربت

السكربت: `scripts/check-endpoints.php` (PHP CLI ≥ 8.0)

```bash
# إخراج نصي (الافتراضي)
php scripts/check-endpoints.php

# إخراج JSON (للاستهلاك من قبل أدوات لاحقة)
php scripts/check-endpoints.php --json

# فلترة وحدة واحدة فقط (مثل finance، wms، notification)
php scripts/check-endpoints.php --module=finance

# إعادة توليد تقرير التدقيق هذا
php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md
```

مبدأ العمل:

1. **الخلفية**: تحليل `config/route.php`، واستعادة بادئة `Route::group` إلى المسار الكامل؛ `Route::resource` يُوسَّع وفق الطرق الموجودة فعليًا في وحدة التحكم (index/store/show/update/destroy وغيرها)؛ `Route::any` يُعتبر أي طريقة.
2. **الواجهة الأمامية**: فحص جميع ملفات `.dart` في `apps/flutter/lib` وجميع ملفات `.ets` في `apps/harmonyos/entry/src/main/ets`، واستخراج النصوص الحرفية لمسارات استدعاءات `ApiService.instance.*` و`api.*` و`_dio.*` و`apiService.*` و`httpRequest.request()` وغيرها؛ يدعم استيفاء `${...}` / `$var` وسلاسل القوالب (مع إزالة بادئة `${BASE_URL}`).
3. **المطابقة**: المقاطع الحرفية للواجهة الأمامية تطابق فقط المقاطع الحرفية للخلفية، والمقاطع الديناميكية للواجهة الأمامية تطابق فقط مقاطع `{param}` في الخلفية (ضمان عدم مطابقة `/admin/notification/my/read` خطأً مع `/admin/notification/{id}/read`)؛ الطرق تُطابق بدقة حسب طريقة HTTP، و`any` يطابق كل شيء.
4. **القوائم**: ① نقاط نهاية ميتة (تستدعيها الواجهة الأمامية لكنها غير موجودة في الخلفية، الأعلى أولوية) ← ② فجوات التغطية (موجودة في الخلفية لكن لا تستدعيها الواجهة الأمامية، مجمعة حسب الوحدة؛ مسارات النظام مثل webhook وفحص الصحة معلمة) ← ③ مسارات غير قابلة للتحليل (مسارات متغيرة، سلاسل مدمجة، استيفاء غير مغلق وغيرها، تتطلب مراجعة يدوية).
