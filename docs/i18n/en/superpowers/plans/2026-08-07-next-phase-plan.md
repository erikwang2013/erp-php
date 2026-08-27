# Next Phase (P4 / Evolution Release 1.1) Project Plan

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Prepared by: System Architect ｜ Date: 2026-08-07 ｜ Based on: three prior investigations (planning & gaps / backend & quality / frontend) + on-site spot-check review
> Status: Draft (pending review) ｜ Target version: 1.1 (evolution release)

---

## 1. Phase Positioning

The P0~P3 roadmap has been fully delivered: 22 business modules, 163 tables, 121 controllers, 24 services, 161 models, 12 middleware; Flutter 96 pages + HarmonyOS 34 pages; overall score 89/100. **This phase adds no new business domains** — instead it closes the loop on capabilities that "exist but are not wired up", manages quality debt, eliminates documentation drift, and produces a long-term maintainable **1.1 evolution release**.

Three core judgments (all confirmed by spot checks):

1. **Many capabilities "exist but are not in effect"**: the TenantScope middleware and model trait are not registered in `config/middleware.php` (multi-tenancy is an empty shell); the queue is configured with redis/rabbitmq dual drivers but `config/process.php` has no consumer process; WebSocket connections do not validate JWT; the Flutter dashboard's OMS/WMS/TMS statistics are hardcoded fake values while the backend `/dashboard/oms|wms|tms` endpoints already exist but are never called; the frontend calls a nonexistent notification endpoint `/admin/notification/my/read` (the backend actually has `/admin/notification/read-all`).
2. **Quality and security debt**: 11 business modules have zero tests; PHPStan is at level 5 but the baseline suppresses 974 errors; all 137 tests are pure unit tests with no integration/E2E/coverage; `.env.docker` has many weak secrets; CI only has PHP jobs with no frontend quality gates.
3. **Systematic documentation drift**: test counts 132/779→135/799→137/805 are inconsistent across three versions; the FUNCTIONS.md appendix differs greatly from measurements; EDITIONS.md numbers contradict themselves; the lite/standard/full branches lag main by 20~41 commits.

**Principles**: first close the "exists but not wired up" items (dead endpoints, unwired TenantScope/queue, mock dashboard), then add tests and quality gates, then improve structure and documentation. All tasks are small and well-defined and can be completed in a single agent session; items that are uncertain are marked "to be verified".

---

## 2. Gap Analysis (Summary)

The gaps from the three investigations are grouped into **6 workstreams**. Each item gives an evidence path.

