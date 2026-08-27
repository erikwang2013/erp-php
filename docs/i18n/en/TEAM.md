# Team Planning (AI Collaboration Team)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> This document defines the AI collaboration team for this project: role composition, responsibility boundaries, collaboration patterns, and task routing.
> The accompanying coordination rules (SendMessage-First, agent naming, lifecycle) are in the root `CLAUDE.md`; role definitions are in `.claude/agents/`.

---

## 1. Project Profile (Planning Basis)

| Dimension | Current State | Implication for the Team |
|------|------|--------------|
| Backend | webman (Workerman) PHP 8.3+, **22 business modules**, 121+ controllers, 24 services, 161 models, 163 tables, 12 middleware (schema uses database/install.sql as its single source of truth) | Large all-in-one monolith; divide work by business domain to prevent single-agent context explosion |
| Frontend | Flutter **97 pages** (Web/mobile) + HarmonyOS **34 pages**, covering all modules | Two frontends maintained in parallel; dedicated frontend roles required |
| Quality baseline | PHPUnit 137 tests / 805 assertions, PHPStan + baseline, CS-Fixer, CI multi-version matrix | Discipline already in place; testing/review roles plug directly into the pipeline |
| Version matrix | `lite` / `standard` / `full` three branches (62/72/163 tables) | Changes must consider cross-branch sync; version coordination needed |
| Roadmap | P0~P3 delivered (overall score 89/100), entering daily iteration and evolution phase | Team scales by task type, not a large permanent project staff |
| Existing facilities | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, max 15 agents, consensus coordination), hooks + memory | Team mounts directly onto existing config; no reinvention |

---

## 2. Team Composition

### 2.1 Core Team (resident, 5 roles)

| Role | Existing Agent Mapping | Responsibilities (for this project) |
|------|-----------------|--------------------|
| **Project Manager Lead** | `planner` / `swarm/hierarchical-coordinator` | Requirement breakdown → routing → acceptance; maintains the 22-module task queue; decides pipeline / fan-out / supervisor patterns; relays messages across roles |
| **System Architect** | `sparc/architecture` | Table structure design (163 tables, schema uses database/install.sql as its single source of truth); cross-module data flows (purchase receiving→inventory→AP, sales delivery→AR→stock-out chains, etc.); microservice split boundary decisions |
| **Backend Developer** | `core` / custom `backend-dev` | Controller / service / model implementation; follows the `app/service` layering and middleware chain (Locale→Cors→SecurityFilter→RateLimit→TracingId→business middleware) |
| **Test Engineer** | `testing/tdd-london-swarm` + `production-validator` | PHPUnit test-first (engine boundary tests); three-branch regression verification; filling `tests/` coverage gaps |
| **Code Reviewer** | `consensus/security-manager` | PHPStan zero new baseline issues, CS-Fixer compliance, 18-layer security pattern checks; guards the pre-commit quality gate |

### 2.2 Specialist Team (drawn per task type, 4 roles)

| Role | Existing Agent Mapping | Activation Scenario | Typical Tasks |
|------|-----------------|----------|----------|
| **Business Engine Expert** | custom `business-engineer` | Algorithm-heavy modules such as finance / payroll / MRP | Algorithm hardening and boundary handling for the double-entry engine, payroll engine, MRP engine (A-tier "industrial-grade" requirement) |
| **Frontend Engineer (Flutter)** | custom `frontend-flutter` | Any change touching `apps/flutter/` | Web admin panel pages, GetX state, ApiService/export integration, 97-page maintenance |
| **Frontend Engineer (HarmonyOS)** | custom `frontend-harmonyos` | Any change touching `apps/harmonyos/` | ArkTS pages, token silent refresh, feature alignment with Flutter (34-page maintenance) |
| **Security/DevOps Engineer** | `consensus/security-manager` + `performance-benchmarker` | Security hardening, performance, deployment | 18-layer protection regression, Docker/gRPC sub-services, migration rollback, observability, Prometheus metrics |

### 2.3 On-Demand Roles (task-triggered, 2 roles)

| Role | Existing Agent Mapping | Activation Condition |
|------|-----------------|----------|
| **Researcher** | custom `researcher` | Before new module/feature design: research competitors, compare `API.md`, `FUNCTIONS.md` against implementation differences, produce design inputs |
| **Edition Coordinator** | custom `edition-coordinator` | When `lite/standard/full` differences are involved: three-branch sync, `EDITIONS.md` matrix validation, cross-branch regression |

