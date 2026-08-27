# API संदर्भ दस्तावेज़

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API दस्तावेज़

परियोजना [hg/apidoc](https://github.com/hg-code/apidoc) से इंटरैक्टिव API दस्तावेज़ स्वतः उत्पन्न करती है।

**पहुंच विधि:** सेवा प्रारंभ करने के बाद `http://localhost:8787/apidoc` पर जाएँ

**दस्तावेज़ समूह:**
| समूह | विवरण | मॉड्यूल संख्या |
|------|------|--------|
| प्रशासन एंड इंटरफ़ेस (Admin) | बैकएंड प्रबंधन प्रणाली के सभी इंटरफ़ेस | 25 मॉड्यूल |
| क्लाइंट इंटरफ़ेस (Service API) | मोबाइल/वेब एंड द्वारा कॉल किए जाने वाले हल्के इंटरफ़ेस | 3 मॉड्यूल |

**वैश्विक अनुरोध हेडर:**
| अनुरोध हेडर | विवरण |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | API संस्करण संख्या (v1) |
| `Accept-Language` | अंतर्राष्ट्रीयकरण भाषा (zh-CN/en) |

**एनोटेशन मानदंड:** सभी कंट्रोलर विधियाँ इंटरफ़ेस नाम, विवरण, URL, अनुरोध विधि, पैरामीटर और रिटर्न मान संरचना को चिह्नित करने के लिए `@Apidoc\*` श्रृंखला एनोटेशन का उपयोग करती हैं।

## 1. अवलोकन

ओपन एडमिन बैकएंड (open-admin) webman v2 पर आधारित है, RESTful JSON API प्रदान करता है। सभी प्रशासन एंड इंटरफ़ेसों को JWT प्रमाणीकरण और RBAC अनुमति सत्यापन की आवश्यकता होती है, सार्वजनिक इंटरफ़ेस API संस्करण हेडर के माध्यम से संस्करणित कंट्रोलर में रूट होते हैं।

- **आधार URL**: `http://localhost:8787`
- **API संस्करण**: अनुरोध हेडर `API-Version: v1` से नियंत्रित (अनुपस्थित होने पर डिफ़ॉल्ट v1)

> **एंडपॉइंट अवलोकन**: प्रमाणीकरण (5) | डैशबोर्ड (1) | उपयोगकर्ता (7) | भूमिका (4) | अनुमति (4) | कॉन्फ़िगरेशन (4) | लॉग (1) | व्यक्तिगत केंद्र (3) | आयात-निर्यात (3) | अपलोड (1) | संचालन (4: health/metrics/docs/security.txt) | कुल 37 एंडपॉइंट
- **प्रमाणीकरण**: `Authorization: Bearer <token>` (JWT)
- **प्रतिक्रिया प्रारूप**: `{ "code": 0, "message": "success", "data": {...} }`
- **दस्तावेज़ एंडपॉइंट**: `GET /api/docs` OpenAPI 3.0 JSON विनिर्देश लौटाता है

### अंतर्राष्ट्रीयकरण

API अनुरोध हेडर `Accept-Language` से भाषा स्वतः स्विच करता है:

| अनुरोध हेडर मान | भाषा |
|---------|------|
| `zh-CN`, `zh` | चीनी (डिफ़ॉल्ट) |
| `en`, `en-US` | English |

```bash
# 英文响应
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# 中文响应（默认）
curl http://localhost:8787/admin/product
```

प्रतिक्रिया का `message` फ़ील्ड संबंधित भाषा में लौटाया जाएगा।

### अनुरोध आवश्यकताएँ

- केवल `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` विधियों की अनुमति, अन्य HTTP विधियों (जैसे TRACE, CONNECT, PATCH) पर 405 लौटता है
- सभी `POST` / `PUT` अनुरोधों में `Content-Type: application/json` होना चाहिए (फ़ाइल अपलोड को छोड़कर), अन्यथा 415 लौटता है
- अनुरोध निकाय का आकार 10MB से अधिक नहीं होना चाहिए, अन्यथा 413 लौटता है
- सुरक्षा फ़िल्टर सभी अनुरोध इनपुटों की XSS, SQL इंजेक्शन, पथ ट्रैवर्सल, कमांड इंजेक्शन स्कैन करता है, हिट पर 403 लौटता है
- लगातार 5 बार लॉगिन विफलता पर खाता लॉक ट्रिगर (15 मिनट), लॉक अवधि में लॉगिन अनुरोध 429 लौटाता है
- एक उपयोगकर्ता के पास अधिकतम 3 वैध टोकन हो सकते हैं, अधिक होने पर सबसे पुराना टोकन स्वतः ब्लैकलिस्ट में जाता है

## 2. त्रुटि कोड

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 0 | सफल | |
| 400 | अनुरोध पैरामीटर त्रुटि | अनुरोध प्रारूप गलत |
| 401 | प्रमाणित नहीं | Token अनुपस्थित / समाप्त / ब्लैकलिस्ट में |
| 403 | अनुमति नहीं / सुरक्षा अवरोधन | RBAC अनुमति अपर्याप्त / SecurityFilter हिट |
| 404 | संसाधन मौजूद नहीं | क्वेरी/अपडेट/डिलीट का लक्ष्य मौजूद नहीं |
| 405 | अनुरोध विधि अनुमत नहीं | केवल GET/POST/PUT/DELETE/OPTIONS/HEAD की अनुमति, गैर-मानक विधि सीधे अस्वीकृत |
| 413 | अनुरोध निकाय बहुत बड़ा | Content-Length 10MB से अधिक |
| 415 | असमर्थित मीडिया प्रकार | POST/PUT अनुरोध का Content-Type JSON नहीं और फ़ाइल अपलोड नहीं |
| 422 | पैरामीटर सत्यापन विफल | आवश्यक फ़ील्ड अनुपस्थित, प्रारूप असंगत, व्यावसायिक सत्यापन पास नहीं |
| 429 | अनुरोध बहुत बार | RateLimit ट्रिगर / खाता लॉक (लगातार 5 बार लॉगिन विफलता पर 15 मिनट लॉक) |
| 500 | सर्वर आंतरिक त्रुटि | |

## 3. सार्वजनिक एंडपॉइंट

सभी सार्वजनिक एंडपॉइंट `/api` समूह के अंतर्गत माउंट होते हैं, `ApiVersion` मिडलवेयर के माध्यम से `API-Version` हेडर के अनुसार संबंधित संस्करणित कंट्रोलर में वितरित होते हैं (जैसे `app\api\v1\controller\AuthController`)।

### 3.1 स्वास्थ्य जांच

```
GET /health
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: नहीं

**प्रतिक्रिया उदाहरण**:
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

`database`, `redis`, `elasticsearch` मान: `"ok"` | `"unavailable"`। `elasticsearch` ES अगम्य होने पर `"unavailable"` लौटाता है, क्लस्टर स्वास्थ्य स्थिति non-green/yellow होने पर वास्तविक status मान लौटाता है (जैसे `"red"`)।

### 3.2 API दस्तावेज़

```
GET /api/docs
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)
- **प्रतिक्रिया**: OpenAPI 3.0.3 JSON विनिर्देश, सभी एंडपॉइंट परिभाषाएँ, पैरामीटर और Schema शामिल

### 3.3 क्लिक कैप्चा उत्पन्न करें