### Workstream A: Business Closure (highest priority)

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| A1 | Notification "mark all as read" frontend calls a nonexistent endpoint | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` calls `/admin/notification/my/read`; the backend route is `POST /admin/notification/read-all` at `config/route.php:250` | Confirmed |
| A2 | Dashboard OMS/WMS/TMS stats are mock fake values, requests carry no JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (standalone Dio with `baseUrl: http://localhost:8787`, no interceptors; `omsStats/wmsStats/tmsStats` hardcoded; comment "Mock values for now"); real backend endpoints at `config/route.php:231-233` | Confirmed |
| A3 | TenantScope middleware and model trait not wired up, multi-tenancy is an empty shell | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` exist; the global chain in `config/middleware.php` only registers Locale/Cors/SecurityFilter/RateLimit/TracingId, and route.php groups have no reference either | Confirmed |
| A4 | Queue has dual drivers but no consumer process, end-to-end not in effect | `config/queue.php` (default redis, optional rabbitmq); `config/process.php` only has webman/socket/monitor three processes | Confirmed |
| A5 | WebSocket has no authentication | `app/process/WebSocket.php:23` comment "could validate JWT here"; `:47-50` the auth message directly returns success:true without validating the token | Confirmed |
| A6 | HarmonyOS 25 list pages have broken pagination parameters (`${this.page}` inside single quotes is not interpolated) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (spot-checked); 24 more occurrences of the same pattern | Confirmed (full list pending verification) |
| A7 | Business action endpoints largely not wired to the frontend (settlement/three statements/fulfillment/approval/payroll calculation, etc.) | Conclusion of the coverage-matrix investigation; e.g. purchase/sales lack settlement pages, finance lacks 13 endpoints, CRM lacks follow/funnel/contract flow | To be verified (needs per-module checklist review) |
| A8 | Many business page forms only have generic name/code fields | Investigation conclusion (creating sales orders/accounting vouchers only fills in name and code) | To be verified (needs per-page review) |

### Workstream B: Test System Rebuild

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| B1 | 11 business modules with zero tests: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | 19 test files in `tests/` only cover admin/finance/inventory/oms/wms/tms/notification/hr/mrp/security base classes; the 11 modules above have no dedicated test files — six of them (crm/eam/dms/quality/report/workflow) are **never mentioned** in any test file; project/purchase/sales/product/bi are only referenced incidentally by generic base-class tests or adjacent-module tests (ControllerPatternTest pattern sampling, bootstrap.php route list, InventoryServiceTest mentioning the purchase/product receiving context, "bi" in DoubleEntryServiceTest as a substring of debit_amount) — none is dedicated coverage | Confirmed |
| B2 | No integration/E2E/coverage; all 137 tests / 805 assertions are pure unit tests (measured: completes within 1.2s, pure in-memory) | `vendor/bin/phpunit` measured "OK (137 tests, 805 assertions)" | Confirmed |
| B3 | PHPStan level 5 but baseline suppresses 974 errors | `phpstan-baseline.neon` measured 974 message nodes | Confirmed |
| B4 | CI has no coverage collection and no integration test job | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, only composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Confirmed |
| B5 | purchase/sales controllers hardcode service dependencies | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143` (both files have `use` declarations at :15-16 and `new InventoryService()/new FinanceService()` instantiation at :142-143) | Confirmed |

