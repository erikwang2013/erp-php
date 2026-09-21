# Open-Admin — Design-Dokument

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Detaillierte Mermaid-Architekturdiagramme siehe [ARCHITECTURE.md](ARCHITECTURE.md) (werden von GitHub/GitLab/VS Code automatisch gerendert).

## 1. Systemarchitektur

> **Funktionsliste**: Authentifizierung(login/register/refresh/logout + Kontosperrung + Sitzungsbegrenzung) | Dashboards(Redis-Cache) | Benutzer-CRUD+Batch+Import | Rollen & Berechtigungen(RBAC) | Systemkonfiguration | Betriebsprüfung(8 Plattform-Quellgeräte) | Dateien(Upload+Export+Maskierung) | Sicherheit(7 Ebenen Tiefenverteidigung der Middleware, L0–L12-Panorama + 35 Klassen Angriffsdetektoren) | Betrieb(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        Client-Schicht                        │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │   Flutter Web (PC)   │  │   HarmonyOS ArkTS (Mobil)    │  │
│  │ Verwaltung (Desktop) │  │    Client (Handy/Tablet)     │  │
│  └──────────┬───────────┘  └───────────────┬──────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │         HTTPS / JSON         │                  
              │  Authorization: Bearer JWT   │                  
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                 │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                 API-Gateway-Schicht                  │    │
│  │ AdminAuth(Auth) → AdminPermission(RBAC) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│  │                          │                           │    │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │      Geschäftslogikschicht (Controller/Service)      │    │
│  │ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │ │Dashboard │ │   User   │ │   Role   │ │  Export  │  │    │
│  │ │Controller│ │Controller│ │Controller│ │Controller│  │    │
│  │ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘  │    │
│  └───────┼────────────┼────────────┼────────────┼───────┘    │
│  │       │            │            │            │       │    │
│  ┌───────┼────────────┼────────────┼────────────┼───────┐    │
│  │       ▼            ▼            ▼            ▼       │    │
│  │                    Model-Schicht                     │    │
│  │   ┌────────────────────────────────────────────┐     │    │
│  │   │  Snowflake ID ← encryptable → Encryption   │     │    │
│  │   │(PK-Erzeugung) (DB-Verschl.) (API-Transport)│     │    │
│  │   └────────────────────────────────────────────┘     │    │
│  └──────────────────────────┬───────────────────────────┘    │
│  │                          │                           │    │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │                 Datenspeicherschicht                 │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │Elasticsearch │  │  Redis   │        │    │
│  │  │(Haupt-DB)│  │  (Volltext)  │  │ (Cache)  │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                          webman v2                           │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend-Architektur

### 2.1 Schichten-Design

| Schicht | Verzeichnis | Zuständigkeit |
|---|------|------|
| Routing | `config/route.php` | Zuordnung URL zu Controller, Middleware-Bindung, versionierte Routen |
| Middleware | `app/middleware/` | Cross-Origin (Cors), Angriffsabwehr (SecurityFilter), Rate-Limit (RateLimit), Kettenverfolgung (TracingId), Authentifizierung (JWT), Autorisierung (RBAC), Betriebsprotokoll (OperationLog), Signatur der offenen Schnittstellen (OpenApiAuth), insgesamt 11 Dateien |
| Controller | Admin 15: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/OpenApi/Webhook (zusätzlich die Basisklasse `BaseController`) + API v1 3: Captcha/Auth/Product | Validierung der Request-Parameter, Aufruf der Businesslogik, Antwortformatierung |
| Business-Services | `app/service/` | Wiederverwendbare Businesslogik (reserviert) |
| Datenmodelle | `app/model/` | ORM-Zuordnung, Beziehungen, Feld-Verschlüsselung |
| Gemeinsame Tools | `app/common/` | Hashids-, Snowflake-, Encryption-Services |

### 2.2 Request-Lebenszyklus

```
Client-Anfrage
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route-Abgleich
  │
  ▼
Middleware-Kette:
  Cors ────────────────► OPTIONS-Preflight verarbeiten, CORS-Response-Header injizieren
  │
  ▼
  SecurityFilter ──────► HTTP-Methodenprüfung → 405 (nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt)
  │                     Abfang von XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF (403)
  ▼
  RateLimit ───────────► Redis-Sliding-Window-Rate-Limit
  │ (bei Überschreitung 429 + Retry-After-Header)
  ▼
  TracingId ───────────► X-Trace-Id generieren, durchgängig über die gesamte Kette
  │ (Versionsnummer liegt im URL-Pfad /admin/v1 /api/v1 /open/v1, keine Versions-Header-Middleware)
  ▼
  AdminAuth ──────────► JWT-Verifizierung, Injektion von $request->adminId
  │ (bei Fehler 401)
  ▼
  AdminPermission ────► RBAC-Berechtigungsprüfung (Redis-60s-Cache)
  │ (bei Fehler 403)
  ▼
  OperationLog ───────► Betriebsprotokollierung (POST/PUT/DELETE), automatische Erkennung des Quellgeräts
  │
  ▼
Controller::method()
  │
  ├─► Parameterprüfung (validator)
  ├─► Bestätigung sensibler Operationen (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model-Operation (automatische encryptable Ver-/Entschlüsselung)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID-Lebenszyklus

```
Erzeugung (Snowflake) → Speicherung (MySQL BIGINT) → Übertragung (Hashids-Kodierung) → außen (Hash-String)
                                                                                                         │
                                                                         HashidsService::decode()        ┘
```

### 2.4 Datenverschlüsselungs-System

```
Transportschicht (encryption)  — AES-256-CBC, eigener Schlüssel
Speicherschicht (encryptable)  — AES-128-ECB, eigener Schlüssel, Model-$casts automatisch
Darstellungsschicht (mask)     — Telefon: 138****1234, E-Mail: a***@example.com
```

## 3. Datenbankdesign

### 3.1 ER-Beziehungen

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (Benutzer)       │    (Benutzer-Rolle)       │     (Rolle)
                   │                           │
                   │                 erp_admin_role_permission
                   │                  (Rolle-Berechtigung)
                   │                           │
                   │                           ▼
                   │                 erp_admin_permission
                   │                   (Berechtigung/Menü)
                   │
                   ▼
            erp_operation_log
              (Betriebsprotokoll)

erp_system_config (Systemkonfiguration) — eigenständige Tabelle
```

### 3.2 Kern-Tabellenstruktur

| Tabellenname | Feldanzahl | Beschreibung |
|------|-------|------|
| `erp_admin_user` | 14 | Verwaltungsbenutzer, phone/email/id_card verschlüsselt gespeichert, Soft-Delete unterstützt |
| `erp_admin_role` | 7 | Rollen, slug eindeutig |
| `erp_admin_permission` | 10 | Berechtigungsbaum (parent_id Selbstreferenz), type: 1=Menü 2=Button 3=API |
| `erp_admin_user_role` | 2 | viele-zu-viele-Zwischentabelle Benutzer-Rolle |
| `erp_admin_role_permission` | 2 | viele-zu-viele-Zwischentabelle Rolle-Berechtigung |
| `erp_system_config` | 8 | Schlüssel-Wert-Konfiguration, group+key kombiniert eindeutig |
| `erp_operation_log` | 9 | Betriebsprüfprotokoll (einschließlich source-Quellgerät) |

### 3.3 Primärschlüssel-Konvention

- Typ: `BIGINT UNSIGNED NOT NULL`
- Eigenschaft: **nicht auto-increment**, wird von der Snowflake-Algorithmus-Anwendungsschicht erzeugt
- Vorteile: global eindeutig, verteilungsfreundlich, trendmäßig aufsteigend günstig für Indizes, setzt das Geschäftsvolumen nicht offen
- Konfiguration: datacenter_id(0-31) + worker_id(0-31), unterstützt 1024 Knoten parallel

## 4. API-Design

### 4.1 URL-Konvention

```
Öffentliche Schnittstellen:  /api/v1/captcha/{generate|verify}
           /api/v1/auth/{login|register|refresh}

Admin:   /admin/{resource}[/{hashid}]
          /admin/v1/export/{excel|pdf}

Ressourcen-Routen:
  GET    /admin/v1/user          → Liste
  POST   /admin/v1/user          → Erstellen
  GET    /admin/v1/user/{hashid} → Details
  PUT    /admin/v1/user/{hashid} → Aktualisieren
  DELETE /admin/v1/user/{hashid} → Löschen (erfordert Passwortbestätigung)

Systemkonfiguration:  /admin/v1/config[/{hashid}]
Betriebsprotokoll:  /admin/v1/log
Persönlicher Bereich:  /admin/v1/profile[/password|/logout]
Import:     /admin/v1/import/users
Upload:     /admin/v1/upload
Batch:     /admin/v1/user/batch/{destroy|status}
Dokumentation:     /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 API-Versionsstrategie

Die API-Version liegt **im URL-Pfad**, es wird kein Versions-Request-Header verwendet: Verwaltung `/admin/v1`, Client `/api/v1`, offene Schnittstelle `/open/v1`.

| Mechanismus | Beschreibung |
|------|------|
| Versionsposition | URL-Pfad, z. B. `/api/v1/auth/login` |
| Routengruppe | `Route::group('/api/v1', …)` in `config/route.php` bindet direkt an den Controller |
| Verzeichnis | Controller nach Version organisiert: `app/api/{version}/controller/` |
| Versions-Header-Middleware | Die historische dynamische `v()`-Auflösung und die `ApiVersion`-Request-Header-Middleware sind **entfernt** |

Erweiterungsbeispiel — neue v2-API:
1. `app/api/v2/controller/AuthController.php` erstellen
2. In `config/route.php` die Gruppe `Route::group('/api/v2', …)` registrieren und direkt an den Controller binden
3. Kein Versions-Request-Header; die Routengruppe selbst ist die Versionsgrenze

```bash
# v1 verwenden
curl http://localhost:8788/api/v1/auth/login

# v2 verwenden
curl http://localhost:8788/api/v2/auth/login
```

### 4.3 Rate-Limit-Strategie

Basiert auf dem Redis-Sorted-Set-Sliding-Window-Algorithmus, Ausführung als atomares Lua-Skript:

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Mal/Minute/IP/Routing |
| POST /api/v1/auth/login | 10 Mal/Minute |
| POST /api/v1/auth/register | 5 Mal/Minute |

Bei Überschreitung wird 429 geliefert, die Antwort-Header enthalten X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Einheitliche Antwort

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 0 | Erfolg | normale Antwort |
| 400 | Parameterfehler | Request-Format nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt/abgelaufen/ungültig |
| 403 | Keine Berechtigung | Benutzerrolle enthält die erforderliche Berechtigung nicht |
| 404 | Nicht vorhanden | Ressource nicht gefunden |
| 422 | Validierung fehlgeschlagen | Formularparameter entsprechen den Regeln nicht / Passwortbestätigung fehlgeschlagen |
| 500 | Serverfehler | unerwartete Ausnahme |

### 4.5 Authentifizierungsablauf (einschließlich Click-Captcha)

```
Client                                 Server
  │                                    │
  │ ① POST /api/v1/captcha/generate       │ captcha_create('click')
  │◄── {key, image(base64), targets}   │
  │                                    │
  │ ② Klick auf die Textposition       │
  │                                    │
  │ ③ POST /api/v1/auth/login             │
  │     {username, password,           │
  │      captcha_key, clicks}          │
  │────────────────────────────────►   │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}   │
  │                                    │
  │ ④ GET /admin/v1/dashboard             │
  │     Authorization: Bearer xxx      │
  │────────────────────────────────►   │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}            │
