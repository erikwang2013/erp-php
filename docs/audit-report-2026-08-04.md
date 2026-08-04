# ERP 系统全面审查报告

**日期**: 2026-08-04（已修复）  
**项目**: erp-php (webman/workerman)  
**PHP**: 8.3.7 | **测试**: 116 pass / 712 assertions / 0 fail  
**分支**: main

---

## 总览

| 维度 | 评分 | 状态 |
|------|------|------|
| 测试覆盖 | A- | 116 测试全过，新增 26 个 OMS/WMS/TMS 服务测试 |
| 安全防护 | A | 多层防御，已修复 TMS 回调认证 |
| 代码质量 | A- | Mass assignment 已修复，code 生成改为 Snowflake |
| 生态配置 | A- | Docker/NGINX/翻译 完整，新增 JSON 异常处理 |
| OMS/WMS/TMS | A- | 业务完整性好，所有问题已修复 |

### 已修复问题

| # | 问题 | 修复 |
|---|------|------|
| 1 | Mass Assignment | 18 个控制器改用 `fillModelFromRequest()` |
| 2 | TMS 回调认证 | 移到公开路由 + `TrackingSignature` HMAC 中间件 |
| 3 | 物流单号冲突 | 全部 Service/Controller 改用 `$this->generateId()` |
| 4 | OmsOrder keyword | 启用 code/channel_order_no 模糊搜索 |
| 5 | 地址快照 JSON cast | TmsShipment 已有 `'array'` cast |
| 6 | OMS/WMS/TMS 测试 | 新增 26 测试 (AddressValidator/Service 方法/中间件) |
| 7 | JSON 异常处理 | `ApiHandler` 对 API/admin 路由返回 JSON |
| 8 | PHPStan 故障 | 需手动 `composer reinstall phpstan/phpstan` |

---

## 一、测试结果

```
PHPUnit 12.5.25 — 90 tests, 661 assertions, 全部通过
```

**覆盖范围**:
- 基础架构: Health、Encryption、Hashids、Snowflake、Captcha、DB Schema、Env Config
- 安全模式: SecurityPattern、ControllerPattern
- 后端增强: BackendEnhancement（路由辅助函数、中间件接口、安全加固）

**缺失覆盖**:
- OMS/WMS/TMS 模块: 0 测试
- 采购/销售/库存/财务: 0 测试
- Controller 集成测试: 0（仅模式验证）
- API 端点测试: 0

**建议**: 为 OMS/WMS/TMS 各 Service 类至少添加单元测试，覆盖核心业务流程。

---

## 二、安全防护审查

### 2.1 已实现的安全机制

| 层级 | 机制 | 实现位置 |
|------|------|----------|
| 网络层 | Nginx 限流、请求体限制、安全头 | `docs/nginx-security.conf` |
| 中间件 | XSS/SQL注入/路径遍历/命令注入检测 | `app/middleware/SecurityFilter.php` |
| 中间件 | CSRF（Origin/Referer 校验） | `app/middleware/SecurityFilter.php` |
| 中间件 | 滑动窗口速率限制（Lua 原子化） | `app/middleware/RateLimit.php` |
| 中间件 | JWT 认证 + 黑名单 | `app/middleware/AdminAuth.php` |
| 中间件 | RBAC 权限控制（60s 缓存） | `app/middleware/AdminPermission.php` |
| 中间件 | 安全响应头（CSP/X-Frame/HSTS 等） | `app/middleware/Cors.php` |
| 应用层 | 敏感字段脱敏（phone/email） | `app/common/EncryptionService.php` |
| 应用层 | 操作日志敏感字段过滤 | `app/middleware/OperationLog.php` |
| 数据层 | PII 字段加密存储（email/phone/id_card） | `app/model/AdminUser.php` (Encryptable) |
| 数据层 | 悲观行锁防并发超卖 | `app/service/inventory/InventoryService.php` |
| 数据层 | 移动加权平均成本计算 | `app/service/inventory/InventoryService.php` |
| 认证 | bcrypt 密码哈希 | 全局 `password_hash(PASSWORD_BCRYPT)` |
| 认证 | 敏感操作二次密码确认 | `app/admin/controller/BaseController.php` |
| 传输 | AES-256-CBC API 加密 | `app/common/EncryptionService.php` |
| ID | Snowflake 分布式ID + Hashids 外部混淆 | app/common |
| 合规 | security.txt (RFC 9116) | `config/route.php` |

### 2.2 需关注的问题

#### 问题 1: Mass Assignment — 控制器绕过 $fillable（中危）

