# 下一阶段（P5 / 演进期 1.2）项目规划

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 编制：系统架构师 ｜ 日期：2026-08-27 ｜ 依据：P4（1.1）完成度审计报告（40 提交 e99e91f~04aa5a9）+ 实地抽查复核（`scripts/check-endpoints.php`、doc-stats、git 分支状态、Dockerfile/docker-compose 核验）
> 状态：草案（待评审）｜ 目标版本：1.2（演进期）

---

## 1. 阶段定位

P4（演进期 1.1）三大路线**绝大部分已闭环**，经实地复核确认：

- **业务闭环（A 组）**：通知端点 A1 ✅、仪表盘真实数据 A2 ✅、队列消费进程 A4 ✅（`config/process.php` 的 QueueConsumer，文档见 `docs/queue.md`）、WebSocket 鉴权 A5 ✅（完整 auth 协议 + `tests/WebSocketAuthTest.php`）、HarmonyOS 分页 A6 ✅（全仓无 `${this.page}` 单引号残留）。
- **测试体系（B 组）**：全模块测试 B1 ✅（`tests/` 下 crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow 专属测试文件齐全）、集成测试 B2 ✅（`tests/Integration/`）、E2E B3 ✅（`tests/E2E/api-coverage.php` 104 端点，CI e2e 作业已接入）、覆盖率 B4 ✅（CI pcov 采集 + 门槛作业）、控制器服务化 B5 ✅（`app/controller/` 无 `new InventoryService|FinanceService` 残留）。
- **基础设施与安全（C 组）**：弱密钥强校验 C1/C2 ✅（`app/functions.php` env_required 拒绝 change-me/CHANGE_ME/xxx 占位启动）、迁移治理 C4 ✅（29 迁移并入 `install.sql` 单文件事实源，`database/backup/backup-validator.sh` 就位）、通知渠道 C5 ✅（email 以文件日志驱动落地，wecom/dingtalk 保留适配点）、监控指标 C6 ✅（队列积压 + WebSocket 连接数 gauge）。
- **文档治理（D 组）**：EDITIONS.md 重写 D2 ✅（doc-stats 注解化）、doc-stats 自动化 D3 ✅（195 处校验全绿）、CI 文档校验作业 D5 ✅、完成度矩阵后端校正 D4 ✅（QMS/EAM/DMS/BI 已改 ✅）。
- **前端质量（E 组）**：Flutter CI 作业 E1 ✅、Flutter 98 测试 E3 ✅、HarmonyOS token 持久化 E4 ✅（`utils/TokenManager.ets` + EntryAbility 磁盘恢复）、核心页增删改 E5 ✅（抽查 `PurchaseOrderPage.ets` 具备 create/edit 表单与提交）、Flutter 最小 i18n ✅（`lib/l10n/*.arb` + gen-l10n）、文档 12 语言国际化 ✅。

**遗留与新增差距**（经审计报告交叉核对）集中在五处：

1. **A7 前端深度未闭环**：`scripts/check-endpoints.php` 实测 **4 个死端点**（采购/销售结算页 PUT/DELETE 调用不存在的路由）+ **161 个覆盖缺口**（后端存在但前端未调用）；且审计确认比对产物未落库 docs/（P4 A7-1 验收项 ⏳）。结算死端点根因明确：`config/route.php` 只注册了 settlement 的 `index` 路由，而 `SettlementController` 的 store/show/update/destroy 方法均已实现却未挂路由——一处注册即可修复。
2. **文档口径三处漂移**：README.md:140 仍写"20 个测试文件，137 个测试方法，805 条断言"（实测 60 个测试文件 / 442+ 用例 / 2238+ 断言）；docs/CLAUDE.md 写"513 tests, 2368 assertions"；docs/FUNCTIONS.md 写"442 个测试用例 / 2238 条断言"。doc-stats 校验范围未覆盖 README.md 与 docs/CLAUDE.md。
3. **部署体验矛盾**：`.env.docker` / `.env.example` 保留 `CHANGE_ME_` 占位 + 启动 fail-fast 强校验，按 docs/CLAUDE.md 的 `cp .env.docker .env && docker-compose up -d` 流程会直接启动失败；CI 靠 `sed -i "s/CHANGE_ME_[0-9a-f]+/$(openssl rand -hex 32)/g"` 绕过，真实用户无此步骤。**审计引证 8ac83b9/42a8a72 称"Docker 自动生成随机密钥"——复核两提交分别为 CI E2E 空口令修复与 HASHIDS 盐接线，Dockerfile（CMD 直启）与 docker-compose.yml（env_file + command 直启）均无密钥生成步骤，该结论不成立，差距成立**。
4. **分支与版本策略未收尾**：审计唯一硬缺口 D1——lite/standard/full 三分支各落后 main 19 个提交（0 ahead，无分叉）且无各自 CI 验证；EDITIONS.md 却写"代码库中无对应分支"（与事实不符）；CI 已新增幂等 release 作业（tag patch+1），与 P4 D1 决策点"main 为唯一开发源"方向一致，需落地文档化。
5. **服务层债标注缺失 + phpstan 基线回升**：审计确认 wms/tms/quality 等仍有 26 处 `new XxxService()` 直建（P4 F2/F3 验收"未抽取模块文档标注已知技术债"未做）；phpstan 基线 974→1086（8.3/8.4 双版本基线合并所致，非治理成果）。

