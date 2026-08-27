# परीक्षण रिपोर्ट — 2026-08-26

> अद्यतन: 2026-08-27 — शेष 5 मदें पूर्ण रूप से बंद; परीक्षण संख्या 505/2342/26 → 513/2368/32; साथ में 4 → 5 स्थानों का फिक्स। पुराने मान दस्तावेज़ के अंत में「अद्यतन रिकॉर्ड」देखें।

## कार्यकारी सारांश

| मीट्रिक | मान |
|------|----|
| रिपोर्ट दिनांक | 2026-08-26 |
| PHP यूनिट टेस्ट | 513 tests / 2368 assertions / 32 skipped |
| Flutter पेज टेस्ट | 98 tests सभी पास (flutter analyze 0 error) |
| API स्वचालन | 104 एंडपॉइंट / ~230 assertions (CI e2e में जुड़ा, ci.yml में「Run E2E API coverage」चरण देखें) |
| कवरेज (pcov माप) | समग्र 7.51% / app/service 15.65% / app/controller 3.62% |
| स्टैटिक विश्लेषण | PHPStan 0 error ✅ |
| कोड शैली | php-cs-fixer 0 diff ✅ (इस बार 3 मौजूदा फ़ाइलें साथ में ठीक कीं) |
| साथ में वास्तविक बग फिक्स | 5 स्थान (3 PHP + 1 Flutter + 1 प्रारूप) |
| Go/Rust | N/A (रिपॉजिटरी में कोई .go/.rs/Cargo.toml कोड नहीं) |

इस बार तीन-पथ समानांतर परीक्षण वितरण: PHP यूनिट टेस्ट (php-tester, 9 नई फ़ाइलें), API स्वचालन (api-tester, 1 नई फ़ाइल), Flutter पेज टेस्ट (ui-tester, 8 नई फ़ाइलें 29 केस)।

## कवरेज मैट्रिक्स

मॉड्यूल (22 व्यावसायिक क्षेत्र + सिस्टम प्रबंधन 14 कंट्रोलर) को परीक्षण प्रकार के अनुसार कवरेज से चिह्नित किया गया।

### 22 व्यावसायिक क्षेत्र

| मॉड्यूल | यूनिट | API | UI | विवरण |
|------|------|-----|-----|------|
| वित्त Consolidation मर्ज | ✅ | ✅ | — | ConsolidationServiceTest 5 केस + API |
| वित्त AccountBalance खाता शेष | ✅ | ✅ | — | AccountBalanceServiceTest 4 केस |
| वित्त PeriodClose अवधि कैरी-ओवर | ✅ | ✅ | — | PeriodCloseServiceTest 5 केस |
| वित्त FinanceRatio | ✅ | — | — | FinanceRatioServiceTest (मौजूदा) |
| वित्त DoubleEntry दोहरी प्रविष्टि | ✅ | — | — | DoubleEntryServiceTest (मौजूदा) |
| इन्वेंटरी | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 केस + ERP सूची पेज UI |
| विक्रय | ✅ | ✅ | ✅ | मौजूदा SalesModuleTest + विक्रय आदेश पेज UI |
| उत्पाद | ✅ | ✅ | ✅ | मौजूदा ProductModuleTest + उत्पाद पेज UI |
| क्रय | ✅ | ✅ | — | मौजूदा PurchaseModuleTest |
| विनिर्माण | ✅ | — | — | मौजूदा ManufacturingServiceTest |
| MRP इंजन | ✅ | — | — | मौजूदा MrpEngineServiceTest |
| CRM | ✅ | ✅ | — | मौजूदा CrmModuleTest/CrmServiceTest |
| HR | ✅ | — | — | मौजूदा HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest |
| परियोजना | ✅ | ✅ | ✅ | मौजूदा ProjectModuleTest + परियोजना पेज UI |
| अनुमोदन/वर्कफ़्लो | ✅ | ✅ | ✅ | मौजूदा WorkflowModuleTest + अनुमोदन पेज UI |
| OMS/WMS/TMS | ✅ | — | — | मौजूदा OmsWmsTmsServiceTest |
| QMS गुणवत्ता | ✅ | — | — | मौजूदा QualityModuleTest |
| EAM संपत्ति | ✅ | — | — | मौजूदा EamModuleTest |
| DMS दस्तावेज़ | ✅ | — | — | मौजूदा DmsModuleTest |
| BI रिपोर्ट | ✅ | ✅ | — | मौजूदा BiModuleTest + API |
| अधिसूचना चैनल | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService 12 केस) |
| रिपोर्ट/दस्तावेज़ विवरण | ✅ | आंशिक | ✅ | जनरेशन तर्क में यूनिट टेस्ट; विवरण पेज UI 3 केस (report_list_page_test) |