```
POST /api/captcha/generate
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **रेट लिमिट**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "difficulty": "medium"
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| difficulty | string | नहीं | `easy` / `medium` / `hard`, डिफ़ॉल्ट `medium` |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| key | string | कैप्चा पहचानकर्ता, सत्यापन के समय वापस भेजा जाता है |
| image | string | base64 एन्कोडेड PNG चित्र |
| extra.targets[].order | int | क्लिक क्रम |
| extra.targets[].text | string | क्लिक लक्ष्य संकेत पाठ |

### 3.4 क्लिक कैप्चा सत्यापित करें

```
POST /api/captcha/verify
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **रेट लिमिट**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| key | string | हाँ | कैप्चा key, generate से लौटाया गया |
| clicks | array{object} | हाँ | क्लिक निर्देशांक सरणी, प्रत्येक तत्व में `x` (int) और `y` (int) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

सत्यापन विफल होने पर `code` 422 होता है, `message` `"验证失败，请重试"` होता है, `data.valid` `false` होता है।

### 3.5 लॉगिन

```
POST /api/auth/login
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **रेट लिमिट**: 10 बार/मिनट (IP + पथ के अनुसार)

**अनुरोध निकाय**:
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

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम |
| password | string | हाँ | min:6, max:32 | पासवर्ड |
| captcha_key | string | हाँ | | कैप्चा key |
| clicks | array{object} | हाँ | min:2 | क्लिक निर्देशांक सरणी |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| access_token | string | JWT पहुंच टोकन |
| refresh_token | string | JWT रीफ्रेश टोकन |
| expires_in | int | पहुंच टोकन वैधता अवधि (सेकंड), डिफ़ॉल्ट 7200 |
| user.id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| user.username | string | उपयोगकर्ता नाम |
| user.real_name | string | वास्तविक नाम |

**संभावित त्रुटियाँ**:
- 422: पैरामीटर सत्यापन विफल (आवश्यक फ़ील्ड अनुपस्थित, प्रारूप असंगत)
- 422: कैप्चा गलत, कृपया पुनः प्रयास करें
- 401: उपयोगकर्ता नाम या पासवर्ड गलत
- 403: खाता अक्षम किया गया है
- 429: खाता लॉक किया गया है, कृपया 15 मिनट बाद पुनः प्रयास करें (लगातार 5 बार लॉगिन विफलता पर ट्रिगर)

### 3.6 पंजीकरण

```
POST /api/auth/register
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **रेट लिमिट**: 5 बार/मिनट (IP + पथ के अनुसार)
- **स्विच**: डिफ़ॉल्ट बंद (`REGISTRATION_ENABLED=0`), बंद होने पर 403 लौटता है; `.env` में स्पष्ट रूप से खोलना आवश्यक (`REGISTRATION_ENABLED=1`)

**अनुरोध निकाय**:
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

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 | पासवर्ड (bcrypt हैश स्टोरेज) |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| captcha_key | string | हाँ | | कैप्चा key |
| clicks | array{object} | हाँ | min:2 | क्लिक निर्देशांक सरणी |

**प्रतिक्रिया उदाहरण**:
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

पंजीकरण सफल होने के बाद सीधे JWT टोकन लौटता है, उपयोगकर्ता स्थिति डिफ़ॉल्ट सक्षम (status=1)। केवल `REGISTRATION_ENABLED=1` होने पर यह एंडपॉइंट उपलब्ध है।

### 3.7 टोकन रीफ्रेश

```
POST /api/auth/refresh
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **रेट लिमिट**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| refresh_token | string | हाँ | लॉगिन/पंजीकरण के समय प्राप्त refresh_token |

**प्रतिक्रिया उदाहरण**:
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

रीफ्रेश सफल होने पर नया access_token और refresh_token दोनों लौटते हैं, पुराना टोकन स्वतः अमान्य हो जाता है। रीफ्रेश के समय उपयोगकर्ता का अंतिम लॉगिन समय और IP अपडेट होता है।

**संभावित त्रुटियाँ**:
- 422: रीफ्रेश टोकन अनुपस्थित
- 401: रीफ्रेश टोकन अमान्य या समाप्त

### 3.8 Prometheus मॉनिटरिंग मेट्रिक्स

```
GET /metrics
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: नहीं
- **प्रतिक्रिया प्रारूप**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus द्वारा स्क्रैप किए जाने के लिए सार्वजनिक Prometheus मॉनिटरिंग मेट्रिक्स एंडपॉइंट।

**प्रतिक्रिया उदाहरण**:
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

| मेट्रिक नाम | प्रकार | विवरण |
|------|------|------|
| `openadmin_http_requests_total` | gauge | संचयी HTTP अनुरोध कुल संख्या |
| `openadmin_active_users` | gauge | वर्तमान सक्रिय उपयोगकर्ता संख्या (24 घंटे के भीतर लॉगिन) |
| `openadmin_db_connection_status` | gauge | डेटाबेस कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_redis_connection_status` | gauge | Redis कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_memory_usage_bytes` | gauge | PHP प्रक्रिया की वर्तमान मेमोरी उपयोग (bytes) |

## 4. डैशबोर्ड

सभी प्रशासन एंड इंटरफ़ेस `/admin` समूह के अंतर्गत माउंट होते हैं, तीन मिडलवेयर से गुजरते हैं: `AdminAuth` (JWT प्रमाणीकरण), `AdminPermission` (RBAC अनुमति सत्यापन), `OperationLog` (ऑपरेशन रिकॉर्ड)।

### 4.1 डैशबोर्ड डेटा

```
GET /admin/dashboard
```

- **प्रमाणीकरण**: JWT + RBAC
- **कैश**: Redis 5 मिनट

**प्रतिक्रिया उदाहरण**:
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

| stats फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| label | string | मेट्रिक नाम |
| value | string | मेट्रिक मान (स्ट्रिंग प्रकार) |
| icon | string | Material आइकन नाम |
| color | string | कार्ड रंग मान |
| trend | float? | दैनिक चक्रवृद्धि वृद्धि दर (प्रतिशत), केवल "कुल उपयोगकर्ता" में यह फ़ील्ड होता है |

| trends फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| dates | array{string} | हाल के 30 दिनों की तिथि अनुक्रम |
| series | array{object} | प्रवृत्ति रेखा डेटा, प्रत्येक में name (नाम), data (मान सरणी), color (रंग) |

## 5. उपयोगकर्ता प्रबंधन

सभी उपयोगकर्ता प्रबंधन इंटरफ़ेसों से लौटाया गया `id` hashid एन्क्रिप्टेड स्ट्रिंग है। पासवर्ड फ़ील्ड प्रतिक्रिया से बाहर रखा गया है। मोबाइल नंबर और ईमेल सूची इंटरफ़ेस में मास्किंग करके प्रदर्शित होते हैं, विवरण इंटरफ़ेस में प्लेनटेक्स्ट लौटता है (डेटाबेस एन्क्रिप्टेड फ़ील्ड Encryptable trait द्वारा स्वतः डिक्रिप्ट होते हैं)।

### 5.1 उपयोगकर्ता सूची

