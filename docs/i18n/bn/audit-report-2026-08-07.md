# অডিট রিপোর্ট — 2026-08-07

**প্রজেক্ট**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**সুযোগ**: সম্পূর্ণ রান টেস্ট, গভীর পরীক্ষা, P0/P1 সমস্যা মেরামত
**নির্দেশ**: "তুমি পুরোটা টেস্ট করো, চালাও, গভীরভাবে পরীক্ষা করে দেখো এখনও সমস্যা বা অপটিমাইজেশনের জায়গা আছে কি না?"
**টেস্ট ফলাফল**: OK (135 tests, 799 assertions) — সব পাস

---

## 1. টেস্ট ও রান ভেরিফিকেশন ফলাফল

| আইটেম | ফলাফল |
|---|---|
| PHPUnit সম্পূর্ণ স্যুট | 135 tests / 799 assertions সব পাস |
| সার্ভিস স্টার্ট (port 8787→অস্থায়ী 8791) | স্বাভাবিক স্টার্ট, কোনো প্রসেস ক্র্যাশ নেই |
| /health হেলথ চেক | code=0, database/redis/elasticsearch ফিল্ড সম্পূর্ণ |
| রেট লিমিট চেইন | /api/auth/login ধারাবাহিক রিকোয়েস্ট 429 রিটার্ন |
| JWT ব্ল্যাকলিস্ট / লগইন লক | স্বাভাবিকভাবে কার্যকর (Redis মেরামতের পরে) |
| CS-Fixer | 31টি ফাইলের ফরম্যাট ভায়োলেশন মেরামত করা হয়েছে |
| PHPStan | ক্যাশ ক্ষতি মেরামতের পরে আবার চলে (851টি ORM ম্যাজিক মেথড মিথ্যা পজিটিভ, 75টি অপ্রচলিত বেসলাইন) |

---

## 2. P0 মেরামত (রানটাইম ফেইলিওর — সব মেরামত ও ভেরিফাই হয়েছে)

### 2.1 support\Redis ক্লাস অনুপস্থিত — সিকিউরিটি মেকানিজম নীরবে নিষ্ক্রিয়

- **ঘটনা**: `support\Redis` নেই (composer.json-এ webman/redis কখনও যোগ করা হয়নি), 9টি ফাইল এটি রেফারেন্স করে।
- **মূল কারণ**: একাধিক `catch (\Throwable)` fail-open ডিজাইন ক্লাস অনুপস্থিত এরর গিলে ফেলে, ফলে রেট লিমিট, JWT ব্ল্যাকলিস্ট, লগইন লক, ব্লক সব নীরবে অকার্যকর — ইন্টারফেস "স্বাভাবিক মনে হয়" কিন্তু কোনো সুরক্ষা নেই।
- **মেরামত**: `composer require webman/redis`; `config/redis.php` এনভায়রনমেন্ট ভেরিয়েবলাইজড (REDIS_PASSWORD/HOST/PORT/DATABASE)।
- **ভেরিফিকেশন**: /health রিটার্ন করে `redis: ok`; রেট লিমিট টেস্ট 429 রিটার্ন করে।

### 2.2 ApiVersion মিডলওয়্যার কম্পাইল ফেইল — সব /api রাউট 500

