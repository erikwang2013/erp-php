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
| Data tables | 62 (planned) | 72 (planned) | 163 <!-- stats:tables=163 --> |
| Controllers | 48 (planned) | 42 (planned) | 123 <!-- stats:controllers=122 --> |
| Business modules | 6 (planned) | 6 (planned) | 19 <!-- stats:modules=19 --> |

> **Measurement note**: the repository currently implements only one codebase — the Full edition; the Lite/Standard columns are product planning values (no corresponding branches exist in the codebase) and are not subject to doc-stats validation. The Full column numbers are measured by `scripts/doc-stats.sh` (163 tables / 123 controllers / 19 business modules), consistent with the appendix measurement in `docs/FUNCTIONS.md`.

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
| 18-layer security protection | ✔ | ✔ | ✔ |
| Internationalization (i18n) Chinese/English | — | — | ✔ |

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
| API docs (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full (完整版) | 163 tables <!-- stats:tables=163 --> / 19 business modules <!-- stats:modules=19 --> | Comprehensive enterprise platform capabilities |

---

## Branch Strategy (from 2026-08)

> This document corresponds to the repository's current version-branch conventions, applicable to the three branches `lite` / `standard` / `full`.

- **`main` is the single development source**: all feature development, bug fixes, and dependency upgrades are merged into `main`.
- **Version branches only receive cherry-picks at release time**: `lite` / `standard` / `full` are no longer independent development lines receiving daily commits; at release time the version engineer cherry-picks the corresponding features from `main` (or performs a one-off full merge as needed), preserving each branch's own trimming intent (module differences in the feature comparison table above).
- **Trimming principle**: a version branch = a subset of main. When merging/porting main content, if a conflict falls in the version-trimming logic (e.g., EDITIONS.md module differences, route trimming), preserve the branch's trimming intent; for unrelated code, always take the main version.
- **Validation**: after merging, version branches must pass a full `php -l` syntax check; tests that become inapplicable due to trimming may be skipped with the reason recorded.
- **Release**: merges/ports to version branches are performed and committed as merge commits by the version engineer; commits to `main` are executed uniformly by the Lead.