```
GET /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| keyword | string | नहीं | | खोज कीवर्ड, उपयोगकर्ता नाम और वास्तविक नाम से मेल खाता है |
| status | int | नहीं | | स्थिति फ़िल्टर, 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| username | string | उपयोगकर्ता नाम |
| real_name | string | वास्तविक नाम |
| phone | string | मास्क किया गया मोबाइल नंबर (`138****5678` प्रारूप) |
| email | string | मास्क किया गया ईमेल (`a***@example.com` प्रारूप) |
| status | int | 1=सक्षम, 0=अक्षम |
| last_login_at | string | अंतिम लॉगिन समय (datetime) |
| created_at | string | निर्माण समय (datetime) |

### 5.2 उपयोगकर्ता बनाएँ

```
POST /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
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

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 | पासवर्ड (bcrypt स्टोरेज) |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| phone | string | नहीं | | मोबाइल नंबर (Encryptable एन्क्रिप्टेड स्टोरेज) |
| email | string | नहीं | | ईमेल (Encryptable एन्क्रिप्टेड स्टोरेज) |
| status | int | नहीं | in:0,1 | स्थिति, डिफ़ॉल्ट 1 (सक्षम) |

**प्रतिक्रिया उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 422: उपयोगकर्ता नाम पहले से मौजूद
- 422: पैरामीटर सत्यापन विफल (आवश्यक फ़ील्ड अनुपस्थित)

### 5.3 उपयोगकर्ता विवरण

```
GET /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**प्रतिक्रिया उदाहरण**:
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

विवरण इंटरफ़ेस में `phone` और `email` प्लेनटेक्स्ट लौटते हैं (डेटाबेस में एन्क्रिप्टेड स्टोर, Encryptable cast स्वतः डिक्रिप्ट करता है), मास्किंग नहीं। `password` और `id_card` हमेशा प्रतिक्रिया में नहीं होते।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.4 उपयोगकर्ता अपडेट करें

```
PUT /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**अनुरोध निकाय**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम, नहीं भेजने पर मूल मान रहता है |
| password | string | नहीं | नया पासवर्ड, खाली स्ट्रिंग या नहीं भेजने पर संशोधित नहीं |
| phone | string | नहीं | मोबाइल नंबर |
| email | string | नहीं | ईमेल |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.5 उपयोगकर्ता हटाएँ

```
DELETE /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है
- **संवेदनशील ऑपरेशन**: पासवर्ड दोबारा पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता पासवर्ड (दोबारा पुष्टि) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

सॉफ्ट डिलीट (Eloquent SoftDeletes) निष्पादित होता है, डेटा deleted_at चिह्नित होता है बिना भौतिक रूप से हटाए।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं
- 422: संवेदनशील ऑपरेशन के लिए पासवर्ड इनपुट आवश्यक (password खाली)
- 422: पासवर्ड सत्यापन विफल (पासवर्ड मेल नहीं खाता)

### 5.6 बैच उपयोगकर्ता हटाएँ

```
POST /admin/user/batch/destroy
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड दोबारा पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID सरणी |
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता पासवर्ड (दोबारा पुष्टि) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

सॉफ्ट डिलीट निष्पादित होता है, `data.count` वास्तविक हटाई गई संख्या है।

**संभावित त्रुटियाँ**:
- 422: कृपया हटाने के लिए उपयोगकर्ता चुनें (ids खाली)
- 422: अमान्य ID (hashid डिकोड विफल)
- 422: पासवर्ड सत्यापन विफल

### 5.7 बैच उपयोगकर्ता सक्षम/अक्षम करें

```
POST /admin/user/batch/status
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID सरणी |
| status | int | हाँ | 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message status मान के अनुसार गतिशील रूप से `"批量启用成功"` या `"批量禁用成功"` में बदलता है।

**संभावित त्रुटियाँ**:
- 422: कृपया उपयोगकर्ता चुनें (ids खाली)
- 422: स्थिति मान अमान्य (status 0 या 1 नहीं)

## 6. भूमिका प्रबंधन

### 6.1 भूमिका सूची

```
GET /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड भूमिका ID |
| name | string | भूमिका नाम |
| slug | string | भूमिका पहचानकर्ता (अद्वितीय, अनुमति निर्णय के लिए) |
| description | string | भूमिका विवरण |
| status | int | 1=सक्षम, 0=अक्षम |
| users_count | int | इस भूमिका वाले उपयोगकर्ताओं की संख्या |

### 6.2 भूमिका बनाएँ

```
POST /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| name | string | हाँ | max:50 | भूमिका नाम |
| slug | string | हाँ | max:50 | भूमिका पहचानकर्ता |
| description | string | नहीं | | भूमिका विवरण, डिफ़ॉल्ट खाली स्ट्रिंग |
| status | int | नहीं | | स्थिति, डिफ़ॉल्ट 1 |
| permission_ids | array{int} | नहीं | | अनुमति ID सरणी (मूल INT ID, hashid नहीं) |

**प्रतिक्रिया उदाहरण**:
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

### 6.3 भूमिका अपडेट करें

```
PUT /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| name | string | नहीं | भूमिका नाम |
| description | string | नहीं | विवरण |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |
| permission_ids | array{int} | नहीं | अनुमति ID सरणी, भेजने पर भूमिका अनुमति सिंक (ओवरराइट) होती है |

**प्रतिक्रिया उदाहरण**:
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

### 6.4 भूमिका हटाएँ

```
DELETE /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड दोबारा पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय भूमिका और सभी अनुमतियों, उपयोगकर्ताओं के बीच संबंध स्वतः हट जाता है, फिर भूमिका रिकॉर्ड भौतिक रूप से हटाया जाता है।

## 7. अनुमति प्रबंधन

अनुमति वृक्ष संरचना (parent_id स्व-संबंध) अपनाती है, तीन प्रकारों में विभाजित। सूची इंटरफ़ेस पूर्ण अनुमति वृक्ष लौटाता है।

### 7.1 अनुमति वृक्ष

```
GET /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड |
| parent_id | string | मूल अनुमति hashid, "0" मूल नोड दर्शाता है |
| name | string | अनुमति नाम |
| slug | string | अनुमति पहचानकर्ता (रूट/बटन पहचानकर्ता) |
| type | int | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | मेनू आइकन (Material आइकन नाम) |
| path | string | फ्रंटएंड रूट पथ |
| sort | int | सॉर्ट मान (आरोही) |
| children | array? | उप-अनुमति सूची (पुनरावर्ती), कोई उप-नोड न होने पर यह फ़ील्ड शामिल नहीं |

### 7.2 अनुमति बनाएँ

```
POST /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
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

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| parent_id | int | नहीं | | मूल अनुमति ID (मूल INT प्रकार), डिफ़ॉल्ट 0 |
| name | string | हाँ | max:50 | अनुमति नाम |
| slug | string | हाँ | max:100 | अनुमति पहचानकर्ता |
| type | int | हाँ | in:1,2,3 | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | नहीं | | मेनू आइकन, डिफ़ॉल्ट खाली |
| path | string | नहीं | | फ्रंटएंड रूट पथ, डिफ़ॉल्ट खाली |
| sort | int | नहीं | | सॉर्ट मान, डिफ़ॉल्ट 0 |

**प्रतिक्रिया उदाहरण**:
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

### 7.3 अनुमति अपडेट करें

```
PUT /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| name | string | नहीं | अनुमति नाम |
| icon | string | नहीं | आइकन |
| path | string | नहीं | रूट पथ |
| sort | int | नहीं | सॉर्ट मान |

### 7.4 अनुमति हटाएँ

