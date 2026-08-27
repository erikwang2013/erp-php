# ERP-Ökosystem-Gesamtroadmap — Designspezifikation

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Erstellt auf Basis des Ökosystem-Prüfberichts vom 2026-08-04, deckt die vier Prioritätsphasen P0~P3 ab

---

## 1. Aktuelle Ausgangsbasis

| Dimension | Ist-Zustand | Bewertung |
|------|------|------|
| Backend-API | 14 Module / 80+ Controller / 120+ Modelle, CRUD-Skelett über mehrere Module | 85/100 |
| Sicherheitsmaßnahmen | 18 Ebenen Tiefenverteidigung, CORS/SecurityFilter/RateLimit/JWT/Verschlüsselung | 95/100 |
| Frontend-UI | Flutter 12 Seiten, HarmonyOS 9 Seiten, deckt ca. 20 % der Module ab; Web-Verwaltungspanel fehlt | 20/100 |
| Betriebsökosystem | Dockerisiert, CI fertig; es fehlen Migrations-Rollback, Backup-Automatisierung, Observability | 70/100 |
| Business-Tiefe | Tabellenstrukturen der Module Finanzwesen/HR/Fertigung vollständig, aber Geschäftslogik überwiegend CRUD | 55/100 |
| **Gesamt** | | **65/100** |

---

## 2. Gesamtstrategie

```
串行瀑布: P0 → P1 → P2 → P3
每个阶段内有独立性的子任务可并行推进
```

### 2.1 Frontend-Technologieauswahl

- **Web-Verwaltungspanel**: Flutter Web, nutzt den bestehenden Code aus `apps/flutter` wieder, PC-Verwaltungsstil, GetX-State-Management
- **Mobil**: Flutter (iOS/Android), teilt sich mit Web den Geschäftscode unter `apps/flutter/lib/app/`
- **HarmonyOS**: ArkTS, ausgerichtet am Flutter-Funktionsumfang

### 2.2 Backend-Strategie

- **Industriestandard** (Klasse A): Doppik, Gehaltsberechnung, MRP-Engine — Algorithmen vollständig, Randfälle ausreichend behandelt, produktionsreif
- **Kernfunktional** (Klasse B): Qualitätsmanagement, Benachrichtigungssystem, BI-Dashboards — Kernregeln implementiert, spätere Iteration nach Bedarf

---

## 3. P0 — Frontend-Ökosystem (3-4 Wochen)

> **Ziel**: Dem System eine nutzbare Verwaltungsoberfläche geben, die alle implementierten Backend-Module abdeckt

### 3.1 Flutter-Projektarchitektur-Umbau

```
apps/flutter/lib/app/
├── main.dart                      # 入口，初始化 GetX + Dio
├── routes/
│   └── app_pages.dart             # 全量路由注册（按模块分组）
├── layouts/
│   └── admin_layout.dart          # PC 三栏布局（侧边栏 + 顶栏 + 内容）
├── theme/
│   └── app_theme.dart             # Material 3 主题（品牌色 #1677FF）
├── services/
│   ├── api_service.dart           # Dio 单例 + JWT 拦截器 + 自动刷新
│   ├── auth_service.dart          # 认证状态管理
│   ├── captcha_service.dart       # 点击验证码
│   └── export_service.dart        # Excel/PDF 导出下载
├── widgets/
│   ├── data_table_wrapper.dart    # 通用数据表格（分页/搜索/批量操作）
│   ├── form_dialog.dart           # 通用表单弹窗
│   ├── confirm_dialog.dart        # 二次确认弹窗（密码输入）
│   └── stat_card.dart             # 统计卡片
└── pages/
    ├── login/                     # 登录页
    ├── dashboard/                 # 仪表盘（6 个看板切换）
    ├── system/
    │   ├── user/                  # 用户管理（含批量/导入）
    │   ├── role/                  # 角色 + 权限树
    │   ├── config/                # 系统配置
    │   └── log/                   # 操作日志
    ├── product/                   # 商品/分类/品牌/SKU
    ├── partner/                   # 供应商/客户/仓库/库位
    ├── purchase/                  # 采购申请/订单/收货/退货/结算
    ├── sales/                     # 销售报价/订单/发货/退货/结算
    ├── inventory/                 # 库存/流水/调拨/盘点/预警
    ├── finance/
    │   ├── voucher/               # 记账凭证
    │   ├── ar_ap/                 # 应收应付
    │   ├── receipt_payment/       # 收付款
    │   ├── ledger/                # 总账/明细账
    │   ├── report/                # 三表（利润/资产负债/现金流）
    │   ├── asset/                 # 固定资产
    │   ├── tax/                   # 税务
    │   ├── currency/              # 多币种/汇率
    │   ├── budget/                # 预算
    │   └── cost_profit/           # 成本/利润中心
    ├── crm/
    │   ├── opportunity/           # 商机漏斗
    │   ├── contact/               # 联系人
    │   ├── pool/                  # 公海池
    │   ├── contract/              # 合同
    │   ├── quotation/             # 报价
    │   ├── campaign/              # 营销活动
    │   ├── ticket/                # 服务工单
    │   └── analytics/             # 客户分析
    ├── oms/                       # OMS 订单/履约/退货/渠道
    ├── wms/                       # WMS 库区库位/收货/上架/波次/拣货/打包
    ├── tms/                       # TMS 承运商/费率/运单/轨迹/结算
    ├── manufacturing/             # BOM/生产订单/工艺/工作站/MRP
    ├── hr/                        # 部门/员工/职位/考勤/请假/薪资
    ├── project/                   # 项目/任务/工时
    ├── workflow/                  # 审批工作流/我的审批
    ├── notification/              # 通知中心
    ├── report/                    # 自定义报表
    └── profile/                   # 个人中心
```