**原则**：只规划"该做且未做"的事；任务小而明确，单 agent 会话可完成；每条差距均有可核验证据路径；拿不准的标注"待验证"。

---

## 2. 差距分析

按 **5 个工作组**组织。状态：已证实 = 本轮实地复核确认；待验证 = 需执行时核对。

### 工作组 A：业务闭环收尾（P4 遗留）

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| A1 | 结算死端点 + 比对产物未落库：Flutter 结算页调 PUT/DELETE `/admin/purchase\|sales/settlement/{id}` 后端不存在；check-endpoints 报告未按 P4 A7-1 验收落库 docs/ | `php scripts/check-endpoints.php` 死端点清单 4 条（`settlement_list_page.dart:80,87`）；根因 `config/route.php:127,136` 仅注册 `Route::any(..., 'index')`，而 `SettlementController` 的 store/show/update/destroy 方法已实现（`:75,:103,:130,:158`）未挂路由；审计 A7-1 ⏳"比对脚本未落库" | 已证实 |
| A2 | 覆盖缺口 161 个（后端存在但 Flutter/HarmonyOS 均未调用），横跨 35 模块 | `php scripts/check-endpoints.php` ② 清单；含高价值动作端点：crm `pool/claim` `ticket/{id}/assign` `analytics/*`、bi `widget` CRUD、finance 报告类、审批/薪资类 | 已证实（优先级待执行时定） |
| A3 | 表单业务字段补齐情况未全量核验（P4 A8-1 无独立验收记录；审计仅确认财务 13 页 + CRM 10 页齐备） | 抽查 Flutter 结算/订单页已带金额/日期/往来单位字段；其余页面待逐页核对 | 待验证 |
| A4 | 服务层债标注缺失：wms/tms/quality 等仍 26 处 `new XxxService()` 直建，未抽取模块未按 P4 F2/F3 验收标注"已知技术债" | 审计报告（26 处，如 WmsInboundService、QmsInspectionService）；`grep -rn "new .*Service(" app/controller/` | 已证实 |
| A5 | phpstan 基线不降反升：974→1086 message（8.3/8.4 双版本基线合并所致，非治理成果） | 审计报告；`phpstan-baseline.neon` message 计数 | 已证实（低优先，可选） |
| A6 | 多租户接线决策：TenantScope 中间件与模型 trait 仍零引用（`config/middleware.php`、`config/route.php` 均无注册），P4 已排除多租户商业化；需决策"接线"或"文档化预留" | `config/middleware.php`、`config/route.php`（TenantScope 零引用）、`app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php` | 已证实（决策点） |

