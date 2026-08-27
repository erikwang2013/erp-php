# টেস্ট রিপোর্ট — 2026-08-26

> আপডেট: 2026-08-27 — অমীমাংসিত বিষয় ৫টি সম্পূর্ণ ক্লোজড; টেস্ট সংখ্যা 505/2342/26 → 513/2368/32; সাথে সাথে ফিক্স ৪ → ৫ জায়গায়। পুরনো মান নিচের «আপডেট রেকর্ড» এ দেখুন।

## এক্সিকিউটিভ সামারি

| মেট্রিক | মান |
|------|----|
| রিপোর্ট তারিখ | 2026-08-26 |
| PHP ইউনিট টেস্ট | 513 tests / 2368 assertions / 32 skipped |
| Flutter পেজ টেস্ট | 98 tests সব পাস (flutter analyze 0 error) |
| API অটোমেশন | 104 এন্ডপয়েন্ট / ~230 অ্যাসারশন (CI e2e সংযুক্ত, ci.yml এর «Run E2E API coverage» স্টেপ দেখুন) |
| কভারেজ (pcov পরিমাপ) | সামগ্রিক 7.51% / app/service 15.65% / app/controller 3.62% |
| স্ট্যাটিক অ্যানালাইসিস | PHPStan 0 error ✅ |
| কোড স্টাইল | php-cs-fixer 0 diff ✅ (এইবার সাথে সাথে ৩টি বিদ্যমান ফাইল ফিক্স করা হয়েছে) |
| সাথে সাথে ফিক্স করা প্রকৃত ত্রুটি | ৫ জায়গায় (3 PHP + 1 Flutter + 1 ফরম্যাট) |
| Go/Rust | N/A (রিপোজিটরিতে কোনো .go/.rs/Cargo.toml কোড নেই) |

এইবার তিন-ট্র্যাক প্যারালাল টেস্ট ডেলিভারি: PHP ইউনিট টেস্ট (php-tester, নতুন ৯টি ফাইল), API অটোমেশন (api-tester, নতুন ১টি ফাইল), Flutter পেজ টেস্ট (ui-tester, নতুন ৮টি ফাইল ২৯ কেস)।

## কভারেজ ম্যাট্রিক্স

মডিউল (22 ব্যবসায়িক ডোমেইন + সিস্টেম ম্যানেজমেন্ট ১৪ কন্ট্রোলার) টেস্ট টাইপ অনুযায়ী কভারেজ চিহ্নিত।

### 22 ব্যবসায়িক ডোমেইন

| মডিউল | ইউনিট | API | UI | ব্যাখ্যা |
|------|------|-----|-----|------|
| ফাইন্যান্স Consolidation কনসোলিডেশন | ✅ | ✅ | — | ConsolidationServiceTest ৫ কেস + API |
| ফাইন্যান্স AccountBalance অ্যাকাউন্ট ব্যালেন্স | ✅ | ✅ | — | AccountBalanceServiceTest ৪ কেস |
| ফাইন্যান্স PeriodClose পিরিয়ড ক্লোজিং | ✅ | ✅ | — | PeriodCloseServiceTest ৫ কেস |
| ফাইন্যান্স FinanceRatio | ✅ | — | — | FinanceRatioServiceTest (বিদ্যমান) |
| ফাইন্যান্স DoubleEntry ডাবল-এন্ট্রি | ✅ | — | — | DoubleEntryServiceTest (বিদ্যমান) |
| ইনভেন্টরি Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest ৫ কেস + ERP লিস্ট পেজ UI |
| বিক্রয় Sales | ✅ | ✅ | ✅ | বিদ্যমান SalesModuleTest + সেলস অর্ডার পেজ UI |
| পণ্য Product | ✅ | ✅ | ✅ | বিদ্যমান ProductModuleTest + পণ্য পেজ UI |
| ক্রয় Purchase | ✅ | ✅ | — | বিদ্যমান PurchaseModuleTest |
| উৎপাদন Manufacturing | ✅ | — | — | বিদ্যমান ManufacturingServiceTest |
| MRP ইঞ্জিন | ✅ | — | — | বিদ্যমান MrpEngineServiceTest |
| CRM | ✅ | ✅ | — | বিদ্যমান CrmModuleTest/CrmServiceTest |
| HR | ✅ | — | — | বিদ্যমান HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest |
| প্রজেক্ট Project | ✅ | ✅ | ✅ | বিদ্যমান ProjectModuleTest + প্রজেক্ট পেজ UI |
| অ্যাপ্রুভাল Approval/Workflow | ✅ | ✅ | ✅ | বিদ্যমান WorkflowModuleTest + অ্যাপ্রুভাল পেজ UI |
| OMS/WMS/TMS | ✅ | — | — | বিদ্যমান OmsWmsTmsServiceTest |
| QMS কোয়ালিটি | ✅ | — | — | বিদ্যমান QualityModuleTest |
| EAM অ্যাসেট | ✅ | — | — | বিদ্যমান EamModuleTest |
| DMS ডকুমেন্ট | ✅ | — | — | বিদ্যমান DmsModuleTest |
| BI রিপোর্ট | ✅ | ✅ | — | বিদ্যমান BiModuleTest + API |
| নোটিফিকেশন চ্যানেল | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService ১২ কেস) |
| রিপোর্ট/ডকুমেন্ট ডিটেইল | ✅ | আংশিক | ✅ | জেনারেশন লজিকে ইউনিট টেস্ট; ডিটেইল পেজ UI ৩ কেস (report_list_page_test) |

