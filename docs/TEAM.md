# 团队规划（AI 协作团队）

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 本文档定义本项目的 AI 协作团队：角色构成、职责边界、协作模式与任务路由。
> 配套协调规则（SendMessage-First、agent 命名、生命周期）见根目录 `CLAUDE.md`；角色定义见 `.claude/agents/`。

---

## 1. 项目画像（规划依据）

| 维度 | 现状 | 对团队的含义 |
|------|------|--------------|
| 后端 | webman (Workerman) PHP 8.3+，**22 个业务模块**、121+ 控制器、24 服务、161 模型、163 张表、29 迁移、12 中间件 | 单体大而全，按业务域分工，防止单 agent 上下文爆炸 |
| 前端 | Flutter **97 页**（Web/移动端）+ HarmonyOS **34 页**，覆盖全部模块 | 双端并行维护，需要专职前端角色 |
| 质量基线 | PHPUnit 137 测试 / 805 断言、PHPStan + baseline、CS-Fixer、CI 多版本矩阵 | 已具备纪律，测试/审查角色直接嵌入流水线 |
| 版本矩阵 | `lite` / `standard` / `full` 三分支（62/72/163 表） | 改动需考虑跨分支同步，需版本协调 |
| 路线图 | P0~P3 已交付（综合评分 89/100），进入日常迭代与演进期 | 团队规模按任务类型伸缩，非项目制大编制 |
| 已有设施 | `.claude/agents/`（planner / sparc / testing / swarm / consensus）、`.claude-flow`（hierarchical-mesh，上限 15 agents，consensus 协调）、hooks + 记忆 | 团队直接挂载到现有配置，不另起炉灶 |

---

## 2. 团队构成

### 2.1 核心团队（常驻，5 角色）

| 角色 | 现有 agent 对应 | 职责（针对本项目） |
|------|-----------------|--------------------|
| **项目经理 Lead** | `planner` / `swarm/hierarchical-coordinator` | 需求拆解 → 路由 → 验收；维护 22 模块任务队列；决定 pipeline / fan-out / supervisor 模式；跨角色消息中转 |
| **系统架构师** | `sparc/architecture` | 表结构/迁移设计（163 表 + 29 迁移归属）；跨模块数据流（采购收货→库存→应付、销售发货→应收→出库等链路）；微服务拆分边界决策 |
| **后端开发者** | `core` / 自定义 `backend-dev` | 控制器 / 服务 / 模型实现；遵循 `app/service` 分层与中间件链（Locale→Cors→SecurityFilter→RateLimit→TracingId→业务中间件） |
| **测试工程师** | `testing/tdd-london-swarm` + `production-validator` | PHPUnit 用例先行（引擎边界测试）；三分支回归验证；`tests/` 覆盖缺口补齐 |
| **代码审查员** | `consensus/security-manager` | PHPStan 零新增 baseline、CS-Fixer 合规、18 层安全模式检查；提交前质量门禁把守 |

### 2.2 专业团队（按任务类型抽调，4 角色）

| 角色 | 现有 agent 对应 | 启用场景 | 典型任务 |
|------|-----------------|----------|----------|
| **业务引擎专家** | 自定义 `business-engineer` | 财务 / 薪资 / MRP 等算法型模块 | 复式记账引擎、薪资计算引擎、MRP 引擎的算法补强与边界处理（A 档"工业级"要求） |
| **前端工程师（Flutter）** | 自定义 `frontend-flutter` | 任何涉及 `apps/flutter/` 的改动 | Web 管理面板页面、GetX 状态、ApiService/导出联动、97 页维护 |
| **前端工程师（HarmonyOS）** | 自定义 `frontend-harmonyos` | 任何涉及 `apps/harmonyos/` 的改动 | ArkTS 页面、token 无感刷新、与 Flutter 功能集对齐（34 页维护） |
| **安全/DevOps 工程师** | `consensus/security-manager` + `performance-benchmarker` | 安全加固、性能、部署 | 18 层防护回归、Docker/gRPC 子服务、迁移回滚、可观测性、Prometheus 指标 |

### 2.3 按需角色（任务触发，2 角色）

| 角色 | 现有 agent 对应 | 启用条件 |
|------|-----------------|----------|
| **研究员** | 自定义 `researcher` | 新模块/新功能设计前：调研竞品，比对 `docs/API.md`、`docs/FUNCTIONS.md` 与实现差异，输出设计输入 |
| **版本协调员** | 自定义 `edition-coordinator` | 涉及 `lite/standard/full` 差异：三分支同步、`docs/EDITIONS.md` 矩阵校验、分支间回归 |

---

## 3. 协作模式

