# उप-प्रोजेक्ट A: बैकएंड संवर्द्धन — डिज़ाइन विनिर्देश

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## दायरा

यह बैकएंड संवर्द्धन है, कुल 15 फ़ीचर बिंदु, जिसमें 9 नई फ़ाइलें + 4 संशोधित फ़ाइलें शामिल हैं।

---

## नई/संशोधित फ़ाइल सूची

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

## 1. मिडलवेयर

### 1.1 CORS मिडलवेयर

**फ़ाइल**: `app/middleware/Cors.php`

- OPTIONS प्रीफ़्लाइट अनुरोध सीधे 204 लौटाता है
- गैर-प्रीफ़्लाइट अनुरोध के प्रतिक्रिया हेडर में `Access-Control-Allow-Origin: *` जोड़ता है
- अनुमत हेडर: `Authorization, Content-Type, API-Version`
- अधिकतम कैश: 86400 सेकंड

माउंटिंग: वैश्विक मिडलवेयर (`config/middleware.php`)

### 1.2 रेट लिमिट मिडलवेयर

**फ़ाइल**: `app/middleware/RateLimit.php`

- स्टोरेज: Redis Sorted Set स्लाइडिंग विंडो
- डिफ़ॉल्ट: 60 बार/मिनट/IP/रूट
- संवेदनशील इंटरफ़ेस:
  - `/api/auth/login`: 10 बार/मिनट
  - `/api/auth/register`: 5 बार/मिनट
- सीमा पार होने पर `429 Too Many Requests` लौटाता है

माउंटिंग: वैश्विक मिडलवेयर (`config/middleware.php`), Cors के बाद, ApiVersion से पहले

### 1.3 ऑपरेशन लॉग मिडलवेयर

**फ़ाइल**: `app/middleware/OperationLog.php`

- केवल POST/PUT/DELETE रिकॉर्ड करता है
- रिकॉर्ड फ़ील्ड: user_id, action, method, path, ip, input(JSON)
- प्रतिक्रिया लौटने के बाद एसिंक्रोनस रूप से लिखता है (ब्लॉक नहीं करता)

माउंटिंग: `/admin` रूट समूह, AdminPermission के बाद

### 1.4 वैश्विक मिडलवेयर निष्पादन श्रृंखला

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 लॉगआउट (JWT ब्लैकलिस्ट)

**फ़ाइल**: `app/middleware/AdminAuth.php` (संशोधित)

**सिद्धांत**: JWT स्वयं स्टेटलेस है, लॉगआउट पर token को Redis ब्लैकलिस्ट में जोड़ा जाता है, AdminAuth सत्यापन करते समय पहले ब्लैकलिस्ट जाँचता है।

**AdminAuth परिवर्तन**:
- `process()` की शुरुआत में जोड़ें: Redis `jwt_blacklist` सेट से जाँचें कि वर्तमान token ब्लैकलिस्ट में है या नहीं
- ब्लैकलिस्ट हिट होने पर 401 लौटाएँ

**लॉगआउट रूट** (पर्सनल सेंटर के अंतर्गत):

| विधि | रूट | विवरण |
|------|------|------|
| `POST` | `/admin/profile/logout` | वर्तमान Bearer token को Redis ब्लैकलिस्ट में जोड़ें, TTL=token की शेष वैधता |

**Logout लॉजिक**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. नए कंट्रोलर और मौजूदा परिवर्तन

### 2.1 सिस्टम कॉन्फ़िगरेशन CRUD (`ConfigController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/config` | पेजिनेटेड सूची, `group` से फ़िल्टर किया जा सकता है, `page`/`limit` पेजिनेशन |
| `store()` | POST `/admin/config` | कॉन्फ़िगरेशन आइटम बनाएँ, अनिवार्य: group, key, value |
| `update()` | PUT `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम value/type/description अपडेट करें |
| `destroy()` | DELETE `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम हटाएँ, `confirmPassword()` आवश्यक |

### 2.2 ऑपरेशन लॉग क्वेरी (`LogController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/log` | पेजिनेटेड सूची, फ़िल्टर समर्थित: user_id, action, path, created_at (दायरा) |

जोड़/संपादन/हटाना उपलब्ध नहीं, लॉग मिडलवेयर द्वारा स्वतः रिकॉर्ड होते हैं।

### 2.3 पर्सनल सेंटर (`ProfileController`)

`BaseController` से विरासत।वर्तमान लॉगिन उपयोगकर्ता पर कार्य करता है (`$request->adminId`)।

| विधि | रूट | विवरण |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email अपडेट करें |
| `updatePassword()` | PUT `/admin/profile/password` | पासवर्ड बदलें, old_password, new_password, new_password_confirmation आवश्यक |

### 2.4 फ़ाइल अपलोड (`UploadController`)