### 工作组 B：文档与统计口径统一

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| B1 | README.md 测试统计陈旧：写"20 个测试文件，137 个测试方法，805 条断言"，实测 60 测试文件 / 442+ 用例 / 2238+ 断言 | `README.md:140` vs `bash scripts/doc-stats.sh` 输出 | 已证实 |
| B2 | 测试数三处口径不一致：README 137/805、docs/CLAUDE.md 513/2368（32 skipped）、docs/FUNCTIONS.md 442/2238 | 三文件实测对比；doc-stats 校验未覆盖 README 与 CLAUDE.md | 已证实 |
| B3 | FUNCTIONS.md 展示文本与 stats 注解不一致：显示"PHP 源文件 343"注解 342、"50 个测试文件"注解 60 | `docs/FUNCTIONS.md:503,507`（展示值与 `<!-- stats:... -->` 注解不符，CI 只校验注解） | 已证实 |
| B4 | 完成度矩阵前端列与实测不符：Flutter/HarmonyOS 列大面积 🔴/0%，与 97 Flutter 页、41 HarmonyOS 文件（含核心页增删改）矛盾 | `docs/FUNCTIONS.md` 矩阵统计段（Flutter 前端 0%、HarmonyOS 0%）vs `apps/flutter/lib`、`apps/harmonyos/entry/src/main/ets` 实测 | 已证实（口径待定义） |
| B5 | EDITIONS.md 写"代码库中无对应分支"，与事实不符（lite/standard/full 分支存在，落后 main 19 提交） | `docs/EDITIONS.md` 统计口径段 vs `git branch -a`、`git rev-list --left-right --count main...lite|standard|full` = 19 0 | 已证实 |
| B6 | docs/TEST_REPORT.md 是否纳入 doc-stats 校验范围 | `scripts/doc-stats.sh` 校验清单核对 | 待验证 |

### 工作组 C：部署体验与安全

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| C1 | 部署流程与 fail-fast 矛盾：`.env.docker`/`.env.example` 全为 `CHANGE_ME_` 占位，启动强校验拒绝占位启动，`cp .env.docker .env && docker-compose up -d` 会失败。审计引证 8ac83b9/42a8a72 称"Docker 自动生成随机密钥"——复核两提交分别为 CI E2E 空口令修复与 HASHIDS 盐接线，声明不成立 | `.env.docker:19,36-37,41`（CHANGE_ME_*）、`app/functions.php:95-102`（占位检测无条件拒绝，注释自述"无论何种场景一律拒绝启动"）、`docker-compose.yml:36-38`（env_file + command 直启，无 entrypoint）、`Dockerfile:57`（CMD 直启，无密钥生成）；CI 用 `sed + openssl rand` 绕过（`ci.yml` 注释自述） | 已证实 |
| C2 | 部署文档未写密钥替换步骤：README/INSTALL 的 Docker 部署段无 sed/生成指引 | `README.md` 部署段、`docs/INSTALL.md` 核对 | 已证实 |
| C3 | CI release 作业（幂等 tag+release）为新能力，无用户文档与本地验证脚本说明 | `ci.yml` release 作业（git log 04aa5a9 引入）vs README/CHANGELOG 无提及 | 待验证 |

### 工作组 D：分支与版本策略收尾

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| D1 | lite/standard/full 三分支落后 main 19 提交，策略未落地：同步 or 明确"main 唯一开发源 + 发版 tag/cherry-pick"并文档化 | `git rev-list --left-right --count main...lite|standard|full`（均 19 0）；P4 规划 D1 决策点 | 已证实 |
| D2 | 分支状态与 EDITIONS.md 表述矛盾（见 B5），需随 D1 决策一并修正 | 同上 | 已证实 |

### 工作组 E：前端深度覆盖（从 A3 缺口按价值优先）

| # | 差距 | 证据路径 | 状态 |
|---|------|----------|------|
| E1 | 结算页 4 死端点对应修复：注册 settlement 资源路由（后端已就绪）或改前端动作 | `config/route.php:127,136` 补 `Route::resource` | 已证实（修复点明确） |
| E2 | 高价值动作端点前端补齐：CRM 公海认领/工单指派/分析、BI 看板/图表管理、财务报告/期末结转等 | A3 缺口清单按价值排序的前 10~15 个动作端点 | 待验证（清单执行时定） |
| E3 | 详情页（GET {id} show）覆盖：缺口含大量详情端点，按模块价值评估是否补页 | A3 缺口清单 show 类端点 | 待验证（量力而行） |

---

## 3. 执行顺序与验收标准

分三批，**每批可独立发布、验收标准全部可量化**。总工期约 **3~4 周**（按 1~2 名开发并行估算；任务间显式依赖见 §5）。