### 3.1 通则（沿用根目录 CLAUDE.md）

- **SendMessage-First**：agent 之间通过 SendMessage 直接通信，不轮询、不共享可变状态；
- **命名必填**：每个 agent 必须命名（`name: "role"`）；
- **一次 spawn**：独立子任务一次性后台拉起，Lead 停下等待结果，不轮询状态；
- **消息必带**：每个 prompt 写明"完成后 SendMessage 给谁、发送什么"。

### 3.2 三种编排拓扑

| 模式 | 流程 | 使用场景 |
|------|------|----------|
| **Pipeline** | Lead → 架构师 → 后端 → 测试 → 审查 | 有顺序依赖的功能开发（新模块、跨模块数据流） |
| **Fan-out** | Lead → A, B, C → Lead 汇总 | 相互独立的并行工作（多页面、多模块调研） |
| **Supervisor** | Lead ↔ 成员多轮往返 | 持续协调的复杂工作（微服务拆分、大规模重构） |

### 3.3 任务路由表

| 任务类型 | 编排 | 参与角色 |
|----------|------|----------|
| 新模块 / 新功能（如 DMS、BI 深化） | pipeline | Lead → 架构师(表设计) → 后端 → 测试 → 审查 |
| 引擎级算法（复式记账 / 薪资 / MRP） | pipeline + TDD | Lead → 业务引擎专家(设计) → 测试(边界用例先行) → 审查 |
| 前端页面（Flutter / HarmonyOS 并行） | fan-out | Lead → 前端×2 + 后端(API 对齐) 并行 → Lead 汇总 |
| 跨模块数据流（采购→库存→应付等） | pipeline | Lead → 架构师 → 后端 → 测试 → 审查 |
| 微服务拆分 / 大规模重构 | supervisor | Lead ↔ 架构师 + 后端 + 审查 多轮 |
| 安全 / 性能专项 | 单线程深挖 | Lead → 安全/DevOps 工程师 → 审查 |
| Bug 修复（单文件 / 1-2 行） | 不进团队 | Lead 直接处理，或 1 个 agent 完成 |
| 三分支差异 / 版本发布 | pipeline | Lead → 版本协调员 → 测试(跨分支回归) → 审查 |

### 3.4 质量门禁（提交前必经，由审查员把守）

```
phpunit            # 137 测试 / 805 断言全绿，新增用例随改动提交
phpstan            # 不允许新增 baseline 之外的问题
php-cs-fixer       # --dry-run 通过
composer audit     # 无高危依赖漏洞
```

涉及数据库的改动必须经过架构师（163 表 + 29 迁移归属明确）；涉及前端改动必须跑 Flutter `flutter analyze` 0 error / 0 warning。

---

## 4. 团队规模建议

| 工作形态 | 建议规模 | 说明 |
|----------|----------|------|
| 日常维护 / 小修 | 1-2 人 | Lead 直接处理，避免过度编排 |
| 单模块迭代 | 3 人 | Lead + 后端 + 测试 |
| 跨模块功能 | 4-5 人 | Lead + 架构师 + 后端 + 测试 + 审查 |
| 前端双端并行 | 4-5 人 | Lead + Flutter + HarmonyOS + 后端(API) + 测试 |
| 引擎级 / 复杂重构 | 5-7 人 | 上述全量 + 业务引擎专家或安全/DevOps |

> 与 `.claude-flow/config.yaml`（`maxAgents: 15`、`hierarchical-mesh`、`consensus` 协调策略）兼容，单次任务占用不超过上限。

---

## 5. 落地步骤

1. **补齐角色定义**：`.claude/agents/` 已有 planner / sparc / testing / swarm / consensus，缺 `business-engineer`、`frontend-flutter`、`frontend-harmonyos`、`researcher`、`edition-coordinator` 五个定义；按现有 YAML/MD 格式各加一个文件即完成挂载；
2. **固化路由**：将 §3.3 路由表写入 `.claude-flow/hooks` 的 routing 逻辑，让 `UserPromptSubmit` 钩子自动把任务派给对应角色；
3. **记忆分域**：`.claude-flow` 已开 `agentScopes`（`defaultScope: project`），建议按 `backend / frontend / ops / security` 四域存档，避免财务引擎上下文污染前端任务；
4. **试点运行**：挑一个跨模块任务（如 DMS 深化或 BI 看板迭代）按 §3.3 路由完整跑一轮，验证消息链与门禁后推广。

---

## 6. 变更记录

| 日期 | 变更 |
|------|------|
| 2026-08-07 | 初版：基于 22 模块现状（P0~P3 已交付、89/100）制定核心 5 + 专业 4 + 按需 2 团队 |
