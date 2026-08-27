# ERP Ecosystem Full Roadmap — Design Spec

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Developed based on the 2026-08-04 ecosystem review report, covering four priority phases P0~P3

---

## 1. Current Baseline

| Dimension | Current State | Score |
|-----------|---------------|-------|
| Backend APIs | 14 modules / 80+ controllers / 120+ models, multi-module CRUD skeletons | 85/100 |
| Security | 18-layer defense in depth, CORS/SecurityFilter/RateLimit/JWT/encryption | 95/100 |
| Frontend UI | Flutter 12 pages, HarmonyOS 9 pages, covering ~20% of modules; Web admin panel missing | 20/100 |
| Ops ecosystem | Dockerized, CI done, missing migration rollback, backup automation, observability | 70/100 |
| Business depth | Finance/HR/manufacturing module table structures complete but business logic is mostly CRUD | 55/100 |
| **Overall** | | **65/100** |

---

## 2. Overall Strategy

```
Serial waterfall: P0 → P1 → P2 → P3
Subtasks with independence within each phase can proceed in parallel
```

### 2.1 Frontend Technology Selection

- **Web admin panel**: Flutter Web, reusing the existing `apps/flutter` code, PC admin-panel style, GetX state management
- **Mobile**: Flutter (iOS/Android), sharing the `apps/flutter/lib/app/` business code with Web
- **HarmonyOS**: ArkTS, aligned with the Flutter feature set

### 2.2 Backend Strategy

- **Industrial-grade** (Tier A): double-entry accounting, payroll calculation, MRP engine — complete algorithms, thorough edge-case handling, production-ready
- **Core-usable** (Tier B): quality management, notification system, BI dashboards — key rules implemented, iterated later as needed

---

## 3. P0 — Frontend Ecosystem (3-4 weeks)

> **Goal**: give the system a usable admin interface covering all implemented backend modules

### 3.1 Flutter Project Architecture Refactor

```
apps/flutter/lib/app/
├── main.dart                      # Entry point, initializes GetX + Dio
├── routes/
│   └── app_pages.dart             # Full route registration (grouped by module)
├── layouts/
│   └── admin_layout.dart          # PC three-column layout (sidebar + top bar + content)
├── theme/
│   └── app_theme.dart             # Material 3 theme (brand color #1677FF)
├── services/
│   ├── api_service.dart           # Dio singleton + JWT interceptor + auto refresh
│   ├── auth_service.dart          # Auth state management
│   ├── captcha_service.dart       # Click captcha
│   └── export_service.dart        # Excel/PDF export download
├── widgets/
│   ├── data_table_wrapper.dart    # Generic data table (pagination/search/batch operations)
│   ├── form_dialog.dart           # Generic form dialog
│   ├── confirm_dialog.dart        # Second confirmation dialog (password input)
│   └── stat_card.dart             # Stat card
└── pages/
    ├── login/                     # Login page
    ├── dashboard/                 # Dashboard (6 boards to switch between)
    ├── system/
    │   ├── user/                  # User management (including batch/import)
    │   ├── role/                  # Roles + permission tree
    │   ├── config/                # System config
    │   └── log/                   # Operation logs
    ├── product/                   # Product/category/brand/SKU
    ├── partner/                   # Supplier/customer/warehouse/location
    ├── purchase/                  # Purchase request/order/receiving/return/settlement
    ├── sales/                     # Sales quotation/order/delivery/return/settlement
    ├── inventory/                 # Inventory/flow/transfer/check/alert
    ├── finance/
    │   ├── voucher/               # Accounting voucher
    │   ├── ar_ap/                 # AR/AP
    │   ├── receipt_payment/       # Receipts/payments
    │   ├── ledger/                # General ledger/detail ledger
    │   ├── report/                # Three statements (income/balance sheet/cash flow)
    │   ├── asset/                 # Fixed assets
    │   ├── tax/                   # Tax
    │   ├── currency/              # Multi-currency/exchange rates
    │   ├── budget/                # Budget
    │   └── cost_profit/           # Cost/profit centers
    ├── crm/
    │   ├── opportunity/           # Opportunity funnel
    │   ├── contact/               # Contacts
    │   ├── pool/                  # Public pool
    │   ├── contract/              # Contracts
    │   ├── quotation/             # Quotations
    │   ├── campaign/              # Marketing campaigns
    │   ├── ticket/                # Service tickets
    │   └── analytics/             # Customer analytics
    ├── oms/                       # OMS order/fulfillment/return/channel
    ├── wms/                       # WMS zones/locations/receiving/putaway/wave/pick/pack
    ├── tms/                       # TMS carrier/rates/shipment/tracking/settlement
    ├── manufacturing/             # BOM/production order/routing/workstation/MRP
    ├── hr/                        # Department/employee/position/attendance/leave/payroll
    ├── project/                   # Project/task/timesheet
    ├── workflow/                  # Approval workflow/my approvals
    ├── notification/              # Notification center
    ├── report/                    # Custom reports
    └── profile/                   # Profile center
```

