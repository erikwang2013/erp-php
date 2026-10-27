# Open ERP System (open-erp)

A full-stack ERP system built with webman v2 + Flutter.

<div align="center"><img src="docs/mascot.svg" alt="open-erp octopus mascot" width="150"></div>

<div align="center">🌐 [中文](README.md) | English</div>

## English documentation has moved

The complete English documentation (README, Installation, API Reference, Architecture, Security, and more) now lives at:

→ **[docs/i18n/en/README.md](docs/i18n/en/README.md)**

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


## Open Source vs Commercial Edition

The commercial edition is a strict superset of the open-source edition (same counting method; no controller, model, route, or table exists only in the open-source edition).

| Dimension | Open source (this repo) | Commercial |
|---|---|---|
| Positioning | Core ERP for SMBs: purchasing, sales, inventory, finance, manufacturing, CRM, workflow, HR, reporting, OMS/WMS/TMS/QMS/EAM | Adds member/retail operations, BI/DMS, print and e-invoice, multi-channel notification, multi-tenancy, and more client surfaces |
| Controllers | 138 | 147 |
| Models | 208 | 225 |
| Route declarations | 338 | 370 |
| Tables | 211 | 227 |
| Admin clients | Angular 22 + Flutter 3 Web | Angular 22 + React 19 + Flutter 3 Web |
| Mobile client | — | HarmonyOS ArkTS (73 source files) |
| Automated tests | 102 files / 933 cases / 4,178 assertions | 113 files / 1,057 cases / 5,070 assertions |
| License | Personal study/research; commercial use requires written authorization | Commercial license; contact erik@erik.xyz |

### Multi-dimensional comparison

| Dimension | Open source (this repo) | Commercial |
|---|---|---|
| Feature coverage | 138 controllers / 208 models / 211 tables / 338 routes; core ERP across purchasing, sales, inventory, finance, manufacturing, CRM, workflow, HR, reporting, OMS/WMS/TMS/QMS/EAM | 147 / 225 / 227 / 370; adds member/retail, BI, DMS, print templates, e-invoice, multi-channel notification, multi-tenancy, React/HarmonyOS clients and a static site |
| Security | security-php detectors + SecurityFilter + Redis sliding-window rate limits (60/min default; login 10, register 5, password change 5) + JWT/RBAC + operation audit + login lockout (log mode) + IP blacklist (5 hits/60s/900s ban) + encrypted fields on 8 models; core middleware and config files are byte-identical | Same core protections and config; adds TenantScope tenant context (middleware seam not registered); React/HarmonyOS clients enlarge the attack surface and need client-side hardening |
| Performance & scale | webman resident workers (cpu_count×4); Redis cache/queue (6 queue jobs); Elasticsearch/Scout; 10 compose services | Same process/cache/queue architecture; 224 Searchable models (open: 208); retail/BI/DMS add index-write and admin-query load |
| Data & search | 211 tables; 208 models indexed via Scout/ES; multi-organization consolidation | 227 tables; 224 Searchable models; adds erp_tenant and retail/BI/DMS tables; same search stack |
| Tests & quality | 102 test files / 933 cases / 4,178 assertions; one CI pipeline (PHP 8.3/8.4, Docs, E2E, Flutter, Release) | 113 / 1,057 / 5,070; same CI; extra module tests |
| Deployment & ops | 10 compose services (nginx/app/mysql/redis/ES + volumes); health/metrics, installer and upgrade scripts | Same topology; adds `site/` static showcase; multi-tenancy and extra modules need extra capacity planning |
| Clients & i18n | Angular 22 + Flutter 3 Web; 13 backend locales; Chinese/English docs only | Adds React 19 + HarmonyOS ArkTS (73 files); same 13 backend locales; 12-language docs |
| Extensibility | Fine-grained RBAC (640 permission seed rows), approval workflow (3 controllers), custom_fields, versioned `/admin/v1`, `/api/v1`, `/open/v1` | 688 permission seed rows; same extension model; adds tenant seam and reusable retail/BI/DMS/print/invoice/notification modules |
| License & support | Personal study/research only; written authorization required for commercial use; no commercial SLA | Commercial licensing, delivery and support: **erik@erik.xyz** |
| Fit | Personal study/research, prototyping, core ERP self-hosting evaluation | SMB/multi-store/multi-tenant production needing full clients, BI/DMS, e-invoice and commercial support |

Commercial-only capabilities:

- **Clients**: React 19 + Vite admin; HarmonyOS ArkTS mobile client (41 registered pages covering login, dashboard, product/inventory/purchasing/sales/OMS/WMS/TMS/manufacturing/HR/approval).
- **Membership & retail**: stored value, points, coupons, member tiers/benefits (7 tables, 9 endpoints).
- **BI**: dashboards, datasets, widgets (3 controllers + 2 tables).
- **DMS**: document, category, and version management (2 controllers + 3 tables).
- **Print templates**: placeholder rendering + dompdf PDF + QR labels.
- **E-invoice**: issuing adapter + mock channel (a real tax-authority channel requires separate integration).
- **Multi-channel notification**: SMS/email mock drivers, channel logs, and retries.
- **Multi-tenancy**: tenant CRUD, provisioning/renewal/suspension/expiry reminders, and `TenantScope` request context; the README notes the middleware seam is reserved and not registered in the current configuration.
- **Delivery**: a static product site under `site/`.

The open-source edition retains the full core ERP: product/purchasing/sales/inventory/finance, CRM, approval workflow, project management, HR, manufacturing (BOM/MRP/work reporting/capacity), custom reports, OMS/WMS/TMS, QMS, EAM, Angular + Flutter admin clients, and 13-language backend messages.

**Commercial licensing and delivery terms: erik@erik.xyz**

## 项目授权 / Project License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

- 本项目仅供个人学习、研究使用。
- 未经作者书面授权，禁止任何形式的商业使用（包括但不限于商业部署、付费服务、二次销售、SaaS 运营及以本项目为基础提供商业服务）。
- 商业授权请联系 **erik@erik.xyz**。
- 未经授权的商业使用，作者将在全球范围内依法追究法律责任，并保留终身追责的权利。
- 第三方组件仍遵循其各自原有的许可证；视觉资产适用仓库内的 ASSETS-LICENSE.md（如适用）。

**English:** This project is provided for personal study and research only. Commercial use of any kind requires prior written authorization from the author (erik@erik.xyz). Unauthorized commercial use will be pursued for legal liability worldwide, and the author reserves the right to pursue such liability for life. Third-party components remain under their original licenses; visual assets are governed by ASSETS-LICENSE.md where applicable.
