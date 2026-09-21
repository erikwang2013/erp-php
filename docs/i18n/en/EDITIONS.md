# Edition Comparison

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Statistics are collected live by `bash scripts/doc-stats.sh` and marked in the docs with `<!-- stats:key=value -->`.
> CI (the docs job in `.github/workflows/ci.yml`) automatically verifies that docs stay consistent with the code facts; drift turns red.

The Open ERP System provides three editions to fit the needs of businesses of different sizes.

---

## Edition Overview

| Dimension | Lite (简化版) | Standard (标准版) | Full (完整版) |
|------|:---:|:---:|:---:|
| Branch | `lite` | `standard` | `full` |
| Data tables | 62 (planned) | 72 (planned) | 227 <!-- stats:tables=227 --> |
| Controllers | 48 (planned) | 42 (planned) | 159 <!-- stats:controllers=159 --> |
| Business modules | 6 (planned) | 6 (planned) | 23 <!-- stats:modules=23 --> |

> **Measurement note**: the repository currently implements only one codebase — the Full edition; the Lite/Standard columns are product planning values (no corresponding branches exist, see "Branch Strategy" below) and are not subject to doc-stats validation. The Full column numbers are measured by `scripts/doc-stats.sh` (227 tables / 159 controllers / 23 business modules), consistent with the appendix measurement in `docs/FUNCTIONS.md`.
> **Branch facts** (measured 2026-09-22 with `git branch -a` + `git ls-remote --heads origin`): both locally and on the remote the repository has only the single branch `main`; the `lite` / `standard` / `full` branches have **been deleted** (a 2026-08-31 measurement still found all three coexisting, all stopped at the 2026-08-17 commit `eea90c0`, with no differences between them and 38 commits behind `main`). That archived commit is still in `main`'s history (`git merge-base --is-ancestor eea90c0 main` holds), so edition differences can now only be traced through commits and tags — there is no edition branch left in the repository to check out.

---

## v1.17.0 Changes (2026-09-15)

> The edition positioning is unchanged: the repository still implements only one codebase, the Full edition; Lite/Standard are product planning values, and their branches have been archived and frozen.

- **The admin side went from two implementations to three**: Angular 22 (`apps/angular/`) and React 19 + Vite (`apps/react/`) were added,
  sitting alongside the existing Flutter 3.x Web (`apps/flutter/`); all three share the same `/admin/v1`, `/api/v1` and `/open/v1` endpoints.
- **13 languages across the whole platform** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id):
  - Backend response messages in `resource/translations/<locale>/` — `zh_CN` 565 entries, each of the other 11 locales 544 entries, `en` 30 entries
    (scope: leaf entries across the three files; the `attributes` field labels in `validation.php` count, their group keys do not — `zh_CN` has 21 more because it translates 21 field labels. The `en` row is "English is the key", so its dictionaries are nearly empty)
  - Admin UIs: Angular source dictionary 1456 keys, React 1451 keys × 11 new locales; **lazily loaded per locale**, each locale becoming its own chunk
  - Generators: `scripts/gen-be-locales.mjs` (backend), `scripts/gen-fe-locales.mjs` (frontend, `--app angular|react`)
  - Switch entry points: a dedicated **globe icon** in the top bar + a dropdown in the personal center (identical on both ends)
- **Flutter and HarmonyOS remain Chinese/English only** and were not part of this round.
- **Impact on the tables below**: the Full column went from 163 tables / 122 controllers / 19 business modules to **227 / 159 / 23**;
  the completion matrix gained a "Multi-language (i18n)" row (module rows 44 → 45, backend API 39 → 40, business logic 33 → 34);
  note that after the duplicate "multi-tenancy" row was merged in the 2026-09-15 matrix, the module rows fell back to 44 (backend API 39, business logic 33) —
  the preceding sentence is the incremental scope as of v1.17.0 and is kept as-is.

## v1.4.0 Changes (2026-09-05)

