# 审计报告 — 2026-08-07

**项目**: erp-php（webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select）
**范围**: 整体运行测试、深入检查、P0/P1 问题修复
**指令**: "你整体测试一下，跑一下，深入检查看看还有问题或优化的地方没？"
**测试结果**: OK (135 tests, 799 assertions) — 全部通过

---

## 1. 测试与运行验证结果

| 项目 | 结果 |
|---|---|
| PHPUnit 全套 | 135 tests / 799 assertions 全部通过 |
| 服务启动 (port 8787→临时 8791) | 正常启动，无进程崩溃 |
| /health 健康检查 | code=0，database/redis/elasticsearch 字段齐全 |
| 限流链路 | /api/auth/login 连续请求返回 429 |
| JWT 黑名单 / 登录锁定 | 正常生效（Redis 修复后） |
| CS-Fixer | 31 个文件格式违规已修复 |
| PHPStan | 缓存损坏修复后恢复运行（851 个 ORM 魔术方法误报，75 条过期基线） |

---

## 2. P0 修复（运行时故障 — 全部已修复并验证）

### 2.1 support\Redis 类缺失 — 安全机制静默失效

- **现象**: `support\Redis` 不存在（composer.json 从未引入 webman/redis），9 个文件引用它。
- **根因**: 多处 `catch (\Throwable)` fail-open 设计吞掉了类缺失错误，导致限流、JWT 黑名单、登录锁定、封禁全部静默失效，接口"看似正常"但无任何防护。
- **修复**: `composer require webman/redis`；`config/redis.php` 环境变量化（REDIS_PASSWORD/HOST/PORT/DATABASE）。
- **验证**: /health 返回 `redis: ok`；限流测试返回 429。

### 2.2 ApiVersion 中间件编译失败 — 全部 /api 路由 500

- **现象**: `Interface "app\middleware\MiddlewareInterface" not found` — 缺少 `use Webman\MiddlewareInterface;`。
- **修复后二次错误**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` 是 `Webman\Http\Request` 的子类，违反参数逆变契约。
- **修复**: 改用 `Webman\Http\Request` / `Webman\Http\Response` 导入。

### 2.3 AdminAuth 中间件参数逆变 — /admin 路由 worker 崩溃

- **现象**: /admin/dashboard 触发 worker Empty reply（编译崩溃）。
- **根因**: 同 2.2 的参数逆变问题。
- **修复**: 改用 `Webman\Http\Request` / `Webman\Http\Response`（保留 `support\Redis`）。
- **验证**: 返回 401 JSON。

### 2.4 validator() 辅助函数不存在 — 登录 500

- **现象**: `Call to undefined function validator()`，99 个文件 105 处调用。
- **修复**: `composer require illuminate/validation`；`app/functions.php` 实现辅助函数（静态 $factory 缓存）。
- **踩坑**: `Factory::__construct()` 第一个参数必须是 `Translator` 而非 `ArrayLoader`。
- **遗留（P2）**: 错误消息未翻译（显示 `validation.required` 而非中文），需补充 zh_CN 语言包。

### 2.5 CORS 硬编码 + 预检响应丢失 CORS 头

- **修复**: 新增 `app/common/CorsPolicy.php`，从 `CORS_ALLOWED_ORIGIN` 环境变量读取白名单（逗号分隔），origin 回显；未命中不发送 CORS 头。
- **关键点**: `Route::fallback` 不走全局中间件链，OPTIONS 预检必须自行附加 CORS 头 — 已在 fallback 闭包中处理。
- **安全头**: 移除已废弃的 X-XSS-Protection；CSP 增加 `connect-src 'self'`。

### 2.6 FastRoute BadRouteException — 路由遮蔽

- **现象**: `Static route "/install" is shadowed by previously defined variable route`。
- **根因**: OPTIONS 通配路由 `/{path:.+}` 遮蔽后续静态路由；插件路由（apidoc）在 config/route.php 之后加载。
- **修复**: 移除通配路由，改用 `Route::fallback`（必须放在路由文件末尾）；`/crm/pool/rules` 从 resource 改为显式 GET 路由，`PoolController::rules()` 改为 public。

---

## 3. P1 修复（工程质量）

- **3.1 PHPStan 缓存损坏**: /tmp/phpstan/cache 来自已删除的 service/ 目录（微服务拆分残留），含旧绝对路径导致 phar 错误、CPU 0% 挂起。清除缓存并重装后恢复。851 个错误为 webman ORM 魔术方法误报；75 条基线路径指向不存在的 service/ 目录（P2）。
- **3.2 CS-Fixer**: 31 个文件空白/use 排序违规已修复。
- **3.3 测试同步**: `test_cors_response_is_assigned_correctly` 更新为断言新实现（withHeaders + CorsPolicy）。

---

## 4. 上一轮审计（08-04）遗漏根因

- 测试未覆盖**中间件类可加载性**和**路由可调用性**（class_exists / is_subclass_of 无法捕获 use 缺失与参数逆变）。
- 提交 b1fe2de 声称的 CORS/X-XSS 修复与实际代码不符 — 审计结论过度依赖提交信息而非运行验证。

---

## 5. 本轮变更清单（git status: 41 修改 + 2 新增）

| 文件 | 变更 |
|---|---|
| app/middleware/ApiVersion.php | 补 use Webman\MiddlewareInterface；参数类型改 Webman\Http |
| app/middleware/AdminAuth.php | 参数类型改 Webman\Http |
| app/middleware/Cors.php | 重构为使用 CorsPolicy；CSP/安全头更新 |
| app/common/CorsPolicy.php | **新增**：CORS 白名单策略 |
| config/route.php | fallback 路由 + /crm/pool/rules 修正 |
| app/controller/crm/PoolController.php | rules() 改 public |
| app/functions.php | 新增 validator() 辅助函数 |
| config/redis.php | **新增**（composer 生成后环境变量化） |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS 断言同步 |
| 其余 ~30 文件 | CS-Fixer 格式修复 |

---

## 6. P2 建议（环境/待办，未修复）

1. **.env DB_PASSWORD 为空** — MySQL root 认证失败，`database: unavailable`；需配置真实密码。
2. **端口 8787 冲突** — 被 cloud-php/service 占用（不同项目）；生产部署需区分。
3. **validator 中文错误消息** — 需安装语言包或自定义 messages。
4. **PHPStan 基线重建** — 75 条路径指向已删除的 service/ 目录，建议清理重建。
5. **fail-open 审计** — 建议全局排查 `catch (\Throwable)` 静默吞错点（本次已发现 1 处严重后果），改为 fail-closed 或显式日志。

---

*报告生成: 2026-08-07，服务已停止，端口已恢复 8787。*