### Workstream C: Infrastructure & Security Governance

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| C1 | `.env.docker` weak secrets | `JWT_SECRET_KEY=change-me-...`, `ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`, `DB_PASSWORD=root`, `ES_PASSWORD=changeme`, `RABBITMQ_PASSWORD=guest` (.env.docker:15,32,37,51,67,81) | Confirmed |
| C2 | Environment variable strong validation incomplete | Investigation: only ENCRYPTION_KEY goes through env_required | To be verified (check config/jwt.php, encryption.php) |
| C3 | Fail-open silent error swallowing | Investigation conclusion; scope to be audited (empty try/catch, catch without logging) | To be verified (needs grep audit) |
| C4 | backup-validator.sh and per-migration `_rollback.sql` missing | `find` across the whole repo finds no match; none of the 29 SQL migrations in `database/migrations/` has a corresponding rollback file | Confirmed |
| C5 | Notification channel stubs (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Confirmed |
| C6 | Monitoring gaps: no metrics for queue backlog/WebSocket connection count | `app/admin/controller/MetricsController.php` currently has 5 gauges | Partially confirmed |

### Workstream D: Version Matrix & Documentation Governance

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| D1 | lite/standard/full branches lag main by 20~41 commits | `git rev-list --left-right --count main...lite|standard|full` measured: 41/41/20 behind, and lite/standard each have 6~7 ahead-only commits | Confirmed |
| D2 | EDITIONS.md numbers contradict themselves | Overview table: controllers 48/42/70, business modules 6/6/12; the upgrade-path section however writes 12/12/19 modules, 163 tables; inconsistent with the measured 121 controllers | Confirmed |
| D3 | FUNCTIONS.md appendix drift | Appendix writes 11 files/90 methods/168 assertions/9 middleware/22 migrations; measured: 19~20 files/137 tests/805 assertions/12 middleware/29 migrations | Confirmed |
| D4 | Test counts drift across three versions (132/779→135/799→137/805) | Documentation history and git commit records | Confirmed |
| D5 | The completion matrix marks QMS/EAM/DMS/BI as 🔴 but the code already exists | Matrix near `docs/FUNCTIONS.md:555` vs implemented `app/controller/{quality,eam,dms,bi}/` | Confirmed |
| D6 | Controller counting inconsistency: docs/CLAUDE.md says "104 business controllers", measured total 122 | `find app -path '*/controller/*.php' | wc -l` = 122 (including admin 14 + api 3 + business 104 + Index/Install); investigation says 121 | Confirmed (counting discrepancy) |
| D7 | Migration counting discrepancy: investigation 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (numbered up to 000030, missing 000007/000008) | Confirmed (29 is the measurement) |

### Workstream E: Frontend Quality & Alignment

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| E1 | CI has no flutter analyze/test/build and no hvigor build | `.github/workflows/ci.yml` only has PHP jobs | Confirmed |
| E2 | README claims CI includes Flutter static analysis, which does not match reality | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml has no such step | Confirmed |
| E3 | Flutter has only 1 smoke test | `apps/flutter/test/widget_test.dart` is the only test file | Confirmed |
| E4 | HarmonyOS token not persisted (AppStorage is memory-only, cold start returns to login page) | Investigation conclusion (to be checked against `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`) | To be verified |
| E5 | HarmonyOS 25 pages are templated, read-only name/code lists with no CRUD | Spot-checked OrderListPage.ets, all 65 lines: only read-only name/code lists | Confirmed |
| E6 | Frontend coverage depth insufficient (see A7/A8) | Same as above | To be verified |

### Workstream F: API Layering & Architecture Governance (low priority, do what's feasible)

| # | Gap | Evidence Path | Status |
|---|-----|---------------|--------|
| F1 | /api versioning only has 3 controllers, all business logic is in the single /admin block | `app/api/v1/controller/` only has Captcha/Auth/Product | Confirmed |
| F2 | Controllers in 10 modules query models directly with no service layer | Investigation conclusion (crm/product etc. controllers use model queries directly) | Partially confirmed (pending full audit) |
| F3 | purchase/sales hardcode `new` services instead of dependency injection | B5 evidence | Confirmed |

---

## 3. Phased Planning

Divided by priority into three batches (P0→P1→P2), **each phase can be released independently and all acceptance criteria are quantifiable**. Total duration about **8~9 weeks** (parallelism assumption: estimated with **2~3 developers in parallel + agent team collaboration**; the single-task total is about **77 person-days** — P0 ≈12.5d, P1 ≈29.5d, P2 ≈35d — if executed serially by one person it would take about 15 weeks. Parallelism rationale: small backend tasks like A1/A4/A5 are independent and can run in parallel; B1 module tests can be split into parallel subtasks; B/C groups and E/D groups can overlap across phases; Flutter/HarmonyOS frontend tasks do not block backend tasks; explicit task dependencies are in §5).

**Numbering system**: phase task numbers correspond one-to-one with the §2 gap numbers (A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3); D6/D7 (controller and migration counting) are merged into task D3, E2 (false README claim) is merged into E1's acceptance, E6 (coverage depth) is merged into A7-2, and F1 (/api versioning) is explicitly out of scope this phase (see §6); there is also an i18n task corresponding to the investigation's "Flutter i18n not completed", not numbered in the gap table.

### 3.1 Batch 1 P0: Closure Baseline (Weeks 1~2)

**Goal**: eliminate dead endpoints and fake data, turn the existing unwired capabilities (TenantScope/queue/WebSocket) into working or explicitly downgraded.

| Task | Content | Scope | Acceptance Criteria | Duration |
|------|---------|-------|---------------------|----------|
| A1 | Fix notification "mark all as read": frontend changes to call `POST /admin/notification/read-all` (or backend adds an alias route — pick one; changing the frontend is recommended) | `notification_page.dart` + `config/route.php` | Manual/automated call passes; add 1 PHPUnit assertion that the route exists | 0.5d |
| A2 | Dashboard connects real data: remove standalone Dio, use ApiService (JWT interceptor) instead; the OMS/WMS/TMS three tabs call `/dashboard/oms\|wms\|tms`; delete hardcoded fake values; keep the Redis 5m cache semantics | `dashboard_controller.dart` + related pages | With a login session, the dashboard's three tabs show real backend data; the Network panel shows 200 with an Authorization header; delete mock comments | 2d |
| A3 | Wire up TenantScope: register in the `/admin` route group; tenant ID comes from a JWT claim or the `X-Tenant-Id` header (**decision point**, see §5); the model trait is ready and needs no major change | `config/route.php`, `app/middleware/TenantScope.php`, `config/middleware.php` | Two tenants' data are mutually invisible (add integration test); requests without a tenant header return 400 instead of silently passing; **fallback downgrade**: if the timing is judged premature, instead document clearly that "multi-tenancy is a reserved capability" with activation steps; acceptance = documentation matches code | 2d |
| A4 | Queue end-to-end: add a `redis-queue` consumer process to config/process.php (default redis driver); add one observable smoke task (e.g. asynchronous operation log write); document the steps to switch to rabbitmq | `config/process.php`, `app/queue/` | After startup the consumer process is online (`php start.php status`); after delivering a smoke task the target side effect appears within 5s | 1d |
| A5 | WebSocket auth: validate JWT on connection establishment/`auth` message (reuse AdminAuth logic); invalid tokens return auth_result:false and disconnect; sync documentation | `app/process/WebSocket.php` + frontend connection code | Connections without/with forged tokens are rejected; valid token connects successfully; add 1 test covering it | 1d |
| A6 | Fix HarmonyOS pagination: change 25 single-quote interpolations to template strings/concatenation; page increment + scroll-to-bottom loading + pull-to-refresh; unify by extracting a pagination component | `apps/harmonyos/entry/src/main/ets/pages/**` (25 files) | grep across the repo finds no remaining `${this.page}` single-quote pattern; list page-turn request parameters are correct; build passes | 2d |
| A7-1 | Clear all dead endpoints: using the investigation coverage matrix as the base, run a "frontend URL × backend route" automated comparison (a script extracts Flutter/HarmonyOS request strings vs `config/route.php`) and output the remaining difference list | `apps/flutter/lib`, `apps/harmonyos/.../pages`, `config/route.php` | The comparison script artifact is committed to the repo (docs/); "frontend calls but backend doesn't exist" items in the difference list drop to zero (items that are legitimately nonexistent are marked on an allowlist) | 2d |
| A8-1 | Add fields to high-value forms: add business-critical fields (amount/date/counterparty/line items) to purchase/sales order and accounting voucher pages; only fill in fields, no form engine | Corresponding Flutter pages | Forms can create complete documents with business fields, API returns 200 | 2d |

**P0 acceptance summary**: A1~A6 all landed; the dead-endpoint list is at zero; CI all green; no new documentation drift (changes sync the feature list in docs/CLAUDE.md).

### 3.2 Batch 2 P1: Test & Security Baseline (Weeks 3~5)

**Goal**: upgrade the test system from "pure unit tests" to "unit + integration + coverage", and eliminate security weaknesses.

| Task | Content | Scope | Acceptance Criteria | Duration |
|------|---------|-------|---------------------|----------|
| B1 | Add tests to the 11 business modules: write service/model layer tests per module covering CRUD + core actions (settlement, approval flows, QC process, equipment work orders, etc.) | `tests/` (add crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow test files) | Add ≥150 tests / ≥500 assertions; each of the 11 modules has ≥10 tests; `vendor/bin/phpunit` all green | 2w |
| B2 | Integration tests: use the existing mysql8/redis7 services in CI, add an integration test group (real-DB CRUD + transaction rollback + TenantScope isolation verification + queue smoke) | `tests/Integration/` + phpunit.xml groups | Integration group all green in CI; locally runnable with `--group=integration` | 1w |
| B3 | E2E smoke: run real HTTP through health→login→core CRUD→dashboard, scripted | `tests/E2E/` (curl/php scripts) | New CI job runs 10 core paths, red on failure | 2d |
| B4 | Coverage: integrate phpunit --coverage, set thresholds (business layer ≥40%, overall ≥30%, pending verification of whether CI supports xdebug collection) | `phpunit.xml`, `ci.yml` | CI produces a coverage report; fails below the threshold | 1d |
| B5 | Controller service-ization (high-frequency 4 modules): finance/inventory/sales/purchase controllers drop `new` and fetch services from the container (`support\Container`), paving the way for B1 tests | `app/controller/{finance,inventory,sales,purchase}/**` | No `new InventoryService/FinanceService` remnants; existing tests all green | 3d |
| C1 | Eliminate weak secrets: `.env.docker`/`.env.example` use random placeholders + strong startup validation (refuse to start if missing or equal to placeholder); CI adds an `env validation` step | `.env*`, `config/*.php`, `ci.yml` | Starting with `change-me` fails immediately with guidance; a fresh Docker instance auto-generates random secrets | 1d |
| C2 | Extend strong env validation: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD into env_required (first check the current state of config/jwt.php, to be verified) | `config/*.php` | Startup fails when any critical secret is missing, with a clear Chinese error message | 1d |
| C3 | Fail-open audit: grep for empty catches/catches without logging, change to fail-closed + logging (including TraceId) | entire app/ | Audit list committed; fixed items have test or log evidence | 2d |
| C4 | Migration governance: add `database/backup/backup-validator.sh` (auto restore verification after backup) + 29 per-migration `_rollback.sql` (reverse-engineered from install.sql table structures) | `database/` | Validator script runs against a backup file (backup→restore→compare table counts/row counts); every migration file has a same-named `_rollback.sql` beside it | 2d |
| C5 | Notification channel delivery (corresponds to gap C5): get at least one usable channel working (recommended email: SMTP driver or file-log driver to implement sending); if the timing is judged premature, explicitly document the downgrade to "in-app messages only + reserved email/wecom/dingtalk adapters" with integration steps (choose one, must be an explicit decision) | `app/service/notification/ChannelRouter.php` + new driver classes + docs | Email driver: ChannelRouter returns true after a notification is sent successfully (tests assert with a log driver); if downgraded: the comment at ChannelRouter.php:23 and docs clearly mark the "reserved" state, removing the "stub for future implementation" ambiguity | 1.5d |
| C6 | Add monitoring metrics: queue backlog (redis LLEN), WebSocket online connection count | `MetricsController.php` | `/metrics` output adds 2 new gauges | 1d |

**P1 acceptance summary**: total tests ≥287 (137+150); coverage report produced and passing the threshold; startup fails with weak/missing secrets; validator and rollback scripts in place; at least one notification channel usable or clearly documented as downgraded; new CI integration/E2E/coverage jobs all green.

### 3.3 Batch 3 P2: Documentation, Version Matrix & Frontend Depth (Weeks 6~8)

**Goal**: documentation numbers fully align with code facts (automated verification), the version matrix regains credibility, and the frontend adds high-value depth.

| Task | Content | Scope | Acceptance Criteria | Duration |
|------|---------|-------|---------------------|----------|
| D1 | Sync the three branches: merge main into lite/standard/full, resolve conflicts, all three branches CI green; **decision point**: thereafter adopt the strategy "main is the single development source, version branches only cherry-pick at release" | git three branches + ci.yml | All three branches behind=0; each branch's own CI green; conflict resolutions recorded | 1w |
| D2 | Rewrite EDITIONS.md: use measurements as truth (table/controller/module counts from a code-counting script), delete self-contradictory sections | `docs/EDITIONS.md` | All numbers in the doc match script output | 1d |
| D3 | Automate doc statistics: write `scripts/doc-stats.sh` (controller/service/model/migration/test/middleware counts + phpunit output), change the FUNCTIONS.md appendix to reference its output; also unify D6 (controller counting 104/121/122) and D7 (migration counting 22/29/30) to the script's single counting convention | `scripts/doc-stats.sh`, `docs/FUNCTIONS.md`, `docs/CLAUDE.md` | Script output matches the docs; all numbers in README/docs are reproducible from the script (including a single controller/migration counting convention) | 2d |
| D4 | Fix the completion matrix: items actually implemented like QMS/EAM/DMS/BI change to ✅ with code evidence | `docs/FUNCTIONS.md` | Matrix corresponds one-to-one with `app/controller/` directories, no 🔴/✅ mismatch | 1d |
| D5 | CI doc-validation job: run doc-stats and compare with the docs, red on drift | `ci.yml` + script | Tampering with one number turns CI red (self-test demo) | 1d |
| E1 | Flutter CI job: flutter analyze + flutter test + build web, wired into ci.yml | `ci.yml`, `apps/flutter/` | All three steps green; the README.md:635 claim matches reality | 1d |
| E3 | Expand Flutter tests: ApiService interceptor/401 refresh, AuthService flow, key form validations, ≥20 widget/unit tests | `apps/flutter/test/` | `flutter test` all green, ≥20 tests | 1w |
| E4 | HarmonyOS token persistence: AppStorage persistent storage + cold-start restore + 401 refresh logic (first check the current state of ApiService, to be verified) | `apps/harmonyos/.../service/ApiService.ets` | Killing the process and restarting keeps the login session; expired tokens auto-refresh | 2d |
| E5 | Add CRUD to HarmonyOS core pages: sorted by value (2~3 list pages each from purchase/sales/inventory/finance/OMS), add Create/Edit/Delete actions and forms to each page | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | The selected ≥10 list pages have CRUD and call the backend successfully; hvigor build passes (if no HarmonyOS SDK environment, mark "pending CI environment readiness") | 1w |
| i18n | Minimal Flutter i18n (corresponds to investigation's "Flutter i18n not completed"): wire ApiService error messages and key login/navigation/dashboard copy into i18n (arb files, linked with backend `app/common/I18n.php`); **minimal viable only, no full-page copy overhaul** | `apps/flutter/lib/app/services/`, `apps/flutter/lib/l10n/` | Key error messages and ≥10 page copy strings can switch with language (en/zh); `flutter test` all green | 2d |
| A7-2 | Frontend deep coverage: per the A7-1 comparison list, add purchase/sales settlement pages, finance three statements/period-end closing/bank accounts, CRM follow/funnel/contract flow, and other key endpoint pages | `apps/flutter/lib/app/pages/**` | High-priority items in the comparison list where "backend exists but frontend doesn't cover" (settlement/three statements/fulfillment/approval/payroll) drop to zero | 1w |
| F2/F3 | Lightweight service-layer extraction (optional, do what's feasible): extract a thin service layer + dependency injection for the 3~5 modules with the heaviest direct model queries; **explicitly not a mandatory full refactor** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Extracted module controllers have no direct model queries; existing tests all green; non-extracted modules document "controller queries model directly, known tech debt" | 1w |

**P2 acceptance summary**: three branches synced with green CI; docs numbers reproducible from script; CI includes Flutter jobs and doc validation; Flutter ≥20 tests; HarmonyOS persistence + ≥10 pages with CRUD; high-priority endpoint coverage at zero.

---

## 4. Acceptance Criteria (Summary, All Verifiable)

- **Endpoints**: A1 notification endpoint, A2 `/dashboard/oms|wms|tms`, A7 high-priority endpoints can all be curl-called with JWT returning 200/business data.
- **Tests**: `vendor/bin/phpunit` all green (≥287 tests); `flutter test` all green (≥20); integration/E2E jobs green in CI.
- **Security**: startup fails with `change-me` secrets; WebSocket rejects invalid tokens; no silent error swallowing in empty catches (audit list).
- **Channels/i18n**: at least one notification channel usable or clearly documented as downgraded; Flutter key error messages and ≥10 copy strings switch between Chinese/English (minimal viable).
- **CI**: all jobs in `.github/workflows/ci.yml` green (PHP matrix + integration + coverage + flutter + doc validation).
- **Docs**: `scripts/doc-stats.sh` output matches all numbers in docs (drift turns CI red).
- **Branches**: `git rev-list --left-right --count main...lite|standard|full` is `0 0` for all.
- **Frontend**: no `${this.page}` single-quote remnants in HarmonyOS; cold start keeps the login session; core pages CRUD successfully call the backend.

---

## 5. Dependencies & Risks

**Dependencies**:
- Group A (closure) → Group B (tests): B1/B2 tests must target **actually usable** endpoints, so P0 first fixes dead endpoints and wiring, then P1 adds tests.
- B5 (controller service-ization) → B1 (tests): **only paves the way for tests of the finance/inventory/sales/purchase four modules it covers** (after removing the hardcoded `new`, services can be injected as mocks; purchase/sales are zero-test modules, finance/inventory already have tests that can be improved along the way); the other zero-test modules (crm/eam/dms/quality/project/product/bi/report/workflow) tests do **not** depend on B5 and can proceed in parallel with B5.
- D1 (branch sync) → D3/D5 (doc validation): after syncing, main is the single source of truth, only then can the doc counting convention be unique.
- E1 (Flutter CI) → E3 (test expansion): only with a gate in place does expanding tests provide protection.

**Risks & Mitigations**:
| Risk | Impact | Mitigation |
|------|--------|------------|
| Wiring TenantScope affects all /admin queries, may introduce data-visibility regressions | High | Integration tests first; take the tenant from a JWT claim (no frontend change needed); or downgrade within P0 to "documented as reserved" with an explicit decision |
| Three-branch sync merge conflicts may introduce regressions | Medium-high | Main green first; after merging, deliver only when each branch's own CI is fully green; conflict resolutions recorded |
| Queue consumer process unavailable in some environments (rabbitmq) | Medium | Default redis driver (CI already has redis7), rabbitmq only documented as a switch step |
| WebSocket auth change breaks existing clients | Medium | Frontend and backend modified together in the same milestone; invalid tokens are rejected without affecting valid sessions |
| Coverage matrix/form field lists are investigation conclusions, some "to be verified" | Medium | A7-1 first builds the automated comparison script; follow script results, don't add pages from impressions |
| Service-layer refactor scope out of control | Medium | Explicitly only extract 3~5 modules, no mandatory full refactor; no full /api versioning (F1 not this phase) |
| Coverage threshold unavailable in CI environment (xdebug not installed) | Low | First produce local report + documented threshold, integrate CI collection after "to be verified" |
| HarmonyOS CI (hvigor) needs the HarmonyOS SDK, may be unavailable in public CI environments | Medium | Mark "pending CI environment readiness"; local build verification is authoritative and does not block other tasks |

---

## 6. Explicitly Out of Scope

Continuing the roadmap §12 exclusions, unless a strong reason appears (needs separate review and project initiation):
- ❌ Microservice split / K8s deployment (experiment kept in `.claude/worktrees/microservices-split/`, not merged into mainline)
- ❌ AI/ML capabilities (prediction, smart recommendations, NLP)
- ❌ Native apps (iOS/Android native) — Flutter already covers all platforms
- ❌ GraphQL interfaces
- ❌ Hardware integration (IoT/scan guns/printers direct connection)
- ❌ Complete multi-tenant commercialization (SaaS billing, tenant self-service onboarding) — this phase only minimal wiring or documented reservation
- ❌ Full /api versioning (F1) — business endpoints stay on /admin, recorded as architecture debt
- ❌ Full service-layer refactor and full form redo — extract by value, no "big-bang" refactor
- ❌ Complete HarmonyOS page completion — only high-value core pages get CRUD
- ❌ Full Flutter i18n copy overhaul — this phase is minimal viable only (error messages + ≥10 key copy strings); full-page multilingual support is left for later versions

---

## 7. Milestone Suggestions

| Milestone | Time | Content | Exit Criteria |
|-----------|------|---------|---------------|
| **M1 Closure Baseline** | End of week 2 | All of Group A: dead endpoints to zero, real dashboard data, TenantScope/queue/WebSocket landed, HarmonyOS pagination fixed | All P0 acceptance summary items pass |
| **M2 Quality Baseline** | End of week 5 | All of Group B + Group C security items: 11-module tests, integration/E2E/coverage, weak secrets eliminated, fail-open audit, migration governance, notification channels | All P1 acceptance summary items pass |
| **M3 Frontend Quality** | End of week 6 | Group E: Flutter CI job + test expansion, HarmonyOS token persistence and core-page CRUD | flutter CI green, persistence in effect, ≥10 pages with CRUD |
| **M4 Version & Doc Governance** | End of week 7 | Group D: three-branch sync, EDITIONS/FUNCTIONS rewrite, doc-stats automation + CI validation | Branches synced, doc drift turns red |
| **M5 Deep Coverage** | End of week 8 | A7-2 frontend depth + Group F lightweight service extraction | High-priority endpoint coverage at zero, extracted modules have no direct model queries |
| **M6 1.1 Release** | End of week 9 | Full regression, release notes (CHANGELOG), final doc verification, archival | All milestone exit criteria pass (hard metrics): total tests ≥287 with phpunit all green, coverage report passes threshold, all ci.yml jobs green (PHP matrix+integration+coverage+flutter+doc validation), three branches synced at 0 0, dead-endpoint list at zero, doc-stats drift-turns-red mechanism in effect; CHANGELOG and final doc verification pass; review re-check is for reference only, no score threshold |

---

## Appendix: Key Files Spot-Checked and Verified for This Plan

- `config/middleware.php`, `config/route.php` (:231-233 dashboard endpoints, :248-251 notification routes, :387-415 middleware groups)
- `config/process.php`, `config/queue.php`
- `app/middleware/TenantScope.php`, `app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php` (:23, :47-50)
- `app/service/notification/ChannelRouter.php` (:23 stub)
- `app/controller/sales/DeliveryController.php` (:142-143), `app/controller/purchase/ReceiveController.php` (:142-143, both files' `new` instantiation is here; `use` declarations at :15-16)
- `app/api/v1/controller/` (only 3 controllers)
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (mock stats + standalone Dio)
- `apps/flutter/lib/app/pages/notification/notification_page.dart` (:43 dead endpoint)
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets` (:24 interpolation bug)
- `tests/` (19 test file list), `vendor/bin/phpunit` measured 137/805
- `phpstan-baseline.neon` (974 messages)
- `.github/workflows/ci.yml` (only PHP jobs), `README.md` (:635 false claim)
- `.env.docker` (weak secrets), `database/migrations/` (29, no _rollback)
- `docs/EDITIONS.md` (self-contradictory), `docs/FUNCTIONS.md` (appendix drift), `docs/CLAUDE.md` (104 vs measured 122 controller counting)
- git branches `lite/standard/full` (behind 41/41/20)

> Counting note: controllers measured with `find app -path '*/controller/*.php'` = 122 (including admin 14 + api 3 + business controllers + Index/Install); investigation says 121, docs/CLAUDE.md business counting says 104; the difference between the three stems from different statistical scopes, listed as governance item D6 to unify the counting convention.