> The edition positioning is unchanged: the repository still implements only one codebase, the Full edition; Lite/Standard are product planning values, and their branches have been archived and frozen.

- **Site-wide path versioning**: `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*`;
  the only exceptions are `GET /api/docs` (OpenAPI documentation) and the TMS webhook. RBAC permission points are authorized by the `method.path` with the version segment stripped,
  so existing role data needs zero migration (commit `3ee1430`; the `API-Version` request-header control had been removed earlier, commit `8276a1b`).
- **P0 multi-organization and cost accounting**: independent accounting per organization (Company/LedgerPeriod), a consolidated-report engine (period-end FX translation + inter-subsidiary elimination,
  snapshots preferentially landing in FinanceConsolidationReport), and inventory/production cost accounting (material issue + cost collection).
- **P1 manufacturing execution and collaboration**: operation reporting / piece-rate wages / subcontracting issue-receive / capacity load / batch-serial traceability (M1/M2/M6/M3), credit control (F7),
  the approval-process canvas (B3), print templates (B1), HR payroll (H1/H2), equipment spot-check scanning (E1), project costs (P1).
- **P2 differentiation and ecosystem**: the membership system (C1), bill ledger and bank reconciliation (F6), input VAT pool and fully-digitalized e-invoices (F5, the real tax bureau is an adaptation point),
  multi-driver channels with failure retry (B4), custom fields (B7), multi-tenant expiry billing (B5 — the tenant isolation middleware is still not registered, so it is only partially enabled),
  training and social insurance (H3/H4).
- **Feature matrix**: of 44 module rows, 33 are double ✅; 21 rows are marked v1.4.0 (including 1 partially enabled), see `docs/FUNCTIONS.md` §19.

> For detailed changes see the repository root `CHANGELOG.md`.

---

## Feature Comparison

### System Management

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| User management (CRUD + batch + import) | ✔ | ✔ | ✔ |
| Roles & permissions (RBAC three-level permission tree) | ✔ | ✔ | ✔ |
| System config (key-value) | ✔ | ✔ | ✔ |
| Operation audit (8-platform source detection) | ✔ | ✔ | ✔ |
| File upload / Excel export / PDF export | ✔ | ✔ | ✔ |
| Health check / Prometheus metrics | ✔ | ✔ | ✔ |
| JWT authentication + click captcha | ✔ | ✔ | ✔ |
| 7-layer security protection | ✔ | ✔ | ✔ |
| Internationalization (i18n) 13 locales (Angular/React; Flutter/HarmonyOS still Chinese/English) | — | — | ✔ |

### Products & Master Data

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Product master + multi-spec SKU | ✔ | ✔ | ✔ |
| Multi-unit conversion + pricing strategy | ✔ | ✔ | ✔ |
| Product categories (tree) + brands | ✔ | ✔ | ✔ |
| Multi-warehouse + multi-location | ✔ | ✔ | ✔ |
| Supplier / customer master | ✔ | ✔ | ✔ |

### Purchase Management

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Purchase requisition + approval | ✔ | ✔ | ✔ |
| Purchase orders | ✔ | ✔ | ✔ |
| Purchase receiving (auto stock-in + AP generation) | ✔ | ✔ | ✔ |
| Purchase returns | ✔ | ✔ | ✔ |
| Supplier settlement | ✔ | ✔ | ✔ |

### Sales Management

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Quotations (convertible to orders) | ✔ | ✔ | ✔ |
| Sales orders | ✔ | ✔ | ✔ |
| Sales delivery (auto stock-out + AR generation) | ✔ | ✔ | ✔ |
| Sales returns | ✔ | ✔ | ✔ |
| Customer settlement + gross margin analysis | ✔ | ✔ | ✔ |

### Inventory Management

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Live inventory (four-dimension precision) | ✔ | ✔ | ✔ |
| Stock in/out flows | ✔ | ✔ | ✔ |
| Batch tracking + serial number tracking | ✔ | ✔ | ✔ |
| Inventory transfers | ✔ | ✔ | ✔ |
| Stock counts (planned + dynamic) | ✔ | ✔ | ✔ |
| Inventory alerts (min/max warnings) | ✔ | ✔ | ✔ |
| Moving weighted average costing | ✔ | ✔ | ✔ |