**编号体系**：任务编号与 §2 差距一一对应（A1~A5、B1~B6、C1~C3、D1~D2、E1~E3；E 组任务从 A 组缺口派生）。

### 3.1 第一批 P0：业务闭环收尾（第 1 周）

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| A1/E1 | 结算路由修复 + 比对产物落库：`config/route.php:127,136` 将 settlement 注册改为 `Route::resource`（或补 store/show/update/destroy 四条路由）；`php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-27.md` 生成比对报告入库 | `config/route.php` + `scripts/check-endpoints.php` | 死端点清单为 0；报告入库 docs/（含后端路由/前端请求/缺口统计）；`tests/E2E/api-coverage.php` 增补结算 PUT/DELETE 用例（含路由存在断言）并全绿 | 0.5d |
| A2-1 | 覆盖缺口优先级清单：从 161 个缺口中筛出高价值动作端点（≥10 个），输出到 docs/（参考 check-endpoints `--doc` 产物） | `scripts/check-endpoints.php` | 清单入库 docs/；每条注明价值理由与对应页面 | 0.5d |
| D1/D2 | 分支策略落地：选"main 唯一开发源，版本分支随发版 cherry-pick（与 CI release 作业一致）"，三分支快进同步至 main 或文档化保留差异；EDITIONS.md 分支表述修正 | git 三分支、`docs/EDITIONS.md` | `git rev-list --left-right --count main...lite\|standard\|full` 三分支一致（或差异已在文档中说明）；EDITIONS.md 与 git 事实一致；若保留分支，给出各自 CI 验证方案或明确"分支仅归档、不维护" | 1d |

**P0 验收汇总**：死端点清单归零且比对报告入库；缺口优先级清单入库；分支策略落地文档化。

### 3.2 第二批 P1：文档与统计口径统一（第 2 周）

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| B1/B2 | 测试统计三处口径统一：README.md、docs/CLAUDE.md、docs/FUNCTIONS.md 的测试数改为引用同一脚本输出（doc-stats 或 phpunit 解析），删除硬编码数字 | `README.md`、`docs/CLAUDE.md`、`docs/FUNCTIONS.md`、`scripts/doc-stats.sh` | 三文件数字与 `vendor/bin/phpunit` 实测一致；doc-stats 校验范围扩展覆盖 README.md 与 docs/CLAUDE.md 的 stats 标注 | 1d |
| B3 | FUNCTIONS.md 展示文本与注解统一：343→342、50→60 等展示值与注解对齐，或展示值改为引用注解 | `docs/FUNCTIONS.md` | 逐行比对无展示值 ≠ 注解值 | 0.5d |
| B4 | 完成度矩阵前端列口径定义并校正：Flutter/HarmonyOS 列改为"页面动作覆盖"口径（与 A2-1 清单挂钩），已实现项改 ✅/⚠️ | `docs/FUNCTIONS.md` 矩阵 | 矩阵列与 `apps/` 实测页面动作一一对应，无整列 0% 与既有页面矛盾 | 1d |
| B6 | TEST_REPORT.md 纳入 doc-stats 校验（或标注"快照文档，校验口径以 FUNCTIONS.md 为准"） | `scripts/doc-stats.sh`、`docs/TEST_REPORT.md` | 校验通过；或文档标注口径说明 | 0.5d |
| C3 | release 作业验证与文档化：README 增"发布流程"段（tag 增量规则、幂等行为、手动触发方式），本地可用 `bump-version.sh` 预演 | `README.md`、`.github/workflows/ci.yml`、`scripts/bump-version.sh` | 文档描述与 ci.yml 实际行为一致；手动触发一次 release 作业成功 | 1d |
| A4 | 服务层债标注：对仍直建服务的模块（wms/tms/quality 等 26 处 `new XxxService()`）在 docs/CLAUDE.md 或模块文档标注"控制器直建服务，已知技术债（P5 不重构）" | `docs/CLAUDE.md`、`app/controller/{wms,tms,quality}/**` | 债标注清单与 `grep -rn "new .*Service(" app/controller/` 实测一致 | 0.5d |

