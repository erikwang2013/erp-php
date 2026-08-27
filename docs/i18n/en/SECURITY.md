# Security Architecture Design Document

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Defense-in-Depth Overview

The system adopts a 7-layer defense-in-depth model that filters malicious requests layer by layer from the outside in, ensuring that even if any single layer fails, subsequent defenses still provide backup.

The entire middleware chain executes in the following order (see `config/middleware.php`):

```
Request → Cors → SecurityFilter → RateLimit → [route-group middleware: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Layer | Middleware/Mechanism | Protection Target |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL injection / path traversal / command injection / CSRF attack blocking |
| 2 | Cors | Cross-origin security + security response header injection |
| 3 | RateLimit | Redis sliding-window rate limiting, brute-force protection |
| 4 | AdminAuth | JWT authentication + blacklist logout |
| 5 | AdminPermission | RBAC method.path granularity authorization |
| 6 | OperationLog | Operation audit + source-platform tracking |
| 7 | Data encryption | Hashids ID obfuscation + Encryptable DB encryption + EncryptionService transport encryption |

The frontend three layers (Flutter) have their own independent input validation; the backend does not trust them, and each layer defends independently.

---

## 2. Attack Detection Engine

### 2.0 HTTP Method Restriction

SecurityFilter validates the HTTP method before all attack detection, allowing only the following standard methods:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Non-standard methods (such as TRACE, CONNECT, PATCH, custom methods, etc.) directly return **405 Method Not Allowed** with an empty HTML response body, without entering subsequent attack detection or business logic.

This is the first line of defense in depth, effectively blocking:
- TRACE cross-site tracing attacks (XST)
- CONNECT tunnel proxy abuse
- Non-standard WebDAV method probing
- HTTP method enumeration by automated scanners

### 2.1 XSS Cross-Site Scripting

All regexes come from `SecurityFilter::PATTERNS['XSS']`, matched case-insensitively.

| Detection Pattern | Regex | Attacks Defended |
|----------|------|-----------|
| Script tags | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` and other space-containing variants |
| Event attributes | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Inline events such as `onclick="javascript:..."` |
| JS pseudo-protocol | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Template injection | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` server-side/Angular/Vue template injection |

### 2.2 SQL Injection

| Detection Pattern | Regex | Attacks Defended |
|----------|------|-----------|
| UNION combined query | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` database dump |
| OR always-true injection | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Table structure destruction | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Stored procedure calls | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL extended stored procedure command execution |
| Metadata probing | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL database structure probing |
| Comment bypass | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` comment bypass |

### 2.3 Path Traversal

| Detection Pattern | Regex | Attacks Defended |
|----------|------|-----------|
| Directory backtracking | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` multi-level directory backtracking |
| Sensitive file probing | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Null-byte truncation | `%00` | `../../../etc/passwd%00.jpg` bypassing extension validation |

### 2.4 Command Injection

| Detection Pattern | Regex | Attacks Defended |
|----------|------|-----------|
| Pipe/semicolon commands | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Backtick substitution | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() substitution | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Remote download pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF Cross-Site Request Forgery

The validation logic is implemented in `SecurityFilter::checkCsrf()`:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Comparison rules:
- Remove the `www.` prefix from Host and compare exactly against the Origin domain
- If Host is a parent domain of Origin (e.g. `Origin: app.example.com`, `Host: example.com` — triggers `str_contains($originHost, '.' . $hostOnly)`), pass through
- Neither exact match nor subdomain → return 403, judged as a CSRF attack

Note: non-browser clients (such as curl without Origin/Referer) pass through directly; CSRF protection only works in browser environments.

### 2.6 Malicious File Upload

| Detection Pattern | Regex | Attacks Defended |
|----------|------|-----------|
| Double-extension disguise | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` bypassing the whitelist |
| PHP extension | `\.php\s*$/m` | Passing `.php` paths directly in request parameters |

---

## 3. Attack Escalation and IP Blacklist

SecurityFilter has a built-in attack escalation mechanism to prevent the same IP from continuously scanning and attacking.

### Escalation Flow

```
1st scan hit → Redis INCR security_escalate:{ip} = 1, TTL=60s
2nd scan hit → INCR → 2
...
5th scan hit → INCR → 5
    → trigger ban: SETEX security_ban:{ip} 900 1
    → clear counter DEL security_escalate:{ip}
    → write security log: [SECURITY] IP banned 15min
```

### Behavior While Banned

Every request entering SecurityFilter first checks `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

All requests (including legitimate ones) from a banned IP directly return 403 for 15 minutes, completely skipping subsequent business logic.

