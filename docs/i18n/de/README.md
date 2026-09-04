# Open-ERP-System (open-erp)

Vollständiges ERP-System auf Basis von webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="open-erp Maskottchen Oktopus" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | Deutsch | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Versionsvergleich](EDITIONS.md) | [Architekturdiagramme](ARCHITECTURE.md) | [Systemarchitektur](#systemarchitektur) | [Designdokument](DESIGN.md) | [Sicherheitsarchitektur](SECURITY.md) | [API-Referenz](API.md) | [Funktionshandbuch](FUNCTIONS.md)

## Funktionsübersicht

| Geschäftsbereich | Funktion | Beschreibung |
|--------|------|------|
| 🔐 Authentifizierung | Login/Registrierung/Token-Refresh/Logout | Klick-Captcha + JWT + Blacklist |
| | Kontosperrung | 5 Fehlversuche sperren für 15 Minuten |
| | Begrenzung paralleler Sitzungen | Maximal 3 gültige Token pro Benutzer |
| 📊 Dashboard | Geschäftsübersicht/Vertriebs-/Bestands-/Finanz-Dashboard | 30-Tage-Umsatztrend/Top5-Verkaufsschlager/Auftragsstatus-Verteilung/Forderungs-Verbindlichkeiten-Fälligkeiten + Redis-Cache 5 Minuten |
| 👥 Benutzerverwaltung | CRUD + Massenlöschung/Aktivieren-Deaktivieren | Soft Delete + Passwort-Bestätigung |
| | Excel-Massenimport | Zeilenweise Validierung + Fehlerbericht |
| 🔒 Rollen & Berechtigungen | Rollen-CRUD + Berechtigungsbaum | RBAC-Autorisierung auf method.path-Ebene |
| ⚙ Systemkonfiguration | Schlüssel-Wert-CRUD | Gruppenverwaltung |
| 📋 Prüfprotokoll | Protokollabfrage + Quellgerät-Erkennung | Automatische Erkennung von 8 Plattformen |
| 📁 Dateiverwaltung | Upload/Excel-Export/PDF-Export | Automatische Maskierung sensibler Daten |
| 🛡 Sicherheit | 18 Ebenen Verteidigung in der Tiefe | XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF/Rate-Limit/CSP... |
| 🏥 Betrieb | Health-Check/metrics/API-Dokumentation/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Artikelverwaltung | Artikelstamm/SKU/Multi-Spezifikation/Multi-Einheit/Kategorien/Marken/Preisstrategien | Mehrstufiger Kategorienbaum + Einheitenumrechnung |
| | Lager & Lagerplätze | Verwaltung mehrerer Lager und Lagerplätze |
| | Lieferanten-/Kundenstamm | Ansprechpartner/Bankkonten/Kreditlimits |
| 📥 Einkaufsverwaltung | Anfrage→Bestellung→Wareneingang→Retoure→Abrechnung | Vollständiger Einkaufsprozess + Genehmigung |
| 📤 Vertriebsverwaltung | Angebot→Auftrag→Versand→Retoure→Abrechnung | Angebot-zu-Auftrag + Verkaufsrohertrag |
| | Kundenkreditkontrolle | Limit/Zahlungsziel/Sperre verwalten + Überschreitungsblockade bei Auftrag und Versand |
| 🏗 Bestandsverwaltung | Echtzeitbestand/Chargen/Seriennummern/Umlagerung/Inventur/Warnungen | Gleitende Durchschnittskostenrechnung |
| 💰 Finanzverwaltung |
| | Multi-Organisation + konsolidierte Berichte | Mehrere Unternehmen/Buchungskreise + Eliminierungsbuchungen (Equity-/Anschaffungskostenmethode) |
| | Bestands-/Produktionskostenrechnung | Fertigungsentnahme → Arbeits-/Gemeinkostensammlung → Herstellkosten → Kostenabweichungsübertrag |
| | Wechsel + Bankabstimmung | Wechselregister + automatische Abstimmung per Bankkontoauszug-Import |
| | Eingangsrechnungspool + E-Rechnung | Eingangsrechnungsverwaltung + Ausgangskanal (Adapter + Mock-Kanal) | Forderungen/Verbindlichkeiten/Ein-/Ausgänge/Tagebuch/Spesen/GuV/Anlagevermögen/Steuern/Multi-Währung/Budget/Kosten- und Profit-Center | Automatische Forderungen/Verbindlichkeiten + Verrechnung + umfassende Finanzverwaltung |
| 🤝 CRM | Kunden/Kontakte/Follow-up-Records/Marketingkampagnen/Service-Tickets/Analyseberichte/Sales-Funnel/Shared-Pool/Angebote/Verträge | Verwaltung des gesamten Kundenlebenszyklus |
| | Kundenwert-Engine | Guthaben-/Punkte-/Kartenprogramm-Mitgliedschaft |
| ✅ Genehmigungsworkflow | Workflow-Definition/Einreichung/Genehmigen/Ablehnen/Zurückziehen/Meine Genehmigungen | Mehrstufige Genehmigungs-Engine |
| | Visueller Workflow-Designer | Knoten/Verzweigungen/Rücksprung-Kanten auf der Leinwand, nutzt Genehmigungs-Engine |
| 🔔 Benachrichtigungen | Benachrichtigungsliste/Gelesen-Markierung/Ungelesen-Zähler/Alles gelesen | Echtzeit-Push und Statusverfolgung |
| | Multikanal-Benachrichtigungen | SMS/E-Mail-Kanaltreiber (Mock-Kanal + Logging + Retries) |
| 📐 Projektmanagement | Projekte/Aufgaben/Zeiterfassung | Projektfortschritt & Ressourcenverwaltung |
| | Projektkosten & Budget | Stunden × Satz → Projektkostenerfassung + Budgetabweichung |
| 👤 Personalwesen | Abteilungen/Mitarbeiter/Positionen/Anwesenheit/Urlaub/Gehälter | Umfassende Personalverwaltung |
| | Rekrutierung/Leistung/Schulung/Sozialversicherung | Recruiting-Trichter + KPI/360-Bewertung + Kurs-Credits + Beitragsbemessungsregeln & Gehaltsabrechnung |
| 🏭 Produktion | BOM/Produktionsaufträge/Arbeitspläne/Arbeitsplätze/MRP | Materialbedarfsplanung und Produktionsausführung |
| | Arbeitsrückmeldung/Akkordlohn/Subunternehmer-Verrechnung | MES-Arbeitsschrittausführung + Materialausgabe und Verrechnung bei Fremdfertigungsaufträgen |
| | Kapazitätslast-Analyse | Arbeitsplatzkalender + Grobkapazitätsbericht |
| | Chargen-/Seriennummern-Rückverfolgung | Vorwärts-/Rückwärts-Rückverfolgungskette + Ablaufwarnung |
| 📈 Benutzerdefinierte Berichte | Berichtsvorlagen/Datensätze/Felder/Filter/Ausführung/Zeitplanung | Visueller Berichts-Builder |
| 📋 Auftragsverwaltung (OMS) | Multi-Kanal-Aufträge/Fulfillment-Orchestrierung/Reservierung/Zuordnung/Stornierung/RMA-Retouren | Verwaltung des gesamten Auftragslebenszyklus |
| 🏗 Lagerverwaltung (WMS) | Zonen/Lagerplätze/ASN/Wareneingang/Einlagerung/Wellen/Kommissionierung/Verpackung/Versand | Vollständiger Lagerablauf |
| 🚚 Transportverwaltung (TMS) | Spediteure/Dienste/Tarife/Frachtscheine/Tracking/Frachtrechnungen | Multi-Spediteur-Tarifvergleich + Sendungsverfolgung |
| 🛠 Anlagenverwaltung (EAM) | Anlagenstamm/Wartungspläne/Reparaturaufträge/Ersatzteile | Verwaltung des gesamten Anlagenlebenszyklus |
| | QR-Prüf-Regelkreis | Scan-Prüfung, Störungen lösen automatisch Reparaturauftrag aus |
| 🌐 Plattform & Offenheit | API-Pfadversionierung | Admin /admin/v1, Client /api/v1, Open /open/v1 (kein Versions-Header) |
| | Druckvorlagen-Engine | Platzhalter-Rendering + dompdf PDF + QR-Code-Etiketten |
| | Benutzerdefinierte Formularfelder | Mastertabellen-Erweiterung per custom_fields JSON + Validierung |
| | Multi-Tenant-Architektur | erp_tenant-Mandant + TenantScope-Anforderungskontext + Ablauf-Abrechnung (Middleware-Seam reserviert, nicht registriert) |

## ERP-Module

Datenfluss zwischen den Geschäftsmodulen:

- Einkauf-Wareneingang → automatische Einlagerung (gleitende Durchschnittskosten) → automatische Verbindlichkeiten
- Vertriebs-Versand → automatische Auslagerung → automatische Forderungen
- Ein-/Ausgänge → Verrechnung von Forderungen/Verbindlichkeiten → Aktualisierung des Tagebuchs
- Belegprüfung → automatische Aktualisierung des Hauptbuchs (Kontenzusammenfassung) + Detailbuch (Einzelposten)
- Bilanz → automatisch aus den Salden des Hauptbuchs zum Periodenende erzeugt
- Kapitalflussrechnung → automatisch aus den Kassen- und Banktagebüchern erzeugt (Betrieb/Investition/Finanzierung)
- Genehmigungsworkflow → Geschäftsbelege zur Genehmigung → Mehrstufenfluss → Ergebnisrückmeldung an die Fachmodule
- Benachrichtigungen → ausgelöst durch Genehmigung/Warnungen/Systemereignisse → Echtzeit-Push → Benutzer markiert als gelesen
- MRP → basierend auf Verkaufsaufträgen + BOM → Berechnung des Materialbedarfs → Erzeugung von Einkaufs-/Produktionsvorschlägen
- OMS → Import von Multi-Kanal-Aufträgen → Bestandsreservierung (ATP) → Fulfillment erstellen → WMS-Kommissionierung/Verpackung anstoßen
- WMS → Wellen-Aggregation → Kommissionieraufgaben → Kommissionierbestätigung → Verpackung abgeschlossen → TMS-Frachtschein erzeugen
- TMS → Frachttarifvergleich → Frachtschein erstellen → Versand bestätigen (stockOut+AR) → Sendungsverfolgung → Zustellung
- WMS-Eingang → ASN-Vormeldung → Wareneingang → Qualitätsprüfung → Einlagerungsbestätigung (stockIn+AP) → Bestandsaktualisierung
- RMA → Retoure-Antrag → Genehmigung → Retoure-Einlagerung → Erstattung

## Technologie-Stack

| Ebene | Technologie | Beschreibung |
|---|------|------|
| Backend-Framework | webman v2 (workerman) | Hochleistungs-PHP-Framework mit dauerhaft laufenden Prozessen |
| PHP-Version | 8.3+ | |
| Datenbank | MySQL 8.0+ | Tabellenpräfix `erp_`, BIGINT-Primärschlüssel ohne AUTO_INCREMENT |
| Suchmaschine | Elasticsearch | Synchronisierung und Abfrage über `webman-scout` |
| Admin-Frontend | Flutter 3.x | Web-Version im PC-Admin-Stil (`apps/flutter/`) |
| Mobile Clients | HarmonyOS ArkTS | Natives HarmonyOS-Client (`apps/harmonyos/`), unterstützt Smartphone/Tablet/2in1 |

## Kernabhängigkeiten

| Paket | Zweck |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake-Algorithmus für global eindeutige BIGINT-Primärschlüssel |
| `erikwang2013/hashids` | ID-Verschlüsselung/-Entschlüsselung in der API-Schicht, verbirgt echte Datenbank-IDs |
| `erikwang2013/jwt-webman` | JWT-Authentifizierung: Token-Ausstellung und -Prüfung |
| `erikwang2013/encryption` | Verschlüsselung sensibler Daten in der Übertragungsschicht |
| `erikwang2013/encryptable` | Automatische Ver-/Entschlüsselung sensibler Felder in der Speicherschicht |
| `erikwang2013/webman-scout` | Elasticsearch-Datensynchronisierung und Volltextsuche |
| `erikwang2013/season` | Nationalflaggen-Daten |
| `erikwang2013/poster-php` | Klick-Captcha-Erzeugung/-Prüfung + Poster-Generierung |
| `erikwang2013/security-php` | Sicherheitsprüfungen |
| `phpoffice/phpspreadsheet` | Excel-Export |
| `barryvdh/laravel-dompdf` | PDF-Export (basiert auf Dompdf) |
| `hg/apidoc` | Automatische API-Dokumentation | Annotationsbasierte Schnittstellendoku, gruppiert nach Admin/Client |

## Internationalisierung

Internationalisierung | Automatische Erkennung über den Accept-Language-Header | Zweisprachig Chinesisch/English

## Projektstruktur

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   ├── api/v1/controller/      # 客户端 API（版本由 API-Version 请求头控制）
│   ├── controller/             # 业务模块控制器 (88 个)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (7 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算 (5 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (5 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心 (20 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批提交/批准/拒绝/撤回 (2 个)
│   │   ├── notification/       # 通知列表/已读/未读计数 (1 个)
│   │   ├── project/            # 项目/任务/工时记录 (3 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资 (5 个)
│   │   ├── manufacturing/      # BOM/生产订单/工艺路线/工作站/MRP (5 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   └── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   ├── service/                # 业务逻辑层
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 161 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 12 个中间件
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/hg/apidoc/        # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（20 个测试文件，137 个测试方法，805 条断言）
├── resource/
│   └── translations/           # 翻译文件 (zh_CN, en)
│       ├── zh_CN/              # 中文翻译 (127 键)
│       └── en/                 # English translations (127 keys)
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
└── vendor/                     # Composer 依赖
```

## Systemarchitektur

> Klicken Sie auf die Bilder, um die SVG-Originale anzuzeigen. Die Diagramme verwenden englische Bezeichnungen und zeigen die Architektur aller Systemebenen vollständig und klar.

### System-Topologie

![System Architecture](./diagrams/system-architecture-cn.svg)

**Fünf-Schichten-Architektur**: Client-Schicht → Gateway-/Edge-Schicht (Nginx-Reverse-Proxy) → Anwendungsschicht (webman v2 + Middleware-Kette + Authentifizierung/Autorisierung + Geschäftslogik + gemeinsame Dienste) → Datenspeicherschicht (MySQL + Redis + Elasticsearch) → Betriebsschicht (CI/CD + Docker + Prometheus)

### Geschäftsdatenfluss

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**Verzahnung der sieben Geschäftsbereiche**: Einkauf → Bestand → Vertrieb → Finanzen bilden den Kern der Lieferketten-Schleife; CRM treibt den Vertrieb; die Produktion (MRP) steuert Einkaufs- und Produktionsplanung auf Basis von Verkaufsaufträgen und Stücklisten; Genehmigungsworkflow, Benachrichtigungen, Projektmanagement und Personalwesen begleiten als unterstützende Module den gesamten Prozess.

### Modulübersicht

![Functional Modules](./diagrams/functional-modules-cn.svg)

**19 Geschäftsbereiche, 163 Datentabellen, 121 Controller**: Authentifizierung/Sicherheit, Dashboard, Systemverwaltung, Schutz, Betriebsmonitoring, Artikelverwaltung, Einkauf, Vertrieb, Bestand, Finanzen (14 Untermodule), CRM (10 Untermodule), Genehmigungsworkflow, Benachrichtigungen, Projektmanagement, Personalwesen, Produktion (MRP), benutzerdefinierte Berichte, Auftragsverwaltung (OMS), Lagerverwaltung (WMS), Transportverwaltung (TMS), Qualitätsmanagement (QMS), Anlagenverwaltung (EAM), Dokumentenverwaltung (DMS), BI-Dashboards.

### Anfrage-Lebenszyklus

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Kompletter Anforderungspfad vom Client zur Datenbank**: Client (Flutter/HarmonyOS) → Nginx-SSL-Terminierung → Sprachdetektion → CORS → Sicherheitsfilter → Rate-Limit → API-Versionsprüfung → [Admin: JWT-Authentifizierung → RBAC-Berechtigungen → Betriebsprotokoll] → Controller → Service-Schicht → Model-Schicht → Cache/Datenbank/Suchmaschine → JSON-Antwort. Das Diagramm zeigt die Pfade für Cache-Treffer und Cache-Fehltreffer.

### Sicherheitsarchitektur in der Tiefe

![Security Architecture](./diagrams/security-architecture-cn.svg)

**18 Ebenen Verteidigung in der Tiefe**: L0 Physisches Netzwerk → L1 Transportsicherheit → L2 HTTP-Sicherheitsheader → L3 Anforderungsvalidierung → L4 Eingabebereinigung → L5 CSRF-Schutz → L6 Rate-Limit → L7 Authentifizierung (JWT+Captcha+Blacklist+Sitzungskontrolle) → L8 RBAC-Autorisierung → L9 Datenschutz (Transport-Verschlüsselung + Speicher-Verschlüsselung + ID-Verschleierung + Datenmaskierung) → L10 Prüfung & Monitoring → L11 Compliance-Offenlegung.

---

## Systemvoraussetzungen

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (nur für Frontend-Entwicklung)
- Elasticsearch >= 7.x (optional, für Suchfunktionen)

## Schnellstart

### 1. Abhängigkeiten installieren

```bash
composer install
```

### 2. Umgebungsvariablen konfigurieren

Umgebungsvariablen kopieren und anpassen (optional; ohne Konfiguration werden die Standardwerte aus `config/*.php` verwendet):

```bash
cp .env.example .env
```

Wichtige Konfigurationseinträge:

| Umgebungsvariable | Beschreibung | Standardwert |
|---------|------|--------|
| `JWT_SECRET` | JWT-Signaturschlüssel | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids-Salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API-Verschlüsselungsschlüssel | 32-Byte-Standardwert |
| `SNOWFLAKE_DATACENTER_ID` | Rechenzentrums-ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker-ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES-Adresse | `http://localhost:9200` |

**In Produktionsumgebungen müssen alle Schlüssel durch Zufallszeichenfolgen ersetzt werden.**

### 3. Datenbank initialisieren

**Variante 1: Web-Installationsassistent (empfohlen)**

Nach dem Start des Dienstes `http://localhost:8788/install` aufrufen und den 4-Schritte-Assistenten durchlaufen: Umgebungsprüfung → Datenbankkonfiguration → Admin-Konto → Ein-Klick-Installation.

**Variante 2: Kommandozeilen-Import**

```bash
mysql -u root -p 数据库名 < database/install.sql
```

`install.sql` wird aus 29 Migrationsdateien zusammengeführt und enthält alle 163 Tabellenstrukturen sowie Seed-Daten.

**Variante 3: Docker-Umgebung**

```bash
```

### 4. Dienst starten

```bash
php start.php start
```

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8788`.

### 5. Frontend starten (optional)

**Flutter-Admin (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC-Admin-Stil)
```

**HarmonyOS-Client (Mobil):**

`apps/harmonyos/` mit DevEco Studio öffnen und auf einem echten Gerät oder Simulator ausführen.

### 6. Docker-Compose-Ein-Klick-Deployment (empfohlen für Produktion)

Das Projekt enthält eine vollständige Docker-Orchestrierung mit 5 Diensten: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Docker-Umgebungsvariablen konfigurieren
cp .env.docker .env
# 2. Platzhalter-Schluessel durch Zufallswerte ersetzen (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Alle Dienste starten
docker compose up -d

# 4. Datenbank initialisieren (im app-Container ausführen)

# 5. Zugriff
# http://localhost:8788  (webman)
# http://localhost:8080  (Nginx-Reverse-Proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basierend auf `php:8.3-cli`
- `docker-compose.yml`: Orchestrierung von 5 Diensten, Netzwerk-Isolation, persistente Datenvolumes
- `.env.docker`: Umgebungsvariablen speziell für Docker

## Bedienung

### 1. Anmelden

Beim ersten Einsatz die Web-Installation `http://localhost:8788/install` aufrufen, um die Installation abzuschließen und ein Administratorkonto anzulegen. Nach der Installation die Konsole öffnen, Zugangsdaten eingeben und das Klick-Captcha lösen.

### 2. Navigation

Nach der Anmeldung über die Seitenleiste in die Module wechseln: Dashboard, Produkte, Einkauf, Verkauf, Lager, Finanzen, CRM, Genehmigungsworkflows, Benachrichtigungen, Projekte, Personal, Fertigung, benutzerdefinierte Berichte, OMS/WMS/TMS, BI-Dashboards und Systemverwaltung (Benutzer/Rollen/Konfiguration/Protokolle). Die Seitenleiste ist am Desktop fixiert und klappt am Handy als Drawer ein.

### 3. Berechtigungen und Sicherheit

- Funktionen und APIs sind über RBAC gesteuert; Menüs und Schnittstellen ohne Berechtigung sind nicht zugänglich (403)
- Sensible Aktionen wie das Löschen von Benutzern/Rollen erfordern die erneute Eingabe des aktuellen Passworts im Request-Body
- Nach dem Abmelden wird das Token sofort auf die Blacklist gesetzt

### 4. Mehrsprachigkeit

Automatische Umschaltung über den `Accept-Language`-Header (zh-CN / en), Standard ist Chinesisch.

## Datenbank-Konventionen

- **Tabellenpräfix**: `erp_`
- **Primärschlüssel**: Alle Tabellen verwenden `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT ist verboten**
- **ID-Erzeugung**: Primärschlüssel werden in der Anwendungsschicht über `SnowflakeService::generate()` erzeugt, verteilt eindeutig
- **Pflichtfelder**: Jede Tabelle muss `id`, `created_at`, `updated_at` enthalten
- **Soft Delete**: Tabellen mit Soft Delete fügen `deleted_at DATETIME DEFAULT NULL` hinzu
- **Sensible Felder**: Mobilnummern, E-Mails, Ausweisnummern usw. werden über das Plugin `encryptable` automatisch ver-/entschlüsselt; die Datenbankfelder speichern den Chiffretext in `VARCHAR(500)`

## API-Konventionen

### API-Dokumentation

Das Projekt erzeugt die Schnittstellendokumentation automatisch mit hg/apidoc; aufrufbar unter `/apidoc`.

- Admin-Schnittstellen (Admin): 25 Modulgruppen, mit vollständigen Anfrageparametern und Antwortstrukturen
- Client-Schnittstellen (Service API): 3 Gruppen (Authentifizierung/Captcha/Artikel)
- Alle Schnittstellen kennzeichnen globale Request-Header wie JWT-Authentifizierung, API-Version, Internationalisierung

### Einheitliches Antwortformat

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Geschäftsfehlercodes

| Fehlercode | Bedeutung | Beschreibung |
|-------|------|------|
| `0` | Erfolg | |
| `400` | Ungültige Anfrageparameter | |
| `401` | Nicht angemeldet (Token ungültig oder abgelaufen) | |
| `403` | Keine Berechtigung / Sicherheitsintervention | RBAC-Fehler / SecurityFilter-Angriffserkennung |
| `404` | Ressource nicht gefunden | |
| `422` | Parameter-Validierung fehlgeschlagen | |
| `413` | Anforderungsbody zu groß | SecurityFilter greift, über 10MB |
| `405` | HTTP-Methode nicht erlaubt | SecurityFilter greift, nur GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Nicht unterstützter Medientyp | SecurityFilter greift, Content-Type ist kein JSON |
| `429` | Zu viele Anfragen | RateLimit ausgelöst / Kontosperrung (5 Login-Fehler sperren 15 Minuten) |
| `500` | Interner Serverfehler | |

### Internationalisierung

Der Request-Header `Accept-Language` schaltet die Sprache automatisch um (zh-CN → Chinesisch, en → English), Standard ist Chinesisch.

### ID-Behandlung

- **IDs in Anfragen/Antworten**: werden mit hashids als Zeichenfolgen verschlüsselt; echte Datenbank-IDs werden nicht offengelegt
- **Schnittstellenpfad**: `GET /admin/user/{hashid}` — `{id}` im Pfad ist eine hashid-Zeichenfolge
- **Datenbankspeicher**: BIGINT-Originalwert, von snowflake erzeugt

### API-Versionen

Die API-Version wird über den Request-Header gesteuert, **nicht über die URL**:

```http
API-Version: v1
```

- Ohne Version wird standardmäßig `v1` verwendet
- Nicht unterstützte Versionen liefern `400 Bad Request`
- Für neue Versionen genügt das Verzeichnis `app/api/{version}/controller/`; der Middleware muss nur die neue Version registriert werden

### Rate-Limit

Redis-Sliding-Window-Algorithmus, standardmäßig 60 Anfragen/Minute/IP/Route. Sensible Schnittstellen sind strenger:
- Login: 10/Minute
- Registrierung: 5/Minute (standardmäßig deaktiviert, erfordert `REGISTRATION_ENABLED=1`)

Die Antwort-Header enthalten `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Bei Überschreitung wird 429 mit `Retry-After` zurückgegeben.

### Middleware-Architektur

Globale Middleware gilt für alle Anfragen und wird in dieser Reihenfolge ausgeführt:

```
Locale（Accept-Language 自动检测，设置语言环境）
  → Cors（跨域预处理 + 响应头）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ApiVersion（API 版本校验，/api 路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health`, `/api/docs` und `/install` sind öffentliche Endpunkte und durchlaufen nur `Locale → Cors → SecurityFilter → RateLimit`.

Sicherheitsverstärkungen:
- **Kontosperrung**: Nach 5 aufeinanderfolgenden fehlgeschlagenen Logins wird das Konto 15 Minuten gesperrt; während der Sperrung liefert der Login 429
- **Begrenzung paralleler Sitzungen**: Maximal 3 gültige Token pro Benutzer; bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt
- **security.txt**: `GET /.well-known/security.txt` liefert Sicherheitskontaktinformationen nach RFC 9116
- **Nginx-Sicherheitskonfiguration**: `docs/nginx-security.conf` dient als vollständige Referenz zur Härtung des Reverse-Proxys

### Authentifizierung

Login und Registrierung erfordern zuerst die Prüfung über ein **Klick-Captcha**:

1. Der Client ruft `POST /api/captcha/generate` auf und erhält das Captcha-Bild (base64 PNG) sowie die Liste der anzuklickenden Textziele
2. Der Benutzer klickt die Textpositionen im Bild in der richtigen Reihenfolge an; die Klickkoordinaten `[{x, y}, ...]` werden gesammelt
3. Beim Login werden `captcha_key` und `clicks` mitgesendet; der Server prüft zuerst das Captcha und dann die Anmeldedaten

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

Nachfolgende Admin-Schnittstellen benötigen die JWT-Authentifizierung:

```http
Authorization: Bearer <token>
```

Nach erfolgreichem Login wird ein access_token (Gültigkeit 2 Stunden) sowie ein refresh_token (Gültigkeit 14 Tage) zurückgegeben.

Beim Logout wird das Token in der Redis-Blacklist gespeichert und kann bis zum Ablauf nicht wiederverwendet werden. POST /admin/profile/logout

### Zweifache Bestätigung bei sensiblen Operationen

Für sensible Operationen wie das Löschen von Benutzern, Rollen oder Berechtigungen muss im Request-Body das `password` des aktuell angemeldeten Benutzers zur Identitätsbestätigung mitgesendet werden:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API-Liste

Die vollständige Schnittstellenliste (öffentliche Schnittstellen / Admin-Schnittstellen / Geschäftsschnittstellen / Client-Schnittstellen) wurde in ein separates Dokument verschoben:

→ [API-Referenz](API.md)

## Frontend-Hinweise

### Flutter-Admin (PC-Stil)

- **Layout**: Seitenleiste (einklappbar, 64px/240px) + obere Leiste + Inhaltsbereich, responsive mit drei Breakpoints (Mobil/Tablet/Desktop)
- **Seiten**: Login, Dashboard, Benutzerverwaltung, Rollen & Berechtigungen, Systemkonfiguration, Betriebsprotokoll, persönlicher Bereich
- **State-Management**: GetX (`ApiService`-Singleton + `AuthService` Token-Persistenz)
- **Dashboard**: Statistik-Karten, Trendlinien (fl_chart), Kreisdiagramme, letzte Betriebsprotokolle
- **Export**: Excel/PDF-Export, PDF mit nicht entfernbarer Copyright-Information
- **Batch-Operationen**: Mehrfachauswahl-Löschung, Batch-Aktivieren/Deaktivieren
- **Theme**: Material 3 mit Hell-/Dunkelmodus

### HarmonyOS-Mobile-Client

- **Seiten**: Login, Dashboard, Benutzerliste/-details, persönlicher Bereich
- **Authentifizierung**: JWT Bearer + automatisches unsichtbares Token-Refresh bei 401; bei Fehlschlag automatische Weiterleitung zur Login-Seite
- **Speicher**: Token über AppStorage verwaltet

## Entwicklungsrichtlinien

- Globale Funktionen/Klassen ohne führendes `\`, einheitlich per `use` importieren
- Alle PHP-Dateien müssen den Copyright-Hinweis am Dateianfang enthalten
- Alle Konfigurationsdateien müssen chinesische Kommentare enthalten
- Datenbank-Primärschlüssel müssen von snowflake in der Anwendungsschicht erzeugt werden; AUTO_INCREMENT ist verboten
- Alle IDs in API-Parametern und -Antworten müssen per hashids ver-/entschlüsselt werden
- Die AdminPermission-Middleware cached Benutzerberechtigungen in Redis (TTL=60s), um den N+1-Abfrageflaschenhals zu beseitigen

## Deployment

### Docker Compose (empfohlen)

Im Projektstamm liegt `docker-compose.yml` mit 5 Diensten:

| Dienst | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Lokal über `Dockerfile` gebaut | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Das PHP-Image wird über `Dockerfile` auf Basis von `php:8.3-cli` gebaut, mit aktiviertem OPcache.

```bash
cp .env.docker .env
# Platzhalter-Schluessel durch Zufallswerte ersetzen (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

GitHub-Actions-Pipeline für kontinuierliche Integration: `.github/workflows/ci.yml`

- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische-Analyse (`flutter analyze`, im CI enthalten und aktiviert — siehe flutter-Job in `.github/workflows/ci.yml`)

### Datenbank-Backup

Verzeichnis `database/backup/`:

- `backup.sh` — mysqldump + gzip, löscht automatisch Backups, die älter als 30 Tage sind
- `restore.sh` — interaktive Wiederherstellung, listet verfügbare Backups zur Auswahl auf

### Nginx-Sicherheitskonfiguration

Für Produktions-Deployments siehe `docs/nginx-security.conf` zur Härtung des Reverse-Proxys.

## Open Source ist kein Selbstläufer — Unterstützung willkommen

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./images/weixinpay.png "WeChat") | ![Alipay](./images/alipay.png "Alipay") |

### Überweisung (Banküberweisung / Global Bank Transfer)

**Begünstigter**

- Name des Begünstigten: WANG KEXUN
- Kontonummer: 881015918251

**Begünstigtenbank**

- ZA Bank SWIFT-Code: AABLHKHHXXX
- Bankname: ZA Bank Limited
- Bankleitzahl: 387
- Bankadresse: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Korrespondenzbank für grenzüberschreitende Überweisungen (falls erforderlich)**

> Dies sind Informationen zur Korrespondenzbank (Zwischenbank), nicht zur Begünstigtenbank. Bitte fragen Sie Ihre überweisende Bank, ob diese Angaben benötigt werden.

- Überweisung in Hongkong-Dollar, Renminbi und US-Dollar: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, Bankleitzahl 006, Filiale Hong Kong Branch, Filialnummer 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Überweisung in anderen Währungen: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Krypto-Spenden (Crypto Donation)

Wenn dieses Projekt Ihnen hilft, scannen Sie gerne den QR-Code, um zu spenden. Vielen Dank!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