```
DELETE /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड दोबारा पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय सभी उप-अनुमतियाँ कैस्केड हटती हैं (`parent_id` = वर्तमान अनुमति ID के रिकॉर्ड), साथ ही सभी भूमिकाओं के साथ संबंध हटता है।

## 8. सिस्टम कॉन्फ़िगरेशन

सिस्टम कॉन्फ़िगरेशन `group` + `key` संयोजन से अद्वितीय होता है।

### 8.1 कॉन्फ़िगरेशन सूची

```
GET /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| group | string | नहीं | | कॉन्फ़िगरेशन समूह द्वारा फ़िल्टर |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| group | string | कॉन्फ़िगरेशन समूह (जैसे `system`, `email`, `storage`) |
| key | string | कॉन्फ़िगरेशन कुंजी |
| value | string | कॉन्फ़िगरेशन मान |
| type | string | मान प्रकार संकेत (`string`, `integer`, `boolean`, `json` आदि) |
| description | string | कॉन्फ़िगरेशन विवरण |

### 8.2 कॉन्फ़िगरेशन बनाएँ

```
POST /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| group | string | हाँ | max:100 | कॉन्फ़िगरेशन समूह |
| key | string | हाँ | max:100 | कॉन्फ़िगरेशन कुंजी (समूह के भीतर अद्वितीय) |
| value | string | हाँ | | कॉन्फ़िगरेशन मान |
| type | string | नहीं | | मान प्रकार, डिफ़ॉल्ट `string` |
| description | string | नहीं | | कॉन्फ़िगरेशन विवरण, डिफ़ॉल्ट खाली |

**प्रतिक्रिया उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 422: कॉन्फ़िगरेशन आइटम पहले से मौजूद (समान group + key)

### 8.3 कॉन्फ़िगरेशन अपडेट करें

```
PUT /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| value | string | नहीं | कॉन्फ़िगरेशन मान अपडेट करें |
| type | string | नहीं | मान प्रकार अपडेट करें |
| description | string | नहीं | विवरण पाठ अपडेट करें |

### 8.4 कॉन्फ़िगरेशन हटाएँ

```
DELETE /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड दोबारा पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

कॉन्फ़िगरेशन रिकॉर्ड भौतिक रूप से हटाया जाता है।

## 9. ऑपरेशन लॉग

ऑपरेशन लॉग केवल-पठन इंटरफ़ेस है, `OperationLog` मिडलवेयर द्वारा हर POST/PUT/DELETE अनुरोध पर स्वतः लिखा जाता है, संग्रहीत फ़ील्ड में `user_id`, `action`, `method`, `path`, `ip`, `source`, `input` शामिल हैं।

### 9.1 ऑपरेशन लॉग सूची

```
GET /admin/log
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| user_id | int | नहीं | | उपयोगकर्ता ID द्वारा सटीक फ़िल्टर (मूल INT प्रकार) |
| action | string | नहीं | | ऑपरेशन क्रिया द्वारा सटीक फ़िल्टर |
| path | string | नहीं | | अनुरोध पथ द्वारा फ़ज़ी फ़िल्टर |
| start_date | string | नहीं | | आरंभ तिथि (Y-m-d प्रारूप) |
| end_date | string | नहीं | | समाप्ति तिथि (Y-m-d प्रारूप) |

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| user_name | string | ऑपरेटिंग उपयोगकर्ता नाम (user संबंध से प्राप्त, लॉगिन न होने वाले ऑपरेशन पर "सिस्टम" दिखता है) |
| action | string | ऑपरेशन क्रिया विवरण |
| method | string | HTTP विधि (POST/PUT/DELETE) |
| path | string | अनुरोध पथ |
| ip | string | क्लाइंट IP |
| source | string | अनुरोध स्रोत |
| input | string | अनुरोध पैरामीटर JSON स्ट्रिंग (फ़ाइलें शामिल नहीं) |
| created_at | string | ऑपरेशन समय (datetime) |

## 10. व्यक्तिगत केंद्र

व्यक्तिगत केंद्र इंटरफ़ेसों को केवल JWT प्रमाणीकरण की आवश्यकता है (RBAC अनुमति सत्यापन की आवश्यकता नहीं — `AdminPermission` मिडलवेयर को इसे व्हाइटलिस्ट में जोड़ना चाहिए)।

### 10.1 व्यक्तिगत जानकारी अपडेट करें

```
PUT /admin/profile
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम |
| phone | string | नहीं | मोबाइल नंबर (Encryptable एन्क्रिप्टेड स्टोरेज) |
| email | string | नहीं | ईमेल (Encryptable एन्क्रिप्टेड स्टोरेज) |

**प्रतिक्रिया उदाहरण**:
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

प्रतिक्रिया में `phone` और `email` प्लेनटेक्स्ट लौटते हैं, `password` और `id_card` हटा दिए गए हैं।

### 10.2 पासवर्ड बदलें

```
PUT /admin/profile/password
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| फ़ील्ड | प्रकार | आवश्यक | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| old_password | string | हाँ | | वर्तमान पासवर्ड |
| new_password | string | हाँ | min:6, max:32 | नया पासवर्ड |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**संभावित त्रुटियाँ**:
- 422: कृपया पुराना और नया पासवर्ड भरें
- 422: पुराना पासवर्ड गलत
- 422: नया पासवर्ड 6-32 अक्षरों का होना चाहिए

### 10.3 लॉगआउट

