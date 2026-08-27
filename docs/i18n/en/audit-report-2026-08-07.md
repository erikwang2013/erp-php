# Audit Report — 2026-08-07

**Project**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**Scope**: overall runtime testing, deep inspection, P0/P1 issue fixes
**Instruction**: "Run the whole system, test it, and inspect deeply for remaining problems or optimization opportunities."
**Test results**: OK (135 tests, 799 assertions) — all passing

---

## 1. Test and Runtime Verification Results

| Item | Result |
|---|---|
| Full PHPUnit suite | 135 tests / 799 assertions all passing |
| Service startup (port 8787→temporary 8791) | Started normally, no process crashes |
| /health health check | code=0, database/redis/elasticsearch fields all present |
| Rate limiting chain | Consecutive /api/auth/login requests return 429 |
| JWT blacklist / login lockout | Working correctly (after Redis fix) |
| CS-Fixer | 31 files with formatting violations fixed |
| PHPStan | Recovered after fixing corrupted cache (851 ORM magic-method false positives, 75 stale baseline entries) |

---

## 2. P0 Fixes (Runtime Failures — All Fixed and Verified)

### 2.1 Missing support\Redis Class — Security Mechanisms Silently Failed

- **Symptom**: `support\Redis` does not exist (webman/redis was never added to composer.json), and 9 files reference it.
- **Root cause**: multiple `catch (\Throwable)` fail-open designs swallowed the class-missing error, silently disabling rate limiting, the JWT blacklist, login lockout, and bans — the endpoints "looked normal" but had no protection at all.
- **Fix**: `composer require webman/redis`; `config/redis.php` environment-variable-driven (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Verification**: /health returns `redis: ok`; rate limit tests return 429.

### 2.2 ApiVersion Middleware Compilation Failure — All /api Routes 500

- **Symptom**: `Interface "app\middleware\MiddlewareInterface" not found` — missing `use Webman\MiddlewareInterface;`.
- **Secondary error after fix**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` is a subclass of `Webman\Http\Request`, violating the parameter contravariance contract.
- **Fix**: switched to `Webman\Http\Request` / `Webman\Http\Response` imports.

### 2.3 AdminAuth Middleware Parameter Contravariance — /admin Routes Crashed Workers

- **Symptom**: /admin/dashboard triggered worker Empty reply (compilation crash).
- **Root cause**: same parameter contravariance issue as 2.2.
- **Fix**: switched to `Webman\Http\Request` / `Webman\Http\Response` (kept `support\Redis`).
- **Verification**: returns 401 JSON.

### 2.4 validator() Helper Function Missing — Login 500

- **Symptom**: `Call to undefined function validator()`, 105 call sites in 99 files.
- **Fix**: `composer require illuminate/validation`; implemented the helper in `app/functions.php` (static $factory cache).
- **Gotcha**: `Factory::__construct()`'s first parameter must be a `Translator`, not an `ArrayLoader`.
- **Remaining (P2)**: error messages are not translated (showing `validation.required` instead of Chinese); a zh_CN language pack needs to be added.

### 2.5 Hardcoded CORS + Preflight Responses Missing CORS Headers

- **Fix**: added `app/common/CorsPolicy.php`, reading the whitelist (comma-separated) from the `CORS_ALLOWED_ORIGIN` environment variable, echoing the origin; no CORS headers sent on miss.
- **Key point**: `Route::fallback` does not go through the global middleware chain, so the OPTIONS preflight must attach CORS headers itself — handled in the fallback closure.
- **Security headers**: removed the deprecated X-XSS-Protection; CSP adds `connect-src 'self'`.

### 2.6 FastRoute BadRouteException — Route Shadowing

- **Symptom**: `Static route "/install" is shadowed by previously defined variable route`.
- **Root cause**: the OPTIONS catch-all route `/{path:.+}` shadowed subsequent static routes; plugin routes (apidoc) load after config/route.php.
- **Fix**: removed the catch-all route, switched to `Route::fallback` (must be placed at the end of the route file); `/crm/pool/rules` changed from resource to an explicit GET route, `PoolController::rules()` changed to public.

---

## 3. P1 Fixes (Engineering Quality)

- **3.1 Corrupted PHPStan cache**: /tmp/phpstan/cache came from the deleted service/ directory (microservice-split leftover), containing old absolute paths causing phar errors and 0% CPU hangs. Recovered after clearing the cache and reinstalling. 851 errors are webman ORM magic-method false positives; 75 baseline paths point to the nonexistent service/ directory (P2).
- **3.2 CS-Fixer**: 31 files with whitespace/use-ordering violations fixed.
- **3.3 Test sync**: `test_cors_response_is_assigned_correctly` updated to assert the new implementation (withHeaders + CorsPolicy).

---

## 4. Root Causes Missed by the Previous Audit (08-04)

- Tests did not cover **middleware class loadability** and **route callability** (class_exists / is_subclass_of cannot catch missing use imports and parameter contravariance).
- Commit b1fe2de's claimed CORS/X-XSS fixes did not match the actual code — the audit conclusion relied too much on commit messages rather than runtime verification.

---

## 5. This Round's Change List (git status: 41 modified + 2 added)

| File | Change |
|---|---|
| app/middleware/ApiVersion.php | Added use Webman\MiddlewareInterface; parameter types changed to Webman\Http |
| app/middleware/AdminAuth.php | Parameter types changed to Webman\Http |
| app/middleware/Cors.php | Refactored to use CorsPolicy; CSP/security headers updated |
| app/common/CorsPolicy.php | **New**: CORS whitelist policy |
| config/route.php | fallback route + /crm/pool/rules fix |
| app/controller/crm/PoolController.php | rules() changed to public |
| app/functions.php | Added validator() helper function |
| config/redis.php | **New** (environment-variable-driven after composer generation) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS assertions synced |
| ~30 other files | CS-Fixer formatting fixes |

---

## 6. P2 Suggestions (Environment/Backlog, Not Fixed)

1. **Empty .env DB_PASSWORD** — MySQL root authentication fails, `database: unavailable`; a real password must be configured.
2. **Port 8787 conflict** — occupied by cloud-php/service (a different project); production deployments need to distinguish.
3. **validator Chinese error messages** — need a language pack or custom messages.
4. **PHPStan baseline rebuild** — 75 paths point to the deleted service/ directory; recommend cleaning and rebuilding.
5. **fail-open audit** — recommend a global review of silently swallowed `catch (\Throwable)` points (this round found 1 with serious consequences), switching to fail-closed or explicit logging.

---

*Report generated: 2026-08-07, service stopped, port restored to 8787.*
