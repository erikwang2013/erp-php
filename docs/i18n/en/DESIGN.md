# Open Admin Console — Design Document

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> For detailed Mermaid architecture diagrams, see [ARCHITECTURE.md](ARCHITECTURE.md) (auto-rendered in GitHub/GitLab/VS Code).

## 1. System Architecture

> **Feature list**: authentication (login/register/refresh/logout + account lockout + session limit) | dashboard (Redis cache) | user CRUD + batch + import | roles & permissions (RBAC) | system config | operation audit (8 platform sources) | files (upload + export + masking) | security (18-layer defense) | operations (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend Architecture

### 2.1 Layered Design

| Layer | Directory | Responsibility |
|---|------|------|
| Routing | `config/route.php` | URL-to-controller mapping, middleware binding, versioned routes |
| Middleware | `app/middleware/` | Attack blocking (SecurityFilter), rate limiting (RateLimit), authentication (JWT), authorization (RBAC), API version (ApiVersion) |
| Controllers | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (admin) + Captcha/Auth (API v1) | Request parameter validation, business logic invocation, response formatting |
| Business services | `app/service/` | Reusable business logic (reserved) |
| Data models | `app/model/` | ORM mapping, relationships, field encryption/decryption |
| Shared utilities | `app/common/` | Hashids, Snowflake, Encryption services |

### 2.2 Request Lifecycle

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID Lifecycle

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Data Encryption System

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Database Design

### 3.1 ER Relationships

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erp_operation_log
             (操作日志)

erp_system_config (系统配置) — 独立表
```

### 3.2 Core Table Structures

| Table | Field Count | Description |
|------|-------|------|
| `erp_admin_user` | 14 | Admin users; phone/email/id_card stored encrypted; soft delete supported |
| `erp_admin_role` | 7 | Roles; slug unique |
| `erp_admin_permission` | 10 | Permission tree (parent_id self-reference); type: 1=menu 2=button 3=API |
| `erp_admin_user_role` | 2 | User-role many-to-many pivot table |
| `erp_admin_role_permission` | 2 | Role-permission many-to-many pivot table |
| `erp_system_config` | 8 | Key-value config; group+key composite unique |
| `erp_operation_log` | 9 | Operation audit log (includes source origin) |

### 3.3 Primary Key Conventions

- Type: `BIGINT UNSIGNED NOT NULL`
- Characteristic: **non-auto-increment**, generated at the application layer by the Snowflake algorithm
- Advantages: globally unique, distributed-friendly, trend-increasing good for indexes, does not expose business volume
- Config: datacenter_id(0-31) + worker_id(0-31), supporting 1024 concurrent nodes

## 4. API Design

### 4.1 URL Conventions

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API Version Strategy

API versions are controlled via the request header, **not reflected in the URL path**:

```http
API-Version: v1
```

| Mechanism | Description |
|------|------|
| Default version | `v1` when no `API-Version` header is present |
| Validation | `ApiVersion` middleware validates; unsupported versions return 400 |
| Routing | the `v()` helper dynamically resolves the controller class by version |
| Directory | controllers organized by version: `app/api/{version}/controller/` |

Extension example — adding a v2 API:
1. Create `app/api/v2/controller/AuthController.php`
2. Add `'v2'` to the `SUPPORTED` constant of the `ApiVersion` middleware
3. Route definitions need no changes

```bash
# Using v1
curl -H "API-Version: v1" /api/auth/login

# Using v2
curl -H "API-Version: v2" /api/auth/login

# Without header, defaults to v1
curl /api/auth/login
```

### 4.3 Rate Limiting Strategy

Based on the Redis Sorted Set sliding-window algorithm, executed as atomic Lua scripts:

| Endpoint | Limit |
|------|------|
| Default | 60 times/minute/IP/route |
| POST /api/auth/login | 10 times/minute |
| POST /api/auth/register | 5 times/minute |

Over limit returns 429, with X-RateLimit-Limit / Remaining / Reset / Retry-After response headers.

### 4.4 Unified Response

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Meaning | Trigger Scenario |
|------|------|---------|
| 0 | Success | Normal response |
| 400 | Parameter error | Malformed request |
| 401 | Unauthenticated | Token missing/expired/invalid |
| 403 | Forbidden | User role lacks the required permission |
| 404 | Not found | Resource not found |
| 422 | Validation failed | Form parameters violate rules / password confirmation failed |
| 500 | Server error | Unexpected exception |

### 4.5 Authentication Flow (with click captcha)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Permission Model (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Second Confirmation for Sensitive Operations

Sensitive operations such as deleting users, roles, and permissions require passing the current user's password in the request body for identity re-verification:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

The frontend pops a confirmation dialog before triggering a delete, collects the user's password, then sends the request.

## 5. Frontend Design

### 5.1 Flutter Web Admin Console

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Features: collapsible sidebar, Material 3 dual themes, high-density data tables, dialog popups, hover interactions

### 5.2 HarmonyOS Mobile

Page routing:

| Page | Route | Description |
|------|------|------|
| LoginPage | `pages/LoginPage` | Username/password + click captcha login |
| DashboardPage | `pages/DashboardPage` | Stat cards + recent operations |
| UserListPage | `pages/UserListPage` | User list, search + pull-to-refresh + scroll-up loading |
| UserDetailPage | `pages/UserDetailPage` | Add/edit/view/delete (AlertDialog confirmation) |
| ProfilePage | `pages/ProfilePage` | Profile center, logout (AlertDialog confirmation) |

Data flow: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Security Design

### 6.1 Defense in Depth

| Layer | Measure |
|------|------|
| Method restriction | SecurityFilter HTTP method whitelist, only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed; non-standard methods return 405 |
| Attack blocking | SecurityFilter middleware, XSS/SQL injection/path traversal/command injection/CSRF detection and blocking |
| Human verification | Click Captcha, mandatory on login/registration |
| Account lockout | 5 consecutive login failures lock the account for 15 minutes; returns 429 while locked |
| Session limit | At most 3 concurrent Tokens per user; the oldest Token is auto-blacklisted when exceeded |
| Rate limiting | RateLimit middleware, Redis sliding window, Lua atomic |
| CSP | Content-Security-Policy header restricts resource origins, prevents XSS and data injection |
| Operation confirmation | Sensitive operations such as delete require entering the current user's password for second confirmation |
| Transport | HTTPS + JWT Bearer Token |
| Endpoint IDs | Hashids encryption, real IDs cannot be reverse-engineered externally |
| Request body | AES-256-CBC encryption of sensitive fields |
| Database | BIGINT primary keys (no exposed auto-increment values) |
| Database | AES-128-ECB encrypted storage of sensitive fields |
| Authentication | JWT HS256, 2h expiry + refresh token |
| Authorization | RBAC, method.path granularity permission control |
| Audit | OperationLog records all operations (including auto-detected source origin) |

### 6.2 Key Management

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Sensitive Data Protection

| Scenario | Field | Measure |
|------|------|------|
| List display | phone | masked: 138****1234 |
| List display | email | masked: a***@example.com |
| Detail view | phone/email | requires decryption endpoint |
| Excel export | phone/email | masked before export |
| PDF export | all fields | masked + non-removable copyright watermark |
| Storage | phone/email/id_card | encryptable encryption into ciphertext |

## 7. Export Design

### 7.1 Excel Export

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF Export

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Deployment Architecture

### 8.1 Recommended Topology

```
Nginx (:443 HTTPS) → webman worker × N (:8788) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (recommended for production)

The project root's `docker-compose.yml` orchestrates all services of the above topology:

| Service | Image/Build | Ports | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse proxy + static files + Gzip |
| `app` | local `Dockerfile` build | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Primary database, data volume persistence |
| `redis` | redis:7-alpine | 6379 | Cache / rate limiting / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Full-text search |

Before startup, replace the secrets in `docker-compose.yml` (`JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY`, etc.) with random strings.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions continuous integration is defined in `.github/workflows/ci.yml`:
- PHP syntax check (`php -l`)
- PHPUnit unit tests
- Flutter static analysis (`flutter analyze`)

### 8.4 Database Backup

`database/backup/backup.sh` — mysqldump + gzip backup, auto-cleans backups older than 30 days.
`database/backup/restore.sh` — interactively select and restore a backup.

### 8.5 Monitoring

The `GET /metrics` endpoint (`MetricsController`) exposes 5 gauge metrics in Prometheus text format: total HTTP requests, active users, database/Redis connection status, memory usage.

### 8.6 Environment Requirements

| Component | Minimum Version | Recommended Config |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ with OPcache enabled |
| MySQL | 8.0+ | 8.0+ master-slave replication |
| Elasticsearch | 7.x | 8.x 3-node cluster |
| Redis | 6.x | 7.x sentinel mode |
| Nginx | 1.20+ | reverse proxy + gzip + SSL |
| Flutter SDK | 3.41+ | latest stable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