`BaseController` से विरासत।

| विधि | रूट | विवरण |
|------|------|------|
| `upload()` | POST `/admin/upload` | फ़ाइल प्राप्त करें, image/jpeg/png/gif/pdf/xlsx/docx समर्थित |

- अधिकतम 10MB
- स्टोरेज पथ: `public/upload/{date}/{hash}.{ext}`
- रिटर्न: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 डैशबोर्ड वास्तविक डेटा

**फ़ाइल**: `app/admin/controller/DashboardController.php` (संशोधित)

वर्तमान हार्डकोडेड नकली डेटा को डेटाबेस रीयल-टाइम आँकड़ों में बदलें:

| मेट्रिक | स्रोत | विवरण |
|------|------|------|
| कुल उपयोगकर्ता | `AdminUser::count()` | सॉफ्ट-डिलीटेड को छोड़कर |
| आज के नए | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| कुल भूमिकाएँ | `AdminRole::count()` | |
| कुल अनुमतियाँ | `AdminPermission::count()` | |
| रुझान डेटा | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | पिछले 7 दिनों के नए दैनिक आँकड़े |
| वितरण डेटा | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | स्थिति के अनुसार वितरण |
| हाल के ऑपरेशन | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | हाल की 10 ऑपरेशन लॉग |

### 2.6 उपयोगकर्ता बैच ऑपरेशन

**फ़ाइल**: `app/admin/controller/UserController.php` (संशोधित, नई विधियाँ)

| विधि | रूट | विवरण |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | बैच डिलीट, अनुरोध बॉडी `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | बैच सक्षम/अक्षम, अनुरोध बॉडी `{ ids: [hashid, ...], status: 1|0 }` |

- प्रत्येक id को पहले `decodeId()` से BIGINT में बदलें
- `batchDestroy()` को `confirmPassword()` सत्यापन पास करना होगा

### 2.7 डेटा आयात

**फ़ाइल**: `app/admin/controller/ImportController.php` (नया)

| विधि | रूट | विवरण |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel फ़ाइल अपलोड करें, बैच में उपयोगकर्ता बनाएँ |

प्रवाह:
1. `.xlsx` फ़ाइल प्राप्त करें
2. PhpSpreadsheet पार्स करें, अपेक्षित कॉलम: `username, password, real_name, phone, email, status`
3. पंक्ति-दर-पंक्ति सत्यापन + निर्माण (snowflake से ID, bcrypt पासवर्ड, encryption से phone/email एन्क्रिप्ट)
4. परिणाम लौटाएँ: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 स्वास्थ्य जाँच

**फ़ाइल**: `app/admin/controller/HealthController.php` (नया)

`GET /health` (प्रमाणीकरण आवश्यक नहीं, ऑपरेशन लॉग में नहीं गिना जाता):

प्रत्येक घटक की कनेक्शन स्थिति लौटाता है:
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

- घटक जाँच विफल होने पर संबंधित फ़ील्ड मान त्रुटि विवरण स्ट्रिंग होता है
- रूट `/admin` उपसर्ग नहीं लगाता, अलग से वैश्विक रूप से पंजीकृत

---

## 3. मॉडल सुधार

### 3.1 OperationLog टाइमस्टैम्प

**फ़ाइल**: `app/model/OperationLog.php` (संशोधित)

तालिका `erp_operation_log` में केवल `created_at` कॉलम है (`updated_at` नहीं)। Eloquent का डिफ़ॉल्ट `save()` `updated_at` लिखने का प्रयास करता है, जिससे SQL त्रुटि होती है।

सुधार: `public $timestamps = false;` + लिखते समय मैन्युअल रूप से `created_at` निर्दिष्ट करें।

### 3.2 AdminUser मॉडल परिवर्तन

- `Searchable` trait जोड़ें
- `toSearchableArray()` लागू करें: username, real_name लौटाएँ
- `UserController::index()` कीवर्ड पहचानने पर MySQL LIKE के बजाय `AdminUser::search($kw)->get()` उपयोग करें

ES के लिए पहले इंडेक्स बनाना होगा, Scout कमांड से:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. रूट परिवर्तन

`config/route.php` में नए रूट:

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

`config/middleware.php` वैश्विक मिडलवेयर पंजीकृत करता है:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. त्रुटि कोड परिशिष्ट

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 429 | अनुरोध बहुत अधिक बार | RateLimit ट्रिगर |

---

## 6. इस दायरे में शामिल नहीं

- नोटिफिकेशन सिस्टम (मैसेज कतार + फ्रंटएंड पुश इन्फ्रास्ट्रक्चर आवश्यक)
- Flutter फ्रंटएंड पेज (उप-प्रोजेक्ट B)
- HarmonyOS Token रीफ्रेश (उप-प्रोजेक्ट C)