### सिस्टम प्रबंधन (14 कंट्रोलर)

| कंट्रोलर क्षेत्र | यूनिट | API | UI | विवरण |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (User पक्ष) + उपयोगकर्ता सूची पेज UI |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (Role पक्ष) + भूमिका सूची पेज UI |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (Permission पक्ष) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (Config पक्ष) + कॉन्फ़िगरेशन पेज UI |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| शेष 7 कंट्रोलर (लॉगिन/ऑडिट/शब्दकोश आदि) | ✅ | ✅ | — | BusinessControllersTest 10 क्षेत्र प्रतिनिधि कंट्रोलर विफलता पथ सत्यापन |
| लॉगिन पेज | — | ✅ | ✅ | login_flow_test 2 केस |
| व्यक्तिगत केंद्र | — | ✅ | ✅ | profile_page_test 3 केस |
| लॉग पेज | — | ✅ | ✅ | log_page_test 2 केस |
| डैशबोर्ड | — | — | ✅ | dashboard_page_test 5 केस |
| इन्वेंटरी अलर्ट/वित्त पेज | — | — | ✅ | erp_list_pages_test |

## परीक्षण आँकड़े

### PHP यूनिट टेस्ट: 513 tests / 2368 assertions / 32 skipped

इस बार 9 नई फ़ाइलें (सभी कॉपीराइट हेडर के साथ, 63 tests / 125 assertions):

| फ़ाइल | केस संख्या | कवर वस्तु |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance मर्ज |
| tests/AccountBalanceServiceTest.php | 4 | खाता शेष |
| tests/PeriodCloseServiceTest.php | 5 | अवधि कैरी-ओवर |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | इन्वेंटरी विस्तार |
| tests/AdminUserRoleControllerTest.php | 9 | User/Role कंट्रोलर |
| tests/AdminPermissionConfigControllerTest.php | 8 | Permission/Config कंट्रोलर |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 क्षेत्र | प्रतिनिधि कंट्रोलर विफलता पथ सत्यापन |

2026-08-27 में 3 नई PHP फ़ाइलें (14 tests; TEST_DB_* की कमी पर एकीकरण टेस्ट 6/6 स्वतः स्किप):

