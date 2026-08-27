# Open-Admin (open-admin)

Full-Stack-Administrationssystem auf Basis von webman v2 + Flutter.

![Oktopus-Maskottchen](images/mascot.svg)

## Copyright-Hinweis

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Unveränderlich, nicht entfernbar, irreversibel.** Alle neuen Dateien müssen den obigen Copyright-Hinweis als Dateikopf-Kommentar enthalten.

## Ökosystem-Roadmap

> Designspezifikation: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> Architekturdokument: `ARCHITECTURE.md` §21
> Funktionsmatrix: `FUNCTIONS.md` §19

**Aktuelle Gesamtbewertung 89/100** — vollständige Roadmap P0~P3 abgeschlossen, 22 Module Full-Stack abgedeckt, produktionsreif.

| Phase | Dauer | Lieferumfang | Status |
|------|------|--------|------|
| 🔵 **P0** Frontend-Ökosystem | 3-4 Wochen | 97 Flutter-Seiten + 34 HarmonyOS-Seiten + 4 Allzweck-Komponenten | ✅ |
| 🟢 **P1** Business-Tiefe | 4-6 Wochen | Finanz-Engine + Gehalts-Engine + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Betriebszuverlässigkeit | 1-2 Wochen | Migrations-Rollback + automatisches Backup + TraceId + Warteschlangen-Dual-Treiber | ✅ |
| 🟣 **P3** Erlebnisverbesserung | 2-3 Wochen | BI-Dashboards + EAM + Multi-Mandant + DMS + 7 neue Tabellen | ✅ |

**Tests**: 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## Funktionsliste

| Domäne | Funktionen |
|----|------|
| Authentifizierung | Login/Registrierung/Refresh/Logout + Captcha + Kontosperrung + Sitzungsbegrenzung |
| Dashboards | Geschäftsübersicht/Vertriebs-Dashboard/Bestands-Dashboard/Finanz-Dashboard (Redis-5m-Cache) |
| Benutzer | CRUD + Batch-Löschen/Aktivieren-Deaktivieren + Excel-Import |
| Rollen & Berechtigungen | CRUD + Berechtigungsbaum + RBAC-method.path-Autorisierung |
| Systemkonfiguration | Schlüssel-Wert-CRUD |
| Betriebsprüfung | Protokollabfrage + automatische Erkennung von 8 Plattform-Quellgeräten |
| Dateien | Upload + Excel/PDF-Export (Maskierung sensibler Daten) |
| Sicherheit | 18 Ebenen Tiefenverteidigung (XSS/SQL-Injection/CSRF/Rate-Limit/CSP...) |
| Betrieb | Health-Check/Prometheus-Metriken/API-Dokumentation/security.txt + Docker + CI/CD |
| Artikelverwaltung | Artikel/SKU/Kategorien/Marken/Lager/Lagerplätze/Lieferanten/Kunden |
| Einkaufsverwaltung | Anfrage→Bestellung→Wareneingang→Retoure→Abrechnung (automatische Einlagerung + Erzeugung von Verbindlichkeiten) |
| Vertriebsverwaltung | Angebot→Auftrag→Versand→Retoure→Abrechnung (automatischer Warenausgang + Erzeugung von Forderungen) |
| Bestandsverwaltung | Echtzeitbestand/Buchungen/Chargen/Umlagerung/Inventur/Warnungen (bewegte Durchschnittskosten) |
| Finanzverwaltung | Forderungen/Verbindlichkeiten/Belege/Zahlungen/Tagebuch/Hauptbuch/Detailbuch/Drei Abschlüsse/Anlagevermögen/Steuern/Multi-Währung/Budget |
| CRM | Chancen/Follow-ups/Funnel/Kontakte/Public-Pool/Verträge/Angebote/Marketing/Tickets/Analysen |
| Genehmigungsworkflow | Workflow-Definition/Einreichen/Genehmigen/Ablehnen/Zurückziehen/Meine Genehmigungen |
| Nachrichten | Benachrichtigungsliste/Gelesen/Alle gelesen/Anzahl ungelesen |
| Projektmanagement | Projekte/Aufgaben/Zeiterfassung |
| Personalwesen | Abteilungen/Mitarbeiter/Positionen/Anwesenheit/Urlaub/Gehälter |
| Produktion und Fertigung | BOM/Produktionsaufträge/Arbeitspläne/Workstations/MRP |
| Benutzerdefinierte Berichte | Berichtsvorlagen/Datensätze/Felder/Filter/Ausführung/Zeitplanung |
| OMS Auftragsverwaltung | Mehrkanal-Aufträge/Erfüllungs-Orchestrierung/Bestandsreservierung (ATP)/RMA-Umtausch-Retoure/Kanalverwaltung |
| WMS Lagerverwaltung | Zonen und Lagerplätze (Hierarchie + Barcode)/Wareneingang (ASN→Empfang→Einlagerung)/Warenausgang (Wellen→Kommissionierung→Packen) |
| TMS Transportverwaltung | Spediteure/Frachtkostenvergleich/Frachtbrief-Formulare/Sendungsverfolgung (webhook) |
| QMS Qualitätsmanagement | IQC-Eingangsprüfung/IPQC-Prozessprüfung/OQC-Ausgangsprüfung + Prüfstandards + Behandlung fehlerhafter Produkte |
| EAM Anlagenverwaltung | Anlagenstamm/Wartungspläne/Reparaturaufträge/Ersatzteilverwaltung |
| DMS Dokumentenverwaltung | Dokumentkategorien/Dokumente/Versionen |
| BI-Dashboards | Dashboard-Layouts/Diagrammkomponenten |