**位置**: 所有 Controller `store()`/`update()` 方法  
**示例**: `app/controller/oms/OrderController.php:75-79`

```php
$data = $request->all();
unset($data['id']);
foreach ($data as $k => $v) {
    if ($v !== null) $item->$k = $v;  // 直接属性赋值，绕过 $fillable
}
```

**风险**: 虽然 Model 定义了 `$fillable`，但直接属性赋值绕过 Eloquent 保护。攻击者可注入 `created_at`、`updated_at` 等内部字段。

**建议**: 使用 `$item->fill($request->only($item->getFillable()))` 替代直接赋值。

#### 问题 2: 物流轨迹回调接口认证不匹配（中危）

**位置**: `config/route.php` + `app/service/tms/TrackingService.php`

```php
// route.php — Tracking 回调在 admin 路由组内，需要 JWT 认证
Route::post('/tms/tracking/callback', [app\controller\tms\TrackingController::class, 'callback']);

// TrackingService::processWebhook() 无签名验证
```

**风险**: 外部承运商无法通过 JWT 认证调用回调接口，运单状态无法自动更新。

**建议**: 将回调路由移到公开接口组，并增加签名验证（HMAC-SHA256）：
```php
// 在 /api 公开组添加
Route::post('/tms/tracking/callback', [app\controller\tms\TrackingController::class, 'callbackWebhook'])
    ->middleware([app\middleware\ApiVersion::class, app\middleware\TrackingSignature::class]);
```

#### 问题 3: 装运单号基于时间戳可能冲突（低危）

**位置**: `app/service/tms/TmsShipmentService.php:30`

```php
$shipment->code = 'SHP' . date('YmdHis') . rand(100, 999);
```

**风险**: 高并发下可能生成重复单号。WMS ASN/收货单/上架任务/拣货任务/打包任务/波次等也使用相同模式。

**建议**: 使用 Snowflake ID 作为 code，或采用数据库唯一约束 + 重试机制。

### 2.3 安全改进建议

| 优先级 | 建议 |
|--------|------|
| 高 | TMS 回调接口改为公开路由 + HMAC 签名验证 |
| 中 | 所有 Controller 使用 `$fillable`/`only()` 防护 mass assignment |
| 中 | 为财务、薪资等敏感模块增加独立 RateLimit |
| 低 | Session 切换为 Redis 驱动（生产环境） |
| 低 | jwt_secret/enryption_key 等敏感配置项，生产环境务必修改默认值 |

---

## 三、生态配置审查

### 3.1 完整度

| 配置项 | 状态 | 备注 |
|--------|------|------|
| Docker Compose (Nginx/App/MySQL/Redis/ES) | 通过 | 含 healthcheck，网络隔离 |
| Dockerfile (PHP 8.3 + OPcache) | 通过 | 多阶段构建，生产优化 |
| Nginx 安全加固 | 通过 | 限流/TLS/HSTS/安全头 |
| .env 模板 | 通过 | 所有配置项有中文注释 |
| .gitignore | 通过 | runtime/.env/vendor 均已排除 |
| phpunit.xml | 通过 | PHPUnit 12.5 配置 |
| phpstan.neon | 故障 | 运行崩溃（phar 内部错误） |
| 翻译 zh_CN | 通过 | 覆盖所有 OMS/WMS/TMS 模块 |
| 翻译 en | 通过 | 覆盖所有模块 |
| security.txt | 通过 | RFC 9116 合规 |
| 路由配置 | 通过 | 分组 + 版本化中间件 |
| 异常处理 | 需关注 | 仅默认 Handler，无自定义 JSON 错误响应 |

### 3.2 问题 4: PHPStan 无法运行（低危）

```
Internal error: phar://...phpstan.phar/.../soap.stub is not a file
```

**建议**:
```bash
rm -rf vendor/phpstan vendor/bin/phpstan*
composer install
```

### 3.3 问题 5: 无自定义异常处理 JSON 响应（低危）

**位置**: `config/exception.php`

```php
return ['' => support\exception\Handler::class];
```

API 异常时返回 HTML 而非 JSON，影响客户端体验。

**建议**: 为 API 路由增加 JSON 异常处理。

---

## 四、OMS/WMS/TMS 模块专项审查

### 4.1 结构完整性

