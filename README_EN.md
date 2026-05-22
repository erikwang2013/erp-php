# Open ERP System (open-erp)

A full-stack ERP system built with webman v2 + Flutter.

> [中文文档](README.md) | [Architecture Diagrams](docs/ARCHITECTURE.md) | [Design Doc](docs/DESIGN.md) | [Security](docs/SECURITY.md) | [API Reference](docs/API.md)

## Features

| Domain | Feature | Notes |
|--------|---------|-------|
| 🔐 Auth | Login/Register/Refresh/Logout | Click captcha + JWT + blacklist |
| | Account lockout | 5 failures → 15 min lock |
| | Concurrent session limit | Max 3 active tokens per user |
| 📊 Dashboard | Overview/Sales/Inventory/Finance dashboards | Redis cached 5 min |
| 👥 Users | CRUD + batch delete/toggle status | Soft delete + password confirmation |
| | Excel batch import | Row-level validation + error report |
| 🔒 Roles & Perms | Role CRUD + permission tree | RBAC method.path granularity |
| ⚙ Config | Key-value CRUD | Grouped management |
| 📋 Audit | Log query + source detection | 8 platforms auto-detected |
| 📁 Files | Upload/Excel export/PDF export | Sensitive data auto-masked |
| 🛡 Security | 18-layer defense-in-depth | XSS/SQLi/path traversal/cmd injection/CSRF/rate limit/CSP... |
| 🏥 Ops | Health check/metrics/API docs/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Products | Product catalog/SKU/variants/multi-unit/categories/brands/pricing | Multi-level category tree + unit conversion |
| | Warehouse/Location | Multi-warehouse multi-location management |
| | Supplier/Customer profiles | Contacts/bank accounts/credit limits |
| 📥 Purchasing | Requisition→Order→Receive→Return→Settlement | Full procurement flow + approval |
| 📤 Sales | Quotation→Order→Delivery→Return→Settlement | Quote-to-order + gross margin |
| 🏗 Inventory | Real-time stock/batch/serial/transfer/stocktaking/alerts | Moving-weighted-average costing |
| 💰 Finance | AR/AP/Receipts & Payments/Journal/Expense/Profit/Fixed Assets/Tax/Multi-Currency/Budget/Cost & Profit Centers | Auto AR/AP + settlement + comprehensive financial management |
| 🤝 CRM | Customers/Contacts/Follow-ups/Campaigns/Tickets/Analytics/Funnel/Pool/Quotation/Contract | Customer lifecycle management |
| ✅ Workflow | Workflow definition/Submit/Approve/Reject/Withdraw/My Approvals | Multi-node approval engine |
| 🔔 Notification | Notification list/Mark read/Unread count/Mark all read | Real-time push with status tracking |
| 📐 Projects | Project/Task/Timesheet | Project progress tracking & resource management |
| 👤 HR | Department/Employee/Position/Attendance/Leave/Salary | Full HR management |
| 🏭 Manufacturing | BOM/Production Order/Routing/Workstation/MRP | Material requirements planning & production execution |
| 📈 Reports | Report templates/Dataset/Field/Filter/Execute/Schedule | Visual report builder |

## ERP Modules

Cross-module data flow:

- Purchase receiving → Auto stock-in (moving-weighted-average costing) → Auto-generate AP
- Sales delivery → Auto stock-out → Auto-generate AR
- Receipts & payments → Write-off AR/AP → Update cash journal
- Voucher audit → Auto-update general ledger + subsidiary ledger
- Balance sheet → Auto-generated from general ledger closing balances
- Cash flow statement → Auto-generated from cash journal (operating/investing/financing)
- Approval workflow → Business document submission → Multi-node routing → Approval callback to business modules
- Notification → Approval/Alert/System event triggers → Real-time push → User mark-as-read
- MRP → Based on sales orders + BOM → Calculate material requirements → Generate purchase/production recommendations

## Copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

This copyright notice is permanent, must not be modified, removed, or reversed. All project files are protected under this copyright.