### Configuration Constants

| Constant | Value | Meaning |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Hit count threshold within the 60-second window |
| ESCALATE_WINDOW | 60 | Counter window (seconds) |
| BAN_DURATION | 900 | Blacklist duration (seconds), i.e. 15 minutes |

### Security Log

File location: `runtime/logs/security.log`

Log format example:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Request Body Size Limit

`Content-Length > 10MB` directly returns 413 Payload Too Large, defending against DoS attacks with oversized request bodies.

### Content-Type Validation

POST/PUT requests **must** declare `Content-Type` as `application/json` or `application/x-www-form-urlencoded`, otherwise 415 Unsupported Media Type is returned. File upload requests (with a file field) skip this check.

---

## 4. Response Security Headers

All headers are injected in the `Cors` middleware, appended to every response via `$response->withHeaders()`.

| Header | Value | Purpose |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Allows any origin to make cross-origin requests (intranet admin console scenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Allowed method set |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Allowed custom headers |
| Access-Control-Max-Age | `86400` | Preflight request cache for 24 hours |
| X-Content-Type-Options | `nosniff` | Prevents browser MIME sniffing |
| X-Frame-Options | `DENY` | Forbids all iframe embedding, clickjacking protection |
| X-XSS-Protection | `1; mode=block` | Enables the browser's built-in XSS filter and blocks page rendering |
| Referrer-Policy | `strict-origin-when-cross-origin` | Full URL on same origin, only domain on cross-origin |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Disables camera/microphone/geolocation APIs site-wide |

OPTIONS preflight requests directly return an empty 204 response without entering the subsequent middleware chain.

### 4.2 Content-Security-Policy (CSP)

Injected in the Cors middleware together with the other security headers, providing defense in depth that restricts the sources of resources the browser may load and execute.

| Header | Value | Purpose |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restricts the sources of scripts/styles/images/connections/frames/forms etc. |
| X-Permitted-Cross-Domain-Policies | `none` | Forbids cross-domain policy file loading by Adobe Flash/PDF etc. |

CSP policy key points:
- `default-src 'self'`: by default only same-origin resources are allowed
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: allows same-origin scripts + inline scripts (required by Flutter Web) + eval (required by Flutter Web debugging)
- `frame-ancestors 'none'`: forbids iframe embedding by any page, double protection with X-Frame-Options: DENY
- `base-uri 'self'`: restricts the `<base>` tag to same-origin only
- `form-action 'self'`: restricts forms to submit to same-origin only

---

## 5. Rate Limiting Strategy

### Algorithm

Redis Sorted Set sliding window + Lua atomic script, key operations:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

The Lua script executes single-threaded on the Redis server, **naturally atomic**, eliminating the TOCTOU (Time-of-check to Time-of-use) race condition.

### Rate Limit Configuration

| Route | Limit | Window | Scenario |
|------|------|------|------|
| Default (all routes) | 60 times/minute | 60s | General API |
| `/api/auth/login` | 10 times/minute | 60s | Login (brute-force protection) |
| `/api/auth/register` | 5 times/minute | 60s | Registration (mass-registration protection; disabled by default, requires `REGISTRATION_ENABLED=1`) |

### Response Headers

When rate limiting triggers, HTTP 429 with a JSON body is returned:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

All responses (including normal ones) carry the following headers:

| Header | Description |
|----|------|
| X-RateLimit-Limit | Maximum requests allowed in the current window |
| X-RateLimit-Remaining | Requests remaining in the current window |
| X-RateLimit-Reset | Unix timestamp of the window reset |
| Retry-After | Present only when rate-limited, suggested wait seconds |

### Degradation Strategy

**fail-open** when Redis fails (connection timeout, unavailable, etc.):

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, allow all requests
}
```

Better to briefly lose rate-limit protection than to block normal business requests.

### 5.4 Account Lockout Mechanism

On top of rate limiting, the login endpoint adds an **account lockout** mechanism to prevent targeted brute-force attacks against specific users.

**Lockout flow**:

```
Login failure → Redis INCR account_lockout:{userId} TTL=900s
5 consecutive failures → Redis SETEX account_locked:{userId} 900 1
            → return 429 "Account locked, try again in 15 minutes"
            → clear counter DEL account_lockout:{userId}