## Technologiestack

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Datenbank: MySQL 8.0+, Tabellenpräfix `erp_`
- Primärschlüssel: BIGINT nicht auto-increment, von `erikwang2013/snowflake-php` erzeugt
- API-Ebene-ID-Verschlüsselung/-Entschlüsselung: `erikwang2013/hashids`
- JWT-Authentifizierung: `erikwang2013/jwt-webman`
- API-sensitive-Daten-Verschlüsselung: `erikwang2013/encryption`
- Verschlüsselung sensibler Datenbankfelder: `erikwang2013/encryptable`
- ES-Synchronisation und -Abfrage: `erikwang2013/webman-scout`
- Länderflaggen: `erikwang2013/season`
- API-Dokumentgenerierung: `hg/apidoc` | Annotationsbasiert, Zugriff über /apidoc

### Frontend
- Flutter 3.x, Quellverzeichnis `apps/flutter/`
- Web-Ende im PC-Verwaltungsstil gestaltet (nicht Mobile-App-Stil)
- Unterstützt Client- und Admin-Bereich
- HarmonyOS ArkTS, Quellverzeichnis `apps/harmonyos/`

## Projektstruktur

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## Middleware-Ausführungskette

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## Sicherheitsverstärkung

- **HTTP-Methodeneinschränkung**: SecurityFilter erlaubt nur GET/POST/PUT/DELETE/OPTIONS/HEAD, nicht standardmäßige Methoden liefern 405
- **CSP-Header**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies werden in alle Antworten injiziert
- **Kontosperrung**: nach 5 aufeinanderfolgenden fehlgeschlagenen Logins wird das Konto 15 Minuten gesperrt
- **Begrenzung paralleler Sitzungen**: maximal 3 gültige Tokens pro Benutzer, bei Überschreitung wird das älteste Token in die Blacklist aufgenommen
- **security.txt**: `/.well-known/security.txt` RFC-9116-Endpunkt
- **Nginx-Sicherheitskonfiguration**: `nginx-security.conf` als Referenz zur Sicherheitshärtung des Reverse-Proxys

## API-Versionsstrategie