```
POST /admin/profile/logout
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**: नहीं (कोई requestBody नहीं, Authorization हेडर से token पढ़ा जाता है)

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

लॉगआउट तर्क: JWT डिकोड करके शेष वैधता अवधि प्राप्त करें (exp - now), उस token का md5 हैश Redis ब्लैकलिस्ट `jwt_blacklist:{md5}` में लिखें, TTL = शेष वैधता अवधि। ब्लैकलिस्ट में token `AdminAuth` मिडलवेयर में अवरोधित होता है, 401 लौटता है।

कोई token न होने पर 401 लौटता है। token समाप्त/अमान्य होने पर (डिकोड अपवाद फेंकता है) फिर भी लॉगआउट सफल माना जाता है।

## 11. आयात-निर्यात

### 11.1 Excel निर्यात

```
POST /admin/export/excel
```

- **प्रमाणीकरण**: JWT + RBAC
- **प्रतिक्रिया प्रकार**: फ़ाइल डाउनलोड (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**अनुरोध निकाय**:
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

| फ़ील्ड | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| table | string | नहीं | `admin_user` | निर्यात तालिका नाम। समर्थित: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | नहीं | | निर्यात कॉलम फ़ील्ड नाम सरणी, खाली होने पर तालिका के सभी कॉलम निर्यात होते हैं |
| conditions | object | नहीं | `{}` | फ़िल्टर शर्तें, key-value जोड़े, मान खाली न होने पर WHERE में उपयोग |
| title | string | नहीं | `数据导出` | Excel शीर्षक (Sheet नाम के रूप में प्रदर्शित) |

**समर्थित तालिकाएँ और कॉलम**:

| table | उपलब्ध कॉलम |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

संवेदनशील फ़ील्ड `phone`, `email`, `id_card` निर्यात के समय स्वतः मास्किंग होते हैं। डेटा सीमा 10000 पंक्तियाँ। Excel पहली पंक्ति फ़्रीज़, स्वतः फ़िल्टर।

### 11.2 PDF निर्यात

```
POST /admin/export/pdf
```

- **प्रमाणीकरण**: JWT + RBAC
- **प्रतिक्रिया प्रकार**: फ़ाइल डाउनलोड (`application/pdf`, A4 क्षैतिज)

**अनुरोध निकाय**:
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

या तालिका मोड:
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

| फ़ील्ड | प्रकार | आवश्यक | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| type | string | नहीं | `table` | निर्यात प्रकार: `table` / `dashboard` |
| title | string | नहीं | `数据导出` | PDF शीर्षक |
| data | object | नहीं | `{}` | निर्यात डेटा |

`type=dashboard` होने पर `data` में `stats` सरणी होनी चाहिए (कार्ड रूप में रेंडर); `type=table` होने पर `data` में `columns` और `rows` सरणी होनी चाहिए।

PDF टेम्पलेट में कॉपीराइट जानकारी और निर्यात टाइमस्टैम्प शामिल है।

### 11.3 उपयोगकर्ता आयात करें (Excel)

```
POST /admin/import/users
```

- **प्रमाणीकरण**: JWT + RBAC
- **अनुरोध प्रकार**: `multipart/form-data` (फ़ाइल अपलोड)

**फ़ॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| file | file | हाँ | `.xlsx` या `.xls` प्रारूप |

**Excel कॉलम आवश्यकताएँ**:

| कॉलम नाम | आवश्यक | विवरण |
|------|------|------|
| username | हाँ | उपयोगकर्ता नाम (अद्वितीय) |
| password | हाँ | पासवर्ड (bcrypt हैश स्टोरेज) |
| real_name | हाँ | वास्तविक नाम |
| phone | नहीं | मोबाइल नंबर |
| email | नहीं | ईमेल |
| status | नहीं | स्थिति, डिफ़ॉल्ट 1 |

पंक्ति 1 कॉलम शीर्षक है (अक्षर केस संवेदनशील नहीं), पंक्ति 2 से डेटा है।

**प्रतिक्रिया उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| total | int | कुल पंक्तियाँ (शीर्षक पंक्ति को छोड़कर) |
| success | int | सफल आयात संख्या |
| failed | int | विफल संख्या |
| errors | array | विफल विवरण, प्रत्येक में row (Excel पंक्ति संख्या) और reason (विफल कारण) |

## 12. फ़ाइल अपलोड

```
POST /admin/upload
```

- **प्रमाणीकरण**: JWT + RBAC
- **अनुरोध प्रकार**: `multipart/form-data`

**फ़ॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| file | file | हाँ | अपलोड फ़ाइल |

**अनुमत फ़ाइल प्रकार**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**अधिकतम फ़ाइल आकार**: 10MB

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

फ़ाइलें तिथि के अनुसार निर्देशिका `public/upload/{Y-m-d}/` में संग्रहीत होती हैं, फ़ाइल नाम `md5(uniqid) + मूल एक्सटेंशन` होता है। `url` साइट मूल पथ के सापेक्ष सापेक्ष पथ है।

**संभावित त्रुटियाँ**:
- 422: कृपया फ़ाइल चुनें (अपलोड नहीं हुई)
- 422: असमर्थित फ़ाइल प्रकार
- 422: फ़ाइल आकार 10MB से अधिक नहीं हो सकता
- 500: फ़ाइल अपलोड विफल (फ़ाइल अमान्य)

## 13. प्रतिक्रिया हेडर

सभी इंटरफ़ेस (वैश्विक मिडलवेयर परत इंजेक्शन) में निम्न प्रतिक्रिया हेडर शामिल हैं:

| हेडर | विवरण |
|----|------|
| `X-RateLimit-Limit` | रेट लिमिट ऊपरी सीमा (संख्या) |
| `X-RateLimit-Remaining` | शेष अनुरोध संख्या |
| `X-RateLimit-Reset` | रेट लिमिट विंडो रीसेट टाइमस्टैम्प |
| `Retry-After` | केवल रेट लिमिट ट्रिगर होने पर लौटता है, सुझावित प्रतीक्षा सेकंड |
| `X-Content-Type-Options` | `nosniff` (webman डिफ़ॉल्ट, MIME स्निफिंग प्रतिबंधित) |
| `X-Frame-Options` | `DENY` (webman के CORS मिडलवेयर/आधार कॉन्फ़िगरेशन द्वारा प्रदान) |

रेट लिमिट विवरण:
- डिफ़ॉल्ट वैश्विक सीमा: 60 बार/मिनट / IP+पथ
- लॉगिन एंडपॉइंट `/api/auth/login`: 10 बार/मिनट
- पंजीकरण एंडपॉइंट `/api/auth/register`: 5 बार/मिनट
- Redis परमाणु स्लाइडिंग विंडो एल्गोरिदम (Lua ZSET) उपयोग, TOCTOU रेस से बचाता है
- Redis अनुपलब्ध होने पर fail open (अनुमति), अनुरोध अवरोधित नहीं होता

## 14. प्रमाणीकरण प्रवाह

पूर्ण प्रमाणीकरण अनुक्रम:

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

### JWT संरचना

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, डिफ़ॉल्ट TTL 7200 सेकंड (JWT कॉन्फ़िगरेशन `default_expire` से नियंत्रित)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, डिफ़ॉल्ट TTL 1209600 सेकंड (JWT कॉन्फ़िगरेशन `refresh_expire` से नियंत्रित, अर्थात 14 दिन)

### सुरक्षा प्रबंधन

- पासवर्ड `PASSWORD_BCRYPT` हैश स्टोरेज
- संवेदनशील फ़ील्ड (phone, email, id_card) `erikwang2013/encryptable` से डेटाबेस परत में पारदर्शी एन्क्रिप्शन/डिक्रिप्शन
- API परत ID `erikwang2013/hashids` से एन्क्रिप्टेड परिवहन, मूल snowflake ID अनुक्रम उजागर होने से बचाता है
- SecurityFilter वैश्विक स्कैन XSS, SQL इंजेक्शन, पथ ट्रैवर्सल, कमांड इंजेक्शन, समान IP 5 बार/60 सेकंड अस्थायी ब्लैकलिस्ट 15 मिनट
- संवेदनशील ऑपरेशन (उपयोगकर्ता, भूमिका, अनुमति, कॉन्फ़िगरेशन हटाना) के लिए वर्तमान लॉगिन उपयोगकर्ता पासवर्ड दोबारा पुष्टि आवश्यक
- समवर्ती सत्र सीमा: एक उपयोगकर्ता के अधिकतम 3 वैध टोकन, चौथे डिवाइस के लॉगिन पर सबसे पुराना टोकन अनिवार्य रूप से ब्लैकलिस्ट में जाता है
- खाता लॉक: लगातार 5 बार लॉगिन विफलता पर 15 मिनट खाता लॉक, लॉक अवधि में 429 लौटता है

## 15. परिनियोजन संचालन

### Docker Compose

प्रोजेक्ट रूट में `docker-compose.yml` उपलब्ध है, 5 सेवाओं का ऑर्केस्ट्रेशन करता है (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` से निर्मित (आधार `php:8.3-cli`, OPcache सक्षम)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions निरंतर एकीकरण पाइपलाइन परिभाषित करता है:
- `php -l` सिंटैक्स जांच
- PHPUnit यूनिट टेस्ट
- `flutter analyze` स्टैटिक विश्लेषण

### डेटाबेस बैकअप