### 3.2 Common Component Development

| Component | Features | Usage Scenarios |
|-----------|----------|-----------------|
| `DataTableWrapper` | Pagination/sorting/keyword search/status filter/batch selection/column config | All list pages |
| `FormDialog` | Dynamic form rendering/field validation/submit/close | All create/edit dialogs |
| `ConfirmDialog` | Password second-confirmation input | All delete operations |
| `StatCard` | Value/trend arrow/title | Dashboard |
| `BreadcrumbNav` | Breadcrumb navigation | Deep pages |
| `FileUploader` | Drag-and-drop upload/progress/preview | Import/image upload |

### 3.3 HarmonyOS Completion

Align with the Flutter page set, adding: OMS/WMS/TMS/Manufacturing/HR/Approval/Notification/Report module pages.

### 3.4 P0 Acceptance Criteria

- [ ] Flutter Web admin panel covers all 14 modules
- [ ] All CRUD list pages work (pagination/search/filter)
- [ ] All create/edit forms work (validation/submit)
- [ ] Delete operations require password second confirmation
- [ ] JWT auto-refresh is seamless
- [ ] PC/tablet/phone responsive layout adaptation
- [ ] HarmonyOS page count ≥ 80% of the Flutter page count

---

## 4. P1 — Business Depth (4-6 weeks)

> **Goal**: upgrade core modules from CRUD skeletons to real business calculation engines

### 4.1 Finance Double-Entry Engine (industrial-grade)

```
app/service/finance/
├── DoubleEntryService.php        # Debit/credit balance validation + automatic entry generation
├── PeriodCloseService.php        # Period-end closing (P&L carry-over/cost carry-over)
├── AccountBalanceService.php     # Subject balance aggregation (monthly/quarterly/yearly)
├── ConsolidationService.php      # Multi-currency consolidated statements (FX conversion)
└── FinancialRatioService.php     # Automatic financial ratio calculation

app/controller/finance/
├── PeriodCloseController.php     # Period-end closing operations
├── AccountBalanceController.php  # Subject balance query
└── FinancialRatioController.php  # Ratio analysis query
```

**Key rules**:
- Voucher saving enforces "every debit has a credit, debits must equal credits"
- Audited vouchers cannot be modified; red-letter reversals are required
- Period-end closing: P&L subject balances → current-year profit, supports multi-step closing
- Multi-currency: converted at period-end rates, FX gains/losses calculated automatically

### 4.2 Payroll Calculation Engine (industrial-grade)

```
app/service/hr/
├── SalaryEngineService.php       # Payroll calculation main engine
├── SocialInsuranceService.php    # Social insurance calculation (pension/medical/unemployment/work injury/maternity)
├── HousingFundService.php        # Housing fund calculation
├── TaxCalculatorService.php      # Individual income tax progressive rate calculation
└── BankPayrollService.php        # Bank payroll file export

app/controller/hr/
└── PayrollController.php         # Payroll calculation/disbursement/query
```

**Key rules**:
- Social insurance base upper/lower limits (adjusted annually by city, configurable)
- Housing fund base + contribution rate (5%-12%, configurable)
- Individual income tax progressive rate table (3%-45%, annual settlement)
- Bank payroll format: supports mainstream banks such as ICBC/BOC/CCB/CMB
- Payslip generation (with all itemized details)

### 4.3 MRP Engine (industrial-grade)

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP calculation main engine
├── DemandForecastService.php      # Demand aggregation (orders + forecast + safety stock)
├── NetRequirementService.php      # Net requirement calculation (gross - on hand - in transit)
├── BomExplosionService.php        # BOM explosion (layer by layer down to raw materials)
└── OrderSuggestionService.php     # Suggested order generation (purchase/production/outsourcing)