| 模块 | Controllers | Services | Models | Migrations | 权限种子 |
|------|-------------|----------|--------|------------|----------|
| OMS | 4 (Order/Fulfillment/Rma/Channel) | 3 (Order/Allocation/Rma) | 7 | 通过 | 通过 |
| WMS | 8 (Zone/Loc/ASN/Recv/Putaway/Wave/Pick/Pack) | 3 (Inbound/Wave/Outbound) | 13 | 通过 | 通过 |
| TMS | 6 (Carrier/Service/Rate/Ship/Track/Invoice) | 3 (Shipment/Freight/Tracking) | 7 | 通过 | 通过 |

### 4.2 业务逻辑审查

#### 库存管理
- 入库: stockIn → 流水 + 实时库存 + 加权平均成本
- 出库: stockOut → 校验 + 流水 + 扣减
- 预占: reserve → ATP 校验 → 逻辑锁
- 消耗: consume → 预占转实际出库
- 释放: release → 取消预占
- 全部使用 `lockForUpdate()` 悲观锁
- **评价**: 财务级严谨性，移动加权平均成本算法正确。

#### OMS 订单生命周期
```
创建 → 库存分配 → 创建履约 → WMS出库 → TMS发货 → 签收
         ↓
       取消（释放预占）
```

#### RMA 退货流程
```
创建 → 审批 → 客户寄回 → 收货入库 → 退款
         ↓
       拒绝
```

#### WMS 入库流程
```
ASN(预到货) → 收货 → 自动生成上架任务 → 上架确认 → stockIn
```

#### WMS 出库流程（波次模式）
```
OMS订单 → 波次聚合 → 释放波次 → 拣货 → 打包 → TMS运单
```

#### TMS 运费计算
- 支持费率卡匹配（按重量区间 + 目的国）
- 支持燃油附加费百分比
- 支持多承运商比价（rateShop）

### 4.3 问题 6: OmsOrder::index() 中 keyword 参数未使用（低危）

**位置**: `app/controller/oms/OrderController.php:27`

```php
$keyword = $request->input('keyword', '');  // 获取但从未用于查询过滤
```

**建议**: 增加关键词搜索逻辑（code、channel_order_no 字段模糊匹配）。

### 4.4 问题 7: 地址快照未设置 JSON cast（建议）

**位置**: `app/model/TmsShipment.php`

`dest_address_snapshot` 和 `origin_address_snapshot` 在 `$casts` 中未包含 `'json'`，存取时需手动编解码。

---

## 五、代码质量

### 5.1 良好实践

- 全部使用 `declare(strict_types=1)`
- 统一使用 Snowflake + Hashids ID 体系
- 分层明确: Middleware → Controller → Service → Model
- Redis 故障采用 fail-open 策略（不阻断业务）
- 数据库操作使用事务保证一致性
- 翻译系统支持中英双语
- 版权声明统一规范

### 5.2 命名一致性

Controller/Service/Model 命名遵循统一约定：
- Controller: `app/controller/{module}/{Entity}Controller`
- Service: `app/service/{module}/{Entity}Service`
- Model: `app/model/{Module}{Entity}`

### 5.3 日志

- 安全日志写入 `runtime/logs/security.log`（`@file_put_contents` 抑制错误）
- 操作日志写入数据库表 `erik_operation_log`
- 建议: 应用日志改用 Monolog（已引入依赖），按级别分流

---

## 六、改进建议优先级汇总

### 高优先级
1. **TMS 回调接口认证修正**: 将 `/tms/tracking/callback` 移到公开路由 + HMAC 签名验证
2. **为 OMS/WMS/TMS Service 编写测试**: 至少覆盖核心业务流程

### 中优先级
3. **Mass Assignment 防护**: Controller 使用 `$fill()` + `only()` 替代直接赋值
4. **修复 PHPStan**: 重装 vendor 后运行静态分析
5. **OmsOrder keyword 搜索**: 启用已定义但未使用的 keyword 参数

### 低优先级
6. **物流单号改用 Snowflake**: 避免时间戳并发冲突
7. **异常处理 JSON 化**: API 异常返回 JSON 格式错误
8. **Session 生产配置**: 切换为 Redis 驱动
9. **地址快照 JSON cast**: 模型增加 json cast
10. **日志升级**: 使用 Monolog 替代 file_put_contents

---

## 七、总结

系统整体架构设计优良，安全防护覆盖全面（网络层/Nginx + 应用层/中间件 + 数据层/加密 + 业务层/悲观锁）。90 个测试全部通过。OMS/WMS/TMS 模块业务逻辑完整，库存管理达到财务级严谨性。

主要待改进项集中在：TMS 回调认证修正、mass assignment 防护规范化、新模块测试覆盖。无阻塞性安全漏洞。

---

*报告由 Claude Code 自动生成 | 2026-08-04*
