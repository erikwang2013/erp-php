# API-Referenz

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API-Dokumentation

Das Projekt erzeugt mit [erikwang2013/apidoc-php](https://github.com/erikwang2013/apidoc-php) automatisch eine interaktive API-Dokumentation.

**Zugriff:** Nach dem Start des Dienstes `http://localhost:8788/apidoc` aufrufen

**Dokumentationsgruppen:**
| Gruppe | Beschreibung | Anzahl Module |
|------|------|--------|
| Admin-Schnittstellen (Admin) | Alle Schnittstellen des Backend-Verwaltungssystems | 25 Module |
| Client-Schnittstellen (Service API) | Leichtgewichtige Schnittstellen für Mobil-/Web-Clients | 3 Module |

**Globale Request-Header:**
| Request-Header | Beschreibung |
|--------|------|
| `Authorization` | JWT-Bearer-Token |
| `Accept-Language` | Sprache der Internationalisierung, unterstützt 13 Sprachen (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), Standard `zh_CN` |

> **Versionshinweis**: Versionspfade auf der gesamten Seite — Admin `/admin/v1`, Client `/api/v1`, offene Schnittstelle `/open/v1`;
> die Versionsnummer liegt im URL-Pfad, **es gibt keinen Versions-Request-Header**; Ausnahmen: `GET /api/docs` (OpenAPI-Dokumentation) und
> der Spediteur-Tracking-Callback `/api/tms/tracking/callback` (HMAC-Signatur, ohne Version).

**Annotations-Konvention:** Alle Controller-Methoden verwenden die `@Apidoc\*`-Annotationsserie zur Kennzeichnung von Schnittstellenname, Beschreibung, URL, HTTP-Methode, Parametern und Antwortstruktur.

## 1. Überblick

Das Open-Admin-Backend (open-admin) basiert auf webman v2 und bietet RESTful-JSON-APIs. Pfadversionierung auf der gesamten Seite: Die Admin-Schnittstellen hängen unter `/admin/v1` (JWT-Authentifizierung + RBAC-Berechtigungsprüfung), die Client-Schnittstellen unter `/api/v1`, die offenen Schnittstellen unter `/open/v1`; die Versionsnummer geht in den URL-Pfad ein, es gibt keinen Versions-Request-Header.

- **Basis-URL**: `http://localhost:8788`
- **Versionierung**: Pfadversionierung auf der gesamten Seite, die Versionsnummer liegt im URL-Pfad (Admin `/admin/v1`, Client `/api/v1`, offene Schnittstelle `/open/v1`), kein Versions-Request-Header

> **Endpunktübersicht**: Authentifizierung(5) | Dashboard(1) | Benutzer(7) | Rollen(4) | Berechtigungen(4) | Konfiguration(4) | Protokolle(1) | persönlicher Bereich(3) | Import/Export(3) | Upload(1) | Betrieb(4: health/metrics/docs/security.txt) | insgesamt 37 Endpunkte
- **Authentifizierung**: `Authorization: Bearer <token>` (JWT)
- **Antwortformat**: `{ "code": 0, "message": "success", "data": {...} }`
- **Dokumentations-Endpunkt**: `GET /api/docs` liefert die OpenAPI-3.0-JSON-Spezifikation

### Internationalisierung

Die API wechselt die Sprache automatisch über den Request-Header `Accept-Language` und unterstützt 13 Sprachen: `zh_CN` (Chinesisch, Standard), `en` (English), `ja` (日本語), `ko` (한국어), `de` (Deutsch), `fr` (Français), `es` (Español), `pt` (Português), `ru` (Русский), `ar` (العربية), `hi` (हिन्दी), `bn` (বাংলা), `id` (Bahasa Indonesia).

| Erstes Sprach-Tag im Request-Header | Aufgelöst zu |
|---------|------|
| `zh`, `zh-CN`, `zh-TW` | `zh_CN` Chinesisch (Standard) |
| `en`, `en-US` | `en` English |
| `ja` / `ko` / `de` / `fr` / `es` / `pt` / `ru` / `ar` / `hi` / `bn` / `id` | die jeweilige Sprache (Regionssuffixe werden ignoriert, z. B. `de-DE` → `de`) |

Auflösungsregeln (`app/common/I18n.php` `getLocale()`):

- Der Browser sortiert die Tags nach absteigender Sprachpräferenz; **nur das erste Tag** (der Teil vor dem Komma) wird verwendet, q-Werte werden nicht ausgewertet;
- Regions-Subtags werden ignoriert, nur das Hauptsprachen-Subtag bleibt erhalten (`zh-CN` → `zh`, `de-DE` → `de`);
- `zh*` wird immer auf `zh_CN` abgebildet; alle anderen Hauptsprachen-Subtags werden unverändert verwendet;
- Fehlt der Request-Header oder ist er leer, gilt `config('translation.locale')` = `zh_CN`.

Rückfallkette (`trans()`): angeforderte Sprache → `zh_CN` → `en` → der Schlüssel selbst wird zurückgegeben (Englisch ist der Schlüssel, der Schlüssel ist also der englische Originaltext). **`en` nimmt nicht an der Rückfallkette teil** — bei einer Anfrage mit `en` wird nur das `en`-Wörterbuch durchsucht, ein Nichttreffer gibt direkt den Schlüssel (englischer Originaltext) zurück und fällt nicht auf Chinesisch zurück.

```bash
# Englische Antwort
curl -H "Accept-Language: en" http://localhost:8788/admin/v1/product

# Japanische Antwort
curl -H "Accept-Language: ja" http://localhost:8788/admin/v1/product

# Chinesische Antwort (Standard)
curl http://localhost:8788/admin/v1/product
```

Das Feld `message` in der Antwort wird in der entsprechenden Sprache zurückgegeben. Die Wörterbuchdateien liegen unter `resource/translations/<locale>/{common,modules,validation}.php`.

### Anforderungsregeln

- Nur die Methoden `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` sind erlaubt; andere HTTP-Methoden (z. B. TRACE, CONNECT, PATCH) liefern 405
- Alle `POST` / `PUT`-Anfragen müssen `Content-Type: application/json` setzen (außer Datei-Upload), sonst 415
- Der Request-Body darf 10MB nicht überschreiten, sonst 413
- Der Sicherheitsfilter scannt alle Eingaben auf XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; bei Treffer wird 403 zurückgegeben
- 5 aufeinanderfolgende fehlgeschlagene Logins lösen eine Kontosperrung aus (15 Minuten); während der Sperrung liefern Login-Anfragen 429
- Ein Benutzer darf gleichzeitig maximal 3 gültige Token besitzen; bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt

## 2. Fehlercodes

| code | Bedeutung | Auslöser |
|------|------|---------|
| 0 | Erfolg | |
| 400 | Ungültige Anfrageparameter | Anfrageformat nicht korrekt |
| 401 | Nicht authentifiziert | Token fehlt / abgelaufen / auf der Blacklist |
| 403 | Keine Berechtigung / Sicherheitsintervention | Unzureichende RBAC-Berechtigung / SecurityFilter ausgelöst |
| 404 | Ressource nicht gefunden | Das Ziel von Abfrage/Aktualisierung/Löschung existiert nicht |
| 405 | HTTP-Methode nicht erlaubt | Nur GET/POST/PUT/DELETE/OPTIONS/HEAD erlaubt, nicht standardkonforme Methoden werden direkt abgelehnt |
| 413 | Request-Body zu groß | Content-Length über 10MB |
| 415 | Nicht unterstützter Medientyp | POST/PUT-Request mit nicht-JSON Content-Type und kein Datei-Upload |
| 422 | Parameter-Validierung fehlgeschlagen | Pflichtfelder fehlen, Formatfehler, Geschäftsvalidierung nicht bestanden |
| 429 | Zu viele Anfragen | RateLimit ausgelöst / Kontosperrung (5 aufeinanderfolgende Login-Fehler sperren 15 Minuten) |
| 500 | Interner Serverfehler | |

## 3. Öffentliche Endpunkte

Alle öffentlichen Endpunkte hängen unter der Gruppe `/api/v1` (die Versionsnummer geht in den URL-Pfad ein, es gibt weder einen Versions-Request-Header noch eine Versions-Middleware), die Controller sind direkt an das Verzeichnis gebunden (z. B. `app\api\v1\controller\AuthController`).

### 3.1 Health-Check

```
GET /health
```

- **Authentifizierung**: keine
- **Rate-Limit**: keines

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Werte von `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` liefert `"unavailable"`, wenn ES nicht erreichbar ist; ist der Cluster-Gesundheitsstatus weder green noch yellow, wird der tatsächliche Statuswert zurückgegeben (z. B. `"red"`).

### 3.2 API-Dokumentation

```
GET /api/docs
```

- **Authentifizierung**: keine
- **Rate-Limit**: globaler Standard (60/Minute)
- **Antwort**: OpenAPI-3.0.3-JSON-Spezifikation mit allen Endpunktdefinitionen, Parametern und Schemas

### 3.3 Klick-Captcha erzeugen

```
POST /api/v1/captcha/generate
```

- **Authentifizierung**: keine
- **Version**: URL-Pfad enthält /api/v1, kein Versions-Request-Header
- **Rate-Limit**: globaler Standard (60/Minute)

**Request-Body**:
```json
{
  "difficulty": "medium"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| difficulty | string | nein | `easy` / `medium` / `hard`, Standard `medium` |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| key | string | Captcha-Kennung, wird bei der Prüfung zurückgesendet |
| image | string | base64-codiertes PNG-Bild |
| extra.targets[].order | int | Klickreihenfolge |
| extra.targets[].text | string | Anzeigetext des Klickziels |

### 3.4 Klick-Captcha prüfen

```
POST /api/v1/captcha/verify
```

- **Authentifizierung**: keine
- **Version**: URL-Pfad enthält /api/v1, kein Versions-Request-Header
- **Rate-Limit**: globaler Standard (60/Minute)

**Request-Body**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| key | string | ja | Captcha-Key, von generate zurückgegeben |
| clicks | array{object} | ja | Array von Klickkoordinaten, jedes Element mit `x` (int) und `y` (int) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Bei Validierungsfehler ist `code` 422, `message` ist `"验证失败，请重试"` und `data.valid` ist `false`.

### 3.5 Login

```
POST /api/v1/auth/login
```

- **Authentifizierung**: keine
- **Version**: URL-Pfad enthält /api/v1, kein Versions-Request-Header
- **Rate-Limit**: 10/Minute (nach IP + Pfad)

**Request-Body**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername |
| password | string | ja | min:6, max:32 | Passwort |
| captcha_key | string | ja | | Captcha-Key |
| clicks | array{object} | ja | min:2 | Array von Klickkoordinaten |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| access_token | string | JWT-Zugriffstoken |
| refresh_token | string | JWT-Refresh-Token |
| expires_in | int | Gültigkeitsdauer des Zugriffstokens (Sekunden), Standard 7200 |
| user.id | string | hashid-verschlüsselte Benutzer-ID |
| user.username | string | Benutzername |
| user.real_name | string | Echter Name |

**Mögliche Fehler**:
- 422: Parameter-Validierung fehlgeschlagen (Pflichtfelder fehlen, Formatfehler)
- 422: Captcha falsch, bitte erneut versuchen
- 401: Benutzername oder Passwort falsch
- 403: Konto wurde deaktiviert
- 429: Konto gesperrt, bitte in 15 Minuten erneut versuchen (ausgelöst durch 5 aufeinanderfolgende Login-Fehler)

### 3.6 Registrierung

```
POST /api/v1/auth/register
```

- **Authentifizierung**: keine
- **Version**: URL-Pfad enthält /api/v1, kein Versions-Request-Header
- **Rate-Limit**: 5/Minute (nach IP + Pfad)
- **Schalter**: Standardmäßig deaktiviert (`REGISTRATION_ENABLED=0`); bei deaktiviert liefert die Schnittstelle 403; muss in `.env` explizit aktiviert werden (`REGISTRATION_ENABLED=1`)

**Request-Body**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 | Passwort (bcrypt-Hash gespeichert) |
| real_name | string | ja | max:50 | Echter Name |
| captcha_key | string | ja | | Captcha-Key |
| clicks | array{object} | ja | min:2 | Array von Klickkoordinaten |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Nach erfolgreicher Registrierung werden direkt JWT-Tokens zurückgegeben; der Benutzerstatus ist standardmäßig aktiviert (status=1). Der Endpunkt ist nur verfügbar, wenn `REGISTRATION_ENABLED=1` gesetzt ist.

### 3.7 Token-Refresh

```
POST /api/v1/auth/refresh
```

- **Authentifizierung**: keine
- **Version**: URL-Pfad enthält /api/v1, kein Versions-Request-Header
- **Rate-Limit**: globaler Standard (60/Minute)

**Request-Body**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| refresh_token | string | ja | Beim Login/Registrierung erhaltenes refresh_token |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Bei erfolgreichem Refresh werden neue access_token und refresh_token zurückgegeben; das alte Token verfällt automatisch. Beim Refresh werden auch letzter Login-Zeitpunkt und IP des Benutzers aktualisiert.

**Mögliche Fehler**:
- 422: Refresh-Token fehlt
- 401: Refresh-Token ungültig oder abgelaufen

### 3.8 Prometheus-Monitoring-Metriken

```
GET /metrics
```

- **Authentifizierung**: keine
- **Rate-Limit**: keines
- **Antwortformat**: Prometheus-Textformat (`text/plain; version=0.0.4`)

Öffentlicher Endpunkt für Prometheus-Monitoring-Metriken, abrufbar von Grafana/Prometheus.

**Beispielantwort**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Metrikname | Typ | Beschreibung |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Gesamtzahl der HTTP-Anfragen |
| `openadmin_active_users` | gauge | Anzahl aktiver Benutzer (Login innerhalb von 24 Stunden) |
| `openadmin_db_connection_status` | gauge | Datenbankverbindungsstatus, 1=ok, 0=Fehler |
| `openadmin_redis_connection_status` | gauge | Redis-Verbindungsstatus, 1=ok, 0=Fehler |
| `openadmin_memory_usage_bytes` | gauge | Aktuelle Speichernutzung des PHP-Prozesses (bytes) |

## 4. Dashboard

Alle Admin-Schnittstellen hängen unter der Gruppe `/admin/v1` und durchlaufen die drei Middlewares `AdminAuth` (JWT-Authentifizierung), `AdminPermission` (RBAC-Berechtigungsprüfung) und `OperationLog` (Betriebsprotokoll).

### 4.1 Dashboard-Daten

```
GET /admin/v1/dashboard
```

- **Authentifizierung**: JWT + RBAC
- **Cache**: Redis 5 Minuten

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats-Feld | Typ | Beschreibung |
|------|------|------|
| label | string | Name der Kennzahl |
| value | string | Wert der Kennzahl (als Zeichenfolge) |
| icon | string | Material-Icon-Name |
| color | string | Kartenfarbe |
| trend | float? | Tagesvergleichswachstum (Prozent), nur "用户总数" hat dieses Feld |

| trends-Feld | Typ | Beschreibung |
|------|------|------|
| dates | array{string} | Datumsreihe der letzten 30 Tage |
| series | array{object} | Trendliniendaten, jede mit name (Name), data (Werte-Array), color (Farbe) |

## 5. Benutzerverwaltung

Alle von der Benutzerverwaltung zurückgegebenen `id` sind hashid-verschlüsselte Zeichenfolgen. Das Passwortfeld ist in Antworten ausgeschlossen. Mobilnummern und E-Mails werden in Listen-Schnittstellen maskiert und in Detail-Schnittstellen im Klartext zurückgegeben (verschlüsselte Datenbankfelder werden vom Encryptable-Trait automatisch entschlüsselt).

### 5.1 Benutzerliste

```
GET /admin/v1/user
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| keyword | string | nein | | Suchbegriff, gleicht Benutzername und echten Namen ab |
| status | int | nein | | Statusfilter, 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselte Benutzer-ID |
| username | string | Benutzername |
| real_name | string | Echter Name |
| phone | string | Maskierte Mobilnummer (Format `138****5678`) |
| email | string | Maskierte E-Mail (Format `a***@example.com`) |
| status | int | 1=aktiviert, 0=deaktiviert |
| last_login_at | string | Letzter Login-Zeitpunkt (datetime) |
| created_at | string | Erstellungszeitpunkt (datetime) |

### 5.2 Benutzer erstellen

```
POST /admin/v1/user
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| username | string | ja | min:3, max:50 | Benutzername (eindeutig) |
| password | string | ja | min:6, max:32 | Passwort (bcrypt gespeichert) |
| real_name | string | ja | max:50 | Echter Name |
| phone | string | nein | | Mobilnummer (Encryptable-verschlüsselt gespeichert) |
| email | string | nein | | E-Mail (Encryptable-verschlüsselt gespeichert) |
| status | int | nein | in:0,1 | Status, Standard 1 (aktiviert) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Mögliche Fehler**:
- 422: Benutzername existiert bereits
- 422: Parameter-Validierung fehlgeschlagen (Pflichtfelder fehlen)

### 5.3 Benutzerdetails

```
GET /admin/v1/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

In der Detail-Schnittstelle werden `phone` und `email` im Klartext zurückgegeben (in der Datenbank verschlüsselt gespeichert, automatisch vom Encryptable-Cast entschlüsselt), nicht maskiert. `password` und `id_card` erscheinen niemals in Antworten.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.4 Benutzer aktualisieren

```
PUT /admin/v1/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID

**Request-Body**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name, ohne Angabe bleibt der bisherige Wert |
| password | string | nein | Neues Passwort; leer oder nicht angegeben = keine Änderung |
| phone | string | nein | Mobilnummer |
| email | string | nein | E-Mail |
| status | int | nein | 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Mögliche Fehler**:
- 404: Benutzer existiert nicht

### 5.5 Benutzer löschen

```
DELETE /admin/v1/user/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Pfadparameter**: `{id}` ist die hashid-verschlüsselte Benutzer-ID
- **Sensible Operation**: erfordert Passwort-Bestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (zweite Bestätigung) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Es wird ein Soft Delete ausgeführt (Eloquent SoftDeletes); die Daten werden mit deleted_at markiert, nicht physisch gelöscht.

**Mögliche Fehler**:
- 404: Benutzer existiert nicht
- 422: Sensible Operation erfordert Passwort-Eingabe (password leer)
- 422: Passwort-Verifizierung fehlgeschlagen (Passwort stimmt nicht)

### 5.6 Benutzer massenweise löschen

```
POST /admin/v1/user/batch/destroy
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Operation**: erfordert Passwort-Bestätigung

**Request-Body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array hashid-verschlüsselter Benutzer-IDs |
| password | string | ja | Passwort des aktuell angemeldeten Benutzers (zweite Bestätigung) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Es wird ein Soft Delete ausgeführt; `data.count` ist die tatsächlich gelöschte Anzahl.

**Mögliche Fehler**:
- 422: Bitte Benutzer zum Löschen auswählen (ids leer)
- 422: Ungültige ID (hashid-Dekodierung fehlgeschlagen)
- 422: Passwort-Verifizierung fehlgeschlagen

### 5.7 Benutzer massenweise aktivieren/deaktivieren

```
POST /admin/v1/user/batch/status
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| ids | array{string} | ja | Array hashid-verschlüsselter Benutzer-IDs |
| status | int | ja | 0=deaktiviert, 1=aktiviert |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message ändert sich dynamisch je nach status-Wert zu `"批量启用成功"` oder `"批量禁用成功"`.

**Mögliche Fehler**:
- 422: Bitte Benutzer auswählen (ids leer)
- 422: Ungültiger Statuswert (status ist weder 0 noch 1)

## 6. Rollenverwaltung

### 6.1 Rollenliste

```
GET /admin/v1/role
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselte Rollen-ID |
| name | string | Rollenname |
| slug | string | Rollenkennung (eindeutig, für Berechtigungsprüfung) |
| description | string | Rollenbeschreibung |
| status | int | 1=aktiviert, 0=deaktiviert |
| users_count | int | Anzahl der Benutzer mit dieser Rolle |

### 6.2 Rolle erstellen

```
POST /admin/v1/role
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| name | string | ja | max:50 | Rollenname |
| slug | string | ja | max:50 | Rollenkennung |
| description | string | nein | | Rollenbeschreibung, Standard leere Zeichenfolge |
| status | int | nein | | Status, Standard 1 |
| permission_ids | array{int} | nein | | Array von Berechtigungs-IDs (rohe INT-IDs, keine hashids) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Rolle aktualisieren

```
PUT /admin/v1/role/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Rollenname |
| description | string | nein | Beschreibung |
| status | int | nein | 0=deaktiviert, 1=aktiviert |
| permission_ids | array{int} | nein | Array von Berechtigungs-IDs; bei Angabe werden die Rollenberechtigungen synchronisiert (überschrieben) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Rolle löschen

```
DELETE /admin/v1/role/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Operation**: erfordert Passwort-Bestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden automatisch alle Verknüpfungen der Rolle mit Berechtigungen und Benutzern aufgehoben und der Rollen-Datensatz anschließend physisch gelöscht.

## 7. Berechtigungsverwaltung

Berechtigungen sind baumförmig strukturiert (parent_id-Selbstreferenz) und in drei Typen unterteilt. Die Listen-Schnittstelle liefert den vollständigen Berechtigungsbaum.

### 7.1 Berechtigungsbaum

```
GET /admin/v1/permission
```

- **Authentifizierung**: JWT + RBAC

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/v1/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/v1/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid-verschlüsselt |
| parent_id | string | hashid der übergeordneten Berechtigung, "0" bedeutet Wurzelknoten |
| name | string | Berechtigungsname |
| slug | string | Berechtigungskennung (Route/Button-Kennung) |
| type | int | 1=Menü, 2=Button, 3=Schnittstelle |
| icon | string | Menü-Icon (Material-Icon-Name) |
| path | string | Frontend-Routing-Pfad |
| sort | int | Sortierwert (aufsteigend) |
| children | array? | Liste der Unterberechtigungen (rekursiv); ohne Kindknoten fehlt dieses Feld |

### 7.2 Berechtigung erstellen

```
POST /admin/v1/permission
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/v1/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| parent_id | int | nein | | ID der übergeordneten Berechtigung (roher INT-Typ), Standard 0 |
| name | string | ja | max:50 | Berechtigungsname |
| slug | string | ja | max:100 | Berechtigungskennung |
| type | int | ja | in:1,2,3 | 1=Menü, 2=Button, 3=Schnittstelle |
| icon | string | nein | | Menü-Icon, Standard leer |
| path | string | nein | | Frontend-Routing-Pfad, Standard leer |
| sort | int | nein | | Sortierwert, Standard 0 |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/v1/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Berechtigung aktualisieren

```
PUT /admin/v1/permission/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| name | string | nein | Berechtigungsname |
| icon | string | nein | Icon |
| path | string | nein | Routing-Pfad |
| sort | int | nein | Sortierwert |

### 7.4 Berechtigung löschen

```
DELETE /admin/v1/permission/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Operation**: erfordert Passwort-Bestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Beim Löschen werden alle Unterberechtigungen kaskadiert gelöscht (Datensätze mit `parent_id` = ID der aktuellen Berechtigung) und die Verknüpfungen mit allen Rollen aufgehoben.

## 8. Systemkonfiguration

Systemkonfigurationen sind über die Kombination `group` + `key` eindeutig.

### 8.1 Konfigurationsliste

```
GET /admin/v1/config
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| group | string | nein | | Filter nach Konfigurationsgruppe |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| group | string | Konfigurationsgruppe (z. B. `system`, `email`, `storage`) |
| key | string | Konfigurationsschlüssel |
| value | string | Konfigurationswert |
| type | string | Hinweis auf den Werttyp (`string`, `integer`, `boolean`, `json` usw.) |
| description | string | Konfigurationsbeschreibung |

### 8.2 Konfiguration erstellen

```
POST /admin/v1/config
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| group | string | ja | max:100 | Konfigurationsgruppe |
| key | string | ja | max:100 | Konfigurationsschlüssel (innerhalb der Gruppe eindeutig) |
| value | string | ja | | Konfigurationswert |
| type | string | nein | | Werttyp, Standard `string` |
| description | string | nein | | Konfigurationsbeschreibung, Standard leer |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Mögliche Fehler**:
- 422: Konfigurationseintrag existiert bereits (gleiche group + key)

### 8.3 Konfiguration aktualisieren

```
PUT /admin/v1/config/{id}
```

- **Authentifizierung**: JWT + RBAC

**Request-Body**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| value | string | nein | Neuer Konfigurationswert |
| type | string | nein | Neuer Werttyp |
| description | string | nein | Neuer Beschreibungstext |

### 8.4 Konfiguration löschen

```
DELETE /admin/v1/config/{id}
```

- **Authentifizierung**: JWT + RBAC
- **Sensible Operation**: erfordert Passwort-Bestätigung

**Request-Body**:
```json
{
  "password": "admin_password"
}
```

Löscht den Konfigurationsdatensatz physisch.

## 9. Betriebsprotokoll

Das Betriebsprotokoll ist eine reine Lese-Schnittstelle, die von der `OperationLog`-Middleware bei jeder POST/PUT/DELETE-Anfrage automatisch geschrieben wird; gespeicherte Felder: `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Betriebsprotokoll-Liste

```
GET /admin/v1/log
```

- **Authentifizierung**: JWT + RBAC

**Abfrageparameter**:

| Parameter | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| page | int | nein | 1 | Seitennummer |
| limit | int | nein | 15 | Einträge pro Seite |
| user_id | int | nein | | Exakte Filterung nach Benutzer-ID (roher INT-Typ) |
| action | string | nein | | Exakte Filterung nach Aktion |
| path | string | nein | | Unscharfe Filterung nach Anfragepfad |
| start_date | string | nein | | Startdatum (Format Y-m-d) |
| end_date | string | nein | | Enddatum (Format Y-m-d) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| id | string | hashid |
| user_name | string | Name des ausführenden Benutzers (über user-Verknüpfung ermittelt; bei nicht angemeldeten Operationen wird "系统" angezeigt) |
| action | string | Beschreibung der Aktion |
| method | string | HTTP-Methode (POST/PUT/DELETE) |
| path | string | Anfragepfad |
| ip | string | Client-IP |
| source | string | Anfragequelle |
| input | string | JSON-Zeichenfolge der Anfrageparameter (ohne Dateien) |
| created_at | string | Zeitpunkt der Operation (datetime) |

## 10. Persönlicher Bereich

Die Schnittstellen des persönlichen Bereichs benötigen nur JWT-Authentifizierung (keine RBAC-Berechtigungsprüfung — die `AdminPermission`-Middleware sollte sie in die Whitelist aufnehmen).

### 10.1 Persönliche Daten aktualisieren

```
PUT /admin/v1/profile
```

- **Authentifizierung**: JWT

**Request-Body**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| real_name | string | nein | Echter Name |
| phone | string | nein | Mobilnummer (Encryptable-verschlüsselt gespeichert) |
| email | string | nein | E-Mail (Encryptable-verschlüsselt gespeichert) |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

In der Antwort werden `phone` und `email` im Klartext zurückgegeben; `password` und `id_card` sind entfernt.

### 10.2 Passwort ändern

```
PUT /admin/v1/profile/password
```

- **Authentifizierung**: JWT

**Request-Body**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Feld | Typ | Pflicht | Validierungsregeln | Beschreibung |
|------|------|------|---------|------|
| old_password | string | ja | | Aktuelles Passwort |
| new_password | string | ja | min:6, max:32 | Neues Passwort |

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Mögliche Fehler**:
- 422: Bitte altes und neues Passwort angeben
- 422: Altes Passwort falsch
- 422: Neues Passwort muss 6-32 Zeichen lang sein

### 10.3 Logout

```
POST /admin/v1/profile/logout
```

- **Authentifizierung**: JWT

**Request-Body**: keiner (kein requestBody, Token wird aus dem Authorization-Header gelesen)

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logout-Logik: JWT dekodieren, verbleibende Gültigkeit (exp - now) ermitteln, den md5-Hash des Tokens mit TTL = verbleibender Gültigkeit in die Redis-Blacklist `jwt_blacklist:{md5}` schreiben. Tokens auf der Blacklist werden in der `AdminAuth`-Middleware abgefangen und liefern 401.

Ohne Token wird 401 zurückgegeben. Bei abgelaufenem/ungültigem Token (Dekodierung wirft eine Ausnahme) gilt der Logout dennoch als erfolgreich.

## 11. Import und Export

### 11.1 Excel exportieren

```
POST /admin/v1/export/excel
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Datei-Download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Request-Body**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| table | string | nein | `admin_user` | Zu exportierende Tabelle. Unterstützt: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | nein | | Array der zu exportierenden Spaltennamen; leer = alle Spalten der Tabelle |
| conditions | object | nein | `{}` | Filterbedingungen, key-value-Paare; nicht-leere Werte werden in der WHERE verwendet |
| title | string | nein | `数据导出` | Excel-Titel (wird als Sheet-Name angezeigt) |

**Unterstützte Tabellen und Spalten**:

| table | verfügbare Spalten |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Sensible Felder `phone`, `email`, `id_card` werden beim Export automatisch maskiert. Obergrenze: 10.000 Zeilen. Erste Excel-Zeile ist fixiert, automatischer Filter aktiv.

### 11.2 PDF exportieren

```
POST /admin/v1/export/pdf
```

- **Authentifizierung**: JWT + RBAC
- **Antworttyp**: Datei-Download (`application/pdf`, A4 quer)

**Request-Body**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Oder Tabellenmodus:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Feld | Typ | Pflicht | Standardwert | Beschreibung |
|------|------|------|------|------|
| type | string | nein | `table` | Exporttyp: `table` / `dashboard` |
| title | string | nein | `数据导出` | PDF-Titel |
| data | object | nein | `{}` | Exportdaten |

Bei `type=dashboard` muss `data` ein `stats`-Array enthalten (Karten-Rendering); bei `type=table` muss `data` die Arrays `columns` und `rows` enthalten.

Die PDF-Vorlage enthält Copyright-Informationen und den Export-Zeitstempel.

### 11.3 Benutzer importieren (Excel)

```
POST /admin/v1/import/users
```

- **Authentifizierung**: JWT + RBAC
- **Requesttyp**: `multipart/form-data` (Datei-Upload)

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | Format `.xlsx` oder `.xls` |

**Excel-Spaltenanforderungen**:

| Spaltenname | Pflicht | Beschreibung |
|------|------|------|
| username | ja | Benutzername (eindeutig) |
| password | ja | Passwort (bcrypt-Hash gespeichert) |
| real_name | ja | Echter Name |
| phone | nein | Mobilnummer |
| email | nein | E-Mail |
| status | nein | Status, Standard 1 |

Zeile 1 enthält die Spaltenüberschriften (Groß-/Kleinschreibung egal), ab Zeile 2 folgen die Daten.

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Feld | Typ | Beschreibung |
|------|------|------|
| total | int | Gesamtzahl der Zeilen (ohne Überschriftenzeile) |
| success | int | Erfolgreich importierte Anzahl |
| failed | int | Anzahl der Fehlschläge |
| errors | array | Fehlerdetails, jede mit row (Excel-Zeilennummer) und reason (Fehlergrund) |

## 12. Datei-Upload

```
POST /admin/v1/upload
```

- **Authentifizierung**: JWT + RBAC
- **Requesttyp**: `multipart/form-data`

**Formularfelder**:

| Feld | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| file | file | ja | Hochzuladende Datei |

**Erlaubte Dateitypen**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Maximale Dateigröße**: 10MB

**Beispielantwort**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Dateien werden datumsweise in `public/upload/{Y-m-d}/` abgelegt; der Dateiname ist `md5(uniqid) + Originalendung`. `url` ist ein relativer Pfad zum Stammverzeichnis der Site.

**Mögliche Fehler**:
- 422: Bitte Datei auswählen (nichts hochgeladen)
- 422: Nicht unterstützter Dateityp
- 422: Dateigröße darf 10MB nicht überschreiten
- 500: Datei-Upload fehlgeschlagen (Datei ungültig)

## 13. Antwort-Header

Alle Schnittstellen (in der globalen Middleware-Schicht injiziert) enthalten folgende Antwort-Header:

| Header | Beschreibung |
|----|------|
| `X-RateLimit-Limit` | Rate-Limit-Obergrenze (Anzahl) |
| `X-RateLimit-Remaining` | Verbleibende Anfragen |
| `X-RateLimit-Reset` | Zeitstempel des Rate-Limit-Fenster-Reset |
| `Retry-After` | Nur bei ausgelöstem Rate-Limit; empfohlene Wartezeit in Sekunden |
| `X-Content-Type-Options` | `nosniff` (von webman standardmäßig gesetzt, verbietet MIME-Sniffing) |
| `X-Frame-Options` | `DENY` (von der CORS-Middleware/Basiskonfiguration von webman bereitgestellt) |

Rate-Limit-Details:
- Standardmäßige globale Begrenzung: 60/Minute / IP+Pfad
- Login-Endpunkt `/api/v1/auth/login`: 10/Minute
- Registrierungs-Endpunkt `/api/v1/auth/register`: 5/Minute
- Verwendet den atomaren Redis-Sliding-Window-Algorithmus (Lua ZSET), vermeidet TOCTOU-Races
- Bei Redis-Ausfall fail open (Anfragen durchlassen), blockiert keine Anfragen

## 14. Authentifizierungsablauf

Vollständige Authentifizierungssequenz:

```
1. 客户端请求 POST /api/v1/captcha/generate
   (URL-Pfad enthält /api/v1, kein Versions-Request-Header)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/v1/auth/login
   (URL-Pfad enthält /api/v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/v1/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/v1/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT-Struktur

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, Standard-TTL 7200 Sekunden (gesteuert über die JWT-Konfiguration `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, Standard-TTL 1209600 Sekunden (gesteuert über die JWT-Konfiguration `refresh_expire`, also 14 Tage)

### Sicherheitsverwaltung

- Passwörter werden als `PASSWORD_BCRYPT`-Hashes gespeichert
- Sensible Felder (phone, email, id_card) werden über `erikwang2013/encryptable` transparent auf Datenbankebene ver-/entschlüsselt
- IDs der API-Schicht werden über `erikwang2013/hashids` verschlüsselt übertragen, um die Offenlegung der rohen Snowflake-ID-Sequenz zu vermeiden
- Der SecurityFilter scannt global auf XSS, SQL-Injection, Pfad-Traversal und Befehlsinjektion; gleiche IP 5×/60 Sekunden → temporäre Blacklist für 15 Minuten
- Sensible Operationen (Löschen von Benutzern, Rollen, Berechtigungen, Konfigurationen) erfordern die Passwort-Bestätigung des aktuell angemeldeten Benutzers
- Begrenzung paralleler Sitzungen: maximal 3 gültige Token pro Benutzer; beim 4. Gerät wird das älteste Token zwangsweise auf die Blacklist gesetzt
- Kontosperrung: 5 aufeinanderfolgende fehlgeschlagene Logins lösen eine 15-minütige Sperre aus; während der Sperrung wird 429 zurückgegeben

## 15. Deployment & Betrieb

### Docker Compose

Im Projektstamm liegt `docker-compose.yml` mit 5 Diensten (Nginx, webman app, MySQL, Redis, Elasticsearch). PHP wird über `Dockerfile` gebaut (Basis `php:8.3-cli`, mit aktiviertem OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` definiert die GitHub-Actions-Pipeline für kontinuierliche Integration:
- `php -l` Syntaxprüfung
- PHPUnit-Unit-Tests
- `flutter analyze` Statische-Analyse

### Datenbank-Backup

Das Verzeichnis `database/backup/` stellt Backup- und Wiederherstellungsskripte bereit:
- `backup.sh` — mysqldump + gzip-komprimiertes Backup, löscht automatisch Backups, die älter als 30 Tage sind
- `restore.sh` — interaktive Wiederherstellung, listet vorhandene Backups zur Auswahl auf

### Nginx-Sicherheitskonfiguration

Für Produktionsumgebungen siehe `docs/nginx-security.conf` zur Härtung des Reverse-Proxys.

## 16. Geschäfts-API-Endpunkte (ERP)

Alle Geschäftsendpunkte hängen unter der Gruppe `/admin/v1` und durchlaufen die drei Middlewares `AdminAuth` (JWT-Authentifizierung), `AdminPermission` (RBAC-Berechtigungsprüfung) und `OperationLog` (Betriebsprotokoll).

> Endpunktanzahl: Artikel(17) | Einkauf(8) | Vertrieb(6) | Bestand(6) | Finanzen(17) | CRM(13) | Workflow(6) | Benachrichtigungen(4) | Projekte(3) | HR(9) | Produktion(7) | Berichte(4) | Dashboard(3) | Client(2) | insgesamt 105 Endpunkte

Endpunkte mit modulübergreifender Verknüpfung sind mit 🔗 markiert.

### 16.1 Artikelverwaltung (Product Management)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/product | Artikelliste (Seitennummerierung + Suche + Kategorie-/Statusfilter) |
| POST | /admin/v1/product | Artikel erstellen (inkl. SKU und Preise) |
| GET | /admin/v1/product/{id} | Artikeldetails (inkl. Kategorie/Marke/SKU/Preise/Einheiten) |
| PUT | /admin/v1/product/{id} | Artikel aktualisieren |
| DELETE | /admin/v1/product/{id} | Artikel löschen (Soft Delete, Passwort-Bestätigung erforderlich) |
| GET | /admin/v1/category | Kategorienliste (Baumstruktur) |
| POST | /admin/v1/category | Kategorie erstellen |
| PUT | /admin/v1/category/{id} | Kategorie aktualisieren |
| DELETE | /admin/v1/category/{id} | Kategorie löschen |
| GET | /admin/v1/brand | Markenliste |
| POST | /admin/v1/brand | Marke erstellen |
| GET | /admin/v1/warehouse | Lagerliste |
| POST | /admin/v1/warehouse | Lager erstellen |
| GET | /admin/v1/location | Lagerplatzliste |
| GET | /admin/v1/warehouse/{id}/locations | Lagerplätze eines Lagers |
| GET | /admin/v1/supplier | Lieferantenliste (ES-Suche) |
| POST | /admin/v1/supplier | Lieferant erstellen |
| GET | /admin/v1/customer | Kundenliste (ES-Suche) |
| POST | /admin/v1/customer | Kunde erstellen |

### 16.2 Einkaufsverwaltung (Purchase)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/purchase/apply | Liste der Einkaufsanfragen |
| POST | /admin/v1/purchase/apply | Einkaufsanfrage erstellen |
| GET | /admin/v1/purchase/order | Liste der Einkaufsbestellungen |
| POST | /admin/v1/purchase/order | Einkaufsbestellung erstellen |
| 🔗 POST | /admin/v1/purchase/receive | Wareneingang erstellen (automatische Einlagerung + Erzeugung von Verbindlichkeiten) |
| GET | /admin/v1/purchase/receive | Liste der Wareneingänge |
| GET | /admin/v1/purchase/receive/{id} | Details des Wareneingangs |
| POST | /admin/v1/purchase/return | Retoure erstellen |
| GET | /admin/v1/purchase/settlement | Liste der Lieferantenabrechnungen |

### 16.3 Vertriebsverwaltung (Sales)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/sales/quotation | Angebotsliste |
| POST | /admin/v1/sales/quotation | Angebot erstellen |
| GET | /admin/v1/sales/order | Liste der Verkaufsaufträge |
| POST | /admin/v1/sales/order | Verkaufsauftrag erstellen |
| 🔗 POST | /admin/v1/sales/delivery | Lieferschein erstellen (automatische Auslagerung + Erzeugung von Forderungen) |
| GET | /admin/v1/sales/delivery | Lieferscheinliste |
| GET | /admin/v1/sales/settlement | Liste der Kundenabrechnungen |

### 16.4 Bestandsverwaltung (Inventory)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/inventory | Echtzeitbestand (Dimensionen Lager/Lagerplatz/Charge/SKU) |
| GET | /admin/v1/inventory/flow | Ein-/Auslagerungs-Buchungen |
| GET | /admin/v1/inventory/transfer | Liste der Umlagerungen |
| POST | /admin/v1/inventory/transfer | Umlagerung erstellen |
| GET | /admin/v1/inventory/check | Liste der Inventuraufgaben |
| POST | /admin/v1/inventory/check | Inventuraufgabe erstellen |
| GET | /admin/v1/inventory/alert | Bestandswarnungsregeln |

### 16.5 Finanzverwaltung (Finance)

| Methode | Pfad | Beschreibung |
|------|------|------|
| POST | /admin/v1/finance/voucher | Buchungsbeleg erstellen |
| GET | /admin/v1/finance/ar-ap | Liste der Forderungen/Verbindlichkeiten |
| POST | /admin/v1/finance/receipt | Zahlungseingang erstellen |
| POST | /admin/v1/finance/payment | Zahlungsausgang erstellen |
| GET | /admin/v1/finance/cash-journal | Kassen- und Banktagebuch |
| GET | /admin/v1/finance/expense | Liste der Spesenabrechnungen |
| POST | /admin/v1/finance/expense | Spesenantrag einreichen |
| GET | /admin/v1/finance/report/profit | Gewinn- und Verlustrechnung |
| GET | /admin/v1/finance/general-ledger | Hauptbuch (Zusammenfassung nach Konto + Periode) |
| GET | /admin/v1/finance/subsidiary-ledger | Detailbuch (Kontodetails) |
| GET | /admin/v1/finance/report/balance-sheet | Bilanz (inkl. automatischer Erzeugung) |
| GET | /admin/v1/finance/report/cash-flow | Kapitalflussrechnung (Betrieb/Investition/Finanzierung) |
| GET | /admin/v1/finance/bank-account | Bankkontenliste |
| GET/POST/PUT/DELETE | /admin/v1/finance/asset | Anlagevermögen CRUD + Abschreibung |
| GET/POST | /admin/v1/finance/tax-rate | Steuersatzkonfiguration |
| GET | /admin/v1/finance/tax-record | Steueraufzeichnungen |
| GET/POST/PUT/DELETE | /admin/v1/finance/currency | Währungsverwaltung |
| GET/POST/PUT/DELETE | /admin/v1/finance/exchange-rate | Wechselkursverwaltung |
| GET/POST/PUT/DELETE | /admin/v1/finance/budget | Budgetverwaltung (inkl. Budget vs. Ist-Vergleich) |
| GET/POST/PUT/DELETE | /admin/v1/finance/cost-center | Kosten-Center (Baumstruktur) |
| GET/POST/PUT/DELETE | /admin/v1/finance/profit-center | Profit-Center (Baumstruktur) |

### 16.6 CRM

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/crm/opportunity | Liste der Chancen |
| POST | /admin/v1/crm/opportunity | Chance erstellen |
| GET | /admin/v1/crm/follow | Liste der Follow-up-Records |
| POST | /admin/v1/crm/follow | Follow-up-Record erstellen |
| GET | /admin/v1/crm/funnel | Funnel-Phasen-Konfiguration |
| GET | /admin/v1/crm/contact | Kontaktliste |
| POST | /admin/v1/crm/contact | Kontakt erstellen |
| GET | /admin/v1/crm/pool | Kundenliste des Shared-Pools |
| POST | /admin/v1/crm/pool/claim/{id} | Shared-Pool-Kunden übernehmen |
| POST | /admin/v1/crm/pool/release/{id} | Kunde in den Shared-Pool freigeben |
| GET/POST | /admin/v1/crm/pool/rules | CRUD der Shared-Pool-Regeln |
| GET | /admin/v1/crm/contract | Vertragsliste |
| POST | /admin/v1/crm/contract | Vertrag erstellen |
| GET | /admin/v1/crm/contract/{id} | Vertragsdetails |
| PUT | /admin/v1/crm/contract/{id} | Vertrag aktualisieren |
| DELETE | /admin/v1/crm/contract/{id} | Vertrag löschen |
| GET | /admin/v1/crm/quotation | CRM-Angebotsliste |
| POST | /admin/v1/crm/quotation | CRM-Angebot erstellen |
| POST | /admin/v1/crm/quotation/{id}/to-contract | 🔗 Angebot in Vertrag umwandeln |
| GET/POST/PUT/DELETE | /admin/v1/crm/campaign | Marketingkampagnen |
| GET/POST/PUT/DELETE | /admin/v1/crm/ticket | Service-Tickets |
| POST | /admin/v1/crm/ticket/{id}/assign | Ticket zuweisen |
| POST | /admin/v1/crm/ticket/{id}/resolve | Ticket lösen |
| GET/POST | /admin/v1/crm/analytics/report | Kundenanalyse-Berichte |
| GET/POST | /admin/v1/crm/analytics/metric | Analyse-Kennzahlen |

### 16.7 Genehmigungsworkflow (Workflow)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/workflow | Liste der Workflow-Definitionen |
| POST | /admin/v1/workflow | Workflow-Definition erstellen |
| GET | /admin/v1/workflow/{id} | Workflow-Details |
| PUT | /admin/v1/workflow/{id} | Workflow aktualisieren |
| DELETE | /admin/v1/workflow/{id} | Workflow löschen |
| POST | /admin/v1/workflow/{id}/submit | 🔗 Genehmigung einreichen (Genehmigungsinstanz erstellen) |
| POST | /admin/v1/approval/{id}/approve | Genehmigen |
| POST | /admin/v1/approval/{id}/reject | Ablehnen |
| POST | /admin/v1/approval/{id}/withdraw | Zurückziehen |
| ANY | /admin/v1/approval/my | Meine Genehmigungsliste (ausstehend/erledigt) |

### 16.8 Benachrichtigungen (Notification)

| Methode | Pfad | Beschreibung |
|------|------|------|
| ANY | /admin/v1/notification/my | Meine Benachrichtigungsliste (seitennummeriert, zeitlich absteigend) |
| POST | /admin/v1/notification/{id}/read | Einzelne als gelesen markieren |
| POST | /admin/v1/notification/read-all | Alle als gelesen markieren |
| ANY | /admin/v1/notification/unread-count | Anzahl ungelesener Nachrichten |

### 16.9 Projektmanagement (Project)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/project | Projektliste |
| POST | /admin/v1/project | Projekt erstellen |
| GET | /admin/v1/project/{id} | Projektdetails |
| PUT | /admin/v1/project/{id} | Projekt aktualisieren |
| DELETE | /admin/v1/project/{id} | Projekt löschen |
| GET | /admin/v1/project/task | Aufgabenliste |
| POST | /admin/v1/project/task | Aufgabe erstellen |
| PUT | /admin/v1/project/task/{id} | Aufgabe aktualisieren |
| DELETE | /admin/v1/project/task/{id} | Aufgabe löschen |
| GET | /admin/v1/project/timesheet | Liste der Zeitaufzeichnungen |
| POST | /admin/v1/project/timesheet | Arbeitszeit erfassen |
| PUT | /admin/v1/project/timesheet/{id} | Arbeitszeit aktualisieren |
| DELETE | /admin/v1/project/timesheet/{id} | Arbeitszeit löschen |

### 16.10 Personalverwaltung (HR)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/hr/department | Abteilungsliste (Baumstruktur) |
| POST | /admin/v1/hr/department | Abteilung erstellen |
| PUT | /admin/v1/hr/department/{id} | Abteilung aktualisieren |
| DELETE | /admin/v1/hr/department/{id} | Abteilung löschen |
| GET | /admin/v1/hr/employee | Mitarbeiterliste |
| POST | /admin/v1/hr/employee | Mitarbeiter erstellen |
| PUT | /admin/v1/hr/employee/{id} | Mitarbeiter aktualisieren |
| DELETE | /admin/v1/hr/employee/{id} | Mitarbeiter löschen |
| GET | /admin/v1/hr/position | Positionsliste |
| POST | /admin/v1/hr/position | Position erstellen |
| PUT | /admin/v1/hr/position/{id} | Position aktualisieren |
| DELETE | /admin/v1/hr/position/{id} | Position löschen |
| ANY | /admin/v1/hr/attendance | Anwesenheitsabfrage |
| POST | /admin/v1/hr/attendance/clock-in | Kommen stempeln |
| POST | /admin/v1/hr/attendance/clock-out | Gehen stempeln |
| ANY | /admin/v1/hr/leave | Urlaubsliste |
| POST | /admin/v1/hr/leave | Urlaubsantrag einreichen |
| GET | /admin/v1/hr/leave/{id} | Urlaubsdetails |
| PUT | /admin/v1/hr/leave/{id} | Urlaub aktualisieren |
| DELETE | /admin/v1/hr/leave/{id} | Urlaub löschen |
| POST | /admin/v1/hr/leave/{id}/approve | 🔗 Urlaub genehmigen |
| GET | /admin/v1/hr/salary | Gehaltsliste |
| POST | /admin/v1/hr/salary | Gehaltsabrechnung erzeugen |
| PUT | /admin/v1/hr/salary/{id} | Gehalt aktualisieren |
| DELETE | /admin/v1/hr/salary/{id} | Gehalt löschen |
| POST | /admin/v1/hr/salary/{id}/pay | Gehalt auszahlen |
| ANY | /admin/v1/hr/salary-item | Liste der Gehaltsbestandteile |
| POST | /admin/v1/hr/salary-item | Gehaltsbestandteil erstellen |
| GET | /admin/v1/hr/salary-item/{id} | Details des Gehaltsbestandteils |
| PUT | /admin/v1/hr/salary-item/{id} | Gehaltsbestandteil aktualisieren |
| DELETE | /admin/v1/hr/salary-item/{id} | Gehaltsbestandteil löschen |

### 16.11 Produktion (Manufacturing)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/mfg/bom | BOM-Liste |
| POST | /admin/v1/mfg/bom | BOM erstellen |
| PUT | /admin/v1/mfg/bom/{id} | BOM aktualisieren |
| DELETE | /admin/v1/mfg/bom/{id} | BOM löschen |
| GET | /admin/v1/mfg/production | Liste der Produktionsaufträge |
| POST | /admin/v1/mfg/production | Produktionsauftrag erstellen |
| PUT | /admin/v1/mfg/production/{id} | Produktionsauftrag aktualisieren |
| DELETE | /admin/v1/mfg/production/{id} | Produktionsauftrag löschen |
| POST | /admin/v1/mfg/production/{id}/start | Produktion starten |
| POST | /admin/v1/mfg/production/{id}/complete | Produktion abschließen |
| GET | /admin/v1/mfg/routing | Liste der Arbeitspläne |
| POST | /admin/v1/mfg/routing | Arbeitsplan erstellen |
| PUT | /admin/v1/mfg/routing/{id} | Arbeitsplan aktualisieren |
| DELETE | /admin/v1/mfg/routing/{id} | Arbeitsplan löschen |
| GET | /admin/v1/mfg/workstation | Arbeitsplatzliste |
| POST | /admin/v1/mfg/workstation | Arbeitsplatz erstellen |
| PUT | /admin/v1/mfg/workstation/{id} | Arbeitsplatz aktualisieren |
| DELETE | /admin/v1/mfg/workstation/{id} | Arbeitsplatz löschen |
| GET | /admin/v1/mfg/mrp | MRP-Planliste |
| POST | /admin/v1/mfg/mrp | MRP-Plan erstellen |
| PUT | /admin/v1/mfg/mrp/{id} | MRP-Plan aktualisieren |
| DELETE | /admin/v1/mfg/mrp/{id} | MRP-Plan löschen |
| POST | /admin/v1/mfg/mrp/{id}/generate | 🔗 MRP ausführen, Einkaufs-/Produktionsvorschläge erzeugen |

### 16.12 Benutzerdefinierte Berichte (Report Builder)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/report | Liste der Berichtsvorlagen |
| POST | /admin/v1/report | Berichtsvorlage erstellen |
| GET | /admin/v1/report/{id} | Details der Berichtsvorlage |
| PUT | /admin/v1/report/{id} | Berichtsvorlage aktualisieren |
| DELETE | /admin/v1/report/{id} | Berichtsvorlage löschen |
| POST | /admin/v1/report/{id}/execute | Bericht ausführen, Daten erzeugen |
| ANY | /admin/v1/report/{id}/result | Berichtsergebnis |
| GET | /admin/v1/report/schedule | Liste der Zeitpläne |
| POST | /admin/v1/report/schedule | Zeitplan erstellen |
| PUT | /admin/v1/report/schedule/{id} | Zeitplan aktualisieren |
| DELETE | /admin/v1/report/schedule/{id} | Zeitplan löschen |

### 16.13 Dashboard

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/dashboard/sales | Vertriebs-Dashboard |
| GET | /admin/v1/dashboard/inventory | Bestands-Dashboard |
| GET | /admin/v1/dashboard/finance | Finanz-Dashboard |

### 16.14 Client-API (Client API)

Client-Schnittstellen hängen unter der Gruppe `/api/v1` (die Versionsnummer geht in den URL-Pfad ein, kein Versions-Request-Header). Artikelinformationen enthalten keinen Einkaufspreis.

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /api/v1/product | Artikelliste (ohne Einkaufspreis) |
| GET | /api/v1/product/{hashid} | Artikeldetails (mit Einzel-/Großhandelspreis, ohne Einkaufspreis) |

### 16.15 OMS-Auftragsverwaltung

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/oms/order | OMS-Auftragsliste |
| POST | /admin/v1/oms/order | OMS-Auftrag erstellen |
| 🔗 POST | /admin/v1/oms/order/{id}/allocate | Bestandszuordnung (Reservierung) |
| 🔗 POST | /admin/v1/oms/order/{id}/fulfill | Fulfillment erstellen |
| POST | /admin/v1/oms/order/{id}/cancel | Auftrag stornieren (Reservierung freigeben) |
| POST | /admin/v1/oms/rma/{id}/approve | RMA genehmigen |
| POST | /admin/v1/oms/rma/{id}/refund | RMA-Erstattung |

### 16.16 WMS-Lagerverwaltung

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/wms/zone | Zonenliste (CRUD) |
| GET | /admin/v1/wms/location | WMS-Lagerplatzliste (CRUD) |
| GET | /admin/v1/wms/asn | ASN-Liste (CRUD) |
| POST | /admin/v1/wms/receiving/{id}/complete | Wareneingang abschließen → automatisch Einlagerungsaufgaben erzeugen |
| POST | /admin/v1/wms/putaway/{id}/complete | Einlagerung bestätigen → stockIn auslösen |
| POST | /admin/v1/wms/wave/{id}/release | Welle freigeben → Kommissionieraufgaben erzeugen |
| POST | /admin/v1/wms/pick/{id}/start | Kommissionierung starten |
| POST | /admin/v1/wms/pick/{id}/confirm | Kommissionierung bestätigen |
| POST | /admin/v1/wms/pack/{id}/complete | Verpackung abgeschlossen |

### 16.17 TMS-Transportverwaltung

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/tms/carrier | Spediteurliste (CRUD) |
| GET | /admin/v1/tms/service | Spediteur-Dienste (CRUD) |
| GET | /admin/v1/tms/freight-rate | Frachttarife (CRUD) |
| GET | /admin/v1/tms/shipment | Frachtscheinliste (CRUD) |
| 🔗 POST | /admin/v1/tms/shipment/{id}/ship | Versand bestätigen (stockOut+AR) |
| POST | /api/tms/tracking/callback | Spediteur-Tracking-Webhook |
| POST | /admin/v1/tms/freight-invoice/{id}/pay | Frachtrechnung bezahlen (AP erzeugen) |

### 16.18 Dashboard-Erweiterungen

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/dashboard/oms | OMS-KPIs (ausstehend/Kommissionierung/heutige Sendungen/RMA) |
| GET | /admin/v1/dashboard/wms | WMS-KPIs (ausstehender Eingang/ausstehende Einlagerung/ausstehende Kommissionierung/ausstehende Verpackung) |
| GET | /admin/v1/dashboard/tms | TMS-KPIs (ausstehender Versand/unterwegs/zugestellt/Abweichungen) |

### 16.19 Hinweise zur modulübergreifenden Verknüpfung

Folgende Endpunkte lösen automatische modulübergreifende Verknüpfungen aus und sind mit 🔗 markiert:

| Endpunkt | Verknüpfungsaktion |
|------|---------|
| 🔗 POST /admin/v1/purchase/receive | Ruft automatisch InventoryService.stockIn() auf, aktualisiert den Bestand und berechnet die gleitenden Durchschnittskosten neu; ruft FinanceService.createAp() auf, um Verbindlichkeiten zu erzeugen |
| 🔗 POST /admin/v1/sales/delivery | Ruft automatisch InventoryService.stockOut() auf, reduziert den Bestand (zu gleitenden Durchschnittskosten); ruft FinanceService.createAr() auf, um Forderungen zu erzeugen |