```

### 4.6 Berechtigungsmodell (RBAC)

```
  Benutzer ──┬── Rolle ──┬── Berechtigung
                         │
                         ├── type=1: Menü (steuert die Sichtbarkeit der Sidebar)
                         ├── type=2: Button (steuert Aktionen innerhalb der Seite)
                         └── type=3: API  (steuert den Schnittstellenzugriff)

  Berechtigungsformat: {method}.{path}
  Beispiel: get.admin/user  post.admin/user  delete.admin/user
  Super-Admin-Kennung: * (überspringt alle Berechtigungsprüfungen)
```

### 4.7 Zweite Bestätigung bei sensiblen Operationen

Sensible Operationen wie das Löschen von Benutzern, Rollen und Berechtigungen erfordern die Übermittlung des aktuellen Benutzerpassworts im Request-Body zur Identitätsprüfung:

```
Client                           Server
  │                              │
  │ DELETE /admin/v1/user/{hashid}  │
  │ { password: "******" }       │
  │────────────────────────────► │
  │                              │ confirmPassword(adminId, password)
  │                              │ → falsches Passwort liefert 422
  │                              │ → korrektes Passwort läuft weiter
  │◄── 200 { code: 0 }           │
```

Das Frontend zeigt vor dem Auslösen der Löschoperation einen Bestätigungsdialog an, sammelt das Benutzerpasswort und sendet dann den Request.

## 5. Frontend-Design

### 5.1 Flutter-Web-Verwaltungsoberfläche

```
┌──────────────────────────────────────────────────────┐
│  Header (56px)                                       │
│  ☰ Menü                 🔔 Mitteilungen  👤 Admin  ▼ │
├──────────────────┬───────────────────────────────────┤
│ Sidebar          │  Content Area                     │
│ (64/240)         │                                   │
│                  │  ┌────────────────┐ ┌────────────┐│
│ 📊 Übersicht     │  │ Kennzahlen ×4  │ │ Trendkurve ││
│ 👥 Benutzer      │  └────────────────┘ └────────────┘│
│                  │  ┌────────────────┐ ┌────────────┐│
│ 🔒 Rollen        │  │ Tortendiagramm │ │Letzte Logs ││
│ ⚙ Konfiguration  │  └────────────────┘ └────────────┘│
│ 📋 Logs          │                                   │
└──────────────────┴───────────────────────────────────┘
```

Eigenschaften: einklappbare Sidebar, Material-3-Doppel-Theme, hochdichte Datentabellen, Dialog-Popups, Hover-Interaktionen

### 5.2 HarmonyOS-Mobilclient

Seiten-Routing:

| Seite | Route | Beschreibung |
|------|------|------|
| LoginPage | `pages/LoginPage` | Benutzername-Passwort + Click-Captcha-Login |
| DashboardPage | `pages/DashboardPage` | Statistik-Karten + letzte Operationen |
| UserListPage | `pages/UserListPage` | Benutzerliste, Suche + Pull-to-Refresh + Scroll-up-Laden |
| UserDetailPage | `pages/UserDetailPage` | Neu/Editieren/Ansehen/Löschen (AlertDialog-Bestätigung) |
| ProfilePage | `pages/ProfilePage` | Persönlicher Bereich, Abmelden (AlertDialog-Bestätigung) |

Datenfluss: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Sicherheitsdesign

### 6.1 Tiefenverteidigung

| Ebene | Maßnahme |
|------|------|
| Methodeneinschränkung | SecurityFilter-HTTP-Methoden-Whitelist, nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht standardmäßige Methoden liefern 405 |
| Angriffsabwehr | SecurityFilter-Middleware, Erkennung und Abwehr von XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF |
| Mensch-Computer-Verifikation | Click-Captcha, Pflichtprüfung bei Login/Registrierung |
| Kontosperrung | 5 aufeinanderfolgende fehlgeschlagene Logins sperren das Konto 15 Minuten, während der Sperre wird 429 geliefert |
| Sitzungsbegrenzung | maximal 3 parallele Tokens pro Benutzer, bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt |
| Rate-Limit | RateLimit-Middleware, Redis-Sliding-Window, Lua-Atomar |
| CSP | Content-Security-Policy-Header begrenzt Ressourcenquellen, schützt vor XSS und Dateninjektion |
| Operationsbestätigung | Sensible Operationen wie Löschen erfordern die Eingabe des aktuellen Benutzerpassworts als zweite Bestätigung |
| Übertragung | HTTPS + JWT-Bearer-Token |
| Schnittstellen-ID | Hashids-Verschlüsselung, von außen keine Rückschlüsse auf die echte ID möglich |
| Request-Body | AES-256-CBC-Verschlüsselung sensibler Felder |
| Datenbank | BIGINT-Primärschlüssel (setzt die Inkrement-Menge nicht offen) |
| Datenbank | AES-128-ECB-Verschlüsselung sensibler Felder bei der Speicherung |
| Authentifizierung | JWT HS256, 2h-Ablauf + refresh token |
| Autorisierung | RBAC, method.path-Granularität der Zugriffskontrolle |
| Audit | OperationLog protokolliert alle Operationen (einschließlich automatischer Erkennung des source-Quellgeräts) |

### 6.2 Schlüsselverwaltung

```
JWT_SECRET_KEY      → Injektion über Umgebungsvariable, 64-stellige Zufallszeichenkette
HASHIDS_SALT        → eindeutiger Salt, nach einem Leak global zu wechseln
ENCRYPTION_KEY      → Schlüssel der API-Transportverschlüsselung, 32 Byte
ENCRYPTABLE_KEY     → Schlüssel der DB-Speicherverschlüsselung, unabhängig vom Transportschlüssel
SCOUT_HOSTS         → ES-Adresse, Deployment im internen Netz
```

### 6.3 Schutz sensibler Daten

| Szenario | Feld | Maßnahme |
|------|------|------|
| Listenanzeige | phone | Maskierung: 138****1234 |
| Listenanzeige | email | Maskierung: a***@example.com |
| Detailansicht | phone/email | erfordert Entschlüsselungs-Schnittstelle |
| Excel-Export | phone/email | maskiert exportieren |
| PDF-Export | alle Felder | Maskierung + nicht entfernbarer Copyright-Wasserzeichen |
| Speicherung | phone/email/id_card | encryptable verschlüsselt zu Chiffretext |

## 7. Export-Design

### 7.1 Excel-Export

```
Request: POST /admin/v1/export/excel { table, columns, conditions, title }
  → fetchExportData() fragt die Daten ab (limit 10000)
  → sensible Felder maskieren
  → PhpSpreadsheet-Aufbau (blauer Kopf mit weißer Schrift + erste Zeile fixiert + Autofilter)
  → Schreiben nach runtime/tmp/ → download-Antwort