- **ঘটনা**: `Interface "app\middleware\MiddlewareInterface" not found` — `use Webman\MiddlewareInterface;` অনুপস্থিত।
- **মেরামতের পর দ্বিতীয় এরর**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` হল `Webman\Http\Request`-এর সাবক্লাস, প্যারামিটার কনট্রাভারিয়েন্স কন্ট্রাক্ট ভায়োলেশন।
- **মেরামত**: `Webman\Http\Request` / `Webman\Http\Response` ইমপোর্ট ব্যবহার করা হয়েছে।

### 2.3 AdminAuth মিডলওয়্যার প্যারামিটার কনট্রাভারিয়েন্স — /admin রাউট worker ক্র্যাশ

- **ঘটনা**: /admin/dashboard ট্রিগার করে worker Empty reply (কম্পাইল ক্র্যাশ)।
- **মূল কারণ**: 2.2-এর মতো একই প্যারামিটার কনট্রাভারিয়েন্স সমস্যা।
- **মেরামত**: `Webman\Http\Request` / `Webman\Http\Response` ব্যবহার করা হয়েছে (`support\Redis` রাখা হয়েছে)।
- **ভেরিফিকেশন**: 401 JSON রিটার্ন করে।

### 2.4 validator() হেল্পার ফাংশন নেই — লগইন 500

- **ঘটনা**: `Call to undefined function validator()`, 99টি ফাইলে 105 বার কল।
- **মেরামত**: `composer require illuminate/validation`; `app/functions.php`-এ হেল্পার ফাংশন ইমপ্লিমেন্ট (স্ট্যাটিক $factory ক্যাশ)।
- **ফাঁদ**: `Factory::__construct()`-এর প্রথম প্যারামিটার অবশ্যই `Translator` হতে হবে, `ArrayLoader` নয়।
- **অবশিষ্ট (P2)**: এরর মেসেজ অনূদিত নয় (`validation.required` দেখায়, চীনা নয়), zh_CN ল্যাঙ্গুয়েজ প্যাক যোগ করতে হবে।

### 2.5 CORS হার্ডকোডেড + প্রি-ফ্লাইট রেসপন্সে CORS হেডার নেই

- **মেরামত**: নতুন `app/common/CorsPolicy.php`, `CORS_ALLOWED_ORIGIN` এনভায়রনমেন্ট ভেরিয়েবল থেকে হোয়াইটলিস্ট পড়ে (কমা-বিভক্ত), origin ইকো; ম্যাচ না হলে CORS হেডার পাঠায় না।
- **মূল পয়েন্ট**: `Route::fallback` গ্লোবাল মিডলওয়্যার চেইন দিয়ে যায় না, OPTIONS প্রি-ফ্লাইটকে নিজেই CORS হেডার যুক্ত করতে হবে — fallback ক্লোজারে সামলানো হয়েছে।
- **সিকিউরিটি হেডার**: অপ্রচলিত X-XSS-Protection সরানো হয়েছে; CSP-তে `connect-src 'self'` যোগ হয়েছে।

### 2.6 FastRoute BadRouteException — রাউট শ্যাডোয়িং

- **ঘটনা**: `Static route "/install" is shadowed by previously defined variable route`।
- **মূল কারণ**: OPTIONS ওয়াইল্ডকার্ড রাউট `/{path:.+}` পরবর্তী স্ট্যাটিক রাউট ছায়া ফেলে; প্লাগইন রাউট (apidoc) config/route.php-এর পরে লোড হয়।
- **মেরামত**: ওয়াইল্ডকার্ড রাউট সরিয়ে `Route::fallback` ব্যবহার (রাউট ফাইলের শেষে থাকতে হবে); `/crm/pool/rules` resource থেকে স্পষ্ট GET রাউটে পরিবর্তন, `PoolController::rules()` public করা হয়েছে।

---

## 3. P1 মেরামত (ইঞ্জিনিয়ারিং কোয়ালিটি)

- **3.1 PHPStan ক্যাশ ক্ষতি**: /tmp/phpstan/cache মুছে ফেলা service/ ডিরেক্টরি থেকে এসেছে (মাইক্রোসার্ভিস বিভাজনের অবশিষ্ট), পুরনো অ্যাবসোলিউট পাথ থাকায় phar এরর, CPU 0% হ্যাং। ক্যাশ পরিষ্কার করে পুনঃস্থাপনের পর ঠিক হয়েছে। 851টি এরর webman ORM ম্যাজিক মেথডের মিথ্যা পজিটিভ; 75টি বেসলাইন পাথ অবাস্তব service/ ডিরেক্টরিতে নির্দেশ করে (P2)।
- **3.2 CS-Fixer**: 31টি ফাইলের স্পেস/use সাজানোর ভায়োলেশন মেরামত হয়েছে।
- **3.3 টেস্ট সিঙ্ক**: `test_cors_response_is_assigned_correctly` নতুন ইমপ্লিমেন্টেশন অ্যাসার্ট করতে আপডেট (withHeaders + CorsPolicy)।

---

## 4. আগের অডিট (08-04) এর মূল কারণ বাদ পড়েছে

- টেস্ট **মিডলওয়্যার ক্লাস লোডযোগ্যতা** এবং **রাউট কলযোগ্যতা** কভার করেনি (class_exists / is_subclass_of use অনুপস্থিত এবং প্যারামিটার কনট্রাভারিয়েন্স ধরতে পারে না)।
- কমিট b1fe2de দাবি করা CORS/X-XSS মেরামত প্রকৃত কোডের সাথে মেলে না — অডিট সিদ্ধান্ত রান ভেরিফিকেশনের বদলে কমিট তথ্যের ওপর বেশি নির্ভর করেছে।

---

## 5. এ রাউন্ডের পরিবর্তন তালিকা (git status: 41 পরিবর্তন + 2 নতুন)

| ফাইল | পরিবর্তন |
|---|---|
| app/middleware/ApiVersion.php | use Webman\MiddlewareInterface যোগ; প্যারামিটার টাইপ Webman\Http |
| app/middleware/AdminAuth.php | প্যারামিটার টাইপ Webman\Http |
| app/middleware/Cors.php | CorsPolicy ব্যবহারে রিফ্যাক্টর; CSP/সিকিউরিটি হেডার আপডেট |
| app/common/CorsPolicy.php | **নতুন**: CORS হোয়াইটলিস্ট পলিসি |
| config/route.php | fallback রাউট + /crm/pool/rules সংশোধন |
| app/controller/crm/PoolController.php | rules() public করা হয়েছে |
| app/functions.php | নতুন validator() হেল্পার ফাংশন |
| config/redis.php | **নতুন** (composer জেনারেটের পর এনভায়রনমেন্ট ভেরিয়েবলাইজড) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS অ্যাসার্ট সিঙ্ক |
| বাকি ~30 ফাইল | CS-Fixer ফরম্যাট মেরামত |

---

## 6. P2 পরামর্শ (এনভায়রনমেন্ট/পেন্ডিং, মেরামত হয়নি)

1. **.env DB_PASSWORD খালি** — MySQL root অথ ফেইল, `database: unavailable`; প্রকৃত পাসওয়ার্ড কনফিগ করতে হবে।
2. **পোর্ট 8787 কনফ্লিক্ট** — cloud-php/service দখল করেছে (ভিন্ন প্রজেক্ট); প্রোডাকশন ডিপ্লয়মেন্টে আলাদা করতে হবে।
3. **validator চীনা এরর মেসেজ** — ল্যাঙ্গুয়েজ প্যাক ইনস্টল বা কাস্টম messages দরকার।
4. **PHPStan বেসলাইন পুনর্নির্মাণ** — 75টি পাথ মুছে ফেলা service/ ডিরেক্টরিতে নির্দেশ করে, পরিষ্কার করে পুনর্নির্মাণের পরামর্শ।
5. **fail-open অডিট** — বিশ্বব্যাপী `catch (\Throwable)` নীরব এরর গিলে ফেলার পয়েন্ট খোঁজার পরামর্শ (এবার 1টি গুরুতর পরিণতি পাওয়া গেছে), fail-closed বা স্পষ্ট লগে পরিবর্তন।

---

*রিপোর্ট জেনারেটেড: 2026-08-07, সার্ভিস বন্ধ, পোর্ট 8787 পুনরুদ্ধার করা হয়েছে।*