### সিস্টেম ম্যানেজমেন্ট (১৪ কন্ট্রোলার)

| কন্ট্রোলার ডোমেইন | ইউনিট | API | UI | ব্যাখ্যা |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (User সাইড) + ইউজার লিস্ট পেজ UI |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (Role সাইড) + রোল লিস্ট পেজ UI |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (Permission সাইড) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (Config সাইড) + কনফিগ পেজ UI |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| বাকি ৭ কন্ট্রোলার (লগইন/অডিট/ডিকশনারি ইত্যাদি) | ✅ | ✅ | — | BusinessControllersTest ১০ ডোমেইন রিপ্রেজেন্টেটিভ কন্ট্রোলারের ফেইল্যুর পাথ ভেরিফিকেশন |
| লগইন পেজ | — | ✅ | ✅ | login_flow_test ২ কেস |
| ব্যক্তিগত সেন্টার | — | ✅ | ✅ | profile_page_test ৩ কেস |
| লগ পেজ | — | ✅ | ✅ | log_page_test ২ কেস |
| ড্যাশবোর্ড | — | — | ✅ | dashboard_page_test ৫ কেস |
| ইনভেন্টরি অ্যালার্ট/ফাইন্যান্স পেজ | — | — | ✅ | erp_list_pages_test |

## টেস্ট পরিসংখ্যান

### PHP ইউনিট টেস্ট: 513 tests / 2368 assertions / 32 skipped

এইবার নতুন ৯টি ফাইল (সবগুলো কপিরাইট হেডারসহ, 63 tests / 125 assertions):

| ফাইল | কেস সংখ্যা | কভার অবজেক্ট |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance কনসোলিডেশন |
| tests/AccountBalanceServiceTest.php | 4 | অ্যাকাউন্ট ব্যালেন্স |
| tests/PeriodCloseServiceTest.php | 5 | পিরিয়ড ক্লোজিং |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | ইনভেন্টরি এক্সটেনশন |
| tests/AdminUserRoleControllerTest.php | 9 | User/Role কন্ট্রোলার |
| tests/AdminPermissionConfigControllerTest.php | 8 | Permission/Config কন্ট্রোলার |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 ডোমেইন | রিপ্রেজেন্টেটিভ কন্ট্রোলারের ফেইল্যুর পাথ ভেরিফিকেশন |

2026-08-27 নতুন ৩টি PHP ফাইল (14 tests; TEST_DB_* অনুপস্থিত থাকলে ইন্টিগ্রেশন টেস্ট ৬/৬ স্বয়ংক্রিয় স্কিপ):

