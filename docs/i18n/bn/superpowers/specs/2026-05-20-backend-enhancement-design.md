# উপ-প্রজেক্ট A: ব্যাকএন্ড এনহ্যান্সমেন্ট — ডিজাইন স্পেক

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## সুযোগ

এটি ব্যাকএন্ড এনহ্যান্সমেন্ট, মোট 15টি ফিচার পয়েন্ট, 9টি নতুন ফাইল + 4টি পরিবর্তিত ফাইল জড়িত।

---

## নতুন/পরিবর্তিত ফাইল তালিকা

```
app/middleware/
├── OperationLog.php          # 新增：操作日志自动记录
├── Cors.php                  # 新增：跨域
└── RateLimit.php             # 新增：Redis 限流
app/admin/controller/
├── ConfigController.php      # 新增：系统配置 CRUD
├── LogController.php         # 新增：操作日志查询
├── ProfileController.php     # 新增：个人中心（含登出）
├── UploadController.php      # 新增：文件上传
├── ImportController.php      # 新增：Excel 导入用户
└── HealthController.php      # 新增：健康检查
app/model/
├── AdminUser.php             # 修改：加 SoftDeletes + Searchable trait
└── OperationLog.php          # 修改：加 public $timestamps = false
app/middleware/
└── AdminAuth.php             # 修改：JWT 黑名单校验
app/admin/controller/
├── DashboardController.php   # 修改：改为数据库实时统计
└── UserController.php        # 修改：新增批处理动作
config/
└── route.php                 # 修改：新增路由 + 中间件
```

---

## 1. মিডলওয়্যার

### 1.1 CORS মিডলওয়্যার

**ফাইল**: `app/middleware/Cors.php`

- OPTIONS প্রি-ফ্লাইট রিকোয়েস্ট সরাসরি 204 ফেরত দেয়
- অ-প্রি-ফ্লাইট রিকোয়েস্টে রেসপন্স হেডারে `Access-Control-Allow-Origin: *` যোগ হয়
- অনুমোদিত হেডার: `Authorization, Content-Type, API-Version`
- সর্বোচ্চ ক্যাশ: 86400 সেকেন্ড

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`)

### 1.2 রেট লিমিট মিডলওয়্যার

**ফাইল**: `app/middleware/RateLimit.php`

- স্টোরেজ: Redis Sorted Set স্লাইডিং উইন্ডো
- ডিফল্ট: 60 বার/মিনিট/IP/রাউট
- সংবেদনশীল এন্ডপয়েন্ট:
  - `/api/auth/login`: 10 বার/মিনিট
  - `/api/auth/register`: 5 বার/মিনিট
- সীমা ছাড়ালে `429 Too Many Requests` ফেরত দেয়

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`), Cors-এর পরে, ApiVersion-এর আগে

### 1.3 অপারেশন লগ মিডলওয়্যার

**ফাইল**: `app/middleware/OperationLog.php`

- শুধুমাত্র POST/PUT/DELETE রেকর্ড হয়
- রেকর্ড করা ফিল্ড: user_id, action, method, path, ip, input(JSON)
- রেসপন্স ফেরত দেওয়ার পর অ্যাসিঙ্কভাবে লেখা হয় (ব্লক করে না)

মাউন্ট: `/admin` রাউট গ্রুপ, AdminPermission-এর পরে

### 1.4 গ্লোবাল মিডলওয়্যার এক্সিকিউশন চেইন

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 লগআউট (JWT ব্ল্যাকলিস্ট)

**ফাইল**: `app/middleware/AdminAuth.php` (পরিবর্তিত)

**নীতি**: JWT নিজে স্টেটলেস, লগআউটের সময় token Redis ব্ল্যাকলিস্টে যোগ হয়, AdminAuth যাচাইয়ের সময় আগে ব্ল্যাকলিস্ট চেক করে।

**AdminAuth পরিবর্তন**:
- `process()`-এর শুরুতে যোগ: Redis `jwt_blacklist` সেট থেকে বর্তমান token ব্ল্যাকলিস্টে আছে কিনা চেক করুন
- ব্ল্যাকলিস্টে থাকলে 401 ফেরত দেয়

**লগআউট রাউট** (পার্সোনাল সেন্টারের অধীনে):

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `POST` | `/admin/profile/logout` | বর্তমান Bearer token Redis ব্ল্যাকলিস্টে যোগ করে, TTL=token-এর বাকি মেয়াদ |

**Logout লজিক**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. নতুন কন্ট্রোলার ও বিদ্যমান পরিবর্তন

### 2.1 সিস্টেম কনফিগ CRUD (`ConfigController`)

`BaseController` থেকে উত্তরাধিকার নেয়।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `index()` | GET `/admin/config` | পেজিনেটেড তালিকা, `group` দিয়ে ফিল্টার, `page`/`limit` পেজিনেশন |
| `store()` | POST `/admin/config` | কনফিগ আইটেম তৈরি, আবশ্যক: group, key, value |
| `update()` | PUT `/admin/config/{id}` | কনফিগ আইটেম value/type/description আপডেট |
| `destroy()` | DELETE `/admin/config/{id}` | কনফিগ আইটেম মুছে ফেলে, `confirmPassword()` প্রয়োজন |

### 2.2 অপারেশন লগ কোয়েরি (`LogController`)

`BaseController` থেকে উত্তরাধিকার নেয়।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `index()` | GET `/admin/log` | পেজিনেটেড তালিকা, ফিল্টার সাপোর্ট: user_id, action, path, created_at (রেঞ্জ) |

সৃষ্টি/পরিবর্তন/মুছে ফেলা নেই, লগ মিডলওয়্যার স্বয়ংক্রিয়ভাবে রেকর্ড করে।

