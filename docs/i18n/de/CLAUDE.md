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

**Aktuelle Gesamtbewertung 89/100** — vollständige Roadmap P0~P3 abgeschlossen, 23 Module Full-Stack abgedeckt, produktionsreif.

| Phase | Dauer | Lieferumfang | Status |
|------|------|--------|------|
| 🔵 **P0** Frontend-Ökosystem | 3-4 Wochen | 102 Flutter-Menürouten + 41 HarmonyOS-Seiten + 4 Allzweck-Komponenten | ✅ |
| 🟢 **P1** Business-Tiefe | 4-6 Wochen | Finanz-Engine + Gehalts-Engine + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Betriebszuverlässigkeit | 1-2 Wochen | Migrations-Rollback + automatisches Backup + TraceId + Warteschlangen-Dual-Treiber | ✅ |
| 🟣 **P3** Erlebnisverbesserung | 2-3 Wochen | BI-Dashboards + EAM + DMS | ✅ |
(Multi-Tenant B5 wurde vorzeitig mit P2 geliefert: TenantScope-Anforderungskontext + erp_tenant, der Seam der Isolations-Middleware ist nicht registriert)

**Tests**: 1044<!-- stats:tests=1044 --> tests, 5020<!-- stats:assertions=5020 --> assertions (23 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

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
| Sicherheit | 7 Ebenen Tiefenverteidigung (XSS/SQL-Injection/CSRF/Rate-Limit/CSP...) |
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
- API-Dokumentgenerierung: `erikwang2013/apidoc-php` | Annotationsbasiert, Zugriff über /apidoc

### Frontend
- Flutter 3.x, Quellverzeichnis `apps/flutter/`
- Web-Ende im PC-Verwaltungsstil gestaltet (nicht Mobile-App-Stil)
- Unterstützt Client- und Admin-Bereich
- HarmonyOS ArkTS, Quellverzeichnis `apps/harmonyos/`
- Angular 22 CLI + ng-zorro-antd, Quellverzeichnis `apps/angular/` (Web-Verwaltungsoberfläche)
- React 19 + Vite, Quellverzeichnis `apps/react/` (Web-Verwaltungsoberfläche)
- Vier Frontends, ein Backend: Angular / React nutzen wie Flutter `/admin/v1`, `/api/v1`, `/open/v1`; während der Entwicklung proxyt jeweils der eigene Dev-Server auf webman

### Internationalisierung (13 Sprachen)
- Sprachenliste: `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`
- Backend-Wörterbuch: `resource/translations/<locale>/{common,modules,validation}.php`, 13 Sprachverzeichnisse; `zh_CN` 565 Einträge, die übrigen 11 Sprachen je 544 Einträge, `en` 30 Einträge (Zählweise: Blatteinträge der drei Dateien, die Feldlabels in `attributes` von `validation.php` zählen mit, deren Gruppenschlüssel nicht)
  - „Englisch ist der Key": bei `en` bleiben common/modules leer; die Schlüssel in `validation.php` sind Framework-Regelnamen, nur die Werte werden übersetzt
  - Generator: `scripts/gen-be-locales.mjs`
- Frontend-Wörterbuch (Angular): Quelle `apps/angular/src/app/core/zh-en/part1..4.ts` (1456 Einträge) → Artefakt `apps/angular/src/app/core/zh-<code>.ts`
- Frontend-Wörterbuch (React): Quelle `apps/react/src/lib/i18n/zhEn.ts` (1451 Einträge) → Artefakt `apps/react/src/lib/i18n/zh<Code>.ts`
  - Die Wörterbücher der 11 neuen Sprachen werden je per dynamischem `import()` zu einem eigenen Chunk; fehlende Einträge fallen auf den chinesischen Originaltext zurück
  - Generator: `scripts/gen-fe-locales.mjs --app angular|react`
- Laufzeit: Ein Sprachwechsel wechselt den Request-Header `Accept-Language`, das Backend liefert die Texte je Sprache (`app/common/I18n.php` + `config/translation.php`)

## Projektstruktur

```
open-erp/
├── app/
│   ├── admin/controller/       # Systemverwaltungs-Controller (16)
│   │   ├── BaseController.php      # Basis-Controller
│   │   ├── DashboardController.php # Dashboard + Vertriebs-/Bestands-/Finanzpanel
│   │   ├── UserController.php      # Benutzer-CRUD + Massenoperationen
│   │   ├── RoleController.php      # Rollen-CRUD
│   │   ├── PermissionController.php# Berechtigungs-CRUD
│   │   ├── ConfigController.php    # Systemkonfigurations-CRUD
│   │   ├── LogController.php       # Abfrage des Betriebsprotokolls
│   │   ├── ProfileController.php   # Persönlicher Bereich + Logout
│   │   ├── ExportController.php    # Excel/PDF-Export
│   │   ├── ImportController.php    # Excel-Import von Benutzern
│   │   ├── UploadController.php    # Datei-Upload
│   │   ├── HealthController.php    # Health-Check
│   │   ├── DocsController.php      # OpenAPI-Dokumentation
│   │   └── MetricsController.php   # Prometheus-Metriken
│   ├── api/v1/controller/      # Client-API (Version im Pfad /api/v1, kein Versions-Header)
│   │   ├── CaptchaController.php   # Klick-Captcha
│   │   ├── AuthController.php      # Login/Registrierung/Refresh
│   │   └── ProductController.php   # Artikelabfrage (ohne Einkaufspreis)
│   ├── controller/              # Geschäftsmodul-Controller (139, inkl. oberste InstallController / IndexController)
│   │   ├── product/             # Artikel/Kategorien/Marken/Lager/Lagerplätze/Lieferanten/Kunden/Spezifikationen (8)
│   │   ├── purchase/            # Anfrage/Angebotsanfrage/Angebot/Bestellung/Wareneingang/Retoure/Abrechnung/Lieferantenbewertung (8)
│   │   ├── sales/               # Verkaufsangebot/Auftrag/Versand/Retoure/Abrechnung (5)
│   │   ├── inventory/           # Bestand/Bewegungen/Umlagerung/Inventur/Warnungen/Rückverfolgung (6)
│   │   ├── finance/             # Forderungen-Verbindlichkeiten/Belege/Zahlungen/Journal/Hauptbuch/Nebenbuch/drei Abschlüsse/Anlagevermögen/Steuern/Mehrwährung/Budget/Kosten- und Profit-Center/Bankkontenabstimmung/Spesen/Rechnungsabrechnung/Konsolidierung/Buchungsperioden (28)
│   │   ├── crm/                 # Verkaufschancen/Follow-up/Funnel/Kontakte/Lead-Pool/Angebote/Verträge/Marketing/Tickets/Analyse (10)
│   │   ├── workflow/            # Workflow-Definition/Designer/Genehmigungsantrag/Freigabe/Ablehnung/Zurückziehen (3)
│   │   ├── notification/        # Benachrichtigungsliste/Gelesen/Ungelesen-Zähler/Benachrichtigungskanäle (2)
│   │   ├── project/             # Projekt/Aufgabe/Arbeitszeitbuchung/Projektkosten (4)
│   │   ├── hr/                  # Abteilung/Mitarbeiter/Position/Anwesenheit/Gehalt/Leistung/Recruiting/Sozialversicherung/Schulung (9)
│   │   ├── manufacturing/       # BOM/Fertigungsauftrag/Arbeitsplan/Arbeitsstation/MRP/Kapazität/Materialentnahme/Arbeitsrückmeldung/Akkordlohn/Kostenerfassung/Subunternehmer und Wareneingang/-ausgang (13)
│   │   ├── report/              # Berichtsvorlage/Datensatz/Ausführung/Zeitplanung (2)
│   │   ├── oms/                 # Auftrag/Fulfillment/Bestandsreservierung/RMA/Kanäle (4)
│   │   ├── wms/                 # Zone und Lagerplatz/ASN-Wareneingang/Einlagerung/Welle/Kommissionierung/Verpackung (8)
│   │   ├── tms/                 # Spediteur/Tarif/Frachtbrief/Versandetikett/Tracking (6)
│   │   ├── quality/             # IQC/IPQC/OQC/Prüfstandards/Nichtkonformitäten (5)
│   │   ├── eam/                 # Anlagen/Wartungspläne/Reparaturaufträge/Ersatzteile/Inspektion (5)
│   │   ├── dms/                 # Dokumentkategorien/Dokumente/Versionen (2)
│   │   ├── open/                # Offene API (1)
│   │   ├── platform/            # Benutzerdefinierte Felder/Mandanten (2)
│   │   ├── print/               # Druckvorlagen (1)
│   │   ├── retail/              # Gutscheine/Mitglieder (2)
│   │   └── bi/                  # BI-Dashboards/Diagrammkomponenten (3)
│   ├── service/                 # Geschäftslogikschicht (64 Dateien / 63 Serviceklassen)
│   │   ├── finance/             # FinanceService: automatische Erzeugung von Forderungen/Verbindlichkeiten + Zahlungsverrechnung + Tagebuch
│   │   ├── inventory/           # InventoryService: Ein-/Auslagerung + gleitende Durchschnittskostenrechnung
│   │   ├── notification/        # NotificationService: Benachrichtigungsversand
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/…  # Auftrag/Lager/Transport/Qualität/Personal/Fertigung usw. (insgesamt 20 Modul-Unterverzeichnisse)
│   ├── common/                  # Gemeinsame Hilfsklassen (6)
│   │   ├── HashidsService.php   # ID-Kodierung/-Dekodierung
│   │   ├── SnowflakeService.php # Snowflake-ID-Generierung
│   │   ├── EncryptionService.php# Datenver-/entschlüsselung + Maskierung
│   │   ├── I18n.php             # Internationalisierungsübersetzung
│   │   ├── CorsPolicy.php       # CORS-Strategie (aufgerufen von middleware/Cors und route.php)
│   │   └── AddressValidator.php # Adressprüfung (Postleitzahlformate mehrerer Länder + Formularfelder)
│   ├── middleware/              # Middlewares (11)
│   │   ├── Cors.php             # Cross-Origin
│   │   ├── SecurityFilter.php   # Abfang von XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF
│   │   ├── RateLimit.php        # Redis-Sliding-Window-Rate-Limit
│   │   ├── AdminAuth.php        # JWT-Authentifizierung + Blacklist
│   │   ├── AdminPermission.php  # RBAC-Berechtigungsprüfung
│   │   ├── OperationLog.php     # Automatische Aufzeichnung des Betriebsprotokolls
│   │   ├── OpenApiAuth.php      # Authentifizierung der offenen Schnittstellen (X-API-Key + Signatur, nur an der Gruppe /open/v1 registriert)
│   │   ├── TenantScope.php      # Mandantenisolierung (reserviert, nicht registriert, siehe ARCHITECTURE.md §22)
│   │   ├── TracingId.php        # Durchgängige TraceId über die gesamte Kette
│   │   ├── TrackingSignature.php# Prüfung der Requests-Signatur
│   │   └── StaticFile.php       # Statischer Dateiserver (webman-intern)
│   ├── model/                   # Datenmodelle (224; inkl. concerns/TenantScope-Trait insgesamt 225 Dateien)
│   ├── queue/                   # Warteschlangenaufgaben
│   └── process/                 # Prozesse (Http, WebSocket, QueueConsumer, Monitor)
├── apps/
│   ├── flutter/                 # Flutter alle Plattformen (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # Geschäftsseiten (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # Responsives Layout
│   │       └── theme/          # Material-3-Theme
│   ├── angular/                 # Angular 22 CLI + ng-zorro-antd Web-Verwaltung
│   │   └── src/app/
│   │       ├── core/            # ApiService / AuthStore / I18n-Service + Sprachwörterbücher (Quelle zh-en/, Produkt zh-<code>.ts)
│   │       └── config/ layout/ pages/ ui/
│   ├── react/                   # React 19 + Vite Web-Verwaltung
│   │   └── src/
│   │       ├── lib/i18n/        # Quellwörterbuch zhEn.ts + 11 Sprachdateien zh<Code>.ts (je Sprache per Lazy-Loading)
│   │       └── components/ layout/ pages/ state/ config/domains/ styles/
│   └── harmonyos/              # HarmonyOS-Client
├── config/                     # Konfigurationsdateien
│   ├── route.php               # Routing + API-Versionsstrategie
│   ├── middleware.php           # Globale Middleware-Registrierung
│   ├── translation.php          # Sprachkonfiguration
│   └── plugin/                  # Plugin-Konfiguration (erikwang2013/*; apidoc siehe erikwang2013/apidoc/)
├── database/
│   ├── install.sql              # Vollständiges Installations-SQL (227 Tabellen + Seed-Daten, alle Migrationen bereits zusammengeführt)
│   ├── e2e-seed.sql             # Minimaler Seed für E2E/CI
│   └── backup/                 # Datenbank-Backup-Skripte
│       ├── backup.sh           # mysqldump+gzip, 30 Tage Aufbewahrung
│       └── restore.sh          # Interaktive Wiederherstellung
├── docs/                       # Dokumentation
│   ├── ARCHITECTURE.md         # Mermaid-Architekturdiagramme
│   ├── DESIGN.md               # Designdokument
│   ├── FEATURE_DESIGN.md       # Funktionsdesigndokument
│   ├── SECURITY.md             # Sicherheitsarchitektur-Design
│   ├── API.md                  # API-Referenz
│   ├── nginx-security.conf     # Nginx-Sicherheitsreferenzkonfiguration
│   ├── diagrams/               # Aufgeschlüsselte Architekturdiagramme
│   └── superpowers/            # Spezifikationen und Pläne
│       ├── specs/              # Designspezifikationen
│       └── plans/              # Umsetzungspläne
├── public/                     # Öffentlicher Einstieg
├── runtime/                    # Laufzeitdateien
├── tests/                      # Tests
├── vendor/                     # Composer-Abhängigkeiten
├── CLAUDE.md                   # Diese Datei
├── README.md                   # Chinesische Beschreibung
├── README_EN.md                # Englische Beschreibung
├── .env                        # Umgebungsvariablen (nicht versioniert)
├── .env.example                # Vorlage für Umgebungsvariablen
├── .env.docker                 # Docker-Umgebungsvariablen
├── composer.json               # PHP-Abhängigkeiten
├── Dockerfile                  # Docker-Build (mit OPcache + event + redis Erweiterungen)
├── docker-compose.yml          # Docker-Orchestrierung
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD-Pipeline (PHP-Syntax+PHPStan+CS Fixer+PHPUnit+composer audit, Mehrversionsmatrix)
```

## Middleware-Ausführungskette

```
Global:    Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → {Routen-Middlewares}
/health:   Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → Controller
/install:  Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → Controller
/admin/v1: Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api/v1:   Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → Controller
/open/v1:  Cors → SecurityFilter(Methodenprüfung→405) → RateLimit → TracingId → OpenApiAuth → Controller
```

## Sicherheitsverstärkung

- **HTTP-Methodeneinschränkung**: SecurityFilter erlaubt nur GET/POST/PUT/DELETE/OPTIONS/HEAD, nicht standardmäßige Methoden liefern 405
- **CSP-Header**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies werden in alle Antworten injiziert
- **Kontosperrung**: nach 5 aufeinanderfolgenden fehlgeschlagenen Logins wird das Konto 15 Minuten gesperrt
- **Begrenzung paralleler Sitzungen**: maximal 3 gültige Tokens pro Benutzer, bei Überschreitung wird das älteste Token in die Blacklist aufgenommen
- **security.txt**: `/.well-known/security.txt` RFC-9116-Endpunkt
- **Nginx-Sicherheitskonfiguration**: `nginx-security.conf` als Referenz zur Sicherheitshärtung des Reverse-Proxys

## API-Versionsstrategie

Die Version liegt im URL-Pfad (`/admin/v1`, `/api/v1`, `/open/v1`), einen Versions-Request-Header gibt es nicht:

```bash
curl http://localhost:8788/api/v1/auth/login
```

Eine neue Version erfordert nur die Erstellung des Verzeichnisses `app/api/{version}/controller/` und die Registrierung der Gruppe `/api/v{version}` in `config/route.php` (die Versionsnummer erscheint ausschließlich im URL-Pfad, Controller werden direkt gebunden; die frühere `ApiVersion`-Header-Middleware wurde entfernt).

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
- Nahtlose Token-Erneuerung: bei 401 automatischer Aufruf von `/api/v1/auth/refresh`
- Bei fehlgeschlagener Erneuerung automatische Weiterleitung zur Login-Seite

## Bekannte technische Schulden

> Die folgende Liste wurde mit `grep -rn "new .*Service(" app/controller/` real gemessen (45 Treffer) und entspricht den Code-Fakten.
> **P5: keine Refaktorierung**: Dass Controller Services direkt instanziieren, ist das bestehende Muster; nur bei neuem Code wird auf Container-Injektion (`support\Container`) umgestellt, bestehender Code bleibt wie er ist.

| Modul | Direkt instanziierte Services | Beschreibung |
|------|-----------|------|
| finance | 22 | Forderungen/Verbindlichkeiten/Verrechnung/Tagebuch/Periodenabschluss/konzerninterne Eliminierung |
| wms | 9 | Prozessservices für Wareneingang/Einlagerung/Wellen/Kommissionierung/Verpackung |
| tms | 5 | Frachtbrief/Preisvergleich/Tracking/Frachtkostenrechnung |
| oms | 3 | Fulfillment/Reservierung/RMA |
| quality | 2 | Prüfung/Behandlung fehlerhafter Produkte |
| hr | 2 | Gehälter/Anwesenheit |
| platform | 1 | Mandant |
| notification | 1 | Benachrichtigungskanal |

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
bash scripts/gen-env-keys.sh .env   # echte Schlüssel erzeugen (Platzhalter-Schlüssel werden beim Start abgelehnt)
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