Die Version wird über den Request-Header `API-Version` gesteuert (Standard `v1`), nicht in der URL abgebildet:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Eine neue Version erfordert nur die Erstellung des Verzeichnisses `app/api/{version}/controller/` und die Registrierung in der `ApiVersion`-Middleware.

## Rate-Limit-Strategie

Redis-Sliding-Window (Lua-Atomar), Standard 60 Mal/Minute/IP/Route:
- Login: 10 Mal/Minute
- Registrierung: 5 Mal/Minute
- Antwort-Header: `X-RateLimit-Limit/Remaining/Reset`, bei Überschreitung zusätzlich `Retry-After`

## Code-Konventionen

### PHP
- Globale Funktionen/Klassenreferenzen ohne vorangestelltes `\`, Import per `use`
- Konfigurationsdateien müssen chinesische Kommentare enthalten, die die Bedeutung jedes Konfigurationseintrags erklären
- Alle neuen `.php`-Dateien müssen den Copyright-Hinweis im Dateikopf enthalten

### Datenbank
- Tabellenpräfix: `erp_`
- Primärschlüssel `id`: BIGINT-Typ, nicht auto-increment, von snowflake erzeugt
- Sensible Felder nutzen das `erikwang2013/encryptable`-trait für automatische Ver-/Entschlüsselung
- schema basiert auf database/install.sql als einziger Tatsachenquelle (SQL-Einzeldatei)

### Flutter
- Web-Endlayout im PC-Verwaltungsstil (Sidebar + Topbar + Inhaltsbereich)
- GetX-State-Management, `ApiService`-Singleton (Dio + JWT-Interceptor)
- Token-Persistenz über `shared_preferences`
- Responsive-Breakpoints: Mobil (< 768px) und Desktop (>= 768px)

### HarmonyOS
- Nativer HTTP-Client `@ohos.net.http`
- Nahtlose Token-Erneuerung: bei 401 automatischer Aufruf von `/api/auth/refresh`
- Bei fehlgeschlagener Erneuerung automatische Weiterleitung zur Login-Seite

## Deployment

### Docker Compose (empfohlen für Produktion)

Die `docker-compose.yml` im Projektstamm orchestriert 5 Dienste:

| Dienst | Beschreibung |
|------|------|
| `nginx` | Nginx-Reverse-Proxy (80/443), statischer Dateidienst |
| `app` | webman-PHP-8.3-Anwendung, `Dockerfile`-Build (inkl. OPcache + event + redis) |
| `mysql` | MySQL 8.0, Datenvolumen-Persistenz |
| `redis` | Redis 7 Alpine, Cache/Rate-Limit/Session |
| `elasticsearch` | Elasticsearch 8.x, Volltextsuche |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` definiert die GitHub-Actions-Pipeline (PHP-8.2/8.3/8.4-Matrix):

- PHP-Syntaxprüfung (`php -l`)
- PHPStan-Statische Analyse (`vendor/bin/phpstan analyse`)
- PHP-CS-Fixer-Code-Stil-Prüfung (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit-Unit-Tests
- Composer-Sicherheitsaudit (`composer audit --no-dev`)

### Datenbank-Backup

`database/backup/backup.sh` — mysqldump + gzip, löscht automatisch Backups älter als 30 Tage.
`database/backup/restore.sh` — interaktive Wiederherstellung, listet verfügbare Backups zur Auswahl auf.

### Monitoring

Der Endpunkt `GET /metrics` (`MetricsController`) gibt das Prometheus-Textformat aus und enthält 5 gauge-Metriken:
- `openadmin_http_requests_total` — Anzahl der Requests insgesamt
- `openadmin_active_users` — Anzahl aktiver Benutzer
- `openadmin_db_connection_status` — Datenbankverbindungsstatus (0/1)
- `openadmin_redis_connection_status` — Redis-Verbindungsstatus (0/1)
- `openadmin_memory_usage_bytes` — Speichernutzung