### 2.3 পার্সোনাল সেন্টার (`ProfileController`)

`BaseController` থেকে উত্তরাধিকার নেয়। বর্তমান লগইন করা ব্যবহারকারীর উপর কাজ করে (`$request->adminId`)।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email আপডেট |
| `updatePassword()` | PUT `/admin/profile/password` | পাসওয়ার্ড পরিবর্তন, দরকার old_password, new_password, new_password_confirmation |

### 2.4 ফাইল আপলোড (`UploadController`)

`BaseController` থেকে উত্তরাধিকার নেয়।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `upload()` | POST `/admin/upload` | ফাইল গ্রহণ, সাপোর্ট image/jpeg/png/gif/pdf/xlsx/docx |

- সর্বোচ্চ 10MB
- স্টোরেজ পাথ: `public/upload/{date}/{hash}.{ext}`
- রিটার্ন: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 ড্যাশবোর্ড রিয়েল ডেটা

**ফাইল**: `app/admin/controller/DashboardController.php` (পরিবর্তিত)

বর্তমান হার্ডকোডেড ফেক ডেটা ডাটাবেস রিয়েল-টাইম পরিসংখ্যানে পরিবর্তন করুন:

| মেট্রিক | উৎস | ব্যাখ্যা |
|------|------|------|
| মোট ব্যবহারকারী | `AdminUser::count()` | সফট ডিলিট বাদে |
| আজকের নতুন | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| মোট ভূমিকা | `AdminRole::count()` | |
| মোট পারমিশন | `AdminPermission::count()` | |
| প্রবণতা ডেটা | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | দিন অনুযায়ী সাম্প্রতিক 7 দিনের নতুন |
| বন্টন ডেটা | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | স্ট্যাটাস অনুযায়ী বন্টন |
| সাম্প্রতিক অপারেশন | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | সাম্প্রতিক 10টি অপারেশন লগ |

### 2.6 ইউজার ব্যাচ অপারেশন

**ফাইল**: `app/admin/controller/UserController.php` (পরিবর্তিত, নতুন মেথড)

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | ব্যাচ ডিলিট, রিকোয়েস্ট বডি `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | ব্যাচ সক্রিয়/নিষ্ক্রিয়, রিকোয়েস্ট বডি `{ ids: [hashid, ...], status: 1|0 }` |

- প্রতিটি id আগে `decodeId()` দিয়ে BIGINT-এ রূপান্তর হয়
- `batchDestroy()` অবশ্যই `confirmPassword()` দিয়ে যাচাই করতে হবে

### 2.7 ডেটা ইমপোর্ট

**ফাইল**: `app/admin/controller/ImportController.php` (নতুন)

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel ফাইল আপলোড, ব্যাচে ইউজার তৈরি |

ফ্লো:
1. `.xlsx` ফাইল গ্রহণ
2. PhpSpreadsheet পার্স, প্রত্যাশিত কলাম: `username, password, real_name, phone, email, status`
3. ধাপে ধাপে যাচাই + তৈরি (snowflake দিয়ে ID, bcrypt পাসওয়ার্ড, encryption দিয়ে phone/email এনক্রিপ্ট)
4. ফলাফল রিটার্ন: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 হেলথ চেক

**ফাইল**: `app/admin/controller/HealthController.php` (নতুন)

`GET /health` (অথেনটিকেশন লাগবে না, অপারেশন লগে গণনা হয় না):

প্রতিটি কম্পোনেন্টের সংযোগ অবস্থা ফেরত দেয়:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- কম্পোনেন্ট ডিটেক্ট ব্যর্থ হলে সংশ্লিষ্ট ফিল্ডের মান হবে এরর বর্ণনা স্ট্রিং
- রাউট `/admin` প্রিফিক্স ছাড়া, আলাদাভাবে গ্লোবালে রেজিস্টার হয়

---

## 3. মডেল সংশোধন

### 3.1 OperationLog টাইমস্ট্যাম্প

**ফাইল**: `app/model/OperationLog.php` (পরিবর্তিত)

টেবিল `erp_operation_log`-এ শুধু `created_at` কলাম আছে (`updated_at` নেই)। Eloquent-এর ডিফল্ট `save()` `updated_at` লেখার চেষ্টা করে, ফলে SQL এরর হয়।

ফিক্স: `public $timestamps = false;` + লেখার সময় ম্যানুয়ালি `created_at` নির্ধারণ।

### 3.2 AdminUser মডেল পরিবর্তন

- `Searchable` trait যোগ করুন
- `toSearchableArray()` বাস্তবায়ন: username, real_name রিটার্ন
- `UserController::index()`-এ কীওয়ার্ড পেলে MySQL LIKE-এর বদলে `AdminUser::search($kw)->get()` ব্যবহার হয়

ES-এ আগে ইন্ডেক্স তৈরি করতে হবে, Scout কমান্ড দিয়ে:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. রাউট পরিবর্তন

`config/route.php`-এ নতুন রাউট:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

`config/middleware.php`-এ গ্লোবাল মিডলওয়্যার রেজিস্টার:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. এরর কোড সংযোজন

| code | অর্থ | ট্রিগার দৃশ্য |
|------|------|---------|
| 429 | খুব বেশি রিকোয়েস্ট | RateLimit ট্রিগার |

---

## 6. এই সুযোগের বাইরে

- নোটিফিকেশন সিস্টেম (মেসেজ কিউ + ফ্রন্টএন্ড পুশ ইনফ্রাস্ট্রাকচার প্রয়োজন)
- Flutter ফ্রন্টএন্ড পেজ (উপ-প্রজেক্ট B)
- HarmonyOS Token রিফ্রেশ (উপ-প্রজেক্ট C)
