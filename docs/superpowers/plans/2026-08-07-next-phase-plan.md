# 下一阶段（P4 / 演进期 1.1）项目规划

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 编制：系统架构师 ｜ 日期：2026-08-07 ｜ 依据：三份前期调研（规划与差距 / 后端与质量 / 前端）+ 实地抽查复核
> 状态：草案（待评审）｜ 目标版本：1.1（演进期）

---

## 1. 阶段定位

P0~P3 路线图已全部交付：22 业务模块、163 表、121 控制器、24 服务、161 模型、12 中间件；
Flutter 96 页 + HarmonyOS 34 页；综合评分 89/100。**本阶段不再新增业务域**，而是把"已实现但未闭环"
的能力补齐、治理质量债、消除文档漂移，产出可长期维护的 **1.1 演进版**。

三个核心判断（均经抽查证实）：

1. **大量能力"存在但未生效"**：TenantScope 中间件与模型 trait 未在 `config/middleware.php` 注册（多租户为空壳）；
   队列配了 redis/rabbitmq 双驱动但 `config/process.php` 无消费进程；WebSocket 连接不校验 JWT；
   Flutter 仪表盘 OMS/WMS/TMS 统计为硬编码假值，而后端 `/dashboard/oms|wms|tms` 端点已存在却未被调用；
   前端调用不存在的通知端点 `/admin/notification/my/read`（后端实为 `/admin/notification/read-all`）。
2. **质量与安全欠账**：11 个业务模块零测试；PHPStan level 5 但基线抑制 974 条错误；137 测试全为纯单测，无集成/E2E/覆盖率；
   `.env.docker` 大量弱密钥；CI 只有 PHP 作业，无任何前端质量门禁。
3. **文档系统性漂移**：测试数 132/779→135/799→137/805 三版不一致；FUNCTIONS.md 附录与实测差距巨大；
   EDITIONS.md 数字自相矛盾；lite/standard/full 三分支落后 main 20~41 commits。

**原则**：先补"已实现但未闭环"（死端点、未接线的 TenantScope/队列、mock 仪表盘），再补测试与质量门禁，
再优化结构与文档。所有任务小而明确，可在单 agent 会话内完成；拿不准的标注"待验证"。

---

## 2. 差距分析（汇总）

将三份调研差距归纳为 **6 个工作组**。每条给出证据路径。

### 工作组 A：业务闭环补全（优先级最高）

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| A1 | 通知"全部标记已读"前端调用不存在端点 | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` 调 `/admin/notification/my/read`；后端路由为 `config/route.php:250` 的 `POST /admin/notification/read-all` | 已证实 |
| A2 | 仪表盘 OMS/WMS/TMS 统计为 mock 假值，请求未带 JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（独立 Dio `baseUrl: http://localhost:8787`，无拦截器；`omsStats/wmsStats/tmsStats` 硬编码；注释 "Mock values for now"）；后端真实端点 `config/route.php:231-233` | 已证实 |
| A3 | TenantScope 中间件与模型 trait 未接线，多租户为空壳 | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` 存在；`config/middleware.php` 全局链仅注册 Locale/Cors/SecurityFilter/RateLimit/TracingId，route.php 各分组亦无引用 | 已证实 |
| A4 | 队列双驱动但无消费进程，端到端未生效 | `config/queue.php`（默认 redis，可选 rabbitmq）；`config/process.php` 仅 webman/socket/monitor 三个进程 | 已证实 |
| A5 | WebSocket 无鉴权 | `app/process/WebSocket.php:23` 注释 "could validate JWT here"；`:47-50` auth 消息直接返回 success:true，不校验 token | 已证实 |
| A6 | HarmonyOS 25 个列表页分页参数失效（单引号内 `${this.page}` 不插值） | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24`（已抽查）；另 24 处同模式 | 已证实（清单待全量核对） |
| A7 | 业务动作端点大面积未接入前端（结算/三表/履约/审批/薪资计算等） | 覆盖矩阵调研结论；例：采购/销售缺结算页、财务缺 13 端点、CRM 缺 follow/funnel/合同流转 | 待验证（需逐模块核对清单） |
| A8 | 大量业务页表单仅通用 name/code 字段 | 调研结论（创建销售订单/记账凭证只填名称编码） | 待验证（需逐页核对） |