```

### 7.2 PDF-Export

```
Request: POST /admin/v1/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + Inline-CSS + Kopfzeilen-Copyright + nicht entfernbarer Fußzeilen-Copyright
  → Dompdf rendert A4 quer
  → Schreiben nach runtime/tmp/ → download-Antwort
```

## 8. Deployment-Architektur

### 8.1 Empfohlene Topologie

```
Nginx (:443 HTTPS) → webman-Worker × N (:8788) → MySQL + ES + Redis
                     statische Dateien: Flutter-Web-Build/
```

### 8.2 Docker Compose (empfohlen für Produktion)

Die `docker-compose.yml` im Projektstamm orchestriert alle Dienste der obigen Topologie:

| Dienst | Image/Build | Port | Beschreibung |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse-Proxy + statische Dateien + Gzip |
| `app` | lokaler `Dockerfile`-Build | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Hauptdatenbank, Datenvolumen-Persistenz |
| `redis` | redis:7-alpine | 6379 | Cache / Rate-Limit / Captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Volltextsuche |

Vor dem Start die Schlüssel `JWT_SECRET_KEY`, `HASHIDS_SALT`, `ENCRYPTION_KEY` usw. in der `docker-compose.yml` durch zufällige Zeichenketten ersetzen.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

Die GitHub-Actions-Continuous-Integration ist in `.github/workflows/ci.yml` definiert:
- PHP-Syntaxprüfung (`php -l`)
- PHPUnit-Unit-Tests
- Flutter-Statische Analyse (`flutter analyze`)

### 8.4 Datenbank-Backup

`database/backup/backup.sh` — mysqldump + gzip-Backup, löscht automatisch Backups älter als 30 Tage.
`database/backup/restore.sh` — interaktive Auswahl und Wiederherstellung von Backups.

### 8.5 Monitoring

Der Endpunkt `GET /metrics` (`MetricsController`) legt im Prometheus-Textformat 5 gauge-Metriken offen: Gesamtzahl der HTTP-Requests, Anzahl aktiver Benutzer, Verbindungsstatus Datenbank/Redis, Speichernutzung.

### 8.6 Umgebungsanforderungen

| Komponente | Mindestversion | Empfohlene Konfiguration |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache aktiviert |
| MySQL | 8.0+ | 8.0+ Master-Slave-Replikation |
| Elasticsearch | 7.x | 8.x 3-Knoten-Cluster |
| Redis | 6.x | 7.x Sentinel-Modus |
| Nginx | 1.20+ | Reverse-Proxy + gzip + SSL |
| Flutter SDK | 3.41+ | neueste stabile Version |
| HarmonyOS | API 12 | DevEco Studio 5.x |