app/model/
├── MfgMrpRunLog.php              # MRP run log
└── MfgOrderSuggestion.php        # Suggested orders
```

**Key rules**:
- BOM explodes layer by layer, taking loss rates into account
- Net requirement = gross requirement - on-hand inventory - in-transit inventory + allocated quantity + safety stock
- Low-level code (LLC) ensures each material is calculated only once
- Lead time back-scheduling for suggested order dates
- Lot-sizing rules: fixed lot size/economic order quantity/lot-for-lot

### 4.4 Quality Management (core-usable)

```
app/controller/quality/
├── InspectionStandardController.php  # Inspection standards
├── IncomingCheckController.php       # IQC incoming inspection
├── ProcessCheckController.php        # IPQC in-process inspection
├── FinalCheckController.php          # OQC outgoing inspection
└── NonconformityController.php       # Nonconforming product handling

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 Real-time Notification System (core-usable)

```
app/service/notification/
├── WebSocketService.php           # WebSocket connection management + push
├── ChannelRouter.php              # Multi-channel routing (in-app/email/WeCom/DingTalk)
├── TemplateRenderer.php           # Notification template rendering

app/process/
└── WebSocket.php                  # WebSocket process

app/controller/notification/
├── WebSocketController.php        # WebSocket event handling
└── ChannelConfigController.php    # Notification channel configuration
```

**Key rules**:
- WebSocket is based on the native workerman protocol
- Notification templates: variable placeholders like `{order_code}` replaced at runtime
- Channel priority: in-app → email → WeCom → DingTalk, configurable

### 4.6 P1 Acceptance Criteria

