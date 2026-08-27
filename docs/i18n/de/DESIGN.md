# Open-Admin — Design-Dokument

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Detaillierte Mermaid-Architekturdiagramme siehe [ARCHITECTURE.md](ARCHITECTURE.md) (werden von GitHub/GitLab/VS Code automatisch gerendert).

## 1. Systemarchitektur

> **Funktionsliste**: Authentifizierung(login/register/refresh/logout + Kontosperrung + Sitzungsbegrenzung) | Dashboards(Redis-Cache) | Benutzer-CRUD+Batch+Import | Rollen & Berechtigungen(RBAC) | Systemkonfiguration | Betriebsprüfung(8 Plattform-Quellgeräte) | Dateien(Upload+Export+Maskierung) | Sicherheit(18 Ebenen Verteidigung) | Betrieb(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend-Architektur

### 2.1 Schichten-Design

| Schicht | Verzeichnis | Zuständigkeit |
|---|------|------|
| Routing | `config/route.php` | Zuordnung URL zu Controller, Middleware-Bindung, versionierte Routen |
| Middleware | `app/middleware/` | Angriffsabwehr (SecurityFilter), Rate-Limit (RateLimit), Authentifizierung (JWT), Autorisierung (RBAC), API-Version (ApiVersion) |
| Controller | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (Admin) + Captcha/Auth (API v1) | Validierung der Request-Parameter, Aufruf der Businesslogik, Antwortformatierung |
| Business-Services | `app/service/` | Wiederverwendbare Businesslogik (reserviert) |
| Datenmodelle | `app/model/` | ORM-Zuordnung, Beziehungen, Feld-Verschlüsselung |
| Gemeinsame Tools | `app/common/` | Hashids-, Snowflake-, Encryption-Services |

### 2.2 Request-Lebenszyklus

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID-Lebenszyklus

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Datenverschlüsselungs-System

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Datenbankdesign

### 3.1 ER-Beziehungen

```
erik_admin_user ──┬── erik_admin_user_role ──┬── erik_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erik_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erik_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erik_operation_log
             (操作日志)

erik_system_config (系统配置) — 独立表
```

### 3.2 Kern-Tabellenstruktur

| Tabellenname | Feldanzahl | Beschreibung |
|------|-------|------|
| `erik_admin_user` | 14 | Verwaltungsbenutzer, phone/email/id_card verschlüsselt gespeichert, Soft-Delete unterstützt |
| `erik_admin_role` | 7 | Rollen, slug eindeutig |
| `erik_admin_permission` | 10 | Berechtigungsbaum (parent_id Selbstreferenz), type: 1=Menü 2=Button 3=API |
| `erik_admin_user_role` | 2 | viele-zu-viele-Zwischentabelle Benutzer-Rolle |
| `erik_admin_role_permission` | 2 | viele-zu-viele-Zwischentabelle Rolle-Berechtigung |
| `erik_system_config` | 8 | Schlüssel-Wert-Konfiguration, group+key kombiniert eindeutig |
| `erik_operation_log` | 9 | Betriebsprüfprotokoll (einschließlich source-Quellgerät) |

### 3.3 Primärschlüssel-Konvention

- Typ: `BIGINT UNSIGNED NOT NULL`
- Eigenschaft: **nicht auto-increment**, wird von der Snowflake-Algorithmus-Anwendungsschicht erzeugt
- Vorteile: global eindeutig, verteilungsfreundlich, trendmäßig aufsteigend günstig für Indizes, setzt das Geschäftsvolumen nicht offen
- Konfiguration: datacenter_id(0-31) + worker_id(0-31), unterstützt 1024 Knoten parallel

## 4. API-Design

### 4.1 URL-Konvention

```
Öffentliche Schnittstellen:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Admin:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

Ressourcen-Routen:
  GET    /admin/user          → Liste
  POST   /admin/user          → Erstellen
  GET    /admin/user/{hashid} → Details
  PUT    /admin/user/{hashid} → Aktualisieren
  DELETE /admin/user/{hashid} → Löschen (erfordert Passwortbestätigung)

Systemkonfiguration:  /admin/config[/{hashid}]
Betriebsprotokoll:  /admin/log
Persönlicher Bereich:  /admin/profile[/password|/logout]
Import:     /admin/import/users
Upload:     /admin/upload
Batch:     /admin/user/batch/{destroy|status}
Dokumentation:     /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 API-Versionsstrategie

Die API-Version wird über den Request-Header gesteuert, **nicht im URL-Pfad abgebildet**:

```http
API-Version: v1
```

| Mechanismus | Beschreibung |
|------|------|
| Standardversion | ohne `API-Version`-Header Standard `v1` |
| Validierung | `ApiVersion`-Middleware prüft, nicht unterstützte Versionen liefern 400 |
| Routing | Hilfsfunktion `v()` löst die Controller-Klasse dynamisch nach Version auf |
| Verzeichnis | Controller nach Version organisiert: `app/api/{version}/controller/` |

Erweiterungsbeispiel — neue v2-API:
1. `app/api/v2/controller/AuthController.php` erstellen
2. In der `ApiVersion`-Middleware der Konstanten `SUPPORTED` `'v2'` hinzufügen
3. Routendefinitionen müssen nicht geändert werden

```bash
# v1 verwenden
curl -H "API-Version: v1" /api/auth/login

# v2 verwenden
curl -H "API-Version: v2" /api/auth/login

# nicht übergeben, Standard v1
curl /api/auth/login
```

### 4.3 Rate-Limit-Strategie

Basiert auf dem Redis-Sorted-Set-Sliding-Window-Algorithmus, Ausführung als atomares Lua-Skript:

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Mal/Minute/IP/Routing |
| POST /api/auth/login | 10 Mal/Minute |
| POST /api/auth/register | 5 Mal/Minute |

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
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Berechtigungsmodell (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Zweite Bestätigung bei sensiblen Operationen

Sensible Operationen wie das Löschen von Benutzern, Rollen und Berechtigungen erfordern die Übermittlung des aktuellen Benutzerpassworts im Request-Body zur Identitätsprüfung:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

Das Frontend zeigt vor dem Auslösen der Löschoperation einen Bestätigungsdialog an, sammelt das Benutzerpasswort und sendet dann den Request.

## 5. Frontend-Design

### 5.1 Flutter-Web-Verwaltungsoberfläche

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
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
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
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
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF-Export

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Deployment-Architektur

### 8.1 Empfohlene Topologie

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (empfohlen für Produktion)

Die `docker-compose.yml` im Projektstamm orchestriert alle Dienste der obigen Topologie:

| Dienst | Image/Build | Port | Beschreibung |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse-Proxy + statische Dateien + Gzip |
| `app` | lokaler `Dockerfile`-Build | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Hauptdatenbank, Datenvolumen-Persistenz |
| `redis` | redis:7-alpine | 6379 | Cache / Rate-Limit / Captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Volltextsuche |

Vor dem Start die Schlüssel `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` usw. in der `docker-compose.yml` durch zufällige Zeichenketten ersetzen.

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