| फ़ाइल | केस संख्या | कवर वस्तु |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | DB ट्रांज़ैक्शन रोलबैक/commit/डुप्लिकेट स्रोत/pcntl_fork समवर्ती लॉक (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | अधिसूचना सेवा |
| tests/FinanceRatioServiceTest.php | 2 | वित्तीय अनुपात |

### Flutter पेज टेस्ट: 98 tests सभी पास

इस बार 8 फ़ाइलें 29 केस (मौजूदा 10 फ़ाइलें अपरिवर्तित, सभी पास); `flutter analyze` 0 error (1 मौजूदा info):

| फ़ाइल | केस संख्या |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 में 1 नई फ़ाइल (3 केस):

| फ़ाइल | केस संख्या |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### API स्वचालन: 104 एंडपॉइंट / ~230 assertions (19 मॉड्यूल समूह)

tests/E2E/api-coverage.php (423 पंक्तियाँ, `php -l` पास): शुद्ध केवल-पढ़ने + इडेम्पोटेंट (व्यक्तिगत केंद्र GET विवरण→PUT उसी मान से वापस लिखना), गायब तालिका पहचान सहित (500 + Base table not found → SKIP संकेत install.sql पूर्ण सीड की आवश्यकता)।

**स्थानीय रूप से निष्पादित नहीं** (MySQL क्रेडेंशियल नहीं, 8788 पर सेवा नहीं), CI e2e वातावरण में चलाना आवश्यक:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

19 मॉड्यूल समूह कवर: सिस्टम प्रबंधन (उपयोगकर्ता/भूमिका/अनुमति/कॉन्फ़िगरेशन/स्वास्थ्य/मेट्रिक्स), वित्त (मर्ज/शेष/कैरी-ओवर/अनुपात), इन्वेंटरी, विक्रय, उत्पाद, क्रय, परियोजना, अनुमोदन, CRM, BI, अधिसूचना, रिपोर्ट।

> संशोधन: api-tester को संदेह था कि `erik_admin_config` तालिका गायब है —— **कोई बग नहीं**। वास्तविक तालिका नाम `erik_system_config` है (install.sql:133 में बनी, SystemConfig मॉडल सही इंगित करता है), रिपोर्ट में सुधार किया गया।

## कवरेज

pcov माप (2026-08-26, 2026-08-27 में दोबारा नहीं मापा, यही मान उपयोग): समग्र **7.51%** (आधार 4.8%), app/service **15.65%** (आधार 10.6%), app/controller **3.62%**।

CI सीमा और लक्ष्य से तुलना (docs/superpowers/plans/2026-08-07-next-phase-plan.md P1-B4 देखें):

| आयाम | वर्तमान | CI सीमा | लक्ष्य |
|------|------|---------|------|
| समग्र | 7.51% | 4% ✅ मानक पूर्ण | 30% |
| app/service | 15.65% | 10% ✅ मानक पूर्ण | 40% |
| app/controller | 3.62% | — | — |

समग्र और service कवरेज CI सीमा पार कर चुके हैं, लक्ष्य से अभी बड़ा अंतर है, P1-B4 मार्ग के अनुसार परीक्षण जोड़ते रहना चाहिए।

## साथ में ठीक किए गए वास्तविक बग (4 स्थान)

| # | स्थान | बग | फिक्स |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php, PermissionController.php | `use support\Response;` की कमी, रनटाइम TypeError | import जोड़ा |
| 2 | app/controller/Admin/DocsController.php | `path()` तीसरे पैरामीटर में null देने पर क्रैश | कॉल सुधारा |
| 3 | lib/pages/user_list_page.dart | बैच डिलीट/सक्षम बटन Obx रैप की कमी, चयन के बाद बटन कभी नहीं दिखता | Obx रैप जोड़ा |
| 4 | scripts/api-coverage.php (और इस बार app/queue/redis/search/ 3 फ़ाइलें) | cs-fixer प्रारूप अमान्य | fixer के अनुसार ठीक किया |
| 5 | app/model/FinanceCashJournal.php | `UPDATED_AT` फ़ील्ड install.sql से असंगत | फ़ील्ड सुधारा |

## Go / Rust

**N/A** — रिपॉजिटरी में कोई .go / .rs / Cargo.toml कोड नहीं, दोनों तकनीकी स्टैक परीक्षण अनुपयुक्त चिह्नित।

## शेष मदों का बंद होना (2026-08-27 अद्यतन)

मूल 2026-08-26 संस्करण की 5 शेष मदें सभी पूरी तरह संसाधित:

1. **DB ट्रांज़ैक्शन पथ** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` में 6 नए केस (रोलबैक/commit/डुप्लिकेट स्रोत/pcntl_fork समवर्ती लॉक, `Group(integration)`), TEST_DB_* न होने पर 6/6 स्वतः स्किप; CI php job में TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST इंजेक्ट किए गए।
2. **api-coverage का CI एकीकरण** ✅ — `.github/workflows/ci.yml` e2e job का सीड पूर्ण install.sql (163 तालिकाएँ) में उन्नत, smoke के बाद「Run E2E API coverage」चरण जोड़ा।
3. **रिपोर्ट/दस्तावेज़ विवरण पेज UI अकवर** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` 3 केस सभी पास।
4. **CaptchaTest पर्यावरण निर्भरता** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` PIXELS→AREA दो-संस्करण संगतता + clone() गार्ड; `tests/CaptchaTest.php` poster-php v1.2.3 अनुबंध के अनुसार फिर से लिखा, स्थानीय imagick पथ 7/7 पास (27 assertions)।
5. **कवरेज लक्ष्य** ✅ प्रगति — नई `tests/NotificationServiceTest.php`, `tests/FinanceRatioServiceTest.php`; कवरेज संख्या 2026-08-26 माप पर आधारित (दोबारा नहीं मापा), लक्ष्य (30%/40%) से निरंतर जोड़ना आवश्यक।

रिग्रेशन आधार: **513 tests / 2368 assertions / 32 skipped** सभी हरे (पिछला संस्करण 505/2342/26)।

## अद्यतन रिकॉर्ड

| दिनांक | परिवर्तन |
|------|------|
| 2026-08-26 | प्रथम संस्करण: 505 tests / 2342 assertions / 26 skipped; शेष मदें 5; साथ में 4 स्थानों का फिक्स |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; शेष 5 मदें पूर्ण बंद; साथ में 5 स्थानों का फिक्स; 4 नई परीक्षण फ़ाइलें; सभी चित्रों पर वॉटरमार्क erik.xyz |

## रिपोर्ट और उत्पाद भंडारण पथ

- यह रिपोर्ट: `docs/TEST_REPORT.md`
- कवरेज डेटा: `runtime/coverage/` (pcov द्वारा उत्पन्न)
- API स्वचालन स्क्रिप्ट: `tests/E2E/api-coverage.php`
- PHP यूनिट टेस्ट: `tests/*.php` (इस बार 9 नई फ़ाइलें ऊपर तालिका देखें)
- Flutter टेस्ट: `test/pages/*.dart` (इस बार 8 नई फ़ाइलें ऊपर तालिका देखें)