## Tech Stack

| Layer | Technology | Notes |
|---|------|------|
| Backend | webman v2 (workerman) | High-performance PHP daemon framework |
| PHP | 8.3+ | |
| Database | MySQL 8.0+ | Table prefix `erik_`, BIGINT non-auto-increment PKs |
| Search | Elasticsearch | Synced via `webman-scout` |
| Admin Frontend | Flutter 3.x | Web renders as desktop admin panel (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Native HarmonyOS client (`apps/harmonyos/`), supports phone/tablet/2in1 |

## Core Packages

| Package | Purpose |
|---|------|
| `erikwang2013/snowflake-php` | Globally unique BIGINT primary key generation |
| `erikwang2013/hashids` | API-layer ID encryption to hide real database IDs |
| `erikwang2013/jwt-webman` | JWT token issuance and verification |
| `erikwang2013/encryption` | Transport-layer sensitive data encryption |
| `erikwang2013/encryptable` | Database-layer sensitive field auto encryption |
| `erikwang2013/webman-scout` | Elasticsearch sync and full-text search |
| `erikwang2013/season` | Country flag data |
| `erikwang2013/poster-php` | Click captcha generation/verification + poster generation |
| `erikwang2013/security-php` | Security tools inspection |
| `phpoffice/phpspreadsheet` | Excel export |
| `barryvdh/laravel-dompdf` | PDF export (Dompdf-based) |
| `hg/apidoc` | API doc auto-generation | Annotation-based docs, admin/client groups |

## Internationalization

i18n | Accept-Language header auto-detection | Chinese/English bilingual support

## Project Structure

```
open-erp/
├── app/
│   ├── admin/controller/       # System management controllers (14)
│   ├── api/v1/controller/      # Client API (version via API-Version header)
│   ├── controller/             # Business module controllers (70)
│   │   ├── product/            # Product/category/brand/warehouse/location/supplier/customer (7)
│   │   ├── purchase/           # Requisition/order/receive/return/settlement (5)
│   │   ├── sales/              # Quotation/order/delivery/return/settlement (5)
│   │   ├── inventory/          # Stock/flow/transfer/check/alert (5)
│   │   ├── finance/            # AR/AP/voucher/receipt/payment/journal/ledger/report/asset/tax/currency/budget/cost-profit-center (20)
│   │   ├── crm/                # Opportunity/follow/funnel/contact/pool/contract/quotation/campaign/ticket/analytics (10)
│   │   ├── workflow/           # Workflow definition/approval submit/approve/reject/withdraw (2)
│   │   ├── notification/       # Notification list/read/unread count (1)
│   │   ├── project/            # Project/task/timesheet (3)
│   │   ├── hr/                 # Department/employee/position/attendance/leave/salary (5)
│   │   ├── manufacturing/      # BOM/production order/routing/workstation/MRP (5)
│   │   └── report/             # Report template/dataset/execute/schedule (2)
│   ├── service/                # Business logic layer
│   │   ├── inventory/          # Stock in/out + moving-weighted-average cost
│   │   ├── finance/            # AR/AP auto-generation + settlement
│   │   └── notification/       # Notification dispatch service
│   ├── model/                  # 121 Eloquent models (shared across modules)
│   ├── middleware/             # 9 middleware
│   ├── common/                 # Hashids/Snowflake/Encryption services
│   └── queue/                  # Queue tasks
├── apps/
│   ├── flutter/                # Flutter cross-platform (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # HarmonyOS native client
├── config/                     # Configuration files (commented in Chinese)
│   ├── plugin/hg/apidoc/        # API doc configuration
├── database/
│   ├── migrations/             # SQL migration files (18 files, 122 tables)
│   └── backup/                 # Backup/restore scripts
├── docs/                       # Architecture, design, security, API docs
├── tests/                      # PHPUnit tests (11 test files, 90 test methods, 168 assertions)
├── resource/
│   └── translations/           # Translation files (zh_CN, en)
│       ├── zh_CN/              # Chinese translations (127 keys)
│       └── en/                 # English translations (127 keys)
├── public/                     # Public entry
├── runtime/                    # Runtime files
└── vendor/                     # Composer dependencies
```

## Requirements

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (frontend development only)
- Elasticsearch >= 7.x (optional, for search)

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Key environment variables:

| Variable | Description | Default |
|---------|-------------|---------|
| `JWT_SECRET` | JWT signing key | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API encryption key | 32-byte default |
| `SNOWFLAKE_DATACENTER_ID` | Datacenter ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES hosts | `http://localhost:9200` |

**Always change all keys to random strings in production.**

### 3. Initialize Database

Run the SQL migration files in order:

```bash
# Create tables
mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
# Seed permissions
mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql
```

### 4. Start Server

```bash
php start.php start
```

Default: `http://0.0.0.0:8787`.

### 5. Start Frontend (Optional)

**Flutter admin panel (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (desktop admin panel style)
```

**HarmonyOS client (Mobile):**

Open `apps/harmonyos/` in DevEco Studio and run on a device or emulator.

### 6. Docker Compose (Recommended for Production)

Full Docker orchestration with 5 services: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Configure Docker environment variables
cp .env.docker .env

# 2. Start all services
docker-compose up -d

# 3. Initialize database (run inside the app container)
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql

# 4. Access
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx reverse proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, based on `php:8.3-cli`
- `docker-compose.yml`: 5 services, isolated network, persistent volumes
- `.env.docker`: Docker-specific environment variables

## Database Conventions

- **Prefix**: `erik_`
- **Primary Key**: `id BIGINT UNSIGNED NOT NULL`, **NO AUTO_INCREMENT**
- **ID Generation**: PKs are generated at the application layer via `SnowflakeService::generate()`
- **Required Columns**: Every table must have `id`, `created_at`, `updated_at`
- **Soft Delete**: Add `deleted_at DATETIME DEFAULT NULL` where needed
- **Sensitive Fields**: Phone, email, ID card — stored as ciphertext via the `encryptable` plugin, database column type `VARCHAR(500)`

## API Conventions

### API Documentation

The project uses hg/apidoc for auto-generated API documentation, accessible at `/apidoc`.

- Admin API: 25 module groups, with full request parameters and response structures
- Service API: 3 groups (auth/captcha/products)
- All endpoints annotated with JWT auth, API version, i18n and other global headers

### Response Format

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Error Codes

| Code | Meaning |
|------|---------|
| `0` | Success |
| `400` | Bad request |
| `401` | Unauthenticated |
| `403` | Forbidden / Security blocked | RBAC / SecurityFilter attack detected |
| `404` | Not found |
| `422` | Validation failed |
| `413` | Payload too large | SecurityFilter triggered, >10MB |
| `405` | Method not allowed | SecurityFilter triggered, only GET/POST/PUT/DELETE/OPTIONS/HEAD permitted |
| `415` | Unsupported media type | SecurityFilter triggered, non-JSON Content-Type |
| `429` | Rate limited | RateLimit triggered / Account locked (5 failed logins, 15 min lockout) |
| `500` | Server error |

### Internationalization

The `Accept-Language` request header auto-switches the language (zh-CN → Chinese, en → English). Default: Chinese.

### ID Handling

- **API request/response IDs**: Encrypted to hashid strings, real DB IDs never exposed
- **URL paths**: `GET /admin/user/{hashid}` — the `{id}` parameter is a hashid
- **Database storage**: BIGINT raw values generated by snowflake

### API Versioning

The API version is specified via a request header — **not in the URL path**:

```http
API-Version: v1
```

- Defaults to `v1` when the header is absent
- Unsupported versions return `400 Bad Request`
- To add a new version, create `app/api/{version}/controller/` and register it in the middleware

### Rate Limiting

Redis sliding-window algorithm, default 60 req/min/IP/route. Stricter limits for auth:
- Login: 10 req/min
- Register: 5 req/min

Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers. 429 responses include `Retry-After`.

### Middleware Architecture

Global middleware runs for every request in order:

```
Locale (Accept-Language auto-detection, sets language locale)
  → Cors (preflight + response headers)
  → SecurityFilter (HTTP method restriction/body size/Content-Type check/XSS/SQLi/path traversal/cmd injection/CSRF blocking)
  → RateLimit (Redis sliding-window + account lockout: 5 failed logins = 15 min lock)
  → ApiVersion (API version validation, /api group)
  → AdminAuth (JWT + blacklist, /admin group)
  → AdminPermission (RBAC, /admin group)
  → OperationLog (auto-log POST/PUT/DELETE with source detection, /admin group)
```

`/health` and `/api/docs` are public, only passing through `Locale → Cors → SecurityFilter → RateLimit`.

Security enhancements:
- **Account lockout**: 5 consecutive failed login attempts lock the account for 15 minutes; login returns 429 during lockout
- **Concurrent session limit**: Max 3 active tokens per user; exceeding this blacklists the oldest token automatically
- **security.txt**: `GET /.well-known/security.txt` provides RFC 9116 standard security contact information
- **Nginx security config**: See `docs/nginx-security.conf` for a complete reverse-proxy security hardening reference

### Authentication

Login and registration require **click captcha** verification:

1. Client requests `POST /api/captcha/generate` to get a captcha image (base64 PNG) and target word list
2. User clicks the corresponding word positions on the image in order
3. Login request includes `captcha_key` and `clicks` array — server verifies captcha before credentials

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

All admin endpoints require a JWT token:

```http
Authorization: Bearer <token>
```

Login returns an `access_token` (2h TTL) and a `refresh_token` (14d TTL).

Logout blacklists the token in Redis for its remaining TTL: `POST /admin/profile/logout`

### Sensitive Operation Confirmation

Destructive operations (delete user, role, permission) require the current user's `password` in the request body for identity re-verification:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API Reference

> All `/api/*` endpoints require the `API-Version: v1` header (defaults to v1 if absent).

### Public Endpoints

| Method | Path | Description |
|-----|------|------|
| `GET` | `/health` | Health check (DB/Redis/ES status) |
| `GET` | `/api/docs` | OpenAPI 3.0 specification |
| `POST` | `/api/captcha/generate` | Generate click captcha |
| `POST` | `/api/captcha/verify` | Verify click positions |
| `POST` | `/api/auth/login` | Login (requires captcha) |
| `POST` | `/api/auth/register` | Register (requires captcha) |
| `POST` | `/api/auth/refresh` | Refresh token |
| `GET` | `/metrics` | Prometheus metrics |

### Admin Endpoints (requires JWT + RBAC)

| Method | Path | Description |
|-----|------|------|
| `GET` | `/admin/dashboard` | Dashboard data (stats, trends, distribution) |
| `GET` | `/admin/user` | User list (paginated + search) |
| `POST` | `/admin/user` | Create user |
| `GET` | `/admin/user/{id}` | User detail |
| `PUT` | `/admin/user/{id}` | Update user |
| `DELETE` | `/admin/user/{id}` | Delete user (soft delete, requires password) |
| `POST` | `/admin/user/batch/destroy` | Batch delete users |
| `POST` | `/admin/user/batch/status` | Batch update user status |
| `GET` | `/admin/role` | Role list |
| `POST` | `/admin/role` | Create role |
| `GET` | `/admin/role/{id}` | Role detail |
| `PUT` | `/admin/role/{id}` | Update role |
| `DELETE` | `/admin/role/{id}` | Delete role (requires password) |
| `GET` | `/admin/permission` | Permission tree |
| `POST` | `/admin/permission` | Create permission |
| `GET` | `/admin/permission/{id}` | Permission detail |
| `PUT` | `/admin/permission/{id}` | Update permission |
| `DELETE` | `/admin/permission/{id}` | Delete permission (cascades children, requires password) |
| `GET` | `/admin/config` | Config list |
| `POST` | `/admin/config` | Create config |
| `PUT` | `/admin/config/{id}` | Update config |
| `DELETE` | `/admin/config/{id}` | Delete config |
| `GET` | `/admin/log` | Operation log list (paginated) |
| `PUT` | `/admin/profile` | Update profile |
| `PUT` | `/admin/profile/password` | Change password |
| `POST` | `/admin/profile/logout` | Logout (blacklist token) |
| `POST` | `/admin/export/excel` | Export to Excel |
| `POST` | `/admin/export/pdf` | Export to PDF |
| `POST` | `/admin/import/users` | Import users from Excel |
| `POST` | `/admin/upload` | Upload file |

### Business Endpoints (requires JWT + RBAC)

| Method | Path | Description |
|-----|------|------|
| `GET/POST/PUT/DELETE` | `/admin/product` | Product CRUD (incl. SKU, pricing) |
| `GET/POST/PUT/DELETE` | `/admin/category` | Product category CRUD (tree) |
| `GET/POST/PUT/DELETE` | `/admin/brand` | Brand CRUD |
| `GET/POST/PUT/DELETE` | `/admin/warehouse` | Warehouse CRUD |
| `GET` | `/admin/warehouse/{id}/locations` | Warehouse locations |
| `GET/POST/PUT/DELETE` | `/admin/location` | Location CRUD |
| `GET/POST/PUT/DELETE` | `/admin/supplier` | Supplier CRUD |
| `GET/POST/PUT/DELETE` | `/admin/customer` | Customer CRUD |
| `ANY` | `/admin/customer-level` | Customer level management |
| `GET/POST/PUT/DELETE` | `/admin/purchase/apply` | Purchase requisition |
| `GET/POST/PUT/DELETE` | `/admin/purchase/order` | Purchase order |
| `GET/POST/PUT/DELETE` | `/admin/purchase/receive` | Purchase receiving (auto stock-in + AP) |
| `GET/POST/PUT/DELETE` | `/admin/purchase/return` | Purchase return |
| `ANY` | `/admin/purchase/settlement` | Supplier settlement |
| `GET/POST/PUT/DELETE` | `/admin/sales/quotation` | Sales quotation |
| `GET/POST/PUT/DELETE` | `/admin/sales/order` | Sales order |
| `GET/POST/PUT/DELETE` | `/admin/sales/delivery` | Sales delivery (auto stock-out + AR) |
| `GET/POST/PUT/DELETE` | `/admin/sales/return` | Sales return |
| `ANY` | `/admin/sales/settlement` | Customer settlement |
| `ANY` | `/admin/inventory` | Real-time inventory query |
| `ANY` | `/admin/inventory/flow` | Inventory flow |
| `GET/POST/PUT/DELETE` | `/admin/inventory/transfer` | Inventory transfer |
| `GET/POST/PUT/DELETE` | `/admin/inventory/check` | Stocktaking task |
| `GET/POST/PUT/DELETE` | `/admin/inventory/alert` | Inventory alert rules |
| `GET/POST/PUT/DELETE` | `/admin/finance/ar-ap` | AR/AP |
| `GET/POST/PUT/DELETE` | `/admin/finance/voucher` | Journal voucher |
| `GET/POST/PUT/DELETE` | `/admin/finance/receipt` | Receipt |
| `GET/POST/PUT/DELETE` | `/admin/finance/payment` | Payment |
| `ANY` | `/admin/finance/cash-journal` | Cash/bank journal |
| `GET/POST/PUT/DELETE` | `/admin/finance/expense` | Expense reimbursement |
| `ANY` | `/admin/finance/report/profit` | Profit statement |
| `GET/POST/PUT/DELETE` | `/admin/finance/bank-account` | Bank account |
| `ANY` | `/admin/finance/general-ledger` | General ledger (by account + period) |
| `ANY` | `/admin/finance/subsidiary-ledger` | Subsidiary ledger (per-account entries) |
| `ANY` | `/admin/finance/report/balance-sheet` | Balance sheet |
| `ANY` | `/admin/finance/report/cash-flow` | Cash flow statement |
| `GET/POST/PUT/DELETE` | `/admin/finance/asset` | Fixed asset CRUD + depreciation |
| `GET/POST/DELETE` | `/admin/finance/tax-rate` | Tax rate configuration |
| `ANY` | `/admin/finance/tax-record` | Tax records |
| `GET/POST/PUT/DELETE` | `/admin/finance/currency` | Currency management |
| `GET/POST/PUT/DELETE` | `/admin/finance/exchange-rate` | Exchange rate management |
| `GET/POST/PUT/DELETE` | `/admin/finance/budget` | Budget management (budget vs. actual) |
| `GET/POST/PUT/DELETE` | `/admin/finance/cost-center` | Cost center (tree structure) |
| `GET/POST/PUT/DELETE` | `/admin/finance/profit-center` | Profit center (tree structure) |
| `GET/POST/PUT/DELETE` | `/admin/crm/opportunity` | Opportunity management |
| `GET/POST/PUT/DELETE` | `/admin/crm/follow` | Follow-up record |
| `GET/POST/PUT/DELETE` | `/admin/crm/funnel` | Sales funnel stages |
| `GET/POST/PUT/DELETE` | `/admin/crm/contact` | Contact |
| `ANY` | `/admin/crm/pool` | Customer pool (list) |
| `POST` | `/admin/crm/pool/claim/{id}` | Claim customer from pool |
| `POST` | `/admin/crm/pool/release/{id}` | Release customer to pool |
| `GET/POST/PUT/DELETE` | `/admin/crm/pool/rules` | Pool rules |
| `GET/POST/PUT/DELETE` | `/admin/crm/contract` | Contract CRUD |
| `POST` | `/admin/crm/contract/{id}/transition` | Contract status transition |
| `GET/POST/PUT/DELETE` | `/admin/crm/quotation` | CRM quotation |
| `POST` | `/admin/crm/quotation/{id}/to-contract` | Convert quotation to contract |
| `GET/POST/PUT/DELETE` | `/admin/crm/campaign` | Marketing campaigns |
| `GET/POST/PUT/DELETE` | `/admin/crm/ticket` | Service tickets |
| `POST` | `/admin/crm/ticket/{id}/assign` | Assign ticket |
| `POST` | `/admin/crm/ticket/{id}/resolve` | Resolve ticket |
| `POST` | `/admin/crm/ticket/{id}/reply` | Reply ticket |
| `ANY` | `/admin/crm/analytics/report` | Analytics reports |
| `POST` | `/admin/crm/analytics/generate` | Generate analytics report |
| `ANY/POST` | `/admin/crm/analytics/metric` | Analytics metrics |
| `ANY` | `/admin/dashboard/sales` | Sales dashboard |
| `ANY` | `/admin/dashboard/inventory` | Inventory dashboard |
| `ANY` | `/admin/dashboard/finance` | Finance dashboard |
| `GET/POST/PUT/DELETE` | `/admin/workflow` | Workflow definition CRUD |
| `POST` | `/admin/workflow/{id}/submit` | Submit for approval |
| `POST` | `/admin/approval/{id}/approve` | Approve |
| `POST` | `/admin/approval/{id}/reject` | Reject |
| `POST` | `/admin/approval/{id}/withdraw` | Withdraw |
| `ANY` | `/admin/approval/my` | My approvals |
| `ANY` | `/admin/notification/my` | My notifications |
| `POST` | `/admin/notification/{id}/read` | Mark as read |
| `POST` | `/admin/notification/read-all` | Mark all read |
| `ANY` | `/admin/notification/unread-count` | Unread count |
| `GET/POST/PUT/DELETE` | `/admin/project` | Project CRUD |
| `GET/POST/PUT/DELETE` | `/admin/project/task` | Project task CRUD |
| `GET/POST/PUT/DELETE` | `/admin/project/timesheet` | Timesheet CRUD |
| `GET/POST/PUT/DELETE` | `/admin/hr/department` | Department CRUD |
| `GET/POST/PUT/DELETE` | `/admin/hr/employee` | Employee CRUD |
| `GET/POST/PUT/DELETE` | `/admin/hr/position` | Position CRUD |
| `ANY/POST` | `/admin/hr/attendance` | Attendance/clock-in/out |
| `GET/POST/PUT/DELETE` | `/admin/hr/leave` | Leave CRUD + approval |
| `GET/POST/PUT/DELETE` | `/admin/hr/salary` | Salary CRUD + pay |
| `ANY/POST` | `/admin/hr/salary-item` | Salary items |
| `GET/POST/PUT/DELETE` | `/admin/mfg/bom` | BOM CRUD |
| `GET/POST/PUT/DELETE` | `/admin/mfg/production` | Production order + start/complete |
| `GET/POST/PUT/DELETE` | `/admin/mfg/routing` | Routing CRUD |
| `GET/POST/PUT/DELETE` | `/admin/mfg/workstation` | Workstation CRUD |
| `GET/POST/PUT/DELETE` | `/admin/mfg/mrp` | MRP plan + generate |
| `GET/POST/PUT/DELETE` | `/admin/report` | Report template CRUD |
| `POST` | `/admin/report/{id}/execute` | Execute report |
| `ANY` | `/admin/report/{id}/result` | Report result |
| `GET/POST/PUT/DELETE` | `/admin/report/schedule` | Report schedule |

### Client Endpoints (requires API-Version header)

| Method | Path | Description |
|-----|------|------|
| `GET` | `/api/product` | Product list (excl. purchase price) |
| `GET` | `/api/product/{hashid}` | Product detail (incl. retail/wholesale price) |

## Frontend Notes

### Flutter Admin Panel (Desktop Style)

- **Layout**: Collapsible sidebar (64px/240px) + header + content area, responsive breakpoints (phone/tablet/desktop)
- **Pages**: Login, Dashboard, User Management, Roles & Permissions, System Config, Operation Logs, Profile
- **State**: GetX (`ApiService` singleton + `AuthService` token persistence)
- **Dashboard**: Stats cards, trend line chart (fl_chart), pie chart, recent activity log
- **Export**: Excel/PDF with non-removable copyright info
- **Batch Ops**: Multi-select batch delete, batch enable/disable
- **Theme**: Material 3 light/dark dual theme

### HarmonyOS Mobile Client

- **Pages**: Login, Dashboard, User List/Detail, Profile
- **Auth**: JWT Bearer + silent token refresh on 401, auto-redirect to login on refresh failure
- **Storage**: Token managed via AppStorage

## Development Rules

- No leading `\` on global function/class references — use `use` imports
- All PHP files must include the copyright header
- All config files must include inline comments
- Primary keys must be generated at the application layer via snowflake — no auto-increment
- All IDs in API parameters and responses must be encoded/decoded via hashids
- AdminPermission middleware uses Redis cache for user permissions (TTL=60s), eliminating N+1 query bottlenecks

## Deployment

### Docker Compose (Recommended)

`docker-compose.yml` in the project root orchestrates 5 services:

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Local `Dockerfile` build | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

The PHP image is built from `Dockerfile`, based on `php:8.3-cli` with OPcache enabled.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions CI pipeline: `.github/workflows/ci.yml`

- PHP syntax check (`php -l`)
- PHPUnit tests
- Flutter static analysis (`flutter analyze`)

### Database Backup

`database/backup/` directory:

- `backup.sh` — mysqldump + gzip backup, auto-clears backups older than 30 days
- `restore.sh` — interactive restore, lists available backups for selection

### Nginx Security

See `docs/nginx-security.conf` for production reverse-proxy security hardening.

## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