- [ ] Saving a voucher with unequal debits/credits → error returned
- [ ] Payroll engine output matches manual calculation (spot-check 10 people's monthly salary data)
- [ ] MRP net requirement calculation matches manual Excel derivation
- [ ] The three quality inspection documents (IQC/IPQC/OQC) flow completely
- [ ] WebSocket notification latency < 2 seconds
- [ ] All new services have PHPUnit test coverage (key algorithms ≥ 95%)

---

## 5. P2 — Ops Reliability (1-2 weeks)

> **Goal**: production-grade operations capabilities

### 5.1 Database Migration Rollback

```
database/migrations/
├── migrate.sh                    # Forward migration script
└── rollback.sh                   # Rollback script (executed in reverse order of migration files)
```

Each migration file gets a corresponding `_rollback.sql` file.

### 5.2 Backup/Restore Enhancement

```
database/backup/
├── backup.sh                     # Existing
├── restore.sh                    # Existing
├── auto-backup.sh                # New: cron scheduled backup + alerts
└── backup-validator.sh           # New: backup file integrity validation
```

### 5.3 Observability

```
app/service/observability/
├── TracerService.php             # OpenTelemetry tracing
└── MetricCollector.php           # Business metric collection
```

- Request-level trace ID (exposed via the `X-Trace-Id` response header)
- Key business metrics: order volume, fulfillment rate, inventory turnover days

### 5.4 Message Queue Upgrade

Existing Redis queue → supports RabbitMQ as an optional driver:

```
config/queue.php                  # Queue driver configuration (redis/rabbitmq)
```

### 5.5 P2 Acceptance Criteria

- [ ] Migration rollback script executes and data integrity verification passes
- [ ] Automatic backup cron triggers correctly
- [ ] Trace ID spans the entire request chain
- [ ] RabbitMQ driver is switchable without message loss

---

## 6. P3 — Experience Enhancement (2-3 weeks)

> **Goal**: advanced features and better user experience

### 6.1 BI Data Dashboards

```
app/controller/bi/
├── DashboardController.php       # Configurable dashboards
├── WidgetController.php          # Chart widget CRUD
└── DatasetController.php         # Dataset management

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- Dashboards with drag-and-drop layouts
- Widgets: bar chart/line chart/pie chart/data card/table
- Reuse the dataset mechanism from `app/controller/report/`

### 6.2 Equipment Management (EAM)

```
app/controller/eam/
├── EquipmentController.php       # Equipment register
├── MaintenancePlanController.php # Maintenance plans
├── RepairOrderController.php     # Repair work orders
└── SparePartController.php       # Spare part management
```

### 6.3 Multi-Tenancy

```
app/middleware/TenantScope.php    # Tenant isolation middleware
app/model/concerns/TenantScope.php # Eloquent tenant scope trait
```

- Shared database + `tenant_id` isolation
- Super admin cross-tenant view

### 6.4 Document Management (DMS)

```
app/controller/dms/
├── DocumentController.php        # Document CRUD + version management
├── CategoryController.php        # Document categories
└── ApprovalController.php        # Document approval and publishing
```

### 6.5 P3 Acceptance Criteria

- [ ] BI dashboards support drag-and-drop custom layouts
- [ ] Equipment register → maintenance plan → repair work order loop closed
- [ ] Tenant A cannot access tenant B's data
- [ ] Document version history is traceable

---

## 7. Data Model Changes Summary

### P0 New Tables

No new tables; the frontend ecosystem does not involve backend table structure changes.

### P1 New Tables

| Table | Purpose | Phase |
|-------|---------|-------|
| `erp_finance_period_close` | Period-end closing records | P1 |
| `erp_finance_account_balance` | Subject balance snapshots | P1 |
| `erp_hr_salary_config` | Payroll calculation config | P1 |
| `erp_hr_social_insurance_config` | Social insurance base config | P1 |
| `erp_hr_housing_fund_config` | Housing fund config | P1 |
| `erp_mfg_mrp_run_log` | MRP run logs | P1 |
| `erp_mfg_order_suggestion` | Suggested orders | P1 |
| `erp_quality_inspection_standard` | Inspection standards | P1 |
| `erp_quality_iqc_record` | IQC incoming inspection | P1 |
| `erp_quality_ipqc_record` | IPQC in-process inspection | P1 |
| `erp_quality_oqc_record` | OQC outgoing inspection | P1 |
| `erp_quality_nonconformity` | Nonconforming products | P1 |
| `erp_notification_channel_config` | Notification channel config | P1 |
| `erp_notification_template` | Notification templates | P1 |

### P3 New Tables

| Table | Purpose | Phase |
|-------|---------|-------|
| `erp_bi_dashboard` | BI dashboards | P3 |
| `erp_bi_widget` | BI widgets | P3 |
| `erp_eam_equipment` | Equipment register | P3 |
| `erp_eam_maintenance_plan` | Maintenance plans | P3 |
| `erp_eam_repair_order` | Repair work orders | P3 |
| `erp_dms_document` | Controlled documents | P3 |
| `erp_dms_document_version` | Document versions | P3 |

---

## 8. Service Layer Changes Summary

| Service | Current | P1 Changes | P2 Changes | P3 Changes |
|---------|---------|------------|------------|------------|
| FinanceService | CRUD | Add DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Payroll | None | Add SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Manufacturing | CRUD | Add MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Quality | None | Add QmsInspectionService | — | — |
| Notification | Basic | Add WebSocketService, ChannelRouter | — | — |
| Observability | Monitor process | — | Add TracerService, MetricCollector | — |
| BI | None | — | — | Add BiDashboardService |
| Equipment | None | — | — | Add EamService |

---

## 9. Middleware Chain Changes

```
Current: Locale → Cors → SecurityFilter → RateLimit → {route group}

P0: no change
P1: + WebSocketUpgrade (upgrade WebSocket connections on the /ws path)
P2: + TracingId (inject X-Trace-Id)
P3: + TenantScope (multi-tenant isolation)
```

---

## 10. Milestones and Deliverables

| Milestone | Time | Deliverables |
|-----------|------|--------------|
| M0 — Current baseline | 2026-08-04 | Review report `audit-report-2026-08-04.md` |
| M1 — P0 complete | +3 weeks | Flutter Web full-module admin panel |
| M2 — P1 complete | +8 weeks | Finance engine + payroll engine + MRP engine + quality + notifications |
| M3 — P2 complete | +10 weeks | Migration rollback + auto backup + trace + queue upgrade |
| M4 — P3 complete | +13 weeks | BI dashboards + equipment management + multi-tenancy + document management |

---

## 11. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Flutter Web performance below native JS | Large data tables lag | Client-side pagination + virtual scrolling + Web Worker |
| Payroll engine regulatory changes | Calculation results non-compliant | Social insurance/tax rates configurable, not hardcoded |
| MRP calculation timeout on large data volumes | Calculation interrupted | Batch processing + progress callbacks |
| Too many WebSocket long connections | Server memory pressure | workerman is naturally high-concurrency + connection count limits |
| Multi-tenant data isolation gap | Data leakage | TenantScope global middleware + test coverage |

---

## 12. Things Not Done (Explicitly Excluded)

- ❌ No microservice split — the current monolith is sufficient; complex logic is cohesive within the Service layer
- ❌ No Kubernetes — Docker Compose satisfies the current scale
- ❌ No AI/ML features — not in the MVP roadmap
- ❌ No separate native iOS/Android apps — Flutter cross-platform already covers them
- ❌ No GraphQL — RESTful APIs are sufficient, the API versioning strategy is mature
- ❌ No e-signature/WMS hardware integration (PDA/scan guns) — software-only scope
