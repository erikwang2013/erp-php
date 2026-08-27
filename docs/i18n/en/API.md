# API Reference Documentation

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API Documentation

The project uses [hg/apidoc](https://github.com/hg-code/apidoc) to auto-generate interactive API documentation.

**Access:** after starting the service, visit `http://localhost:8787/apidoc`

**Documentation groups:**
| Group | Description | Module Count |
|------|------|--------|
| Admin endpoints | All endpoints of the backend management system | 25 modules |
| Client endpoints (Service API) | Lightweight endpoints called by mobile/Web clients | 3 modules |

**Global request headers:**
| Header | Description |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | API version number (v1) |
| `Accept-Language` | i18n language (zh-CN/en) |

**Annotation convention:** all controller methods use `@Apidoc\*` annotations to document the endpoint name, description, URL, request method, parameters, and response structure.

## 1. Overview

The Open Admin Console (open-admin) is built on webman v2 and provides RESTful JSON APIs. All admin endpoints require JWT authentication and RBAC permission checks; public endpoints are routed to versioned controllers via the API version header.

- **Base URL**: `http://localhost:8787`
- **API version**: controlled via the `API-Version: v1` request header (defaults to v1 when missing)

> **Endpoint overview**: Auth(5) | Dashboard(1) | User(7) | Role(4) | Permission(4) | Config(4) | Log(1) | Profile(3) | Import/Export(3) | Upload(1) | Operations(4: health/metrics/docs/security.txt) | 37 endpoints total
- **Authentication**: `Authorization: Bearer <token>` (JWT)
- **Response format**: `{ "code": 0, "message": "success", "data": {...} }`
- **Docs endpoint**: `GET /api/docs` returns the OpenAPI 3.0 JSON specification

### Internationalization

The API switches language automatically via the `Accept-Language` request header:

| Header Value | Language |
|---------|------|
| `zh-CN`, `zh` | Chinese (default) |
| `en`, `en-US` | English |

```bash
# English response
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# Chinese response (default)
curl http://localhost:8787/admin/product
```

The `message` field in responses is returned in the corresponding language.

### Request Requirements

- Only `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` methods are allowed; other HTTP methods (such as TRACE, CONNECT, PATCH) return 405
- All `POST` / `PUT` requests must set `Content-Type: application/json` (except file uploads), otherwise 415 is returned
- The request body must not exceed 10MB, otherwise 413 is returned
- The security filter scans all request input for XSS, SQL injection, path traversal, and command injection; hits return 403
- 5 consecutive failed logins trigger an account lockout (15 minutes); login requests return 429 during the lockout
- A single user may hold at most 3 valid tokens concurrently; the oldest token is automatically blacklisted when exceeded

## 2. Error Codes

| code | Meaning | Trigger Scenario |
|------|------|---------|
| 0 | Success | |
| 400 | Invalid request parameters | Malformed request |
| 401 | Not authenticated | Token missing / expired / blacklisted |
| 403 | No permission / security block | Insufficient RBAC permission / SecurityFilter hit |
| 404 | Resource not found | The query/update/delete target does not exist |
| 405 | Method not allowed | Only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed; non-standard methods rejected outright |
| 413 | Request body too large | Content-Length exceeds 10MB |
| 415 | Unsupported media type | POST/PUT request Content-Type is neither JSON nor a file upload |
| 422 | Parameter validation failed | Required field missing, format mismatch, or business validation failed |
| 429 | Too many requests | RateLimit triggered / account lockout (15 min after 5 consecutive failed logins) |
| 500 | Internal server error | |

## 3. Public Endpoints

All public endpoints are mounted under the `/api` group and dispatched by the `ApiVersion` middleware to the versioned controllers per the `API-Version` header (e.g. `app\api\v1\controller\AuthController`).

### 3.1 Health Check

```
GET /health
```

- **Auth**: none
- **Rate limit**: none

**Example response**:
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

`database`, `redis`, `elasticsearch` values: `"ok"` | `"unavailable"`. `elasticsearch` returns `"unavailable"` when ES is unreachable; if the cluster health status is not green/yellow, the actual status value is returned (e.g. `"red"`).

### 3.2 API Documentation

```
GET /api/docs
```

- **Auth**: none
- **Rate limit**: global default (60 requests/minute)
- **Response**: OpenAPI 3.0.3 JSON specification containing all endpoint definitions, parameters, and schemas

### 3.3 Generate Click Captcha

```
POST /api/captcha/generate
```

- **Auth**: none
- **Header**: `API-Version: v1` (required)
- **Rate limit**: global default (60 requests/minute)

**Request body**:
```json
{
  "difficulty": "medium"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, default `medium` |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| key | string | Captcha identifier, sent back when verifying |
| image | string | base64-encoded PNG image |
| extra.targets[].order | int | Click order |
| extra.targets[].text | string | Click target hint text |

### 3.4 Verify Click Captcha

```
POST /api/captcha/verify
```

- **Auth**: none
- **Header**: `API-Version: v1` (required)
- **Rate limit**: global default (60 requests/minute)

**Request body**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| key | string | Yes | Captcha key, returned by generate |
| clicks | array{object} | Yes | Click coordinate array, each element contains `x` (int) and `y` (int) |

**Example response**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

On verification failure, `code` is 422, `message` is `"验证失败，请重试"`, and `data.valid` is `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Auth**: none
- **Header**: `API-Version: v1` (required)
- **Rate limit**: 10 requests/minute (per IP + path)

**Request body**:
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

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username |
| password | string | Yes | min:6, max:32 | Password |
| captcha_key | string | Yes | | Captcha key |
| clicks | array{object} | Yes | min:2 | Click coordinate array |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| access_token | string | JWT access token |
| refresh_token | string | JWT refresh token |
| expires_in | int | Access token lifetime (seconds), default 7200 |
| user.id | string | Hashid-encrypted user ID |
| user.username | string | Username |
| user.real_name | string | Real name |

**Possible errors**:
- 422: parameter validation failed (missing required fields, format mismatch)
- 422: incorrect captcha, please retry
- 401: incorrect username or password
- 403: account disabled
- 429: account locked, try again in 15 minutes (triggered by 5 consecutive failed logins)

### 3.6 Register

```
POST /api/auth/register
```

- **Auth**: none
- **Header**: `API-Version: v1` (required)
- **Rate limit**: 5 requests/minute (per IP + path)
- **Toggle**: disabled by default (`REGISTRATION_ENABLED=0`), returns 403 when disabled; must be explicitly enabled in `.env` (`REGISTRATION_ENABLED=1`)

**Request body**:
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

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 | Password (stored as bcrypt hash) |
| real_name | string | Yes | max:50 | Real name |
| captcha_key | string | Yes | | Captcha key |
| clicks | array{object} | Yes | min:2 | Click coordinate array |

**Example response**:
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

After successful registration, JWT tokens are returned directly and the user status defaults to enabled (status=1). This endpoint is available only when `REGISTRATION_ENABLED=1`.

### 3.7 Refresh Token

```
POST /api/auth/refresh
```

- **Auth**: none
- **Header**: `API-Version: v1` (required)
- **Rate limit**: global default (60 requests/minute)

**Request body**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| refresh_token | string | Yes | The refresh_token obtained at login/registration |

**Example response**:
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

A successful refresh returns both a new access_token and refresh_token; the old tokens are invalidated automatically. The user's last login time and IP are updated on refresh.

**Possible errors**:
- 422: missing refresh token
- 401: refresh token invalid or expired

### 3.8 Prometheus Metrics

```
GET /metrics
```

- **Auth**: none
- **Rate limit**: none
- **Response format**: Prometheus text format (`text/plain; version=0.0.4`)

Public Prometheus metrics endpoint for scraping by Grafana/Prometheus.

**Example response**:
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

| Metric | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Cumulative HTTP request count |
| `openadmin_active_users` | gauge | Current active users (logged in within 24h) |
| `openadmin_db_connection_status` | gauge | Database connection status, 1=ok, 0=error |
| `openadmin_redis_connection_status` | gauge | Redis connection status, 1=ok, 0=error |
| `openadmin_memory_usage_bytes` | gauge | Current PHP process memory usage (bytes) |

## 4. Dashboard

All admin endpoints are mounted under the `/admin` group and pass through three middlewares: `AdminAuth` (JWT authentication), `AdminPermission` (RBAC permission check), and `OperationLog` (operation logging).

### 4.1 Dashboard Data

```
GET /admin/dashboard
```

- **Auth**: JWT + RBAC
- **Cache**: Redis 5 minutes

**Example response**:
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

| stats fields | Type | Description |
|------|------|------|
| label | string | Metric name |
| value | string | Metric value (string type) |
| icon | string | Material icon name |
| color | string | Card color |
| trend | float? | Day-over-day growth rate (percentage), only present for "用户总数" |

| trends fields | Type | Description |
|------|------|------|
| dates | array{string} | Date series of the last 30 days |
| series | array{object} | Trend line data; each entry contains name (name), data (value array), color (color) |

## 5. User Management

All `id` values returned by user management endpoints are hashid-encrypted strings. Password fields are excluded from responses. Phone numbers and emails are masked in list endpoints and returned in plaintext in detail endpoints (database encrypted fields are decrypted automatically by the Encryptable trait).

### 5.1 User List

```
GET /admin/user
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| keyword | string | No | | Search keyword, matches username and real name |
| status | int | No | | Status filter, 0=disabled, 1=enabled |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | Hashid-encrypted user ID |
| username | string | Username |
| real_name | string | Real name |
| phone | string | Masked phone number (`138****5678` format) |
| email | string | Masked email (`a***@example.com` format) |
| status | int | 1=enabled, 0=disabled |
| last_login_at | string | Last login time (datetime) |
| created_at | string | Creation time (datetime) |

### 5.2 Create User

```
POST /admin/user
```

- **Auth**: JWT + RBAC

**Request body**:
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

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| username | string | Yes | min:3, max:50 | Username (unique) |
| password | string | Yes | min:6, max:32 | Password (stored as bcrypt) |
| real_name | string | Yes | max:50 | Real name |
| phone | string | No | | Phone number (stored encrypted via Encryptable) |
| email | string | No | | Email (stored encrypted via Encryptable) |
| status | int | No | in:0,1 | Status, default 1 (enabled) |

**Example response**:
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

**Possible errors**:
- 422: username already exists
- 422: parameter validation failed (required fields missing)

### 5.3 User Detail

```
GET /admin/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Example response**:
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

In detail endpoints, `phone` and `email` are returned in plaintext (stored encrypted in the database; the Encryptable cast decrypts automatically), without masking. `password` and `id_card` are never included in responses.

**Possible errors**:
- 404: user not found

### 5.4 Update User

```
PUT /admin/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID

**Request body**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name; if not passed, keeps the original value |
| password | string | No | New password; an empty string or omitted means no change |
| phone | string | No | Phone number |
| email | string | No | Email |
| status | int | No | 0=disabled, 1=enabled |

**Example response**:
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

**Possible errors**:
- 404: user not found

### 5.5 Delete User

```
DELETE /admin/user/{id}
```

- **Auth**: JWT + RBAC
- **Path parameter**: `{id}` is the hashid-encrypted user ID
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| password | string | Yes | Current logged-in user's password (re-confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Performs a soft delete (Eloquent SoftDeletes); data is marked with deleted_at rather than physically deleted.

**Possible errors**:
- 404: user not found
- 422: sensitive operation requires password confirmation (password empty)
- 422: password verification failed (password mismatch)

### 5.6 Batch Delete Users

```
POST /admin/user/batch/destroy
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| password | string | Yes | Current logged-in user's password (re-confirmation) |

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Performs a soft delete; `data.count` is the actual number deleted.

**Possible errors**:
- 422: please select users to delete (ids empty)
- 422: invalid ID (hashid decode failed)
- 422: password verification failed

### 5.7 Batch Enable/Disable Users

```
POST /admin/user/batch/status
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| ids | array{string} | Yes | Array of hashid-encrypted user IDs |
| status | int | Yes | 0=disabled, 1=enabled |

**Example response**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

The message changes dynamically based on the status value: `"批量启用成功"` or `"批量禁用成功"`.

**Possible errors**:
- 422: please select users (ids empty)
- 422: invalid status value (status is not 0 or 1)

## 6. Role Management

### 6.1 Role List

```
GET /admin/role
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | Hashid-encrypted role ID |
| name | string | Role name |
| slug | string | Role identifier (unique, used for permission checks) |
| description | string | Role description |
| status | int | 1=enabled, 0=disabled |
| users_count | int | Number of users holding this role |

### 6.2 Create Role

```
POST /admin/role
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| name | string | Yes | max:50 | Role name |
| slug | string | Yes | max:50 | Role identifier |
| description | string | No | | Role description, default empty string |
| status | int | No | | Status, default 1 |
| permission_ids | array{int} | No | | Array of permission IDs (raw INT IDs, not hashids) |

**Example response**:
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

### 6.3 Update Role

```
PUT /admin/role/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Role name |
| description | string | No | Description |
| status | int | No | 0=disabled, 1=enabled |
| permission_ids | array{int} | No | Array of permission IDs; when passed, syncs (overwrites) the role's permissions |

**Example response**:
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

### 6.4 Delete Role

```
DELETE /admin/role/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

On deletion, the role's associations with all permissions and users are detached, then the role record is physically deleted.

## 7. Permission Management

Permissions use a tree structure (self-referencing parent_id) and come in three types. List endpoints return the complete permission tree.

### 7.1 Permission Tree

```
GET /admin/permission
```

- **Auth**: JWT + RBAC

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | Hashid-encrypted |
| parent_id | string | Parent permission hashid, "0" means root node |
| name | string | Permission name |
| slug | string | Permission identifier (route/button identifier) |
| type | int | 1=menu, 2=button, 3=API |
| icon | string | Menu icon (Material icon name) |
| path | string | Frontend route path |
| sort | int | Sort value (ascending) |
| children | array? | Child permission list (recursive); omitted when there are no children |

### 7.2 Create Permission

```
POST /admin/permission
```

- **Auth**: JWT + RBAC

**Request body**:
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

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| parent_id | int | No | | Parent permission ID (raw INT type), default 0 |
| name | string | Yes | max:50 | Permission name |
| slug | string | Yes | max:100 | Permission identifier |
| type | int | Yes | in:1,2,3 | 1=menu, 2=button, 3=API |
| icon | string | No | | Menu icon, default empty |
| path | string | No | | Frontend route path, default empty |
| sort | int | No | | Sort value, default 0 |

**Example response**:
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

### 7.3 Update Permission

```
PUT /admin/permission/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| name | string | No | Permission name |
| icon | string | No | Icon |
| path | string | No | Route path |
| sort | int | No | Sort value |

### 7.4 Delete Permission

```
DELETE /admin/permission/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

**Example response**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

On deletion, all child permissions are deleted in cascade (`parent_id` = current permission ID), and associations with all roles are detached.

## 8. System Config

System config is uniquely identified by the combination of `group` + `key`.

### 8.1 Config List

```
GET /admin/config
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| group | string | No | | Filter by config group |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Config group (e.g. `system`, `email`, `storage`) |
| key | string | Config key |
| value | string | Config value |
| type | string | Value type hint (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Config description |

### 8.2 Create Config

```
POST /admin/config
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| group | string | Yes | max:100 | Config group |
| key | string | Yes | max:100 | Config key (unique within the same group) |
| value | string | Yes | | Config value |
| type | string | No | | Value type, default `string` |
| description | string | No | | Config description, default empty |

**Example response**:
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

**Possible errors**:
- 422: config item already exists (same group + key)

### 8.3 Update Config

```
PUT /admin/config/{id}
```

- **Auth**: JWT + RBAC

**Request body**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| value | string | No | Update config value |
| type | string | No | Update value type |
| description | string | No | Update description text |

### 8.4 Delete Config

```
DELETE /admin/config/{id}
```

- **Auth**: JWT + RBAC
- **Sensitive operation**: requires password re-confirmation

**Request body**:
```json
{
  "password": "admin_password"
}
```

Physically deletes the config record.

## 9. Operation Logs

Operation logs are a read-only interface, written automatically by the `OperationLog` middleware on every POST/PUT/DELETE request, storing `user_id`, `action`, `method`, `path`, `ip`, `source`, and `input`.

### 9.1 Operation Log List

```
GET /admin/log
```

- **Auth**: JWT + RBAC

**Query parameters**:

| Parameter | Type | Required | Default | Description |
|------|------|------|------|------|
| page | int | No | 1 | Page number |
| limit | int | No | 15 | Items per page |
| user_id | int | No | | Exact filter by user ID (raw INT type) |
| action | string | No | | Exact filter by action |
| path | string | No | | Fuzzy filter by request path |
| start_date | string | No | | Start date (Y-m-d format) |
| end_date | string | No | | End date (Y-m-d format) |

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Operating username (obtained via the user relation; shows "系统" for unauthenticated operations) |
| action | string | Operation description |
| method | string | HTTP method (POST/PUT/DELETE) |
| path | string | Request path |
| ip | string | Client IP |
| source | string | Request source |
| input | string | Request parameter JSON string (excluding files) |
| created_at | string | Operation time (datetime) |

## 10. Profile

Profile endpoints only require JWT authentication (no RBAC permission check — the `AdminPermission` middleware should whitelist them).

### 10.1 Update Profile

```
PUT /admin/profile
```

- **Auth**: JWT

**Request body**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Field | Type | Required | Description |
|------|------|------|------|
| real_name | string | No | Real name |
| phone | string | No | Phone number (stored encrypted via Encryptable) |
| email | string | No | Email (stored encrypted via Encryptable) |

**Example response**:
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

In the response, `phone` and `email` are returned in plaintext; `password` and `id_card` are excluded.

### 10.2 Change Password

```
PUT /admin/profile/password
```

- **Auth**: JWT

**Request body**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Field | Type | Required | Validation Rules | Description |
|------|------|------|---------|------|
| old_password | string | Yes | | Current password |
| new_password | string | Yes | min:6, max:32 | New password |

**Example response**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Possible errors**:
- 422: please provide old and new passwords
- 422: incorrect old password
- 422: new password must be 6-32 characters

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Auth**: JWT

**Request body**: none (no requestBody; the token is read from the Authorization header)

**Example response**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout logic: decode the JWT to get the remaining lifetime (exp - now), write the token's md5 hash to the Redis blacklist `jwt_blacklist:{md5}` with TTL = remaining lifetime. Blacklisted tokens are blocked by the `AdminAuth` middleware and return 401.

Without a token, 401 is returned. If the token is expired/invalid (decode throws), logout is still treated as successful.

## 11. Import & Export

### 11.1 Export Excel

```
POST /admin/export/excel
```

- **Auth**: JWT + RBAC
- **Response type**: file download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Request body**:
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

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| table | string | No | `admin_user` | Table to export. Supported: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Array of column field names; empty exports all columns of the table |
| conditions | object | No | `{}` | Filter conditions, key-value pairs; non-empty values are used in WHERE |
| title | string | No | `数据导出` | Excel title (displayed as the sheet name) |

**Supported tables and columns**:

| table | Available columns |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Sensitive fields `phone`, `email`, `id_card` are automatically masked on export. Data is capped at 10000 rows. The first Excel row is frozen with auto-filter enabled.

### 11.2 Export PDF

```
POST /admin/export/pdf
```

- **Auth**: JWT + RBAC
- **Response type**: file download (`application/pdf`, A4 landscape)

**Request body**:
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

Or table mode:
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

| Field | Type | Required | Default | Description |
|------|------|------|------|------|
| type | string | No | `table` | Export type: `table` / `dashboard` |
| title | string | No | `数据导出` | PDF title |
| data | object | No | `{}` | Export data |

With `type=dashboard`, `data` must contain a `stats` array (rendered as cards); with `type=table`, `data` must contain `columns` and `rows` arrays.

The PDF template includes copyright information and the export timestamp.

### 11.3 Import Users (Excel)

```
POST /admin/import/users
```

- **Auth**: JWT + RBAC
- **Request type**: `multipart/form-data` (file upload)

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | `.xlsx` or `.xls` format |

**Excel column requirements**:

| Column | Required | Description |
|------|------|------|
| username | Yes | Username (unique) |
| password | Yes | Password (stored as bcrypt hash) |
| real_name | Yes | Real name |
| phone | No | Phone number |
| email | No | Email |
| status | No | Status, default 1 |

Row 1 is the column header (case-insensitive); data starts at row 2.

**Example response**:
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

| Field | Type | Description |
|------|------|------|
| total | int | Total rows (excluding the header row) |
| success | int | Successfully imported count |
| failed | int | Failed count |
| errors | array | Failure details, each entry contains row (Excel row number) and reason (failure reason) |

## 12. File Upload

```
POST /admin/upload
```

- **Auth**: JWT + RBAC
- **Request type**: `multipart/form-data`

**Form fields**:

| Field | Type | Required | Description |
|------|------|------|------|
| file | file | Yes | Uploaded file |

**Allowed file types**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Maximum file size**: 10MB

**Example response**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Files are stored in date-based directories under `public/upload/{Y-m-d}/`, with the filename `md5(uniqid) + original extension`. `url` is a relative path from the site root.

**Possible errors**:
- 422: please select a file (none uploaded)
- 422: unsupported file type
- 422: file size must not exceed 10MB
- 500: file upload failed (invalid file)

## 13. Response Headers

All endpoints (injected at the global middleware layer) include the following response headers:

| Header | Description |
|----|------|
| `X-RateLimit-Limit` | Rate limit cap (count) |
| `X-RateLimit-Remaining` | Remaining request count |
| `X-RateLimit-Reset` | Rate limit window reset timestamp |
| `Retry-After` | Returned only when rate-limited; suggested seconds to wait |
| `X-Content-Type-Options` | `nosniff` (webman default, prevents MIME sniffing) |
| `X-Frame-Options` | `DENY` (provided by webman's CORS middleware/base config) |

Rate limit details:
- Default global limit: 60 requests/minute / IP+path
- Login endpoint `/api/auth/login`: 10 requests/minute
- Register endpoint `/api/auth/register`: 5 requests/minute
- Uses the Redis atomic sliding window algorithm (Lua ZSET) to avoid TOCTOU races
- When Redis is unavailable, fails open (allows requests) without blocking

## 14. Authentication Flow

The complete authentication sequence:

```
1. Client requests POST /api/captcha/generate
   (Header: API-Version: v1)
    ↓
   Server returns: key + base64 image + click target hints

2. User clicks the target positions in the image; frontend/client collects click coordinates

3. Client requests POST /api/auth/login
   (Headers: API-Version: v1, Content-Type: application/json)
   Body: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Server:
   a. Parameter validation → 422
   b. Captcha validation → 422
   c. Credential validation → 401
   d. Account status check → 403
   e. Issue JWT (access + refresh) → 200
   f. Update last_login_at / last_login_ip
    ↓
   Client saves: access_token, refresh_token, expires_in

4. Subsequent requests carry the JWT
   Header: Authorization: Bearer <access_token>
    ↓
   AdminAuth middleware:
   a. Extract Bearer token
   b. Check blacklist (Redis jwt_blacklist:{md5}) → 401
   c. Decode JWT, validate expiry → 401
   d. Set $request->adminId = sub claim
    ↓
   AdminPermission middleware:
   a. Resolve the permission identifier for the resource route
   b. Query user roles → role permissions, match
   c. No permission → 403
    ↓
   Controller handles the request
    ↓
   Response + X-RateLimit-* headers

5. Refresh before access token expiry
   Client requests POST /api/auth/refresh
   Body: { refresh_token: "..." }
    ↓
   Server decodes refresh_token → issues new access + refresh
    ↓
   Client updates local tokens

6. Logout
   Client requests POST /admin/profile/logout
   Header: Authorization: Bearer <access_token>
    ↓
   Server:
   a. Decode JWT to get remaining TTL
   b. Write to Redis blacklist: jwt_blacklist:{md5(token)} = 1, TTL = remaining lifetime
   c. Return success
```

### JWT Structure

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, default TTL 7200 seconds (controlled by the JWT config `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, default TTL 1209600 seconds (controlled by the JWT config `refresh_expire`, i.e. 14 days)

### Security Management

- Passwords are stored as `PASSWORD_BCRYPT` hashes
- Sensitive fields (phone, email, id_card) are transparently encrypted/decrypted at the database layer via `erikwang2013/encryptable`
- API-layer IDs are encrypted in transit with `erikwang2013/hashids` to avoid exposing raw snowflake ID sequences
- SecurityFilter scans globally for XSS, SQL injection, path traversal, and command injection; the same IP gets a temporary blacklist of 15 minutes after 5 hits per 60 seconds
- Sensitive operations (deleting users, roles, permissions, configs) require the current logged-in user's password for re-confirmation
- Concurrent session limit: max 3 valid tokens per user; when a 4th device logs in, the oldest token is forcibly blacklisted
- Account lockout: 5 consecutive failed logins trigger a 15-minute account lockout; 429 is returned during the lockout

## 15. Deployment & Operations

### Docker Compose

The project root provides `docker-compose.yml`, orchestrating 5 services (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP is built via the `Dockerfile` (based on `php:8.3-cli`, with OPcache enabled).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` defines the GitHub Actions continuous integration pipeline:
- `php -l` syntax check
- PHPUnit unit tests
- `flutter analyze` static analysis

### Database Backup

The `database/backup/` directory provides backup and restore scripts:
- `backup.sh` — mysqldump + gzip compressed backup, auto-cleans backup files older than 30 days
- `restore.sh` — interactive restore, lists existing backups for selection

### Nginx Security Configuration

For production deployments, follow `docs/nginx-security.conf` for reverse-proxy security hardening.

## 16. Business API Endpoints (ERP)

All business endpoints are under the `/admin` group and pass through three middlewares: `AdminAuth` (JWT authentication), `AdminPermission` (RBAC permission check), and `OperationLog` (operation logging).

> Endpoint totals: Product(17) | Purchase(8) | Sales(6) | Inventory(6) | Finance(17) | CRM(13) | Workflow(6) | Notification(4) | Project(3) | HR(9) | Manufacturing(7) | Report(4) | Dashboard(3) | Client(2) | 105 endpoints total

Cross-module linkage endpoints are marked with 🔗.

### 16.1 Product Management

| Method | Path | Description |
|------|------|------|
| GET | /admin/product | Product list (paged+search+category/status filter) |
| POST | /admin/product | Create product (with SKUs and prices) |
| GET | /admin/product/{id} | Product detail (with category/brand/SKU/price/unit) |
| PUT | /admin/product/{id} | Update product |
| DELETE | /admin/product/{id} | Delete product (soft delete, requires password confirmation) |
| GET | /admin/category | Category list (tree) |
| POST | /admin/category | Create category |
| PUT | /admin/category/{id} | Update category |
| DELETE | /admin/category/{id} | Delete category |
| GET | /admin/brand | Brand list |
| POST | /admin/brand | Create brand |
| GET | /admin/warehouse | Warehouse list |
| POST | /admin/warehouse | Create warehouse |
| GET | /admin/location | Location list |
| GET | /admin/warehouse/{id}/locations | Locations under a warehouse |
| GET | /admin/supplier | Supplier list (ES search) |
| POST | /admin/supplier | Create supplier |
| GET | /admin/customer | Customer list (ES search) |
| POST | /admin/customer | Create customer |

### 16.2 Purchase

| Method | Path | Description |
|------|------|------|
| GET | /admin/purchase/apply | Purchase requisition list |
| POST | /admin/purchase/apply | Create purchase requisition |
| GET | /admin/purchase/order | Purchase order list |
| POST | /admin/purchase/order | Create purchase order |
| 🔗 POST | /admin/purchase/receive | Create receiving note (auto stock-in + AP generation) |
| GET | /admin/purchase/receive | Receiving note list |
| GET | /admin/purchase/receive/{id} | Receiving note detail |
| POST | /admin/purchase/return | Create return note |
| GET | /admin/purchase/settlement | Supplier settlement list |

### 16.3 Sales

| Method | Path | Description |
|------|------|------|
| GET | /admin/sales/quotation | Quotation list |
| POST | /admin/sales/quotation | Create quotation |
| GET | /admin/sales/order | Sales order list |
| POST | /admin/sales/order | Create sales order |
| 🔗 POST | /admin/sales/delivery | Create delivery note (auto stock-out + AR generation) |
| GET | /admin/sales/delivery | Delivery note list |
| GET | /admin/sales/settlement | Customer settlement list |

### 16.4 Inventory

| Method | Path | Description |
|------|------|------|
| GET | /admin/inventory | Live inventory (warehouse/location/batch/SKU dimensions) |
| GET | /admin/inventory/flow | Stock in/out flows |
| GET | /admin/inventory/transfer | Transfer note list |
| POST | /admin/inventory/transfer | Create transfer note |
| GET | /admin/inventory/check | Stock count task list |
| POST | /admin/inventory/check | Create stock count task |
| GET | /admin/inventory/alert | Inventory alert rules |

### 16.5 Finance

| Method | Path | Description |
|------|------|------|
| POST | /admin/finance/voucher | Create accounting voucher |
| GET | /admin/finance/ar-ap | AR/AP list |
| POST | /admin/finance/receipt | Create receipt voucher |
| POST | /admin/finance/payment | Create payment voucher |
| GET | /admin/finance/cash-journal | Cash & bank journal |
| GET | /admin/finance/expense | Expense reimbursement list |
| POST | /admin/finance/expense | Submit reimbursement request |
| GET | /admin/finance/report/profit | Income statement |
| GET | /admin/finance/general-ledger | General ledger (summarized by account + period) |
| GET | /admin/finance/subsidiary-ledger | Subsidiary ledger (per-account transaction details) |
| GET | /admin/finance/report/balance-sheet | Balance sheet (with auto-generation) |
| GET | /admin/finance/report/cash-flow | Cash flow statement (operating/investing/financing) |
| GET | /admin/finance/bank-account | Bank account list |
| GET/POST/PUT/DELETE | /admin/finance/asset | Fixed asset CRUD + depreciation accrual |
| GET/POST | /admin/finance/tax-rate | Tax rate config |
| GET | /admin/finance/tax-record | Tax records |
| GET/POST/PUT/DELETE | /admin/finance/currency | Currency management |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | Exchange rate management |
| GET/POST/PUT/DELETE | /admin/finance/budget | Budget management (with budget vs actual comparison) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | Cost centers (tree structure) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | Profit centers (tree structure) |

### 16.6 CRM

| Method | Path | Description |
|------|------|------|
| GET | /admin/crm/opportunity | Opportunity list |
| POST | /admin/crm/opportunity | Create opportunity |
| GET | /admin/crm/follow | Follow-up record list |
| POST | /admin/crm/follow | Create follow-up record |
| GET | /admin/crm/funnel | Funnel stage config |
| GET | /admin/crm/contact | Contact list |
| POST | /admin/crm/contact | Create contact |
| GET | /admin/crm/pool | Shared pool customer list |
| POST | /admin/crm/pool/claim/{id} | Claim a shared pool customer |
| POST | /admin/crm/pool/release/{id} | Release a customer to the shared pool |
| GET/POST | /admin/crm/pool/rules | Shared pool rule CRUD |
| GET | /admin/crm/contract | Contract list |
| POST | /admin/crm/contract | Create contract |
| GET | /admin/crm/contract/{id} | Contract detail |
| PUT | /admin/crm/contract/{id} | Update contract |
| DELETE | /admin/crm/contract/{id} | Delete contract |
| GET | /admin/crm/quotation | CRM quotation list |
| POST | /admin/crm/quotation | Create CRM quotation |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 Quotation to contract |
| GET/POST/PUT/DELETE | /admin/crm/campaign | Campaigns |
| GET/POST/PUT/DELETE | /admin/crm/ticket | Service tickets |
| POST | /admin/crm/ticket/{id}/assign | Assign ticket |
| POST | /admin/crm/ticket/{id}/resolve | Resolve ticket |
| GET/POST | /admin/crm/analytics/report | Customer analytics reports |
| GET/POST | /admin/crm/analytics/metric | Analytics metrics |

### 16.7 Approval Workflow

| Method | Path | Description |
|------|------|------|
| GET | /admin/workflow | Workflow definition list |
| POST | /admin/workflow | Create workflow definition |
| GET | /admin/workflow/{id} | Workflow detail |
| PUT | /admin/workflow/{id} | Update workflow |
| DELETE | /admin/workflow/{id} | Delete workflow |
| POST | /admin/workflow/{id}/submit | 🔗 Submit for approval (creates an approval instance) |
| POST | /admin/approval/{id}/approve | Approve |
| POST | /admin/approval/{id}/reject | Reject |
| POST | /admin/approval/{id}/withdraw | Withdraw |
| ANY | /admin/approval/my | My approvals (pending/approved) |

### 16.8 Notification

| Method | Path | Description |
|------|------|------|
| ANY | /admin/notification/my | My notification list (paged, reverse chronological) |
| POST | /admin/notification/{id}/read | Mark one as read |
| POST | /admin/notification/read-all | Mark all as read |
| ANY | /admin/notification/unread-count | Unread message count |

### 16.9 Project

| Method | Path | Description |
|------|------|------|
| GET | /admin/project | Project list |
| POST | /admin/project | Create project |
| GET | /admin/project/{id} | Project detail |
| PUT | /admin/project/{id} | Update project |
| DELETE | /admin/project/{id} | Delete project |
| GET | /admin/project/task | Task list |
| POST | /admin/project/task | Create task |
| PUT | /admin/project/task/{id} | Update task |
| DELETE | /admin/project/task/{id} | Delete task |
| GET | /admin/project/timesheet | Timesheet list |
| POST | /admin/project/timesheet | Log hours |
| PUT | /admin/project/timesheet/{id} | Update timesheet |
| DELETE | /admin/project/timesheet/{id} | Delete timesheet |

### 16.10 Human Resources (HR)

| Method | Path | Description |
|------|------|------|
| GET | /admin/hr/department | Department list (tree) |
| POST | /admin/hr/department | Create department |
| PUT | /admin/hr/department/{id} | Update department |
| DELETE | /admin/hr/department/{id} | Delete department |
| GET | /admin/hr/employee | Employee list |
| POST | /admin/hr/employee | Create employee |
| PUT | /admin/hr/employee/{id} | Update employee |
| DELETE | /admin/hr/employee/{id} | Delete employee |
| GET | /admin/hr/position | Position list |
| POST | /admin/hr/position | Create position |
| PUT | /admin/hr/position/{id} | Update position |
| DELETE | /admin/hr/position/{id} | Delete position |
| ANY | /admin/hr/attendance | Attendance record query |
| POST | /admin/hr/attendance/clock-in | Clock in |
| POST | /admin/hr/attendance/clock-out | Clock out |
| ANY | /admin/hr/leave | Leave list |
| POST | /admin/hr/leave | Submit leave request |
| GET | /admin/hr/leave/{id} | Leave detail |
| PUT | /admin/hr/leave/{id} | Update leave |
| DELETE | /admin/hr/leave/{id} | Delete leave |
| POST | /admin/hr/leave/{id}/approve | 🔗 Approve leave |
| GET | /admin/hr/salary | Payroll list |
| POST | /admin/hr/salary | Generate payroll |
| PUT | /admin/hr/salary/{id} | Update payroll |
| DELETE | /admin/hr/salary/{id} | Delete payroll |
| POST | /admin/hr/salary/{id}/pay | Disburse payroll |
| ANY | /admin/hr/salary-item | Salary item list |
| POST | /admin/hr/salary-item | Create salary item |
| GET | /admin/hr/salary-item/{id} | Salary item detail |
| PUT | /admin/hr/salary-item/{id} | Update salary item |
| DELETE | /admin/hr/salary-item/{id} | Delete salary item |

### 16.11 Manufacturing

| Method | Path | Description |
|------|------|------|
| GET | /admin/mfg/bom | BOM list |
| POST | /admin/mfg/bom | Create BOM |
| PUT | /admin/mfg/bom/{id} | Update BOM |
| DELETE | /admin/mfg/bom/{id} | Delete BOM |
| GET | /admin/mfg/production | Production order list |
| POST | /admin/mfg/production | Create production order |
| PUT | /admin/mfg/production/{id} | Update production order |
| DELETE | /admin/mfg/production/{id} | Delete production order |
| POST | /admin/mfg/production/{id}/start | Start production |
| POST | /admin/mfg/production/{id}/complete | Complete production |
| GET | /admin/mfg/routing | Routing list |
| POST | /admin/mfg/routing | Create routing |
| PUT | /admin/mfg/routing/{id} | Update routing |
| DELETE | /admin/mfg/routing/{id} | Delete routing |
| GET | /admin/mfg/workstation | Workstation list |
| POST | /admin/mfg/workstation | Create workstation |
| PUT | /admin/mfg/workstation/{id} | Update workstation |
| DELETE | /admin/mfg/workstation/{id} | Delete workstation |
| GET | /admin/mfg/mrp | MRP plan list |
| POST | /admin/mfg/mrp | Create MRP plan |
| PUT | /admin/mfg/mrp/{id} | Update MRP plan |
| DELETE | /admin/mfg/mrp/{id} | Delete MRP plan |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 Run MRP to generate purchase/production suggestions |

### 16.12 Report Builder

| Method | Path | Description |
|------|------|------|
| GET | /admin/report | Report template list |
| POST | /admin/report | Create report template |
| GET | /admin/report/{id} | Report template detail |
| PUT | /admin/report/{id} | Update report template |
| DELETE | /admin/report/{id} | Delete report template |
| POST | /admin/report/{id}/execute | Execute report to generate data |
| ANY | /admin/report/{id}/result | Report execution result |
| GET | /admin/report/schedule | Schedule list |
| POST | /admin/report/schedule | Create schedule |
| PUT | /admin/report/schedule/{id} | Update schedule |
| DELETE | /admin/report/schedule/{id} | Delete schedule |

### 16.13 Dashboard

| Method | Path | Description |
|------|------|------|
| GET | /admin/dashboard/sales | Sales board |
| GET | /admin/dashboard/inventory | Inventory board |
| GET | /admin/dashboard/finance | Finance board |

### 16.14 Client API

Client endpoints are mounted under the `/api` group and require the `API-Version` request header. Product information does not include the purchase price.

| Method | Path | Description |
|------|------|------|
| GET | /api/product | Product list (without purchase price) |
| GET | /api/product/{hashid} | Product detail (with retail/wholesale prices, without purchase price) |

### 16.15 OMS Order Management

| Method | Path | Description |
|------|------|------|
| GET | /admin/oms/order | OMS order list |
| POST | /admin/oms/order | Create OMS order |
| 🔗 POST | /admin/oms/order/{id}/allocate | Inventory allocation (reservation) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | Create fulfillment |
| POST | /admin/oms/order/{id}/cancel | Cancel order (release reservation) |
| POST | /admin/oms/rma/{id}/approve | Approve RMA |
| POST | /admin/oms/rma/{id}/refund | RMA refund |

### 16.16 WMS Warehouse Management

| Method | Path | Description |
|------|------|------|
| GET | /admin/wms/zone | Zone list (CRUD) |
| GET | /admin/wms/location | WMS location list (CRUD) |
| GET | /admin/wms/asn | ASN list (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | Complete receiving → auto-generate putaway tasks |
| POST | /admin/wms/putaway/{id}/complete | Confirm putaway → trigger stockIn |
| POST | /admin/wms/wave/{id}/release | Release wave → generate picking tasks |
| POST | /admin/wms/pick/{id}/start | Start picking |
| POST | /admin/wms/pick/{id}/confirm | Confirm picking |
| POST | /admin/wms/pack/{id}/complete | Complete packing |

### 16.17 TMS Transportation Management

| Method | Path | Description |
|------|------|------|
| GET | /admin/tms/carrier | Carrier list (CRUD) |
| GET | /admin/tms/service | Carrier services (CRUD) |
| GET | /admin/tms/freight-rate | Freight rates (CRUD) |
| GET | /admin/tms/shipment | Shipment list (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | Confirm shipping (stockOut+AR) |
| POST | /admin/tms/tracking/callback | Carrier tracking webhook |
| POST | /admin/tms/freight-invoice/{id}/pay | Freight invoice payment (generates AP) |

### 16.18 Dashboard Extensions

| Method | Path | Description |
|------|------|------|
| GET | /admin/dashboard/oms | OMS KPIs (pending/picking/today's shipments/RMA) |
| GET | /admin/dashboard/wms | WMS KPIs (awaiting receiving/putaway/picking/packing) |
| GET | /admin/dashboard/tms | TMS KPIs (pending shipment/in transit/delivered/exception) |

### 16.19 Cross-Module Linkage

The following endpoints trigger automatic cross-module linkage, marked with 🔗:

| Endpoint | Linkage Actions |
|------|---------|
| 🔗 POST /admin/purchase/receive | Automatically calls InventoryService.stockIn() to update inventory + recalculate moving weighted average cost; calls FinanceService.createAp() to generate AP records |
| 🔗 POST /admin/sales/delivery | Automatically calls InventoryService.stockOut() to deduct inventory (at moving weighted average cost); calls FinanceService.createAr() to generate AR records |