### 工作组 B：测试体系重建

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| B1 | 11 个业务模块零测试：crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | `tests/` 19 个测试文件仅覆盖 admin/finance/inventory/oms/wms/tms/notification/hr/mrp/安全基类；上述 11 模块无专属测试文件——其中 crm/eam/dms/quality/report/workflow 六模块在任何测试文件中**零提及**；project/purchase/sales/product/bi 仅被通用基类测试或相邻模块测试偶然引用（ControllerPatternTest 模式采样、bootstrap.php 路由清单、InventoryServiceTest 提及 purchase/product 入库上下文、DoubleEntryServiceTest 中 "bi" 为 debit_amount 子串），均非专属覆盖 | 已证实 |
| B2 | 无集成/E2E/覆盖率；137 tests / 805 assertions 全为纯单测（实测 1.2s 内跑完，纯内存） | `vendor/bin/phpunit` 实测 "OK (137 tests, 805 assertions)" | 已证实 |
| B3 | PHPStan level 5 但基线抑制 974 条错误 | `phpstan-baseline.neon` 实测 974 个 message 节点 | 已证实 |
| B4 | CI 无覆盖率收集、无集成测试作业 | `.github/workflows/ci.yml`（PHP 8.2/8.3/8.4 × mysql8/redis7，仅 composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit） | 已证实 |
| B5 | purchase/sales 控制器硬编码依赖服务 | `app/controller/sales/DeliveryController.php:142-143`、`app/controller/purchase/ReceiveController.php:142-143`（两文件均为 `use` 声明于 :15-16、`new InventoryService()/new FinanceService()` 实例化于 :142-143） | 已证实 |