### Finance Management

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| AR/AP (auto-generated + write-off) | ✔ | ✔ | ✔ |
| Receipt vouchers / payment vouchers | ✔ | ✔ | ✔ |
| Cash & bank journals | ✔ | ✔ | ✔ |
| Expense reimbursement (submit → approve → pay) | ✔ | ✔ | ✔ |
| Income statement | ✔ | ✔ | ✔ |
| Fixed asset depreciation | — | — | ✔ |
| Tax management (multi tax-type config) | — | — | ✔ |
| Multi-currency + exchange rate management | — | — | ✔ |
| Budget management (budget vs actual comparison) | — | — | ✔ |
| Cost center / profit center (tree accounting) | — | — | ✔ |

### CRM

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Customer contact management | ✔ | ✔ | ✔ |
| Follow-up records | ✔ | ✔ | ✔ |
| Campaign management | — | — | ✔ |
| Service tickets (priority + assignment + resolution flow) | — | — | ✔ |
| Customer analytics reports | — | — | ✔ |

### Platform Capabilities

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Approval workflow engine | — | — | ✔ |
| Notification system | — | — | ✔ |
| API docs (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Extended Modules

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Project management (WBS/Gantt/timesheets) | — | — | ✔ |
| Human resources (organization/attendance/payroll) | — | — | ✔ |
| Manufacturing (BOM/MRP/work orders/routings) | — | — | ✔ |
| Custom report builder | — | — | ✔ |

---

## Use Cases

| Edition | Recommended Scenario |
|------|---------|
| **Lite** | Small and medium trading companies focused on inventory + basic finance, with no need for approval flows or extended modules |
| **Standard** | Same feature scope with a leaner data model, suitable as a base for custom development |
| **Full** | Mid-to-large enterprises needing the complete inventory + finance + CRM + HR + manufacturing + project management full-stack platform |

---

## Upgrade Path

| Edition | Scale (data tables / business modules) | Description |
|------|--------------------------|------|
| Lite (简化版) | 62 tables / 6 business modules (planned) | No approval/notification/HR/manufacturing/reports |
| Standard (标准版) | 72 tables / 6 business modules (planned) | Leaner data model |
| Full (完整版) | 227 tables <!-- stats:tables=227 --> / 23 business modules <!-- stats:modules=23 --> | Comprehensive enterprise platform capabilities |

---

## Branch Strategy (from 2026-08-27)

> Applies to the `lite` / `standard` / `full` edition branches and is consistent with the CI release job (idempotent version tags).
> **Current-state addendum (measured 2026-09-22)**: the three branches have been deleted, so the remaining items in this section should be read as "archive = commits and tags";
> there is no edition branch left to check out.

- **`main` is the single development source**: all feature development, bug fixes and dependency upgrades are merged into `main`, and commits are executed uniformly by the Lead.
- **Edition branches are archived, not maintained**: `lite` / `standard` / `full` are frozen as historical archive branches — they no longer receive new commits,
  no longer sync increments from `main`, and are not force-updated or pushed (to avoid maintaining three code lines); **after the freeze period the three branches have been deleted**,
  with the archived content preserved in `main`'s history at `eea90c0`.
- **Edition differences are recorded as version tags**: releases are created idempotently by the CI release job as `vX.Y.Z` from the latest tag
  (see `scripts/bump-version.sh`); the feature differences between editions are defined by the tags and the feature comparison table above, not by maintaining branch code lines.
- **Validation**: CI on `main` is the release validation; archive branches no longer run CI separately. (From 2026-09-15 the release job depends on `docs` + `e2e`; the php job still runs but does not block releases — its red marks are CI-specific historical integration-test debt, see the comments in `.github/workflows/ci.yml`.)