---

## 3. Collaboration Patterns

### 3.1 General Rules (per root CLAUDE.md)

- **SendMessage-First**: agents communicate directly via SendMessage; no polling, no shared mutable state;
- **Naming required**: every agent must be named (`name: "role"`);
- **One-shot spawn**: independent subtasks are launched in the background at once; the Lead stops and waits for results, no polling of status;
- **Message must include**: every prompt states "after completion, SendMessage to whom, with what".

### 3.2 Three Orchestration Topologies

| Pattern | Flow | Use Cases |
|------|------|----------|
| **Pipeline** | Lead → Architect → Backend → Test → Review | Feature development with sequential dependencies (new modules, cross-module data flows) |
| **Fan-out** | Lead → A, B, C → Lead aggregates | Mutually independent parallel work (multiple pages, multi-module research) |
| **Supervisor** | Lead ↔ members, multiple rounds | Complex work requiring ongoing coordination (microservice splits, large-scale refactors) |

### 3.3 Task Routing Table

| Task Type | Orchestration | Participating Roles |
|----------|------|----------|
| New module / new feature (e.g. DMS, BI deepening) | pipeline | Lead → Architect(table design) → Backend → Test → Review |
| Engine-level algorithms (double-entry / payroll / MRP) | pipeline + TDD | Lead → Business Engine Expert(design) → Test(boundary cases first) → Review |
| Frontend pages (Flutter / HarmonyOS in parallel) | fan-out | Lead → Frontend×2 + Backend(API alignment) in parallel → Lead aggregates |
| Cross-module data flows (purchase→inventory→AP, etc.) | pipeline | Lead → Architect → Backend → Test → Review |
| Microservice split / large-scale refactor | supervisor | Lead ↔ Architect + Backend + Review, multiple rounds |
| Security / performance specials | single-thread deep dive | Lead → Security/DevOps Engineer → Review |
| Bug fixes (single file / 1-2 lines) | not in the team | Lead handles directly, or 1 agent completes it |
| Three-branch differences / version releases | pipeline | Lead → Edition Coordinator → Test(cross-branch regression) → Review |

### 3.4 Quality Gate (mandatory before commit, guarded by the reviewer)

```
phpunit            # 137 tests / 805 assertions all green; new cases submitted with changes
phpstan            # no issues beyond the baseline allowed
php-cs-fixer       # --dry-run passes
composer audit     # no high-severity dependency vulnerabilities
```

Database-related changes must go through the architect (163 tables, schema uses database/install.sql as its single source of truth); frontend changes must pass Flutter `flutter analyze` with 0 error / 0 warning.

---

## 4. Team Size Recommendations

| Working Mode | Recommended Size | Notes |
|----------|----------|------|
| Daily maintenance / small fixes | 1-2 people | Lead handles directly; avoid over-orchestration |
| Single-module iteration | 3 people | Lead + Backend + Test |
| Cross-module features | 4-5 people | Lead + Architect + Backend + Test + Review |
| Dual-frontend parallel | 4-5 people | Lead + Flutter + HarmonyOS + Backend(API) + Test |
| Engine-level / complex refactors | 5-7 people | All of the above + Business Engine Expert or Security/DevOps |

> Compatible with `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, `consensus` coordination strategy); a single task's usage never exceeds the limit.

---

## 5. Rollout Steps

1. **Fill in role definitions**: `.claude/agents/` already has planner / sparc / testing / swarm / consensus; five definitions are missing — `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator`; adding one file each in the existing YAML/MD format completes the mounting;
2. **Solidify routing**: write the §3.3 routing table into the routing logic of `.claude-flow/hooks` so the `UserPromptSubmit` hook auto-dispatches tasks to the corresponding role;
3. **Memory partitioning**: `.claude-flow` already has `agentScopes` enabled (`defaultScope: project`); recommend archiving by the four domains `backend / frontend / ops / security` to avoid finance-engine context polluting frontend tasks;
4. **Pilot run**: pick one cross-module task (e.g. DMS deepening or BI dashboard iteration), run a full round per §3.3 routing, verify the message chain and quality gate, then roll out.

---

## 6. Change Log

| Date | Change |
|------|------|
| 2026-08-07 | Initial version: core 5 + specialist 4 + on-demand 2 team based on the 22-module status (P0~P3 delivered, 89/100) |