### 工作组 C：基础设施与安全治理

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| C1 | `.env.docker` 弱密钥 | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest`（.env.docker:15,32,37,51,67,81） | 已证实 |
| C2 | 环境变量强校验不完整 | 调研：仅 ENCRYPTION_KEY 走 env_required | 待验证（核对 config/jwt.php、encryption.php） |
| C3 | fail-open 静默吞错 | 调研结论；范围待审计（空 try/catch、catch 无日志） | 待验证（需 grep 审计） |
| C4 | backup-validator.sh 与逐迁移 `_rollback.sql` 缺失 | `find` 全库无匹配；`database/migrations/` 29 个 SQL 迁移均无对应回滚文件 | 已证实 |
| C5 | 通知渠道 stub（email/wecom/dingtalk） | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | 已证实 |
| C6 | 监控缺口：队列积压/WebSocket 连接数无指标 | `app/admin/controller/MetricsController.php` 现有 5 个 gauge | 部分证实 |

### 工作组 D：版本矩阵与文档治理

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| D1 | lite/standard/full 分支落后 main 20~41 commits | `git rev-list --left-right --count main...lite|standard|full` 实测：41/41/20 behind，且 lite/standard 各有 6~7 ahead 独有提交 | 已证实 |
| D2 | EDITIONS.md 数字自相矛盾 | 概览表：控制器 48/42/70、业务模块 6/6/12；升级路径段却写 12/12/19 模块、163 表；与实测 121 控制器不符 | 已证实 |
| D3 | FUNCTIONS.md 附录漂移 | 附录写 11 文件/90 方法/168 断言/9 中间件/22 迁移；实测 19~20 文件/137 测试/805 断言/12 中间件/29 迁移 | 已证实 |
| D4 | 测试数三版漂移（132/779→135/799→137/805） | 文档历史与 git 提交记录 | 已证实 |
| D5 | 完成度矩阵将 QMS/EAM/DMS/BI 标 🔴 但代码已存在 | `docs/FUNCTIONS.md:555` 附近矩阵 vs `app/controller/{quality,eam,dms,bi}/` 已实现 | 已证实 |
| D6 | 控制器口径混乱：docs/CLAUDE.md 写"业务控制器 104 个"，实测全量 122 | `find app -path '*/controller/*.php' | wc -l` = 122（含 admin 14 + api 3 + 业务 104 + Index/Install）；调研口径 121 | 已证实（口径差异） |
| D7 | 迁移数口径：调研 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29（编号至 000030，缺 000007/000008） | 已证实（29 为实测） |

### 工作组 E：前端质量与对齐

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| E1 | CI 无 flutter analyze/test/build、无 hvigor 构建 | `.github/workflows/ci.yml` 仅 PHP 作业 | 已证实 |
| E2 | README 声称 CI 含 Flutter 静态分析，与事实不符 | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml 无此步骤 | 已证实 |
| E3 | Flutter 仅 1 冒烟测试 | `apps/flutter/test/widget_test.dart` 为唯一测试文件 | 已证实 |
| E4 | HarmonyOS token 不持久化（AppStorage 仅内存，冷启动回登录页） | 调研结论（待核对 `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`） | 待验证 |
| E5 | HarmonyOS 25 页模板化，只读 name/code 列表无增删改 | 已抽查 OrderListPage.ets 全 65 行：仅 name/code 只读列表 | 已证实 |
| E6 | 前端覆盖深度不足（见 A7/A8） | 同上 | 待验证 |

### 工作组 F：API 分层与架构治理（低优先，量力而行）

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| F1 | /api 版本化仅 3 控制器，业务全在 /admin 单块 | `app/api/v1/controller/` 仅 Captcha/Auth/Product 三个 | 已证实 |
| F2 | 10 模块控制器直查模型无服务层 | 调研结论（crm/product 等控制器直接使用模型查询） | 部分证实（待全量审计） |
| F3 | purchase/sales 硬编码 `new` 服务而非依赖注入 | B5 证据 | 已证实 |

---

## 3. 分期规划

按优先级分三批（P0→P1→P2），**每期可独立发布、验收标准全部可量化**。总工期约 **8~9 周**（并行度假设：按 **2~3 名开发并行 + agent 团队协作**估算；单任务合计约 **77 人日**——P0 ≈12.5d、P1 ≈29.5d、P2 ≈35d——若单人串行执行约需 15 周。并行依据：A1/A4/A5 等后端小任务互相独立可并行；B1 各模块测试可拆分子任务并行；B/C 组与 E/D 组可跨期重叠；Flutter/HarmonyOS 前端任务与后端任务互不阻塞；任务间显式依赖见 §5）。

**编号体系**：分期任务编号与 §2 差距编号一一对应（A1~A8 → A1~A6/A7-1/A7-2/A8-1，B1~B5 → B1~B5，C1~C6 → C1~C6，D1~D7 → D1~D5，E1~E6 → E1/E3/E4/E5，F2/F3 → F2/F3）；其中 D6/D7（控制器与迁移口径）并入 D3 任务统一，E2（README 不实声明）并入 E1 验收，E6（覆盖深度）并入 A7-2，F1（/api 版本化）明确本期不做（见 §6）；另有 i18n 任务对应调研"Flutter i18n 未完成"，非差距表编号。

### 3.1 第一批 P0：闭环基线（第 1~2 周）

**目标**：消灭死端点与假数据，把已存在的未接线能力（TenantScope/队列/WebSocket）落地为可用或明确降级。

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| A1 | 修复通知"全部标记已读"：前端改调 `POST /admin/notification/read-all`（或后端补别名路由，二选一，推荐改前端） | `notification_page.dart` + `config/route.php` | 手动/自动调用通过；新增 1 条 PHPUnit 断言该路由存在 | 0.5d |
| A2 | 仪表盘接真实数据：移除独立 Dio，改走 ApiService（JWT 拦截器）；OMS/WMS/TMS 三 Tab 调 `/dashboard/oms\|wms\|tms`；删除硬编码假值；保留 Redis 5m 缓存语义 | `dashboard_controller.dart` + 相关页面 | 登录态下仪表盘三 Tab 展示后端真实数据，Network 面板可见 200 且带 Authorization 头；删除 mock 注释 | 2d |
| A3 | TenantScope 接线：注册到 `/admin` 路由组；租户 ID 取自 JWT 声明或 `X-Tenant-Id` 头（**决策点**，见 §5）；模型 trait 已就绪无需大改 | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | 两个租户数据互不可见（新增集成测试）；未传租户头时返回 400 而非静默放行；**备选降级**：若判定时机不成熟，改为文档明确标注"多租户为预留能力"并给出开启步骤，验收=文档与代码一致 | 2d |
| A4 | 队列端到端：config/process.php 增加 `redis-queue` 消费进程（默认 redis 驱动）；新增一条可观察的冒烟任务（如异步写操作日志）；文档写明切换 rabbitmq 的步骤 | `config/process.php`、`app/queue/` | 启动后消费进程在线（`php start.php status`）；投递冒烟任务后目标副作用 5s 内出现 | 1d |
| A5 | WebSocket 鉴权：连接建立/`auth` 消息校验 JWT（复用 AdminAuth 逻辑），非法 token 返回 auth_result:false 并断开；文档同步 | `app/process/WebSocket.php` + 前端连接处 | 未带/伪造 token 的连接被拒；合法 token 连接成功；新增 1 条测试覆盖 | 1d |
| A6 | 修复 HarmonyOS 分页：25 处单引号插值改模板字符串/拼接；page 自增 + 触底加载 + 下拉刷新；统一抽分页组件 | `apps/harmonyos/entry/src/main/ets/pages/**`（25 文件） | grep 全仓无残留 `${this.page}` 单引号模式；列表翻页请求参数正确；构建通过 | 2d |
| A7-1 | 死端点全量清零：以调研覆盖矩阵为底稿，跑一次"前端 URL × 后端路由"自动比对（脚本提取 Flutter/HarmonyOS 请求串 vs `config/route.php`），输出剩余差异清单 | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | 比对脚本产物入库（docs/）；差异清单中"前端调了但后端不存在"归零（不存在但合理的标注白名单） | 2d |
| A8-1 | 高价值表单补字段：采购/销售订单、记账凭证页补业务关键字段（金额/日期/往来单位/明细行），仅补齐，不做表单引擎 | 对应 Flutter 页面 | 表单可创建带业务字段的完整单据，接口 200 | 2d |

**P0 验收汇总**：A1~A6 全部落地；死端点清单归零；CI 全绿；无新增文档漂移（改动同步更新 docs/CLAUDE.md 功能清单）。

### 3.2 第二批 P1：测试与安全基线（第 3~5 周）

**目标**：测试体系从"纯单测"升级为"单测+集成+覆盖率"，安全弱项清零。

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| B1 | 11 个业务模块补测试：按模块写服务/模型层测试，覆盖 CRUD + 核心动作（结算、审批流、质检流程、设备工单等） | `tests/`（新增 crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow 测试文件） | 新增 ≥150 tests / ≥500 assertions；11 模块每模块 ≥10 tests；`vendor/bin/phpunit` 全绿 | 2w |
| B2 | 集成测试：利用 CI 已有 mysql8/redis7 services，新增集成测试组（真库 CRUD + 事务回滚 + TenantScope 隔离验证 + 队列冒烟） | `tests/Integration/` + `phpunit.xml` 分组 | 集成组在 CI 全绿；本地可 `--group=integration` 运行 | 1w |
| B3 | E2E 冒烟：真实 HTTP 走通 health→login→核心 CRUD→仪表盘，脚本化 | `tests/E2E/`（curl/php 脚本） | CI 新作业跑通 10 条核心链路，失败即红 | 2d |
| B4 | 覆盖率：接入 phpunit --coverage，设门槛（业务层 ≥40%，整体 ≥30%，待验证是否 CI 支持 xdebug 采集） | `phpunit.xml`、`ci.yml` | CI 产出覆盖率报告；低于门槛失败 | 1d |
| B5 | 控制器服务化（高频 4 模块）：finance/inventory/sales/purchase 控制器去掉 `new`，改容器取服务（`support\Container`），为 B1 测试铺路 | `app/controller/{finance,inventory,sales,purchase}/**` | 无 `new InventoryService/FinanceService` 残留；既有测试全绿 | 3d |
| C1 | 弱密钥清零：`.env.docker`/`.env.example` 改为随机占位 + 启动强校验（缺失/等于占位即拒绝启动）；CI 加 `env 校验` 步骤 | `.env*`、`config/*.php`、`ci.yml` | 用 `change-me` 启动直接失败并给出指引；Docker 起新实例自动生成随机密钥 | 1d |
| C2 | 环境变量强校验扩展：JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD 纳入 env_required（先核对 config/jwt.php 现状，待验证） | `config/*.php` | 缺任一关键密钥启动失败，错误信息中文明确 | 1d |
| C3 | fail-open 审计：grep 空 catch/无日志 catch，改为 fail-closed + 日志（含 TraceId） | 全 app/ | 审计清单入库；修复项均有测试或日志佐证 | 2d |
| C4 | 迁移治理：补 `database/backup/backup-validator.sh`（备份后自动恢复校验）+ 29 个逐迁移 `_rollback.sql`（按 install.sql 反推表结构） | `database/` | validator 脚本对备份文件跑通（备份→恢复→比对表数/行数）；每个迁移文件旁有同名 `_rollback.sql` | 2d |
| C5 | 通知渠道落地（对应差距 C5）：至少打通一条可用渠道（推荐 email：SMTP 驱动或文件日志驱动实现发送）；若判定时机不成熟，则明确文档化降级为"仅站内信 + 预留 email/wecom/dingtalk 适配点"并给出接入步骤（二选一，须显式决策） | `app/service/notification/ChannelRouter.php` + 新增驱动类 + docs | 邮件驱动：通知发送成功后 ChannelRouter 返回 true（测试用日志驱动断言）；若降级：ChannelRouter.php:23 注释与 docs 明确标注"预留"状态，消除 "stub for future implementation" 歧义 | 1.5d |
| C6 | 监控补指标：队列积压（redis LLEN）、WebSocket 在线连接数 | `MetricsController.php` | `/metrics` 输出新增 2 个 gauge | 1d |

**P1 验收汇总**：测试总数 ≥287（137+150）；覆盖率报告产出且过门槛；弱密钥/缺密钥启动失败；validator 与回滚脚本就位；通知渠道至少一条可用或明确降级文档化；CI 新增集成/E2E/覆盖率作业全绿。

### 3.3 第三批 P2：文档、版本矩阵与前端深度（第 6~8 周）

**目标**：文档数字与代码事实完全对齐（自动校验），版本矩阵恢复可信，前端补齐高价值深度。

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| D1 | 三分支同步：main 合并入 lite/standard/full，解决冲突，三分支 CI 全绿；**决策点**：此后采用"main 为唯一开发源，版本分支仅随发版 cherry-pick"策略 | git 三分支 + ci.yml | 三分支 behind=0；三分支各自 CI 绿；冲突解决记录在案 | 1w |
| D2 | EDITIONS.md 重写：以实测为准（表/控制器/模块数取自代码计数脚本），删除自相矛盾段落 | `docs/EDITIONS.md` | 文档所有数字与脚本输出一致 | 1d |
| D3 | 文档统计自动化：写 `scripts/doc-stats.sh`（控制器/服务/模型/迁移/测试/中间件计数 + phpunit 输出），FUNCTIONS.md 附录改为引用其输出；同时统一 D6（控制器口径 104/121/122）与 D7（迁移口径 22/29/30）为脚本唯一口径 | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | 脚本输出与文档一致；README/docs 中所有数字均可由脚本复现（含控制器/迁移口径单一化） | 2d |
| D4 | 完成度矩阵修正：QMS/EAM/DMS/BI 等实际已实现项改 ✅，附代码证据 | `docs/FUNCTIONS.md` | 矩阵与 `app/controller/` 目录一一对应，无 🔴/✅ 错位 | 1d |
| D5 | CI 文档校验作业：跑 doc-stats 与文档比对，漂移即红 | `ci.yml` + 脚本 | 篡改一处数字后 CI 变红（自测演示） | 1d |
| E1 | Flutter CI 作业：flutter analyze + flutter test + build web，接入 ci.yml | `ci.yml`、`apps/flutter/` | 三步骤全绿；README.md:635 声明与实际一致 | 1d |
| E3 | Flutter 测试扩充：ApiService 拦截器/401 刷新、AuthService 流程、关键表单校验，≥20 个 widget/unit 测试 | `apps/flutter/test/` | `flutter test` 全绿，≥20 tests | 1w |
| E4 | HarmonyOS token 持久化：AppStorage 落地持久化 + 冷启动恢复 + 401 刷新逻辑（先核对 ApiService 现状，待验证） | `apps/harmonyos/.../service/ApiService.ets` | 杀掉进程重启后保持登录态；token 过期自动刷新 | 2d |
| E5 | HarmonyOS 核心页补增删改：按价值排序（采购/销售/库存/财务/OMS 各取 2~3 个列表页），每页补齐 新建/编辑/删除 动作与表单 | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | 选中的 ≥10 个列表页具备增删改且调通后端；hvigor 构建通过（无鸿蒙 SDK 环境则标注"待 CI 环境就绪"） | 1w |
| i18n | Flutter 最小 i18n（对应调研"Flutter i18n 未完成"）：ApiService 错误消息与登录/导航/仪表盘关键文案接入 i18n（arb 文件，与后端 `app/common/I18n.php` 联动）；**仅最小可行，不做全量页面文案改造** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | 关键错误消息与 ≥10 处页面文案可随语言切换（en/zh）；`flutter test` 全绿 | 2d |
| A7-2 | 前端深度覆盖：按 A7-1 比对清单，补齐采购/销售结算页、财务三表/期末结转/银行账户、CRM follow/funnel/合同流转等关键端点页面 | `apps/flutter/lib/app/pages/**` | 比对清单中"后端存在但前端未覆盖"的高优先级项（结算/三表/履约/审批/薪资）清零 | 1w |
| F2/F3 | 服务层轻量提取（可选，量力而行）：对直查模型最重的 3~5 模块抽薄服务层 + 依赖注入；**明确不强制全量重构** | `app/controller/{crm,product,project,hr,manufacturing}/**` | 抽取模块控制器无模型直查；既有测试全绿；不抽取模块文档标注"控制器直查模型，已知技术债" | 1w |

**P2 验收汇总**：三分支同步且 CI 绿；docs 数字脚本可复现；CI 含 Flutter 作业与文档校验；Flutter ≥20 测试；HarmonyOS 持久化 + ≥10 页增删改；高优先级端点覆盖清零。

---

## 4. 验收标准（汇总，全部可验证）

- **端点**：A1 通知端点、A2 `/dashboard/oms|wms|tms`、A7 高优端点均可 curl 带 JWT 调用返回 200/业务数据。
- **测试**：`vendor/bin/phpunit` 全绿（≥287 tests）；`flutter test` 全绿（≥20）；集成/E2E 作业 CI 绿。
- **安全**：用 `change-me` 密钥启动失败；WebSocket 非法 token 被拒；无空 catch 静默吞错（审计清单）。
- **渠道/i18n**：通知至少一条渠道可用或明确降级文档化；Flutter 关键错误消息与 ≥10 处文案可中英切换（最小可行）。
- **CI**：`.github/workflows/ci.yml` 全部作业绿（PHP 矩阵 + 集成 + 覆盖率 + flutter + 文档校验）。
- **文档**：`scripts/doc-stats.sh` 输出与 docs 全部数字一致（漂移即 CI 红）。
- **分支**：`git rev-list --left-right --count main...lite|standard|full` 均为 `0 0`。
- **前端**：HarmonyOS 无 `${this.page}` 单引号残留；冷启动保持登录；核心页增删改调通后端。

---

## 5. 依赖与风险

**依赖关系**：
- A 组（闭环）→ B 组（测试）：B1/B2 的测试必须针对**真实可用**的端点，故 P0 先修死端点与接线，P1 再补测试。
- B5（控制器服务化）→ B1（测试）：**仅为其覆盖的 finance/inventory/sales/purchase 四模块测试铺路**（消掉 `new` 硬编码后服务可注入 mock；其中 purchase/sales 属零测试模块，finance/inventory 已有测试可顺带改进）；其余零测试模块（crm/eam/dms/quality/project/product/bi/report/workflow）的测试**不依赖** B5，可与 B5 并行推进。
- D1（分支同步）→ D3/D5（文档校验）：同步后 main 为唯一事实源，文档口径才能唯一。
- E1（Flutter CI）→ E3（测试扩充）：先有门禁，再扩充测试才有保护意义。

**风险与缓解**：
| 风险 | 影响 | 缓解 |
|------|------|------|
| TenantScope 接线影响全部 /admin 查询，可能引入数据可见性回归 | 高 | 集成测试先行；按 JWT 声明取租户（无需前端改造）；或 P0 内降级为"文档标注预留"并明确决策 |
| 三分支同步合并冲突，可能引入回归 | 中高 | 先 main 全绿；合并后三分支各自 CI 全绿才可交付；冲突解决记录在案 |
| 队列消费进程在部分环境（rabbitmq）不可用 | 中 | 默认 redis 驱动（CI 已有 redis7），rabbitmq 仅文档化切换步骤 |
| WebSocket 鉴权改动破坏现有客户端 | 中 | 前后端同一里程碑内协同修改；非法 token 拒绝但不影响合法会话 |
| 覆盖矩阵/表单字段清单为调研结论，部分"待验证" | 中 | A7-1 先做自动化比对脚本，以脚本结果为准，不凭印象补页面 |
| 服务层重构范围失控 | 中 | 明确只抽 3~5 个模块，不强制全量；不做 /api 全量版本化（F1 本期不做） |
| 覆盖率门槛在 CI 环境不可用（xdebug 未装） | 低 | 先本地产出报告 + 文档门槛，CI 采集能力"待验证"后接入 |
| HarmonyOS CI（hvigor）需要鸿蒙 SDK，公共 CI 环境可能不具备 | 中 | 标注"待 CI 环境就绪"；本地构建验证为准，不阻塞其他任务 |

---

## 6. 明确不做

延续路线图 §12 排除项，除非出现强理由（需单独评审立项）：
- ❌ 微服务拆分 / K8s 部署（实验保留在 `.claude/worktrees/microservices-split/`，不并入主线）
- ❌ AI/ML 能力（预测、智能推荐、NLP）
- ❌ 原生 App（iOS/Android 原生）——Flutter 已覆盖全平台
- ❌ GraphQL 接口
- ❌ 硬件集成（IoT/扫码枪/打印设备直连）
- ❌ 多租户完整商业化方案（SaaS 计费、租户自助开通）——本期仅最小接线或文档化预留
- ❌ /api 全量版本化（F1）——业务端仍在 /admin，仅记录为架构债
- ❌ 全量服务层重构与全量表单重做——按价值排序抽取，不做"大爆炸"式重构
- ❌ HarmonyOS 全量页面补齐——只补高价值核心页增删改
- ❌ Flutter 全量 i18n 文案改造——本期仅最小可行（错误消息 + ≥10 处关键文案），全量页面多语言留给后续版本

---

## 7. 里程碑建议

| 里程碑 | 时间 | 内容 | 出口标准 |
|--------|------|------|----------|
| **M1 闭环基线** | 第 2 周末 | A 组全部：死端点清零、仪表盘真实数据、TenantScope/队列/WebSocket 落地、HarmonyOS 分页修复 | P0 验收汇总全过 |
| **M2 质量基线** | 第 5 周末 | B 组全部 + C 组安全项：11 模块测试、集成/E2E/覆盖率、弱密钥清零、fail-open 审计、迁移治理、通知渠道 | P1 验收汇总全过 |
| **M3 前端质量** | 第 6 周末 | E 组：Flutter CI 作业 + 测试扩充、HarmonyOS token 持久化与核心页增删改 | flutter CI 绿、持久化生效、≥10 页增删改 |
| **M4 版本与文档治理** | 第 7 周末 | D 组：三分支同步、EDITIONS/FUNCTIONS 重写、doc-stats 自动化 + CI 校验 | 分支同步、文档漂移即红 |
| **M5 深度覆盖** | 第 8 周末 | A7-2 前端深度 + F 组服务层轻量提取 | 高优端点覆盖清零、抽取模块无模型直查 |
| **M6 1.1 发布** | 第 9 周末 | 全量回归、发布说明（CHANGELOG）、文档最终核验、归档 | 全部里程碑出口标准通过（硬指标）：测试总数 ≥287 且 phpunit 全绿、覆盖率报告过门槛、ci.yml 全部作业绿（PHP 矩阵+集成+覆盖率+flutter+文档校验）、三分支同步为 0 0、死端点清单归零、doc-stats 漂移即红机制生效；CHANGELOG 与文档最终核验通过；评审复审仅作参考，不设分数门槛 |

---

## 附：本规划已抽查验证的关键文件

- `config/middleware.php`、`config/route.php`（:231-233 dashboard 端点、:248-251 通知路由、:387-415 中间件分组）
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php`（:23、:47-50）
- `app/service/notification/ChannelRouter.php`（:23 stub）
- `app/controller/sales/DeliveryController.php`（:142-143）、`app/controller/purchase/ReceiveController.php`（:142-143，两文件 `new` 实例化均在此；`use` 声明于 :15-16）
- `app/api/v1/controller/`（仅 3 控制器）
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（mock 统计 + 独立 Dio）
- `apps/flutter/lib/app/pages/notification/notification_page.dart`（:43 死端点）
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets`（:24 插值 bug）
- `tests/`（19 测试文件清单）、`vendor/bin/phpunit` 实测 137/805
- `phpstan-baseline.neon`（974 message）
- `.github/workflows/ci.yml`（仅 PHP 作业）、`README.md`（:635 不实声明）
- `.env.docker`（弱密钥）、`database/migrations/`（29 个，无 _rollback）
- `docs/EDITIONS.md`（自相矛盾）、`docs/FUNCTIONS.md`（附录漂移）、`docs/CLAUDE.md`（104 vs 实测 122 控制器口径）
- git 分支 `lite/standard/full`（behind 41/41/20）

> 口径说明：控制器实测 `find app -path '*/controller/*.php'` = 122（含 admin 14 + api 3 + 业务控制器 + Index/Install）；调研口径 121，docs/CLAUDE.md 业务口径 104，三者差异源于统计范围不同，已在 D6 列为治理项统一口径。
