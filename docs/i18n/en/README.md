# Open ERP System (open-erp)

A full-stack ERP system built on webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="open-erp octopus mascot Little Octopus" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | English | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [中文版](../../../README.md) | [Editions](EDITIONS.md) | [Architecture](ARCHITECTURE.md) | [System Architecture](#system-architecture-diagram) | [Design](DESIGN.md) | [Security](SECURITY.md) | [API Reference](API.md) | [Functions](FUNCTIONS.md)

## Project Introduction

open-erp is an **open-source full-stack ERP system** aimed at small and medium-sized businesses, covering the complete business domains of purchase-sales-inventory (purchase/sales/inventory), financial accounting, manufacturing (BOM/MRP/operation reporting/capacity load), CRM, approval workflow, human resources, message notifications and custom reports. The backend is built on webman v2 + MySQL 8.0 (table prefix `erp_`, Snowflake globally unique primary keys); the admin side offers three implementations — Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`) and Flutter 3.x Web (`apps/flutter/`) — complemented by a native HarmonyOS client for mobile (`apps/harmonyos/`).

The system is designed around **document-driven, automatic linkage**: approving a business document automatically triggers inventory movements, AR/AP generation and cost collection; the approval workflow and message notifications run through every key document; MRP calculates material requirements from sales orders and the BOM and generates purchase/production suggestions, forming an end-to-end business closed loop from sales order intake to purchase receiving, and from production scheduling to financial closing.

## Project Description

- **Exact decimal accounting**: business values such as amounts, quantities and weights are computed with bcmath decimal arithmetic; moving weighted average cost, AR/AP write-off and every report output are string-precision, with no floating-point error
- **Enterprise-grade security baseline**: JWT tokens + RBAC method-level authorization, defense in depth (L0–L12 layered panorama + 35 attack detector categories + 7-layer middleware chain — XSS/SQL injection/CSRF/rate limiting/CSP etc.), encrypted storage of sensitive fields and encrypted API transport, and a complete operation audit trail
- **Configurable capabilities**: multi-node approval workflow (including a visual process designer canvas), document print template engine (placeholder rendering + dompdf PDF output + QR-code labels), real-time customer credit limit interception, full-chain forward and reverse batch/serial traceability
- **Traceable data**: every business transaction leaves a record; inventory batches and serial numbers span the entire inbound→issue→outbound→traceability lifecycle, and costing is traced down to the document line level
- **Deployment-friendly**: one-click startup with Docker Compose v2 (MySQL/Redis/Elasticsearch); a local `composer install` also runs directly
- **Internationalization**: 13 languages (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), covering backend messages and both the Angular and React admin UIs, with frontend dictionaries lazily loaded per language; the README additionally provides documentation in 12 languages

## Feature List

| Business Domain | Feature | Description |
|--------|------|------|
| 🔐 Authentication | Login/Register/Refresh token/Logout | Click captcha + JWT + blacklist |
| | Account lockout | Locked for 15 minutes after 5 failed attempts |
| | Concurrent session limit | Max 3 valid tokens per user |
| 📊 Dashboard | Business overview + six boards: sales/inventory/finance/OMS/WMS/TMS | 30-day sales trend/Top5 hot products/order status distribution/AR-AP aging + Redis cache 5 minutes |
| 👥 User Management | CRUD + batch delete/enable-disable | Soft delete + password re-confirmation |
| | Excel bulk import | Row-by-row validation + error report |
| 🔒 Roles & Permissions | Role CRUD + permission tree | RBAC method.path granular authorization |
| ⚙ System Config | Key-value CRUD | Group management |
| 📋 Operation Audit | Log query + source client detection | Auto-detects 8 platforms |
| 📁 File Management | Upload/Excel export/PDF export | Sensitive data auto-masked |
| 🛡 Security | 35 attack detectors + 7-layer middleware chain | XSS/SQL injection/path traversal/command injection/CSRF/rate limiting/CSP... |
| 🏥 Operations | Health check/metrics/API docs/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Product Management | Product master/SKU/multi-spec/multi-unit/category/brand/pricing strategy | Multi-level category tree + multi-unit conversion |
| | Warehouses & locations | Multi-warehouse, multi-location management |
| | Supplier/Customer master | Contacts/bank accounts/credit limits |
| 📥 Purchase Management | Requisition→Order→Receiving→Return→Settlement | Full purchase flow + approval |
| | Sourcing (RFQ → quotation → award-to-order) | Multi-supplier price comparison, quotations must cover every RFQ line, one-click conversion of the winning bid into a purchase order |
| | Supplier evaluation | Automatic rating from a 0–100 total score (A ≥ 90 / B ≥ 70 / C) + evaluation-dimension JSON + evaluator audit trail |
| 📤 Sales Management | Quotation→Order→Delivery→Return→Settlement | Quotation to order + sales gross margin |
| | Customer credit control | Limit/credit-term/freeze management + blocking of over-limit or overdue orders and deliveries |
| 🏗 Inventory Management | Live inventory/batch/serial/transfer/count/alert | Moving weighted average costing |
| 💰 Finance Management | AR/AP/receipts & payments/journal/expense reimbursement/income statement/fixed assets/tax/multi-currency/budget/cost & profit centers | Auto-generated AR/AP + write-off + comprehensive financial management |
| | Multi-organization + consolidated reports | Multi-company/ledger accounting + elimination entries (equity/cost method) |
| | Inventory/production cost accounting | Production material issue→labour/overhead collection→completion cost→cost variance carry-over |
| | Acceptance bills + bank-enterprise reconciliation | Bill ledger + bank statement import with automatic reconciliation |
| | Input invoice pool + fully-digitalized e-invoices | Input invoice management + invoicing outlet (adapter + Mock channel) |
| 🤝 CRM | Customers/contacts/follow-up records/campaigns/service tickets/analytics reports/sales funnel/shared pool/quotation/contracts | Full customer lifecycle management |
| | Membership value engine | Stored value/points/coupon membership operations |
| ✅ Approval Workflow | Workflow definition/submit approval/approve/reject/withdraw/my approvals | Multi-node approval process engine |
| | Visual process designer | Canvas configuration of nodes/branches/rejection back-edges, reusing the approval engine |
| 🔔 Notifications | Notification list/read marking/unread count/mark all read | Real-time message push and status tracking |
| | Multi-channel notifications | SMS/email channel drivers (Mock channel + logging + retry) |
| 📐 Project Management | Projects/tasks/timesheets | Project progress tracking and resource management |
| | Project cost and budget | Hours × rate → project cost collection + budget variance |
| 👤 Human Resources | Departments/employees/positions/attendance/leave/payroll | Comprehensive HR management |
| | Recruiting/performance/training/social insurance | Recruiting funnel + KPI/360 appraisal + course credits + social insurance base rules and payslips |
| 🏭 Manufacturing | BOM/production orders/routings/workstations/MRP | Material requirements planning and production execution |
| | Operation reporting/piece-rate wages/subcontract write-off | MES operation execution layer + subcontract order issue/write-off |
| | Capacity load analysis | Workstation calendar + rough-cut capacity load report |
| | Batch/serial traceability | Forward and reverse traceability chains + near-expiry alerts |
| 📈 Custom Reports | Report templates/datasets/fields/filters/execute/schedule | Visual report builder |
| 📋 Order Management (OMS) | Multi-channel orders/fulfillment orchestration/inventory reservation/allocation/cancellation/RMA returns | Full order lifecycle management |
| 🏗 Warehouse Management (WMS) | Zones/locations/ASN/receiving/putaway/waves/picking/packing/shipping | Complete warehouse operations flow |
| 🚚 Transportation Management (TMS) | Carriers/services/rates/shipments/tracking/freight invoices | Multi-carrier freight comparison + tracking |
| 🛠 Equipment Management (EAM) | Equipment register/maintenance plans/repair orders/spare parts | Full equipment lifecycle management |
| | Spot-check scanning loop | Scan-based spot checks; exceptions automatically raise repair orders |
| 🌐 Platform & Open | API path versioning | Admin `/admin/v1`, client `/api/v1`, open `/open/v1` (no version request header) |
| | Document print template engine | Placeholder rendering + dompdf PDF + QR-code labels |
| | Custom form fields | `custom_fields` JSON extension on master tables + validation |
| | Multi-tenant architecture | `erp_tenant` tenants + TenantScope request context + expiry billing (middleware seam reserved but not registered) |
| | Multi-language | 13 locales (backend messages + both the Angular/React admin UIs); dictionaries are lazily loaded into per-locale chunks; the switcher lives in the persistent top-bar globe and the personal-center dropdown |

## ERP Modules

Data flow between business modules:

- Purchase receiving → automatic stock-in (moving weighted average costing) → automatic AP generation
- Sales delivery → automatic stock-out → automatic AR generation
- Receipts/payments → write off AR/AP → update journals
- Voucher approval → auto-update general ledger (account summary) + subsidiary ledger (per-transaction records)
- Balance sheet → auto-summarized from general ledger closing balances
- Cash flow statement → auto-summarized from cash & bank journals (operating/investing/financing categories)
- Approval workflow → business documents submitted for approval → multi-node flow → approval result callback to business modules
- Notifications → triggered by approvals/alerts/system events → real-time push → user marks read
- MRP → based on sales orders + BOM → calculates material requirements → generates purchase/production suggestions
- OMS → multi-channel order import → inventory reservation (ATP) → create fulfillment → dispatch WMS picking/packing
- WMS → wave aggregation → picking tasks → picking confirmation → packing complete → triggers TMS shipment creation
- TMS → freight rate comparison → create shipment → confirm shipping (stockOut+AR) → tracking → proof of delivery
- WMS inbound → ASN advance notice → receiving → quality inspection → putaway confirmation (stockIn+AP) → inventory update
- RMA → return request → approval → return stock-in → refund

## Tech Stack

| Layer | Technology | Description |
|---|------|------|
| Backend framework | webman v2 (workerman) | Ultra-high-performance PHP resident-process framework |
| PHP version | 8.3+ | |
| Database | MySQL 8.0+ | Table prefix `erp_`, BIGINT non-auto-increment primary keys |
| Search engine | Elasticsearch | Index auto-synced on write/delete via `webman-scout` (optional component, see the "Full-Text Search Engine" section) |
| Admin frontend A | Angular 22 | Config-driven resource pages, `ResourcePage` rendering engine (`apps/angular/`) |
| Admin frontend B | React 19 + Vite | Config-driven like Angular, plus style tokens (`apps/react/`) |
| Admin frontend C | Flutter 3.x | Web is a PC admin console style (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | HarmonyOS native client (`apps/harmonyos/`), supports phone/tablet/2in1 |

## Core Dependencies

| Package | Purpose |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake algorithm for globally unique BIGINT primary keys |
| `erikwang2013/hashids` | API-layer ID encryption to hide real database IDs |
| `erikwang2013/jwt-webman` | JWT authentication token issuance and validation |
| `erikwang2013/encryption` | Sensitive data encryption/decryption at the transport layer |
| `erikwang2013/encryptable` | Automatic encryption/decryption of sensitive fields at the storage layer |
| `erikwang2013/webman-scout` | Elasticsearch data sync and full-text search |
| `erikwang2013/season` | Country flag data |
| `erikwang2013/poster-php` | Click captcha generation/validation + poster generation |
| `erikwang2013/security-php` | Security tool checks |
| `phpoffice/phpspreadsheet` | Excel export |
| `barryvdh/laravel-dompdf` | PDF export (based on Dompdf) |
| `erikwang2013/apidoc-php` | Automatic API documentation | Annotation-based interface docs, admin/client groups |

## Internationalization

The system supports **13 locales**: `zh` (default), `en`, `ja`, `ko`, `de`, `fr`, `es`, `pt`, `ru`, `ar`, `hi`, `bn`, `id`.

| Layer | Dictionary location | Size |
|---|---------|------|
| Backend messages | `resource/translations/{locale}/` | 13 locale directories: `zh_CN` 565 entries, the other 11 locales 544 entries each, `en` 30 entries (counted as the leaf entries of the three files `common`/`modules`/`validation`; the `attributes` field labels in `validation.php` are counted, its group keys are not) |
| Angular admin | `apps/angular/src/app/core/zh-*.ts` (source dictionary `zh-en/`, merged from 4 slices) | source dictionary 1456 keys × 11 locales (each locale has a 1:1 key count with the source dictionary) |
| React admin | `apps/react/src/lib/i18n/zh*.ts` | source dictionary 1451 keys × 11 locales |

- **"English as key" on the backend**: the backend message keys are English text themselves, and `en` only maintains a small mapping such as framework rule names — no full dictionary is required
- **Lazy loading per locale**: the 12 frontend dictionaries are each bundled into a separate chunk and fetched on demand when switching languages, so first paint is unaffected
- **Switching entry points**: a dedicated globe icon in the top bar plus a dropdown in the personal center (identical on the Angular and React sides)
- **Interface layer**: the `Accept-Language` request header is negotiated automatically (zh-CN → Chinese, en → English, other locales matched against the list), defaulting to Chinese
- **Generators**: `scripts/gen-be-locales.mjs` (backend) and `scripts/gen-fe-locales.mjs --app angular|react` (frontend), both resumable from the last checkpoint

## Project Structure

```
open-erp/
├── app/
│   ├── admin/controller/       # System management controllers (16)
│   ├── api/v1/controller/      # Client API (version in the path /api/v1, no version header)
│   ├── controller/             # Business module controllers (139, 23 domains)
│   │   ├── product/            # Products/categories/brands/warehouses/locations/suppliers/customers (8)
│   │   ├── purchase/           # Purchase requisitions/orders/receiving/returns/settlement/inquiries/quotations/supplier evaluation (8)
│   │   ├── sales/              # Sales quotations/orders/deliveries/returns/settlement (5)
│   │   ├── inventory/          # Stock/stock movements/transfers/counts/alerts (6)
│   │   ├── finance/            # AR-AP/vouchers/receipts & payments/journals/general ledger/subsidiary ledger/reports/assets/tax/multi-currency/budget/cost & profit centers/notes/reconciliation/invoices (28)
│   │   ├── crm/                # Opportunities/follow-ups/funnel/contacts/public pool/contracts/quotations/campaigns/tickets/analytics (10)
│   │   ├── workflow/           # Workflow definitions/approvals/process designer (3)
│   │   ├── notification/       # In-app notifications/channel delivery (2)
│   │   ├── project/            # Projects/tasks/timesheets/costs (4)
│   │   ├── hr/                 # Departments/employees/positions/attendance/leave/payroll/recruiting/performance/social insurance/training (9)
│   │   ├── manufacturing/      # BOM/work orders/routings/workstations/MRP/reporting/outsourcing/costing/capacity (13)
│   │   ├── report/             # Report templates/datasets/execution/scheduled dispatch (2)
│   │   ├── print/              # Print template engine (1)
│   │   ├── retail/             # Member stored value/points/coupons (2)
│   │   ├── platform/           # Multi-tenancy/custom fields (2)
│   │   ├── quality/            # Quality inspection (5)
│   │   ├── eam/                # Equipment/maintenance/repairs/spare parts/spot checks (5)
│   │   ├── bi/                 # Business intelligence (3)
│   │   ├── dms/                # Document management (2)
│   │   ├── oms/                # OMS orders/fulfillment/RMA/channels (4)
│   │   ├── wms/                # Zones/locations/ASN/receiving/putaway/waves/picking/packing (8)
│   │   ├── tms/                # Carriers/services/rates/shipments/tracking/freight invoices (6)
│   │   └── open/               # Open platform API (1)
│   ├── service/                # Business logic layer (64)
│   │   ├── inventory/          # Stock in/out + moving weighted average costing + inventory reservation/ATP
│   │   ├── finance/            # Automatic AR-AP generation + write-off
│   │   ├── notification/       # Notification delivery service
│   │   ├── oms/                # Order orchestration/inventory allocation/RMA lifecycle
│   │   ├── wms/                # Inbound flow (ASN→receiving→putaway) / outbound flow (wave→pick→pack)
│   │   └── tms/                # Shipment management/freight comparison/logistics tracking
│   ├── model/                  # 224 Eloquent models (shared across modules)
│   ├── middleware/             # 11 middleware (ApiVersion removed, version lives in the path)
│   ├── common/                 # Hashids/Snowflake/Encryption services
│   └── queue/                  # Queue jobs
├── apps/
│   ├── angular/                # Angular 22 admin (config-driven resource pages, ng serve :4200)
│   ├── react/                  # React 19 + Vite admin (Vite :5173)
│   ├── flutter/                # Flutter cross-platform (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # HarmonyOS native client
├── config/                     # Config files (Chinese comments)
│   ├── plugin/erikwang2013/apidoc/ # API documentation config
├── database/
│   ├── install.sql              # Complete install SQL (227 tables + seed data)
│   ├── e2e-seed.sql             # Minimal E2E/CI seed
│   └── backup/                 # Backup/restore scripts
├── docs/                       # Architecture, design, security, API docs
├── tests/                      # PHPUnit tests (<!-- stats:test_files=111 --> test files, <!-- stats:tests=1008 --> test methods, <!-- stats:assertions=4768 --> assertions)
├── resource/
│   └── translations/           # Backend message dictionaries for 13 locales (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # Chinese translations (565 entries)
│       ├── en/                 # English is the key, only 30 entries such as framework rule names
│       └── ja|ko|de|.../       # the other 11 locales, 544 entries each (generator scripts/gen-be-locales.mjs)
├── public/                     # Public entry point
├── runtime/                    # Runtime files
└── vendor/                     # Composer dependencies
```

## System Architecture Diagram

> Click the image to view the original SVG. The diagrams use English naming and fully illustrate the architecture design at each layer.

### System Topology Architecture

![System Architecture](./diagrams/system-architecture-cn.svg)

**Five-layer architecture**: Client layer → Gateway edge layer (Nginx reverse proxy) → Application layer (webman v2 + middleware chain + authentication & authorization + business logic + common services) → Data storage layer (MySQL + Redis + Elasticsearch) → Operations layer (CI/CD + Docker + Prometheus)

### Business Data Flow

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**Seven business domains working together**: Purchase → Inventory → Sales → Finance form the core supply chain loop; customer relationship management drives sales; manufacturing MRP drives purchase and production plans based on sales orders + BOM; approval workflow, message notifications, project management, and human resources serve as supporting modules throughout the whole process.

### Functional Module Overview

![Functional Modules](./diagrams/functional-modules-cn.svg)

**23 business domains, 227 data tables, 159 controllers**: Covering authentication & security, dashboard, system management, security protection, operations monitoring, product management, purchase, sales, inventory, finance (14 sub-modules), CRM (10 sub-modules), approval workflow, message notifications, project management, human resources, manufacturing (MRP), custom reports, order management (OMS), warehouse management (WMS), transportation management (TMS), quality management (QMS), equipment management (EAM), document management (DMS), BI dashboards.

### Request Lifecycle

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Full request path from client to database**: Client (Angular/React/Flutter/HarmonyOS) → Nginx SSL termination → CORS handling → security filter → rate limiting → [Admin: JWT authentication → RBAC permission → operation log] → Controller → Service layer → Model layer → cache/database/search engine → JSON response. The diagram includes both cache hit and cache miss paths. (API versions are merged into the URL path, so there is no separate version-validation step; the locale is parsed by `app/common/I18n.php` from `Accept-Language`.)

### Security Defense-in-Depth Architecture

![Security Architecture](./diagrams/security-architecture-cn.svg)

**Defense-in-depth panorama (L0–L12)**: L0 physical network → L1 transport security → L2 HTTP security headers → L3 request validation → L4 input sanitization → L5 CSRF protection → L6 rate limiting → L7 authentication (JWT+Captcha+blacklist+session control) → L8 RBAC authorization → L9 data protection (transport encryption + storage encryption + ID obfuscation + data masking) → L10 audit monitoring → L11 compliance disclosure → L12 observability (X-Trace-Id distributed tracing + business metrics + audit enhancement). Diagram source: `docs/diagrams/security-architecture-cn.dot` (L0–L12); the 7-layer executable middleware chain is documented in `docs/SECURITY.md`; the 35 attack detectors live in `config/plugin/erikwang2013/security-php/app.php`.

---

## Environment Requirements

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (frontend development only)
- Node >= 22.22.3 (Angular/React admin development only; the `engines` lower bound of Angular CLI 22)
- Elasticsearch >= 7.x or OpenSearch >= 2.x (optional, required for index sync; not installing it does not affect business reads/writes)
- DevEco Studio (optional, HarmonyOS client builds only; the command-line equivalent is `hvigorw assembleHap`)

## Default Local Domain

The project uses the local domain **`http://erp.test`** by default (the Flutter client's default API address and the convention for the backend web entry point; the HarmonyOS client defaults to the emulator host `http://10.0.2.2:8788`).

- **Local access**: add a line `127.0.0.1 erp.test` to your hosts file, and point the web server/reverse proxy at the backend listening port (default `8788`, see `APP_HTTP_PORT` in `.env`; changeable in the install wizard or in `.env`; WebSocket defaults to `8282`, corresponding to `APP_WS_PORT`).
- **Changing the deployment domain**:
  - Flutter build injection: `flutter build web --dart-define=API_BASE_URL=https://your-domain`
  - HarmonyOS: edit `BASE_URL` in `apps/harmonyos/entry/src/main/ets/utils/Config.ets` (a read-only constant, default `http://10.0.2.2:8788`)
  - For emulator debugging you can temporarily switch back to `http://10.0.2.2:8788` (to reach the host machine)
- All API versions are already placed in the path (`/admin/v1`, `/api/v1`, `/open/v1`), so the client only needs to configure the root address.

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment Variables

Copy and modify the environment variables (optional; if not configured, defaults from `config/*.php` are used):

```bash
cp .env.example .env
```

Key configuration items:

| Environment Variable | Description | Default |
|---------|------|--------|
| `JWT_SECRET_KEY` | JWT signing secret (`env_required`: missing / empty / weak placeholder values are rejected at startup) | 48-character random value preset in `.env.example` |
| `HASHIDS_SALT` | Hashids salt (`env_required`) | 48-character random value preset in `.env.example` |
| `ENCRYPTION_KEY` | Master key for API transport-layer and storage-layer encryption (`env_crypto_key`: AES-256 requires 32 bytes, a length mismatch is rejected at startup) | 32-character random value preset in `.env.example` |
| `APIDOC_PASSWORD` / `APIDOC_SECRET_KEY` | Documentation site access password and token signing key. Left empty, or still at the `CHANGE_ME_*` placeholder, they are **always treated as unconfigured** (the placeholder value lives in this public repository, so copying it to production means publishing the password) → the doc site refuses access, application startup is unaffected | `.env.example` ships `CHANGE_ME_*` placeholders (must be replaced by the generator script below) |
| `SNOWFLAKE_DATACENTER_ID` | Datacenter ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker node ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES address | `http://localhost:9200` |
| `APP_HTTP_PORT` / `APP_WS_PORT` | Backend HTTP / WebSocket listening ports (reverse proxies such as Nginx point at them) | `8788` / `8282` |
| `ANGULAR_DEV_PORT` / `REACT_DEV_PORT` | Frontend dev-server ports (`npm run dev`, development only) | `4200` / `5173` |
| `NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT` | Ports published to the host by docker-compose (container-internal ports are fixed) | `80` / `443` / `3306` / `9200` |

**Always replace every key with a random string in production** (`JWT_SECRET_KEY` / `ENCRYPTION_KEY` / `HASHIDS_SALT` and the like: when missing, empty, or still a weak placeholder such as `change-me`/`xxx`, startup is rejected by `env_required` / `env_crypto_key` — there is no silent fallback):

```bash
# Generate random keys and write them into .env (idempotent; already-configured values are not overwritten)
bash scripts/gen-env-keys.sh .env
```

### 3. Initialize the Database

**Option 1: Web installation wizard (recommended)**

After starting the service, visit `http://localhost:8788/install` and follow the 4-step guided install: environment check → database config → admin account → one-click install. The database-configuration step offers a **load demo data** checkbox (products/specs/SKUs/customers/suppliers, ID range 41…, removable by range); off by default — leave it unchecked in production.

**Option 2: Command-line import**

```bash
mysql -u root -p your_database_name < database/install.sql
```

`install.sql` is a single-file complete baseline and contains all 227 table structures and seed data.

**Option 3: Docker environment**

Nothing needs to be imported manually: `install.sql` is mounted into the MySQL container at `/docker-entrypoint-initdb.d` and initializes automatically on first start.

### 4. Start the Service

```bash
php start.php start
```

Listens on `http://0.0.0.0:8788` by default.

### 5. Start the Frontend (Optional)

**Flutter admin console (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC admin console style)
```

**HarmonyOS client (Mobile):**

Open the `apps/harmonyos/` directory with DevEco Studio, and run on a real device or emulator.

### 6. One-Click Deployment with Docker Compose (recommended for production)

The project provides a complete Docker orchestration with 5 services: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Configure Docker environment variables
cp .env.docker .env

# 2. Replace placeholder keys with random values (JWT_SECRET_KEY/ENCRYPTION_KEY/HASHIDS_SALT etc., idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Start all services (requires Docker Compose v2: `docker compose`; v1 is deprecated and incompatible with the http+docker protocol)
docker compose up -d

# 4. Check service status (MySQL imports database/install.sql automatically on first start, no manual initialization needed)
docker compose ps --format "table {{.Name}}\t{{.Status}}"

# 5. Access (Nginx publishes ${NGINX_PORT:-80}; webman 8788 is container-internal only, reverse-proxied by Nginx)
# http://localhost

# Reset hint: if you previously started with an old .env (the data volume has the old password/schema baked in), clear the volume first:
# docker compose down -v   (⚠️ deletes MySQL/Redis/ES data; use only for first-time troubleshooting)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, based on `php:8.3-cli-alpine`
- `docker-compose.yml`: 5-service orchestration, network isolation, data volume persistence
- `.env.docker`: environment variables for the Docker environment

## Usage

### 1. Login

On first use, visit the web installer `http://localhost:8788/install` to complete the installation and create an admin account. If already installed, open the console, enter your credentials and pass the click captcha to log in.

### 2. Feature navigation

After login, enter each business module from the sidebar: dashboard, products, purchasing, sales, inventory, finance, CRM, approval workflows, notifications, projects, HR, manufacturing, custom reports, OMS/WMS/TMS, BI dashboards and system management (users/roles/config/logs). The sidebar is fixed on desktop and collapses into a drawer on mobile.

### 3. Permissions and security

- Features and APIs are controlled by RBAC; menus and endpoints without permission are inaccessible (403)
- Sensitive operations such as deleting a user/role require re-entering the current password in the request body
- After logout the token is immediately blacklisted and cannot be reused

### 4. Full-Text Search Engine (optional)

Index sync is implemented via `erikwang2013/webman-scout` (once a model uses the `Searchable` trait, saving it syncs the index automatically). Both **Elasticsearch** and **OpenSearch** are supported — pick one:

**① Install the matching client (the Composer package and the driver must match; the wrong one reports "Please install the ... client")**

| Engine | Composer client |
|---|---|
| Elasticsearch | `composer require elasticsearch/elasticsearch:^9.5` |
| OpenSearch | `composer require opensearch-project/opensearch-php:^2.0` |

**② Configure `.env` to pick the driver**

```ini
# elasticsearch | opensearch（与上面安装的客户端一致）
SCOUT_DRIVER=opensearch
# 索引名称前缀 / 分片 / 副本 / 批量块大小 / 软删除（两种引擎通用）
SCOUT_PREFIX=erp_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true
```

**③ Connection configuration (the two engines read from different places)**

- **Elasticsearch**: `SCOUT_HOSTS` in `.env` (multiple nodes comma-separated, e.g. `http://localhost:9200`), a direct connection with no authentication;
- **OpenSearch**: the official image enables the security plugin by default (self-signed TLS + account authentication), so it goes through the `opensearch` section of `config/scout.php` and does not read `SCOUT_HOSTS`:

  ```ini
  # .env
  SCOUT_OPENSEARCH_HOST=https://localhost:9200
  SCOUT_OPENSEARCH_USERNAME=admin
  SCOUT_OPENSEARCH_PASSWORD=你的密码
  ```

  The `opensearch` section of `config/scout.php` defaults to `ssl_verification=false` (for local self-signed certificates); in production set it to `true` and configure a certificate, and never use a weak password.

> This project ships Elasticsearch inside Docker Compose (the `open-admin-es` service): for a Docker deployment choose the **elasticsearch driver + ES client**; for an external/standalone OpenSearch container choose the **opensearch driver + opensearch-php**.
>
> **Index scope**: all 224 models under `app/model/` use `Searchable`; writes and soft deletes sync the index through `ModelObserver`. AdminUser, Customer, Product and Supplier (4 in total) define a custom `toSearchableArray()` whitelist; every other model indexes the whole row.
>
> **Engine unavailability does not affect business writes** (verified: pointing the driver at an unreachable port still lets `save()` succeed, at the cost of one connection timeout) — the search engine is an optional component, and the full business scope runs without it.
>
> **Scope note**: this project currently wires up **index sync only** (synced on write/soft delete); no search API or search UI is provided. When you need search, call the Scout query API yourself (admin list-page filters use backend `where` queries and do not go through the search engine).

### 5. Multi-language

Switched automatically via the `Accept-Language` request header; 13 locales are supported (`zh` is the default, plus `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`); the Angular/React admin consoles additionally offer a dedicated globe icon in the top bar and a dropdown in the personal center. See [Internationalization](#internationalization).

## Database Conventions

- **Table prefix**: `erp_`
- **Primary key**: all tables use `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT is forbidden**
- **ID generation**: primary key IDs are generated at the application layer by `SnowflakeService::generate()`, globally unique across distributed nodes
- **Mandatory fields**: every table must include `id`, `created_at`, `updated_at`
- **Soft delete**: tables requiring soft delete add `deleted_at DATETIME DEFAULT NULL`
- **Sensitive fields**: phone numbers, emails, ID card numbers, etc. are automatically encrypted/decrypted via the `encryptable` plugin; database columns use `VARCHAR(500)` to store ciphertext

## API Conventions

### API Documentation

The project uses `erikwang2013/apidoc-php`, and **the documentation is generated automatically from controller annotations** — there is no separate documentation to maintain:

```bash
php start.php start          # start the backend
# then open it in a browser
http://localhost:8788/apidoc
```

- **Access path**: `/apidoc` (the plugin route prefix, see `config/plugin/erikwang2013/apidoc/route.php`);
  this path is exempted in the rate-limit middleware, so browsing annotations in bulk will not be throttled
- **Coverage**: admin endpoints (Admin) are grouped by module, with complete request parameters and response structures; client endpoints (Service API) cover authentication/captcha/products
- **How to document a new endpoint**: just annotate the controller method — saving takes effect on the next `/apidoc` refresh

  ```php
  #[\erikwang2013\apidoc\annotation\Title("商品列表")]
  #[\erikwang2013\apidoc\annotation\Desc("分页查询商品")]
  #[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
  #[\erikwang2013\apidoc\annotation\Method("GET")]
  #[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
  #[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
  public function index(Request $request): Response { /* ... */ }
  ```

- To restrict access in production, see `docs/nginx-security.conf`

### Unified Response Format

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Business Error Codes

| Error Code | Meaning | Description |
|-------|------|------|
| `0` | Success | |
| `400` | Invalid request parameters | |
| `401` | Not logged in (invalid or expired token) | |
| `403` | No permission / security block | RBAC authorization failure / SecurityFilter attack detection |
| `404` | Resource not found | |
| `422` | Parameter validation failed | |
| `413` | Request body too large | SecurityFilter triggered, exceeds 10MB |
| `405` | Method not allowed | SecurityFilter triggered, only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed |
| `415` | Unsupported media type | SecurityFilter triggered, Content-Type is not JSON |
| `429` | Too many requests | RateLimit triggered / account lockout (15 min after 5 failed logins) |
| `500` | Internal server error | |

### Internationalization

The `Accept-Language` request header switches language automatically; 13 locales are supported (`zh` is the default, plus `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`).

### ID Handling

- **IDs in requests/responses**: encrypted to strings with hashids, real database IDs are never exposed
- **Endpoint paths**: `GET /admin/v1/user/{hashid}` — `{id}` in the path is a hashid string
- **Database storage**: BIGINT raw values, generated by snowflake
- **Frontend convention**: every `*_id` (including detail-line `items[].*_id`) is passed straight back as an **opaque string**; `Number()`/`parseInt()` conversion and truthiness checks are forbidden — a hashid may be an all-digit string, indistinguishable by value alone from a bare ID or the `0` sentinel (the contract lives in `tests/FieldContractRegressionTest.php`)

### API Versioning

API versions are placed in the URL path (e.g. `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`); **clients need no version request header at all**:

- Versioned public endpoints are bound directly to the controller class of the matching version (`app/api/v1/controller/`)
- To add a version, register a new `/api/vN` route group and place the controllers under `app/api/vN/`
- The historic `v()` dynamic resolution and the `ApiVersion` request-header middleware have both been removed

### Rate Limiting

Based on the Redis sliding window algorithm, default 60 requests/minute/IP/route. Sensitive endpoints are stricter:
- Login: 10 requests/minute
- Register: 5 requests/minute (disabled by default; enable with `REGISTRATION_ENABLED=1`)

Response headers include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Exceeding the limit returns 429 with `Retry-After`.

### Middleware Architecture

Global middleware (`config/middleware.php`) applies to all requests and executes in order:

```
Cors (CORS preflight + response headers)
  → SecurityFilter (HTTP method restriction / request body size / Content-Type validation / XSS / SQL injection / path traversal / command injection / CSRF blocking)
  → RateLimit (Redis sliding-window rate limiting + account lockout: 5 failed logins → 15-minute lockout)
  → TracingId (tracing ID)
```

Route-group middleware: `/admin/v1` carries `AdminAuth (JWT authentication + blacklist) → AdminPermission (RBAC authorization) → OperationLog (automatic logging for POST/PUT/DELETE, including caller-side detection)`; `/open/v1` carries `OpenApiAuth`; TMS tracking callbacks carry `TrackingSignature`. The locale is parsed from `Accept-Language` by `app/common/I18n.php` — it is not middleware.

`/health`, `/api/docs`, and `/install` are public endpoints that only pass through `Cors → SecurityFilter → RateLimit → TracingId`.

Security enhancements:
- **Account lockout**: after 5 consecutive failed logins, the account is locked for 15 minutes; login returns 429 during the lockout
- **Concurrent session limit**: max 3 valid tokens per user; the oldest token is blacklisted when exceeded
- **security.txt**: `GET /.well-known/security.txt` provides RFC 9116 standard security contact information
- **Nginx security config**: see `nginx-security.conf` for a complete reverse-proxy security hardening example

### Authentication

Login and registration must first pass the **click captcha**:

1. The client requests `POST /api/v1/captcha/generate` to get the captcha image (base64 PNG) and the list of target texts
2. The user clicks the corresponding text positions in the image in order, collecting click coordinates `[{x, y}, ...]`
3. Submit `captcha_key` and `clicks` together at login; the server validates the captcha first, then the credentials

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Subsequent admin endpoints require JWT authentication:

```http
Authorization: Bearer <token>
```

After successful login, an access_token is returned, valid for 2 hours; a refresh_token is also returned, valid for 14 days.

On logout the token is added to the Redis blacklist and cannot be reused while valid. POST /admin/v1/profile/logout

### Sensitive-Operation Re-confirmation

Sensitive operations such as deleting users, roles, and permissions require passing the current logged-in user's `password` in the request body for identity re-confirmation:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API List

The complete endpoint list (public endpoints / admin endpoints / business endpoints / client endpoints) has been moved to a standalone document:

→ [API Reference](API.md)

## Frontend Notes

### Angular Admin (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (port: see ANGULAR_DEV_PORT in .env)
npm run build      # tsc --noEmit + ng build, output in dist/angular
npm run typecheck  # type checking only
```

- **Node version requirement**: Angular CLI 22's `engines` require **Node ≥ 22.22.3** (older versions make `ng build` refuse to start outright).
  When the local Node is too old, pin it temporarily with npx (the most common build posture in this repository, used everywhere outside CI):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  On environments without `npx` (such as this repository's offline verification machine), fall back to the CLI's bundled tsc for type checking:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Dev proxy**: `proxy.conf.js` already proxies `/admin` `/api` `/open` `/health` `/metrics` `/install`
  to `APP_HTTP_PORT` from `.env` (default 8788), so **no** backend address needs to be configured when running `ng serve`
- **Architecture**: config-driven — `src/app/config/domains/*.ts` declares menus and resource pages, and **a single `ResourcePage`
  renders every business page** (adding a resource page ≈ adding one config object, with no component to write)
- **Internationalization**: 13 languages, dictionaries lazily loaded per language (each becomes its own chunk); switch via the globe icon in the top bar
- **Self-checks** (none require a browser; run them directly with `node`): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### React Admin (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (port: see REACT_DEV_PORT in .env)
npm run build      # tsc --noEmit + vite build, output in dist/
```

- Like Angular, it is **config-driven**: `src/config/domains/*.ts` declares menus and resource pages,
  the rendering engine lives in `src/components/ResourcePage.tsx`, and the style tokens are in `src/styles/tokens.css`
  (matching the values of `styles/theme.less` on the Angular side)
- The language switcher lives on the **personal center** page (the Angular side additionally has a globe icon in the top bar)

### Flutter Admin Console (PC Style, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC admin console style); iOS/Android/macOS/Windows/Linux are also supported
flutter analyze          # static analysis (same as CI)
```

- **Layout**: sidebar (collapsible 64px/240px) + top bar + content area, responsive with three breakpoints (mobile/tablet/desktop)
- **Coverage**: 22 top-level menu items (21 groups + the standalone dashboard), 102 routable pages, 119 page files (menus are declared in `lib/app/config/menu_config.dart`, pages live in `lib/app/pages/`) — Dashboard, System Management, Product Management, Business Partners, Purchase Management, Sales Management, Inventory Management, Finance Management, CRM, Order Management, Warehouse Management, Transportation Management, Manufacturing, Quality Management, Human Resources, Project Management, Approval Workflow, Notification Center, Custom Reports, BI Dashboards, Equipment Management, Document Management
- **State management**: GetX (`ApiService` singleton + `AuthService` token persistence)
- **Dashboard**: stat cards, sales trend line, top products, order status distribution, AR/AP aging, inventory overview (fl_chart)
- **Export**: Excel/PDF export (`ExportService`); PDF contains non-removable copyright information
- **Batch operations**: multi-select batch delete, batch enable/disable
- **Theme**: Material 3 light/dark dual themes
- **Internationalization**: Chinese/English bilingual (`lib/l10n/app_zh.arb` is the template, generated with `flutter gen-l10n`)

### HarmonyOS Mobile (`apps/harmonyos/`)

- **Build**: open `apps/harmonyos/` with DevEco Studio; the command-line equivalent is
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (requires the HarmonyOS SDK + command-line-tools; the artifact is `entry/build/default/outputs/default/*.hap`)
- **Pages**: 41 pages are registered in `entry/src/main/resources/base/profile/main_pages.json` and every one of them is reachable from the UI — login, dashboard (KPI cards + business grid), user list/detail, role permissions, profile, plus subsystem pages for products/inventory/purchase/sales/OMS/WMS/TMS/manufacturing/HR/approval. The dashboard business grid provides 32 direct entry points; subsystem detail pages are opened from list-row actions.
- **Authentication**: JWT Bearer + 401 automatic seamless token refresh; on refresh failure, auto-redirect to the login page
- **Storage**: tokens managed via AppStorage
- **Internationalization**: Chinese/English bilingual (`resources/base/element/string.json` and `resources/en_US/element/string.json`)
- **Networking**: `BASE_URL` is defined in `entry/src/main/ets/utils/Config.ets` (a read-only constant, default `http://10.0.2.2:8788`, i.e. the emulator reaching the host machine; change it here for real devices/production)

## Development Conventions

- Global functions/classes are referenced without a leading `\`, always imported via `use`
- All PHP files must contain the copyright notice at the top
- All config files must contain Chinese comments explaining each setting
- Database primary keys must be generated by snowflake at the application layer; auto-increment is forbidden
- All IDs in API-layer parameters and responses must be encrypted/decrypted via hashids
- The AdminPermission middleware caches user permissions in Redis (TTL=60s), eliminating the N+1 query bottleneck

## Deployment

### Docker Compose (recommended)

The project root provides `docker-compose.yml`, orchestrating 5 services:

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | built from local `Dockerfile` | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

The PHP image is built from the `Dockerfile`, base image `php:8.3-cli-alpine`, with OPcache enabled.

```bash
cp .env.docker .env
# Replace placeholder keys with random values (idempotent; the C1 strict check rejects startup on CHANGE_ME_ placeholders)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

GitHub Actions continuous integration pipeline: `.github/workflows/ci.yml`, with five jobs:

| Job | Contents |
|------|------|
| `php` (matrix PHP 8.3 / 8.4, with MySQL 8 + Redis 7 services) | composer validation and security audit → `php -l` → **PHPStan** (level 5 + baseline) → **PHP CS Fixer** (dry-run) → import the full `install.sql` → **PHPUnit** (including integration cases) → pcov coverage collection → coverage gates (overall ≥ 4%, `app/service` ≥ 10%, tightened over time) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, to be tightened once the environment is stable) |
| `docs` | `bash scripts/doc-stats.sh --check`: verifies that the `stats` annotations in the README and docs match the counts measured from the source (controllers/services/models/tables/tests, etc.); drift turns the job red |
| `e2e` | starts a real webman service → health check → HTTP core-path smoke tests + admin API coverage |
| `release` | after a push to `main` with the jobs above passing, tags patch+1 and publishes a Release (see below) |

> Frontend static-check coverage: CI currently only runs Flutter; Angular/React (`tsc --noEmit`) and HarmonyOS (`hvigorw assembleHap`) must be run locally or added as later jobs.

### Release Process (Version Increment)

Once a push to `main` passes the php / docs / e2e checks, the `release` job in `ci.yml` automatically creates and pushes a new version tag at the latest tag's **patch+1** (`v1.1.4` → `v1.1.5`), then creates a GitHub Release of the same name (`--generate-notes` generates the change notes automatically).

- **Trigger**: pushes to `main` only (PRs do not trigger it; a tag push does not match the branch filter and so does not recursively trigger this workflow)
- **Idempotent**: if a tag or release of the same name already exists remotely (concurrent CI runs / it was already tagged manually), it skips silently instead of failing
- **Local dry run**: `bash scripts/bump-version.sh --check` prints the next version number (read-only, writes nothing remote)

### Database Backup

`database/backup/` directory:

- `backup.sh` — mysqldump + gzip backup, auto-cleans backups older than 30 days
- `restore.sh` — interactive restore, lists available backups to choose from

### Nginx Security Configuration

For production deployments, configure reverse-proxy security hardening by following `nginx-security.conf`.

## Open Source Is Hard Work — Your Support Is Welcome

| WeChat Pay | Alipay |
|:---:|:---:|
| ![WeChat Pay](images/weixinpay.png "WeChat Pay") | ![Alipay](images/alipay.png "Alipay") |

### Global Bank Transfer (银行汇款 / Global Bank Transfer)

**Recipient Information**

- Recipient Name: WANG KEXUN
- Account Number: 881015918251

**Receiving Bank**

- ZA Bank SWIFT Code: AABLHKHHXXX
- Bank Name: ZA Bank Limited
- Bank Code: 387
- Bank Address: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Correspondent Bank for Cross-Border Remittance (if required)**

> This is the correspondent (intermediary) bank information, not the receiving bank. Check with your remitting bank whether it must be provided.

- For HKD, CNY, and USD remittances: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, bank code 006, branch Hong Kong Branch, branch code 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- For other currencies: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Crypto Donation

If this project helps you, scan the QR code to donate, thank you!

| <img src="../../../docs/coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../../docs/coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../../docs/coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../../docs/coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../../docs/coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../../docs/coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../../docs/coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../../docs/coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../../docs/coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../../docs/coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