### 3.2 Allzweck-Komponenten-Entwicklung

| Komponente | Funktion | Einsatzbereich |
|------|------|----------|
| `DataTableWrapper` | Paginierung/Sortierung/Keyword-Suche/Statusfilter/Batch-Auswahl/Spaltenkonfiguration | Alle Listenseiten |
| `FormDialog` | Dynamisches Formular-Rendering/Feldvalidierung/Übermittlung/Schließen | Alle Erstell-/Bearbeitungsdialoge |
| `ConfirmDialog` | Passwort-Zweitbestätigungseingabe | Alle Löschvorgänge |
| `StatCard` | Wert/Trendpfeil/Titel | Dashboard |
| `BreadcrumbNav` | Breadcrumb-Navigation | Tief verschachtelte Seiten |
| `FileUploader` | Drag-and-Drop-Upload/Fortschritt/Vorschau | Import/Bildupload |

### 3.3 HarmonyOS-Vervollständigung

Ausrichtung am Flutter-Seitensatz, Vervollständigung: Seiten der Module OMS/WMS/TMS/Fertigung/HR/Genehmigung/Benachrichtigung/Berichte.

### 3.4 P0-Abnahmekriterien

- [ ] Flutter-Web-Verwaltungspanel deckt alle 14 Module ab
- [ ] Alle CRUD-Listenseiten nutzbar (Paginierung/Suche/Filter)
- [ ] Alle Erstell-/Bearbeitungsformulare nutzbar (Validierung/Übermittlung)
- [ ] Passwort-Zweitbestätigung bei Löschvorgängen
- [ ] Automatisches JWT-Refresh ohne spürbare Unterbrechung
- [ ] Responsive Layout-Anpassung für PC/Tablet/Handy
- [ ] HarmonyOS-Seitenzahl ≥ 80 % der Flutter-Seitenzahl

---

## 4. P1 — Business-Tiefe (4-6 Wochen)

> **Ziel**: Die Kernmodule vom CRUD-Skelett zu echten Geschäftsberechnungs-Engines aufwerten

### 4.1 Finanz-Doppik-Engine (Industriestandard)

```
app/service/finance/
├── DoubleEntryService.php        # 借贷平衡校验 + 自动分录生成
├── PeriodCloseService.php        # 期末结转（损益结转/成本结转）
├── AccountBalanceService.php     # 科目余额汇总（按月/按季/按年）
├── ConsolidationService.php      # 多币种合并报表（汇率折算）
└── FinancialRatioService.php     # 财务比率自动计算

app/controller/finance/
├── PeriodCloseController.php     # 期末结转操作
├── AccountBalanceController.php  # 科目余额查询
└── FinancialRatioController.php  # 比率分析查询
```