```

**Behavior while locked**:

During the lockout period, all login requests directly return 429 without password validation, completely blocking brute-force attempts.

**Configuration constants**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Maximum consecutive failures |
| LOCKOUT_DURATION | 900 | Lockout duration (seconds), i.e. 15 minutes |

Note: account lockout is based on `userId` rather than IP, so attackers cannot bypass it by changing IPs. Combined with IP rate limiting (10/minute) it forms dual protection:
- IP level: 10/minute rate limit blocks distributed brute-force attacks
- Account level: lockout after 5 failures blocks targeted brute-force attacks

---

## 6. Authentication and Authorization

### 6.1 JWT Authentication

Implemented in the AdminAuth middleware, mounted on route groups requiring authentication.

**Parameter configuration** (`config/plugin/erikwang2013/jwt/jwt`, injected from `.env`):

| Parameter | Value | Description |
|------|-----|------|
| Algorithm | HS256 | HMAC-SHA256 symmetric signing |
| Secret | `JWT_SECRET` | Environment variable injection, must be changed in production |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Token extraction**: extracted from the `Authorization: Bearer <token>` header, stripping the `Bearer ` prefix to get the raw JWT.

**Authentication flow**:
1. Empty token → directly 401 `{"code": 401, "message": "未登录"}`
2. Check Redis blacklist `jwt_blacklist:{md5(token)}` → hit → 401 `Token已失效，请重新登录`
3. JWT decode → failure (expired/signature mismatch) → 401 `Token已过期或无效`
4. Success → inject `$request->adminId` and `$request->adminUsername`

**Blacklist mechanism**: when a user logs out, `md5(token)` is written to Redis with TTL set to the JWT's remaining validity. When Redis fails, the blacklist check is skipped (fail-open); a logged-out token can then still be used briefly, but the JWT's own short validity (2h) acts as a fallback protection.

### 6.2 Concurrent Session Limit

To prevent a leaked token from being abused across multiple devices, the system limits the number of valid tokens one user may hold simultaneously.

**Limit logic**:

```
Login success → issue new token
         → query the user's current valid token count: Redis SCARD user_tokens:{userId}
         → if count >= 3 (MAX_CONCURRENT_SESSIONS):
            → sort by creation time ascending, remove the oldest token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → add the new token to the set: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Configuration constant**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Maximum concurrent tokens per user |

**Kicked-offline scenario**: when a user logs in on a 4th device, the token on the 1st device is forcibly blacklisted and subsequent requests return 401 "Token已失效，请重新登录".

On logout, the current token is removed from the set. When a token naturally expires, its Redis key auto-expires and the set membership shrinks accordingly.

### 6.3 RBAC Permission Model

Implemented in the AdminPermission middleware.

**Data model**: User -> Role -> Permission three-tier association

- `erp_admin_user` (users table)
- `erp_admin_user_role` (user-role association table)
- `erp_admin_role` (roles table)
- `erp_admin_role_permission` (role-permission association table)
- `erp_admin_permission` (permissions table)

**Permission types**:
| type | Meaning | Example |
|------|------|------|
| 1 | Menu permission | Controls left navigation visibility |
| 2 | Button permission | Controls in-page action buttons (create/edit/delete) |
| 3 | API permission | Controls backend endpoint access |

API permission identifier format: `{method}.{path}`

For example:
- `post.admin/user` — create user
- `put.admin/user` — edit user
- `delete.admin/user` — delete user
- `get.admin/user` — view user list

**Authorization flow**:
1. `$request->adminId` empty → pass through (route has no auth prerequisite configured)
2. Get user → roles (skip disabled roles with `status=0`) → permission list
3. Super admin (`slug = '*'`) → pass through directly
4. Build `strtolower(method) . '.' . trim(path, '/')` → compare against the permission list
5. No match → 403 `{"code": 403, "message": "无权限访问"}`

**Second confirmation**: BaseController provides the `confirmPassword()` method; sensitive operations (deleting users, data export, etc.) additionally require entering the current password at the Controller layer, preventing unauthorized operations after session hijacking.

---

## 7. Audit Logs

### 7.1 Operation Logs

The OperationLog middleware automatically records operation logs for POST / PUT / DELETE requests. GET requests are not recorded.

**Recorded fields**:

| Field | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | Globally unique ID |
| user_id | `$request->adminId` | Operator ID, 0 when not logged in |
| action | `$request->method()` | Same as method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Request path |
| ip | `$request->getRealIp()` | Client real IP |
| source | detectSource() | Client source platform |
| input | Request body (masked JSON) | Submitted operation data |
| created_at | `date('Y-m-d H:i:s')` | Operation time |

