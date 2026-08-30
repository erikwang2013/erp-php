# API রেফারেন্স ডকুমেন্টেশন

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API ডকুমেন্টেশন

প্রজেক্টটি [hg/apidoc](https://github.com/hg-code/apidoc) দিয়ে স্বয়ংক্রিয়ভাবে ইন্টারঅ্যাক্টিভ API ডকুমেন্টেশন তৈরি করে।

**অ্যাক্সেস পদ্ধতি:** সার্ভিস চালু হওয়ার পর `http://localhost:8788/apidoc` দেখুন

**ডকুমেন্টেশন গ্রুপ:**
| গ্রুপ | বিবরণ | মডিউল সংখ্যা |
|------|------|--------|
| অ্যাডমিন ইন্টারফেস (Admin) | ব্যাকএন্ড ম্যানেজমেন্ট সিস্টেমের সব ইন্টারফেস | ২৫টি মডিউল |
| ক্লায়েন্ট ইন্টারফেস (Service API) | মোবাইল/ওয়েব ক্লায়েন্টের জন্য লাইটওয়েট ইন্টারফেস | ৩টি মডিউল |

**গ্লোবাল রিকোয়েস্ট হেডার:**
| হেডার | বিবরণ |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | API ভার্সন নম্বর (v1) |
| `Accept-Language` | ইন্টারন্যাশনালাইজেশন ভাষা (zh-CN/en) |

**অ্যানোটেশন নিয়মাবলী:** সব কন্ট্রোলার মেথডে `@Apidoc\*` সিরিজের অ্যানোটেশন দিয়ে ইন্টারফেসের নাম, বিবরণ, URL, রিকোয়েস্ট মেথড, প্যারামিটার ও রেসপন্স স্ট্রাকচার চিহ্নিত করা আছে।

## 1. ওভারভিউ

ওপেন অ্যাডমিন ব্যাকএন্ড (open-admin) webman v2 ভিত্তিক, RESTful JSON API প্রদান করে। সব অ্যাডমিন ইন্টারফেসে JWT প্রমাণীকরণ ও RBAC অনুমোদন যাচাই প্রয়োজন, পাবলিক ইন্টারফেস API ভার্সন হেডারের মাধ্যমে ভার্সনযুক্ত কন্ট্রোলারে রাউট হয়।

- **বেস URL**: `http://localhost:8788`
- **API ভার্সন**: রিকোয়েস্ট হেডার `API-Version: v1` দিয়ে নিয়ন্ত্রিত (না থাকলে ডিফল্ট v1)

> **এন্ডপয়েন্ট ওভারভিউ**: প্রমাণীকরণ(5) | ড্যাশবোর্ড(1) | ইউজার(7) | রোল(4) | পারমিশন(4) | কনফিগ(4) | লগ(1) | প্রোফাইল(3) | ইমপোর্ট-এক্সপোর্ট(3) | আপলোড(1) | অপারেশন(4: health/metrics/docs/security.txt) | মোট 37 এন্ডপয়েন্ট
- **প্রমাণীকরণ**: `Authorization: Bearer <token>` (JWT)
- **রেসপন্স ফরম্যাট**: `{ "code": 0, "message": "success", "data": {...} }`
- **ডকুমেন্টেশন এন্ডপয়েন্ট**: `GET /api/docs` OpenAPI 3.0 JSON স্পেসিফিকেশন রিটার্ন করে

### ইন্টারন্যাশনালাইজেশন

API রিকোয়েস্ট হেডার `Accept-Language` দিয়ে স্বয়ংক্রিয়ভাবে ভাষা পরিবর্তন করে:

| হেডার মান | ভাষা |
|---------|------|
| `zh-CN`, `zh` | চীনা (ডিফল্ট) |
| `en`, `en-US` | English |

```bash
# 英文响应
curl -H "Accept-Language: en" http://localhost:8788/admin/product

# 中文响应（默认）
curl http://localhost:8788/admin/product
```

রেসপন্সের `message` ফিল্ড সংশ্লিষ্ট ভাষায় রিটার্ন হয়।

### রিকোয়েস্ট প্রয়োজনীয়তা

- শুধুমাত্র `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` মেথড অনুমোদিত, অন্য HTTP মেথড (যেমন TRACE, CONNECT, PATCH) ব্যবহার করলে 405 রিটার্ন হয়
- সব `POST` / `PUT` রিকোয়েস্টে অবশ্যই `Content-Type: application/json` সেট করতে হবে (ফাইল আপলোড ছাড়া), অন্যথায় 415 রিটার্ন হয়
- রিকোয়েস্ট বডির আকার ১০MB-এর বেশি হতে পারবে না, বেশি হলে 413 রিটার্ন হয়
- নিরাপত্তা ফিল্টার সব রিকোয়েস্ট ইনপুটে XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, হিট হলে 403 রিটার্ন হয়
- একটানা ৫ বার লগইন ব্যর্থ হলে অ্যাকাউন্ট লক (১৫ মিনিট), লক অবস্থায় লগইন রিকোয়েস্ট 429 রিটার্ন করে
- একজন ব্যবহারকারী সর্বোচ্চ ৩টি সক্রিয় টোকেন ধারণ করতে পারে, বেশি হলে সবচেয়ে পুরনো টোকেন স্বয়ংক্রিয়ভাবে ব্ল্যাকলিস্টে যায়

## 2. এরর কোড

| code | অর্থ | ট্রিগার পরিস্থিতি |
|------|------|---------|
| 0 | সফল | |
| 400 | রিকোয়েস্ট প্যারামিটার ত্রুটি | রিকোয়েস্ট ফরম্যাট সঠিক নয় |
| 401 | প্রমাণীকৃত নয় | টোকেন অনুপস্থিত / মেয়াদোত্তীর্ণ / ব্ল্যাকলিস্টে |
| 403 | অনুমতি নেই / নিরাপত্তা ব্লক | RBAC অনুমতি অপর্যাপ্ত / SecurityFilter হিট |
| 404 | রিসোর্স নেই | কোয়েরি/আপডেট/ডিলিটের লক্ষ্য বিদ্যমান নেই |
| 405 | রিকোয়েস্ট মেথড অনুমোদিত নয় | শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমোদিত, নন-স্ট্যান্ডার্ড মেথড সরাসরি প্রত্যাখ্যাত |
| 413 | রিকোয়েস্ট বডি অতিরিক্ত বড় | Content-Length ১০MB-এর বেশি |
| 415 | অসমর্থিত মিডিয়া টাইপ | POST/PUT রিকোয়েস্টের Content-Type JSON নয় এবং ফাইল আপলোড নয় |
| 422 | প্যারামিটার যাচাই ব্যর্থ | প্রয়োজনীয় ফিল্ড অনুপস্থিত, ফরম্যাট সঠিক নয়, ব্যবসায়িক যাচাই পাস হয়নি |
| 429 | অতিরিক্ত ঘন ঘন রিকোয়েস্ট | RateLimit ট্রিগার / অ্যাকাউন্ট লক (একটানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিট লক) |
| 500 | সার্ভার অভ্যন্তরীণ ত্রুটি | |

## 3. পাবলিক এন্ডপয়েন্ট

সব পাবলিক এন্ডপয়েন্ট `/api` গ্রুপে মাউন্ট করা, `ApiVersion` মিডলওয়্যার `API-Version` হেডার অনুযায়ী সংশ্লিষ্ট ভার্সনযুক্ত কন্ট্রোলারে বিতরণ করে (যেমন `app\api\v1\controller\AuthController`)।

### 3.1 হেলথ চেক

```
GET /health
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **রেট লিমিট**: নেই

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`, `redis`, `elasticsearch` মান: `"ok"` | `"unavailable"`। ES অপ্রাপ্ত হলে `elasticsearch` `"unavailable"` রিটার্ন করে, ক্লাস্টার স্বাস্থ্য অবস্থা green/yellow না হলে প্রকৃত status মান রিটার্ন করে (যেমন `"red"`)।

### 3.2 API ডকুমেন্টেশন

```
GET /api/docs
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (60 বার/মিনিট)
- **রেসপন্স**: OpenAPI 3.0.3 JSON স্পেসিফিকেশন, সব এন্ডপয়েন্ট ডেফিনিশন, প্যারামিটার ও Schema সহ

### 3.3 ক্লিক ক্যাপচা তৈরি

```
POST /api/captcha/generate
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (60 বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "difficulty": "medium"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| difficulty | string | না | `easy` / `medium` / `hard`, ডিফল্ট `medium` |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| key | string | ক্যাপচা শনাক্তকারী, যাচাইয়ের সময় ফেরত পাঠাতে হয় |
| image | string | base64 এনকোডেড PNG ছবি |
| extra.targets[].order | int | ক্লিক ক্রম |
| extra.targets[].text | string | ক্লিক টার্গেট নির্দেশনা টেক্সট |

### 3.4 ক্লিক ক্যাপচা যাচাই

```
POST /api/captcha/verify
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (60 বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| key | string | হ্যাঁ | ক্যাপচা key, generate থেকে রিটার্ন হয় |
| clicks | array{object} | হ্যাঁ | ক্লিক কোঅর্ডিনেট অ্যারে, প্রতিটি উপাদানে `x` (int) ও `y` (int) থাকে |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

যাচাই ব্যর্থ হলে `code` 422, `message` `"验证失败，请重试"`, `data.valid` `false`।

### 3.5 লগইন

```
POST /api/auth/login
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: 10 বার/মিনিট (IP + পাথ অনুযায়ী)

**রিকোয়েস্ট বডি**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key |
| clicks | array{object} | হ্যাঁ | min:2 | ক্লিক কোঅর্ডিনেট অ্যারে |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| access_token | string | JWT অ্যাক্সেস টোকেন |
| refresh_token | string | JWT রিফ্রেশ টোকেন |
| expires_in | int | অ্যাক্সেস টোকেনের বৈধতা (সেকেন্ড), ডিফল্ট 7200 |
| user.id | string | hashid এনক্রিপ্টেড ইউজার ID |
| user.username | string | ইউজারনেম |
| user.real_name | string | প্রকৃত নাম |

**সম্ভাব্য ত্রুটি**:
- 422: প্যারামিটার যাচাই ব্যর্থ (প্রয়োজনীয় ফিল্ড অনুপস্থিত, ফরম্যাট সঠিক নয়)
- 422: ক্যাপচা ভুল, আবার চেষ্টা করুন
- 401: ইউজারনেম বা পাসওয়ার্ড ভুল
- 403: অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে
- 429: অ্যাকাউন্ট লক করা হয়েছে, ১৫ মিনিট পরে আবার চেষ্টা করুন (একটানা ৫ বার লগইন ব্যর্থ হলে)

### 3.6 রেজিস্টার

```
POST /api/auth/register
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: 5 বার/মিনিট (IP + পাথ অনুযায়ী)
- **সুইচ**: ডিফল্ট বন্ধ (`REGISTRATION_ENABLED=0`), বন্ধ থাকলে 403 রিটার্ন; `.env`-এ স্পষ্টভাবে চালু করতে হবে (`REGISTRATION_ENABLED=1`)

**রিকোয়েস্ট বডি**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড (bcrypt হ্যাশে সংরক্ষিত) |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key |
| clicks | array{object} | হ্যাঁ | min:2 | ক্লিক কোঅর্ডিনেট অ্যারে |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

রেজিস্টার সফল হলে সরাসরি JWT টোকেন রিটার্ন হয়, ইউজার স্ট্যাটাস ডিফল্টভাবে সক্রিয় (status=1)। শুধুমাত্র `REGISTRATION_ENABLED=1` হলে এই এন্ডপয়েন্ট ব্যবহার করা যায়।

### 3.7 টোকেন রিফ্রেশ

```
POST /api/auth/refresh
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (60 বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| refresh_token | string | হ্যাঁ | লগইন/রেজিস্টারে প্রাপ্ত refresh_token |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

রিফ্রেশ সফল হলে নতুন access_token ও refresh_token একসাথে রিটার্ন হয়, পুরনো টোকেন স্বয়ংক্রিয়ভাবে অকার্যকর। রিফ্রেশের সময় ইউজারের শেষ লগইন সময় ও IP আপডেট হয়।

**সম্ভাব্য ত্রুটি**:
- 422: রিফ্রেশ টোকেন অনুপস্থিত
- 401: রিফ্রেশ টোকেন অবৈধ বা মেয়াদোত্তীর্ণ

### 3.8 Prometheus মনিটরিং মেট্রিক

```
GET /metrics
```

- **প্রমাণীকরণ**: প্রয়োজন নেই
- **রেট লিমিট**: নেই
- **রেসপন্স ফরম্যাট**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus স্ক্র্যাপিংয়ের জন্য পাবলিক Prometheus মনিটরিং মেট্রিক এন্ডপয়েন্ট।

**রেসপন্স উদাহরণ**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| মেট্রিক নাম | টাইপ | বিবরণ |
|------|------|------|
| `openadmin_http_requests_total` | gauge | মোট HTTP রিকোয়েস্ট সংখ্যা |
| `openadmin_active_users` | gauge | বর্তমান সক্রিয় ইউজার সংখ্যা (২৪ ঘণ্টায় লগইন করা) |
| `openadmin_db_connection_status` | gauge | ডাটাবেস সংযোগ অবস্থা, 1=স্বাভাবিক, 0=অস্বাভাবিক |
| `openadmin_redis_connection_status` | gauge | Redis সংযোগ অবস্থা, 1=স্বাভাবিক, 0=অস্বাভাবিক |
| `openadmin_memory_usage_bytes` | gauge | PHP প্রসেসের বর্তমান মেমরি ব্যবহার (bytes) |

## 4. ড্যাশবোর্ড

সব অ্যাডমিন ইন্টারফেস `/admin` গ্রুপে মাউন্ট করা, `AdminAuth` (JWT প্রমাণীকরণ), `AdminPermission` (RBAC অনুমোদন), `OperationLog` (অপারেশন রেকর্ড) তিনটি মিডলওয়্যারের মধ্য দিয়ে যায়।

### 4.1 ড্যাশবোর্ড ডেটা

```
GET /admin/dashboard
```

- **প্রমাণীকরণ**: JWT + RBAC
- **ক্যাশ**: Redis ৫ মিনিট

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| label | string | মেট্রিক নাম |
| value | string | মেট্রিক মান (স্ট্রিং টাইপ) |
| icon | string | Material আইকন নাম |
| color | string | কার্ডের রঙের মান |
| trend | float? | দৈনিক তুলনায় বৃদ্ধির হার (শতাংশ), শুধুমাত্র "মোট ইউজার" এর এই ফিল্ড থাকে |

| trends ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| dates | array{string} | সাম্প্রতিক ৩০ দিনের তারিখ সিরিজ |
| series | array{object} | ট্রেন্ড লাইন ডেটা, প্রতিটিতে name (নাম), data (মান অ্যারে), color (রঙ) থাকে |

## 5. ইউজার ম্যানেজমেন্ট

সব ইউজার ম্যানেজমেন্ট ইন্টারফেসের রিটার্ন করা `id` হলো hashid এনক্রিপ্টেড স্ট্রিং। পাসওয়ার্ড ফিল্ড রেসপন্স থেকে বাদ দেওয়া হয়েছে। ফোন নম্বর ও ইমেইল লিস্ট ইন্টারফেসে মাস্ক করে দেখানো হয়, ডিটেইল ইন্টারফেসে প্লেইনটেক্সট রিটার্ন হয় (ডাটাবেস এনক্রিপ্টেড ফিল্ড Encryptable trait দিয়ে স্বয়ংক্রিয় ডিক্রিপ্ট হয়)।

### 5.1 ইউজার লিস্ট

```
GET /admin/user
```

- **প্রমাণীকরণ**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| keyword | string | না | | সার্চ কীওয়ার্ড, ইউজারনেম ও প্রকৃত নাম মেলে |
| status | int | না | | অবস্থা ফিল্টার, 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড ইউজার ID |
| username | string | ইউজারনেম |
| real_name | string | প্রকৃত নাম |
| phone | string | মাস্ক করা ফোন নম্বর (`138****5678` ফরম্যাট) |
| email | string | মাস্ক করা ইমেইল (`a***@example.com` ফরম্যাট) |
| status | int | 1=সক্রিয়, 0=নিষ্ক্রিয় |
| last_login_at | string | শেষ লগইন সময় (datetime) |
| created_at | string | তৈরি সময় (datetime) |

### 5.2 ইউজার তৈরি

```
POST /admin/user
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড (bcrypt সংরক্ষিত) |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| phone | string | না | | ফোন নম্বর (Encryptable এনক্রিপ্টেড সংরক্ষণ) |
| email | string | না | | ইমেইল (Encryptable এনক্রিপ্টেড সংরক্ষণ) |
| status | int | না | in:0,1 | অবস্থা, ডিফল্ট 1 (সক্রিয়) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**সম্ভাব্য ত্রুটি**:
- 422: ইউজারনেম ইতিমধ্যে বিদ্যমান
- 422: প্যারামিটার যাচাই ব্যর্থ (প্রয়োজনীয় ফিল্ড অনুপস্থিত)

### 5.3 ইউজার ডিটেইল

```
GET /admin/user/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হলো hashid এনক্রিপ্টেড ইউজার ID

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

ডিটেইল ইন্টারফেসে `phone` ও `email` প্লেইনটেক্সট রিটার্ন হয় (ডাটাবেসে এনক্রিপ্টেড সংরক্ষিত, Encryptable cast স্বয়ংক্রিয় ডিক্রিপ্ট করে), মাস্ক করা হয় না। `password` ও `id_card` সবসময় রেসপন্সে থাকে না।

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার বিদ্যমান নেই

### 5.4 ইউজার আপডেট

```
PUT /admin/user/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হলো hashid এনক্রিপ্টেড ইউজার ID

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম, না পাঠালে পুরনো মান থাকে |
| password | string | না | নতুন পাসওয়ার্ড, খালি স্ট্রিং বা না পাঠালে পরিবর্তন হয় না |
| phone | string | না | ফোন নম্বর |
| email | string | না | ইমেইল |
| status | int | না | 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার বিদ্যমান নেই

### 5.5 ইউজার ডিলিট

```
DELETE /admin/user/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হলো hashid এনক্রিপ্টেড ইউজার ID
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

সফট ডিলিট (Eloquent SoftDeletes) কার্যকর হয়, ডেটায় deleted_at চিহ্নিত হয় কিন্তু শারীরিকভাবে মুছে ফেলা হয় না।

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার বিদ্যমান নেই
- 422: সংবেদনশীল অপারেশনে পাসওয়ার্ড নিশ্চিতকরণ প্রয়োজন (password খালি)
- 422: পাসওয়ার্ড যাচাই ব্যর্থ (পাসওয়ার্ড মেলে না)

### 5.6 ইউজার ব্যাচ ডিলিট

```
POST /admin/user/batch/destroy
```

- **প্রমাণীকরণ**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

সফট ডিলিট কার্যকর হয়, `data.count` প্রকৃত ডিলিট সংখ্যা।

**সম্ভাব্য ত্রুটি**:
- 422: ডিলিট করার ইউজার নির্বাচন করুন (ids খালি)
- 422: অবৈধ ID (hashid ডিকোড ব্যর্থ)
- 422: পাসওয়ার্ড যাচাই ব্যর্থ

### 5.7 ইউজার ব্যাচ সক্রিয়/নিষ্ক্রিয়

```
POST /admin/user/batch/status
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| status | int | হ্যাঁ | 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message status মান অনুযায়ী `"批量启用成功"` বা `"批量禁用成功"` তে পরিবর্তিত হয়।

**সম্ভাব্য ত্রুটি**:
- 422: ইউজার নির্বাচন করুন (ids খালি)
- 422: অবস্থার মান অবৈধ (status 0 বা 1 নয়)

## 6. রোল ম্যানেজমেন্ট

### 6.1 রোল লিস্ট

```
GET /admin/role
```

- **প্রমাণীকরণ**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড রোল ID |
| name | string | রোল নাম |
| slug | string | রোল শনাক্তকারী (ইউনিক, অনুমোদন বিচারে ব্যবহৃত) |
| description | string | রোল বিবরণ |
| status | int | 1=সক্রিয়, 0=নিষ্ক্রিয় |
| users_count | int | এই রোলের অধীনে থাকা ইউজার সংখ্যা |

### 6.2 রোল তৈরি

```
POST /admin/role
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| name | string | হ্যাঁ | max:50 | রোল নাম |
| slug | string | হ্যাঁ | max:50 | রোল শনাক্তকারী |
| description | string | না | | রোল বিবরণ, ডিফল্ট খালি স্ট্রিং |
| status | int | না | | অবস্থা, ডিফল্ট 1 |
| permission_ids | array{int} | না | | পারমিশন ID অ্যারে (আসল INT ID, hashid নয়) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 রোল আপডেট

```
PUT /admin/role/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| name | string | না | রোল নাম |
| description | string | না | বিবরণ |
| status | int | না | 0=নিষ্ক্রিয়, 1=সক্রিয় |
| permission_ids | array{int} | না | পারমিশন ID অ্যারে, পাঠালে রোল পারমিশন সিঙ্ক (ওভাররাইট) হয় |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 রোল ডিলিট

```
DELETE /admin/role/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিট করার সময় রোলের সাথে সব পারমিশন ও ইউজারের সম্পর্ক স্বয়ংক্রিয়ভাবে বাতিল হয়, তারপর রোল রেকর্ড শারীরিকভাবে মুছে ফেলা হয়।

## 7. পারমিশন ম্যানেজমেন্ট

পারমিশন ট্রি স্ট্রাকচার ব্যবহার করে (parent_id সেলফ-রেফারেন্স), তিন ধরনের পার্থক্য করা হয়। লিস্ট ইন্টারফেস সম্পূর্ণ পারমিশন ট্রি রিটার্ন করে।

### 7.1 পারমিশন ট্রি

```
GET /admin/permission
```

- **প্রমাণীকরণ**: JWT + RBAC

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড |
| parent_id | string | প্যারেন্ট পারমিশন hashid, "0" রুট নোড নির্দেশ করে |
| name | string | পারমিশন নাম |
| slug | string | পারমিশন শনাক্তকারী (রুট/বাটন শনাক্তকারী) |
| type | int | 1=মেনু, 2=বাটন, 3=ইন্টারফেস |
| icon | string | মেনু আইকন (Material আইকন নাম) |
| path | string | ফ্রন্টএন্ড রুট পাথ |
| sort | int | সাজানোর মান (আরোহী) |
| children | array? | চাইল্ড পারমিশন লিস্ট (রিকার্সিভ), চাইল্ড নোড না থাকলে এই ফিল্ড থাকে না |

### 7.2 পারমিশন তৈরি

```
POST /admin/permission
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| parent_id | int | না | | প্যারেন্ট পারমিশন ID (আসল INT টাইপ), ডিফল্ট 0 |
| name | string | হ্যাঁ | max:50 | পারমিশন নাম |
| slug | string | হ্যাঁ | max:100 | পারমিশন শনাক্তকারী |
| type | int | হ্যাঁ | in:1,2,3 | 1=মেনু, 2=বাটন, 3=ইন্টারফেস |
| icon | string | না | | মেনু আইকন, ডিফল্ট খালি |
| path | string | না | | ফ্রন্টএন্ড রুট পাথ, ডিফল্ট খালি |
| sort | int | না | | সাজানোর মান, ডিফল্ট 0 |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 পারমিশন আপডেট

```
PUT /admin/permission/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| name | string | না | পারমিশন নাম |
| icon | string | না | আইকন |
| path | string | না | রুট পাথ |
| sort | int | না | সাজানোর মান |

### 7.4 পারমিশন ডিলিট

```
DELETE /admin/permission/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিট করার সময় সব চাইল্ড পারমিশন ক্যাসকেড ডিলিট হয় (`parent_id` = বর্তমান পারমিশন ID এর রেকর্ড), সাথে সব রোলের সম্পর্ক বাতিল হয়।

## 8. সিস্টেম কনফিগারেশন

সিস্টেম কনফিগারেশন `group` + `key` কম্বিনেশনে ইউনিক।

### 8.1 কনফিগ লিস্ট

```
GET /admin/config
```

- **প্রমাণীকরণ**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| group | string | না | | কনফিগ গ্রুপ অনুযায়ী ফিল্টার |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid |
| group | string | কনফিগ গ্রুপ (যেমন `system`, `email`, `storage`) |
| key | string | কনফিগ কী |
| value | string | কনফিগ মান |
| type | string | মানের টাইপ নির্দেশ (`string`, `integer`, `boolean`, `json` ইত্যাদি) |
| description | string | কনফিগ বিবরণ |

### 8.2 কনফিগ তৈরি

```
POST /admin/config
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| group | string | হ্যাঁ | max:100 | কনফিগ গ্রুপ |
| key | string | হ্যাঁ | max:100 | কনফিগ কী (একই গ্রুপে ইউনিক) |
| value | string | হ্যাঁ | | কনফিগ মান |
| type | string | না | | মানের টাইপ, ডিফল্ট `string` |
| description | string | না | | কনফিগ বিবরণ, ডিফল্ট খালি |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**সম্ভাব্য ত্রুটি**:
- 422: কনফিগ আইটেম ইতিমধ্যে বিদ্যমান (একই group + key)

### 8.3 কনফিগ আপডেট

```
PUT /admin/config/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| value | string | না | কনফিগ মান আপডেট |
| type | string | না | মানের টাইপ আপডেট |
| description | string | না | বিবরণ টেক্সট আপডেট |

### 8.4 কনফিগ ডিলিট

```
DELETE /admin/config/{id}
```

- **প্রমাণীকরণ**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

কনফিগ রেকর্ড শারীরিকভাবে মুছে ফেলা হয়।

## 9. অপারেশন লগ

অপারেশন লগ একটি রিড-অনলি ইন্টারফেস, `OperationLog` মিডলওয়্যার প্রতিটি POST/PUT/DELETE রিকোয়েস্টে স্বয়ংক্রিয়ভাবে লিখে, স্টোরেজ ফিল্ডের মধ্যে আছে `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`।

### 9.1 অপারেশন লগ লিস্ট

```
GET /admin/log
```

- **প্রমাণীকরণ**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| user_id | int | না | | ইউজার ID দিয়ে নির্ভুল ফিল্টার (আসল INT টাইপ) |
| action | string | না | | অ্যাকশন দিয়ে নির্ভুল ফিল্টার |
| path | string | না | | রিকোয়েস্ট পাথ দিয়ে ফাজি ফিল্টার |
| start_date | string | না | | শুরুর তারিখ (Y-m-d ফরম্যাট) |
| end_date | string | না | | শেষ তারিখ (Y-m-d ফরম্যাট) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid |
| user_name | string | অপারেটিং ইউজারনেম (user সম্পর্ক দিয়ে প্রাপ্ত, লগইন ছাড়া অপারেশনে "সিস্টেম" দেখায়) |
| action | string | অপারেশন অ্যাকশন বিবরণ |
| method | string | HTTP মেথড (POST/PUT/DELETE) |
| path | string | রিকোয়েস্ট পাথ |
| ip | string | ক্লায়েন্ট IP |
| source | string | রিকোয়েস্ট সোর্স |
| input | string | রিকোয়েস্ট প্যারামিটার JSON স্ট্রিং (ফাইল অন্তর্ভুক্ত নয়) |
| created_at | string | অপারেশন সময় (datetime) |

## 10. প্রোফাইল

প্রোফাইল ইন্টারফেসে শুধুমাত্র JWT প্রমাণীকরণ প্রয়োজন (RBAC অনুমোদন যাচাই প্রয়োজন নেই — `AdminPermission` মিডলওয়্যারে হোয়াইটলিস্টে যোগ করতে হবে)।

### 10.1 ব্যক্তিগত তথ্য আপডেট

```
PUT /admin/profile
```

- **প্রমাণীকরণ**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম |
| phone | string | না | ফোন নম্বর (Encryptable এনক্রিপ্টেড সংরক্ষণ) |
| email | string | না | ইমেইল (Encryptable এনক্রিপ্টেড সংরক্ষণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

রেসপন্সে `phone` ও `email` প্লেইনটেক্সট রিটার্ন হয়, `password` ও `id_card` বাদ দেওয়া হয়েছে।

### 10.2 পাসওয়ার্ড পরিবর্তন

```
PUT /admin/profile/password
```

- **প্রমাণীকরণ**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | যাচাই নিয়ম | বিবরণ |
|------|------|------|---------|------|
| old_password | string | হ্যাঁ | | বর্তমান পাসওয়ার্ড |
| new_password | string | হ্যাঁ | min:6, max:32 | নতুন পাসওয়ার্ড |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**সম্ভাব্য ত্রুটি**:
- 422: পুরনো ও নতুন পাসওয়ার্ড লিখুন
- 422: পুরনো পাসওয়ার্ড ভুল
- 422: নতুন পাসওয়ার্ডের দৈর্ঘ্য 6-32 অক্ষর

### 10.3 লগআউট

```
POST /admin/profile/logout
```

- **প্রমাণীকরণ**: JWT

**রিকোয়েস্ট বডি**: নেই (requestBody নেই, Authorization হেডার থেকে token পড়া হয়)

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

লগআউট লজিক: JWT ডিকোড করে অবশিষ্ট বৈধতা (exp - now) পায়, সেই token এর md5 হ্যাশ Redis ব্ল্যাকলিস্ট `jwt_blacklist:{md5}` এ লেখে, TTL = অবশিষ্ট বৈধতা। ব্ল্যাকলিস্টের token `AdminAuth` মিডলওয়্যারে ব্লক হয়, 401 রিটার্ন করে।

token না থাকলে 401 রিটার্ন হয়। token মেয়াদোত্তীর্ণ/অবৈধ হলে (ডিকোডে এক্সেপশন) তবুও লগআউট সফল ধরা হয়।

## 11. ইমপোর্ট ও এক্সপোর্ট

### 11.1 Excel এক্সপোর্ট

```
POST /admin/export/excel
```

- **প্রমাণীকরণ**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**রিকোয়েস্ট বডি**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| table | string | না | `admin_user` | এক্সপোর্ট টেবিলের নাম। সাপোর্টেড: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | না | | এক্সপোর্ট কলাম ফিল্ড নামের অ্যারে, খালি হলে টেবিলের সব কলাম এক্সপোর্ট হয় |
| conditions | object | না | `{}` | ফিল্টার শর্ত, key-value জোড়া, মান খালি না হলে WHERE-এ ব্যবহৃত হয় |
| title | string | না | `数据导出` | Excel শিরোনাম (Sheet নাম হিসেবে দেখানো হয়) |

**সাপোর্টেড টেবিল ও কলাম**:

| table | উপলব্ধ কলাম |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

সংবেদনশীল ফিল্ড `phone`, `email`, `id_card` এক্সপোর্টের সময় স্বয়ংক্রিয়ভাবে মাস্ক করা হয়। ডেটা সীমা ১০০০০ সারি। Excel-এ প্রথম সারি ফ্রোজেন, অটো ফিল্টার সক্রিয়।

### 11.2 PDF এক্সপোর্ট

```
POST /admin/export/pdf
```

- **প্রমাণীকরণ**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/pdf`, A4 ল্যান্ডস্কেপ)

**রিকোয়েস্ট বডি**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

অথবা টেবিল মোড:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| type | string | না | `table` | এক্সপোর্ট টাইপ: `table` / `dashboard` |
| title | string | না | `数据导出` | PDF শিরোনাম |
| data | object | না | `{}` | এক্সপোর্ট ডেটা |

`type=dashboard` হলে `data` তে `stats` অ্যারে থাকতে হবে (কার্ড ফর্মে রেন্ডার); `type=table` হলে `data` তে `columns` ও `rows` অ্যারে থাকতে হবে।

PDF টেমপ্লেটে কপিরাইট তথ্য ও এক্সপোর্ট টাইমস্ট্যাম্প থাকে।

### 11.3 ইউজার ইমপোর্ট (Excel)

```
POST /admin/import/users
```

- **প্রমাণীকরণ**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data` (ফাইল আপলোড)

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| file | file | হ্যাঁ | `.xlsx` বা `.xls` ফরম্যাট |

**Excel কলাম প্রয়োজনীয়তা**:

| কলাম নাম | বাধ্যতামূলক | বিবরণ |
|------|------|------|
| username | হ্যাঁ | ইউজারনেম (ইউনিক) |
| password | হ্যাঁ | পাসওয়ার্ড (bcrypt হ্যাশে সংরক্ষিত) |
| real_name | হ্যাঁ | প্রকৃত নাম |
| phone | না | ফোন নম্বর |
| email | না | ইমেইল |
| status | না | অবস্থা, ডিফল্ট 1 |

১ম সারি কলাম শিরোনাম (কেস-ইনসেনসিটিভ), ২য় সারি থেকে ডেটা।

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| total | int | মোট সারি (শিরোনাম সারি ছাড়া) |
| success | int | সফল ইমপোর্ট সংখ্যা |
| failed | int | ব্যর্থ সংখ্যা |
| errors | array | ব্যর্থ বিবরণ, প্রতিটিতে row (Excel সারি নম্বর) ও reason (ব্যর্থ কারণ) থাকে |

## 12. ফাইল আপলোড

```
POST /admin/upload
```

- **প্রমাণীকরণ**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data`

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| file | file | হ্যাঁ | আপলোড ফাইল |

**অনুমোদিত ফাইল টাইপ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**সর্বোচ্চ ফাইল সাইজ**: 10MB

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ফাইল তারিখ অনুযায়ী `public/upload/{Y-m-d}/` ডিরেক্টরিতে সংরক্ষিত হয়, ফাইল নাম `md5(uniqid) + মূল এক্সটেনশন`। `url` সাইট রুটের আপেক্ষিক পাথ।

**সম্ভাব্য ত্রুটি**:
- 422: ফাইল নির্বাচন করুন (আপলোড হয়নি)
- 422: অসমর্থিত ফাইল টাইপ
- 422: ফাইল সাইজ ১০MB-এর বেশি হতে পারবে না
- 500: ফাইল আপলোড ব্যর্থ (ফাইল অবৈধ)

## 13. রেসপন্স হেডার

সব ইন্টারফেসে (গ্লোবাল মিডলওয়্যার স্তরে ইনজেক্ট করা) নিম্নলিখিত রেসপন্স হেডার থাকে:

| হেডার | বিবরণ |
|----|------|
| `X-RateLimit-Limit` | রেট লিমিট সীমা (বার) |
| `X-RateLimit-Remaining` | অবশিষ্ট রিকোয়েস্ট সংখ্যা |
| `X-RateLimit-Reset` | রেট লিমিট উইন্ডো রিসেট টাইমস্ট্যাম্প |
| `Retry-After` | শুধুমাত্র রেট লিমিট ট্রিগার হলে রিটার্ন, প্রস্তাবিত অপেক্ষার সেকেন্ড |
| `X-Content-Type-Options` | `nosniff` (webman ডিফল্ট, MIME স্নিফিং নিষিদ্ধ) |
| `X-Frame-Options` | `DENY` (webman এর CORS মিডলওয়্যার/বেস কনফিগ দিয়ে প্রদান করা হয়) |

রেট লিমিট বিবরণ:
- ডিফল্ট গ্লোবাল লিমিট: 60 বার/মিনিট / IP+পাথ
- লগইন এন্ডপয়েন্ট `/api/auth/login`: 10 বার/মিনিট
- রেজিস্টার এন্ডপয়েন্ট `/api/auth/register`: 5 বার/মিনিট
- Redis অ্যাটমিক স্লাইডিং উইন্ডো অ্যালগরিদম (Lua ZSET) ব্যবহার করে, TOCTOU রেস এড়ায়
- Redis অনুপলব্ধ হলে fail open (ছেড়ে দেওয়া), রিকোয়েস্ট ব্লক হয় না

## 14. প্রমাণীকরণ ফ্লো

সম্পূর্ণ প্রমাণীকরণ সিকোয়েন্স:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT স্ট্রাকচার

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, ডিফল্ট TTL 7200 সেকেন্ড (JWT কনফিগ `default_expire` দিয়ে নিয়ন্ত্রিত)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, ডিফল্ট TTL 1209600 সেকেন্ড (JWT কনফিগ `refresh_expire` দিয়ে নিয়ন্ত্রিত, অর্থাৎ ১৪ দিন)

### নিরাপত্তা ব্যবস্থাপনা

- পাসওয়ার্ড `PASSWORD_BCRYPT` হ্যাশে সংরক্ষিত
- সংবেদনশীল ফিল্ড (phone, email, id_card) `erikwang2013/encryptable` দিয়ে ডাটাবেস স্তরে ট্রান্সপারেন্ট এনক্রিপশন/ডিক্রিপশন
- API স্তরের ID `erikwang2013/hashids` দিয়ে এনক্রিপ্ট করে ট্রান্সমিট হয়, আসল snowflake ID সিকোয়েন্স প্রকাশ এড়ায়
- SecurityFilter গ্লোবাল XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, একই IP ৫ বার/৬০ সেকেন্ডে ১৫ মিনিটের জন্য টেম্পোরারি ব্ল্যাকলিস্ট
- সংবেদনশীল অপারেশন (ইউজার, রোল, পারমিশন, কনফিগ ডিলিট) বর্তমান লগইন ইউজারের পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন
- কনকারেন্ট সেশন সীমা: একজন ইউজারের সর্বোচ্চ ৩টি সক্রিয় টোকেন, ৪র্থ ডিভাইস লগইন করলে সবচেয়ে পুরনো টোকেন ব্ল্যাকলিস্টে যায়
- অ্যাকাউন্ট লক: একটানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিট অ্যাকাউন্ট লক, লক অবস্থায় 429 রিটার্ন

## 15. ডিপ্লয়মেন্ট ও অপারেশন

### Docker Compose

প্রজেক্ট রুট ডিরেক্টরিতে `docker-compose.yml` আছে, ৫টি সার্ভিস অর্কেস্ট্রেট করে (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` দিয়ে তৈরি (`php:8.3-cli` ভিত্তিক, OPcache সক্রিয়)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions কন্টিনিউয়াস ইন্টিগ্রেশন পাইপলাইন সংজ্ঞায়িত করে:
- `php -l` সিনট্যাক্স চেক
- PHPUnit ইউনিট টেস্ট
- `flutter analyze` স্ট্যাটিক অ্যানালাইসিস

### ডাটাবেস ব্যাকআপ

`database/backup/` ডিরেক্টরি ব্যাকআপ ও রিস্টোর স্ক্রিপ্ট প্রদান করে:
- `backup.sh` — mysqldump + gzip কম্প্রেসড ব্যাকআপ, ৩০ দিনের আগের পুরনো ব্যাকআপ ফাইল স্বয়ংক্রিয় পরিষ্কার
- `restore.sh` — ইন্টারঅ্যাক্টিভ রিস্টোর, বিদ্যমান ব্যাকআপ তালিকা দেখিয়ে ইউজার বেছে নেয়

### Nginx নিরাপত্তা কনফিগারেশন

প্রোডাকশন ডিপ্লয়মেন্টে `docs/nginx-security.conf` দেখে রিভার্স প্রক্সি নিরাপত্তা শক্তিশালীকরণ কনফিগার করুন।

## 16. ব্যবসায়িক API এন্ডপয়েন্ট (ERP)

সব ব্যবসায়িক এন্ডপয়েন্ট `/admin` গ্রুপে, `AdminAuth` (JWT প্রমাণীকরণ), `AdminPermission` (RBAC অনুমোদন), `OperationLog` (অপারেশন রেকর্ড) তিনটি মিডলওয়্যারের মধ্য দিয়ে যায়।

> মোট এন্ডপয়েন্ট: পণ্য(17) | ক্রয়(8) | বিক্রয়(6) | ইনভেন্টরি(6) | ফাইন্যান্স(17) | CRM(13) | ওয়ার্কফ্লো(6) | নোটিফিকেশন(4) | প্রজেক্ট(3) | HR(9) | ম্যানুফ্যাকচারিং(7) | রিপোর্ট(4) | ড্যাশবোর্ড(3) | ক্লায়েন্ট(2) | মোট 105 এন্ডপয়েন্ট

ক্রস-মডিউল লিংকড এন্ডপয়েন্ট 🔗 দিয়ে চিহ্নিত।

### 16.1 পণ্য ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/product | পণ্য লিস্ট (পেজিনেশন+সার্চ+ক্যাটাগরি/স্ট্যাটাস ফিল্টার) |
| POST | /admin/product | পণ্য তৈরি (SKU ও প্রাইস সহ) |
| GET | /admin/product/{id} | পণ্য ডিটেইল (ক্যাটাগরি/ব্র্যান্ড/SKU/প্রাইস/ইউনিট সহ) |
| PUT | /admin/product/{id} | পণ্য আপডেট |
| DELETE | /admin/product/{id} | পণ্য ডিলিট (সফট ডিলিট, পাসওয়ার্ড নিশ্চিতকরণ প্রয়োজন) |
| GET | /admin/category | ক্যাটাগরি লিস্ট (ট্রি) |
| POST | /admin/category | ক্যাটাগরি তৈরি |
| PUT | /admin/category/{id} | ক্যাটাগরি আপডেট |
| DELETE | /admin/category/{id} | ক্যাটাগরি ডিলিট |
| GET | /admin/brand | ব্র্যান্ড লিস্ট |
| POST | /admin/brand | ব্র্যান্ড তৈরি |
| GET | /admin/warehouse | গুদাম লিস্ট |
| POST | /admin/warehouse | গুদাম তৈরি |
| GET | /admin/location | লোকেশন লিস্ট |
| GET | /admin/warehouse/{id}/locations | গুদামের অধীনে লোকেশন লিস্ট |
| GET | /admin/supplier | সাপ্লায়ার লিস্ট (ES সার্চ) |
| POST | /admin/supplier | সাপ্লায়ার তৈরি |
| GET | /admin/customer | কাস্টমার লিস্ট (ES সার্চ) |
| POST | /admin/customer | কাস্টমার তৈরি |

### 16.2 ক্রয় ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/purchase/apply | ক্রয় আবেদন লিস্ট |
| POST | /admin/purchase/apply | ক্রয় আবেদন তৈরি |
| GET | /admin/purchase/order | ক্রয় অর্ডার লিস্ট |
| POST | /admin/purchase/order | ক্রয় অর্ডার তৈরি |
| 🔗 POST | /admin/purchase/receive | রিসিভিং ডকুমেন্ট তৈরি (স্বয়ংক্রিয় স্টক-ইন+AP তৈরি) |
| GET | /admin/purchase/receive | রিসিভিং ডকুমেন্ট লিস্ট |
| GET | /admin/purchase/receive/{id} | রিসিভিং ডকুমেন্ট ডিটেইল |
| POST | /admin/purchase/return | রিটার্ন ডকুমেন্ট তৈরি |
| GET | /admin/purchase/settlement | সাপ্লায়ার সেটেলমেন্ট লিস্ট |

### 16.3 বিক্রয় ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/sales/quotation | কোটেশন লিস্ট |
| POST | /admin/sales/quotation | কোটেশন তৈরি |
| GET | /admin/sales/order | সেলস অর্ডার লিস্ট |
| POST | /admin/sales/order | সেলস অর্ডার তৈরি |
| 🔗 POST | /admin/sales/delivery | ডেলিভারি ডকুমেন্ট তৈরি (স্বয়ংক্রিয় স্টক-আউট+AR তৈরি) |
| GET | /admin/sales/delivery | ডেলিভারি ডকুমেন্ট লিস্ট |
| GET | /admin/sales/settlement | কাস্টমার সেটেলমেন্ট লিস্ট |

### 16.4 ইনভেন্টরি ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/inventory | রিয়েল-টাইম ইনভেন্টরি (গুদাম/লোকেশন/ব্যাচ/SKU মাত্রা) |
| GET | /admin/inventory/flow | ইন/আউট স্টক ফ্লো |
| GET | /admin/inventory/transfer | ট্রান্সফার ডকুমেন্ট লিস্ট |
| POST | /admin/inventory/transfer | ট্রান্সফার ডকুমেন্ট তৈরি |
| GET | /admin/inventory/check | কাউন্ট টাস্ক লিস্ট |
| POST | /admin/inventory/check | কাউন্ট টাস্ক তৈরি |
| GET | /admin/inventory/alert | ইনভেন্টরি সতর্কতা নিয়ম |

### 16.5 ফাইন্যান্স ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| POST | /admin/finance/voucher | জার্নাল ভাউচার তৈরি |
| GET | /admin/finance/ar-ap | AR/AP লিস্ট |
| POST | /admin/finance/receipt | রসিদ তৈরি |
| POST | /admin/finance/payment | পেমেন্ট তৈরি |
| GET | /admin/finance/cash-journal | ক্যাশ ও ব্যাংক জার্নাল |
| GET | /admin/finance/expense | খরচ রিইমবার্সমেন্ট লিস্ট |
| POST | /admin/finance/expense | রিইমবার্সমেন্ট আবেদন সাবমিট |
| GET | /admin/finance/report/profit | লাভ-লস স্টেটমেন্ট |
| GET | /admin/finance/general-ledger | জেনারেল লেজার (অ্যাকাউন্ট+পিরিয়ড সমষ্টি) |
| GET | /admin/finance/subsidiary-ledger | সাবসিডিয়ারি লেজার (অ্যাকাউন্টের প্রতি লেনদেন) |
| GET | /admin/finance/report/balance-sheet | ব্যালেন্স শিট (স্বয়ংক্রিয় তৈরি সহ) |
| GET | /admin/finance/report/cash-flow | ক্যাশ ফ্লো স্টেটমেন্ট (অপারেটিং/ইনভেস্টিং/ফাইন্যান্সিং) |
| GET | /admin/finance/bank-account | ব্যাংক অ্যাকাউন্ট লিস্ট |
| GET/POST/PUT/DELETE | /admin/finance/asset | স্থায়ী সম্পদ CRUD + অবচয় |
| GET/POST | /admin/finance/tax-rate | ট্যাক্স রেট কনফিগ |
| GET | /admin/finance/tax-record | ট্যাক্স রেকর্ড |
| GET/POST/PUT/DELETE | /admin/finance/currency | কারেন্সি ম্যানেজমেন্ট |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | এক্সচেঞ্জ রেট ম্যানেজমেন্ট |
| GET/POST/PUT/DELETE | /admin/finance/budget | বাজেট ম্যানেজমেন্ট (বাজেট বনাম প্রকৃত তুলনা সহ) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | কস্ট সেন্টার (ট্রি স্ট্রাকচার) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | প্রফিট সেন্টার (ট্রি স্ট্রাকচার) |

### 16.6 CRM

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/crm/opportunity | সুযোগ লিস্ট |
| POST | /admin/crm/opportunity | সুযোগ তৈরি |
| GET | /admin/crm/follow | ফলো-আপ রেকর্ড লিস্ট |
| POST | /admin/crm/follow | ফলো-আপ রেকর্ড তৈরি |
| GET | /admin/crm/funnel | ফানেল স্টেজ কনফিগ |
| GET | /admin/crm/contact | কন্টাক্ট লিস্ট |
| POST | /admin/crm/contact | কন্টাক্ট তৈরি |
| GET | /admin/crm/pool | পাবলিক পুল কাস্টমার লিস্ট |
| POST | /admin/crm/pool/claim/{id} | পাবলিক পুল কাস্টমার ক্লেইম |
| POST | /admin/crm/pool/release/{id} | পাবলিক পুলে কাস্টমার রিলিজ |
| GET/POST | /admin/crm/pool/rules | পাবলিক পুল নিয়ম CRUD |
| GET | /admin/crm/contract | কন্ট্রাক্ট লিস্ট |
| POST | /admin/crm/contract | কন্ট্রাক্ট তৈরি |
| GET | /admin/crm/contract/{id} | কন্ট্রাক্ট ডিটেইল |
| PUT | /admin/crm/contract/{id} | কন্ট্রাক্ট আপডেট |
| DELETE | /admin/crm/contract/{id} | কন্ট্রাক্ট ডিলিট |
| GET | /admin/crm/quotation | CRM কোটেশন লিস্ট |
| POST | /admin/crm/quotation | CRM কোটেশন তৈরি |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 কোটেশন থেকে কন্ট্রাক্ট |
| GET/POST/PUT/DELETE | /admin/crm/campaign | মার্কেটিং ক্যাম্পেইন |
| GET/POST/PUT/DELETE | /admin/crm/ticket | সার্ভিস টিকেট |
| POST | /admin/crm/ticket/{id}/assign | টিকেট অ্যাসাইন |
| POST | /admin/crm/ticket/{id}/resolve | টিকেট সমাধান |
| GET/POST | /admin/crm/analytics/report | কাস্টমার অ্যানালিটিক্স রিপোর্ট |
| GET/POST | /admin/crm/analytics/metric | অ্যানালিটিক্স মেট্রিক |

### 16.7 অ্যাপ্রুভাল ওয়ার্কফ্লো

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/workflow | ওয়ার্কফ্লো ডেফিনিশন লিস্ট |
| POST | /admin/workflow | ওয়ার্কফ্লো ডেফিনিশন তৈরি |
| GET | /admin/workflow/{id} | ওয়ার্কফ্লো ডিটেইল |
| PUT | /admin/workflow/{id} | ওয়ার্কফ্লো আপডেট |
| DELETE | /admin/workflow/{id} | ওয়ার্কফ্লো ডিলিট |
| POST | /admin/workflow/{id}/submit | 🔗 অনুমোদন সাবমিট (অ্যাপ্রুভাল ইন্সট্যান্স তৈরি) |
| POST | /admin/approval/{id}/approve | অনুমোদন |
| POST | /admin/approval/{id}/reject | প্রত্যাখ্যান |
| POST | /admin/approval/{id}/withdraw | প্রত্যাহার |
| ANY | /admin/approval/my | আমার অনুমোদন লিস্ট (অপেক্ষমাণ/অনুমোদিত) |

### 16.8 নোটিফিকেশন

| মেথড | পাথ | বিবরণ |
|------|------|------|
| ANY | /admin/notification/my | আমার নোটিফিকেশন লিস্ট (পেজিনেশন, সময়ের উল্টো ক্রমে) |
| POST | /admin/notification/{id}/read | একটি পঠিত চিহ্নিত |
| POST | /admin/notification/read-all | সব পঠিত চিহ্নিত |
| ANY | /admin/notification/unread-count | অপঠিত মেসেজ সংখ্যা |

### 16.9 প্রজেক্ট ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/project | প্রজেক্ট লিস্ট |
| POST | /admin/project | প্রজেক্ট তৈরি |
| GET | /admin/project/{id} | প্রজেক্ট ডিটেইল |
| PUT | /admin/project/{id} | প্রজেক্ট আপডেট |
| DELETE | /admin/project/{id} | প্রজেক্ট ডিলিট |
| GET | /admin/project/task | টাস্ক লিস্ট |
| POST | /admin/project/task | টাস্ক তৈরি |
| PUT | /admin/project/task/{id} | টাস্ক আপডেট |
| DELETE | /admin/project/task/{id} | টাস্ক ডিলিট |
| GET | /admin/project/timesheet | টাইমশিট লিস্ট |
| POST | /admin/project/timesheet | টাইমশিট এন্ট্রি |
| PUT | /admin/project/timesheet/{id} | টাইমশিট আপডেট |
| DELETE | /admin/project/timesheet/{id} | টাইমশিট ডিলিট |

### 16.10 হিউম্যান রিসোর্স ম্যানেজমেন্ট (HR)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/hr/department | ডিপার্টমেন্ট লিস্ট (ট্রি) |
| POST | /admin/hr/department | ডিপার্টমেন্ট তৈরি |
| PUT | /admin/hr/department/{id} | ডিপার্টমেন্ট আপডেট |
| DELETE | /admin/hr/department/{id} | ডিপার্টমেন্ট ডিলিট |
| GET | /admin/hr/employee | এমপ্লয়ি লিস্ট |
| POST | /admin/hr/employee | এমপ্লয়ি তৈরি |
| PUT | /admin/hr/employee/{id} | এমপ্লয়ি আপডেট |
| DELETE | /admin/hr/employee/{id} | এমপ্লয়ি ডিলিট |
| GET | /admin/hr/position | পজিশন লিস্ট |
| POST | /admin/hr/position | পজিশন তৈরি |
| PUT | /admin/hr/position/{id} | পজিশন আপডেট |
| DELETE | /admin/hr/position/{id} | পজিশন ডিলিট |
| ANY | /admin/hr/attendance | অ্যাটেনডেন্স রেকর্ড কোয়েরি |
| POST | /admin/hr/attendance/clock-in | কাজ শুরু ক্লক-ইন |
| POST | /admin/hr/attendance/clock-out | কাজ শেষ ক্লক-আউট |
| ANY | /admin/hr/leave | ছুটির লিস্ট |
| POST | /admin/hr/leave | ছুটির আবেদন সাবমিট |
| GET | /admin/hr/leave/{id} | ছুটির ডিটেইল |
| PUT | /admin/hr/leave/{id} | ছুটি আপডেট |
| DELETE | /admin/hr/leave/{id} | ছুটি ডিলিট |
| POST | /admin/hr/leave/{id}/approve | 🔗 ছুটি অনুমোদন |
| GET | /admin/hr/salary | বেতন লিস্ট |
| POST | /admin/hr/salary | বেতন স্লিপ তৈরি |
| PUT | /admin/hr/salary/{id} | বেতন আপডেট |
| DELETE | /admin/hr/salary/{id} | বেতন ডিলিট |
| POST | /admin/hr/salary/{id}/pay | বেতন প্রদান |
| ANY | /admin/hr/salary-item | বেতন আইটেম লিস্ট |
| POST | /admin/hr/salary-item | বেতন আইটেম তৈরি |
| GET | /admin/hr/salary-item/{id} | বেতন আইটেম ডিটেইল |
| PUT | /admin/hr/salary-item/{id} | বেতন আইটেম আপডেট |
| DELETE | /admin/hr/salary-item/{id} | বেতন আইটেম ডিলিট |

### 16.11 ম্যানুফ্যাকচারিং

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/mfg/bom | BOM লিস্ট |
| POST | /admin/mfg/bom | BOM তৈরি |
| PUT | /admin/mfg/bom/{id} | BOM আপডেট |
| DELETE | /admin/mfg/bom/{id} | BOM ডিলিট |
| GET | /admin/mfg/production | প্রোডাকশন অর্ডার লিস্ট |
| POST | /admin/mfg/production | প্রোডাকশন অর্ডার তৈরি |
| PUT | /admin/mfg/production/{id} | প্রোডাকশন অর্ডার আপডেট |
| DELETE | /admin/mfg/production/{id} | প্রোডাকশন অর্ডার ডিলিট |
| POST | /admin/mfg/production/{id}/start | কাজ শুরু |
| POST | /admin/mfg/production/{id}/complete | কাজ সম্পন্ন |
| GET | /admin/mfg/routing | রাউটিং লিস্ট |
| POST | /admin/mfg/routing | রাউটিং তৈরি |
| PUT | /admin/mfg/routing/{id} | রাউটিং আপডেট |
| DELETE | /admin/mfg/routing/{id} | রাউটিং ডিলিট |
| GET | /admin/mfg/workstation | ওয়ার্কস্টেশন লিস্ট |
| POST | /admin/mfg/workstation | ওয়ার্কস্টেশন তৈরি |
| PUT | /admin/mfg/workstation/{id} | ওয়ার্কস্টেশন আপডেট |
| DELETE | /admin/mfg/workstation/{id} | ওয়ার্কস্টেশন ডিলিট |
| GET | /admin/mfg/mrp | MRP প্ল্যান লিস্ট |
| POST | /admin/mfg/mrp | MRP প্ল্যান তৈরি |
| PUT | /admin/mfg/mrp/{id} | MRP প্ল্যান আপডেট |
| DELETE | /admin/mfg/mrp/{id} | MRP প্ল্যান ডিলিট |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 MRP চালিয়ে ক্রয়/উৎপাদন পরামর্শ তৈরি |

### 16.12 কাস্টম রিপোর্ট (রিপোর্ট বিল্ডার)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/report | রিপোর্ট টেমপ্লেট লিস্ট |
| POST | /admin/report | রিপোর্ট টেমপ্লেট তৈরি |
| GET | /admin/report/{id} | রিপোর্ট টেমপ্লেট ডিটেইল |
| PUT | /admin/report/{id} | রিপোর্ট টেমপ্লেট আপডেট |
| DELETE | /admin/report/{id} | রিপোর্ট টেমপ্লেট ডিলিট |
| POST | /admin/report/{id}/execute | রিপোর্ট এক্সিকিউট করে ডেটা তৈরি |
| ANY | /admin/report/{id}/result | রিপোর্ট এক্সিকিউশন ফলাফল |
| GET | /admin/report/schedule | শিডিউল লিস্ট |
| POST | /admin/report/schedule | শিডিউল তৈরি |
| PUT | /admin/report/schedule/{id} | শিডিউল আপডেট |
| DELETE | /admin/report/schedule/{id} | শিডিউল ডিলিট |

### 16.13 ড্যাশবোর্ড

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/dashboard/sales | সেলস প্যানেল |
| GET | /admin/dashboard/inventory | ইনভেন্টরি প্যানেল |
| GET | /admin/dashboard/finance | ফাইন্যান্স প্যানেল |

### 16.14 ক্লায়েন্ট API

ক্লায়েন্ট ইন্টারফেস `/api` গ্রুপে মাউন্ট করা, `API-Version` রিকোয়েস্ট হেডার প্রয়োজন। পণ্য তথ্যে ক্রয়মূল্য থাকে না।

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /api/product | পণ্য লিস্ট (ক্রয়মূল্য ছাড়া) |
| GET | /api/product/{hashid} | পণ্য ডিটেইল (রিটেইল/হোলসেল প্রাইস সহ, ক্রয়মূল্য ছাড়া) |

### 16.15 OMS অর্ডার ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/oms/order | OMS অর্ডার লিস্ট |
| POST | /admin/oms/order | OMS অর্ডার তৈরি |
| 🔗 POST | /admin/oms/order/{id}/allocate | ইনভেন্টরি অ্যালোকেশন (রিজার্ভেশন) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | ফুলফিলমেন্ট তৈরি |
| POST | /admin/oms/order/{id}/cancel | অর্ডার ক্যান্সেল (রিজার্ভেশন রিলিজ) |
| POST | /admin/oms/rma/{id}/approve | RMA অনুমোদন |
| POST | /admin/oms/rma/{id}/refund | RMA রিফান্ড |

### 16.16 WMS ওয়্যারহাউস ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/wms/zone | জোন লিস্ট (CRUD) |
| GET | /admin/wms/location | WMS লোকেশন লিস্ট (CRUD) |
| GET | /admin/wms/asn | ASN লিস্ট (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | রিসিভিং সম্পন্ন → স্বয়ংক্রিয় পুটওয়ে টাস্ক তৈরি |
| POST | /admin/wms/putaway/{id}/complete | পুটওয়ে নিশ্চিত → stockIn ট্রিগার |
| POST | /admin/wms/wave/{id}/release | ওয়েভ রিলিজ → পিকিং টাস্ক তৈরি |
| POST | /admin/wms/pick/{id}/start | পিকিং শুরু |
| POST | /admin/wms/pick/{id}/confirm | পিকিং নিশ্চিতকরণ |
| POST | /admin/wms/pack/{id}/complete | প্যাকিং সম্পন্ন |

### 16.17 TMS ট্রান্সপোর্ট ম্যানেজমেন্ট

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/tms/carrier | ক্যারিয়ার লিস্ট (CRUD) |
| GET | /admin/tms/service | ক্যারিয়ার সার্ভিস (CRUD) |
| GET | /admin/tms/freight-rate | ফ্রেট রেট (CRUD) |
| GET | /admin/tms/shipment | শিপমেন্ট লিস্ট (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | ডেলিভারি নিশ্চিতকরণ (stockOut+AR) |
| POST | /admin/tms/tracking/callback | ক্যারিয়ার ট্র্যাকিং webhook |
| POST | /admin/tms/freight-invoice/{id}/pay | ফ্রেট ইনভয়েস পেমেন্ট (AP তৈরি) |

### 16.18 ড্যাশবোর্ড এক্সটেনশন

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/dashboard/oms | OMS KPI (অপেক্ষমাণ/পিকিং চলছে/আজকের ডেলিভারি/RMA) |
| GET | /admin/dashboard/wms | WMS KPI (রিসিভ অপেক্ষা/পুটওয়ে অপেক্ষা/পিক অপেক্ষা/প্যাক অপেক্ষা) |
| GET | /admin/dashboard/tms | TMS KPI (ডেলিভারি অপেক্ষা/পরিবহনে/স্বাক্ষরিত/অস্বাভাবিক) |

### 16.19 ক্রস-মডিউল লিংকড এন্ডপয়েন্ট বিবরণ

নিম্নলিখিত এন্ডপয়েন্ট ক্রস-মডিউল স্বয়ংক্রিয় লিংক ট্রিগার করে, 🔗 দিয়ে চিহ্নিত:

| এন্ডপয়েন্ট | লিংক অ্যাকশন |
|------|---------|
| 🔗 POST /admin/purchase/receive | স্বয়ংক্রিয়ভাবে InventoryService.stockIn() কল করে ইনভেন্টরি আপডেট + মুভিং ওয়েটেড এভারেজ খরচ পুনরায় গণনা; FinanceService.createAp() কল করে AP রেকর্ড তৈরি |
| 🔗 POST /admin/sales/delivery | স্বয়ংক্রিয়ভাবে InventoryService.stockOut() কল করে ইনভেন্টরি কমানো (মুভিং ওয়েটেড এভারেজ খরচ অনুযায়ী); FinanceService.createAr() কল করে AR রেকর্ড তৈরি |