`database/backup/` निर्देशिका बैकअप और पुनर्स्थापना स्क्रिप्ट प्रदान करती है:
- `backup.sh` — mysqldump + gzip संपीड़न बैकअप, 30 दिन पुरानी बैकअप फ़ाइलें स्वतः साफ़ करता है
- `restore.sh` — इंटरैक्टिव पुनर्स्थापना, उपलब्ध बैकअप सूचीबद्ध कर उपयोगकर्ता को चुनने देता है

### Nginx सुरक्षा कॉन्फ़िगरेशन

उत्पादन पर्यावरण परिनियोजन में कृपया रिवर्स प्रॉक्सी सुरक्षा सुदृढ़ीकरण कॉन्फ़िगरेशन के लिए `docs/nginx-security.conf` देखें।

## 16. व्यावसायिक API एंडपॉइंट (ERP)

सभी व्यावसायिक एंडपॉइंट `/admin` समूह के अंतर्गत हैं, तीन मिडलवेयर से गुजरते हैं: `AdminAuth` (JWT प्रमाणीकरण), `AdminPermission` (RBAC अनुमति सत्यापन), `OperationLog` (ऑपरेशन रिकॉर्ड)।

> एंडपॉइंट कुल: उत्पाद (17) | क्रय (8) | विक्रय (6) | इन्वेंटरी (6) | वित्त (17) | CRM (13) | वर्कफ़्लो (6) | अधिसूचना (4) | परियोजना (3) | HR (9) | विनिर्माण (7) | रिपोर्ट (4) | डैशबोर्ड (3) | क्लाइंट (2) | कुल 105 एंडपॉइंट

क्रॉस-मॉड्यूल लिंकेज एंडपॉइंट 🔗 से चिह्नित हैं।

### 16.1 उत्पाद प्रबंधन (Product Management)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/product | उत्पाद सूची (पेजिंग+खोज+श्रेणी/स्थिति फ़िल्टर) |
| POST | /admin/product | उत्पाद बनाएँ (SKU और मूल्य सहित) |
| GET | /admin/product/{id} | उत्पाद विवरण (श्रेणी/ब्रांड/SKU/मूल्य/इकाई सहित) |
| PUT | /admin/product/{id} | उत्पाद अपडेट करें |
| DELETE | /admin/product/{id} | उत्पाद हटाएँ (सॉफ्ट डिलीट, पासवर्ड पुष्टि आवश्यक) |
| GET | /admin/category | श्रेणी सूची (वृक्ष) |
| POST | /admin/category | श्रेणी बनाएँ |
| PUT | /admin/category/{id} | श्रेणी अपडेट करें |
| DELETE | /admin/category/{id} | श्रेणी हटाएँ |
| GET | /admin/brand | ब्रांड सूची |
| POST | /admin/brand | ब्रांड बनाएँ |
| GET | /admin/warehouse | वेयरहाउस सूची |
| POST | /admin/warehouse | वेयरहाउस बनाएँ |
| GET | /admin/location | स्थान सूची |
| GET | /admin/warehouse/{id}/locations | वेयरहाउस के अंतर्गत स्थान सूची |
| GET | /admin/supplier | आपूर्तिकर्ता सूची (ES खोज) |
| POST | /admin/supplier | आपूर्तिकर्ता बनाएँ |
| GET | /admin/customer | ग्राहक सूची (ES खोज) |
| POST | /admin/customer | ग्राहक बनाएँ |

### 16.2 क्रय प्रबंधन (Purchase)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/purchase/apply | क्रय अनुरोध सूची |
| POST | /admin/purchase/apply | क्रय अनुरोध बनाएँ |
| GET | /admin/purchase/order | क्रय आदेश सूची |
| POST | /admin/purchase/order | क्रय आदेश बनाएँ |
| 🔗 POST | /admin/purchase/receive | प्राप्ति दस्तावेज़ बनाएँ (स्वतः इनबाउंड + देय उत्पन्न) |
| GET | /admin/purchase/receive | प्राप्ति दस्तावेज़ सूची |
| GET | /admin/purchase/receive/{id} | प्राप्ति दस्तावेज़ विवरण |
| POST | /admin/purchase/return | वापसी दस्तावेज़ बनाएँ |
| GET | /admin/purchase/settlement | आपूर्तिकर्ता निपटान सूची |

### 16.3 विक्रय प्रबंधन (Sales)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/sales/quotation | कोटेशन सूची |
| POST | /admin/sales/quotation | कोटेशन बनाएँ |
| GET | /admin/sales/order | विक्रय आदेश सूची |
| POST | /admin/sales/order | विक्रय आदेश बनाएँ |
| 🔗 POST | /admin/sales/delivery | डिलीवरी दस्तावेज़ बनाएँ (स्वतः आउटबाउंड + प्राप्य उत्पन्न) |
| GET | /admin/sales/delivery | डिलीवरी दस्तावेज़ सूची |
| GET | /admin/sales/settlement | ग्राहक निपटान सूची |

### 16.4 इन्वेंटरी प्रबंधन (Inventory)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/inventory | रीयल-टाइम इन्वेंटरी (वेयरहाउस/स्थान/बैच/SKU आयाम) |
| GET | /admin/inventory/flow | इनबाउंड-आउटबाउंड फ्लो |
| GET | /admin/inventory/transfer | स्थानांतरण दस्तावेज़ सूची |
| POST | /admin/inventory/transfer | स्थानांतरण दस्तावेज़ बनाएँ |
| GET | /admin/inventory/check | गणना कार्य सूची |
| POST | /admin/inventory/check | गणना कार्य बनाएँ |
| GET | /admin/inventory/alert | इन्वेंटरी अलर्ट नियम |

### 16.5 वित्त प्रबंधन (Finance)

| विधि | पथ | विवरण |
|------|------|------|
| POST | /admin/finance/voucher | बहीखाता वाउचर बनाएँ |
| GET | /admin/finance/ar-ap | प्राप्य-देय सूची |
| POST | /admin/finance/receipt | प्राप्ति आदेश बनाएँ |
| POST | /admin/finance/payment | भुगतान आदेश बनाएँ |
| GET | /admin/finance/cash-journal | नकद/बैंक जर्नल |
| GET | /admin/finance/expense | व्यय प्रतिपूर्ति सूची |
| POST | /admin/finance/expense | प्रतिपूर्ति अनुरोध सबमिट करें |
| GET | /admin/finance/report/profit | लाभ विवरण |
| GET | /admin/finance/general-ledger | सामान्य खाता बही (खाता+अवधि के अनुसार सारांश) |
| GET | /admin/finance/subsidiary-ledger | विवरण खाता बही (खाते की क्रमबद्ध विवरण) |
| GET | /admin/finance/report/balance-sheet | बैलेंस शीट (स्वतः उत्पादन सहित) |
| GET | /admin/finance/report/cash-flow | नकदी प्रवाह विवरण (परिचालन/निवेश/वित्तपोषण) |
| GET | /admin/finance/bank-account | बैंक खाता सूची |
| GET/POST/PUT/DELETE | /admin/finance/asset | स्थायी संपत्ति CRUD + मूल्यह्रास आहरण |
| GET/POST | /admin/finance/tax-rate | कर दर कॉन्फ़िगरेशन |
| GET | /admin/finance/tax-record | कर रिकॉर्ड |
| GET/POST/PUT/DELETE | /admin/finance/currency | मुद्रा प्रबंधन |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | विनिमय दर प्रबंधन |
| GET/POST/PUT/DELETE | /admin/finance/budget | बजट प्रबंधन (बजट बनाम वास्तविक तुलना सहित) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | लागत केंद्र (वृक्ष संरचना) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | लाभ केंद्र (वृक्ष संरचना) |