**Sensitive field filtering**: recursively traverses the request body, replacing the values of the following fields with `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Source detection** (`detectSource()`): in priority order:

1. Prefer the `X-Client-Platform` custom header (explicitly declared by native clients)
2. Fall back to User-Agent string inference (`detectSource()` method detection order):

| Platform | UA Keywords |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Fallback default |

**Fault tolerance**: log write exceptions do not block business requests (silently swallowed by `catch (\Throwable)`).

### 7.2 Security Logs

**File location**: `runtime/logs/security.log`

**Recorded content**:
- Attack blocking logs: attack category, IP, path, field, source, payload fragment (first 200 characters)
- IP ban notifications: banned IP, trigger count

Log permissions use `FILE_APPEND | LOCK_EX` for concurrency-safe writes.

---

## 8. Data Protection

The system adopts a three-layer data protection strategy corresponding to the three stages of data flow.

### 8.1 Transport Layer — EncryptionService

`EncryptionService` uses the `erikwang2013/encryption` package to encrypt/decrypt sensitive fields in API requests/responses.

**Technical details**:
- Algorithm: `aes-256-cbc-hmac` (built-in HMAC signature against tampering)
- Key: `ENCRYPTION_KEY` environment variable, auto-aligned to 32 bytes
- Used for: transporting fields such as phone numbers and ID card numbers between the client and the API

**Masking utility methods**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (username longer than 2 chars) or `a**@example.com`

### 8.2 Storage Layer — Encryptable Cast

The `AdminUser` model uses the `Erikwang2013\Encryptable\Encryptable` Eloquent cast; the corresponding fields:

- `email` → cast to Encryptable, auto encrypt/decrypt
- `phone` → cast to Encryptable, auto encrypt/decrypt  
- `id_card` → cast to Encryptable, auto encrypt/decrypt

Automatically encrypted to ciphertext when written to the database, and decrypted to plaintext when read. The database column type is `VARCHAR(500)`, with ciphertext stored in base64 form.

**Key system**: uses the independent `ENCRYPTABLE_KEY` (separate from the transport-layer `ENCRYPTION_KEY`), so a leak of one key does not compromise the other layer.

Key rotation: the `ENCRYPTION_PREVIOUS_KEYS` environment variable supports a list of historical keys (comma-separated); historical keys are tried when reading old data, and the current key re-encrypts on write.

### 8.3 Presentation Layer — ID Obfuscation and Masking

**Hashids ID obfuscation**: `HashidsService` uses the `erikwang2013/hashids` package.

- Database BIGINT IDs returned by external APIs are encoded as hash strings (e.g. `xK3mN9qR2pL7wV8b`)
- Clients pass hash strings in requests; the backend automatically decodes them to raw IDs
- Salt injected via the `HASHIDS_SALT` environment variable; different salts produce completely different encode/decode results
- Minimum hash length 16, using a 62-character alphanumeric charset
- BaseController provides the `encodeId()`, `decodeId()`, `encodeIds()` convenience methods

**Export masking**: on Excel/PDF export (ExportController), sensitive fields are uniformly masked:
- Phone: `138****1234`
- Email: `a***@example.com`
- ID card: fully covered as `********`

---

## 9. Key Management

All keys are injected via `.env` environment variables; config files read them with `getenv()` and have built-in fallback defaults (safe only for development).

| Environment Variable | Purpose | Package | Production Requirement |
|----------|------|-----|---------|
| JWT_SECRET | JWT signing secret | erikwang2013/jwt-webman | 64+ character random string |
| JWT_ALGORITHM | JWT signing algorithm | same as above | keep HS256 |
| HASHIDS_SALT | ID encoding salt | erikwang2013/hashids | random string |
| SNOWFLAKE_DATACENTER_ID | Datacenter ID (0-31) | erikwang2013/snowflake-php | keep default for single datacenter |
| ENCRYPTION_KEY | API transport-layer encryption key | erikwang2013/encryption | 32-byte random string |
| ENCRYPTABLE_KEY | DB storage-layer encryption key | erikwang2013/encryptable | 32-byte random string, different from the transport key |

**Security requirements**:
- The `.env` file is in `.gitignore` and must never be committed to the repository
- `.env.example` is a public template file containing no real keys
- Production **must** replace all default keys with random strings
- It is recommended to generate keys with `openssl rand -base64 32`

### Key Storage Isolation

| Layer | Config Key | Key Environment Variable |
|----|--------|-------------|
| Transport encryption | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Storage encryption | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID obfuscation | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT signing | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

The system provides an RFC 9116-compliant security contact information endpoint at `/.well-known/security.txt`, making it easy for security researchers to quickly find a reporting channel when they discover vulnerabilities.

**Access method**:

```
GET /.well-known/security.txt
```

**Response content**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Field descriptions**:

| Field | Description |
|------|------|
| Contact | Security vulnerability report contact |
| Expires | File expiry time, needs periodic updates |
| Preferred-Languages | Preferred communication languages |
| Canonical | Canonical URL of this file |
| Policy | Security policy/vulnerability disclosure policy link |

This endpoint is not restricted by rate limiting, authentication, or other middleware; anyone can access it directly.

---

## 11. Nginx Security Configuration

The project provides `docs/nginx-security.conf` as a hardening reference configuration for the production Nginx reverse proxy.

**Included security measures**:

| Config Item | Purpose |
|--------|------|
| `server_tokens off` | Hides the Nginx version number |
| `client_max_body_size 10m` | Limits request body size, working with SecurityFilter |
| `limit_req_zone` | Request rate limiting at the Nginx level |
| `limit_conn_zone` | Concurrent connection limit |
| `add_header` security headers | Appends security headers such as X-XSS-Protection at the Nginx level |
| `if ($request_method)` | Rejects non-standard HTTP methods at the Nginx level |
| SSL/TLS configuration | Modern TLS 1.2/1.3 configuration, weak cipher suites disabled |
| Hide backend headers | `proxy_hide_header` removes sensitive headers such as the webman version |

**Usage**: merge the configuration from `docs/nginx-security.conf` into your Nginx server block, adjusting to your actual domain and certificate paths.

---

## 12. Threat Model

### 12.1 Protected Threats

| Threat Type | Attack Vector | Defense Layers |
|----------|---------|---------|
| HTTP method abuse | TRACE/TRACK XST attacks, CONNECT tunnel proxy, WebDAV method probing | SecurityFilter 405 method whitelist (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Targeted brute force | Repeated password attempts against specific users | Account lockout (lock after 5 failures for 15 min) + RateLimit (login 10/min) + Captcha |
| Brute force | Distributed IPs repeatedly trying usernames/passwords | RateLimit (login 10/min) + Captcha |
| XSS cross-site scripting | `<script>`, onerror, javascript: | SecurityFilter (5 patterns) + X-XSS-Protection response header + CSP |
| SQL injection | UNION SELECT, OR 1=1, comment bypass | SecurityFilter (6 patterns) + Eloquent ORM parameterized queries |
| CSRF cross-site request forgery | Malicious websites sending requests on behalf of users | SecurityFilter Origin/Referer validation |
| Path traversal | `../../etc/passwd` | SecurityFilter path traversal patterns + UploadController extension whitelist |
| Command injection | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 patterns) |
| Session hijacking | Stealing JWT tokens | Short JWT validity (2h) + blacklist logout + second password confirmation for sensitive operations |
| ID enumeration | Traversing numeric IDs to guess data volumes | Hashids obfuscation to random strings |
| Data leakage | DB dump / man-in-the-middle / log leakage | Three-layer encryption/masking + OperationLog sensitive field filtering |
| DoS attacks | Oversized request bodies / high-frequency requests | 10MB request body limit + RateLimit 60/min + IP blacklist |
| Privilege escalation | Low-privilege users accessing admin endpoints | RBAC method.path granularity authorization |
| File upload attacks | shell.php.png double extensions | SecurityFilter malicious file detection |

### 12.2 Known Limitations

| Limitation | Impact Scope | Mitigation |
|------|---------|---------|
| CSRF protection only works for browsers | Non-browser clients (curl, Postman, mobile apps) can skip Origin/Referer checks | Non-browser clients are naturally not subject to CSRF; rely on JWT authentication instead of cookies |
| Rate limiting and blacklist degrade to fail-open when Redis is down | Attackers can bypass rate limiting and high-frequency blocking | Monitor Redis availability with alerts; short JWT validity as fallback |
| No standalone WAF engine | SecurityFilter uses `@preg_match` regex matching, not a dedicated WAF rules engine | Production recommends fronting Nginx ModSecurity or Cloudflare WAF |
| Stateless JWT cannot be actively revoked | Tokens cannot be revoked server-side before expiry (except via blacklist) | Blacklist + short 2h TTL reduces the risk window |
| IP blacklist is memory-only storage | Blacklist lost after Redis restart | Ban duration is only 15 minutes, limited impact |
| No special rate limit for admin endpoints | Admin endpoints share the 60/min default limit with regular endpoints | Admin operations are naturally low-frequency, no differentiation needed for now |
| `@preg_match` suppresses errors | Silently fails on malformed regex input | `preg_last_error()` could add monitoring, not currently implemented |