**Kernregeln**:
- Beim Speichern von Belegen wird „Jede Buchung hat Soll und Haben, Soll und Haben müssen gleich sein" erzwungen
- Freigegebene Belege sind nicht änderbar, Rückbuchung per Rotstorno erforderlich
- Periodenabschluss: Salden der Ertrags-/Aufwandskonten → Jahresgewinn, Mehrschritt-Umbuchung unterstützt
- Multi-Währung: Umrechnung zum Stichtagskurs, automatische Berechnung von Wechselkursdifferenzen

### 4.2 Gehaltsberechnungs-Engine (Industriestandard)

```
app/service/hr/
├── SalaryEngineService.php       # 薪资计算主引擎
├── SocialInsuranceService.php    # 社保计算（养老/医疗/失业/工伤/生育）
├── HousingFundService.php        # 公积金计算
├── TaxCalculatorService.php      # 个税累进税率计算
└── BankPayrollService.php        # 银行代发文件导出

app/controller/hr/
└── PayrollController.php         # 薪资计算/发放/查询
```

**Kernregeln**:
- Sozialversicherungs-Bemessungsgrenzen (jährliche Anpassung je Stadt, konfigurierbar)
- Housing-Fund-Bemessungsgrundlage + Beitragssatz (5%-12 %, konfigurierbar)
- Progressive Einkommensteuertabelle (3%-45 %, jährliche Veranlagung)
- Bank-Auszahlungsformat: unterstützt ICBC/BOC/CCB/CMB und andere große Banken
- Gehaltsabrechnungserzeugung (mit allen Detailpositionen)

### 4.3 MRP-Engine (Industriestandard)

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP 运算主引擎
├── DemandForecastService.php      # 需求汇总（订单+预测+安全库存）
├── NetRequirementService.php      # 净需求计算（毛需求-在库-在途）
├── BomExplosionService.php        # BOM 展开（逐层展开到原材料）
└── OrderSuggestionService.php     # 建议订单生成（采购/生产/外协）

app/model/
├── MfgMrpRunLog.php              # MRP 运算日志
└── MfgOrderSuggestion.php        # 建议订单
```

**Kernregeln**:
- BOM-Ebene-für-Ebene-Auflösung unter Berücksichtigung der Ausschussquote
- Nettobedarf = Bruttobedarf - vorhandener Bestand - Bestand in Transit + bereits zugeordnete Menge + Sicherheitsbestand
- Low-Level-Code (LLC) stellt sicher, dass dasselbe Material nur einmal berechnet wird
- Rückwärtsrechnung des empfohlenen Bestelldatums anhand der Vorlaufzeit
- Losgrößenregeln: feste Losgröße/wirtschaftliche Losgröße/bedarfsweise

### 4.4 Qualitätsmanagement (kernfunktional)

```
app/controller/quality/
├── InspectionStandardController.php  # 检验标准
├── IncomingCheckController.php       # IQC 来料检验
├── ProcessCheckController.php        # IPQC 过程检验
├── FinalCheckController.php          # OQC 出货检验
└── NonconformityController.php       # 不合格品处理

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 Echtzeit-Benachrichtigungssystem (kernfunktional)

```
app/service/notification/
├── WebSocketService.php           # WebSocket 连接管理 + 推送
├── ChannelRouter.php              # 多渠道路由（站内/邮件/企微/钉钉）
├── TemplateRenderer.php           # 通知模板渲染

app/process/
└── WebSocket.php                  # WebSocket 进程

app/controller/notification/
├── WebSocketController.php        # WebSocket 事件处理
└── ChannelConfigController.php    # 通知渠道配置
```

**Kernregeln**:
- WebSocket basiert auf dem nativen workerman-Protokoll
- Benachrichtigungsvorlagen: Variablen-Platzhalter `{order_code}` werden zur Laufzeit ersetzt
- Kanalpriorität: In-App → E-Mail → WeCom → DingTalk, konfigurierbar

### 4.6 P1-Abnahmekriterien