### 16.6 CRM

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/crm/opportunity | अवसर सूची |
| POST | /admin/crm/opportunity | अवसर बनाएँ |
| GET | /admin/crm/follow | फॉलो-अप रिकॉर्ड सूची |
| POST | /admin/crm/follow | फॉलो-अप रिकॉर्ड बनाएँ |
| GET | /admin/crm/funnel | फ़नल चरण कॉन्फ़िगरेशन |
| GET | /admin/crm/contact | संपर्क व्यक्ति सूची |
| POST | /admin/crm/contact | संपर्क व्यक्ति बनाएँ |
| GET | /admin/crm/pool | पब्लिक पूल ग्राहक सूची |
| POST | /admin/crm/pool/claim/{id} | पब्लिक पूल ग्राहक लें |
| POST | /admin/crm/pool/release/{id} | ग्राहक को पब्लिक पूल में जारी करें |
| GET/POST | /admin/crm/pool/rules | पब्लिक पूल नियम CRUD |
| GET | /admin/crm/contract | अनुबंध सूची |
| POST | /admin/crm/contract | अनुबंध बनाएँ |
| GET | /admin/crm/contract/{id} | अनुबंध विवरण |
| PUT | /admin/crm/contract/{id} | अनुबंध अपडेट करें |
| DELETE | /admin/crm/contract/{id} | अनुबंध हटाएँ |
| GET | /admin/crm/quotation | CRM कोटेशन सूची |
| POST | /admin/crm/quotation | CRM कोटेशन बनाएँ |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 कोटेशन से अनुबंध |
| GET/POST/PUT/DELETE | /admin/crm/campaign | मार्केटिंग अभियान |
| GET/POST/PUT/DELETE | /admin/crm/ticket | सेवा टिकट |
| POST | /admin/crm/ticket/{id}/assign | टिकट आवंटित करें |
| POST | /admin/crm/ticket/{id}/resolve | टिकट हल करें |
| GET/POST | /admin/crm/analytics/report | ग्राहक विश्लेषण रिपोर्ट |
| GET/POST | /admin/crm/analytics/metric | विश्लेषण मेट्रिक |

### 16.7 अनुमोदन वर्कफ़्लो (Workflow)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/workflow | वर्कफ़्लो परिभाषा सूची |
| POST | /admin/workflow | वर्कफ़्लो परिभाषा बनाएँ |
| GET | /admin/workflow/{id} | वर्कफ़्लो विवरण |
| PUT | /admin/workflow/{id} | वर्कफ़्लो अपडेट करें |
| DELETE | /admin/workflow/{id} | वर्कफ़्लो हटाएँ |
| POST | /admin/workflow/{id}/submit | 🔗 अनुमोदन सबमिट करें (अनुमोदन इंस्टेंस बनाएँ) |
| POST | /admin/approval/{id}/approve | स्वीकृत |
| POST | /admin/approval/{id}/reject | अस्वीकृत |
| POST | /admin/approval/{id}/withdraw | वापस लें |
| ANY | /admin/approval/my | मेरे अनुमोदन सूची (लंबित/अनुमोदित) |

### 16.8 संदेश अधिसूचना (Notification)

| विधि | पथ | विवरण |
|------|------|------|
| ANY | /admin/notification/my | मेरी अधिसूचना सूची (पेजिंग, समय उल्टे क्रम में) |
| POST | /admin/notification/{id}/read | एकल पठित चिह्नित करें |
| POST | /admin/notification/read-all | सभी पठित चिह्नित करें |
| ANY | /admin/notification/unread-count | अपठित संदेश संख्या |

### 16.9 परियोजना प्रबंधन (Project)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/project | परियोजना सूची |
| POST | /admin/project | परियोजना बनाएँ |
| GET | /admin/project/{id} | परियोजना विवरण |
| PUT | /admin/project/{id} | परियोजना अपडेट करें |
| DELETE | /admin/project/{id} | परियोजना हटाएँ |
| GET | /admin/project/task | कार्य सूची |
| POST | /admin/project/task | कार्य बनाएँ |
| PUT | /admin/project/task/{id} | कार्य अपडेट करें |
| DELETE | /admin/project/task/{id} | कार्य हटाएँ |
| GET | /admin/project/timesheet | कार्य-घंटे रिकॉर्ड सूची |
| POST | /admin/project/timesheet | कार्य-घंटे दर्ज करें |
| PUT | /admin/project/timesheet/{id} | कार्य-घंटे अपडेट करें |
| DELETE | /admin/project/timesheet/{id} | कार्य-घंटे हटाएँ |

### 16.10 मानव संसाधन प्रबंधन (HR)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/hr/department | विभाग सूची (वृक्ष) |
| POST | /admin/hr/department | विभाग बनाएँ |
| PUT | /admin/hr/department/{id} | विभाग अपडेट करें |
| DELETE | /admin/hr/department/{id} | विभाग हटाएँ |
| GET | /admin/hr/employee | कर्मचारी सूची |
| POST | /admin/hr/employee | कर्मचारी बनाएँ |
| PUT | /admin/hr/employee/{id} | कर्मचारी अपडेट करें |
| DELETE | /admin/hr/employee/{id} | कर्मचारी हटाएँ |
| GET | /admin/hr/position | पद सूची |
| POST | /admin/hr/position | पद बनाएँ |
| PUT | /admin/hr/position/{id} | पद अपडेट करें |
| DELETE | /admin/hr/position/{id} | पद हटाएँ |
| ANY | /admin/hr/attendance | उपस्थिति रिकॉर्ड क्वेरी |
| POST | /admin/hr/attendance/clock-in | काम शुरू पंच |
| POST | /admin/hr/attendance/clock-out | काम समाप्त पंच |
| ANY | /admin/hr/leave | अवकाश सूची |
| POST | /admin/hr/leave | अवकाश अनुरोध सबमिट करें |
| GET | /admin/hr/leave/{id} | अवकाश विवरण |
| PUT | /admin/hr/leave/{id} | अवकाश अपडेट करें |
| DELETE | /admin/hr/leave/{id} | अवकाश हटाएँ |
| POST | /admin/hr/leave/{id}/approve | 🔗 अवकाश अनुमोदन |
| GET | /admin/hr/salary | वेतन सूची |
| POST | /admin/hr/salary | वेतन पर्ची उत्पन्न करें |
| PUT | /admin/hr/salary/{id} | वेतन अपडेट करें |
| DELETE | /admin/hr/salary/{id} | वेतन हटाएँ |
| POST | /admin/hr/salary/{id}/pay | वेतन भुगतान करें |
| ANY | /admin/hr/salary-item | वेतन आइटम सूची |
| POST | /admin/hr/salary-item | वेतन आइटम बनाएँ |
| GET | /admin/hr/salary-item/{id} | वेतन आइटम विवरण |
| PUT | /admin/hr/salary-item/{id} | वेतन आइटम अपडेट करें |
| DELETE | /admin/hr/salary-item/{id} | वेतन आइटम हटाएँ |