**P1 验收汇总**：三处测试数一致且与实测相符；doc-stats 覆盖 README/CLAUDE.md；矩阵前端列口径明确；release 流程有文档；服务层债标注与实测一致。

### 3.3 第三批 P2：部署体验与前端深度（第 3~4 周）

| 任务 | 内容 | 涉及范围 | 验收标准 | 工期 |
|------|------|----------|----------|------|
| C1/C2 | 部署密钥生成：① Dockerfile/docker-compose 增加 entrypoint 生成随机密钥（首次启动写入 .env）或 ② README/INSTALL 部署段显式给出 `sed + openssl rand` 替换步骤（与 CI 一致）；二选一，推荐 ① 自动化 | `Dockerfile`、`docker-compose.yml`、`README.md`、`docs/INSTALL.md` | 全新用户按文档流程 `cp .env.docker .env && docker-compose up -d` 可正常启动（app 日志无占位拒绝）；CI 不受影响 | 1.5d |
| E2 | 高价值动作端点补齐（取 A2-1 清单前 10~15 个）：CRM 公海认领/工单指派/分析、BI 看板与图表管理、财务报告/期末结转等 | `apps/flutter/lib/app/pages/**`（按清单） | 清单中选定端点均有对应页面操作且 200；`scripts/check-endpoints.php` 覆盖缺口按选定项逐条核销 | 1w |
| E3 | 详情页补充（量力而行）：按 A2-1 清单价值排序，补 5~10 个 GET {id} 详情页（优先 CRM/采购/销售/财务） | `apps/flutter/lib/app/pages/**` | 新增详情页可展示后端真实数据；Flutter 98 测试全绿（新增用例可选） | 3d |
| A3 | 表单字段全量核验：逐页核对 Flutter 业务表单字段，缺关键字段的补齐（仅补，不做表单引擎） | `apps/flutter/lib/app/pages/**` | 核验清单入库；每模块 ≥1 个表单字段补全后创建单据接口 200 | 2d |
| A5 | phpstan 基线治理（可选）：拆解 8.3/8.4 双版本基线冗余，修复高价值错误后按模块下调基线条目 | `phpstan-baseline.neon`、`phpstan.neon` | 基线 message 计数 1086 → ≤974；phpstan analyse 双版本全绿 | 2d |

**P2 验收汇总**：全新部署流程可跑通；选定高价值端点覆盖清零；详情页 ≥5 个；表单核验清单入库；phpstan 基线不升（可选项）。

---

## 4. 验收标准（汇总，全部可验证）

- **端点**：`php scripts/check-endpoints.php` 死端点 = 0；结算 PUT/DELETE 有 E2E 用例。
- **测试**：`vendor/bin/phpunit` 全绿（含新增结算用例）；`flutter test` 全绿。
- **文档**：README.md / docs/CLAUDE.md / docs/FUNCTIONS.md 测试数三处一致且与实测相符；doc-stats 校验全绿（含扩展范围）；EDITIONS.md 与 git 事实一致。
- **部署**：全新用户按文档流程 docker-compose 可启动；占位密钥启动失败的指引保留。
- **发布**：README 发布流程段与 ci.yml release 作业行为一致。
- **技术债**：服务层债标注与 `grep -rn "new .*Service(" app/controller/` 实测一致；phpstan 基线不升（可选）。

---

## 5. 依赖与风险

**依赖关系**：
- A1（结算路由）→ E1/E2（前端深度）：先修后端路由，前端结算页动作才能闭环；A2-1 优先级清单是 E2/E3 的选点依据。
- A6（多租户决策）→ B4（矩阵校正）：矩阵多租户行的最终标注取决于决策结果。
- C1/C2（部署）→ B1（文档口径）：部署文档修改与 README 统计修正可在同批进行，互不阻塞。
- D1（分支）→ B5（EDITIONS 修正）：分支决策先定，EDITIONS 表述随后修正。

**风险与缓解**：