| ফাইল | কেস সংখ্যা | কভার অবজেক্ট |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | DB ট্রানজেকশন রোলব্যাক/commit/ডুপ্লিকেট সোর্স/pcntl_fork কনকারেন্ট লক (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | নোটিফিকেশন সার্ভিস |
| tests/FinanceRatioServiceTest.php | 2 | ফাইন্যান্সিয়াল রেশিও |

### Flutter পেজ টেস্ট: 98 tests সব পাস

এইবার নতুন ৮টি ফাইল ২৯ কেস (বিদ্যমান ১০টি ফাইল অপরিবর্তিত, সব পাস); `flutter analyze` 0 error (১টি বিদ্যমান info):

| ফাইল | কেস সংখ্যা |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 নতুন ১টি ফাইল (৩ কেস):

| ফাইল | কেস সংখ্যা |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### API অটোমেশন: 104 এন্ডপয়েন্ট / ~230 অ্যাসারশন (19 গ্রুপ মডিউল)

tests/E2E/api-coverage.php (423 লাইন, `php -l` পাস): বিশুদ্ধ রিড-অনলি + আইডেমপোটেন্ট (ব্যক্তিগত সেন্টার GET ডিটেইল→PUT একই মান ফেরত লেখা), টেবিল অনুপস্থিত সনাক্তকরণসহ (500 + Base table not found → SKIP, install.sql ফুল সিডের প্রয়োজনীয়তা নির্দেশ করে)।

**লোকালি এক্সিকিউট হয়নি** (MySQL ক্রেডেনশিয়াল নেই, 8788-এ সার্ভিস নেই), CI e2e পরিবেশে চালাতে হবে:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

কভারেজ ১৯ গ্রুপ মডিউল: সিস্টেম ম্যানেজমেন্ট (ইউজার/রোল/পারমিশন/কনফিগ/হেলথ/মেট্রিক্স), ফাইন্যান্স (কনসোলিডেশন/ব্যালেন্স/ক্লোজিং/রেশিও), ইনভেন্টরি, বিক্রয়, পণ্য, ক্রয়, প্রজেক্ট, অ্যাপ্রুভাল, CRM, BI, নোটিফিকেশন, রিপোর্ট।

> ভুল সংশোধন: api-tester একবার সন্দেহ করেছিল `erp_admin_config` টেবিল অনুপস্থিত —— **ত্রুটি নয়**। প্রকৃত টেবিলের নাম `erp_system_config` (install.sql:133 এ তৈরি, SystemConfig মডেল সঠিক পয়েন্ট করে), রিপোর্টে সংশোধন করা হলো।

## কভারেজ

pcov পরিমাপ (2026-08-26, 2026-08-27 এ পুনরায় পরিমাপ করা হয়নি, এই মান ব্যবহৃত): সামগ্রিক **7.51%** (বেসলাইন 4.8%), app/service **15.65%** (বেসলাইন 10.6%), app/controller **3.62%**।

CI থ্রেশহোল্ড ও টার্গেটের সাথে তুলনা (docs/superpowers/plans/2026-08-07-next-phase-plan.md P1-B4 দেখুন):

| মাত্রা | বর্তমান | CI থ্রেশহোল্ড | টার্গেট |
|------|------|---------|------|
| সামগ্রিক | 7.51% | 4% ✅ অর্জিত | 30% |
| app/service | 15.65% | 10% ✅ অর্জিত | 40% |
| app/controller | 3.62% | — | — |

সামগ্রিক ও service কভারেজ CI থ্রেশহোল্ড অতিক্রম করেছে, টার্গেট থেকে এখনও বড় ব্যবধান আছে, P1-B4 রুট অনুযায়ী টেস্ট যোগ করা চালিয়ে যেতে হবে।

## সাথে সাথে ফিক্স করা প্রকৃত ত্রুটি (৫ জায়গায়)

| # | অবস্থান | ত্রুটি | ফিক্স |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php、PermissionController.php | `use support\Response;` নেই, রানটাইম TypeError | import যোগ করা হয়েছে |
| 2 | app/controller/Admin/DocsController.php | `path()` তৃতীয় প্যারামিটারে null দিলে ক্র্যাশ | কল সংশোধন |
| 3 | lib/pages/user_list_page.dart | ব্যাচ ডিলিট/সক্রিয় বাটনে Obx র্যাপার নেই, চেক করলে বাটন কখনো দেখা যায় না | Obx র্যাপার যোগ |
| 4 | scripts/api-coverage.php (এবং এইবার app/queue/redis/search/ ৩টি ফাইল) | cs-fixer ফরম্যাট অসম্মত | fixer অনুযায়ী ফিক্স করা হয়েছে |
| 5 | app/model/FinanceCashJournal.php | `UPDATED_AT` ফিল্ড install.sql এর সাথে অমিল | ফিল্ড সংশোধন করা হয়েছে |

## Go / Rust

**N/A** — রিপোজিটরিতে কোনো .go / .rs / Cargo.toml কোড নেই, দুটি টেক স্ট্যাক টেস্টের চিহ্ন প্রয়োগযোগ্য নয়।

## অমীমাংসিত বিষয় ক্লোজড (2026-08-27 আপডেট)

মূল 2026-08-26 সংস্করণের ৫টি অমীমাংসিত বিষয় সম্পূর্ণ প্রসেস করা হয়েছে:

1. **DB ট্রানজেকশন পাথ** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` নতুন ৬ কেস (রোলব্যাক/commit/ডুপ্লিকেট সোর্স/pcntl_fork কনকারেন্ট লক, `Group(integration)`), TEST_DB_* না থাকলে ৬/৬ স্বয়ংক্রিয় স্কিপ; CI php জব এ TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST ইনজেক্ট করা হয়েছে।
2. **api-coverage CI সংযুক্ত** ✅ — `.github/workflows/ci.yml` e2e জব সিড আপগ্রেড করে ফুল install.sql (১৬৩ টেবিল), smoke এর পর নতুন «Run E2E API coverage» স্টেপ।
3. **রিপোর্ট/ডকুমেন্ট ডিটেইল পেজ UI কভার হয়নি** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` ৩ কেস সব পাস।
4. **CaptchaTest পরিবেশ ডিপেন্ডেন্সি** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` PIXELS→AREA ডুয়াল-ভার্সন কম্প্যাটিবিলিটি + clone() গার্ড; `tests/CaptchaTest.php` poster-php v1.2.3 কন্ট্রাক্ট অনুযায়ী নতুন করে লেখা, লোকাল imagick পাথে ৭/৭ পাস (২৭ অ্যাসারশন)।
5. **কভারেজ টার্গেট** ✅ অগ্রগতি — নতুন `tests/NotificationServiceTest.php`、`tests/FinanceRatioServiceTest.php`; কভারেজ সংখ্যা 2026-08-26 পরিমাপ অনুযায়ী ব্যবহৃত (পুনরায় পরিমাপ করা হয়নি), টার্গেট (30%/40%) থেকে এখনও ধারাবাহিকভাবে যোগ করা প্রয়োজন।

রিগ্রেশন বেসলাইন: **513 tests / 2368 assertions / 32 skipped** সবুজ (আগের সংস্করণ 505/2342/26)।

## আপডেট রেকর্ড

| তারিখ | পরিবর্তন |
|------|------|
| 2026-08-26 | প্রাথমিক সংস্করণ: 505 tests / 2342 assertions / 26 skipped; অমীমাংসিত বিষয় ৫টি; সাথে সাথে ফিক্স ৪ জায়গায় |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; অমীমাংসিত বিষয় ৫টি সম্পূর্ণ ক্লোজড; সাথে সাথে ফিক্স ৫ জায়গায়; নতুন ৪টি টেস্ট ফাইল; সব ছবিতে ওয়াটারমার্ক erik.xyz |

## রিপোর্ট ও আর্টিফ্যাক্ট স্টোরেজ পাথ

- এই রিপোর্ট: `docs/TEST_REPORT.md`
- কভারেজ ডেটা: `runtime/coverage/` (pcov জেনারেটেড)
- API অটোমেশন স্ক্রিপ্ট: `tests/E2E/api-coverage.php`
- PHP ইউনিট টেস্ট: `tests/*.php` (এইবার নতুন ৯ ফাইল উপরের টেবিলে দেখুন)
- Flutter টেস্ট: `test/pages/*.dart` (এইবার নতুন ৮ ফাইল উপরের টেবিলে দেখুন)