- [ ] Sind Soll und Haben beim Speichern eines Belegs ungleich → Fehler zurückgeben
- [ ] Ergebnisse der Gehalts-Engine stimmen mit manueller Berechnung überein (Stichprobe: Monatsgehälter von 10 Personen)
- [ ] MRP-Nettobedarfsberechnung stimmt mit manueller Excel-Berechnung überein
- [ ] Vollständiger Durchlauf der drei Qualitätsprüfungen (IQC/IPQC/OQC)
- [ ] WebSocket-Benachrichtigungslatenz < 2 Sekunden
- [ ] Alle neuen Services haben PHPUnit-Testabdeckung (Kernalgorithmen ≥ 95 %)

---

## 5. P2 — Betriebszuverlässigkeit (1-2 Wochen)

> **Ziel**: Produktionsreife Betriebsfähigkeit

### 5.1 Datenbank-Migrations-Rollback

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

Für jede Migrationsdatei wird eine zugehörige `_rollback.sql`-Datei hinzugefügt.

### 5.2 Backup-Wiederherstellungs-Verbesserung

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 Observability

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- Trace-ID auf Anfrageebene (über den Antwort-Header `X-Trace-Id` durchgereicht)
- Kern-Geschäftskennzahlen: Auftragsvolumen, Erfüllungsquote, Lagerumschlagstage

### 5.4 Message-Queue-Upgrade

Bestehende Redis-Warteschlange → Unterstützung von RabbitMQ als optionalen Treiber:

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 P2-Abnahmekriterien

- [ ] Migrations-Rollback-Skript ausführbar und Datenintegritätsprüfung bestanden
- [ ] Automatisches Backup-Cron wird korrekt ausgelöst
- [ ] Trace-ID durchzieht die gesamte Anforderungskette
- [ ] RabbitMQ-Treiber umschaltbar, keine Nachrichtenverluste

---

## 6. P3 — Erlebnisverbesserung (2-3 Wochen)

> **Ziel**: Erweiterte Funktionen und bessere Benutzererfahrung

### 6.1 BI-Daten-Dashboards