| 风险 | 影响 | 缓解 |
|------|------|------|
| 多租户接线引发 /admin 全量查询回归 | 高 | 集成测试先行；或选择降级案（文档化预留），风险归零 |
| E2 前端深度范围蔓延（161 缺口全做） | 中 | 以 A3-1 优先级清单为准，只做前 10~15 个动作端点；详情页明确量力而行 |
| C1 部署修复改动 Docker 镜像影响 CI | 中 | 推荐 entrypoint 仅在 .env 缺失/占位时生成密钥，CI 的 sed 路径不受影响；改动后 e2e/php 作业回归验证 |
| 测试数口径统一后 CI 数字与本地浮动（扩展版本差异） | 低 | 沿用 FUNCTIONS.md 既有策略：断言数标注"随补丁版本浮动，不参与精确校验" |
| release 作业手动验证需推送 tag | 低 | 用 `bump-version.sh` 本地预演 + 文档化，避免在公共仓库反复触发 |

---

## 6. 明确不做

延续 P4 排除项（微服务、AI/ML、原生 App、GraphQL、硬件集成、多租户商业化、/api 全量版本化、全量服务层重构、全量表单重做、全量 i18n 文案改造），除非出现强理由（需单独评审立项）。本期新增明确不做：

- ❌ 161 个覆盖缺口全量补齐——只做优先级清单前 10~15 个动作端点 + 少量详情页
- ❌ 多租户完整接线若判定时机不成熟——允许降级为文档化预留（决策点，见 A6）
- ❌ 三分支强制同步——若采纳"main 唯一开发源"策略，分支差异文档化即可，不做强制合并

---

## 7. 里程碑建议

| 里程碑 | 时间 | 内容 | 出口标准 |
|--------|------|------|----------|
| **M1 闭环收尾** | 第 1 周末 | 结算路由修复、多租户决策落地、缺口清单入库、分支策略落地 | 死端点归零；A6 决策落地且三方一致；清单入库 |
| **M2 口径统一** | 第 2 周末 | 测试数三处统一、FUNCTIONS 展示对齐、矩阵前端列校正、release 文档化 | 三处数字一致；doc-stats 覆盖 README/CLAUDE.md 全绿 |
| **M3 部署与深度** | 第 4 周末 | 部署密钥自动化、高价值端点补齐、详情页、表单核验 | 全新部署可启动；选定端点清零；详情页 ≥5 |
| **M4 1.2 发布** | 第 4 周末 | 全量回归（phpunit/flutter/doc-stats/e2e CI 全绿）、CHANGELOG 更新 | 全部出口标准通过；无新增文档漂移 |

---

## 附：本规划已抽查验证的关键文件

- `config/route.php`（:127,136 settlement 仅 index 路由；:248-251 通知路由已含 read-all）
- `app/controller/purchase/SettlementController.php`（:75,:103,:130,:158 方法已实现未挂路由）
- `scripts/check-endpoints.php`（实测：死端点 4、覆盖缺口 161、后端路由 563、Flutter 397 / HarmonyOS 88 请求）
- `config/middleware.php`、`config/route.php`（TenantScope 零引用）
- `config/process.php`（QueueConsumer 消费进程 + docs/queue.md）
- `app/process/WebSocket.php`（鉴权协议）、`tests/WebSocketAuthTest.php`
- `app/functions.php`（:56 env_required、:95-102 占位检测）、`.env.docker` / `.env.example`（CHANGE_ME_ 占位）、`docker-compose.yml`（无密钥生成步骤）
- `.github/workflows/ci.yml`（php 矩阵+覆盖率门槛 / flutter / docs / e2e / release 作业）
- `README.md`（:140 陈旧测试统计、部署段）、`docs/CLAUDE.md`（513/2368 口径）、`docs/FUNCTIONS.md`（:503,:507 展示值与注解不符、矩阵前端列 0%）
- `docs/EDITIONS.md`（"代码库中无对应分支"表述）、git 分支状态（lite/standard/full 均 19 0）
- `apps/harmonyos/entry/src/main/ets/utils/TokenManager.ets`（持久化）、`pages/PurchaseOrderPage.ets`（增删改表单）
- `apps/flutter/lib/l10n/`（arb + gen-l10n）、`tests/`（60 测试文件、Integration/E2E 目录）

> 口径说明：测试数实测以 `bash scripts/doc-stats.sh` 为准（git log 429437b 记录 513/2368/32，FUNCTIONS.md 注解 60 测试文件 / 442 用例 / 2238 断言——两者差异源于统计快照时点与 skipped 计数，已列为 B2 统一项）。