### 16.11 उत्पादन विनिर्माण (Manufacturing)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/mfg/bom | BOM सूची |
| POST | /admin/mfg/bom | BOM बनाएँ |
| PUT | /admin/mfg/bom/{id} | BOM अपडेट करें |
| DELETE | /admin/mfg/bom/{id} | BOM हटाएँ |
| GET | /admin/mfg/production | उत्पादन आदेश सूची |
| POST | /admin/mfg/production | उत्पादन आदेश बनाएँ |
| PUT | /admin/mfg/production/{id} | उत्पादन आदेश अपडेट करें |
| DELETE | /admin/mfg/production/{id} | उत्पादन आदेश हटाएँ |
| POST | /admin/mfg/production/{id}/start | आरंभ |
| POST | /admin/mfg/production/{id}/complete | पूर्ण |
| GET | /admin/mfg/routing | प्रक्रिया मार्ग सूची |
| POST | /admin/mfg/routing | प्रक्रिया मार्ग बनाएँ |
| PUT | /admin/mfg/routing/{id} | प्रक्रिया मार्ग अपडेट करें |
| DELETE | /admin/mfg/routing/{id} | प्रक्रिया मार्ग हटाएँ |
| GET | /admin/mfg/workstation | वर्कस्टेशन सूची |
| POST | /admin/mfg/workstation | वर्कस्टेशन बनाएँ |
| PUT | /admin/mfg/workstation/{id} | वर्कस्टेशन अपडेट करें |
| DELETE | /admin/mfg/workstation/{id} | वर्कस्टेशन हटाएँ |
| GET | /admin/mfg/mrp | MRP योजना सूची |
| POST | /admin/mfg/mrp | MRP योजना बनाएँ |
| PUT | /admin/mfg/mrp/{id} | MRP योजना अपडेट करें |
| DELETE | /admin/mfg/mrp/{id} | MRP योजना हटाएँ |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 MRP चलाकर क्रय/उत्पादन सुझाव उत्पन्न करें |

### 16.12 कस्टम रिपोर्ट (Report Builder)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/report | रिपोर्ट टेम्पलेट सूची |
| POST | /admin/report | रिपोर्ट टेम्पलेट बनाएँ |
| GET | /admin/report/{id} | रिपोर्ट टेम्पलेट विवरण |
| PUT | /admin/report/{id} | रिपोर्ट टेम्पलेट अपडेट करें |
| DELETE | /admin/report/{id} | रिपोर्ट टेम्पलेट हटाएँ |
| POST | /admin/report/{id}/execute | रिपोर्ट चलाकर डेटा उत्पन्न करें |
| ANY | /admin/report/{id}/result | रिपोर्ट निष्पादन परिणाम |
| GET | /admin/report/schedule | शेड्यूलिंग सूची |
| POST | /admin/report/schedule | शेड्यूलिंग बनाएँ |
| PUT | /admin/report/schedule/{id} | शेड्यूलिंग अपडेट करें |
| DELETE | /admin/report/schedule/{id} | शेड्यूलिंग हटाएँ |

### 16.13 डैशबोर्ड (Dashboard)

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/dashboard/sales | विक्रय पैनल |
| GET | /admin/dashboard/inventory | इन्वेंटरी पैनल |
| GET | /admin/dashboard/finance | वित्त पैनल |

### 16.14 क्लाइंट API (Client API)

क्लाइंट इंटरफ़ेस `/api` समूह के अंतर्गत माउंट होते हैं, `API-Version` अनुरोध हेडर की आवश्यकता होती है। उत्पाद जानकारी में खरीद मूल्य शामिल नहीं होता।

| विधि | पथ | विवरण |
|------|------|------|
| GET | /api/product | उत्पाद सूची (खरीद मूल्य शामिल नहीं) |
| GET | /api/product/{hashid} | उत्पाद विवरण (खुदरा/थोक मूल्य सहित, खरीद मूल्य शामिल नहीं) |

### 16.15 OMS ऑर्डर प्रबंधन

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/oms/order | OMS ऑर्डर सूची |
| POST | /admin/oms/order | OMS ऑर्डर बनाएँ |
| 🔗 POST | /admin/oms/order/{id}/allocate | इन्वेंटरी आवंटन (प्री-रिज़र्वेशन) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | फ़ुलफ़िलमेंट बनाएँ |
| POST | /admin/oms/order/{id}/cancel | ऑर्डर रद्द करें (रिज़र्वेशन रिलीज़) |
| POST | /admin/oms/rma/{id}/approve | RMA अनुमोदन |
| POST | /admin/oms/rma/{id}/refund | RMA रिफंड |

### 16.16 WMS वेयरहाउस प्रबंधन

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/wms/zone | ज़ोन सूची (CRUD) |
| GET | /admin/wms/location | WMS स्थान सूची (CRUD) |
| GET | /admin/wms/asn | ASN सूची (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | प्राप्ति पूर्ण करें→स्वतः पुटअवे कार्य उत्पन्न |
| POST | /admin/wms/putaway/{id}/complete | पुटअवे पुष्टि→stockIn ट्रिगर |
| POST | /admin/wms/wave/{id}/release | वेव जारी करें→पिकिंग कार्य उत्पन्न |
| POST | /admin/wms/pick/{id}/start | पिकिंग आरंभ करें |
| POST | /admin/wms/pick/{id}/confirm | पिकिंग पुष्टि |
| POST | /admin/wms/pack/{id}/complete | पैकिंग पूर्ण |

### 16.17 TMS परिवहन प्रबंधन

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/tms/carrier | कैरियर सूची (CRUD) |
| GET | /admin/tms/service | कैरियर सेवा (CRUD) |
| GET | /admin/tms/freight-rate | माल ढुलाई दर (CRUD) |
| GET | /admin/tms/shipment | शिपमेंट सूची (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | शिपमेंट पुष्टि (stockOut+AR) |
| POST | /admin/tms/tracking/callback | कैरियर ट्रैकिंग webhook |
| POST | /admin/tms/freight-invoice/{id}/pay | माल ढुलाई चालान भुगतान (AP उत्पन्न) |

### 16.18 डैशबोर्ड विस्तार

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/dashboard/oms | OMS KPI (लंबित/पिकिंग में/आज की शिपमेंट/RMA) |
| GET | /admin/dashboard/wms | WMS KPI (प्राप्ति लंबित/पुटअवे लंबित/पिकिंग लंबित/पैकिंग लंबित) |
| GET | /admin/dashboard/tms | TMS KPI (शिपमेंट लंबित/पारगमन में/हस्ताक्षरित/असामान्य) |

### 16.19 क्रॉस-मॉड्यूल लिंकेज विवरण

निम्न एंडपॉइंट क्रॉस-मॉड्यूल स्वचालित लिंकेज ट्रिगर करते हैं, 🔗 से चिह्नित:

| एंडपॉइंट | लिंकेज क्रिया |
|------|---------|
| 🔗 POST /admin/purchase/receive | स्वतः InventoryService.stockIn() कॉल कर इन्वेंटरी अपडेट + मूविंग वेटेड एवरेज लागत पुनर्गणना; FinanceService.createAp() कॉल कर देय रिकॉर्ड उत्पन्न |
| 🔗 POST /admin/sales/delivery | स्वतः InventoryService.stockOut() कॉल कर इन्वेंटरी घटाना (मूविंग वेटेड एवरेज लागत के अनुसार); FinanceService.createAr() कॉल कर प्राप्य रिकॉर्ड उत्पन्न |