```
app/controller/bi/
├── DashboardController.php       # 可配置仪表盘
├── WidgetController.php          # 图表小组件 CRUD
└── DatasetController.php         # 数据集管理

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- Dashboard mit Drag-and-Drop-Layout
- Widgets: Balkendiagramm/Liniendiagramm/Kreisdiagramm/Datenkarten/Tabellen
- Wiederverwendung des Datensatzmechanismus aus `app/controller/report/`

### 6.2 Anlagenverwaltung (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 Multi-Mandant

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- Gemeinsame Datenbank + `tenant_id`-Isolation
- Mandantenübergreifende Ansicht für Superadministratoren

### 6.4 Dokumentenverwaltung (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 P3-Abnahmekriterien

- [ ] BI-Dashboard mit anpassbarem Drag-and-Drop-Layout
- [ ] Geschlossener Kreislauf Anlagenstamm → Wartungsplan → Reparaturauftrag
- [ ] Mandant A kann nicht auf Daten von Mandant B zugreifen
- [ ] Dokumentversionsverlauf nachvollziehbar

---

## 7. Zusammenfassung der Datenmodelländerungen

### Neue Tabellen in P0

Keine neuen Tabellen, das Frontend-Ökosystem beinhaltet keine Backend-Tabellenstrukturänderungen.

### Neue Tabellen in P1

| Tabellenname | Verwendungszweck | Phase |
|------|------|------|
| `erp_finance_period_close` | Periodenabschluss-Protokoll | P1 |
| `erp_finance_account_balance` | Kontosaldo-Snapshot | P1 |
| `erp_hr_salary_config` | Gehaltsberechnungskonfiguration | P1 |
| `erp_hr_social_insurance_config` | Sozialversicherungs-Bemessungsgrundlagenkonfiguration | P1 |
| `erp_hr_housing_fund_config` | Housing-Fund-Konfiguration | P1 |
| `erp_mfg_mrp_run_log` | MRP-Berechnungsprotokoll | P1 |
| `erp_mfg_order_suggestion` | Empfohlene Bestellungen | P1 |
| `erp_quality_inspection_standard` | Prüfstandard | P1 |
| `erp_quality_iqc_record` | IQC-Eingangsprüfung | P1 |
| `erp_quality_ipqc_record` | IPQC-Prozessprüfung | P1 |
| `erp_quality_oqc_record` | OQC-Ausgangsprüfung | P1 |
| `erp_quality_nonconformity` | Nichtkonforme Produkte | P1 |
| `erp_notification_channel_config` | Benachrichtigungskanal-Konfiguration | P1 |
| `erp_notification_template` | Benachrichtigungsvorlagen | P1 |

### Neue Tabellen in P3

| Tabellenname | Verwendungszweck | Phase |
|------|------|------|
| `erp_bi_dashboard` | BI-Dashboard | P3 |
| `erp_bi_widget` | BI-Widgets | P3 |
| `erp_eam_equipment` | Anlagenstamm | P3 |
| `erp_eam_maintenance_plan` | Wartungspläne | P3 |
| `erp_eam_repair_order` | Reparaturaufträge | P3 |
| `erp_dms_document` | Kontrollierte Dokumente | P3 |
| `erp_dms_document_version` | Dokumentversionen | P3 |

---

## 8. Zusammenfassung der Service-Ebenen-Änderungen

| Service | Aktuell | P1-Änderungen | P2-Änderungen | P3-Änderungen |
|------|------|---------|---------|---------|
| FinanceService | CRUD | Neu: DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Gehalt | Keine | Neu: SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Fertigung | CRUD | Neu: MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Qualität | Keine | Neu: QmsInspectionService | — | — |
| Benachrichtigung | Basis | Neu: WebSocketService, ChannelRouter | — | — |
| Observability | Monitor-Prozess | — | Neu: TracerService, MetricCollector | — |
| BI | Keine | — | — | Neu: BiDashboardService |
| Anlagen | Keine | — | — | Neu: EamService |

---

## 9. Änderungen der Middleware-Kette

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. Meilensteine und Liefergegenstände

| Meilenstein | Zeitpunkt | Liefergegenstand |
|--------|------|--------|
| M0 — aktuelle Ausgangsbasis | 2026-08-04 | Prüfbericht `audit-report-2026-08-04.md` |
| M1 — P0 abgeschlossen | +3 Wochen | Flutter-Web-Verwaltungspanel für alle Module |
| M2 — P1 abgeschlossen | +8 Wochen | Finanz-Engine + Gehalts-Engine + MRP-Engine + Qualität + Benachrichtigung |
| M3 — P2 abgeschlossen | +10 Wochen | Migrations-Rollback + automatisches Backup + Trace + Warteschlangen-Upgrade |
| M4 — P3 abgeschlossen | +13 Wochen | BI-Dashboards + Anlagenverwaltung + Multi-Mandant + Dokumentenverwaltung |

---

## 11. Risiken und Gegenmaßnahmen

| Risiko | Auswirkung | Gegenmaßnahmen |
|------|------|----------|
| Flutter-Web-Performance unterhalb von nativem JS | Ruckeln bei großen Datentabellen | Clientseitige Paginierung + virtuelles Scrollen + Web Worker |
| Änderungen der Rechtsvorschriften für die Gehalts-Engine | Berechnungsergebnisse nicht konform | Sozialversicherung/Steuersätze konfigurierbar, nicht hartkodiert |
| Zeitüberschreitung bei MRP-Berechnung mit großen Datenmengen | Berechnung abgebrochen | Stapelverarbeitung + Fortschritts-Callback |
| Zu viele WebSocket-Langverbindungen | Speicherdruck auf dem Server | workerman von Natur aus hoch parallel + Verbindungslimit |
| Lücken bei der Multi-Mandanten-Datenisolation | Datenleak | TenantScope als globale Middleware + Testabdeckung |

---

## 12. Nicht durchgeführte Dinge (ausdrücklich ausgeschlossen)

- ❌ Keine Einführung der Microservice-Aufteilung — die aktuelle monolithische Architektur reicht aus, komplexe Logik wird in der Service-Ebene gebündelt
- ❌ Kein Kubernetes — Docker Compose erfüllt die aktuelle Größenordnung
- ❌ Keine AI/ML-Funktionen — nicht in der MVP-Roadmap
- ❌ Keine separaten nativen iOS/Android-Apps — Flutter-Plattformübergreifend bereits abgedeckt
- ❌ Kein GraphQL — RESTful-API reicht aus, API-Versionsstrategie ist ausgereift
- ❌ Keine elektronische Signatur/WMS-Hardwareintegration (PDA/Scanner) — rein auf Softwareebene
